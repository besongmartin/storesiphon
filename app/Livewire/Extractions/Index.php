<?php

namespace App\Livewire\Extractions;

use App\Models\ExtractionJob;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Extractions')]
class Index extends Component
{
    use WithPagination;

    public function render()
    {
        $jobs = ExtractionJob::ownedBy(auth()->user())
            ->with('store')
            ->latest()
            ->paginate(20);

        return view('livewire.extractions.index', [
            'jobs' => $jobs,
            'polling' => $jobs->getCollection()->contains(fn (ExtractionJob $j) => $j->isActive()),
        ]);
    }
}
