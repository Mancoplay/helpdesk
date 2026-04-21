<?php

namespace App\Livewire;

use Livewire\Component;

class DashboardOverview extends Component
{
    public array $stats = [];

    public array $chartLabels = [];

    public array $chartValues = [];

    public array $chartColors = [];

    public bool $isAdmin = false;

    public function mount(
        array $stats,
        array $chartLabels,
        array $chartValues,
        array $chartColors,
        bool $isAdmin
    ): void {
        $this->stats = $stats;
        $this->chartLabels = $chartLabels;
        $this->chartValues = $chartValues;
        $this->chartColors = $chartColors;
        $this->isAdmin = $isAdmin;
    }

    public function render()
    {
        return view('livewire.dashboard-overview');
    }
}
