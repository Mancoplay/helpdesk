## Tecnologias

- PHP 8.2+
- Laravel 12
- Livewire
- PostgreSQL
- Node.js 20+

## Requisitos

Antes de levantar el proyecto necesitas tener instalado:

- PHP 8.2 o superior
- Composer
- Node.js y npm
- PostgreSQL
- Git

Extensiones PHP recomendadas:

- `pdo_pgsql`
- `openssl`
- `mbstring`
- `tokenizer`
- `xml`
- `ctype`
- `json`
- `bcmath`
- `fileinfo`

## Levantar el proyecto en otra PC

### 1. Clonar el proyecto

```bash
git clone <URL_DEL_REPOSITORIO>
cd helpdesk
```

### 2. Instalar dependencias

```bash
composer install
npm install
```

### 3. Crear el archivo `.env`

En Linux o Git Bash:

```bash
cp .env.example .env
```

En PowerShell:

```powershell
Copy-Item .env.example .env
```

### 4. Configurar la base de datos en `.env`

Editar estas variables:

```env
APP_NAME=HelpDesk
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=helpdesk
DB_USERNAME=postgres
DB_PASSWORD=tu_password

HELPDESK_CHAT_ATTACHMENTS_DISK=public
HELPDESK_CHAT_ATTACHMENTS_DIR=ticket-mensajes
```

### 5. Crear la base de datos

En PostgreSQL:

```sql
CREATE DATABASE help-desk;
```

### 6. Generar la clave y correr migraciones

```bash
php artisan key:generate
php artisan migrate
```

Si quieres cargar datos iniciales:

```bash
php artisan db:seed
```

### 7. Crear el enlace de storage

```bash
php artisan storage:link
```

### 8. Levantar el proyecto

Abre 2 terminales.

Terminal 1:

```bash
php artisan serve
```

Terminal 2:

```bash
npm run dev
```

Si el proyecto usa colas, en una tercera terminal:

```bash
php artisan queue:work
```

Tambien puedes usar este comando para desarrollo:

```bash
composer run dev
```

## Acceso local

Cuando todo este levantado, abre:

```text
http://127.0.0.1:8000
```

