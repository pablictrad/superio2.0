<?php

use Livewire\Volt\Component;
use Illuminate\Support\Facades\DB;

new class extends Component {
    
    public $llamados = [];

    public function mount()
    {
        $this->cargarLlamados();
    }

    public function cargarLlamados()
    {
        $hoy = now();
        $ESTADO_CERRADO = 9; // ID de estado cerrado

        // Obtener llamados con la información relacionada principal
        $llamadosRaw = DB::table('nuevo_llamado')
            ->join('tipo_llamado', 'nuevo_llamado.idtipo_llamado', '=', 'tipo_llamado.id')
            ->join('tb_tipoestado', 'nuevo_llamado.idtb_tipoestado', '=', 'tb_tipoestado.idtb_tipoestado')
            ->join('tb_zona', 'nuevo_llamado.idtb_zona', '=', 'tb_zona.id')
            ->select(
                'nuevo_llamado.*', 
                'tipo_llamado.nombre as tipo_nombre', 
                'tb_tipoestado.nombre_tipoestado as estado_nombre',
                'tb_zona.nombre_zona'
            )
            ->orderBy('nuevo_llamado.created_at', 'desc')
            ->get();

        foreach ($llamadosRaw as $llamado) {
            // Auto-cerrar si la fecha fin ya pasó
            if ($llamado->fecha_fin < $hoy && $llamado->idtb_tipoestado != $ESTADO_CERRADO) {
                DB::table('nuevo_llamado')
                    ->where('id', $llamado->id)
                    ->update(['idtb_tipoestado' => $ESTADO_CERRADO]);
                
                $llamado->idtb_tipoestado = $ESTADO_CERRADO;
                $llamado->estado_nombre = 'Cerrado';
            }

            // Buscar si tiene espacios curriculares
            $espacio = DB::table('nuevo_espacios_por_llamado')
                ->join('nuevo_rel_carrera_espacio', 'nuevo_espacios_por_llamado.nuevo_rel_carrera_espacio_id', '=', 'nuevo_rel_carrera_espacio.id')
                ->join('tb_espacioscurriculares', 'nuevo_rel_carrera_espacio.espacio_id', '=', 'tb_espacioscurriculares.idEspacioCurricular')
                ->join('tb_carreras', 'nuevo_rel_carrera_espacio.carrera_id', '=', 'tb_carreras.id')
                ->where('nuevo_espacios_por_llamado.llamado_id', $llamado->id)
                ->select('tb_espacioscurriculares.nombre_espacio as elemento', 'tb_carreras.nombre as carrera_nombre')
                ->first();

            // Si no tiene espacios, buscar si tiene cargos
            if (!$espacio) {
                $cargo = DB::table('nuevo_cargo_por_llamado')
                    ->join('nuevo_rel_carrera_cargo', 'nuevo_cargo_por_llamado.nuevo_rel_carrera_cargo_id', '=', 'nuevo_rel_carrera_cargo.id')
                    ->join('tb_cargos', 'nuevo_rel_carrera_cargo.cargo_id', '=', 'tb_cargos.id')
                    ->join('tb_carreras', 'nuevo_rel_carrera_cargo.carrera_id', '=', 'tb_carreras.id')
                    ->where('nuevo_cargo_por_llamado.llamado_id', $llamado->id)
                    ->select('tb_cargos.nombre_cargo as elemento', 'tb_carreras.nombre as carrera_nombre')
                    ->first();
                
                $llamado->detalle_elemento = $cargo ? $cargo->elemento : 'N/A';
                $llamado->detalle_carrera = $cargo ? $cargo->carrera_nombre : 'N/A';
                $llamado->tipo_detalle = 'Cargo';
            } else {
                $llamado->detalle_elemento = $espacio->elemento;
                $llamado->detalle_carrera = $espacio->carrera_nombre;
                $llamado->tipo_detalle = 'Espacio Curricular';
            }
        }

        $this->llamados = $llamadosRaw;
    }
};
?>

<div class="p-6 max-w-7xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Convocatorias / Llamados</h1>
        <!-- Enlace simulado al panel admin, útil para probar -->
        <a href="/admin/llamados/crear" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded shadow text-sm font-semibold transition">
            Admin: Crear Llamado
        </a>
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden border border-gray-200">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Zona / Carrera</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Detalle</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo / Estado</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fechas</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Inscripción</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($llamados as $llamado)
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">{{ $llamado->detalle_carrera }}</div>
                                <div class="text-sm text-gray-500">{{ $llamado->nombre_zona }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-bold text-gray-800">{{ $llamado->detalle_elemento }}</div>
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                    {{ $llamado->tipo_detalle }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">{{ $llamado->tipo_nombre }}</div>
                                
                                @if($llamado->idtb_tipoestado == 8)
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 mt-1">
                                        {{ $llamado->estado_nombre }}
                                    </span>
                                @elseif($llamado->idtb_tipoestado == 9)
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800 mt-1">
                                        {{ $llamado->estado_nombre }}
                                    </span>
                                @else
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800 mt-1">
                                        {{ $llamado->estado_nombre }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <div><span class="font-semibold text-gray-700">Inicio:</span> {{ \Carbon\Carbon::parse($llamado->fecha_ini)->format('d/m/Y H:i') }}</div>
                                <div><span class="font-semibold text-gray-700">Fin:</span> {{ \Carbon\Carbon::parse($llamado->fecha_fin)->format('d/m/Y H:i') }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                @if($llamado->idtb_tipoestado == 8 && $llamado->fecha_fin >= now())
                                    <a href="{{ $llamado->url_form ?: '#' }}" target="_blank" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition">
                                        Inscribirse
                                    </a>
                                @else
                                    <span class="text-gray-400 italic">Cerrado</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-4 text-center text-gray-500 font-medium">
                                No hay llamados registrados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>