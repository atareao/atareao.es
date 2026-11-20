#!/usr/bin/env python3
"""Subcomandos e informes de Google Search Console.

Construye informes legibles y estructurados (resumen, listados, auditoría de
sitemaps, oportunidades y comparativa de periodos) sobre las filas que devuelve
:mod:`gsc`. Las funciones de informe son **puras** y reciben las filas ya obtenidas,
de modo que se puedan probar sin red: el acceso a credenciales y a la red vive en
:mod:`gsc` y en un único punto de este módulo (:func:`build_client`).

Solo se usa la biblioteca estándar; el repositorio no tiene gestor de paquetes.
"""

from __future__ import annotations

import argparse
import json
import os
import re
import sys
import urllib.parse
from collections.abc import Mapping, Sequence
from dataclasses import dataclass
from datetime import date, timedelta
from pathlib import Path
from typing import Any

import gsc

# ---------------------------------------------------------------------------
# Valores por defecto y umbrales de la heurística de oportunidades
# ---------------------------------------------------------------------------

DEFAULT_DAYS = 28
DEFAULT_ROWS = 15
DEFAULT_OPPORTUNITY_ROWS = 20
DEFAULT_MIN_IMPRESSIONS = 300
DEFAULT_MAX_CTR = 0.02
DEFAULT_MIN_POSITION = 3.0
DEFAULT_MAX_POSITION = 20.0

#: Filas que se piden a la API antes de filtrar oportunidades (se recortan después).
OPPORTUNITY_FETCH_LIMIT = 1000

#: Detecta una referencia a otro sitemap dentro de un fichero de índice.
_SITEMAP_REFERENCE = re.compile(rb"<sitemap[\s>]")

#: Nombre que declara ser un índice de sitemaps: ``index.xml`` precedido del inicio
#: del nombre o de un separador ``_``/``-`` (``index.xml``, ``sitemap_index.xml``,
#: ``sitemap-index.xml``). Es deliberadamente estrecho: ``reindex.xml`` no cuenta.
_INDEX_NAME = re.compile(r"(?:^|[_-])index\.xml$")


# ---------------------------------------------------------------------------
# Cliente (único punto del módulo que toca credenciales y red)
# ---------------------------------------------------------------------------


@dataclass(frozen=True)
class Client:
    """Sesión de solo lectura contra la Search Console API."""

    site: str
    token: str
    transport: gsc.Transport

    def search(
        self,
        start: date,
        end: date,
        dimensions: Sequence[str] = (),
        row_limit: int = 1000,
    ) -> list[dict[str, Any]]:
        """Consulta ``searchanalytics.query`` delegando en :mod:`gsc`."""
        return gsc.search_analytics(
            self.site,
            start,
            end,
            dimensions,
            row_limit,
            token=self.token,
            transport=self.transport,
        )

    def sitemaps(self) -> list[dict[str, Any]]:
        """Lista los sitemaps enviados delegando en :mod:`gsc`."""
        return gsc.list_sitemaps(self.site, self.token, transport=self.transport)


def build_client() -> Client:
    """Resuelve la credencial, canjea el token y construye el cliente.

    Es el único punto de este módulo donde se accede a ``crypta`` y a la red.
    """
    creds = gsc.load_service_account()
    transport = gsc.default_transport
    token = gsc.fetch_access_token(creds, transport=transport)
    return Client(site=gsc.DEFAULT_SITE, token=token, transport=transport)


# ---------------------------------------------------------------------------
# Rangos de fechas
# ---------------------------------------------------------------------------


def date_range(days: int, today: date | None = None) -> tuple[date, date]:
    """Devuelve ``(inicio, fin)`` de un periodo de ``days`` días terminando hoy."""
    if days < 1:
        raise gsc.GscError(f"El número de días debe ser >= 1, no {days}")
    today = today or date.today()
    return today - timedelta(days=days - 1), today


def previous_date_range(days: int, today: date | None = None) -> tuple[date, date]:
    """Periodo inmediatamente anterior al devuelto por :func:`date_range`."""
    start, _ = date_range(days, today=today)
    return start - timedelta(days=days), start - timedelta(days=1)


def period_info(start: date, end: date, days: int) -> dict[str, Any]:
    """Representación serializable de un periodo consultado."""
    return {"start": start.isoformat(), "end": end.isoformat(), "days": days}


# ---------------------------------------------------------------------------
# Formateo
# ---------------------------------------------------------------------------


def format_table(
    headers: Sequence[str],
    rows: Sequence[Sequence[str]],
    align_right: Sequence[bool],
) -> str:
    """Compone una tabla de texto alineada por columnas, sin dependencias."""
    headers = [str(header) for header in headers]
    cells = [[str(cell) for cell in row] for row in rows]
    widths = [len(header) for header in headers]
    for row in cells:
        for index, cell in enumerate(row[: len(widths)]):
            widths[index] = max(widths[index], len(cell))

    def render(values: Sequence[str]) -> str:
        parts = []
        for index, value in enumerate(values):
            width = widths[index]
            parts.append(
                value.rjust(width) if align_right[index] else value.ljust(width)
            )
        return "  ".join(parts).rstrip()

    lines = [render(headers), render(["-" * width for width in widths])]
    lines.extend(render(row) for row in cells)
    return "\n".join(lines)


def order_rows_by_clicks(rows: Sequence[Mapping[str, Any]]) -> list[dict[str, Any]]:
    """Ordena las filas por clics de mayor a menor (no muta la entrada)."""
    return sorted(rows, key=lambda row: row.get("clicks", 0), reverse=True)


def _fmt_int(value: Any) -> str:
    return f"{int(value):,}"


def _fmt_ctr(value: Any) -> str:
    return f"{float(value):.2%}"


def _fmt_position(value: Any) -> str:
    return f"{float(value):.2f}"


def _fmt_signed(value: Any) -> str:
    return f"{int(value):+,}"


def _fmt_percent(value: float | None) -> str:
    return "—" if value is None else f"{value:+.2%}"


def _markdown_table(
    headers: Sequence[str],
    rows: Sequence[Sequence[str]],
    align_right: Sequence[bool],
) -> str:
    lines = ["| " + " | ".join(headers) + " |"]
    separators = ["---:" if right else ":---" for right in align_right]
    lines.append("| " + " | ".join(separators) + " |")
    lines.extend("| " + " | ".join(str(cell) for cell in row) + " |" for row in rows)
    return "\n".join(lines)


def render_markdown(
    title: str,
    start: date,
    end: date,
    headers: Sequence[str],
    rows: Sequence[Sequence[str]],
    align_right: Sequence[bool],
) -> str:
    """Compone un informe Markdown con título, rango de fechas y tabla."""
    body = _markdown_table(headers, rows, align_right)
    return f"# {title}\n\n**Periodo:** {start} — {end}\n\n{body}\n"


def write_markdown(
    path: Path,
    title: str,
    start: date,
    end: date,
    headers: Sequence[str],
    rows: Sequence[Sequence[str]],
    align_right: Sequence[bool],
) -> None:
    """Escribe el informe Markdown en ``path``, creando el directorio si hace falta."""
    path = Path(path)
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(render_markdown(title, start, end, headers, rows, align_right))


def default_out_path(name: str, today: date | None = None) -> Path:
    """Destino por defecto del informe: ``$XDG_DATA_HOME/atareao/gsc/<name>-<fecha>.md``.

    Se sitúa fuera del repositorio para que la ejecución desatendida no ensucie el
    árbol de trabajo.
    """
    today = today or date.today()
    if xdg := os.environ.get("XDG_DATA_HOME"):
        data_home = Path(xdg)
    else:
        data_home = Path.home() / ".local" / "share"
    return data_home / "atareao" / "gsc" / f"{name}-{today.isoformat()}.md"


def resolve_out_path(out: str | Path | None, name: str) -> Path | None:
    """Resuelve el valor de ``--out`` a la ruta del fichero de informe.

    Acepta dos formas: un **fichero** explícito, que se respeta tal cual, o un
    **directorio** (indicado con una barra final o porque ya existe como tal), en
    cuyo caso el informe se escribe **dentro de ese directorio** con el nombre
    fechado que produce :func:`default_out_path` y el prefijo ``name``. Es lo que
    permite a las unidades de systemd pasar un directorio y no tener que construir
    el nombre fechado. El directorio indicado se respeta siempre; si aún no existe,
    lo crea :func:`write_markdown` al escribir. Devuelve ``None`` cuando no se pidió
    salida a fichero.
    """
    if not out:
        return None
    raw = str(out)
    if raw.endswith(os.sep) or Path(raw).is_dir():
        # Se toma solo el nombre fechado del destino por defecto y se antepone el
        # directorio recibido, que es el que manda.
        return Path(raw) / default_out_path(name).name
    return Path(raw)


# ---------------------------------------------------------------------------
# Oportunidades y comparativa
# ---------------------------------------------------------------------------


def find_opportunities(
    rows: Sequence[Mapping[str, Any]],
    min_impressions: int = DEFAULT_MIN_IMPRESSIONS,
    max_ctr: float = DEFAULT_MAX_CTR,
    min_position: float = DEFAULT_MIN_POSITION,
    max_position: float = DEFAULT_MAX_POSITION,
) -> list[dict[str, Any]]:
    """Ordena candidatos a mejora por impacto potencial.

    Heurística: una fila es oportunidad si acumula **impresiones altas**
    (``>= min_impressions``), su **CTR es bajo** (``< max_ctr``) y su **posición es
    modesta** (entre ``min_position`` y ``max_position``, es decir, visible pero sin
    llegar a los primeros puestos donde el CTR esperable ya sería alto). El impacto
    potencial se estima como ``impressions * (1 - ctr) * (1 - min(position, 20) / 20)``:
    premia el volumen y penaliza tanto la posición como el CTR ya buenos. Los umbrales
    son explícitos y ajustables; sin un CTR esperable por posición el orden es
    orientativo, no una verdad absoluta.
    """
    opportunities: list[dict[str, Any]] = []
    for row in rows:
        impressions = row.get("impressions", 0) or 0
        ctr = row.get("ctr", 0.0) or 0.0
        position = row.get("position", 0.0) or 0.0
        if (
            impressions >= min_impressions
            and ctr < max_ctr
            and min_position <= position <= max_position
        ):
            enriched = dict(row)
            enriched["score"] = (
                impressions * (1 - ctr) * (1 - min(position, 20.0) / 20.0)
            )
            opportunities.append(enriched)
    opportunities.sort(key=lambda row: row["score"], reverse=True)
    return opportunities


def compare_periods(
    current: Mapping[str, Any],
    previous: Mapping[str, Any],
) -> dict[str, Any]:
    """Compara los agregados de dos periodos.

    Devuelve los agregados de ambos y la variación **absoluta y porcentual** de
    ``clicks`` e ``impressions``. El porcentaje se calcula contra el periodo anterior;
    si el anterior es ``0``, la variación porcentual es ``None`` (nunca una división
    por cero).
    """

    def aggregate(period: Mapping[str, Any]) -> dict[str, Any]:
        rows = period.get("rows") or []
        row = rows[0] if rows else {}
        return {
            "clicks": row.get("clicks", 0),
            "impressions": row.get("impressions", 0),
        }

    def variation(key: str) -> dict[str, Any]:
        absolute = current_agg[key] - previous_agg[key]
        percent = None if previous_agg[key] == 0 else absolute / previous_agg[key]
        return {"absolute": absolute, "percent": percent}

    current_agg = aggregate(current)
    previous_agg = aggregate(previous)
    return {
        "current": current_agg,
        "previous": previous_agg,
        "clicks": variation("clicks"),
        "impressions": variation("impressions"),
    }


# ---------------------------------------------------------------------------
# Auditoría de sitemaps
# ---------------------------------------------------------------------------


def contains_sitemap_reference(xml: bytes) -> bool:
    """Indica si ``xml`` contiene alguna referencia ``<sitemap>`` a otro sitemap."""
    return bool(_SITEMAP_REFERENCE.search(xml))


def declares_sitemap_index(filename: str) -> bool:
    """Indica si el nombre del fichero declara ser un índice de sitemaps.

    La heurística es deliberadamente estrecha para no dar falsos positivos: solo
    cuentan ``index.xml``, ``sitemap_index.xml`` y variantes con separador ``_``/``-``
    antes de ``index.xml``. Un nombre como ``reindex.xml`` no declara un índice y por
    tanto nunca se señala por su nombre.
    """
    return bool(_INDEX_NAME.search(filename))


def parse_robots_sitemaps(text: str) -> list[str]:
    """Extrae las URLs declaradas en las líneas ``Sitemap:`` de un ``robots.txt``."""
    declared = []
    for line in text.splitlines():
        line = line.strip()
        if line.lower().startswith("sitemap:"):
            value = line.split(":", 1)[1].strip()
            if value:
                declared.append(value)
    return declared


def _robots_url(site: str) -> str:
    return urllib.parse.urljoin(site, "/robots.txt")


def summarize_sitemaps(
    sitemaps: Sequence[Mapping[str, Any]],
    fingerprints: Mapping[str, str],
    has_sitemap_refs: Mapping[str, bool],
    declared: Sequence[str],
) -> list[dict[str, Any]]:
    """Cruza los sitemaps enviados con sus huellas y el ``robots.txt``.

    Marca los duplicados (misma huella) indicando cuál conservar —el declarado en el
    fichero de reglas de rastreo—, señala los nombres que prometen un índice sin
    contener referencias ``<sitemap>`` y presenta errores y warnings de cada sitemap.
    """
    declared_set = set(declared)
    by_fingerprint: dict[str, list[str]] = {}
    for entry in sitemaps:
        path = entry.get("path")
        fingerprint = fingerprints.get(path) if path else None
        if path and fingerprint:
            by_fingerprint.setdefault(fingerprint, []).append(path)

    findings: list[dict[str, Any]] = []
    for entry in sitemaps:
        path = str(entry.get("path", ""))
        fingerprint = fingerprints.get(path)
        siblings = [
            other
            for other in by_fingerprint.get(fingerprint or "", [])
            if other != path
        ]
        duplicate = bool(siblings)
        is_declared = path in declared_set
        keep = is_declared or not any(other in declared_set for other in siblings)
        retire = duplicate and not keep
        filename = urllib.parse.urlparse(path).path.rsplit("/", 1)[-1].lower()
        declares_index = declares_sitemap_index(filename)
        misleading_index = declares_index and not has_sitemap_refs.get(path, False)

        notes: list[str] = []
        if duplicate:
            notes.append(f"Contenido idéntico a: {', '.join(siblings)}")
        if retire:
            notes.append(
                "Candidato a retirar: el duplicado declarado en el fichero de reglas "
                "de rastreo es la versión que se conserva"
            )
        if misleading_index:
            notes.append(
                "El nombre declara un índice pero el fichero no contiene etiquetas <sitemap>"
            )

        errors = str(entry.get("errors", "0"))
        warnings = str(entry.get("warnings", "0"))
        if errors not in ("", "0"):
            notes.append(f"Errores reportados: {errors}")

        findings.append(
            {
                "path": path,
                "errors": errors,
                "warnings": warnings,
                "fingerprint": fingerprint,
                "duplicate": duplicate,
                "declared": is_declared,
                "retire": retire,
                "misleading_index": misleading_index,
                "notes": notes,
            }
        )
    return findings


def collect_sitemap_audit(client: Client) -> list[dict[str, Any]]:
    """Descarga los sitemaps declarados (sin rastrear sus URLs) y los audita."""
    sitemaps = client.sitemaps()
    fingerprints: dict[str, str] = {}
    has_refs: dict[str, bool] = {}
    for entry in sitemaps:
        path = entry.get("path")
        if not path:
            continue
        raw = gsc.fetch_bytes(path, transport=client.transport)
        # La huella la calcula :mod:`gsc` en un único punto, para no tener dos
        # implementaciones del mismo ``sha256`` que puedan divergir.
        fingerprints[path] = gsc.sitemap_fingerprint(raw)
        has_refs[path] = contains_sitemap_reference(raw)

    declared: list[str] = []
    try:
        robots = gsc.fetch_text(_robots_url(client.site), transport=client.transport)
    except gsc.GscHttpError:
        robots = ""
    declared = parse_robots_sitemaps(robots)

    return summarize_sitemaps(sitemaps, fingerprints, has_refs, declared)


# ---------------------------------------------------------------------------
# Subcomandos
# ---------------------------------------------------------------------------


def run_resumen(args: argparse.Namespace, client: Client) -> str:
    start, end = date_range(args.days)
    rows = client.search(start, end)
    metrics = rows[0] if rows else {}
    payload = {
        "period": period_info(start, end, args.days),
        "metrics": {
            key: metrics.get(key, 0)
            for key in ("clicks", "impressions", "ctr", "position")
        },
    }
    table_rows = [
        ["Clics", _fmt_int(metrics.get("clicks", 0))],
        ["Impresiones", _fmt_int(metrics.get("impressions", 0))],
        ["CTR", _fmt_ctr(metrics.get("ctr", 0.0))],
        ["Posición media", _fmt_position(metrics.get("position", 0.0))],
    ]
    if out_path := resolve_out_path(args.out, args.command):
        write_markdown(
            out_path,
            "Resumen de Google Search Console",
            start,
            end,
            ["Métrica", "Valor"],
            table_rows,
            [False, True],
        )
    if args.json:
        return json.dumps(payload, ensure_ascii=False, indent=2)
    header = f"Periodo: {start} — {end} ({args.days} días)"
    return f"{header}\n{format_table(['Métrica', 'Valor'], table_rows, [False, True])}"


def run_listing(
    args: argparse.Namespace,
    client: Client,
    dimension: str,
    label: str,
) -> str:
    start, end = date_range(args.days)
    rows = order_rows_by_clicks(client.search(start, end, [dimension], args.rows))
    payload = {
        "period": period_info(start, end, args.days),
        "dimension": dimension,
        "rows": rows,
    }
    headers = [label, "Clics", "Impresiones", "CTR", "Posición"]
    table_rows = [
        [
            row.get("keys", [""])[0],
            _fmt_int(row.get("clicks", 0)),
            _fmt_int(row.get("impressions", 0)),
            _fmt_ctr(row.get("ctr", 0.0)),
            _fmt_position(row.get("position", 0.0)),
        ]
        for row in rows
    ]
    if out_path := resolve_out_path(args.out, args.command):
        write_markdown(
            out_path,
            f"{label} de Google Search Console",
            start,
            end,
            headers,
            table_rows,
            [False, True, True, True, True],
        )
    if args.json:
        return json.dumps(payload, ensure_ascii=False, indent=2)
    header = f"Periodo: {start} — {end} ({args.days} días)"
    return f"{header}\n{format_table(headers, table_rows, [False, True, True, True, True])}"


def run_sitemaps(args: argparse.Namespace, client: Client) -> str:
    findings = collect_sitemap_audit(client)
    payload = {"sitemaps": findings}
    headers = ["Sitemap", "Errores", "Warnings", "Duplicado", "Retirar"]
    table_rows = [
        [
            finding["path"],
            finding["errors"],
            finding["warnings"],
            "sí" if finding["duplicate"] else "no",
            "sí" if finding["retire"] else "no",
        ]
        for finding in findings
    ]
    if out_path := resolve_out_path(args.out, args.command):
        today = date.today()
        write_markdown(
            out_path,
            "Auditoría de sitemaps de Google Search Console",
            today,
            today,
            headers,
            table_rows,
            [False, True, True, True, True],
        )
    if args.json:
        return json.dumps(payload, ensure_ascii=False, indent=2)
    table = format_table(headers, table_rows, [False, True, True, True, True])
    notes = [note for finding in findings for note in finding["notes"]]
    if notes:
        table += "\n\n" + "\n".join(f"- {note}" for note in notes)
    return table


def run_oportunidades(args: argparse.Namespace, client: Client) -> str:
    start, end = date_range(args.days)
    rows = client.search(start, end, ["query"], OPPORTUNITY_FETCH_LIMIT)
    found = find_opportunities(rows, args.min_impressions, args.max_ctr)
    top = found[: args.rows]
    payload = {
        "period": period_info(start, end, args.days),
        "thresholds": {
            "min_impressions": args.min_impressions,
            "max_ctr": args.max_ctr,
        },
        "opportunities": top,
    }
    headers = ["Consulta", "Impresiones", "CTR", "Posición", "Score"]
    table_rows = [
        [
            row.get("keys", [""])[0],
            _fmt_int(row.get("impressions", 0)),
            _fmt_ctr(row.get("ctr", 0.0)),
            _fmt_position(row.get("position", 0.0)),
            f"{row['score']:.0f}",
        ]
        for row in top
    ]
    if out_path := resolve_out_path(args.out, "oportunidades"):
        write_markdown(
            out_path,
            "Oportunidades de contenido (Google Search Console)",
            start,
            end,
            headers,
            table_rows,
            [False, True, True, True, True],
        )
    if args.json:
        return json.dumps(payload, ensure_ascii=False, indent=2)
    header = f"Periodo: {start} — {end} ({args.days} días)"
    return f"{header}\n{format_table(headers, table_rows, [False, True, True, True, True])}"


def run_compara(args: argparse.Namespace, client: Client) -> str:
    start, end = date_range(args.days)
    prev_start, prev_end = previous_date_range(args.days)
    current = {"rows": client.search(start, end)}
    previous = {"rows": client.search(prev_start, prev_end)}
    comparison = compare_periods(current, previous)
    payload = {
        "period": period_info(start, end, args.days),
        "previous_period": period_info(prev_start, prev_end, args.days),
        **comparison,
    }
    headers = ["Métrica", "Actual", "Anterior", "Variación", "%"]
    table_rows = [
        [
            label,
            _fmt_int(comparison["current"][key]),
            _fmt_int(comparison["previous"][key]),
            _fmt_signed(comparison[key]["absolute"]),
            _fmt_percent(comparison[key]["percent"]),
        ]
        for label, key in (("Clics", "clicks"), ("Impresiones", "impressions"))
    ]
    if out_path := resolve_out_path(args.out, args.command):
        write_markdown(
            out_path,
            "Comparativa de periodos (Google Search Console)",
            start,
            end,
            headers,
            table_rows,
            [False, True, True, True, True],
        )
    if args.json:
        return json.dumps(payload, ensure_ascii=False, indent=2)
    header = f"Actual: {start} — {end} | Anterior: {prev_start} — {prev_end}"
    return f"{header}\n{format_table(headers, table_rows, [False, True, True, True, True])}"


# ---------------------------------------------------------------------------
# CLI
# ---------------------------------------------------------------------------


def _positive_int(value: str) -> int:
    """Valida un entero de línea de comandos que debe ser >= 1.

    Evita que un ``--rows`` negativo o cero llegue a la API (que rechazaría
    ``rowLimit``). Se usa como ``type`` de ``argparse``, de modo que el error se
    informa con un mensaje claro y un código de salida no nulo.
    """
    try:
        number = int(value)
    except ValueError as exc:
        raise argparse.ArgumentTypeError(
            f"valor entero esperado, no {value!r}"
        ) from exc
    if number < 1:
        raise argparse.ArgumentTypeError(
            f"El número de filas debe ser >= 1, no {number}"
        )
    return number


def _add_output_flags(parser: argparse.ArgumentParser) -> None:
    parser.add_argument("--json", action="store_true", help="Emite solo JSON válido")
    # Se recibe como texto, no como ``Path``, para poder distinguir un directorio
    # indicado con barra final (que ``Path`` normalizaría y perdería): ver
    # ``resolve_out_path``.
    parser.add_argument(
        "--out",
        type=str,
        default=None,
        metavar="DESTINO",
        help="Escribe el informe en Markdown (fichero o directorio)",
    )


def parse_args(argv: Sequence[str] | None = None) -> argparse.Namespace:
    """Construye el parser y los subcomandos de la CLI."""
    parser = argparse.ArgumentParser(
        prog="gsc", description="Consulta Google Search Console (solo lectura)"
    )
    subparsers = parser.add_subparsers(dest="command", required=True)

    resumen = subparsers.add_parser("resumen", help="Resumen del periodo")
    resumen.add_argument("--days", type=int, default=DEFAULT_DAYS)
    _add_output_flags(resumen)

    for name, label in (("queries", "Consulta"), ("paginas", "Página")):
        listing = subparsers.add_parser(
            name, help=f"Listado de {label.lower()}s con más clics"
        )
        listing.add_argument("--days", type=int, default=DEFAULT_DAYS)
        listing.add_argument("--rows", type=_positive_int, default=DEFAULT_ROWS)
        _add_output_flags(listing)

    sitemaps = subparsers.add_parser("sitemaps", help="Auditoría de sitemaps enviados")
    _add_output_flags(sitemaps)

    oportunidades = subparsers.add_parser(
        "oportunidades", help="Candidatos a mejora por impacto potencial"
    )
    oportunidades.add_argument("--days", type=int, default=DEFAULT_DAYS)
    oportunidades.add_argument(
        "--rows", type=_positive_int, default=DEFAULT_OPPORTUNITY_ROWS
    )
    oportunidades.add_argument(
        "--min-impressions", type=int, default=DEFAULT_MIN_IMPRESSIONS
    )
    oportunidades.add_argument("--max-ctr", type=float, default=DEFAULT_MAX_CTR)
    _add_output_flags(oportunidades)

    compara = subparsers.add_parser(
        "compara", help="Comparativa con el periodo anterior"
    )
    compara.add_argument("--days", type=int, default=DEFAULT_DAYS)
    _add_output_flags(compara)

    return parser.parse_args(argv)


def main(argv: Sequence[str] | None = None) -> int:
    """Punto de entrada: 0 en éxito, 1 en error (mensaje por ``stderr``)."""
    args = parse_args(argv)
    try:
        client = build_client()
        if args.command == "resumen":
            output = run_resumen(args, client)
        elif args.command == "queries":
            output = run_listing(args, client, "query", "Consulta")
        elif args.command == "paginas":
            output = run_listing(args, client, "page", "Página")
        elif args.command == "sitemaps":
            output = run_sitemaps(args, client)
        elif args.command == "oportunidades":
            output = run_oportunidades(args, client)
        elif args.command == "compara":
            output = run_compara(args, client)
        else:  # pragma: no cover - argparse ya lo impide
            raise gsc.GscError(f"Subcomando desconocido: {args.command}")
    except gsc.GscError as exc:
        print(f"Error: {exc}", file=sys.stderr)
        return 1
    if output:
        print(output)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
