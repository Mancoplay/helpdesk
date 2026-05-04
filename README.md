## Tecnologias

- PHP 8.2 o superior
- Laravel 12
- Livewire / Volt / Flux
- PostgreSQL
- Node.js 20 o superior
- Composer
- npm

laravel new helpdesk
composer create-project laravel/laravel helpdesk
```

Esos comandos crean un proyecto Laravel nuevo y pueden generar carpetas, vistas, rutas o configuraciones que no pertenecen a este Helpdesk.

Tampoco copies `vendor`, `node_modules` ni `.env` desde otra PC. Es mejor generarlos nuevamente con `composer install`, `npm install` y una copia limpia de `.env.example`.

## Requisitos

Instala antes:

- PHP 8.2+
- Composer
- Node.js 20+ y npm
- PostgreSQL
- Git

Extensiones PHP necesarias o recomendadas:

- `pdo_pgsql`
- `openssl`
- `mbstring`
- `tokenizer`
- `xml`
- `ctype`
- `json`
- `bcmath`
- `fileinfo`

Si usas XAMPP, revisa que el PHP que usa Composer sea el mismo PHP donde habilitaste `pdo_pgsql`.

Puedes verificarlo con:

```bash
php -v
php -m
composer -V
node -v
npm -v
```

## Instalacion en otra PC

### 1. Clonar el repositorio

```bash
git clone <URL_DEL_REPOSITORIO>
cd helpdesk
```

### 2. Instalar dependencias de PHP

```bash
composer install
```

### 3. Instalar dependencias de Node

```bash
npm install
```

### 4. Crear el archivo `.env`

En PowerShell:

```powershell
Copy-Item .env.example .env
```

En Git Bash, Linux o macOS:

```bash
cp .env.example .env
```

### 5. Configurar `.env`

Edita el archivo `.env` y deja estas variables principales asi:

```env
APP_NAME=Helpdesk
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

APP_LOCALE=es
APP_FALLBACK_LOCALE=es

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=helpdesk
DB_USERNAME=postgres
DB_PASSWORD=tu_password_de_postgres

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

BROADCAST_CONNECTION=reverb
REVERB_APP_ID=helpdesk
REVERB_APP_KEY=helpdesk-key
REVERB_APP_SECRET=helpdesk-secret
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"

HELPDESK_CHAT_ATTACHMENTS_DISK=public
HELPDESK_CHAT_ATTACHMENTS_DIR=ticket-mensajes
HELPDESK_PENDING_TICKET_REMINDER_MINUTES=5
HELPDESK_PENDING_TICKET_REMINDER_WEB_FALLBACK=true
HELPDESK_NOTIFICATIONS_RETENTION_DAYS=7

MAIL_MAILER=log
```

Usa siempre el mismo nombre de base de datos en PostgreSQL y en `.env`. En esta guia se usa `helpdesk`.

### 6. Crear la base de datos en PostgreSQL

En PostgreSQL crea la base de datos:

```sql
CREATE DATABASE helpdesk;
```

Si la base ya existe y quieres reinstalar desde cero, primero elimina sus tablas o crea una base nueva. No ejecutes migraciones sobre una base que pertenece a otro proyecto.

### 7. Generar la clave de la aplicacion

```bash
php artisan key:generate
```

### 8. Ejecutar migraciones

```bash
php artisan migrate
```

### 9. Cargar usuarios y roles iniciales

```bash
php artisan db:seed
```

Esto crea roles, permisos y usuarios de prueba.

Usuarios iniciales:

```text
Administrador: admin@helpdesk.com / password
Empleado:      empleado@helpdesk.com / password
Usuario:       usuario@helpdesk.com / password
```

### 10. Crear enlace de storage

```bash
php artisan storage:link
```

### 11. Limpiar cache de configuracion

Despues de editar `.env`, ejecuta:

```bash
php artisan optimize:clear
```

## Levantar el proyecto

La forma mas completa para desarrollo es:

```bash
composer run dev
```

Ese comando levanta:

- servidor Laravel
- cola de trabajos
- Vite
- Reverb para notificaciones en tiempo real

Luego abre:

```text
http://127.0.0.1:8000
```

## Levantarlo manualmente

Si prefieres usar terminales separadas:

Terminal 1:

```bash
php artisan serve
```

Terminal 2:

```bash
npm run dev
```

Terminal 3, para colas:

```bash
php artisan queue:work
```

Terminal 4, para Reverb/notificaciones en tiempo real:

```bash
php artisan reverb:start --host=0.0.0.0 --port=8080
```

## Problemas comunes

### Error de conexion a PostgreSQL

Revisa que:

- PostgreSQL este iniciado.
- La base `helpdesk` exista.
- `DB_USERNAME` y `DB_PASSWORD` sean correctos.
- La extension `pdo_pgsql` este habilitada en PHP.
- Despues de cambiar `.env`, hayas ejecutado `php artisan optimize:clear`.

### No cargan estilos o JavaScript

Ejecuta:

```bash
npm install
npm run dev
```

### No puedo iniciar sesion

Ejecuta los seeders:

```bash
php artisan db:seed
```

Luego prueba con:

```text
admin@helpdesk.com
password
```

### Las notificaciones en tiempo real no funcionan

Levanta Reverb:

```bash
php artisan reverb:start --host=0.0.0.0 --port=8080
```

O usa directamente:

```bash
composer run dev
```

## Comandos utiles

```bash
php artisan migrate:fresh --seed
php artisan optimize:clear
php artisan route:list
npm run build
```

`php artisan migrate:fresh --seed` borra todas las tablas de la base configurada y las crea nuevamente. Usalo solo en desarrollo.
