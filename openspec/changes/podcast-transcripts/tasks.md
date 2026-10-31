# Tasks: Pipeline de transcripciones del podcast

## 0. Contexto y decisiones previas

- [x] 0.1 Confirmar el alcance: el sitio **no** transcribe en tiempo de ejecución; el pipeline es un
      proceso batch externo que corre donde hay GPU (RTX 4060 Ti, 16 GB)
- [x] 0.2 Confirmar el modelo de Whisper (`turbo` por defecto, mejor español que el `medium` cacheado;
      sobreescribible) y el idioma (`es`)
- [x] 0.3 Confirmar la ubicación del código: `scripts/podcast-transcripts/` (versionado) y
      `tests/podcast-transcripts/` para las pruebas unitarias

## 1. RED — pruebas que describen el contrato (antes de implementar)

- [x] 1.1 `tests/podcast-transcripts/test_transcribe.py` (solo biblioteca estándar, `unittest`):
      - [ ] 1.1.1 `escenario Validez del SubRip producido / bloques numerados consecutivos`
      - [ ] 1.1.2 `escenario Las marcas de tiempo son válidas y crecientes`
      - [ ] 1.1.3 `escenario El SRT conserva los caracteres del idioma` (UTF-8)
      - [ ] 1.1.4 `escenario Un episodio ya transcrito se salta` (decisión de idempotencia, función pura)
      - [ ] 1.1.5 `escenario Episodio sin mp3-url se omite` y `escenario Episodio con URL no utilizable se omite`
      - [ ] 1.1.6 `escenario El alcance se restringe a podcasts publicados` (filtro, función pura)
      - [ ] 1.1.7 `escenario El modo de ensayo no escribe nada` (el planificador no emite acciones de escritura)
- [x] 1.2 Ejecutar las pruebas por CLI y **confirmar que fallan** (las funciones aún no existen):
      `python3 -m unittest discover -s tests/podcast-transcripts`
- [x] 1.3 Receta `just test-transcripts` en `.justfile` que ejecuta 1.2
- [x] 1.4 Registrar la evidencia literal del RED (salida de la CLI)

#### Evidencia — RED (2026-10-31)

Decisiones confirmadas por el titular: el sitio **no** transcribe; modelo `turbo` (sobreescribible),
idioma `es`; código versionado en `scripts/podcast-transcripts/`.

`tests/podcast-transcripts/test_transcribe.py` (279 líneas, 15 métodos, solo biblioteca estándar) cubre
los escenarios del delta. El módulo de implementación **no existe todavía**, así que el RED es una
importación fallida —reportada sin disimular, sin `try/except` ni stubs—:

```
$ python3 -m unittest discover -s tests/podcast-transcripts -v
ERROR: test_transcribe (unittest.loader._FailedTest.test_transcribe)
ImportError: Failed to import test module: test_transcribe
  File ".../tests/podcast-transcripts/test_transcribe.py", line 26, in <module>
    import transcribe
ModuleNotFoundError: No module named 'transcribe'
Ran 1 test in 0.000s
FAILED (errors=1)                                    → EXIT=1

$ just test-transcripts
FAILED (errors=1)
error: recipe `test-transcripts` failed with exit code 1
```

**Sin regresión en la suite heredada** (el RED no rompe nada existente):

```
$ just test
Total passed: 1068, Total failed: 0
All tests passed successfully!
```

## 2. GREEN — implementación mínima

- [ ] 2.1 `scripts/podcast-transcripts/transcribe.py` (solo biblioteca estándar):
      - [ ] 2.1.1 Escritura y **validación** del SRT (bloques, numeración, tiempos, UTF-8)
      - [ ] 2.1.2 Decisión de idempotencia: un episodio con `transcript-srt` hacia adjunto accesible se salta
      - [ ] 2.1.3 Guarda de URL: solo `http`/`https` utilizables; lo demás se omite
      - [ ] 2.1.4 Selección de episodios: solo `podcast` **publicado**; `--limit N`; `--dry-run`
      - [ ] 2.1.5 Recolección de pendientes desde producción vía WP-CLI (JSON)
      - [ ] 2.1.6 Descarga del MP3 remoto (sigue la redirección `anchor.fm` → CloudFront)
      - [ ] 2.1.7 Transcripción con `whisper --language es --output_format srt`
      - [ ] 2.1.8 Subida por lote: `docker cp` del directorio de SRT + **una** pasada `wp eval-file` que
            crea los adjuntos y escribe las metas
      - [ ] 2.1.9 Resumen final: resueltos / omitidos / fallidos
- [ ] 2.2 Ejecutar `just test-transcripts` → **verde**
- [ ] 2.3 `python3 -m py_compile` (o equivalente) del script, sin errores

## 3. Ensayo real acotado (antes del lote completo)

- [ ] 3.1 `--dry-run` sobre el conjunto real: comprobar que **no** escribe nada y que el plan es correcto
- [ ] 3.2 Ejecutar con `--limit` reducido (p. ej. 3-5 episodios) y **verificar**:
      - [ ] 3.2.1 Se crean los adjuntos `.srt` y las metas apuntan a IDs válidos
      - [ ] 3.2.2 El feed **servido** emite `<podcast:transcript>` para esos episodios (primera vez que
            la rama se ejerce en producción)
      - [ ] 3.2.3 La URL del adjunto responde `200` y el SRT es válido
      - [ ] 3.2.4 Segunda ejecución con el mismo límite: los ya transcritos se **saltan** (idempotencia
            verificada en producción)
- [ ] 3.3 Medir el tiempo real por episodio para estimar el lote completo (835 episodios, 13,7 GB)

## 4. Lote completo y verificación

- [ ] 4.1 Ejecutar el pipeline sobre **todos** los pendientes (reanudable si se interrumpe)
- [ ] 4.2 Verificar el resultado en producción:
      - [ ] 4.2.1 Nº de episodios con `transcript-srt` accesible (esperado: los 835 con `mp3-url`)
      - [ ] 4.2.2 Los 3 episodios con `mp3-url` vacía siguen omitidos, no fallidos-silenciosos
      - [ ] 4.2.3 El feed servido declara `<podcast:transcript>` en los ítems con transcripción
      - [ ] 4.2.4 Un SRT servido de muestra es SubRip válido y su URL responde `200`
- [ ] 4.3 Documentar la **limitación conocida**: el `.srt` se sirve como `application/octet-stream`
      (nginx sin mapeo `srt`) mientras el feed declara `application/x-subrip`
- [ ] 4.4 Anotar la evidencia E2E en este `tasks.md` (convención del proyecto)

## 5. Entrega

- [ ] 5.1 `just php-lint` y las pruebas del repo (`just test`) sin regresiones
- [ ] 5.2 PR de la rama `feature/podcast-transcripts` a `development` (merge commit, convención del proyecto)
- [ ] 5.3 `openspec archive podcast-transcripts` fusionando el delta en `openspec/specs/podcast-transcripts/spec.md`

## Notas de implementación

- **El sitio no transcribe.** Cualquier diseño que ejecute Whisper dentro del contenedor de WordPress es
  incorrecto por construcción: no hay GPU ni herramientas, y el sitio no debe hacer trabajo pesado.
- **La fuente de verdad de la idempotencia es WordPress**, no un fichero de estado local: así una
  ejecución interrumpida o lanzada desde otra máquina converge al mismo resultado sin coordinación.
- **Un `wp eval-file` por lote, no por episodio:** 835 `docker cp` y 835 invocaciones de WP-CLI serían
  lentas y frágiles. Se copia el directorio de SRT de una vez y se resuelven todos los adjuntos en una
  pasada.
- **El audio no se versiona ni se conserva:** es material de trabajo temporal y se elimina tras procesar.
