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
import base64
import hashlib
import http.client
import json
import math
import re
import shutil
import signal
import subprocess
import sys
import urllib.error
import urllib.parse
import urllib.request
from collections.abc import Callable, Iterable, Mapping, Sequence
from dataclasses import dataclass, field
from pathlib import Path
from typing import Any, Protocol, TypeAlias

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

#: Número máximo de líneas de salida que se conservan en el motivo de un fallo.
FAILURE_DETAIL_MAX_LINES = 5

#: Línea de tiempos SubRip: ``HH:MM:SS,mmm --> HH:MM:SS,mmm`` (horas >= 2 dígitos,
#: minutos y segundos siempre por debajo de 60).
TIMING_RE = re.compile(
    r"^(\d{2,}):([0-5]\d):([0-5]\d),(\d{3}) --> "
    r"(\d{2,}):([0-5]\d):([0-5]\d),(\d{3})$"
)

#: Separador de bloques SubRip: una o más líneas en blanco (admite espacios/tabuladores).
SRT_BLOCK_SEP_RE = re.compile(r"\n(?:[ \t]*\n)+")

#: Umbral de ruido de ``ffmpeg silencedetect`` (por debajo se considera silencio).
SILENCE_NOISE_FLOOR = "-35dB"

#: Duración mínima (segundos) para que un tramo cuente como silencio.
SILENCE_MIN_DURATION = 0.5

#: Fracción audible por debajo de la cual el audio se considera *sin voz*.
NO_SPEECH_MAX_AUDIBLE_FRACTION = 0.05

#: Frecuencia de muestreo de la copia normalizada entregada al reconocedor (Hz).
PREPROCESS_SAMPLE_RATE = 16000

#: Número de canales de la copia normalizada (mono).
PREPROCESS_CHANNELS = 1

#: Códec de la copia normalizada (PCM con signo de 16 bits, little-endian).
PREPROCESS_CODEC = "pcm_s16le"

#: Frecuencia del paso alto de la copia normalizada (Hz).
PREPROCESS_HIGHPASS_HZ = 80

#: Frecuencia del paso bajo de la copia normalizada (Hz).
PREPROCESS_LOWPASS_HZ = 8000

#: Duración total que ffmpeg imprime en su salida de error: ``Duration: HH:MM:SS.ss``.
FFMPEG_DURATION_RE = re.compile(r"Duration:\s*(\d+):([0-5]\d):([0-5]\d)\.(\d+)")

#: Marcas del detector de silencios de ffmpeg (en su salida de error).
SILENCE_START_RE = re.compile(r"silence_start:\s*(-?\d+(?:\.\d+)?)")
SILENCE_END_RE = re.compile(r"silence_end:\s*(-?\d+(?:\.\d+)?)")

#: Glosario versionado de términos del programa (grafía canónica y alias).
DEFAULT_GLOSSARY_PATH = SCRIPTS_DIR / "glossary.tsv"

#: Umbral de hueco temporal (segundos) entre cues que separa fin de frase de coma.
#:
#: Un hueco mayor que este umbral se interpreta como fin de frase (``.``); uno menor
#: o igual, como continuación dentro de la misma frase (``,``). Valor por defecto
#: documentado y ajustable con ``--glossary``/código, no por CLI todavía.
SENTENCE_GAP_SECONDS = 0.5

#: Nombre del manifiesto local del backfill (con los ``sha256`` de cada episodio).
BACKFILL_MANIFEST_NAME = "backfill-manifest.json"

#: Nombre del helper PHP que lee/reemplaza/restaura el SRT del adjunto en producción.
BACKFILL_HELPER_NAME = "wp-backfill.php"


# ---------------------------------------------------------------------------
# Excepciones
# ---------------------------------------------------------------------------


class TranscribeError(Exception):
    """Error base del pipeline."""


class SrtError(TranscribeError):
    """El texto SubRip está mal formado."""


class PipelineError(TranscribeError):
    """Un paso operativo del pipeline falló (recolección, descarga, subida...)."""


class NoSpeechError(TranscribeError):
    """El audio no tiene voz transcribible (tras el saneo no queda ningún texto).

    No es un fallo del pipeline: el comando de transcripción pudo terminar bien, pero
    el reconocedor no produjo ningún bloque con texto. Se contabiliza como *omitido*
    con motivo ``sin voz``, no como fallido.
    """


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
# Contrato puro: post-proceso del texto (puntuación, mayúsculas y glosario)
# ---------------------------------------------------------------------------

#: Un ``cue`` es ``(inicio, fin, texto)``; los tiempos van en segundos.
Cue = tuple[float, float, str]

#: Patrón de una «palabra» para comparar secuencias: letras/dígitos, sin guiones.
_WORD_RE = re.compile(r"[^\W_]+", re.UNICODE)

#: Primera letra de un texto (para la mayúscula inicial).
_LETTER_RE = re.compile(r"[^\W\d_]", re.UNICODE)

#: Signos finales que el motor normaliza antes de reponer el suyo.
_TRAILING_PUNCT = ".,;:!?…"


@dataclass(frozen=True)
class GlossaryEntry:
    """Una entrada del glosario: ``canonical`` (grafía) o ``alias`` (sustitución)."""

    kind: str
    match: str
    replacement: str


@dataclass(frozen=True)
class Glossary:
    """Glosario compilado: aplica sus reglas de forma determinista e idempotente."""

    entries: tuple[GlossaryEntry, ...]
    rules: tuple[tuple[re.Pattern[str], str], ...]

    def apply(self, text: str) -> str:
        """Aplica todas las reglas (case-insensitive, palabra/frase completa)."""
        for pattern, replacement in self.rules:
            text = pattern.sub(lambda _match, value=replacement: value, text)
        return text


#: Un motor de post-proceso recibe las cues y el glosario y devuelve los textos.
#:
#: Contrato: misma longitud que ``cues`` y, por bloque, la **misma secuencia de
#: palabras** salvo los alias **declarados** del glosario (solo cambian puntuación y
#: mayúsculas). Un motor que viole el contrato en un bloque hace que ese bloque
#: conserve su texto original (degradación segura).
PostprocessEngine: TypeAlias = Callable[[Sequence[Cue], Glossary], Sequence[str]]


def _compile_entry(entry: GlossaryEntry) -> tuple[re.Pattern[str], str]:
    """Compila una entrada como patrón de palabra/frase completa (``\\w``-delimitada)."""
    body = r"\s+".join(re.escape(word) for word in entry.match.split())
    pattern = re.compile(rf"(?<!\w){body}(?!\w)", re.IGNORECASE | re.UNICODE)
    return pattern, entry.replacement


def load_glossary(path: str | Path) -> Glossary:
    """Carga un glosario TSV (``kind<TAB>término<TAB>grafía``) y lo compila.

    Ignora líneas vacías y comentarios (``#``). Las reglas se ordenan de la frase más
    larga a la más corta para que un alias no se coma a otro. Lanza
    :class:`PipelineError` si el fichero no se puede leer, una línea es inválida o una
    entrada ``canonical`` cambia el número de palabras (solo los alias pueden).
    """
    glossary_path = Path(path)
    try:
        raw = glossary_path.read_text(encoding="utf-8")
    except (OSError, UnicodeError) as exc:
        raise PipelineError(f"glosario ilegible {glossary_path}: {exc}") from exc

    entries: list[GlossaryEntry] = []
    for line_number, line in enumerate(raw.splitlines(), start=1):
        stripped = line.strip()
        if not stripped or stripped.startswith("#"):
            continue
        fields = line.split("\t")
        if len(fields) != 3:
            raise PipelineError(
                f"glosario {glossary_path}: línea {line_number} mal formada "
                "(se esperan 3 columnas separadas por tabulador)"
            )
        kind, match, replacement = (field.strip() for field in fields)
        if kind not in {"canonical", "alias"} or not match or not replacement:
            raise PipelineError(
                f"glosario {glossary_path}: línea {line_number} inválida: {line!r}"
            )
        if kind == "canonical" and len(match.split()) != len(replacement.split()):
            raise PipelineError(
                f"glosario {glossary_path}: línea {line_number} canónica "
                f"{match!r} -> {replacement!r} cambia el número de palabras; "
                "solo los alias declarados pueden hacerlo"
            )
        entries.append(GlossaryEntry(kind=kind, match=match, replacement=replacement))

    ordered = sorted(entries, key=lambda entry: len(entry.match), reverse=True)
    rules = tuple(_compile_entry(entry) for entry in ordered)
    return Glossary(entries=tuple(ordered), rules=rules)


def _word_tokens(text: str) -> list[str]:
    """Normaliza ``text`` a su secuencia de palabras (minúsculas, sin signos)."""
    return [match.group(0).lower() for match in _WORD_RE.finditer(text)]


def _collapse_whitespace(text: str) -> str:
    """Colapsa cualquier espacio en blanco a un único espacio y recorta extremos."""
    return re.sub(r"\s+", " ", text).strip()


def _capitalize_first_letter(text: str) -> str:
    """Pone en mayúscula la primera letra de ``text`` (sin tocar el resto)."""
    match = _LETTER_RE.search(text)
    if match is None:
        return text
    index = match.start()
    return text[:index] + text[index].upper() + text[index + 1 :]


def deterministic_engine(cues: Sequence[Cue], glossary: Glossary) -> list[str]:
    """Motor A (solo stdlib): mayúscula inicial y puntuación por hueco temporal.

    Aplica el glosario, normaliza los espacios y repone al final de cada bloque una
    coma (si el hueco hasta la cue siguiente es corto) o un punto (hueco largo o
    último bloque). No inventa ``¿?¡!``.
    """
    texts: list[str] = []
    total = len(cues)
    for index, (_start, end, text) in enumerate(cues):
        cleaned = _collapse_whitespace(glossary.apply(text)).rstrip(_TRAILING_PUNCT)
        cleaned = cleaned.rstrip()
        if not cleaned:
            texts.append(text)
            continue
        if index + 1 < total:
            gap = cues[index + 1][0] - end
            ending = "." if gap > SENTENCE_GAP_SECONDS else ","
        else:
            ending = "."
        texts.append(_capitalize_first_letter(cleaned) + ending)
    return texts


def postprocess_cues(
    cues: Iterable[Cue],
    glossary: Glossary,
    *,
    engine: PostprocessEngine | None = None,
) -> list[Cue]:
    """Post-procesa ``cues`` con ``engine`` (por defecto, el determinista).

    Conserva exactamente los timecodes y el número de bloques. Por bloque, si el
    motor cambia la secuencia de palabras más allá de los alias declarados (o devuelve
    un número de bloques distinto), ese bloque —o todos— conserva su texto original.
    """
    cue_list = list(cues)
    if not cue_list:
        return []

    runner = engine or deterministic_engine
    proposed = list(runner(cue_list, glossary))
    if len(proposed) != len(cue_list):
        return cue_list

    result: list[Cue] = []
    for (start, end, text), candidate in zip(cue_list, proposed):
        normalized = _collapse_whitespace(str(candidate))
        expected = _word_tokens(glossary.apply(text))
        if normalized and _word_tokens(normalized) == expected:
            result.append((start, end, normalized))
        else:
            result.append((start, end, text))
    return result


def postprocess_srt(
    text: str, glossary: Glossary, *, engine: PostprocessEngine | None = None
) -> str:
    """Devuelve el SRT ``text`` post-procesado, conservando bloques y timecodes."""
    blocks = validate_srt(text)
    cues: list[Cue] = [
        (start / 1000, end / 1000, content) for _index, start, end, content in blocks
    ]
    return render_srt(postprocess_cues(cues, glossary, engine=engine))


def postprocess_srt_file(
    path: str | Path, glossary: Glossary, *, engine: PostprocessEngine | None = None
) -> bool:
    """Post-procesa ``path`` en el sitio; devuelve si el fichero cambió.

    Es idempotente: una segunda pasada sobre un SRT ya post-procesado no reescribe el
    fichero y devuelve ``False``.
    """
    srt_path = Path(path)
    try:
        original = srt_path.read_text(encoding="utf-8")
    except (OSError, UnicodeError) as exc:
        raise PipelineError(f"SRT ilegible {srt_path}: {exc}") from exc

    try:
        processed = postprocess_srt(original, glossary, engine=engine)
    except SrtError as exc:
        raise PipelineError(f"SRT inválido {srt_path}: {exc}") from exc

    if processed == original:
        return False
    srt_path.write_text(processed, encoding="utf-8")
    return True


def _count_changing_blocks(srt_path: Path, glossary: Glossary) -> int | None:
    """Bloques de ``srt_path`` que el post-proceso cambiaría.

    Devuelve ``None`` si el fichero no existe, no es legible o no es un SRT válido
    (en un ensayo eso no impide informar del resto del plan).
    """
    try:
        original = srt_path.read_text(encoding="utf-8")
    except (OSError, UnicodeError):
        return None
    try:
        before = validate_srt(original)
        after = validate_srt(postprocess_srt(original, glossary))
    except SrtError:
        return None
    return sum(1 for old, new in zip(before, after) if old[3] != new[3])


# ---------------------------------------------------------------------------
# Contrato puro: medición de audio (fracción audible)
# ---------------------------------------------------------------------------


def _audible_fraction(
    duration: float,
    silence_intervals: Iterable[tuple[float, float | None]],
) -> float:
    """Fracción audible del audio: ``(duración − silencio_total) / duración``.

    Cada intervalo es ``(inicio, fin)`` en segundos; un fin ``None`` representa un
    silencio abierto que llega hasta ``duration``. Los intervalos se recortan a
    ``[0, duration]``. Una duración no positiva se trata como totalmente silenciosa.
    """
    if duration <= 0:
        return 0.0

    silent = 0.0
    for start, end in silence_intervals:
        resolved_end = duration if end is None else end
        start_clamped = min(max(start, 0.0), duration)
        end_clamped = min(max(resolved_end, 0.0), duration)
        if end_clamped > start_clamped:
            silent += end_clamped - start_clamped

    return max(0.0, duration - silent) / duration


def _parse_silencedetect(raw: str) -> tuple[float, list[tuple[float, float | None]]]:
    """Extrae ``(duración, intervalos de silencio)`` de la salida de ffmpeg.

    La duración procede de la línea ``Duration: HH:MM:SS.ss``. Un ``silence_start`` sin
    ``silence_end`` (silencio abierto al final) se devuelve con fin ``None``. Lanza
    :class:`PipelineError` si no se puede leer la duración.
    """
    duration_match = FFMPEG_DURATION_RE.search(raw)
    if duration_match is None:
        raise PipelineError("no se pudo leer la duración del audio (ffmpeg)")

    hours, minutes, secs, fraction = duration_match.groups()
    duration = (
        int(hours) * 3600
        + int(minutes) * 60
        + int(secs)
        + int(fraction) / (10 ** len(fraction))
    )

    intervals: list[tuple[float, float | None]] = []
    open_start: float | None = None
    for line in raw.splitlines():
        start_match = SILENCE_START_RE.search(line)
        if start_match is not None:
            open_start = float(start_match.group(1))
            continue
        end_match = SILENCE_END_RE.search(line)
        if end_match is not None and open_start is not None:
            intervals.append((open_start, float(end_match.group(1))))
            open_start = None

    if open_start is not None:
        intervals.append((open_start, None))

    return duration, intervals


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


def _statuses_for(include_drafts: bool) -> tuple[str, ...]:
    """Estados de post permitidos según el alcance pedido (fuente única de verdad).

    Sin borradores solo ``publish``; con ``include_drafts=True``, ``publish`` y
    ``draft``. La usan tanto la selección/planificación como la recolección y la
    subida, de modo que el alcance no pueda divergir entre pasos.
    """
    return ("publish", "draft") if include_drafts else ("publish",)


def select_episodes(
    records: Iterable[Mapping[str, Any]],
    include_drafts: bool = False,
) -> list[Mapping[str, Any]]:
    """Conserva los podcasts seleccionados, manteniendo el orden de entrada.

    Por defecto solo los publicados. Con ``include_drafts=True`` también se conservan
    los borradores de tipo ``podcast``; cualquier otro tipo se descarta en ambos casos.
    """
    statuses = set(_statuses_for(include_drafts))
    return [
        record
        for record in records
        if record.get("post_type") == "podcast" and record.get("status") in statuses
    ]


def plan_transcript_job(
    records: Iterable[Mapping[str, Any]],
    limit: int | None = None,
    dry_run: bool = False,
    include_drafts: bool = False,
) -> dict[str, Any]:
    """Planifica el lote y devuelve un dict con exactamente cuatro claves.

    ``to_process`` es la lista ya filtrada (y truncada a ``limit``), con los
    contadores calculados sobre el conjunto completo seleccionado. ``writes_allowed``
    es ``not dry_run``. ``include_drafts`` amplía el alcance a los borradores.
    """
    selected = select_episodes(records, include_drafts=include_drafts)
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


def _last_non_empty_lines(raw: bytes | None, max_lines: int) -> list[str]:
    """Devuelve las últimas ``max_lines`` líneas no vacías de ``raw``."""
    if max_lines <= 0:
        return []

    lines = [line for line in _text(raw).splitlines() if line.strip()]
    return lines[-max_lines:]


def _has_output(raw: bytes | None) -> bool:
    """Devuelve ``True`` si ``raw`` tiene algún contenido no blanco."""
    return bool(_text(raw).strip())


def failure_detail(
    returncode: int,
    stderr: bytes | None,
    stdout: bytes | None,
    *,
    srt_present: bool,
    max_lines: int = FAILURE_DETAIL_MAX_LINES,
) -> str:
    """Compone el motivo utilizable de un fallo de transcripción.

    Distingue el fallo del comando del caso en que el comando termina sin error pero no
    deja el ``.srt``. El prefijo informa del código de salida (``exit code N``) o, si el
    proceso murió por una señal, de su nombre (``SIGKILL``). El detalle son las últimas
    líneas no vacías de ``stderr`` y, si éstas faltan, las de ``stdout``. Sin salida
    ninguna, se informa del código observado en lugar de inventar un detalle.
    """
    lines = _last_non_empty_lines(stderr, max_lines) or _last_non_empty_lines(
        stdout, max_lines
    )

    if returncode == 0 and not srt_present:
        base = "el comando terminó sin error pero no dejó el fichero SRT esperado"
        if lines:
            return f"{base}: " + " | ".join(lines)
        return base

    if returncode > 0:
        prefix = f"exit code {returncode}"
    elif returncode < 0:
        try:
            prefix = signal.Signals(-returncode).name
        except ValueError:
            prefix = f"señal {abs(returncode)}"
    else:
        prefix = "sin código de salida"

    if lines:
        return f"{prefix}: " + " | ".join(lines)
    if _has_output(stderr) or _has_output(stdout):
        # La salida existe, pero ``max_lines`` la omitió entera.
        return f"{prefix}: salida del comando omitida (max_lines={max_lines})"

    return f"{prefix} (sin salida del comando)"


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
    ssh_host: str,
    container: str,
    scripts_dir: Path,
    statuses: Sequence[str] = ("publish",),
) -> list[dict[str, Any]]:
    """Recolecta desde producción los podcasts de los estados pedidos como JSON.

    Sube ``wp-collect.php`` al contenedor y lo ejecuta con ``wp eval-file`` pasándole
    los estados permitidos como CSV (un solo argumento posicional). Por defecto solo
    ``publish``. El helper imprime solo JSON; aquí se tolera cualquier ruido previo
    extrayendo el array.
    """
    local_php = scripts_dir / "wp-collect.php"
    if not local_php.is_file():
        raise PipelineError(f"falta el helper PHP: {local_php}")

    remote_php = f"{CONTAINER_TMP_DIR}/wp-collect.php"
    _push_bytes_to_container(ssh_host, container, remote_php, local_php.read_bytes())

    effective_statuses = tuple(statuses) or ("publish",)
    statuses_csv = ",".join(effective_statuses)
    proc = _run_ssh(
        ssh_host,
        f"docker exec {container} wp eval-file {remote_php} {statuses_csv}",
        check=True,
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


def measure_audible_fraction(audio_path: Path) -> float:
    """Fracción audible de ``audio_path`` medida con ``ffmpeg silencedetect``.

    Un único pase de ffmpeg (el detector escribe en su salida de error). Devuelve la
    fracción ``(duración − silencio) / duración``. Lanza :class:`PipelineError` si
    ffmpeg falla o si no deja medir la duración.
    """
    command = [
        "ffmpeg",
        "-hide_banner",
        "-nostats",
        "-i",
        str(audio_path),
        "-af",
        f"silencedetect=noise={SILENCE_NOISE_FLOOR}:d={SILENCE_MIN_DURATION}",
        "-f",
        "null",
        "-",
    ]
    proc = run_command(command)
    if proc.returncode != 0:
        detail = " | ".join(
            _last_non_empty_lines(proc.stderr, FAILURE_DETAIL_MAX_LINES)
        )
        raise PipelineError(
            f"ffmpeg silencedetect falló (exit code {proc.returncode}): "
            f"{detail or 'sin salida del comando'}"
        )

    duration, intervals = _parse_silencedetect(_text(proc.stderr))
    return _audible_fraction(duration, intervals)


def preprocess_audio(audio_path: Path, out_dir: Path) -> Path:
    """Produce una copia normalizada del audio para el reconocedor.

    Copia a ``<out_dir>/<audio_path.stem>.wav`` en 16 kHz, mono, ``pcm_s16le`` y banda
    limitada (paso alto 80 Hz, paso bajo 8 kHz). El nombre base se conserva para que el
    reconocedor siga emitiendo ``<episode_id>.srt`` sin cambiar las rutas ni la
    reanudación. Devuelve la ruta de la copia. Lanza :class:`PipelineError` si ffmpeg
    falla o no deja el fichero.
    """
    out_dir.mkdir(parents=True, exist_ok=True)
    destination = out_dir / f"{audio_path.stem}.wav"
    command = [
        "ffmpeg",
        "-hide_banner",
        "-nostats",
        "-y",
        "-i",
        str(audio_path),
        "-ar",
        str(PREPROCESS_SAMPLE_RATE),
        "-ac",
        str(PREPROCESS_CHANNELS),
        "-c:a",
        PREPROCESS_CODEC,
        "-af",
        f"highpass=f={PREPROCESS_HIGHPASS_HZ},lowpass=f={PREPROCESS_LOWPASS_HZ}",
        str(destination),
    ]
    proc = run_command(command)
    if proc.returncode != 0 or not destination.is_file():
        detail = " | ".join(
            _last_non_empty_lines(proc.stderr, FAILURE_DETAIL_MAX_LINES)
        )
        raise PipelineError(
            f"el preprocesado falló (exit code {proc.returncode}): "
            f"{detail or 'sin salida del comando'}"
        )

    return destination


def transcribe_audio(
    record: Mapping[str, Any],
    audio_path: Path,
    srt_dir: Path,
    model: str,
    language: str,
    initial_prompt: str = "",
) -> Path:
    """Transcribe ``audio_path`` con ``whisper`` y devuelve el ``.srt`` resultante.

    Si el SRT ya existe en ``srt_dir`` no se vuelve a transcribir (reanudación).
    ``initial_prompt`` es un sesgo de vocabulario opcional: solo se pasa al reconocedor
    cuando no está vacío, de modo que la orden por defecto es idéntica a la de antes.
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
    ]
    if initial_prompt:
        command += ["--initial_prompt", initial_prompt]
    command.append(str(audio_path))
    proc = run_command(command)
    if proc.returncode != 0 or not srt_path.is_file():
        detail = failure_detail(
            proc.returncode,
            proc.stderr,
            proc.stdout,
            srt_present=srt_path.is_file(),
        )
        raise PipelineError(f"episodio {episode_id}: fallo al transcribir: {detail}")

    return srt_path


def _sanitize_srt(srt_path: Path) -> int:
    """Reescribe ``srt_path`` descartando los bloques sin texto y renumerando.

    El reconocedor puede emitir bloques cuya línea de texto está vacía o solo con
    espacios; son salida legítima, no un error. Se descartan y los bloques restantes
    se renumeran consecutivos desde 1, conservando **sin cambios** su texto y sus
    marcas de tiempo. Devuelve el número de bloques conservados.

    Lanza :class:`PipelineError` si el fichero no puede leerse o no está en UTF-8, y
    :class:`NoSpeechError` si tras el saneo no queda ningún bloque con texto (el audio
    no tiene voz transcribible). En ese caso no reescribe el fichero.
    """
    try:
        text = srt_path.read_text(encoding="utf-8")
    except (OSError, UnicodeError) as exc:
        raise PipelineError(f"SRT ilegible: {exc}") from exc

    normalized = text.replace("\r\n", "\n").replace("\r", "\n").lstrip("\ufeff")
    body = normalized.strip("\n")

    kept: list[str] = []
    if body.strip():
        for chunk in re.split(SRT_BLOCK_SEP_RE, body):
            lines = chunk.split("\n")
            if len(lines) < 2:
                continue
            content = "\n".join(lines[2:])
            if not content.strip():
                continue
            kept.append(f"{len(kept) + 1}\n{lines[1]}\n{content}")

    if not kept:
        raise NoSpeechError("el SRT no conserva ningún bloque con texto")

    srt_path.write_text("\n\n".join(kept) + "\n", encoding="utf-8")
    return len(kept)


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
    allowed_statuses: Sequence[str] = ("publish",),
) -> dict[str, Any]:
    """Sube el lote de SRT y devuelve el JSON de resultado del helper PHP.

    Un único ``docker cp`` del directorio preparado y **una sola** pasada de
    ``wp eval-file`` resuelven todos los adjuntos y metas. ``allowed_statuses`` se
    pasa como segundo argumento posicional (CSV) para que el helper acepte el destino
    solo si su estado está en la lista; por defecto, solo ``publish``.
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

    effective_statuses = tuple(allowed_statuses) or ("publish",)
    statuses_csv = ",".join(effective_statuses)
    command = (
        f"docker exec {container} wp eval-file "
        f"{STAGE_DIR}/wp-attach.php {STAGE_DIR}/manifest.json {statuses_csv}"
    )
    proc = _run_ssh(ssh_host, command, check=True)

    try:
        return _parse_json_object(_text(proc.stdout))
    except (json.JSONDecodeError, PipelineError) as exc:
        raise PipelineError(f"la subida no devolvió JSON válido: {exc}") from exc


# ---------------------------------------------------------------------------
# Backfill reversible del adjunto (mismo ID/meta; original en ``.orig.srt``)
# ---------------------------------------------------------------------------


@dataclass(frozen=True)
class RemoteTranscript:
    """Contenido actual del SRT de un adjunto, recuperado de producción."""

    episode_id: int
    attachment_id: int
    content: bytes


@dataclass
class BackfillResult:
    """Recuento de un pase de backfill."""

    changed: int = 0
    skipped: int = 0
    reverted: int = 0
    failed: list[int] = field(default_factory=list)


class TranscriptBackend(Protocol):
    """Frontera de acceso al adjunto: leer, reemplazar y revertir su SRT.

    La implementación de producción es :class:`SshTranscriptBackend`; los tests
    inyectan un doble en memoria. Gracias a esta frontera, el contrato del SRT y la
    orquestación no dependen de ``ssh`` ni de WordPress.
    """

    def fetch(self, episode_id: int) -> RemoteTranscript | None:
        """Devuelve el SRT del adjunto, o ``None`` si no hay transcripción."""
        ...

    def replace(self, transcript: RemoteTranscript, data: bytes) -> int:
        """Reemplaza el contenido del adjunto y devuelve su ID (inalterado)."""
        ...

    def revert(self, episode_id: int) -> bool:
        """Restaura el original conservado; devuelve si lo consiguió."""
        ...


def _transcript_attachment_id(record: Mapping[str, Any]) -> int | None:
    """ID del adjunto de la transcripción si es un entero positivo, o ``None``."""
    value = record.get("transcript_id", 0)
    if isinstance(value, int) and not isinstance(value, bool) and value > 0:
        return value
    return None


class SshTranscriptBackend:
    """Frontera de producción: lee y reemplaza el SRT del adjunto vía WP-CLI.

    Cada operación delega en el helper PHP ``wp-backfill.php`` dentro del contenedor
    (``get``/``put``/``revert``). El helper conserva ``<fichero>.orig.srt`` junto al
    adjunto antes de sobrescribirlo y restaura desde ahí al revertir, de modo que el
    ID del adjunto y la meta ``transcript-srt`` **no cambian**.
    """

    def __init__(self, ssh_host: str, container: str, scripts_dir: str | Path) -> None:
        self.ssh_host = ssh_host
        self.container = container
        self.scripts_dir = Path(scripts_dir)

    def _helper_command(self, *args: object) -> str:
        """Comando remoto que ejecuta el helper con sus argumentos posicionales."""
        joined = " ".join(str(arg) for arg in args)
        return (
            f"docker exec {self.container} wp eval-file "
            f"{CONTAINER_TMP_DIR}/{BACKFILL_HELPER_NAME} {joined}"
        )

    def _ensure_helper(self) -> None:
        """Copia el helper PHP al contenedor (idempotente por sobrescritura)."""
        local = self.scripts_dir / BACKFILL_HELPER_NAME
        if not local.is_file():
            raise PipelineError(f"falta el helper PHP: {local}")
        _push_bytes_to_container(
            self.ssh_host,
            self.container,
            f"{CONTAINER_TMP_DIR}/{BACKFILL_HELPER_NAME}",
            local.read_bytes(),
        )

    def _call(self, *args: object) -> dict[str, Any]:
        """Ejecuta el helper y devuelve su JSON (o lanza ``PipelineError``)."""
        proc = _run_ssh(self.ssh_host, self._helper_command(*args), check=True)
        try:
            payload = _parse_json_object(_text(proc.stdout))
        except (json.JSONDecodeError, PipelineError) as exc:
            raise PipelineError(
                f"el helper de backfill no devolvió JSON: {exc}"
            ) from exc
        if payload.get("error"):
            raise PipelineError(f"backfill remoto: {payload['error']}")
        return payload

    def fetch(self, episode_id: int) -> RemoteTranscript | None:
        """Recupera el SRT del adjunto enlazado, o ``None`` si no hay transcripción."""
        self._ensure_helper()
        payload = self._call("get", episode_id)
        if not payload.get("attachment_id"):
            return None
        content = base64.b64decode(str(payload.get("content", "")))
        return RemoteTranscript(episode_id, int(payload["attachment_id"]), content)

    def replace(self, transcript: RemoteTranscript, data: bytes) -> int:
        """Reemplaza el contenido del adjunto y devuelve su ID (sin cambiarlo)."""
        self._ensure_helper()
        remote_tmp = f"{CONTAINER_TMP_DIR}/backfill-{transcript.episode_id}.srt"
        _push_bytes_to_container(self.ssh_host, self.container, remote_tmp, data)
        payload = self._call("put", transcript.episode_id, remote_tmp)
        attachment_id = int(payload.get("attachment_id", 0))
        if attachment_id != transcript.attachment_id:
            raise PipelineError(
                f"episodio {transcript.episode_id}: el ID del adjunto cambió "
                f"({transcript.attachment_id} -> {attachment_id})"
            )
        return attachment_id

    def revert(self, episode_id: int) -> bool:
        """Restaura el SRT original desde ``.orig.srt`` y dice si lo consiguió."""
        self._ensure_helper()
        payload = self._call("revert", episode_id)
        return bool(payload.get("reverted"))


def _load_manifest(path: Path) -> dict[str, Any]:
    """Lee el manifiesto del backfill; vacío si no existe o está corrupto."""
    try:
        return json.loads(path.read_text(encoding="utf-8"))
    except FileNotFoundError:
        return {}
    except (OSError, UnicodeError, json.JSONDecodeError) as exc:
        raise PipelineError(f"manifiesto de backfill ilegible {path}: {exc}") from exc


def _write_manifest(path: Path, manifest: Mapping[str, Any]) -> None:
    """Escribe el manifiesto del backfill en UTF-8 (sin escapar acentos)."""
    path.write_text(
        json.dumps(manifest, ensure_ascii=False, indent=2), encoding="utf-8"
    )


def _manifest_original_sha256(
    manifest: Mapping[str, Any], episode_id: int
) -> str | None:
    """``original_sha256`` guardado para ``episode_id``, o ``None`` si no consta."""
    entry = manifest.get(str(episode_id))
    if not isinstance(entry, Mapping):
        return None
    value = entry.get("original_sha256")
    return value if isinstance(value, str) and value else None


def _sha256_hex(data: bytes) -> str:
    """``sha256`` en hexadecimal del contenido ``data``."""
    return hashlib.sha256(data).hexdigest()


def _restoration_reproduces_original(
    backend: TranscriptBackend, episode_id: int, expected_sha: str | None
) -> bool:
    """Dice si el contenido ya restaurado coincide con el ``sha256`` esperado.

    Sin ``sha256`` de referencia (p. ej. manifiesto ausente) se da por bueno: no hay
    nada con lo que comparar.
    """
    if expected_sha is None:
        return True
    restored = backend.fetch(episode_id)
    content = restored.content if restored is not None else b""
    return _sha256_hex(content) == expected_sha


def backfill_transcripts(
    records: Iterable[Mapping[str, Any]],
    *,
    glossary: Glossary,
    backend: TranscriptBackend,
    work_dir: str | Path,
    dry_run: bool = False,
    revert: bool = False,
    report: Callable[[str], None] = print,
) -> BackfillResult:
    """Aplica (o revierte) el post-proceso a las transcripciones ya adjuntas.

    ``backend`` aporta ``fetch``/``replace``/``revert`` (ver
    :class:`SshTranscriptBackend`). Es **idempotente y reanudable**: con el SRT ya
    post-procesado, ``postprocess_srt`` devuelve el mismo texto y no se reemplaza
    nada (segunda pasada = 0 cambios). Con ``dry_run`` no se reemplaza, ni se crea el
    directorio de trabajo, ni se escribe el manifiesto, ni se toca la meta. El
    manifiesto local guarda los ``sha256`` del original y del resultado por episodio;
    ``--revert`` los usa para **saltar** los episodios ya restaurados y para
    **verificar** que la restauración reproduce el original.
    """
    work_path = Path(work_dir)
    if not dry_run:
        work_path.mkdir(parents=True, exist_ok=True)
    manifest_path = work_path / BACKFILL_MANIFEST_NAME
    manifest = {} if dry_run else _load_manifest(manifest_path)

    result = BackfillResult()
    for record in records:
        episode_id = _episode_id(record)
        if episode_id is None or _transcript_attachment_id(record) is None:
            continue
        try:
            transcript = backend.fetch(episode_id)
            if transcript is None:
                result.skipped += 1
                report(f"[{episode_id}] sin adjunto de transcripción: se omite")
                continue

            if revert:
                expected_sha = _manifest_original_sha256(manifest, episode_id)
                current_sha = _sha256_hex(transcript.content)
                if expected_sha is not None and current_sha == expected_sha:
                    result.skipped += 1
                    report(f"[{episode_id}] ya está en el original: se omite")
                    continue
                if dry_run:
                    result.reverted += 1
                    report(f"[{episode_id}] se restauraría el original (dry-run)")
                    continue
                if not backend.revert(episode_id):
                    result.skipped += 1
                    report(f"[{episode_id}] sin copia del original: se omite")
                    continue
                if not _restoration_reproduces_original(
                    backend, episode_id, expected_sha
                ):
                    result.failed.append(episode_id)
                    report(f"[{episode_id}] la restauración no reproduce el original")
                    continue
                result.reverted += 1
                report(f"[{episode_id}] original restaurado")
                continue

            original_text = transcript.content.decode("utf-8")
            processed = postprocess_srt(original_text, glossary).encode("utf-8")
            if processed == transcript.content:
                result.skipped += 1
                continue

            result.changed += 1
            if dry_run:
                report(f"[{episode_id}] cambiaría {transcript.attachment_id} (dry-run)")
                continue

            attachment_id = backend.replace(transcript, processed)
            manifest[str(episode_id)] = {
                "attachment_id": attachment_id,
                "original_sha256": _sha256_hex(transcript.content),
                "processed_sha256": _sha256_hex(processed),
            }
            _write_manifest(manifest_path, manifest)
            report(f"[{episode_id}] post-procesado {attachment_id}")
        except (PipelineError, SrtError, UnicodeError) as exc:
            result.failed.append(episode_id)
            report(f"[{episode_id}] falló: {exc}")

    return result


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
        "--initial-prompt",
        default="",
        metavar="TEXT",
        help=(
            "prompt de vocabulario opcional que se pasa al reconocedor "
            "(por defecto, ninguno)"
        ),
    )
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
    parser.add_argument(
        "--include-drafts",
        action="store_true",
        help="incluir también los borradores de podcast (por defecto, solo publicados)",
    )
    parser.add_argument(
        "--postprocess",
        action="store_true",
        help=(
            "restaurar puntuación y mayúsculas del SRT y aplicar el glosario "
            "(por defecto, comportamiento actual sin post-proceso)"
        ),
    )
    parser.add_argument(
        "--glossary",
        default=str(DEFAULT_GLOSSARY_PATH),
        metavar="PATH",
        help="glosario TSV de términos del programa (grafía canónica y alias)",
    )
    parser.add_argument(
        "--backfill",
        action="store_true",
        help="post-procesar las transcripciones ya adjuntas (reversible)",
    )
    parser.add_argument(
        "--revert",
        action="store_true",
        help="restaurar los SRT originales guardados por el backfill",
    )
    return parser.parse_args(argv)


def run_pipeline(args: argparse.Namespace) -> int:
    """Ejecuta el pipeline completo con los argumentos ya parseados."""
    work_dir = Path(args.work_dir).expanduser()
    include_drafts = bool(getattr(args, "include_drafts", False))
    initial_prompt = getattr(args, "initial_prompt", "")
    statuses = _statuses_for(include_drafts)

    glossary: Glossary | None = None
    if getattr(args, "postprocess", False):
        glossary = load_glossary(getattr(args, "glossary", DEFAULT_GLOSSARY_PATH))

    try:
        records = collect_episodes(
            args.ssh_host,
            args.wpcli_container,
            SCRIPTS_DIR,
            statuses=statuses,
        )
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

    plan = plan_transcript_job(
        records,
        limit=args.limit,
        dry_run=args.dry_run,
        include_drafts=include_drafts,
    )

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
            detail = ""
            if glossary is not None and label is not None:
                count = _count_changing_blocks(
                    work_dir / "srt" / f"{label}.srt", glossary
                )
                if count is not None:
                    detail = f" (bloques a cambiar: {count})"
            print(f"  - {shown}: {record.get('mp3_url', '')}{detail}")
        print("(ensayo: ninguna acción ejecutada)")
        return 0

    srt_dir = work_dir / "srt"
    audio_dir = work_dir / "audio"
    srt_dir.mkdir(parents=True, exist_ok=True)

    entries: dict[int, Path] = {}
    failed: list[int] = []
    no_speech: list[int] = []

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
                prepared_path: Path | None = None
                try:
                    audible = measure_audible_fraction(audio_path)
                    if audible < NO_SPEECH_MAX_AUDIBLE_FRACTION:
                        raise NoSpeechError(
                            f"fracción audible {audible:.2%} "
                            f"< {NO_SPEECH_MAX_AUDIBLE_FRACTION:.0%}"
                        )
                    prepared_path = preprocess_audio(audio_path, audio_dir)
                    print(
                        f"{prefix}: transcribiendo con {args.model} ({args.language})...",
                        flush=True,
                    )
                    transcribe_audio(
                        record,
                        prepared_path,
                        srt_dir,
                        args.model,
                        args.language,
                        initial_prompt=initial_prompt,
                    )
                finally:
                    # La copia normalizada se elimina siempre; el original solo si
                    # no se pidió conservarlo con ``--keep-audio``.
                    if prepared_path is not None:
                        prepared_path.unlink(missing_ok=True)
                    if not args.keep_audio:
                        audio_path.unlink(missing_ok=True)
            _sanitize_srt(srt_path)
            _ensure_valid_srt(srt_path, episode_id)
            if glossary is not None and postprocess_srt_file(srt_path, glossary):
                print(f"{prefix}: post-proceso aplicado", flush=True)
            entries[episode_id] = srt_path
            print(f"{prefix}: ok", flush=True)
        except NoSpeechError:
            no_speech.append(episode_id)
            print(
                f"⚠️  episodio {episode_id}: audio sin voz, se omite",
                file=sys.stderr,
            )
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
                allowed_statuses=statuses,
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
        f"omitidos  : {skipped + len(no_speech)} "
        f"(ya hechos: {plan['skipped_done']}, sin audio: {plan['skipped_no_audio']}, "
        f"sin voz: {len(no_speech)})"
    )
    print(f"fallidos  : {len(failed)}")
    if failed:
        print("  IDs fallidos: " + ", ".join(str(value) for value in sorted(failed)))

    return 0 if not failed else 1


def run_backfill(args: argparse.Namespace) -> int:
    """Ejecuta (o revierte) el backfill de las transcripciones ya adjuntas."""
    work_dir = Path(args.work_dir).expanduser()
    include_drafts = bool(getattr(args, "include_drafts", False))
    statuses = _statuses_for(include_drafts)
    glossary = load_glossary(getattr(args, "glossary", DEFAULT_GLOSSARY_PATH))

    try:
        records = collect_episodes(
            args.ssh_host,
            args.wpcli_container,
            SCRIPTS_DIR,
            statuses=statuses,
        )
    except PipelineError as exc:
        raise PipelineError(
            f"no se pudieron recolectar los episodios de producción: {exc}"
        ) from exc

    if args.episode:
        wanted = set(args.episode)
        records = [record for record in records if _episode_id(record) in wanted]

    pending = [record for record in records if _transcript_attachment_id(record)]
    if args.limit is not None:
        pending = pending[: max(args.limit, 0)]

    mode = "revertir" if args.revert else "post-procesar"
    if args.dry_run:
        print(f"== ENSAYO (--dry-run): no se escribirá ni se subirá nada ({mode}) ==")
    print(f"transcripciones adjuntas: {len(pending)}")

    backend = SshTranscriptBackend(args.ssh_host, args.wpcli_container, SCRIPTS_DIR)
    result = backfill_transcripts(
        pending,
        glossary=glossary,
        backend=backend,
        work_dir=work_dir,
        dry_run=args.dry_run,
        revert=args.revert,
    )

    print("")
    print("== Resumen ==")
    print(f"cambiados : {result.changed}")
    print(f"revertidos: {result.reverted}")
    print(f"omitidos  : {result.skipped}")
    print(f"fallidos  : {len(result.failed)}")
    if result.failed:
        print(
            "  IDs fallidos: "
            + ", ".join(str(value) for value in sorted(result.failed))
        )

    return 0 if not result.failed else 1


def main(argv: Sequence[str] | None = None) -> int:
    """Entrada CLI: traduce cualquier error del pipeline a un mensaje claro."""
    args = parse_args(argv)
    try:
        if args.backfill or args.revert:
            return run_backfill(args)
        return run_pipeline(args)
    except TranscribeError as exc:
        print(f"error: {exc}", file=sys.stderr)
        return 1
    except KeyboardInterrupt:
        print("error: interrumpido por el usuario", file=sys.stderr)
        return 130


if __name__ == "__main__":
    raise SystemExit(main())
