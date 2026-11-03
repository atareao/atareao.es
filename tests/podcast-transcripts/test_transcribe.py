"""Unit tests for the podcast-transcripts pipeline module (``scripts/podcast-transcripts/transcribe.py``).

Red phase of TDD: the module under test does not exist yet, so importing it fails.
These tests describe the frozen contract that the implementation must satisfy.

Only the Python standard library is used (``unittest``, ``sys``, ``os``, ``tempfile``,
``pathlib``); the repository has no package manager.

Scenarios covered come from the canonical specs in
``openspec/specs/podcast-transcripts/spec.md`` and the change deltas in
``openspec/changes/podcast-transcripts/specs/podcast-transcripts/spec.md`` plus
``openspec/changes/podcast-transcribe-diagnostics/specs/podcast-transcripts/spec.md``:
 - Validez del SubRip producido (bloques consecutivos, tiempos crecientes, UTF-8)
 - Idempotencia y reanudación (un episodio ya transcrito se salta)
 - Omisión de episodios sin audio utilizable
 - Ejecución acotada y alcance (ensayo, límite, solo podcasts publicados)
 - Diagnóstico utilizable de un fallo de transcripción (change
   ``podcast-transcribe-diagnostics``)
"""

import argparse
import contextlib
import io
import os
import pathlib
import subprocess
import sys
import tempfile
import unittest
from unittest import mock

REPO_ROOT = pathlib.Path(__file__).resolve().parents[2]
sys.path.insert(0, str(REPO_ROOT / "scripts" / "podcast-transcripts"))

# The module under test does not exist yet: this import MUST fail in the RED phase.
import transcribe  # noqa: E402


def make_record(
    rec_id,
    post_type="podcast",
    status="publish",
    mp3_url="https://anchor.fm/atareao/episodes/episodio.mp3",
    transcript_id=0,
):
    """Build a flat record dict shaped exactly like the JSON coming from WordPress."""
    return {
        "id": rec_id,
        "post_type": post_type,
        "status": status,
        "mp3_url": mp3_url,
        "transcript_id": transcript_id,
    }


def make_args(work_dir, **overrides):
    """Build the ``argparse.Namespace`` that ``run_pipeline`` expects."""
    values = {
        "limit": None,
        "dry_run": False,
        "episode": None,
        "model": transcribe.DEFAULT_MODEL,
        "language": transcribe.DEFAULT_LANGUAGE,
        "work_dir": str(work_dir),
        "keep_audio": False,
        "ssh_host": transcribe.DEFAULT_SSH_HOST,
        "wpcli_container": transcribe.DEFAULT_WPCLI_CONTAINER,
        "include_drafts": False,
    }
    values.update(overrides)
    return argparse.Namespace(**values)


class TimestampFormattingTest(unittest.TestCase):
    """``format_timestamp`` produces ``HH:MM:SS,mmm`` with a comma decimal separator."""

    def test_format_timestamp(self):
        cases = [
            (0.0, "00:00:00,000"),
            (61.5, "00:01:01,500"),
            (3661.001, "01:01:01,001"),
            (360000.0, "100:00:00,000"),  # hours may exceed two digits
        ]
        for seconds, expected in cases:
            with self.subTest(seconds=seconds):
                self.assertEqual(transcribe.format_timestamp(seconds), expected)


class SrtRenderingTest(unittest.TestCase):
    """``render_srt`` + ``validate_srt``: SubRip validity of the produced file."""

    @staticmethod
    def _assert_valid_timestamp_string(test, value):
        """Assert ``value`` matches ``HH:MM:SS,mmm`` with hours >= 2 digits."""
        parts = value.split(":")
        test.assertEqual(len(parts), 3, f"formato de tiempo inválido: {value!r}")
        hours, minutes, rest = parts
        test.assertGreaterEqual(len(hours), 2, f"horas sin dos dígitos: {value!r}")
        test.assertTrue(hours.isdigit(), f"horas no numéricas: {value!r}")
        test.assertEqual(len(minutes), 2, f"minutos sin dos dígitos: {value!r}")
        test.assertTrue(minutes.isdigit(), f"minutos no numéricos: {value!r}")
        seconds, comma, millis = rest.partition(",")
        test.assertEqual(comma, ",", f"falta la coma decimal: {value!r}")
        test.assertEqual(len(seconds), 2, f"segundos sin dos dígitos: {value!r}")
        test.assertTrue(seconds.isdigit(), f"segundos no numéricos: {value!r}")
        test.assertEqual(len(millis), 3, f"milisegundos sin tres dígitos: {value!r}")
        test.assertTrue(millis.isdigit(), f"milisegundos no numéricos: {value!r}")

    def test_render_produces_consecutive_numbered_blocks(self):
        cues = [
            (0.0, 1.0, "primero"),
            (1.0, 2.5, "segundo"),
            (2.5, 4.0, "tercero"),
        ]
        parsed = transcribe.validate_srt(transcribe.render_srt(cues))
        self.assertEqual([cue[0] for cue in parsed], [1, 2, 3])

    def test_timestamps_are_well_formed_and_non_decreasing(self):
        cues = [
            (0.0, 1.0, "uno"),
            (1.0, 2.5, "dos"),
            (2.5, 4.0, "tres"),
        ]
        text = transcribe.render_srt(cues)

        # Every timing line follows the required format.
        for line in text.splitlines():
            if "-->" not in line:
                continue
            self.assertIn(" --> ", line)
            left, right = line.split(" --> ")
            self._assert_valid_timestamp_string(self, left.strip())
            self._assert_valid_timestamp_string(self, right.strip())

        # start <= end, and each block starts when or after the previous one ended.
        parsed = transcribe.validate_srt(text)
        previous_end = None
        for _index, start, end, _text in parsed:
            self.assertLessEqual(start, end)
            if previous_end is not None:
                self.assertGreaterEqual(start, previous_end)
            previous_end = end

    def test_utf8_characters_are_preserved(self):
        text_value = "canción de eñes y acentos: áéíóú ñ ¿?"
        cues = [(0.0, 2.0, text_value)]
        with tempfile.TemporaryDirectory() as tmp_dir:
            path = pathlib.Path(tmp_dir) / "episodio.srt"
            transcribe.write_srt(path, cues)

            raw = path.read_bytes()
            decoded = raw.decode("utf-8")
            self.assertIn(text_value, decoded)

            parsed = transcribe.validate_srt(decoded)
            self.assertEqual(len(parsed), 1)
            self.assertEqual(parsed[0][3], text_value)


class SrtValidationRejectsInvalidTest(unittest.TestCase):
    """``validate_srt`` raises ``SrtError`` on malformed SubRip text."""

    def test_rejects_numbering_gap(self):
        text = (
            "1\n"
            "00:00:01,000 --> 00:00:02,000\n"
            "uno\n"
            "\n"
            "3\n"
            "00:00:03,000 --> 00:00:04,000\n"
            "tres\n"
        )
        with self.assertRaises(transcribe.SrtError):
            transcribe.validate_srt(text)

    def test_rejects_malformed_timing_line(self):
        texts = [
            (
                "1\n"
                "00:00:01.000 --> 00:00:02.000\n"  # dot instead of comma
                "uno\n"
            ),
            (
                "1\n"
                "0:0:1,0 --> 0:0:2,0\n"  # missing zero padding
                "uno\n"
            ),
        ]
        for text in texts:
            with self.subTest(text=text):
                with self.assertRaises(transcribe.SrtError):
                    transcribe.validate_srt(text)

    def test_rejects_non_increasing_times(self):
        text = (
            "1\n"
            "00:00:01,000 --> 00:00:05,000\n"
            "uno\n"
            "\n"
            "2\n"
            "00:00:03,000 --> 00:00:06,000\n"  # starts before the previous one ends
            "dos\n"
        )
        with self.assertRaises(transcribe.SrtError):
            transcribe.validate_srt(text)

    def test_rejects_block_without_text(self):
        text = "1\n00:00:01,000 --> 00:00:02,000\n\n"
        with self.assertRaises(transcribe.SrtError):
            transcribe.validate_srt(text)


class IdempotencyTest(unittest.TestCase):
    """A podcast whose ``transcript_id`` is a positive integer is already done."""

    def test_classify_done_when_transcript_id_positive(self):
        record = make_record(18828, transcript_id=123)
        self.assertEqual(transcribe.classify(record), "done")

    def test_plan_skips_done_episodes(self):
        done = make_record(18828, transcript_id=123)
        pending = make_record(18829, transcript_id=0)
        plan = transcribe.plan_transcript_job([done, pending])

        self.assertEqual(plan["skipped_done"], 1)
        self.assertEqual([rec["id"] for rec in plan["to_process"]], [18829])


class NoAudioTest(unittest.TestCase):
    """Episodes without a usable mp3 URL are omitted and counted."""

    def test_classify_no_audio_for_unusable_urls(self):
        unusable = ["", "ftp://x/y.mp3", "no-es-una-url"]
        for mp3_url in unusable:
            with self.subTest(mp3_url=mp3_url):
                record = make_record(1, mp3_url=mp3_url)
                self.assertEqual(transcribe.classify(record), "no_audio")

    def test_is_usable_audio_url_rejects_unusable(self):
        unusable = ["", "ftp://x/y.mp3", "no-es-una-url", "https://"]
        for mp3_url in unusable:
            with self.subTest(mp3_url=mp3_url):
                self.assertFalse(transcribe.is_usable_audio_url(mp3_url))
        self.assertTrue(transcribe.is_usable_audio_url("https://anchor.fm/x.mp3"))

    def test_plan_counts_no_audio_episodes(self):
        without_audio = make_record(1, mp3_url="")
        other_without_audio = make_record(2, mp3_url="ftp://x/y.mp3")
        pending = make_record(3)
        plan = transcribe.plan_transcript_job(
            [without_audio, other_without_audio, pending]
        )

        self.assertEqual(plan["skipped_no_audio"], 2)
        self.assertEqual([rec["id"] for rec in plan["to_process"]], [3])


class ScopeTest(unittest.TestCase):
    """``select_episodes`` keeps only published podcasts, in input order."""

    def test_select_episodes_excludes_drafts_and_other_types(self):
        records = [
            make_record(1, status="draft"),
            make_record(2, post_type="post"),
            make_record(3),
            make_record(4, post_type="tutorial", status="publish"),
        ]
        selected = transcribe.select_episodes(records)
        self.assertEqual([rec["id"] for rec in selected], [3])


class PlanLimitTest(unittest.TestCase):
    """``plan_transcript_job`` respects the ``limit`` without touching the counters."""

    def test_limit_truncates_to_process_only(self):
        records = [make_record(i) for i in range(1, 6)]  # 5 pending
        unlimited = transcribe.plan_transcript_job(records)
        limited = transcribe.plan_transcript_job(records, limit=2)

        self.assertEqual(len(limited["to_process"]), 2)
        self.assertEqual(limited["skipped_done"], unlimited["skipped_done"])
        self.assertEqual(limited["skipped_no_audio"], unlimited["skipped_no_audio"])


class DryRunTest(unittest.TestCase):
    """The dry-run plan is pure: it plans the same but forbids writes."""

    def test_dry_run_forbids_writes_but_still_plans(self):
        records = [make_record(1), make_record(2)]
        plan = transcribe.plan_transcript_job(records, dry_run=True)

        self.assertIs(plan["writes_allowed"], False)
        self.assertEqual([rec["id"] for rec in plan["to_process"]], [1, 2])

        real = transcribe.plan_transcript_job(records, dry_run=False)
        self.assertTrue(real["writes_allowed"])

    def test_plan_has_exactly_the_contract_keys(self):
        plan = transcribe.plan_transcript_job([make_record(1)])
        self.assertEqual(
            set(plan.keys()),
            {"to_process", "skipped_done", "skipped_no_audio", "writes_allowed"},
        )


class EnsureValidSrtTest(unittest.TestCase):
    """``_ensure_valid_srt`` must translate every unreadable/unusable SRT into ``PipelineError``.

    Regression guards for defects B1 (non-UTF8 bytes) and B3 (empty SRT). Both currently
    escape as ``UnicodeDecodeError`` / silent success, which aborts the whole batch or
    links an empty transcript forever.
    """

    def _write(self, tmp_dir, raw_bytes):
        path = pathlib.Path(tmp_dir) / "18828.srt"
        path.write_bytes(raw_bytes)
        return path

    def test_non_utf8_srt_raises_pipeline_error(self):
        # B1: byte 0xF3 is not valid UTF-8; read_text raises UnicodeDecodeError (a
        # ValueError, NOT an OSError), so today it is not wrapped in PipelineError.
        raw = b"1\n00:00:01,000 --> 00:00:02,000\ncanci\xf3n\n"
        with tempfile.TemporaryDirectory() as tmp_dir:
            path = self._write(tmp_dir, raw)
            with self.assertRaises(transcribe.PipelineError):
                transcribe._ensure_valid_srt(path, 18828)

    def test_empty_or_blank_srt_raises_pipeline_error(self):
        # B3: validate_srt("") returns [] without raising, and the return is ignored,
        # so a zero-block SRT would be linked and marked done forever.
        for content in ["", "   \n\n\t\n \n"]:
            with self.subTest(content=content):
                with tempfile.TemporaryDirectory() as tmp_dir:
                    path = pathlib.Path(tmp_dir) / "18828.srt"
                    path.write_text(content, encoding="utf-8")
                    with self.assertRaises(transcribe.PipelineError):
                        transcribe._ensure_valid_srt(path, 18828)


class AudioUrlHardeningTest(unittest.TestCase):
    """Regression guards for defect B2: URLs with spaces/control chars must be rejected.

    ``urlopen`` raises ``http.client.InvalidURL`` (neither ``URLError`` nor ``OSError``)
    for such URLs, which escapes the ``download_audio`` wrapper and aborts the batch.
    """

    def test_is_usable_audio_url_rejects_whitespace_and_control_characters(self):
        unusable = [
            "http://127.0.0.1:9/a b",  # space
            "http://127.0.0.1:9/a\nb",  # newline
            "http://127.0.0.1:9/a\tb",  # tab
        ]
        for url in unusable:
            with self.subTest(url=url):
                self.assertFalse(transcribe.is_usable_audio_url(url))

    def test_download_audio_translates_invalid_url_to_pipeline_error(self):
        # Unroutable host + closed port: no real network traffic, fails fast.
        record = make_record(18828, mp3_url="http://127.0.0.1:9/a b")
        with tempfile.TemporaryDirectory() as tmp_dir:
            with self.assertRaises(transcribe.PipelineError):
                transcribe.download_audio(record, pathlib.Path(tmp_dir))


class BatchResilienceTest(unittest.TestCase):
    """Regression guard for defect I2: one malformed record must not abort the batch.

    ``int(record["id"])`` is evaluated outside the per-episode ``try``, so a record
    without a numeric ``id`` raises ``KeyError`` and kills the whole run.
    """

    def test_run_pipeline_continues_when_a_record_has_no_id(self):
        without_id = {
            "post_type": "podcast",
            "status": "publish",
            "mp3_url": "https://e/x.mp3",
            "transcript_id": 0,
        }
        valid = make_record(18829)

        with tempfile.TemporaryDirectory() as tmp_dir:
            work_dir = pathlib.Path(tmp_dir)
            # Pre-create a valid SRT for the good episode so no download/transcribe
            # (i.e. no network) is attempted: the loop resumes instead.
            srt_dir = work_dir / "srt"
            srt_dir.mkdir(parents=True)
            transcribe.write_srt(srt_dir / "18829.srt", [(0.0, 1.0, "hola")])

            args = argparse.Namespace(
                limit=None,
                dry_run=False,
                episode=None,
                model=transcribe.DEFAULT_MODEL,
                language=transcribe.DEFAULT_LANGUAGE,
                work_dir=tmp_dir,
                keep_audio=False,
                ssh_host=transcribe.DEFAULT_SSH_HOST,
                wpcli_container=transcribe.DEFAULT_WPCLI_CONTAINER,
            )

            with (
                mock.patch.object(
                    transcribe, "collect_episodes", return_value=[without_id, valid]
                ),
                mock.patch.object(
                    transcribe,
                    "upload_transcripts",
                    return_value={"18829": {"attachment_id": 4242}},
                ),
            ):
                # Must not raise: the bad record is counted as failed, the batch runs on.
                return_code = transcribe.run_pipeline(args)

            self.assertEqual(return_code, 1)


class SrtBomTest(unittest.TestCase):
    """Regression guard for defect M1: a UTF-8 BOM must not break validation."""

    def test_validate_srt_accepts_leading_bom(self):
        text = "\ufeff1\n00:00:01,000 --> 00:00:02,000\nhola\n"
        parsed = transcribe.validate_srt(text)
        self.assertEqual(len(parsed), 1)
        self.assertEqual(parsed[0][0], 1)
        self.assertEqual(parsed[0][3], "hola")


class TimingRangeTest(unittest.TestCase):
    """Regression guard for defect M2: minutes and seconds must stay below 60."""

    def test_rejects_minutes_or_seconds_at_60_or_more(self):
        invalid = [
            ("00:60:00,000", "00:60:59,000"),  # 60 minutes
            ("00:99:59,000", "01:00:00,000"),  # 99 minutes
            ("00:00:60,000", "00:00:61,000"),  # 60 seconds
            ("00:00:00,000", "00:00:99,999"),  # 99 seconds
        ]
        for start, end in invalid:
            with self.subTest(start=start, end=end):
                text = f"1\n{start} --> {end}\nuno\n"
                with self.assertRaises(transcribe.SrtError):
                    transcribe.validate_srt(text)

    def test_accepts_boundary_values_below_60(self):
        text = "1\n00:59:59,000 --> 01:00:00,000\nuno\n"
        parsed = transcribe.validate_srt(text)
        self.assertEqual(len(parsed), 1)


class NonFiniteTimestampTest(unittest.TestCase):
    """Regression guard for defect M4: non-finite floats must raise ``SrtError``."""

    def test_format_timestamp_rejects_non_finite_values(self):
        # nan -> ValueError, inf/-inf -> OverflowError today; all must be SrtError.
        for value in [float("nan"), float("inf"), float("-inf")]:
            with self.subTest(value=value):
                with self.assertRaises(transcribe.SrtError):
                    transcribe.format_timestamp(value)


class TranscriptionFailureDiagnosticsTest(unittest.TestCase):
    """Regression guard for defect D1: a transcription failure must carry a usable reason.

    ``transcribe_audio`` builds its reason **only** from ``_text(proc.stderr)`` and never
    reports the ``returncode`` (``transcribe.py:500``). But ``openai-whisper 20250625``
    writes **everything** to ``stdout`` and **nothing** to ``stderr`` (measured:
    ``stdout=23 025 B``, ``stderr=0 B``), so any failure that is not a Python exception
    is always reported as the empty label ``"sin detalle"``.

    Contract of the pure helper ``failure_detail(returncode, stderr, stdout, *,
    srt_present, max_lines=5)`` that ``transcribe_audio`` must use instead of
    ``tail = detail[-1] if detail else "sin detalle"``:

    1. ``returncode == 0`` and no ``srt_present`` -> an explicit message saying the
       command finished cleanly but left no SRT (distinguishable from a command failure).
    2. Otherwise the prefix is ``exit code {returncode}`` for ``returncode > 0``, the
       signal name (``signal.Signals(-returncode).name``) for ``returncode < 0``.
    3. The detail comes from the last ``max_lines`` non-empty ``stderr`` lines, falling
       back to ``stdout`` when ``stderr`` has no useful content; several lines survive.
    4. No output and no signal -> report the observed code, never invent a detail (and
       never the empty ``"sin detalle"`` label).

    Scenarios covered come from
    ``openspec/changes/podcast-transcribe-diagnostics/specs/podcast-transcripts/spec.md``:
     - Con la salida de error vacía, el detalle sale de la salida estándar
     - El código de salida se informa siempre
     - Una muerte por señal se informa como señal
     - El comando termina bien pero no deja el SRT
     - El motivo conserva varias líneas
     - Un fallo sin salida no inventa un detalle
    """

    # --- pure helper contract ------------------------------------------------

    def test_empty_stderr_falls_back_to_stdout(self):
        detail = transcribe.failure_detail(
            1, b"", b"linea A\nlinea B\n", srt_present=False
        )
        self.assertIn("linea B", detail)
        self.assertNotIn("sin detalle", detail)

    def test_exit_code_is_always_reported(self):
        detail = transcribe.failure_detail(3, b"", b"", srt_present=False)
        self.assertIn("3", detail)

    def test_signal_death_is_reported_as_signal(self):
        detail = transcribe.failure_detail(-9, b"", b"", srt_present=False)
        self.assertIn("SIGKILL", detail)
        self.assertNotIn("exit code", detail)

    def test_exit_zero_without_srt_is_explicit_and_distinguishable(self):
        detail = transcribe.failure_detail(0, b"", b"", srt_present=False)
        command_failure = transcribe.failure_detail(1, b"", b"", srt_present=False)
        self.assertIn("srt", detail.lower())
        self.assertNotEqual(detail, command_failure)

    def test_several_lines_are_kept_not_only_the_last(self):
        stderr = b"".join(f"linea {n}\n".encode() for n in range(1, 7))
        detail = transcribe.failure_detail(1, stderr, b"", srt_present=False)
        self.assertIn("linea 5", detail)
        self.assertIn("linea 6", detail)

    def test_failure_without_output_reports_the_code_without_inventing_detail(self):
        detail = transcribe.failure_detail(1, b"", b"", srt_present=False)
        self.assertIn("1", detail)
        self.assertNotIn("sin detalle", detail)

    def test_unknown_signal_number_is_reported_legibly(self):
        # -999 has no signal.Signals entry: must not raise and must not fall back to a
        # positive exit code.
        detail = transcribe.failure_detail(-999, b"", b"", srt_present=False)
        self.assertNotIn("exit code", detail)
        self.assertTrue(
            any(part.isdigit() for part in detail.split()),
            f"no se nombra la señal de forma legible: {detail!r}",
        )

    def test_zero_max_lines_does_not_claim_there_was_no_output(self):
        # max_lines=0 suppresses the detail, but the command DID write to stderr, so the
        # message must not assert that there was no output at all.
        detail = transcribe.failure_detail(
            1, b"x\n", b"", srt_present=False, max_lines=0
        )
        self.assertNotIn("sin salida del comando", detail.lower())

    # --- integration through transcribe_audio --------------------------------

    @staticmethod
    def _write_fake_whisper(tmp_dir, body):
        """Create an executable ``whisper`` stub in ``<tmp_dir>/bin``; return its dir.

        The stub replaces the real tool on ``PATH``: no GPU and no network are used.
        """
        bin_dir = pathlib.Path(tmp_dir) / "bin"
        bin_dir.mkdir()
        script = bin_dir / "whisper"
        script.write_text(body, encoding="utf-8")
        script.chmod(0o755)
        return bin_dir

    def _transcribe_with_fake(self, tmp_dir, body):
        """Run ``transcribe_audio`` against the ``whisper`` stub; return its message."""
        bin_dir = self._write_fake_whisper(tmp_dir, body)
        work = pathlib.Path(tmp_dir)
        audio_path = work / "18828.mp3"
        audio_path.write_bytes(b"\x00")
        srt_dir = work / "srt"
        record = make_record(18828)

        with mock.patch.dict(os.environ, {"PATH": str(bin_dir)}):
            with self.assertRaises(transcribe.PipelineError) as ctx:
                transcribe.transcribe_audio(record, audio_path, srt_dir, "turbo", "es")

        return str(ctx.exception)

    def test_transcribe_audio_reports_stdout_and_exit_code(self):
        body = (
            "#!/bin/sh\n"
            "echo 'traza por stdout: linea A'\n"
            "echo 'traza por stdout: linea B'\n"
            "exit 3\n"
        )
        with tempfile.TemporaryDirectory() as tmp_dir:
            message = self._transcribe_with_fake(tmp_dir, body)
        self.assertIn("traza por stdout: linea B", message)
        self.assertIn("3", message)

    def test_transcribe_audio_reports_signal_death(self):
        body = "#!/bin/sh\nkill -9 $$\n"
        with tempfile.TemporaryDirectory() as tmp_dir:
            message = self._transcribe_with_fake(tmp_dir, body)
        self.assertIn("SIGKILL", message)

    def test_transcribe_audio_reports_clean_exit_without_srt(self):
        body = "#!/bin/sh\necho 'terminado sin escribir srt'\nexit 0\n"
        with tempfile.TemporaryDirectory() as tmp_dir:
            message = self._transcribe_with_fake(tmp_dir, body)
        self.assertIn("srt", message.lower())
        # M-1: the stdout the stub did write must not be thrown away.
        self.assertIn("terminado sin escribir srt", message)


class SrtSanitizeTest(unittest.TestCase):
    """``_sanitize_srt`` descarta los cues vacíos del reconocedor y renumera.

    Escenarios de ``openspec/changes/transcript-srt-sanitize/specs/podcast-transcripts/spec.md``:
     - Los cues vacíos del reconocedor se descartan
     - El saneo no altera los bloques con texto
    """

    def _write(self, tmp_dir, text):
        path = pathlib.Path(tmp_dir) / "12096.srt"
        path.write_text(text, encoding="utf-8")
        return path

    def test_drops_empty_cues_and_renumbers(self):
        # Caso medido del episodio #12096: cues vacíos intercalados entre válidos.
        text = (
            "1\n00:00:01,000 --> 00:00:02,000\nhola\n"
            "\n"
            "2\n00:01:34,000 --> 00:02:34,000\n\n"  # texto vacío
            "\n"
            "3\n00:02:34,000 --> 00:02:35,000\n   \n"  # solo espacios
            "\n"
            "4\n00:02:35,000 --> 00:02:36,000\nmundo\n"
        )
        with tempfile.TemporaryDirectory() as tmp_dir:
            path = self._write(tmp_dir, text)
            kept = transcribe._sanitize_srt(path)
            self.assertEqual(kept, 2)

            parsed = transcribe.validate_srt(path.read_text(encoding="utf-8"))
            self.assertEqual([cue[0] for cue in parsed], [1, 2])
            self.assertEqual([cue[3] for cue in parsed], ["hola", "mundo"])

    def test_preserves_text_and_timestamps_of_surviving_blocks(self):
        text = (
            "1\n00:00:01,000 --> 00:00:02,000\nhola\n"
            "\n"
            "2\n00:00:02,000 --> 00:00:03,000\n   \n"
            "\n"
            "3\n00:00:03,000 --> 00:00:04,000\nmundo\n"
        )
        with tempfile.TemporaryDirectory() as tmp_dir:
            path = self._write(tmp_dir, text)
            transcribe._sanitize_srt(path)

            out = path.read_text(encoding="utf-8")
            self.assertIn("00:00:01,000 --> 00:00:02,000", out)
            self.assertIn("00:00:03,000 --> 00:00:04,000", out)
            parsed = transcribe.validate_srt(out)
            self.assertEqual([cue[3] for cue in parsed], ["hola", "mundo"])
            self.assertEqual(parsed[0][1:3], (1000, 2000))
            self.assertEqual(parsed[1][1:3], (3000, 4000))

    def test_sanitized_srt_with_text_is_linkable(self):
        text = (
            "1\n00:00:01,000 --> 00:00:02,000\nhola\n"
            "\n"
            "2\n00:00:02,000 --> 00:00:03,000\n\n"
        )
        with tempfile.TemporaryDirectory() as tmp_dir:
            path = self._write(tmp_dir, text)
            transcribe._sanitize_srt(path)
            # Un cue vacío ya no invalida el episodio entero.
            transcribe._ensure_valid_srt(path, 12096)


class NoSpeechDetectionTest(unittest.TestCase):
    """Un SRT sin ningún bloque con texto es "sin voz", no un fallo de transcripción."""

    def test_all_empty_srt_raises_no_speech(self):
        text = (
            "1\n00:00:01,000 --> 00:00:02,000\n\n"
            "\n"
            "2\n00:00:02,000 --> 00:00:03,000\n   \n"
        )
        with tempfile.TemporaryDirectory() as tmp_dir:
            path = pathlib.Path(tmp_dir) / "17378.srt"
            path.write_text(text, encoding="utf-8")
            with self.assertRaises(transcribe.NoSpeechError):
                transcribe._sanitize_srt(path)

    def test_no_speech_error_is_not_a_pipeline_failure(self):
        self.assertTrue(
            issubclass(transcribe.NoSpeechError, transcribe.TranscribeError)
        )
        self.assertFalse(issubclass(transcribe.NoSpeechError, transcribe.PipelineError))


class NoSpeechBatchSummaryTest(unittest.TestCase):
    """``run_pipeline`` informa sin voz como omitido, no como fallido."""

    def test_run_pipeline_counts_no_speech_as_omitted(self):
        with tempfile.TemporaryDirectory() as tmp_dir:
            work_dir = pathlib.Path(tmp_dir)
            srt_dir = work_dir / "srt"
            srt_dir.mkdir(parents=True)
            # SRT ya saneado hasta quedar sin ningún bloque con texto.
            (srt_dir / "17378.srt").write_text(
                "1\n00:00:01,000 --> 00:00:02,000\n\n", encoding="utf-8"
            )

            args = argparse.Namespace(
                limit=None,
                dry_run=False,
                episode=None,
                model=transcribe.DEFAULT_MODEL,
                language=transcribe.DEFAULT_LANGUAGE,
                work_dir=tmp_dir,
                keep_audio=False,
                ssh_host=transcribe.DEFAULT_SSH_HOST,
                wpcli_container=transcribe.DEFAULT_WPCLI_CONTAINER,
            )

            out = io.StringIO()
            with (
                mock.patch.object(
                    transcribe,
                    "collect_episodes",
                    return_value=[make_record(17378)],
                ),
                mock.patch.object(transcribe, "upload_transcripts") as upload,
                contextlib.redirect_stdout(out),
                contextlib.redirect_stderr(io.StringIO()),
            ):
                return_code = transcribe.run_pipeline(args)

            # Sin voz no es un fallo: el lote termina bien y no se sube nada.
            self.assertEqual(return_code, 0)
            self.assertIn("sin voz", out.getvalue())
            self.assertIn("fallidos  : 0", out.getvalue())
            upload.assert_not_called()


class AudibleFractionTest(unittest.TestCase):
    """``_audible_fraction``: aritmética pura ``(duración − silencio) / duración``.

    Escenario del delta ``transcript-srt-sanitize``: un audio prácticamente mudo se
    detecta midiendo el audio (fracción audible), no el SRT.
    """

    def test_no_silence_is_fully_audible(self):
        self.assertEqual(transcribe._audible_fraction(100.0, []), 1.0)

    def test_silence_covering_everything_is_near_zero(self):
        self.assertAlmostEqual(transcribe._audible_fraction(100.0, [(0.0, 100.0)]), 0.0)

    def test_partial_silence(self):
        # 40 s de silencio de 100 s -> 60 % audible.
        self.assertAlmostEqual(transcribe._audible_fraction(100.0, [(0.0, 40.0)]), 0.6)

    def test_open_final_interval_extends_to_duration(self):
        # ``None`` como fin = silencio abierto hasta el final: 4 s -> 10 s de 10 s.
        self.assertAlmostEqual(transcribe._audible_fraction(10.0, [(4.0, None)]), 0.4)

    def test_multiple_intervals_are_summed(self):
        self.assertAlmostEqual(
            transcribe._audible_fraction(100.0, [(10.0, 20.0), (50.0, 60.0)]), 0.8
        )

    def test_zero_duration_is_treated_as_silent(self):
        self.assertEqual(transcribe._audible_fraction(0.0, []), 0.0)


class ParseSilencedetectTest(unittest.TestCase):
    """``_parse_silencedetect`` extrae duración e intervalos de la salida de ffmpeg."""

    def test_parses_duration_and_closed_intervals(self):
        raw = (
            "  Duration: 00:18:25.60, start: 0.023021, bitrate: 131 kb/s\n"
            "[Parsed_silencedetect_0 @ 0x1] silence_start: 0\n"
            "[Parsed_silencedetect_0 @ 0x1] silence_end: 1.166833 "
            "| silence_duration: 1.166833\n"
            "[Parsed_silencedetect_0 @ 0x1] silence_start: 10.167875\n"
            "[Parsed_silencedetect_0 @ 0x1] silence_end: 1095.227083 "
            "| silence_duration: 1085.059208\n"
        )
        duration, intervals = transcribe._parse_silencedetect(raw)
        self.assertAlmostEqual(duration, 1105.6, places=2)
        self.assertEqual(intervals, [(0.0, 1.166833), (10.167875, 1095.227083)])

    def test_open_final_silence_keeps_none_end(self):
        raw = (
            "  Duration: 00:00:10.00, start: 0.0, bitrate: 1 kb/s\nsilence_start: 4.0\n"
        )
        duration, intervals = transcribe._parse_silencedetect(raw)
        self.assertAlmostEqual(duration, 10.0)
        self.assertEqual(intervals, [(4.0, None)])

    def test_missing_duration_raises(self):
        with self.assertRaises(transcribe.PipelineError):
            transcribe._parse_silencedetect("sin duración aquí\n")


class MeasureAudibleFractionTest(unittest.TestCase):
    """``measure_audible_fraction`` ejecuta ffmpeg de verdad (con un stub en PATH)."""

    @staticmethod
    def _write_fake_ffmpeg(tmp_dir, body):
        bin_dir = pathlib.Path(tmp_dir) / "bin"
        bin_dir.mkdir()
        script = bin_dir / "ffmpeg"
        script.write_text(body, encoding="utf-8")
        script.chmod(0o755)
        return bin_dir

    def test_measures_fraction_from_ffmpeg_stderr(self):
        body = (
            "#!/bin/sh\n"
            "echo '  Duration: 00:00:10.00, start: 0.0, bitrate: 1 kb/s' 1>&2\n"
            "echo 'silence_start: 0' 1>&2\n"
            "echo 'silence_end: 9.0 | silence_duration: 9.0' 1>&2\n"
            "exit 0\n"
        )
        with tempfile.TemporaryDirectory() as tmp_dir:
            bin_dir = self._write_fake_ffmpeg(tmp_dir, body)
            audio = pathlib.Path(tmp_dir) / "17378.mp3"
            audio.write_bytes(b"\x00")
            with mock.patch.dict(os.environ, {"PATH": str(bin_dir)}):
                fraction = transcribe.measure_audible_fraction(audio)
        self.assertAlmostEqual(fraction, 0.1)

    def test_ffmpeg_failure_raises_pipeline_error(self):
        body = "#!/bin/sh\necho 'no such file' 1>&2\nexit 1\n"
        with tempfile.TemporaryDirectory() as tmp_dir:
            bin_dir = self._write_fake_ffmpeg(tmp_dir, body)
            audio = pathlib.Path(tmp_dir) / "x.mp3"
            audio.write_bytes(b"\x00")
            with mock.patch.dict(os.environ, {"PATH": str(bin_dir)}):
                with self.assertRaises(transcribe.PipelineError):
                    transcribe.measure_audible_fraction(audio)


class AudioNoSpeechGateTest(unittest.TestCase):
    """El gate de audio decide *sin voz* antes de transcribir, sin lanzar whisper."""

    def test_silent_audio_is_omitted_without_transcribing(self):
        with tempfile.TemporaryDirectory() as tmp_dir:
            work_dir = pathlib.Path(tmp_dir)
            out = io.StringIO()
            with (
                mock.patch.object(
                    transcribe,
                    "collect_episodes",
                    return_value=[make_record(17378)],
                ),
                mock.patch.object(
                    transcribe,
                    "download_audio",
                    return_value=work_dir / "17378.mp3",
                ),
                mock.patch.object(
                    transcribe, "measure_audible_fraction", return_value=0.017
                ),
                mock.patch.object(transcribe, "transcribe_audio") as whisper,
                mock.patch.object(transcribe, "upload_transcripts") as upload,
                contextlib.redirect_stdout(out),
                contextlib.redirect_stderr(io.StringIO()),
            ):
                return_code = transcribe.run_pipeline(make_args(work_dir))

            self.assertEqual(return_code, 0)
            whisper.assert_not_called()  # no se gasta la transcripción
            upload.assert_not_called()  # ni adjunto ni meta
            self.assertIn("sin voz", out.getvalue())
            self.assertIn("fallidos  : 0", out.getvalue())

    def test_audible_audio_is_transcribed_normally(self):
        def fake_transcribe(record, audio_path, srt_dir, model, language):
            path = pathlib.Path(srt_dir) / f"{record['id']}.srt"
            transcribe.write_srt(path, [(0.0, 1.0, "hola")])
            return path

        def fake_preprocess(audio_path, out_dir):
            path = pathlib.Path(out_dir) / f"{audio_path.stem}.wav"
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_bytes(b"wav")
            return path

        with tempfile.TemporaryDirectory() as tmp_dir:
            work_dir = pathlib.Path(tmp_dir)
            out = io.StringIO()
            with (
                mock.patch.object(
                    transcribe,
                    "collect_episodes",
                    return_value=[make_record(18829)],
                ),
                mock.patch.object(
                    transcribe,
                    "download_audio",
                    return_value=work_dir / "18829.mp3",
                ),
                mock.patch.object(
                    transcribe, "measure_audible_fraction", return_value=1.0
                ),
                mock.patch.object(
                    transcribe, "preprocess_audio", side_effect=fake_preprocess
                ),
                mock.patch.object(
                    transcribe, "transcribe_audio", side_effect=fake_transcribe
                ) as whisper,
                mock.patch.object(
                    transcribe,
                    "upload_transcripts",
                    return_value={"18829": {"attachment_id": 4242}},
                ),
                contextlib.redirect_stdout(out),
                contextlib.redirect_stderr(io.StringIO()),
            ):
                return_code = transcribe.run_pipeline(make_args(work_dir))

            self.assertEqual(return_code, 0)
            whisper.assert_called_once()
            self.assertIn("resueltos : 1", out.getvalue())
            self.assertIn("sin voz: 0", out.getvalue())
            self.assertNotIn("audio sin voz", out.getvalue())


class AudioPreprocessTest(unittest.TestCase):
    """``preprocess_audio`` produce la copia normalizada y ``run_pipeline`` la usa.

    Escenarios del delta ``transcript-audio-preprocess``: el reconocedor recibe la copia
    (16 kHz, mono, ``pcm_s16le``, banda limitada), la copia se elimina al terminar el
    episodio, el original no se altera y la medición ``sin voz`` usa el original.
    """

    @staticmethod
    def _fake_run_command(args, **kwargs):
        pathlib.Path(args[-1]).write_bytes(b"wav")
        return subprocess.CompletedProcess(args, 0, b"", b"")

    @staticmethod
    def _fake_download(record, directory):
        path = pathlib.Path(directory) / f"{record['id']}.mp3"
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_bytes(b"mp3-bytes")
        return path

    @staticmethod
    def _fake_preprocess(audio_path, out_dir):
        path = pathlib.Path(out_dir) / f"{audio_path.stem}.wav"
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_bytes(b"wav")
        return path

    @staticmethod
    def _fake_transcribe(record, audio_path, srt_dir, model, language):
        path = pathlib.Path(srt_dir) / f"{record['id']}.srt"
        transcribe.write_srt(path, [(0.0, 1.0, "hola")])
        return path

    def test_preprocess_command_builds_expected_ffmpeg_args(self):
        with tempfile.TemporaryDirectory() as tmp_dir:
            work = pathlib.Path(tmp_dir)
            audio = work / "12096.mp3"
            audio.write_bytes(b"mp3")
            out_dir = work / "audio"
            with mock.patch.object(
                transcribe, "run_command", side_effect=self._fake_run_command
            ) as run:
                result = transcribe.preprocess_audio(audio, out_dir)

        args = run.call_args.args[0]
        self.assertEqual(args[args.index("-ar") + 1], "16000")
        self.assertEqual(args[args.index("-ac") + 1], "1")
        self.assertEqual(args[args.index("-c:a") + 1], "pcm_s16le")
        self.assertEqual(args[args.index("-af") + 1], "highpass=f=80,lowpass=f=8000")
        self.assertEqual(result, out_dir / "12096.wav")

    def test_preprocess_failure_raises_pipeline_error(self):
        with tempfile.TemporaryDirectory() as tmp_dir:
            work = pathlib.Path(tmp_dir)
            audio = work / "12096.mp3"
            audio.write_bytes(b"mp3")
            with mock.patch.object(
                transcribe,
                "run_command",
                return_value=subprocess.CompletedProcess([], 1, b"", b"boom"),
            ):
                with self.assertRaises(transcribe.PipelineError):
                    transcribe.preprocess_audio(audio, work / "audio")

    def test_downloaded_audio_is_not_altered(self):
        original = b"original-mp3-bytes"
        with tempfile.TemporaryDirectory() as tmp_dir:
            work = pathlib.Path(tmp_dir)
            audio = work / "12096.mp3"
            audio.write_bytes(original)
            with mock.patch.object(
                transcribe, "run_command", side_effect=self._fake_run_command
            ):
                result = transcribe.preprocess_audio(audio, work / "audio")

            self.assertEqual(audio.read_bytes(), original)
            self.assertEqual(result.suffix, ".wav")
            self.assertNotEqual(result, audio)

    def test_transcribe_audio_receives_preprocessed_copy(self):
        with tempfile.TemporaryDirectory() as tmp_dir:
            work_dir = pathlib.Path(tmp_dir)
            audio_dir = work_dir / "audio"
            out = io.StringIO()
            with (
                mock.patch.object(
                    transcribe,
                    "collect_episodes",
                    return_value=[make_record(18829)],
                ),
                mock.patch.object(
                    transcribe, "download_audio", side_effect=self._fake_download
                ),
                mock.patch.object(
                    transcribe, "measure_audible_fraction", return_value=1.0
                ) as measure,
                mock.patch.object(
                    transcribe, "preprocess_audio", side_effect=self._fake_preprocess
                ) as preprocess,
                mock.patch.object(
                    transcribe, "transcribe_audio", side_effect=self._fake_transcribe
                ) as whisper,
                mock.patch.object(
                    transcribe,
                    "upload_transcripts",
                    return_value={"18829": {"attachment_id": 1}},
                ),
                contextlib.redirect_stdout(out),
                contextlib.redirect_stderr(io.StringIO()),
            ):
                return_code = transcribe.run_pipeline(make_args(work_dir))

            self.assertEqual(return_code, 0)
            # 1.2: el reconocedor recibe la copia .wav, no el .mp3 descargado.
            sent = whisper.call_args.args[1]
            self.assertEqual(sent, audio_dir / "18829.wav")
            self.assertNotEqual(sent, audio_dir / "18829.mp3")
            # 1.5: el preprocesado parte del original y el gate mide el original.
            self.assertEqual(preprocess.call_args.args[0], audio_dir / "18829.mp3")
            self.assertEqual(measure.call_args.args[0], audio_dir / "18829.mp3")

    def test_temporary_copy_is_removed_at_episode_end(self):
        for keep_audio in (False, True):
            with self.subTest(keep_audio=keep_audio):
                with tempfile.TemporaryDirectory() as tmp_dir:
                    work_dir = pathlib.Path(tmp_dir)
                    audio_dir = work_dir / "audio"
                    with (
                        mock.patch.object(
                            transcribe,
                            "collect_episodes",
                            return_value=[make_record(18829)],
                        ),
                        mock.patch.object(
                            transcribe,
                            "download_audio",
                            side_effect=self._fake_download,
                        ),
                        mock.patch.object(
                            transcribe, "measure_audible_fraction", return_value=1.0
                        ),
                        mock.patch.object(
                            transcribe,
                            "preprocess_audio",
                            side_effect=self._fake_preprocess,
                        ),
                        mock.patch.object(
                            transcribe,
                            "transcribe_audio",
                            side_effect=self._fake_transcribe,
                        ),
                        mock.patch.object(
                            transcribe,
                            "upload_transcripts",
                            return_value={"18829": {"attachment_id": 1}},
                        ),
                        contextlib.redirect_stdout(io.StringIO()),
                        contextlib.redirect_stderr(io.StringIO()),
                    ):
                        return_code = transcribe.run_pipeline(
                            make_args(work_dir, keep_audio=keep_audio)
                        )

                    self.assertEqual(return_code, 0)
                    # 1.3: la copia .wav desaparece siempre.
                    self.assertFalse((audio_dir / "18829.wav").exists())
                    # el original solo se conserva con --keep-audio.
                    self.assertEqual((audio_dir / "18829.mp3").exists(), keep_audio)

    def test_preprocess_failure_marks_episode_failed(self):
        with tempfile.TemporaryDirectory() as tmp_dir:
            work_dir = pathlib.Path(tmp_dir)
            out = io.StringIO()
            with (
                mock.patch.object(
                    transcribe,
                    "collect_episodes",
                    return_value=[make_record(18829)],
                ),
                mock.patch.object(
                    transcribe, "download_audio", side_effect=self._fake_download
                ),
                mock.patch.object(
                    transcribe, "measure_audible_fraction", return_value=1.0
                ),
                mock.patch.object(
                    transcribe,
                    "preprocess_audio",
                    side_effect=transcribe.PipelineError("ffmpeg boom"),
                ),
                mock.patch.object(transcribe, "transcribe_audio") as whisper,
                mock.patch.object(transcribe, "upload_transcripts") as upload,
                contextlib.redirect_stdout(out),
                contextlib.redirect_stderr(io.StringIO()),
            ):
                return_code = transcribe.run_pipeline(make_args(work_dir))

            self.assertEqual(return_code, 1)
            whisper.assert_not_called()
            upload.assert_not_called()
            self.assertIn("resueltos : 0", out.getvalue())
            self.assertIn("fallidos  : 1", out.getvalue())


class IncludeDraftsTest(unittest.TestCase):
    """Opt-in ``--include-drafts``: drafts get collected, planned and uploaded.

    Change ``transcript-draft-episodes``: scope broadens from published-only to
    ``publish`` + ``draft`` when the flag is present. Without the flag the scope,
    the planning counters and the upload guard are unchanged; the only observable
    difference is that the helper command now always carries an explicit status CSV
    (``publish``), which the PHP helper treats as today's default.
    """

    # --- R1: CLI flag --------------------------------------------------------

    def test_parse_args_include_drafts_defaults_to_false(self):
        self.assertIs(transcribe.parse_args([]).include_drafts, False)

    def test_parse_args_include_drafts_flag_sets_true(self):
        self.assertIs(transcribe.parse_args(["--include-drafts"]).include_drafts, True)

    # --- R2: selection -------------------------------------------------------

    def test_select_episodes_excludes_drafts_by_default(self):
        records = [
            make_record(1, status="draft"),
            make_record(2, status="publish"),
        ]
        selected = transcribe.select_episodes(records)
        self.assertEqual([rec["id"] for rec in selected], [2])

    def test_select_episodes_with_include_drafts_keeps_podcast_drafts_only(self):
        records = [
            make_record(1, status="draft"),
            make_record(2, status="publish"),
            make_record(3, post_type="post", status="draft"),
            make_record(4, post_type="tutorial", status="draft"),
            make_record(5, post_type="podcast", status="trash"),
        ]
        selected = transcribe.select_episodes(records, include_drafts=True)
        self.assertEqual([rec["id"] for rec in selected], [1, 2])

    # --- R3: planning --------------------------------------------------------

    def test_plan_puts_podcast_draft_in_to_process_only_with_flag(self):
        draft = make_record(9, status="draft")

        without_flag = transcribe.plan_transcript_job([draft])
        self.assertEqual(without_flag["to_process"], [])

        with_flag = transcribe.plan_transcript_job([draft], include_drafts=True)
        self.assertEqual([rec["id"] for rec in with_flag["to_process"]], [9])

    # --- R4: collection command carries the status CSV -----------------------

    def _collect_command(self, *args, **kwargs):
        proc = subprocess.CompletedProcess([], 0, b"[]", b"")
        with (
            mock.patch.object(transcribe, "_push_bytes_to_container") as push,
            mock.patch.object(transcribe, "_run_ssh", return_value=proc) as run,
        ):
            transcribe.collect_episodes(
                "host", "container", transcribe.SCRIPTS_DIR, *args, **kwargs
            )
        push.assert_called_once()
        return run.call_args.args[1]

    def test_collect_episodes_defaults_to_publish_status(self):
        command = self._collect_command()
        self.assertIn("wp eval-file", command)
        self.assertTrue(command.endswith(" publish"), command)
        self.assertNotIn("draft", command)

    def test_collect_episodes_passes_statuses_csv(self):
        command = self._collect_command(statuses=("publish", "draft"))
        self.assertIn("wp eval-file", command)
        self.assertIn("publish,draft", command)

    # --- R5: upload command carries the allowed-status CSV -------------------

    def _upload_command(self, **kwargs):
        with tempfile.TemporaryDirectory() as tmp_dir:
            work = pathlib.Path(tmp_dir)
            srt = work / "7.srt"
            transcribe.write_srt(srt, [(0.0, 1.0, "hola")])
            proc = subprocess.CompletedProcess(
                [], 0, b'{"7": {"attachment_id": 1}}', b""
            )
            with (
                mock.patch.object(transcribe, "_push_directory"),
                mock.patch.object(transcribe, "_run_ssh", return_value=proc) as run,
            ):
                result = transcribe.upload_transcripts(
                    {7: srt},
                    ssh_host="host",
                    container="container",
                    staging_dir=work / "staging",
                    scripts_dir=transcribe.SCRIPTS_DIR,
                    **kwargs,
                )
            self.assertEqual(result, {"7": {"attachment_id": 1}})
        return run.call_args.args[1]

    def test_upload_transcripts_defaults_to_publish_status(self):
        command = self._upload_command()
        self.assertIn("manifest.json", command)
        self.assertTrue(command.endswith(" publish"), command)
        self.assertNotIn("draft", command)

    def test_upload_transcripts_passes_allowed_statuses_csv_as_second_arg(self):
        command = self._upload_command(allowed_statuses=("publish", "draft"))
        # The allowed-status CSV is the second positional argument after the manifest.
        self.assertIn("manifest.json publish,draft", command)

    # --- R6: run_pipeline propagation ---------------------------------------

    def _run_pipeline_spies(self, include_drafts):
        with tempfile.TemporaryDirectory() as tmp_dir:
            work_dir = pathlib.Path(tmp_dir)
            srt_dir = work_dir / "srt"
            srt_dir.mkdir(parents=True)
            episode_id = 7
            transcribe.write_srt(srt_dir / f"{episode_id}.srt", [(0.0, 1.0, "hola")])
            record = make_record(episode_id)
            plan = {
                "to_process": [record],
                "skipped_done": 0,
                "skipped_no_audio": 0,
                "writes_allowed": True,
            }
            args = make_args(work_dir, include_drafts=include_drafts)
            with (
                mock.patch.object(
                    transcribe, "collect_episodes", return_value=[record]
                ) as collect,
                mock.patch.object(
                    transcribe, "plan_transcript_job", return_value=plan
                ) as planner,
                mock.patch.object(
                    transcribe,
                    "upload_transcripts",
                    return_value={str(episode_id): {"attachment_id": 1}},
                ) as upload,
                contextlib.redirect_stdout(io.StringIO()),
                contextlib.redirect_stderr(io.StringIO()),
            ):
                return_code = transcribe.run_pipeline(args)

            self.assertEqual(return_code, 0)
            return collect, planner, upload

    def test_run_pipeline_propagates_drafts_when_enabled(self):
        collect, planner, upload = self._run_pipeline_spies(include_drafts=True)
        self.assertIn("draft", collect.call_args.kwargs["statuses"])
        self.assertIs(planner.call_args.kwargs["include_drafts"], True)
        self.assertIn("draft", upload.call_args.kwargs["allowed_statuses"])

    def test_run_pipeline_stays_publish_only_without_flag(self):
        collect, planner, upload = self._run_pipeline_spies(include_drafts=False)
        self.assertEqual(tuple(collect.call_args.kwargs["statuses"]), ("publish",))
        self.assertIs(planner.call_args.kwargs["include_drafts"], False)
        self.assertEqual(
            tuple(upload.call_args.kwargs["allowed_statuses"]), ("publish",)
        )

    def test_run_pipeline_processes_real_draft_record_when_enabled(self):
        # No plan spy here: the real planner must put the draft in ``to_process``.
        with tempfile.TemporaryDirectory() as tmp_dir:
            work_dir = pathlib.Path(tmp_dir)
            srt_dir = work_dir / "srt"
            srt_dir.mkdir(parents=True)
            episode_id = 424242
            transcribe.write_srt(srt_dir / f"{episode_id}.srt", [(0.0, 1.0, "hola")])
            draft = make_record(episode_id, status="draft")
            args = make_args(work_dir, include_drafts=True)
            out = io.StringIO()
            with (
                mock.patch.object(
                    transcribe, "collect_episodes", return_value=[draft]
                ) as collect,
                mock.patch.object(
                    transcribe,
                    "upload_transcripts",
                    return_value={str(episode_id): {"attachment_id": 1}},
                ) as upload,
                contextlib.redirect_stdout(out),
                contextlib.redirect_stderr(io.StringIO()),
            ):
                return_code = transcribe.run_pipeline(args)

            self.assertEqual(return_code, 0)
            self.assertIn("draft", collect.call_args.kwargs["statuses"])
            # El borrador real llega a procesarse y, por tanto, a subirse.
            upload.assert_called_once()
            uploaded_entries = upload.call_args.args[0]
            self.assertIn(episode_id, uploaded_entries)
            self.assertIn("draft", upload.call_args.kwargs["allowed_statuses"])
            self.assertIn("resueltos : 1", out.getvalue())

    # --- R7: draft idempotency ----------------------------------------------

    def test_draft_with_transcript_is_done_and_not_reprocessed(self):
        done_draft = make_record(9, status="draft", transcript_id=555)
        self.assertEqual(transcribe.classify(done_draft), "done")

        plan = transcribe.plan_transcript_job([done_draft], include_drafts=True)
        self.assertEqual(plan["skipped_done"], 1)
        self.assertEqual(plan["to_process"], [])

    # --- R8: single source of truth for the statuses -------------------------

    def test_statuses_for_returns_publish_only_without_drafts(self):
        self.assertEqual(tuple(transcribe._statuses_for(False)), ("publish",))

    def test_statuses_for_returns_publish_and_draft_with_drafts(self):
        self.assertEqual(tuple(transcribe._statuses_for(True)), ("publish", "draft"))

    # --- R9/R10: empty status sequence falls back to publish -----------------

    def test_collect_episodes_empty_statuses_falls_back_to_publish(self):
        command = self._collect_command(statuses=())
        self.assertTrue(command.endswith(" publish"), command)
        self.assertNotIn("draft", command)

    def test_upload_transcripts_empty_statuses_falls_back_to_publish(self):
        command = self._upload_command(allowed_statuses=())
        self.assertTrue(command.endswith(" publish"), command)
        self.assertNotIn("draft", command)


if __name__ == "__main__":
    unittest.main()
