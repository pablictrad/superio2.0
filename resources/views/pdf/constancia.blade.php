<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
<title>Constancia de Inscripción #{{ $inscripcion->codigo_constancia }}</title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
        font-family: DejaVu Sans, sans-serif;
        font-size: 9pt;
        color: #1a1a2e;
        background: #fff;
        padding: 14mm 16mm;
    }

    /* ── ENCABEZADO ─────────────────────────────────────────────── */
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
    .badge-codigo {
        display: inline-block;
        background: #1e3a5f;
        color: #fff;
        font-size: 9pt;
        font-weight: bold;
        padding: 3px 12px;
        border-radius: 4px;
        float: right;
        margin-top: 4px;
    }
    .fecha-emision {
        font-size: 7.5pt;
        color: #777;
        margin-top: 4px;
        text-align: right;
    }

    /* ── ALERTA CENTRAL ─────────────────────────────────────────── */
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

    /* ── SECCIÓN ────────────────────────────────────────────────── */
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

    /* ── META TABLE ─────────────────────────────────────────────── */
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

    /* ── TABLA DOCUMENTOS ───────────────────────────────────────── */
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

    /* ── BADGE ──────────────────────────────────────────────────── */
    .badge-si  { color: #15803d; font-weight: bold; }
    .badge-no  { color: #b91c1c; font-weight: bold; }

    /* ── PIE ────────────────────────────────────────────────────── */
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

    /* ── VERIFICACIÓN QR PLACEHOLDER ───────────────────────────── */
    .verify-box {
        border: 1px dashed #bbb;
        border-radius: 4px;
        padding: 6px 10px;
        margin: 10px 0;
        font-size: 7.5pt;
        color: #555;
        text-align: center;
    }
    .verify-code {
        font-size: 10pt;
        font-weight: bold;
        color: #1e3a5f;
        letter-spacing: 2px;
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
                <div class="org-name">Sistema de Convocatorias Docentes — Nivel Superior</div>
                <div class="org-sub">La Rioja · Comisión Provisoria de Nivel Superior</div>
            </td>
            <td style="text-align:right; width:280px">
                <div class="doc-title">Constancia de Inscripción</div>
                <span class="badge-codigo">{{ $inscripcion->codigo_constancia }}</span>
                <div class="fecha-emision" style="clear:both; margin-top:6px">
                    Emitida: {{ $fechaHoy }}
                </div>
            </td>
        </tr>
    </table>
</div>

{{-- ═══════════════════════════════════════════════════════
     ALERTA DE ÉXITO
═══════════════════════════════════════════════════════ --}}
<div class="alert-box">
    <div class="check">✓</div>
    <div class="title">Inscripción registrada exitosamente</div>
    <div class="sub">
        Este documento acredita que el/la docente se ha inscripto a la convocatoria indicada.<br>
        Conserve esta constancia como comprobante.
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════
     DATOS DE LA CONVOCATORIA
═══════════════════════════════════════════════════════ --}}
<div class="section-title">Datos de la Convocatoria</div>
<table class="meta-table">
    <tr>
        <td class="label">Convocatoria N°</td>
        <td class="value">#{{ $llamado->id }}</td>
        <td class="label">Tipo</td>
        <td class="value">{{ $llamado->tipo_nombre ?? '—' }}</td>
    </tr>
    <tr>
        <td class="label">Zona</td>
        <td class="value">{{ $llamado->nombre_zona ?? '—' }}</td>
        <td class="label">Cierre de inscripciones</td>
        <td class="value">{{ \Carbon\Carbon::parse($llamado->fecha_fin)->format('d/m/Y H:i') }}</td>
    </tr>
    @if(!empty($espacios))
    @foreach($espacios as $esp)
    <tr>
        <td class="label">Espacio / Cargo</td>
        <td class="value" colspan="3">
            <strong>{{ $esp->nombre }}</strong>
            — {{ $esp->carrera ?? '' }}
            — {{ $esp->instituto ?? '' }}
        </td>
    </tr>
    @endforeach
    @endif
</table>

{{-- ═══════════════════════════════════════════════════════
     DATOS DEL DOCENTE
═══════════════════════════════════════════════════════ --}}
<div class="section-title">Datos del Docente</div>
<table class="meta-table">
    <tr>
        <td class="label">Apellido y Nombre</td>
        <td class="value" colspan="3">
            <strong>{{ strtoupper($inscripcion->apellido) }}, {{ $inscripcion->nombre }}</strong>
        </td>
    </tr>
    <tr>
        <td class="label">D.N.I.</td>
        <td class="value">{{ $inscripcion->dni }}</td>
        <td class="label">Teléfono</td>
        <td class="value">{{ $inscripcion->telefono ?? '—' }}</td>
    </tr>
    <tr>
        <td class="label">Correo Electrónico</td>
        <td class="value">{{ $inscripcion->email ?? '—' }}</td>
        <td class="label">Localidad</td>
        <td class="value">
            {{ $inscripcion->localidad ?? '—' }}
            @php $zonaTexto = ($domicilio->zona_override ?? null) ?: ($domicilio->zona_departamento ?? null); @endphp
            @if($zonaTexto) (Zona {{ $zonaTexto }}) @endif
        </td>
    </tr>
    <tr>
        <td class="label">Domicilio</td>
        <td class="value" colspan="3">{{ $inscripcion->domicilio ?? '—' }}</td>
    </tr>
    <tr>
        <td class="label">Posee Legajo</td>
        <td class="value">
            @if($inscripcion->tiene_legajo)
                <span class="badge-si">✓ SÍ</span>
            @else
                <span class="badge-no">✗ NO</span>
            @endif
        </td>
        <td class="label">Presentó F2</td>
        <td class="value">
            @if($inscripcion->presento_f2)
                <span class="badge-si">✓ SÍ</span>
            @else
                <span class="badge-no">✗ NO</span>
            @endif
        </td>
    </tr>
    <tr>
        <td class="label">DNI Presentado</td>
        <td class="value">
            @if(!empty($domicilio->archivo_dni ?? null))
                <span class="badge-si">✓ SÍ</span>
            @else
                <span class="badge-no">✗ NO</span>
            @endif
        </td>
        <td class="label">Comprobante Domicilio</td>
        <td class="value">
            @if(!empty($domicilio->archivo_factura ?? null) || !empty($domicilio->archivo_certifdomicilio ?? null))
                <span class="badge-si">✓ SÍ</span>
            @else
                <span class="badge-no">✗ NO</span>
            @endif
        </td>
    </tr>
</table>

{{-- ═══════════════════════════════════════════════════════
     TÍTULOS DECLARADOS
═══════════════════════════════════════════════════════ --}}
@if($titulos->isNotEmpty())
<div class="section-title">Títulos Declarados</div>
<table class="docs-table">
    <thead>
        <tr>
            <th style="width:55%">Título</th>
            <th style="width:30%">Institución</th>
            <th style="width:15%; text-align:center">Año egreso</th>
             <th style="width:15%; text-align:center">N° Registro</th>
        </tr>
    </thead>
    <tbody>
        @foreach($titulos as $tit)
        <tr>
            <td>{{ $tit->nombre_titulo }}</td>
            <td>{{ $tit->institucion ?? '—' }}</td>
            <td style="text-align:center">{{ $tit->anio_egreso ?? '—' }}</td>
            <td style="text-align:center">{{ $tit->num_registro ?? '—' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

{{-- ═══════════════════════════════════════════════════════
     CERTIFICADOS DECLARADOS
═══════════════════════════════════════════════════════ --}}
@if($certificados->isNotEmpty())
<div class="section-title">Certificados Declarados</div>
<table class="docs-table">
    <thead>
        <tr>
            <th style="width:50%">Certificado</th>
            <th style="width:30%">Tipo</th>
            
        </tr>
    </thead>
    <tbody>
        @foreach($certificados as $cert)
        <tr>
            <td>{{ $cert->nombre_certificado }}</td>
            <td>{{ $cert->tipo ?? '—' }}</td>
           
        </tr>
        @endforeach
    </tbody>
</table>
@endif

{{-- ═══════════════════════════════════════════════════════
     CÓDIGO DE VERIFICACIÓN
═══════════════════════════════════════════════════════ --}}
<div class="verify-box">
    Código de verificación de constancia:
    <div class="verify-code">{{ $inscripcion->codigo_constancia }}</div>
    <div style="margin-top:3px; font-size:7pt; color:#888">
        Conserve este código. Puede ser requerido por la institución para verificar su inscripción.
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════
     PIE
═══════════════════════════════════════════════════════ --}}
<div class="footer">
    <div class="footer-aviso">
        Esta constancia es válida como comprobante de inscripción a la convocatoria indicada.<br>
        La habilitación definitiva queda sujeta a la evaluación de antecedentes por la Comisión Provisoria.
    </div>
    <div style="text-align: right; font-size:8pt; color:#555; margin-bottom:20px">
        La Rioja, {{ $fechaHoy }}
    </div>
    <div style="text-align:center">
        <span class="footer-firma">Firma del Docente</span>
        <span class="footer-firma">Sello y Firma Institucional</span>
    </div>
    <div class="footer-legal">
        Comisión Provisoria de Nivel Superior · Sistema de Convocatorias Docentes ·
        Constancia generada automáticamente · Código {{ $inscripcion->codigo_constancia }}
    </div>
</div>

</body>
</html>