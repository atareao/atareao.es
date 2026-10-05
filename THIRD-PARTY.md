# Third-party JavaScript registry

Registro de procedencia de todo el JavaScript de terceros vendorizado en este
repositorio, junto con su hash de integridad (SHA-256) tal y como se sirve.
También documenta la correspondencia de los minificados propios del tema con
sus fuentes `.js`.

> **Política de integridad (resumen).** Los recursos son **locales y del mismo
> origen**, por lo que no aplicamos atributos `integrity` (Subresource
> Integrity) en los `wp_enqueue_script`/`<script>`: el SRI protege frente a la
> manipulación de un recurso alojado en un tercero, no frente a la modificación
> del propio fichero en el servidor que lo sirve. La integridad se garantiza con
> el **hash versionado de este registro** y la revisión de cambios en el
> repositorio. Detalle y justificación en
> [Decisión sobre SRI](#decisión-sobre-sri).

## Vendorizado (terceros)

| Fichero | Versión | Origen upstream | Licencia | SHA-256 (tal como se sirve) |
|---------|---------|-----------------|----------|-----------------------------|
| `wp-content/plugins/atareao-functionality/assets/blocks/crontab-helper/qrcode.min.js` | sin tag de versión; commit de referencia `06c7a5e134f116402699f03cda5819e10a0e5787` (rama `master`, 2013-07-12) | https://github.com/davidshimjs/qrcodejs (`qrcode.min.js`) | MIT — Copyright (c) 2012 davidshimjs | `c541ef06327885a8415bca8df6071e14189b4855336def4f36db54bde8484f36` |
| `wp-content/plugins/atareao-functionality/assets/vendor/js-yaml.min.js` | `4.1.0` | https://github.com/nodeca/js-yaml (`dist/js-yaml.min.js`) | MIT — Copyright (c) 2011-2015 Vitaly Puzrin | `45dc3dd03dc07a06705a2c2989b8c7f709013f04bd5386e3279d4e447f07ebd7` |

### Notas por fichero

- **`qrcode.min.js`** — librería `qrcodejs` de davidshimjs (implementación de QR
  en cliente). El repositorio upstream **no publica tags ni releases**, por lo
  que se fija el **commit** que coincide byte a byte con el fichero vendorizado.
  El fichero **no incluye cabecera de licencia**; la licencia MIT se acredita
  mediante el `LICENSE` del repositorio upstream. No modificar el contenido sin
  revisar este registro.
- **`js-yaml.min.js`** — build minificado oficial de `js-yaml` 4.1.0. El propio
  fichero empieza por `/*! js-yaml 4.1.0 https://github.com/nodeca/js-yaml
  @license MIT */`. Se sirve directamente desde
  `templates/tools-yaml-json.php` (`window.jsyaml`).

### Verificación de integridad

Para comprobar que los ficheros servidos no han cambiado respecto al registro:

```bash
sha256sum \
  wp-content/plugins/atareao-functionality/assets/blocks/crontab-helper/qrcode.min.js \
  wp-content/plugins/atareao-functionality/assets/vendor/js-yaml.min.js
```

Si un hash no coincide con la tabla anterior, el cambio debe revisarse y, si es
intencionado, actualizar este registro en el mismo commit.

## Minificados propios del tema

Los ficheros `js/*.min.js` del tema son **derivados minificados** de sus fuentes
`.js` versionadas. La **fuente legible `.js` es la referencia**; el `.min.js` se
genera a partir de ella y su versión queda ligada a la versión del tema
(`wp-content/themes/atareao-theme/style.css`, `Version: 1.13.0`). El repositorio
**no tiene build tools**, así que la minificación es manual: cualquier cambio en
un `.js` debe reproducirse en su `.min.js` en el mismo commit.

| Fuente (referencia) | Minificado (servido) | SHA-256 de la fuente | SHA-256 del minificado |
|---------------------|----------------------|----------------------|------------------------|
| `js/main.js` | `js/main.min.js` | `8687a8fd98ffcb5310d8ded4c74825b3d5b1505258b16a2778376b73f6a4d588` | `11fd4aec40e18c92a6561afbf5a739f28780f02f37b6036f7f3c06b7d0905b6c` |
| `js/navigation.js` | `js/navigation.min.js` | `98419f661d2a5c5d60bb9d4dc312bae6fcf594992511acbd5ab3d0db751f13a5` | `134444eb2417464866515e25b596ea9813ad1acceba1ce611ac3737f20a524d7` |
| `js/share.js` | `js/share.min.js` | `771d3e042e71f7e5da5d316367349e7bf1a9148db4db3c368d92d3238150c320` | `9d00841a32989910c3b3156a4962272a4aebf58502677dd750de757c01fdb449` |
| `js/comment-ajax.js` | `js/comment-ajax.min.js` | `c2906d71e5d9320c20790c2a886509aef1329035fe5ce522e1b56b32b9f28d5a` | `4497c4e940bc0bb22240c63351f4633e7337b2806db455337b792704396e2be2` |

Rutas completas: `wp-content/themes/atareao-theme/js/`. El cache-busting se hace
con `wp_get_theme()->get('Version')` en `functions.php`, de modo que la versión
del tema controla la de los minificados servidos.

## Decisión sobre SRI

**No se aplican atributos `integrity` (SRI).** Justificación:

1. Todos los recursos (`qrcode.min.js`, `js-yaml.min.js` y los `js/*.min.js`) se
   sirven desde el **propio origen** del sitio. El SRI está pensado para mitigar
   que un **tercero** (CDN, origen externo) sirva contenido alterado; no aporta
   protección frente a la modificación del fichero ya presente en el servidor.
2. Mantener hashes SRI sincronizados con cada `.min.js` en el HTML/PHP añade
   riesgo de **desincronización silenciosa** (un `integrity` obsoleto bloquea el
   script legítimo). El registro versionado de este documento evita ese acoplamiento.
3. La integridad frente a cambios no intencionados se cubre con los **hashes
   SHA-256 versionados** y con la revisión de diffs en el repositorio.

Si en el futuro se decide aplicar `integrity`, el valor **debe** registrarse aquí
y actualizarse en el mismo commit que modifique el fichero; nunca debe quedar un
`integrity` sin valor registrado.
