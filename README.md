## Stack tecnologico

- Laravel 12
- Livewire 4 (v4.2.1)
- Livewire Volt
- Livewire Flux
- PostgreSQL

## Requisitos

- PHP 8.2+
- Composer 2+
- Node.js 20+ y npm
- PostgreSQL 14+ (recomendado)
- Extensiones PHP: `pdo_pgsql`, `openssl`, `mbstring`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`

## 1) Clonar proyecto

```bash
git clone <URL_DEL_REPOSITORIO>
cd helpdesk
```

## 2) Instalar dependencias

```bash
composer install
npm install
```

## 3) Configurar entorno

Crear `.env` desde el ejemplo:

```bash
cp .env.example .env
```

En PowerShell:

```powershell
Copy-Item .env.example .env
```

Generar clave de Laravel:

```bash
php artisan key:generate
```

## 4) Configurar PostgreSQL en `.env`

Ajusta estos valores:

```env
APP_NAME=Helpdesk
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=helpdesk
DB_USERNAME=postgres
DB_PASSWORD=tu_password

# Adjuntos del chat
HELPDESK_CHAT_ATTACHMENTS_DISK=public
HELPDESK_CHAT_ATTACHMENTS_DIR=ticket-mensajes
```

## 5) Crear base de datos PostgreSQL

Con `psql`:

```sql
CREATE DATABASE helpdesk;
```

## 6) Migraciones y datos iniciales

```bash
php artisan migrate
# opcional:
# php artisan db:seed
```

## 7) Habilitar archivos públicos (adjuntos)

```bash
php artisan storage:link
```

Esto crea el enlace simbólico `public/storage` hacia `storage/app/public`.

## 8) Limpiar cachés y levantar proyecto

```bash
php artisan optimize:clear
npm run dev
php artisan serve
```

Opcional (si usas colas):

```bash
php artisan queue:work
```

## Comando único de desarrollo (opcional)

```bash
composer run dev
```

Ejecuta servidor Laravel + queue listener + Vite en paralelo.

## Despliegue básico en servidor

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Solución rápida de problemas

- Si no cargan estilos/scripts: ejecutar `npm run dev` (local) o `npm run build` (producción).
- Si no se ven adjuntos: ejecutar `php artisan storage:link`.
- Si falla conexión a BD: revisar credenciales PostgreSQL en `.env`.
- Si cambiaste `.env`: ejecutar `php artisan config:clear`.

## Notas de adjuntos

- Los archivos se guardan en disco (filesystem), no dentro de la base de datos.
- PostgreSQL solo guarda metadatos: ruta, nombre, mime y tamaño.
- Carpeta base configurable por:
  - `HELPDESK_CHAT_ATTACHMENTS_DISK`
  - `HELPDESK_CHAT_ATTACHMENTS_DIR`

