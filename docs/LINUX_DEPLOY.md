# Despliegue en Linux

Esta guia sirve para subir el Helpdesk a un servidor Linux con Nginx, PHP-FPM, PostgreSQL, Composer y Node.js.

## 1. Requisitos del servidor

- PHP 8.2 o superior con extensiones: `pgsql`, `pdo_pgsql`, `mbstring`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`, `openssl`, `tokenizer`, `curl`, `zip`.
- Composer.
- Node.js 20 o superior y npm.
- PostgreSQL.
- Nginx o Apache. La guia usa Nginx.
- Git, si vas a subirlo como repositorio.

## 2. Subir el proyecto

Opcion con Git:

```bash
cd /var/www
git clone <URL_DEL_REPOSITORIO> helpdesk
cd /var/www/helpdesk
```

Opcion copiando carpeta:

```bash
cd /var/www/helpdesk
```

No subas `vendor`, `node_modules`, `.env`, `public/build` ni `public/storage`. Esos se generan en el servidor.

## 3. Crear base de datos

En PostgreSQL crea una base y un usuario:

```sql
CREATE DATABASE helpdesk;
CREATE USER helpdesk_user WITH PASSWORD 'cambia_esta_contrasena';
GRANT ALL PRIVILEGES ON DATABASE helpdesk TO helpdesk_user;
```

En PostgreSQL 15 o superior puede hacer falta dar permisos sobre el esquema:

```sql
\c helpdesk
GRANT ALL ON SCHEMA public TO helpdesk_user;
```

## 4. Crear el `.env`

```bash
cp .env.production.example .env
nano .env
```

Cambia como minimo:

- `APP_URL`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`
- `MAIL_*`, si enviaras correos reales
- `REVERB_APP_KEY` y `REVERB_APP_SECRET`
- `REVERB_HOST`, normalmente tu dominio

Luego genera la clave:

```bash
php artisan key:generate --force
```

## 5. Instalacion automatica

Puedes ejecutar el script incluido:

```bash
bash scripts/linux/deploy.sh
```

El script instala dependencias, compila assets, ejecuta migraciones, crea el link de storage y optimiza caches.

Si es una instalacion nueva con datos de prueba, ejecuta despues:

```bash
php artisan db:seed --force
```

## 6. Instalacion manual

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan storage:link
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 7. Permisos

El usuario del servidor web necesita escribir en `storage` y `bootstrap/cache`.

En Ubuntu/Debian con Nginx:

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache
```

## 8. Nginx

Ejemplo de sitio:

```nginx
server {
    listen 80;
    server_name tudominio.com;
    root /var/www/helpdesk/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location /index.php {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Activa el sitio y reinicia Nginx:

```bash
sudo ln -s /etc/nginx/sites-available/helpdesk /etc/nginx/sites-enabled/helpdesk
sudo nginx -t
sudo systemctl reload nginx
```

## 9. Cola de trabajos

El proyecto usa `QUEUE_CONNECTION=database`, por eso en produccion debe quedar un worker activo.

Archivo ejemplo `/etc/systemd/system/helpdesk-queue.service`:

```ini
[Unit]
Description=Helpdesk Laravel Queue Worker
After=network.target

[Service]
User=www-data
Group=www-data
Restart=always
WorkingDirectory=/var/www/helpdesk
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --timeout=90

[Install]
WantedBy=multi-user.target
```

Activar:

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now helpdesk-queue
sudo systemctl status helpdesk-queue
```

## 10. Reverb/notificaciones en tiempo real

Si usaras notificaciones en tiempo real, deja Reverb corriendo.

Archivo ejemplo `/etc/systemd/system/helpdesk-reverb.service`:

```ini
[Unit]
Description=Helpdesk Laravel Reverb Server
After=network.target

[Service]
User=www-data
Group=www-data
Restart=always
WorkingDirectory=/var/www/helpdesk
ExecStart=/usr/bin/php artisan reverb:start --host=127.0.0.1 --port=8080

[Install]
WantedBy=multi-user.target
```

Activar:

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now helpdesk-reverb
sudo systemctl status helpdesk-reverb
```

Para usar Reverb con HTTPS normalmente se publica por Nginx como proxy WebSocket hacia `127.0.0.1:8080`.

## 11. Actualizar el sistema despues

Cuando subas cambios nuevos:

```bash
git pull
bash scripts/linux/deploy.sh
sudo systemctl restart helpdesk-queue
sudo systemctl restart helpdesk-reverb
```

## 12. Comprobaciones

```bash
php artisan about
php artisan migrate:status
php artisan route:list
```

Si algo falla despues de cambiar `.env`:

```bash
php artisan optimize:clear
```
