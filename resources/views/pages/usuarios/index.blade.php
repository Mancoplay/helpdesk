<?php

use App\Http\Controllers\HomeController;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Usuarios - Help Desk')] class extends Component
{
    public function render()
    {
        return app(HomeController::class)->clientes(request());
    }
};
