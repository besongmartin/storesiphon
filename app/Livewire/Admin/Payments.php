<?php

namespace App\Livewire\Admin;

use App\Domain\Billing\PaymentService;
use App\Models\AuditLog;
use App\Models\Invoice;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

#[Layout('components.layouts.app')]
#[Title('Payments · Admin')]
class Payments extends Component
{
    use WithPagination;

    #[Url(except: 'review')]
    public string $filter = 'review';   // review | open | all

    public ?string $selected = null;

    public string $note = '';

    public ?string $message = null;

    public function select(string $id): void
    {
        $this->selected = $this->selected === $id ? null : $id;
        $this->note = '';
        $this->resetValidation();
    }

    public function recheck(PaymentService $payments, string $id): void
    {
        $invoice = Invoice::findOrFail($id);
        try {
            $payments->refresh($invoice, 'admin re-check by '.auth()->user()->email);
            $this->message = 'Re-checked with the provider.';
        } catch (Throwable $e) {
            report($e);
            $this->message = 'The provider could not be reached.';
        }
    }

    public function resolve(PaymentService $payments, string $id, bool $grant): void
    {
        $this->validate(['note' => 'required|string|min:5|max:1000'], ['note.required' => 'Write a short note explaining the decision.']);
        $invoice = Invoice::findOrFail($id);
        $payments->resolve($invoice, auth()->user(), $this->note, $grant);

        $this->message = $grant ? 'Resolved and plan period granted.' : 'Resolved without granting access.';
        $this->selected = null;
        $this->note = '';
    }

    public function updatingFilter(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $invoices = Invoice::with(['user', 'plan'])
            ->when($this->filter === 'review', fn ($q) => $q->where('needs_review', true))
            ->when($this->filter === 'open', fn ($q) => $q->whereIn('status', Invoice::OPEN))
            ->latest()
            ->paginate(25);

        return view('livewire.admin.payments', [
            'invoices' => $invoices,
            'reviewCount' => Invoice::where('needs_review', true)->count(),
            'history' => $this->selected
                ? AuditLog::with('actor')->where('subject_type', 'Invoice')->where('subject_id', $this->selected)->latest('id')->limit(20)->get()
                : collect(),
        ]);
    }
}
