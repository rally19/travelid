<?php
use Livewire\Attributes\{Layout, Title, Url};
use Livewire\Volt\Component;
use App\Models\{RoutesSchedule, Order, OrdersSeat, Bus, Terminal};
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\WithFileUploads;
use Flux\Flux;

new #[Layout('components.layouts.app')]
    #[Title('Book Bus')]
class extends Component {
    use WithFileUploads;
    
    public RoutesSchedule $schedule;
    public Bus $bus;
    public Terminal $departureTerminal;
    public Terminal $arrivalTerminal;
    
    public int $quantity = 1;
    public array $seats = [];
    public string $paymentMethod = 'bank_transfer';
    public $paymentProof;
    public int $availableSeats = 0;
    public bool $isOperational = true;
    public array $operationalErrors = [];
    public array $quantityOptions = [];
    public string $currentStep = 'passenger_info'; // 'passenger_info' / 'payment'
    
    public function mount(): void
    {
        $this->schedule = RoutesSchedule::find(request()->route('id'))->load(['bus', 'departureTerminal', 'arrivalTerminal']);
        $this->bus = $this->schedule->bus;
        $this->departureTerminal = $this->schedule->departureTerminal;
        $this->arrivalTerminal = $this->schedule->arrivalTerminal;
        
        $this->checkOperationalStatus();
        
        if ($this->isOperational) {

            $this->updateAvailableSeats();
            
            $this->seats = array_fill(0, $this->quantity, [
                'name' => auth()->user()->name ?? '',
                'age' => '',
                'title' => 'Mx',
            ]);
        }
    }
    
    public function checkOperationalStatus(): void
    {
        $this->isOperational = true;
        $this->operationalErrors = [];
        
        if ($this->schedule->departure_time->isPast()) {
            $this->isOperational = false;
            $this->operationalErrors[] = 'This route has already departed';
        }
        
        if ($this->schedule->status !== 'operational') {
            $this->isOperational = false;
            $this->operationalErrors[] = 'This route is currently not operational';
        }
        
        if ($this->bus->status !== 'operational') {
            $this->isOperational = false;
            $this->operationalErrors[] = 'The bus for this route is currently not operational';
        }
        
        if ($this->departureTerminal->status !== 'operational') {
            $this->isOperational = false;
            $this->operationalErrors[] = 'The departure terminal is currently not operational';
        }
        
        if ($this->arrivalTerminal->status !== 'operational') {
            $this->isOperational = false;
            $this->operationalErrors[] = 'The arrival terminal is currently not operational';
        }
    }
    
    public function updateAvailableSeats(): void
    {
        $bookedSeats = OrdersSeat::where('routes_schedules_id', $this->schedule->id)
            ->whereHas('order', function($query) {
                $query->whereIn('status', ['pending', 'success']);
            })
            ->count();
            
        $this->availableSeats = max(0, $this->bus->capacity - $bookedSeats);
        
        $this->quantityOptions = [];
        $maxSelectable = min(4, $this->availableSeats);
        for ($i = 1; $i <= $maxSelectable; $i++) {
            $this->quantityOptions[$i] = $i;
        }
        
        if ($this->quantity > $maxSelectable && $maxSelectable > 0) {
            $this->quantity = $maxSelectable;
            $this->updatedQuantity($maxSelectable);
        }
    }
    
    public function updatedQuantity($value): void
    {
        $value = (int)$value;
        if ($value < 1) $value = 1;
        if ($value > 4) $value = 4;
        
        // Add or remove seats based on quantity change
        if ($value > count($this->seats)) {
            for ($i = count($this->seats); $i < $value; $i++) {
                $this->seats[$i] = [
                    'name' => auth()->user()->name ?? '',
                    'age' => '',
                    'title' => 'Mx',
                ];
            }
        } else {
            $this->seats = array_slice($this->seats, 0, $value);
        }
    }
    
    public function calculateTotalCost(): float
    {
        return $this->quantity * $this->schedule->price;
    }
    
    public function calculateDuration($departure, $arrival): string
    {
        $diff = $departure->diff($arrival);
        
        $hours = $diff->h;
        $minutes = $diff->i;
        
        if ($diff->d > 0) {
            $hours += $diff->d * 24;
        }
        
        return sprintf('%dh %dm', $hours, $minutes);
    }
    
    public function proceedToPayment(): void
    {
        $this->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:4'],
            'seats.*.name' => ['required', 'string', 'max:255'],
            'seats.*.age' => ['required', 'integer', 'min:1', 'max:120'],
            'seats.*.title' => ['required', 'in:Mx,Ms,Mrs,Mr'],
        ]);
        
        $this->currentStep = 'payment';
    }
    
    public function backToPassengerInfo(): void
    {
        $this->currentStep = 'passenger_info';
    }
    
    public function submitOrder(): void
    {
        $this->validate([
            'paymentMethod' => ['required', 'in:bank_transfer,credit_card,e_wallet'],
            'paymentProof' => ['required', 'nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);
        
        DB::transaction(function() {
            $this->checkOperationalStatus();
            
            if (!$this->isOperational) {
                Flux::toast(
                    variant: 'danger',
                    heading: 'Booking not available',
                    text: implode(', ', $this->operationalErrors),
                    duration: 5000,
                );
                return;
            }
            
            $freshSchedule = RoutesSchedule::with(['bus'])
                ->where('id', $this->schedule->id)
                ->lockForUpdate()
                ->first();
                
            if ($freshSchedule->departure_time->isPast()) {
                Flux::toast(
                    variant: 'danger',
                    heading: 'Booking not available',
                    text: 'This route has already departed',
                    duration: 5000,
                );
                return;
            }
            
            $freshBus = $freshSchedule->bus;
            
            $bookedSeats = OrdersSeat::where('routes_schedules_id', $freshSchedule->id)
                ->whereHas('order', function($query) {
                    $query->whereIn('status', ['pending', 'success']);
                })
                ->count();
                
            $availableSeats = max(0, $freshBus->capacity - $bookedSeats);
            
            if ($this->quantity > $availableSeats) {
                $this->updateAvailableSeats();
                Flux::toast(
                    variant: 'danger',
                    heading: 'Not enough seats',
                    text: "Only {$availableSeats} seats available now. Please adjust your booking.",
                    duration: 5000,
                );
                $this->currentStep = 'passenger_info';
                return;
            }
            
            $paymentProofPath = null;
            if ($this->paymentProof) {
                $paymentProofPath = $this->paymentProof->store('payment_proofs', 'local');
            }
            
            $orderCount = Order::where('routes_schedules_code', $freshSchedule->code)->count();
            $orderCode = $freshSchedule->code . '-O-' . Str::random(6) . '-' . ($orderCount + 1);
            
            $order = Order::create([
                'code' => $orderCode,
                'users_id' => auth()->id(),
                'status' => 'pending',
                'bus_code' => $freshBus->code,
                'bus_name' => $freshBus->name,
                'routes_schedules_code' => $freshSchedule->code,
                'route_name' => $freshSchedule->name,
                'departure_terminal' => $this->departureTerminal->name,
                'departure_location' => $this->departureTerminal->regencity . ', ' . $this->departureTerminal->province,
                'departure_time' => $freshSchedule->departure_time,
                'arrival_terminal' => $this->arrivalTerminal->name,
                'arrival_location' => $this->arrivalTerminal->regencity . ', ' . $this->arrivalTerminal->province,
                'arrival_time' => $freshSchedule->arrival_time,
                'payment_method' => $this->paymentMethod,
                'payment_proof' => $paymentProofPath,
                'quantity' => $this->quantity,
                'total_cost' => $this->calculateTotalCost(),
            ]);
            
            $seatCount = OrdersSeat::where('routes_schedules_id', $freshSchedule->id)->count();
            foreach ($this->seats as $index => $seat) {
                $seatCode = $freshSchedule->code . '-S-' . Str::random(6) . '-' . ($seatCount + $index + 1);
                
                OrdersSeat::create([
                    'code' => $seatCode,
                    'orders_id' => $order->id,
                    'routes_schedules_id' => $freshSchedule->id,
                    'name' => $seat['name'],
                    'age' => $seat['age'],
                    'title' => $seat['title'],
                    'cost' => $this->schedule->price,
                ]);
            }
            
            Flux::toast(
                variant: 'success',
                heading: 'Booking Successful',
                text: 'Your booking has been submitted successfully',
                duration: 5000,
            );
            
            $this->redirect(route('booking', $order->id), navigate: true);
        });
    }
}; ?>

<div>
    <div class="relative">
        @if($bus->details_pic)
            <img src="{{ asset('storage/' . $bus->details_pic) }}" alt="Bus Details" class="rounded-lg outline w-full h-64 object-cover">
        @else
            <div class="w-full h-64 bg-gray-100 flex items-center justify-center">
                <flux:icon.photo class="w-16 h-16 text-gray-400" />
            </div>
        @endif
        
        <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/80 to-transparent p-6 rounded-lg">
            <div class="flex items-end gap-4">
                @if($bus->thumbnail_pic)
                    <img src="{{ asset('storage/' . $bus->thumbnail_pic) }}" alt="Bus Thumbnail" class="w-20 h-20 rounded-md object-cover">
                @endif
                <div>
                    <flux:heading size="2xl" class="text-white">{{ $bus->name }}</flux:heading>
                    <div class="flex flex-wrap gap-2 mt-2">
                        @foreach($bus->tags as $tag)
                            <flux:badge :color="$tag->type ? 'lime' : 'zinc'" class="text-white bg-white/10 backdrop-blur-sm">
                                {{ $tag->name }}
                            </flux:badge>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="mt-6 p-6 outline rounded-lg">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
                <flux:heading size="lg" class="mb-4">Departure</flux:heading>
                <div class="space-y-2">
                    <div class="font-medium">{{ $departureTerminal->name }}</div>
                    <div class="text-neutral-600 dark:text-neutral-400">{{ $departureTerminal->address }}</div>
                    <div class="mt-2 font-medium text-primary-600">
                        {{ $schedule->departure_time->format('l, F j, Y H:i') }}
                    </div>
                </div>
            </div>
            
            <div class="flex flex-col items-center justify-center">
                <div class="text-center mb-2">
                    <div class="text-sm text-neutral-600 dark:text-neutral-400">Duration</div>
                    <div class="font-medium">
                        {{ $this->calculateDuration($schedule->departure_time, $schedule->arrival_time) }}
                    </div>
                </div>
                <div class="w-full border-t border-dashed my-2"></div>
                <div class="text-center mt-2">
                    <div class="text-sm text-neutral-600 dark:text-neutral-400">Price per seat</div>
                    <div class="font-medium text-primary-600">
                        {{ number_format($schedule->price, 2) }}
                    </div>
                </div>
            </div>
            
            <div class="text-right">
                <flux:heading size="lg" class="mb-4">Arrival</flux:heading>
                <div class="space-y-2">
                    <div class="font-medium">{{ $arrivalTerminal->name }}</div>
                    <div class="text-neutral-600 dark:text-neutral-400">{{ $arrivalTerminal->address }}</div>
                    <div class="mt-2 font-medium text-primary-600">
                        {{ $schedule->arrival_time->format('l, F j, Y H:i') }}
                    </div>
                </div>
            </div>
        </div>
        
        <div class="mt-6 p-4 rounded-lg mb-4 outline {{ $availableSeats > 0 ? 'bg-primary-50' : 'bg-danger-50' }}">
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="md">Seat Availability</flux:heading>
                    <div class="text-neutral-600 dark:text-neutral-400">Max 4 tickets per booking</div>
                </div>
                <div class="text-right">
                    <div class="text-lg font-bold {{ $availableSeats > 0 ? 'text-primary-600' : 'text-danger-600' }}">
                        {{ $availableSeats }} / {{ $bus->capacity }} seats available
                    </div>
                    @if($availableSeats <= 0)
                        <div class="text-sm text-danger-600">This bus is fully booked</div>
                    @endif
                </div>
            </div>
        </div>
        
        <flux:heading size="lg">Description</flux:heading>
        <flux:text>{{$schedule->description}}</flux:text>
    </div>
        
        @if($currentStep === 'passenger_info' && $isOperational && $availableSeats > 0)
        <div>
            <flux:heading size="xl" class="mb-4">Passenger Information</flux:heading>
            
            <form wire:submit.prevent="proceedToPayment" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <flux:label>Number of Passengers</flux:label>
                        <flux:select 
                            wire:model.live="quantity" 
                            required
                            :disabled="count($quantityOptions) === 0"
                        >
                            @foreach($quantityOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                            @if($this->quantity > 0 && !in_array($this->quantity, array_keys($quantityOptions)))
                                <option value="{{ $this->quantity }}" disabled>
                                    {{ $this->quantity }} (Not available)
                                </option>
                            @endif
                        </flux:select>
                        @if($this->quantity > 0 && !in_array($this->quantity, array_keys($quantityOptions)))
                            <div class="mt-1 text-sm text-danger-600">
                                Only {{ $availableSeats }} seats available
                            </div>
                        @endif
                    </div>
                </div>
                
                <div class="space-y-4">
                    @foreach($seats as $index => $seat)
                    <div class="p-4 outline rounded-lg">
                        <flux:heading size="lg" class="mb-4">Passenger {{ $index + 1 }}</flux:heading>
                        
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
                
                <div class="p-4 outline rounded-lg bg-primary-50/50">
                    <div class="flex items-center justify-between">
                        <div>
                            <flux:heading size="lg">Order Summary</flux:heading>
                            <div class="text-neutral-600 dark:text-neutral-400">{{ $quantity }} passenger(s)</div>
                        </div>
                        <div class="text-right">
                            <div class="text-2xl font-bold text-primary-600">
                                {{ number_format($this->calculateTotalCost(), 2) }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-between">
                    <flux:button :href="route('bookings')" wire:navigate variant="ghost">
                        Back to Bookings
                    </flux:button>

                    <flux:button type="submit" variant="primary">
                        Proceed to Payment
                    </flux:button>
                </div>
            </form>
        </div>
        
        @elseif($currentStep === 'payment' && $isOperational && $availableSeats > 0)
        <div>
            <flux:heading size="xl" class="mb-4">Payment Information</flux:heading>
            
            <form wire:submit.prevent="submitOrder" class="space-y-6">
                <div class="p-4 outline rounded-lg bg-primary-50/50">
                    <div class="flex items-center justify-between">
                        <div>
                            <flux:heading size="lg">Order Summary</flux:heading>
                            <div class="text-neutral-600 dark:text-neutral-400">{{ $quantity }} passenger(s)</div>
                        </div>
                        <div class="text-right">
                            <div class="text-2xl font-bold text-primary-600">
                                {{ number_format($this->calculateTotalCost(), 2) }}
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="p-4 outline rounded-lg">
                    <flux:heading size="lg" class="mb-4">Payment Method</flux:heading>
                    
                    <div class="space-y-4">
                        <div>
                            <flux:label>Select Payment Method</flux:label>
                            <flux:select wire:model.live="paymentMethod" required>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="credit_card">Credit Card</option>
                                <option value="e_wallet">E-Wallet</option>
                            </flux:select>
                        </div>
                        
                        <div>
                            <flux:label>Payment Proof</flux:label>
                            <div class="flex items-center gap-4">
                                @if($paymentProof)
                                    <div class="flex items-center gap-2">
                                        <flux:icon.document-text class="w-6 h-6 text-primary-600" />
                                        <span class="text-sm">{{ $paymentProof->getClientOriginalName() }}</span>
                                    </div>
                                @endif
                                <flux:input
                                    type="file"
                                    wire:model="paymentProof"
                                    accept="image/*,.pdf"
                                    class="truncate"
                                />
                            </div>
                            @error('paymentProof') <div class="mt-1 text-sm text-danger-600">{{ $message }}</div> @enderror
                            <div class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                                Upload screenshot or PDF of your payment receipt.
                            </div>
                        </div>

                        @if($paymentMethod === 'bank_transfer')
                        <div class="p-4 bg-gray-800 rounded-lg">
                            <div class="flex items-start gap-3">
                                <div class="text-gray-200">
                                    <flux:icon.information-circle class="w-6 h-6" />
                                </div>
                                <div>
                                    <div class="font-medium text-gray-200">Bank Transfer Instructions</div>
                                    <div class="mt-2 text-sm text-gray-100">
                                        Option 1: Bank Indonesia<br>
                                        Please transfer the exact amount to:<br>
                                        Account Number: 1234567890<br>
                                        Account Name: TravelID<br>
                                        Amount: {{ number_format($this->calculateTotalCost(), 2) }}<br>
                                        <br>
                                        Option 2: Bank Sulawesi Timur<br>
                                        Please transfer the exact amount to:<br>
                                        Account Number: 1234567890<br>
                                        Account Name: TravelID<br>
                                        Amount: {{ number_format($this->calculateTotalCost(), 2) }}<br>
                                        <br>
                                        Option 3: Bank Mandiri<br>
                                        Please transfer the exact amount to:<br>
                                        Account Number: 1234567890<br>
                                        Account Name: TravelID<br>
                                        Amount: {{ number_format($this->calculateTotalCost(), 2) }}<br>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif

                        @if($paymentMethod === 'credit_card')
                        <div class="p-4 bg-gray-800 rounded-lg">
                            <div class="flex items-start gap-3">
                                <div class="text-gray-200">
                                    <flux:icon.information-circle class="w-6 h-6" />
                                </div>
                                <div>
                                    <div class="font-medium text-gray-200">Credit Card Payment Instructions</div>
                                    <div class="mt-2 text-sm text-gray-100">
                                        To pay by credit card manually, please use the following details or visit our designated payment portal:<br>
                                        <br>
                                        Option 1: Online Portal<br>
                                        Visit: <flux:link href="#">BayarYuk!</flux:link><br>
                                        <br>
                                        Option 2: Over the Phone<br>
                                        Call our customer service at: +62 812 3456 7890 to provide your card details.<br>
                                        <br>
                                        Option 3: In-Person<br>
                                        Visit our ticketing office at: Jl. Travelid Raya No. 31, to use our POS terminal.<br>
                                        <br>
                                        Important: After completing your credit card payment, please upload a screenshot or a PDF of your payment confirmation in the "Payment Proof" section above.
                                        Total Amount: {{ number_format($this->calculateTotalCost(), 2) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif

                        @if($paymentMethod === 'e_wallet')
                        <div class="p-4 bg-gray-800 rounded-lg">
                            <div class="flex items-start gap-3">
                                <div class="text-gray-200">
                                    <flux:icon.information-circle class="w-6 h-6" />
                                </div>
                                <div>
                                    <div class="font-medium text-gray-200">E-Wallet Payment Instructions</div>
                                    <div class="mt-2 text-sm text-gray-100">
                                        To pay using E-Wallet, please follow the instructions below and complete your payment via your chosen e-wallet application:<br>
                                        <br>
                                        Option 1: Scan QR Code<br>
                                        <div class="bg-white w-[300px] h-[300px] flex justify-center items-center">@php echo DNS2D::getBarcodeHTML('https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'QRCODE'); @endphp</div><br>
                                        <br>
                                        Option 2: Transfer to E-Wallet Account<br>
                                        E-Wallet Provider:  Dana, OVO, GoPay, LinkAja<br>
                                        E-Wallet Phone Number: +62 876 5432 1098<br>
                                        Account Name: Traveli<br>
                                        <br>
                                        Important: After completing your e-wallet payment, please upload a screenshot of your successful transaction in the "Payment Proof" section above.
                                        Amount: {{ number_format($this->calculateTotalCost(), 2) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
                
                <div class="flex justify-between">
                    <flux:button type="button" wire:click="backToPassengerInfo" variant="ghost">
                        Back to Passenger Info
                    </flux:button>
                    <flux:button type="submit" variant="primary">
                        Confirm Booking
                    </flux:button>
                </div>
            </form>
        </div>
        @endif
        
        @if(!$isOperational)
        <div class="mt-6 p-6 text-center bg-danger-50 rounded-lg">
            <flux:heading size="xl" class="text-danger-600 mb-4">Booking Not Available</flux:heading>
            <p class="text-gray-600">
                @foreach($operationalErrors as $error)
                    {{ $error }}<br>
                @endforeach
            </p>
            <div class="mt-4">
                <flux:button :href="route('bookings')" wire:navigate>
                    Back to Schedules
                </flux:button>
            </div>
        </div>
        @elseif($availableSeats <= 0)
        <div class="mt-6 p-6 text-center bg-danger-50 rounded-lg">
            <flux:heading size="xl" class="text-danger-600 mb-4">Fully Booked</flux:heading>
            <p class="text-gray-600">There are no available seats for this route. Please try another schedule.</p>
            <div class="mt-4">
                <flux:button :href="route('bookings')" wire:navigate>
                    Back to Schedules
                </flux:button>
            </div>
        </div>
        @endif
    </div>
</div>