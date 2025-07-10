<?php
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Flux\Flux;

new class extends Component {
    use WithFileUploads;
    
    public string $name = '';
    public string $email = '';
    public bool $email_verified = false;
    public string $password = '';
    public string $identifier = '';
    public string $identifier_type = 'nik';
    public string $phone_numbers = '';
    public string $address = '';
    public string $nationality = '';
    public string $gender = 'unknown';
    public string $birthdate = '';
    public $avatar;
    
    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'string', Rules\Password::defaults()],
            'identifier' => ['required', 'string', 'max:25'],
            'identifier_type' => ['required', 'in:nik,paspor,sim,nisn,akta'],
            'phone_numbers' => ['required', 'string', 'max:25'],
            'nationality' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string'],
            'gender' => ['required', 'in:unknown,male,female,other'],
            'birthdate' => ['required', 'date'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
        ]);

        $validated['password'] = Hash::make($validated['password']);

        if ($this->avatar) {
            $validated['avatar'] = $this->avatar->store('avatars', 'public');
        }

        if ($this->email_verified) {
            $validated['email_verified_at'] = now();
        }

        Flux::toast(
            variant: 'success',
            heading: 'User Created.',
            text: 'User successfully created.',
        );

        event(new Registered(($user = User::create($validated))));

        $this->reset();
    }
}; ?>

<div>
    <flux:modal.trigger name="create-user">
        <flux:button variant="primary">Create User</flux:button>
    </flux:modal.trigger>

    <flux:modal name="create-user" variant="flyout" class="max-w-lg">
        <div class="space-y-6">
            <form wire:submit.prevent="register" class="flex flex-col gap-6">
                <div class="space-y-4">
                    <div class="flex items-center gap-4">
                        @if($avatar)
                            <img src="{{ $avatar->temporaryUrl() }}" alt="Preview" class="h-20 w-20 rounded-full object-cover">
                        @endif
                        <flux:input 
                            type="file" 
                            wire:model="avatar" 
                            :label="__('Avatar')" 
                            accept="image/jpeg,image/png"
                            class="truncate"
                        />
                    </div>
                    
                    <flux:input 
                        wire:model="name" 
                        :label="__('Name')" 
                        type="text" 
                        required 
                        autofocus 
                        autocomplete="name" 
                        :placeholder="__('Full name')" 
                    />
                    
                    <flux:input 
                        wire:model="email" 
                        :label="__('Email address')" 
                        type="email" 
                        required 
                        autocomplete="email" 
                        placeholder="email@example.com" 
                    />
                    
                    <flux:checkbox 
                        wire:model="email_verified" 
                        label="Verify Email address" 
                    />
                    
                    <flux:input 
                        wire:model="password" 
                        :label="__('Password')" 
                        type="password" 
                        required 
                        autocomplete="new-password" 
                        :placeholder="__('Password')" 
                        viewable 
                    />
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <flux:input 
                            wire:model="identifier" 
                            :label="__('Identification Number')" 
                            type="text" 
                            required 
                            :placeholder="__('Your ID number')" 
                        />
                        
                        <flux:select 
                            wire:model="identifier_type" 
                            :label="__('Identification Type')" 
                            required
                        >
                            <option value="nik">{{ __('NIK') }}</option>
                            <option value="paspor">{{ __('Passport') }}</option>
                            <option value="sim">{{ __('Driver License') }}</option>
                            <option value="nisn">{{ __('Student ID') }}</option>
                            <option value="akta">{{ __('Birth Certificate') }}</option>
                        </flux:select>
                    </div>
                    
                    <flux:input 
                        wire:model="phone_numbers" 
                        :label="__('Phone Number')" 
                        type="tel" 
                        required 
                        :placeholder="__('Your phone number')" 
                    />
                    
                    <flux:select 
                        variant="combobox" 
                        wire:model="nationality"
                        :label="__('Nationality')"
                        required
                        :placeholder="__('Your nationality')"
                    >
                    @include('livewire.countries')
                    </flux:select>
                    
                    <flux:textarea 
                        wire:model="address" 
                        :label="__('Address')" 
                        required 
                        :placeholder="__('Your complete address')" 
                        rows="3" 
                    />
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <flux:select 
                            wire:model="gender" 
                            :label="__('Gender')" 
                            required
                        >
                            <option value="unknown">{{ __('Prefer not to say') }}</option>
                            <option value="male">{{ __('Male') }}</option>
                            <option value="female">{{ __('Female') }}</option>
                            <option value="other">{{ __('Other') }}</option>
                        </flux:select>
                        
                        <flux:input 
                            wire:model="birthdate" 
                            :label="__('Birthdate')" 
                            type="date" 
                            required 
                        />
                    </div>
                </div>
                
                <div class="flex items-center justify-end">
                    <flux:button type="submit" variant="primary" class="w-full">
                        {{ __('Create User') }}
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>