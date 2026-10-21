<?php

namespace App\Livewire;

use App\Domain\Extraction\ExtractionService;
use App\Models\ExtractionJob;
use App\Models\Product;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Anonymous preview: preflight + first few products, no export. The job is bound to a random token kept
 * in the visitor's session (only its hash is stored), so nobody else can open it.
 */
#[Layout('components.layouts.marketing')]
#[Title('Try StoreSiphon')]
class TryIt extends Component
{
    public string $url = '';

    #[Locked]
    public ?string $jobId = null;

    public function mount(): void
    {
        $this->jobId = session('guest_job');
    }

    public function start(ExtractionService $service): void
    {
        $this->validate(['url' => 'required|string|max:2048']);

        $token = session('guest_token');
        if (! $token) {
            $token = Str::random(40);
            session(['guest_token' => $token]);
        }

        $job = $service->startGuest($token, (string) request()->ip(), $this->url);
        session(['guest_job' => $job->id]);
        $this->jobId = $job->id;
    }

    public function startOver(): void
    {
        session()->forget('guest_job');
        $this->jobId = null;
        $this->url = '';
    }

    public function render()
    {
        $job = $this->job();

        return view('livewire.try-it', [
            'job' => $job,
            'products' => $job ? Product::where('extraction_job_id', $job->id)->whereNull('parent_id')->with('variations')->orderBy('position')->limit(10)->get() : collect(),
        ]);
    }

    private function job(): ?ExtractionJob
    {
        $token = session('guest_token');
        if (! $this->jobId || ! $token) {
            return null;
        }

        return ExtractionJob::whereKey($this->jobId)
            ->whereNull('user_id')
            ->where('guest_token', ExtractionJob::guestTokenHash($token))
            ->first();
    }
}
