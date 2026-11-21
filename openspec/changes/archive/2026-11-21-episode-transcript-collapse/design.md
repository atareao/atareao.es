# Design: Plegar la transcripción del episodio en un desplegable

## Decisiones

### 1. `<details>`/`<summary>` nativo, no JS
Se usa el elemento nativo `<details>`, accesible y operable por teclado sin JavaScript y sin coste de
render. Plegado por defecto (sin atributo `open`). El texto sigue en el HTML → **indexable**.

### 2. Se conservan las clases y la semántica
Se mantiene el `aria-label` de la sección y las clases `episode-transcript` /
`episode-transcript__title` (el título pasa a ser el `<summary>`), para no romper CSS ni expectativas.
El `<h2>` interno se sustituye por el `<summary>` (un `<summary>` en un `<details>` no debe anidar
encabezados de forma que descuadre el esquema del documento; se documenta la elección).

### 3. Párrafos: se mantienen en este cambio
El troceado arbitrario del SRT se corrige en **#247** (post-proceso del texto). Aquí solo se cambia la
**presentación** (plegado + tipografía), sin reescribir el contenido.

### 4. Estilo
CSS: `<summary>` con marcador, cursor `pointer`, foco visible, `details[open]` con espaciado;
párrafos con `max-width` de lectura y `line-height` cómodo. Sin dependencias.

## Riesgos
- **Indexabilidad:** mitigada al mantener el texto en el HTML (no `display:none` sobre el contenido;
  es el navegador quien oculta el contenido de un `<details>` cerrado, y los buscadores leen el HTML).
- **Regresión del feed:** nula; solo cambia el HTML de la página.
