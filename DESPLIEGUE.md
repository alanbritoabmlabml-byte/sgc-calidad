# Despliegue en Windows Server con IIS

Guía para poner SGC Calidad en el servidor de planta de Plásticos Carmen.

El sistema tiene dos públicos con necesidades de red distintas:

| Parte | Quién entra | Desde dónde |
|---|---|---|
| La aplicación (`/`, `/lotes`, `/inspecciones`, `/configuracion`) | Personal de Calidad, con usuario y contraseña | Red interna |
| **El certificado (`/c/{token}`)** | **Clientes, escaneando el QR** | **Internet, desde cualquier celular** |

Esa segunda fila es la parte difícil de un servidor en planta y se resuelve en la
**sección 5**. Un servidor que solo responde en la LAN deja todas las etiquetas
impresas inservibles.

---

## 1. Qué instalar en el servidor

| Componente | Versión | Nota |
|---|---|---|
| **PHP** | 8.3 o 8.4, build **Non-Thread-Safe (NTS)** x64 | NTS es la que corresponde para FastCGI en IIS |
| Visual C++ Redistributable 2015-2022 | x64 | Lo pide PHP; si falta, PHP no arranca |
| **MySQL** o MariaDB | MySQL 8 / MariaDB 10.6+ | |
| **Composer 2** | | |
| Node.js | 20+ | Solo para compilar assets. Se puede evitar (ver 4.3) |
| Git para Windows | | |
| **IIS: rol CGI** | | Habilita FastCGI |
| **IIS: URL Rewrite 2.1** | | **Obligatorio.** Sin esto Laravel devuelve 404 en todas las rutas |

Recursos: el volumen de Calidad es bajo. **2 vCPU y 4 GB de RAM sobran.**

### 1.1 Habilitar IIS y CGI

En PowerShell como administrador:

```powershell
Enable-WindowsOptionalFeature -Online -FeatureName IIS-WebServerRole, IIS-WebServer, IIS-CGI, IIS-StaticContent, IIS-DefaultDocument, IIS-HttpErrors, IIS-HttpCompressionStatic, IIS-RequestFiltering -All
```

En Windows Server también sirve `Install-WindowsFeature Web-Server, Web-CGI`.

### 1.2 URL Rewrite

Descargalo de `https://www.iis.net/downloads/microsoft/url-rewrite` e instalalo.
Sin este módulo el sistema no funciona: IIS no sabe enviar las rutas a
`index.php` y todo devuelve 404 menos la página de inicio.

### 1.3 PHP

Bajá el ZIP **NTS x64** de `https://windows.php.net/download/` y descomprimilo en
`C:\php`. Después:

```powershell
Copy-Item C:\php\php.ini-production C:\php\php.ini
```

Editá `C:\php\php.ini` y dejá estas líneas activas (quitando el `;` inicial):

```ini
extension_dir = "C:\php\ext"

extension=curl
extension=fileinfo
extension=gd
extension=intl
extension=mbstring
extension=openssl
extension=pdo_mysql
extension=zip

memory_limit = 512M
upload_max_filesize = 8M
post_max_size = 8M

; Necesario para FastCGI en IIS
cgi.fix_pathinfo = 1
fastcgi.impersonate = 1
fastcgi.logging = 0
```

Agregá `C:\php` al PATH del sistema y verificá:

```powershell
php -v; php -m
```

### 1.4 Registrar PHP en IIS (FastCGI)

```powershell
C:\Windows\System32\inetsrv\appcmd.exe set config /section:system.webServer/fastCGI /+"[fullPath='C:\php\php-cgi.exe']"
```

```powershell
C:\Windows\System32\inetsrv\appcmd.exe set config /section:handlers /+"[name='PHP_via_FastCGI',path='*.php',verb='*',modules='FastCgiModule',scriptProcessor='C:\php\php-cgi.exe',resourceType='Either']"
```

---

## 2. Base de datos

```sql
CREATE DATABASE sgc_calidad CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'sgc'@'localhost' IDENTIFIED BY 'una-clave-larga-y-aleatoria';
GRANT ALL PRIVILEGES ON sgc_calidad.* TO 'sgc'@'localhost';
FLUSH PRIVILEGES;
```

---

## 3. Traer el código

El repositorio es privado. Lo más limpio es una **deploy key** de solo lectura,
así el servidor puede bajar cambios sin poder escribir en GitHub.

```powershell
ssh-keygen -t ed25519 -C "servidor-calidad" -f "$env:USERPROFILE\.ssh\sgc_deploy" -N '""'
```

Cargá el contenido de `sgc_deploy.pub` en GitHub, en *Settings → Deploy keys* del
repositorio `sgc-calidad`, **sin** marcar permiso de escritura. Configurá SSH
para usar esa clave (`%USERPROFILE%\.ssh\config`):

```
Host github.com
    HostName github.com
    User git
    IdentityFile ~/.ssh/sgc_deploy
```

```powershell
git clone git@github.com:alanbritoabmlabml-byte/sgc-calidad.git C:\inetpub\sgc-calidad
```

---

## 4. Instalar la aplicación

```powershell
cd C:\inetpub\sgc-calidad
```

### 4.1 Dependencias de PHP

```powershell
composer install --no-dev --optimize-autoloader
```

### 4.2 Configuración

```powershell
Copy-Item .env.example .env; php artisan key:generate
```

Editá `.env`:

```
APP_NAME="SGC Calidad"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://calidad.plasticoscarmen.com

APP_LOCALE=es
APP_TIMEZONE=America/La_Paz

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sgc_calidad
DB_USERNAME=sgc
DB_PASSWORD=una-clave-larga-y-aleatoria

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
```

> **`APP_URL` es la decisión irreversible.** Es la dirección que queda impresa
> dentro de cada código QR. Cambiarla después obliga a reimprimir todas las
> etiquetas ya pegadas en los rollos y fardos. Definila antes de emitir la
> primera boleta.

### 4.3 Assets

Si el servidor tiene Node:

```powershell
npm ci; npm run build
```

Si preferís no instalar Node en el servidor, compilá en tu PC con `npm run build`
y copiá la carpeta `public\build` completa al servidor. El sistema no necesita
Node para funcionar, solo para generar esos archivos.

### 4.4 Base y usuarios

```powershell
php artisan migrate --seed --force
```

### 4.5 Permisos de escritura

IIS necesita escribir en dos carpetas. Sin esto el sistema falla con error 500 al
primer request.

```powershell
icacls C:\inetpub\sgc-calidad\storage /grant "IIS_IUSRS:(OI)(CI)M" /T
```

```powershell
icacls C:\inetpub\sgc-calidad\bootstrap\cache /grant "IIS_IUSRS:(OI)(CI)M" /T
```

Si el sitio corre con una identidad de pool propia, usá
`IIS AppPool\<NombreDelPool>` en lugar de `IIS_IUSRS`.

### 4.6 Optimizar

```powershell
php artisan config:cache; php artisan route:cache; php artisan view:cache
```

### 4.7 Cambiar las contraseñas iniciales

El seeder crea `admin@plasticoscarmen.com` y `calidad@plasticoscarmen.com` con la
clave `calidad2026`. **Cambialas antes de dar acceso a nadie**, desde
*Configuración → Usuarios*.

### 4.8 Crear el sitio en IIS

La raíz del sitio es la carpeta **`public`**, nunca la carpeta del proyecto. Si
apuntás a la raíz del proyecto, `.env` queda accesible por web con todas las
credenciales de la base.

```powershell
Import-Module IISAdministration
New-IISSite -Name "SGC Calidad" -PhysicalPath "C:\inetpub\sgc-calidad\public" -BindingInformation "*:80:calidad.plasticoscarmen.com"
```

Si el módulo `IISAdministration` no está disponible, lo mismo con `appcmd`:

```powershell
C:\Windows\System32\inetsrv\appcmd.exe add site /name:"SGC Calidad" /physicalPath:"C:\inetpub\sgc-calidad\public" /bindings:"http/*:80:calidad.plasticoscarmen.com"
```

El archivo `public\web.config` ya viene en el repositorio con las reglas de
rewrite, los tipos MIME de las tipografías y las cabeceras de seguridad. No hay
que crearlo.

### 4.9 Verificar

```powershell
Invoke-WebRequest -Uri "http://localhost/login" -UseBasicParsing | Select-Object StatusCode
```

Y que `.env` **no** sea accesible:

```powershell
try { Invoke-WebRequest -Uri "http://localhost/../.env" -UseBasicParsing } catch { Write-Output "Bien: no accesible" }
```

---

## 5. Publicar el certificado hacia internet

El QR lo escanea un cliente desde su celular, con datos móviles, fuera de la red
de la empresa.

### Opción A — Cloudflare Tunnel (recomendada para planta)

La mejor opción para un servidor en fábrica: **no requiere IP pública fija, no
requiere abrir ningún puerto en el firewall** y el HTTPS es automático y
gratuito. El servidor abre una conexión saliente hacia Cloudflare y el tráfico
entra por ahí. Corre como servicio de Windows.

```powershell
winget install --id Cloudflare.cloudflared
```

```powershell
cloudflared tunnel login
```

```powershell
cloudflared tunnel create sgc-calidad
```

Creá `C:\Windows\System32\config\systemprofile\.cloudflared\config.yml`:

```yaml
tunnel: sgc-calidad
credentials-file: C:\Windows\System32\config\systemprofile\.cloudflared\<id-del-tunel>.json

ingress:
  - hostname: calidad.plasticoscarmen.com
    service: http://localhost:80
    originRequest:
      httpHostHeader: calidad.plasticoscarmen.com
  - service: http_status:404
```

```powershell
cloudflared tunnel route dns sgc-calidad calidad.plasticoscarmen.com
```

```powershell
cloudflared service install; Start-Service cloudflared
```

Requiere que el dominio `plasticoscarmen.com` use los nameservers de Cloudflare.
Eso lo define quien administre el DNS del dominio.

### Opción B — IP pública fija y port forward

Si la empresa tiene IP fija: registro DNS `A` para
`calidad.plasticoscarmen.com`, redirección del puerto 443 al servidor y
certificado con **win-acme**, que es el cliente de Let's Encrypt para IIS:

```powershell
winget install --id WinAcme.WinAcme
```

```powershell
wacs.exe --target iis --host calidad.plasticoscarmen.com --installation iis
```

win-acme instala el certificado en IIS y programa la renovación automática en el
Task Scheduler. Desventaja: expone el servidor directamente y depende de que la
IP no cambie.

### Endurecer: exponer solo el certificado

La aplicación interna no necesita estar en internet. Podés publicar hacia afuera
únicamente la ruta pública y dejar el resto en la LAN, creando **un segundo
sitio en IIS** que solo atienda `/c/*` y apunte a la misma carpeta `public`.

En el `web.config` de ese sitio público, antes de las reglas normales:

```xml
<rule name="Solo el certificado" stopProcessing="true">
    <match url="^(?!c/[A-Za-z0-9]{8,32}$|build/|favicon)" />
    <action type="CustomResponse" statusCode="404" statusDescription="Not Found" />
</rule>
```

Así un cliente escanea el QR y ve su certificado, pero el login y la
configuración solo existen dentro de la red de la empresa.

---

## 6. Actualizaciones

Desde PowerShell como administrador, en la carpeta del proyecto:

```powershell
.\deploy.ps1
```

El script baja los cambios, instala dependencias, corre migraciones y regenera
los cachés. Si algo falla, saca el sistema del modo mantenimiento para no
dejarlo caído.

Si el servidor no tiene Node:

```powershell
.\deploy.ps1 -SaltarAssets
```

**El certificado público sigue respondiendo durante todo el despliegue**, incluso
en modo mantenimiento: un cliente puede estar escaneando el QR de un lote justo
en ese momento. Eso está cubierto por una prueba automatizada.

---

## 7. Respaldos

**Cada boleta emitida es el certificado de calidad de un producto ya vendido.**
Si se pierde la base, se pierden los certificados que los clientes pueden estar
consultando por QR en ese momento.

Script `C:\scripts\respaldo-sgc.ps1`:

```powershell
$fecha = Get-Date -Format 'yyyy-MM-dd'
$destino = "D:\respaldos\sgc_$fecha.sql"
& 'C:\Program Files\MySQL\MySQL Server 8.0\bin\mysqldump.exe' `
    --user=sgc --password=una-clave-larga-y-aleatoria `
    --single-transaction --routines sgc_calidad > $destino
Compress-Archive -Path $destino -DestinationPath "$destino.zip" -Force
Remove-Item $destino
Get-ChildItem D:\respaldos\sgc_*.zip |
    Where-Object { $_.LastWriteTime -lt (Get-Date).AddDays(-30) } |
    Remove-Item
```

Programalo diario:

```powershell
$accion = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument '-NoProfile -ExecutionPolicy Bypass -File C:\scripts\respaldo-sgc.ps1'
$disparador = New-ScheduledTaskTrigger -Daily -At 1am
Register-ScheduledTask -TaskName 'Respaldo SGC Calidad' -Action $accion -Trigger $disparador -RunLevel Highest
```

**Una copia tiene que salir del servidor.** Un respaldo que vive en el mismo
disco que la base no es un respaldo.

---

## 8. Si algo falla

| Síntoma | Causa habitual |
|---|---|
| 404 en todas las rutas menos la de inicio | Falta el módulo **URL Rewrite 2.1** |
| 500.19 al abrir el sitio | Alguna sección del `web.config` está bloqueada a nivel servidor. Desbloqueá con `appcmd unlock config /section:httpErrors` |
| 500 al primer request, con log en `storage\logs` | Faltan permisos de escritura en `storage` y `bootstrap\cache` (paso 4.5) |
| "The Mix manifest does not exist" o página sin estilos | Falta compilar los assets, o falta copiar `public\build` (paso 4.3) |
| El QR abre pero da error de certificado en el celular | El HTTPS no está resuelto (sección 5) |
| El QR no abre nada desde el celular | `APP_URL` quedó apuntando a un nombre interno o a localhost |
| Error de conexión a la base | Falta habilitar `extension=pdo_mysql` en `php.ini` |
| PHP no arranca | Falta el Visual C++ Redistributable, o se instaló la build **ZTS** en lugar de **NTS** |

Los errores de la aplicación quedan en `storage\logs\laravel.log`.

---

## 9. Lista de verificación antes de abrir a Calidad

- [ ] `APP_ENV=production` y `APP_DEBUG=false`
- [ ] `APP_URL` con el dominio definitivo, y `php artisan config:cache` corrido
- [ ] Contraseñas de `admin@` y `calidad@` cambiadas
- [ ] La raíz del sitio en IIS apunta a `public`, y `.env` no es accesible por web
- [ ] HTTPS funcionando con certificado válido
- [ ] **Un QR escaneado con un celular usando datos móviles, no el WiFi de la empresa, abre el certificado**
- [ ] Respaldo diario configurado y **probado restaurando una copia**
- [ ] Pesos nominales por producto confirmados por Calidad (README, sección 5)
- [ ] Una boleta de prueba emitida, impresa y comparada contra el formulario COD.02 o COD.03 en papel
