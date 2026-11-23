@props([
    'sidebar' => false,
])

<a {{ $attributes->class('inline-flex shrink-0 items-center') }}>
    <img
        src="{{ asset('images/lcm-long-header.png') }}"
        alt="{{ config('app.name', 'LCM Solutions') }}"
        class="{{ $sidebar ? 'h-14' : 'h-16' }} w-auto rounded-md bg-white p-1"
    />
</a>
