<?php

use Livewire\Component;
use App\Models\Zona;
use App\Models\Instituto;
use App\Models\Carrera;

new class extends Component
{
    public $zonas=[];
    public $institutos=[];
    public $carreras=[];

    public $zona_id = '';
    public $instituto_id = '';
    public $carrera_id = '';

    public function mount()
    {
        $this->zonas = Zona::orderBy('nombre')->get();
       
    }

    public function updatedZonaId($value)
    {
        $this->institutos = Instituto::where('zona_id', $value)->orderBy('nombre')->get();

        // Limpiar las selecciones anteriores
        $this->instituto_id = '';
        $this->carreras = [];
        $this->carrera_id = '';
    }

    public function updatedInstitutoId($value)
    {
        $this->carreras = Carrera::where('instituto_id', $value)->get();

        // Limpiar la selección anterior
        $this->carrera_id = '';
    }       
    
};
?>


<div class="p-4">
    <h1 class="text-2xl font-bold mb-4">Seleccione Dependientes</h1>
    
    {{--ZONA --}}
    <select wire:model.live="zona_id">
        <option value="">Seleccione Zona</option>
        @foreach($zonas as $zona)
            <option value="{{ $zona->id }}">{{ $zona->nombre }}</option>
        @endforeach
    </select>

    {{-- INSTITUTO --}}
    <select wire:model.live="instituto_id">
        <option value="">Seleccione Instituto</option>
        @foreach($institutos as $instituto)
            <option value="{{ $instituto->id }}">{{ $instituto->nombre }}</option>
        @endforeach
    </select>

    {{-- CARRERA --}}
    <select wire:model.live="carrera_id">
        <option value="">Seleccione Carrera</option>
        @foreach($carreras as $carrera)
            <option value="{{ $carrera->id }}">{{ $carrera->nombre }}</option>
        @endforeach
    </select>
</div>