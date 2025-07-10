<?php
use App\Models\RoutesSchedule;
use App\Models\Bus;
use App\Models\Terminal;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Component;
use Flux\Flux;

new class extends Component {
    public string $code = '';
    public ?int $buses_id = null;
    public string $name = '';
    public string $price = '';
    public string $description = '';
    public ?int $departure_id = null;
    public string $departure_time = '';
    public ?int $arrival_id = null;
    public string $arrival_time = '';

    public array $buses = [];
    public array $terminals = [];

    public function mount(): void
    {
        $this->buses = Bus::all()->toArray();
        $this->terminals = Terminal::all()->toArray();
    }

    public function create(): void
    {
        $validated = $this->validate([
            'code' => ['required', 'string', 'max:50', 'unique:routes_schedules,code'],
            'buses_id' => ['required', 'exists:buses,id'],
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'departure_id' => ['required', 'exists:terminals,id'],
            'departure_time' => ['required', 'date'],
            'arrival_id' => ['required', 'exists:terminals,id', 'different:departure_id'],
            'arrival_time' => ['required', 'date', 'after:departure_time'],
        ]);

        DB::transaction(function () use ($validated) {
            $routeSchedule = RoutesSchedule::create($validated);

            Flux::toast(
                variant: 'success',
                heading: 'Route Schedule Created',
                text: 'Route schedule has been successfully created.',
            );
        });

        $this->reset();
    }
}; ?>

<div>
    <flux:modal.trigger name="create-route-schedule">
        <flux:button variant="primary">Create Route Schedule</flux:button>
    </flux:modal.trigger>

    <flux:modal name="create-route-schedule" variant="flyout" class="max-w-2xl">
        <div class="space-y-6">
            <form wire:submit.prevent="create" class="flex flex-col gap-6">
                <div class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <flux:input 
                            wire:model="code" 
                            :label="__('Route Code')" 
                            type="text" 
                            required 
                            :placeholder="__('Unique route code')" 
                        />
                        
                        <flux:input 
                            wire:model="name" 
                            :label="__('Route Name')" 
                            type="text" 
                            required 
                            :placeholder="__('Route name')" 
                        />
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <flux:select 
                            variant="combobox"
                            wire:model="buses_id" 
                            :label="__('Bus')" 
                            required
                        >
                            @foreach($buses as $bus)
                                <flux:select.option value="{{ $bus['id'] }}">{{ $bus['code'] }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        
                        <flux:input 
                            wire:model="price" 
                            :label="__('Price')" 
                            type="number" 
                            min="0" 
                            step="0.01" 
                            required 
                            :placeholder="__('Ticket price')" 
                        />
                    </div>
                    
                    <flux:textarea 
                        wire:model="description" 
                        :label="__('Description')" 
                        :placeholder="__('Route description')" 
                        rows="2" 
                    />
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <flux:select 
                            variant="combobox"
                            wire:model="departure_id" 
                            :label="__('Departure Terminal')" 
                            required
                        >
                            @foreach($terminals as $terminal)
                                <flux:select.option value="{{ $terminal['id'] }}">{{ $terminal['name'] }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        
                        <flux:input 
                            wire:model="departure_time" 
                            :label="__('Departure Time')" 
                            type="datetime-local" 
                            required 
                        />
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <flux:select 
                            variant="combobox"
                            wire:model="arrival_id" 
                            :label="__('Arrival Terminal')" 
                            required
                        >
                            @foreach($terminals as $terminal)
                                <flux:select.option value="{{ $terminal['id'] }}">{{ $terminal['name'] }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        
                        <flux:input 
                            wire:model="arrival_time" 
                            :label="__('Arrival Time')" 
                            type="datetime-local" 
                            required 
                        />
                    </div>
                </div>
                
                <div class="flex items-center justify-end">
                    <flux:button type="submit" variant="primary" class="w-full">
                        {{ __('Create Route Schedule') }}
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>