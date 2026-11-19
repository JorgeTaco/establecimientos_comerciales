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
| `/admin/encuestas` | Administrador | Filas originales de las cargas archivadas, estado de importación y acceso a las fichas guardadas. |

La barra principal muestra el directorio, la evaluación y las secciones disponibles según el rol. En las demás pantallas, **Volver** lleva a la sección anterior de la ruta, incluso al abrir un enlace directo. Al regresar desde una ficha, la búsqueda y los filtros públicos se conservan si la ficha se abrió desde el directorio.

El directorio distingue una base sin comercios, una búsqueda sin coincidencias y un fallo de carga. **Reintentar** consulta la API con los filtros actuales. La vista previa de GitHub Pages utiliza sus propios datos ficticios. Para probar el flujo completo con MySQL, carga los comercios de ejemplo con `php artisan db:seed`.

Los filtros muestran cuántos resultados quedarán con cada opción. Se actualizan según el texto y los demás filtros activos. Una selección que llegue a cero sigue disponible para quitarla. Cuando no hay coincidencias, **Quitar filtros** limpia las selecciones y conserva el texto de búsqueda.

La búsqueda, los filtros generales, el orden y la vista seleccionada se conservan en la URL. La ubicación de origen de una búsqueda por radio permanece en el historial de la pestaña. En la red local por HTTP se admiten coordenadas manuales. **Usar mi ubicación** requiere permiso y un origen seguro.

**Mostrar más comercios** carga la siguiente página cuando el orden es alfabético y la búsqueda no tiene un origen geográfico. Los conteos abarcan la búsqueda completa. El mapa solicita sus puntos por separado.

Desde **Mapa** puedes abrir una ficha o consultar **Cómo llegar** para cada comercio con coordenadas. **Mostrar mapa** carga Google Maps bajo demanda. También puedes cambiar entre **Lista** y **Mapa** con las flechas del teclado.

Para preparar un catálogo ficticio opcional, crea `emprendedor@ejemplo.test` mediante `php artisan usuario:crear` y ejecuta `php artisan db:seed --class=UsuarioProductoSeeder` después de cargar los comercios de ejemplo.

La [guía de integración](integracion-piloto.md) reúne las rutas de la API, la validación del Excel y el reparto de trabajo.
