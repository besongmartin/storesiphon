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
        $plan = $user->currentPlan();
        $subscription = $user->activeSubscription();
        $used = $user->productsUsedThisPeriod();
        $recent = (clone $jobs)->with('store')->latest()->limit(5)->get();

        return view('livewire.dashboard', [
            'stats' => [
                ['label' => 'Plan', 'value' => $plan->name, 'hint' => $subscription ? 'Paid until '.$subscription->ends_at->toFormattedDateString() : number_format($plan->max_products_per_job).' products per extraction'],
                ['label' => 'Products this period', 'value' => number_format($used).' / '.number_format($plan->product_quota), 'hint' => 'Since '.$user->periodStart()->toFormattedDateString()],
                ['label' => 'Extractions', 'value' => number_format((clone $jobs)->count()), 'hint' => 'Results kept for '.$plan->retention_days.' days'],
            ],
            'recent' => $recent,
            'polling' => $recent->contains(fn (ExtractionJob $j) => $j->isActive()),
        ]);
    }
}
