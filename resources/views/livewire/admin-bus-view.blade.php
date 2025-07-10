<?php
use Livewire\Attributes\{Layout, Title, Computed};
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Component;
use App\Models\{Bus, Tag, TagType};
use Flux\Flux;

new #[Layout('components.layouts.admin')]
    #[Title('Edit Bus')]
    
class extends Component {
    use WithFileUploads;
    
    public string $code = '';
    public string $name = '';
    public string $plate_number = '';
    public string $status = 'active';
    public string $description = '';
    public $thumbnail_pic;
    public $details_pic;
    public string $capacity = '';
    public $bus;
    
    public $tagTypeFilter = '';
    public $availableTags = [];
    public $tagSearchInput = '';

    public function mount(): void
    {
        $this->bus = Bus::with('tags.type')->find(request()->route('id'));
        
        $this->code = $this->bus->code;
        $this->name = $this->bus->name;
        $this->plate_number = $this->bus->plate_number;
        $this->status = $this->bus->status;
        $this->description = $this->bus->description;
        $this->capacity = $this->bus->capacity;
        
        $this->updateAvailableTags();
    }

    public function updateBus(): void
    {
        $validated = $this->validate([
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'plate_number' => ['required', 'string', 'max:20'],
            'status' => ['required', 'in:unknown,operational,maintenance,unavailable'],
            'description' => ['nullable', 'string'],
            'capacity' => ['required', 'numeric', 'min:1'],
            'thumbnail_pic' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
            'details_pic' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
        ]);

        $this->bus->fill(collect($validated)->except(['thumbnail_pic', 'details_pic'])->toArray());

        if ($this->thumbnail_pic) {
            if ($this->bus->thumbnail_pic) {
                Storage::disk('public')->delete($this->bus->thumbnail_pic);
            }
            $path = $this->thumbnail_pic->store('bus_thumbnails', 'public');
            $this->bus->thumbnail_pic = $path;
        }

        if ($this->details_pic) {
            if ($this->bus->details_pic) {
                Storage::disk('public')->delete($this->bus->details_pic);
            }
            $path = $this->details_pic->store('bus_details', 'public');
            $this->bus->details_pic = $path;
        }

        $this->bus->save();

        Flux::toast(
            variant: 'success',
            heading: 'Changes saved.',
            text: 'Bus information updated.',
            duration: 4000,
        );
    }

    public function removeThumbnail(): void
    {
        if ($this->bus->thumbnail_pic) {
            Storage::disk('public')->delete($this->bus->thumbnail_pic);
            $this->bus->thumbnail_pic = null;
            $this->bus->save();
        }
        
        $this->thumbnail_pic = null;
    }

    public function removeDetailsPic(): void
    {
        if ($this->bus->details_pic) {
            Storage::disk('public')->delete($this->bus->details_pic);
            $this->bus->details_pic = null;
            $this->bus->save();
        }
        
        $this->details_pic = null;
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
            ->whereNotIn('id', $this->bus->tags->pluck('id'))
            ->orderBy('name')
            ->get();
    }
    
    public function addTag($tagId)
    {
        $this->bus->tags()->attach($tagId);
        $this->bus->load('tags');
        $this->updateAvailableTags();
        
        Flux::toast(
            variant: 'success',
            heading: 'Tag added',
            text: 'The tag has been added to this bus',
            duration: 3000,
        );
    }
    
    public function removeTag($tagId)
    {
        $this->bus->tags()->detach($tagId);
        $this->bus->load('tags');
        $this->updateAvailableTags();
        
        Flux::toast(
            variant: 'success',
            heading: 'Tag removed',
            text: 'The tag has been removed from this bus',
            duration: 3000,
        );
    }
    
    #[Computed] 
    public function tagTypes()
    {
        return TagType::orderBy('name')->get();
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-4 sm:hidden">
        <div>
            <flux:button icon="pencil" :href="route('admin.edit.bus', ['id' => $bus->id])" wire:navigate></flux:button>
        </div>
        <div class="flex items-center gap-4">
            <div>
                <flux:button :href="route('admin.buses')" wire:navigate>Back</flux:button>
            </div>
        </div>
    </div>
    <div class="flex items-center justify-between mb-4">
        <div><flux:heading size="xl">View Bus ({{ $bus->id }}) <span class="font-extrabold">{{ $bus->name }}</span></flux:heading></div>
        <div class="flex items-center gap-4">
            <div class="hidden sm:block">
                <flux:button icon="pencil" :href="route('admin.edit.bus', ['id' => $bus->id])" wire:navigate></flux:button>
            </div>
            <div class="hidden sm:block">
                <flux:button :href="route('admin.buses')" wire:navigate>Back</flux:button>
            </div>
        </div>
    </div>
            <div class="space-y-6">
                <form wire:submit="updateBus" class="flex flex-col gap-6">
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <flux:input 
                                wire:model="code" 
                                :label="__('Bus Code')" 
                                type="text" 
                                required 
                                :placeholder="__('Unique bus code')" 
                                readonly
                            />
                            
                            <flux:input 
                                wire:model="name" 
                                :label="__('Bus Name')" 
                                type="text" 
                                required 
                                :placeholder="__('Bus name')" 
                                readonly
                            />
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <flux:input 
                                wire:model="plate_number" 
                                :label="__('Plate Number')" 
                                type="text" 
                                required 
                                :placeholder="__('License plate number')" 
                                readonly
                            />
                            
                            <flux:select 
                                wire:model="status" 
                                :label="__('Status')" 
                                required
                                disabled
                            >
                                <option value="unknown">{{ __('Unknown') }}</option>
                                <option value="operational">{{ __('Operational') }}</option>
                                <option value="maintenance">{{ __('Maintenance') }}</option>
                                <option value="unavailable">{{ __('Unavailable') }}</option>
                            </flux:select>
                        </div>
                        
                        <flux:input 
                            wire:model="capacity" 
                            :label="__('Passenger Capacity')" 
                            type="number" 
                            required 
                            min="1" 
                            :placeholder="__('Number of passengers')" 
                            readonly
                        />
                        
                        <flux:textarea 
                            wire:model="description" 
                            :label="__('Description')" 
                            :placeholder="__('Bus description')" 
                            rows="3" 
                            readonly
                        />
                        
                        <div class="space-y-4">
                            <div class="space-y-4">
                                @if($bus->thumbnail_pic)
                                    <div class="flex items-start gap-4">
                                        <img src="{{ Storage::url($bus->thumbnail_pic) }}" alt="Thumbnail" class="h-20 w-20 rounded-md object-cover">
                                        <div class="space-y-1">
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                {{ __('Current Thumbnail') }}
                                            </label>
                                            <flux:button variant="danger" wire:click="removeThumbnail" type="button" disabled>
                                                {{ __('Remove Thumbnail') }}
                                            </flux:button>
                                        </div>
                                    </div>
                                @else
                                    <div class="flex items-center gap-4">
                                        @if($thumbnail_pic)
                                            <img src="{{ $thumbnail_pic->temporaryUrl() }}" alt="Preview" class="h-20 w-20 rounded-md object-cover">
                                        @endif
                                        <flux:input 
                                            type="file" 
                                            wire:model="thumbnail_pic" 
                                            :label="__('Thumbnail Image')" 
                                            accept="image/jpeg,image/png"
                                            disabled
                                        />
                                    </div>
                                @endif
                            </div>
                            
                            <div class="space-y-4">
                                @if($bus->details_pic)
                                    <div class="flex items-start gap-4">
                                        <img src="{{ Storage::url($bus->details_pic) }}" alt="Details" class="h-20 w-20 rounded-md object-cover">
                                        <div class="space-y-1">
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                {{ __('Current Details Image') }}
                                            </label>
                                            <flux:button variant="danger" wire:click="removeDetailsPic" type="button" disabled>
                                                {{ __('Remove Details Image') }}
                                            </flux:button>
                                        </div>
                                    </div>
                                @else
                                    <div class="flex items-center gap-4">
                                        @if($details_pic)
                                            <img src="{{ $details_pic->temporaryUrl() }}" alt="Preview" class="h-20 w-20 rounded-md object-cover">
                                        @endif
                                        <flux:input 
                                            type="file" 
                                            wire:model="details_pic" 
                                            :label="__('Details Image')" 
                                            accept="image/jpeg,image/png"
                                            readonly
                                        />
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div>
                        <div class="space-y-4 outline rounded-lg p-4">
                            <div class="flex items-center justify-between">
                                <flux:heading size="lg">Assigned Tags</flux:heading>
                                <span class="text-sm text-neutral-600 dark:text-neutral-400">{{ $bus->tags->count() }} tags</span>
                            </div>
                            
                            @if($bus->tags->count())
                                <div class="flex flex-wrap gap-2">
                                    @foreach($bus->tags as $tag)
                                        <flux:badge 
                                            class="hover:bg-danger-100 transition-colors"
                                        >
                                            {{ $tag->type ? $tag->type->name . ': ' : '' }}{{ $tag->name }}
                                        </flux:badge>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-center py-8 text-neutral-600 dark:text-neutral-400">
                                    <flux:icon.tag class="w-8 h-8 mx-auto mb-2" />
                                    <p>No tags assigned yet</p>
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="flex items-center justify-end">
                        <flux:button variant="primary" type="submit" class="w-full"  disabled>{{ __('Save') }}</flux:button>
                    </div>
                </form>
            </div>
            
</div>