# Datos y validación

Brandon prepara aquí las reglas de importación y los ejemplos ficticios. El primer avance cubre el mapeo de columnas, la normalización de respuestas, la revisión de coordenadas y la detección de posibles duplicados. Los casos dudosos pasan a revisión antes de fusionar fichas. El importador se implementa como un comando de Artisan para que el mismo flujo sirva con Excel o CSV.

El [ejemplo del listado](ejemplos/establecimientos.json) sigue el [contrato inicial](../docs/contrato-establecimientos.md). Los seeders de datos ficticios van en [`database/seeders/`](../database/seeders/) y las pruebas automatizadas en [`tests/`](../tests/). Para la primera prueba, abrir el listado en un celular y comprobar que los tres estados de recepción de propuestas se distinguen con claridad. Para el piloto, verificar que un emprendedor pueda registrar un producto, encontrar comercios compatibles, contactar uno y guardar el resultado.

## Importador de establecimientos

El comando `establecimientos:importar` lee `.xlsx` (sin dependencias externas) o `.csv`, detecta la fila de encabezados, normaliza los valores y genera un reporte antes de guardar. El listado ficticio [`ejemplos/encuesta-ficticia.csv`](ejemplos/encuesta-ficticia.csv) sirve para probar todos los estados sin usar datos reales.

```bash
# Analizar sin guardar nada
php artisan establecimientos:importar datos/ejemplos/encuesta-ficticia.csv --dry-run

# Importar y guardar el reporte JSON
php artisan establecimientos:importar datos/ejemplos/encuesta-ficticia.csv \
  --categoria=Lácteos --json=storage/app/importaciones/ejemplo.json
```

Opciones principales:

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

Estados del reporte:

- `importada`: la fila se guardó.
- `valida`: pasó las validaciones, pero no se guardó por `--dry-run`.
- `invalida`: falta un dato obligatorio o no cumple las reglas de `DatosEstablecimiento`.
- `posible_duplicado`: comparte teléfono, o nombre y dirección, con otra fila o con la base. No se guarda hasta revisarlo.
- `revisar`: las coordenadas necesitan confirmación manual (por ejemplo, latitud positiva).
- `reintentar`: la base estaba ocupada; se puede volver a ejecutar el lote.

El reporte incluye `candidatos` (duplicados fuertes dentro del archivo), `coincidencias` (mismo nombre o misma dirección por separado; son comunes entre comercios vecinos y no bloquean la importación) y `candidatos_db` (IDs que ya existen en la base).

### Criterios de normalización

- Parroquias: `Mulalo` → `Mulaló`, `Tanicuchi` → `Tanicuchí`; el resto se guarda con mayúsculas iniciales.
- Tipos: `Minimarket`/`Mini Market` → `minimercado`, `Panadería` → `panaderia`; los tipos desconocidos se guardan en formato slug y generan un aviso.
- Booleanos: `Si`, `Sí`, `S`, `1`, `X` → `true`; `No`, `N`, `0` → `false`.
- Teléfonos: se conservan solo los dígitos (y el prefijo `+` si venía); valores como `No tiene` se descartan.
- Correos: se pasan a minúsculas, se quitan tildes y eñes (error frecuente de digitación) y los inválidos se descartan con aviso.
- Contacto: se guarda como dato privado con `autorizar_contacto = false`; la ficha pública no muestra teléfono ni correo.
- Recepción: toda importación queda en `por_confirmar`.
- Lácteos: `interes_encuesta` sale de la columna de interés en nuevos proveedores. La categoría solo se asocia si se indica `--categoria` y existe en el catálogo.

### Datos reales

El Excel real y los reportes con datos de contacto **no se suben a Git**. Guárdalos en `datos/privados/` (ignorada por Git) y usa nombres ficticios en `datos/ejemplos/`. Los `.xlsx`, `.xls` y `.xlsm` también están ignorados.

### Pendientes de coordinación

- La comprobación de duplicados de `DatosEstablecimiento` también considera la dirección por sí sola. Varios comercios de la misma calle pueden quedar como `posible_duplicado` al guardar, aunque el reporte los clasifique como coincidencia leve. Revisar con Jorge si conviene exigir nombre o teléfono en esa regla.
- El catálogo de categorías está vacío en una base recién migrada; `--categoria=Lácteos` no asocia nada hasta que exista esa categoría. Confirmar con Jorge quién carga el catálogo inicial.
