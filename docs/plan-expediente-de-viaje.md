# Plan del proyecto — Expediente de viaje y área de Abastecimiento

> Estado: **borrador para discutir** · 24-10-2026
> Se desarrolla y prueba en **trpatytest.aveja.cloud** (rama `test`) antes de
> pasar a producción.

## 1. Qué buscamos

Que **un viaje sea algo que todas las áreas compartan**. Hoy cada área ve su
pedazo por separado: Programación arma la salida, Mantenimiento registra lo
suyo, alguien entrega combustible y viáticos, Contabilidad factura la GR. Nadie
ve el viaje completo.

La meta es que cada salida tenga un **expediente único** —como un ticket— que
nace cuando se programa y se cierra cuando está liquidado y facturado, y que al
final responda en una sola pantalla:

- qué unidad, carreta y conductor salieron, a dónde y para quién;
- si mecánica liberó la unidad y con qué observaciones;
- cuánto combustible se cargó, dónde y a qué precio;
- cuánto dinero se entregó (viáticos/anticipo) y cuánto se gastó, con sustento;
- qué GR-transportista se emitieron y qué factura las cobra;
- qué fallas o incidentes reportó el conductor;
- **cuánto dejó el viaje**: flete − combustible − viáticos − peajes − costos.

Y que **los involucrados se enteren a tiempo** por WhatsApp, sin tener que
estar mirando el sistema.

## 2. Cómo lo hacen otros

Los TMS/ERP logísticos (McLeod, PCS, Vektor, Trimble, SAP TM) giran todos
alrededor de una **orden de viaje** con estados, y le cuelgan:

- **Anticipos y combustible** atados a la orden: lo entregado se descuenta solo
  en la liquidación del conductor; los errores salen de pasar datos a mano.
- **Liquidación (settlement)**: anticipos − gastos sustentados = saldo a favor
  o en contra; de ahí sale la utilidad por viaje y por unidad.
- **Inspección pre-salida (DVIR)**: checklist con foto; cada defecto abre una
  orden de trabajo y la unidad no se despacha hasta que mecánica la libera.
- **Gastos desde el celular**: el conductor fotografía el comprobante en el
  momento; si no, el sustento llega tarde o no llega.

En Perú, además, el combustible exige comprobante electrónico para sustentar el
gasto: el expediente debe guardar RUC y serie-número, no solo el monto.

## 3. De dónde partimos (ya existe y se reutiliza)

| Pieza | Hoy | Rol en el expediente |
|---|---|---|
| `Programacion` | Unidad, conductor, cliente, destino, flete, lugar de carga. Solo particular. | **Es el embrión del expediente.** |
| `Viaje` | Una fila por GR-transportista (importada o emitida a SUNAT). | Las GR del expediente. |
| `Factura` | Muchas a muchas con viajes, cobranza, detracción. | La facturación del expediente. |
| Mantenimiento | OT, preventivo por km, llantas, fajas, lecturas de km. | Insumo de la liberación. |
| Documentos | Vencimientos de vehículo (SOAT, RT…) y conductor (licencia…). | Insumo de la liberación. |
| `Novedad` | Taller, no habido, infracción… por tracto. | Destino de las fallas reportadas. |
| WhatsApp | Servicio Node + `AreaAviso` + `EnvioWhatsapp` (con ✓✓). Ya avisa cada salida. | Canal de alertas. |
| Cotizador | Costos de Paty, prorrateos por unidad. | El **presupuesto** del viaje. |
| Roles por área | `Permiso::AREAS`, panel de roles, gerencia ve todo. | Se suma el área Abastecimiento. |

Lo que **no** existe: el vínculo entre programación y GR, el registro de
combustible, anticipos y gastos, la liberación mecánica por salida, el acceso
del conductor y la liquidación.

## 4. Lo que dicen las GR (análisis del 24-10-2026)

Se reconstruyó el recorrido de cada tráiler con las **825 GR** vigentes del
23-07 al 12-10-2026 (62 tractos), agrupando los lugares en zonas: **Mina**
(San Rafael/Antauta), **Pisco** (Paracas), **Lima** (Callao, Huaral, Chilca,
Lurín…), **Juliaca** (y Puno), **Sur** (Arequipa, Tacna, Moquegua, Ilo) y
**Cusco**.

**Tramos más comunes** (GR):

| GR | Tramo | Carga |
|---|---|---|
| 272 | Mina → Pisco | concentrado |
| 144 | Lima → Juliaca | particular |
| 132 | Pisco → Lima (Callao) | metálico |
| 72 | Lima → Sur | particular |
| 50 | Lima → Mina | materiales |
| 38 | Pisco → Mina | escoria |
| 32 | Lima → Cusco | particular |

**Las GR se agrupan en 768 salidas**: casi siempre 1 GR por salida, pero hay
salidas con 2 a 9 GR (materiales de mina a varios destinos el mismo día).

**El circuito** se cierra cada vez que la unidad vuelve a cargar en mina. Se
encontraron **233 vueltas completas**, con duración mediana de **13 días**
(la mitad entre 11 y 15). Las más frecuentes:

| Vueltas | Días (mediana) | Recorrido |
|---|---|---|
| 41 | 12 | Mina →conc→ Pisco ⇢ Lima →part→ Juliaca ⇢ Mina |
| 32 | 13 | Mina →conc→ Pisco →met→ Callao →mat→ Mina |
| 30 | 14 | Mina →conc→ Pisco →met→ Callao →part→ Juliaca ⇢ Mina |
| 30 | 10 | Mina →conc→ Pisco →esc→ Mina |
| 11 | 15 | Mina →conc→ Pisco →met→ Callao →part→ Sur ⇢ Mina |
| 11 | 12 | Mina →conc→ Pisco ⇢ Lima →part→ Sur ⇢ Mina |
| 10 | 13 | Mina →conc→ Pisco ⇢ Lima →part→ Cusco ⇢ Mina |

Las 7 primeras cubren el **71 %** de las vueltas. El resto son variantes
(dos particulares seguidos, Sur → Juliaca, etc.).

**Hay tramos sin GR que también consumen combustible y viáticos**: el
recorrido vacío entre el destino de una carga y el origen de la siguiente.
Los más frecuentes son **Juliaca ⇢ Mina (96)**, **Pisco ⇢ Lima (95)**,
**Sur ⇢ Mina (39)** y **Cusco ⇢ Mina (15)**. Si el expediente solo
existiera donde hay GR, ese gasto quedaría sin dueño.

**Conclusiones para el diseño:**

1. Hacen falta **dos niveles**: el **tramo** (el ticket de cada salida, con o
   sin carga) y la **vuelta/circuito** que los encadena de mina a mina.
2. El tramo vacío es un tramo más, sin GR.
3. La vuelta se puede **programar con plantillas** (las 7 de arriba) y
   ajustarse sobre la marcha, porque en Pisco o en Callao se decide qué sigue.
4. Con este mismo algoritmo se pueden **armar las vueltas pasadas** desde las
   GR que ya existen, así el módulo arranca con historia y no vacío.

## 5. El concepto: Expediente de viaje

Un expediente = **una salida de una unidad** con su código (`OV-000123`). Puede
llevar una o varias GR (reemisión, carga en dos puntos, tramos del circuito).

### Ciclo de vida

```
Programado ─► Liberado ─► Abastecido ─► En ruta ─► Llegó ─► Por liquidar ─► Liquidado ─► Cerrado
                 │                                                              │
                 └─ Observado (mecánica)                       Facturado ◄──────┘
Anulado (en cualquier punto antes de En ruta)
```

- Cada cambio de estado deja un **evento** en la línea de tiempo (quién, cuándo,
  qué), y los eventos son los que disparan los avisos.
- **Al principio nada bloquea**: si una unidad sale sin liberar o sin
  abastecimiento registrado, el expediente avanza igual y queda marcado. Es la
  misma regla que ya usa el sistema (alertas graduadas, nunca impedir trabajar).
  Si después se quiere bloquear, es una regla que se prende.
- **Cerrado** = liquidado + todas sus GR facturadas (o marcadas no facturables).

### Qué hace cada área

| Área | Qué registra en el expediente |
|---|---|
| **Programación** (`operaciones`) | Crea el expediente: fecha/hora de salida, tracto, carreta, conductor, cliente, mina o particular, origen, destino, flete. Las GR se asocian solas al emitirse o importarse. |
| **Mantenimiento** | **Liberación**: checklist corto (luces, llantas, frenos, fugas, documentos a bordo…) con resultado *Apto / Observado / No apto* y foto. El sistema le muestra solo lo que ya sabe: OT abierta, preventivo vencido por km, llanta fuera de límite, SOAT/RT/licencia por vencer. |
| **Abastecimiento** *(nueva)* | **Combustible** (grifo, galones, precio, importe, km del tablero, comprobante), **anticipo/viáticos** (monto, medio: efectivo/Yape/transferencia, constancia), **peajes** si se pagan por adelantado. |
| **Conductor** | Desde el celular, sin usuario: gastos en ruta con foto, **fallas** (pasan a novedad u OT), *salí*, *llegué*, *descargué*. |
| **Contabilidad** (`contador`) | **Liquidación**: anticipos − gastos sustentados = saldo; aprueba. La factura ya se vincula hoy por GR. |
| **Gerencia** | Lee todo: resultado del viaje (presupuesto del cotizador vs real), rendimiento km/galón por unidad, viajes sin liquidar, salidas sin liberar. |

## 6. Acceso móvil y alertas

**Web responsive (PWA) del mismo Laravel**, no app nativa:

- Oficina, mecánicos y abastecimiento entran con su usuario desde el celular.
  Las pantallas del expediente se diseñan *mobile first*.
- **El conductor no tiene usuario** (el rol se eliminó a propósito). Recibe por
  WhatsApp un **link firmado y con vencimiento** a *su* expediente, donde solo
  puede reportar gastos, fallas y llegada. Sin contraseña que olvidar.
- Instalable en la pantalla de inicio (PWA) para quien la use a diario.

**Alertas por WhatsApp** sobre el servicio que ya existe, ampliando
`AreaAviso` para que cada área elija **a qué eventos** se suscribe:

| Evento | A quién |
|---|---|
| Salida programada | Mantenimiento, Abastecimiento, conductor (ya existe en parte) |
| Unidad sin liberar a X h de la salida | Mantenimiento, Programación |
| Unidad observada / no apta | Programación, Gerencia |
| Salida sin combustible o anticipo registrado | Abastecimiento |
| Falla reportada en ruta | Mantenimiento, Programación |
| Expediente sin liquidar a X días de la llegada | Contabilidad, conductor |
| GR del expediente sin facturar a X días | Contabilidad |

> Riesgo conocido: el servicio actual usa WhatsApp no oficial (riesgo de
> bloqueo del número). Si el volumen crece, evaluar la **WhatsApp Cloud API**
> oficial; el cambio queda encapsulado en `WhatsappServicio`.

## 7. Modelo de datos (borrador)

Nombres tentativos; se ajustan al diseñar cada fase.

| Tabla | Para qué | Campos clave |
|---|---|---|
| `programaciones` → **expediente** | Evolucionar la tabla actual en vez de crear otra (conserva datos y avisos). | + `codigo`, `tipo_servicio` (mina/particular), `estado`, `carreta_id`, `origen`, `salida_programada_at`, hitos `liberado_at`, `salida_at`, `llegada_at`, `liquidado_at`, `cerrado_at`, `cotizacion_id` |
| `viajes` | Ya existe. | + `expediente_id` nullable (N GR → 1 expediente) |
| `eventos_expediente` | Línea de tiempo y disparador de avisos. | `expediente_id`, `tipo`, `datos` json, `user_id`, `ocurrio_at` |
| `liberaciones` | Checklist mecánico por salida. | `expediente_id`, `vehiculo_id`, `resultado`, `items` json, `observacion`, `user_id`, fotos (media) |
| `cargas_combustible` | Cada abastecimiento. | `expediente_id` nullable, `vehiculo_id`, `fecha`, `grifo`, `ruc_grifo`, `comprobante`, `galones`, `precio_galon`, `importe`, `km`, `medio_pago`, foto |
| `anticipos` | Dinero entregado al conductor. | `expediente_id`, `conductor_id`, `monto`, `medio`, `entregado_at`, `user_id`, constancia |
| `gastos_viaje` | Lo que se gastó en ruta. | `expediente_id`, `categoria` (peaje, alimentación, hospedaje, estiba, cochera, reparación menor, otro), `monto`, `tipo_comprobante`, `ruc`, `numero`, `sustentado`, foto, `reportado_por` (conductor/usuario) |
| `liquidaciones` | El cierre de dinero del viaje. | `expediente_id`, `total_anticipos`, `total_gastos`, `no_sustentado`, `saldo`, `estado`, `aprobado_por`, `aprobado_at` |

Principios que se mantienen del sistema actual:

- **Placas y conductor copiados** en el expediente, no referenciados a una
  asignación: si mañana cambia la carreta, el viaje pasado sigue diciendo con
  cuál salió.
- **La GR es la fuente de verdad del despacho**: si lo programado y la GR
  difieren, se muestra la diferencia; no se corrige solo.
- Un plan que no termina en viaje (expediente anulado) es un dato válido.

## 8. Fases

Cada fase se puede usar sola, se prueba en trpatytest y recién después va a
producción.

| # | Fase | Entrega | Listo cuando… |
|---|---|---|---|
| 0 | **Ambiente de pruebas** ✅ | trpatytest con copia de prod, deploy por rama `test`, WhatsApp/SOL neutralizados. | Hecho el 23-10-2026. |
| 1a | **Tramos y vueltas desde las GR** | Tablas de vuelta y tramo con código de referencia; reconstrucción del historial desde las GR (incluidos los tramos vacíos); pantalla de la unidad con sus vueltas y la ficha de cada tramo (GR + factura). | Las vueltas de agosto–octubre de 3 unidades coinciden con lo que recuerda Programación. |
| 1b | **Programar la vuelta** | Programación crea el próximo tramo (ticket) o la vuelta completa desde una plantilla; las GR emitidas o importadas llenan el tramo programado; lo programado y lo real se comparan. Se reemplaza la pantalla de Programación actual sin perder los avisos. | Una semana de salidas se programa y cada GR cae en su ticket sin tocarla. |
| 2 | **Abastecimiento** | Área, rol y permisos; registro de combustible, anticipos y peajes contra el expediente; bandeja «salidas del día por abastecer». | Abastecimiento registra todo lo de un día desde el celular en menos de lo que tarda hoy. |
| 3 | **Liberación mecánica** | Checklist por salida usando OT/preventivo/llantas/documentos; alerta de salida sin liberar. | Ninguna salida de la semana queda sin resultado de mecánica. |
| 4 | **Conductor en ruta** | Link firmado por WhatsApp: gastos con foto, fallas (→ novedad/OT), llegada/descarga. | Los conductores de una ruta piloto reportan sin ayuda. |
| 5 | **Liquidación y resultado** | Liquidación por expediente, saldo del conductor, utilidad real vs cotizada, rendimiento km/galón. | Contabilidad cierra los viajes del mes desde el sistema. |
| 6 | **Alertas por evento** | Suscripciones por área y evento; recordatorios de pendientes. | Cada área recibe solo lo que le toca. |

Las alertas de la fase 6 se pueden ir prendiendo desde la fase 1 con lo que ya
existe; la fase 6 es ordenarlas y hacerlas configurables.

## 9. Cómo trabajamos

1. Se desarrolla en local (copia de prod) en una rama de trabajo.
2. `push` a **`test`** → se despliega solo en **trpatytest.aveja.cloud**
   (franja amarilla «AMBIENTE DE PRUEBAS»). Ahí se prueba con datos reales
   copiados; WhatsApp y SUNAT están desconectados.
3. Cuando el área lo aprueba, merge a **`main`** → producción.
4. Cada fase con tests; nada de `migrate:fresh` ni borrados sin respaldo.
5. Para refrescar los datos de test: copia de la base de prod hacia
   `transpaty_test` (los PDF no se copian).

## 10. Decisiones pendientes

Antes de diseñar la fase 1:

1. **¿Un expediente es un tramo o el circuito completo?** Ej. Juliaca → mina →
   Pisco → Callao → Lima → Juliaca con varias GR. Define qué se liquida junto y
   cómo se mide la utilidad.
2. **¿Los viajes a mina los programa Paty o llegan armados de Minsur?** Hoy
   Programación es solo particular; la mina hay que sumarla sin duplicar trabajo.
3. **¿Cómo se paga el combustible?** Vales/crédito con grifos, tarjeta, o
   efectivo del anticipo. Cambia quién registra y con qué comprobante.
4. **¿Todos los conductores tienen smartphone con WhatsApp?** Decide si el link
   del conductor es la vía principal o un complemento.

Antes de las fases 2–5:

5. ¿Los viáticos son un **monto fijo por ruta** o se entrega según el viaje?
   (Si es fijo, se configura una tabla de viáticos por ruta.)
6. ¿Quién hace la liberación: un mecánico con usuario, o el jefe de taller por
   todos?
7. ¿El saldo del conductor se descuenta de planilla o se devuelve en efectivo?
   (RRHH entra solo si es planilla.)
8. ¿Se quiere que algún día una unidad *No apta* **bloquee** la salida, o
   siempre solo avisar?

## 11. Fuera de alcance (por ahora)

- GPS en vivo y ETA: no hay API del proveedor de GPS. *Llegó* lo marca el
  conductor o la oficina. (Hay credenciales de Tracksolid en el `.env`, pero no
  se usan; evaluar aparte.)
- Contabilidad formal / asientos contables: la liquidación es operativa, no
  reemplaza al contador.
- App nativa en tiendas.
- Inventario de repuestos y compras.

## 12. Indicadores que debería dar al final

- Utilidad por viaje, por unidad, por cliente y por ruta (real vs cotizado).
- Costo de combustible por km y **rendimiento km/galón** por unidad (alerta si
  cae: fuga, mal manejo o robo).
- Viáticos y gastos por viaje; % sustentado.
- Tiempo de programación → salida; salidas sin liberar; salidas sin abastecer.
- Expedientes sin liquidar y GR sin facturar, con antigüedad.
- Meta mensual de concentrado (ya existe en el tablero) cruzada con la utilidad.
