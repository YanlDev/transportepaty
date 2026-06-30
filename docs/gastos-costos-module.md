# Plan de Implementación — Módulo de Gastos y Costos

> Plan pensado para que un desarrollador junior lo ejecute paso a paso y luego
> se verifique. Faseado. Reutiliza patrones que ya existen en el código.

## Decisiones cerradas (no cambiar)

1. **Factura + RUC + comprobante** por gasto (metadata de factura, opcional; no
   es contabilidad SUNAT).
2. **Combustible y Mantenimiento NO se re-digitan**: la vista de Costos los
   **agrega** desde sus módulos (con sus comprobantes). Son **categorías
   virtuales de sistema**, no filas en `categorias_gasto`.
3. **Categorías administrables (CRUD admin)** para el resto (Seguros, SOAT,
   Impuestos, etc.). Borrado: si la categoría está en uso → **desactivar**; si
   no → borrar.
4. **Alcance**: un gasto es por **vehículo** o por **sucursal** (ambos
   nullable). Si hay vehículo, la sucursal se infiere de él.
5. **Faseado**: Fase 1 = categorías + módulo Gastos. Fase 2 = tablero de Costos
   consolidado.

## Convenciones obligatorias (copiar de estos archivos)

- **CRUD admin con diálogos (PATRÓN PRINCIPAL A CLONAR):**
  - `app/Http/Controllers/PlantillaMantenimientoController.php`
  - `resources/js/pages/mantenedor/plantillas-mantenimiento.tsx`
  - `resources/js/components/mantenimiento/plantilla-form-dialog.tsx`
  - `resources/js/components/mantenimiento/eliminar-plantilla-dialog.tsx`
- **Modelo + media + softDeletes:** `app/Models/Mantenimiento.php` (la colección
  `comprobante` acepta PDF).
- **Service de agregación:** `app/Services/Mantenimiento/PlanMantenimientoService.php`
  (`costosAnio` es el ejemplo de unir fuentes de costo).
- **Policy admin/visor:** `app/Policies/SucursalPolicy.php`.
- **Charts:** `resources/js/components/mantenimiento/chart-costos-anio.tsx` +
  `ChartCard` de `resources/js/components/combustible/`.
- **Reglas del repo:**
  - Usar `php artisan make:` para generar archivos.
  - **No** cambiar dependencias sin aprobación.
  - **Nunca** `migrate:fresh`, `migrate:refresh` ni seeders sobre la BD de
    desarrollo (pgsql, datos reales). Usar `php artisan migrate` normal; los
    tests usan sqlite `:memory:` (phpunit.xml), aislados.
  - Antes de pedir review: `vendor/bin/pint <archivos>`,
    `php artisan wayfinder:generate --with-form`, `npx tsc --noEmit`,
    `npx eslint <archivos>`, `npm run build`, `php artisan test --compact`.
  - Cada cambio con su test (unit/feature).

---

## FASE 1 — Categorías + Gastos

### 1.1 Migraciones (2)

**`create_categorias_gasto_table`**

| Campo | Tipo | Notas |
|---|---|---|
| id | id | |
| nombre | string, unique | "Seguros", "Peajes"… |
| color | string(7) nullable | hex para badge, opcional |
| activo | boolean default true | |
| timestamps | | |

**`create_gastos_table`**

| Campo | Tipo | Notas |
|---|---|---|
| id | id | |
| categoria_id | foreignId constrained('categorias_gasto') restrictOnDelete | no borrar categoría con gastos |
| vehiculo_id | foreignId nullable constrained nullOnDelete | |
| sucursal_id | foreignId nullable constrained('sucursales') nullOnDelete | |
| registrado_por | foreignId nullable constrained('users') nullOnDelete | |
| descripcion | string | |
| monto | decimal(10,2) | |
| fecha | date | fecha del gasto |
| proveedor | string nullable | razón social |
| ruc_proveedor | string(11) nullable | |
| factura_numero | string(50) nullable | |
| fecha_emision | date nullable | |
| observaciones | text nullable | |
| timestamps + softDeletes | | |

Índices: `(sucursal_id, fecha)`, `(vehiculo_id, fecha)`, `(categoria_id)`.

> **Aceptación:** `php artisan migrate` corre y revierte. (NO `migrate:fresh`.)

### 1.2 Modelos + factories

- **`CategoriaGasto`** (`make:model -f`): `#[Fillable]`, `casts` (`activo`
  bool). Relación `gastos(): HasMany`. Scope `scopeActivas`.
- **`Gasto`** (`make:model -f`): traits `HasFactory, SoftDeletes,
  InteractsWithMedia`. Relaciones `categoria()`, `vehiculo()`, `sucursal()`,
  `registradoPor()`. Media: colección `comprobante` (singleFile,
  `image/jpeg,png,webp` + `application/pdf`) + conversión `thumb` nonQueued.
  `casts`: `monto` decimal:2, `fecha`/`fecha_emision` `date:Y-m-d`.
- Factories con datos realistas (RUC `fake()->numerify('###########')`).

### 1.3 Seeder `CategoriaGastoSeeder`

`updateOrCreate` por `nombre`, sembrar: Seguros, SOAT, Impuestos, Revisión
técnica, Peajes, Multas, Neumáticos, Accesorios, GPS, Otros. Registrar en
`DatabaseSeeder`.

### 1.4 Policies (auto-discovered)

- **`CategoriaGastoPolicy`**: viewAny/create/update/delete = **admin**.
- **`GastoPolicy`**: viewAny/view = **admin, visor**; create/update/delete =
  **admin**.

### 1.5 Requests

- **`StoreCategoriaGastoRequest`**: `nombre` required, string, max:60,
  `Rule::unique('categorias_gasto','nombre')` (en update,
  `->ignore($categoria)`); `color` nullable string max:7; `activo` required
  boolean. (Usa Store+Update separados como Conductor, o un solo request con el
  unique parametrizable.)
- **`StoreGastoRequest`** (sirve store y update):

```php
'categoria_id'  => ['required', 'exists:categorias_gasto,id'],
'vehiculo_id'   => ['nullable', 'exists:vehiculos,id'],
'sucursal_id'   => ['nullable', 'exists:sucursales,id'],
'descripcion'   => ['required', 'string', 'max:255'],
'monto'         => ['required', 'numeric', 'min:0'],
'fecha'         => ['required', 'date'],
'proveedor'     => ['nullable', 'string', 'max:255'],
'ruc_proveedor' => ['nullable', 'digits:11'],
'factura_numero'=> ['nullable', 'string', 'max:50'],
'fecha_emision' => ['nullable', 'date'],
'observaciones' => ['nullable', 'string', 'max:1000'],
'comprobante'   => ['nullable', 'file', 'mimes:jpeg,png,webp,pdf', 'max:10240'],
```

`withValidator`: exigir **al menos uno** de `vehiculo_id` / `sucursal_id` (si no,
error en `vehiculo_id`).

### 1.6 Controllers

- **`CategoriaGastoController`** (clonar `PlantillaMantenimientoController`):
  `index` (Inertia `mantenedor/categorias-gasto`, con `withCount('gastos')`),
  `store`, `update`, `destroy`. En `destroy`: si `gastos_count > 0` → no borrar,
  devolver toast de error sugiriendo desactivar (o setear `activo = false`).
- **`GastoController`**:
  - `index(Request)`: `authorize('viewAny', Gasto::class)`. Filtros: `buscar`,
    `categoria_id`, `sucursal_id`, `vehiculo_id`, `desde`, `hasta`. Query con
    `with(['categoria','vehiculo:id,placa','sucursal:id,nombre','media'])`,
    `latest('fecha')`, `paginate(20)->withQueryString()`. Pasar opciones para
    selects (categorías activas, sucursales, vehículos) + DTO con
    `comprobante_url`.
  - `store(StoreGastoRequest)`: crea el gasto (`registrado_por = user`); si hay
    archivo sube `comprobante`. Si viene `vehiculo_id` y no `sucursal_id`,
    inferir la sucursal del vehículo.
  - `update`, `destroy` (soft) con sus `authorize`.

**Rutas** (en `routes/web.php`, grupo `auth`):

```php
// Mantenedor (admin) — junto a plantillas:
//   mantenedor/categorias-gasto  -> mantenedor.categorias.{index,store,update,destroy}
// Gastos (admin gestiona, visor ve):
Route::resource('gastos', GastoController::class)
    ->only(['index', 'store', 'update', 'destroy']);
```

Luego `php artisan wayfinder:generate --with-form`.

> **Aceptación (Feature tests):**
> - `CategoriaGastoControllerTest`: admin CRUD; visor/conductor forbidden;
>   `nombre` único; **no borra categoría con gastos** (la desactiva).
> - `GastoControllerTest`: admin crea gasto con comprobante (assert media + DB);
>   requiere vehículo **o** sucursal; RUC inválido (≠ 11 díg) falla; visor ve
>   pero no crea; conductor forbidden; filtros (por categoría / sucursal)
>   devuelven lo correcto.

### 1.7 Frontend Fase 1

- **`types/fleet.ts`**: `CategoriaGasto`, `Gasto` (con `categoria`, `vehiculo`,
  `sucursal`, `comprobante_url`).
- **`pages/mantenedor/categorias-gasto.tsx`** + `categoria-gasto-form-dialog.tsx`
  + `eliminar-categoria-gasto-dialog.tsx` (clonar el de plantillas; toggle
  `activo`, badge de color).
- **`pages/gastos/index.tsx`**: tabla con filtros (categoría, sucursal,
  vehículo, rango de fechas, búsqueda), botón "Registrar gasto", miniatura del
  comprobante, montos. Paginación.
- **`components/gastos/`**: `gasto-form-dialog.tsx` (categoría select,
  **alcance**: toggle vehículo / sucursal con su select, monto, fecha,
  proveedor, RUC, n.º factura, fecha emisión, comprobante upload — `useForm` +
  `forceFormData`), `eliminar-gasto-dialog.tsx`.
- **Sidebar**: ítem "Gastos" (admin + visor) y "Categorías de gasto" dentro de
  admin (junto a "Plantillas de mant.").

> **Aceptación:** `tsc` / `eslint` / `prettier` / `build` limpios;
> registrar/editar/eliminar gasto y categorías funcionan; filtros operan.

---

## FASE 2 — Tablero de Costos consolidado

### 2.1 Service `App\Services\Costos\ResumenCostosService`

Une **3 fuentes** con filtros (rango fechas, sucursal, vehículo, categoría):

- `gastos` (por su categoría)
- `cargas_combustible.costo_total` → categoría virtual **"Combustible"**
- `mantenimientos.costo_total` → categoría virtual **"Mantenimiento"**

Métodos:

- `resumen(array $filtros): array` →
  `{ total, por_categoria: [{label, monto, color}], por_mes: [{mes, monto}], top_vehiculos: [{placa, monto}] }`.
- `costoPorKm(...)`: **opcional / aproximado** — km del período =
  `max(odómetro) − min(odómetro)` de las lecturas (combustible + mantenimiento)
  del vehículo en el rango, o GPS. **Marcar como estimado**; si no hay datos
  suficientes, devolver `null` (no inventar). _(Honestidad: igual que con
  rendimiento — si el dato no es confiable, no se muestra.)_

### 2.2 Controller + página

- **`CostosController@index`** (admin/visor): aplica filtros →
  `ResumenCostosService` → Inertia `costos/index`.
- **`pages/costos/index.tsx`**: filtros (rango fechas, sucursal, vehículo,
  categoría) + **donut por categoría** + **barras por mes** + **ranking de
  vehículos** + KPI **costo/km** (si disponible). Reutiliza `ChartCard`,
  `ChartCostosAnio`, `formatearSoles`.
- **Sidebar**: ítem "Costos" (admin + visor).

### 2.3 Integración con el TCO del vehículo

- Extender `PlanMantenimientoService::costosAnio()` (o moverlo a
  `ResumenCostosService`) para **sumar también `gastos`** del vehículo en el año
  → el donut del vehículo pasa a tener Combustible + Mantenimiento +
  (categorías de gasto).

> **Aceptación (tests):** `ResumenCostosServiceTest` (suma de 3 fuentes, filtro
> por sucursal/vehículo/rango, categorías virtuales combustible/mantenimiento,
> costo/km null sin datos); `CostosControllerTest` (permisos admin/visor,
> filtros, shape del payload).

---

## Comandos de verificación (antes de pedir review)

```bash
vendor/bin/pint <archivos tocados> --format agent
php artisan wayfinder:generate --with-form
npx tsc --noEmit
npx eslint <archivos tocados>
npm run build
php artisan test --compact     # toda la suite verde
```

## Checklist para el junior

- [ ] Migraciones corren/revierten (sin `migrate:fresh`).
- [ ] Modelos con media (comprobante PDF), softDeletes, relaciones tipadas.
- [ ] Seeder de categorías idempotente y registrado en `DatabaseSeeder`.
- [ ] Policies + Requests (RUC 11 díg, vehículo **o** sucursal obligatorio) +
      Controllers + rutas.
- [ ] Categoría con gastos NO se borra (se desactiva).
- [ ] Combustible/Mantenimiento se **agregan** (no se re-digitan) en Costos.
- [ ] Frontend: Gastos (CRUD + filtros + comprobante), Categorías (CRUD), Costos
      (dashboard); ítems de sidebar.
- [ ] Tests por rol + validación + agregación; suite verde; `tsc`/`build`
      limpios.

## Principios a respetar (transversales)

- **El tablero solo muestra lo que se sostiene con datos reales** — `costo/km` se
  omite si no hay km confiables.
- **Nunca tocar la BD de desarrollo con comandos destructivos**; los tests
  corren en sqlite `:memory:`.
