# gsc-reporting Specification

## Purpose
Esta capability define la **consulta de solo lectura de Google Search Console desde el repositorio**: obtener clics, impresiones, CTR y posición de la propiedad `https://atareao.es/`, sacar de ahí un informe de oportunidades de contenido y auditar los sitemaps enviados, todo desde la terminal y sin abrir la interfaz web. La credencial vive en `crypta` y nunca en el repositorio; el ámbito se limita a `webmasters.readonly`, de modo que la integración no puede alterar nada en Search Console. La capability cubre además la ejecución periódica desatendida que deja el informe de oportunidades en un fichero localizado y fuera del árbol versionado. No define lógica de aplicación ni del sitio público: no toca el theme, el plugin, el contenido ni la configuración del servidor.

## Requirements

### Requirement: Acceso de solo lectura a Google Search Console

La integración SHALL consultar la Search Console API únicamente con el ámbito `https://www.googleapis.com/auth/webmasters.readonly` y SHALL limitarse a las operaciones de lectura `searchanalytics.query` y `sitemaps.list` sobre la propiedad `https://atareao.es/`. La integración SHALL NOT enviar sitemaps, SHALL NOT solicitar indexación y SHALL NOT realizar ninguna otra escritura en Search Console.

#### Scenario: Consulta de métricas de un periodo

- **WHEN** se solicita el resumen de un periodo de `N` días
- **THEN** se devuelven clics, impresiones, CTR y posición media del periodo, junto con las fechas de inicio y fin consultadas

#### Scenario: Intento de operación con efectos

- **WHEN** se necesita una operación que modifique Search Console (enviar un sitemap, pedir indexación)
- **THEN** la integración no la ofrece: el ámbito declarado es de solo lectura y ninguna herramienta del repositorio la ejecuta

### Requirement: Credencial resuelta desde crypta

La credencial de la service account SHALL almacenarse en `crypta` y SHALL resolverse en tiempo de ejecución mediante `crypta lookup`, sin que el JSON de la service account se versiona en el repositorio ni se transmite por argumentos de línea de comandos. Si la credencial no puede resolverse, la integración SHALL fallar con un mensaje explícito y SHALL NOT solicitar entrada interactiva.

#### Scenario: Credencial presente

- **WHEN** existe la clave de la service account en `crypta`
- **THEN** la integración obtiene la credencial, la usa para firmar la petición y completa la consulta sin pedir ninguna interacción

#### Scenario: Credencial ausente

- **WHEN** la clave no existe en `crypta`
- **THEN** la integración termina con error y un mensaje que indica el nombre exacto de la clave y el comando para darla de alta, sin volcar la credencial ni pedirla por teclado

#### Scenario: Sin credenciales en el repositorio

- **WHEN** se busca la clave privada, el correo de la service account o su identificador de clave en el árbol versionado
- **THEN** no hay ninguna coincidencia

### Requirement: Receta `just gsc` con subcomandos

El repositorio SHALL exponer una receta `gsc` que acepte los subcomandos `resumen`, `queries`, `paginas`, `sitemaps`, `oportunidades` y `compara`, y SHALL permitir acotar el número de filas, el número de días y la salida. La receta SHALL añadirse sin modificar ninguna receta existente del proyecto.

#### Scenario: Datos ordenados por valor

- **WHEN** se pide un listado de queries o de páginas
- **THEN** la salida viene ordenada por clics de mayor a menor, con impresiones y posición media visibles en cada fila

#### Scenario: Salida legible por máquina

- **WHEN** se invoca un subcomando con la opción de salida estructurada
- **THEN** el resultado se emite como JSON válido, sin texto decorativo que impida su consumo automático

#### Scenario: Recetas preexistentes intactas

- **WHEN** se compara la receta del proyecto antes y después de añadir `gsc`
- **THEN** las recetas existentes (`install`, `start`, `stop`, `status`, `test`, `php-lint`, `phpcs`, `build`, entre otras) conservan su comportamiento

### Requirement: Informe de oportunidades

La integración SHALL producir un informe de oportunidades que combine volumen de impresiones, posición media y CTR para ordenar candidatos a mejora de contenido por impacto potencial, y SHALL ofrecer una comparativa entre el periodo consultado y el periodo inmediatamente anterior.

#### Scenario: Candidato con visibilidad y baja conversión

- **WHEN** una consulta o página acumula impresiones altas, posición media modesta y CTR por debajo de lo esperable para esa posición
- **THEN** aparece en el informe de oportunidades con su volumen, su posición y su CTR, ordenada por impacto potencial

#### Scenario: Comparación de periodos

- **WHEN** se solicita la comparativa de un periodo
- **THEN** el informe muestra los valores del periodo actual y del anterior, con la variación de clics e impresiones entre ambos

### Requirement: Auditoría de sitemaps

La integración SHALL listar los sitemaps enviados a Search Console con sus errores y warnings, y SHALL señalar de forma explícita los sitemaps duplicados (misma huella de contenido) y los ficheros cuyo nombre declara un índice que no contiene ninguna referencia a otros sitemaps. La auditoría SHALL descargar como máximo los ficheros sitemap declarados y SHALL NOT rastrear las URLs que contienen.

#### Scenario: Sitemaps duplicados

- **WHEN** dos sitemaps enviados tienen contenido idéntico
- **THEN** la auditoría los marca como duplicados e indica cuál está declarado en el fichero de reglas de rastreo, para poder retirar el otro

#### Scenario: Nombre de índice sin serlo

- **WHEN** un sitemap enviado se llama index pero no contiene ninguna etiqueta de referencia a otros sitemaps
- **THEN** la auditoría lo señala como nombre engañoso

#### Scenario: Sin rastreo masivo

- **WHEN** se ejecuta la auditoría de sitemaps
- **THEN** el número de peticiones al sitio público se limita a los ficheros sitemap declarados, sin recorrer las URLs que contienen

### Requirement: Ejecución periódica desatendida

El proyecto SHALL proporcionar una unidad temporizada de systemd de usuario que ejecute periódicamente el informe de oportunidades y SHALL escribir su salida fuera del árbol versionado, en `$HOME/.local/share/atareao/gsc/`. Esa ruta es **fija** (no se deriva de `XDG_DATA_HOME`) para que la unidad y la receta que la instala coincidan siempre, aunque el usuario defina `XDG_DATA_HOME`. El ciclo de vida de esa unidad SHALL gestionarse desde una receta del proyecto. La ejecución desatendida SHALL NOT requerir ninguna interacción ni credencial introducida a mano.

#### Scenario: Ejecución programada

- **WHEN** llega la fecha programada del temporizador
- **THEN** se genera el informe de oportunidades con la fecha del día en el nombre del fichero, bajo `$HOME/.local/share/atareao/gsc/`, sin intervención del usuario

#### Scenario: Ciclo de vida desde el repositorio

- **WHEN** se usa la receta de gestión del temporizador
- **THEN** se pueden enlazar las unidades, habilitarlas, consultar su estado y ver sus registros sin manipular ficheros a mano

#### Scenario: Informes fuera del repositorio

- **WHEN** se inspecciona el árbol de trabajo tras una ejecución
- **THEN** el informe generado no aparece como cambio pendiente, porque su destino está fuera del repositorio

### Requirement: Cero dependencias externas de Python

La integración SHALL funcionar con la biblioteca estándar de Python y `openssl`, sin fichero de dependencias ni paquetes instalados aparte. Las pruebas SHALL ejecutarse sin red mediante un transporte HTTP inyectable.

#### Scenario: Ejecución en un host sin paquetes extra

- **WHEN** se ejecuta la integración en un host con `python3` y `openssl` pero sin paquetes de Python instalados por el usuario
- **THEN** la integración funciona sin instalar nada

#### Scenario: Pruebas sin red

- **WHEN** se ejecuta la batería de pruebas de la integración
- **THEN** no se realiza ninguna petición de red real y el transporte HTTP se sustituye por un doble de prueba
