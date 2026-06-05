<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
<title>Llamado #{{ $llamado->id }}</title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
        font-family: DejaVu Sans, sans-serif;
        font-size: 8.5pt;
        color: #1a1a2e;
        background: #fff;
        padding: 10mm 12mm;
    }

    /* ── ENCABEZADO INSTITUCIONAL ─────────────────────────────────── */
    .header {
        border-bottom: 3px solid #1e3a5f;
        padding-bottom: 6px;
        margin-bottom: 8px;
    }
    .header-inner {
        display: flex;           /* dompdf no soporta flex; usamos tabla */
    }
    .header-table {
        width: 100%;
        border-collapse: collapse;
    }
    .header-table td { vertical-align: middle; }
    .header-logo-cell { width: 60px; }
    .header-title-cell { padding-left: 8px; }
    .header-right-cell { text-align: right; width: 200px; }

    .org-name {
        font-size: 11pt;
        font-weight: bold;
        color: #1e3a5f;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .org-sub {
        font-size: 7.5pt;
        color: #555;
        margin-top: 2px;
    }
    .doc-title {
        font-size: 13pt;
        font-weight: bold;
        color: #1e3a5f;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    .doc-subtitle {
        font-size: 8pt;
        color: #666;
        margin-top: 2px;
    }
    .badge-id {
        display: inline-block;
        background: #1e3a5f;
        color: #fff;
        font-size: 9pt;
        font-weight: bold;
        padding: 3px 10px;
        border-radius: 4px;
    }
    .fecha-emision {
        font-size: 7.5pt;
        color: #777;
        margin-top: 4px;
    }

    /* ── SECCIÓN DATOS DEL LLAMADO ────────────────────────────────── */
    .section-title {
        font-size: 8pt;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        color: #1e3a5f;
        background: #e8eef7;
        padding: 4px 8px;
        border-left: 4px solid #1e3a5f;
        margin: 8px 0 5px 0;
    }

    /* Grilla de metadatos del llamado */
    .meta-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 6px;
    }
    .meta-table td {
        padding: 3px 6px;
        font-size: 8pt;
        vertical-align: top;
        border: 1px solid #dde2ea;
    }
    .meta-table .label {
        font-weight: bold;
        color: #1e3a5f;
        background: #f4f6fb;
        width: 110px;
        white-space: nowrap;
    }
    .meta-table .value {
        color: #222;
    }

    /* ── TABLA DE DETALLES (espacios/cargos) ──────────────────────── */
    .data-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 8px;
    }
    .data-table thead tr {
        background: #1e3a5f;
        color: #fff;
    }
    .data-table thead th {
        padding: 5px 6px;
        font-size: 7.5pt;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        text-align: left;
        border: 1px solid #16305a;
    }
    .data-table tbody tr:nth-child(odd) {
        background: #f7f9fc;
    }
    .data-table tbody tr:nth-child(even) {
        background: #ffffff;
    }
    .data-table tbody td {
        padding: 4px 6px;
        font-size: 8pt;
        border: 1px solid #dde2ea;
        vertical-align: top;
    }
    .data-table tfoot td {
        background: #e8eef7;
        font-size: 7.5pt;
        color: #444;
        padding: 3px 6px;
        font-style: italic;
        border: 1px solid #dde2ea;
    }

    /* Badge tipo (Espacio / Cargo) */
    .badge-tipo {
        display: inline-block;
        font-size: 6.5pt;
        font-weight: bold;
        padding: 1px 5px;
        border-radius: 3px;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }
    .badge-espacio { background: #dbeafe; color: #1d4ed8; }
    .badge-cargo   { background: #ede9fe; color: #6d28d9; }

    /* ── TABLA DE INSCRIPTOS ──────────────────────────────────────── */
    .inscriptos-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 8px;
    }
    .inscriptos-table thead tr {
        background: #134e4a;
        color: #fff;
    }
    .inscriptos-table thead th {
        padding: 5px 6px;
        font-size: 7.5pt;
        font-weight: bold;
        text-transform: uppercase;
        text-align: left;
        border: 1px solid #0f3d3a;
    }
    .inscriptos-table tbody tr:nth-child(odd)  { background: #f0fdf4; }
    .inscriptos-table tbody tr:nth-child(even) { background: #ffffff; }
    .inscriptos-table tbody td {
        padding: 4px 6px;
        font-size: 8pt;
        border: 1px solid #d1e7dd;
        vertical-align: top;
    }
    .orden-num {
        font-weight: bold;
        color: #134e4a;
        text-align: center;
    }
    .puntaje-cell {
        font-weight: bold;
        color: #065f46;
        text-align: center;
    }

    /* ── TABLA SIN CLASIFICAR ─────────────────────────────────────── */
    .sc-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 8px;
    }
    .sc-table thead tr {
        background: #7f1d1d;
        color: #fff;
    }
    .sc-table thead th {
        padding: 5px 6px;
        font-size: 7.5pt;
        font-weight: bold;
        text-transform: uppercase;
        text-align: left;
        border: 1px solid #6b1111;
    }
    .sc-table tbody tr:nth-child(odd)  { background: #fef2f2; }
    .sc-table tbody tr:nth-child(even) { background: #ffffff; }
    .sc-table tbody td {
        padding: 4px 6px;
        font-size: 8pt;
        border: 1px solid #fecaca;
        vertical-align: top;
    }
    .sc-subtitle {
        font-size: 7.5pt;
        color: #991b1b;
        font-style: italic;
        margin-bottom: 4px;
    }

    /* ── PERFIL ───────────────────────────────────────────────────── */
    .perfil-cell {
        font-size: 7.5pt;
        line-height: 1.4;
        color: #334155;
        white-space: pre-line;
    }

    /* ── ESTADO BADGE ─────────────────────────────────────────────── */
    .estado-abierto { color: #15803d; font-weight: bold; }
    .estado-cerrado { color: #b91c1c; font-weight: bold; }

    /* ── PIE DE PÁGINA ────────────────────────────────────────────── */
    .footer {
        border-top: 2px solid #1e3a5f;
        margin-top: 12px;
        padding-top: 6px;
        text-align: center;
    }
    .footer-firma {
        display: inline-block;
        width: 200px;
        border-top: 1px solid #333;
        margin: 0 30px;
        padding-top: 3px;
        font-size: 7.5pt;
        font-weight: bold;
        text-align: center;
    }
    .footer-legal {
        font-size: 7pt;
        color: #888;
        margin-top: 6px;
        text-align: center;
    }
    .page-break { page-break-before: always; }

    /* ── AVISO vacío ──────────────────────────────────────────────── */
    .empty-notice {
        text-align: center;
        padding: 10px;
        font-style: italic;
        color: #888;
        font-size: 8pt;
        border: 1px dashed #ccc;
        border-radius: 4px;
        margin: 4px 0 8px 0;
    }
</style>
</head>
<body>

{{-- ═══════════════════════════════════════════════════════════════
     ENCABEZADO INSTITUCIONAL
═══════════════════════════════════════════════════════════════ --}}
<div class="header">
    <table class="header-table">
        <tr>
            <td class="header-title-cell">
                <div class="org-name">Sistema de Llamados Docentes — Nivel Superior</div>
                <div class="org-sub">La Rioja · Comisión Provisoria de Nivel Superior</div>
            </td>
            <td class="header-right-cell">
                <div class="doc-title">Llamado Docente</div>
                <div class="doc-subtitle">
                    @if($llamado->tipo_nombre)
                        {{ strtoupper($llamado->tipo_nombre) }}
                    @endif
                </div>
                <div style="margin-top:4px">
                    <span class="badge-id">#{{ $llamado->id }}</span>
                </div>
                <div class="fecha-emision">Emitido: {{ $llamado->fecha_hoy }}</div>
            </td>
        </tr>
    </table>
</div>

{{-- ═══════════════════════════════════════════════════════════════
     DATOS DEL LLAMADO
═══════════════════════════════════════════════════════════════ --}}
<div class="section-title">Datos del Llamado</div>

<table class="meta-table">
    <tr>
        <td class="label">Zona</td>
        <td class="value">{{ $llamado->nombre_zona ?? '—' }}</td>
        <td class="label">Tipo de Llamado</td>
        <td class="value">{{ $llamado->tipo_nombre ?? '—' }}</td>
        <td class="label">Estado</td>
        <td class="value">
            @if($llamado->idtb_tipoestado == 8)
                <span class="estado-abierto">● ABIERTO</span>
            @else
                <span class="estado-cerrado">● CERRADO</span>
            @endif
        </td>
    </tr>
    <tr>
        <td class="label">Fecha de inicio</td>
        <td class="value">{{ $llamado->fecha_ini_fmt }}</td>
        <td class="label">Fecha de cierre</td>
        <td class="value">{{ $llamado->fecha_fin_fmt }}</td>
        <td class="label">Publicado</td>
        <td class="value">{{ $llamado->publicado ? 'Sí' : 'No' }}</td>
    </tr>
    @if($llamado->descripcion)
    <tr>
        <td class="label">Descripción</td>
        <td class="value" colspan="5">{{ $llamado->descripcion }}</td>
    </tr>
    @endif
</table>

{{-- ═══════════════════════════════════════════════════════════════
     ESPACIOS Y CARGOS CONVOCADOS
═══════════════════════════════════════════════════════════════ --}}
<div class="section-title">Espacios / Cargos Convocados</div>

@if($detalles->isNotEmpty())
<table class="data-table">
    <thead>
        <tr>
            <th style="width:8%">Tipo</th>
            <th style="width:14%">Instituto</th>
            <th style="width:13%">Carrera</th>
            <th style="width:16%">Espacio / Cargo</th>
            <th style="width:5%">Hs</th>
            <th style="width:5%">Año</th>
            <th style="width:8%">Período</th>
            <th style="width:8%">Turno</th>
            <th style="width:10%">Sit. Revista</th>
            <th style="width:13%">Horario</th>
        </tr>
    </thead>
    <tbody>
        @foreach($detalles as $det)
        <tr>
            <td>
                <span class="badge-tipo {{ $det->tipo === 'Espacio' ? 'badge-espacio' : 'badge-cargo' }}">
                    {{ $det->tipo }}
                </span>
            </td>
            <td>{{ $det->instituto ?? '—' }}</td>
            <td>{{ $det->carrera ?? '—' }}</td>
            <td><strong>{{ $det->nombre ?? '—' }}</strong></td>
            <td style="text-align:center">{{ $det->hora_catedra ?? '—' }}</td>
            <td style="text-align:center">{{ $det->anio ? $det->anio.'°' : '—' }}</td>
            <td>{{ $det->periodo ?? '—' }}</td>
            <td>{{ $det->turno ?? '—' }}</td>
            <td>{{ $det->situacion_revista ?? '—' }}</td>
            <td>{{ $det->horario ?? '—' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

{{-- Perfil(es) — se muestra cuando hay perfil definido --}}
@php
    $perfiles = $detalles->where('perfil', '!=', null)->pluck('perfil', 'nombre')->unique();
@endphp
@if($perfiles->isNotEmpty())
    <div class="section-title" style="margin-top:6px">Perfil Requerido</div>
    @foreach($perfiles as $espNombre => $perfilTexto)
    <table class="meta-table" style="margin-bottom:4px">
        <tr>
            <td class="label" style="width:150px">{{ $espNombre }}</td>
            <td class="value">
                <span class="perfil-cell">{{ $perfilTexto }}</span>
            </td>
        </tr>
    </table>
    @endforeach
@endif

@else
    <div class="empty-notice">No se registraron espacios ni cargos para este llamado.</div>
@endif

{{-- ═══════════════════════════════════════════════════════════════
     LISTADO DE INSCRIPTOS HABILITADOS
═══════════════════════════════════════════════════════════════ --}}
<div class="section-title" style="margin-top:10px">
    Listado de Orden de Mérito — Habilitados
</div>

@if($habilitados->isNotEmpty())
<table class="inscriptos-table">
    <thead>
        <tr>
            <th style="width:5%;  text-align:center">Ord.</th>
            <th style="width:20%">Apellido y Nombre</th>
            <th style="width:10%">D.N.I. N°</th>
            <th style="width:12%">Teléfono</th>
            <th style="width:20%">Correo Electrónico</th>
            <th style="width:20%">Domicilio</th>
            <th style="width:13%; text-align:center">Clasificación</th>
        </tr>
    </thead>
    <tbody>
        @foreach($habilitados as $ins)
        <tr>
            <td class="orden-num">{{ $ins->orden_display }}</td>
            <td><strong>{{ strtoupper($ins->apellido) }}</strong> {{ $ins->nombre }}</td>
            <td>{{ $ins->dni }}</td>
            <td>{{ $ins->telefono ?? '—' }}</td>
            <td>{{ $ins->email ?? '—' }}</td>
            <td>{{ $ins->domicilio ?? '—' }}</td>
            <td class="puntaje-cell">
                @if($ins->puntaje !== null)
                    PUNTAJE: {{ number_format($ins->puntaje, 2) }}
                @else
                    —
                @endif
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
@else
    <div class="empty-notice">No hay inscriptos habilitados registrados para este llamado.</div>
@endif

{{-- ═══════════════════════════════════════════════════════════════
     SIN CLASIFICAR
═══════════════════════════════════════════════════════════════ --}}
@if($sinClasificar->isNotEmpty())

<div class="section-title" style="margin-top:10px; border-left-color:#991b1b; background:#fef2f2; color:#991b1b">
    Sin Clasificar (No válido para designar)
</div>
<div class="sc-subtitle">Docentes que no reúnen las condiciones requeridas.</div>

<table class="sc-table">
    <thead>
        <tr>
            <th style="width:22%">Apellido y Nombre</th>
            <th style="width:12%">D.N.I. N°</th>
            <th style="width:20%">Unidad Curricular</th>
            <th style="width:20%">Carrera</th>
            <th style="width:26%">Motivo</th>
        </tr>
    </thead>
    <tbody>
        @foreach($sinClasificar as $ins)
        <tr>
            <td><strong>{{ strtoupper($ins->apellido) }}</strong> {{ $ins->nombre }}</td>
            <td>{{ $ins->dni }}</td>
            {{-- La unidad curricular y la carrera se deducen del primer detalle del llamado --}}
            <td>{{ $detalles->first()->nombre ?? '—' }}</td>
            <td>{{ $detalles->first()->carrera ?? '—' }}</td>
            <td>{{ $ins->observaciones ?? 'No reúne perfil requerido' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

@endif

{{-- ═══════════════════════════════════════════════════════════════
     PIE DE PÁGINA / FIRMAS
═══════════════════════════════════════════════════════════════ --}}
<div class="footer">
    <div style="text-align: right; font-size:8pt; margin-bottom: 24px; color:#555">
        La Rioja, {{ $llamado->fecha_hoy }}
    </div>

    <div>
        <span class="footer-firma">Vocal 1</span>
        <span class="footer-firma">Vocal 2</span>
        <span class="footer-firma">Presidente — Comisión Provisoria</span>
    </div>

    <div class="footer-legal">
        Comisión Provisoria de Nivel Superior · Sistema de Llamados Docentes —
        Documento generado automáticamente · Llamado #{{ $llamado->id }}
    </div>
</div>

</body>
</html>
