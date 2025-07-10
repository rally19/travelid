<?php
use Livewire\Attributes\{Layout, Title, Computed, Url};
use Livewire\Volt\Component;
use App\Models\RoutesSchedule;
use App\Models\Terminal;
use App\Models\Bus;
use App\Models\TagType;
use App\Models\OrdersSeat;
use App\Models\Order;
use Flux\Flux;

new #[Title('Routes Schedules')]
class extends Component {
    
    public bool $showFilters = false;
    public bool $loadingMore = false;
    public int $perLoad = 10;
    public int $loadedCount = 0;

    #[Url(history: true)]
    public $search = [
        'departure_id' => '',
        'departure_time' => '',
        'arrival_id' => '',
    ];

    #[Url(history: true)]
    public $filters = [
        'buses_id' => '',
        'tags' => [],
        'bookable_only' => false
    ];
    
    #[Url(history: true)]
    public $sortBy = 'departure_time';

    #[Url(history: true)]
    public $sortDirection = 'asc';
    
    public function mount()
    {
        $this->search = session()->get('bookings.search', $this->search);
        $this->filters = session()->get('bookings.filters', $this->filters);
        $this->sortBy = session()->get('bookings.sortBy', $this->sortBy);
        $this->sortDirection = session()->get('bookings.sortDirection', $this->sortDirection);
        $this->loadedCount = session()->get('bookings.loadedCount', $this->perLoad);
        
        $this->dispatch('init-scroll-position', 
            scrollPosition: session()->get('bookings.scrollPosition', 0)
        );
    }
    
    public function updatedFilters()
    {
        session()->put('bookings.filters', $this->filters);
        $this->reset('loadedCount');
        $this->loadedCount = $this->perLoad;
        session()->put('bookings.loadedCount', $this->loadedCount);
    }

    public function updatedSearch()
    {
        session()->put('bookings.search', $this->search);
        $this->reset('loadedCount');
        $this->loadedCount = $this->perLoad;
        session()->put('bookings.loadedCount', $this->loadedCount);
    }
    
    public function resetFilters()
    {
        $this->reset('filters');
        session()->forget('bookings.filters');
        $this->reset('loadedCount');
        $this->loadedCount = $this->perLoad;
        session()->put('bookings.loadedCount', $this->loadedCount);
    }
    
    public function loadMore()
    {
        $this->loadingMore = true;
        $this->loadedCount += $this->perLoad;
        session()->put('bookings.loadedCount', $this->loadedCount);
        $this->loadingMore = false;
        
        $this->dispatch('save-scroll-position');
    }
    
    public function toggleTag($tagId)
    {
        if (in_array($tagId, $this->filters['tags'])) {
            $this->filters['tags'] = array_diff($this->filters['tags'], [$tagId]);
        } else {
            $this->filters['tags'][] = $tagId;
        }
        
        $this->reset('loadedCount');
        $this->loadedCount = $this->perLoad;
        session()->put('bookings.filters', $this->filters);
        session()->put('bookings.loadedCount', $this->loadedCount);
    }
    
    #[Computed]
    public function getSchedules()
    {
        return RoutesSchedule::query()
            ->with(['bus', 'departureTerminal', 'arrivalTerminal'])
            ->when($this->search['departure_id'], function ($query) {
                if (str_starts_with($this->search['departure_id'], 'regencity_')) {
                    $regencity = str_replace('regencity_', '', $this->search['departure_id']);
                    $query->whereHas('departureTerminal', function($q) use ($regencity) {
                        $q->where('regencity', $regencity);
                    });
                } else {
                    $query->where('departure_id', $this->search['departure_id']);
                }
            })
            ->when($this->search['arrival_id'], function ($query) {
                if (str_starts_with($this->search['arrival_id'], 'regencity_')) {
                    $regencity = str_replace('regencity_', '', $this->search['arrival_id']);
                    $query->whereHas('arrivalTerminal', function($q) use ($regencity) {
                        $q->where('regencity', $regencity);
                    });
                } else {
                    $query->where('arrival_id', $this->search['arrival_id']);
                }
            })
            ->when($this->search['departure_time'], function ($query) {
                $query->whereDate('departure_time', $this->search['departure_time']);
            })
            ->when($this->filters['buses_id'], function ($query) {
                $query->where('buses_id', $this->filters['buses_id']);
            })
            ->when($this->filters['tags'], function ($query) {
                $query->whereHas('bus.tags', function ($q) {
                    $q->whereIn('tags.id', $this->filters['tags']);
                }, '>=', count($this->filters['tags']));
            })
            ->when($this->filters['bookable_only'], function ($query) {
                $query->where('status', 'operational')
                    ->whereHas('bus', function($q) {
                        $q->where('status', 'operational');
                    })
                    ->whereHas('departureTerminal', function($q) {
                        $q->where('status', 'operational');
                    })
                    ->whereHas('arrivalTerminal', function($q) {
                        $q->where('status', 'operational');
                    });
            })
            ->when($this->sortBy, function ($query) {
                $query->orderBy($this->sortBy, $this->sortDirection);
            })
            ->get()
            ->filter(function ($schedule) {
                if ($schedule->departure_time->isPast()) {
                    return false;
                }
                
                if ($this->filters['bookable_only']) {
                    return $this->isRouteBookable($schedule);
                }
                return true;
            })
            ->map(function ($schedule) {
                $bookedSeats = OrdersSeat::where('routes_schedules_id', $schedule->id)
                    ->whereHas('order', function($query) {
                        $query->whereIn('status', ['pending', 'success']);
                    })
                    ->count();
                
                $schedule->available_seats = $schedule->bus->capacity - $bookedSeats;
                $schedule->is_bookable = $this->isRouteBookable($schedule);
                return $schedule;
            });
    }

    protected function isRouteBookable($schedule): bool
    {
        if ($schedule->departure_time->isPast()) {
            return false;
        }
        
        if ($schedule->status !== 'operational' || 
            $schedule->bus->status !== 'operational' || 
            $schedule->departureTerminal->status !== 'operational' || 
            $schedule->arrivalTerminal->status !== 'operational') {
            return false;
        }
        
        $bookedSeats = OrdersSeat::where('routes_schedules_id', $schedule->id)
            ->whereHas('order', function($query) {
                $query->whereIn('status', ['pending', 'success']);
            })
            ->count();
        
        return ($schedule->bus->capacity - $bookedSeats) > 0;
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
    
    #[Computed]
    public function getTagTypesWithTags()
    {
        return TagType::with('tags')->get();
    }
    
    public function sort($column, $direction = null) {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = $direction ?? 'asc';
        }
        
        session()->put('bookings.sortBy', $this->sortBy);
        session()->put('bookings.sortDirection', $this->sortDirection);
        
        $this->reset('loadedCount');
        $this->loadedCount = $this->perLoad;
        session()->put('bookings.loadedCount', $this->loadedCount);
        
        $this->dispatch('save-scroll-position');
    }
    
    #[Computed]
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

<div
x-data="{
    scrollPosition: 0,  // track current scroll position of the page
    
    init() {
        // this runs when the component initializes
        
        // Res pos
        // when the page loads, restore the saved scroll position after a small delay
        window.addEventListener('load', () => {
            setTimeout(() => {
                window.scrollTo(0, this.scrollPosition);
            }, 100);  // Small delay to ensure DOM is fully ready
        });
        
        // Pos init
        // listens for a Livewier event to set the scroll position from server-side
        this.$wire.on('init-scroll-position', ({ scrollPosition }) => {
            this.scrollPosition = scrollPosition; // Update local scroll position
            window.scrollTo(0, scrollPosition); // Scroll to the given position
        });
        
        // save scroll position before page unloads
        window.addEventListener('beforeunload', () => {
            this.saveScrollPosition();
        });
    },
    
    // save the current scroll position
    saveScrollPosition() {
        this.scrollPosition = window.scrollY;  // store current vertical scroll position
    }
}"
{{-- debounced scroll event - saves position 250ms after scrolling stops --}}
@scroll.debounce.250ms="saveScrollPosition"
{{-- listens for global 'save-scroll-position' event to trigger saving --}}
@save-scroll-position.window="saveScrollPosition">
    <div class="overflow-hidden">
        <div class="p-4 outline outline-offset-[-1px] rounded-lg shadow">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <flux:label>Departure Terminal</flux:label>
                    <flux:select variant="combobox" wire:model.live="search.departure_id">
                        <flux:select.option value="">All Terminals</flux:select.option>
                        @foreach($this->getTerminals->groupBy('regencity') as $regencity => $terminals)
                            <flux:select.option value="regencity_{{ $regencity }}">{{ $regencity }}, All terminals</flux:select.option>
                            @foreach($terminals as $terminal)
                                <flux:select.option value="{{ $terminal->id }}">{{ $terminal->regencity }}, {{ $terminal->name }}</flux:select.option>
                            @endforeach
                        @endforeach
                    </flux:select>
                </div>
                <div>
                    <flux:label>Arrival Terminal</flux:select.option>
                    <flux:select variant="combobox" wire:model.live="search.arrival_id">
                        <flux:select.option value="">All Terminals</flux:select.option>
                        @foreach($this->getTerminals->groupBy('regencity') as $regencity => $terminals)
                            <flux:select.option value="regencity_{{ $regencity }}">{{ $regencity }}, All Terminals</flux:select.option>
                            @foreach($terminals as $terminal)
                                <flux:select.option value="{{ $terminal->id }}">{{ $terminal->regencity }}, {{ $terminal->name }}</flux:select.option>
                            @endforeach
                        @endforeach
                    </flux:select>
                </div>
                <div>
                    <flux:label>Departure Date</flux:label>
                    <flux:date-picker wire:model.live="search.departure_time" clearable/>
                </div>
            </div>
        </div>
    </div>
    
    <div class="flex items-center justify-between my-6">
        <div><flux:heading size="xl">Bookings</flux:heading></div>
        <div class="flex items-center gap-4">
            <div>
                <flux:modal.trigger name="sort">
                    <flux:button type="button">
                        <span><flux:icon.arrows-up-down/></span> Sort
                    </flux:button>
                </flux:modal.trigger>
            </div>
            <div>
                <flux:modal.trigger name="filters">
                    <flux:button type="button">
                        <span><flux:icon.funnel/></span> Filters
                    </flux:button>
                </flux:modal.trigger>
            </div>
        </div>
    </div>
    
    @if($this->getSchedules()->count())
    <div class="grid grid-cols-1 gap-6">
        @foreach ($this->getSchedules()->take($this->loadedCount) as $schedule)
        <div class="border rounded-lg overflow-hidden shadow-sm hover:shadow-md transition-shadow relative">
            @if(!$schedule->is_bookable)
                <div class="absolute inset-0 bg-gray-400/30 z-10"></div>
            @endif
            
            <div class="grid grid-cols-1 lg:grid-cols-14 gap-4 p-4 relative">
                <div class="lg:col-span-3 flex items-center justify-center">
                    @if($schedule->bus->thumbnail_pic)
                        <img src="{{ asset('storage/' . $schedule->bus->thumbnail_pic) }}" class="w-full h-32 object-cover rounded" alt="Bus Thumbnail">
                    @else
                        <div class="w-full h-32 outline rounded flex items-center justify-center">
                            <flux:icon.photo class="w-12 h-12 text-gray-400" />
                        </div>
                    @endif
                </div>
                
                <div class="lg:col-span-9">
                    <div class="flex flex-col h-full justify-between">
                        <div>
                            <div class="flex items-center gap-2 mb-2">
                                <flux:heading size="lg">{{ $schedule->bus?->name ?? 'N/A' }}</flux:heading>
                                <div class="flex flex-wrap gap-1">
                                    @foreach($schedule->bus->tags as $tag)
                                        <flux:badge :title="$tag->type ? $tag->type->name : 'No type'">
                                            {{ $tag->name }}
                                        </flux:badge>
                                    @endforeach
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-3 gap-4">
                                <div>
                                    <div class="text-sm text-neutral-600 dark:text-neutral-400">Departure</div>
                                    <div class="font-medium">{{ $schedule->departureTerminal?->name ?? 'N/A' }}</div>
                                    <div class="text-sm">{{ $schedule->departure_time->format('Y-m-d H:i') }}</div>
                                </div>
                                
                                <div class="flex flex-col items-center justify-center">
                                    <div class="text-sm text-neutral-600 dark:text-neutral-400">
                                        {{ $this->calculateDuration($schedule->departure_time, $schedule->arrival_time) }}
                                    </div>
                                    <div class="w-full border-t border-dashed my-2"></div>
                                    <div class="text-sm font-medium">
                                        {{ number_format($schedule->price, 2) }}
                                    </div>
                                </div>
                                
                                <div class="text-right">
                                    <div class="text-sm text-neutral-600 dark:text-neutral-400">Arrival</div>
                                    <div class="font-medium">{{ $schedule->arrivalTerminal?->name ?? 'N/A' }}</div>
                                    <div class="text-sm">{{ $schedule->arrival_time->format('Y-m-d H:i') }}</div>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                @if(!$schedule->is_bookable)
                                    <div class="flex items-center gap-2 text-danger-600">
                                        <flux:icon.exclamation-circle class="w-4 h-4" />
                                        <span class="text-sm">
                                            @if($schedule->status !== 'operational')
                                                Route is not operational
                                            @elseif($schedule->bus->status !== 'operational')
                                                Bus is not operational
                                            @elseif($schedule->departureTerminal->status !== 'operational')
                                                Departure terminal is not operational
                                            @elseif($schedule->arrivalTerminal->status !== 'operational')
                                                Arrival terminal is not operational
                                            @else
                                                No seats available ({{ $schedule->available_seats }}/{{ $schedule->bus->capacity }})
                                            @endif
                                        </span>
                                    </div>
                                @else
                                    <div class="flex items-center gap-2 text-success-600">
                                        <flux:icon.check-circle class="w-4 h-4" />
                                        <span class="text-sm">
                                            {{ $schedule->available_seats }}/{{ $schedule->bus->capacity }} seats available
                                        </span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="lg:col-span-2 flex items-center justify-end">
                    <flux:button 
                        icon="book-open" 
                        variant="primary" 
                        :href="route('book', ['id' => $schedule->id])" 
                        wire:navigate
                        class="w-full"
                        :disabled="!$schedule->is_bookable"
                        :title="!$schedule->is_bookable ? 'This route is not currently bookable' : 'Book this route'"
                    >
                        Book Now
                    </flux:button>
                </div>
            </div>
        </div>
        @endforeach
        
        @if($this->loadedCount < $this->getSchedules()->count())
        <div class="flex justify-center mt-6" wire:loading.remove>
            <flux:button wire:click="loadMore" :loading="$loadingMore">
                Load More
            </flux:button>
        </div>
        @endif
        
        <div wire:loading>
            <div class="flex justify-center py-8">
                <flux:icon.loading />
            </div>
        </div>
    </div>
    @else
    <div class="text-center py-12">
        <div class="text-gray-400 mb-4">
            <flux:icon.magnifying-glass class="w-12 h-12 mx-auto" />
        </div>
        <p class="text-neutral-600 dark:text-neutral-400 text-lg">No routes schedules found matching your criteria.</p>
        <div class="mt-4">
            <flux:button wire:click="resetFilters">Reset Filters</flux:button>
        </div>
    </div>
    @endif
    
    <flux:modal name="sort" class="w-full">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Sort By</flux:heading>
            </div>
            
            <div class="space-y-4">
                <div>
                    <flux:button 
                        type="button" 
                        variant="{{ $sortBy === 'departure_time' ? 'primary' : 'ghost' }}" 
                        wire:click="sort('departure_time')"
                        class="w-full"
                    >
                        <div class="inline-flex items-center">Departure Time
                        @if($sortBy === 'departure_time')
                            <flux:icon.arrow-down class="ml-2 w-4 h-4 {{ $sortDirection === 'desc' ? 'transform rotate-180' : '' }}" />
                        @endif
                        </div>
                    </flux:button>
                </div>
                
                <div>
                    <flux:button 
                        type="button" 
                        variant="{{ $sortBy === 'arrival_time' ? 'primary' : 'ghost' }}" 
                        wire:click="sort('arrival_time')"
                        class="w-full inline-flex items-center"
                    >
                        <div class="inline-flex items-center">Arrival Time
                        @if($sortBy === 'arrival_time')
                            <flux:icon.arrow-down class="ml-2 w-4 h-4 {{ $sortDirection === 'desc' ? 'transform rotate-180' : '' }}" />
                        @endif
                        </div>
                    </flux:button>
                </div>
                
                <div>
                    <flux:button 
                        type="button" 
                        variant="{{ $sortBy === 'price' ? 'primary' : 'ghost' }}" 
                        wire:click="sort('price')"
                        class="w-full inline-flex items-center"
                    >
                        <div class="inline-flex items-center">Price
                        @if($sortBy === 'price')
                            <flux:icon.arrow-down class="ml-2 w-4 h-4 {{ $sortDirection === 'desc' ? 'transform rotate-180' : '' }}" />
                        @endif
                        </div>
                    </flux:button>
                </div>
            </div>
            
            <div class="flex">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Close</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
    
    <flux:modal name="filters" class="w-full">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Filters</flux:heading>
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
            
            <div>
                <flux:label>Show Only Bookable Routes</flux:label>
                <flux:checkbox wire:model.live="filters.bookable_only" />
            </div>
            
            <div>
                @foreach($this->getTagTypesWithTags as $type)
                    @if($type->tags->count())
                        <div class="mb-4">
                            <flux:label>{{ $type->name }}</flux:label>
                            <div class="flex flex-wrap gap-2">
                                @foreach($type->tags as $tag)
                                    <flux:badge 
                                        :color="in_array($tag->id, $filters['tags'] ?? []) ? 'lime' : 'zinc'"
                                        wire:click="toggleTag({{ $tag->id }})"
                                        class="cursor-pointer"
                                    >
                                        {{ $tag->name }}
                                    </flux:badge>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
            
            <div class="flex">
                <flux:button type="button" wire:click="resetFilters">
                    Reset Filters
                </flux:button>
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Close</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
    <script>
        document.addEventListener('livewire:initialized', () => {
            Livewire.on('save-scroll-position', () => {
                const scrollPosition = window.scrollY;
                Livewire.dispatch('saveScrollToSession', { scrollPosition });
            });
        });

        document.addEventListener('DOMContentLoaded', () => {
            window.addEventListener('scroll', () => {
                const scrollPosition = window.scrollY;
                Livewire.dispatch('saveScrollToSession', { scrollPosition });
            }, { passive: true });
        });
    </script>
</div>