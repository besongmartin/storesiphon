<?php

namespace App\Livewire;

use App\Models\ExtractionJob;
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
        $user = auth()->user();
        $jobs = ExtractionJob::ownedBy($user);
        $monthProducts = (clone $jobs)->where('created_at', '>=', now()->startOfMonth())->sum('products_found');
        $recent = (clone $jobs)->with('store')->latest()->limit(5)->get();

        return view('livewire.dashboard', [
            'stats' => [
                ['label' => 'Plan', 'value' => 'Free', 'hint' => config('storesiphon.limits.free.max_products_per_job').' products per extraction'],
                ['label' => 'Products this month', 'value' => number_format($monthProducts), 'hint' => 'Across all extractions'],
                ['label' => 'Extractions', 'value' => number_format((clone $jobs)->count()), 'hint' => 'Kept for '.config('storesiphon.limits.free.retention_days').' days'],
            ],
            'recent' => $recent,
            'polling' => $recent->contains(fn (ExtractionJob $j) => $j->isActive()),
        ]);
    }
}
