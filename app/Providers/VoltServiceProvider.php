<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Blade;
use Livewire\Volt\Volt;

class VoltServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Volt::mount([
            config('livewire.view_path', resource_path('views/livewire')),
            resource_path('views/livewire/pages'),
        ]);

        View::addNamespace(
            'pages',
            resource_path('views/livewire/pages')
        );

        Blade::anonymousComponentPath(
            resource_path('views/livewire/pages'),
            'pages'
        );
    }
}