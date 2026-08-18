<?php

namespace App\Listeners;
use App\Support\Auditoria;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Facades\Event;

/**
 * Registra accesos al sistema (login/logout/intentos fallidos) en tb_auditoria.
 * Se engancha a los eventos nativos de Laravel que Fortify ya dispara,
 * sin tocar el formulario de login.
 */
class AuditoriaAccesoListener
{
    public function boot(): void
    {
        Event::listen(Login::class,  [AuditoriaAccesoListener::class, 'handleLogin']);
        Event::listen(Logout::class, [AuditoriaAccesoListener::class, 'handleLogout']);
        Event::listen(Failed::class, [AuditoriaAccesoListener::class, 'handleFailed']);
    }
    public function handleLogin(Login $event): void
    {
        Auditoria::registrar('login', 'usuario', $event->user->id, "Inicio de sesión: {$event->user->email}");
    }

    public function handleLogout(Logout $event): void
    {
        if ($event->user) {
            Auditoria::registrar('logout', 'usuario', $event->user->id, "Cierre de sesión: {$event->user->email}");
        }
    }

    public function handleFailed(Failed $event): void
    {
        $email = $event->credentials['email'] ?? 'desconocido';
        Auditoria::registrar('login_fallido', 'usuario', null, "Intento de acceso fallido: {$email}");
    }
}
