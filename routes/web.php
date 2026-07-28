<?php

use Livewire\Volt\Volt;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LlamadoPdfController;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {

    Route::view('/dashboard', 'dashboard')
        ->name('dashboard');

    // ADMIN

    Route::livewire(
        '/admin/llamados',
        'pages.admin.llamados.index'
    )->name('admin.llamados.index');

    Route::livewire(
        '/admin/llamados/crear',
        'pages.admin.llamados.crear'
    )->name('admin.llamados.crear');

    Route::livewire(
        '/admin/llamados/{id}/edit',
        'pages.admin.llamados.edit'
    )->name('admin.llamados.edit');

    Route::livewire(
        '/admin/rel-instituto-carrera',
        'pages.admin.rel-instituto-carrera.⚡index'
    )->name('admin.rel-instituto-carrera');

    Route::livewire(
        '/admin/rel-carrera-espacio',
        'pages.admin.rel-carrera-espacio.espacio-carrera'
    )->name('admin.rel-carrera-espacio');

    Route::livewire(
        '/admin/rel-carrera-cargo',
        'pages.admin.rel-carrera-cargo.cargo-carrera'
    )->name('admin.rel-carrera-cargo');

    Route::livewire(
        '/admin/rel-instituto-cargo',
        'pages.admin.rel-instituto-cargo.instituto-cargo'
    )->name('admin.rel-instituto-cargo');

    Route::get('/perfil', \App\Livewire\Perfil::class)->name('perfil');
    Route::livewire('/post/create', 'pages.post.⚡create')->name('post.create');
    Route::livewire('/contador', 'pages.contador.index')->name('contador');
    Route::livewire('/perfil', 'pages.perfil.perfil')->name('perfil');
    Route::livewire('/dependientes', 'pages.dependientes.⚡index')->name('dependientes');
    Route::livewire('/llamados', 'pages.llamados.⚡index')->name('llamados');

    Route::get('/admin/llamados/{llamadoId}/pdf', [LlamadoPdfController::class, 'generar'])
     ->name('admin.llamados.pdf');
  
   Volt::route('/admin/lom', 'pages.admin.lom.lomcrear')
    ->name('admin.lom.crear');
     Volt::route('admin/catalogos', 'pages.admin.catalogos.abm-catalogos')->name('admin.catalogos');
  
     Volt::route('admin/requisitos', 'pages.admin.requisitos')
        ->name('admin.requisitos');

     Volt::route('admin/docentes', 'pages.admin.docentes.gestionar-docentes')
        ->name('admin.docentes');
});
 
// PUBLICAS

Volt::route('/publico', 'pages.admin.llamados.publico')
    ->name('admin.llamados.publico');

    // LOM


Volt::route('/lom', 'pages.admin.lom.lompublico')
    ->name('admin.lom.publico');
    
Volt::route('/inscripcion-llamado', 'inscripcion-llamado');
Volt::route('/gestionar-inscriptos', 'gestionar-inscriptos');

Volt::route('/lom/{id}', 'pages.admin.lom.lomvista')
    ->name('lom.vista');
// ── CONSTANCIA PDF (pública, sin login) ──────────────────────────────
Route::get('/constancia/{codigo}', [InscripcionConstanciaController::class, 'descargar'])
    ->name('constancia.descargar');    

Route::get('/constancia/{codigo}', [App\Http\Controllers\InscripcionConstanciaController::class, 'descargar'])
    ->name('constancia.descargar');
        
require __DIR__.'/settings.php';
require __DIR__.'/auth.php';