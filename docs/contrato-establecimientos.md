# Contrato inicial del listado de establecimientos

`GET /api/establecimientos` devuelve `200 OK` con JSON y una propiedad `establecimientos` que contiene una lista ordenada por nombre. Cuando la lista esté vacía, devuelve `{"establecimientos":[]}`. El [archivo de ejemplo](../datos/ejemplos/establecimientos.json) tiene la misma estructura.

| Campo | Formato | Uso |
| --- | --- | --- |
| `id` | Cadena estable | Identifica el comercio en la lista y al abrir su ficha |
| `nombre` | Cadena | Nombre para mostrar |
| `tipo` | Cadena | Los tipos normalizados de la encuesta son `tienda`, `minimercado`, `supermercado`, `distribuidor` y `panaderia`. `Minimarket` se importa como `minimercado`. La API admite otros tipos. |
| `parroquia` | Cadena | Parroquia del establecimiento |
| `latitud`, `longitud` | Números o ambos `null` | Coordenadas decimales. Sin coordenadas, la ficha aparece en el listado y queda pendiente de ubicar en el mapa |
| `recepcionProveedores` | `recibe_propuestas`, `no_recibe_por_ahora` o `por_confirmar` | Estado actual de recepción de propuestas |

El listado se limita a los campos de la tabla. Usa [la búsqueda ampliada y la ficha](backend.md#directorio-y-ficha-comercial) para consultar dirección, sector, foto y otros datos del perfil. La recepción de un comercio importado empieza en `por_confirmar`.
