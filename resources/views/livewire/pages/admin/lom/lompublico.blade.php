    <?php
    use Livewire\Attributes\Layout;
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Storage;
    use Livewire\Attributes\Computed;
    use Livewire\Volt\Component;

    new #[Layout('layouts.publico')]
    class extends Component
    {
        #[Computed(cache: false)]
        public function loms()
        {
            return DB::table('tb_lom')
                ->leftJoin('tb_zona', 'tb_lom.idtb_zona', '=', 'tb_zona.id')
                ->leftJoin('tb_instituto_superior', 'tb_lom.id_instituto_superior', '=', 'tb_instituto_superior.id')
                ->leftJoin('tb_carreras', 'tb_lom.idCarrera', '=', 'tb_carreras.id')
                ->leftJoin('tipo_llamado', 'tb_lom.idtipo_llamado', '=', 'tipo_llamado.id')
                ->leftJoin('tb_cargos', 'tb_lom.idtb_cargo', '=', 'tb_cargos.id')
                ->leftJoin('tb_espacioscurriculares', 'tb_lom.idEspacioCurricular', '=', 'tb_espacioscurriculares.idEspacioCurricular')
                ->where('tb_lom.idtb_tipoestado', 8)
                ->select(
                    'tb_lom.*',
                    'tb_zona.nombre_zona',
                    'tb_instituto_superior.nombre as instituto_nombre',
                    'tb_carreras.nombre as carrera_nombre',
                    'tipo_llamado.nombre as tipo_nombre',
                    'tb_cargos.nombre_cargo',
                    'tb_espacioscurriculares.nombre_espacio'
                )
                ->orderBy('tb_lom.idtb_lom', 'desc')
                ->get();
        }
    };
    
?>

<div class="p-6 bg-white rounded-xl shadow-lg border border-gray-100 max-w-6xl mx-auto my-8">
    
    <div class="flex items-center mb-8 pb-4 border-b-4 border-indigo-500 w-fit">
        <svg class="w-8 h-8 mr-3 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
        </svg>
        <h1 class="text-3xl font-black text-gray-800">Listados de Orden de Mérito</h1>
    </div>
      @if (Route::has('login'))
        <div class="auth-row">
                        @auth
                            <a href="{{ route('dashboard') }}" class="btn-volver bg-indigo-600 hover:bg-indigo-700 text-white">Volver</a>
                        @else
                        <a href="{{ route('home') }}" class="btn-volver bg-indigo-600 hover:bg-indigo-700 text-white">Volver</a>
                        @endauth
                    </div>
     @endif      
    <div class="overflow-x-auto rounded-2xl border border-gray-300 shadow-2xl">
        <table class="min-w-full border border-gray-300 table-fixed bg-white text-center">
            <thead class="bg-gray-900 text-white">
                <tr>
                    <th class="w-[5%] px-2 py-4 text-xs font-black uppercase border-r border-gray-700">ID</th>
                    <th class="w-[10%] px-2 py-4 text-xs font-black uppercase border-r border-gray-700">Zona</th>
                    <th class="w-[18%] px-2 py-4 text-xs font-black uppercase border-r border-gray-700">Instituto</th>
                    <th class="w-[18%] px-2 py-4 text-xs font-black uppercase border-r border-gray-700">Carrera</th>
                    <th class="w-[18%] px-2 py-4 text-xs font-black uppercase border-r border-gray-700">Espacio / Cargo</th>
                   
                   
                    <th class="w-[13%] px-2 py-4 text-xs font-black uppercase">PDF</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($this->loms as $lom)
                    <tr wire:key="lom-{{ $lom->idtb_lom }}" class="hover:bg-slate-50 transition-all">
                        <td class="px-2 py-4 text-xs font-black text-indigo-600">#{{ $lom->idtb_lom }}</td>
                        <td class="px-2 py-4 text-xs text-gray-700">{{ $lom->nombre_zona ?? '-' }}</td>
                        <td class="px-2 py-4 text-xs font-bold text-gray-800">{{ $lom->instituto_nombre ?? '-' }}</td>
                        <td class="px-2 py-4 text-xs text-gray-700">{{ $lom->carrera_nombre ?? '-' }}</td>
                        <td class="px-2 py-4 text-xs text-gray-700">
                            {{ $lom->nombre_espacio ?? $lom->nombre_cargo ?? '-' }}
                        </td>
                       
                        
                        <td class="px-2 py-4 text-center">
                            @if($lom->pdf)
                                <a href="{{ route('lom.vista', $lom->idtb_lom) }}"
                                    class="inline-flex items-center bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-lg text-xs font-black uppercase transition shadow-md">
                                        <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                        </svg>
                                        Ver LOM
                                </a>
                            @else
                                <span class="text-gray-400 text-xs italic">Sin PDF</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-12 text-center text-gray-400 font-bold italic">
                            No hay LOMs publicados en este momento.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>