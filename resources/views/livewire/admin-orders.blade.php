<?php
use Livewire\Attributes\{Layout, Title, Computed, Url};
use Livewire\WithPagination;
use Livewire\Volt\Component;
use App\Exports\OrdersExport;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use App\Models\{Order, Terminal, User};
use Flux\Flux;

new #[Layout('components.layouts.admin')]
    #[Title('Orders Management')]
class extends Component {
    use WithPagination;
    
    public bool $showFilters = false;
    public ?Order $orderToChangeStatus = null;
    public ?Order $orderToEditComment = null;
    public string $newStatus = '';
    public string $newComment = '';
    public $search = [
        'departure_id' => '',
        'arrival_id' => '',
        'user' => '',
        'code' => '',
    ];
    public $filters = [
        'status' => '',
        'payment_method' => '',
        'after_date' => '',
        'before_date' => '',
    ];
    public $sortBy = 'created_at';
    public $sortDirection = 'desc';
    public $perPage = 10;
    
    public function mount()
    {
        $this->search = session()->get('orders.search', $this->search);
        
        $this->filters = session()->get('orders.filters', $this->filters);
        
        $this->sortBy = session()->get('orders.sortBy', $this->sortBy);
        
        $this->sortDirection = session()->get('orders.sortDirection', $this->sortDirection);
        
        $this->perPage = session()->get('orders.perPage', $this->perPage);
        
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
    
    public function updatedPerPage($value)
    {
        session()->put('orders.perPage', $value);
        $this->resetPage();
        $this->validatePage();
    }
    
    public function updatedSearch($value, $key)
    {
        session()->put('orders.search', $this->search);
        $this->resetPage();
    }
    
    public function updatedFilters($value, $key)
    {
        session()->put('orders.filters', $this->filters);
        $this->resetPage();
    }
    
    public function resetFilters()
    {
        $this->reset('search');
        $this->reset('filters');
        session()->forget('orders.search');
        session()->forget('orders.filters');
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
            ->with(['user', 'seats'])
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
            ->when($this->search['user'], function ($query) {
                $query->whereHas('user', function($q) {
                    $q->Where('email', 'like', '%'.$this->search['user'].'%');
                });
            })
            ->when($this->search['code'], function ($query) {
                $query->where('code', 'like', '%'.$this->search['code'].'%');
            })
            ->when($this->filters['status'], function ($query) {
                $query->where('status', $this->filters['status']);
            })
            ->when($this->filters['payment_method'], function ($query) {
                $query->where('payment_method', $this->filters['payment_method']);
            })
            ->when($this->filters['after_date'], function ($query) {
                $query->whereDate('created_at', '>=', $this->filters['after_date']);
            })
            ->when($this->filters['before_date'], function ($query) {
                $query->whereDate('created_at', '<=', $this->filters['before_date']);
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
        
        session()->put('orders.sortBy', $this->sortBy);
        session()->put('orders.sortDirection', $this->sortDirection);
        
        $this->validatePage();
    }
    
    public function confirmStatusChange($orderId)
    {
        $this->orderToChangeStatus = Order::find($orderId);
        $this->newStatus = $this->orderToChangeStatus->status;
        Flux::modal('change-status-modal')->show();
    }
    
    public function updateOrderStatus()
    {
        $this->validate([
            'newStatus' => 'required|in:pending,success,cancelled,failed'
        ]);
        
        if (!$this->orderToChangeStatus) {
            $this->dispatch('toast', message: 'Order not found', type: 'error');
            return;
        }
        
        try {
            $this->orderToChangeStatus->update(['status' => $this->newStatus]);
            
            Flux::toast(
                variant: 'success',
                heading: 'Order Status Updated',
                text: 'Order status has been successfully updated.',
            );
            
            $this->orderToChangeStatus = null;
            Flux::modal('change-status-modal')->close();
            
            $this->resetPage();
            unset($this->getOrders);
            
        } catch (\Exception $e) {
            $this->dispatch('toast', message: 'Failed to update order status: ' . $e->getMessage(), type: 'error');
        }
    }
    
    public function editComment($orderId)
    {
        $this->orderToEditComment = Order::find($orderId);
        $this->newComment = $this->orderToEditComment->comments ?? '';
        Flux::modal('edit-comment-modal')->show();
    }
    
    public function updateComment()
    {
        $this->validate([
            'newComment' => 'nullable|string|max:500'
        ]);
        
        if (!$this->orderToEditComment) {
            $this->dispatch('toast', message: 'Order not found', type: 'error');
            return;
        }
        
        try {
            $this->orderToEditComment->update(['comments' => $this->newComment]);
            
            Flux::toast(
                variant: 'success',
                heading: 'Comment Updated',
                text: 'Order comment has been successfully updated.',
            );
            
            $this->orderToEditComment = null;
            Flux::modal('edit-comment-modal')->close();
            
            $this->resetPage();
            unset($this->getOrders);
            
        } catch (\Exception $e) {
            $this->dispatch('toast', message: 'Failed to update comment: ' . $e->getMessage(), type: 'error');
        }
    }

    public function export(): BinaryFileResponse
    {
        return Excel::download(
            new OrdersExport($this->search, $this->filters, $this->sortBy, $this->sortDirection),
            'orders-export-' . now()->format('Y-m-d') . '.xlsx'
        );
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-4">
        <div><flux:heading size="xl">Orders Management</flux:heading></div>
        <div class="flex items-center gap-4">
            <div class="hidden sm:block">
                <flux:button type="button" wire:click="$toggle('showFilters')">
                    <span x-show="!$wire.showFilters"><flux:icon.funnel/></span>
                    <span x-show="$wire.showFilters"><flux:icon.funnel variant="solid"/></span>
                </flux:button>
            </div>
            <div class="hidden sm:block">
                <flux:button 
                    variant="primary" 
                    wire:click="export"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove>
                        <flux:icon.arrow-down-tray class="w-4 h-4" />
                    </span>
                    <span wire:loading>
                        <flux:icon.arrow-path class="w-4 h-4 animate-spin" />
                    </span>
                </flux:button>
            </div>
            <div class="hidden sm:block">
                <flux:select wire:model.live="perPage">
                    <option value="5">5 per page</option>
                    <option value="10">10 per page</option>
                    <option value="20">20 per page</option>
                    <option value="50">50 per page</option>
                    <option value="100">100 per page</option>
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
                <flux:button 
                    variant="primary" 
                    wire:click="export"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove>
                        <flux:icon.arrow-down-tray class="w-4 h-4" />
                    </span>
                    <span wire:loading>
                        <flux:icon.arrow-path class="w-4 h-4 animate-spin" />
                    </span>
                </flux:button>
            </div>
            <div>
                <flux:select wire:model.live="perPage">
                    <option value="5">5 per page</option>
                    <option value="10">10 per page</option>
                    <option value="20">20 per page</option>
                    <option value="50">50 per page</option>
                    <option value="100">100 per page</option>
                </flux:select>
            </div>
        </div>
    </div>
    
    <div x-data="{ show: $wire.entangle('showFilters') }"
         x-show="show"
         x-collapse
         class="overflow-hidden">
        <div class="p-4 outline outline-offset-[-1px] rounded-lg shadow">
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
                    <flux:label>Customer</flux:label>
                    <flux:input wire:model.live="search.user" placeholder="Search by email..." />
                </div>
                
                <div>
                    <flux:label>Order Code</flux:label>
                    <flux:input wire:model.live="search.code" placeholder="Search by order code..." />
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
                
                <div>
                    <flux:label>After Date</flux:label>
                    <flux:input type="date" wire:model.live="filters.after_date" />
                </div>
                
                <div>
                    <flux:label>Before Date</flux:label>
                    <flux:input type="date" wire:model.live="filters.before_date" />
                </div>
            </div>
            
            <div class="mt-4 flex justify-end">
                <flux:button type="button" wire:click="resetFilters">
                    Reset Filters
                </flux:button>
            </div>
        </div>
    </div>
    
    <br>
    @if($this->getOrders()->count())
    <flux:table :paginate="$this->getOrders()">
        <flux:table.columns>
            <flux:table.column class="text-center">No.</flux:table.column>
            <flux:table.column class="text-center">Actions</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'code'" :direction="$sortDirection" wire:click="sort('code')">Order Code</flux:table.column>
            <flux:table.column>Customer</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')">Status</flux:table.column>
            <flux:table.column>Departure Terminal</flux:table.column>
            <flux:table.column>Arrival Terminal</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'quantity'" :direction="$sortDirection" wire:click="sort('quantity')">Seats</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'total_cost'" :direction="$sortDirection" wire:click="sort('total_cost')">Total Cost</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'payment_method'" :direction="$sortDirection" wire:click="sort('payment_method')">Payment Method</flux:table.column>
            <flux:table.column>Comments</flux:table.column>
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
                            :href="route('admin.booking', ['id' => $order->id])" 
                            wire:navigate
                        ></flux:button>
                        <flux:button 
                            icon="chat-bubble-left-ellipsis" 
                            variant="primary" 
                            wire:click="editComment({{ $order->id }})"
                        ></flux:button>
                        <flux:button 
                            icon="pencil" 
                            variant="primary" 
                            wire:click="confirmStatusChange({{ $order->id }})"
                        ></flux:button>
                        @if ($order->payment_proof)
                        <flux:button 
                            icon="credit-card" 
                            variant="ghost"
                            href="{{ route('admin-payment-proof', [
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
                    {{ $order->user->email ?? 'N/A' }}<br>
                    <small class="text-neutral-600 dark:text-neutral-400">{{ $order->user->name ?? '' }}</small>
                </flux:table.cell>
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
                <flux:table.cell class="text-center">{{ $order->seats->count() }}</flux:table.cell>
                <flux:table.cell>{{ number_format($order->total_cost, 2) }}</flux:table.cell>
                <flux:table.cell>{{ $this->getPaymentMethodOptions[$order->payment_method] ?? $order->payment_method }}</flux:table.cell>
                <flux:table.cell>
                    @if($order->comments)
                        <div class="line-clamp-2" title="{{ $order->comments }}">
                            {{ $order->comments }}
                        </div>
                    @else
                        <span class="text-gray-400">No comments</span>
                    @endif
                </flux:table.cell>
                <flux:table.cell>{{ $order->created_at->format('Y-m-d H:i') }}</flux:table.cell>
            </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <flux:modal name="change-status-modal" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Change Order Status</flux:heading>
                <flux:text class="mt-2">
                    @if($this->orderToChangeStatus)
                        <p>You're about to change status for order <strong>{{ $this->orderToChangeStatus->code }}</strong>.</p>
                    @endif
                </flux:text>
                
                <div class="mt-4">
                    <flux:radio.group wire:model="newStatus" label="Select new status">
                        @foreach($this->getStatusOptions as $value => $label)
                            <flux:radio 
                                value="{{ $value }}" 
                                label="{{ $label }}" 
                                :checked="$value === ($this->orderToChangeStatus->status ?? '')" 
                            />
                        @endforeach
                    </flux:radio.group>
                </div>
            </div>

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button 
                    variant="primary" 
                    wire:click="updateOrderStatus"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove>Update Status</span>
                    <span wire:loading>Updating...</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="edit-comment-modal" class="min-w-[30rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Edit Order Comment</flux:heading>
                <flux:text class="mt-2">
                    @if($this->orderToEditComment)
                        <p>Editing comment for order <strong>{{ $this->orderToEditComment->code }}</strong>.</p>
                    @endif
                </flux:text>
                
                <div class="mt-4">
                    <flux:label>Comment</flux:label>
                    <flux:textarea 
                        wire:model="newComment" 
                        placeholder="Enter order comments..."
                        rows="4"
                    ></flux:textarea>
                    <flux:text class="text-sm text-neutral-600 dark:text-neutral-400 mt-1">Max 500 characters</flux:text>
                </div>
            </div>

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button 
                    variant="primary" 
                    wire:click="updateComment"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove>Update Comment</span>
                    <span wire:loading>Updating...</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
    @else
    <div class="text-center py-8">
        <p class="text-neutral-600 dark:text-neutral-400">No routes schedules found. You've been redirected to the last available page.</p>
    </div>
    @endif
</div>