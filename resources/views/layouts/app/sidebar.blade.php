<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
  <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Administración')" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Panel') }}
                    </flux:sidebar.item>
                   
                </flux:sidebar.group>
               
                <flux:sidebar.item icon="plus" :href="route('admin.catalogos')" :current="request()->routeIs('admin.catalogos')" wire:navigate>
                        {{ __('Gestión Institucional') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="plus" :href="route('admin.rel-instituto-carrera')" :current="request()->routeIs('admin.rel-instituto-carrera')" wire:navigate>
                        {{ __('Instituto-Carrera') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="plus" :href="route('admin.rel-instituto-cargo')" :current="request()->routeIs('admin.rel-instituto-cargo')" wire:navigate>
                        {{ __('Instituto-Cargo') }}
                </flux:sidebar.item>
                 <!-- <flux:sidebar.item icon="plus" :href="route('admin.rel-carrera-cargo')" :current="request()->routeIs('admin.rel-carrera-cargo')" wire:navigate>
                        {{ __('Cargos-Carrera') }}
                </flux:sidebar.item> -->
                
                <flux:sidebar.item icon="plus" :href="route('admin.rel-carrera-espacio')" :current="request()->routeIs('admin.rel-carrera-espacio')" wire:navigate>
                        {{ __('Carrera_Espacios') }}
                </flux:sidebar.item>
               
               
                <flux:sidebar.item icon="plus" :href="route('admin.llamados.crear')" :current="request()->routeIs('admin.llamados.crear')" wire:navigate>
                        {{ __('Crear-Convocatoria') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="plus" :href="route('admin.llamados.publico')" :current="request()->routeIs('admin.llamados.publico')" wire:navigate>
                        {{ __('Ver convocatorias-Publicadas') }}
                </flux:sidebar.item>    
                <flux:sidebar.item icon="plus" :href="route('admin.requisitos')" :current="request()->routeIs('admin.requisitos')" wire:navigate>
                        {{ __('Requisitos/Novedades') }}
                </flux:sidebar.item> 
                <flux:sidebar.item icon="plus" :href="route('admin.lom.publico')" :current="request()->routeIs('admin.lom.publico')" wire:navigate>
                        {{ __('Ver LOM-Publicado') }}
                </flux:sidebar.item>   
                 <flux:sidebar.item icon="plus" :href="route('admin.docentes')" :current="request()->routeIs('admin.docentes')" wire:navigate>
                        {{ __('Gestionar Docentes') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="academic-cap" :href="route('admin.trayecto')" :current="request()->routeIs('admin.trayecto')" wire:navigate>
                        {{ __('Trayecto Formativo') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="building-library" :href="route('admin.trayecto.instituciones')" :current="request()->routeIs('admin.trayecto.instituciones')" wire:navigate>
                        {{ __('Trayecto - Instituciones') }}
                </flux:sidebar.item>
            @if(auth()->user()?->esSuperAdmin())
                <flux:sidebar.item icon="chart-bar" :href="route('admin.dashboard')" :current="request()->routeIs('admin.dashboard')" wire:navigate>
                    {{ __('admin.dashboard') }}
                </flux:sidebar.item>

                <flux:sidebar.item icon="shield-check" :href="route('admin.auditoria')" :current="request()->routeIs('admin.auditoria')" wire:navigate>
                    {{ __('admin.auditoria') }}
                </flux:sidebar.item>
            @endif
                
            </flux:sidebar.nav>

            <flux:spacer />

            <!-- <flux:sidebar.nav>
                <flux:sidebar.item icon="folder-git-2" href="https://github.com/laravel/livewire-starter-kit" target="_blank">
                    {{ __('Repository') }}
                </flux:sidebar.item>

                <flux:sidebar.item icon="book-open-text" href="https://laravel.com/docs/starter-kits#livewire" target="_blank">
                    {{ __('Documentation') }}
                </flux:sidebar.item>
            </flux:sidebar.nav> -->
                <div class="px-2 pb-2">
                    <flux:radio.group variant="segmented" size="sm" x-data x-model="$flux.appearance" class="w-full">
                        <flux:radio value="light" icon="sun" />
                        <flux:radio value="dark" icon="moon" />
                        <flux:radio value="system" icon="computer-desktop" />
                    </flux:radio.group>
                </div>
            @if(auth()->check())
                <x-desktop-user-menu
                    class="hidden lg:block"
                    :name="auth()->user()->name"
                />
            @endif
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <flux:radio.group variant="segmented" size="sm" x-data x-model="$flux.appearance">
                            <flux:radio value="light" icon="sun" />
                            <flux:radio value="dark" icon="moon" />
                            <flux:radio value="system" icon="computer-desktop" />
                        </flux:radio.group>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <!-- <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>  -->

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="Salir-button"
                        >
                            {{ __('Salir') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
