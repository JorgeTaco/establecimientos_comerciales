# Solicitud al backend — lo que necesito de Jorge para continuar el frontend

**Elaborado por:** Michelle (frontend)
**Fecha:** 19 de noviembre de 2026
**Rama revisada:** `michelle/listado-establecimientos` (con `main` ya integrado, `d93046e`)
**Backend revisado:** `main` @ `cd1f05c` (PR #1 y #2 mergeados)
**Alcance:** solo cambios **aditivos** al contrato. Nada de esto reescribe la lógica existente.

---

## 1. Resumen

De los 7 bloqueantes que reporté antes, al revisar el código contra `main` quedan **3 reales** y **1 decisión de producto**. Los demás eran inexactitudes de mi reporte anterior (ver §5).

| # | Necesidad | Tipo | ¿Bloquea? | Prioridad |
|---|---|---|---|---|
| B1 | Ruta web `/establecimientos` | ya incluida en mi PR | sin ella la vista no se abre | **P0** |
| B2 | Exponer el interés declarado en la encuesta | contrato aditivo | el filtro principal nace vacío | **P0** |
| B3 | Categorías creadas (Leche, Queso, Yogurt, Mantequilla) | datos, no código | filtro por categoría y compatibilidad | **P0** |
| B4 | Confirmar el catálogo de `tipo` (6 valores reales) | documentación | no bloquea (la UI ya tolera) | P1 |
| B5 | Filtro exacto por `parroquia` en `/buscar` | contrato aditivo | filtro impreciso | P1 |
| B6 | `sector` y `foto_url` en el listado | contrato aditivo | tarjetas ambiguas | P2 |
| B7 | Google Maps: key + Map ID | configuración | no hay mapa real | P1 |

---

## 2. P0 — Bloqueantes

### B1. Ruta web `/establecimientos`

**Qué necesito:** que se acepte esta línea en `routes/web.php` (tu archivo):

```php
Route::view('/establecimientos', 'establecimientos.index')->name('establecimientos.index');
```

**Por qué:** la vista existe en mi rama, pero `routes/web.php` solo tiene `/`. Sin la ruta, la página no es accesible (404) aunque el PR se mergee.

**Estado:** ya está en mi rama sin commitear. Solo necesito tu revisión al mergear. **Cero riesgo:** no toca controladores, sesión ni API.

**Criterio de aceptación:** `GET /establecimientos` devuelve 200 y renderiza el listado.

---

### B2. El interés declarado en la encuesta no es visible para el frontend

**Qué pasa (verificado en código):**

- `app/Support/DatosEstablecimiento.php:96` fuerza en la importación:
  `$base['recepcion_proveedores'] = 'por_confirmar';`
- `:103` guarda la respuesta de la encuesta en `perfil.interes_encuesta`, **privado**.

**Consecuencia:** los 162 comercios entrarán como `por_confirmar`. El filtro “Recepción de propuestas” —que es el corazón del docx (“revisa si reciben propuestas antes de contactarlos”)— nacería **siempre vacío**, y la ficha mostraría “Por confirmar” en el 100 % de los casos. El usuario no obtiene ninguna señal para decidir.

**Necesito una de estas tres** (decide tú/Brandon):

| Opción | Cambio | Ventaja | Coste |
|---|---|---|---|
| **A (recomendada)** | Exponer `interesDeclarado` (`true \| false \| null`) en `EstablecimientoResource` y `FichaEstablecimientoResource`, y aceptar `interes_declarado=1\|0` en `/buscar` | Honesto: “interés declarado” ≠ “confirmado”. No rompe nada | Campo + parámetro aditivo |
| B | Que la importación mapee `interes_encuesta` → `recepcion_proveedores` | La UI funciona sin cambios | Mezcla dos conceptos distintos; el docx pide diferenciarlos |
| C | Dejar todo en `por_confirmar` y ocultar el filtro hasta que se verifique | Cero cambios de backend | Se pierde la propuesta de valor en la primera entrega |

**Si eliges C**, avísame y quito el filtro; pero el listado queda sin el dato más útil.

**Criterio de aceptación (opción A):** `GET /api/establecimientos` devuelve `interesDeclarado` y `GET /api/establecimientos/buscar?interes_declarado=1` filtra correctamente.

---

### B3. Categorías: sin datos, el filtro y la compatibilidad no funcionan

**Qué pasa:** `GET /api/categorias` existe y responde bien, pero devuelve `[]`. Y `GET /productos/{id}/establecimientos-compatibles` filtra por `categoria_id`, así que sin categorías asignadas devuelve siempre vacío.

**Qué necesito:** las 4 categorías lácteas creadas (**Leche, Queso, Yogurt, Mantequilla**) y la regla de mapeo desde la columna del Excel “Productos lácteos que comercializa”.

**Nota:** `POST /api/admin/categorias` ya permite crearlas sin código. El mapeo del Excel es de **Brandon**; la creación/seeder puede ser tuya o suya. Aquí lo importante es que existan antes de la carga.

**Criterio de aceptación:** `GET /api/categorias` devuelve las 4, y al menos un establecimiento tiene `categorias` no vacías en su ficha.

---

## 3. P1 — Necesario para una entrega usable

### B4. Confirmar el catálogo de `tipo`

Verifiqué que **no hace falta migración**: la columna es `string` y la validación es `string|max:80`. Los valores reales del Excel son:

| Tipo | Registros |
|---|---|
| Tienda | 81 |
| Minimarket | 41 |
| Supermercado | 20 |
| **Distribuidor** | 17 |
| **Panadería** | 3 |

**Qué necesito:** que `docs/contrato-establecimientos.md` documente los 6 valores (hoy dice 3). Mi UI ya muestra cualquier tipo desconocido con su etiqueta capitalizada, así que **no me bloquea**, pero conviene que el contrato no contradiga los datos.

---

### B5. Filtro exacto por parroquia

**Qué pasa:** `/buscar` no acepta `parroquia`. El parámetro `sector` hace:

```php
->where(fn ($p) => $p->where('parroquia', $sector)
    ->orWhereHas('perfilComercial', fn ($q) => $q->where('sector', $sector)))
```

Es decir, “sector” busca en **parroquia O barrio**. Si filtro `sector=Centro`, entran comercios de cualquier parroquia cuyo barrio sea “Centro”. Para el usuario, que elige una parroquia, el resultado es impreciso.

**Qué necesito:** un parámetro `parroquia` con coincidencia exacta (aditivo, no rompe `sector`).

**Criterio de aceptación:** `GET /api/establecimientos/buscar?parroquia=Lasso` devuelve solo comercios de Lasso.

---

### B7. Google Maps: key y Map ID

**Qué necesito de ti:** coordinar con el cliente la cuenta de Google Cloud (**a nombre de la entidad**) y entregar:

- `GOOGLE_MAPS_API_KEY` restringida a **Maps JavaScript API** y a los referrers `http://127.0.0.1:8000/*`, `http://localhost:8000/*` y el dominio de producción.
- `GOOGLE_MAPS_MAP_ID` (mapa vectorial, para marcadores avanzados).

**Advertencia:** esa clave se imprime en el HTML del navegador; la restricción por referrer **no es opcional** o cualquiera puede consumir el presupuesto del cliente.

**Mientras no exista:** la pantalla ya muestra un aviso con los comercios y sus coordenadas, sin romperse. No me bloquea para seguir, solo impide el mapa real.

---

## 4. P2 — Mejoras que me ahorrarían trabajo

### B6. `sector` y `foto_url` en el listado

`EstablecimientoResource` devuelve solo `id, nombre, tipo, parroquia, latitud, longitud, recepcionProveedores`.

El Excel tiene **35 nombres repetidos** (“Abastos Don Manuel” ×4, “Distribuidora El Vecino” ×4, etc.) que son comercios **distintos** en barrios distintos. Sin `sector` ni `direccion`, dos tarjetas pueden parecer duplicados.

**Qué necesito (aditivo, nullable):**

```php
'sector' => $this->perfilComercial?->sector,
'foto_url' => $this->perfilComercial?->foto_url,
```

Esto obliga a cargar `perfilComercial` en el listado; si prefieres no hacerlo por rendimiento, dímelo y resuelvo la desambiguación de otra forma.

---

## 5. Correcciones a mi reporte anterior

Para que no se implemente trabajo innecesario:

| Lo que dije antes | Verificación real |
|---|---|
| “El tipo de comercio es texto libre, hay que migrar el enum” | ❌ No hay enum en la BD: `$table->string('tipo')`. **No requiere migración.** Solo documentar valores |
| “El listado solo expone 6 campos: bloqueante” | ⚠️ Cierto, pero **no bloqueante**: con los 6 campos el listado funciona. `sector` es mejora (B6) |
| “Sin fotos” | ⚠️ `POST /api/fotos` existe y `foto_url` es nullable. No es bloqueante; la UI usa marcador de posición |
| “Falta endpoint de mapa/agrupación” | ❌ Innecesario: con 162 puntos se agrupa en el cliente |
| “La rama de Jorge no está en main” | ❌ Ya está: PR #2 mergeado en `cd1f05c` |

---

## 6. Lo que NO es de Jorge (para no confundir responsabilidades)

| Tarea | Responsable |
|---|---|
| Leer el Excel, mapear columnas, normalizar tildes/variantes, lotes de importación | **Brandon** |
| Decidir criterio de duplicados con datos reales | **Brandon** (revisión: Jorge) |
| Cargar los 162 registros | **Brandon** |
| Crear las 4 categorías y asignarlas | Jorge/Brandon (según B3) |
| Cuenta de Google Cloud y facturación | **Cliente/entidad** |

---

## 7. Lo que ya resolví por mi lado (no requiere backend)

- Fuente de datos con respaldo: intenta `GET /api/establecimientos`; si falla o está vacío, usa el JSON ficticio y lo avisa en pantalla. Cuando haya datos, el aviso desaparece sin cambiar código.
- 12 comercios ficticios para maquetar (8 parroquias, 2 sin coordenadas, los 3 estados).
- Manejo de tipos desconocidos, comercios sin coordenadas, contacto no autorizado y estados no previstos.
- Navegación y estilos móvil primero, sin dependencias nuevas (sin `package.json`).

---

## 8. Checklist para Jorge

- [ ] Revisar y aceptar la línea de ruta en `routes/web.php` (B1).
- [ ] Decidir A / B / C sobre el interés declarado (B2). **Es lo único que me impide mostrar el dato clave del docx.**
- [ ] Confirmar que las 4 categorías existirán antes de la importación (B3).
- [ ] Documentar los 6 valores de `tipo` en el contrato (B4).
- [ ] Añadir el parámetro `parroquia` a `/buscar` (B5).
- [ ] (Opcional) `sector` y `foto_url` en el listado (B6).
- [ ] Coordinar la key de Google Maps con el cliente (B7).

---

## 9. Cómo probar cada cambio

```bash
# B1
curl -s -o /dev/null -w "%{http_code}\n" http://127.0.0.1:8000/establecimientos

# B2 (si se elige la opción A)
curl -s "http://127.0.0.1:8000/api/establecimientos/buscar?interes_declarado=1" | head -c 300

# B3
curl -s http://127.0.0.1:8000/api/categorias

# B5
curl -s "http://127.0.0.1:8000/api/establecimientos/buscar?parroquia=Lasso" | head -c 300
```

---

**Nota:** este documento no modifica el contrato; solo propone cambios aditivos y deja constancia de lo verificado. Si algo de esto contradice lo que ya acordaron, dime y lo ajusto antes de que lo implementen.
