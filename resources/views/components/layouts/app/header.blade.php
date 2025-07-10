<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:header container class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 sticky top-0">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <a href="{{ route('home') }}" class="ml-2 mr-5 flex items-center space-x-2 lg:ml-0" wire:navigate>
                <x-app-logo />
            </a>

            <flux:navbar class="-mb-px max-lg:hidden">
                <flux:navbar.item icon="home" :href="route('home')" :current="request()->routeIs('home')" wire:navigate>
                    {{ __('Home') }}
                </flux:navbar.item>
                <flux:navbar.item icon="ticket" :href="route('bookings')" :current="request()->routeIs('bookings')" wire:navigate>
                    {{ __('Book') }}
                </flux:navbar.item>
                <flux:navbar.item icon="question-mark-circle" :href="route('about')" :current="request()->routeIs('about')" wire:navigate>
                    {{ __('About') }}
                </flux:navbar.item>
                @auth
                <flux:navbar.item icon="layout-grid" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                    {{ __('Dashboard') }}
                </flux:navbar.item>
                @endauth
            </flux:navbar>

            <flux:spacer />

            <!-- Desktop User Menu -->
            <flux:dropdown position="top" align="end">
                <flux:profile
                class="cursor-pointer"
                avatar="{{ auth()->check() ? (auth()->user()->avatar ? asset('storage/' . auth()->user()->avatar) : asset('images/avatar.webp')) : asset('images/avatar.webp') }}"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                                <flux:avatar 
                                src="{{ auth()->check() ? (auth()->user()->avatar ? asset('storage/' . auth()->user()->avatar) : asset('images/avatar.webp')) : asset('images/avatar.webp') }}"
                                />

                                <div class="grid flex-1 text-left text-sm leading-tight">
                                    <span class="truncate font-semibold">
                                        {{ auth()->check() ? auth()->user()->name : 'Guest' }}
                                    </span>
                                    <span class="truncate text-xs">
                                        {{ auth()->check() ? auth()->user()->email : 'Please sign in' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        
                        @if ((auth()->user()->email ?? '') === 'masterrally1808@gmail.com' && auth()->user()->hasVerifiedEmail())
                        <flux:menu.item :href="route('admin')" icon="arrow-right-end-on-rectangle" wire:navigate> {{ __('Admin') }} </flux:menu.item>
                        @endif
                        @auth
                        <flux:menu.item :href="route('settings.profile')" icon="cog" wire:navigate>{{ __('Settings') }}</flux:menu.item>
                        @else
                        <flux:menu.item :href="route('login')" icon="arrow-left-end-on-rectangle" wire:navigate>{{ __('Login') }}</flux:menu.item>
                        @endauth
                    </flux:menu.radio.group>
                    @auth
                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                            {{ __('Log Out') }}
                        </flux:menu.item>
                        
                    </form>
                    @endauth
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        <!-- Mobile Menu -->
        <flux:sidebar stashable sticky class="lg:hidden border-r border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

            <a href="{{ route('dashboard') }}" class="ml-1 flex items-center space-x-2" wire:navigate>
                <x-app-logo />
            </a>

            <flux:navlist variant="outline">
                <flux:navlist.group :heading="__('Platform')">
                    <flux:navlist.item icon="home" :href="route('home')" :current="request()->routeIs('home')" wire:navigate>
                    {{ __('Home') }}
                    </flux:navlist.item>
                    <flux:navlist.item icon="ticket" :href="route('bookings')" :current="request()->routeIs('bookings')" wire:navigate>
                    {{ __('Book') }}
                    </flux:navlist.item>
                    <flux:navlist.item icon="question-mark-circle" :href="route('about')" :current="request()->routeIs('about')" wire:navigate>
                    {{ __('About') }}
                </flux:navlist.item>
                    @auth
                    <flux:navlist.item icon="layout-grid" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:navlist.item>
                    @endauth
                </flux:navlist.group>
            </flux:navlist>
        </flux:sidebar>

        {{ $slot }}

        @fluxScripts
        @persist('toast')
            <flux:toast />
        @endpersist
    </body>
</html>
