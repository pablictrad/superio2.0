<?php

use Livewire\Component;
use App\Models\Zona;
use App\Models\Instituto;
use App\Models\Carrera;
use App\Models\Llamado;
use App\Models\CargoPorLlamado;
use App\Models\Cargo;
use App\Models\Situacion_revista;
use App\Models\Turno;
use App\Models\Estado;

new class extends Component
{
    public $llamadoId;

    public $zonas=[];
    public $items=[];

    public function mount($id)
    {
        $this->llamadoId=$id;
        $this->zonas=Zona::orderBy('nombre')->get();
      
        $registros=CargoPorLlamado::where('llamado_id',$id)->get();

        foreach($registros as $registro)
        {
           $institutos=Instituto::where('zona_id',$registro->zona_id)
           ->orderBy('nombre')
           ->get()
           ->toArray();

           $carreras=Carrera::where('instituto_id',$registro->instituto_id)
           ->orderBy('nombre')
           ->get()
           ->toArray();

           $this->items[]=[
             'id' => $registro->id,
             'zona_id' => $registro->zona_id,
             'instituto_id' => $registro->instituto_id,
             'carrera_id' => $registro->carrera_id,
             'horas' => $registro->horas,
             'institutos' => $institutos,
             'carreras' => $carreras,
             'cargo_id' => $registro->cargo_id,
             'cargos' => $cargos,
             ];
       }

       if(count($this->items)===0)
       {
           $this->agregar();
       }

    }
    public function agregar()
    {
         $this->items[]=[
            
             'zona_id' => '',
             'instituto_id' => '',
             'carrera_id' => '',
             'horas' => '',
             'institutos' => [],
             'carreras' => [],
            
         ];
    }
    
     public function eliminar($index)
     {
          if($this->items[$index]['id'])
          {
              CargoPorLlamado::find($this->items[$index]['id'])?->delete();
          }

          unset($this->items[$index]);
          $this->items=array_values($this->items);
     }

    public function updatedItems($value, $key)
    {
        //ejemplo: key: 0.zona_id
        $partes=explode('.',$key);

        $fila=$partes[0]; //0
        $campo=$partes[1]; //zona_id

       if($campo==='zona_id')
        {
            $this->items[$fila]['institutos']=
            Instituto::where('zona_id',$value)
            ->orderBy('nombre')
            ->get()
            ->toArray();

            $this->items[$fila]['instituto_id']='';
            $this->items[$fila]['carrera_id']='';
            $this->items[$fila]['carreras']=[];
      }    

        if($campo==='instituto_id')
        {
            $this->items[$fila]['carreras']=
            Carrera::where('instituto_id',$value)
            ->orderBy('nombre')
            ->get()
            ->toArray();

            $this->items[$fila]['carrera_id']='';
        }
    }
     
    public function guardar()
    {
        $this->validate([
             'items.*.zona_id' => 'required',
             'items.*.instituto_id' => 'required',
             'items.*.carrera_id' => 'required',
             'items.*.horas' => 'required|numeric',
            
         ]);
        foreach($this->items as $item)
        {
            if($item['id'])
            {
               CargoPorLlamado::where('id',$item['id'])
               ->update([
                   'llamado_id' => $item['llamado_id'],
                     'cargo_id' => $item['cargo_id'],
                     'turno_id' => $item['turno_id'],
                        'horas' => $item['horas']
               ]);
            }
            else{
                CargoPorLlamado::create([
                    'llamado_id' => $this->llamadoId,
                    'cargo_id' => $item['cargo_id'],
                    'turno_id' => $item['turno_id'],
                    'horas' => $item['horas']
                ]);
            }
        }

         session()->flash('mensaje','Datos guardados correctamente');
    }
};

?>

<div class="p-6">
    <h1 class="text-2xl font-bold mb-4">Llamados</h1>

    @foreach($items as $index => $item)
        <div style="border:1px solid #ccc; padding:10px; margin-bottom:10px;">

           <h3>Bloque {{ $index + 1 }}</h3>

           {{-- Zona --}}
           <select wire:model.live="items.{{ $index }}.zona_id" class="border-gray-300 rounded-md shadow-sm">
                <option value="">Seleccione Zona</option>
                @foreach($zonas as $zona)
                     <option value="{{ $zona->id }}" >{{ $zona->nombre }}</option>
                @endforeach
            </select>
            
            {{-- Instituto --}} 
            <select wire:model.live="items.{{ $index }}.instituto_id" class="border-gray-300 rounded-md shadow-sm">
                <option value="">Seleccione Instituto</option>
                @foreach($item['institutos'] as $instituto)
                     <option value="{{ $instituto['id'] }}" >{{ $instituto['nombre'] }}</option>
                @endforeach
            </select>

            {{-- Carrera --}}
            <select wire:model.live="items.{{ $index }}.carrera_id" class="border-gray-300 rounded-md shadow-sm">
                <option value="">Seleccione Carrera</option>
                @foreach($item['carreras'] as $carrera)
                     <option value="{{ $carrera['id'] }}" >{{ $carrera['nombre'] }}</option>
                @endforeach
            </select>   

            {{-- Horas --}}
            <input type="number" wire:model.live="items.{{ $index }}.horas" class="border-gray-300 rounded-md shadow-sm" placeholder="Horas">   

            {{-- Eliminar --}}
            <button wire:click="eliminar({{ $index }})" class="bg-red-500 text-white px-2 py-1 rounded-md">Eliminar</button>

        </div>
    @endforeach
    <button wire:click="agregar" class="bg-blue-500 text-white px-4 py-2 rounded-md">Agregar Bloque</button>
    <button wire:click="guardar" class="bg-green-500 text-white px-4 py-2 rounded-md">Guardar</button>  
    
</div>