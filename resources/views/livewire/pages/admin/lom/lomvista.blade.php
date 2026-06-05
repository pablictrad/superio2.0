<?php

use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new #[Layout('layouts.publico')]
class extends Component
{
    public int $lomId = 0;

    public function mount(int $id): void
    {
        $this->lomId = $id;
    }

    #[Computed(cache: false)]
    public function lom()
    {
        return DB::table('tb_lom')
            ->leftJoin('tb_zona',              'tb_lom.idtb_zona',            '=', 'tb_zona.id')
            ->leftJoin('tb_instituto_superior', 'tb_lom.id_instituto_superior','=', 'tb_instituto_superior.id')
            ->leftJoin('tb_carreras',           'tb_lom.idCarrera',            '=', 'tb_carreras.id')
            ->leftJoin('tipo_llamado',          'tb_lom.idtipo_llamado',       '=', 'tipo_llamado.id')
            ->leftJoin('tb_cargos',             'tb_lom.idtb_cargo',           '=', 'tb_cargos.id')
            ->leftJoin('tb_espacioscurriculares','tb_lom.idEspacioCurricular', '=', 'tb_espacioscurriculares.idEspacioCurricular')
            ->where('tb_lom.idtb_lom', $this->lomId)
            ->where('tb_lom.idtb_tipoestado', 8)
            ->select(
                'tb_lom.*',
                'tb_zona.nombre_zona',
                'tb_instituto_superior.nombre as instituto_nombre',
                'tb_carreras.nombre           as carrera_nombre',
                'tipo_llamado.nombre          as tipo_nombre',
                'tb_cargos.nombre_cargo',
                'tb_espacioscurriculares.nombre_espacio'
            )
            ->first();
    }

    #[Computed(cache: false)]
    public function habilitados()
    {
        if (!$this->lom) return collect();

        return DB::table('inscripciones_llamado')
            ->where('llamado_id', $this->lom->llamado_id)
            ->where('estado', 'habilitado')
            ->orderByDesc('puntaje')
            ->orderBy('apellido')
            ->get()
            ->map(function ($ins, $idx) {
                $ins->orden_display = str_pad($idx + 1, 2, '0', STR_PAD_LEFT);
                return $ins;
            });
    }

    #[Computed(cache: false)]
    public function sinClasificar()
    {
        if (!$this->lom) return collect();

        return DB::table('inscripciones_llamado')
            ->where('llamado_id', $this->lom->llamado_id)
            ->where('estado', 'sin_clasificar')
            ->orderBy('apellido')
            ->get();
    }
}; ?>

<div class="p-6 bg-white rounded-xl shadow-lg border border-gray-100 max-w-6xl mx-auto my-8">

    @if(!$this->lom)
        <div class="text-center py-16 text-gray-400 font-bold italic">
            LOM no encontrado o no publicado.
        </div>
    @else

    {{-- ENCABEZADO --}}
    <div class="mb-8 pb-4 border-b-4 border-indigo-500">
        <div class="flex items-center mb-3">
            <svg class="w-8 h-8 mr-3 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            <h1 class="text-3xl font-black text-gray-800">Listado de Orden de Mérito</h1>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4">
            <div class="bg-gray-50 rounded-lg p-3 border border-gray-100">
                <div class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">Zona</div>
                <div class="text-sm font-bold text-gray-700">{{ $this->lom->nombre_zona ?? '—' }}</div>
            </div>
            <div class="bg-gray-50 rounded-lg p-3 border border-gray-100">
                <div class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">Instituto</div>
                <div class="text-sm font-bold text-gray-700">{{ $this->lom->instituto_nombre ?? '—' }}</div>
            </div>
            <div class="bg-gray-50 rounded-lg p-3 border border-gray-100">
                <div class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">Carrera</div>
                <div class="text-sm font-bold text-gray-700">{{ $this->lom->carrera_nombre ?? '—' }}</div>
            </div>
            <div class="bg-gray-50 rounded-lg p-3 border border-gray-100">
                <div class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">Espacio / Cargo</div>
                <div class="text-sm font-bold text-gray-700">
                    {{ $this->lom->nombre_espacio ?? $this->lom->nombre_cargo ?? '—' }}
                </div>
            </div>
        </div>
    </div>

    {{-- HABILITADOS --}}
    <div class="mb-8">
        <h2 class="text-sm font-black text-white uppercase tracking-widest bg-teal-700 px-4 py-3 rounded-t-xl">
            Orden de Mérito — Habilitados
        </h2>

        @if($this->habilitados->isNotEmpty())
        <div class="overflow-x-auto rounded-b-xl border border-teal-100 shadow">
            <table class="min-w-full bg-white">
                <thead class="bg-teal-50">
                    <tr>
                        <th class="px-4 py-3 text-center text-xs font-black uppercase text-teal-700 w-12">Ord.</th>
                        <th class="px-4 py-3 text-left text-xs font-black uppercase text-teal-700">Apellido y Nombre</th>
                        <th class="px-4 py-3 text-left text-xs font-black uppercase text-teal-700">DNI</th>
                        <th class="px-4 py-3 text-left text-xs font-black uppercase text-teal-700">Domicilio</th>
                        <th class="px-4 py-3 text-center text-xs font-black uppercase text-teal-700">Clasificación</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($this->habilitados as $ins)
                    <tr class="hover:bg-teal-50 transition">
                        <td class="px-4 py-3 text-center font-black text-teal-700">{{ $ins->orden_display }}</td>
                        <td class="px-4 py-3 font-bold text-gray-800">
                            {{ strtoupper($ins->apellido) }}, {{ $ins->nombre }}
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600 font-mono">{{ $ins->dni }}</td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ $ins->domicilio ?? '—' }}</td>
                        <td class="px-4 py-3 text-center font-black text-teal-700">
                            @if($ins->puntaje !== null)
                                {{ number_format($ins->puntaje, 2) }}
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
            <div class="text-center py-8 text-gray-400 italic border border-t-0 border-gray-100 rounded-b-xl">
                No hay inscriptos habilitados.
            </div>
        @endif
    </div>

    {{-- SIN CLASIFICAR --}}
    @if($this->sinClasificar->isNotEmpty())
    <div>
        <h2 class="text-sm font-black text-white uppercase tracking-widest bg-red-700 px-4 py-3 rounded-t-xl">
            Sin Clasificar — No válido para designar
        </h2>
        <div class="overflow-x-auto rounded-b-xl border border-red-100 shadow">
            <table class="min-w-full bg-white">
                <thead class="bg-red-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-black uppercase text-red-700">Apellido y Nombre</th>
                        <th class="px-4 py-3 text-left text-xs font-black uppercase text-red-700">DNI</th>
                        <th class="px-4 py-3 text-left text-xs font-black uppercase text-red-700">Motivo</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($this->sinClasificar as $ins)
                    <tr class="hover:bg-red-50 transition">
                        <td class="px-4 py-3 font-bold text-gray-800">
                            {{ strtoupper($ins->apellido) }}, {{ $ins->nombre }}
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600 font-mono">{{ $ins->dni }}</td>
                        <td class="px-4 py-3 text-sm text-gray-500 italic">
                            {{ $ins->observaciones ?? 'No reúne perfil requerido' }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- PIE --}}
    <div class="mt-8 pt-4 border-t border-gray-100 text-center text-xs text-gray-400">
        Comisión Provisoria de Nivel Superior · La Rioja · LOM #{{ $this->lom->idtb_lom }}
    </div>

    @endif
</div>