<?php

use App\Http\Controllers\NotificationController;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Notificaciones - Help Desk')] class extends Component
{
    public function render()
    {
        return app(NotificationController::class)->index(request());
    }
};
