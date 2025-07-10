<?php
use Livewire\Attributes\{Layout, Title};
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;
use App\Models\Terminal;
use Flux\Flux;

new #[Layout('components.layouts.admin')]
    #[Title('Edit Terminal')]
    
class extends Component {
    use WithFileUploads;
    
    public string $code = '';
    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $address = '';
    public string $regencity = '';
    public string $province = '';
    public string $status = '';
    public $terminal;

    public function mount(): void
    {
        $this->terminal = Terminal::find(request()->route('id'));
        $this->code = $this->terminal->code;
        $this->name = $this->terminal->name;
        $this->email = $this->terminal->email;
        $this->phone = $this->terminal->phone;
        $this->address = $this->terminal->address;
        $this->regencity = $this->terminal->regencity;
        $this->province = $this->terminal->province;
        $this->status = $this->terminal->status;
    }

    public function updateTerminal(): void
    {
        $validated = $this->validate([
            'code' => ['required', 'string', 'max:255', Rule::unique('terminals')->ignore($this->terminal->id)],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('terminals')->ignore($this->terminal->id)],
            'phone' => ['required', 'string', 'max:20'],
            'address' => ['required', 'string'],
            'regencity' => ['required', 'string', 'max:255'],
            'province' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:unknown,operational,maintenance,unavailable'],
        ]);

        $this->terminal->update($validated);

        Flux::toast(
            variant: 'success',
            heading: 'Changes saved.',
            text: 'Terminal settings updated.',
            duration: 4000,
        );
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-4 sm:hidden">
        <div>
            <flux:button icon="eye" :href="route('admin.view.terminal', ['id' => $terminal->id])" wire:navigate></flux:button>
        </div>
        <div class="flex items-center gap-4">
            <div>
                <flux:button :href="route('admin.terminals')" wire:navigate>Back</flux:button>
            </div>
        </div>
    </div>
    <div class="flex items-center justify-between mb-4">
        <div><flux:heading size="xl">Edit Terminal ({{ $terminal->id }}) <span class="font-extrabold">{{ $terminal->name }}</span></flux:heading></div>
        <div class="flex items-center gap-4">
            <div class="hidden sm:block">
                <flux:button icon="eye" :href="route('admin.view.terminal', ['id' => $terminal->id])" wire:navigate></flux:button>
            </div>
            <div class="hidden sm:block">
                <flux:button :href="route('admin.terminals')" wire:navigate>Back</flux:button>
            </div>
        </div>
    </div>
    <div class="space-y-6">
        <form wire:submit="updateTerminal" class="flex flex-col gap-6">
            <div class="space-y-4">
                <flux:input 
                    wire:model="code" 
                    :label="__('Terminal Code')" 
                    type="text" 
                    required 
                    :placeholder="__('Unique terminal code')" 
                />
                
                <flux:input 
                    wire:model="name" 
                    :label="__('Terminal Name')" 
                    type="text" 
                    required 
                    autofocus 
                    :placeholder="__('Terminal name')" 
                />
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:input 
                        wire:model="email" 
                        :label="__('Email address')" 
                        type="email" 
                        required 
                        placeholder="email@example.com" 
                    />
                    
                    <flux:input 
                        wire:model="phone" 
                        :label="__('Phone Number')" 
                        type="tel" 
                        required 
                        :placeholder="__('Terminal phone number')" 
                    />
                </div>
                
                <flux:textarea 
                    wire:model="address" 
                    :label="__('Address')" 
                    required 
                    :placeholder="__('Terminal complete address')" 
                    rows="3" 
                />

                <flux:select 
                        variant="combobox" 
                        wire:model="regencity" 
                        :label="__('Regencity')" 
                        type="text" 
                        required 
                        :placeholder="__('Regencity')" 
                    >
                    @include('livewire.regencities')
                </flux:select>

                <flux:select 
                        variant="combobox" 
                        wire:model="province" 
                        :label="__('Province')" 
                        type="text" 
                        required 
                        :placeholder="__('Province')" 
                    >
                    @include('livewire.provinces')
                    </flux:select>
                <flux:select 
                    wire:model="status" 
                    :label="__('Status')" 
                    required
                >
                    <option value="unknown">{{ __('Unknown') }}</option>
                    <option value="operational">{{ __('Operational') }}</option>
                    <option value="maintenance">{{ __('Maintenance') }}</option>
                    <option value="unavailable">{{ __('Unavailable') }}</option>
                </flux:select>
            </div>
            
            <div class="flex items-center justify-end">
                <flux:button variant="primary" type="submit" class="w-full">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </div>
</div>