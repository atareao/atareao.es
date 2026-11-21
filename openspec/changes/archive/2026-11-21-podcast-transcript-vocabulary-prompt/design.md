# Design: Prompt de vocabulario opcional

## Contexto

`transcribe_audio()` construye la línea de órdenes de `whisper` (modelo, idioma, formato, salida,
audio). Hoy no admite `--initial_prompt`. El objetivo es añadir un sesgo de vocabulario **opcional**
sin tocar el camino por defecto.

## Decisión

- `parse_args()` añade `--initial-prompt` (string, por defecto `''`).
- `run_pipeline()` lee el valor y lo pasa a `transcribe_audio(..., initial_prompt=...)`.
- `transcribe_audio()` incluye `["--initial_prompt", prompt]` en la orden **solo si** `prompt` no
  está vacío. Por defecto la orden es idéntica a la actual.

## Por qué no por defecto

El prompt altera el reconocimiento y en pruebas no eliminó todos los garbles; activarlo por defecto
cambiaría la salida de todas las transcripciones sin garantía de mejora. Opt-in mantiene el
comportamiento actual y deja la decisión al operador, que puede iterar su vocabulario.

## Alternativas descartadas

- **Prompt por defecto con un glosario fijo**: cambia el comportamiento y no mejoró de forma fiable.
- **Post-proceso con sustituciones deterministas**: es otra capacidad (saneo por glosario); no se
  aborda aquí, pero es el complemento natural.
