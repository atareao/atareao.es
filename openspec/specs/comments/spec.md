# comments Specification

## Purpose
Esta capability fija el contrato de seguridad de los comentarios de atareao.es: todo dato de comentario no confiable se escapa en el punto de salida del tema, la nota de «Responder» del JavaScript se construye con nodos de texto (sin `innerHTML`), el captcha incluye el tiempo en su firma HMAC y comprueba cota inferior y superior, y el contenido del comentario se sanea antes de insertarse como defensa en profundidad. Cierra la cadena de XSS almacenado explotable por un comentarista anónimo sin cambiar el contrato de campos, hooks ni opciones.

## Requirements

### Requirement: Salida escapada del autor y del contenido en las plantillas

El tema SHALL escapar en la salida todos los datos de comentario que pinta: el nombre del autor y su URL en `atareao_comment_callback`, el contenido de la rama fallback del AJAX y los títulos de archivo de `index.php`. El nombre SHALL escaparse con `esc_html` y la URL con `esc_url`; el contenido de la rama fallback SHALL sanearse con `wp_kses_post`. El saneado de entrada (`sanitize_text_field`) SHALL NOT considerarse suficiente: la cadena almacenada puede contener entidades HTML (por ejemplo `&lt;img src=x onerror=alert(1)&gt;`) que el navegador decodifica al insertarse como HTML. Los `printf` de `index.php` para `single_cat_title`/`single_tag_title`/`get_the_author` SHALL escapar el valor con `esc_html`.

#### Scenario: Autor con entidades HTML no inyecta marcado

- **GIVEN** un comentario cuyo autor almacenado es `&lt;img src=x onerror=alert(1)&gt;`
- **WHEN** el tema renderiza el comentario con `atareao_comment_callback`
- **THEN** el HTML servido escapa el nombre y muestra el texto literal, sin crear un elemento `<img>` ni ejecutar `onerror`

#### Scenario: Nombre y URL del autor escapados en el listado

- **GIVEN** un comentario con nombre y URL
- **WHEN** `atareao_comment_callback` pinta el bloque `.comment-author-info`
- **THEN** el nombre se emite con `esc_html` y la URL con `esc_url`, conservando el enlace cuando la URL es legítima

#### Scenario: Contenido de la rama fallback escapado

- **GIVEN** una respuesta AJAX que cae en la rama fallback del render (`functions.php:246`)
- **WHEN** se compone el `comment_html`
- **THEN** el contenido se pasa por `wp_kses_post` y no se emite marcado no confiable

#### Scenario: Títulos de archivo escapados

- **GIVEN** una vista de categoría, de etiqueta o de autor
- **WHEN** `index.php` pinta el `page-title` mediante `printf`
- **THEN** el término (`single_cat_title`/`single_tag_title`) o el nombre (`get_the_author`) se escapan con `esc_html` antes del `printf`

### Requirement: Construcción segura de la nota de respuesta en el JavaScript

El JavaScript de «Responder» SHALL construir la nota sin reinyectar el nombre del autor como HTML. El nombre SHALL insertarse con `textContent` o `createTextNode`, y el botón «Cancelar» SHALL crearse con `createElement('button')`, fijando `type="button"` e `id="atareao-cancel-reply"` y enganchando su `click` con `addEventListener`. El fichero `js/comment-ajax.js` SHALL NOT usar `innerHTML` ni `insertAdjacentHTML` para componer la nota. El fichero minificado `js/comment-ajax.min.js` SHALL reflejar el mismo cambio, ya que el repositorio no tiene build tools. El uso de `innerHTML` para insertar el `comment_html` ya saneado que devuelve el servidor queda fuera del alcance de este requisito. La posición de la nota y el comportamiento del botón «Cancelar» (resetear `comment_parent` a `0` y eliminar la nota) SHALL conservarse.

#### Scenario: El nombre no se interpreta como HTML

- **GIVEN** un comentario cuyo autor almacenado es `&lt;img src=x onerror=alert(1)&gt;`
- **WHEN** el usuario pulsa «Responder»
- **THEN** la nota muestra el nombre como texto y no crea ningún elemento `<img>` ni ejecuta `onerror`

#### Scenario: El botón Cancelar se crea con createElement y sigue funcionando

- **WHEN** el usuario pulsa «Responder»
- **THEN** existe un botón `type="button"` con id `atareao-cancel-reply` creado con `createElement` que, al pulsarlo, elimina la nota y devuelve `comment_parent` a `0`

#### Scenario: Sin el sink innerHTML en la nota

- **GIVEN** el código de `js/comment-ajax.js` y de `js/comment-ajax.min.js`
- **THEN** la construcción de la nota no usa `innerHTML` ni `insertAdjacentHTML`, y el minificado no contiene la concatenación «Respondiendo a » con `innerHTML`

### Requirement: Captcha firmado con el tiempo y con cota superior

El sistema SHALL incluir el instante del formulario (`form_time`) en la firma HMAC del captcha, de modo que la firma sea `hash_hmac('sha256', $a . ':' . $b . ':' . $form_time, wp_salt('nonce'))`, en lugar de firmar solo `a:b`. La validación SHALL comprobar la cota inferior (el formulario no puede enviarse antes de un mínimo de segundos) y una cota superior (el formulario caduca pasado un máximo), rechazando el envío con un error accionable cuando se incumpla cualquiera de las dos. El endurecimiento SHALL aplicarse a `CommentSecurity::validateComment()`, a `CommentSecurity::processAjaxComment()` y a `ContactForm`. Los nombres de campos, hooks y opciones SHALL conservarse.

#### Scenario: Cambiar el tiempo invalida la firma

- **GIVEN** un captcha con una firma calculada para un `form_time` concreto
- **WHEN** se envía el mismo par `a:b` con otro `form_time`
- **THEN** el sistema rechaza el envío por firma inválida

#### Scenario: Cota inferior del formulario

- **WHEN** el formulario se envía menos de 2 segundos después de haberse generado
- **THEN** el sistema lo rechaza con el aviso de formulario enviado demasiado rápido

#### Scenario: Cota superior del formulario

- **WHEN** el `form_time` supera la antigüedad máxima permitida (3600 segundos)
- **THEN** el sistema lo rechaza como formulario caducado

#### Scenario: También en el formulario de contacto

- **WHEN** el formulario de contacto se envía con un `form_time` manipulado o fuera de la ventana válida
- **THEN** la firma no valida y el sistema rechaza el envío

### Requirement: Sanitización del contenido del comentario

El contenido del comentario SHALL sanitizarse por tipo antes de insertarse y SHALL NOT conservar marcado no permitido. La sanitización de entrada SHALL tratarse como defensa en profundidad y SHALL NOT sustituir al escape de salida. El autor y la URL SHALL seguir sanitizándose por tipo (`sanitize_text_field` y `esc_url_raw`) sin cambiar el contrato ni los campos existentes.

#### Scenario: Contenido saneado antes de insertar

- **WHEN** se envía un comentario con marcado no permitido en el contenido
- **THEN** el contenido almacenado no conserva ese marcado no permitido

#### Scenario: La entrada saneada no exime del escape de salida

- **GIVEN** un valor de entrada que ya pasó por el saneado y aún contiene entidades
- **WHEN** la plantilla lo pinta
- **THEN** la plantilla lo escapa igualmente en la salida, sin confiar en el saneado de entrada
