# Plan de inicio de sesión y cuenta de evaluación

El docente debe poder abrir el enlace de entrega desde el celular, iniciar sesión con el correo y la contraseña que le demos y empezar el recorrido del emprendedor. El equipo probará esas mismas credenciales en la instalación que se entrega.

## Punto de partida

Laravel ya registra cuentas de emprendedor o comercio, inicia y cierra sesión, devuelve el usuario actual y aplica permisos. La tabla `users` contiene correo único, contraseña con hash, rol y estado de suspensión. La tabla `sessions` permite persistir la sesión en MySQL. El trabajo inicial consiste en conectar las pantallas móviles a esa base y preparar una cuenta reproducible para la evaluación. [Contrato de sesión](backend.md#sesión-para-blade-o-javascript-del-mismo-origen)

La ruta `/iniciar-sesion` incluye un formulario Blade base para probar el flujo. Michelle puede adaptar esa vista y consultar `GET /api/sesion/contexto` para obtener el usuario, el token, los roles de registro y el destino en una sola respuesta. [Cómo conectar la pantalla](backend.md#conectar-la-pantalla-de-acceso)

En una base temporal de MySQL 8.4 se probó el registro, la entrada con credenciales válidas e inválidas, la salida, la rotación de CSRF y el acceso restringido por rol mediante peticiones HTTP con cookies. La prueba usó una cuenta desechable.

## Responsables y orden

| Orden | Responsable | Entregable |
| --- | --- | --- |
| 1 | **Jorge** con Michelle y Brandon | Confirmar el contrato actual de sesión y de errores, el destino inicial tras entrar y las áreas de cada rol. Preparar las instrucciones para levantar una base local con migraciones y datos ficticios. |
| 2 | **Jorge y Michelle** | Añadir la ruta web de entrada y la pantalla en un cambio coordinado. Jorge prepara las rutas y la redirección de invitados. Michelle conecta el formulario, el estado de sesión y la salida. |
| 3 | **Brandon** con Jorge | Preparar el mecanismo de cuenta de evaluación y las pruebas con MySQL. Esto puede avanzar en paralelo con la pantalla porque usa el contrato de sesión existente. Jorge revisa el comando y cualquier cambio de esquema que se justifique. |
| 4 | **Jorge** con Michelle y Brandon | Integrar sobre el `main` actualizado. Probar pantallas, permisos y persistencia juntos. Michelle revisa la experiencia móvil y Brandon ejecuta la prueba de aceptación. |
| 5 | **Jorge** | Crear la cuenta del docente en la instalación final, comprobarla en un navegador limpio y entregar los datos por el canal privado acordado. |

Cada cambio sale del último `main`. Se pueden usar `jorge/acceso-web` para el cambio coordinado y `brandon/pruebas-acceso` para aprovisionamiento y pruebas. Michelle puede desarrollar su vista en `michelle/pantallas-acceso` y entregarla al PR coordinado con las rutas. El PR de acceso debe abrir la página por sí mismo y el de aprovisionamiento debe funcionar con el contrato existente. Jorge integra cuando las comprobaciones aplicables estén aprobadas. [Acuerdos de colaboración](../CONTRIBUTING.md)

## Frontend de Michelle

1. Crear la vista Blade móvil de **Iniciar sesión** y enlazarla desde el listado. Usar campos etiquetados de correo y contraseña, permitir autocompletado de credenciales y mostrar el estado del envío.
2. Conservar la cookie del mismo sitio. Obtener el token con `GET /api/sesion/contexto` o desde Blade y enviar `POST /api/sesion` con el encabezado `X-CSRF-TOKEN`. Reemplazarlo por el token de la respuesta exitosa.
3. Tras iniciar sesión, enviar al usuario a `/establecimientos`. Añadir enlaces a las áreas de emprendedor, comercio y administración a medida que sus pantallas estén disponibles. Al recargar, consultar `GET /api/sesion/contexto` para recuperar el usuario y su rol.
4. Implementar **Cerrar sesión** con `DELETE /api/sesion`. Después de recibir `204`, limpiar el estado de usuario de la interfaz y volver a la pantalla de entrada. Si hay otra escritura posterior, obtener un token nuevo.
5. Conectar el registro de emprendedor y comercio después de verificar entrada y salida. Mostrar errores junto a sus campos y explicar cómo volver a entrar si la sesión vence.

| Respuesta | Qué hace la interfaz |
| --- | --- |
| `401` | Mostrar la entrada cuando haga falta autenticar al usuario. |
| `403` | Indicar que el rol o el estado de la cuenta impide la acción. |
| `409` | Consultar la sesión actual si ya estaba iniciada. |
| `419` | Solicitar un nuevo token y permitir que el usuario vuelva a enviar. |
| `422` | Mostrar los errores de validación o el mensaje genérico de credenciales inválidas. |
| `429` | Indicar que espere antes de intentar de nuevo. |

## Backend y MySQL de Jorge

- Definir `/iniciar-sesion` en `routes/web.php` y orientar allí a invitados cuando una página privada requiera sesión. Mantener las respuestas JSON de las rutas `/api`.
- Reutilizar `SesionController`, el token CSRF y `CuentaActiva`. Laravel debe conservar la regeneración de sesión al entrar y su invalidación al salir. Las comprobaciones de rol y propiedad se ejecutan en el servidor.
- Revisar con Brandon las migraciones de `users` y `sessions` en una base MySQL limpia y en una base con datos de prueba previos. El esquema actual soporta este flujo. Si un requisito confirmado exige un campo adicional, usar una migración nueva y probarla con registros existentes.
- Configurar el entorno de entrega con HTTPS, una `APP_KEY` persistente y cookie segura. Comprobar la persistencia de sesión con `SESSION_DRIVER=database` y ejecutar la prueba sobre el mismo dominio que recibirá el docente.

## Cuenta del docente

La cuenta principal será de **emprendedor**, que es el rol del recorrido de aceptación. Se creará en la base de la instalación de evaluación con datos ficticios y una contraseña única. Si el docente solicita probar administración o comercio, se prepara un acceso separado para ese rol.

1. **El comando de aprovisionamiento de Brandon y Jorge** recibe el correo y solicita la contraseña dos veces mediante un prompt oculto. El rol predeterminado es emprendedor. La creación rechaza correos existentes. Si hace falta cambiar una contraseña, `--actualizar-clave` actualiza ese campo y conserva el rol y la suspensión.
2. **Jorge prepara la instalación de evaluación** con las migraciones y los datos ficticios. Luego crea la cuenta del docente. El comando permite repetir el proceso tras recrear una base de evaluación.
3. **Brandon o Jorge comprueba el acceso en una ventana privada**, con el enlace y la contraseña exactos que se van a entregar. Comprueba que aparece como emprendedor y que puede empezar la prueba.
4. **Jorge entrega por un canal privado** la URL HTTPS, el correo de entrada, la contraseña y una instrucción corta para empezar. La contraseña se mantiene fuera de Git, del README, de los seeders y de los reportes de prueba.

```bash
php artisan usuario:crear docente@example.test --nombre="Docente de evaluación"
php artisan usuario:crear docente@example.test --actualizar-clave
```

Sustituye el correo de ejemplo por el acordado para la entrega y ejecuta la segunda línea solo si necesitas cambiar la contraseña de la cuenta existente.

## Pruebas antes de entregar

| Caso | Resultado esperado |
| --- | --- |
| Entrar con la cuenta ya creada | El docente entra sin pasar por registro y el listado muestra su sesión activa. |
| Correo o contraseña incorrectos | Aparece el mensaje de credenciales inválidas y la sesión permanece cerrada. |
| Recargar y navegar | Se conserva la sesión y el rol correcto. |
| Salir y volver a una página privada | Se solicita iniciar sesión y la API anterior devuelve `401`. |
| Solicitud de escritura con CSRF ausente o anterior | Laravel devuelve `419` y la interfaz permite recuperar el token. |
| Cuenta de emprendedor pide una acción de administración, o una cuenta está suspendida | Laravel rechaza la acción. Un usuario tampoco puede consultar productos, notas o propuestas ajenos. |
| Registro de prueba | El correo es único, la contraseña tiene al menos 12 caracteres y hasta 72 bytes, y el formulario solo permite los roles de emprendedor y comercio. |
| Prueba completa en celular | Con la cuenta del docente, abrir la pantalla de entrada, registrar un producto, encontrar un comercio, consultar su ficha y guardar el resultado de contacto en cuanto estén disponibles las pantallas del recorrido. |

La tarea de inicio de sesión queda terminada cuando la entrada y la salida funcionan desde un teléfono, pasan las pruebas con MySQL y la cuenta del docente ha sido verificada en la URL de evaluación.
