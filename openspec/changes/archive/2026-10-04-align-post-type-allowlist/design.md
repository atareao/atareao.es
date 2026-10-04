# Design: Alinear el contrato de `post_type` con la lista permitida

## Contexto

`postTypeArgument()` decide si el argumento explícito `post_type` es válido. Hoy usa `publicPostTypes()`, que envuelve `get_post_types(array('public' => true))`. Ese conjunto es **dependiente del runtime** y más amplio que el dominio documentado.

## Decisión 1 — Lista permitida explícita para el argumento

Se define una constante con los seis tipos del dominio:

```php
private const ALLOWED_POST_TYPES = array(
    'post', 'tutorial', 'capitulo', 'aplicacion', 'podcast', 'software',
);
```

`postTypeArgument()` valida el valor **explícito** contra esta constante (`in_array(..., true)`). Un valor fuera → `\WP_Error` → `-32602`.

## Decisión 2 — El valor por defecto NO cambia

El escenario «`post_type` ausente se comporta como hasta ahora» exige que, sin argumento, se consulten **todos los tipos públicos**. Por eso `getLatestPosts()`/`searchPosts()` siguen usando `publicPostTypes()` cuando `$post_type === null`. Solo se restringe el conjunto de valores **explícitamente solicitables**.

### Asimetría aceptada y razonada

Con esta decisión, por defecto podrían aparecer entradas de un tipo público que no está en la lista (p. ej. `page`), pero no se podría **filtrar** a ese tipo. Es coherente con la spec vigente (el escenario del valor ausente manda «todos los tipos públicos») y con el `enum` del cliente (los seis del dominio son los que un agente necesita). Si en el futuro se quisiera que el dominio sea también el conjunto por defecto, sería un change aparte que modifique ese escenario.

## Decisión 3 — Sin cambios en el cliente

El `enum` de `webmcp.js` ya lista los seis tipos; al restringir el servidor, ambos extremos quedan alineados sin tocar JS.

## Alternativa descartada

**Ampliar el `enum` del JS a todos los tipos públicos.** Se descarta porque el JS es estático (no puede conocer el runtime) y porque el dominio del blog son los seis CPT: `page` no es contenido que un agente deba consultar vía estas herramientas.

## Fuera de alcance

- Cambiar el comportamiento por defecto (todos los tipos públicos).
- Añadir o renombrar tipos de contenido.
