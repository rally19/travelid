<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-neutral-900 antialiased">
        <div class="relative grid h-dvh flex-col items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0">
            <div class="bg-muted relative hidden h-full flex-col p-10 text-white lg:flex dark:border-r dark:border-neutral-800">
                @php
                    $photos = [
                        'https://cdn1-production-images-kly.akamaized.net/q5GNCPhXV6wmbPxs3RLsBHL-r7k=/1200x1200/smart/filters:quality(75):strip_icc():format(webp)/kly-media-production/medias/2552640/original/084804000_1545285455-20181220-Naik-Bus_-Jokowi-Uji-Coba-Tol-Trans-Jawa-Angga4.jpg',
                        'https://asset.kompas.com/crops/ZNv6oDt8qtT93jJDdJfxLrweb1I=/0x272:3264x2448/1200x800/data/photo/2023/07/05/64a5a12ab6833.jpg',
                        'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTvag5PMDocbNQpGo-a8MPF-OqCdO48JP7STw&s',
                        'https://i.ytimg.com/vi/qD0_5PzCt2I/maxresdefault.jpg',
                        'https://awsimages.detik.net.id/community/media/visual/2019/05/28/aa10eb72-f67a-4c27-8189-0637a7c2e8f9_169.jpeg?w=600&q=90',
                        'https://asset.kompas.com/crops/RFXgIhJzDSnXxtjoLnmkHFz6foU=/0x3:1280x857/1200x800/data/photo/2022/01/09/61dabcb9d2c9b.jpg',
                        'https://pict.sindonews.net/dyn/850/pena/news/2021/11/19/171/604611/catat-21-terminal-bus-di-jakarta-melayani-penumpang-akdp-dan-akap-mct.jpg',
                    ];
                    $randomPhoto = Arr::random($photos);
                @endphp
                <div class="absolute inset-0 bg-neutral-900">
                    <img src="{{ asset($randomPhoto) }}" class="w-full h-full object-cover">
                    <div class="absolute inset-0 bg-gradient-to-b from-black/50 to-black/80"></div>
                </div>
                <a href="{{ route('home') }}" class="relative z-20 flex items-center text-lg font-medium" wire:navigate>
                    <span class="flex h-10 w-10 items-center justify-center rounded-md">
                        <x-app-logo-icon class="mr-2 h-7 fill-current text-white" />
                    </span>
                    {{ config('app.name', 'TravelID') }}
                </a>

                @php
                    [$message, $author] = str(Illuminate\Foundation\Inspiring::quotes()->random())->explode('-');
                @endphp

                <div class="relative z-20 mt-auto">
                    <blockquote class="space-y-2">
                        <flux:heading size="lg">&ldquo;{{ trim($message) }}&rdquo;</flux:heading>
                        <footer><flux:heading>{{ trim($author) }}</flux:heading></footer>
                    </blockquote>
                </div>
            </div>
            <div class="w-full lg:p-8">
                <div class="mx-auto flex w-full flex-col justify-center space-y-6 sm:w-[350px]">
                    <a href="{{ route('home') }}" class="z-20 flex flex-col items-center gap-2 font-medium lg:hidden" wire:navigate>
                        <span class="flex h-9 w-9 items-center justify-center rounded-md">
                            <x-app-logo-icon class="size-9 fill-current text-black dark:text-white" />
                        </span>

                        <span class="sr-only">{{ config('app.name', 'TravelID') }}</span>
                    </a>
                    {{ $slot }}
                    <flux:button variant="subtle" size="sm" class="w-32 block mx-auto" :href="route('home')" wire:navigate>Return</flux:button>
                </div>
            </div>
        </div>
        @fluxScripts
        @persist('toast')
            <flux:toast />
        @endpersist
    </body>
</html>