# Despliegue en servidor propio

Guía para poner SGC Calidad en un servidor de planta de Plásticos Carmen.

El sistema tiene dos públicos con necesidades distintas de red:

| Parte | Quién entra | Desde dónde |
|---|---|---|
| La aplicación (`/`, `/lotes`, `/inspecciones`, `/configuracion`) | Personal de Calidad, con usuario y contraseña | Red interna |
| **El certificado (`/c/{token}`)** | **Clientes, escaneando el QR** | **Internet, desde cualquier celular** |

Esa segunda fila es la parte difícil de un servidor en planta y está resuelta en
la sección 3.

---

## 1. Requisitos del servidor

- **PHP 8.3 o superior** con las extensiones: `pdo_mysql`, `mbstring`, `openssl`,
  `fileinfo`, `curl`, `zip`, `intl`, `tokenizer`, `xml`, `ctype`, `bcmath`
- **MySQL 8** o **MariaDB 10.6+**
- **Composer 2**
- **Node.js 20+** (solo para compilar los assets; se puede compilar en otra
  máquina y subir la carpeta `public/build`)
- **Git**
- Un servidor web: **Nginx** (recomendado) o Apache en Linux; **IIS** en Windows Server

Recursos: el volumen de Calidad es bajo. **2 vCPU y 2 GB de RAM sobran.**

---

## 2. Instalación

### 2.1 Base de datos

```sql
CREATE DATABASE sgc_calidad CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'sgc'@'localhost' IDENTIFIED BY 'una-clave-larga-y-aleatoria';
GRANT ALL PRIVILEGES ON sgc_calidad.* TO 'sgc'@'localhost';
FLUSH PRIVILEGES;
```

### 2.2 Traer el código

El repositorio es privado, así que hace falta autenticarse. Lo más limpio es una
**deploy key** de solo lectura:

```bash
ssh-keygen -t ed25519 -C "servidor-calidad" -f ~/.ssh/sgc_deploy -N ""
```

Cargá el contenido de `~/.ssh/sgc_deploy.pub` en GitHub, en
*Settings → Deploy keys* del repositorio `sgc-calidad`, **sin** permiso de
escritura. Después:

```bash
git clone git@github.com:alanbritoabmlabml-byte/sgc-calidad.git /var/www/sgc-calidad
```

### 2.3 Dependencias

```bash
cd /var/www/sgc-calidad && composer install --no-dev --optimize-autoloader
```

```bash
npm ci && npm run build
```

### 2.4 Configuración

```bash
cp .env.example .env && php artisan key:generate
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
> etiquetas ya pegadas. Definila antes de emitir la primera boleta.

### 2.5 Base y permisos

```bash
php artisan migrate --seed --force
```

```bash
sudo chown -R www-data:www-data storage bootstrap/cache && sudo chmod -R 775 storage bootstrap/cache
```

### 2.6 Optimizar

```bash
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

### 2.7 Cambiar las contraseñas iniciales

El seeder crea `admin@plasticoscarmen.com` y `calidad@plasticoscarmen.com` con
la clave `calidad2026`. **Cambialas antes de dar acceso a nadie**, desde
*Configuración → Usuarios*.

### 2.8 Nginx

La raíz del sitio es `public/`, **nunca** la carpeta del proyecto: si apuntás a
la raíz, queda expuesto el archivo `.env` con las credenciales.

```nginx
server {
    listen 80;
    server_name calidad.plasticoscarmen.com;
    root /var/www/sgc-calidad/public;

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    client_max_body_size 8M;
}
```

---

## 3. Publicar el certificado hacia internet

El QR lo escanea un cliente desde su celular, con datos móviles, fuera de la red
de la empresa. Si el servidor solo es accesible en la LAN, **los QR no sirven**.

### Opción A — Cloudflare Tunnel (recomendada para planta)

Es la mejor opción para un servidor en fábrica: **no requiere IP pública fija,
no requiere abrir ningún puerto en el firewall** y el certificado HTTPS es
automático y gratuito. El servidor abre una conexión saliente hacia Cloudflare y
el tráfico entra por ahí.

```bash
cloudflared tunnel login
```

```bash
cloudflared tunnel create sgc-calidad
```

`/etc/cloudflared/config.yml`:

```yaml
tunnel: sgc-calidad
credentials-file: /root/.cloudflared/sgc-calidad.json

ingress:
  - hostname: calidad.plasticoscarmen.com
    service: http://localhost:80
  - service: http_status:404
```

```bash
cloudflared tunnel route dns sgc-calidad calidad.plasticoscarmen.com
```

```bash
sudo cloudflared service install && sudo systemctl enable --now cloudflared
```

Requiere que el dominio `plasticoscarmen.com` use los nameservers de Cloudflare.
Eso lo tiene que hacer quien administre el DNS del dominio.

### Opción B — IP pública fija y port forward

Si la empresa tiene IP fija: un registro DNS `A` para
`calidad.plasticoscarmen.com`, redirección del puerto 443 al servidor, y
certificado con Let's Encrypt:

```bash
sudo certbot --nginx -d calidad.plasticoscarmen.com
```

Desventaja: expone el servidor directamente y depende de que la IP no cambie.

### Opción C — VPS chico como proxy inverso

Un VPS de 5 dólares al mes recibe el tráfico público y lo reenvía al servidor de
planta por VPN o túnel SSH. Más piezas para mantener; solo tiene sentido si
Sistemas ya opera una VPN.

### Endurecer: exponer solo el certificado

La aplicación interna no necesita estar en internet. Si querés reducir la
superficie expuesta, publicá hacia afuera únicamente la ruta pública y dejá el
resto en la LAN:

```nginx
# En el server block que atiende desde internet
location ~ ^/c/[A-Za-z0-9]{8,32}$ {
    try_files $uri /index.php?$query_string;
}

location /build/ { }
location = /favicon.ico { }

location / {
    return 404;
}
```

Así un cliente escanea el QR y ve su certificado, pero el login y la
configuración solo existen dentro de la red de la empresa.

---

## 4. Actualizaciones

```bash
cd /var/www/sgc-calidad && ./deploy.sh
```

El script está en la raíz del repositorio: baja los cambios, instala
dependencias, corre migraciones y regenera los cachés.

---

## 5. Respaldos

**Cada boleta emitida es el certificado de calidad de un producto ya vendido.**
Si se pierde la base, se pierden los certificados que los clientes pueden estar
consultando por QR en ese momento.

Respaldo diario de la base:

```bash
mysqldump -u sgc -p sgc_calidad | gzip > /respaldos/sgc_$(date +\%F).sql.gz
```

Poné eso en un cron diario, con retención de al menos 30 días y **una copia
fuera del servidor**. Un respaldo que vive en el mismo disco que la base no es
un respaldo.

---

## 6. Lista de verificación antes de abrir a Calidad

- [ ] `APP_ENV=production` y `APP_DEBUG=false`
- [ ] `APP_URL` con el dominio definitivo, y `php artisan config:cache` corrido
- [ ] Contraseñas de `admin@` y `calidad@` cambiadas
- [ ] La raíz del sitio web apunta a `public/`, y `https://.../.env` devuelve 404
- [ ] HTTPS funcionando con certificado válido
- [ ] **Un QR escaneado con un celular usando datos móviles, no WiFi de la empresa, abre el certificado**
- [ ] Respaldo diario de la base configurado y probado restaurando una copia
- [ ] Pesos nominales por producto confirmados por Calidad (ver README, sección 5)
