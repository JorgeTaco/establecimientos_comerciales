# Establecimientos comerciales

Aplicación web para que emprendedores encuentren comercios donde ofrecer sus productos y den seguimiento a sus oportunidades, desde el contacto inicial hasta el primer pedido. El piloto se centra en lácteos en Latacunga, Cotopaxi.

El proyecto usa **Laravel 13** con **Blade** para la interfaz, **MySQL** para los datos y **Google Maps JavaScript API** para el mapa.

## Reparto de trabajo

| Persona | Rutas a cargo | Responsabilidad | Primer avance |
| --- | --- | --- | --- |
| **Jorge** | `app/Http/`, `app/Models/`, `app/Policies/`, `routes/`, `database/migrations/`, `config/` | API, esquema MySQL, roles y permisos, lógica de catálogo, compatibilidad, propuestas y oportunidades, integración | Desarrollar el listado de establecimientos en la API y definir su migración |
| **Michelle** | `resources/views/`, `public/css/`, `public/js/` | Interfaz móvil, mapa con Google Maps, filtros, fichas, catálogo, propuestas, seguimiento y panel de administración | Mostrar el listado y sus estados con los datos de ejemplo. Añadir al mapa los comercios con coordenadas |
| **Brandon** | [`datos/`](datos/), `app/Importacion/`, `app/Console/Commands/`, `database/factories/`, `database/seeders/`, `tests/` | Importación y validación del Excel, datos ficticios y pruebas del recorrido completo | Mapear las columnas y definir cómo revisar coordenadas y marcar posibles duplicados |

La [guía de colaboración](CONTRIBUTING.md) indica qué rama abrir, quién revisa cada avance y cómo coordinar cambios compartidos. Los [contratos del backend](docs/backend.md) documentan las rutas para la interfaz y el formato que usará el importador de Excel. Los contratos y las migraciones se amplían de forma aditiva.

## Qué se puede probar

- **Directorio público** con búsqueda por nombre, dirección, tipo, parroquia, categoría, estado de recepción y radio. La ficha muestra condiciones y contacto autorizado. **Mostrar mapa** carga Google Maps cuando se solicita.
- **Emprendedor** puede crear y archivar productos, buscar comercios compatibles, compartir un catálogo revocable, enviar propuestas y actualizar sus oportunidades.
- **Comercio** puede registrar o actualizar su ficha y responder las propuestas que recibe.
- **Administrador** puede revisar indicadores, gestionar categorías y usuarios, resolver reportes y simular o importar el Excel de la encuesta.

La [vista previa pública](https://jorgetaco.github.io/establecimientos_comerciales/) permite buscar y filtrar fichas ficticias en GitHub Pages. Para probar inicio de sesión y cambios con MySQL, abre **`/evaluacion`** en la instalación del equipo o en una instalación local.

## Levantar la aplicación

Instala PHP **8.3 o superior**, Composer y MySQL. PHP necesita las [extensiones de Laravel](https://laravel.com/docs/13.x/deployment#server-requirements), además de `pdo_mysql` y `zip`. La aplicación usa Blade y sirve los estilos y el JavaScript desde `public/`.

1. Clona el proyecto e inicializa su configuración en la primera instalación.

   ```bash
   git clone https://github.com/JorgeTaco/establecimientos_comerciales.git
   cd establecimientos_comerciales
   composer install
   cp .env.example .env
   php artisan key:generate
   ```

2. Crea la base MySQL `establecimientos_comerciales` con codificación `utf8mb4` y un usuario con permisos sobre esa base. Ajusta `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` y `DB_PASSWORD` en `.env`.
3. Migra, carga los comercios ficticios y enlaza las fotos. Para una instalación destinada a la encuesta real, ejecuta `php artisan db:seed --class=CategoriaSeeder` en lugar de `php artisan db:seed`.

   ```bash
   php artisan migrate
   php artisan db:seed
   php artisan storage:link
   ```

4. Crea la cuenta del docente. El comando pide y confirma una contraseña de al menos 12 caracteres mediante una entrada oculta. Cambia el correo de ejemplo si la entrega usa otro.

   ```bash
   php artisan usuario:crear docente@ejemplo.test --nombre="Docente de evaluación" --rol=emprendedor
   php artisan serve
   ```

5. Abre [la entrada de evaluación local](http://127.0.0.1:8000/evaluacion) y comprueba el acceso en una ventana privada. En el celular conectado a la misma red, ejecuta `php artisan serve --host=0.0.0.0 --port=8000`, ajusta `APP_URL=http://IP-DEL-EQUIPO:8000` y abre `http://IP-DEL-EQUIPO:8000/evaluacion`. Si el celular no conecta, verifica la IP vigente, el puerto 8000 en el cortafuegos y que el Wi-Fi permita comunicación entre dispositivos.

Abre [el directorio local](http://127.0.0.1:8000/establecimientos) para buscar fichas, ver cuántos comercios coinciden con cada filtro y cambiar entre lista y mapa. La ruta `/api/establecimientos/buscar` entrega el JSON que consume la interfaz.

### Entrega al docente

Para acceso desde fuera de la red local, instala Laravel en un servidor con PHP y MySQL. Configura HTTPS y dirige la raíz del sitio a `public/`. Ajusta `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` con el dominio final, `SESSION_DRIVER=database` y `SESSION_SECURE_COOKIE=true`. Conserva la misma `APP_KEY` al actualizar y usa `php artisan migrate --force` en los despliegues.

Entrega al docente la URL HTTPS que termina en `/evaluacion` y las credenciales por un canal privado. El [plan de acceso](docs/plan-inicio-sesion.md) incluye el recorrido de aceptación. Para preparar cuentas de comercio o administración, usa `usuario:crear` con `--rol=comercio` o `--rol=administrador`.

### Verificar cambios

Crea una base MySQL separada llamada `establecimientos_comerciales_test` con permisos para migrar y ejecutar las pruebas. Puedes usar `.env.testing` para su configuración.

```bash
php artisan test
```

Consulta las [pantallas y sus rutas](docs/frontend.md), el [contrato de la API](docs/backend.md) y la [guía de importación](datos/README.md).

### Google Maps

Al desarrollar el mapa, habilita **Maps JavaScript API** en el proyecto de Google Cloud y coloca en el `.env` local la clave `GOOGLE_MAPS_API_KEY` y el identificador `GOOGLE_MAPS_MAP_ID` para los marcadores avanzados. Restringe la clave a Maps JavaScript API y a los sitios web autorizados, incluido el entorno local que utilice el equipo. La configuración está disponible para Blade mediante `config('services.google_maps')`. [Configuración de Maps JavaScript API](https://developers.google.com/maps/documentation/javascript/get-api-key) · [Marcadores avanzados](https://developers.google.com/maps/documentation/javascript/advanced-markers/start)

El listado presenta las coordenadas y carga Google Maps al pulsar **Mostrar mapa**. Los filtros reutilizan el mapa de la página. Para probar el listado y la búsqueda, basta con consultar la API de Laravel.

El Excel original, las credenciales y los datos reales se guardan fuera de Git.
