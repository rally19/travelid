<?php
use App\Models\Bus;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Flux\Flux;

new class extends Component {
    use WithFileUploads;
    
    public string $code = '';
    public string $name = '';
    public string $plate_number = '';
    public string $description = '';
    public $thumbnail_pic;
    public $details_pic;
    public int $capacity = 0;
    
    public function createBus(): void
    {
        $validated = $this->validate([
            'code' => ['required', 'string', 'max:50', 'unique:buses,code'],
            'name' => ['required', 'string', 'max:255'],
            'plate_number' => ['required', 'string', 'max:20', 'unique:buses,plate_number'],
            'description' => ['nullable', 'string'],
            'thumbnail_pic' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
            'details_pic' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
            'capacity' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        if ($this->thumbnail_pic) {
            $validated['thumbnail_pic'] = $this->thumbnail_pic->store('bus_thumbnails', 'public');
        }
        
        if ($this->details_pic) {
            $validated['details_pic'] = $this->details_pic->store('bus_details', 'public');
        }

        Flux::toast(
            variant: 'success',
            heading: 'Bus Created.',
            text: 'Bus successfully created.',
        );

        Bus::create($validated);

        $this->reset();
    }
}; ?>

<div>
    <flux:modal.trigger name="create-bus">
        <flux:button variant="primary">Add New Bus</flux:button>
    </flux:modal.trigger>

    <flux:modal name="create-bus" variant="flyout" class="max-w-lg">
        <div class="space-y-6">
            <form wire:submit.prevent="createBus" class="flex flex-col gap-6">
                <div class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <flux:input 
                            wire:model="code" 
                            :label="__('Bus Code')" 
                            type="text" 
                            required 
                            :placeholder="__('Unique bus code')" 
                        />
                        
                        <flux:input 
                            wire:model="name" 
                            :label="__('Bus Name')" 
                            type="text" 
                            required 
                            :placeholder="__('Bus name')" 
                        />
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <flux:input 
                            wire:model="plate_number" 
                            :label="__('Plate Number')" 
                            type="text" 
                            required 
                            :placeholder="__('License plate number')" 
                        />
                        
                        <flux:input 
                            wire:model="capacity" 
                            :label="__('Passenger Capacity')" 
                            type="number" 
                            required 
                            min="1"
                            max="100"
                            :placeholder="__('Number of passengers')" 
                        />
                    </div>
                    
                    <flux:textarea 
                        wire:model="description" 
                        :label="__('Description')" 
                        :placeholder="__('Bus description')" 
                        rows="3" 
                    />
                    
                    <div class="space-y-4">
                        <div class="flex items-center gap-4">
                            @if($thumbnail_pic)
                                <img src="{{ $thumbnail_pic->temporaryUrl() }}" alt="Thumbnail Preview" class="h-20 w-20 rounded-md object-cover">
                            @endif
                            <flux:input 
                                type="file" 
                                wire:model="thumbnail_pic" 
                                :label="__('Thumbnail Image')" 
                                accept="image/jpeg,image/png"
                                class="truncate"
                            />
                        </div>
                        
                        <div class="flex items-center gap-4">
                            @if($details_pic)
                                <img src="{{ $details_pic->temporaryUrl() }}" alt="Details Preview" class="h-20 w-20 rounded-md object-cover">
                            @endif
                            <flux:input 
                                type="file" 
                                wire:model="details_pic" 
                                :label="__('Details Image')" 
                                accept="image/jpeg,image/png"
                                class="truncate"
                            />
                        </div>
                    </div>
                </div>
                
                <div class="flex items-center justify-end">
                    <flux:button type="submit" variant="primary" class="w-full">
                        {{ __('Add Bus') }}
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>