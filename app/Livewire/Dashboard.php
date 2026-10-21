<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    public function render(): View
    {
        return view('livewire.dashboard', [
            // Placeholder figures until extraction jobs (Phase 3) and billing (Phase 4) exist.
            'stats' => [
                ['label' => 'Plan', 'value' => 'Free', 'hint' => '25 products per export'],
                ['label' => 'Products this month', 'value' => '0', 'hint' => 'Resets with your plan period'],
                ['label' => 'Extractions', 'value' => '0', 'hint' => 'Jobs you have run'],
            ],
        ]);
    }
}
