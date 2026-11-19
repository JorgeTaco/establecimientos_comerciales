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

La primera entrega será un listado móvil conectado a la API de Laravel, con comercios de prueba y su estado de recepción de propuestas. Michelle puede trabajar con el [JSON ficticio](datos/ejemplos/establecimientos.json) mientras Brandon prepara el formato de importación. Laravel sirve el [contrato del listado](docs/contrato-establecimientos.md) desde MySQL.

La [guía de integración del piloto](docs/integracion-piloto.md) reúne las rutas, los datos de prueba y los pasos de Michelle y Brandon para trabajar con el mismo backend local.

La prueba de aceptación del piloto será completar desde el celular este recorrido de emprendedor: registrar un producto, encontrar un comercio compatible, consultar su ficha, contactarlo y guardar el resultado.

## Inicio de sesión y cuenta del docente

El [plan de inicio de sesión](docs/plan-inicio-sesion.md) define qué prepara Jorge en Laravel, qué conecta Michelle en la interfaz y cómo valida Brandon la sesión y la cuenta de evaluación con MySQL. También describe el orden de integración y cómo entregar al docente un acceso ya probado.

## Levantar el proyecto en local

Se necesita PHP 8.3 o superior con las extensiones de Laravel y `pdo_mysql`, Composer y MySQL. La base actual usa Blade y sirve los archivos estáticos desde `public/`. [Requisitos de Laravel 13](https://laravel.com/framework/docs/deployment)

1. Desde la raíz del repositorio, instala las dependencias y prepara la configuración local.

   ```bash
   composer install
   cp .env.example .env
   php artisan key:generate
   ```

2. Crea una base MySQL llamada `establecimientos_comerciales` y actualiza en `.env` los valores `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` y `DB_PASSWORD` según tu instalación. Usa un usuario con permisos sobre esa base. `DB_CONNECTION` ya está configurado como `mysql`.
3. Ejecuta las migraciones iniciales y levanta el servidor.

   ```bash
   php artisan migrate
   php artisan storage:link
   php artisan serve
   ```

   Abre `http://127.0.0.1:8000/establecimientos`. Ejecuta `php artisan db:seed` en tu base de desarrollo para ver los comercios ficticios o `php artisan db:seed --class=CategoriaSeeder` para instalar solo el catálogo.

4. Para ejecutar `php artisan test`, crea también la base MySQL separada `establecimientos_comerciales_test` y otorga al usuario de pruebas permisos para ejecutar migraciones sobre ella. Puedes guardar credenciales específicas de pruebas en `.env.testing`.

### Google Maps

Al desarrollar el mapa, habilita **Maps JavaScript API** en el proyecto de Google Cloud y coloca en el `.env` local la clave `GOOGLE_MAPS_API_KEY` y el identificador `GOOGLE_MAPS_MAP_ID` para los marcadores avanzados. Restringe la clave a Maps JavaScript API y a los sitios web autorizados, incluido el entorno local que utilice el equipo. La configuración está disponible para Blade mediante `config('services.google_maps')`. [Configuración de Maps JavaScript API](https://developers.google.com/maps/documentation/javascript/get-api-key) · [Marcadores avanzados](https://developers.google.com/maps/documentation/javascript/advanced-markers/start)

El backend incluye sesión y roles, fichas comerciales, catálogo, compatibilidad, oportunidades, propuestas y administración. El Excel original, las credenciales y los datos reales se guardan fuera de Git.
