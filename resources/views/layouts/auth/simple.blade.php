<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
        <div class="bg-background flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <a href="{{ route('home') }}" class="block w-full max-w-xl overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-black/5" wire:navigate>
                <img
                    src="{{ asset('images/lcm-long.png') }}"
                    alt="{{ config('app.name', 'Laravel') }}"
                    width="3168"
                    height="792"
                    class="aspect-[2/1] w-full object-cover sm:aspect-[4/1]"
                >
            </a>

            <div class="flex w-full max-w-sm flex-col gap-6">
                {{ $slot }}
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
