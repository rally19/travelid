<?php
use Livewire\Attributes\{Layout, Title};
use Livewire\WithFileUploads;
use Livewire\Volt\Component;
use App\Models\{Order, OrdersSeat};
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Flux\Flux;

new #[Layout('components.layouts.app')]
    #[Title('Edit Booking')]
class extends Component {
    use WithFileUploads;
    
    public Order $order;
    public array $seats = [];
    public string $paymentMethod = '';
    public $paymentProof;
    public ?string $existingPaymentProof = null;
    
    public function mount($id): void
    {
        $this->order = Order::with(['seats', 'user'])
            ->where('id', $id)
            ->where('users_id', Auth::id())
            ->where('status', 'pending')
            ->firstOrFail();
            
        $this->seats = $this->order->seats->map(function($seat) {
            return [
                'id' => $seat->id,
                'title' => $seat->title,
                'name' => $seat->name,
                'age' => $seat->age,
            ];
        })->toArray();
        
        $this->paymentMethod = $this->order->payment_method;
        $this->existingPaymentProof = $this->order->payment_proof;
    }
    
    public function updateBooking(): void
    {
        $this->validate([
            'seats.*.title' => ['required', 'in:Mr,Mrs,Ms,Miss,Dr'],
            'seats.*.name' => ['required', 'string', 'max:255'],
            'seats.*.age' => ['required', 'integer', 'min:1', 'max:120'],
            'paymentMethod' => ['required', 'in:bank_transfer,credit_card,e_wallet'],
            'paymentProof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);
        
        foreach ($this->seats as $seatData) {
            $seat = OrdersSeat::find($seatData['id']);
            if ($seat) {
                $seat->update([
                    'title' => $seatData['title'],
                    'name' => $seatData['name'],
                    'age' => $seatData['age'],
                ]);
            }
        }
        
        $paymentProofPath = $this->existingPaymentProof;
        if ($this->paymentProof) {
            if ($this->existingPaymentProof) {
                Storage::disk('local')->delete($this->existingPaymentProof);
            }
            $paymentProofPath = $this->paymentProof->store('payment_proofs', 'local');
        }
        
        $this->order->update([
            'payment_method' => $this->paymentMethod,
            'payment_proof' => $paymentProofPath,
        ]);
        
        Flux::toast(
            variant: 'success',
            heading: 'Booking Updated',
            text: 'Your booking has been updated successfully',
            duration: 5000,
        );
    }
    
    public function removePaymentProof(): void
    {
        if ($this->existingPaymentProof) {
            Storage::disk('local')->delete($this->existingPaymentProof);
            $this->order->payment_proof = null;
            $this->order->save();
            $this->existingPaymentProof = null;
        }
        
        $this->paymentProof = null;
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-4">
        <div>
            <flux:heading size="xl">Edit Booking #{{ $order->code }}</flux:heading>
        </div>
        <div>
            <flux:button :href="route('dashboard')" wire:navigate>
                Back
            </flux:button>
        </div>
    </div>
    <form wire:submit.prevent="updateBooking" class="space-y-6">
        <div class="space-y-4">
            <flux:heading size="lg">Passenger Information</flux:heading>
            @foreach($seats as $index => $seat)
            <div class="p-4 outline rounded-lg">
                <flux:heading size="md" class="mb-2">Passenger {{ $index + 1 }}</flux:heading>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <flux:label>Title</flux:label>
                        <flux:select wire:model="seats.{{ $index }}.title" required>
                            <option value="Mx">Mx</option>
                            <option value="Ms">Ms</option>
                            <option value="Mrs">Mrs</option>
                            <option value="Mr">Mr</option>
                        </flux:select>
                    </div>
                    
                    <div>
                        <flux:label>Full Name</flux:label>
                        <flux:input 
                            type="text" 
                            wire:model="seats.{{ $index }}.name" 
                            required
                            placeholder="Passenger full name"
                        />
                    </div>
                    
                    <div>
                        <flux:label>Age</flux:label>
                        <flux:input 
                            type="number" 
                            wire:model="seats.{{ $index }}.age" 
                            min="1" 
                            max="120" 
                            required
                            placeholder="Passenger age"
                        />
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        
        <div class="space-y-4">
            <flux:heading size="lg">Payment Information</flux:heading>
            <div>
                @if($existingPaymentProof)
                <flux:label>Payment Proof</flux:label>
                    <div class="flex items-start gap-4 mt-2">

                            <flux:icon.document-text class="w-6 h-6 text-primary-600" />
                            <a href="{{ route('payment-proof', [
                                    'orderId' => $order->id,
                                    'filename' => basename($order->payment_proof)
                                ]) }}" 
                            target="_blank" 
                            class="flex items-center gap-2 text-primary-600 hover:underline truncate">{{$order->payment_proof}}</a>
                            <flux:button 
                                variant="danger" 
                                wire:click="removePaymentProof" 
                                type="button"
                            >
                                Remove Proof
                            </flux:button>
                    </div>
                @endif
                @if (!$existingPaymentProof)
                <div class="mt-2">
                    <flux:input 
                        type="file" 
                        wire:model="paymentProof" 
                        accept="image/*,.pdf"
                        :label="'Payment Proof'"
                    />
                    <div class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                        Upload screenshot or PDF of your payment receipt (max 5MB)
                    </div>
                </div>
                @endif

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <flux:label>Payment Method</flux:label>
                        <flux:select wire:model="paymentMethod" required>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="credit_card">Credit Card</option>
                            <option value="e_wallet">E-Wallet</option>
                        </flux:select>
                    </div>

                    
                </div>
            </div>
            <div class="space-x-1 text-center text-sm text-zinc-600 dark:text-zinc-400 text-left">
                {{ __('How to pay, click this') }}
                <flux:link :href="route('about', '#payments')" wire:navigate>{{ __('link') }}</flux:link>
            </div>
        </div>
        <div class="flex justify-end">
            <flux:button type="submit" variant="primary">
                Save Changes
            </flux:button>
        </div>
    </form>
</div>