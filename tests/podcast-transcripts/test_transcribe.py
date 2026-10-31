"""Unit tests for the podcast-transcripts pipeline module (``scripts/podcast-transcripts/transcribe.py``).

Red phase of TDD: the module under test does not exist yet, so importing it fails.
These tests describe the frozen contract that the implementation must satisfy.

Only the Python standard library is used (``unittest``, ``sys``, ``os``, ``tempfile``,
``pathlib``); the repository has no package manager.

Scenarios covered come from
``openspec/changes/podcast-transcripts/specs/podcast-transcripts/spec.md``:
 - Validez del SubRip producido (bloques consecutivos, tiempos crecientes, UTF-8)
 - Idempotencia y reanudación (un episodio ya transcrito se salta)
 - Omisión de episodios sin audio utilizable
 - Ejecución acotada y alcance (ensayo, límite, solo podcasts publicados)
"""

import argparse
import pathlib
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


if __name__ == "__main__":
    unittest.main()
