<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
<title>Constancia de Inscripción — Trayecto Formativo {{ $cohorte }}</title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
        font-family: DejaVu Sans, sans-serif;
        font-size: 9pt;
        color: #1a1a2e;
        background: #fff;
        padding: 14mm 16mm;
    }

    .header {
        border-bottom: 3px solid #1e3a5f;
        padding-bottom: 8px;
        margin-bottom: 10px;
    }
    .header-table { width: 100%; border-collapse: collapse; }
    .header-table td { vertical-align: middle; }
    .org-name {
        font-size: 11pt;
        font-weight: bold;
        color: #1e3a5f;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .org-sub { font-size: 7.5pt; color: #555; margin-top: 2px; }
    .doc-title {
        font-size: 11pt;
        font-weight: bold;
        color: #1e3a5f;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        text-align: right;
        white-space: nowrap;
    }
    .fecha-emision {
        font-size: 7.5pt;
        color: #777;
        margin-top: 4px;
        text-align: right;
    }

    .alert-box {
        background: #f0fdf4;
        border: 2px solid #16a34a;
        border-radius: 6px;
        padding: 10px 14px;
        margin: 10px 0;
        text-align: center;
    }
    .alert-box .check { font-size: 22pt; color: #16a34a; }
    .alert-box .title {
        font-size: 11pt;
        font-weight: bold;
        color: #15803d;
        text-transform: uppercase;
        margin-top: 2px;
    }
    .alert-box .sub { font-size: 8pt; color: #555; margin-top: 2px; }

    .section-title {
        font-size: 8pt;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        color: #1e3a5f;
        background: #e8eef7;
        padding: 4px 8px;
        border-left: 4px solid #1e3a5f;
        margin: 10px 0 5px 0;
    }

    .meta-table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
    .meta-table td { padding: 3px 6px; font-size: 8.5pt; vertical-align: top; border: 1px solid #dde2ea; }
    .meta-table .label {
        font-weight: bold;
        color: #1e3a5f;
        background: #f4f6fb;
        width: 130px;
        white-space: nowrap;
    }
    .meta-table .value { color: #222; }

    .docs-table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
    .docs-table thead tr { background: #1e3a5f; color: #fff; }
    .docs-table thead th {
        padding: 4px 6px;
        font-size: 7.5pt;
        font-weight: bold;
        text-transform: uppercase;
        text-align: left;
        border: 1px solid #16305a;
    }
    .docs-table tbody tr:nth-child(odd) { background: #f7f9fc; }
    .docs-table tbody tr:nth-child(even) { background: #fff; }
    .docs-table tbody td {
        padding: 3px 6px;
        font-size: 8pt;
        border: 1px solid #dde2ea;
        vertical-align: top;
    }

    .badge-si  { color: #15803d; font-weight: bold; }
    .badge-no  { color: #b91c1c; font-weight: bold; }

    .footer {
        border-top: 2px solid #1e3a5f;
        margin-top: 18px;
        padding-top: 8px;
    }
    .footer-aviso {
        font-size: 7.5pt;
        color: #555;
        text-align: center;
        font-style: italic;
        margin-bottom: 16px;
    }
    .footer-firma {
        display: inline-block;
        width: 180px;
        border-top: 1px solid #333;
        margin: 0 20px;
        padding-top: 3px;
        font-size: 7.5pt;
        font-weight: bold;
        text-align: center;
    }
    .footer-legal {
        font-size: 6.5pt;
        color: #aaa;
        margin-top: 8px;
        text-align: center;
    }
</style>
</head>
<body>

{{-- ═══════════════════════════════════════════════════════
     ENCABEZADO
═══════════════════════════════════════════════════════ --}}
<div class="header">
    <table class="header-table">
        <tr>
            <td>
                <div class="org-name">{{ $nombreTrayecto }}</div>
                <div class="org-sub">Trayecto Formativo para Aspirantes a Cargos Directivos y Supervisivos — Convocatoria {{ $cohorte }}</div>
            </td>
            <td style="text-align:right; width:220px">
                <div class="doc-title">Constancia de Inscripción</div>
                <div class="fecha-emision">Emitida: {{ $fechaHoy }}</div>
            </td>
        </tr>
    </table>
</div>

{{-- ═══════════════════════════════════════════════════════
     ALERTA DE ÉXITO
═══════════════════════════════════════════════════════ --}}
<div class="alert-box">
    <div class="check">✓</div>
    <div class="title">Inscripción registrada</div>
    <div class="sub">
        Este documento acredita la inscripción al Trayecto Formativo, Convocatoria {{ $cohorte }}.<br>
        Conserve esta constancia como comprobante.
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════
     DATOS PERSONALES Y DE CONTACTO
═══════════════════════════════════════════════════════ --}}
<div class="section-title">Datos Personales y de Contacto</div>
<table class="meta-table">
    <tr>
        <td class="label">Apellido y Nombre</td>
        <td class="value" colspan="3">
            <strong>{{ strtoupper($datos->apellido) }}, {{ $datos->nombre }}</strong>
        </td>
    </tr>
    <tr>
        <td class="label">D.N.I.</td>
        <td class="value">{{ $datos->dni }}</td>
        <td class="label">Fecha de nacimiento</td>
        <td class="value">{{ $datos->fecha_nac ? \Carbon\Carbon::parse($datos->fecha_nac)->format('d/m/Y') : '—' }}</td>
    </tr>
    <tr>
        <td class="label">Teléfono</td>
        <td class="value">{{ $datos->telefono ?? '—' }}</td>
        <td class="label">Correo Electrónico</td>
        <td class="value">{{ $datos->email ?? '—' }}</td>
    </tr>
    <tr>
        <td class="label">Domicilio</td>
        <td class="value">{{ $datos->domicilio ?? '—' }}</td>
        <td class="label">Barrio</td>
        <td class="value">{{ $datos->barrio ?? '—' }}</td>
    </tr>
</table>

{{-- ═══════════════════════════════════════════════════════
     INSCRIPCIÓN(ES)
═══════════════════════════════════════════════════════ --}}
<div class="section-title">Inscripción al Trayecto Formativo</div>
<table class="docs-table">
    <thead>
        <tr>
            <th style="width:25%">Nivel</th>
            <th style="width:45%">Estamento</th>
            <th style="width:30%">Institución</th>
        </tr>
    </thead>
    <tbody>
        @foreach($inscripciones as $insc)
        <tr>
            <td>{{ $insc->nivel }}</td>
            <td>{{ $insc->estamento }}</td>
            <td>{{ $insc->institucion_nombre ?? '—' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

{{-- ═══════════════════════════════════════════════════════
     DOCUMENTACIÓN
═══════════════════════════════════════════════════════ --}}
<div class="section-title">Documentación</div>
<table class="docs-table">
    <thead>
        <tr>
            <th style="width:75%">Documento</th>
            <th style="width:25%">Estado</th>
        </tr>
    </thead>
    <tbody>
        @foreach($documentos as $doc)
        <tr>
            <td>{{ $doc['label'] }}</td>
            <td>
                @if($doc['entregado'])
                    <span class="badge-si">✓ Entregada</span>
                @else
                    <span class="badge-no">✗ Pendiente</span>
                @endif
            </td>
        </tr>
        @endforeach
    </tbody>
</table>

{{-- ═══════════════════════════════════════════════════════
     PIE
═══════════════════════════════════════════════════════ --}}
<div class="footer">
    <div class="footer-aviso">
        Esta constancia es válida como comprobante de inscripción al Trayecto Formativo indicado.<br>
        La habilitación definitiva queda sujeta a la evaluación de la documentación presentada.
    </div>
    <div style="text-align: right; font-size:8pt; color:#555; margin-bottom:20px">
        La Rioja, {{ $fechaHoy }}
    </div>
    <div style="text-align:center">
        <span class="footer-firma">Firma del Docente</span>
        <span class="footer-firma">Sello y Firma Institucional</span>
    </div>
    <div class="footer-legal">
        {{ $nombreTrayecto }} · Constancia generada automáticamente
    </div>
</div>

</body>
</html>
