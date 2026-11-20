#!/usr/bin/env python3
"""Cliente de solo lectura de la Google Search Console API.

Resuelve la credencial de la service account desde ``crypta``, firma un JWT RS256
con ``openssl`` (la clave privada viaja por la entrada estándar del subproceso y
nunca se escribe en disco) y canjea ese JWT por un token de acceso. Con el token se
consultan ``searchanalytics.query`` y ``sitemaps.list``.

Solo se usa la biblioteca estándar de Python y el binario ``openssl``: el repositorio
no tiene gestor de paquetes. El acceso a la red queda aislado detrás de un
``Transport`` inyectable, de modo que las pruebas no toquen internet.

El módulo no tiene efectos secundarios al importarse: las credenciales y la red solo
se usan cuando se invocan sus funciones.
"""

from __future__ import annotations

import base64
import hashlib
import json
import shutil
import subprocess
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request
from collections.abc import Callable, Mapping, Sequence
from datetime import date
from pathlib import Path
from typing import Any

# ---------------------------------------------------------------------------
# Constantes
# ---------------------------------------------------------------------------

#: Propiedad de Search Console consultada por defecto.
DEFAULT_SITE = "https://atareao.es/"

#: Ámbito de solo lectura: la integración no puede escribir en Search Console.
READONLY_SCOPE = "https://www.googleapis.com/auth/webmasters.readonly"

#: Clave bajo la que vive la service account en ``crypta``.
SECRET_KEY = "atareao_gsc_service_account"

#: Base de la API de Search Console (Webmasters v3).
API_BASE = "https://www.googleapis.com/webmasters/v3/sites"

#: Tipo del transporte HTTP inyectable: ``(method, url, headers, body) -> (status, body)``.
Transport = Callable[[str, str, dict[str, str] | None, bytes | None], tuple[int, bytes]]

#: Timeout de las peticiones HTTP, en segundos.
HTTP_TIMEOUT = 30

#: Tipo de contenido del canje del JWT por un token de acceso.
JWT_BEARER_GRANT = "urn:ietf:params:oauth:grant-type:jwt-bearer"


def _encode_site(site: str) -> str:
    """Codifica el sitio para la URL de la API (la barra final debe ser ``%2F``)."""
    return urllib.parse.quote(site, safe="")


#: Ruta REST del método ``searchanalytics.query``. La ``A`` de ``searchAnalytics``
#: es mayúscula: en minúscula la API responde 404.
SEARCH_ANALYTICS_PATH = "searchAnalytics/query"

#: Ruta REST del método ``sitemaps.list`` (en este caso todo en minúscula).
SITEMAPS_PATH = "sitemaps"

#: URL de ``searchanalytics.query`` para la propiedad por defecto.
SEARCH_ANALYTICS_URL = (
    f"{API_BASE}/{_encode_site(DEFAULT_SITE)}/{SEARCH_ANALYTICS_PATH}"
)

#: URL de ``sitemaps.list`` para la propiedad por defecto.
SITEMAPS_URL = f"{API_BASE}/{_encode_site(DEFAULT_SITE)}/{SITEMAPS_PATH}"


# ---------------------------------------------------------------------------
# Excepciones
# ---------------------------------------------------------------------------


class GscError(Exception):
    """Error base de la integración con Search Console."""


class GscCredentialError(GscError):
    """La credencial de la service account no pudo resolverse o es inválida."""


class GscHttpError(GscError):
    """Una petición HTTP devolvió un código inesperado o falló a nivel de red."""

    def __init__(
        self, message: str, status: int | None = None, body: bytes = b""
    ) -> None:
        super().__init__(message)
        self.status = status
        self.body = body


def _credential_hint() -> str:
    """Mensaje de ayuda con el nombre exacto de la clave y el comando de alta."""
    return f"Dala de alta con: crypta store {SECRET_KEY}"


# ---------------------------------------------------------------------------
# Transporte HTTP
# ---------------------------------------------------------------------------


def default_transport(
    method: str,
    url: str,
    headers: dict[str, str] | None = None,
    body: bytes | None = None,
) -> tuple[int, bytes]:
    """Transporte por defecto basado en ``urllib.request``.

    No lanza ante códigos 4xx/5xx: captura ``urllib.error.HTTPError`` y devuelve su
    código y cuerpo, para que la capa de arriba decida. Los errores de red sí se
    propagan envueltos en :class:`GscHttpError`.
    """
    request = urllib.request.Request(
        url, data=body, headers=headers or {}, method=method
    )
    try:
        with urllib.request.urlopen(request, timeout=HTTP_TIMEOUT) as response:
            return int(response.status), response.read()
    except urllib.error.HTTPError as exc:
        return int(exc.code), exc.read()
    except urllib.error.URLError as exc:
        raise GscHttpError(f"No se pudo conectar con {url}: {exc.reason}") from exc


# ---------------------------------------------------------------------------
# Credenciales
# ---------------------------------------------------------------------------


def _default_lookup() -> str:
    """Resuelve la credencial con ``crypta lookup`` (no interactivo)."""
    crypta = shutil.which("crypta")
    if crypta is None:
        fallback = Path.home() / ".cargo" / "bin" / "crypta"
        if fallback.exists():
            crypta = str(fallback)
        else:
            raise GscCredentialError(
                f"No se encontró el binario 'crypta' para resolver la credencial "
                f"'{SECRET_KEY}'. {_credential_hint()}"
            )
    result = subprocess.run(
        [crypta, "lookup", SECRET_KEY],
        capture_output=True,
        text=True,
    )
    if result.returncode != 0:
        raise GscCredentialError(
            f"No se pudo resolver la credencial '{SECRET_KEY}'. {_credential_hint()}"
        )
    return result.stdout


def load_service_account(lookup: Callable[[], str] | None = None) -> dict[str, Any]:
    """Carga y valida la credencial de la service account.

    :param lookup: función que devuelve el JSON de la credencial. Por defecto usa
        ``crypta lookup``. Se inyecta en las pruebas.
    :raises GscCredentialError: si la credencial no está, no es JSON válido o le
        faltan campos. El mensaje incluye el nombre exacto de la clave y el comando
        de alta, y nunca el contenido de ``private_key``.
    """
    lookup = lookup or _default_lookup
    raw = lookup()
    try:
        creds = json.loads(raw)
    except json.JSONDecodeError as exc:
        raise GscCredentialError(
            f"La credencial '{SECRET_KEY}' no es un JSON válido. {_credential_hint()}"
        ) from exc
    if not isinstance(creds, dict):
        raise GscCredentialError(
            f"La credencial '{SECRET_KEY}' no es un objeto JSON. {_credential_hint()}"
        )
    required = ("client_email", "private_key", "token_uri")
    missing = [field for field in required if not creds.get(field)]
    if missing:
        raise GscCredentialError(
            f"La credencial '{SECRET_KEY}' no contiene: {', '.join(missing)}. "
            f"{_credential_hint()}"
        )
    return creds


# ---------------------------------------------------------------------------
# Firma del JWT (RS256 con openssl)
# ---------------------------------------------------------------------------


def b64url(data: bytes) -> str:
    """Codifica en base64url sin relleno."""
    return base64.urlsafe_b64encode(data).rstrip(b"=").decode("ascii")


def _json_bytes(obj: Mapping[str, Any]) -> bytes:
    """Serializa a JSON compacto (sin espacios), como exige el JWT."""
    return json.dumps(obj, separators=(",", ":"), sort_keys=False).encode("utf-8")


def sign_rs256(message: bytes, private_key_pem: str) -> bytes:
    """Firma ``message`` con RS256 usando ``openssl``.

    Ejecuta ``openssl dgst -sha256 -sign /dev/stdin <fichero>`` pasando el PEM por la
    entrada estándar del subproceso: la clave privada nunca toca el disco. El mensaje
    (que no es secreto) se escribe en un fichero temporal de un directorio privado que
    se borra siempre en un ``finally``.

    :raises GscError: si ``openssl`` no está disponible o la firma falla.
    """
    openssl = shutil.which("openssl")
    if openssl is None:
        raise GscError(
            "No se encontró el binario 'openssl', necesario para firmar el JWT RS256"
        )
    with tempfile.TemporaryDirectory(prefix="gsc-sign-") as tmpdir:
        data_path = Path(tmpdir) / "data.bin"
        data_path.write_bytes(message)
        try:
            result = subprocess.run(
                [openssl, "dgst", "-sha256", "-sign", "/dev/stdin", str(data_path)],
                input=private_key_pem.encode("utf-8"),
                capture_output=True,
            )
        except OSError as exc:
            raise GscError(f"No se pudo ejecutar openssl: {exc}") from exc
        finally:
            data_path.unlink(missing_ok=True)
        if result.returncode != 0:
            stderr = result.stderr.decode("utf-8", "replace").strip()
            raise GscError(f"openssl falló al firmar el JWT: {stderr}")
        return result.stdout


def build_jwt(
    creds: Mapping[str, Any],
    now: int | None = None,
    lifetime: int = 3600,
) -> str:
    """Construye el JWT RS256 de la service account, sin firmarlo en disco.

    El header incluye ``kid`` solo si la credencial trae ``private_key_id``. Las
    claims son ``iss``, ``scope``, ``aud``, ``iat`` y ``exp``.
    """
    issued_at = int(time.time()) if now is None else int(now)
    header: dict[str, Any] = {"alg": "RS256", "typ": "JWT"}
    if creds.get("private_key_id"):
        header["kid"] = creds["private_key_id"]
    claims: dict[str, Any] = {
        "iss": creds["client_email"],
        "scope": READONLY_SCOPE,
        "aud": creds["token_uri"],
        "iat": issued_at,
        "exp": issued_at + lifetime,
    }
    signing_input = (
        f"{b64url(_json_bytes(header))}.{b64url(_json_bytes(claims))}".encode("ascii")
    )
    signature = sign_rs256(signing_input, creds["private_key"])
    return f"{signing_input.decode('ascii')}.{b64url(signature)}"


# ---------------------------------------------------------------------------
# Canje del token y consultas
# ---------------------------------------------------------------------------


def fetch_access_token(
    creds: Mapping[str, Any],
    transport: Transport | None = None,
    now: int | None = None,
) -> str:
    """Canjea el JWT por un token de acceso de OAuth2.

    :raises GscHttpError: si el código de respuesta no es 200.
    :raises GscError: si la respuesta no trae ``access_token``.
    """
    transport = transport or default_transport
    assertion = build_jwt(creds, now=now)
    body = urllib.parse.urlencode(
        {"grant_type": JWT_BEARER_GRANT, "assertion": assertion}
    ).encode("ascii")
    headers = {
        "Content-Type": "application/x-www-form-urlencoded",
        "Accept": "application/json",
    }
    status, raw = transport("POST", str(creds["token_uri"]), headers, body)
    if status != 200:
        raise GscHttpError(
            f"El canje del token falló con HTTP {status}", status=status, body=raw
        )
    try:
        payload = json.loads(raw)
    except json.JSONDecodeError as exc:
        raise GscError("La respuesta del canje de token no es JSON válido") from exc
    token = payload.get("access_token")
    if not token:
        raise GscError("La respuesta del canje de token no contiene 'access_token'")
    return str(token)


def _auth_headers(token: str) -> dict[str, str]:
    """Cabeceras con el token de acceso."""
    return {"Authorization": f"Bearer {token}", "Accept": "application/json"}


def _json_headers(token: str) -> dict[str, str]:
    """Cabeceras para una petición POST con cuerpo JSON autenticada.

    ``Content-Type: application/json`` es imprescindible: sin él ``urllib`` envía
    el cuerpo como ``application/x-www-form-urlencoded`` y la API responde 400
    ("startDate field is required").
    """
    return {**_auth_headers(token), "Content-Type": "application/json"}


def search_analytics(
    site: str,
    start: date,
    end: date,
    dimensions: Sequence[str] = (),
    row_limit: int = 1000,
    *,
    token: str,
    transport: Transport | None = None,
) -> list[dict[str, Any]]:
    """Consulta ``searchanalytics.query`` y devuelve sus filas.

    ``token`` es de solo palabra clave porque va detrás de parámetros con valor por
    defecto (la firma con ``token`` posicional sería inválida en Python).
    """
    transport = transport or default_transport
    url = f"{API_BASE}/{_encode_site(site)}/{SEARCH_ANALYTICS_PATH}"
    body = _json_bytes(
        {
            "startDate": start.isoformat(),
            "endDate": end.isoformat(),
            "dimensions": list(dimensions),
            "rowLimit": row_limit,
        }
    )
    status, raw = transport("POST", url, _json_headers(token), body)
    if status != 200:
        raise GscHttpError(
            f"searchanalytics.query falló con HTTP {status}", status=status, body=raw
        )
    return _rows_from(raw, "rows")


def list_sitemaps(
    site: str,
    token: str,
    transport: Transport | None = None,
) -> list[dict[str, Any]]:
    """Lista los sitemaps enviados con ``sitemaps.list``."""
    transport = transport or default_transport
    url = f"{API_BASE}/{_encode_site(site)}/{SITEMAPS_PATH}"
    status, raw = transport("GET", url, _auth_headers(token), None)
    if status != 200:
        raise GscHttpError(
            f"sitemaps.list falló con HTTP {status}", status=status, body=raw
        )
    return _rows_from(raw, "sitemap")


def _rows_from(raw: bytes, key: str) -> list[dict[str, Any]]:
    """Extrae una lista de filas de una respuesta JSON, vacía si no aparece."""
    try:
        payload = json.loads(raw)
    except json.JSONDecodeError as exc:
        raise GscError(f"La respuesta de la API no es JSON válido: {exc}") from exc
    value = payload.get(key) if isinstance(payload, dict) else None
    return list(value) if value else []


def fetch_bytes(url: str, transport: Transport | None = None) -> bytes:
    """Descarga un recurso y devuelve su cuerpo.

    :raises GscHttpError: si el código de respuesta no es 200.
    """
    transport = transport or default_transport
    status, raw = transport("GET", url, None, None)
    if status != 200:
        raise GscHttpError(
            f"No se pudo descargar {url}: HTTP {status}", status=status, body=raw
        )
    return raw


def fetch_text(url: str, transport: Transport | None = None) -> str:
    """Descarga un recurso de texto (por ejemplo ``robots.txt``)."""
    return fetch_bytes(url, transport=transport).decode("utf-8", "replace")


def sitemap_fingerprint(content: bytes) -> str:
    """Devuelve el ``sha256`` hexadecimal de un contenido de sitemap.

    Única implementación de la huella de un sitemap: la usan tanto
    :func:`fetch_sitemap_hash` como la auditoría de sitemaps de :mod:`report`, de
    modo que no haya dos cálculos del mismo hash que puedan divergir con el tiempo.
    """
    return hashlib.sha256(content).hexdigest()


def fetch_sitemap_hash(url: str, transport: Transport | None = None) -> str:
    """Devuelve el ``sha256`` hexadecimal del contenido de un sitemap descargado.

    Sirve para detectar sitemaps duplicados sin rastrear las URLs que contienen.
    """
    return sitemap_fingerprint(fetch_bytes(url, transport=transport))
