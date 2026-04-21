<?php

use App\Http\Controllers\HomeController;
use App\Models\Empleado;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Revisar Empleado - Help Desk')] class extends Component
{
    public function render()
    {
        /** @var Empleado|null $empleado */
        $empleado = request()->route('empleado');

        abort_if(!$empleado, 404);

        return app(HomeController::class)->reviewEmpleado(request(), $empleado);
    }
};
