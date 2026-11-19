# Contratos del backend

Las rutas usan el prefijo `/api`. Las propiedades documentadas mantienen su nombre, tipo, significado y valores publicados. Un cambio compatible se añade en un campo opcional o en una ruta nueva. Las migraciones que ya se aplicaron se amplían con otra migración. Un cambio incompatible requiere una ruta versionada y coordinación previa entre Jorge, Michelle y Brandon.

El [listado inicial](contrato-establecimientos.md) mantiene su formato exacto. Los identificadores de establecimientos salen como cadenas. Los de usuarios, categorías, productos, oportunidades y propuestas salen como números y las rutas los reciben en decimal. Los precios del catálogo salen como cadenas decimales de dos posiciones en USD. Las fechas de gestión usan `AAAA-MM-DD`. Los demás tiempos salen en ISO 8601. Los atributos opcionales admiten `null` cuando el campo lo permite. En un `PATCH` la omisión conserva el valor actual.

## Sesión para Blade o JavaScript del mismo origen

Para peticiones autenticadas, conserva la cookie de sesión. Envía `Accept: application/json` y, en cada escritura, `X-CSRF-TOKEN`. Obtén el token con `GET /api/sesion/csrf` o con `csrf_token()` en Blade. Usa el token nuevo que devuelve el registro o inicio de sesión. Las rutas de escritura con JSON usan `Content-Type: application/json`.

| Método y ruta bajo `/api` | Entrada | Salida |
| --- | --- | --- |
| GET `/sesion/contexto` | Cookie del sitio, si existe | `usuario` o `null`, `csrf_token`, `roles_registro` y `destino` |
| GET `/sesion/csrf` | — | `csrf_token` |
| POST `/registro` | `name`, `email`, `password`, `password_confirmation`, `rol` | 201 con `usuario` y `csrf_token` |
| POST `/sesion` | `email`, `password` | `usuario` y `csrf_token` |
| GET `/sesion` | Sesión activa | `usuario` y `csrf_token` |
| DELETE `/sesion` | Sesión iniciada | 204 |

`usuario` contiene `id`, `name`, `email` y `rol`. El registro público permite `emprendedor` y `comercio`. Una contraseña nueva requiere al menos 12 caracteres y hasta 72 bytes. En una terminal, `php artisan usuario:crear correo@ejemplo.test --rol=administrador` crea una cuenta nueva con una contraseña ingresada de forma oculta. Para asignar ese rol a una cuenta activa ya existente, usa `php artisan usuario:administrador correo@ejemplo.test`. Un administrador puede suspender otras cuentas y cambiar sus roles entre comercio y emprendedor.

Códigos habituales: 401 sin sesión, 403 por rol o suspensión, 404 cuando no existe o la cuenta no es propietaria, 409 por conflicto, 419 por CSRF vencido, 422 con `message` y `errors` para validación y 429 al superar el límite.

### Conectar la pantalla de acceso

Consulta `GET /api/sesion/contexto` al abrir la interfaz. Una sola respuesta permite saber si hay sesión, obtener el token y rellenar las opciones de registro. Un invitado recibe esta estructura:

```json
{
  "usuario": null,
  "csrf_token": "<token-de-la-sesion>",
  "roles_registro": ["emprendedor", "comercio"],
  "destino": "/iniciar-sesion"
}
```

Con la cuenta activa, `usuario` contiene `id`, `name`, `email` y `rol`, y `destino` es `/establecimientos`. Una cuenta suspendida recibe `403`. Conserva la cookie del mismo origen en cada solicitud. Para salir de una cuenta suspendida, obtén un token desde `/api/sesion/csrf` y usa la ruta de salida.

1. **Entrar.** Envía `email` y `password` a `POST /api/sesion` con JSON y `X-CSRF-TOKEN`. Tras un `200`, guarda el `csrf_token` nuevo en memoria y abre `/establecimientos`. Muestra el mensaje de `errors.email` si las credenciales fallan.
2. **Registrar.** Envía los campos de la tabla a `POST /api/registro`. Usa `roles_registro` para el selector y el token nuevo después de un `201`.
3. **Salir.** Envía `DELETE /api/sesion` con el token vigente. Después del `204`, consulta el contexto de nuevo para obtener el estado de invitado y su token.

Para probar la entrada desde un navegador ya existe una vista base en `GET /iniciar-sesion`. Blade puede usar el formulario `POST /iniciar-sesion` con `@csrf` y el formulario de salida `POST /cerrar-sesion` con el token. La entrada redirige a `/establecimientos` y la salida regresa a la pantalla de entrada. Web y API comparten el límite de 10 intentos de entrada por minuto por IP.

## Directorio y ficha comercial

| Método y ruta bajo `/api` | Acceso | Salida |
| --- | --- | --- |
| GET `/establecimientos` | Público | `establecimientos` con el contrato original |
| GET `/establecimientos/buscar` | Público | `establecimientos` con los campos del listado y `distanciaKm`, `direccion`, `sector` y `foto_url`, que pueden ser `null` |
| GET `/establecimientos/{id}` | Público | `establecimiento` con la ficha |
| GET `/categorias` | Público | `categorias` activas con `id` y `nombre` |
| GET `/mis-establecimientos` | Comercio | `establecimientos` propios con fichas |
| GET `/mis-establecimientos/{id}` | Responsable o administrador | Ficha en `establecimiento` y datos privados en `edicion` |
| POST `/establecimientos` | Comercio o administrador | 201 con `establecimiento` |
| PATCH `/establecimientos/{id}` | Responsable o administrador | `establecimiento` |
| POST `/establecimientos/{id}/reportes` | Cuenta activa | 201 con `reporte`. Envía `descripcion` |
| POST `/fotos` | Cuenta activa | 201 con `foto_url`. Envía `multipart/form-data` con `foto` JPG, PNG o WebP de máximo 4 MB |

La búsqueda acepta `q` para nombre, dirección, parroquia, sector o cantón, además de los filtros `canton`, `parroquia`, `sector`, `tipo`, `categoria_id` y `recepcionProveedores`. Usa `parroquia` para una coincidencia exacta en ese campo. Para distancia usa `latitud`, `longitud` y opcionalmente `distancia_km` (radio máximo 500 km). Con origen, los resultados se ordenan por distancia. `distanciaKm` es una distancia en línea recta o `null` si falta ubicación. La interfaz puede obtener el origen por GPS con autorización o a partir de la dirección ingresada por el usuario. La ficha devuelve `como_llegar_url` para abrir Google Maps.

Con `PUBLICAR_INTERES_ENCUESTA=true`, la búsqueda ampliada y la ficha incluyen `interesDeclarado` con valor `true`, `false` o `null`. La búsqueda admite `interes_declarado=1` o `0`. Las respuestas desconocidas quedan fuera de ambos filtros. El valor predeterminado mantiene las respuestas privadas. Habilítalo para datos ficticios en desarrollo o cuando esté autorizada la publicación de la encuesta real. `recepcionProveedores` conserva el estado de recepción comercial.

Al registrar, envía `nombre`, `tipo` y `parroquia`. Los demás campos son opcionales. Ejemplo de alta o de una fila normalizada por Brandon.

```json
{
  "nombre": "Tienda Demo Centro",
  "tipo": "tienda",
  "parroquia": "Mulaló",
  "latitud": -0.775,
  "longitud": -78.58,
  "recepcion_proveedores": "por_confirmar",
  "categorias": [1],
  "perfil": {
    "direccion": "Avenida ficticia",
    "canton": "Latacunga",
    "sector": "Centro",
    "productos_buscados": ["Yogur"],
    "tiene_refrigeracion": true,
    "horario_proveedores": "Lunes de 09:00 a 11:00",
    "modalidad_compra": "contado",
    "pedido_inicial": 12,
    "decision_compra": "sucursal"
  }
}
```

La API admite ambos puntos como números en rango o ambos como `null`. `categorias` es una lista de IDs activos; enviarla vacía elimina las asociaciones. El objeto `perfil` también admite `foto_url`, `requisitos`, `devoluciones`, `contacto_nombre`, `contacto_telefono`, `contacto_email`, `canales_contacto` y `autorizar_contacto`. Los canales son `telefono`, `whatsapp` y `email`. En una ficha pública, `contacto` es `null` hasta otorgar autorización explícita y devuelve `nombre`, `telefono`, `email` y `canales` solo según esa autorización. Al cambiar los datos de contacto, se exige volver a autorizar. Los teléfonos se guardan sin espacios, guiones ni paréntesis. Los valores de `modalidad_compra` son `contado`, `credito`, `consignacion`. Los de `decision_compra` son `sucursal` y `oficina_central`.

La ficha amplía los campos del listado con `foto_url`, `direccion`, `canton`, `sector`, `categorias` (objetos de `id` y `nombre`), `productos_buscados`, `tiene_refrigeracion`, `horario_proveedores`, `requisitos`, `modalidad_compra`, `pedido_inicial`, `devoluciones`, `decision_compra`, `verificado_en`, `actualizado_en`, `contacto` y `como_llegar_url`. Una edición invalida la verificación hasta que un administrador la confirme de nuevo.

El campo `perfil.horario_atencion` acepta el horario general informado por el establecimiento y la ficha lo devuelve como `horario_atencion`. Usa `perfil.horario_proveedores` para el horario comercial específico y `productos_buscados` para necesidades declaradas por el comercio.

## Productos, catálogo y coincidencias

Estas rutas requieren una cuenta de emprendedor, salvo el catálogo público.

| Método y ruta bajo `/api` | Entrada | Salida |
| --- | --- | --- |
| GET `/productos` | — | `productos` propios, incluidos archivados |
| POST `/productos` | Datos del producto | 201 con `producto` |
| GET `/productos/{id}` | — | `producto` propio |
| PATCH `/productos/{id}` | Campos a editar | `producto` |
| DELETE `/productos/{id}` | — | 204. Registra `archivado_en` |
| GET `/productos/{id}/ficha.pdf` | — | PDF descargable |
| GET `/productos/{id}/establecimientos-compatibles` | — | `establecimientos` con `motivos`, `pendientes` y un `aviso` general |
| POST `/catalogo/compartir` | — | `url` JSON y `url_web` para la vista pública. Ambos enlaces son revocables. |
| DELETE `/catalogo/compartir` | — | 204 |
| GET `/catalogos/{token}` | Público con enlace | `catalogo.nombre` y `catalogo.productos` activos |

El producto requiere `nombre` y `categoria_id`. Los demás datos son `descripcion`, `foto_url`, `presentacion`, `precio_comercio`, `precio_sugerido`, `pedido_minimo`, `capacidad_semanal`, `vida_util_dias`, `requiere_refrigeracion` (false por defecto), `conservacion`, `zonas_entrega` (lista de nombres), `frecuencia_entrega` y `documentacion` (lista de descripciones). El pedido y el abastecimiento se expresan en unidades de la presentación comercial. El catálogo público devuelve JSON y deja fuera la documentación, las cuentas y los productos archivados. La ficha PDF se genera sin solicitar imágenes externas.

El filtro de compatibilidad usa categoría, cobertura por cantón/parroquia/sector, recepción, refrigeración y pedido inicial frente al mínimo y la capacidad semanal. Una condición desconocida se señala en `pendientes`. Las coincidencias son oportunidades a evaluar y no garantizan aceptación.

## Oportunidades y propuestas

| Método y ruta bajo `/api` | Acceso | Entrada y salida |
| --- | --- | --- |
| GET `/oportunidades` | Emprendedor | `oportunidades` propias, con un resumen de cada establecimiento |
| GET `/oportunidades/{id}` | Emprendedor propietario | `oportunidad` con establecimiento, seguimiento y propuestas enviadas |
| POST `/oportunidades` | Emprendedor | `establecimiento_id`; devuelve `oportunidad` con 201 o 200 si ya estaba guardada |
| PATCH `/oportunidades/{id}` | Emprendedor propietario | `estado`, `notas`, `proxima_gestion`, `condiciones_acordadas`; devuelve `oportunidad` |
| GET `/oportunidades/{id}/propuestas` | Emprendedor propietario | `propuestas` enviadas |
| POST `/establecimientos/{id}/propuestas` | Emprendedor | `productos` (IDs), `mensaje`, `referencia` (UUID opcional para reintentar); devuelve `propuesta` |
| GET `/propuestas-recibidas` | Comercio | `propuestas` de establecimientos a su cargo |
| GET `/propuestas-recibidas/{id}` | Comercio responsable | `propuesta` con los productos enviados y la respuesta, si existe |
| PATCH `/propuestas-recibidas/{id}/respuesta` | Comercio responsable | `respuesta`, `mensaje_respuesta` opcional; devuelve `propuesta` |

Estados del seguimiento en orden comercial: `por_contactar`, `contactado`, `muestra_entregada`, `en_negociacion`, `primer_pedido`, `punto_venta_activo` y `no_concretada`. El emprendedor registra su estado real incluso si el comercio carece de cuenta. El primer pedido registra `primer_pedido_en` para indicadores. Las respuestas son `me_interesa`, `solicito_informacion`, `agendar_visita` y `no_interesado_por_ahora`. La propuesta conserva una copia de los datos comerciales de los productos al enviarla. El comercio recibe la propuesta y el identificador del establecimiento. Las notas y condiciones del seguimiento son privadas.

## Administración e integración de Excel

| Método y ruta bajo `/api/admin` | Entrada y salida |
| --- | --- |
| GET `/categorias` | `categorias` activas e inactivas |
| POST `/categorias`, PATCH `/categorias/{id}` | `nombre`, `activa`; devuelve `categoria` |
| GET `/usuarios`, GET `/reportes` | `usuarios` o `reportes` paginados, con `data` dentro de esa propiedad |
| PATCH `/usuarios/{id}` | `rol` y/o `suspendido`; devuelve `usuario` |
| PATCH `/reportes/{id}` | `estado` (`resuelto` o `descartado`) y `resolucion`; devuelve `reporte` |
| GET `/indicadores` | `indicadores.emprendedores_con_primer_pedido` y `indicadores.puntos_venta_activos` |
| POST `/establecimientos/{id}/verificar` | Actualiza la fecha de verificación; devuelve `establecimiento` |
| PATCH `/establecimientos/{id}/responsable` | `responsable_id` de un comercio activo o `null`; devuelve `responsable_id` |
| POST `/establecimientos/duplicados` | Recibe una ficha normalizada y devuelve IDs en `candidatos` |
| POST `/establecimientos/importar` | `filas` normalizadas; devuelve `resultados` |
| POST `/establecimientos/importar-archivo` | `multipart/form-data` con `archivo` XLSX o CSV y `simular` (`1` para simular o `0` para guardar). Devuelve el resumen y el número y estado de cada fila. |

El panel acepta archivos de hasta 1 MiB, 4096 filas y 64 columnas. El modo de simulación valida las filas sin guardarlas. El reporte web omite datos de contacto, y el archivo temporal se elimina al finalizar.

Brandon puede convertir el Excel en lotes de hasta 100 fichas con el JSON de ejemplo de esta guía. Las categorías deben existir antes de asociarlas. Cada fila admite `interes_encuesta` como booleano o nulo. Cada resultado incluye `fila` (índice desde cero) y `estado`. Las filas `creado` incluyen `establecimiento_id`, las `posible_duplicado` incluyen `candidatos` y las `invalida` incluyen `errores`. Las filas `reintentar` se pueden reenviar cuando termine otra carga.

La consulta administrativa de candidatos muestra coincidencias por nombre, dirección o teléfono. La importación detiene filas con teléfono compartido o nombre y dirección coincidentes. En fichas sin dirección, usa nombre, tipo, parroquia y el teléfono disponible para detectar reintentos. La carga conserva los contactos como privados y asigna `por_confirmar` a la recepción. El Excel real se mantiene fuera de Git.

Para probar las fotos en local, ejecuta `php artisan storage:link` y configura `APP_URL` con el origen de la app. El mapa requiere una clave válida de Google Maps en el entorno de la interfaz. [Autenticación Laravel](https://laravel.com/framework/docs/13.x/authentication) · [Almacenamiento Laravel](https://laravel.com/framework/docs/13.x/filesystem) · [Enlaces de Google Maps](https://developers.google.com/maps/documentation/urls/get-started)
