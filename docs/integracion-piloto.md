# Integración del piloto

La rama `jorge/integracion-piloto` reúne el backend de `main`, el [importador de Brandon](https://github.com/JorgeTaco/establecimientos_comerciales/pull/3) y el [listado de Michelle](https://github.com/JorgeTaco/establecimientos_comerciales/pull/4). Los cambios en el backend responden a la [solicitud de Michelle](solicitud-backend-jorge.md) y a la revisión del importador.

## Entorno compartido

Usen Laravel y Blade en el mismo origen. Cada integrante debe apuntar su `.env` a su propia base MySQL de desarrollo. Sigan la [instalación del README](../README.md#levantar-la-aplicación) y luego ejecuten en esa base:

```bash
php artisan migrate
php artisan db:seed
php artisan serve
```

`db:seed` carga el catálogo y tres comercios ficticios. Para una base destinada a importar la encuesta, ejecuten solo `php artisan db:seed --class=CategoriaSeeder`. El catálogo incluye Lácteos, Leche, Queso, Yogurt y Mantequilla. Para crear cuentas de prueba, ejecuten `php artisan usuario:crear correo@ejemplo.test` en una terminal interactiva. El rol predeterminado es emprendedor y `--rol` permite elegir comercio o administrador para una cuenta nueva. La contraseña se introduce de forma oculta.

## Michelle: listado, mapa y flujos con sesión

Consulta las [pantallas integradas y sus permisos](frontend.md).

| Pantalla | Contrato del backend |
| --- | --- |
| Tarjetas y mapa | `GET /api/establecimientos/buscar`. Usa la misma respuesta para ambas vistas, con marcadores para registros con las dos coordenadas. El listado conserva comercios sin ubicación. |
| Filtros | `GET /api/categorias` para obtener los IDs activos. Envía a la búsqueda `categoria_id`, `parroquia`, `tipo`, `q` o `recepcionProveedores` según el filtro elegido. Para un radio agrega `latitud`, `longitud` y `distancia_km`. |
| Ficha y contacto | `GET /api/establecimientos/{id}`. Presenta el horario general y el de proveedores por separado. Muestra las acciones de contacto solo para los canales presentes en `contacto`. Usa `como_llegar_url` para abrir Maps. |
| Catálogo y oportunidades | Usa `/api/productos`, `/api/productos/{id}/establecimientos-compatibles` y `/api/oportunidades`. Muestra `motivos` y `pendientes` de cada coincidencia. |
| Envío de propuestas | Usa `POST /api/establecimientos/{id}/propuestas` y conserva la referencia UUID para un reintento. Con cuenta de comercio, usa `/api/propuestas-recibidas` y la ruta de respuesta. |

El listado usa `/establecimientos/buscar` dentro de la API. El [contrato inicial](contrato-establecimientos.md) sigue disponible para consumidores que ya usan `GET /api/establecimientos`. Ejemplo de una consulta para la pantalla:

```bash
curl -fsS 'http://127.0.0.1:8000/api/establecimientos/buscar?parroquia=Lasso'
```

En Blade, usa `csrf_token()`. En JavaScript del mismo origen, conserva la cookie de sesión, pide el token mediante `GET /api/sesion/csrf` y envíalo como `X-CSRF-TOKEN` en escrituras. Usa el token devuelto tras iniciar sesión. Los [contratos del backend](backend.md) detallan entradas, salidas y errores.

Para probar el interés declarado con datos ficticios, configura `PUBLICAR_INTERES_ENCUESTA=true` en `.env`. El listado permite filtrarlo por separado de la recepción. Cuando sea `null`, omite la etiqueta. Habilitarlo con la encuesta real queda a cargo de la entidad responsable.

### Google Maps

1. En el proyecto de Google Cloud de la entidad, habilitar Maps JavaScript API y crear una clave de navegador restringida a esa API y a los sitios autorizados. Incluir `http://127.0.0.1:8000/*`, `http://localhost:8000/*` o el dominio HTTPS que efectivamente se use.
2. Configurar `GOOGLE_MAPS_API_KEY` y `GOOGLE_MAPS_MAP_ID` en `.env`. Para pruebas del marcador con una Maps Demo Key se puede usar `DEMO_MAP_ID`. En el entorno de entrega usar el Map ID de la entidad. Ejecutar `php artisan config:clear` localmente si Laravel conservó una configuración anterior.
3. Abrir `/establecimientos` y comprobar el listado y las coordenadas. Para revisar marcadores con la clave de prueba, pulsar **Mostrar mapa** solo cuando haga falta. La vista carga una instancia por página y la reutiliza al filtrar. Para automatizar pruebas del frontend, simular el proveedor. Compartir la configuración de la clave mediante un canal de acceso de la entidad y mantener el valor fuera de Git.

La ubicación del navegador requiere permiso del usuario. La búsqueda por radio recibe las coordenadas de origen. Si se incorpora geocodificación de una dirección manual, acordar el servicio y su configuración antes de añadir una llamada server-side. [Seguridad de Google Maps Platform](https://developers.google.com/maps/api-security-best-practices) · [Alternativa de prueba con Maps Demo Key](https://developers.google.com/maps/demo-key) · [Marcadores avanzados](https://developers.google.com/maps/documentation/javascript/advanced-markers/start)

## Brandon: validación e importación

1. Ejecuta la migración y el seeder de categorías. Los IDs se resuelven por nombre en cada base. Las categorías derivadas salen de «Productos lácteos que comercializa» y se asocian solo cuando estén activas.
2. Primero simula el importador con el CSV ficticio. Para la encuesta, conserva el Excel y los reportes con contactos en `datos/privados/` o fuera del repositorio.
3. Revisa coordenadas dudosas y coincidencias fuertes antes de aplicar la carga. Dos comercios en la misma dirección pueden guardarse si son distintos. Teléfonos repetidos pasan a revisión para distinguir duplicados de sucursales.
4. Elige una vía de carga por archivo. Usa el importador directo para Excel o prepara lotes de hasta 100 filas para `POST /api/admin/establecimientos/importar` con sesión de administrador y CSRF. Reintenta solo las filas con estado `reintentar`.

```bash
php artisan establecimientos:importar datos/ejemplos/encuesta-ficticia.csv --dry-run --categoria=Lácteos
php artisan establecimientos:preparar-lotes datos/ejemplos/establecimientos-importar.csv
```

La carga asigna `por_confirmar` y guarda los contactos como privados. El interés declarado permanece separado. El horario de la encuesta se guarda como `horario_atencion`. Verifica los campos y opciones en la [guía de datos](../datos/README.md).

## Revisión antes de integrar en main

- Michelle puede comprobar desde un celular el listado, la ficha, los filtros y la configuración del mapa. Los demás formularios pueden integrarse contra las rutas desde el mismo origen.
- Brandon puede repetir la validación del archivo e inspeccionar cuáles se guardan, cuáles requieren revisión y qué categorías aparecen en la ficha.
- Jorge revisa conjuntamente las rutas, migraciones, permisos y pruebas con MySQL antes de incorporar la rama de integración a `main`.
