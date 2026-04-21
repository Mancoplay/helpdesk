<?php

use App\Http\Controllers\HomeController;
use App\Models\Ticket;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Ticket Detalle - Help Desk')] class extends Component
{
    public function render()
    {
        /** @var Ticket|null $ticket */
        $ticket = request()->route('ticket');

        abort_if(!$ticket, 404);

        return app(HomeController::class)->showTicket($ticket);
    }
};
