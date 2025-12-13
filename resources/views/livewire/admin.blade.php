{{--
// use Livewire\Attributes\{Layout, Title, Computed, Url};
// use Livewire\Volt\Component;
// use Illuminate\Support\Facades\Storage;
// use App\Models\User;
// use App\Models\Order;
// use App\Models\Tag;
// use App\Models\Bus;
// use App\Models\Terminal;
// use App\Models\RoutesSchedule;
// use Flux\Flux;

// new #[Layout('components.layouts.admin')]
//     #[Title('Dashboard')]

// class extends Component {
//     public string $total_users = '';
//     public string $total_orders = '';
//     public string $total_tags = '';
//     public string $total_buses = '';
//     public string $total_terminals = '';
//     public string $total_routes = '';

//     public function mount()
//     {
//         $this->total_users = User::count();
//         $this->total_orders = Order::count();
//         $this->total_tags = Tag::count();
//         $this->total_buses = Bus::count();
//         $this->total_terminals = Terminal::count();
//         $this->total_routes = RoutesSchedule::count();
//     }
// }; ?>

<div>
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <div class="grid auto-rows-min gap-4 xl:grid-cols-6 lg:grid-cols-3 md:grid-cols-6 grid-cols-3">
            <flux:card class="items-center overflow-hiddenl">
                <flux:text>Total Users</flux:text>
                <flux:heading size="xl" class="mt-2 tabular-nums inline-flex items-center">{{ $this->total_users }}<flux:icon.users class="ml-2"/></flux:heading>
            </flux:card>
            <flux:card class="items-center overflow-hiddenl">
                <flux:text>Total Orders</flux:text>
                <flux:heading size="xl" class="mt-2 tabular-nums inline-flex items-center">{{ $this->total_orders }}<flux:icon.currency-dollar class="ml-2"/></flux:heading>
            </flux:card>
            <flux:card class="items-center overflow-hiddenlo">
                <flux:text>Total Tags</flux:text>
                <flux:heading size="xl" class="mt-2 tabular-nums inline-flex items-center">{{ $this->total_tags }}<flux:icon.tag class="ml-2"/></flux:heading>
            </flux:card>
            <flux:card class="items-center overflow-hiddenlo">
                <flux:text>Total Buses</flux:text>
                <flux:heading size="xl" class="mt-2 tabular-nums inline-flex items-center">{{ $this->total_buses }}<flux:icon.truck class="ml-2"/></flux:heading>
            </flux:card>
            <flux:card class="items-center overflow-hiddenlo">
                <flux:text>Total Terminals</flux:text>
                <flux:heading size="xl" class="mt-2 tabular-nums inline-flex items-center">{{ $this->total_terminals }}<flux:icon.home-modern class="ml-2"/></flux:heading>
            </flux:card>
            <flux:card class="items-center overflow-hiddenlo">
                <flux:text>Total Schedules</flux:text>
                <flux:heading size="xl" class="mt-2 tabular-nums inline-flex items-center">{{ $this->total_routes }}<flux:icon.map-pin class="ml-2"/></flux:heading>
            </flux:card>
        </div>
        <div class="relative h-full flex-1 overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
            <x-placeholder-pattern class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" />
        </div>
    </div>
    <br>
    <div class="text-center">
        <flux:heading size="xl">WELCOME BACK ADMIN {{ Auth::user()->name }} !</flux:heading>
    </div>
</div> --}}

<?php
use Livewire\Attributes\{Layout, Title, Computed, Url};
use Livewire\Volt\Component;
use App\Models\Bus;
use App\Models\Order;
use App\Models\OrdersSeat;
use App\Models\RoutesSchedule;
use App\Models\Tag;
use App\Models\TagType;
use App\Models\Terminal;
use App\Models\User;

new #[Layout('components.layouts.admin')]
    #[Title('Dashboard')]
class extends Component {
    public $stats = [];

    public function mount()
    {
        $this->stats = [
            [
                'title' => 'Buses',
                'count' => Bus::count(),
                'icon' => 'truck',
            ],
            [
                'title' => 'Orders',
                'count' => Order::count(),
                'icon' => 'currency-dollar',
            ],
            [
                'title' => 'Booked Seats',
                'count' => OrdersSeat::count(),
                'icon' => 'shopping-cart',
            ],
            [
                'title' => 'Routes',
                'count' => RoutesSchedule::count(),
                'icon' => 'map-pin',
            ],
            [
                'title' => 'Tags',
                'count' => Tag::count(),
                'icon' => 'tag',
            ],
            [
                'title' => 'Tag Types',
                'count' => TagType::count(),
                'icon' => 'tag',
            ],
            [
                'title' => 'Terminals',
                'count' => Terminal::count(),
                'icon' => 'building-storefront',
            ],
            [
                'title' => 'Users',
                'count' => User::count(),
                'icon' => 'users',
            ],
        ];
    }
}; ?>

<div>
    <div class="text-center">
    <flux:heading size="xl">WELCOME BACK
                                        @if ((auth()->user()->role ?? '') === 'admin')
                                        ADMIN
                                        @else
                                        STAFF
                                        @endif
                                        {{ Auth::user()->name }}!</flux:heading>
    </div>
    <br><br>
    <flux:separator text="DASHBOARD" />
    <br><br>
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        @foreach($stats as $stat)
            <flux:card class="overflow-hidden min-w-[12rem]">
                <div class="flex items-center justify-between">
                    <div>
                        <flux:text>{{ $stat['title'] }}</flux:text>
                        <flux:heading size="xl" class="mt-2 tabular-nums">{{ number_format($stat['count']) }}</flux:heading>
                    </div>
                    <x-icon :name="$stat['icon']" class="w-8 h-8" />
                </div>
            </flux:card>
        @endforeach
    </div>
</div>