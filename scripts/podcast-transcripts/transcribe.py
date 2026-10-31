#!/usr/bin/env python3
"""Pipeline batch que produce las transcripciones del podcast y las enlaza.

El proceso corre **fuera** del runtime del sitio (necesita ``whisper`` y GPU): descarga
el audio remoto de cada episodio pendiente, lo transcribe a SubRip, valida el SRT y lo
sube por lote a producción, donde se crea el adjunto y se escribe su ID en la meta
``transcript-srt``.

Solo se usa la biblioteca estándar de Python 3: el repositorio no tiene gestor de
paquetes. Los binarios externos (``whisper``, ``ffmpeg``, ``docker``, ``ssh``) se
invocan con ``subprocess``.

El módulo no tiene efectos secundarios al importarse: la recolección, la descarga, la
transcripción y la subida solo ocurren desde :func:`main`.
"""

from __future__ import annotations

import argparse
import http.client
import json
import math
import re
import shutil
import subprocess
import sys
import urllib.error
import urllib.parse
import urllib.request
from collections.abc import Iterable, Mapping, Sequence
from pathlib import Path
from typing import Any

# ---------------------------------------------------------------------------
# Constantes de entorno (valores por defecto sensatos, sobreescribibles por CLI)
# ---------------------------------------------------------------------------

SCRIPTS_DIR = Path(__file__).resolve().parent

DEFAULT_WORK_DIR = "/tmp/opencode/podcast-transcripts"
DEFAULT_MODEL = "turbo"
DEFAULT_LANGUAGE = "es"
DEFAULT_SSH_HOST = "atareao"
DEFAULT_WPCLI_CONTAINER = "wpcli"

#: Directorio temporal dentro del contenedor de WP-CLI.
CONTAINER_TMP_DIR = "/tmp"

#: Ruta (host remoto == contenedor) donde se copia el lote de SRT de una vez.
STAGE_DIR = "/tmp/opencode/podcast-transcripts-staging"

#: ``User-Agent`` usado al descargar el audio (la CDN lo ignora, pero conviene enviar uno).
DOWNLOAD_USER_AGENT = "atareao-podcast-transcripts/1.0 (+https://atareao.es)"

#: Timeout de la descarga de audio, en segundos.
DOWNLOAD_TIMEOUT = 120

#: Marcador con el que se contabiliza un registro cuyo ``id`` no es utilizable.
UNKNOWN_EPISODE_ID = -1

#: Línea de tiempos SubRip: ``HH:MM:SS,mmm --> HH:MM:SS,mmm`` (horas >= 2 dígitos,
#: minutos y segundos siempre por debajo de 60).
TIMING_RE = re.compile(
    r"^(\d{2,}):([0-5]\d):([0-5]\d),(\d{3}) --> "
    r"(\d{2,}):([0-5]\d):([0-5]\d),(\d{3})$"
)


# ---------------------------------------------------------------------------
# Excepciones
# ---------------------------------------------------------------------------


class TranscribeError(Exception):
    """Error base del pipeline."""


class SrtError(TranscribeError):
    """El texto SubRip está mal formado."""


class PipelineError(TranscribeError):
    """Un paso operativo del pipeline falló (recolección, descarga, subida...)."""


# ---------------------------------------------------------------------------
# Contrato puro: SRT (escritura, validación y formato de tiempos)
# ---------------------------------------------------------------------------


def format_timestamp(seconds: float) -> str:
    """Formatea ``seconds`` como ``HH:MM:SS,mmm`` (coma decimal).

    Las horas llevan dos dígitos como mínimo pero pueden superarlos
    (``360000.0`` -> ``"100:00:00,000"``). El milisegundo se obtiene redondeando el
    producto a entero, lo que absorbe el error de representación flotante.
    """
    if not math.isfinite(seconds):
        raise SrtError(f"timestamp no finito: {seconds!r}")

    total_ms = int(round(seconds * 1000))
    if total_ms < 0:
        raise SrtError(f"timestamp negativo: {seconds!r}")

    millis = total_ms % 1000
    total_seconds = total_ms // 1000
    secs = total_seconds % 60
    total_minutes = total_seconds // 60
    mins = total_minutes % 60
    hours = total_minutes // 60

    return f"{hours:02d}:{mins:02d}:{secs:02d},{millis:03d}"


def _parse_timestamp(hours: str, minutes: str, secs: str, millis: str) -> int:
    """Convierte una marca de tiempos ya tokenizada a milisegundos."""
    return ((int(hours) * 60 + int(minutes)) * 60 + int(secs)) * 1000 + int(millis)


def render_srt(cues: Iterable[tuple[float, float, str]]) -> str:
    """Renderiza ``cues`` (``(start, end, text)``) como SubRip válido.

    Los bloques van numerados desde 1 y separados por una línea en blanco. La salida
    termina en salto de línea; un SRT vacío produce cadena vacía.
    """
    blocks: list[str] = []
    for index, (start, end, text) in enumerate(cues, start=1):
        blocks.append(
            f"{index}\n{format_timestamp(start)} --> {format_timestamp(end)}\n{text}"
        )
    if not blocks:
        return ""

    return "\n\n".join(blocks) + "\n"


def write_srt(path: str | Path, cues: Iterable[tuple[float, float, str]]) -> None:
    """Escribe ``cues`` como SubRip UTF-8 en ``path``."""
    Path(path).write_text(render_srt(cues), encoding="utf-8")


def validate_srt(text: str) -> list[tuple[int, int, int, str]]:
    """Valida ``text`` como SubRip y devuelve ``(index, start, end, text)``.

    ``start`` y ``end`` se devuelven en milisegundos. Lanza :class:`SrtError` si la
    numeración no es consecutiva desde 1, si una marca de tiempos está mal formada, si
    un bloque empieza antes de que terminara el anterior (se exige ``>=``), si un
    bloque termina antes de empezar o si un bloque no tiene texto.
    """
    normalized = text.replace("\r\n", "\n").replace("\r", "\n")
    normalized = normalized.lstrip("\ufeff")
    body = normalized.strip("\n")
    if not body.strip():
        return []

    cues: list[tuple[int, int, int, str]] = []
    previous_end: int | None = None
    expected = 1

    for block in re.split(r"\n[ \t]*\n", body):
        lines = block.split("\n")
        if len(lines) < 3:
            raise SrtError(f"bloque {expected} sin texto")

        index_field = lines[0].strip()
        if not index_field.isdigit() or int(index_field) != expected:
            raise SrtError(
                f"numeración no consecutiva: se esperaba {expected}, hay {index_field!r}"
            )

        match = TIMING_RE.match(lines[1].strip())
        if match is None:
            raise SrtError(
                f"línea de tiempos mal formada en el bloque {expected}: {lines[1]!r}"
            )
        groups = match.groups()
        start = _parse_timestamp(*groups[:4])
        end = _parse_timestamp(*groups[4:])

        if start > end:
            raise SrtError(f"el bloque {expected} termina antes de empezar")
        if previous_end is not None and start < previous_end:
            raise SrtError(
                f"el bloque {expected} empieza antes de que terminara el anterior"
            )

        content = "\n".join(lines[2:])
        if not content.strip():
            raise SrtError(f"bloque {expected} sin texto")

        cues.append((expected, start, end, content))
        previous_end = end
        expected += 1

    return cues


# ---------------------------------------------------------------------------
# Contrato puro: planificación, idempotencia y guarda de URL
# ---------------------------------------------------------------------------


def is_usable_audio_url(url: str) -> bool:
    """Devuelve ``True`` solo si ``url`` es ``http(s)`` y tiene host.

    ``"https://"`` no tiene host y por tanto no es utilizable. Se rechaza cualquier
    URL con espacios en blanco o caracteres de control (``\\x00``-``\\x1f``, ``\\x7f``),
    que ``urlopen`` no sabe manejar.
    """
    if not isinstance(url, str):
        return False

    candidate = url.strip()
    if any(
        char.isspace() or ord(char) < 0x20 or ord(char) == 0x7F for char in candidate
    ):
        return False

    parts = urllib.parse.urlsplit(candidate)
    return parts.scheme in ("http", "https") and parts.hostname is not None


def classify(record: Mapping[str, Any]) -> str:
    """Clasifica un registro como ``done``, ``no_audio`` o ``pending``.

    ``done`` tiene prioridad sobre ``no_audio``: un episodio ya transcrito se salta
    aunque su URL no sea utilizable.
    """
    transcript_id = record.get("transcript_id", 0)
    if (
        isinstance(transcript_id, int)
        and not isinstance(transcript_id, bool)
        and transcript_id > 0
    ):
        return "done"

    if not is_usable_audio_url(record.get("mp3_url", "")):
        return "no_audio"

    return "pending"


def _episode_id(record: Mapping[str, Any]) -> int | None:
    """Devuelve el ``id`` numérico del registro, o ``None`` si no es utilizable."""
    try:
        return int(record["id"])
    except (KeyError, TypeError, ValueError):
        return None


def select_episodes(records: Iterable[Mapping[str, Any]]) -> list[Mapping[str, Any]]:
    """Conserva solo los podcasts publicados, manteniendo el orden de entrada."""
    return [
        record
        for record in records
        if record.get("post_type") == "podcast" and record.get("status") == "publish"
    ]


def plan_transcript_job(
    records: Iterable[Mapping[str, Any]],
    limit: int | None = None,
    dry_run: bool = False,
) -> dict[str, Any]:
    """Planifica el lote y devuelve un dict con exactamente cuatro claves.

    ``to_process`` es la lista ya filtrada (y truncada a ``limit``), con los
    contadores calculados sobre el conjunto completo seleccionado. ``writes_allowed``
    es ``not dry_run``.
    """
    selected = select_episodes(records)
    skipped_done = 0
    skipped_no_audio = 0
    to_process: list[Mapping[str, Any]] = []

    for record in selected:
        kind = classify(record)
        if kind == "done":
            skipped_done += 1
        elif kind == "no_audio":
            skipped_no_audio += 1
        else:
            to_process.append(record)

    if limit is not None:
        to_process = to_process[: max(limit, 0)]

    return {
        "to_process": to_process,
        "skipped_done": skipped_done,
        "skipped_no_audio": skipped_no_audio,
        "writes_allowed": not dry_run,
    }


# ---------------------------------------------------------------------------
# Utilidades de proceso
# ---------------------------------------------------------------------------


def _text(raw: bytes | None) -> str:
    """Decodifica la salida de un proceso sin abortar por bytes inválidos."""
    if not raw:
        return ""

    return raw.decode("utf-8", errors="replace")


def run_command(
    args: Sequence[str],
    *,
    input_bytes: bytes | None = None,
    timeout: int | None = None,
) -> subprocess.CompletedProcess[bytes]:
    """Ejecuta ``args`` capturando salida; traduce fallos de lanzamiento a error claro."""
    try:
        return subprocess.run(
            list(args),
            input=input_bytes,
            capture_output=True,
            timeout=timeout,
        )
    except FileNotFoundError as exc:
        raise PipelineError(f"ejecutable no encontrado: {args[0]}") from exc
    except subprocess.TimeoutExpired as exc:
        raise PipelineError(f"el comando agotó el tiempo: {args[0]}") from exc


def _run_ssh(
    ssh_host: str,
    remote_command: str,
    *,
    input_bytes: bytes | None = None,
    check: bool = False,
) -> subprocess.CompletedProcess[bytes]:
    """Ejecuta ``remote_command`` en ``ssh_host`` (shell remota: fish)."""
    proc = run_command(["ssh", ssh_host, remote_command], input_bytes=input_bytes)
    if check and proc.returncode != 0:
        raise PipelineError(
            f"comando remoto falló ({remote_command}): {_text(proc.stderr).strip()}"
        )

    return proc


def _extract_balanced(text: str, open_char: str, close_char: str) -> str:
    """Extrae el primer fragmento JSON balanceado de ``text`` (ignorando cadenas)."""
    start = text.find(open_char)
    if start < 0:
        raise PipelineError("la salida remota no contiene JSON")

    depth = 0
    in_string = False
    escaped = False
    for position in range(start, len(text)):
        char = text[position]
        if in_string:
            if escaped:
                escaped = False
            elif char == "\\":
                escaped = True
            elif char == '"':
                in_string = False
            continue
        if char == '"':
            in_string = True
        elif char == open_char:
            depth += 1
        elif char == close_char:
            depth -= 1
            if depth == 0:
                return text[start : position + 1]

    raise PipelineError("la salida remota contiene JSON desbalanceado")


def _parse_json_array(text: str) -> list[Any]:
    """Extrae y parsea el array JSON de ``text``."""
    payload = json.loads(_extract_balanced(text, "[", "]"))
    if not isinstance(payload, list):
        raise PipelineError("se esperaba un array JSON")

    return payload


def _parse_json_object(text: str) -> dict[str, Any]:
    """Extrae y parsea el objeto JSON de ``text``."""
    payload = json.loads(_extract_balanced(text, "{", "}"))
    if not isinstance(payload, dict):
        raise PipelineError("se esperaba un objeto JSON")

    return payload


# ---------------------------------------------------------------------------
# Pasos operativos
# ---------------------------------------------------------------------------


def _push_bytes_to_container(
    ssh_host: str, container: str, container_path: str, data: bytes
) -> None:
    """Copia ``data`` a ``container_path`` dentro del contenedor (una sola invocación)."""
    remote = f"docker exec -i {container} sh -lc 'cat > {container_path}'"
    proc = _run_ssh(ssh_host, remote, input_bytes=data)
    if proc.returncode != 0:
        raise PipelineError(
            f"no se pudo copiar {container_path}: {_text(proc.stderr).strip()}"
        )


def collect_episodes(
    ssh_host: str, container: str, scripts_dir: Path
) -> list[dict[str, Any]]:
    """Recolecta desde producción los podcasts publicados como registros JSON.

    Sube ``wp-collect.php`` al contenedor y lo ejecuta con ``wp eval-file``. El helper
    imprime solo JSON; aquí se tolera cualquier ruido previo extrayendo el array.
    """
    local_php = scripts_dir / "wp-collect.php"
    if not local_php.is_file():
        raise PipelineError(f"falta el helper PHP: {local_php}")

    remote_php = f"{CONTAINER_TMP_DIR}/wp-collect.php"
    _push_bytes_to_container(ssh_host, container, remote_php, local_php.read_bytes())

    proc = _run_ssh(
        ssh_host, f"docker exec {container} wp eval-file {remote_php}", check=True
    )

    try:
        records = _parse_json_array(_text(proc.stdout))
    except (json.JSONDecodeError, PipelineError) as exc:
        raise PipelineError(f"la recolección no devolvió JSON válido: {exc}") from exc

    return [record for record in records if isinstance(record, dict)]


def download_audio(record: Mapping[str, Any], audio_dir: Path) -> Path:
    """Descarga el MP3 de ``record`` a ``audio_dir/<id>.mp3`` siguiendo redirecciones."""
    episode_id = int(record["id"])
    url = str(record.get("mp3_url", ""))
    if not is_usable_audio_url(url):
        raise PipelineError(
            f"episodio {episode_id}: URL de audio no utilizable: {url!r}"
        )

    audio_dir.mkdir(parents=True, exist_ok=True)
    destination = audio_dir / f"{episode_id}.mp3"
    request = urllib.request.Request(url, headers={"User-Agent": DOWNLOAD_USER_AGENT})

    try:
        with urllib.request.urlopen(request, timeout=DOWNLOAD_TIMEOUT) as response:
            with destination.open("wb") as handle:
                shutil.copyfileobj(response, handle)
    except (urllib.error.URLError, OSError, http.client.HTTPException) as exc:
        destination.unlink(missing_ok=True)
        raise PipelineError(
            f"episodio {episode_id}: fallo al descargar: {exc}"
        ) from exc

    if destination.stat().st_size == 0:
        destination.unlink(missing_ok=True)
        raise PipelineError(f"episodio {episode_id}: la descarga quedó vacía")

    return destination


def transcribe_audio(
    record: Mapping[str, Any],
    audio_path: Path,
    srt_dir: Path,
    model: str,
    language: str,
) -> Path:
    """Transcribe ``audio_path`` con ``whisper`` y devuelve el ``.srt`` resultante.

    Si el SRT ya existe en ``srt_dir`` no se vuelve a transcribir (reanudación).
    """
    episode_id = int(record["id"])
    srt_path = srt_dir / f"{episode_id}.srt"
    if srt_path.is_file():
        return srt_path

    srt_dir.mkdir(parents=True, exist_ok=True)
    command = [
        "whisper",
        "--model",
        model,
        "--language",
        language,
        "--output_format",
        "srt",
        "--output_dir",
        str(srt_dir),
        str(audio_path),
    ]
    proc = run_command(command)
    if proc.returncode != 0 or not srt_path.is_file():
        detail = _text(proc.stderr).strip().splitlines()
        tail = detail[-1] if detail else "sin detalle"
        raise PipelineError(f"episodio {episode_id}: fallo al transcribir: {tail}")

    return srt_path


def _ensure_valid_srt(srt_path: Path, episode_id: int) -> None:
    """Comprueba que el SRT existe, es legible y válido; si no, lanza error claro."""
    try:
        text = srt_path.read_text(encoding="utf-8")
    except (OSError, UnicodeError) as exc:
        raise PipelineError(f"episodio {episode_id}: SRT ilegible: {exc}") from exc

    try:
        blocks = validate_srt(text)
    except SrtError as exc:
        raise PipelineError(f"episodio {episode_id}: SRT inválido: {exc}") from exc

    if not blocks:
        raise PipelineError(f"episodio {episode_id}: el SRT no tiene bloques")


def _push_directory(
    local_dir: Path,
    host_dir: str,
    container_dir: str,
    ssh_host: str,
    container: str,
) -> None:
    """Envía ``local_dir`` completa al host remoto y la copia al contenedor.

    Se empaqueta con ``tar`` y se extrae al otro lado para no hacer una invocación por
    fichero; después un único ``docker cp`` deja el lote dentro del contenedor.
    """
    tar_proc = subprocess.run(
        ["tar", "-C", str(local_dir), "-cf", "-", "."], capture_output=True
    )
    if tar_proc.returncode != 0:
        raise PipelineError(f"tar falló: {_text(tar_proc.stderr).strip()}")

    _run_ssh(
        ssh_host,
        f"mkdir -p {host_dir} && tar -C {host_dir} -xf -",
        input_bytes=tar_proc.stdout,
        check=True,
    )
    _run_ssh(ssh_host, f"docker exec {container} mkdir -p {container_dir}", check=True)
    _run_ssh(
        ssh_host, f"docker cp {host_dir}/. {container}:{container_dir}", check=True
    )


def upload_transcripts(
    entries: Mapping[int, Path],
    *,
    ssh_host: str,
    container: str,
    staging_dir: Path,
    scripts_dir: Path,
) -> dict[str, Any]:
    """Sube el lote de SRT y devuelve el JSON de resultado del helper PHP.

    Un único ``docker cp`` del directorio preparado y **una sola** pasada de
    ``wp eval-file`` resuelven todos los adjuntos y metas.
    """
    safe_staging = Path(staging_dir)
    if safe_staging.exists():
        shutil.rmtree(safe_staging)
    safe_staging.mkdir(parents=True, exist_ok=True)
    for episode_id, srt_path in entries.items():
        shutil.copy2(srt_path, safe_staging / f"{episode_id}.srt")

    manifest = {
        str(episode_id): f"{STAGE_DIR}/{episode_id}.srt" for episode_id in entries
    }
    (safe_staging / "manifest.json").write_text(
        json.dumps(manifest, ensure_ascii=False), encoding="utf-8"
    )

    attach_php = scripts_dir / "wp-attach.php"
    if not attach_php.is_file():
        raise PipelineError(f"falta el helper PHP: {attach_php}")
    shutil.copy2(attach_php, safe_staging / "wp-attach.php")

    _push_directory(safe_staging, STAGE_DIR, STAGE_DIR, ssh_host, container)

    command = (
        f"docker exec {container} wp eval-file "
        f"{STAGE_DIR}/wp-attach.php {STAGE_DIR}/manifest.json"
    )
    proc = _run_ssh(ssh_host, command, check=True)

    try:
        return _parse_json_object(_text(proc.stdout))
    except (json.JSONDecodeError, PipelineError) as exc:
        raise PipelineError(f"la subida no devolvió JSON válido: {exc}") from exc


# ---------------------------------------------------------------------------
# Orquestación y CLI
# ---------------------------------------------------------------------------


def parse_args(argv: Sequence[str] | None = None) -> argparse.Namespace:
    """Define y parsea los argumentos de la línea de comandos."""
    parser = argparse.ArgumentParser(
        description=(
            "Produce las transcripciones SubRip de los episodios del podcast y las "
            "enlaza con la meta `transcript-srt` de producción."
        )
    )
    parser.add_argument(
        "--limit", type=int, default=None, help="procesar como máximo N episodios"
    )
    parser.add_argument(
        "--dry-run",
        action="store_true",
        help="planificar sin escribir, descargar ni transcribir nada",
    )
    parser.add_argument(
        "--episode",
        type=int,
        action="append",
        default=None,
        metavar="ID",
        help="procesar solo el episodio indicado (repetible)",
    )
    parser.add_argument("--model", default=DEFAULT_MODEL, help="modelo de Whisper")
    parser.add_argument("--language", default=DEFAULT_LANGUAGE, help="idioma del audio")
    parser.add_argument(
        "--work-dir", default=DEFAULT_WORK_DIR, help="directorio temporal de trabajo"
    )
    parser.add_argument(
        "--keep-audio",
        action="store_true",
        help="no borrar el audio descargado al terminar",
    )
    parser.add_argument(
        "--ssh-host", default=DEFAULT_SSH_HOST, help="host de producción vía ssh"
    )
    parser.add_argument(
        "--wpcli-container",
        default=DEFAULT_WPCLI_CONTAINER,
        help="contenedor que tiene WP-CLI",
    )
    return parser.parse_args(argv)


def run_pipeline(args: argparse.Namespace) -> int:
    """Ejecuta el pipeline completo con los argumentos ya parseados."""
    work_dir = Path(args.work_dir).expanduser()

    try:
        records = collect_episodes(args.ssh_host, args.wpcli_container, SCRIPTS_DIR)
    except PipelineError as exc:
        raise PipelineError(
            f"no se pudieron recolectar los episodios de producción: {exc}"
        ) from exc

    if args.episode:
        wanted = set(args.episode)
        records = [record for record in records if _episode_id(record) in wanted]
        found = {_episode_id(record) for record in records}
        missing = wanted - found
        if missing:
            print(
                "aviso: episodios no encontrados en producción: "
                + ", ".join(str(value) for value in sorted(missing)),
                file=sys.stderr,
            )

    plan = plan_transcript_job(records, limit=args.limit, dry_run=args.dry_run)

    if args.dry_run:
        print("== ENSAYO (--dry-run): no se escribirá ni se descargará nada ==")
        print(f"pendientes : {len(plan['to_process'])}")
        print(f"ya hechos  : {plan['skipped_done']}")
        print(f"sin audio  : {plan['skipped_no_audio']}")
        listing_header = "pendientes a procesar"
        if args.limit is not None:
            listing_header += f" (limit {args.limit})"
        print(f"{listing_header}:")
        for record in plan["to_process"]:
            label = _episode_id(record)
            shown = label if label is not None else "?"
            print(f"  - {shown}: {record.get('mp3_url', '')}")
        print("(ensayo: ninguna acción ejecutada)")
        return 0

    srt_dir = work_dir / "srt"
    audio_dir = work_dir / "audio"
    srt_dir.mkdir(parents=True, exist_ok=True)

    entries: dict[int, Path] = {}
    failed: list[int] = []

    total = len(plan["to_process"])
    for index, record in enumerate(plan["to_process"], start=1):
        srt_path: Path | None = None
        episode_id: int | None = None
        try:
            episode_id = _episode_id(record)
            if episode_id is None:
                raise ValueError("el registro no tiene un id numérico utilizable")
            prefix = f"[{index}/{total}] {episode_id}"
            srt_path = srt_dir / f"{episode_id}.srt"
            if not srt_path.is_file():
                print(f"{prefix}: descargando...", flush=True)
                audio_path = download_audio(record, audio_dir)
                try:
                    print(
                        f"{prefix}: transcribiendo con {args.model} ({args.language})...",
                        flush=True,
                    )
                    transcribe_audio(
                        record, audio_path, srt_dir, args.model, args.language
                    )
                finally:
                    if not args.keep_audio:
                        audio_path.unlink(missing_ok=True)
            _ensure_valid_srt(srt_path, episode_id)
            entries[episode_id] = srt_path
            print(f"{prefix}: ok", flush=True)
        except (KeyError, TypeError, ValueError) as exc:
            failed.append(UNKNOWN_EPISODE_ID)
            print(
                f"⚠️  [{index}/{total}] registro sin id utilizable, se omite: {exc}",
                file=sys.stderr,
            )
        except PipelineError as exc:
            failed.append(episode_id)
            srt_path.unlink(missing_ok=True)
            print(f"⚠️  episodio {episode_id}: {exc}", file=sys.stderr)

    resolved = 0
    if entries:
        try:
            results = upload_transcripts(
                entries,
                ssh_host=args.ssh_host,
                container=args.wpcli_container,
                staging_dir=work_dir / "staging",
                scripts_dir=SCRIPTS_DIR,
            )
        except PipelineError as exc:
            print(f"⚠️  la subida por lote falló: {exc}", file=sys.stderr)
            results = {}
            failed.extend(ep for ep in entries if ep not in failed)

        for episode_id in entries:
            if episode_id in failed:
                continue
            result = results.get(str(episode_id))
            if isinstance(result, Mapping) and result.get("attachment_id"):
                resolved += 1
            else:
                failed.append(episode_id)
                message = (
                    result.get("error")
                    if isinstance(result, Mapping)
                    else "sin respuesta del helper de subida"
                )
                print(f"⚠️  episodio {episode_id}: {message}", file=sys.stderr)

    if not args.keep_audio and audio_dir.exists():
        shutil.rmtree(audio_dir, ignore_errors=True)

    skipped = plan["skipped_done"] + plan["skipped_no_audio"]
    print("")
    print("== Resumen ==")
    print(f"resueltos : {resolved}")
    print(
        f"omitidos  : {skipped} "
        f"(ya hechos: {plan['skipped_done']}, sin audio: {plan['skipped_no_audio']})"
    )
    print(f"fallidos  : {len(failed)}")
    if failed:
        print("  IDs fallidos: " + ", ".join(str(value) for value in sorted(failed)))

    return 0 if not failed else 1


def main(argv: Sequence[str] | None = None) -> int:
    """Entrada CLI: traduce cualquier error del pipeline a un mensaje claro."""
    args = parse_args(argv)
    try:
        return run_pipeline(args)
    except TranscribeError as exc:
        print(f"error: {exc}", file=sys.stderr)
        return 1
    except KeyboardInterrupt:
        print("error: interrumpido por el usuario", file=sys.stderr)
        return 130


if __name__ == "__main__":
    raise SystemExit(main())
