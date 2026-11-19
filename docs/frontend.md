# Interfaz web

Blade sirve las pantallas y el JavaScript consume la [API de Laravel](backend.md) desde el mismo origen. Las operaciones privadas requieren una cuenta activa, el rol correspondiente y permiso sobre el registro.

| Página | Acceso | Función |
| --- | --- | --- |
| `/evaluacion` | Público | Entrada para el docente y recorrido de prueba. |
| `/`, `/establecimientos` | Público | Directorio, filtros, búsqueda por distancia y mapa bajo demanda. |
| `/establecimientos/{id}` | Público | Ficha completa y contacto autorizado. Una sesión habilita reportar. Un emprendedor puede guardar la oportunidad y enviar una propuesta. |
| `/iniciar-sesion`, `/registro` | Invitado | Entrada con una cuenta existente y registro de emprendedores o comercios. |
| `/productos` | Emprendedor | Alta, edición, archivo, PDF, comercios compatibles y enlace revocable del catálogo. |
| `/catalogos/{token}` | Público con enlace | Catálogo compartido con productos activos. |
| `/oportunidades` | Emprendedor | Seguimiento, notas privadas, próxima gestión, condiciones y propuestas enviadas. |
| `/comercio/establecimientos`, `/propuestas-recibidas` | Comercio | Fichas propias y respuesta a propuestas de los comercios a cargo. |
| `/admin/indicadores` | Administrador | Indicadores, categorías, usuarios, reportes e importación. |

Cuando el directorio todavía está vacío o la API falla, la interfaz señala que está usando el JSON ficticio. Una búsqueda sin coincidencias en la API muestra un resultado vacío. Las fichas ficticias sirven para explorar el directorio. Para guardar oportunidades usa los comercios de la base que carga `php artisan db:seed`.

La pestaña **Mapa** lista los comercios con coordenadas. **Mostrar mapa** solicita Google Maps una vez por página. El listado y las coordenadas quedan disponibles con la clave vacía. Para buscar por radio desde HTTP en la red local, introduce las coordenadas de origen. **Usar mi ubicación** requiere permiso del navegador y un origen seguro.

Para preparar un catálogo ficticio opcional, crea `emprendedor@ejemplo.test` mediante `php artisan usuario:crear` y ejecuta `php artisan db:seed --class=UsuarioProductoSeeder` después de cargar los comercios de ejemplo.

La [guía de integración](integracion-piloto.md) reúne las rutas de la API, la validación del Excel y el reparto de trabajo.
