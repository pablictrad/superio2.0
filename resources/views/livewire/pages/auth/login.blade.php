<x-layouts::auth :title="__('Login')"> 
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('SAGE')" :description="__('inicia sesión en tu cuenta')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('Correo electrónico')"
                :value="old('email')"
                type="email"
                required
                autofocus
                autocomplete="email"
                placeholder="email@example.com"
            />

            <!-- Password -->
            <div class="relative">
                <flux:input
                    name="password"
                    :label="__('Contraseña')"
                    type="password"
                    required
                    autocomplete="current-password"
                    :placeholder="__('Contraseña')"
                    viewable
                />

                @if (Route::has('password.request'))
                    <!-- <flux:link class="absolute top-0 text-sm end-0" :href="route('password.request')" wire:navigate>
                        {{ __('Olvidaste tu contraseña?') }}
                    </flux:link> -->
                @endif
            </div>

            <!-- Remember Me -->
            <!-- <flux:checkbox name="remember" :label="__('Recuérdame')" :checked="old('remember')" /> -->

            <div class="flex items-center justify-end">
                <flux:button variant="primary" type="submit" class="w-full bg-green-600 hover:bg-green-700 border-green-600 text-white" data-test="login-button">
                    {{ __('Iniciar sesión') }}
                </flux:button>
            </div>
        </form>

        @if (Route::has('register'))
            <!-- <div class="space-x-1 text-sm text-center rtl:space-x-reverse text-zinc-600 dark:text-zinc-400">
                <span>{{ __('No tienes una cuenta?') }}</span>
                <flux:link :href="route('register')" wire:navigate>{{ __('Regístrate') }}</flux:link>
            </div> -->
        @endif
    </div>
</x-layouts::auth>
