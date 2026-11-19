# Datos y validación

Brandon prepara aquí las reglas de importación y los ejemplos ficticios. El primer avance cubre el mapeo de columnas, la normalización de respuestas, la revisión de coordenadas y la detección de posibles duplicados. Los casos dudosos pasan a revisión antes de fusionar fichas. Hay dos comandos complementarios: `establecimientos:importar` valida y guarda directamente desde `.xlsx` o `.csv`, y `establecimientos:preparar-lotes` convierte un CSV de Excel en lotes JSON para la API.

El [ejemplo del listado](ejemplos/establecimientos.json) sigue el [contrato inicial](../docs/contrato-establecimientos.md). Los ejemplos de importación son [`ejemplos/encuesta-ficticia.csv`](ejemplos/encuesta-ficticia.csv) y [`ejemplos/establecimientos-importar.csv`](ejemplos/establecimientos-importar.csv), ambos con datos ficticios. Los seeders van en [`database/seeders/`](../database/seeders/) y las pruebas en [`tests/`](../tests/).

## Importador directo (.xlsx o .csv)

```bash
php artisan establecimientos:importar datos/ejemplos/encuesta-ficticia.csv --dry-run
php artisan establecimientos:importar datos/ejemplos/encuesta-ficticia.csv \
  --categoria=Lácteos --json=storage/app/importaciones/ejemplo.json
```

| Opción | Uso |
| --- | --- |
| `--dry-run` | Analiza y reporta, pero no escribe en la base. |
| `--hoja=NOMBRE` | Elige una hoja del `.xlsx` por nombre o número. |
| `--fila-encabezado=N` | Fuerza la fila de encabezados cuando la detección automática falla. |
| `--limite=N` | Procesa solo las primeras N filas de datos. |
| `--categoria=NOMBRE` | Asocia esa categoría (debe existir y estar activa) a los comercios que comercializan lácteos. |
| `--corregir-signo` | Convierte latitudes positivas evidentes en negativas (Ecuador está al sur). |
| `--permitir-coordenadas-dudosas` | Importa filas con coordenadas sospechosas en vez de dejarlas para revisión. |
| `--json=RUTA` | Guarda el reporte completo; por defecto va a `storage/app/importaciones/`. |

Estados del reporte: `importada`, `valida`, `invalida`, `posible_duplicado`, `revisar` y `reintentar`. El reporte incluye `candidatos` (duplicados fuertes dentro del archivo), `coincidencias` (mismo nombre o misma dirección por separado; son comunes entre comercios vecinos y no bloquean) y `candidatos_db` (IDs que ya existen en la base).

Normalización aplicada: parroquias `Mulalo` → `Mulaló` y `Tanicuchi` → `Tanicuchí`; tipos `Minimarket`/`Mini Market` → `minimercado` y `Panadería` → `panaderia`; booleanos `Si`/`Sí`/`S`/`1`/`X` → `true`, `No`/`N`/`0` → `false`; teléfonos reducidos a dígitos; correos en minúsculas y sin tildes; contacto privado con `autorizar_contacto = false`; recepción siempre `por_confirmar`. `interes_encuesta` sale de la columna de interés en nuevos proveedores y la categoría solo se asocia si se indica `--categoria` y existe en el catálogo.

## Preparación de lotes para la API

`establecimientos:preparar-lotes` lee un CSV exportado desde Excel (UTF-8, con delimitadores `,`, `;`, tabulador o `|`, y conversión desde Windows-1252) y genera lotes de hasta 100 fichas según el [contrato de integración](../docs/backend.md).

```bash
php artisan establecimientos:preparar-lotes datos/ejemplos/establecimientos-importar.csv
IMPORTADOR_CORREO=admin@example.test IMPORTADOR_CLAVE=... \
  php artisan establecimientos:preparar-lotes archivo.csv --enviar
```

También acepta `--url`, `--correo`, `--clave`, `--destino` y `--tamano-lote`. La clave nunca se imprime ni se guarda. Los archivos generados quedan en `datos/importacion/` (`lote-N.json`, `informe.json` y, si se envió, `respuestas.json`), directorio excluido de Git. Con `--enviar`, los lotes se publican en `POST /api/admin/establecimientos/importar` tras iniciar sesión y enviar el token CSRF.

### Mapeo de las columnas del Excel

El encabezado se compara sin distinción de mayúsculas, tildes, espacios ni guiones bajos, así que funciona con las cabeceras originales y con alias cortos.

| Columna del Excel | Destino en la API |
| --- | --- |
| `Nombre del establecimiento` | `nombre` |
| `Tipo de establecimiento` | `tipo` (`Minimarket` se normaliza a `minimercado`; se conservan `tienda`, `supermercado`, `distribuidor` y `panadería`) |
| `Parroquia` | `parroquia` |
| `Direccion completa` | `perfil.direccion` |
| `Canton` | `perfil.canton` |
| `Barrio o sector` | `perfil.sector` |
| `Nombre del propietario/administrador` | `perfil.contacto_nombre` (privado) |
| `Telefono de contacto` | `perfil.contacto_telefono` (privado) |
| `Correo electronico` | `perfil.contacto_email` (privado) |
| `Horario de atencion` | `perfil.horario_proveedores` |
| `Cuenta con refrigeracion` | `perfil.tiene_refrigeracion` |
| `Producto lacteo con mayor demanda` | `perfil.productos_buscados` |
| `Interes en nuevos proveedores` | `interes_encuesta` (privado) |
| `Latitud (GPS)`, `Longitud (GPS)` | `latitud`, `longitud` |

Alias adicionales admitidos: `nombre`, `nombre_comercial`, `razon_social`, `tipo`, `parroquia`, `latitud`/`lat`, `longitud`/`lon`/`lng`, `direccion`, `canton`, `sector`, `productos_buscados`, `tiene_refrigeracion`/`refrigeracion`, `horario_proveedores`, `modalidad_compra`, `pedido_inicial`, `decision_compra`, `telefono`/`celular`, `correo`/`email`, `canales_contacto`, `interes_encuesta`, `categorias`, `foto_url` y `requisitos`.

Estas columnas del Excel no tienen campo en el esquema actual y aparecen en `sin_mapear` del informe. Brandon no crea migraciones: si se van a conservar, hay que acordar el formato y pedirle a Jorge la migración aditiva.

`N°`, columna vacía inicial, `Provincia`, `N° aprox. clientes/dia`, `Comercializa lacteos`, `Productos lacteos que comercializa`, `Marcas que vende actualmente`, `Vende de productores locales`, `Volumen promedio compra semanal`, `Frecuencia de abastecimiento`, `Principal proveedor`, `Capacidad almacenamiento refrigerado`, `Interes en productos artesanales/asociaciones`, `Observaciones` y `Local propio o arrendado`.

Los textos «No tiene» y «No dispone de correo electrónico», presentes en el archivo real, se convierten en `null` en teléfono, correo y demás campos opcionales; el CSV de origen no se modifica. Las coordenadas se validan en `-90..90` y `-180..180`, se redondean a seis decimales y una sola coordenada sin la otra marca la fila como inválida. Las filas con el mismo nombre, dirección y teléfono se marcan como `posible_duplicado`, tanto contra la base como dentro del mismo archivo.

## Archivos reales y datos ficticios

El repositorio es público: el Excel real y los reportes con datos de contacto **no se suben a Git**. En esta copia local están `datos/privados/Lacteos_Datos_Unificados_Consolidado (1).xlsx` y `datos/privados/establecimientos.csv`, exportado con las 30 columnas y los 162 registros originales, ambos ignorados por Git. La columna de agrupación (`GRUPO N°1` … `GRUPO N°5`) llega sin encabezado, por lo que se ignora al mapear, pero las 162 fichas se conservan. El comando produce dos lotes porque el contrato limita cada carga a 100 fichas. Un registro tiene latitud positiva fuera del rango esperado de Latacunga; se conserva sin cambiar y debe corregirse desde la ficha con autorización de un administrador.

```bash
php artisan db:seed
php artisan test --filter=ImportacionCsvTest
php artisan test --filter=ImportarEstablecimientosTest
php artisan test --filter=NormalizadorFilasTest
php artisan test --filter=ImportacionTest
php artisan test --filter=DatosFicticiosTest
```

Los seeders crean las categorías y los tres comercios del ejemplo del listado, uno por cada estado de recepción. Las factories permiten generar establecimientos, perfiles y categorías adicionales en las pruebas. La primera revisión manual consiste en abrir el listado en un celular y comprobar que los tres estados se distinguen con claridad. Para el piloto, verificar que un emprendedor pueda registrar un producto, encontrar comercios compatibles, contactar uno y guardar el resultado.

### Pendientes de coordinación

- La comprobación de duplicados de `DatosEstablecimiento` también considera la dirección por sí sola. Varios comercios de la misma calle pueden quedar como `posible_duplicado` al guardar, aunque el reporte los clasifique como coincidencia leve. Revisar con Jorge si conviene exigir nombre o teléfono en esa regla.
- El catálogo de categorías está vacío en una base recién migrada; `--categoria=Lácteos` no asocia nada hasta que exista esa categoría. Confirmar con Jorge quién carga el catálogo inicial.
- Las 14 columnas pendientes listadas arriba requieren acuerdo con Jorge y una migración aditiva antes de conservarse.
