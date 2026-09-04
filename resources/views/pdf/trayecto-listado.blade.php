<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
<title>Trayecto Formativo — Listado de Inscriptos {{ $cohorte }}</title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
        font-family: DejaVu Sans, sans-serif;
        font-size: 8pt;
        color: #1a1a2e;
        background: #fff;
        padding: 10mm 12mm;
    }

    .header {
        border-bottom: 3px solid #1e3a5f;
        padding-bottom: 8px;
        margin-bottom: 8px;
    }
    .header-table { width: 100%; border-collapse: collapse; }
    .header-table td { vertical-align: middle; }
    .org-name {
        font-size: 12pt;
        font-weight: bold;
        color: #1e3a5f;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .org-sub { font-size: 8pt; color: #555; margin-top: 2px; }
    .doc-title {
        font-size: 11pt;
        font-weight: bold;
        color: #1e3a5f;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        text-align: right;
    }
    .fecha-emision { font-size: 7.5pt; color: #777; margin-top: 4px; text-align: right; }

    .filtros {
        background: #f4f6fb;
        border: 1px solid #dde2ea;
        border-radius: 4px;
        padding: 5px 10px;
        margin-bottom: 8px;
        font-size: 7.5pt;
        color: #333;
    }
    .filtros b { color: #1e3a5f; }

    table.listado { width: 100%; border-collapse: collapse; }
    table.listado thead tr { background: #1e3a5f; color: #fff; }
    table.listado thead th {
        padding: 4px 5px;
        font-size: 7pt;
        font-weight: bold;
        text-transform: uppercase;
        text-align: left;
        border: 1px solid #16305a;
    }
    table.listado tbody tr:nth-child(odd)  { background: #f7f9fc; }
    table.listado tbody tr:nth-child(even) { background: #fff; }
    table.listado tbody td {
        padding: 3px 5px;
        font-size: 7.5pt;
        border: 1px solid #dde2ea;
        vertical-align: top;
    }

    .badge-si  { color: #15803d; font-weight: bold; }
    .badge-no  { color: #b91c1c; font-weight: bold; }

    #footer {
        position: fixed;
        bottom: 4mm;
        left: 0;
        right: 0;
        text-align: center;
        font-size: 6.5pt;
        color: #aaa;
    }
    #footer .pagenum:before { content: counter(page); }
</style>
</head>
<body>

<div class="header">
    <table class="header-table">
        <tr>
            <td>
                <div class="org-name">{{ $nombreTrayecto }}</div>
                <div class="org-sub">Trayecto Formativo — Listado de Inscriptos, Convocatoria {{ $cohorte }}</div>
            </td>
            <td style="text-align:right; width:220px">
                <div class="doc-title">Listado de Inscriptos</div>
                <div class="fecha-emision">Emitido: {{ $fechaHoy }}</div>
            </td>
        </tr>
    </table>
</div>

<div class="filtros">
    <b>Filtros aplicados:</b>
    Cohorte: <b>{{ $cohorte }}</b> ·
    Nivel: <b>{{ $filtroNivel !== '' ? $filtroNivel : 'Todos' }}</b> ·
    Estamento: <b>{{ $filtroEstamento !== '' ? $filtroEstamento : 'Todos' }}</b> ·
    Búsqueda: <b>{{ $busqueda !== '' ? $busqueda : '—' }}</b> ·
    Total: <b>{{ $inscripciones->count() }}</b> inscripto(s)
</div>

<table class="listado">
    <thead>
        <tr>
            <th style="width:8%">DNI</th>
            <th style="width:15%">Apellido y Nombre</th>
            <th style="width:9%">Nivel</th>
            <th style="width:16%">Estamento</th>
            <th style="width:15%">Institución</th>
            <th style="width:10%">Teléfono</th>
            <th style="width:12%">Email</th>
            <th style="width:5%">F2</th>
            <th style="width:5%">Cert.</th>
            <th style="width:5%">Concepto</th>
        </tr>
    </thead>
    <tbody>
        @forelse($inscripciones as $insc)
        <tr>
            <td>{{ $insc->dni }}</td>
            <td>{{ $insc->apellido }}, {{ $insc->nombre }}</td>
            <td>{{ $insc->nivel }}</td>
            <td>{{ $insc->estamento }}</td>
            <td>
                @if($insc->institucion_nombre)
                    {{ $insc->institucion_nombre }}
                @else
                    —
                @endif
            </td>
            <td>{{ $insc->telefono ?? '—' }}</td>
            <td>{{ $insc->email ?? '—' }}</td>
            <td>{!! !empty($insc->f2_path) ? '<span class="badge-si">✓</span>' : '<span class="badge-no">✗</span>' !!}</td>
            <td>{!! !empty($insc->certificacion_servicio_path) ? '<span class="badge-si">✓</span>' : '<span class="badge-no">✗</span>' !!}</td>
            <td>{!! !empty($insc->concepto_path) ? '<span class="badge-si">✓</span>' : '<span class="badge-no">✗</span>' !!}</td>
        </tr>
        @empty
        <tr>
            <td colspan="10" style="text-align:center; padding:14px; color:#999;">No hay inscripciones para los filtros seleccionados.</td>
        </tr>
        @endforelse
    </tbody>
</table>

<div id="footer">
    {{ $nombreTrayecto }} · Listado generado automáticamente · Página <span class="pagenum"></span>
</div>

</body>
</html>
