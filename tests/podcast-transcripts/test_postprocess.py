"""Tests del post-proceso del SRT (puntuación, mayúsculas y glosario).

Change OpenSpec ``podcast-transcript-postprocess`` (issue #247). Cubren el contrato
de la spec en ``openspec/changes/podcast-transcript-postprocess/specs/podcast-transcripts``:

 - opciones de línea de órdenes (``--postprocess``, ``--glossary``, ``--backfill``,
   ``--revert``);
 - invariancia de la rejilla temporal (mismos bloques y timecodes);
 - invariancia de la secuencia de palabras salvo alias **declarados** del glosario;
 - restauración de mayúscula inicial y de fin de frase por hueco temporal;
 - glosario determinista e idempotente;
 - degradación segura por bloque cuando un motor rompe el invariante;
 - idempotencia del post-proceso (segunda pasada = 0 cambios);
 - backfill reversible (mismo ID de adjunto, meta intacta, original recuperable);
 - ``--dry-run`` que no escribe, no sube y no toca metas;
 - validez SubRip del resultado.

Solo se usa la biblioteca estándar (``unittest``, ``hashlib``, ``json``...). El
proceso de producción (``ssh``/``wp``) queda tras una frontera inyectable, de modo
que estos tests no tocan la red.
"""

import argparse
import base64
import contextlib
import hashlib
import io
import json
import pathlib
import sys
import tempfile
import unittest
from unittest import mock

REPO_ROOT = pathlib.Path(__file__).resolve().parents[2]
sys.path.insert(0, str(REPO_ROOT / "scripts" / "podcast-transcripts"))

# La API bajo prueba no existe todavía: en RED este import falla en los atributos.
import transcribe  # noqa: E402


def make_record(rec_id, transcript_id=0):
    """Registro plano con la forma que devuelve ``wp-collect.php``."""
    return {
        "id": rec_id,
        "post_type": "podcast",
        "status": "publish",
        "mp3_url": "https://anchor.fm/atareao/episodes/episodio.mp3",
        "transcript_id": transcript_id,
    }


def write_glossary(tmp_dir, body):
    """Escribe un TSV de glosario en ``tmp_dir`` y devuelve su ruta."""
    path = pathlib.Path(tmp_dir) / "glossary.tsv"
    path.write_text(body, encoding="utf-8")
    return path


def srt_from_cues(cues):
    """Renderiza ``cues`` como texto SubRip (texto crudo, en minúsculas)."""
    return transcribe.render_srt(cues)


class PostprocessArgsTest(unittest.TestCase):
    """``parse_args`` expone el post-proceso como opt-in y el modo backfill."""

    def test_postprocess_disabled_by_default(self):
        args = transcribe.parse_args([])
        self.assertFalse(args.postprocess)
        self.assertFalse(args.backfill)
        self.assertFalse(args.revert)
        self.assertEqual(pathlib.Path(args.glossary), transcribe.DEFAULT_GLOSSARY_PATH)

    def test_postprocess_flags_parsed(self):
        args = transcribe.parse_args(
            [
                "--postprocess",
                "--glossary",
                "/tmp/g.tsv",
                "--backfill",
                "--revert",
                "--dry-run",
            ]
        )
        self.assertTrue(args.postprocess)
        self.assertTrue(args.backfill)
        self.assertTrue(args.revert)
        self.assertTrue(args.dry_run)
        self.assertEqual(args.glossary, "/tmp/g.tsv")


class GlossaryTest(unittest.TestCase):
    """Glosario: grafía canónica y alias declarados, deterministas e idempotentes."""

    def test_canonical_spelling_applied_case_insensitively(self):
        with tempfile.TemporaryDirectory() as tmp_dir:
            path = write_glossary(
                tmp_dir, "canonical\tgitea\tGitea\ncanonical\tlinux\tLinux\n"
            )
            glossary = transcribe.load_glossary(path)
            self.assertEqual(glossary.apply("uso GITEA en linux"), "uso Gitea en Linux")

    def test_declared_alias_replaces_phrase(self):
        with tempfile.TemporaryDirectory() as tmp_dir:
            path = write_glossary(tmp_dir, "alias\tslim book\tSlimbook\n")
            glossary = transcribe.load_glossary(path)
            self.assertEqual(glossary.apply("mi slim book"), "mi Slimbook")

    def test_undeclared_alias_is_not_invented(self):
        with tempfile.TemporaryDirectory() as tmp_dir:
            path = write_glossary(tmp_dir, "canonical\tgitea\tGitea\n")
            glossary = transcribe.load_glossary(path)
            # Sin alias declarado, "slim book" queda tal cual.
            self.assertEqual(glossary.apply("slim book y gitea"), "slim book y Gitea")

    def test_word_boundary_does_not_match_inside_a_word(self):
        with tempfile.TemporaryDirectory() as tmp_dir:
            path = write_glossary(tmp_dir, "canonical\tarch\tArch\n")
            glossary = transcribe.load_glossary(path)
            self.assertEqual(glossary.apply("el archivo de arch"), "el archivo de Arch")

    def test_apply_is_idempotent(self):
        with tempfile.TemporaryDirectory() as tmp_dir:
            path = write_glossary(
                tmp_dir,
                "canonical\tgitea\tGitea\nalias\tslim book\tSlimbook\n",
            )
            glossary = transcribe.load_glossary(path)
            once = glossary.apply("gitea y slim book")
            self.assertEqual(glossary.apply(once), once)

    def test_longest_match_wins_over_a_shorter_canonical(self):
        with tempfile.TemporaryDirectory() as tmp_dir:
            path = write_glossary(tmp_dir, "alias\tfoo baz\tQUX\ncanonical\tfoo\tBAR\n")
            glossary = transcribe.load_glossary(path)
            # Precedencia por longitud: la frase más larga (`foo baz`) se aplica antes
            # que el término corto (`foo`), así que no puede quedar "BAR baz".
            self.assertEqual(glossary.apply("foo baz"), "QUX")

    def test_canonical_entry_must_preserve_the_word_count(self):
        with tempfile.TemporaryDirectory() as tmp_dir:
            # Un canónico que cambia el número de palabras burlaría el invariante
            # ("solo los alias son excepción"): debe rechazarse al cargar.
            path = write_glossary(tmp_dir, "canonical\tslim book\tSlimbook\n")
            with self.assertRaises(transcribe.PipelineError):
                transcribe.load_glossary(path)

    def test_alias_entry_may_change_the_word_count(self):
        with tempfile.TemporaryDirectory() as tmp_dir:
            path = write_glossary(tmp_dir, "alias\tslim book\tSlimbook\n")
            glossary = transcribe.load_glossary(path)
            self.assertEqual(glossary.apply("slim book"), "Slimbook")

    def test_malformed_entry_raises_pipeline_error(self):
        with tempfile.TemporaryDirectory() as tmp_dir:
            path = write_glossary(tmp_dir, "canonical\tsolo-dos-columnas\n")
            with self.assertRaises(transcribe.PipelineError):
                transcribe.load_glossary(path)

    def test_missing_file_raises_pipeline_error(self):
        with self.assertRaises(transcribe.PipelineError):
            transcribe.load_glossary("/no/existe/glossary.tsv")

    def test_default_glossary_contains_required_terms(self):
        glossary = transcribe.load_glossary(transcribe.DEFAULT_GLOSSARY_PATH)
        result = glossary.apply(
            "atareao, slim book, gitea, termux, neovim, github, youtube, ivoox, "
            "wordpress, docker, podman, linux, gnome, kde, arch, ubuntu, fedora"
        )
        for term in (
            "atareao",
            "Slimbook",
            "Gitea",
            "Termux",
            "Neovim",
            "GitHub",
            "YouTube",
            "iVoox",
            "WordPress",
            "Docker",
            "Podman",
            "Linux",
            "GNOME",
            "KDE",
            "Arch",
            "Ubuntu",
            "Fedora",
        ):
            with self.subTest(term=term):
                self.assertIn(term, result)


class PostprocessInvarianceTest(unittest.TestCase):
    """El post-proceso conserva bloques, timecodes y la secuencia de palabras."""

    CUES = [
        (0.0, 2.0, "hola esto es una prueba"),
        (2.05, 4.0, "gitea y slim book"),
        (10.0, 12.0, "terminamos el episodio"),
    ]

    def _glossary(self, tmp_dir):
        path = write_glossary(
            tmp_dir,
            "canonical\tgitea\tGitea\nalias\tslim book\tSlimbook\n",
        )
        return transcribe.load_glossary(path)

    def test_blocks_and_timecodes_unchanged(self):
        with tempfile.TemporaryDirectory() as tmp_dir:
            original = srt_from_cues(self.CUES)
            processed = transcribe.postprocess_srt(original, self._glossary(tmp_dir))

            before = transcribe.validate_srt(original)
            after = transcribe.validate_srt(processed)

            self.assertEqual(
                [(i, s, e) for i, s, e, _ in before],
                [(i, s, e) for i, s, e, _ in after],
            )

    def test_word_sequence_preserved_except_declared_alias(self):
        with tempfile.TemporaryDirectory() as tmp_dir:
            glossary = self._glossary(tmp_dir)
            original = srt_from_cues(self.CUES)
            processed = transcribe.postprocess_srt(original, glossary)

            # Expectativa LITERAL escrita a mano: NO se calcula con `_word_tokens`
            # (la misma función de la implementación) o el oráculo sería tautológico
            # y una mutación que borre la identidad conservando el conteo pasaría.
            # El único cambio es el alias declarado `slim book` -> `Slimbook`.
            expected = [
                ["hola", "esto", "es", "una", "prueba"],
                ["gitea", "y", "slimbook"],
                ["terminamos", "el", "episodio"],
            ]
            observed = [
                transcribe._word_tokens(text)
                for _index, _start, _end, text in transcribe.validate_srt(processed)
            ]
            self.assertEqual(observed, expected)

    def test_word_order_is_preserved_not_just_the_count(self):
        with tempfile.TemporaryDirectory() as tmp_dir:
            glossary = transcribe.load_glossary(
                write_glossary(tmp_dir, "canonical\tgitea\tGitea\n")
            )
            cues = [(0.0, 1.0, "gitea antes gitea"), (3.0, 4.0, "despues al final")]
            processed = transcribe.postprocess_srt(srt_from_cues(cues), glossary)
            observed = [
                transcribe._word_tokens(text)
                for _index, _start, _end, text in transcribe.validate_srt(processed)
            ]
            # El orden importa: `gitea` repetido en cabeza, sin reordenar ni contar.
            self.assertEqual(
                observed, [["gitea", "antes", "gitea"], ["despues", "al", "final"]]
            )

    def test_validates_after_postprocess(self):
        with tempfile.TemporaryDirectory() as tmp_dir:
            processed = transcribe.postprocess_srt(
                srt_from_cues(self.CUES), self._glossary(tmp_dir)
            )
            self.assertEqual(len(transcribe.validate_srt(processed)), len(self.CUES))


class PostprocessCapitalizationTest(unittest.TestCase):
    """Mayúscula inicial y puntuación guiada por el hueco temporal."""

    def test_capitalizes_first_letter_of_each_cue(self):
        with tempfile.TemporaryDirectory() as tmp_dir:
            glossary = transcribe.load_glossary(
                write_glossary(tmp_dir, "canonical\tgitea\tGitea\n")
            )
            cues = [(0.0, 1.0, "uno"), (1.1, 2.0, "dos"), (3.0, 4.0, "tres")]
            texts = [text for _, _, text in transcribe.postprocess_cues(cues, glossary)]
            self.assertEqual(texts, ["Uno,", "Dos.", "Tres."])

    def test_period_on_long_gap_and_comma_on_short_gap(self):
        with tempfile.TemporaryDirectory() as tmp_dir:
            glossary = transcribe.load_glossary(write_glossary(tmp_dir, ""))
            cues = [(0.0, 1.0, "a"), (1.1, 2.0, "b"), (5.0, 6.0, "c")]
            _, _, first = transcribe.postprocess_cues(cues, glossary)[0]
            _, _, second = transcribe.postprocess_cues(cues, glossary)[1]
            self.assertTrue(first.endswith(","))
            self.assertTrue(second.endswith("."))

    def test_does_not_invent_question_or_exclamation_marks(self):
        with tempfile.TemporaryDirectory() as tmp_dir:
            glossary = transcribe.load_glossary(write_glossary(tmp_dir, ""))
            cues = [(0.0, 1.0, "que tal"), (3.0, 4.0, "muy bien")]
            processed = transcribe.postprocess_srt(srt_from_cues(cues), glossary)
            self.assertNotIn("?", processed)
            self.assertNotIn("!", processed)

    def test_collapses_extra_whitespace(self):
        with tempfile.TemporaryDirectory() as tmp_dir:
            glossary = transcribe.load_glossary(write_glossary(tmp_dir, ""))
            cues = [(0.0, 1.0, "hola   mundo   ")]
            _, _, text = transcribe.postprocess_cues(cues, glossary)[0]
            self.assertEqual(text, "Hola mundo.")


class SafeDegradationTest(unittest.TestCase):
    """Un motor que rompe el invariante en un bloque deja ese bloque intacto."""

    def _glossary(self, tmp_dir):
        return transcribe.load_glossary(write_glossary(tmp_dir, ""))

    def test_broken_block_keeps_original_text(self):
        with tempfile.TemporaryDirectory() as tmp_dir:
            glossary = self._glossary(tmp_dir)
            cues = [(0.0, 1.0, "uno"), (1.1, 2.0, "dos")]

            def broken_engine(cues_arg, glossary_arg):
                return ["palabras distintas", "aqui tampoco"]

            result = transcribe.postprocess_cues(cues, glossary, engine=broken_engine)
            self.assertEqual([text for _, _, text in result], ["uno", "dos"])

    def test_partial_degradation_keeps_only_the_broken_block(self):
        with tempfile.TemporaryDirectory() as tmp_dir:
            glossary = self._glossary(tmp_dir)
            cues = [(0.0, 1.0, "hola"), (1.1, 2.0, "dos"), (3.0, 4.0, "tres")]

            def engine(cues_arg, glossary_arg):
                return ["Hola.", "otras palabras inventadas", "mas invento"]

            result = transcribe.postprocess_cues(cues, glossary, engine=engine)
            self.assertEqual([text for _, _, text in result], ["Hola.", "dos", "tres"])

    def test_engine_with_wrong_length_keeps_all_originals(self):
        with tempfile.TemporaryDirectory() as tmp_dir:
            glossary = self._glossary(tmp_dir)
            cues = [(0.0, 1.0, "uno"), (1.1, 2.0, "dos")]

            def short_engine(cues_arg, glossary_arg):
                return ["solo uno"]

            result = transcribe.postprocess_cues(cues, glossary, engine=short_engine)
            self.assertEqual([text for _, _, text in result], ["uno", "dos"])


class PostprocessFileIdempotenceTest(unittest.TestCase):
    """Aplicar el post-proceso dos veces al mismo fichero no lo modifica."""

    def test_second_pass_is_a_noop(self):
        with tempfile.TemporaryDirectory() as tmp_dir:
            glossary = transcribe.load_glossary(
                write_glossary(tmp_dir, "canonical\tgitea\tGitea\n")
            )
            srt_path = pathlib.Path(tmp_dir) / "1.srt"
            srt_path.write_text(
                srt_from_cues([(0.0, 1.0, "gitea"), (3.0, 4.0, "adios")]),
                encoding="utf-8",
            )

            self.assertTrue(transcribe.postprocess_srt_file(srt_path, glossary))
            first = srt_path.read_text(encoding="utf-8")
            # La segunda pasada no cambia el fichero.
            self.assertFalse(transcribe.postprocess_srt_file(srt_path, glossary))
            self.assertEqual(srt_path.read_text(encoding="utf-8"), first)


class FakeAttachmentStore:
    """Doble en memoria de la frontera de producción (adjuntos + meta)."""

    def __init__(self, items):
        # episode_id -> (attachment_id, content)
        self._items = dict(items)
        self.meta = {ep: att for ep, (att, _) in items.items()}
        self.orig = {}
        self.calls = []

    def fetch(self, episode_id):
        item = self._items.get(episode_id)
        if item is None:
            return None
        attachment_id, content = item
        return transcribe.RemoteTranscript(episode_id, attachment_id, content)

    def replace(self, transcript, data):
        self.calls.append(("replace", transcript.episode_id))
        self.orig.setdefault(transcript.episode_id, transcript.content)
        self._items[transcript.episode_id] = (transcript.attachment_id, data)
        return transcript.attachment_id

    def revert(self, episode_id):
        self.calls.append(("revert", episode_id))
        if episode_id not in self.orig:
            return False
        attachment_id = self._items[episode_id][0]
        self._items[episode_id] = (attachment_id, self.orig[episode_id])
        return True

    def attachment_id(self, episode_id):
        return self._items[episode_id][0]

    def content(self, episode_id):
        return self._items[episode_id][1]


class BackfillTest(unittest.TestCase):
    """Backfill reversible: mismo adjunto y meta, original recuperable, reanudable."""

    RAW = srt_from_cues([(0.0, 2.0, "hola mundo"), (5.0, 7.0, "adios")])

    def _glossary(self, tmp_dir):
        return transcribe.load_glossary(
            write_glossary(tmp_dir, "canonical\tgitea\tGitea\n")
        )

    def _run(self, tmp_dir, store, *, dry_run=False, revert=False):
        return transcribe.backfill_transcripts(
            [make_record(101, transcript_id=4242)],
            glossary=self._glossary(tmp_dir),
            backend=store,
            work_dir=tmp_dir,
            dry_run=dry_run,
            revert=revert,
        )

    def test_replaces_content_keeping_attachment_id_and_meta(self):
        with tempfile.TemporaryDirectory() as tmp_dir:
            store = FakeAttachmentStore({101: (4242, self.RAW.encode("utf-8"))})
            result = self._run(tmp_dir, store)

            self.assertEqual(result.changed, 1)
            self.assertEqual(store.attachment_id(101), 4242)
            self.assertEqual(store.meta[101], 4242)
            new_text = store.content(101).decode("utf-8")
            self.assertNotEqual(new_text, self.RAW)
            self.assertEqual(
                [(i, s, e) for i, s, e, _ in transcribe.validate_srt(self.RAW)],
                [(i, s, e) for i, s, e, _ in transcribe.validate_srt(new_text)],
            )

    def test_original_is_recoverable_and_manifest_has_sha256(self):
        with tempfile.TemporaryDirectory() as tmp_dir:
            store = FakeAttachmentStore({101: (4242, self.RAW.encode("utf-8"))})
            self._run(tmp_dir, store)

            self.assertEqual(store.orig[101].decode("utf-8"), self.RAW)
            manifest_path = pathlib.Path(tmp_dir) / "backfill-manifest.json"
            manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
            entry = manifest["101"]
            self.assertEqual(entry["attachment_id"], 4242)
            self.assertEqual(
                entry["original_sha256"],
                hashlib.sha256(self.RAW.encode("utf-8")).hexdigest(),
            )
            self.assertEqual(
                entry["processed_sha256"],
                hashlib.sha256(store.content(101)).hexdigest(),
            )

    def test_write_manifest_translates_oserror(self):
        with tempfile.TemporaryDirectory() as tmp_dir:
            # Un directorio con el nombre del manifiesto: `write_text` falla.
            target = pathlib.Path(tmp_dir) / "backfill-manifest.json"
            target.mkdir()
            with self.assertRaises(transcribe.PipelineError):
                transcribe._write_manifest(target, {"101": {"attachment_id": 1}})

    def test_second_pass_changes_nothing(self):
        with tempfile.TemporaryDirectory() as tmp_dir:
            store = FakeAttachmentStore({101: (4242, self.RAW.encode("utf-8"))})
            self._run(tmp_dir, store)
            replace_calls = len(store.calls)

            again = self._run(tmp_dir, store)
            self.assertEqual(again.changed, 0)
            self.assertEqual(len(store.calls), replace_calls)

    def test_revert_restores_original(self):
        with tempfile.TemporaryDirectory() as tmp_dir:
            store = FakeAttachmentStore({101: (4242, self.RAW.encode("utf-8"))})
            self._run(tmp_dir, store)

            result = self._run(tmp_dir, store, revert=True)
            self.assertEqual(result.reverted, 1)
            self.assertEqual(store.content(101).decode("utf-8"), self.RAW)
            self.assertEqual(store.attachment_id(101), 4242)
            self.assertEqual(store.meta[101], 4242)

    def test_revert_is_idempotent_using_the_recorded_sha256(self):
        with tempfile.TemporaryDirectory() as tmp_dir:
            store = FakeAttachmentStore({101: (4242, self.RAW.encode("utf-8"))})
            self._run(tmp_dir, store)

            first = self._run(tmp_dir, store, revert=True)
            self.assertEqual(first.reverted, 1)
            # Ya restaurado: la segunda pasada no vuelve a "revertir" nada.
            second = self._run(tmp_dir, store, revert=True)
            self.assertEqual(second.reverted, 0)

    def test_revert_verifies_the_restored_content_hash(self):
        with tempfile.TemporaryDirectory() as tmp_dir:
            store = FakeAttachmentStore({101: (4242, self.RAW.encode("utf-8"))})
            self._run(tmp_dir, store)
            # El backend dice haber revertido pero NO reproduce el original.
            store.revert = lambda episode_id: True

            result = self._run(tmp_dir, store, revert=True)

            self.assertEqual(result.reverted, 0)
            self.assertEqual(result.failed, [101])

    def _drop_manifest(self, tmp_dir):
        """Borra el manifiesto para simular una reversión sin referencia."""
        (pathlib.Path(tmp_dir) / "backfill-manifest.json").unlink()

    def test_revert_without_manifest_is_never_a_success(self):
        with tempfile.TemporaryDirectory() as tmp_dir:
            store = FakeAttachmentStore({101: (4242, self.RAW.encode("utf-8"))})
            self._run(tmp_dir, store)
            self._drop_manifest(tmp_dir)

            first = self._run(tmp_dir, store, revert=True)

            # Sin sha256 de referencia no se puede afirmar que se restauró: fallo.
            self.assertEqual(first.reverted, 0)
            self.assertEqual(first.failed, [101])
            # Idempotente respecto al contenido: la segunda pasada no cambia nada.
            after_first = store.content(101)
            second = self._run(tmp_dir, store, revert=True)
            self.assertEqual(second.reverted, 0)
            self.assertEqual(second.failed, [101])
            self.assertEqual(store.content(101), after_first)

    def test_revert_lying_backend_without_manifest_is_failed(self):
        with tempfile.TemporaryDirectory() as tmp_dir:
            store = FakeAttachmentStore({101: (4242, self.RAW.encode("utf-8"))})
            self._run(tmp_dir, store)
            self._drop_manifest(tmp_dir)
            # `revert()` miente: dice que restauró sin tocar el contenido.
            store.revert = lambda episode_id: True

            result = self._run(tmp_dir, store, revert=True)

            self.assertEqual(result.reverted, 0)
            self.assertEqual(result.failed, [101])

    def test_manifest_is_written_once_per_batch(self):
        with tempfile.TemporaryDirectory() as tmp_dir:
            store = FakeAttachmentStore(
                {
                    101: (4242, self.RAW.encode("utf-8")),
                    102: (4243, self.RAW.encode("utf-8")),
                }
            )
            with mock.patch.object(
                transcribe, "_write_manifest", wraps=transcribe._write_manifest
            ) as write_manifest:
                result = transcribe.backfill_transcripts(
                    [
                        make_record(101, transcript_id=4242),
                        make_record(102, transcript_id=4243),
                    ],
                    glossary=self._glossary(tmp_dir),
                    backend=store,
                    work_dir=tmp_dir,
                )

            self.assertEqual(result.changed, 2)
            # Antes se reescribía entero por episodio (O(n²)); ahora, una sola vez.
            self.assertEqual(write_manifest.call_count, 1)

    def test_manifest_write_failure_is_reported_without_aborting(self):
        with tempfile.TemporaryDirectory() as tmp_dir:
            store = FakeAttachmentStore(
                {
                    101: (4242, self.RAW.encode("utf-8")),
                    102: (4243, self.RAW.encode("utf-8")),
                }
            )
            with mock.patch.object(
                transcribe, "_write_manifest", side_effect=OSError("disco lleno")
            ):
                # No debe propagar: la ejecución continúa y se reporta como fallo.
                result = transcribe.backfill_transcripts(
                    [
                        make_record(101, transcript_id=4242),
                        make_record(102, transcript_id=4243),
                    ],
                    glossary=self._glossary(tmp_dir),
                    backend=store,
                    work_dir=tmp_dir,
                )

            self.assertEqual(result.failed, [101, 102])
            # El post-proceso SÍ se aplicó en ambos episodios (no se abortó).
            self.assertNotEqual(store.content(101), self.RAW.encode("utf-8"))
            self.assertNotEqual(store.content(102), self.RAW.encode("utf-8"))

    def test_revert_dry_run_never_calls_the_backend(self):
        with tempfile.TemporaryDirectory() as tmp_dir:
            store = FakeAttachmentStore({101: (4242, self.RAW.encode("utf-8"))})
            self._run(tmp_dir, store)  # backfill real: deja el texto procesado
            processed = store.content(101)
            manifest_path = pathlib.Path(tmp_dir) / "backfill-manifest.json"
            manifest_before = manifest_path.read_text(encoding="utf-8")
            calls_before = len(store.calls)

            result = self._run(tmp_dir, store, dry_run=True, revert=True)

            self.assertEqual(result.reverted, 1)
            self.assertEqual(len(store.calls), calls_before)  # no se llamó a revert
            self.assertEqual(store.content(101), processed)  # el adjunto queda igual
            self.assertEqual(manifest_path.read_text(encoding="utf-8"), manifest_before)

    def test_changed_attachment_id_is_a_failure_and_writes_nothing(self):
        with tempfile.TemporaryDirectory() as tmp_dir:
            raw = self.RAW.encode("utf-8")
            get_payload = json.dumps(
                {"attachment_id": 4242, "content": base64.b64encode(raw).decode()}
            ).encode("utf-8")
            put_payload = json.dumps({"attachment_id": 9999, "changed": True}).encode(
                "utf-8"
            )

            def fake_ssh(host, command, *, input_bytes=None, check=False):
                return mock.Mock(
                    returncode=0,
                    stdout=put_payload if " put " in command else get_payload,
                    stderr=b"",
                )

            backend = transcribe.SshTranscriptBackend("host", "wpcli", "work")
            with (
                mock.patch.object(
                    transcribe.SshTranscriptBackend, "_ensure_helper", return_value=None
                ),
                mock.patch.object(transcribe, "_run_ssh", side_effect=fake_ssh),
                mock.patch.object(transcribe, "_push_bytes_to_container"),
            ):
                result = transcribe.backfill_transcripts(
                    [make_record(101, transcript_id=4242)],
                    glossary=self._glossary(tmp_dir),
                    backend=backend,
                    work_dir=tmp_dir,
                )

            self.assertEqual(result.failed, [101])
            self.assertFalse(
                (pathlib.Path(tmp_dir) / "backfill-manifest.json").exists()
            )

    def test_dry_run_does_not_create_the_work_dir(self):
        with tempfile.TemporaryDirectory() as tmp_dir:
            target = pathlib.Path(tmp_dir) / "work" / "no-existe"
            store = FakeAttachmentStore({101: (4242, self.RAW.encode("utf-8"))})

            result = transcribe.backfill_transcripts(
                [make_record(101, transcript_id=4242)],
                glossary=self._glossary(tmp_dir),
                backend=store,
                work_dir=target,
                dry_run=True,
            )

            self.assertEqual(result.changed, 1)
            self.assertFalse(target.exists())

    def test_dry_run_writes_nothing(self):
        with tempfile.TemporaryDirectory() as tmp_dir:
            store = FakeAttachmentStore({101: (4242, self.RAW.encode("utf-8"))})
            result = self._run(tmp_dir, store, dry_run=True)

            self.assertEqual(store.calls, [])
            self.assertEqual(store.content(101).decode("utf-8"), self.RAW)
            self.assertEqual(store.meta[101], 4242)
            self.assertFalse(
                (pathlib.Path(tmp_dir) / "backfill-manifest.json").exists()
            )
            self.assertEqual(result.changed, 1)

    def test_corrupt_srt_is_reported_without_aborting_the_run(self):
        with tempfile.TemporaryDirectory() as tmp_dir:
            store = FakeAttachmentStore(
                {
                    101: (4242, b"esto no es un SRT"),
                    102: (4243, self.RAW.encode("utf-8")),
                }
            )
            result = transcribe.backfill_transcripts(
                [
                    make_record(101, transcript_id=4242),
                    make_record(102, transcript_id=4243),
                ],
                glossary=self._glossary(tmp_dir),
                backend=store,
                work_dir=tmp_dir,
            )

            self.assertEqual(result.failed, [101])
            self.assertEqual(result.changed, 1)
            self.assertEqual(store.content(101), b"esto no es un SRT")


class PipelineIntegrationTest(unittest.TestCase):
    """El pipeline aplica el post-proceso solo con ``--postprocess``."""

    RAW = srt_from_cues([(0.0, 2.0, "hola mundo"), (5.0, 7.0, "adios")])

    def _args(self, work_dir, **overrides):
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
            "initial_prompt": "",
            "postprocess": False,
            "glossary": str(transcribe.DEFAULT_GLOSSARY_PATH),
        }
        values.update(overrides)
        return argparse.Namespace(**values)

    def _run_pipeline_and_read_srt(self, tmp_dir, **overrides):
        work_dir = pathlib.Path(tmp_dir)
        srt_dir = work_dir / "srt"
        srt_dir.mkdir(parents=True)
        srt_path = srt_dir / "18829.srt"
        srt_path.write_text(self.RAW, encoding="utf-8")

        args = self._args(tmp_dir, **overrides)
        with (
            mock.patch.object(
                transcribe, "collect_episodes", return_value=[make_record(18829)]
            ),
            mock.patch.object(
                transcribe,
                "upload_transcripts",
                return_value={"18829": {"attachment_id": 4242}},
            ),
        ):
            code = transcribe.run_pipeline(args)
        self.assertEqual(code, 0)
        return srt_path.read_text(encoding="utf-8")

    def test_pipeline_applies_postprocess_before_upload(self):
        with tempfile.TemporaryDirectory() as tmp_dir:
            text = self._run_pipeline_and_read_srt(tmp_dir, postprocess=True)
            self.assertNotEqual(text, self.RAW)
            self.assertEqual(
                [(i, s, e) for i, s, e, _ in transcribe.validate_srt(self.RAW)],
                [(i, s, e) for i, s, e, _ in transcribe.validate_srt(text)],
            )

    def test_pipeline_without_postprocess_keeps_sanitized_text(self):
        with tempfile.TemporaryDirectory() as tmp_dir:
            text = self._run_pipeline_and_read_srt(tmp_dir, postprocess=False)
            self.assertEqual(text, self.RAW)

    def test_dry_run_reports_the_changing_blocks(self):
        with tempfile.TemporaryDirectory() as tmp_dir:
            work_dir = pathlib.Path(tmp_dir)
            srt_dir = work_dir / "srt"
            srt_dir.mkdir(parents=True)
            (srt_dir / "18829.srt").write_text(self.RAW, encoding="utf-8")

            args = self._args(tmp_dir, dry_run=True, postprocess=True)
            captured = io.StringIO()
            with (
                mock.patch.object(
                    transcribe, "collect_episodes", return_value=[make_record(18829)]
                ),
                contextlib.redirect_stdout(captured),
            ):
                code = transcribe.run_pipeline(args)

            self.assertEqual(code, 0)
            # El SRT tiene 2 bloques y ambos cambiarían: el ensayo debe informarlo.
            self.assertIn("bloques a cambiar: 2", captured.getvalue())


class SshBackendTest(unittest.TestCase):
    """El backend de producción compone el comando y parsea el JSON del helper."""

    def _proc(self, stdout):
        return mock.Mock(returncode=0, stdout=stdout, stderr=b"")

    def setUp(self):
        # Aislar del envío real del helper: aquí se prueba el comando y el parseo.
        patcher = mock.patch.object(
            transcribe.SshTranscriptBackend, "_ensure_helper", return_value=None
        )
        patcher.start()
        self.addCleanup(patcher.stop)

    def test_fetch_decodes_base64_content(self):
        payload = json.dumps(
            {"attachment_id": 4242, "content": base64.b64encode(b"hola").decode()}
        ).encode("utf-8")
        backend = transcribe.SshTranscriptBackend("host", "wpcli", "work")

        with mock.patch.object(
            transcribe, "_run_ssh", return_value=self._proc(payload)
        ):
            transcript = backend.fetch(101)

        self.assertEqual(transcript.attachment_id, 4242)
        self.assertEqual(transcript.content, b"hola")

    def test_replace_pushes_content_and_returns_attachment_id(self):
        payload = json.dumps({"attachment_id": 4242, "changed": True}).encode("utf-8")
        backend = transcribe.SshTranscriptBackend("host", "wpcli", "work")
        transcript = transcribe.RemoteTranscript(101, 4242, b"viejo")

        with (
            mock.patch.object(transcribe, "_run_ssh", return_value=self._proc(payload)),
            mock.patch.object(transcribe, "_push_bytes_to_container") as push,
        ):
            attachment_id = backend.replace(transcript, b"nuevo")

        push.assert_called_once()
        self.assertEqual(attachment_id, 4242)

    def test_replace_rejects_a_changed_attachment_id(self):
        payload = json.dumps({"attachment_id": 9999, "changed": True}).encode("utf-8")
        backend = transcribe.SshTranscriptBackend("host", "wpcli", "work")
        transcript = transcribe.RemoteTranscript(101, 4242, b"viejo")

        with (
            mock.patch.object(transcribe, "_run_ssh", return_value=self._proc(payload)),
            mock.patch.object(transcribe, "_push_bytes_to_container"),
            self.assertRaises(transcribe.PipelineError),
        ):
            backend.replace(transcript, b"nuevo")

    def test_revert_reports_restoration(self):
        payload = json.dumps({"attachment_id": 4242, "reverted": True}).encode("utf-8")
        backend = transcribe.SshTranscriptBackend("host", "wpcli", "work")

        with mock.patch.object(
            transcribe, "_run_ssh", return_value=self._proc(payload)
        ):
            self.assertTrue(backend.revert(101))


if __name__ == "__main__":
    unittest.main()
