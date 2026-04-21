<?php

use App\Http\Controllers\HomeController;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Tickets - Help Desk')] class extends Component
{
    public function render()
    {
        return app(HomeController::class)->tickets(request());
    }
};
