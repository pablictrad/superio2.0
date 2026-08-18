<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\DB;

new #[Title('Dashboard')] class extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()?->esSuperAdmin(), 403, 'No tenés permiso para ver esta sección.');
    }

    #[Computed]
    public function statsLlamados()
    {
        $total    = DB::table('nuevo_llamado')->count();
        $abiertos = DB::table('nuevo_llamado')->where('idtb_tipoestado', 8)->count();
        $cerrados = DB::table('nuevo_llamado')->where('idtb_tipoestado', 9)->count();

        // Abiertos que ya vencieron por fecha pero cerrarVencidos() todavía no corrió
        $vencidosSinCerrar = DB::table('nuevo_llamado')
            ->where('idtb_tipoestado', 8)
            ->whereNotNull('fecha_fin')
            ->where('fecha_fin', '<', now())
            ->count();

        // Abiertos que cierran en las próximas 48hs
        $proximosACerrar = DB::table('nuevo_llamado')
            ->where('idtb_tipoestado', 8)
            ->whereNotNull('fecha_fin')
            ->whereBetween('fecha_fin', [now(), now()->addHours(48)])
            ->count();

        $porTipo = DB::table('nuevo_llamado')
            ->leftJoin('tipo_llamado', 'nuevo_llamado.idtipo_llamado', '=', 'tipo_llamado.id')
            ->select('tipo_llamado.nombre as tipo', DB::raw('count(*) as total'))
            ->groupBy('tipo_llamado.nombre')
            ->orderByDesc('total')
            ->get();

        $porZona = DB::table('nuevo_llamado')
            ->leftJoin('tb_zona', 'nuevo_llamado.idtb_zona', '=', 'tb_zona.id')
            ->select('tb_zona.nombre_zona as zona', DB::raw('count(*) as total'))
            ->groupBy('tb_zona.nombre_zona')
            ->orderByDesc('total')
            ->get();

        return compact('total', 'abiertos', 'cerrados', 'vencidosSinCerrar', 'proximosACerrar', 'porTipo', 'porZona');
    }

    #[Computed]
    public function statsInscriptos()
    {
        $total = DB::table('inscripciones_llamado')->count();

        $porEstado = DB::table('inscripciones_llamado')
            ->select('estado', DB::raw('count(*) as total'))
            ->groupBy('estado')
            ->get();

        $f2Pendientes = DB::table('inscripciones_llamado')
            ->where('presento_f2', true)
            ->where(function ($q) {
                $q->whereNull('f2_estado_id')->orWhere('f2_estado_id', 1);
            })
            ->count();

        // AJUSTAR: confirmar que tb_docente_titulos/tb_docente_certificados/tb_domicilio
        // usan estado_id = 1 / tipoestado_id = 1 para "Pendiente" (mismo catálogo que gestionar-docentes).
        $titulosPendientes = DB::table('tb_docente_titulos')
            ->where(function ($q) { $q->whereNull('estado_id')->orWhere('estado_id', 1); })
            ->count();

        $certificadosPendientes = DB::table('tb_docente_certificados')
            ->where(function ($q) { $q->whereNull('estado_id')->orWhere('estado_id', 1); })
            ->count();

        $domiciliosPendientes = DB::table('tb_domicilio')
            ->where(function ($q) { $q->whereNull('tipoestado_id')->orWhere('tipoestado_id', 1); })
            ->count();

        return compact('total', 'porEstado', 'f2Pendientes', 'titulosPendientes', 'certificadosPendientes', 'domiciliosPendientes');
    }

    #[Computed]
    public function statsLom()
    {
        // AJUSTAR: confirmar nombre de columna PK en tb_lom (se asume 'idtb_lom').
        $generados = DB::table('tb_lom')->distinct('llamado_id')->count('llamado_id');

        $publicados = DB::table('tb_lom')
            ->where('idtb_tipoestado', 8) // 8 = Publicado (mismo criterio usado en gestionar-inscriptos)
            ->distinct('llamado_id')
            ->count('llamado_id');

        $cerradosSinLom = DB::table('nuevo_llamado')
            ->where('idtb_tipoestado', 9)
            ->whereNotIn('id', function ($q) {
                $q->select('llamado_id')->from('tb_lom');
            })
            ->count();

        return compact('generados', 'publicados', 'cerradosSinLom');
    }
}; ?>

<div class="p-6 max-w-7xl mx-auto" x-data>
    <div class="mb-6">
        <h1 class="text-2xl font-black text-gray-800">Dashboard</h1>
        <p class="text-sm text-gray-500 mt-1">Panorama general de llamados e inscriptos.</p>
    </div>

    {{-- ══════════════════ ALERTAS ══════════════════ --}}
    @if($this->statsLlamados['vencidosSinCerrar'] > 0 || $this->statsLom['cerradosSinLom'] > 0)
    <div class="mb-6 space-y-2">
        @if($this->statsLlamados['vencidosSinCerrar'] > 0)
        <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800 flex items-center gap-2">
            <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.72-1.36 3.486 0l6.517 11.59c.75 1.334-.213 2.98-1.743 2.98H3.483c-1.53 0-2.493-1.646-1.743-2.98l6.517-11.59zM11 14a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V7a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
            <strong>{{ $this->statsLlamados['vencidosSinCerrar'] }}</strong> llamado(s) ya vencieron por fecha pero siguen marcados como abiertos.
        </div>
        @endif
        @if($this->statsLom['cerradosSinLom'] > 0)
        <div class="p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-800 flex items-center gap-2">
            <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.72-1.36 3.486 0l6.517 11.59c.75 1.334-.213 2.98-1.743 2.98H3.483c-1.53 0-2.493-1.646-1.743-2.98l6.517-11.59zM11 14a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V7a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
            <strong>{{ $this->statsLom['cerradosSinLom'] }}</strong> llamado(s) cerrados todavía no tienen LOM generado.
        </div>
        @endif
    </div>
    @endif

    {{-- ══════════════════ TARJETAS: LLAMADOS ══════════════════ --}}
    <h2 class="text-xs font-black text-gray-400 uppercase tracking-wide mb-2">Llamados</h2>
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
        <div class="bg-white border border-gray-200 rounded-xl p-4">
            <p class="text-[10px] font-black text-gray-400 uppercase">Total</p>
            <p class="text-2xl font-black text-gray-800">{{ $this->statsLlamados['total'] }}</p>
        </div>
        <div class="bg-white border border-gray-200 rounded-xl p-4">
            <p class="text-[10px] font-black text-gray-400 uppercase">Abiertos</p>
            <p class="text-2xl font-black text-green-600">{{ $this->statsLlamados['abiertos'] }}</p>
        </div>
        <div class="bg-white border border-gray-200 rounded-xl p-4">
            <p class="text-[10px] font-black text-gray-400 uppercase">Cerrados</p>
            <p class="text-2xl font-black text-gray-500">{{ $this->statsLlamados['cerrados'] }}</p>
        </div>
        <div class="bg-white border border-gray-200 rounded-xl p-4">
            <p class="text-[10px] font-black text-gray-400 uppercase">Cierran en 48hs</p>
            <p class="text-2xl font-black text-amber-600">{{ $this->statsLlamados['proximosACerrar'] }}</p>
        </div>
        <div class="bg-white border border-gray-200 rounded-xl p-4">
            <p class="text-[10px] font-black text-gray-400 uppercase">Vencidos sin cerrar</p>
            <p class="text-2xl font-black text-red-600">{{ $this->statsLlamados['vencidosSinCerrar'] }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <div class="bg-white border border-gray-200 rounded-xl p-4">
            <p class="text-xs font-black text-gray-500 uppercase mb-3">Llamados por tipo</p>
            <canvas id="chartTipo" height="180"></canvas>
        </div>
        <div class="bg-white border border-gray-200 rounded-xl p-4">
            <p class="text-xs font-black text-gray-500 uppercase mb-3">Llamados por zona</p>
            <canvas id="chartZona" height="180"></canvas>
        </div>
    </div>

    {{-- ══════════════════ TARJETAS: INSCRIPTOS ══════════════════ --}}
    <h2 class="text-xs font-black text-gray-400 uppercase tracking-wide mb-2">Inscriptos</h2>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
        <div class="bg-white border border-gray-200 rounded-xl p-4">
            <p class="text-[10px] font-black text-gray-400 uppercase">Total inscripciones</p>
            <p class="text-2xl font-black text-gray-800">{{ $this->statsInscriptos['total'] }}</p>
        </div>
        <div class="bg-white border border-gray-200 rounded-xl p-4">
            <p class="text-[10px] font-black text-gray-400 uppercase">F2 pendientes</p>
            <p class="text-2xl font-black text-amber-600">{{ $this->statsInscriptos['f2Pendientes'] }}</p>
        </div>
        <div class="bg-white border border-gray-200 rounded-xl p-4">
            <p class="text-[10px] font-black text-gray-400 uppercase">Títulos pendientes</p>
            <p class="text-2xl font-black text-amber-600">{{ $this->statsInscriptos['titulosPendientes'] }}</p>
        </div>
        <div class="bg-white border border-gray-200 rounded-xl p-4">
            <p class="text-[10px] font-black text-gray-400 uppercase">Certificados pendientes</p>
            <p class="text-2xl font-black text-amber-600">{{ $this->statsInscriptos['certificadosPendientes'] }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <div class="bg-white border border-gray-200 rounded-xl p-4">
            <p class="text-xs font-black text-gray-500 uppercase mb-3">Inscriptos por estado</p>
            <canvas id="chartEstado" height="180"></canvas>
        </div>
        <div class="bg-white border border-gray-200 rounded-xl p-4 flex flex-col justify-center">
            <p class="text-xs font-black text-gray-500 uppercase mb-3">LOM</p>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <p class="text-[10px] font-black text-gray-400 uppercase">Generados</p>
                    <p class="text-xl font-black text-gray-800">{{ $this->statsLom['generados'] }}</p>
                </div>
                <div>
                    <p class="text-[10px] font-black text-gray-400 uppercase">Publicados</p>
                    <p class="text-xl font-black text-green-600">{{ $this->statsLom['publicados'] }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════════════ CHART.JS ══════════════════ --}}
    @once
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
    @endonce

    <script>
        document.addEventListener('livewire:navigated', initDashboardCharts);
        document.addEventListener('DOMContentLoaded', initDashboardCharts);

        function initDashboardCharts() {
            const porTipo  = @json($this->statsLlamados['porTipo']);
            const porZona  = @json($this->statsLlamados['porZona']);
            const porEstado = @json($this->statsInscriptos['porEstado']);

            const colores = ['#6366f1', '#14b8a6', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#84cc16'];

            const elTipo = document.getElementById('chartTipo');
            if (elTipo && !elTipo.dataset.rendered) {
                elTipo.dataset.rendered = '1';
                new Chart(elTipo, {
                    type: 'bar',
                    data: {
                        labels: porTipo.map(r => r.tipo ?? 'Sin tipo'),
                        datasets: [{ data: porTipo.map(r => r.total), backgroundColor: colores }]
                    },
                    options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
                });
            }

            const elZona = document.getElementById('chartZona');
            if (elZona && !elZona.dataset.rendered) {
                elZona.dataset.rendered = '1';
                new Chart(elZona, {
                    type: 'bar',
                    data: {
                        labels: porZona.map(r => r.zona ?? 'Sin zona'),
                        datasets: [{ data: porZona.map(r => r.total), backgroundColor: colores }]
                    },
                    options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
                });
            }

            const elEstado = document.getElementById('chartEstado');
            if (elEstado && !elEstado.dataset.rendered) {
                elEstado.dataset.rendered = '1';
                new Chart(elEstado, {
                    type: 'doughnut',
                    data: {
                        labels: porEstado.map(r => r.estado ?? 'Sin estado'),
                        datasets: [{ data: porEstado.map(r => r.total), backgroundColor: colores }]
                    },
                    options: { plugins: { legend: { position: 'bottom' } } }
                });
            }
        }
    </script>
</div>
