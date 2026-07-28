<?php

use Livewire\Component;
use App\Models\Carrera;
use App\Models\Espacio_curricular;
use App\Models\Perfil;
use App\Models\Turno;
use App\Models\Periodo;
use App\Models\RelCarreraEspacio;

new class extends Component
{
    public $buscarCarrera = '';
    public $buscarEspacio = '';

    public $rows = [];
    public $erroresFila = [];
    public $carreras = [];
    public $espacios = [];
    public $perfiles = [];
    public $turnos = [];
    public $periodos = [];

    public $mostrarModal = false;

    public $nuevo = [
        'carrera_id'    => '',
        'espacio_id'    => '',
        'perfil_id'     => '',
        'anio'          => '',
        'hora_catedra'  => '',
        'periodo_id'    => '',
        'turno_id'      => '',
    ];

    public function mount()
    {
        $this->carreras  = Carrera::orderBy('nombre')->get();
        $this->espacios  = Espacio_curricular::orderBy('nombre_espacio')->get();
        $this->perfiles  = Perfil::orderBy('nombre_perfil')->get();
        $this->turnos    = Turno::orderBy('nombre_turno')->get();
        $this->periodos  = Periodo::orderBy('nombre_periodo')->get();

        $this->buscar();
    }

    public function updatedBuscarCarrera()
    {
        $this->buscar();
    }

    public function updatedBuscarEspacio()
    {
        $this->buscar();
    }

    public function buscar()
    {
        $query = RelCarreraEspacio::with(['carrera','espacio','perfil']);

        if ($this->buscarCarrera) {
            $query->whereHas('carrera', fn($q) =>
                $q->where('nombre','like','%'.$this->buscarCarrera.'%'));
        }

        if ($this->buscarEspacio) {
            $query->whereHas('espacio', fn($q) =>
                $q->where('nombre_espacio','like','%'.$this->buscarEspacio.'%'));
        }

        $this->rows = $query->limit(100)->get();
    }

    public function abrirModal()
    {
        $this->mostrarModal = true;
    }

    public function cerrarModal()
    {
        $this->mostrarModal = false;
    }

    public function guardarNuevo()
    {
        $this->validate();

        RelCarreraEspacio::create($this->nuevo);

        $this->nuevo = [
            'carrera_id'    => '',
            'espacio_id'    => '',
            'perfil_id'     => '',
            'anio'          => '',
            'hora_catedra'  => '',
            'periodo_id'    => '',
            'turno_id'      => '',
        ];

        $this->cerrarModal();
        $this->buscar();

        session()->flash('success', 'Registro agregado.');
    }

    public function guardarFila($index)
    {
        $this->erroresFila[$index] = [];

        if (!$this->rows[$index]['anio']) {
            $this->erroresFila[$index]['anio'] = 'Ingrese año';
        }

        if (!$this->rows[$index]['hora_catedra']) {
            $this->erroresFila[$index]['hora_catedra'] = 'Ingrese horas';
        }

        if (!$this->rows[$index]['periodo_id']) {
            $this->erroresFila[$index]['periodo_id'] = 'Seleccione periodo';
        }

        if (!$this->rows[$index]['turno_id']) {
            $this->erroresFila[$index]['turno_id'] = 'Seleccione turno';
        }

        if (!empty($this->erroresFila[$index])) {
            return;
        }

        RelCarreraEspacio::find($this->rows[$index]['id'])->update([
            'carrera_id'   => $this->rows[$index]['carrera_id'],
            'espacio_id'   => $this->rows[$index]['espacio_id'],
            'perfil_id'    => $this->rows[$index]['perfil_id'],
            'anio'         => $this->rows[$index]['anio'],
            'hora_catedra' => $this->rows[$index]['hora_catedra'],
            'periodo_id'   => $this->rows[$index]['periodo_id'],
            'turno_id'     => $this->rows[$index]['turno_id'],
        ]);

        session()->flash('success', 'Fila actualizada.');
    }

    public function eliminar($id)
    {
        RelCarreraEspacio::find($id)?->delete();

        $this->buscar();

        session()->flash('success', 'Registro eliminado.');
    }
    protected function rules()
    {
        return [
            'nuevo.carrera_id'   => 'required',
            'nuevo.espacio_id'   => 'required',
            'nuevo.anio'         => 'required|numeric',
            'nuevo.hora_catedra' => 'required|numeric',
            'nuevo.periodo_id'   => 'required',
            'nuevo.turno_id'     => 'required',
        ];
    }
   
};
?>

<div class="p-4 rounded-xl shadow" style="background-color: white;">

    <h1 class="text-2xl font-bold mb-4">
        CRUD Rel Carrera Espacio
    </h1>

    @if(session()->has('success'))
        <div class=" px-4 py-2 rounded mb-4" style="background-color: #d1fae5; color: #065f46; text-align: center; font-weight: bold;">
            {{ session('success') }}
        </div>
    @endif

    {{-- FILTROS --}}
    <div class="grid md:grid-cols-3 gap-4 mb-4">

        <input
            wire:model.live.debounce.400ms="buscarCarrera"
            placeholder="Buscar carrera..."
            class="border rounded px-3 py-2">

        <input
            wire:model.live.debounce.400ms="buscarEspacio"
            placeholder="Buscar espacio..."
            class="border rounded px-3 py-2">

        <button
            wire:click="abrirModal"
            class="rounded px-4 py-2"
            style="background-color: #16a34a; color:white; font-weight: bold;">
            + Nuevo
        </button>

    </div>

    {{-- TABLA --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">

        <table class="min-w-full table-auto text-sm border-collapse">

            <thead style="background-color: blue; color: white;">
                <tr>
                    <th class="p-2 w-56 text-left">Carrera</th>
                    <th class="p-2 w-56 text-left">Espacio</th>
                    <th class="p-2 w-20 text-left">Año</th>
                    <th class="p-2 w-20 text-left">Hs</th>
                    <th class="p-2 w-36 text-left">Periodo</th>
                    <th class="p-2 w-36 text-left">Turno</th>
                    <th class="p-2 w-24 text-left">Acciones</th>
                </tr>
            </thead>

            <tbody>

                @foreach($rows as $index => $row)

                <tr class="border-b">

                   <td class="p-2" style="width:220px; min-width:220px;">
                        <select
                            wire:model.live="rows.{{ $index }}.carrera_id"
                            class="border rounded px-2 py-1 w-full">

                            @foreach($carreras as $c)
                                <option value="{{ $c['id'] }}">
                                    {{ $c['nombre'] }}
                                </option>
                            @endforeach

                        </select>
                    </td>
                   <td class="p-2" style="width:220px; min-width:220px;">
                        <select
                            wire:model.live="rows.{{ $index }}.espacio_id"
                            class="border rounded px-2 py-1 w-full">

                            @foreach($espacios as $e)
                                <option value="{{ $e['idespaciocurricular'] }}">
                                    {{ $e['nombre_espacio'] }}
                                </option>
                            @endforeach

                        </select>
                    </td>

                    <td class="p-2">
                        <input wire:model.live="rows.{{ $index }}.anio"
                            class="border rounded px-2 py-1 w-20">
                            @isset($erroresFila[$index]['anio'])
                                <div class="text-red-600 text-xs">
                                    {{ $erroresFila[$index]['anio'] }}
                                </div>
                            @endisset
                    </td>

                    <td class="p-2" style="width:20px; min-width:20px;">
                        <input wire:model.live="rows.{{ $index }}.hora_catedra"
                            class="border rounded px-2 py-1 w-20">
                            @isset($erroresFila[$index]['hora_catedra'])
                                <div class="text-red-600 text-xs">
                                    {{ $erroresFila[$index]['hora_catedra'] }}
                                </div>
                            @endisset
                    </td>

                    <td class="p-2">
                        <select wire:model.live="rows.{{ $index }}.periodo_id"
                            class="border rounded px-2 py-1">

                            @foreach($periodos as $p)
                                <option value="{{ $p['idtb_periodo_cursado'] }}">
                                    {{ $p['nombre_periodo'] }}
                                </option>
                            @endforeach                    
                        </select>
                        @isset($erroresFila[$index]['periodo_id'])
                            <div class="text-red-600 text-xs">
                                {{ $erroresFila[$index]['periodo_id'] }}
                            </div>
                        @endisset
                    </td>

                    <td class="p-2">
                        <select wire:model.live="rows.{{ $index }}.turno_id"
                            class="border rounded px-2 py-1">

                            @foreach($turnos as $t)
                                <option value="{{ $t['id'] }}">
                                    {{ $t['nombre_turno'] }}
                                </option>
                            @endforeach
                        </select>
                        @isset($erroresFila[$index]['turno_id'])
                            <div class="text-red-600 text-xs">
                                {{ $erroresFila[$index]['turno_id'] }}
                            </div>
                        @endisset
                    </td>

                    <td class="p-2 flex gap-2">

                        <button
                            wire:click="guardarFila({{ $index }})"
                            class="px-3 py-1 rounded"
                            style="background-color: blue; color: white; font-weight: bold;">
                            Guardar
                        </button>

                        <button
                            onclick="confirm('¿Eliminar?') || event.stopImmediatePropagation()"
                            wire:click="eliminar({{ $row['id'] }})"
                            class="px-3 py-1 rounded"
                            style="background-color: red; color: white; font-weight: bold;">
                            Eliminar
                        </button>

                    </td>

                </tr>

                @endforeach

            </tbody>

        </table>

    </div>

    {{-- MODAL NUEVO --}}
    @if($mostrarModal)

  <div class="fixed inset-0 z-9999 flex items-center justify-center px-2"
            style="background:rgba(0,0,0,.45);">

       <div class="bg-white rounded-xl shadow-xl w-full  max-h-[85vh] overflow-y-auto p-4" style="max-width: 65%;">

            <h2 class="text-xl font-bold mb-4"  style="background-color: white; color: black;">
                Nuevo Registro
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 w-full" style="background-color: #f3f4f6; color: black;">

                <select wire:model="nuevo.carrera_id" class="border rounded px-3 py-2 w-full">
                    <option value="">Carrera</option>
                    @foreach($carreras as $c)
                        <option value="{{ $c['id'] }}">{{ $c['nombre'] }}</option>
                    @endforeach
                </select>
                @error('nuevo.carrera_id')
                    <div class="text-red-600 text-xs">{{ $message }}</div>
                @enderror

                <select wire:model="nuevo.espacio_id" class="border rounded px-3 py-2 w-full">
                    <option value="">Espacio</option>
                    @foreach($espacios as $e)
                        <option value="{{ $e['idespaciocurricular'] }}">{{ $e['nombre_espacio'] }}</option>
                    @endforeach
                </select>
                @error('nuevo.espacio_id')
                    <div class="text-red-600 text-xs">{{ $message }}</div>
                @enderror

                <select wire:model="nuevo.perfil_id" class="border rounded px-3 py-2 w-full wrap-break-word text-shadow-mauve-600">
                    <option value="">Perfil</option>
                    @foreach($perfiles as $p)
                        <option value="{{ $p['idtb_perfil'] }}">{{ $p['nombre_perfil'] }}</option>
                    @endforeach
                </select>
                @error('nuevo.perfil_id')
                    <div class="text-white-600 text-xs">{{ $message }}</div>
                @enderror

                <input wire:model="nuevo.anio"
                    placeholder="Año"
                    class="border rounded px-3 py-2 w-full">
                    @error('nuevo.anio')
                        <div class="text-red-600 text-xs">{{ $message }}</div>
                    @enderror
                <input wire:model="nuevo.hora_catedra"
                    placeholder="Hs Cátedra"
                    class="border rounded px-3 py-2 w-full">
                    @error('nuevo.hora_catedra')
                        <div class="text-red-600 text-xs">{{ $message }}</div>
                    @enderror
                <select wire:model="nuevo.periodo_id" class="border rounded px-3 py-2 w-full">
                    <option value="">Periodo</option>
                    @foreach($periodos as $p)
                        <option value="{{ $p['idtb_periodo_cursado'] }}">{{ $p['nombre_periodo'] }}</option>
                    @endforeach
                </select>

                <select wire:model="nuevo.turno_id" class="border rounded px-3 py-2 w-full">
                    <option value="">Turno</option>
                    @foreach($turnos as $t)
                        <option value="{{ $t['id'] }}">{{ $t['nombre_turno'] }}</option>
                    @endforeach
                </select>
                @error('nuevo.turno_id')
                    <div class="text-red-600 text-xs">{{ $message }}</div>
                @enderror

            </div>

            <div class="flex justify-end gap-3 mt-6">

                <button
                    wire:click="cerrarModal"
                    class="px-4 py-2 rounded"
                    style="background-color: grey; color: black; font-weight: bold;">
                    Cancelar
                </button>

                <button
                    wire:click="guardarNuevo"
                    class="px-4 py-2 rounded"
                    style="background-color: #3b82f6; color: white; font-weight: bold;">
                    Guardar
                </button>

            </div>

        </div>

    </div>

    @endif

</div>