<?php

use App\Http\Controllers\HomeController;
use App\Models\Cliente;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Revisar Usuario - Help Desk')] class extends Component
{
    public function render()
    {
        /** @var Cliente|null $cliente */
        $cliente = request()->route('cliente');

        abort_if(!$cliente, 404);

        return app(HomeController::class)->reviewCliente(request(), $cliente);
    }
};
