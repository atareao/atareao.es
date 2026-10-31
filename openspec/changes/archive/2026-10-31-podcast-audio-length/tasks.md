# Tasks — Tamaño real del audio remoto en el `<enclosure>`

## 1. Caracterización de la ruta local (RED → GREEN)

- [x] 1.1 RED — Añadir a `tests/test-valet.php` el escenario «Tamaño resuelto desde el adjunto local»: la URL del audio resuelve a un adjunto legible → `length` es el tamaño local y **no** se registra ninguna petición de red (`mock_remote_requests` vacío). **Verificación:** `just test` falla solo en las aserciones nuevas.
- [x] 1.2 GREEN — Mantener intacta la ruta local de `audioLength()` (adjunto legible → `filesize()`). **Verificación:** `just test` 100 % verde.

## 2. Tamaño por `HEAD` remoto (RED → GREEN)

- [x] 2.1 HARNESS — Dobles fieles a core de `wp_safe_remote_head`, `wp_remote_retrieve_header`, `wp_remote_retrieve_response_code` y `wp_remote_retrieve_body` en `tests/wp-stubs.php` (registran la petición en `mock_remote_requests` y consumen `mock_remote_response`). **Verificación:** `just test` sin cambios en el recuento.
- [x] 2.2 RED — Escenario «Tamaño resuelto con `HEAD` siguiendo redirecciones»: audio remoto, `HEAD` `200` con `Content-Length` → `length` == ese tamaño. **Verificación:** `just test` falla solo en las aserciones nuevas.
- [x] 2.3 GREEN — Implementar la resolución por `HEAD` y su uso en `audioLength()`; `itemElements()` pasa el ID del episodio. **Verificación:** `just test` 100 % verde.

## 3. Redirecciones (RED → GREEN)

- [x] 3.1 RED — Escenario de redirección: `HEAD` `302` con `Location` a la CDN y `HEAD` final `200` con `Content-Length` → se usa el tamaño final; el número de saltos no supera el máximo. **Verificación:** `just test` falla solo en las aserciones nuevas.
- [x] 3.2 GREEN — Seguir redirecciones **manualmente** (`redirection => 0`), resolviendo `Location` relativas y con tope de saltos. **Verificación:** `just test` 100 % verde.

## 4. Reserva con `Range` (RED → GREEN)

- [x] 4.1 RED — Escenario «`Range` cuando `HEAD` no lo declara»: `HEAD` `405` (o `200` sin longitud) → `GET` con `Range: bytes=0-0` `206` y `Content-Range: bytes 0-0/24594909` → `length` == 24594909. **Verificación:** `just test` falla solo en las aserciones nuevas.
- [x] 4.2 GREEN — Implementar la reserva: `GET` con `Range` cuando el `HEAD` no da longitud; parsear el total del `Content-Range`. **Verificación:** `just test` 100 % verde.
- [x] 4.3 RED — Aserción de que la reserva declara un tope de cuerpo (`limit_response_size`) además de `Range` y `redirection => 0`. **Verificación:** `just test` falla solo en las aserciones nuevas.
- [x] 4.4 GREEN — Añadir `limit_response_size` a la petición `Range`. **Verificación:** `just test` 100 % verde.

## 5. Guarda SSRF (RED → GREEN)

- [x] 5.1 RED — Escenario «Se rechaza un host interno»: `http://127.0.0.1/x.mp3` (loopback), `http://10.0.0.1/x.mp3` (privado), `http://169.254.169.254/x.mp3` (link-local de metadatos), `file:///x.mp3` (esquema) y una redirección a `http://192.168.1.1/x.mp3` → sin contacto y `length` == 0. **Verificación:** `just test` falla solo en las aserciones nuevas.
- [x] 5.2 GREEN — Validar cada URL (esquema, host público, sin userinfo) **antes** de cada petición y **en cada salto**; `reject_unsafe_urls => true`. **Verificación:** `just test` 100 % verde.

## 6. Caché del tamaño (RED → GREEN)

- [x] 6.1 RED — Escenario «La resolución remota se cachea y no se repite»: tras la primera resolución, una segunda llamada no registra peticiones de red. El fallo se cachea con caducidad corta: dentro de la ventana no se reintenta, caducado sí. **Verificación:** `just test` falla solo en las aserciones nuevas.
- [x] 6.2 GREEN — Guardar el resultado en el metadato del episodio (acierto con caducidad larga, fallo con caducidad corta) y consultarlo antes de tocar la red. **Verificación:** `just test` 100 % verde.

## 7. Presupuesto de red por composición (RED → GREEN)

- [x] 7.1 RED — Escenario de presupuesto: con el presupuesto agotado, un episodio sin resolver no hace petición de red y emite `length` == 0, sin romper el XML. **Verificación:** `just test` falla solo en las aserciones nuevas.
- [x] 7.2 GREEN — Implementar el presupuesto (tiempo máximo por composición) en la emisión del `<item>`. **Verificación:** `just test` 100 % verde.

## 8. Comando WP-CLI de calentado (recomendado)

- [x] 8.1 RED — Escenario del comando: recorre los episodios publicados con `mp3-url`, resuelve y cachea su tamaño e informa el recuento resuelto/no resuelto. Nota: el arnés no define `WP_CLI`, así que el escenario cubre `warmAudioLengths()` —el núcleo del comando—; el registro del comando se verifica por lectura. **Verificación:** `just test` falla solo en las aserciones nuevas.
- [x] 8.2 GREEN — Registrar el comando `wp atareao podcast-audio-length` cuando `WP_CLI` está definido y `\WP_CLI` existe; `init()` queda idéntico en lo demás (la aserción de aridad de `rss2_item` lo comprueba). **Verificación:** `just test` 100 % verde.

## 9. Refactor y análisis estático

- [x] 9.1 `just php-lint` sin errores y `just phpcs` sin violaciones nuevas. **Verificación:** salida literal de ambos.
- [x] 9.2 Sin código muerto ni rutas alternativas: ninguna otra parte mide el tamaño ni emite `<enclosure>`. **Verificación:** `rg -n "audioLength|Content-Length|Content-Range|enclosure"`.

## 10. Verificación final

- [x] 10.1 Arnés procedural completo en verde. **Verificación:** `just test` con el recuento de aserciones a favor y 0 fallos.
- [x] 10.2 `openspec validate podcast-audio-length --strict` sin hallazgos.
- [x] 10.3 No regresión de los escenarios existentes del requisito: enclosure único, numeración, sin audio, transcripción, aridad de callbacks y `charset`. **Verificación:** aserciones del arnés.
- [x] 10.4 E2E en producción tras el despliegue: el feed servido declara `length` > 0 en los episodios resueltos, el XML sigue bien formado, `/feed/` no cambia y el tiempo de composición se mantiene dentro del presupuesto. **Verificación:** `curl` con el User-Agent de la ruta exenta y análisis del XML con `python3`.

  **Resultado (2026-10-31, producción):** calentado con `wp atareao podcast-audio-length` → 834 resueltos / 4 fallidos. Feed servido: `status=200`, `application/rss+xml; charset=UTF-8`, `x-cache-status: BYPASS`, 100 `<item>` y 100 `<enclosure>` todos `audio/mpeg` con URL `http`, **100/100 con `length` > 0** (ninguno en `0`), suma declarada 1755,1 MB, XML bien formado, cierra `</channel></rss>`, `t=10,9 s` contra los 12,9 s previos al change, y `/feed/` intacto (100 items, 0 enclosures, 0 vocabulario podcasting).
  **Evidencia previa al despliegue, sobre el código real dentro del WordPress de producción** (fichero íntegro con la clase renombrada y `postId=0`, sin escribir caché): 24 594 909 B por las tres vías (`HEAD` siguiendo la redirección de `anchor.fm`, `HEAD` directo a CloudFront y reserva `Range` invocada por reflexión) y 15 señuelos SSRF a `0` (loopback/privado/link-local IPv4 e IPv6, `localhost` por nombre, host sin DNS, `file://`, `gopher://`, credenciales embebidas, sin esquema, 404 y cadena vacía). El calentador encuentra 838 episodios.

## 11. Entrega

- [x] 11.1 PR por gitflow con commits convencionales (gitmoji) a `development`: **PR #137**, fusionado como `7eab7bd`.
- [x] 11.2 Marcar las tareas y archivar el change, verificando que el header del delta coincide literalmente con `openspec/specs/podcast-feed/spec.md`. Header `### Requirement: Episodio con audio y numeración` verificado carácter a carácter contra la spec destino.

## 13. Despliegue y reversión (2026-10-31)

- **Fichero único desplegado:** `wp-content/plugins/atareao-functionality/includes/class-podcast-feed.php`. La fusión no tocó ningún otro fichero de `wp-content/`, así que el despliegue es una sola copia al volumen de producción (`docker cp` al contenedor `atareao`), seguida de `chown www-data:www-data` y `chmod 644`.
- **Copia de seguridad:** `~/backups/atareao-plugin/class-podcast-feed.20261031-122916.php` — `md5 e150d0dd88df751cc4b607bd17c12097`, la versión anterior (la del change `podcast-feed-namespace`).
- **Versión desplegada:** `md5 05cf6b4e8259eea113ca15a26dc0d530`, idéntico al del repositorio en `development`. Sin reinicio del contenedor: el opcache tiene `validate_timestamps=On` y `revalidate_freq=2`, así que recoge el fichero solo.
- **Reversión:** volver a copiar la copia de seguridad con el mismo `docker cp`, y —si se quiere el estado exacto anterior— borrar la caché con `wp post meta delete <id> _atareao_podcast_audio_length`.
- **Nota operativa:** sin el calentado, cada composición del feed gastaría hasta ~5 s de red y resolvería ~25-60 episodios por pasada, rellenándose en composiciones posteriores; el calentado en una pasada lo evita.
- **Trazabilidad de la evidencia:** el archive elimina este directorio, así que la evidencia de 10.4 y este registro de despliegue quedan en el historial de git y en el PR de archive, siguiendo la convención de los changes anteriores.

## 12. Decisiones y limitaciones conocidas

- **Escritura de metadatos durante la composición del feed (INFO):** resolver y cachear el tamaño implica un `update_post_meta()` durante un `GET` del feed. Es intencional y está acotado por el presupuesto; la alternativa —calentado solo por WP-CLI/Cron— dejaría `0` mientras nadie caliente la caché.
- **Cobertura real de la guarda SSRF (verificado en el core desplegado):** `wp_safe_remote_*` fuerza `reject_unsafe_urls`, y `wp_http_validate_url()` de WP 7.1.4 **sí resuelve el host** con `gethostbyname()` y rechaza los rangos loopback/privados/reservados (incl. `169.254.0.0/16`, `100.64.0.0/10` y las TEST-NET) antes de cada petición, además de exigir `http`/`https` y prohibir credenciales embebidas. La guarda propia añade el chequeo de literales IPv6. **Limitaciones residuales, dichas tal cual:** (a) el arnés no resuelve DNS, así que los escenarios cubren literales y esquemas, no nombres que resuelvan a un host interno; (b) queda una ventana TOCTOU entre la resolución de la validación y la que hace el transporte HTTP (DNS rebinding); (c) si el host coincide con el dominio del sitio, el core omite la comprobación de rangos —intencional, para permitir audio autoalojado—.
- **Granularidad del presupuesto de red (INFO):** el presupuesto se consulta **una vez por episodio**, no dentro de la resolución, así que una composición puede exceder `REMOTE_BUDGET_SECONDS` en, como mucho, lo que tarde la resolución ya en curso (tope teórico: `REMOTE_MAX_REDIRECTS` × `REMOTE_TIMEOUT` + una reserva). En la práctica el presupuesto corta el barrido y las composiciones siguientes sirven de la caché; la alternativa —abortar a mitad de una petición— no es expresable con la API HTTP de WordPress.
- **Dependencia de entrega:** el delta modifica el requisito de `podcast-feed`, capability que **crea** el archive del change `podcast-feed-namespace` (PR #135). Este change se apoya en ese archive: no puede fusionarse antes que él.
