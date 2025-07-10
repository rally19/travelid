<?php
use Livewire\Attributes\{Layout, Title, Url};
use Livewire\Volt\Component;
use App\Models\Order;
use App\Models\OrdersSeat;
use Illuminate\Support\Facades\Auth;
use Flux\Flux;

new #[Layout('components.layouts.app')]
    #[Title('Booking Invoice')]
class extends Component {
    public Order $order;
    public $seats = [];
    
    public function mount($id): void
    {
        $this->order = Order::with(['seats', 'user'])
            ->where('id', $id)
            ->where('users_id', Auth::id())
            ->firstOrFail();
            
        $this->seats = $this->order->seats;
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
    
    public function formatPrice($price): string
    {
        return number_format($price, 2);
    }
}; ?>

<div id="invoice">
    <div class="flex flex-row gap-4 justify-end hide-print"
        x-data="{ 
            originalAppearance: $flux.appearance, 
            printWithLightTheme() {
                if (this.originalAppearance !== 'light') {
                    $flux.appearance = 'light';
                    setTimeout(() => {
                        window.print();
                        setTimeout(() => {
                            $flux.appearance = this.originalAppearance;
                        }, 500);
                    }, 100);
                } else {
                    window.print();
                }
            }
        }"
    >
        <flux:button 
            variant="primary" 
            icon="printer"
            @click="printWithLightTheme()"
        >
            Print Tickets
        </flux:button>
        <flux:button 
            :href="route('dashboard')" 
            wire:navigate
        >
            Back
        </flux:button>
    </div>
    <style>
        @media print {
            html, body {
                margin: 0;
                padding: 0;
                width: 100%;
                height: 100%;
                visibility: hidden;
            }

            #invoice {
                visibility: visible;
                position: absolute;
                left: 0;
                top: 0;
                width: 100vw; /* Use full width of viewport */
                max-width: 100%; /* Prevent any max width limitations */
                padding: 0;
                margin: 0;
            }

            .hide-print {
                display: none;
            }
        }
    </style>
    <div class="flex mb-2">
        <div class="flex aspect-square size-8 items-center justify-center rounded-md bg-accent-content text-accent-foreground mr-2">
            <x-app-logo-icon class="size-5 fill-current text-white dark:text-black" />
        </div>
        <flux:heading size="xl"> TravelID</flux:heading>
    </div>

    <div class="outline rounded-lg overflow-hidden hover:shadow-md transition-shadow mb-8">
        <div class=" p-6">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <flux:heading size="xl">Booking Invoice</flux:heading>
                    <div class="text-neutral-600 dark:text-neutral-400">Order #{{ $order->code }}</div>
                </div>
                <div class="text-right">
                    <flux:badge variant="solid" :color="match($order->status) {
                        'success' => 'lime',
                        'pending' => 'yellow',
                        'cancelled' => 'zinc',
                        'failed' => 'red',
                        default => 'zinc'
                    }">
                        {{ ucfirst($order->status) }}
                    </flux:badge>
                    <div class="text-sm text-neutral-600 dark:text-neutral-400 mt-1">
                        Booked on {{ $order->created_at->format('M j, Y H:i') }}
                    </div>
                </div>
            </div>
            <flux:separator class="mb-4"/>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <flux:heading size="lg" class="mb-4">Departure</flux:heading>
                    <div class="space-y-2">
                        <div class="font-medium">{{ $order->departure_terminal }}</div>
                        <div class="text-neutral-600 dark:text-neutral-400">{{ $order->departure_location }}</div>
                        <div class="mt-2 font-medium text-primary-600">
                            {{ $order->departure_time->format('l, F j, Y H:i') }}
                        </div>
                    </div>
                </div>
                <flux:separator class="md:hidden"/>
                <div>
                    <flux:heading size="lg" class="mb-4">Arrival</flux:heading>
                    <div class="space-y-2">
                        <div class="font-medium">{{ $order->arrival_terminal }}</div>
                        <div class="text-neutral-600 dark:text-neutral-400">{{ $order->arrival_location }}</div>
                        <div class="mt-2 font-medium text-primary-600">
                            {{ $order->arrival_time->format('l, F j, Y H:i') }}
                        </div>
                    </div>
                </div>
            </div>
            <flux:separator class="md:hidden"/>
            <div class="flex flex-col md:flex-row justify-between gap-4 mt-6">
                <div>
                    <div class="text-neutral-600 dark:text-neutral-400">Bus</div>
                    <div class="font-medium">{{ $order->bus_name }} ({{ $order->bus_code }})</div>
                </div>
                
                <div class="md:text-right">
                    <div class="text-neutral-600 dark:text-neutral-400">Duration</div>
                    <div class="font-medium">
                        {{ $this->calculateDuration($order->departure_time, $order->arrival_time) }}
                    </div>
                </div>
                
                <div class="md:text-right">
                    <div class="text-neutral-600 dark:text-neutral-400">Passengers</div>
                    <div class="font-medium">{{ $order->quantity }}</div>
                </div>
                
                <div class="md:text-right">
                    <div class="text-neutral-600 dark:text-neutral-400">Total Cost</div>
                    <div class="font-medium text-primary-600">
                        Rp {{ $this->formatPrice($order->total_cost) }}
                    </div>
                </div>
                
                <div class="md:text-right">
                    <div class="text-neutral-600 dark:text-neutral-400">Payment Method</div>
                    <div class="font-medium">{{ ucwords(str_replace('_', ' ', $order->payment_method)) }}</div>
                </div>
            </div>
        </div>
        <div class="border-t p-4 flex justify-center bg-gray-50 sm:hidden">
            
            @php
                echo DNS1D::getBarcodeSVG($order->code, 'C128',0.9,80)
            @endphp
        </div>
        <div class="border-t p-4 justify-center bg-gray-50 hidden sm:flex">
            @php
                echo DNS1D::getBarcodeSVG($order->code, 'C128',1.3,80)
            @endphp
        </div>
    </div>
    
    <flux:heading size="xl" class="mb-4 hide-print">Passenger Tickets</flux:heading>
    <div class="space-y-6">
        @foreach($seats as $seat)
        <div style="break-after:page"></div>
        <div class="flex mb-2">
            <div class="flex aspect-square size-8 items-center justify-center rounded-md bg-accent-content text-accent-foreground mr-2">
                <x-app-logo-icon class="size-5 fill-current text-white dark:text-black" />
            </div>
            <flux:heading size="xl"> TravelID</flux:heading>
        </div>
        <div class="outline rounded-lg overflow-hidden hover:shadow-md transition-shadow">
            <div class="bg-primary-600 p-4 flex justify-between items-center">
                <div>
                    <div class="font-bold">{{ $order->bus_name }}</div>
                    <div class="text-sm text-neutral-600 dark:text-neutral-400">{{ $order->bus_code }}</div>
                </div>
                <div class="text-right">
                    <div class="font-bold truncate">Ticket #{{ $seat->code }}</div>
                    <div class="text-sm text-neutral-600 dark:text-neutral-400">Seat {{ $loop->iteration }}</div>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 p-6 outline">
                <div>
                    <flux:heading size="md" class="mb-4">Passenger</flux:heading>
                    <div class="space-y-2">
                        <div class="font-medium">{{ $seat->title }}. {{ $seat->name }}</div>
                        <div class="text-neutral-600 dark:text-neutral-400">Age: {{ $seat->age }}</div>
                    </div>
                </div>
                <flux:separator class="md:hidden"/>
                <div>
                    <flux:heading size="md" class="mb-4">Route</flux:heading>
                    <div class="space-y-2">
                        <div>
                            <div class="text-sm text-neutral-600 dark:text-neutral-400">Departure</div>
                            <div class="font-medium">{{ $order->departure_terminal }}</div>
                            <div class="text-sm text-neutral-600 dark:text-neutral-400">
                                {{ $order->departure_time->format('D, M j, Y H:i') }}
                            </div>
                        </div>
                        <div class="border-l-2 border-primary-400 pl-3 ml-1 my-2">
                            <div class="text-sm text-neutral-600 dark:text-neutral-400">
                                {{ $this->calculateDuration($order->departure_time, $order->arrival_time) }} journey
                            </div>
                        </div>
                        <div>
                            <div class="text-sm text-neutral-600 dark:text-neutral-400">Arrival</div>
                            <div class="font-medium">{{ $order->arrival_terminal }}</div>
                            <div class="text-sm text-neutral-600 dark:text-neutral-400">
                                {{ $order->arrival_time->format('D, M j, Y H:i') }}
                            </div>
                        </div>
                    </div>
                </div>
                <flux:separator class="md:hidden"/>
                <div>
                    <flux:heading size="md" class="mb-4">Ticket Details</flux:heading>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-neutral-600 dark:text-neutral-400">Ticket Price:</span>
                            <span class="font-medium">Rp {{ $this->formatPrice($seat->cost) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-neutral-600 dark:text-neutral-400">Ticket Status:</span>
                            <span class="font-medium">{{ ucfirst($order->status) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-neutral-600 dark:text-neutral-400">Ticket Code:</span>
                            <span class="font-mono">{{ $seat->code }}</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="border-t p-4 flex justify-center bg-gray-50 sm:hidden">
                @php
                    echo DNS1D::getBarcodeSVG($seat->code, 'C128',0.9,80)
                @endphp
            </div>
            <div class="border-t p-4 justify-center bg-gray-50 hidden sm:flex">
                @php
                    echo DNS1D::getBarcodeSVG($seat->code, 'C128',1.3,80)
                @endphp
            </div>
        </div>
        @endforeach
    </div>
</div>