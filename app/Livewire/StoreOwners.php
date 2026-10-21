<?php

namespace App\Livewire;

use App\Domain\Compliance\OptOutService;
use App\Domain\Security\Turnstile;
use App\Models\OptOutRequest;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.marketing')]
#[Title('For store owners')]
class StoreOwners extends Component
{
    public string $domain = '';

    public string $email = '';

    public string $turnstileToken = '';

    #[Locked]
    public ?int $requestId = null;

    public ?string $result = null;

    public function submit(OptOutService $service, Turnstile $turnstile): void
    {
        $this->validate([
            'domain' => 'required|string|max:253',
            'email' => 'required|email:rfc|max:255',
        ]);
        $turnstile->verifyOrFail($this->turnstileToken, request()->ip());

        $key = 'optout:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('domain', 'Too many requests. Please try again in an hour.');

            return;
        }
        RateLimiter::hit($key, 3600);

        $this->requestId = $service->request($this->domain, $this->email, request()->ip())->id;
        $this->result = null;
    }

    public function verify(OptOutService $service): void
    {
        $request = OptOutRequest::findOrFail($this->requestId);
        $key = 'optout-verify:'.$request->id;
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->result = 'too_many';

            return;
        }
        RateLimiter::hit($key, 600);

        $this->result = $service->verify($request) ? 'verified' : 'not_found';
    }

    public function render()
    {
        return view('livewire.store-owners', [
            'request' => $this->requestId ? OptOutRequest::find($this->requestId) : null,
            'path' => OptOutService::PATH,
        ]);
    }
}
