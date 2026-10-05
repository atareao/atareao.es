# anti-abuse Delta

## Purpose

Esta capability fija el contrato de los **controles antiabuso de la superficie pública** de `atareao-functionality`: el formulario de contacto (`ContactForm::handleSubmission()`, `template_redirect`) y el endpoint AJAX público de conteo de vistas (`wp_ajax_nopriv_atareao_track_view`). Cubre los hallazgos de la auditoría de formularios/AJAX FR-01 (bombeo ilimitado de mensajes a Matrix), FR-02 (inflado de `post_views_count` eludiendo la cookie), FR-03 (firma del captcha sin el tiempo), FR-04 (ventana temporal sin cota superior y `form_time` controlado por el usuario) y FR-08 (captcha aritmético resoluble en el cliente, documentado como limitación). El objetivo es frenar el abuso automatizado **sin cambiar campos, hooks, nonces, opciones, acciones AJAX ni el comportamiento visible del sitio**: los controles antiabuso son aditivos y el uso legítimo no se ve afectado.

## ADDED Requirements

### Requirement: Rate limiting y dedupe del formulario de contacto

El formulario de contacto SHALL aplicar, **en el servidor y antes de llamar a `MatrixConfig::sendMatrixMessage()`**, un **rate limiting por IP de origen** con ventana fija y un **tope de envíos por ventana** (ajustable por filtro/constante), sobre un transient de WordPress cuya clave sea un hash de la IP, **sin almacenar la dirección en claro**. El sistema SHALL aplicar además un **dedupe/tope por ventana** que impida el bombeo repetido desde una misma IP. Al superar cualquier límite, el sistema SHALL **no enviar el mensaje a Matrix** y SHALL responder con el mensaje de error accionable ya existente (redirección con `atareao_contact=error`), **sin** revelar información interna. El sistema SHALL NOT confiar en cabeceras de proxy (`X-Forwarded-For` u otras) para determinar la IP salvo configuración explícita. Los nonces, campos, mensajes de error visibles y la redirección SHALL conservarse.

#### Scenario: Envío dentro del límite

- **GIVEN** una IP que no ha superado el tope de envíos de la ventana
- **WHEN** un visitante envía el formulario de contacto con nonce, captcha y `form_time` válidos
- **THEN** el sistema valida el envío, lo entrega a Matrix y redirige con `atareao_contact=success`

#### Scenario: Uso legítimo no bloqueado

- **WHEN** una persona envía varios mensajes a lo largo del día por debajo del tope
- **THEN** el sistema atiende todos sus envíos sin bloquearla

#### Scenario: Superación del tope

- **GIVEN** una IP que ha agotado el número de envíos permitidos en la ventana
- **WHEN** se envía un nuevo POST al formulario de contacto con datos válidos
- **THEN** el sistema responde con el error accionable y **no** llama a `sendMatrixMessage()`

#### Scenario: Contadores por IP aislados

- **WHEN** una IP ha agotado su límite y otra IP distinta envía el formulario
- **THEN** la segunda IP no se ve afectada por el contador de la primera

#### Scenario: Cabeceras de proxy no confiables

- **WHEN** una petición intenta variar su IP de origen manipulando cabeceras de proxy no configuradas
- **THEN** el sistema usa la IP que ve el servidor y no la cabecera manipulada para el límite

### Requirement: Dedupe server-side del contador de vistas

El endpoint `wp_ajax_nopriv_atareao_track_view` SHALL deduplicar en el **servidor** además de la cookie del cliente, de modo que una petición sin cookie no pueda incrementar `post_views_count` arbitrariamente. El sistema SHALL registrar un transient por `post_id` y **IP de origen hasheada**, con TTL correspondiente a la ventana; una segunda petición del mismo cliente para el mismo post dentro de la ventana SHALL **no incrementar** el contador y SHALL responder con `cached: true` y el valor actual. La deduplicación SHALL estar **acotada**: como máximo un incremento por `post_id`/IP/ventana. El sistema SHALL conservar la validación de `post_id`, el nonce existente y la forma de la respuesta (`success`, `cached`, `views`); SHALL NOT almacenar la IP en claro.

#### Scenario: Primera vista

- **GIVEN** una IP que no ha visto el post dentro de la ventana
- **WHEN** llega una petición válida a `atareao_track_view` con `post_id` correcto
- **THEN** el sistema incrementa `post_views_count` una vez y responde con el nuevo valor

#### Scenario: Segunda vista sin cookie

- **GIVEN** que la misma IP ya incrementó el contador del post dentro de la ventana
- **WHEN** llega otra petición **sin la cookie** `atareao_post_view_<post_id>`
- **THEN** el sistema no incrementa `post_views_count` y responde `cached: true` con el valor actual

#### Scenario: Otra IP sí incrementa

- **GIVEN** que una IP ya contó el post dentro de la ventana
- **WHEN** otra IP distinta solicita contar el mismo post
- **THEN** el contador se incrementa para esa nueva IP

#### Scenario: La ventana caduca

- **WHEN** transcurre el TTL del transient de deduplicación y el mismo cliente vuelve a solicitar el post
- **THEN** el sistema vuelve a contar la vista

#### Scenario: Post inválido no escribe

- **WHEN** la petición no acredita el nonce o no aporta un `post_id` válido
- **THEN** el sistema responde el error correspondiente sin modificar `post_views_count`

### Requirement: Firma del captcha con el tiempo y ventana temporal acotada

El sistema SHALL incluir el instante del formulario (`form_time`) en la firma HMAC del captcha, de modo que la firma sea `hash_hmac('sha256', $a . ':' . $b . ':' . $form_time, wp_salt('nonce'))`, y SHALL comprobar una **cota inferior** (el formulario no puede enviarse antes de un mínimo de segundos) y una **cota superior** (el formulario caduca pasado un máximo), rechazando el envío con un error accionable cuando se incumpla cualquiera de las dos. El contrato SHALL aplicarse a `CommentSecurity::validateComment()`, a `CommentSecurity::processAjaxComment()` y a `ContactForm`, y los nombres de campos, hooks y nonces SHALL conservarse. Cambiar el `form_time` SHALL invalidar la firma.

#### Scenario: Cambiar el tiempo invalida la firma

- **GIVEN** un captcha con una firma calculada para un `form_time` concreto
- **WHEN** se reenvía el mismo par `a:b` con otro `form_time`
- **THEN** el sistema rechaza el envío por firma inválida

#### Scenario: Cota inferior del formulario

- **WHEN** el formulario se envía menos de 2 segundos después de haberse generado
- **THEN** el sistema lo rechaza con el aviso de formulario enviado demasiado rápido

#### Scenario: Cota superior del formulario

- **WHEN** el `form_time` supera la antigüedad máxima permitida (3600 segundos)
- **THEN** el sistema lo rechaza como formulario caducado

#### Scenario: También en el formulario de contacto

- **WHEN** el formulario de contacto se envía con un `form_time` manipulado o fuera de la ventana válida
- **THEN** la firma no valida y el sistema rechaza el envío sin llamar a `sendMatrixMessage()`

#### Scenario: También en la ruta AJAX de comentarios

- **WHEN** el comentario se envía por `processAjaxComment()` con un `form_time` manipulado o fuera de la ventana
- **THEN** el sistema responde `status: error` sin insertar el comentario

### Requirement: Limitación documentada del captcha aritmético

El sistema SHALL documentar que el captcha aritmético es una **defensa en profundidad** y no un control anti-bot robusto, porque ambos operandos viajan al cliente en campos ocultos (`page-contact.php:49-51`, `class-comment-security.php:57-66`) y son trivialmente resolubles por un bot. Los controles que realmente frenan la automatización SHALL ser el rate limiting, el dedupe server-side, el honeypot y la moderación. Esta documentación SHALL NOT implicar cambio funcional alguno en el captcha, sus campos o su validación.

#### Scenario: La limitación queda escrita

- **WHEN** una persona consulta la documentación de la capability antiabuso
- **THEN** encuentra explícito que el captcha aritmético es solo defensa en profundidad y que los controles principales son el rate limiting, el dedupe, el honeypot y la moderación

#### Scenario: El captcha no es el único control

- **GIVEN** un bot que resuelve correctamente el captcha aritmético
- **WHEN** intenta enviar el formulario o contar vistas de forma masiva
- **THEN** el sistema lo frena con el rate limiting y el dedupe server-side, no con el captcha

#### Scenario: Sin cambio funcional

- **WHEN** se comparan los campos y la validación del captcha antes y después del change
- **THEN** el contrato del captcha (campos `atareao_captcha_a`/`_b`/`_sig`, `atareao_form_time` y su comprobación) no cambia
