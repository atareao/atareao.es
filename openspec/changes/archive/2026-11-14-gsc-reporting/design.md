# Design

## Context

El proyecto no tiene gestión de dependencias: no hay `composer.json`, ni `package.json`, ni `requirements.txt`. El único script Python del repositorio (`scripts/podcast-transcripts/transcribe.py`) usa exclusivamente la biblioteca estándar y sus pruebas corren con `python3 -m unittest`. Las tareas se orquestan con `just`, y los servicios con Podman y quadlets, con los secretos en `crypta`.

Existe una service account de GSC con permiso `siteFullUser` sobre `https://atareao.es/`, validada con un script temporal. La Search Console API exige un JWT firmado con RS256, cuyo resultado se canjea por un token de acceso; el ámbito de solo lectura basta para todo lo que se necesita.

La infraestructura se organiza en dos directorios: `quadlets/`, enlazado por `just install` a `~/.config/containers/systemd` y reservado a unidades de contenedor, y el resto del repositorio. Un temporizador de systemd de usuario no es un quadlet y no debe colocarse en ese directorio.

El sitio está protegido por un WAF propio que devuelve 503 ante ráfagas de peticiones: una muestra de 120 URLs con 24 peticiones concurrentes produjo 14 respuestas 503 que desaparecieron al repetirlas en serie.

## Goals / Non-Goals

**Goals:**

- Consultar GSC de la propiedad desde la terminal, con datos accionables y repetibles.
- Sacar un informe de oportunidades que priorice qué contenido mejorar.
- Auditar los sitemaps y detectar duplicidades y nombres engañosos de forma automática.
- Mantener la credencial fuera del repositorio, en el gestor de secretos ya adoptado.
- Ejecutar el informe de oportunidades de forma periódica sin intervención manual.
- No introducir ninguna dependencia nueva ni alterar nada del sitio público.

**Non-Goals:**

- Escribir en Search Console (enviar sitemaps, solicitar indexación) o modificar el sitemap del sitio.
- Crear un servidor MCP de GSC.
- Cambiar el theme, el plugin, el contenido, nginx, los quadlets o la base de datos.
- Analítica propia del sitio: para eso ya existe la analítica del proyecto.
- Rastrear las URLs del sitemap para auditar su estado.

## Decisions

### D1. Autenticación con la biblioteca estándar y `openssl`

Se firma el JWT con `openssl dgst -sha256 -sign` en lugar de usar `cryptography` o `PyJWT`, que exigirían instalar paquetes. La clave privada se entrega a `openssl` por entrada estándar, sin escribirla en disco.

- **Alternativas descartadas:** `cryptography` y `PyJWT` (imponen dependencias a un repo que no las tiene); `requests` para el transporte (ídem, la biblioteca estándar ya cubre las peticiones); convertir el script en algo que viva dentro de un contenedor (complejidad desproporcionada para una consulta HTTP).

### D2. Credencial en `crypta`, resuelta en tiempo de ejecución

Se almacena con `crypta store` (valor por entrada estándar) y se lee con `crypta lookup`. Se descarta pasar la credencial por argumento, porque los argumentos quedan visibles en el listado de procesos.

- **Alternativas descartadas:** JSON en el repositorio (fuga evidente); JSON en una ruta fija del home referenciada por variable de entorno (fuera del repo, pero igualmente sin cifrar ni auditado); variables de entorno con el JSON completo (hereda el problema de `crypta` y añade exposición en el entorno del proceso).

### D3. Una receta con subcomandos

`just gsc` actúa como passthrough hacia el script de informes, de modo que los subcomandos viven en Python y no en el fichero de recetas. Esto mantiene la lógica en un sitio testeable y evita multiplicar recetas.

- **Alternativas descartadas:** una receta por subcomando (duplica y dispersa); una receta genérica con parámetros posicionales (menos descubrible y sin nombres).

### D4. Salida legible y salida estructurada

Cada subcomando imprime una tabla legible por defecto y admite salida JSON. El informe periódico se escribe en Markdown, que es legible y además versionable si el usuario lo decidiera, sin que el repo lo fuerce.

### D5. Temporizador fuera del directorio de quadlets

Las unidades van a un directorio nuevo del repositorio y la receta de gestión las enlaza en `~/.config/systemd/user/`. No se colocan junto a los quadlets porque ese directorio lo consume el generador de Podman y solo espera unidades de contenedor.

### D6. Informes fuera del repositorio

El destino de los informes es `$HOME/.local/share/atareao/gsc/`, invirtiendo la decisión habitual de guardar artefactos en el repo: son datos de analítica de fecha variable, no fuente. Se evita así que cada ejecución programada ensucie el árbol de trabajo. La receta admite un destino explícito para quien prefiera otro sitio. La ruta se fija en `$HOME/.local/share` (y no se deriva de `XDG_DATA_HOME`) a propósito: la unidad de systemd usa `%h/.local/share/atareao/gsc/` y systemd no expande `XDG_DATA_HOME`, así que fijarla es lo que garantiza que unidad e instalador coincidan. La función `default_out_path` sí respeta `XDG_DATA_HOME` cuando se pide un informe por defecto sin `--out`, pero la ejecución desatendida siempre pasa `--out` con la ruta fija.

### D7. Cero rastreo de las URLs del sitemap

La auditoría descarga únicamente los ficheros sitemap y compara sus huellas. Rastrear 3.179 URLs sería lento y activaría el WAF del propio sitio, como ya se comprobó.

### D8. Transporte HTTP inyectable

El acceso a la red se aísla tras una función sustituible, de forma que las pruebas usen un doble y nunca dependan de internet ni de GSC. Es lo que permite tener pruebas deterministas para el formateo y la lógica de oportunidades.

## Risks / Trade-offs

- **`openssl` es una dependencia de sistema.** Está presente en el host y en las imágenes habituales, pero la integración debe comprobarlo al arrancar y fallar con un mensaje claro en vez de con un error críptico de proceso.
- **`crypta` debe seguir siendo no interactivo.** Hoy no pide frase de contraseña, pero si algún día lo hiciera, la ejecución desatendida se rompería silenciosamente. Es asumible porque el fallo es visible: el fichero del informe no se genera.
- **Cuota de la API.** El consumo es mínimo (bajo demanda y dos consultas semanales del temporizador), lejos de cualquier límite.
- **Los 143 warnings no se cierran con la herramienta.** La API no expone su desglose: la auditoría detecta la duplicidad y la incoherencia del índice, pero el detalle final requiere mirar la interfaz de Search Console.
- **Umbrales del informe de oportunidades.** El cruce de impresiones, posición y CTR es una heurística: sin un CTR esperable por posición, el orden es orientativo. Los umbrales quedan explícitos y ajustables en el código, y no se venden como verdad absoluta.
- **La comparativa de periodos consulta cuatro conjuntos de datos** (actual y anterior, con y sin dimensiones), lo que multiplica las peticiones respecto a un informe simple; se mantiene igualmente dentro de un uso razonable.
