<?php
use App\Models\Terminal;
use Illuminate\Support\Facades\Validator;
use Livewire\Volt\Component;
use Flux\Flux;

new class extends Component {
    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $address = '';
    public string $regencity = '';
    public string $province = '';
    public string $code = '';

    public function create(): void
    {
        $validated = $this->validate([
            'code' => ['required', 'string', 'max:255', 'unique:terminals,code'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:terminals,email'],
            'phone' => ['required', 'string', 'max:20'],
            'address' => ['required', 'string'],
            'regencity' => ['required', 'string', 'max:255'],
            'province' => ['required', 'string', 'max:255'],
        ]);

        $terminals = Terminal::create($validated);

        Flux::toast(
            variant: 'success',
            heading: 'Terminal Created',
            text: 'Terminal successfully created.',
        );

        $this->reset();
    }
}; ?>

<div>
    <flux:modal.trigger name="create-terminal">
        <flux:button variant="primary">Create Terminal</flux:button>
    </flux:modal.trigger>

    <flux:modal name="create-terminal" variant="flyout" class="max-w-lg">
        <div class="space-y-6">
            <form wire:submit.prevent="create" class="flex flex-col gap-6">
                <div class="space-y-4">
                    <flux:input 
                        wire:model="code" 
                        :label="__('Terminal Code')" 
                        type="text" 
                        required 
                        autofocus 
                        :placeholder="__('Unique terminal code')" 
                    />
                    
                    <flux:input 
                        wire:model="name" 
                        :label="__('Terminal Name')" 
                        type="text" 
                        required 
                        :placeholder="__('Terminal name')" 
                    />
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <flux:input 
                            wire:model="email" 
                            :label="__('Email')" 
                            type="email" 
                            required 
                            :placeholder="__('terminal@example.com')" 
                        />
                        
                        <flux:input 
                            wire:model="phone" 
                            :label="__('Phone Number')" 
                            type="tel" 
                            required 
                            :placeholder="__('+62...')" 
                        />
                    </div>
                    
                    <flux:textarea 
                        wire:model="address" 
                        :label="__('Address')" 
                        required 
                        :placeholder="__('Complete terminal address')" 
                        rows="3" 
                    />

                    <flux:select 
                        variant="combobox" 
                        wire:model="province" 
                        :label="__('Province')" 
                        type="text" 
                        required 
                        :placeholder="__('Province name')" 
                    >
                    @include('livewire.provinces')
                    </flux:select>

                    <flux:select 
                        variant="combobox" 
                        wire:model="regencity" 
                        :label="__('Regencity')" 
                        type="text" 
                        required 
                        :placeholder="__('Regencity name')" 
                    >
                    @include('livewire.regencities')
                    </flux:select>
                </div>
                
                <div class="flex items-center justify-end">
                    <flux:button type="submit" variant="primary" class="w-full">
                        {{ __('Create Terminal') }}
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>