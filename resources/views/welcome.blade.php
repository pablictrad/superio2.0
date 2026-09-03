<!DOCTYPE html>
<!-- <html lang="{{ str_replace('_', '-', app()->getLocale()) }}"> -->
<html lang="es" class="light" style="color-scheme: light;">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Convocatoria Docente — Nivel Superior La Rioja</title>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        <style>
            *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

            body {
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                background: #e8ede8;
              
                background-size: cover;
                background-position: center;
                background-repeat: no-repeat;
                font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
                padding: 1.5rem;
            }

            /* ── TARJETA PRINCIPAL ── */
            .card {
                width: 100%;
                max-width: 680px;
                border-radius: 16px;
                overflow: hidden;
                box-shadow:
                    0 4px 6px rgba(0,0,0,0.05),
                    0 20px 60px rgba(0,0,0,0.18),
                    0 0 0 1px rgba(255,255,255,0.3);
            }

            /* ── HERO VERDE ── */
            .hero {
                background: #2a7a2a;
                background-image:
                    radial-gradient(ellipse 70% 50% at 25% 65%, #1c5e1c 0%, transparent 65%),
                    radial-gradient(ellipse 55% 65% at 85% 25%, #3d9e3d 0%, transparent 55%);
                padding: 5rem 2.5rem 5rem;
              
                display: flex;
                flex-direction: column;
                align-items: center;
                position: relative;
                overflow: hidden;
            }

            /* Textura orgánica tipo fondo original */
            .hero::before {
                content: '';
                position: absolute;
                inset: 0;
                background-image: url("data:image/svg+xml,%3Csvg width='500' height='220' viewBox='0 0 500 220' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' stroke='%231a5c1a' stroke-width='14' opacity='0.3'%3E%3Cpath d='M-30 90 Q50 45 100 90 Q150 135 200 90 Q250 45 300 90 Q350 135 400 90 Q450 45 530 90'/%3E%3Cpath d='M-30 155 Q50 110 100 155 Q150 200 200 155 Q250 110 300 155 Q350 200 400 155 Q450 110 530 155'/%3E%3Cpath d='M-30 25 Q50 -20 100 25 Q150 70 200 25 Q250 -20 300 25 Q350 70 400 25 Q450 -20 530 25'/%3E%3Ccircle cx='70' cy='115' r='7'/%3E%3Ccircle cx='210' cy='60' r='5'/%3E%3Ccircle cx='340' cy='125' r='8'/%3E%3Ccircle cx='140' cy='178' r='5'/%3E%3Ccircle cx='270' cy='185' r='6'/%3E%3Ccircle cx='430' cy='70' r='4'/%3E%3C/g%3E%3C/svg%3E");
                background-size: cover;
                pointer-events: none;
            }

            .hero-title {
                font-size: clamp(22px, 5vw, 30px);
                font-weight: 800;
                letter-spacing: 0.06em;
                text-transform: uppercase;
                color: #fff;
                text-align: center;
                position: relative;
                text-shadow: 0 2px 12px rgba(0,0,0,0.3);
                margin-bottom: 0.35rem;
                line-height: 1.15;
            }

            .hero-sub {
                font-size: 13px;
                color: rgba(255,255,255,0.80);
                text-align: center;
                position: relative;
                letter-spacing: 0.04em;
                margin-bottom: 2.2rem;
            }

            /* ── BOTONES ── */
            .cta-row {
                display: flex;
                gap: 14px;
                justify-content: center;
                flex-wrap: wrap;
                position: relative;
            }

            .btn-conv {
                display: inline-flex;
                align-items: center;
                gap: 9px;
                background: #fff;
                color: #1a5c1a;
                font-size: 14px;
                font-weight: 700;
                letter-spacing: 0.03em;
                padding: 14px 28px;
                border-radius: 10px;
                border: none;
                cursor: pointer;
                text-decoration: none;
                box-shadow: 0 4px 14px rgba(0,0,0,0.2);
                transition: transform 0.15s ease, box-shadow 0.15s ease;
            }
            .btn-conv:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 22px rgba(0,0,0,0.25);
            }
            .btn-conv:active { transform: translateY(0); }

            .btn-lom {
                display: inline-flex;
                align-items: center;
                gap: 9px;
                background: rgba(255,255,255,0.12);
                color: #fff;
                font-size: 14px;
                font-weight: 700;
                letter-spacing: 0.03em;
                padding: 14px 28px;
                border-radius: 10px;
                border: 1.5px solid rgba(255,255,255,0.55);
                cursor: pointer;
                text-decoration: none;
                transition: transform 0.15s ease, background 0.15s ease;
            }
            .btn-lom:hover {
                background: rgba(255,255,255,0.22);
                transform: translateY(-2px);
            }
            .btn-lom:active { transform: translateY(0); }

            /* Íconos SVG inline */
            .icon {
                width: 18px;
                height: 18px;
                flex-shrink: 0;
            }

            /* ── ZONA INFERIOR ── */
            .body-area {
                background: #f4f4f1;
                padding: 1.8rem 2rem 1.6rem;
                display: flex;
                flex-direction: column;
                gap: 1.4rem;
                position: relative;
                overflow: hidden;
            }

            /* Textura ondulada fondo inferior */
            .body-area::before {
                content: '';
                position: absolute;
                inset: 0;
                background-image: url("data:image/svg+xml,%3Csvg width='700' height='260' viewBox='0 0 700 260' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' stroke='%23cbcbc6' stroke-width='20' opacity='0.5'%3E%3Cpath d='M-50 130 Q100 60 200 130 Q300 200 400 130 Q500 60 600 130 Q700 200 800 130'/%3E%3Cpath d='M-50 220 Q100 150 200 220 Q300 290 400 220 Q500 150 600 220 Q700 290 800 220'/%3E%3Cpath d='M-50 40 Q100 -30 200 40 Q300 110 400 40 Q500 -30 600 40 Q700 110 800 40'/%3E%3C/g%3E%3C/svg%3E");
                background-size: cover;
                pointer-events: none;
            }

            /* ── CHIPS ── */
            .chips-row {
                display: flex;
                gap: 9px;
                flex-wrap: wrap;
                justify-content: center;
                position: relative;
            }

            .chip {
                background: #fff;
                border: 0.5px solid #d6d6d2;
                border-radius: 999px;
                padding: 5px 14px;
                font-size: 11.5px;
                color: #555;
                display: flex;
                align-items: center;
                gap: 5px;
            }

            .chip-dot {
                width: 6px;
                height: 6px;
                border-radius: 50%;
                background: #2a7a2a;
                flex-shrink: 0;
            }

            /* ── LOGOS INSTITUCIONALES ── */
            .logos-bar {
                position: relative;
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding-top: 1.2rem;
                border-top: 0.5px solid #d0d0cc;
                flex-wrap: wrap;
                gap: 14px;
                padding-left: 2rem;
            }

            .logos-left {
                display: flex;
                gap: 18px;
                flex-wrap: wrap;
                align-items: flex-end;
            }

            .logo-item {
                display: flex;
                flex-direction: column;
                line-height: 1.3;
            }
            .logo-top {
                font-size: 9px;
                color: #666;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                font-weight: 400;
            }
            .logo-bot {
                font-size: 10px;
                color: #1a1a1a;
                text-transform: uppercase;
                letter-spacing: 0.04em;
                font-weight: 700;
            }

            .logo-sep {
                width: 0.5px;
                height: 26px;
                background: #d0d0cc;
                align-self: center;
            }

            /* Escudo La Rioja */
            .logo-rioja {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 3px;
                padding-right: 2rem;
            }
           
            .rioja-sub {
                font-size: 8.5px;
                color: #777;
                letter-spacing: 0.2em;
                text-transform: uppercase;
            }

            /* ── AUTH LINKS ── */
            .auth-row {
                display: flex;
                justify-content: center;
                align-items: center;
                gap: 4px;
                position: relative;
            }

            .auth-link {
                font-size: 11px;
                color: #bbb;
                text-decoration: none;
                padding: 3px 8px;
                border-radius: 4px;
                transition: color 0.15s;
            }
            .auth-link:hover { color: #666; }

            .auth-sep {
                font-size: 11px;
                color: #ddd;
            }

            /* ── RESPONSIVE ── */
            @media (max-width: 480px) {
                .hero { padding: 2.2rem 1.5rem 2rem; }
                .body-area { padding: 1.5rem 1.2rem 1.4rem; }
                .logos-left { gap: 12px; }
                .logo-sep { display: none; }
                .btn-conv, .btn-lom { padding: 12px 20px; font-size: 13px; }
            }
        </style>
    </head>

    <body>
        <div class="card">

            {{-- ── HERO VERDE ── --}}
            <div class="hero">
                <h1 class="hero-title">Convocatoria Docente</h1>
                <p class="hero-sub">Educación Superior &middot; La Rioja</p>

                <div class="cta-row">
                    <a href="{{ route('admin.llamados.publico') }}" class="btn-conv">
                        {{-- Ícono clipboard --}}
                        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/>
                            <rect x="9" y="3" width="6" height="4" rx="1"/>
                            <line x1="9" y1="12" x2="15" y2="12"/>
                            <line x1="9" y1="16" x2="13" y2="16"/>
                        </svg>
                        Ver convocatorias
                    </a>

                    <a href="{{ route('admin.lom.publico') }}" class="btn-lom">
                        {{-- Ícono lista numerada --}}
                        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <line x1="10" y1="6" x2="21" y2="6"/>
                            <line x1="10" y1="12" x2="21" y2="12"/>
                            <line x1="10" y1="18" x2="21" y2="18"/>
                            <path d="M4 6h1v4"/>
                            <path d="M4 10h2"/>
                            <path d="M6 18H4c0-1 2-2 2-3s-1-1.5-2-1"/>
                        </svg>
                        Ver LOM
                    </a>

                    @if($trayectoHabilitado)
                        <a href="{{ route('trayecto.registro') }}" class="btn-lom">
                            {{-- Ícono birrete/graduación --}}
                            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M22 10L12 5 2 10l10 5 10-5z"/>
                                <path d="M6 12v5c0 1.5 3 3 6 3s6-1.5 6-3v-5"/>
                            </svg>
                            Trayecto Formativo
                        </a>
                    @endif
                </div>
            </div>

            {{-- ── ZONA INFERIOR ── --}}
            <div class="body-area">

                {{-- Chips informativos --}}
                <div class="chips-row">
                    
                       
                    </div>
                </div>

                {{-- Logos institucionales --}}
                <div class="logos-bar">
                    <div class="logos-left">
                        <div class="logo-item">
                            <span class="logo-top">Ministerio de</span>
                            <span class="logo-bot">Educación</span>
                        </div>
                        <div class="logo-sep"></div>
                        <div class="logo-item">
                            <span class="logo-top">Secretaría de</span>
                            <span class="logo-bot">Gestión Educativa</span>
                        </div>
                        <div class="logo-sep"></div>
                        <div class="logo-item">
                            <span class="logo-top">Dirección General de</span>
                            <span class="logo-bot">Educación Superior</span>
                        </div>
                        <div class="logo-sep"></div>
                        <div class="logo-item">
                            <span class="logo-top">Comisión Provisoria</span>
                            <span class="logo-bot">Nivel Superior</span>
                        </div>
                    </div>

                    {{-- Escudo La Rioja Gobierno --}}
                    <div class="logo-rioja">
                        <img src="img/logo_gob_lr.png" width="42" height="42" alt="Logo Gobierno de La Rioja">
                            
                       
                    </div>
                </div>

                {{-- Links de autenticación, discretos --}}
                @if (Route::has('login'))
                    <div class="auth-row">
                        @auth
                            <a href="{{ route('dashboard') }}" class="auth-link">Panel de administración</a>
                        @else
                            <a href="{{ route('login') }}" class="auth-link">Iniciar sesión</a>
                            @if (Route::has('register'))
                                <span class="auth-sep">&middot;</span>
                                <a href="{{ route('register') }}" class="auth-link">Registrarse</a>
                            @endif
                        @endauth
                    </div>
                @endif

            </div>
        </div>
    </body>
</html>