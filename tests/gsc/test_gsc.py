"""Pruebas unitarias del cliente de Google Search Console y sus informes.

Fase RED de TDD: los módulos bajo prueba (``scripts/gsc/gsc.py`` y
``scripts/gsc/report.py``) todavía no existen, así que su importación falla. Estas
pruebas describen el contrato congelado que la implementación debe satisfacer.

Solo se usa la biblioteca estándar (``unittest``, ``unittest.mock``, ``json``,
``sys``, ``pathlib``, ``tempfile``, ``subprocess``, ``hashlib``); el repositorio no
tiene gestor de dependencias.

Ninguna prueba toca la red: el transporte HTTP se sustituye por ``FakeTransport`` y
las respuestas se cargan de ``tests/gsc/fixtures/``. Las pruebas que necesitan el
binario ``openssl`` se saltan si no está disponible.
"""

from __future__ import annotations

import base64
import contextlib
import hashlib
import io
import json
import os
import pathlib
import shutil
import subprocess
import sys
import tempfile
import unittest
import urllib.parse
from datetime import date, timedelta
from unittest import mock

REPO_ROOT = pathlib.Path(__file__).resolve().parents[2]
sys.path.insert(0, str(REPO_ROOT / "scripts" / "gsc"))

# Los módulos bajo prueba no existen todavía: estos imports DEBEN fallar en RED.
import gsc  # noqa: E402
import report  # noqa: E402

FIXTURES = pathlib.Path(__file__).resolve().parent / "fixtures"
HAVE_OPENSSL = shutil.which("openssl") is not None


def load_fixture(name: str) -> bytes:
    """Devuelve el contenido binario de un fixture grabado."""
    return (FIXTURES / name).read_bytes()


class FakeTransport:
    """Doble de transporte HTTP: registra llamadas y sirve respuestas grabadas."""

    def __init__(self, responses: dict[tuple[str, str], tuple[int, bytes]]) -> None:
        self.responses = responses
        self.calls: list[dict[str, object]] = []

    def __call__(
        self,
        method: str,
        url: str,
        headers: dict[str, str] | None = None,
        body: bytes | None = None,
    ) -> tuple[int, bytes]:
        self.calls.append(
            {"method": method, "url": url, "headers": headers, "body": body}
        )
        key = (method, url)
        if key not in self.responses:
            raise AssertionError(f"Llamada inesperada: {method} {url}")
        return self.responses[key]


def sample_creds(**overrides: object) -> dict[str, object]:
    """Credencial de service account de mentira (nunca es una clave real)."""
    creds: dict[str, object] = {
        "type": "service_account",
        "project_id": "proyecto-de-prueba",
        "private_key_id": "abc123",
        "private_key": "-----BEGIN PRIVATE KEY-----\nFAKE\n-----END PRIVATE KEY-----\n",
        "client_email": (
            "servicio-de-prueba@proyecto-de-prueba.iam.gserviceaccount.com"
        ),
        "token_uri": "https://oauth2.googleapis.com/token",
    }
    creds.update(overrides)
    return creds


class TestJwt(unittest.TestCase):
    """Construcción y estructura del JWT RS256."""

    @staticmethod
    def _decode_segment(segment: str) -> dict[str, object]:
        padded = segment + "=" * (-len(segment) % 4)
        return json.loads(base64.urlsafe_b64decode(padded))

    def test_three_segments_and_claims(self) -> None:
        creds = sample_creds()
        with mock.patch.object(gsc, "sign_rs256", return_value=b"firma"):
            token = gsc.build_jwt(creds, now=1_700_000_000, lifetime=3600)

        header_seg, payload_seg, sig_seg = token.split(".")
        header = self._decode_segment(header_seg)
        payload = self._decode_segment(payload_seg)

        self.assertEqual(header["alg"], "RS256")
        self.assertEqual(header["typ"], "JWT")
        self.assertEqual(header["kid"], "abc123")
        self.assertEqual(payload["iss"], creds["client_email"])
        self.assertEqual(payload["scope"], gsc.READONLY_SCOPE)
        self.assertEqual(payload["aud"], creds["token_uri"])
        self.assertEqual(payload["iat"], 1_700_000_000)
        self.assertEqual(payload["exp"], 1_700_003_600)
        self.assertEqual(sig_seg, gsc.b64url(b"firma"))

    def test_kid_omitted_without_private_key_id(self) -> None:
        creds = sample_creds(private_key_id=None)
        with mock.patch.object(gsc, "sign_rs256", return_value=b"firma"):
            token = gsc.build_jwt(creds, now=1)
        header = self._decode_segment(token.split(".")[0])
        self.assertNotIn("kid", header)

    def test_b64url_has_no_padding(self) -> None:
        self.assertEqual(gsc.b64url(b"a"), "YQ")
        self.assertNotIn("=", gsc.b64url(b"ab"))

    def test_module_constants_encode_the_site(self) -> None:
        self.assertEqual(gsc.DEFAULT_SITE, "https://atareao.es/")
        self.assertEqual(
            gsc.READONLY_SCOPE,
            "https://www.googleapis.com/auth/webmasters.readonly",
        )
        self.assertEqual(gsc.SECRET_KEY, "atareao_gsc_service_account")
        self.assertIn("%2F", gsc.SEARCH_ANALYTICS_URL)
        self.assertIn("%2F", gsc.SITEMAPS_URL)
        self.assertNotIn("https://atareao.es/", gsc.SEARCH_ANALYTICS_URL)

    def test_search_analytics_url_literal(self) -> None:
        """La URL de ``searchanalytics.query`` es exactamente la esperada.

        Fija el literal carácter a carácter para que un descuido en la caja de
        ``searchAnalytics`` (la ``A`` debe ser mayúscula; en minúscula la API
        responde 404) haga fallar la prueba.
        """
        self.assertEqual(
            gsc.SEARCH_ANALYTICS_URL,
            "https://www.googleapis.com/webmasters/v3/sites/"
            "https%3A%2F%2Fatareao.es%2F/searchAnalytics/query",
        )

    def test_sitemaps_url_literal(self) -> None:
        """La URL de ``sitemaps.list`` es exactamente la esperada.

        Igual que en analytics, fija el literal completo para blindar la ruta
        frente a cambios accidentales.
        """
        self.assertEqual(
            gsc.SITEMAPS_URL,
            "https://www.googleapis.com/webmasters/v3/sites/"
            "https%3A%2F%2Fatareao.es%2F/sitemaps",
        )


class TestCredentials(unittest.TestCase):
    """Resolución de la credencial desde ``crypta``."""

    def test_loads_valid_json(self) -> None:
        creds = sample_creds()
        loaded = gsc.load_service_account(lambda: json.dumps(creds))
        self.assertEqual(loaded["client_email"], creds["client_email"])

    def test_missing_credential_message(self) -> None:
        with self.assertRaises(gsc.GscCredentialError) as ctx:
            gsc.load_service_account(lambda: "")
        message = str(ctx.exception)
        self.assertIn(gsc.SECRET_KEY, message)
        self.assertIn(f"crypta store {gsc.SECRET_KEY}", message)
        self.assertNotIn("PRIVATE KEY", message)

    def test_invalid_json_message(self) -> None:
        with self.assertRaises(gsc.GscCredentialError) as ctx:
            gsc.load_service_account(lambda: "no-json")
        message = str(ctx.exception)
        self.assertIn(gsc.SECRET_KEY, message)
        self.assertIn(f"crypta store {gsc.SECRET_KEY}", message)

    def test_missing_required_fields(self) -> None:
        with self.assertRaises(gsc.GscCredentialError) as ctx:
            gsc.load_service_account(lambda: json.dumps({"client_email": "x"}))
        self.assertIn("private_key", str(ctx.exception))

    def test_default_lookup_without_crypta(self) -> None:
        empty_home = pathlib.Path(tempfile.mkdtemp())
        with (
            mock.patch.object(gsc.shutil, "which", return_value=None),
            mock.patch.object(gsc.Path, "home", return_value=empty_home),
        ):
            with self.assertRaises(gsc.GscCredentialError) as ctx:
                gsc.load_service_account()
        message = str(ctx.exception)
        self.assertIn(gsc.SECRET_KEY, message)
        self.assertIn(f"crypta store {gsc.SECRET_KEY}", message)


@unittest.skipUnless(HAVE_OPENSSL, "openssl no disponible")
class TestSignRs256(unittest.TestCase):
    """Firma RS256 con ``openssl`` y verificación independiente."""

    @classmethod
    def setUpClass(cls) -> None:
        cls._tmp = tempfile.TemporaryDirectory()
        tmp = pathlib.Path(cls._tmp.name)
        cls.key_path = tmp / "key.pem"
        cls.pub_path = tmp / "pub.pem"
        subprocess.run(
            ["openssl", "genrsa", "-out", str(cls.key_path), "2048"],
            check=True,
            capture_output=True,
        )
        subprocess.run(
            [
                "openssl",
                "rsa",
                "-in",
                str(cls.key_path),
                "-pubout",
                "-out",
                str(cls.pub_path),
            ],
            check=True,
            capture_output=True,
        )
        cls.pem = cls.key_path.read_text()

    @classmethod
    def tearDownClass(cls) -> None:
        cls._tmp.cleanup()

    def test_signature_verifies_with_public_key(self) -> None:
        message = b"cabecera.cuerpo"
        signature = gsc.sign_rs256(message, self.pem)
        with tempfile.TemporaryDirectory() as tmp:
            data = pathlib.Path(tmp) / "data.bin"
            sig = pathlib.Path(tmp) / "sig.bin"
            data.write_bytes(message)
            sig.write_bytes(signature)
            result = subprocess.run(
                [
                    "openssl",
                    "dgst",
                    "-sha256",
                    "-verify",
                    str(self.pub_path),
                    "-signature",
                    str(sig),
                    str(data),
                ],
                capture_output=True,
            )
            self.assertEqual(result.returncode, 0, result.stderr.decode())

    def test_signature_is_deterministic(self) -> None:
        self.assertEqual(
            gsc.sign_rs256(b"igual", self.pem),
            gsc.sign_rs256(b"igual", self.pem),
        )

    def test_missing_openssl_raises(self) -> None:
        with mock.patch.object(gsc.shutil, "which", return_value=None):
            with self.assertRaises(gsc.GscError):
                gsc.sign_rs256(b"x", self.pem)


class TestTokenExchange(unittest.TestCase):
    """Canje del JWT por el token de acceso."""

    def test_fetch_access_token(self) -> None:
        creds = sample_creds()
        body = json.dumps({"access_token": "tok-123", "expires_in": 3600}).encode()
        transport = FakeTransport({("POST", creds["token_uri"]): (200, body)})
        with mock.patch.object(gsc, "sign_rs256", return_value=b"sig"):
            token = gsc.fetch_access_token(creds, transport=transport, now=1)
        self.assertEqual(token, "tok-123")
        call = transport.calls[0]
        self.assertIn(
            "application/x-www-form-urlencoded", call["headers"]["Content-Type"]
        )
        form = urllib.parse.parse_qs(call["body"].decode())
        self.assertEqual(
            form["grant_type"][0], "urn:ietf:params:oauth:grant-type:jwt-bearer"
        )
        self.assertIn("assertion", form)

    def test_error_status_raises_http_error(self) -> None:
        creds = sample_creds()
        transport = FakeTransport(
            {("POST", creds["token_uri"]): (400, b'{"error":"x"}')}
        )
        with mock.patch.object(gsc, "sign_rs256", return_value=b"sig"):
            with self.assertRaises(gsc.GscHttpError) as ctx:
                gsc.fetch_access_token(creds, transport=transport)
        self.assertEqual(ctx.exception.status, 400)

    def test_missing_token_raises(self) -> None:
        creds = sample_creds()
        transport = FakeTransport({("POST", creds["token_uri"]): (200, b"{}")})
        with mock.patch.object(gsc, "sign_rs256", return_value=b"sig"):
            with self.assertRaises(gsc.GscError):
                gsc.fetch_access_token(creds, transport=transport)


class TestSearchAnalytics(unittest.TestCase):
    """Consulta ``searchanalytics.query``."""

    def test_query_encodes_site_and_returns_rows(self) -> None:
        transport = FakeTransport(
            {("POST", gsc.SEARCH_ANALYTICS_URL): (200, load_fixture("queries.json"))}
        )
        rows = gsc.search_analytics(
            "https://atareao.es/",
            date(2026, 1, 1),
            date(2026, 1, 28),
            ["query"],
            5,
            token="tok",
            transport=transport,
        )
        self.assertEqual(rows[0]["keys"], ["kitty terminal"])
        call = transport.calls[0]
        self.assertIn("%2F", call["url"])
        self.assertNotIn("https://atareao.es/", call["url"])
        payload = json.loads(call["body"])
        self.assertEqual(payload["dimensions"], ["query"])
        self.assertEqual(payload["rowLimit"], 5)
        self.assertEqual(payload["startDate"], "2026-01-01")
        self.assertEqual(payload["endDate"], "2026-01-28")
        self.assertEqual(call["headers"]["Authorization"], "Bearer tok")
        self.assertEqual(call["headers"]["Content-Type"], "application/json")

    def test_empty_rows(self) -> None:
        transport = FakeTransport({("POST", gsc.SEARCH_ANALYTICS_URL): (200, b"{}")})
        rows = gsc.search_analytics(
            "https://atareao.es/",
            date(2026, 1, 1),
            date(2026, 1, 28),
            token="tok",
            transport=transport,
        )
        self.assertEqual(rows, [])

    def test_http_error_raises(self) -> None:
        """Un 5xx de ``searchanalytics.query`` se convierte en ``GscHttpError``."""
        transport = FakeTransport({("POST", gsc.SEARCH_ANALYTICS_URL): (500, b"boom")})
        with self.assertRaises(gsc.GscHttpError) as ctx:
            gsc.search_analytics(
                "https://atareao.es/",
                date(2026, 1, 1),
                date(2026, 1, 28),
                token="tok",
                transport=transport,
            )
        self.assertEqual(ctx.exception.status, 500)

    def test_invalid_json_raises(self) -> None:
        """Un cuerpo que no es JSON válido se rechaza con ``GscError``."""
        transport = FakeTransport(
            {("POST", gsc.SEARCH_ANALYTICS_URL): (200, b"<html>no json</html>")}
        )
        with self.assertRaises(gsc.GscError):
            gsc.search_analytics(
                "https://atareao.es/",
                date(2026, 1, 1),
                date(2026, 1, 28),
                token="tok",
                transport=transport,
            )


class TestSitemapsApi(unittest.TestCase):
    """Consulta ``sitemaps.list`` y descarga de huellas."""

    def test_list_sitemaps(self) -> None:
        transport = FakeTransport(
            {("GET", gsc.SITEMAPS_URL): (200, load_fixture("sitemaps.json"))}
        )
        sitemaps = gsc.list_sitemaps(
            "https://atareao.es/", token="tok", transport=transport
        )
        self.assertEqual(len(sitemaps), 2)
        self.assertEqual(sitemaps[0]["path"], "https://atareao.es/sitemap.xml")
        self.assertEqual(sitemaps[1]["path"], "https://atareao.es/sitemap_index.xml")

    def test_list_empty(self) -> None:
        transport = FakeTransport({("GET", gsc.SITEMAPS_URL): (200, b"{}")})
        self.assertEqual(
            gsc.list_sitemaps("https://atareao.es/", token="tok", transport=transport),
            [],
        )

    def test_http_error_raises(self) -> None:
        """Un 4xx de ``sitemaps.list`` se convierte en ``GscHttpError``."""
        transport = FakeTransport({("GET", gsc.SITEMAPS_URL): (403, b"denied")})
        with self.assertRaises(gsc.GscHttpError) as ctx:
            gsc.list_sitemaps("https://atareao.es/", token="tok", transport=transport)
        self.assertEqual(ctx.exception.status, 403)

    def test_invalid_json_raises(self) -> None:
        """Un cuerpo que no es JSON válido se rechaza con ``GscError``."""
        transport = FakeTransport({("GET", gsc.SITEMAPS_URL): (200, b"<<<no json>>>")})
        with self.assertRaises(gsc.GscError):
            gsc.list_sitemaps("https://atareao.es/", token="tok", transport=transport)

    def test_fetch_sitemap_hash_identical_files(self) -> None:
        body = load_fixture("sitemap.xml")
        transport = FakeTransport(
            {
                ("GET", "https://atareao.es/sitemap.xml"): (200, body),
                ("GET", "https://atareao.es/sitemap_index.xml"): (200, body),
            }
        )
        first = gsc.fetch_sitemap_hash(
            "https://atareao.es/sitemap.xml", transport=transport
        )
        second = gsc.fetch_sitemap_hash(
            "https://atareao.es/sitemap_index.xml", transport=transport
        )
        self.assertEqual(first, second)
        self.assertEqual(first, hashlib.sha256(body).hexdigest())

    def test_fetch_sitemap_hash_error(self) -> None:
        transport = FakeTransport({("GET", "https://x/s.xml"): (404, b"nope")})
        with self.assertRaises(gsc.GscHttpError):
            gsc.fetch_sitemap_hash("https://x/s.xml", transport=transport)


class TestFormatting(unittest.TestCase):
    """Formateo de tablas y orden por clics."""

    def test_table_columns_and_alignment(self) -> None:
        table = report.format_table(
            ["Consulta", "Clics", "Pos."],
            [["kitty terminal", "39", "4.3"], ["yt-dlp", "9", "8.7"]],
            [False, True, True],
        )
        lines = table.splitlines()
        self.assertEqual(lines[0].split(), ["Consulta", "Clics", "Pos."])
        self.assertEqual(lines[2].split(), ["kitty", "terminal", "39", "4.3"])
        self.assertEqual(lines[3].split(), ["yt-dlp", "9", "8.7"])
        self.assertTrue(set(lines[1]) <= {"-", " "})
        # La última columna está alineada a la derecha.
        self.assertEqual(lines[2].index("4.3"), lines[3].index("8.7"))

    def test_order_rows_by_clicks_desc(self) -> None:
        rows = [{"clicks": 1}, {"clicks": 9}, {"clicks": 5}]
        self.assertEqual(
            [row["clicks"] for row in report.order_rows_by_clicks(rows)], [9, 5, 1]
        )


class TestOpportunities(unittest.TestCase):
    """Heurística del informe de oportunidades."""

    def setUp(self) -> None:
        self.rows = json.loads(load_fixture("queries.json"))["rows"]

    def test_includes_low_ctr_high_impressions(self) -> None:
        terms = [row["keys"][0] for row in report.find_opportunities(self.rows)]
        self.assertIn("yt-dlp", terms)
        self.assertIn("niri", terms)
        self.assertNotIn("kitty terminal", terms)  # ctr 0.0229 >= 0.02
        self.assertNotIn("atareao con linux", terms)  # ctr alto
        self.assertNotIn("whatsapp linux", terms)  # ctr 0.0212 >= 0.02

    def test_sorted_desc_by_score(self) -> None:
        found = report.find_opportunities(self.rows)
        scores = [row["score"] for row in found]
        self.assertEqual(scores, sorted(scores, reverse=True))
        self.assertEqual(found[0]["keys"][0], "yt-dlp")

    def test_thresholds_are_adjustable(self) -> None:
        found = report.find_opportunities(self.rows, min_impressions=5000)
        self.assertEqual([row["keys"][0] for row in found], ["yt-dlp"])

    def test_position_bounds_are_enforced(self) -> None:
        """Una fila con volumen y CTR de oportunidad pero posición fuera de
        ``[min_position, max_position]`` no es candidata.

        Fija el filtro de posición: sin él (por ejemplo ``and True``) las filas de
        los extremos también aparecerían y la prueba fallaría.
        """
        rows = [
            {
                "keys": ["demasiado-arriba"],
                "impressions": 1000,
                "ctr": 0.0,
                "position": 2.9,
            },
            {"keys": ["dentro"], "impressions": 1000, "ctr": 0.0, "position": 10.0},
            {
                "keys": ["demasiado-abajo"],
                "impressions": 1000,
                "ctr": 0.0,
                "position": 20.1,
            },
        ]
        terms = [row["keys"][0] for row in report.find_opportunities(rows)]
        self.assertEqual(terms, ["dentro"])

    def test_position_limits_are_adjustable(self) -> None:
        """``min_position`` y ``max_position`` son parámetros ajustables."""
        rows = [{"keys": ["x"], "impressions": 1000, "ctr": 0.0, "position": 1.0}]
        self.assertEqual(report.find_opportunities(rows), [])
        found = report.find_opportunities(rows, min_position=1.0, max_position=2.0)
        self.assertEqual([row["keys"][0] for row in found], ["x"])

    def test_score_formula_is_exact(self) -> None:
        """Fija la fórmula literal del score.

        Cualquier función monótona distinta de
        ``impressions * (1 - ctr) * (1 - min(position, 20) / 20)`` (por ejemplo
        ``float(impressions)``) hace fallar esta prueba.
        """
        row = {"keys": ["x"], "impressions": 1000, "ctr": 0.01, "position": 8.0}
        found = report.find_opportunities([row])
        expected = 1000 * (1 - 0.01) * (1 - 8.0 / 20.0)
        self.assertAlmostEqual(found[0]["score"], expected, places=9)

    def test_score_caps_position_at_twenty(self) -> None:
        """Con posición > 20 el factor de posición se satura en 0."""
        row = {"keys": ["x"], "impressions": 1000, "ctr": 0.0, "position": 30.0}
        found = report.find_opportunities([row], max_position=40.0)
        self.assertEqual(len(found), 1)
        self.assertAlmostEqual(found[0]["score"], 0.0, places=9)

    def test_threshold_boundaries(self) -> None:
        """Fija ``impressions >= min`` y ``ctr < max_ctr`` en sus fronteras."""
        at_ctr_limit = {
            "keys": ["ctr-limite"],
            "impressions": 300,
            "ctr": 0.02,
            "position": 10.0,
        }
        self.assertEqual(report.find_opportunities([at_ctr_limit]), [])

        just_below_ctr = {
            "keys": ["ctr-justo-debajo"],
            "impressions": 300,
            "ctr": 0.0199,
            "position": 10.0,
        }
        self.assertEqual(
            [row["keys"][0] for row in report.find_opportunities([just_below_ctr])],
            ["ctr-justo-debajo"],
        )

        below_impressions = {
            "keys": ["pocas-impresiones"],
            "impressions": 299,
            "ctr": 0.0,
            "position": 10.0,
        }
        self.assertEqual(report.find_opportunities([below_impressions]), [])


class TestCompare(unittest.TestCase):
    """Comparativa entre periodos."""

    def test_variations(self) -> None:
        current = json.loads(load_fixture("summary.json"))
        previous = json.loads(load_fixture("summary_previous.json"))
        result = report.compare_periods(current, previous)
        self.assertEqual(result["current"]["clicks"], 4935)
        self.assertEqual(result["previous"]["clicks"], 4610)
        self.assertEqual(result["clicks"]["absolute"], 325)
        self.assertAlmostEqual(result["clicks"]["percent"], 325 / 4610, places=6)
        self.assertEqual(result["impressions"]["absolute"], 22300)
        self.assertAlmostEqual(
            result["impressions"]["percent"], 22300 / 371200, places=6
        )

    def test_previous_zero_percent_is_none(self) -> None:
        current = {
            "rows": [{"clicks": 10, "impressions": 20, "ctr": 0.5, "position": 1.0}]
        }
        previous = {
            "rows": [{"clicks": 0, "impressions": 0, "ctr": 0.0, "position": 0.0}]
        }
        result = report.compare_periods(current, previous)
        self.assertIsNone(result["clicks"]["percent"])
        self.assertIsNone(result["impressions"]["percent"])
        self.assertEqual(result["clicks"]["absolute"], 10)

    def test_periods_without_rows_are_zero(self) -> None:
        """Un periodo con lista vacía y otro con la clave ausente cuentan como 0."""
        result = report.compare_periods({"rows": []}, {})
        self.assertEqual(result["current"]["clicks"], 0)
        self.assertEqual(result["current"]["impressions"], 0)
        self.assertEqual(result["previous"]["clicks"], 0)
        self.assertEqual(result["clicks"]["absolute"], 0)
        self.assertIsNone(result["clicks"]["percent"])
        self.assertIsNone(result["impressions"]["percent"])


class TestDateRanges(unittest.TestCase):
    """Rangos de fechas del periodo actual y del inmediatamente anterior."""

    def test_date_range(self) -> None:
        start, end = report.date_range(28, today=date(2026, 2, 1))
        self.assertEqual((start, end), (date(2026, 1, 5), date(2026, 2, 1)))

    def test_previous_date_range_is_contiguous_and_equal_length(self) -> None:
        start, end = report.previous_date_range(28, today=date(2026, 2, 1))
        self.assertEqual((start, end), (date(2025, 12, 8), date(2026, 1, 4)))
        current_start, _ = report.date_range(28, today=date(2026, 2, 1))
        self.assertEqual(end + timedelta(days=1), current_start)
        self.assertEqual((end - start).days + 1, 28)


class TestDefaultOutPath(unittest.TestCase):
    """Destino por defecto de los informes."""

    def test_respects_xdg_data_home(self) -> None:
        with tempfile.TemporaryDirectory() as tmp:
            with mock.patch.dict(os.environ, {"XDG_DATA_HOME": tmp}):
                path = report.default_out_path(
                    "oportunidades", today=date(2026, 11, 14)
                )
            self.assertEqual(
                path,
                pathlib.Path(tmp) / "atareao" / "gsc" / "oportunidades-2026-11-14.md",
            )

    def test_falls_back_to_local_share(self) -> None:
        with (
            mock.patch.dict(os.environ, {}, clear=True),
            mock.patch.object(
                report.Path, "home", return_value=pathlib.Path("/home/demo")
            ),
        ):
            path = report.default_out_path("resumen", today=date(2026, 1, 2))
        self.assertEqual(
            path,
            pathlib.Path("/home/demo/.local/share/atareao/gsc/resumen-2026-01-02.md"),
        )


class TestResolveOutPath(unittest.TestCase):
    """Resolución de ``--out``: fichero explícito o directorio de datos."""

    def test_none_returns_none(self) -> None:
        self.assertIsNone(report.resolve_out_path(None, "oportunidades"))
        self.assertIsNone(report.resolve_out_path("", "oportunidades"))

    def test_existing_directory_places_report_inside_it(self) -> None:
        """Un directorio existente se respeta: el informe va dentro, con nombre fechado."""
        with tempfile.TemporaryDirectory() as tmp:
            directory = pathlib.Path(tmp)
            path = report.resolve_out_path(tmp, "oportunidades")
            self.assertEqual(
                path,
                directory / f"oportunidades-{date.today().isoformat()}.md",
            )

    def test_existing_directory_with_trailing_slash_places_report_inside_it(
        self,
    ) -> None:
        """Un directorio existente con barra final se respeta igual que sin barra."""
        with tempfile.TemporaryDirectory() as tmp:
            directory = pathlib.Path(tmp)
            path = report.resolve_out_path(f"{tmp}{os.sep}", "oportunidades")
            self.assertEqual(
                path,
                directory / f"oportunidades-{date.today().isoformat()}.md",
            )

    def test_inexistent_directory_with_trailing_slash_places_report_inside_it(
        self,
    ) -> None:
        """Un directorio inexistente con barra final se usa como destino del informe.

        No se crea aquí: lo hará :func:`write_markdown` al escribir.
        """
        with tempfile.TemporaryDirectory() as tmp:
            inexistent = pathlib.Path(tmp) / "aun-no-existe"
            path = report.resolve_out_path(f"{inexistent}{os.sep}", "oportunidades")
            self.assertEqual(
                path,
                inexistent / f"oportunidades-{date.today().isoformat()}.md",
            )
            self.assertFalse(inexistent.exists())

    def test_file_value_is_respected(self) -> None:
        with tempfile.TemporaryDirectory() as tmp:
            target = pathlib.Path(tmp) / "informe.md"
            self.assertEqual(
                report.resolve_out_path(str(target), "oportunidades"), target
            )


class TestSitemapAudit(unittest.TestCase):
    """Auditoría de sitemaps con los fixtures grabados."""

    def _client(self) -> report.Client:
        transport = FakeTransport(
            {
                ("GET", gsc.SITEMAPS_URL): (200, load_fixture("sitemaps.json")),
                ("GET", "https://atareao.es/sitemap.xml"): (
                    200,
                    load_fixture("sitemap.xml"),
                ),
                ("GET", "https://atareao.es/sitemap_index.xml"): (
                    200,
                    load_fixture("sitemap_index.xml"),
                ),
                ("GET", "https://atareao.es/robots.txt"): (
                    200,
                    load_fixture("robots.txt"),
                ),
            }
        )
        return report.Client(site=gsc.DEFAULT_SITE, token="tok", transport=transport)

    def test_duplicate_and_misleading_index(self) -> None:
        findings = report.collect_sitemap_audit(self._client())
        by_path = {finding["path"]: finding for finding in findings}

        index = by_path["https://atareao.es/sitemap_index.xml"]
        plain = by_path["https://atareao.es/sitemap.xml"]

        self.assertTrue(plain["duplicate"])
        self.assertTrue(index["duplicate"])
        self.assertFalse(plain["retire"])
        self.assertTrue(index["retire"])
        self.assertFalse(plain["misleading_index"])
        self.assertTrue(index["misleading_index"])
        self.assertFalse(index["declared"])

    def test_does_not_crawl_contained_urls(self) -> None:
        client = self._client()
        report.collect_sitemap_audit(client)
        urls = sorted(call["url"] for call in client.transport.calls)
        self.assertEqual(
            urls,
            sorted(
                [
                    gsc.SITEMAPS_URL,
                    "https://atareao.es/sitemap.xml",
                    "https://atareao.es/sitemap_index.xml",
                    "https://atareao.es/robots.txt",
                ]
            ),
        )

    def test_contains_sitemap_reference(self) -> None:
        self.assertFalse(report.contains_sitemap_reference(load_fixture("sitemap.xml")))
        self.assertTrue(
            report.contains_sitemap_reference(
                b"<sitemapindex><sitemap><loc>x</loc></sitemap></sitemapindex>"
            )
        )

    def test_parse_robots_sitemaps(self) -> None:
        declared = report.parse_robots_sitemaps(load_fixture("robots.txt").decode())
        self.assertEqual(declared, ["https://atareao.es/sitemap.xml"])

    def test_duplicates_without_declared_keep_both(self) -> None:
        """Sin ningún duplicado declarado en robots.txt se conservan los dos.

        Cubre la rama ``not any(other in declared_set …)`` de la elección de
        candidato a retirar: simplificarla a ``keep = is_declared`` marcaría ambos
        como candidatos a retirar y esta prueba fallaría.
        """
        sitemaps = [
            {"path": "https://atareao.es/a.xml"},
            {"path": "https://atareao.es/b.xml"},
        ]
        fingerprints = {
            "https://atareao.es/a.xml": "misma-huella",
            "https://atareao.es/b.xml": "misma-huella",
        }
        findings = report.summarize_sitemaps(sitemaps, fingerprints, {}, [])
        self.assertTrue(all(finding["duplicate"] for finding in findings))
        self.assertFalse(any(finding["retire"] for finding in findings))

    def test_declared_duplicate_is_kept_and_other_retires(self) -> None:
        sitemaps = [
            {"path": "https://atareao.es/a.xml"},
            {"path": "https://atareao.es/b.xml"},
        ]
        fingerprints = {
            "https://atareao.es/a.xml": "misma-huella",
            "https://atareao.es/b.xml": "misma-huella",
        }
        declared = ["https://atareao.es/a.xml"]
        by_path = {
            finding["path"]: finding
            for finding in report.summarize_sitemaps(
                sitemaps, fingerprints, {}, declared
            )
        }
        self.assertFalse(by_path["https://atareao.es/a.xml"]["retire"])
        self.assertTrue(by_path["https://atareao.es/b.xml"]["retire"])

    def test_genuine_index_is_not_misleading(self) -> None:
        """Un fichero con nombre de índice que sí referencia otros sitemaps no se
        marca como engañoso."""
        sitemaps = [{"path": "https://atareao.es/sitemap_index.xml"}]
        refs = {"https://atareao.es/sitemap_index.xml": True}
        findings = report.summarize_sitemaps(sitemaps, {}, refs, [])
        self.assertFalse(findings[0]["misleading_index"])

    def test_name_containing_index_but_not_declaring_one(self) -> None:
        """``reindex.xml`` contiene "index" pero no declara un índice: no se señala."""
        sitemaps = [{"path": "https://atareao.es/reindex.xml"}]
        findings = report.summarize_sitemaps(sitemaps, {}, {}, [])
        self.assertFalse(findings[0]["misleading_index"])

    def test_declares_sitemap_index_helper(self) -> None:
        self.assertTrue(report.declares_sitemap_index("sitemap_index.xml"))
        self.assertTrue(report.declares_sitemap_index("index.xml"))
        self.assertTrue(report.declares_sitemap_index("sitemap-index.xml"))
        self.assertFalse(report.declares_sitemap_index("reindex.xml"))
        self.assertFalse(report.declares_sitemap_index("sitemap.xml"))

    def test_robots_unreachable_keeps_going(self) -> None:
        """Un ``robots.txt`` inaccesible no aborta la auditoría: no hay declarados."""
        transport = FakeTransport(
            {
                ("GET", gsc.SITEMAPS_URL): (200, load_fixture("sitemaps.json")),
                ("GET", "https://atareao.es/sitemap.xml"): (
                    200,
                    load_fixture("sitemap.xml"),
                ),
                ("GET", "https://atareao.es/sitemap_index.xml"): (
                    200,
                    load_fixture("sitemap_index.xml"),
                ),
                ("GET", "https://atareao.es/robots.txt"): (404, b"no such file"),
            }
        )
        client = report.Client(site=gsc.DEFAULT_SITE, token="tok", transport=transport)
        findings = report.collect_sitemap_audit(client)
        self.assertTrue(findings)
        self.assertTrue(all(not finding["declared"] for finding in findings))


class TestCli(unittest.TestCase):
    """Integración de la CLI con un cliente falso (sin red)."""

    def _client(
        self, responses: dict[tuple[str, str], tuple[int, bytes]]
    ) -> report.Client:
        return report.Client(
            site=gsc.DEFAULT_SITE, token="tok", transport=FakeTransport(responses)
        )

    def test_queries_json_is_valid(self) -> None:
        client = self._client(
            {("POST", gsc.SEARCH_ANALYTICS_URL): (200, load_fixture("queries.json"))}
        )
        with mock.patch.object(report, "build_client", return_value=client):
            out = io.StringIO()
            with contextlib.redirect_stdout(out):
                code = report.main(["queries", "--json", "--rows", "5"])
        self.assertEqual(code, 0)
        payload = json.loads(out.getvalue())
        self.assertEqual(payload["rows"][0]["keys"], ["kitty terminal"])

    def test_queries_sorted_by_clicks(self) -> None:
        client = self._client(
            {("POST", gsc.SEARCH_ANALYTICS_URL): (200, load_fixture("queries.json"))}
        )
        with mock.patch.object(report, "build_client", return_value=client):
            out = io.StringIO()
            with contextlib.redirect_stdout(out):
                code = report.main(["queries"])
        self.assertEqual(code, 0)
        text = out.getvalue()
        self.assertLess(text.index("kitty terminal"), text.index("yt-dlp"))

    def test_out_writes_markdown(self) -> None:
        client = self._client(
            {("POST", gsc.SEARCH_ANALYTICS_URL): (200, load_fixture("summary.json"))}
        )
        with tempfile.TemporaryDirectory() as tmp:
            target = pathlib.Path(tmp) / "resumen.md"
            with mock.patch.object(report, "build_client", return_value=client):
                with contextlib.redirect_stdout(io.StringIO()):
                    code = report.main(["resumen", "--out", str(target)])
            self.assertEqual(code, 0)
            content = target.read_text()
            self.assertIn("# ", content)
            self.assertIn("Periodo", content)

    def test_out_accepts_directory_with_dated_name(self) -> None:
        client = self._client(
            {("POST", gsc.SEARCH_ANALYTICS_URL): (200, load_fixture("queries.json"))}
        )
        with tempfile.TemporaryDirectory() as tmp:
            directory = pathlib.Path(tmp)
            with mock.patch.object(report, "build_client", return_value=client):
                with contextlib.redirect_stdout(io.StringIO()):
                    code = report.main(["oportunidades", "--out", f"{tmp}{os.sep}"])
            self.assertEqual(code, 0)
            expected = directory / f"oportunidades-{date.today().isoformat()}.md"
            self.assertTrue(expected.is_file())
            self.assertIn("# Oportunidades", expected.read_text())

    def test_oportunidades_does_not_write_by_default(self) -> None:
        client = self._client(
            {("POST", gsc.SEARCH_ANALYTICS_URL): (200, load_fixture("queries.json"))}
        )
        with mock.patch.object(report, "build_client", return_value=client):
            with mock.patch.object(
                report,
                "default_out_path",
                side_effect=AssertionError("no debe escribir fichero por defecto"),
            ):
                with contextlib.redirect_stdout(io.StringIO()):
                    code = report.main(["oportunidades"])
        self.assertEqual(code, 0)

    def test_error_returns_one(self) -> None:
        with mock.patch.object(
            report,
            "build_client",
            side_effect=gsc.GscCredentialError("fallo controlado"),
        ):
            err = io.StringIO()
            with contextlib.redirect_stderr(err):
                code = report.main(["resumen"])
        self.assertEqual(code, 1)
        self.assertIn("fallo controlado", err.getvalue())

    @staticmethod
    def _summary_responses() -> dict[tuple[str, str], tuple[int, bytes]]:
        return {("POST", gsc.SEARCH_ANALYTICS_URL): (200, load_fixture("summary.json"))}

    @staticmethod
    def _sitemap_responses() -> dict[tuple[str, str], tuple[int, bytes]]:
        return {
            ("GET", gsc.SITEMAPS_URL): (200, load_fixture("sitemaps.json")),
            ("GET", "https://atareao.es/sitemap.xml"): (
                200,
                load_fixture("sitemap.xml"),
            ),
            ("GET", "https://atareao.es/sitemap_index.xml"): (
                200,
                load_fixture("sitemap_index.xml"),
            ),
            ("GET", "https://atareao.es/robots.txt"): (200, load_fixture("robots.txt")),
        }

    def test_resumen_json_is_valid(self) -> None:
        client = self._client(self._summary_responses())
        with mock.patch.object(report, "build_client", return_value=client):
            out = io.StringIO()
            with contextlib.redirect_stdout(out):
                code = report.main(["resumen", "--json"])
        self.assertEqual(code, 0)
        payload = json.loads(out.getvalue())
        self.assertIn("period", payload)
        self.assertEqual(payload["metrics"]["clicks"], 4935)

    def test_compara_json_is_valid(self) -> None:
        client = self._client(self._summary_responses())
        with mock.patch.object(report, "build_client", return_value=client):
            out = io.StringIO()
            with contextlib.redirect_stdout(out):
                code = report.main(["compara", "--json"])
        self.assertEqual(code, 0)
        payload = json.loads(out.getvalue())
        self.assertIn("previous_period", payload)
        self.assertEqual(payload["current"]["clicks"], 4935)
        self.assertIn("absolute", payload["clicks"])

    def test_sitemaps_json_is_valid(self) -> None:
        client = self._client(self._sitemap_responses())
        with mock.patch.object(report, "build_client", return_value=client):
            out = io.StringIO()
            with contextlib.redirect_stdout(out):
                code = report.main(["sitemaps", "--json"])
        self.assertEqual(code, 0)
        payload = json.loads(out.getvalue())
        paths = [entry["path"] for entry in payload["sitemaps"]]
        self.assertIn("https://atareao.es/sitemap_index.xml", paths)

    def test_rows_must_be_positive(self) -> None:
        """``--rows`` se valida: un valor no positivo no llega a la API."""
        err = io.StringIO()
        with contextlib.redirect_stderr(err):
            with self.assertRaises(SystemExit):
                report.main(["queries", "--rows", "0"])
        self.assertIn("filas", err.getvalue())

    def test_rows_rejects_non_integer(self) -> None:
        err = io.StringIO()
        with contextlib.redirect_stderr(err):
            with self.assertRaises(SystemExit):
                report.main(["queries", "--rows", "muchas"])
        self.assertIn("entero", err.getvalue())


if __name__ == "__main__":
    unittest.main()
