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

import pathlib
import sys
import tempfile
import unittest

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


if __name__ == "__main__":
    unittest.main()
