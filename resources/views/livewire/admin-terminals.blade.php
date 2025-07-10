<?php
use Livewire\Attributes\{Layout, Title, Computed, Url};
use Livewire\WithPagination;
use Livewire\Volt\Component;
use App\Models\Terminal;
use Flux\Flux;

new #[Layout('components.layouts.admin')]
    #[Title('Terminals')]
class extends Component {
    use WithPagination;
    
    public bool $showFilters = false;
    public ?Terminal $terminalToDelete = null;
    public $filters = [
        'name' => '',
        'code' => '',
        'regencity' => '',
        'province' => '',
        'status' => '',
    ];
    public $sortBy = 'id';
    public $sortDirection = 'asc';
    public $perPage = 10;
    
    public function mount()
    {
        $this->filters = session()->get('terminals.filters', $this->filters);
        $this->sortBy = session()->get('terminals.sortBy', $this->sortBy);
        $this->sortDirection = session()->get('terminals.sortDirection', $this->sortDirection);
        $this->perPage = session()->get('terminals.perPage', $this->perPage);
        
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
        $this->resetPage();
        $this->validatePage();
        session()->put('terminals.perPage', $value);
    }
    
    public function updatedFilters()
    {
        $this->resetPage();
        session()->put('terminals.filters', $this->filters);
    }
    
    public function resetFilters()
    {
        $this->reset('filters');
        session()->forget('terminals.filters');
        $this->resetPage();
        session()->put('terminals.page', 1);
    }
    
    public function validatePage()
    {
        $terminals = $this->getTerminals();
        
        if ($terminals->currentPage() > $terminals->lastPage()) {
            $this->setPage($terminals->lastPage());
        }
    }
    
    #[Computed]
    public function getTerminals()
    {
        return Terminal::query()
            ->when($this->filters['name'], function ($query) {
                $query->where('name', 'like', '%' . $this->filters['name'] . '%');
            })
            ->when($this->filters['code'], function ($query) { // Added code filter
                $query->where('code', 'like', '%' . $this->filters['code'] . '%');
            })
            ->when($this->filters['province'], function ($query) {
                $query->where('province', 'like', '%' . $this->filters['province'] . '%');
            })
            ->when($this->filters['regencity'], function ($query) {
                $query->where('regencity', 'like', '%' . $this->filters['regencity'] . '%');
            })
            ->when($this->filters['status'], function ($query) {
                $query->where('status', $this->filters['status']);
            })
            ->when($this->sortBy, function ($query) {
                $query->orderBy($this->sortBy, $this->sortDirection);
            })
            ->paginate($this->perPage);
    }

    public function confirmDelete($terminalId)
    {
        $this->terminalToDelete = Terminal::find($terminalId);
        Flux::modal('delete-terminal-modal')->show();
    }
    
    public function deleteTerminal()
    {
        if (!$this->terminalToDelete) {
            $this->dispatch('toast', message: 'Terminal not found', type: 'error');
            return;
        }
        
        try {
            $this->terminalToDelete->delete();
            
            Flux::toast(
                variant: 'success',
                heading: 'Terminal Deleted.',
                text: 'Terminal successfully deleted.',
            );
            
            $this->terminalToDelete = null;
            
            Flux::modal('delete-terminal-modal')->close();
            

            $this->resetPage();
            unset($this->getTerminals);
            
        } catch (\Exception $e) {
            $this->dispatch('toast', message: 'Failed to delete terminal: ' . $e->getMessage(), type: 'error');
        }
    }
    
    #[Computed]
    public function getProvinces()
    {
        return Terminal::select('province')
            ->whereNotNull('province')
            ->distinct()
            ->orderBy('province')
            ->pluck('province');
    }

    #[Computed]
    public function getRegencity()
    {
        return Terminal::select('regencity')
            ->whereNotNull('regencity')
            ->distinct()
            ->orderBy('regencity')
            ->pluck('regencity');
    }
    
    #[Computed]
    public function getStatusOptions()
    {
        return [
            'unknown' => 'Unknown',
            'operational' => 'Operational',
            'maintenance' => 'Maintenance',
            'unavailable' => 'Unavailable'
        ];
    }
    
    public function sort($column) {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
        
        $this->validatePage();

        session()->put('terminals.sortBy', $this->sortBy);
        session()->put('terminals.sortDirection', $this->sortDirection);
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-4">
        <div><flux:heading size="xl">Terminals</flux:heading></div>
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
                    <option value="100">100 per page</option>
                </flux:select>
            </div>
            <div><livewire:admin-terminal-create/></div>
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
                    <div>
                        <flux:label>Terminal Name</flux:label>
                        <flux:input wire:model.live="filters.name" placeholder="Search by name..." />
                    </div>
                </div>
                <div>
                    <flux:label>Terminal Code</flux:label>
                    <flux:input wire:model.live="filters.code" placeholder="Search by code..." />
                </div>
                <div>
                    <flux:label>Regency/City</flux:label>
                    <flux:select wire:model.live="filters.regencity">
                        <option value="">All Provinces</option>
                        @foreach($this->getRegencity as $regencity)
                            <option value="{{ $regencity }}">{{ $regencity }}</option>
                        @endforeach
                    </flux:select>
                </div>
                <div>
                    <flux:label>Province</flux:label>
                    <flux:select wire:model.live="filters.province">
                        <option value="">All Provinces</option>
                        @foreach($this->getProvinces as $province)
                            <option value="{{ $province }}">{{ $province }}</option>
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
            </div>
            
            <div class="mt-4 flex justify-end">
                <flux:button type="button" wire:click="resetFilters">
                    Reset Filters
                </flux:button>
            </div>
        </div>
    </div>
    
    <br>
    @if($this->getTerminals()->count())
    <flux:table :paginate="$this->getTerminals()">
        <flux:table.columns>
            <flux:table.column class="text-center">No.</flux:table.column>
            <flux:table.column class="text-center">Actions</flux:table.column>
            <flux:table.column class="text-center" sortable :sorted="$sortBy === 'id'" :direction="$sortDirection" wire:click="sort('id')">ID</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'code'" :direction="$sortDirection" wire:click="sort('code')">Code</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')">Status</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">Name</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'email'" :direction="$sortDirection" wire:click="sort('email')">Email</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'phone'" :direction="$sortDirection" wire:click="sort('phone')">Phone</flux:table.column>
            <flux:table.column>Address</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'regencity'" :direction="$sortDirection" wire:click="sort('regencity')">Regency/City</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'province'" :direction="$sortDirection" wire:click="sort('province')">Province</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($this->getTerminals() as $terminal)
            <flux:table.row>
                <flux:table.cell class="text-center">{{ ($this->getTerminals()->currentPage() - 1) * $this->getTerminals()->perPage() + $loop->iteration }}.</flux:table.cell>
                <flux:table.cell class="text-center">
                    <div class="flex items-center justify-center gap-2">
                        <flux:button 
                            icon="trash" 
                            variant="danger"
                            wire:click="confirmDelete({{ $terminal->id }})"
                        ></flux:button>
                        <flux:button icon="pencil" variant="primary" :href="route('admin.edit.terminal', ['id' => $terminal->id])" wire:navigate></flux:button>
                        <flux:button icon="eye" :href="route('admin.view.terminal', ['id' => $terminal->id])" wire:navigate></flux:button>
                    </div>
                </flux:table.cell>
                <flux:table.cell>({{$terminal->id}})</flux:table.cell>
                <flux:table.cell>{{$terminal->code}}</flux:table.cell>
                <flux:table.cell>
                    <flux:badge variant="solid" :color="match($terminal->status) {
                        'operational' => 'lime',
                        'maintenance' => 'yellow',
                        'unavailable' => 'zinc',
                        default => 'zinc'
                    }">
                        {{ $this->getStatusOptions[$terminal->status] ?? $terminal->status }}
                    </flux:badge>
                </flux:table.cell>
                <flux:table.cell>{{$terminal->name}}</flux:table.cell>
                <flux:table.cell>{{$terminal->email}}</flux:table.cell>
                <flux:table.cell>{{$terminal->phone}}</flux:table.cell>
                <flux:table.cell>{{$terminal->address}}</flux:table.cell>
                <flux:table.cell>{{$terminal->regencity}}</flux:table.cell>
                <flux:table.cell>{{$terminal->province}}</flux:table.cell>
            </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
<flux:modal name="delete-terminal-modal" class="min-w-[22rem]">
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">Delete terminal?</flux:heading>
            <flux:text class="mt-2">
                @if($this->terminalToDelete)
                    <p>You're about to delete <strong>{{ $this->terminalToDelete->name }}</strong> ({{ $this->terminalToDelete->email }}).</p>
                    <p>This action cannot be undone.</p>
                @endif
            </flux:text>
        </div>

        <div class="flex gap-2">
            <flux:spacer />
            <flux:modal.close>
                <flux:button variant="ghost">Cancel</flux:button>
            </flux:modal.close>
            <flux:button 
                variant="danger" 
                wire:click="deleteTerminal"
                wire:loading.attr="disabled"
            >
                <span wire:loading.remove>Delete Terminal</span>
                <span wire:loading>Deleting...</span>
            </flux:button>
        </div>
    </div>
</flux:modal>
    @else
    <div class="text-center py-8">
        <p class="text-neutral-600 dark:text-neutral-400">No terminals found. You've been redirected to the last available page.</p>
    </div>
    @endif
</div>