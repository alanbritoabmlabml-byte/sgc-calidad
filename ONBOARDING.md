# Onboarding para desarrollo — SGC Calidad

Sistema de control de calidad por lotes de Plásticos Carmen. Este documento es
para quien va a tomar el código y terminar los ajustes.

Leé también [README.md](README.md) (dominio y decisiones) y
[DESPLIEGUE.md](DESPLIEGUE.md) (producción en Windows Server con IIS).

---

## 1. Arranque

```bash
git clone git@github.com:alanbritoabmlabml-byte/sgc-calidad.git
```

```bash
composer setup
```

Eso instala dependencias, crea `.env`, genera la clave, corre migraciones y
**siembra el sector Rafia completo** (4 procesos, 13 productos, 24 máquinas y las
4 plantillas de ensayo con sus tolerancias reales), y compila los assets.

Datos de ejemplo con lecturas reales de la planilla del 13-08:

```bash
composer demo
```

```bash
php artisan serve
```

| Usuario | Contraseña | Rol |
|---|---|---|
| `admin@plasticoscarmen.com` | `PC-Admin-2026` | Administrador |
| `calidad@plasticoscarmen.com` | `PC-Calidad-2026` | Control de Calidad |
| `gerencia@plasticoscarmen.com` | `PC-Gerencia-2026` | Gerencia |

Verificado: clon limpio desde GitHub → `composer setup` → **83 pruebas en verde**.

### Entorno en Windows

- **PHP 8.3+.** Para desarrollo sirve cualquier build; para IIS en producción hace
  falta la **Non-Thread-Safe (NTS)**. No copies la de desarrollo al servidor.
- Hay que crear el `php.ini` a mano (los ZIP de php.net no lo traen) y habilitar:
  `pdo_sqlite`, `sqlite3`, `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`,
  `curl`, `zip`, `intl`, `gd`.
- **No pongas el proyecto en una carpeta de OneDrive.** OneDrive marca los
  directorios como `ReadOnly` y `is_writable()` de PHP devuelve `false` aunque la
  escritura funcione: Laravel aborta con *"bootstrap/cache must be present and
  writable"*. Además sincroniza `vendor/` y el archivo SQLite mientras se
  escribe. Este proyecto vive en `C:\dev\sgc-calidad` por esa razón.

---

## 2. Qué hay que entender antes de tocar nada

El sistema es **modular por datos**: sectores, procesos, plantillas de ensayo y
parámetros se cargan como registros, no como código. Agregar un sector nuevo no
debería requerir programar. Cinco conceptos sostienen eso.

### 2.1 El motor de especificaciones — `app/Models/TestParameter.php`

Decide si una medición es conforme. Un parámetro tiene un **modo** de
especificación (`spec_modo`) y, según el modo, límites, objetivo y tolerancia.

```
rango          min ≤ v ≤ max
objetivo_tol   |v − objetivo| ≤ tolerancia        gramaje ± 2, elongación 23 ± 5
objetivo_pct   |v − objetivo| ≤ objetivo · pct    denier ± 3,5 %, peso ± 3 %
minimo         v ≥ min                            tensión mínimo 60 kgf
maximo         v ≤ max
opciones       v ∈ opciones.conformes             corte: B sí, M no
libre          sin evaluación
```

Lo importante: **el objetivo puede venir del producto del lote**, no de la
plantilla. `spec_desde_producto = 'gramaje_nominal'` hace que `Bl 65x104/66` se
evalúe contra 66 ± 2 y `V.Cl 56x96/64` contra 64 ± 2, con **un solo parámetro
configurado**. Eso existe porque en el código de producto `Bl 65x104/66` el
`66` **es** el gramaje nominal en g/m² — es lo que Calidad ya hace a mano cuando
anota *"92 Grs. de acuerdo al cálculo del código"*.

Métodos clave: `limites()`, `evaluar()`, `specTexto()`.

### 2.2 La puerta entre procesos — `Lot::puedeInspeccionar()`

Un lote no se puede inspeccionar en un proceso si algún proceso anterior marcado
como `bloquea_siguiente` no tiene inspección aprobada. Revisa **todos** los
anteriores bloqueantes, no solo el inmediato: Impresión es opcional y no debe
habilitar un salto por encima de Tejido.

En Rafia solo **Tejido** y **Corte y Costura** son puertas. Extrusión se controla
por bobina en su propio registro, e Impresión no aplica a todos los productos.

### 2.3 La herencia por lote de origen — `Lot::inspeccionEfectivaDe()`

Un lote de bolsas **no repite** el control de Tejido: lo hereda del rollo que
consume, vía `source_lot_id`. Ese vínculo existe porque en la planilla de Corte y
Costura el campo `Nº LOTE` es en realidad el **N° de tarjeta del rollo de
Tejido**. Si el rollo de origen fue rechazado, el lote de bolsas queda bloqueado.

`recalcularEstado()` usa la misma resolución, así que un lote de bolsas se libera
con Tejido heredado + su propio Corte y Costura aprobado.

### 2.4 La plantilla queda congelada por inspección

`inspections.test_template_id` apunta a la **revisión** con la que se llenó, no a
la vigente. Una boleta emitida hace seis meses sigue mostrando la especificación
de ese día. Para cambiar una tolerancia se usa **Crear revisión nueva**
(`PlantillaController::duplicar`), no la edición directa.

Si tocás esto, no rompas esa propiedad: es lo que hace defendible un certificado
de calidad frente a un cliente.

### 2.5 Emisión y token público

Una inspección se guarda primero y se **emite** después (`published_at`). Hasta
que se emite, la ruta pública devuelve 404: así una etiqueta impresa por
adelantado no expone datos en borrador. Una vez emitida no se puede editar sin
anular la emisión.

El token (`public_token`) es aleatorio de 24 caracteres, **no correlativo**, para
que no se pueda enumerar. La ruta `/c/{token}` es corta a propósito: menos
densidad en el QR, mejor lectura desde una etiqueta chica.

**El certificado está excluido del modo mantenimiento** (`bootstrap/app.php`): un
cliente puede estar escaneando un QR justo durante un despliegue.

---

## 3. Mapa del código

```
app/
  Models/
    Sector, Process, Product, Machine        catálogos
    TestTemplate, TestParameter              motor de especificaciones
    Lot, Inspection, Measurement             trazabilidad y mediciones
  Http/Controllers/
    LotController, InspectionController      operación diaria
    CertificadoController                    vista pública del QR
    EtiquetaController                       hojas de etiquetas
    DashboardController                      tablero de Calidad y avisos
    GerenciaController                       tablero gerencial
    Admin/  Plantilla, Parametro, Producto, Maquina, Sector, Usuario
  Http/Middleware/
    Permiso.php     autoriza por permiso ('permiso:inspecciones.emitir')
  Rules/
    NombrePersona, HoraNoFutura
  Support/
    Permisos.php        catálogo de permisos por módulo y presets por rol
    Avisos.php          boletas sin emitir, lotes bloqueados, fichas incompletas
    Qr.php              QR en SVG (vectorial, sin depender de GD)
    UrlPublica.php      valida que APP_URL sirva para imprimir QR
    FormatoEtiqueta.php formatos Zebra y personalizable

resources/views/
  components/logo.blade.php        marca; usa public/img/logo-pc.png si existe
  components/modal-alta-rapida     alta de producto o sector sin cambiar pantalla
  inspecciones/_form.blade.php     grilla de muestras con evaluación en vivo
  inspecciones/_boleta.blade.php   boleta media carta, compartida con el certificado
  publico/certificado.blade.php    lo que ve el cliente
  etiquetas/hoja.blade.php         etiquetas QR, térmica Zebra y A4
  gerencia.blade.php               tablero gerencial
  avisos.blade.php                 pendientes del sistema
  admin/plantillas/                configuración de ensayos

resources/js/app.js   componente Alpine grillaMediciones
database/seeders/
  RafiaSeeder.php   sector Rafia relevado de las planillas
  DemoSeeder.php    lecturas reales del 13-08
```

### La grilla de mediciones

`resources/js/app.js` replica en el navegador la lógica de
`TestParameter::evaluar()` para dar aviso inmediato mientras el inspector
escribe: pinta en rojo lo que sale de especificación, calcula el promedio y
muestra el estado sugerido en vivo.

**El servidor vuelve a evaluar todo al guardar.** La copia en JavaScript es solo
para la experiencia de carga; si cambiás una regla de especificación, hay que
cambiarla en los dos lados.

---

## 4. Agregar un sector nuevo (sin código)

1. `sectors`: código, nombre y prefijo de lote (por ejemplo `EXP` → `EXP-26-00001`).
2. `processes`: uno por etapa, con `orden`, `bloquea_siguiente` y `etiquetas`
   (los rótulos de la boleta impresa de ese proceso).
3. `products` y `machines` del sector.
4. Una `test_template` activa por proceso, con sus `test_parameters`.

Los pasos 3 y 4 ya se hacen por pantalla en *Configuración*. **Sectores y
procesos todavía no tienen CRUD** — ver la sección 5.

Lo más rápido para un sector nuevo hoy es copiar `RafiaSeeder` como modelo.

---

## 5. Ajustes pendientes

Ordenados por lo que bloquea la puesta en producción.

### Bloqueantes

1. **Confirmar los pesos nominales por producto.** Solo tres salen de lo que
   Calidad escribió en las observaciones de la planilla (`Bl 65x104/66` → 92 g,
   `Az 56x102/66` → 79 g, `Cl 80x120/66` → 131 g). El resto es una estimación
   geométrica (tejido tubular + 4 cm de dobladillo). **De ese valor depende el
   veredicto del peso en Corte y Costura**, así que un nominal mal cargado da
   veredictos mal. Se corrige en *Configuración → Productos*, sin código.

2. **Definir `APP_URL` antes de imprimir la primera etiqueta.** Queda impresa
   dentro de cada QR; cambiarla después obliga a reimprimir todo lo ya pegado.
   `App\Support\UrlPublica` avisa si apunta al equipo local o a la red interna.

3. **Desplegar en el servidor** siguiendo `DESPLIEGUE.md`, y **cambiar las
   contraseñas** de los dos usuarios sembrados.

### Especificaciones a confirmar con Calidad

4. **Tolerancia del peso de bolsa: ± 3 %.** Reproduce los veredictos que Calidad
   registró en la planilla, pero no está documentada en ningún formato oficial.
5. **Rango de costura: 2 a 3 cm.** Tomado del histórico observado (2,4 a 3,0).
6. **Densidad de cintas (trama y urdimbre).** El formulario COD.02 tiene el campo
   pero la planilla no declara tolerancia: quedó `libre`, sin evaluación.
7. **Denier por producto.** Extrusión quedó en 750 ± 3,5 % como valor por
   defecto. Si cada producto tiene el suyo, se carga en `products.denier_nominal`
   y el sistema lo toma automáticamente.
8. **Criterio de rechazo.** Ningún parámetro está marcado como crítico, porque en
   las planillas un desvío se registra como `P.OBS` y el rechazo no aparece
   nunca. Si Calidad define que alguna característica rechaza el lote por sí
   sola, se marca como crítica y el sistema sugiere `RECHAZADO`.

### Huecos conocidos del sistema

9. **Los procesos no tienen CRUD.** Los sectores sí (`SectorController`), pero
   los procesos de cada sector se cargan por seeder. Es el hueco más relevante si
   se van a sumar Expandido, Inyección u otros sectores: hoy hay que copiar
   `RafiaSeeder` como modelo. Los rótulos de la boleta viven en
   `processes.etiquetas` (JSON) y también se cargan ahí.
10. **Al editar una inspección, las mediciones se reemplazan**
    (`InspectionController::update` borra e inserta). Es seguro porque solo se
    puede editar antes de emitir, pero **no queda historial de qué se cambió**.
    Si Calidad necesita auditoría de mediciones, hay que versionarlas. Las
    plantillas sí registran autoría (`created_by`, `activada_por`, `activada_at`).
11. **No hay exportación a Excel.** Calidad viene de planillas y es probable que
    la pida para reportes mensuales.
12. **El PDF se genera imprimiendo desde el navegador** (CSS `@media print`), no
    con una librería. Funciona bien y en cualquier dispositivo. Si se necesita
    generar el PDF del lado del servidor —para adjuntarlo a un correo, por
    ejemplo— hay que sumar algo tipo `dompdf`.
13. **Los avisos no se envían.** `App\Support\Avisos` se calcula y se muestra en
    la campana y en `/avisos`, pero nadie recibe un correo. Si hace falta, el
    lugar natural es un comando programado que consulte esa misma clase.
14. **El logo es una reconstrucción**, no el archivo oficial. Ver
    `resources/views/components/logo.blade.php`: si se copia el oficial a
    `public/img/logo-pc.svg`, el componente lo usa en todas las pantallas.

### Piezas nuevas que conviene conocer

| Archivo | Qué resuelve |
|---|---|
| `app/Support/Permisos.php` | Catálogo de permisos por módulo y presets por rol |
| `app/Http/Middleware/Permiso.php` | Autoriza por permiso; falla ruidosamente ante un permiso inexistente |
| `app/Support/Avisos.php` | Lo que quedó a medio camino (boletas sin emitir, etc.) |
| `app/Support/FormatoEtiqueta.php` | Formatos de etiqueta Zebra y personalizable |
| `app/Support/UrlPublica.php` | Valida que `APP_URL` sirva para imprimir QR |
| `app/Rules/NombrePersona.php` | Nombres sin números, con ñ y tildes |
| `app/Rules/HoraNoFutura.php` | La hora de hoy no puede ser futura |
| `Inspection::datosFaltantes()` | Qué falta para poder emitir la boleta |

**Cuidado con el orden de rutas:** `/plantillas/nueva` tiene que declararse antes
de `/plantillas/{plantilla}`. Ya pasó una vez: "nueva" se tomaba como
identificador de plantilla y la ruta devolvía 404.

**Cuidado con Blade:** una directiva pegada a una palabra no se reconoce.
`más@endif` deja el `@if` sin cerrar y la vista revienta con "unexpected end of
file". Siempre un espacio antes de `@endif`.

### Un hallazgo que conviene resolver con Calidad

Al cargar las lecturas reales del 13-08, el sistema reproduce **6 de los 7**
veredictos que anotó Calidad. La diferencia está en `Bl 65x104/66` del telar T-2:
la **elongación de trama fue 29,2 %** contra un límite superior de 28 % (23 ± 5),
y quedó registrada como `P.C`. El desvío se pasó por alto en la revisión manual.
Si 29,2 % es aceptable, hay que corregir la tolerancia; si no, el sistema ya lo
detecta. Está cubierto en `EspecificacionTest`.

---

## 6. Convenciones

- **El dominio se escribe en español** (`Lot::puedeInspeccionar`, `estadoSugerido`,
  `TestParameter::evaluar`), porque los términos son los de Calidad. Lo que viene
  de Laravel queda en inglés. No mezclar dentro de un mismo método.
- **Los comentarios explican por qué, no qué.** Si un valor sale de una planilla
  o de una decisión de Calidad, el comentario lo dice.
- **Las pruebas usan lecturas reales de las planillas**, con el veredicto que
  anotó Calidad como valor esperado. No inventes datos de prueba para el motor de
  especificaciones: es lo que hace que las pruebas signifiquen algo.
- Los textos visibles y los comentarios van **sin tildes** para evitar problemas
  de codificación entre Windows, Git y la consola. La documentación sí las lleva.
- PHPUnit 12: los proveedores de datos van con el atributo
  `#[DataProvider('nombre')]`. La anotación `@dataProvider` en docblock **ya no
  funciona**.
- Tailwind 4: `@apply` solo acepta utilidades reales, **no clases propias**.
  `.btn-primario { @apply btn ... }` falla al compilar; la base se declara una vez
  para todas las variantes (ver `resources/css/app.css`).

```bash
composer exec pint
```

```bash
php artisan test
```

---

## 7. Comandos útiles

```bash
php artisan migrate:fresh --seed && php artisan db:seed --class=DemoSeeder
```

```bash
npm run dev
```

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

Lo último expone el servidor a la red local, para escanear un QR con el celular.
Requiere que `APP_URL` apunte a la IP de la máquina y una regla de firewall para
el puerto 8000.

Comparar los veredictos del sistema contra los de la planilla:

```bash
php artisan tinker --execute='foreach (App\Models\Inspection::with("lot.product")->get() as $i) { printf("%-11s %-16s planilla=%-6s sistema=%-6s\n", $i->code, $i->lot->product->code, $i->estado, $i->estadoSugerido()); }'
```

---

## 8. Pruebas

```bash
php artisan test
```

**83 pruebas, 279 aserciones.**

| Archivo | Cubre |
|---|---|
| `EspecificacionTest` | El motor de especificaciones contra lecturas reales del 13-08 y 14-08 |
| `FlujoInspeccionTest` | Lote → inspección → emisión → certificado, bloqueo entre procesos, herencia por lote de origen, aislamiento de la observación interna, etiquetas, permisos por rol |
| `UrlPublicaTest` | Detección de `APP_URL` no alcanzable desde internet |
| `AccesoTest` | Autenticación y rutas protegidas |

Si vas a cambiar el motor de especificaciones o las reglas de bloqueo,
**corré las pruebas antes y después**: están escritas para que un cambio de
criterio se note.
