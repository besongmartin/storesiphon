<?php

namespace App\Livewire\Billing;

use App\Domain\Billing\PaymentService;
use App\Models\Plan;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use RuntimeException;

#[Layout('components.layouts.app')]
#[Title('Billing')]
class Index extends Component
{
    public ?string $error = null;

    public function buy(PaymentService $payments, string $slug)
    {
        $this->error = null;
        $user = auth()->user();
        $key = 'checkout:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 6)) {
            $this->error = 'Too many payment attempts. Please wait a minute and try again.';

            return null;
        }
        RateLimiter::hit($key, 60);

        $plan = Plan::where('slug', $slug)->where('is_active', true)->firstOrFail();
        try {
            $invoice = $payments->checkout($user, $plan);
        } catch (RuntimeException $e) {
            $this->error = $e->getMessage();

            return null;
        }

        return $this->redirectRoute('billing.invoices.show', $invoice);
    }

    public function render(PaymentService $payments)
    {
        $user = auth()->user();

        return view('livewire.billing.index', [
            'plan' => $user->currentPlan(),
            'subscription' => $user->activeSubscription(),
            'used' => $user->productsUsedThisPeriod(),
            'plans' => Plan::purchasable(),
            'invoices' => $user->invoices()->with('plan')->latest()->limit(20)->get(),
            'configured' => $payments->provider()->isConfigured(),
        ]);
    }
}
