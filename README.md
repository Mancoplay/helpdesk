# Helpdesk

Sistema Helpdesk en Laravel 12 para administrar usuarios, empleados, departamentos, tickets, chat de atencion, soporte remoto, reportes y notificaciones.

## Tecnologias

- PHP 8.2 o superior
- Laravel 12
- Livewire / Volt / Flux
- PostgreSQL
- Reverb para eventos en tiempo real
- Node.js 20 o superior
- Composer
- npm / Vite

## Estructura rapida

- `routes/web.php`: rutas principales del sistema.
- `routes/auth.php`: autenticacion, recuperacion de contrasena y logout.
- `app/Http/Controllers/HomeController.php`: flujo principal del helpdesk.
- `app/Http/Requests/Admin`: validaciones de formularios administrativos.
- `app/Services`: reglas reutilizables y servicios de notificacion.
- `resources/views`: pantallas Blade agrupadas por modulo.
- `resources/sass`: estilos fuente.
- `resources/js`: JavaScript fuente.
- `docs/STRUCTURE.md`: guia completa para ubicarse en el proyecto.
- `docs/LINUX_DEPLOY.md`: guia de despliegue en Linux.

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

Verifica tu entorno con:

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

### 2. Instalar dependencias

```bash
composer install
npm install
```

### 3. Crear y configurar `.env`

Este proyecto no mantiene `.env.example` porque el archivo de entorno se maneja localmente. Crea un archivo `.env` en la raiz y configura las variables necesarias.

Base minima para desarrollo:

```env
APP_NAME=Helpdesk
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

APP_LOCALE=es
APP_FALLBACK_LOCALE=es
APP_FAKER_LOCALE=es_BO

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=helpdesk
DB_USERNAME=postgres
DB_PASSWORD=tu_password_de_postgres

SESSION_DRIVER=database
SESSION_LIFETIME=5256000
SESSION_EXPIRE_ON_CLOSE=true
SESSION_ENFORCE_SINGLE_LOGIN=true
SESSION_CONCURRENT_WINDOW_SECONDS=30
SESSION_ENCRYPT=false
SESSION_COOKIE=helpdesk_session
SESSION_PATH=/
SESSION_DOMAIN=null

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

FILESYSTEM_DISK=local
HELPDESK_CHAT_ATTACHMENTS_DISK=public
HELPDESK_CHAT_ATTACHMENTS_DIR=ticket-mensajes
HELPDESK_PENDING_TICKET_REMINDER_MINUTES=5
HELPDESK_PENDING_TICKET_REMINDER_WEB_FALLBACK=true
HELPDESK_NOTIFICATIONS_RETENTION_DAYS=7

MAIL_MAILER=log
```

Despues genera la clave:

```bash
php artisan key:generate
php artisan optimize:clear
```

### 4. Crear base de datos

En PostgreSQL crea la base:

```sql
CREATE DATABASE helpdesk;
```

El nombre debe coincidir con `DB_DATABASE`.

### 5. Migrar y cargar datos iniciales

```bash
php artisan migrate
php artisan db:seed
php artisan storage:link
```

Usuarios iniciales:

```text
Administrador: admin@helpdesk.com / password
Empleado:      empleado@helpdesk.com / password
Usuario:       usuario@helpdesk.com / password
```

## Levantar el proyecto

Forma completa para desarrollo:

```bash
composer run dev
```

Ese comando levanta servidor Laravel, cola, Vite y Reverb.

Forma manual:

```bash
php artisan serve --host=0.0.0.0 --port=8000
npm run dev
php artisan queue:work
php artisan reverb:start --host=0.0.0.0 --port=8080
```

En la PC abre:

```text
http://127.0.0.1:8000
```

Desde un celular en la misma WiFi usa la IP local de la PC, por ejemplo:

```text
http://192.168.100.197:8000
```

Si el celular no entra y Laravel esta escuchando en `0.0.0.0:8000`, revisa el firewall de Windows.

## Comandos utiles

```bash
php artisan optimize:clear
php artisan route:list
php artisan test
npm run build
```

Para reinstalar una base de desarrollo desde cero:

```bash
php artisan migrate:fresh --seed
```

Ese comando borra las tablas de la base configurada. Usalo solo en desarrollo.

## Problemas comunes

### Error de conexion a PostgreSQL

Revisa que PostgreSQL este iniciado, que la base exista y que `DB_USERNAME` / `DB_PASSWORD` sean correctos. Si usas XAMPP, verifica que el PHP de Composer tenga habilitado `pdo_pgsql`.

### No cargan estilos o JavaScript

```bash
npm install
npm run dev
```

Para generar assets de produccion:

```bash
npm run build
```

### No puedo iniciar sesion

Ejecuta:

```bash
php artisan db:seed
```

Luego prueba con los usuarios iniciales.

### Notificaciones en tiempo real no funcionan

Levanta Reverb:

```bash
php artisan reverb:start --host=0.0.0.0 --port=8080
```

## Despliegue en servidor Linux

Usa la guia completa en `docs/LINUX_DEPLOY.md`. En produccion, el servidor web debe apuntar a `public`, no a la raiz del proyecto.
