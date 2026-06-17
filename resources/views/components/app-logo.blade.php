@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand name="SAGE Superior" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md text-accent-foreground">
        <img src="/favicon.svg" alt="Logo" class="size-5 fill-current text-black dark:text-white" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand name="SAGE Superior" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md text-accent-foreground">
        <img src="/favicon.svg" alt="Logo" class="size-5 fill-current text-black dark:text-white" />
        </x-slot>
    </flux:brand>
@endif
