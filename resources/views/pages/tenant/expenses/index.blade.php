<?php

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\PaymentMethod;
use App\Services\TenantContext;
use App\Support\Money;
use App\Support\TenantStorage;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Expenses')] class extends Component
{
    use WithFileUploads, WithPagination;

    public string $dateFrom = '';

    public string $dateTo = '';

    public bool $showFormModal = false;

    public bool $showCategoryModal = false;

    public ?int $editingId = null;

    public string $expense_category_id = '';

    public string $amount = '';

    public string $expense_date = '';

    public string $payment_method_id = '';

    public string $description = '';

    public string $notes = '';

    public $receipt = null;

    public string $new_category_name = '';

    public function mount(): void
    {
        $tenant = app(TenantContext::class)->tenant();
        $this->dateFrom = now($tenant->timezone)->startOfMonth()->toDateString();
        $this->dateTo = now($tenant->timezone)->toDateString();
    }

    public function getExpensesProperty()
    {
        return Expense::query()
            ->with(['category', 'paymentMethod'])
            ->when($this->dateFrom, fn ($q) => $q->whereDate('expense_date', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('expense_date', '<=', $this->dateTo))
            ->latest('expense_date')
            ->latest('id')
            ->paginate(15);
    }

    public function getTotalProperty(): int
    {
        return Expense::query()
            ->when($this->dateFrom, fn ($q) => $q->whereDate('expense_date', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('expense_date', '<=', $this->dateTo))
            ->sum('amount');
    }

    public function getCategoriesProperty()
    {
        return ExpenseCategory::query()->orderBy('name')->get();
    }

    public function getPaymentMethodsProperty()
    {
        return PaymentMethod::query()->orderBy('sort_order')->get();
    }

    public function openCreate(): void
    {
        $this->reset(['editingId', 'expense_category_id', 'amount', 'payment_method_id', 'description', 'notes', 'receipt']);
        $this->expense_date = now()->toDateString();
        $this->showFormModal = true;
    }

    public function openEdit(int $expenseId): void
    {
        $expense = Expense::findOrFail($expenseId);

        $this->editingId = $expense->id;
        $this->expense_category_id = (string) $expense->expense_category_id;
        $this->amount = number_format($expense->amount / 100, 2, '.', '');
        $this->expense_date = $expense->expense_date->toDateString();
        $this->payment_method_id = (string) $expense->payment_method_id;
        $this->description = (string) $expense->description;
        $this->notes = (string) $expense->notes;
        $this->receipt = null;
        $this->showFormModal = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'expense_category_id' => ['nullable', 'exists:expense_categories,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'expense_date' => ['required', 'date'],
            'payment_method_id' => ['nullable', 'exists:payment_methods,id'],
            'description' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'receipt' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $tenant = app(TenantContext::class)->tenant();

        $attributes = [
            'expense_category_id' => $data['expense_category_id'] ?: null,
            'amount' => \App\Support\Money::toCents($data['amount']),
            'expense_date' => $data['expense_date'],
            'payment_method_id' => $data['payment_method_id'] ?: null,
            'description' => $data['description'] ?: null,
            'notes' => $data['notes'] ?: null,
        ];

        if ($this->receipt) {
            $attributes['receipt_image_path'] = TenantStorage::storeImage($this->receipt, 'receipts', $tenant);
        }

        if ($this->editingId) {
            $expense = Expense::findOrFail($this->editingId);

            if ($this->receipt) {
                TenantStorage::delete($expense->receipt_image_path);
            }

            $expense->update($attributes);
        } else {
            $attributes['recorded_by'] = Auth::id();
            Expense::create($attributes);
        }

        $this->showFormModal = false;
        session()->flash('status', 'Expense saved.');
    }

    public function delete(int $expenseId): void
    {
        $expense = Expense::findOrFail($expenseId);
        TenantStorage::delete($expense->receipt_image_path);
        $expense->delete();
        session()->flash('status', 'Expense deleted.');
    }

    public function saveCategory(): void
    {
        $this->validate(['new_category_name' => ['required', 'string', 'max:255']]);

        ExpenseCategory::create(['name' => $this->new_category_name]);
        $this->new_category_name = '';
    }

    public function deleteCategory(int $categoryId): void
    {
        ExpenseCategory::findOrFail($categoryId)->delete();
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-ink">Expenses</h1>
            <p class="mt-1 text-sm text-muted">Track what it costs to run the business.</p>
        </div>
        <div class="flex gap-2">
            <button type="button" wire:click="$set('showCategoryModal', true)"
                    class="rounded-lg border border-hairline bg-surface px-4 py-2 text-sm font-medium text-ink hover:bg-app-bg">
                Categories
            </button>
            <button type="button" wire:click="openCreate"
                    class="flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">
                <x-lucide-plus class="h-4 w-4" /> Add Expense
            </button>
        </div>
    </div>

    @if (session('status'))
        <div class="rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('status') }}</div>
    @endif

    <div class="flex flex-wrap items-end justify-between gap-3">
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label class="mb-1 block text-xs font-medium text-muted">From</label>
                <input wire:model.live="dateFrom" type="date" class="rounded-lg border border-hairline px-3 py-2 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-muted">To</label>
                <input wire:model.live="dateTo" type="date" class="rounded-lg border border-hairline px-3 py-2 text-sm">
            </div>
        </div>
        <div class="text-right">
            <p class="text-xs font-medium uppercase text-muted">Total Expenses</p>
            <p class="text-xl font-semibold text-ink">{{ Money::format($this->total) }}</p>
        </div>
    </div>

    <div class="overflow-x-auto rounded-xl border border-hairline bg-surface">
        <table class="w-full min-w-[760px] text-left text-sm">
            <thead class="border-b border-hairline bg-app-bg text-xs font-medium uppercase tracking-wide text-muted">
                <tr>
                    <th class="px-4 py-3">Date</th>
                    <th class="px-4 py-3">Category</th>
                    <th class="px-4 py-3">Description</th>
                    <th class="px-4 py-3">Amount</th>
                    <th class="px-4 py-3">Payment</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-hairline">
                @forelse ($this->expenses as $expense)
                    <tr>
                        <td class="px-4 py-3 text-muted">{{ $expense->expense_date->format('M j, Y') }}</td>
                        <td class="px-4 py-3 text-ink">{{ $expense->category?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-muted">{{ $expense->description ?? '—' }}</td>
                        <td class="px-4 py-3 text-ink">{{ Money::format($expense->amount) }}</td>
                        <td class="px-4 py-3 text-muted">{{ $expense->paymentMethod?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex justify-end gap-1">
                                @if ($expense->receipt_image_path)
                                    <a href="{{ TenantStorage::url($expense->receipt_image_path) }}" target="_blank" class="rounded-lg p-2 text-muted hover:bg-app-bg hover:text-ink">
                                        <x-lucide-receipt class="h-4 w-4" />
                                    </a>
                                @endif
                                <button type="button" wire:click="openEdit({{ $expense->id }})" class="rounded-lg p-2 text-muted hover:bg-app-bg hover:text-ink">
                                    <x-lucide-pencil class="h-4 w-4" />
                                </button>
                                <button type="button" wire:click="delete({{ $expense->id }})" wire:confirm="Delete this expense?" class="rounded-lg p-2 text-muted hover:bg-app-bg hover:text-danger-500">
                                    <x-lucide-trash-2 class="h-4 w-4" />
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-12 text-center text-muted">No expenses in this date range.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $this->expenses->links() }}

    @if ($showFormModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-lg rounded-xl bg-surface p-6 shadow-xl">
                <h2 class="text-lg font-semibold text-ink">{{ $editingId ? 'Edit Expense' : 'Add Expense' }}</h2>

                <form wire:submit="save" class="mt-4 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-ink">Amount (₱)</label>
                            <input wire:model="amount" type="text" inputmode="decimal" class="w-full rounded-lg border border-hairline px-3 py-2 text-sm">
                            @error('amount') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-ink">Date</label>
                            <input wire:model="expense_date" type="date" class="w-full rounded-lg border border-hairline px-3 py-2 text-sm">
                            @error('expense_date') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-ink">Category</label>
                            <select wire:model="expense_category_id" class="w-full rounded-lg border border-hairline px-3 py-2 text-sm">
                                <option value="">None</option>
                                @foreach ($this->categories as $category)
                                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-ink">Payment Method</label>
                            <select wire:model="payment_method_id" class="w-full rounded-lg border border-hairline px-3 py-2 text-sm">
                                <option value="">None</option>
                                @foreach ($this->paymentMethods as $method)
                                    <option value="{{ $method->id }}">{{ $method->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-ink">Description</label>
                        <input wire:model="description" type="text" class="w-full rounded-lg border border-hairline px-3 py-2 text-sm">
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-ink">Notes</label>
                        <textarea wire:model="notes" rows="2" class="w-full rounded-lg border border-hairline px-3 py-2 text-sm"></textarea>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-ink">Receipt Image</label>
                        <input wire:model="receipt" type="file" accept="image/*" class="w-full text-sm">
                        @error('receipt') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" wire:click="$set('showFormModal', false)" class="rounded-lg border border-hairline px-4 py-2 text-sm font-medium text-ink hover:bg-app-bg">
                            Cancel
                        </button>
                        <button type="submit" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">
                            Save Expense
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($showCategoryModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-md rounded-xl bg-surface p-6 shadow-xl">
                <h2 class="text-lg font-semibold text-ink">Expense Categories</h2>

                <form wire:submit="saveCategory" class="mt-4 flex gap-2">
                    <input wire:model="new_category_name" type="text" placeholder="New category name"
                           class="flex-1 rounded-lg border border-hairline px-3 py-2 text-sm">
                    <button type="submit" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">
                        Add
                    </button>
                </form>
                @error('new_category_name') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror

                <ul class="mt-4 max-h-64 space-y-1 overflow-y-auto">
                    @forelse ($this->categories as $category)
                        <li class="flex items-center justify-between rounded-lg px-3 py-2 text-sm hover:bg-app-bg">
                            {{ $category->name }}
                            <button type="button" wire:click="deleteCategory({{ $category->id }})" wire:confirm="Delete this category?" class="text-muted hover:text-danger-500">
                                <x-lucide-trash-2 class="h-4 w-4" />
                            </button>
                        </li>
                    @empty
                        <li class="px-3 py-2 text-sm text-muted">No categories yet.</li>
                    @endforelse
                </ul>

                <div class="mt-4 flex justify-end">
                    <button type="button" wire:click="$set('showCategoryModal', false)" class="rounded-lg border border-hairline px-4 py-2 text-sm font-medium text-ink hover:bg-app-bg">
                        Close
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
