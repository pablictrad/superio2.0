<?php

use Livewire\Attributes\Layout;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;


new #[Layout('layouts.publico')]
class extends Component
{
    private function tz(): string
    {
        return config('app.timezone', 'America/Argentina/Buenos_Aires');
    }

    #[Computed(cache: false)]
    public function llamados()
    {
        $ahora = Carbon::now($this->tz());
        $ESTADO_CERRADO = 9;

        $rows = DB::table('nuevo_llamado')
            ->leftJoin('tb_zona', 'nuevo_llamado.idtb_zona', '=', 'tb_zona.id')
            ->leftJoin('tipo_llamado', 'nuevo_llamado.idtipo_llamado', '=', 'tipo_llamado.id')
            ->leftJoin('tb_tipoestado', 'nuevo_llamado.idtb_tipoestado', '=', 'tb_tipoestado.idtb_tipoestado')
            ->where('nuevo_llamado.publicado', true)   // ← solo publicados
            ->select(
                'nuevo_llamado.*',
                'tb_zona.nombre_zona',
                'tipo_llamado.nombre as tipo_nombre',
                'tb_tipoestado.nombre_tipoestado as estado_nombre'
            )
            ->orderBy('nuevo_llamado.id', 'desc')
            ->get();

        if ($rows->isEmpty()) {
            return $rows;
        }

        $ids = $rows->pluck('id')->toArray();

        $espaciosPorLlamado = DB::table('nuevo_espacios_por_llamado')
            ->join('nuevo_rel_carrera_espacio', 'nuevo_espacios_por_llamado.nuevo_rel_carrera_espacio_id', '=', 'nuevo_rel_carrera_espacio.id')
            ->join('tb_espacioscurriculares', 'nuevo_rel_carrera_espacio.espacio_id', '=', 'tb_espacioscurriculares.idEspacioCurricular')
            ->join('tb_carreras', 'nuevo_rel_carrera_espacio.carrera_id', '=', 'tb_carreras.id')
            ->join(
                'tb_instituto_superior',
                'nuevo_espacios_por_llamado.instituto_id',
                '=',
                'tb_instituto_superior.id'
            )
            ->join('tb_periodo_cursado', 'nuevo_rel_carrera_espacio.periodo_id', '=', 'tb_periodo_cursado.idtb_periodo_cursado')
            ->join('tb_turnos', 'nuevo_rel_carrera_espacio.turno_id', '=', 'tb_turnos.id')
            ->join('tb_perfil', 'nuevo_rel_carrera_espacio.perfil_id', '=', 'tb_perfil.idtb_perfil')
            ->join('tb_situacion_revista', 'nuevo_espacios_por_llamado.situacion_revista_id', '=', 'tb_situacion_revista.idtb_situacion_revista')
            ->whereIn('nuevo_espacios_por_llamado.llamado_id', $ids)
            ->select(
                'nuevo_espacios_por_llamado.llamado_id',
                'tb_espacioscurriculares.nombre_espacio as detalle',
                'tb_carreras.nombre as carrera',
                'tb_instituto_superior.nombre as instituto',
                'nuevo_rel_carrera_espacio.hora_catedra',
                'nuevo_rel_carrera_espacio.anio',
                'tb_periodo_cursado.nombre_periodo as periodo',
                'tb_turnos.nombre_turno as turno',
                'tb_perfil.nombre_perfil as perfil',
                'nuevo_espacios_por_llamado.horario_espacio',
                'tb_situacion_revista.nombre_situacion_revista as situacion_revista',
                DB::raw("'Espacio' as tipo"),
            )
            ->get()->groupBy('llamado_id');

        $cargosPorLlamado = DB::table('nuevo_cargo_por_llamado')
          ->join('nuevo_rel_instituto_cargo', 'nuevo_cargo_por_llamado.nuevo_rel_instituto_cargo_id', '=', 'nuevo_rel_instituto_cargo.id')
          ->join('tb_cargos', 'nuevo_rel_instituto_cargo.cargo_id', '=', 'tb_cargos.id')
          ->leftJoin('tb_carreras', 'nuevo_cargo_por_llamado.carrera_id', '=', 'tb_carreras.id')
          ->join('tb_instituto_superior', 'nuevo_cargo_por_llamado.instituto_id', '=', 'tb_instituto_superior.id')
          ->leftJoin('tb_perfil', 'nuevo_rel_instituto_cargo.perfil_id', '=', 'tb_perfil.idtb_perfil')
          ->leftJoin('tb_turnos', 'nuevo_rel_instituto_cargo.turno_id', '=', 'tb_turnos.id')
          ->join('tb_situacion_revista', 'nuevo_cargo_por_llamado.situacion_revista_id', '=', 'tb_situacion_revista.idtb_situacion_revista')
          ->whereIn('nuevo_cargo_por_llamado.llamado_id', $ids)
          ->select(
                'nuevo_cargo_por_llamado.llamado_id',
                'tb_cargos.nombre_cargo as detalle',
                'tb_carreras.nombre as carrera',
                'tb_instituto_superior.nombre as instituto',
                'tb_cargos.hora_catedra',
                DB::raw('NULL as anio'),
                DB::raw('NULL as periodo'),
                'tb_turnos.nombre_turno as turno',
                'tb_perfil.nombre_perfil as perfil',
                'nuevo_cargo_por_llamado.horario_cargo as horario_espacio',
                'tb_situacion_revista.nombre_situacion_revista as situacion_revista',
                DB::raw("'Cargo' as tipo"),
            )
            ->get()->groupBy('llamado_id');

        foreach ($rows as $item) {
            // Cierre visual
            if (Carbon::parse($item->fecha_fin, $this->tz())->lte($ahora) && $item->idtb_tipoestado != $ESTADO_CERRADO) {
                $item->idtb_tipoestado = $ESTADO_CERRADO;
                $item->estado_nombre = 'Cerrado';
            }

            $espacios = $espaciosPorLlamado->get($item->id, collect());
            $cargos = $cargosPorLlamado->get($item->id, collect());
            $todos = $espacios->merge($cargos);

            $item->detalles = $todos->values()->toArray();
            $item->nombres_carreras = $todos->pluck('carrera')->unique()->values()->toArray();
            $item->nombres_institutos = $todos->pluck('instituto')->unique()->values()->toArray();
        }

        return $rows;
    }
}; ?>

<div class="p-6 bg-white rounded-xl shadow-lg border border-gray-100 max-w-6xl mx-auto my-8">
    <div class="flex justify-center mb-6">
         <img src="{{ asset('img/cabecera.png') }}" alt="Listados de Orden de Mérito" class="w-full h-60 mb-1">
   </div>
  @livewire('pages.publico.inscripcion-llamado')
    <div class="flex items-center mb-8 pb-4 border-b-4 border-indigo-500 w-fit">
        <div class="auth-row">
                        @auth
                            <a href="{{ route('dashboard') }}" class="btn-volver bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg ">Volver</a>
                        @else
                        <a href="{{ route('home') }}" class="btn btn-volver bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg">Volver</a>
                        @endauth
                    </div>
    </div>
    @if (Route::has('login'))
       
     @endif                
    <div class="overflow-x-auto rounded-2xl border border-gray-300 shadow-2xl">

      
        <table class="min-w-full border border-gray-300 table-fixed bg-white text-center">
            <thead class="bg-gray-900 text-white">
                <tr>
                    <th class="w-[6%] px-2 py-4 text-center whitespace-nowrap text-xs font-black uppercase border-r border-gray-700">ID / Zona</th>
                    <th class="w-[12%] px-2 py-4 text-center whitespace-nowrap text-xs font-black uppercase border-r border-gray-700">Instituto</th>
                    <th class="w-[12%] px-2 py-4 text-center whitespace-nowrap text-xs font-black uppercase border-r border-gray-700">Carreras</th>
                    <th class="w-[30%] px-2 py-4 text-center whitespace-nowrap text-xs font-black uppercase border-r border-gray-700">Espacios / Cargos</th>
                    <th class="w-[30%] px-2 py-4 text-center whitespace-nowrap text-xs font-black uppercase border-r border-gray-700">Perfil</th>
                    <th class="w-[10%] px-2 py-4 text-center whitespace-nowrap text-xs font-black uppercase border-r border-gray-700">Inscripción</th>                  
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($this->llamados as $item)
                    <tr wire:key="llamado-{{ $item->id }}" class="border-b hover:bg-slate-50 hover:shadow-sm transition-all">
                        <td class="px-1 py-4 align-top text-center overflow-hidden">
                            <div class="text-[10px] font-black text-indigo-600 mb-1">#{{ $item->id }}</div>
                            <div class="inline-block bg-indigo-100 text-indigo-800 text-[10px] font-black px-1 rounded uppercase tracking-tighter mb-1">
                                {{ $item->nombre_zona }}
                            </div>
                               <div class="mt-3 mb-2 text-center">
                                    @if($item->idtb_tipoestado == 8)
                                        <div class="flex flex-col items-center justify-center">
                                            <span class="bg-green-200 text-green-700 px-2 py-1 rounded-full text-[10px] font-black uppercase mb-1 border border-green-200 shadow-sm animate-pulse">Abierto</span>
                                            <!-- <a href="{{ $item->url_form }}" target="_blank" 
                                               class="inline-flex items-center bg-blue-600 hover:bg-blue-700 text-white font-black px-2 py-2 rounded-lg text-[10px] uppercase transition-all shadow-md hover:shadow-lg transform hover:-translate-y-0.5">
                                                Postularme
                                            </a> -->
                                        
                                            <button
                                                wire:click="$dispatchTo('pages.publico.inscripcion-llamado', 'abrirModal', { id: {{ $item->id }} })"
                                                @if($item->idtb_tipoestado != 8) disabled @endif
                                                class="inline-flex items-center bg-blue-600 hover:bg-blue-700 disabled:bg-gray-300
                                                    disabled:cursor-not-allowed text-white font-black px-2 py-2 rounded-lg
                                                    text-[10px] uppercase transition-all shadow-md">
                                                Inscribirme
                                            </button>
                                        </div>
                                    @else
                                        <span class="bg-red-200 text-red-600 px-2 py-1 rounded-full text-[10px] font-black uppercase border border-red-100">Cerrado</span>
                                    @endif
                                </div>  
                            <div class="text-xs font-bold text-gray-500">{{ $item->descripcion }}</div>
                        </td>

                        <td class="px-2 py-4 align-top border-r border-gray-100">
                            @foreach($item->nombres_institutos as $instituto)
                                <div class="text-sm font-bold text-gray-800 py-0.5">{{ $instituto }}</div>
                            @endforeach
                        </td>

                        <td class="px-2 py-4 align-top border-r border-gray-100">
                            @foreach($item->nombres_carreras as $carrera)
                                <div class="text-sm font-bold text-gray-800 py-0.5 min-h-[120px]">{{ $carrera }}</div>
                            @endforeach
                        </td>

                        <td class="px-2 py-2 align-top border-r border-gray-100">
                            <div class="flex flex-col">
                                @foreach($item->detalles as $det)
                                    <div class="min-h-[120px] border-b border-gray-100 pb-2 mb-2">
                                        
                                        <div class="text-sm font-black text-slate-700">
                                            {{ $det->detalle }}
                                        </div>

                                        <div class="mt-2 text-xs text-gray-500 space-y-1">
                                            <div>
                                                <span class="font-bold">{{ $det->situacion_revista }}</span>
                                            </div>

                                            @if($det->tipo === 'Espacio')
                                                @if($det->periodo)
                                                <div>
                                                    <span class="font-bold">Período:</span> {{ $det->periodo }}
                                                </div>
                                                @endif
                                                @if($det->anio)
                                                <div>
                                                    <span class="font-bold">Curso:</span> {{ $det->anio }}° Año
                                                   @if($det->turno)
                                                    <div>
                                                        <span class="font-bold">Turno:</span> {{ $det->turno }}
                                                    </div>
                                                    @endif
                                                </div>
                                                @endif
                                            @endif

                                            @if($det->horario_espacio)
                                            <div class="break-words whitespace-normal">
                                                <span class="font-bold">Horario:</span> {{ $det->horario_espacio }}
                                            </div>
                                            @endif
                                            @if(!empty($det->hora_catedra))
                                            <div class="break-words whitespace-normal">
                                                <span class="font-bold">Hs. Cátedra:</span> {{ $det->hora_catedra }}
                                            </div>
                                            @endif
                                        </div>

                                    </div>
                                @endforeach
                            </div>
                        </td>

                        <td class="px-2 py-2 align-top border-r border-gray-100">
                            <div class="flex flex-col">
                                @foreach($item->detalles as $det)

                                    <div class="min-h-[120px] border-b border-gray-100 pb-2 mb-2">
                                        <div class="text-xs text-gray-700 break-words whitespace-normal leading-relaxed">
                                            {{ $det->perfil }}
                                        </div>
                                    </div>

                                @endforeach
                            </div>
                        </td>
                        <td class="px-2 py-4 align-top">
                                <div class="space-y-3 text-center">
                                    <div class="bg-gray-50 px-1 py-2 rounded border border-gray-100">
                                        <div class="text-[9px] font-black text-gray-400 uppercase mb-0.5 tracking-tighter">
                                            Inicia
                                        </div>
                                        <div class="text-[11px] font-black text-gray-700">
                                            {{ \Carbon\Carbon::parse($item->fecha_ini)->format('d/m/y H:i') }}
                                        </div>
                                    </div>
                                    <div class="bg-gray-50 px-1 py-2 rounded border border-red-100">
                                        <div class="text-[9px] font-black text-red-400 uppercase mb-0.5 tracking-tighter">
                                            Finaliza
                                        </div>

                                        <div class="text-[11px] font-black text-red-700">
                                            {{ \Carbon\Carbon::parse($item->fecha_fin)->format('d/m/y H:i') }}
                                        </div>
                                    </div>
                                </div>                   
                            </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center text-gray-400 font-bold italic">
                            No hay llamados publicados en este momento.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>