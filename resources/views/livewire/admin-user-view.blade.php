<?php
use Livewire\Attributes\{Layout, Title};
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\{Rules, Rule};
use Livewire\Volt\Component;
use App\Models\User;
use Flux\Flux;

new #[Layout('components.layouts.admin')]
    #[Title('View User')]
    
class extends Component {
    use WithFileUploads;
    
    public string $name = '';
    public string $email = '';
    public ?string $email_verified_at = '';
    public string $password_old = '';
    public string $password = '';
    public string $role = 'user';
    public string $identifier = '';
    public string $identifier_type = 'nik';
    public string $phone_numbers = '';
    public string $address = '';
    public string $nationality = '';
    public string $gender = 'unknown';
    public string $birthdate = '';
    public $avatar;
    public $user;

    public function mount(): void
    {
        $this->user = User::find(request()->route('id'));
        $this->name = $this->user->name;
        $this->email = $this->user->email;
        $this->email_verified_at = $this->user->email_verified_at;
        $this->password_old = $this->user->password;
        $this->password = $this->user->password;
        $this->role = $this->user->role ?? 'user';
        $this->identifier = $this->user->identifier;
        $this->identifier_type = $this->user->identifier_type ?? 'nik';
        $this->phone_numbers = $this->user->phone_numbers;
        $this->address = $this->user->address;
        $this->nationality = $this->user->nationality;
        $this->gender = $this->user->gender ?? 'unknown';
        $this->birthdate = $this->user->birthdate?->toDateString() ?? '';
    }

    public function updateUser(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
            ],
            'email_verified_at' => ['date'],
            'password' => ['required', 'string', Rules\Password::defaults()],
            'role' => ['required', 'in:user,staff,admin'],
            'identifier' => ['required', 'string', 'max:25'],
            'identifier_type' => ['required', 'in:nik,paspor,sim,nisn,akta'],
            'phone_numbers' => ['required', 'string', 'max:25'],
            'address' => ['required', 'string'],
            'nationality' => ['required', 'string', 'max:255'],
            'gender' => ['required', 'in:unknown,male,female,other'],
            'birthdate' => ['required', 'date'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
        ]);

        if ($validated['password'] !== $this->password_old) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            $validated['password'] = $this->password_old;
        }

        $this->user->fill(collect($validated)->except('avatar')->toArray());

        if ($this->avatar) {
            if ($this->user->avatar) {
                Storage::disk('public')->delete($this->user->avatar);
            }
            
            $path = $this->avatar->store('avatars', 'public');
            $this->user->avatar = $path;
        }

        if ($this->user->isDirty('email')) {
            $this->user->email_verified_at = null;
        }

        $this->user->save();

        $this->dispatch('profile-updated', name: $this->user->name);

        Flux::toast(
            variant: 'success',
            heading: 'Changes saved.',
            text: 'User settings updated.',
            duration: 4000,
        );
    }

    public function removeAvatar(): void
    {
        if ($this->user->avatar) {
            Storage::disk('public')->delete($this->user->avatar);
            $this->user->avatar = null;
            $this->user->save();
        }
        
        $this->avatar = null;
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-4 sm:hidden">
        <div>
            <flux:button icon="pencil" :href="route('admin.edit.user', ['id' => request()->route('id')])" wire:navigate></flux:button>
        </div>
        <div class="flex items-center gap-4">
            <div>
                <flux:button :href="route('admin.users')" wire:navigate>Back</flux:button>
            </div>
        </div>
    </div>
    <div class="flex items-center justify-between mb-4">
        <div><flux:heading size="xl">View User ({{ $user->id }}) <span class="font-extrabold">{{ $user->email }}</span></flux:heading></div>
        <div class="flex items-center gap-4">
            <div class="hidden sm:block">
                <flux:button icon="pencil" :href="route('admin.edit.user', ['id' => request()->route('id')])" wire:navigate></flux:button>
            </div>
            <div class="hidden sm:block">
               <flux:button :href="route('admin.users')" wire:navigate>Back</flux:button>
            </div>
        </div>
    </div>
    <div class="space-y-6">
        <form wire:submit="updateUser" class="flex flex-col gap-6">
            <div class="space-y-4">
                <div class="space-y-4">
                    @if($user->avatar)
                        <div class="flex items-start gap-4">
                            <img src="{{ Storage::url($user->avatar) }}" alt="Avatar" class="h-20 w-20 rounded-full object-cover">
                            <div class="space-y-1">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ __('Current Avatar') }}
                                </label>
                                <flux:button variant="danger" wire:click="removeAvatar" type="button" disabled>
                                    {{ __('Remove Avatar') }}
                                </flux:button>
                            </div>
                        </div>
                    @else
                        <div class="flex items-center gap-4">
                            @if($avatar)
                                <img src="{{ $avatar->temporaryUrl() }}" alt="Preview" class="h-20 w-20 rounded-full object-cover">
                            @endif
                            <flux:input 
                                type="file" 
                                wire:model="avatar" 
                                :label="__('Avatar')" 
                                accept="image/jpeg,image/png"
                                disabled
                            />
                        </div>
                    @endif
                </div>
                
                <flux:input 
                    wire:model="name" 
                    :label="__('Name')" 
                    type="text" 
                    required 
                    readonly
                    autofocus 
                    autocomplete="name" 
                    :placeholder="__('Full name')" 
                />
                
                <flux:input 
                    wire:model="email" 
                    :label="__('Email address')" 
                    type="email" 
                    required 
                    readonly
                    autocomplete="email" 
                    placeholder="email@example.com" 
                />
                
                <flux:input 
                    wire:model="email_verified_at" 
                    :label="__('Email Verification')" 
                    type="datetime-local" 
                    readonly
                    step="1"
                />
                
                <flux:input 
                    wire:model="password" 
                    :label="__('Password')" 
                    type="password" 
                    required 
                    readonly
                    autocomplete="new-password" 
                    :placeholder="__('Password')" 
                    viewable 
                />

                <flux:select 
                    wire:model="role" 
                    :label="__('Role')" 
                    disabled
                    readonly
                >
                    <option value="user">{{ __('User') }}</option>
                    <option value="staff">{{ __('Staff') }}</option>
                    <option value="admin">{{ __('Admin') }}</option>
                </flux:select>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:input 
                        wire:model="identifier" 
                        :label="__('Identification Number')" 
                        type="text" 
                        required 
                        readonly
                        :placeholder="__('Your ID number')"
                    />
                    
                    <flux:select 
                        wire:model="identifier_type" 
                        :label="__('Identification Type')" 
                        disabled
                        readonly
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
                    readonly
                    :placeholder="__('Your phone number')" 
                />
                
                <flux:input 
                    wire:model="nationality" 
                    :label="__('Nationality')" 
                    type="text" 
                    required 
                    readonly
                    :placeholder="__('Your nationality')" 
                />
                
                <flux:textarea 
                    wire:model="address" 
                    :label="__('Address')" 
                    required 
                    readonly
                    :placeholder="__('Your complete address')" 
                    rows="3" 
                />
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:select 
                        wire:model="gender" 
                        :label="__('Gender')" 
                        required
                        disabled
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
                        readonly
                    />
                </div>
            </div>
            
            <div class="flex items-center justify-end">
                <flux:button variant="primary" type="submit" class="w-full" disabled>{{ __('Save') }}</flux:button>
            </div>
        </form>
    </div>
</div>