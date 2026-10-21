<?php

namespace App\Livewire\Extractions;

use App\Domain\Extraction\ExtractionService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('New extraction')]
class Create extends Component
{
    #[Validate('required|string|max:2048')]
    public string $url = '';

    public int $maxProducts = 25;

    public bool $includeVariations = true;

    public function mount(): void
    {
        $this->maxProducts = (int) config('storesiphon.limits.free.max_products_per_job');
    }

    public function start(ExtractionService $service)
    {
        $this->validate();
        $max = (int) config('storesiphon.limits.free.max_products_per_job');
        $this->validate(['maxProducts' => "required|integer|min:1|max:{$max}"]);

        $job = $service->start(auth()->user(), $this->url, [
            'max_products' => $this->maxProducts,
            'include_variations' => $this->includeVariations,
        ]);

        return $this->redirectRoute('extractions.show', $job, navigate: false);
    }

    public function render()
    {
        return view('livewire.extractions.create', [
            'limit' => (int) config('storesiphon.limits.free.max_products_per_job'),
        ]);
    }
}
