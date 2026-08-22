# SGC Calidad — Plásticos Carmen S.R.L.

Sistema de control de calidad por lotes con emisión de boletas de inspección,
etiquetas QR y certificado de calidad público para clientes.

Primera versión enfocada en el **sector Rafia**. La estructura es modular: los
sectores, procesos, plantillas de ensayo y parámetros son **datos**, no código,
así que agregar Expandido, Inyección u otro sector no requiere programar.

- **Stack:** Laravel 13 · PHP 8.4 · Blade + Tailwind CSS 4 + Alpine.js · SQLite (dev) / MySQL (producción)
- **Responsive:** computadora, tablet y celular
- **QR:** SVG vectorial (`bacon/bacon-qr-code`), sin depender de extensiones de imagen


> **¿Vas a desarrollar sobre esto?** Empezá por **[ONBOARDING.md](ONBOARDING.md)**.

---

## 1. Puesta en marcha

```bash
php artisan migrate:fresh --seed
```

Eso crea la base y carga el sector Rafia completo: 4 procesos, 24 máquinas,
13 productos y las 4 plantillas de ensayo con las tolerancias reales relevadas
de las planillas de Calidad.

Datos de demostración con lecturas reales del 13-08 (opcional, recomendado para
recorrer el sistema):

```bash
php artisan db:seed --class=DemoSeeder
```

Compilar los assets y levantar el servidor:

```bash
npm install && npm run build
```

```bash
php artisan serve
```

### Usuarios iniciales

| Correo | Contraseña | Rol |
|---|---|---|
| `admin@plasticoscarmen.com` | `PC-Admin-2026` | Administrador |
| `calidad@plasticoscarmen.com` | `PC-Calidad-2026` | Control de Calidad |
| `gerencia@plasticoscarmen.com` | `PC-Gerencia-2026` | Gerencia |

**Cambiá estas contraseñas antes de poner el sistema en producción.**

---

## 2. Antes de imprimir la primera etiqueta

El código QR codifica la URL definitiva del sistema, tomada de `APP_URL`.
**Esa dirección queda impresa para siempre en cada etiqueta.**

En `.env`:

```
APP_URL=https://calidad.plasticoscarmen.com
```

```bash
php artisan config:clear
```

Mientras `APP_URL` apunte a `localhost`, la pantalla de etiquetas muestra una
advertencia en rojo y no conviene imprimir: esos QR no se pueden abrir desde
otra computadora ni desde el celular de un cliente.

---

## 3. Cómo funciona

### Cadena de calidad de Rafia

| Orden | Proceso | Boleta | ¿Bloquea el avance? |
|---|---|---|---|
| 1 | Extrusión | — | No — se controla por bobina y máquina, en su propio registro |
| 2 | **Tejido** | COD.02 | **Sí** |
| 3 | Impresión | — | No — no todos los productos se imprimen |
| 4 | **Corte y Costura** | COD.03 | **Sí** |

Corte y Costura no se habilita si Tejido no tiene una inspección aprobada. Si
Tejido sale `RECHAZADO`, el lote queda **bloqueado** y el sistema no deja
registrar el proceso siguiente.

### Trazabilidad entre lotes

En la planilla de Corte y Costura, el campo `Nº LOTE` es en realidad el
**N° de tarjeta del rollo de Tejido** que alimentó a esa producción de bolsas.
El sistema formaliza ese vínculo con el campo **lote de origen**: un lote de
bolsas hereda la aprobación de Tejido de su rollo, no la repite. Si el rollo
de origen fue rechazado, el lote de bolsas queda bloqueado también.

### Estados

**Inspección** — los mismos que ya usa Calidad, más el rechazo explícito:

- `P.C` — pasa conforme
- `P.OBS` — pasa con observación
- `RECHAZADO` — no conforme (bloquea el avance)
- `PENDIENTE` — cargada pero sin cerrar; no se puede emitir

**Lote:** `abierto` · `liberado` · `bloqueado` · `cerrado`

### Estado sugerido

Al cargar mediciones, el sistema evalúa cada valor contra la especificación y
**sugiere** un estado, en vivo, mientras el inspector escribe:

- todo dentro de especificación → `P.C`
- algún promedio fuera → `P.OBS`
- un parámetro marcado como **crítico** fuera → `RECHAZADO`

**La decisión final siempre es del inspector**, que puede sobreescribir la
sugerencia. Ningún parámetro viene cargado como crítico, porque en las
planillas actuales un desvío se registra como `P.OBS` y el rechazo no aparece
nunca. Si Calidad decide que alguna característica debe rechazar el lote por sí
sola, la marca como crítica desde Configuración.

### Emisión de la boleta

Una inspección se guarda primero y se **emite** después. Hasta que se emite, el
QR no resuelve nada — así una etiqueta impresa por adelantado no expone datos en
borrador. Una vez emitida no se puede editar sin anular la emisión.

### Qué ve el cliente

| | Interno (con login) | Cliente (por QR, sin login) |
|---|---|---|
| Identificación del lote y producto | Sí | Sí |
| Cada muestra individual (M1…M13) | Sí | No |
| Promedio, especificación y veredicto | Sí | Sí |
| Observación | Sí | Sí |
| **Observación interna** | Sí | **Nunca** |

Un resultado `RECHAZADO` **no se oculta** al que escanea: el certificado muestra
un aviso destacado de producto no conforme.

### Roles

| Rol | Puede |
|---|---|
| Administrador | Todo, incluida la configuración de plantillas, catálogos y usuarios |
| Control de Calidad | Cargar lotes e inspecciones, emitir boletas, imprimir etiquetas |
| Solo lectura | Consultar y reimprimir |

---

## 4. La especificación sale del código de producto

En `Bl 65x104/66`: **Bl** es el color, **65** el ancho en cm, **104** el largo
en cm y **66 el gramaje nominal en g/m²**.

Un parámetro de ensayo puede tomar su objetivo del producto del lote
(`spec_desde_producto`), en lugar de tener un valor fijo. Así el gramaje de
`Bl 65x104/66` se evalúa contra 66 ± 2 y el de `V.Cl 56x96/64` contra 64 ± 2,
con un solo parámetro configurado. Es lo mismo que Calidad ya hace a mano
cuando anota *"92 Grs. de acuerdo al cálculo del código"*.

Modos de especificación disponibles:

| Modo | Evalúa |
|---|---|
| Rango | `min ≤ valor ≤ max` |
| Objetivo ± tolerancia | Gramaje ± 2, ancho ± 1, elongación 23 ± 5 |
| Objetivo ± porcentaje | Denier ± 3,5 %, peso de bolsa ± 3 % |
| Solo mínimo | Tensión mínimo 60 kgf |
| Solo máximo | — |
| Valores conformes | Corte: `B` conforme, `M` no |
| Sin evaluación | Densidad de cintas, temperaturas, velocidad |

### Revisiones de plantilla

Para cambiar una tolerancia usá **Crear revisión nueva**, no la edición directa.
Cada inspección queda atada a la revisión con la que se llenó, así una boleta
emitida hace seis meses sigue mostrando la especificación que estaba vigente
ese día.

---

## 5. Pendientes para Calidad

Estos valores se cargaron con un criterio razonable pero **necesitan
confirmación** antes de usarse en producción:

1. **Peso nominal de bolsa por producto.** Solo tres están tomados de lo que
   Calidad escribió en las observaciones de la planilla: `Bl 65x104/66` → 92 g,
   `Az 56x102/66` → 79 g, `Cl 80x120/66` → 131 g. El resto es una estimación
   geométrica (tejido tubular + 4 cm de dobladillo) y hay que verificarla, porque
   de ese valor depende el veredicto del peso en Corte y Costura.
   Se corrige en **Configuración → Productos**.

2. **Tolerancia del peso de bolsa: ± 3 %.** Reproduce los veredictos que Calidad
   registró en la planilla, pero no está documentada en ningún formato.

3. **Rango de costura: 2 a 3 cm.** Tomado del histórico observado (2,4 a 3,0),
   no de una especificación escrita.

4. **Densidad de cintas (trama y urdimbre).** La boleta COD.02 tiene el campo
   pero la planilla no declara tolerancia, así que quedó sin evaluación
   automática.

5. **Denier por producto.** La especificación de extrusión quedó en 750 ± 3,5 %
   como valor por defecto. Si cada producto tiene su denier, cargalo en el campo
   correspondiente del producto y el sistema lo usa automáticamente.

### Un hallazgo de la migración

Al cargar las lecturas reales del 13-08, el sistema reproduce **6 de los 7**
veredictos que anotó Calidad. La diferencia está en la fila de `Bl 65x104/66`
del telar T-2: la **elongación de trama fue 29,2 %** contra un límite superior
de 28 % (23 ± 5), y quedó registrada como `P.C`. El desvío se pasó por alto en
la revisión manual. Vale confirmarlo con Calidad: si 29,2 % es aceptable, hay
que corregir la tolerancia; si no, el sistema ya lo detecta.

---

## 6. Estructura

```
app/
  Models/            Sector, Process, Product, Machine,
                     TestTemplate, TestParameter  <- motor de especificaciones
                     Lot, Inspection, Measurement <- trazabilidad
  Http/Controllers/  Lot, Inspection, Certificado, Etiqueta, Dashboard
                     Admin/  Plantilla, Parametro, Producto, Maquina, Usuario
  Http/Middleware/   PuedeEditar (admin + calidad), SoloAdmin
  Support/Qr.php     Generación de QR en SVG

database/seeders/
  RafiaSeeder.php    Sector Rafia completo, relevado de las planillas
  DemoSeeder.php     Lecturas reales del 13-08 para demostración

resources/views/
  lotes/  inspecciones/  publico/  etiquetas/  admin/
  inspecciones/_form.blade.php     Grilla de muestras con evaluación en vivo
  inspecciones/_boleta.blade.php   Boleta A4, compartida con el certificado
```

Piezas centrales:

- `TestParameter::evaluar()` — decide conforme / fuera de especificación
- `TestParameter::limites()` — resuelve min y max según el modo y el producto
- `Lot::puedeInspeccionar()` — la puerta entre procesos
- `Lot::inspeccionEfectivaDe()` — resuelve la aprobación propia o heredada
- `Inspection::estadoSugerido()` — la sugerencia de estado

---

## 7. Pruebas

```bash
php artisan test
```

83 pruebas, 279 aserciones. Cubren el motor de especificaciones contra lecturas
reales de las planillas, el bloqueo entre procesos, la herencia de aprobación
por lote de origen, la emisión de boletas, el aislamiento de la observación
interna en el certificado del cliente, la generación de etiquetas y los permisos
por rol.

---

## 8. Despliegue

**Infraestructura definida: servidor propio en planta, Windows Server con IIS.**
Base MySQL, PHP por FastCGI y el certificado publicado hacia internet con
Cloudflare Tunnel.

> **Guía completa paso a paso: [DESPLIEGUE.md](DESPLIEGUE.md)**

Resumen de lo indispensable:

1. IIS con el rol **CGI** y el módulo **URL Rewrite 2.1**. Sin URL Rewrite,
   Laravel devuelve 404 en todas las rutas.
2. PHP 8.3 o 8.4, build **Non-Thread-Safe (NTS)** x64, registrado como
   controlador FastCGI.
3. La raíz del sitio en IIS es la carpeta **`public`**, nunca la del proyecto:
   si apuntás a la raíz, `.env` queda accesible por web con las credenciales de
   la base. El `public\web.config` ya viene en el repositorio.
4. Permisos de escritura para `IIS_IUSRS` en `storage` y `bootstrap\cache`.
5. `APP_URL` con el dominio definitivo **antes de emitir la primera boleta**: esa
   dirección queda impresa dentro de cada QR.
6. Respaldo diario de la base por Task Scheduler, con una copia fuera del
   servidor. Cada boleta emitida es el certificado de un producto ya vendido.

Actualizaciones: `.\deploy.ps1` desde PowerShell como administrador.

### Por qué no GitHub Pages

GitHub Pages solo sirve archivos estáticos: no ejecuta PHP ni tiene base de
datos. FinControl sí funciona ahí porque es React puro con Firebase como backend.
GitHub queda como **repositorio del código**, en
`alanbritoabmlabml-byte/sgc-calidad`, **privado** — `DemoSeeder.php` contiene
lecturas de producción reales y nombres de operadores.

### Ubicación del proyecto

Vive en `C:\dev\sgc-calidad`, **fuera de OneDrive** a propósito. OneDrive marca
las carpetas como `ReadOnly` y PHP interpreta que no puede escribir, además de
sincronizar `vendor\` y el archivo de base de datos mientras se escribe, con
riesgo de corrupción.

---

## 9. Notas de producción

- `APP_ENV=production` y `APP_DEBUG=false`.
- Cambiar las contraseñas de los usuarios iniciales antes de dar acceso.
- `php artisan config:cache route:cache view:cache` después de cada cambio de
  configuración.
- **HTTPS obligatorio.** El certificado va a manos de clientes: sin HTTPS el
  navegador del cliente muestra advertencias al abrir el enlace del QR.
- El certificado público queda excluido del modo mantenimiento
  (`bootstrap/app.php`), así un cliente que escanea un QR durante un despliegue
  no se encuentra con un error 503.

---

## 10. La boleta replica el formulario en papel

La boleta que imprime el sistema usa el mismo encabezado, los mismos rótulos y el
mismo recuadro de estado que los formularios preimpresos COD.02 y COD.03:

| Proceso | Título | Recuadro de estado | Código |
|---|---|---|---|
| Tejido | INSPECCION DE TEJIDO (IT) | ESTADO DE INSPECCION DE TEJIDO | COD.02 |
| Corte y Costura | INSPECCION DE CORTE Y COSTURA (IC/C) | ESTADO DE INSPECCION DE BOLSAS | COD.03 |

Los rótulos son **datos**, no código: viven en la columna `etiquetas` de cada
proceso. Tejido imprime *"Fecha de corte de telar"* y *"Telar"*; Corte y Costura
imprime *"Fecha de corte de rollo"*, *"Código de bolsa"* y *"Total de bolsas
buenas"*. Para cambiar un rótulo se edita ese dato, sin tocar la vista.

**Una diferencia respecto del papel, a propósito:** en el formulario impreso el
recuadro "ESTADO DE INSPECCION" es una caja vacía que se completa a mano. En el
sistema ese recuadro contiene el veredicto (`P.C` / `P.OBS` / `RECHAZADO`), la
tabla de resultados con su especificación y la observación. Un certificado que va
a un cliente necesita mostrar contra qué se midió, no solo el resultado.

---

## 11. Segunda versión

### Identidad de marca

Logo de Plásticos Carmen reconstruido en **SVG vectorial** y aplicado en login,
barra superior, boleta, certificado y etiquetas. Paleta del sistema sobre los
colores de la marca: azul `#0F3C91`, azul oscuro `#0B2A5E`, rojo `#E4121C`.

> **El logo oficial ya está instalado**: `public/img/logo-pc.png` (símbolo) y
> `public/img/logo-pc-completo.png` (con el nombre). Se recortaron del archivo
> corporativo que está en OneDrive, en
> `Documentos / CarpetasSupervisorAlmacen / Letreros / logo_PC.png`.
>
> **Para tenerlo vectorial** —lo ideal para imprimir— exportá a SVG el kit
> vectorial de `Analista de Datos / ClaudeIA / Documentos / kit-logo-pc.pdf`
> y guardalo como `public/img/logo-pc.svg`. El componente prefiere el SVG sobre
> el PNG automáticamente, sin tocar código.

### Permisos por módulo

La autorización pasó de rol a **permiso**: 20 permisos agrupados en 7 módulos.
El rol define un punto de partida y el administrador ajusta cada casilla desde
*Configuración → Usuarios*.

| Rol | Alcance |
|---|---|
| Administrador | Todo, incluida la configuración y los usuarios |
| Control de Calidad | Lotes, inspecciones, emisión, etiquetas y **creación** de plantillas |
| Gerencia | Solo consulta: tableros, inspecciones y boletas |
| Solo lectura | Consulta de la operación y reimpresión |

**Calidad puede crear plantillas de ensayo pero no modificarlas ni eliminarlas.**
Es a propósito: una especificación vigente no se retoca, porque cambiaría cómo se
leen las boletas ya emitidas. Para cambiar una tolerancia se usa *Crear revisión
nueva*. Queda registrado quién creó cada revisión y quién la puso vigente, con
fecha y hora.

### La boleta no se emite incompleta

Antes de emitir, el sistema verifica que no falte ningún dato: hora, máquina,
operador, responsable, código de producto, N° de tarjeta, cantidades donde el
proceso las lleva, y **cada una de las mediciones**. Si falta algo, la pantalla
lista exactamente qué y el botón queda deshabilitado. Una boleta emitida es el
certificado de un producto vendido: no puede salir con campos en blanco.

### Boleta en media carta

Formato **139,7 × 215,9 mm** (media carta vertical), con tres firmas: operador,
responsable de Control de Calidad y **supervisor de turno / jefe de producción**.
El estado se escribe completo: `PRODUCTO CONFORME`, `PRODUCTO CON OBSERVACIÓN`,
`PRODUCTO NO CONFORME`.

### Etiquetas para impresora térmica Zebra

| Formato | Uso |
|---|---|
| **50 × 30 mm** | El más usado para rollos y fardos |
| **40 × 25 mm** | Formato chico |
| **Personalizable** | Ancho y alto en mm, entre 20 y 210 |
| Hoja A4 | Varias etiquetas por hoja, impresora de oficina |

En los formatos térmicos, cada etiqueta es una página del tamaño exacto del
rollo, así la impresora corta donde debe. El QR escala en milímetros. La etiqueta
lleva logo, **nombre del producto con su gramaje**, lote, fecha y hora, máquina y
operador. Por defecto imprime **5**.

### Validaciones de carga

- La fecha de corte no puede ser posterior a hoy, y la hora no puede ser
  posterior a la hora actual cuando la fecha es hoy.
- Los campos de nombre rechazan números y símbolos, pero aceptan **ñ**, tildes,
  apóstrofo y guion (`María Ñuñez`, `Ana García-López`, `Juan D'Angelo`).
- El **responsable de Control de Calidad** se toma del usuario con la sesión
  abierta, no del formulario: la boleta no puede atribuirse a otra persona.

### Ficha técnica del producto

*Configuración → Productos* incorpora norma de referencia, tipo de tejido,
tratamiento UV, cliente, capacidad en kg, densidades nominales de urdimbre y
trama, y observaciones técnicas, con sello de quién la actualizó y cuándo. La
pantalla avisa cuando faltan nominales, porque sin ellos el ensayo no tiene
especificación contra la que evaluar.

**Alta rápida:** desde el formulario de lote se puede crear un producto o un
sector sin cambiar de pantalla. Si el código tiene el formato `Bl 65x104/66`, el
sistema deduce ancho 65, largo 104 y gramaje 66.

### Tablero gerencial

En `/gerencia`, para el rol Gerencia y el administrador: conformidad, aprobación,
porcentaje de unidades falladas, tendencia mensual de 12 meses y rankings de
desvíos por característica, por máquina y por producto. Los gráficos son SVG y
HTML sin librería: se imprimen bien y no dependen de JavaScript.

### Avisos

Campana en la barra superior con contador, y pantalla propia en `/avisos`. El
caso central es la **boleta cerrada sin emitir**: mientras no se emita, el QR de
su etiqueta no resuelve y el trabajo no llega al cliente. También avisa de
inspecciones pendientes de cierre, lotes bloqueados, lotes sin inspección y
productos con ficha técnica incompleta.
