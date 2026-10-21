<?php

namespace App\Livewire\Extractions;

use App\Domain\Extraction\ExtractionService;
use App\Models\Export;
use App\Models\ExtractionJob;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class Show extends Component
{
    use WithPagination;

    public ExtractionJob $job;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $type = '';

    #[Url(except: '')]
    public string $stock = '';

    public bool $htmlDescriptions = false;

    public function mount(ExtractionJob $job): void
    {
        $this->authorize('view', $job);
        $this->job = $job;
    }

    public function updating(string $property): void
    {
        if (in_array($property, ['search', 'type', 'stock'], true)) {
            $this->resetPage();
        }
    }

    public function cancel(ExtractionService $service): void
    {
        $this->authorize('cancel', $this->job);
        $service->cancel($this->job);
    }

    public function export(ExtractionService $service, string $format): void
    {
        $this->authorize('export', $this->job);
        $service->export($this->job, $format, $this->htmlDescriptions);
    }

    public function render()
    {
        $this->job->refresh();
        $exports = $this->job->exports()->latest()->limit(10)->get();

        return view('livewire.extractions.show', [
            'products' => $this->products()->paginate(25),
            'exports' => $exports,
            'polling' => $this->job->isActive() || $exports->contains(fn (Export $e) => in_array($e->status, [Export::QUEUED, Export::RUNNING], true)),
        ])->title(($this->job->store?->name ?? parse_url($this->job->input_url, PHP_URL_HOST)).' · Extraction');
    }

    private function products(): Builder
    {
        $term = trim($this->search);

        return Product::query()
            ->where('extraction_job_id', $this->job->id)
            ->whereNull('parent_id')
            ->with('variations')
            ->when($term !== '', function (Builder $q) use ($term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $q->where(fn (Builder $w) => $w->where('name', 'like', $like)->orWhere('sku', 'like', $like)
                    ->orWhereHas('variations', fn (Builder $v) => $v->where('sku', 'like', $like)));
            })
            ->when(in_array($this->type, ['simple', 'variable'], true), fn (Builder $q) => $q->where('type', $this->type))
            ->when(in_array($this->stock, ['instock', 'outofstock', 'onbackorder'], true), fn (Builder $q) => $q->where('stock_status', $this->stock))
            ->orderBy('position');
    }
}
