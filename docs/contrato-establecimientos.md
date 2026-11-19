# Contrato inicial del listado de establecimientos

Propuesta para la primera entrega. `GET /api/establecimientos` devuelve `200 OK` con JSON y una propiedad `establecimientos` que contiene una lista. Cuando la lista esté vacía, devuelve `{"establecimientos":[]}`. El [archivo de ejemplo](../datos/ejemplos/establecimientos.json) tiene la misma estructura.

| Campo | Formato | Uso |
| --- | --- | --- |
| `id` | Cadena estable | Identifica el comercio en la lista y al abrir su ficha |
| `nombre` | Cadena | Nombre para mostrar |
| `tipo` | Cadena | Tipo de comercio normalizado. El ejemplo incluye tienda, minimercado y supermercado |
| `parroquia` | Cadena | Parroquia del establecimiento |
| `latitud`, `longitud` | Números o ambos `null` | Coordenadas decimales. Sin coordenadas, la ficha aparece en el listado y queda pendiente de ubicar en el mapa |
| `recepcionProveedores` | `recibe_propuestas`, `no_recibe_por_ahora` o `por_confirmar` | Estado actual de recepción de propuestas |

El interés declarado en la encuesta se conserva como dato de origen. La ficha empieza con `por_confirmar` hasta verificar la recepción de propuestas. El listado inicial se limita a los campos de la tabla y los contactos autorizados se incorporarán a la ficha en una entrega posterior.
