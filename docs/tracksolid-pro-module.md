# Prompt de implementación — Módulo Tracksolid Pro (GPS + Dashcam)

> Pega este prompt para implementar el módulo. Está adaptado a este proyecto y a
> que los equipos son **dashcams JIMI JC181** (soportan video en vivo).

## Contexto del proyecto (ya existe, NO rehacer)

- **Stack**: Laravel 13 + Inertia v3 + React 19 + Tailwind v4. BD PostgreSQL. Roles spatie (`admin` gestiona, `visor`/`conductor` solo lectura). Toasts vía evento `flash` (`->with('toast', ['type','message'])`). `auth.roles` compartido en props. Wayfinder para rutas tipadas. Tests en Pest; calidad con Pint + Larastan (PHPStan) `--memory-limit=1G`.
- **Dominio**: `Sucursal → Conductor → Vehiculo`. `Vehiculo` (SoftDeletes) ya tiene fotos (`VehiculoFoto`) y documentos (`VehiculoDocumento`) con spatie/medialibrary. La pantalla `resources/js/pages/vehiculos/show.tsx` ya tiene **placeholders "Próximamente"** para: **Resumen de uso** (km/combustible), **Mantenimientos**, **Historial de actividad reciente**, y se mencionó un botón **"Ver en mapa"**.
- **Cliente Tracksolid YA construido y FUNCIONANDO**: `app/Services/Tracksolid/TracksolidClient.php` (singleton, firma automática, token cacheado ~2h con auto-refresh, retry/timeouts) + `TracksolidException`. Config en `config/services.php` → `services.tracksolid` desde `.env` (`TRACKSOLID_*`). Comando `php artisan tracksolid:devices`. Métodos actuales: `listDevices(?account)`, `deviceDetail($imei)`, `latestLocations($imeis)`.
- **Credenciales (lecciones aprendidas, NO repetir errores)**: región **US** (`https://us-open.tracksolidpro.com/route/rest`). `user_id` = **nombre de cuenta** (`SelcosiXportSAC`), NO el número "UserID". El `user_pwd_md5` lo da el proveedor y va **tal cual** en `TRACKSOLID_PASSWORD_MD5`. Cuidar el rate limit del token (`code 1006`): cachear, nunca pedir token por request.
- **Equipos reales**: 2× **JIMI JC181** (dashcam) → habilitar módulo de **video en vivo / fotos remotas**.
- **Firma JIMI**: `strtoupper(md5(appSecret + params ordenados por clave (clave.valor, sin el campo sign) + appSecret))`. Comunes: method, timestamp (`gmdate('Y-m-d H:i:s')` UTC), app_key, sign_method=md5, v=1.0, format=json. Las llamadas con token agregan `access_token`.

## Objetivo

Construir el módulo **Tracksolid Pro** que conecte la flota con el GPS/dashcam: vincular dispositivos a vehículos, sincronizar datos, ubicación en mapa, kilometraje/uso, video en vivo (dashcam) e historial de actividad — llenando los placeholders ya maquetados y respetando el diseño existente (tarjetas, verde de marca `bg-emerald-800`, `EstadoBadge`, etc.).

## Endpoints disponibles (JIMI Open API)

- Dispositivos: `jimi.user.device.list`, `jimi.track.device.detail`, `jimi.open.device.update` (incluye `mileage`, `oil`, estado), `jimi.device.group.list`.
- Ubicación: `jimi.device.location.get` (uno/varios), `jimi.user.device.location.list` (todos), `jimi.device.location.URL.share` (link de mapa).
- Historial/uso: `jimi.device.track.list` (ruta, ≤7 días, dentro de 3 meses), `jimi.device.track.mileage` (km por rango), `jimi.open.platform.report.parking`.
- Comandos: `jimi.open.instruction.list/send/result`.
- Video (dashcam JC181): `jimi.device.live.page.url`, `jimi.device.media.live.stream`, `jimi.device.meida.cmd.send` (foto/video on-demand), `jimi.device.media.history.list.cmd` + `...list.get` + `...history.stream`, `jimi.device.media.close.stream`, `jimi.device.jimi.media.URL`.
- Geocercas: `jimi.open.device.fence.*`.

## Trabajo a realizar (por fases)

### Fase 1 — Vínculo dispositivo ↔ vehículo (base)
- Migración: agregar `imei` (string, nullable, unique) a `vehiculos` (+ índice). Modelo: fillable/cast y helper `tieneGps()`.
- Extender `TracksolidClient` si falta algún método; añadir DTOs ligeros (`TracksolidDevice`) opcional.
- Pantalla admin **"Dispositivos GPS"** (`/integraciones/tracksolid` o dentro de Vehículos): lista `listDevices()`, muestra IMEI/modelo/placa/estado y permite **vincular** a un vehículo existente (match sugerido por placa) o **importar** como vehículo nuevo (mapear vehicleNumber→placa, VIN/carFrame→numero_serie, vehicleBrand→marca, vehicleModels→modelo, engineNumber→numero_motor, currentMileage→kilometraje).
- Acción de **sincronizar datos del dispositivo** (`deviceDetail`) hacia el vehículo (botón "Sincronizar con GPS" en el show + comando/Job programado). Respetar rate limit (token cacheado, throttle).
- Tests: `Http::fake()` para el cliente; feature tests de vincular/importar/sincronizar; policy admin.

### Fase 2 — Ubicación + mapa ("Ver en mapa")
- `latestLocations([imei])` para la última posición; mostrar tarjeta de ubicación + botón **"Ver en mapa"** en `show.tsx` (usar `location.URL.share` para link nativo, o mapa embebido).
- Página **Mapa de flota**: todas las posiciones (`user.device.location.list`) con marcadores. Definir proveedor de mapa (ver decisiones abiertas).
- Posición "en vivo": `usePoll` (Inertia v3) en un intervalo razonable (p. ej. 30–60s) reusando caché corto en backend para no exceder el API.

### Fase 3 — Resumen de uso (km / combustible)
- `track.mileage` por rango (este mes / rango elegido) → llenar el placeholder **Resumen de uso** (km recorrido, gráfico de kilometraje por mes). Usar `fuel_100km` del detalle para rendimiento estimado.
- Opción de **sincronizar odómetro** del GPS (`currentMileage`) hacia `vehiculo.kilometraje`, o empujar hacia el equipo con `open.device.update`.

### Fase 4 — Video en vivo / cámara (dashcam JC181)
- Sección **"Cámara"** en el show del vehículo (solo si `imei` y el modelo es dashcam): iniciar live (`media.live.stream` / `live.page.url`), tomar **foto/clip on-demand** (`meida.cmd.send`), listar y reproducir **video histórico** (`media.history.list.*`, `history.stream`), y cerrar stream (`media.close.stream`).
- Definir reproductor (HLS vs iframe de la page URL — ver decisiones).
- Manejar estados: cámara offline, sin señal, permisos (solo admin u operador).

### Fase 5 — Historial de actividad + alarmas + geocercas (opcional)
- Alimentar el placeholder **Historial de actividad reciente** con eventos (sincronizaciones, cambios de ubicación relevantes, cargas futuras, alarmas).
- Geocercas por vehículo (`fence.create/delete`) si se requiere.

## Requisitos transversales (buenas prácticas)

- Reusar y extender `TracksolidClient` (no crear otro cliente). Mantener token cacheado y firma centralizada. Manejar `TracksolidException` y mapear códigos (1001 AppKey/firma, 1002 user, 1006 rate limit → backoff).
- Sincronizaciones periódicas con **Jobs en cola** + `schedule` (`withoutOverlapping`, `onOneServer`), respetando límites de frecuencia del API.
- Cachear respuestas volátiles (ubicación) por pocos segundos para no exceder el API ante múltiples usuarios.
- Autorización por Policy: `admin` gestiona/sincroniza/maneja cámara; `visor`/`conductor` solo lectura de lo permitido.
- Frontend consistente con el diseño actual (tarjetas, `bg-emerald-800`, `EstadoBadge`, toasts, breadcrumbs vía `setLayoutProps`). Tipos en `resources/js/types/fleet.ts`.
- **Calidad obligatoria**: tests Pest (incluyendo `Http::fake()` para cada método del cliente y feature tests de pantallas/acciones), `vendor/bin/pint --dirty`, `phpstan --memory-limit=1G` y `npm run build` deben pasar.
- No commitear secretos; todo por `.env`/config.

## Decisiones a confirmar antes de empezar

1. **Proveedor de mapa**: Leaflet + OpenStreetMap (gratis, sin API key) vs Google Maps (requiere key/billing). Recomendado para empezar: Leaflet + OSM.
2. **Video**: usar la *page URL* embebida (`live.page.url`, rápido) vs integrar un reproductor HLS con `media.live.stream` (más control). Recomendado: empezar con page URL embebida y evaluar HLS.
3. **Relación dispositivo–vehículo**: 1 dispositivo por vehículo (vía `imei` en `vehiculos`) vs varias cámaras por vehículo (tabla aparte `vehiculo_dispositivos`). Por ahora: 1:1 con `imei`.
4. **Historial de ubicación**: solo consultar al API on-demand vs persistir posiciones localmente para histórico/analítica. Empezar on-demand.
5. **Frecuencia de polling/sync** y si la ubicación en vivo se cachea en backend.

## Orden sugerido de entrega
Fase 1 → 2 → 3 → 4 → 5. Cada fase: backend + tests + frontend + verificación (pint/phpstan/build/test) antes de la siguiente.
