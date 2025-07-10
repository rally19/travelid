<?php
use Livewire\Attributes\{Layout, Title, Computed, Url};
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Storage;
use App\Models\{Bus, Tag, TagType};
use Flux\Flux;

new #[Layout('components.layouts.admin')]
    #[Title('Buses')]
class extends Component {
    use WithFileUploads, WithPagination;
    
    public string $code = '';
    public string $name = '';
    public string $plate_number = '';
    public string $status = '';
    public string $description = '';
    public $thumbnail_pic;
    public $details_pic;
    public int $capacity = 0;
    public bool $showFilters = false;
    public ?Bus $busToDelete = null;
    public $filters = [
        'status' => '',
        'capacity_min' => '',
        'capacity_max' => '',
        'selected_tags' => []
    ];
    public $tagTypeFilter = '';
    public $availableTags = [];
    public $tagSearchInput = '';
    public $sortBy = 'id';
    public $sortDirection = 'asc';
    public $perPage = 10;
    
    public function mount()
    {
        $this->filters = session()->get('buses.filters', $this->filters);
        
        $this->sortBy = session()->get('buses.sortBy', $this->sortBy);
        
        $this->sortDirection = session()->get('buses.sortDirection', $this->sortDirection);
        
        $this->perPage = session()->get('buses.perPage', $this->perPage);
        
        $savedPage = session()->get('terminals.page', 1);
        $this->setPage($savedPage);
        
        $this->validatePage();
        $this->updateAvailableTags();
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
        session()->put('buses.perPage', $value);
        $this->resetPage();
        $this->validatePage();
    }
    
    public function updatedFilters($value, $key)
    {
        session()->put('buses.filters', $this->filters);
        $this->resetPage();
    }
    
    public function updatedTagTypeFilter()
    {
        $this->updateAvailableTags();
    }
    
    public function updatedTagSearchInput()
    {
        $this->updateAvailableTags();
    }
    
    public function updateAvailableTags()
    {
        $this->availableTags = Tag::query()
            ->when($this->tagTypeFilter, function ($query) {
                $query->where('types_id', $this->tagTypeFilter);
            })
            ->when($this->tagSearchInput, function ($query) {
                $query->where('name', 'like', '%' . $this->tagSearchInput . '%');
            })
            ->orderBy('name')
            ->get();
    }
    
    public function addTagToFilter($tagId)
    {
        $tagId = (int)$tagId;
        if (!in_array($tagId, $this->filters['selected_tags'])) {
            $this->filters['selected_tags'][] = $tagId;
            $this->tagSearchInput = '';
            $this->updateAvailableTags();
            $this->resetPage();
            session()->put('buses.filters', $this->filters);
        }
    }
    
    public function removeTagFromFilter($tagId)
    {
        $tagId = (int)$tagId;
        
        $key = array_search($tagId, $this->filters['selected_tags']);
        
        if ($key !== false) {
            unset($this->filters['selected_tags'][$key]);
            $this->filters['selected_tags'] = array_values($this->filters['selected_tags']);
            session()->put('buses.filters', $this->filters);
        }
        
        $this->resetPage();
    }
    
    public function resetFilters()
    {
        $this->reset('filters');
        session()->forget('buses.filters');
        $this->resetPage();
        session()->put('terminals.page', 1);
    }
    
    public function validatePage()
    {
        $buses = $this->getBuses();
        
        if ($buses->currentPage() > $buses->lastPage()) {
            $this->setPage($buses->lastPage());
        }
    }
    
    public function sort($column) {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
        
        session()->put('buses.sortBy', $this->sortBy);
        session()->put('buses.sortDirection', $this->sortDirection);
        
        $this->validatePage();
    }
    
    #[Computed]
    public function getBuses()
    {
        return Bus::query()
            ->when($this->filters['status'], function ($query) {
                $query->where('status', $this->filters['status']);
            })
            ->when($this->filters['capacity_min'], function ($query) {
                $query->where('capacity', '>=', $this->filters['capacity_min']);
            })
            ->when($this->filters['capacity_max'], function ($query) {
                $query->where('capacity', '<=', $this->filters['capacity_max']);
            })
            ->when(!empty($this->filters['selected_tags']), function ($query) {
                $query->whereHas('tags', function ($q) {
                    $q->whereIn('id', $this->filters['selected_tags']);
                }, '=', count($this->filters['selected_tags']));
            })
            ->when($this->sortBy, function ($query) {
                $query->orderBy($this->sortBy, $this->sortDirection);
            })
            ->with('tags.type')
            ->paginate($this->perPage);
    }

    public function confirmDelete($busId)
    {
        $this->busToDelete = Bus::find($busId);
        Flux::modal('delete-bus-modal')->show();
    }
    
    public function deleteBus()
    {
        if (!$this->busToDelete) {
            $this->dispatch('toast', message: 'Bus not found', type: 'error');
            return;
        }
        
        try {
            $this->busToDelete->tags()->detach();
            if ($this->busToDelete->thumbnail_pic) {
                Storage::disk('public')->delete($this->busToDelete->thumbnail_pic);
            }
            if ($this->busToDelete->details_pic) {
                Storage::disk('public')->delete($this->busToDelete->details_pic);
            }

            $this->busToDelete->delete();
            
            Flux::toast(
                variant: 'success',
                heading: 'Bus Deleted.',
                text: 'Bus successfully deleted.',
            );
            
            $this->busToDelete = null;
            Flux::modal('delete-bus-modal')->close();
            
            $this->resetPage();
            unset($this->getBuses);
            
        } catch (\Exception $e) {
            $this->dispatch('toast', message: 'Failed to delete bus: ' . $e->getMessage(), type: 'error');
        }
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
    
    #[Computed]
    public function getTagTypes()
    {
        return TagType::orderBy('name')->get();
    }
    
    #[Computed]
    public function getSelectedTags()
    {
        return Tag::whereIn('id', $this->filters['selected_tags'])->get();
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-4">
        <div><flux:heading size="xl">Buses</flux:heading></div>
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
            <div><livewire:admin-bus-create/></div>
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
                    <flux:label>Status</flux:label>
                    <flux:select wire:model.live="filters.status">
                        <option value="">All Statuses</option>
                        @foreach($this->getStatusOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </flux:select>
                </div>
                
                <div>
                    <flux:label>Min Capacity</flux:label>
                    <flux:input type="number" min="0" wire:model.live="filters.capacity_min" placeholder="Minimum capacity" />
                </div>
                
                <div>
                    <flux:label>Max Capacity</flux:label>
                    <flux:input type="number" min="0" wire:model.live="filters.capacity_max" placeholder="Maximum capacity" />
                </div>
                
                <div class="md:col-span-3 space-y-4 p-3 outline rounded-lg">
                    <div x-show="$wire.filters.selected_tags.length" class="outline p-3 rounded-lg">
                        <flux:label>Selected Tags</flux:label>
                        <div class="flex flex-wrap gap-2 mt-2">
                            @foreach($this->getSelectedTags as $tag)
                                <flux:badge 
                                    class="cursor-pointer hover:bg-danger-100"
                                    wire:click="removeTagFromFilter({{ $tag->id }})"
                                >
                                    {{ $tag->type ? $tag->type->name . ': ' : '' }}{{ $tag->name }}
                                    <span class="ml-1">&times;</span>
                                </flux:badge>
                            @endforeach
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <flux:label>Filter Tag Types</flux:label>
                            <flux:select wire:model.live="tagTypeFilter">
                                <option value="">Select Tag Types</option>
                                @foreach($this->getTagTypes as $tagType)
                                    <option value="{{ $tagType->id }}">{{ $tagType->name }}</option>
                                @endforeach
                            </flux:select>
                        </div>
                        <div>
                            <flux:label>Add Tags</flux:label>
                            <div class="flex gap-2">
                                <flux:input 
                                    type="text" 
                                    wire:model.live="tagSearchInput" 
                                    placeholder="Search tags to add"
                                    wire:keydown.enter.prevent
                                />
                            </div>
                        </div>
                    </div>
                    <div x-show="$wire.tagTypeFilter || $wire.tagSearchInput" class="outline p-3 rounded-lg">
                        <flux:label>Available Tags</flux:label>
                        <div class="flex flex-wrap gap-2 mt-2">
                            @forelse($this->availableTags as $tag)
                                <flux:badge 
                                    class="cursor-pointer hover:bg-primary-100"
                                    wire:click="addTagToFilter({{ $tag->id }})"
                                    x-bind:class="{ 'bg-primary-100': $wire.filters.selected_tags.includes({{ $tag->id }}) }"
                                >
                                    {{ $tag->type ? $tag->type->name . ': ' : '' }}{{ $tag->name }}
                                </flux:badge>
                            @empty
                                <p class="text-text-neutral-600 dark:text-neutral-400 text-sm">No tags found</p>
                            @endforelse
                        </div>
                    </div>
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
    @if($this->getBuses()->count())
    <flux:table :paginate="$this->getBuses()">
        <flux:table.columns>
            <flux:table.column class="text-center">No.</flux:table.column>
            <flux:table.column class="text-center">Actions</flux:table.column>
            <flux:table.column class="text-center" sortable :sorted="$sortBy === 'id'" :direction="$sortDirection" wire:click="sort('id')">ID</flux:table.column>
            <flux:table.column>Thumbnail</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'code'" :direction="$sortDirection" wire:click="sort('code')">Code</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">Name</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'plate_number'" :direction="$sortDirection" wire:click="sort('plate_number')">Plate Number</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')">Status</flux:table.column>
            <flux:table.column>Description</flux:table.column>
            <flux:table.column>Details Image</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'capacity'" :direction="$sortDirection" wire:click="sort('capacity')">Capacity</flux:table.column>
            <flux:table.column>Tags</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($this->getBuses() as $bus)
            <flux:table.row>
                <flux:table.cell class="text-center">{{ ($this->getBuses()->currentPage() - 1) * $this->getBuses()->perPage() + $loop->iteration }}.</flux:table.cell>
                <flux:table.cell class="text-center">
                    <div class="flex items-center justify-center gap-2">
                        <flux:button 
                            icon="trash" 
                            variant="danger"
                            wire:click="confirmDelete({{ $bus->id }})"
                        ></flux:button>
                        <flux:button icon="pencil" variant="primary" :href="route('admin.edit.bus', ['id' => $bus->id])" wire:navigate></flux:button>
                        <flux:button icon="eye" :href="route('admin.view.bus', ['id' => $bus->id])" wire:navigate></flux:button>
                    </div>
                </flux:table.cell>
                <flux:table.cell>({{$bus->id}})</flux:table.cell>
                <flux:table.cell>
                    @if($bus->thumbnail_pic)
                        <img src="{{ asset('storage/' . $bus->thumbnail_pic) }}" class="w-16 h-16 object-cover rounded" alt="Thumbnail">
                    @else
                        <div class="w-16 h-16 outline rounded flex items-center justify-center">
                            <flux:icon.photo class="w-8 h-8 text-gray-400" />
                        </div>
                    @endif
                </flux:table.cell>
                <flux:table.cell>{{$bus->code}}</flux:table.cell>
                <flux:table.cell>{{$bus->name}}</flux:table.cell>
                <flux:table.cell>{{$bus->plate_number}}</flux:table.cell>
                <flux:table.cell>
                    <flux:badge variant="solid" :color="match($bus->status) {
                        'operational' => 'lime',
                        'maintenance' => 'yellow',
                        default => 'zinc'
                    }">
                        {{ $this->getStatusOptions[$bus->status] ?? $bus->status }}
                    </flux:badge>
                </flux:table.cell>
                <flux:table.cell class="max-w-xs truncate">{{$bus->description}}</flux:table.cell>
                <flux:table.cell>
                    @if($bus->details_pic)
                        <img src="{{ asset('storage/' . $bus->details_pic) }}" class="w-16 h-16 object-cover rounded" alt="Details">
                    @else
                        <div class="w-16 h-16 outline rounded flex items-center justify-center">
                            <flux:icon.photo class="w-8 h-8 text-gray-400" />
                        </div>
                    @endif
                </flux:table.cell>
                <flux:table.cell>{{$bus->capacity}}</flux:table.cell>
                <flux:table.cell>
                    <div class="flex flex-wrap gap-1">
                        @foreach($bus->tags as $tag)
                            <flux:badge :title="$tag->type ? $tag->type->name : 'No type'">
                                {{ $tag->name }}
                            </flux:badge>
                        @endforeach
                    </div>
                </flux:table.cell>
            </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
    <flux:modal name="delete-bus-modal" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Delete bus?</flux:heading>
                <flux:text class="mt-2">
                    @if($this->busToDelete)
                        <p>You're about to delete <strong>{{ $this->busToDelete->name }}</strong> ({{ $this->busToDelete->plate_number }}).</p>
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
                    wire:click="deleteBus"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove>Delete Bus</span>
                    <span wire:loading>Deleting...</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
    @else
    <div class="text-center py-8">
        <p class="text-neutral-600 dark:text-neutral-400">No buses found. You've been redirected to the last available page.</p>
    </div>
    @endif
</div>