# Tasks

## 1. Credencial y preparación

- [x] 1.1 Dar de alta la service account en `crypta` como `atareao_gsc_service_account` con el valor por entrada estándar, sin que la credencial quede en el historial de comandos ni en el árbol de trabajo
- [x] 1.2 Verificar que `crypta lookup atareao_gsc_service_account` devuelve un JSON válido sin pedir interacción, sin imprimir la clave privada en la salida
- [x] 1.3 Comprobar en el host la presencia de `python3` y `openssl` y registrar las versiones

## 2. Pruebas primero (RED)

- [x] 2.1 Crear `tests/gsc/` con fixtures de respuestas grabadas: resumen sin dimensiones, listado de queries, listado de páginas, listado de sitemaps con duplicados y con un índice que no lo es, y respuesta de comparación de periodos
- [x] 2.2 Escribir `tests/gsc/test_gsc.py` (unittest) para la construcción y firma del JWT, la resolución de la credencial y el canje del token, con transporte falso
- [x] 2.3 Escribir pruebas para `searchanalytics.query` y `sitemaps.list` a partir de los fixtures, sin red
- [x] 2.4 Escribir pruebas para el formateo de tablas, la salida JSON, el orden por clics y el cálculo de la variación entre periodos
- [x] 2.5 Escribir pruebas para la lógica de oportunidades y para la detección de sitemaps duplicados y de índices que no lo son
- [x] 2.6 Ejecutar `python3 -m unittest discover -s tests/gsc -v` y confirmar que las pruebas nuevas fallan por funcionalidad ausente

## 3. Cliente de la Search Console API (GREEN)

- [x] 3.1 Implementar en `scripts/gsc/gsc.py` la resolución de la credencial desde `crypta`, con error explícito y sin interacción cuando falte
- [x] 3.2 Implementar la construcción del JWT RS256 y su firma mediante `openssl` con la clave por entrada estándar, sin escribirla en disco
- [x] 3.3 Implementar el canje del JWT por el token de acceso con el ámbito de solo lectura
- [x] 3.4 Implementar `searchanalytics.query` (rango de fechas, dimensiones, límite de filas) y `sitemaps.list`, con transporte HTTP inyectable
- [x] 3.5 Implementar la descarga de los ficheros sitemap declarados y el cálculo de su huella, sin rastrear las URLs que contienen

## 4. Informes y subcomandos (GREEN)

- [x] 4.1 Implementar en `scripts/gsc/report.py` los subcomandos `resumen`, `queries`, `paginas`, `sitemaps`, `oportunidades` y `compara`
- [x] 4.2 Implementar el formateo en tabla y la salida JSON, con orden por clics descendente
- [x] 4.3 Implementar el cálculo de oportunidades combinando impresiones, posición y CTR, con umbrales explícitos y orden por impacto potencial
- [x] 4.4 Implementar la comparativa entre el periodo consultado y el anterior, con la variación de clics e impresiones
- [x] 4.5 Implementar el volcado a fichero con destino explícito y nombre de fichero fechado para el informe periódico
- [x] 4.6 Ejecutar `python3 -m unittest discover -s tests/gsc -v` y confirmar que todas las pruebas pasan

## 5. Recetas del proyecto

- [x] 5.1 Añadir la receta `gsc` como passthrough con subcomandos, sin tocar ninguna receta existente
- [x] 5.2 Añadir la receta de pruebas de la integración
- [x] 5.3 Verificar que las recetas preexistentes conservan su comportamiento: `just --list` y ejecución de las recetas de estado y de calidad

## 6. Ejecución periódica desatendida

- [x] 6.1 Crear `systemd/atareao-gsc-opportunities.service` (ejecución única, salida al directorio de datos del usuario)
- [x] 6.2 Crear `systemd/atareao-gsc-opportunities.timer` (semanal, con recuperación de ejecuciones perdidas)
- [x] 6.3 Añadir la receta de gestión del temporizador con las acciones de enlace, habilitación, consulta de estado y consulta de registros
- [x] 6.4 Verificar una ejecución manual de la unidad y confirmar que el informe aparece en el directorio de datos y no en el árbol de trabajo

## 7. Refactor y calidad

- [x] 7.1 Refactorizar el cliente y los informes sin cambiar comportamiento
- [x] 7.2 Confirmar que la integración no importa módulos fuera de la biblioteca estándar y que no hay fichero de dependencias nuevo
- [x] 7.3 Ejecutar `python3 -m unittest discover -s tests/gsc -v` y dejar la salida en verde
- [x] 7.4 Ejecutar `just test` y confirmar que el arnés del proyecto sigue en verde sin cambios
- [x] 7.5 Ejecutar `just php-lint` y confirmar que no hay errores nuevos

## 8. Verificación anti-fuga y evidencia

- [x] 8.1 Buscar en el árbol versionado la clave privada, el correo de la service account y su identificador de clave, y confirmar cero coincidencias
- [x] 8.2 Revisar el estado del repositorio y confirmar que ningún fichero con credenciales queda pendiente de seguimiento
- [x] 8.3 Adjuntar la salida literal de `just gsc resumen`
- [x] 8.4 Adjuntar la salida literal de `just gsc sitemaps`, señalando `sitemap_index.xml` como duplicado y no-índice
- [x] 8.5 Adjuntar la salida literal de `just gsc oportunidades`
- [ ] 8.6 Comprobar en la interfaz de Search Console el desglose de los 143 warnings y dejar constancia del resultado
  - *La API no expone ese desglose; `just gsc sitemaps` ya señala el duplicado y el falso índice. La comprobación manual queda pendiente del usuario.*

## 9. Documentación y cierre

- [x] 9.1 Escribir `docs/gsc-reporting.md` con el funcionamiento, la configuración, el alta de la credencial y las decisiones
- [x] 9.2 Actualizar las casillas de este change, validarlo y archivar la capability en las specs del proyecto
- [ ] 9.3 Preparar la rama de feature y la propuesta de integración hacia la rama de desarrollo
  - *Se completa al abrir la propuesta de integración.*
