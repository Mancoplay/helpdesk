# Guia de estructura del proyecto

Este documento resume como ubicarse rapido dentro del Helpdesk sin tener que leer todo el codigo desde cero.

## Capas principales

- `routes/web.php`: rutas funcionales del sistema: dashboard, usuarios, empleados, departamentos, tickets, reportes y notificaciones.
- `routes/auth.php`: rutas de autenticacion Livewire/Volt y cierre de sesion.
- `app/Http/Controllers/HomeController.php`: flujo principal del helpdesk. Es grande; cuando se agreguen features nuevas, preferir mover logica a `app/Services` o `FormRequest` antes de seguir creciendo este controlador.
- `app/Http/Controllers/Auth`: controladores heredados de autenticacion Laravel UI.
- `app/Http/Requests/Admin`: validacion de formularios administrativos.
- `app/Services`: reglas de negocio reutilizables, notificaciones y rangos de revision.
- `app/Support`: utilidades de infraestructura o seguridad, como sesiones y broadcast seguro.
- `app/Models`: entidades del dominio: tickets, mensajes, usuarios, empleados, clientes, departamentos, areas y configuraciones.
- `resources/views`: vistas Blade agrupadas por modulo funcional.
- `resources/views/layouts/app.blade.php`: layout principal del panel.
- `resources/views/livewire`: pantallas Volt/Livewire, principalmente autenticacion y ajustes.
- `resources/sass`: estilos fuente compilados por Vite.
- `resources/js`: JavaScript fuente compilado por Vite.
- `public/css`: CSS estatico que no pasa por Vite.
- `config/helpdesk.php`: configuracion propia del dominio helpdesk.

## Convenciones de vistas

- Las vistas reales de negocio viven en carpetas por modulo: `tickets`, `usuarios`, `empleados`, `departamentos`, `reportes` y `notifications`.
- Los fragmentos reutilizables de cada modulo viven en `partials` dentro de su modulo.
- Evitar crear nuevas vistas duplicadas en `resources/views/pages`; esa carpeta fue retirada porque contenia wrappers no usados por las rutas actuales.
- Mantener PHP de presentacion minimo en Blade. Si una regla se repite o consulta modelos, moverla a controlador, service, view model o relacion del modelo.

## Convenciones de assets

- CSS compartido: `resources/sass/app.scss` y parciales en `resources/sass/components` o `resources/sass/layout`.
- CSS por pantalla compleja: `resources/sass/pages`, por ejemplo `ticket-show.scss`.
- JavaScript compartido: `resources/js/app.js` y modulos en `resources/js/ui`.
- JavaScript muy especifico de una pantalla puede quedar temporalmente en `@push('scripts')`, pero si crece o se repite debe pasar a `resources/js`.
- La funcionalidad de mostrar/ocultar contrasena esta centralizada en `resources/js/ui/password-toggle.js`.

## Archivos generados o locales

- No versionar `vendor`, `node_modules`, `public/build`, `public/hot`, `.phpunit.result.cache` ni archivos temporales.
- El archivo `.env` es local y contiene secretos. Si se entrega el proyecto a otra persona, entregar las variables necesarias por un canal seguro o documentarlas sin contrasenas reales.
- Despues de cambiar `.env`, ejecutar `php artisan optimize:clear`.

## Reglas de mantenimiento

- Mantener validacion en `FormRequest` cuando sea posible.
- Mantener reglas de negocio en services/controladores, no en CSS/JS ni en Blade.
- Antes de borrar una vista o mover una ruta, buscar referencias en `app`, `routes` y `resources/views`.
- Despues de refactors, ejecutar:

```bash
php artisan route:list
php artisan test
npm run build
```
