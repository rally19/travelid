<?php
use Livewire\Attributes\{Layout, Title, Computed, Url};
use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\{Order, Terminal};
use Illuminate\Support\Facades\Auth;
use Flux\Flux;

new #[Layout('components.layouts.app')]
    #[Title('My Transactions')]
class extends Component {
    use WithPagination;
    
    public bool $showFilters = false;
    public ?Order $orderToCancel = null;
    public $search = [
        'departure_id' => '',
        'arrival_id' => '',
    ];
    public $filters = [
        'status' => '',
        'payment_method' => '',
    ];
    public $sortBy = 'created_at';
    public $sortDirection = 'desc';
    public $perPage = 10;
    
    public function mount()
    {
        $this->search = session()->get('transactions.search', $this->search);
        $this->filters = session()->get('transactions.filters', $this->filters);
        $this->sortBy = session()->get('transactions.sortBy', $this->sortBy);
        $this->sortDirection = session()->get('transactions.sortDirection', $this->sortDirection);
        $this->perPage = session()->get('transactions.perPage', $this->perPage);
        
        $savedPage = session()->get('terminals.page', 1);
        $this->setPage($savedPage);
        
        $this->validatePage();
    }

    public function updatedPage($value)
    {
        session()->put('terminals.page', $value);
    }

    public function gotoPage($page)
    {
        $this->setPage($page);
        session()->put('terminals.page', $page);
        $this->validatePage();
    }
    
    public function confirmCancel($orderId)
    {
        $this->orderToCancel = Order::find($orderId);
        Flux::modal('cancel-order-modal')->show();
    }
    
    public function cancelOrder()
    {
        if (!$this->orderToCancel) {
            $this->dispatch('toast', message: 'Order not found', type: 'error');
            return;
        }
        
        try {
            $this->orderToCancel->update(['status' => 'cancelled']);
            
            Flux::toast(
                variant: 'success',
                heading: 'Order Cancelled.',
                text: 'Your order has been successfully cancelled.',
            );
            
            $this->orderToCancel = null;
            Flux::modal('cancel-order-modal')->close();
            
            $this->resetPage();
            unset($this->getOrders);
            
        } catch (\Exception $e) {
            $this->dispatch('toast', message: 'Failed to cancel order: ' . $e->getMessage(), type: 'error');
        }
    }
    
    public function updatedPerPage($value)
    {
        $this->resetPage();
        $this->validatePage();
        session()->put('transactions.perPage', $value);
    }
    
    public function updatedSearch()
    {
        $this->resetPage();
        session()->put('transactions.search', $this->search);
    }
    
    public function updatedFilters()
    {
        $this->resetPage();
        session()->put('transactions.filters', $this->filters);
    }
    
    public function resetFilters()
    {
        $this->reset('search');
        $this->reset('filters');
        session()->forget(['transactions.search', 'transactions.filters']);
        $this->resetPage();
        session()->put('terminals.page', 1);
    }
    
    public function validatePage()
    {
        $orders = $this->getOrders();
        
        if ($orders->currentPage() > $orders->lastPage()) {
            $this->setPage($orders->lastPage());
        }
    }
    
    #[Computed]
    public function getOrders()
    {
        return Order::query()
            ->where('users_id', Auth::id())
            ->when($this->search['departure_id'], function ($query) {
                if (str_starts_with($this->search['departure_id'], 'regencity_')) {
                    $regencity = str_replace('regencity_', '', $this->search['departure_id']);
                    $query->where('departure_location', 'like', "%{$regencity}%");
                } else {
                    $query->where('departure_terminal', 'like', "%{$this->search['departure_id']}%");
                }
            })
            ->when($this->search['arrival_id'], function ($query) {
                if (str_starts_with($this->search['arrival_id'], 'regencity_')) {
                    $regencity = str_replace('regencity_', '', $this->search['arrival_id']);
                    $query->where('arrival_location', 'like', "%{$regencity}%");
                } else {
                    $query->where('arrival_terminal', 'like', "%{$this->search['arrival_id']}%");
                }
            })
            ->when($this->filters['status'], function ($query) {
                $query->where('status', $this->filters['status']);
            })
            ->when($this->filters['payment_method'], function ($query) {
                $query->where('payment_method', $this->filters['payment_method']);
            })
            ->when($this->sortBy, function ($query) {
                $query->orderBy($this->sortBy, $this->sortDirection);
            })
            ->paginate($this->perPage);
    }

    #[Computed]
    public function getStatusOptions()
    {
        return [
            'pending' => 'Pending',
            'success' => 'Success',
            'cancelled' => 'Cancelled',
            'failed' => 'Failed'
        ];
    }
    
    #[Computed]
    public function getPaymentMethodOptions()
    {
        return [
            'bank_transfer' => 'Bank Transfer',
            'credit_card' => 'Credit Card',
            'e_wallet' => 'E-Wallet'
        ];
    }
    
    #[Computed]
    public function getTerminals()
    {
        return Terminal::orderBy('name')->get();
    }
    
    public function sort($column) {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
        
        $this->validatePage();
        
        session()->put('transactions.sortBy', $this->sortBy);
        session()->put('transactions.sortDirection', $this->sortDirection);
    }

    #[Computed]
    public function getTotalOrders()
    {
        return Order::where('users_id', Auth::id())->count();
    }

    #[Computed]
    public function getTotalOrderedSeats()
    {
        return Order::where('users_id', Auth::id())->sum('quantity');
    }

    #[Computed]
    public function getTotalSpent()
    {
        return Order::where('users_id', Auth::id())
            ->where('status', 'success') // Only count successful orders
            ->sum('total_cost');
    }
}; ?>

<div>
    <div><flux:heading size="xl">Welcome back {{ Auth::user()->name }}!</flux:heading></div><br>
    <div class="grid auto-rows-min gap-4 grid-cols-3">
        <flux:card class="items-center overflow-hidden">
            <flux:text>Total Order</flux:text>
            <flux:heading size="xl" class="mt-2 tabular-nums inline-flex items-center">{{ $this->getTotalOrders }}</flux:heading>
        </flux:card>
        <flux:card class="items-center overflow-hidden">
            <flux:text>Total Ordered Seats</flux:text>
            <flux:heading size="xl" class="mt-2 tabular-nums inline-flex items-center">{{ $this->getTotalOrderedSeats }}</flux:heading>
        </flux:card>
        <flux:card class="items-center overflow-hidden">
            <flux:text>Total Spent</flux:text>
            <flux:heading size="xl" class="mt-2 tabular-nums inline-flex items-center">{{ number_format($this->getTotalSpent, 2) }}</flux:heading>
        </flux:card>
    </div>
    <br>
    <div class="flex items-center justify-between mb-4">
        <div><flux:heading size="xl">My Transactions</flux:heading></div>
        <div class="flex items-center gap-4">
            <div class="hidden sm:block">
                <flux:button type="button" wire:click="$toggle('showFilters')">
                    <span x-show="!$wire.showFilters"><flux:icon.funnel/></span>
                    <span x-show="$wire.showFilters"><flux:icon.funnel variant="solid"/></span>
                </flux:button>
            </div>
            <div class="hidden sm:block">
                <flux:select wire:model.live="perPage">
                    <option value="5">5 per page</option>
                    <option value="10">10 per page</option>
                    <option value="20">20 per page</option>
                    <option value="50">50 per page</option>
                </flux:select>
            </div>
        </div>
    </div>
    
    <div class="flex items-center justify-between mb-4 sm:hidden">
        <div>
            <flux:button type="button" wire:click="$toggle('showFilters')">
                <span x-show="!$wire.showFilters"><flux:icon.funnel/></span>
                <span x-show="$wire.showFilters"><flux:icon.funnel variant="solid"/></span>
            </flux:button>
        </div>
        <div class="flex items-center gap-4">
            <div>
                <flux:select wire:model.live="perPage">
                    <option value="5">5 per page</option>
                    <option value="10">10 per page</option>
                    <option value="20">20 per page</option>
                    <option value="50">50 per page</option>
                </flux:select>
            </div>
        </div>
    </div>
    
    <div x-data="{ show: $wire.entangle('showFilters') }"
         x-show="show"
         x-collapse
         class="overflow-hidden">
        <div class="mb-6 p-4 outline outline-offset-[-1px] rounded-lg shadow">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <flux:label>Departure Terminal</flux:label>
                    <flux:select variant="combobox" wire:model.live="search.departure_id">
                        <flux:select.option value="">All Terminals</flux:select.option>
                        @foreach($this->getTerminals->groupBy('regencity') as $regencity => $terminals)
                            <flux:select.option value="regencity_{{ $regencity }}">{{ $regencity }}, All terminals</flux:select.option>
                            @foreach($terminals as $terminal)
                                <flux:select.option value="{{ $terminal->name }}">{{ $terminal->regencity }}, {{ $terminal->name }}</flux:select.option>
                            @endforeach
                        @endforeach
                    </flux:select>
                </div>
                
                <div>
                    <flux:label>Arrival Terminal</flux:label>
                    <flux:select variant="combobox" wire:model.live="search.arrival_id">
                        <flux:select.option value="">All Terminals</flux:select.option>
                        @foreach($this->getTerminals->groupBy('regencity') as $regencity => $terminals)
                            <flux:select.option value="regencity_{{ $regencity }}">{{ $regencity }}, All terminals</flux:select.option>
                            @foreach($terminals as $terminal)
                                <flux:select.option value="{{ $terminal->name }}">{{ $terminal->regencity }}, {{ $terminal->name }}</flux:select.option>
                            @endforeach
                        @endforeach
                    </flux:select>
                </div>
                
                <div>
                    <flux:label>Status</flux:label>
                    <flux:select wire:model.live="filters.status">
                        <option value="">All Statuses</option>
                        @foreach($this->getStatusOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </flux:select>
                </div>
                
                <div>
                    <flux:label>Payment Method</flux:label>
                    <flux:select wire:model.live="filters.payment_method">
                        <option value="">All Methods</option>
                        @foreach($this->getPaymentMethodOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </flux:select>
                </div>
            </div>
            
            <div class="mt-4 flex justify-end">
                <flux:button type="button" wire:click="resetFilters">
                    Reset Filters
                </flux:button>
            </div>
        </div>
    </div>
    
    @if($this->getOrders()->count())
    <flux:table :paginate="$this->getOrders()">
        <flux:table.columns>
            <flux:table.column class="text-center">No.</flux:table.column>
            <flux:table.column class="text-center">Actions</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'code'" :direction="$sortDirection" wire:click="sort('code')">Order Code</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')">Status</flux:table.column>
            <flux:table.column>Departure Terminal</flux:table.column>
            <flux:table.column>Arrival Terminal</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'quantity'" :direction="$sortDirection" wire:click="sort('quantity')">Quantity</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'total_cost'" :direction="$sortDirection" wire:click="sort('total_cost')">Total Cost</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'payment_method'" :direction="$sortDirection" wire:click="sort('payment_method')">Payment Method</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'created_at'" :direction="$sortDirection" wire:click="sort('created_at')">Order Date</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($this->getOrders() as $order)
            <flux:table.row>
                <flux:table.cell class="text-center">{{ ($this->getOrders()->currentPage() - 1) * $this->getOrders()->perPage() + $loop->iteration }}.</flux:table.cell>
                <flux:table.cell class="text-center">
                    <div class="flex items-center justify-start gap-2">
                        <flux:button 
                            icon="eye" 
                            :href="route('booking', ['id' => $order->id])" 
                            wire:navigate
                        ></flux:button>
                        @if ($order->status === 'pending')
                        <flux:button 
                            icon="pencil" 
                            variant="primary" 
                            :href="route('booking.edit', ['id' => $order->id])" 
                            wire:navigate
                        ></flux:button>
                        <flux:button 
                            icon="x-circle" 
                            variant="danger" 
                            wire:click="confirmCancel({{ $order->id }})"
                        ></flux:button>
                        @endif
                        @if ($order->payment_proof)
                        <flux:button 
                            icon="credit-card" 
                            variant="ghost"
                            href="{{ route('payment-proof', [
                                    'orderId' => $order->id,
                                    'filename' => basename($order->payment_proof)
                                ]) }}" 
                            target="_blank"
                        ></flux:button>
                        @endif
                    </div>
                </flux:table.cell>
                <flux:table.cell>{{ $order->code }}</flux:table.cell>
                <flux:table.cell>
                    <flux:badge variant="solid" :color="match($order->status) {
                        'success' => 'lime',
                        'pending' => 'yellow',
                        'cancelled' => 'zinc',
                        'failed' => 'red',
                        default => 'zinc'
                    }">
                        {{ $this->getStatusOptions[$order->status] ?? $order->status }}
                    </flux:badge>
                </flux:table.cell>
                <flux:table.cell>{{ $order->departure_terminal }}</flux:table.cell>
                <flux:table.cell>{{ $order->arrival_terminal }}</flux:table.cell>
                <flux:table.cell class="text-center">{{ $order->quantity }}</flux:table.cell>
                <flux:table.cell>{{ number_format($order->total_cost, 2) }}</flux:table.cell>
                <flux:table.cell>{{ $this->getPaymentMethodOptions[$order->payment_method] ?? $order->payment_method }}</flux:table.cell>
                <flux:table.cell>{{ $order->created_at->format('Y-m-d H:i') }}</flux:table.cell>
            </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <flux:modal name="cancel-order-modal" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Cancel this order?</flux:heading>
                <flux:text class="mt-2">
                    @if($this->orderToCancel)
                        <p>You're about to cancel order <strong>{{ $this->orderToCancel->code }}</strong>.</p>
                        <p>This action cannot be undone.</p>
                    @endif
                </flux:text>
            </div>

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">No, Keep It</flux:button>
                </flux:modal.close>
                <flux:button 
                    variant="danger" 
                    wire:click="cancelOrder"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove>Yes, Cancel Order</span>
                    <span wire:loading>Cancelling...</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
    @else
    <div class="text-center py-8">
        <div class="text-gray-400 mb-4">
            <flux:icon.magnifying-glass class="w-12 h-12 mx-auto" />
        </div>
        <p class="text-neutral-600 dark:text-neutral-400 text-lg">No transactions found matching your criteria.</p>
        <div class="mt-4">
            <flux:button wire:click="resetFilters">Reset Filters</flux:button>
        </div>
    </div>
    @endif
</div>