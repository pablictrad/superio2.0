<?php
use Livewire\Volt\Component;
use App\Models\RelCarreraCargo;
use App\Models\Turno;
use App\Models\Periodo;
use App\Models\Perfil;
use App\Models\Cargo;
use App\Models\Carrera;
use Livewire\Attributes\Title;
use Livewire\WithPagination;
use Livewire\Attributes\On;

new #[Title('Relación Carrera - Cargo')] class extends Component
{
    use WithPagination;

    public $buscarCarrera = '';
    public $buscarCargo = '';
    public $buscarPerfil = '';

    public $turnos = [];
    public $periodos = [];
    public $carreras = [];
    public $cargos = [];
    public $perfiles = [];

    // Propiedades para nuevo registro
    public $newCarreraId = '';
    public $newCargoId = '';
    public $newAnio = '';
    public $newHoraCatedra = '';
    public $newPeriodoId = '';
    public $newTurnoId = '';
    public $newPerfilId = '';

    // Propiedades para edición
    public $editId = null;
    public $editCarreraId = '';
    public $editCargoId = '';
    public $editAnio = '';
    public $editHoraCatedra = '';
    public $editPeriodoId = '';
    public $editTurnoId = '';
    public $editPerfilId = '';

    public function mount()
    {
        $this->turnos = Turno::orderBy('nombre_turno')->get()->toArray();
        $this->periodos = Periodo::orderBy('nombre_periodo')->get()->toArray();
        $this->carreras = Carrera::orderBy('nombre')->get()->toArray();
        $this->cargos = Cargo::orderBy('nombre_cargo')->get()->toArray();
        $this->perfiles = Perfil::orderBy('nombre_perfil')->get()->toArray();
    }

    public function updatedBuscarCarrera() { $this->resetPage(); }
    public function updatedBuscarCargo() { $this->resetPage(); }
    public function updatedBuscarPerfil() { $this->resetPage(); }

    public function getRowsProperty()
    {
        $query = RelCarreraCargo::query()
            ->with(['carrera', 'cargo', 'perfil', 'periodo', 'turno']);

        if ($this->buscarCarrera) {
            $query->whereHas('carrera', fn($q) =>
                $q->where('nombre', 'like', '%' . $this->buscarCarrera . '%'));
        }

        if ($this->buscarCargo) {
            $query->whereHas('cargo', fn($q) =>
                $q->where('nombre_cargo', 'like', '%' . $this->buscarCargo . '%'));
        }

        if ($this->buscarPerfil) {
            $query->whereHas('perfil', fn($q) =>
                $q->where('nombre_perfil', 'like', '%' . $this->buscarPerfil . '%'));
        }

        return $query->paginate(10);
    }

    public function formatPerfil($texto)
    {
        if (!$texto) return '';
        $formatted = str_replace(['/', ';'], "\n", $texto);
        return trim($formatted);
    }

    public function getPerfilTexto($id)
    {
        if (!$id) return 'Sin perfil configurado';
        foreach ($this->perfiles as $p) {
            if ($p['idtb_perfil'] == $id) return $this->formatPerfil($p['nombre_perfil']);
        }
        return 'Sin perfil configurado';
    }

    public function editar($id)
    {
        $rel = RelCarreraCargo::findOrFail($id);
        $this->editId = $rel->id;
        $this->editCarreraId = $rel->carrera_id;
        $this->editCargoId = $rel->cargo_id;
        $this->editAnio = $rel->anio;
        $this->editHoraCatedra = $rel->hora_catedra;
        $this->editPeriodoId = $rel->periodo_id;
        $this->editTurnoId = $rel->turno_id;
        $this->editPerfilId = $rel->perfil_id;

        $this->dispatch('modal-show', name: 'edit-modal');
    }

    public function guardarEdicion()
    {
        $this->validate([
            'editCarreraId' => 'required',
            'editCargoId' => 'required',
            'editAnio' => 'required',
            'editHoraCatedra' => 'required',
        ]);

        RelCarreraCargo::find($this->editId)->update([
            'carrera_id' => $this->editCarreraId,
            'cargo_id' => $this->editCargoId,
            'anio' => $this->editAnio,
            'hora_catedra' => $this->editHoraCatedra,
            'periodo_id' => $this->editPeriodoId ?: null,
            'turno_id' => $this->editTurnoId ?: null,
            'perfil_id' => $this->editPerfilId ?: null,
        ]);

        $this->dispatch('modal-close', name: 'edit-modal');
        $this->dispatch('toast', variant: 'success', heading: 'Éxito', text: 'Registro actualizado.');
    }

    public function guardarNuevo()
    {
        $this->validate([
            'newCarreraId' => 'required',
            'newCargoId' => 'required',
            'newAnio' => 'required',
            'newHoraCatedra' => 'required',
        ]);

        RelCarreraCargo::create([
            'carrera_id' => $this->newCarreraId,
            'cargo_id' => $this->newCargoId,
            'anio' => $this->newAnio,
            'hora_catedra' => $this->newHoraCatedra,
            'periodo_id' => $this->newPeriodoId ?: null,
            'turno_id' => $this->newTurnoId ?: null,
            'perfil_id' => $this->newPerfilId ?: null,
        ]);

        $this->resetNewRecord();
        $this->dispatch('modal-close', name: 'create-modal');
        $this->dispatch('toast', variant: 'success', heading: 'Éxito', text: 'Nuevo registro creado.');
    }
    #[On('perfilSeleccionado')]
    public function recibirPerfil($id)
    {
        if ($this->editId) {
            $this->editPerfilId = $id;
        } else {
            $this->newPerfilId = $id;
        }
}
    public function resetNewRecord()
    {
        $this->newCarreraId = '';
        $this->newCargoId = '';
        $this->newAnio = '';
        $this->newHoraCatedra = '';
        $this->newPeriodoId = '';
        $this->newTurnoId = '';
        $this->newPerfilId = '';
    }
};
?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl" level="1" class="!text-zinc-900 dark:!text-white font-bold">Relación Carrera - Cargo</flux:heading>
        
        <flux:modal.trigger name="create-modal">
            <flux:button variant="primary" icon="plus" wire:click="resetNewRecord">Nuevo Registro</flux:button>
        </flux:modal.trigger>
    </div>

    <flux:card>
        <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
            <flux:field>
                <flux:label class="!text-zinc-900 font-bold">Buscar Carrera</flux:label>
                <flux:input wire:model.live.debounce.400ms="buscarCarrera" icon="magnifying-glass" size="sm" />
            </flux:field>

            <flux:field>
                <flux:label class="!text-zinc-900 font-bold">Buscar Cargo</flux:label>
                <flux:input wire:model.live.debounce.400ms="buscarCargo" icon="magnifying-glass" size="sm" />
            </flux:field>
           
        </div>
    </flux:card>

    <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900 shadow-sm">
        <flux:table :paginate="$this->rows">
            <flux:table.columns>
                <flux:table.column class="w-1/4 !text-zinc-900 font-bold">Carrera</flux:table.column>
                <flux:table.column class="w-1/4 !text-zinc-900 font-bold">Cargo</flux:table.column>
                
                <flux:table.column class="w-16 text-center !text-zinc-900 font-bold">Perfil ID</flux:table.column>
                <flux:table.column class="w-16 text-center !text-zinc-900 font-bold">Hs</flux:table.column>
               
                <flux:table.column class="w-16 !text-zinc-900 font-bold">Turno</flux:table.column>
                <flux:table.column class="w-16 text-center"></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach($this->rows as $row)
                    <flux:table.row :wire:key="'row-'.$row->id">
                        <flux:table.cell class="text-sm !text-zinc-900 font-medium truncate whitespace-normal break-all">
                            {{ $row->carrera->nombre ?? '' }}
                        </flux:table.cell>

                        <flux:table.cell class="text-sm !text-zinc-900 font-bold truncate whitespace-normal break-all">
                            {{ $row->cargo->nombre_cargo ?? '' }}
                        </flux:table.cell>

                       

                        <flux:table.cell class="text-center">
                            @if($row->perfil_id)
                                <flux:badge variant="solid" color="zinc" size="sm" class="font-mono !text-white-900 bg-zinc-100">#{{ $row->perfil_id }}</flux:badge>
                            @else
                                <span class="text-zinc-400 text-sm italic">Sin ID</span>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell class="text-center text-sm !text-zinc-900">
                            {{ $row->hora_catedra }}
                        </flux:table.cell>

                       
                        <flux:table.cell class="text-sm !text-zinc-900">
                            {{ $row->turno->nombre_turno ?? '-' }}
                        </flux:table.cell>

                        <flux:table.cell class="text-center">
                            <flux:button wire:click="editar({{ $row->id }})" variant="ghost" size="sm" icon="pencil-square" class="!text-zinc-900" />
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </div>

    {{-- MODAL PARA NUEVO REGISTRO --}}
    <flux:modal name="create-modal" class="md:w-[800px]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Nuevo Registro</flux:heading>
                <flux:subheading>Relación Carrera - Cargo</flux:subheading>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <flux:field>
                    <flux:label>Carrera</flux:label>
                    <flux:select wire:model="newCarreraId" searchable size="sm">
                        <option value="">Seleccione...</option>
                        @foreach($carreras as $c)
                            <option value="{{ $c['id'] }}">{{ $c['nombre'] }}</option>
                        @endforeach
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>Cargo</flux:label>
                    <flux:select wire:model="newCargoId" searchable size="sm">
                        <option value="">Seleccione...</option>
                        @foreach($cargos as $ca)
                            <option value="{{ $ca['id'] }}">{{ $ca['nombre_cargo'] }}</option>
                        @endforeach
                    </flux:select>
                </flux:field>

                

                <flux:field>
                    <flux:label>Horas</flux:label>
                    <flux:input wire:model="newHoraCatedra" type="number" size="sm" />
                </flux:field>

                

                <flux:field>
                    <flux:label>Turno</flux:label>
                    <flux:select wire:model="newTurnoId" size="sm">
                        <option value="">Seleccione...</option>
                        @foreach($turnos as $t)
                            <option value="{{ $t['id'] }}">{{ $t['nombre_turno'] }}</option>
                        @endforeach
                    </flux:select>
                </flux:field>
            </div>

             <flux:field>
                 <livewire:perfil />

                <div class="mt-4">
                    <div class="w-full text-xs ...">
                        {{ $this->getPerfilTexto($editPerfilId) }}
                    </div>
                </div>
            </flux:field>

            <div class="flex justify-end gap-2 pt-6 border-t border-zinc-100 dark:border-zinc-800">
                <flux:modal.close>
                    <flux:button variant="ghost" size="sm">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" wire:click="guardarNuevo" size="sm">Guardar Registro</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- MODAL PARA EDICION --}}
    <flux:modal name="edit-modal" class="md:w-[800px]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Editar Registro</flux:heading>
                <flux:subheading>Actualizar detalles</flux:subheading>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <flux:field>
                    <flux:label>Carrera</flux:label>
                    <flux:select wire:model="editCarreraId" searchable size="sm">
                        @foreach($carreras as $c)
                            <option value="{{ $c['id'] }}">{{ $c['nombre'] }}</option>
                        @endforeach
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>Cargo</flux:label>
                    <flux:select wire:model="editCargoId" searchable size="sm">
                        @foreach($cargos as $ca)
                            <option value="{{ $ca['id'] }}">{{ $ca['nombre_cargo'] }}</option>
                        @endforeach
                    </flux:select>
                </flux:field>

               

                <flux:field>
                    <flux:label>Horas</flux:label>
                    <flux:input wire:model="editHoraCatedra" type="number" size="sm" />
                </flux:field>

               
                <flux:field>
                    <flux:label>Turno</flux:label>
                    <flux:select wire:model="editTurnoId" size="sm">
                        <option value="">Seleccione...</option>
                        @foreach($turnos as $t)
                            <option value="{{ $t['id'] }}">{{ $t['nombre_turno'] }}</option>
                        @endforeach
                    </flux:select>
                </flux:field>
            </div>
            <flux:field>
              <livewire:perfil />


                <div class="mt-4">
                    <div class="w-full text-xs ...">
                        {{ $this->getPerfilTexto($editPerfilId) }}
                    </div>
                </div>
            </flux:field>

            <div class="flex justify-end gap-2 pt-6 border-t border-zinc-100 dark:border-zinc-800">
                <flux:modal.close>
                    <flux:button variant="ghost" size="sm">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" wire:click="guardarEdicion" size="sm">Guardar Cambios</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
