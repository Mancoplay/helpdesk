# Project Structure Guide

This document explains the current structure and conventions of the Helpdesk project.

## 1) Main Layers

- `routes/`
  - `web.php`: business routes (dashboard, clientes, empleados, departamentos, tickets).
  - `auth.php`: authentication and account routes.
- `app/Http/Controllers/`
  - `HomeController.php`: ticket/helpdesk use cases and admin CRUD actions.
  - `Auth/*`: legacy auth controllers.
- `app/Http/Requests/Admin/`
  - `StoreClienteRequest.php`
  - `UpdateClienteRequest.php`
  - `StoreEmpleadoRequest.php`
  - `UpdateEmpleadoRequest.php`
  Centralized validation and input normalization for admin forms.
- `app/Http/Middleware/`
  - `EnsureActiveAccount.php`: disabled-account protection.
  - `PreventBackHistory.php`: anti-cache protection for authenticated pages.
- `app/Models/`
  - Domain models (`Ticket`, `TicketMensaje`, `TicketRemoteSession`, `Cliente`, `Empleado`, `Departamento`, `User`).
- `resources/views/`
  - `layouts/`: base application layout.
  - `tickets/`, `clientes/`, `empleados/`, `departamentos/`: feature views.
  - `auth/` and `livewire/`: auth/settings UI.
- `config/`
  - `helpdesk.php`: feature configuration (attachments).

## 2) Validation Rules

- Validation for create/update admin forms is handled in `FormRequest` classes.
- Phone input is normalized before validation (non-digit characters are removed).
- Bolivia phone format is enforced:
  - mobile: 8 digits starting with `6` or `7`
  - landline: 7 digits starting with `2`, `3`, or `4`

## 3) Security Design Notes

- Authenticated pages include no-cache headers through middleware.
- Ticket attachments are served through authenticated routes and access checks.
- Password recovery flow uses throttling and generic responses to reduce user enumeration.

## 4) Maintenance Rules

- Keep business rules in controllers/services, not in Blade templates.
- Keep input validation in `FormRequest` classes.
- Before removing any file, verify references with:
  - `rg "view\\(|route\\(|Volt::route|include\\(" -n app routes resources/views`
- After refactors, validate routes:
  - `php artisan route:list`

