<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="flex min-h-screen flex-col bg-white text-zinc-900 dark:bg-zinc-900 dark:text-zinc-100">
        <flux:header container class="h-20! border-b border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <x-app-logo href="{{ route('home') }}" wire:navigate />

            <flux:navbar class="ms-6 max-sm:hidden">
                <flux:navbar.item icon="squares-2x2" :href="route('home')" :current="request()->routeIs('home', 'products.show')" wire:navigate>
                    {{ __('Shop') }}
                </flux:navbar.item>
                @auth
                    <flux:navbar.item icon="clipboard-document-list" :href="route('orders')" :current="request()->routeIs('orders')" wire:navigate>
                        {{ __('My orders') }}
                    </flux:navbar.item>
                @endauth
                @can('manage-shop')
                    <flux:navbar.item icon="cog-6-tooth" :href="route('admin.products')" wire:navigate>
                        {{ __('Admin') }}
                    </flux:navbar.item>
                @endcan
            </flux:navbar>

            <flux:spacer />

            <livewire:cart-badge />

            @auth
                <flux:dropdown position="bottom" align="end">
                    <flux:profile :initials="auth()->user()->initials()" icon-trailing="chevron-down" />

                    <flux:menu>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>{{ __('Settings') }}</flux:menu.item>
                        <flux:menu.item :href="route('orders')" icon="clipboard-document-list" wire:navigate>{{ __('My orders') }}</flux:menu.item>
                        <flux:menu.separator />
                        <form method="POST" action="{{ route('logout') }}" class="w-full">
                            @csrf
                            <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full cursor-pointer">
                                {{ __('Log out') }}
                            </flux:menu.item>
                        </form>
                    </flux:menu>
                </flux:dropdown>
            @else
                <flux:button :href="route('login')" variant="ghost" size="sm" wire:navigate>{{ __('Log in') }}</flux:button>
                @if (Route::has('register'))
                    <flux:button :href="route('register')" variant="primary" size="sm" class="ms-1" wire:navigate>{{ __('Create account') }}</flux:button>
                @endif
            @endauth
        </flux:header>

        <main class="flex-1">
            {{ $slot }}
        </main>

        <footer class="border-t border-zinc-200 py-8 dark:border-zinc-700">
            <div class="mx-auto flex max-w-7xl flex-col gap-2 px-6 text-sm text-zinc-500 sm:flex-row sm:items-center sm:justify-between lg:px-8">
                <span>&copy; {{ now()->year }} LCM Solutions | B2B Retail Site</span>
                <span>{{ __('Guest checkout available — no account required.') }}</span>
            </div>
        </footer>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
