# Proposal: Registro REST de metadatos endurecido y `seo_description` de solo lectura

## Why

La auditoría de seguridad de `atareao-functionality` (2026-10-03) dejó fuera de alcance, en el change `rest-blocks-hardening`, el método `\Atareao\Metaboxes::registerMetaFields()` porque su activación provoca un `TypeError` fatal (pasa un array como `$post_type` a `register_post_meta()`) y porque expone metas internas por REST. Además, el campo REST `seo_description` divulga la meta protegida `_genesis_description` y acepta escrituras sin un control de capacidad declarado. Este change cierra ambos frentes: activa el registro de metadatos de forma segura y reduce `seo_description` a solo lectura saneada.

## What Changes

- **Activación segura de `registerMetaFields()`.** Se corrige el error de tipo que le pasa un array (`array('application','software')`) como `$post_type` a `register_post_meta()` —causa de un `TypeError` fatal en cada petición— iterando los tipos y pasando siempre un string. El método se engancha en `init`. No se produce ningún error fatal.
- **Metas internas fuera de REST.** Las metas con prefijo `_` que gestiona el plugin (`_download_url`, `_repository_url`, `_version`) pasan a registrarse con `show_in_rest => false`, de modo que dejan de divulgarse por la API REST.
- **Escritura REST con capacidad.** Las metas públicas registradas (`mp3-url`, `number`, `season`, `numero-capitulo`, `tutorial-id`, `post_views_count`) declaran `auth_callback` que exige `edit_posts` para escribir; la lectura se conserva.
- **`metadata` acotado.** El campo REST `metadata` (tipo `podcast`) se limita al conjunto curado de metas públicas, sin claves protegidas ni internas.
- **BREAKING:** `seo_description` deja de aceptar escritura por REST (se retira `update_callback`). No hay consumidores conocidos; la escritura de `_genesis_description` queda en el editor y en el plugin de SEO. La lectura se conserva, saneada.
- **Compatibilidad.** No se renombra ni se elimina ningún campo REST, tipo de post o clave de meta. Los canales legítimos siguen funcionando; solo se restringe lo indebido.

## Capabilities

### New Capabilities

- `rest-metafields`: contrato de exposición REST de los metadatos de post del plugin —activación sin error fatal, exclusión de metas internas (`_`), control de capacidad (`edit_posts`) en la escritura, campo `metadata` acotado a claves públicas y campo `seo_description` de solo lectura saneado—, conservando nombres de campos, tipos y claves.

### Modified Capabilities

- Ninguna. La capability `metaboxes` (delta en curso en `rest-blocks-hardening`) permanece como está: su requisito de «fuera de alcance» era una nota de ese change y no se modifica aquí.

## Fuera de alcance

- **Limpieza del requisito histórico de `metaboxes`.** El delta de `rest-blocks-hardening` incluye un requisito que declara `metadata` y `registerMetaFields()` «fuera de alcance» de *aquel* change. Este change no lo edita (la spec destino aún no existe); si al consolidar ambas specs resulta ambiguo, se limpiará en un change posterior.
- **Reescritura del guardado de metaboxes** (`saveMetaboxes`, `render*Metabox`) y del bloque de podcast: no cambia su comportamiento.

## Impact

- **Archivos a modificar (solo tras aprobación, en la fase TDD):**
  - `wp-content/plugins/atareao-functionality/includes/class-metaboxes.php` — corrección de `registerMetaFields()` y su enganche en `init`; `show_in_rest => false` para las metas `_`; `auth_callback` para las metas públicas; `metadata` acotado; `seo_description` de solo lectura.
- **Nuevas specs al archivar:** `openspec/specs/rest-metafields/spec.md` a partir del delta.
- **Contratos que NO se tocan:** los nombres de los campos REST `all_metadata`, `metadata` y `seo_description`; los tipos de post (`post`, `page`, `podcast`, `capitulo`, `tutorial`, `aplicacion`, `application`, `software`); los nombres de las metas; el hook `init` y el ciclo de registro.
- **Compatibilidad:** PHP 8.3, PSR12, WordPress 6.0+. Sin nuevas dependencias. Consumidores legítimos (editor autenticado, API de lectura pública, cliente MCP) siguen operando; solo se restringe la escritura indebida y la exposición de metas internas.
- **Verificación:** sin framework de tests ni build tools. Se verifica con `just php-lint` (0 errores) + `just phpcs` (baseline 752 errores / 429 warnings, objetivo +0), un arnés externo de stubs en `/tmp/opencode/metaboxes-meta-harness/` (no versionado) y E2E manual del usuario.
