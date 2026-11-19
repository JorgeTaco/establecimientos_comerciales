# Reporte: faltantes y falencias del backend

**Fecha:** 18 de noviembre de 2026
**Elaborado por:** Michelle (frontend)
**Revisado:** rama `jorge/api-establecimientos` @ `8948fbf` (aún **no** integrada a `main`)
**Fuentes analizadas:**

- `Objetivo de la aplicación.docx` — requisitos funcionales del cliente
- `Lacteos_Datos_Unificados_Consolidado.xlsx` — 162 establecimientos, 30 columnas
- `docs/contrato-establecimientos.md`, `docs/backend.md`, `docs/frontend.md`
- Código del backend: `routes/api.php`, migraciones, `app/Http/Resources/`, `app/Support/`

---

## 1. Resumen ejecutivo

El backend de la rama de Jorge cubre **la mayor parte** del documento de objetivo: roles, establecimientos, ficha completa, catálogo de productos, propuestas, oportunidades, reportes y panel administrativo. Son ~2.267 líneas, 9 controladores, 5 migraciones y 2 pruebas.

Sin embargo, hay **6 bloqueantes** que impiden que el frontend muestre lo que el cliente pidió, y **14 columnas del Excel que no tienen dónde guardarse**. El riesgo principal: la aplicación se vería como "un mapa con nombres" en vez de una herramienta para decidir dónde ofrecer un producto.

| Categoría | Cantidad |
| --- | --- |
| Bloqueantes para el frontend | 7 |
| Columnas del Excel sin campo en la base | 14 |
| Reglas de negocio sin definir | 6 |
| Falencias técnicas / seguridad | 6 |
| Vacíos de pruebas automatizadas | 5 |

---

## 2. Lo que el backend ya resuelve correctamente

| Requisito del documento | Endpoint | Estado |
| --- | --- | --- |
| Listado de establecimientos | `GET /api/establecimientos` | ✅ (limitado, ver 4.1) |
| Ficha con los 11 campos del docx | `GET /api/establecimientos/{id}` | ✅ |
| Búsqueda con texto, cantón, sector, tipo, categoría, recepción y distancia | `GET /api/establecimientos/buscar` | ✅ |
| Categorías de productos | `GET /api/categorias` | ✅ |
| Registro e inicio de sesión | `POST /api/registro`, `POST /api/sesion`, `GET /api/sesion` | ✅ |
| Catálogo de productos del emprendedor | `apiResource productos` | ✅ |
| Compartir catálogo por enlace y PDF | `POST /api/catalogo/compartir`, `GET /api/productos/{id}/ficha.pdf` | ✅ |
| "Buscar dónde ofrecerlo" con explicación de coincidencia | `GET /api/productos/{id}/establecimientos-compatibles` | ✅ |
| Mis oportunidades con los 7 estados | `GET|POST|PATCH /api/oportunidades` | ✅ |
| Propuestas y respuestas del comercio | `POST /api/establecimientos/{id}/propuestas`, `PATCH /api/propuestas-recibidas/{id}/respuesta` | ✅ |
| Panel administrativo (usuarios, categorías, reportes, indicadores, verificación, importación, duplicados) | `/api/admin/*` | ✅ |
| Roles y cuentas suspendidas | migración `add_roles_to_users_table`, middleware `CuentaActiva` | ✅ |
| "Cómo llegar" | campo `como_llegar_url` en la ficha | ✅ |

**Conclusión:** el backend está mucho más avanzado de lo que refleja `main`. El problema no es que falte casi todo, sino que **el contrato del listado quedó congelado en 6 campos** y **los datos de la encuesta no tienen columnas**.

---

## 3. Bloqueantes para el frontend

### 3.1 El listado solo expone 6 campos

`EstablecimientoResource` devuelve únicamente:

```json
{ "id", "nombre", "tipo", "parroquia", "latitud", "longitud", "recepcionProveedores" }
```

Con eso **no se puede** pintar en la tarjeta: dirección, teléfono autorizado, refrigeración, fecha de verificación, foto ni distancia. Hoy el listado se ve como una lista de nombres, que es exactamente lo que el cliente dijo que **no** quiere como indicador de éxito.

**Propuesta:** agregar a `EstablecimientoResource` los campos de resumen y un parámetro `?incluir=ficha` para traer el bloque completo en una sola llamada.

### 3.2 La distancia solo existe en `/buscar`

`/api/establecimientos` no acepta `latitud`, `longitud` ni `distancia_km`, y no devuelve `distanciaKm`. El frontend tendría que hacer **dos peticiones** y cruzar resultados por `id`.

**Propuesta:** unificar en un solo endpoint: aceptar los mismos filtros de `/buscar` en el listado y devolver `distanciaKm` cuando se envíen coordenadas.

### 3.3 Sin paginación

El listado devuelve **todos** los registros. Con 162 funciona; con 2.000 no.

**Propuesta:** `?page=` y `?per_page=` con bloque `meta` (`total`, `per_page`, `current_page`).

### 3.4 El tipo de comercio es texto libre

- El contrato define solo `tienda`, `minimercado`, `supermercado`.
- El Excel trae además **`Distribuidor` (17 registros)** y **`Panadería` (3)**.
- La columna `tipo` es `string` sin validación (`Rule::in` ausente en `DatosEstablecimiento::reglas`).

Hoy el frontend muestra "Distribuidor" y "Panaderia" sin tilde porque el dato viene así.

**Propuesta:** definir la lista de tipos válidos, normalizarla en la importación y validarla con `Rule::in`.

### 3.5 Sin normalización de tildes ni de variantes

El Excel tiene datos que hoy se ven como **dos cosas distintas**:

| Problema | Ejemplo real | Registros |
| --- | --- | --- |
| Parroquia duplicada por tilde | `Mulaló` vs `Mulalo` | 12 + 9 |
| Tipo duplicado por tilde | `Panadería` vs `Panaderia` | 2 + 1 |
| Booleano con dos formas | `Sí` vs `Si` | 27 + 86 (interés) |
| Tenencia con 4 variantes | `Propio`, `Casa Propia`, `Arrendado`, `Arrendada` | 75 aprox. |

En el frontend esto produce filtros con opciones repetidas. En el backend `buscar` usa `whereLike`, que depende de la colación de MySQL y **no** resuelve `Mulaló` ≠ `Mulalo`.

**Propuesta:** normalizar en la importación (una sola forma canónica) y exponer un catálogo de parroquias y tipos (`GET /api/catalogos/parroquias`, `GET /api/catalogos/tipos`).

### 3.6 No hay fotos

`perfiles_comerciales.foto_url` existe y `POST /api/fotos` permite subir, pero **ningún establecimiento del Excel tiene foto**. La ficha del documento exige "Nombre y fotografía".

**Propuesta:** definir imagen por defecto por tipo (placeholder) y un proceso para que el comercio suba la suya al registrarse.

### 3.7 Sin endpoint de mapa/agrupación

El mapa necesita los puntos y el tipo (ya se puede con el listado actual), pero al filtrar por distancia o al hacer zoom el frontend no tiene forma de pedir "solo lo que está en pantalla" ni agrupaciones.

**Propuesta (no bloqueante para la primera entrega):** aceptar `bbox` (norte/sur/este/oeste) en `/buscar`.

---

## 4. Datos del Excel sin campo en la base

De las 30 columnas del Excel, **14 no tienen dónde guardarse**. Son justamente las que un emprendedor necesita para decidir.

| # | Columna del Excel | Campo en la BD | Estado |
| --- | --- | --- | --- |
| A | Grupo de encuesta | — | ➖ no aplica a la app |
| B | N° | — | ➖ no aplica |
| C | Nombre del establecimiento | `establecimientos.nombre` | ✅ |
| D | Nombre del propietario/administrador | — | ❌ falta |
| E | Tipo de establecimiento | `establecimientos.tipo` | ⚠️ sin validar (3.4) |
| F | Dirección completa | `perfiles_comerciales.direccion` | ✅ |
| G | Provincia | — | ❌ falta (solo hay `canton`) |
| H | Cantón | `perfiles_comerciales.canton` | ✅ |
| I | Parroquia | `establecimientos.parroquia` | ⚠️ sin normalizar (3.5) |
| J | Barrio o sector | `perfiles_comerciales.sector` | ✅ |
| K | Teléfono de contacto | `perfiles_comerciales.contacto_telefono` | ⚠️ requiere autorización (5.2) |
| L | Correo electrónico | `perfiles_comerciales.contacto_email` | ⚠️ requiere autorización (5.2) |
| M | Horario de atención | solo `horario_proveedores` | ⚠️ **no es el mismo dato** |
| N | N° aprox. clientes/día | — | ❌ falta |
| O | Comercializa lácteos | — | ❌ falta |
| P | Productos lácteos que comercializa | — | ❌ falta |
| Q | Marcas que vende actualmente | — | ❌ falta |
| R | Vende de productores locales | — | ❌ falta |
| S | Producto lácteo con mayor demanda | — | ❌ falta |
| T | Volumen promedio de compra semanal | — | ❌ falta |
| U | Frecuencia de abastecimiento | — | ❌ falta |
| V | Principal proveedor | — | ❌ falta |
| W | Cuenta con refrigeración | `perfiles_comerciales.tiene_refrigeracion` | ✅ |
| X | Capacidad de almacenamiento refrigerado | — | ❌ falta (solo booleano) |
| Y | Interés en nuevos proveedores | `perfiles_comerciales.interes_encuesta` | ⚠️ guardado pero **no se usa ni se expone** |
| Z | Interés en productos artesanales/asociaciones | — | ❌ falta |
| AA | Observaciones | — | ❌ falta |
| AB | Local propio o arrendado | — | ❌ falta |
| AC | Latitud (GPS) | `establecimientos.latitud` | ✅ |
| AD | Longitud (GPS) | `establecimientos.longitud` | ✅ |

### Propuesta de esquema

Dos niveles, para no inflar la tabla principal:

1. **Campos tipados** (se filtran y se ordenan):
   `capacidad_refrigeracion_litros`, `clientes_dia`, `volumen_compra_semanal`, `frecuencia_abastecimiento`, `local_tenencia` (`propio|arrendado|otro`), `interes_artesanales` (bool), `comercializa_lacteos` (bool), `provincia`, `propietario_nombre`.

2. **Campo JSON `datos_encuesta`** para lo que solo se muestra en la ficha:
   `marcas[]`, `productos_lacteos[]`, `producto_mayor_demanda`, `proveedor_principal`, `observaciones`, `vende_locales` (bool).

---

## 5. Importación del Excel: la falencia más grave

### 5.1 El endpoint no acepta archivos

`POST /api/admin/establecimientos/importar` recibe **JSON** con `filas[]`, **máximo 100 filas por llamada** y solo para administradores.

- El documento pide explícitamente *"carga de Excel"*.
- El Excel tiene **162 filas** → requiere 2 llamadas y un cliente que lo parta.
- No existe ningún comando de Artisan para importar (solo `usuario:administrador`).
- No hay **modo simulación** (`--dry-run`) ni reporte descargable de errores.

**Propuesta:** comando `php artisan establecimientos:importar {archivo.xlsx} --dry-run` que:
1. lea el Excel con `PhpSpreadsheet`,
2. normalice tildes y variantes,
3. mapee las 30 columnas a los campos de 4.x,
4. detecte duplicados,
5. escriba un CSV con el resultado por fila (`creado`, `duplicado`, `inválida` + motivo).

### 5.2 Privacidad: hay teléfonos sin consentimiento

El Excel trae **149 teléfonos y 149 correos**, pero la encuesta no incluye autorización de publicación. El esquema lo exige (`contacto_autorizado_en`) y la ficha solo devuelve contacto si ese campo no es nulo.

Consecuencia actual: **la API no mostrará ningún contacto** de los 162 registros importados. La ficha quedará sin la acción "Contactar al comercio", que el documento pone como acción principal.

**Propuesta:** decidir con el cliente una de estas dos:
- (a) importar contactos **sin publicar** y mostrar solo "Contacto disponible para administradores", o
- (b) proceso de consentimiento (llamada/WhatsApp) que active `contacto_autorizado_en` por registro.

### 5.3 Duplicados: criterio sin definir

Hay **31 nombres repetidos**, varios con 4 apariciones:

```
Abastos Don Manuel (4) · Distribuidora El Vecino (4) · Comercial El Rosal (4)
Distribuidora El Progreso (4) · Bodega El Rosal (4) · Tienda San José (2) ...
```

`POST /api/admin/establecimientos/duplicados` compara los campos que recibe, pero:
- no define si "San José" y `"San José"` (con comillas) son el mismo,
- no usa teléfono ni dirección como criterio combinado,
- no hay flujo de **fusión** de fichas.

**Propuesta:** regla explícita `nombre normalizado + (teléfono | dirección aproximada)` y endpoint de fusión que conserve la ficha más completa.

### 5.4 `recepcion_proveedores` no viene en el Excel

El Excel no tiene esa columna; solo "interés en nuevos proveedores" (`Sí`/`No`), que se guarda en `interes_encuesta`.

**Propuesta:** fijar la regla ya insinuada en el contrato: **todo registro importado nace en `por_confirmar`**, y solo el comercio o un administrador lo cambian. No derivar `recibe_propuestas` automáticamente desde la encuesta, porque sería afirmar algo que nadie confirmó.

### 5.5 No se distingue el origen del registro

El documento pide *"diferenciar un comercio registrado en el mapa de uno que confirmó que recibe proveedores"*. Hoy no hay campo `origen` (`importado` / `autoregistro` / `administrador`) ni `confirmado_por_comercio_en`.

---

## 6. Otras falencias técnicas

| # | Falencia | Impacto | Propuesta |
| --- | --- | --- | --- |
| 1 | Sin paginación en listado | Escala mal | `page`/`per_page` + `meta` |
| 2 | Sin `Cache-Control` ni ETag en listado público | Cada visita golpea MySQL | caché de 60 s por combinación de filtros |
| 3 | Sin rate limit en el listado público | Abuso trivial | `throttle:60,1` |
| 4 | `verificado_en` sin autor ni historial | No se puede auditar quién verificó | `verificado_por` + tabla de bitácora |
| 5 | Sin notificaciones | El comercio no se entera de una propuesta | correo o bandeja interna |
| 6 | CORS/Sanctum no documentado | Si el frontend se sirve en otro origen, falla | documentar y configurar `SANCTUM_STATEFUL_DOMAINS` |
| 7 | `GOOGLE_MAPS_*` no está en el pipeline de despliegue | El mapa no funciona en producción | documentar variables requeridas |

## 7. Vacíos de pruebas

Existen `ListarEstablecimientosTest` y `GestionComercialTest`. Faltan pruebas de:

1. Importación (fila válida, duplicada, inválida, límite de 100).
2. Detección de duplicados.
3. Búsqueda por distancia (cálculo y orden).
4. Permisos por rol (emprendedor no puede editar establecimientos).
5. Normalización de tildes y variantes (`Mulaló`/`Mulalo`, `Sí`/`Si`).

---

## 8. Contrato propuesto para el listado ampliado

```json
{
  "establecimientos": [
    {
      "id": "12",
      "nombre": "Minimarket Los 3 Hermanos",
      "tipo": "minimercado",
      "parroquia": "Mulaló",
      "latitud": -0.781192,
      "longitud": -78.575341,
      "recepcionProveedores": "por_confirmar",
      "direccion": "Mulaló, Calle 27 de Febrero",
      "sector": "Mulaló Centro",
      "canton": "Latacunga",
      "fotoUrl": null,
      "tieneRefrigeracion": true,
      "capacidadRefrigeracion": "200 litros",
      "categorias": [{ "id": 3, "nombre": "Lácteos" }],
      "verificadoEn": null,
      "distanciaKm": 2.4,
      "comoLlegarUrl": "https://www.google.com/maps/dir/?api=1&destination=-0.781192,-78.575341"
    }
  ],
  "meta": { "total": 162, "perPage": 25, "currentPage": 1 }
}
```

Regla de compatibilidad: **los 6 campos actuales se mantienen tal cual**. Todo lo demás es aditivo, así que el frontend actual sigue funcionando mientras se integra.

---

## 9. Prioridad sugerida

| Prioridad | Tarea | Bloquea |
| --- | --- | --- |
| **P0** | Ampliar `EstablecimientoResource` (dirección, sector, refrigeración, verificación, foto, `como_llegar_url`) | Tarjetas y ficha usable |
| **P0** | Unificar filtros y `distanciaKm` en el listado | Filtro por distancia |
| **P0** | Validar y normalizar `tipo` y `parroquia` | Filtros duplicados |
| **P0** | Comando de importación del Excel con `--dry-run` | Cargar los 162 registros |
| **P1** | Columnas faltantes de la encuesta (4.x) | Ficha con marcas/volumen/refrigeración |
| **P1** | Decisión de privacidad del contacto (5.2) | Acción "Contactar" |
| **P1** | Criterio y fusión de duplicados (5.3) | Calidad del mapa |
| **P1** | `origen` y `confirmado_por_comercio_en` | Distinguir registro de confirmación |
| **P2** | Paginación, caché, rate limit | Escala |
| **P2** | Notificaciones, auditoría de verificación | Operación |
| **P2** | `bbox` para el mapa | Rendimiento del mapa |

---

## 10. Checklist para la siguiente versión del frontend

- [ ] Tarjetas con dirección, sector, teléfono (si está autorizado), refrigeración y estado de verificación.
- [ ] Ficha completa con foto, mapa, categorías, productos buscados, requisitos, condiciones de compra y "Cómo llegar".
- [ ] Marcadores diferenciados por tipo y filtro por distancia.
- [ ] Alternar mapa ↔ listado.
- [ ] Buscador por cantón/sector además de parroquia.
- [ ] Catálogo de productos con compartir y PDF.
- [ ] "Buscar dónde ofrecerlo" con explicación de la coincidencia.
- [ ] Mis oportunidades con los 7 estados, notas y próxima gestión.
- [ ] Panel administrativo: verificar, importar, resolver duplicados, reportes.
- [ ] Registro/login con roles y PWA instalable.

---

## Anexo: números de la encuesta

| Indicador | Valor |
| --- | --- |
| Registros totales | 162 |
| Con coordenadas | 162 (100 %) |
| Interés en nuevos proveedores | 113 Sí / 49 No |
| Comercializa lácteos | 149 Sí / 13 No |
| Ambas condiciones | **109 comercios** |
| Teléfono / correo / propietario | 149 de 162 |
| Parroquias | 9 (con `Mulaló`/`Mulalo` sin unificar) |
| Tipos | Tienda 81 · Minimarket 41 · Supermercado 20 · Distribuidor 17 · Panadería 3 |
| Nombres repetidos | 31 |
