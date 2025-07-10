<?php
use Livewire\Attributes\{Layout, Title};
use Livewire\Volt\Component;
use App\Models\{RoutesSchedule, Bus, Terminal};
use Flux\Flux;

new #[Layout('components.layouts.admin')]
    #[Title('Edit Route Schedule')]
    
class extends Component {
    public RoutesSchedule $route;
    public $buses;
    public $terminals;
    
    public string $code = '';
    public string $name = '';
    public $buses_id = '';
    public string $status = '';
    public string $price = '';
    public string $description = '';
    public $departure_id = '';
    public $departure_time = '';
    public $arrival_id = '';
    public $arrival_time = '';
    
    public function mount(): void
    {
        $this->route = RoutesSchedule::with(['bus', 'departureTerminal', 'arrivalTerminal'])->find(request()->route('id'));
        
        $this->code = $this->route->code;
        $this->name = $this->route->name;
        $this->buses_id = $this->route->buses_id;
        $this->status = $this->route->status;
        $this->price = $this->route->price;
        $this->description = $this->route->description;
        $this->departure_id = $this->route->departure_id;
        $this->arrival_id = $this->route->arrival_id;
        $this->departure_time = $this->route->departure_time ? $this->route->departure_time->format('Y-m-d\TH:i') : '';
        $this->arrival_time = $this->route->arrival_time ? $this->route->arrival_time->format('Y-m-d\TH:i') : '';
        $this->buses = Bus::orderBy('name')->get();
        $this->terminals = Terminal::orderBy('name')->get();
    }

    public function updateRoute(): void
    {
        $validated = $this->validate([
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'buses_id' => ['required', 'exists:buses,id'],
            'status' => ['required', 'in:unknown,unavailable,operational'],
            'price' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'departure_id' => ['required', 'exists:terminals,id'],
            'departure_time' => ['required', 'date'],
            'arrival_id' => ['required', 'exists:terminals,id'],
            'arrival_time' => ['required', 'date', 'after:departure_time'],
        ]);

        $this->route->update($validated);

        Flux::toast(
            variant: 'success',
            heading: 'Changes saved.',
            text: 'Route schedule updated.',
            duration: 4000,
        );
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-4 sm:hidden">
        <div>
            <flux:button icon="pencil" :href="route('admin.edit.routes-schedule', ['id' => $route->id])" wire:navigate></flux:button>
        </div>
        <div class="flex items-center gap-4">
            <div>
                <flux:button :href="route('admin.routes-schedules')" wire:navigate>Back</flux:button>
            </div>
        </div>
    </div>
    <div class="flex items-center justify-between mb-4">
        <div><flux:heading size="xl">View Route Schedule ({{ $route->id }}) <span class="font-extrabold">{{ $route->name }}</span></flux:heading></div>
        <div class="flex items-center gap-4">
            <div class="hidden sm:block">
                <flux:button icon="pencil" :href="route('admin.edit.routes-schedule', ['id' => $route->id])" wire:navigate></flux:button>
            </div>
            <div class="hidden sm:block">
                <flux:button :href="route('admin.routes-schedules')" wire:navigate>Back</flux:button>
            </div>
        </div>
    </div>
    
    <div class="space-y-6">
        <form wire:submit="updateRoute" class="flex flex-col gap-6">
            <div class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:input 
                        wire:model="code" 
                        :label="__('Route Code')" 
                        type="text" 
                        required 
                        :placeholder="__('Unique route code')" 
                        readonly
                    />
                    
                    <flux:input 
                        wire:model="name" 
                        :label="__('Route Name')" 
                        type="text" 
                        required 
                        :placeholder="__('Route name')" 
                        readonly
                    />
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:select 
                        wire:model="buses_id" 
                        :label="__('Assigned Bus')" 
                        required
                        disabled
                    >
                        <option value="">Select Bus</option>
                        @foreach($buses as $bus)
                            <option value="{{ $bus->id }}" @selected($bus->id == $route->buses_id)>
                                {{ $bus->name }} ({{ $bus->plate_number }})
                            </option>
                        @endforeach
                    </flux:select>
                    
                    <flux:select 
                        wire:model="status" 
                        :label="__('Status')" 
                        required
                        disabled
                    >
                        <option value="unknown">{{ __('Unknown') }}</option>
                        <option value="unavailable">{{ __('Unavailable') }}</option>
                        <option value="operational">{{ __('Operational') }}</option>
                    </flux:select>
                </div>
                
                <flux:input 
                    wire:model="price" 
                    :label="__('Price')" 
                    type="number" 
                    required 
                    min="0" 
                    step="0.01" 
                    :placeholder="__('Ticket price')" 
                    readonly
                />
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:select 
                        wire:model="departure_id" 
                        :label="__('Departure Terminal')" 
                        required
                        disabled
                    >
                        <option value="">Select Departure Terminal</option>
                        @foreach($terminals as $terminal)
                            <option value="{{ $terminal->id }}" @selected($terminal->id == $route->departure_id)>
                                {{ $terminal->name }} ({{ $terminal->code }})
                            </option>
                        @endforeach
                    </flux:select>
                    
                    <flux:input  
                        wire:model="departure_time" 
                        :label="__('Departure Time')" 
                        required
                        type="datetime-local" 
                        readonly
                    />
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:select 
                        wire:model="arrival_id" 
                        :label="__('Arrival Terminal')" 
                        required
                        disabled
                    >
                        <option value="">Select Arrival Terminal</option>
                        @foreach($terminals as $terminal)
                            <option value="{{ $terminal->id }}" @selected($terminal->id == $route->arrival_id)>
                                {{ $terminal->name }} ({{ $terminal->code }})
                            </option>
                        @endforeach
                    </flux:select>
                    
                    <flux:input  
                        wire:model="arrival_time" 
                        :label="__('Arrival Time')" 
                        type="datetime-local" 
                        required
                        readonly
                    />
                </div>
                
                <flux:textarea 
                    wire:model="description" 
                    :label="__('Description')" 
                    :placeholder="__('Route description')" 
                    rows="3" 
                    readonly
                />
            </div>
            
            <div class="flex items-center justify-end">
                <flux:button variant="primary" type="submit" class="w-full" disabled>{{ __('Save') }}</flux:button>
            </div>
        </form>
    </div>
</div>