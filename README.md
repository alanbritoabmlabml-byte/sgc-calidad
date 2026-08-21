# SGC Calidad — Plásticos Carmen S.R.L.

Sistema de control de calidad por lotes con emisión de boletas de inspección,
etiquetas QR y certificado de calidad público para clientes.

Primera versión enfocada en el **sector Rafia**. La estructura es modular: los
sectores, procesos, plantillas de ensayo y parámetros son **datos**, no código,
así que agregar Expandido, Inyección u otro sector no requiere programar.

- **Stack:** Laravel 13 · PHP 8.4 · Blade + Tailwind CSS 4 + Alpine.js · SQLite (dev) / MySQL (producción)
- **Responsive:** computadora, tablet y celular
- **QR:** SVG vectorial (`bacon/bacon-qr-code`), sin depender de extensiones de imagen

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
| `admin@plasticoscarmen.com` | `calidad2026` | Administrador |
| `calidad@plasticoscarmen.com` | `calidad2026` | Control de Calidad |

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

33 pruebas, 122 aserciones. Cubren el motor de especificaciones contra lecturas
reales de las planillas, el bloqueo entre procesos, la herencia de aprobación
por lote de origen, la emisión de boletas, el aislamiento de la observación
interna en el certificado del cliente, la generación de etiquetas y los permisos
por rol.

---

## 8. Hosting: por qué GitHub Pages no sirve acá

**GitHub Pages solo sirve archivos estáticos.** No ejecuta PHP ni tiene base de
datos, así que no puede alojar este sistema. FinControl sí funciona ahí porque es
React puro con Firebase como backend; Laravel necesita un servidor con PHP.

GitHub sigue siendo el lugar correcto para el **repositorio del código**. Lo que
tiene que cambiar es dónde corre la aplicación:

| Opción | Costo | Cuándo conviene |
|---|---|---|
| **Hosting propio de la empresa** (cPanel de `plasticoscarmen.com`) | Ya pagado | Si el plan incluye PHP 8.3+ y MySQL. Permite `calidad.plasticoscarmen.com`. **La primera a revisar.** |
| Hosting compartido PHP nuevo | ~USD 3–5/mes | Si el plan actual no da PHP. Muy simple de administrar. |
| Render / Railway | Gratis a ~USD 7/mes | Despliegue automático desde GitHub en cada push. El plan gratis apaga el servidor cuando no hay tráfico: mal para un QR que un cliente puede escanear en cualquier momento. |
| Servidor propio en planta + túnel | Costo de infraestructura | Solo si Sistemas ya administra servidores y se puede publicar hacia afuera con HTTPS. |

Lo que **no** funciona: GitHub Pages, y cualquier solución que no sea accesible
desde internet — el certificado tiene que abrirse desde el celular de un cliente,
fuera de la red de la empresa.

### Nota sobre el repositorio

El repositorio conviene que sea **privado**. `DemoSeeder.php` contiene lecturas
de producción reales y nombres de operadores (Moisés Góngora, Javier Pastedo,
Fabiola). En un repositorio público eso queda expuesto.

---

## 9. Pasar a producción

1. `APP_ENV=production`, `APP_DEBUG=false` y `APP_URL` con el dominio real.
2. Cambiar a MySQL en `.env` (`DB_CONNECTION=mysql` y credenciales) y correr
   `php artisan migrate --seed`.
3. Cambiar las contraseñas de los usuarios iniciales.
4. `php artisan config:cache route:cache view:cache` y `npm run build`.
5. Servir `public/` por HTTPS. El certificado va a manos de clientes: sin HTTPS
   el navegador del cliente va a mostrar advertencias sobre el enlace del QR.
6. Respaldo de la base: cada boleta emitida es un certificado de calidad de
   producto vendido.

> **Ubicacion.** El proyecto vive en `C:\dev\sgc-calidad`, **fuera de OneDrive** a propósito.
> OneDrive marca las carpetas como `ReadOnly` y PHP interpreta que no puede
> escribir, además de sincronizar `vendor/` y el archivo de base de datos
> mientras se escribe, con riesgo de corrupción.

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
