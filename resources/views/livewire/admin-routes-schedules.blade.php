<?php
use Livewire\Attributes\{Layout, Title, Computed, Url};
use Livewire\WithPagination;
use Livewire\Volt\Component;
use App\Models\RoutesSchedule;
use App\Models\Terminal;
use App\Models\Bus;
use Flux\Flux;

new #[Layout('components.layouts.admin')]
    #[Title('Routes Schedules')]
class extends Component {
    use WithPagination;
    
    public bool $showFilters = false;
    public ?RoutesSchedule $scheduleToDelete = null;
    public $filters = [
        'status' => '',
        'code' => '',
        'departure_id' => '',
        'arrival_id' => '',
        'buses_id' => ''
    ];
    public $sortBy = 'departure_time';
    public $sortDirection = 'asc';
    public $perPage = 10;
    
    public function mount()
    {
        $this->filters = session()->get('routes_schedules.filters', $this->filters);
        
        $this->sortBy = session()->get('routes_schedules.sortBy', $this->sortBy);
        
        $this->sortDirection = session()->get('routes_schedules.sortDirection', $this->sortDirection);
        
        $this->perPage = session()->get('routes_schedules.perPage', $this->perPage);
        
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
        session()->put('routes_schedules.perPage', $value);
        $this->resetPage();
        $this->validatePage();
    }
    
    public function updatedFilters($value, $key)
    {
        session()->put('routes_schedules.filters', $this->filters);
        $this->resetPage();
    }
    
    public function resetFilters()
    {
        $this->reset('filters');
        session()->forget('routes_schedules.filters');
        $this->resetPage();
        session()->put('terminals.page', 1);
    }
    
    public function validatePage()
    {
        $schedules = $this->getSchedules();
        
        if ($schedules->currentPage() > $schedules->lastPage()) {
            $this->setPage($schedules->lastPage());
        }
    }
    
    #[Computed]
    public function getSchedules()
    {
        return RoutesSchedule::query()
            ->with(['bus', 'departureTerminal', 'arrivalTerminal'])
            ->when($this->filters['status'], function ($query) {
                $query->where('status', $this->filters['status']);
            })
            ->when($this->filters['departure_id'], function ($query) {
                $query->where('departure_id', $this->filters['departure_id']);
            })
            ->when($this->filters['arrival_id'], function ($query) {
                $query->where('arrival_id', $this->filters['arrival_id']);
            })
            ->when($this->filters['buses_id'], function ($query) {
                $query->where('buses_id', $this->filters['buses_id']);
            })
            ->when($this->sortBy, function ($query) {
                $query->orderBy($this->sortBy, $this->sortDirection);
            })
            ->when($this->filters['code'], function ($query) { // Added code filter
                $query->where('code', 'like', '%' . $this->filters['code'] . '%');
            })
            ->paginate($this->perPage);
    }

    public function confirmDelete($scheduleId)
    {
        $this->scheduleToDelete = RoutesSchedule::find($scheduleId);
        Flux::modal('delete-schedule-modal')->show();
    }
    
    public function deleteSchedule()
    {
        if (!$this->scheduleToDelete) {
            $this->dispatch('toast', message: 'Schedule not found', type: 'error');
            return;
        }
        
        try {
            $this->scheduleToDelete->delete();
            
            Flux::toast(
                variant: 'success',
                heading: 'Schedule Deleted.',
                text: 'Route schedule successfully deleted.',
            );
            
            $this->scheduleToDelete = null;
            Flux::modal('delete-schedule-modal')->close();
            
            $this->resetPage();
            unset($this->getSchedules);
            
        } catch (\Exception $e) {
            $this->dispatch('toast', message: 'Failed to delete schedule: ' . $e->getMessage(), type: 'error');
        }
    }
    
    #[Computed]
    public function getTerminals()
    {
        return Terminal::orderBy('name')->get();
    }
    
    #[Computed]
    public function getBuses()
    {
        return Bus::orderBy('name')->get();
    }
    
    public function sort($column) {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
        
        session()->put('routes_schedules.sortBy', $this->sortBy);
        session()->put('routes_schedules.sortDirection', $this->sortDirection);
        
        $this->validatePage();
    }

    public function calculateDuration($departure, $arrival)
    {
        $diff = $departure->diff($arrival);
        
        $hours = $diff->h;
        $minutes = $diff->i;
        
        if ($diff->d > 0) {
            $hours += $diff->d * 24;
        }
        
        return sprintf('%dh %dm', $hours, $minutes);
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-4">
        <div><flux:heading size="xl">Routes Schedules</flux:heading></div>
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
            <div><livewire:admin-routes-schedule-create/></div>
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
                    <flux:label>Terminal Code</flux:label>
                    <flux:input wire:model.live="filters.code" placeholder="Search by code..." />
                </div>
                
                <div>
                    <flux:label>Status</flux:label>
                    <flux:select wire:model.live="filters.status">
                        <option value="">All Statuses</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </flux:select>
                </div>
                
                <div>
                    <flux:label>Departure Terminal</flux:label>
                    <flux:select variant="combobox" wire:model.live="filters.departure_id">
                        <flux:select.option value="">All Terminals</flux:select.option>
                        @foreach($this->getTerminals as $terminal)
                            <flux:select.option value="{{ $terminal->id }}">{{ $terminal->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
                
                <div>
                    <flux:label>Arrival Terminal</flux:label>
                    <flux:select variant="combobox" wire:model.live="filters.arrival_id">
                        <flux:select.option value="">All Terminals</flux:select.option>
                        @foreach($this->getTerminals as $terminal)
                            <flux:select.option value="{{ $terminal->id }}">{{ $terminal->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
                
                <div>
                    <flux:label>Bus</flux:label>
                    <flux:select variant="combobox" wire:model.live="filters.buses_id">
                        <flux:select.option value="">All Buses</flux:select.option>
                        @foreach($this->getBuses as $bus)
                            <flux:select.option value="{{ $bus->id }}">{{ $bus->name }}</flux:select.option>
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
    @if($this->getSchedules()->count())
    <flux:table :paginate="$this->getSchedules()">
        <flux:table.columns>
            <flux:table.column class="text-center">No.</flux:table.column>
            <flux:table.column class="text-center">Actions</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'code'" :direction="$sortDirection" wire:click="sort('code')">Code</flux:table.column>
            <flux:table.column>Bus</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'price'" :direction="$sortDirection" wire:click="sort('price')">Price</flux:table.column>
            <flux:table.column>Departure Terminal</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'departure_time'" :direction="$sortDirection" wire:click="sort('departure_time')">Departure Time</flux:table.column>
            <flux:table.column>Arrival Terminal</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'arrival_time'" :direction="$sortDirection" wire:click="sort('arrival_time')">Arrival Time</flux:table.column>
            <flux:table.column>Estimation Time</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($this->getSchedules() as $schedule)
            <flux:table.row>
                <flux:table.cell class="text-center">{{ ($this->getSchedules()->currentPage() - 1) * $this->getSchedules()->perPage() + $loop->iteration }}.</flux:table.cell>
                <flux:table.cell class="text-center">
                    <div class="flex items-center justify-center gap-2">
                        <flux:button 
                            icon="trash" 
                            variant="danger"
                            wire:click="confirmDelete({{ $schedule->id }})"
                        ></flux:button>
                        <flux:button icon="pencil" variant="primary" :href="route('admin.edit.routes-schedule', ['id' => $schedule->id])" wire:navigate></flux:button>
                        <flux:button icon="eye" :href="route('admin.view.routes-schedule', ['id' => $schedule->id])" wire:navigate></flux:button>
                    </div>
                </flux:table.cell>
                <flux:table.cell>{{ $schedule->code }}</flux:table.cell>
                <flux:table.cell>{{ $schedule->bus?->name ?? 'N/A' }}</flux:table.cell>
                <flux:table.cell>
                    <flux:badge  :color="match($schedule->status) {
                        'operational' => 'lime',
                        'maintenance' => 'yellow',
                        'unavailable' => 'zinc',
                        default => 'zinc'
                    }">
                        {{ ucfirst($schedule->status) }}
                    </flux:badge>
                </flux:table.cell>
                <flux:table.cell>{{ number_format($schedule->price, 2) }}</flux:table.cell>
                <flux:table.cell>{{ $schedule->departureTerminal?->name ?? 'N/A' }}</flux:table.cell>
                <flux:table.cell>{{ $schedule->departure_time->format('Y-m-d H:i') }}</flux:table.cell>
                <flux:table.cell>{{ $schedule->arrivalTerminal?->name ?? 'N/A' }}</flux:table.cell>
                <flux:table.cell>{{ $schedule->arrival_time->format('Y-m-d H:i') }}</flux:table.cell>
                <flux:table.cell>{{ $this->calculateDuration($schedule->departure_time, $schedule->arrival_time) }}</flux:table.cell>
            </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
    <flux:modal name="delete-schedule-modal" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Delete route schedule?</flux:heading>
                <flux:text class="mt-2">
                    @if($this->scheduleToDelete)
                        <p>You're about to delete schedule <strong>{{ $this->scheduleToDelete->code }}</strong>.</p>
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
                    wire:click="deleteSchedule"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove>Delete Schedule</span>
                    <span wire:loading>Deleting...</span>
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