<?php

use Livewire\Volt\Component;
use Livewire\Features\SupportFileUploads\WithFileUploads;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;
use Livewire\Attributes\Title;

new #[Title('Gestión de LOM')] class extends Component
{
    use WithPagination, WithFileUploads;

    public $buscarId = '';
    public $pdf = null;
    public $lomEditandoId = null; // ID del registro en tb_lom (si ya existe)
    public $llamadoSeleccionadoId = null; // ID del llamado que se está gestionando
    public $pdfActual = '';
    public $modalAbierto = false;

    public function updatedBuscarId() { $this->resetPage(); }

    #[Computed(cache: false)]
    public function llamados()
    {
        $ahora = \Carbon\Carbon::now(config('app.timezone', 'America/Argentina/Buenos_Aires'));

        $query = DB::table('nuevo_llamado')
            ->leftJoin('tb_zona', 'nuevo_llamado.idtb_zona', '=', 'tb_zona.id')
            ->leftJoin('tipo_llamado', 'nuevo_llamado.idtipo_llamado', '=', 'tipo_llamado.id')
            ->leftJoin('tb_tipoestado', 'nuevo_llamado.idtb_tipoestado', '=', 'tb_tipoestado.idtb_tipoestado')
            ->where('nuevo_llamado.publicado', true)
            ->select(
                'nuevo_llamado.*',
                'tb_zona.nombre_zona',
                'tipo_llamado.nombre as tipo_nombre',
                'tb_tipoestado.nombre_tipoestado as estado_nombre'
            )
            ->orderBy('nuevo_llamado.id', 'desc');

        if ($this->buscarId) {
            $query->where('nuevo_llamado.id', $this->buscarId);
        }

        $rows = $query->paginate(10);

        if ($rows->count() === 0) return $rows;
        $ids = $rows->getCollection()->pluck('id')->toArray();

        $espaciosPorLlamado = DB::table('nuevo_espacios_por_llamado')
            ->join('nuevo_rel_carrera_espacio', 'nuevo_espacios_por_llamado.nuevo_rel_carrera_espacio_id', '=', 'nuevo_rel_carrera_espacio.id')
            ->join('tb_espacioscurriculares', 'nuevo_rel_carrera_espacio.espacio_id', '=', 'tb_espacioscurriculares.idEspacioCurricular')
            ->join('tb_carreras', 'nuevo_rel_carrera_espacio.carrera_id', '=', 'tb_carreras.id')
            ->join('tb_instituto_superior', 'nuevo_espacios_por_llamado.instituto_id', '=', 'tb_instituto_superior.id')
            ->leftJoin('tb_periodo_cursado', 'nuevo_rel_carrera_espacio.periodo_id', '=', 'tb_periodo_cursado.idtb_periodo_cursado')
            ->leftJoin('tb_turnos', 'nuevo_rel_carrera_espacio.turno_id', '=', 'tb_turnos.id')
            ->leftJoin('tb_perfil', 'nuevo_rel_carrera_espacio.perfil_id', '=', 'tb_perfil.idtb_perfil')
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
                DB::raw("'Espacio' as tipo")
            )
            ->get()->groupBy('llamado_id');

        $cargosPorLlamado = DB::table('nuevo_cargo_por_llamado')
            ->join('nuevo_rel_carrera_cargo', 'nuevo_cargo_por_llamado.nuevo_rel_carrera_cargo_id', '=', 'nuevo_rel_carrera_cargo.id')
            ->join('tb_cargos', 'nuevo_rel_carrera_cargo.cargo_id', '=', 'tb_cargos.id')
            ->join('tb_carreras', 'nuevo_rel_carrera_cargo.carrera_id', '=', 'tb_carreras.id')
            ->join('tb_instituto_superior', 'nuevo_cargo_por_llamado.instituto_id', '=', 'tb_instituto_superior.id')
          
            ->leftJoin('tb_turnos', 'nuevo_rel_carrera_cargo.turno_id', '=', 'tb_turnos.id')
            ->leftJoin('tb_perfil', 'nuevo_rel_carrera_cargo.perfil_id', '=', 'tb_perfil.idtb_perfil')
            ->join('tb_situacion_revista', 'nuevo_cargo_por_llamado.situacion_revista_id', '=', 'tb_situacion_revista.idtb_situacion_revista')
            ->whereIn('nuevo_cargo_por_llamado.llamado_id', $ids)
            ->select(
                'nuevo_cargo_por_llamado.llamado_id',
                'tb_cargos.nombre_cargo as detalle',
                'tb_carreras.nombre as carrera',
                'tb_instituto_superior.nombre as instituto',
                          
                
                'tb_turnos.nombre_turno as turno',
                'tb_perfil.nombre_perfil as perfil',
                'nuevo_cargo_por_llamado.horario_cargo as horario_espacio',
                'tb_situacion_revista.nombre_situacion_revista as situacion_revista',
                DB::raw("'Cargo' as tipo")
            )
            ->get()->groupBy('llamado_id');

        $institutosPorLlamado = DB::table('nuevo_espacios_por_llamado')
            ->join('tb_instituto_superior', 'nuevo_espacios_por_llamado.instituto_id', '=', 'tb_instituto_superior.id')
            ->whereIn('nuevo_espacios_por_llamado.llamado_id', $ids)
            ->select('nuevo_espacios_por_llamado.llamado_id', 'tb_instituto_superior.nombre')
            ->union(
                DB::table('nuevo_cargo_por_llamado')
                    ->join('tb_instituto_superior', 'nuevo_cargo_por_llamado.instituto_id', '=', 'tb_instituto_superior.id')
                    ->whereIn('nuevo_cargo_por_llamado.llamado_id', $ids)
                    ->select('nuevo_cargo_por_llamado.llamado_id', 'tb_instituto_superior.nombre')
            )
            ->get()->groupBy('llamado_id');

        // Traer LOMs existentes para estos llamados
        $lomsExistentes = DB::table('tb_lom')
            ->whereIn('llamado_id', $ids)
            ->get()->keyBy('llamado_id');

        foreach ($rows as $item) {
            $tz = config('app.timezone', 'America/Argentina/Buenos_Aires');
            if (\Carbon\Carbon::parse($item->fecha_fin, $tz)->lte($ahora) && $item->idtb_tipoestado != 9) {
                $item->idtb_tipoestado = 9;
                $item->estado_nombre = 'Cerrado';
            }

            $espacios = $espaciosPorLlamado->get($item->id, collect());
            $cargos   = $cargosPorLlamado->get($item->id, collect());
            $todos    = $espacios->merge($cargos);

            $item->detalles = $todos->values()->toArray();
            $item->nombres_carreras   = $todos->pluck('carrera')->unique()->values()->toArray();
            $item->nombres_institutos = $institutosPorLlamado->get($item->id, collect())->pluck('nombre')->unique()->values()->toArray();
            $item->lom = $lomsExistentes->get($item->id, null);
        }

        return $rows;
    }

    public function abrirModal($llamadoId)
    {
        $this->llamadoSeleccionadoId = $llamadoId;
        $this->pdf = null;

        $lom = DB::table('tb_lom')->where('llamado_id', $llamadoId)->first();
        if ($lom) {
            $this->lomEditandoId = $lom->idtb_lom;
            $this->pdfActual = $lom->pdf ?? '';
        } else {
            $this->lomEditandoId = null;
            $this->pdfActual = '';
        }

        $this->modalAbierto = true;
    }

    public function cerrarModal()
    {
        $this->modalAbierto = false;
        $this->llamadoSeleccionadoId = null;
        $this->lomEditandoId = null;
        $this->pdfActual = '';
        $this->pdf = null;
    }

    public function guardarLom()
    {
        $this->validate([
            'pdf' => $this->lomEditandoId ? 'nullable|file|mimes:pdf|max:10240' : 'required|file|mimes:pdf|max:10240',
        ]);

        $llamado = DB::table('nuevo_llamado')->where('id', $this->llamadoSeleccionadoId)->first();

        $pdfPath = $this->pdfActual;
        if ($this->pdf) {
            if ($this->pdfActual) {
                Storage::disk('public')->delete($this->pdfActual);
            }
            $pdfPath = $this->pdf->store('lom', 'public');
        }

        if ($this->lomEditandoId) {
            DB::table('tb_lom')->where('idtb_lom', $this->lomEditandoId)->update([
                'pdf'             => $pdfPath,
                'idUsuarioEditar' => auth()->id(),
                'updated_at'      => now(),
            ]);
            session()->flash('success', 'LOM actualizado correctamente.');
        } else {
            // Tomar datos del llamado y primer detalle disponible
            $espacio = DB::table('nuevo_espacios_por_llamado')
                ->join('nuevo_rel_carrera_espacio', 'nuevo_espacios_por_llamado.nuevo_rel_carrera_espacio_id', '=', 'nuevo_rel_carrera_espacio.id')
                ->where('nuevo_espacios_por_llamado.llamado_id', $this->llamadoSeleccionadoId)
                ->select('nuevo_espacios_por_llamado.instituto_id', 'nuevo_rel_carrera_espacio.carrera_id', 'nuevo_rel_carrera_espacio.espacio_id')
                ->first();

            $cargo = DB::table('nuevo_cargo_por_llamado')
                ->join('nuevo_rel_carrera_cargo', 'nuevo_cargo_por_llamado.nuevo_rel_carrera_cargo_id', '=', 'nuevo_rel_carrera_cargo.id')
                ->where('nuevo_cargo_por_llamado.llamado_id', $this->llamadoSeleccionadoId)
                ->select('nuevo_cargo_por_llamado.instituto_id', 'nuevo_rel_carrera_cargo.carrera_id', 'nuevo_rel_carrera_cargo.cargo_id')
                ->first();

            DB::table('tb_lom')->insert([
                'llamado_id'           => $this->llamadoSeleccionadoId,
                'idtb_zona'            => $llamado->idtb_zona,
                'id_instituto_superior'=> $espacio->instituto_id ?? ($cargo->instituto_id ?? null),
                'idCarrera'            => $espacio->carrera_id ?? ($cargo->carrera_id ?? null),
                'idEspacioCurricular'  => $espacio->espacio_id ?? null,
                'idtb_cargo'           => $cargo->cargo_id ?? null,
                'idtipo_llamado'       => $llamado->idtipo_llamado,
                'pdf'                  => $pdfPath,
                'idtb_tipoestado'      => 8,
                'idUsuarioCrear'       => auth()->id(),
                'created_at'           => now(),
            ]);
            session()->flash('success', 'LOM creado correctamente.');
        }

        $this->cerrarModal();
    }

    public function publicarLom($llamadoId)
    {
        DB::table('tb_lom')->where('llamado_id', $llamadoId)->update(['idtb_tipoestado' => 8]);
        session()->flash('success', 'LOM publicado.');
    }

    public function eliminarLom($lomId)
    {
        $lom = DB::table('tb_lom')->where('idtb_lom', $lomId)->first();
        if ($lom && $lom->pdf) {
            Storage::disk('public')->delete($lom->pdf);
        }
        DB::table('tb_lom')->where('idtb_lom', $lomId)->delete();
        session()->flash('success', 'LOM eliminado.');
    }
};
?>

<div class="p-6 bg-white rounded-xl shadow-lg border border-gray-100 max-w-6xl mx-auto my-8">

    {{-- HEADER --}}
    <div class="flex justify-between items-center mb-6 pb-4 border-b-4 border-indigo-500 w-fit">
        <div class="flex items-center">
            <svg class="w-8 h-8 mr-3 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            <h1 class="text-3xl font-black text-gray-800">Gestión de LOMs</h1>
        </div>
    </div>

    {{-- FLASH --}}
    @if(session()->has('success'))
        <div class="mb-6 p-4 bg-green-50 border-l-4 border-green-500 text-green-700 rounded-r-lg flex items-center">
            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
            <span class="font-bold">{{ session('success') }}</span>
        </div>
    @endif

    {{-- BUSCADOR --}}
    <div class="mb-6">
        <div class="flex items-center gap-3 max-w-sm">
            <div class="relative flex-1">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input
                    type="number"
                    wire:model.live.debounce.400ms="buscarId"
                    placeholder="Buscar por ID de llamado..."
                    class="w-full pl-9 pr-4 py-2 border border-gray-300 rounded-lg shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
            </div>
            @if($buscarId)
                <button wire:click="$set('buscarId', '')" class="text-xs font-bold text-gray-500 hover:text-red-600 uppercase transition">Limpiar</button>
            @endif
        </div>
        <p class="text-xs text-gray-400 mt-1 ml-1">Solo se muestran llamados publicados.</p>
    </div>

    {{-- TABLA --}}
    <div class="overflow-x-auto rounded-2xl border border-gray-300 shadow-2xl">
        <table class="min-w-full border border-gray-300 table-fixed bg-white text-center">
            <thead class="bg-gray-900 text-white">
                <tr>
                    <th class="w-[6%] px-2 py-4 text-center text-xs font-black uppercase border-r border-gray-700">ID / Zona</th>
                    <th class="w-[12%] px-2 py-4 text-center text-xs font-black uppercase border-r border-gray-700">Instituto</th>
                    <th class="w-[12%] px-2 py-4 text-center text-xs font-black uppercase border-r border-gray-700">Carreras</th>
                    <th class="w-[28%] px-2 py-4 text-center text-xs font-black uppercase border-r border-gray-700">Espacios / Cargos</th>
                    <th class="w-[22%] px-2 py-4 text-center text-xs font-black uppercase border-r border-gray-700">Perfil</th>
                    <th class="w-[10%] px-2 py-4 text-center text-xs font-black uppercase border-r border-gray-700">Inscripción</th>
                    <th class="w-[10%] px-2 py-4 text-center text-xs font-black uppercase">LOM</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 align-top text-center">

                @forelse($this->llamados as $item)
                    <tr wire:key="lom-llamado-{{ $item->id }}" class="border-b hover:bg-slate-50 hover:shadow-sm transition-all">

                        {{-- ID / ZONA / ESTADO --}}
                        <td class="px-1 py-4 align-top text-center overflow-hidden">
                            <div class="text-[10px] font-black text-indigo-600 mb-1">#{{ $item->id }}</div>
                            <div class="inline-block bg-indigo-100 text-indigo-800 text-[10px] font-black px-1 rounded uppercase tracking-tighter mb-1">
                                {{ $item->nombre_zona }}
                            </div>
                            <div class="mt-2">
                                @if($item->idtb_tipoestado == 8)
                                    <span class="bg-green-100 text-green-700 px-2 py-0.5 rounded-full text-[10px] font-black uppercase border border-green-200">Abierto</span>
                                @else
                                    <span class="bg-red-100 text-red-600 px-2 py-0.5 rounded-full text-[10px] font-black uppercase border border-red-100">Cerrado</span>
                                @endif
                            </div>
                        </td>

                        {{-- INSTITUTO --}}
                        <td class="px-2 py-4 align-top border-r border-gray-100">
                            @foreach($item->nombres_institutos as $instituto)
                                <div class="text-xs font-bold text-gray-800 py-0.5">{{ $instituto }}</div>
                            @endforeach
                        </td>

                        {{-- CARRERAS --}}
                        <td class="px-2 py-4 align-top border-r border-gray-100">
                            @foreach($item->detalles as $det)
                                <div class="text-xs font-bold text-gray-800 py-0.5 min-h-[100px]">{{ $det->carrera }}</div>
                            @endforeach
                        </td>

                        {{-- ESPACIOS / CARGOS --}}
                        <td class="px-2 py-2 align-top border-r border-gray-100">
                            <div class="flex flex-col">
                                @foreach($item->detalles as $det)
                                    <div class="min-h-[100px] border-b border-gray-100 pb-2 mb-2">
                                        <div class="text-xs font-black text-slate-700">{{ $det->detalle }}</div>
                                        <div class="mt-1 text-xs text-gray-500 space-y-0.5">
                                            <div>
                                               
                                                <span class="font-bold ml-2">{{ $det->situacion_revista }}</span>
                                            </div>
                                           
                                            <div>
                                                
                                                <span class="font-bold ml-2">Turno:</span> {{ $det->turno }}
                                            </div>
                                            <div class="break-words whitespace-normal">
                                                <span class="font-bold">Horario:</span> {{ $det->horario_espacio }}
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </td>

                        {{-- PERFIL --}}
                        <td class="px-2 py-2 align-top border-r border-gray-100">
                            <div class="flex flex-col">
                                @foreach($item->detalles as $det)
                                    <div class="min-h-[100px] border-b border-gray-100 pb-2 mb-2">
                                        <div class="text-xs text-gray-700 break-words whitespace-normal leading-relaxed">
                                            {{ $det->perfil }}
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </td>

                        {{-- FECHAS --}}
                        <td class="px-2 py-4 align-top border-r border-gray-100">
                            <div class="space-y-2 text-center">
                                <div class="bg-gray-50 px-1 py-1.5 rounded border border-gray-100">
                                    <div class="text-[9px] font-black text-gray-400 uppercase tracking-tighter">Inicia</div>
                                    <div class="text-[11px] font-black text-gray-700">{{ \Carbon\Carbon::parse($item->fecha_ini)->format('d/m/y H:i') }}</div>
                                </div>
                                <div class="bg-gray-50 px-1 py-1.5 rounded border border-red-100">
                                    <div class="text-[9px] font-black text-red-400 uppercase tracking-tighter">Finaliza</div>
                                    <div class="text-[11px] font-black text-red-700">{{ \Carbon\Carbon::parse($item->fecha_fin)->format('d/m/y H:i') }}</div>
                                </div>
                            </div>
                        </td>

                        {{-- COLUMNA LOM --}}
                        <td class="px-2 py-4 align-top">
                            <div class="flex flex-col items-center space-y-2">

                                @if($item->lom)
                                    {{-- Ya tiene LOM --}}
                                    @if($item->lom->pdf)
                                        <a href="{{ Storage::url($item->lom->pdf) }}" target="_blank"
                                           class="inline-flex items-center bg-red-600 hover:bg-red-700 text-white px-2 py-1.5 rounded text-[10px] font-black uppercase transition shadow-sm">
                                            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"></path></svg>
                                            Ver PDF
                                        </a>
                                    @endif

                                    @if($item->lom->idtb_tipoestado == 8)
                                        <div class="w-full px-2 bg-green-50 text-green-700 text-[10px] font-black py-1.5 rounded border border-green-100 uppercase italic text-center">
                                            Publicado
                                        </div>
                                    @else
                                        <button wire:click="publicarLom({{ $item->id }})"
                                                class="w-full px-2 bg-yellow-500 hover:bg-yellow-600 text-white font-black py-1.5 rounded text-[10px] uppercase transition shadow-sm">
                                            Publicar
                                        </button>
                                    @endif

                                    <button wire:click="abrirModal({{ $item->id }})"
                                            class="w-full px-2 bg-slate-700 hover:bg-slate-800 text-white font-black py-1.5 rounded text-[10px] uppercase transition shadow-sm">
                                        Editar PDF
                                    </button>

                                    <button
                                        onclick="confirm('¿Eliminar el LOM de este llamado?') || event.stopImmediatePropagation()"
                                        wire:click="eliminarLom({{ $item->lom->idtb_lom }})"
                                        class="w-full px-2 bg-white hover:bg-red-50 text-red-600 font-black py-1.5 rounded text-[10px] uppercase border border-red-100 transition">
                                        Eliminar LOM
                                    </button>

                                @else
                                    {{-- Sin LOM todavía --}}
                                    <div class="text-[10px] text-gray-400 italic mb-1">Sin LOM</div>
                                    <button wire:click="abrirModal({{ $item->id }})"
                                            class="w-full px-2 bg-indigo-600 hover:bg-indigo-700 text-white font-black py-1.5 rounded text-[10px] uppercase transition shadow-sm">
                                        + Crear LOM
                                    </button>
                                @endif

                            </div>
                        </td>

                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-16 text-center bg-gray-50">
                            <div class="flex flex-col items-center">
                                <svg class="w-12 h-12 text-gray-200 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                </svg>
                                <span class="text-gray-400 font-black uppercase tracking-widest text-sm">
                                    @if($buscarId)
                                        No se encontró el llamado #{{ $buscarId }}
                                    @else
                                        No hay llamados publicados
                                    @endif
                                </span>
                            </div>
                        </td>
                    </tr>
                @endforelse

            </tbody>
        </table>

        <div class="mt-4 px-4 pb-4">
            {{ $this->llamados->links() }}
        </div>
    </div>

    {{-- MODAL PDF --}}
    @if($modalAbierto)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 p-6">

                <div class="flex justify-between items-center mb-4 border-b pb-3">
                    <div class="flex items-center">
                        <span class="bg-indigo-600 text-white px-3 py-1 rounded-lg text-sm font-black mr-3">
                            LOM #{{ $llamadoSeleccionadoId }}
                        </span>
                        <h2 class="text-lg font-black text-gray-800 uppercase">
                            {{ $lomEditandoId ? 'Editar PDF' : 'Nuevo LOM' }}
                        </h2>
                    </div>
                    <button wire:click="cerrarModal" class="text-gray-400 hover:text-gray-600 text-2xl font-bold p-1 rounded-full hover:bg-gray-100">✕</button>
                </div>

                <div class="space-y-4">
                    @if($pdfActual)
                        <div>
                            <p class="text-xs font-bold text-gray-500 uppercase mb-2">PDF Actual</p>
                            <a href="{{ Storage::url($pdfActual) }}" target="_blank"
                               class="inline-flex items-center bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-lg text-xs font-black uppercase transition">
                                <svg class="w-4 h-4 mr-1.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"></path></svg>
                                Ver PDF actual
                            </a>
                        </div>
                    @endif

                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1 uppercase">
                            {{ $pdfActual ? 'Reemplazar PDF (opcional)' : 'Subir PDF *' }}
                        </label>
                        <input type="file" wire:model="pdf" accept=".pdf"
                               class="w-full border border-gray-300 rounded-lg text-sm p-2 bg-white shadow-sm">
                        @error('pdf') <span class="text-red-500 text-xs font-bold mt-1 block">{{ $message }}</span> @enderror
                        <div wire:loading wire:target="pdf" class="text-xs text-indigo-500 mt-1">Subiendo archivo...</div>
                    </div>
                </div>

                <div class="flex justify-end gap-3 mt-6 pt-4 border-t">
                    <button wire:click="cerrarModal"
                            class="px-5 py-2 text-xs font-black text-gray-500 hover:text-gray-700 uppercase transition">
                        Cancelar
                    </button>
                    <button wire:click="guardarLom" wire:loading.attr="disabled"
                            class="bg-indigo-600 hover:bg-indigo-700 text-white font-black px-8 py-2 rounded-lg text-xs uppercase shadow-md transition disabled:opacity-50 flex items-center">
                        <span wire:loading.remove>{{ $lomEditandoId ? 'Actualizar' : 'Guardar LOM' }}</span>
                        <span wire:loading class="flex items-center">
                            <svg class="animate-spin h-4 w-4 mr-2 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Procesando...
                        </span>
                    </button>
                </div>

            </div>
        </div>
    @endif

</div>