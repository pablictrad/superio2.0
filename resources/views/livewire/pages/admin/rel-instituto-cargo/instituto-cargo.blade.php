<?php
use Livewire\Attributes\Title;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\RelInstitutoCargo;
use App\Models\Instituto;
use App\Models\Cargo;
use App\Models\Turno;
use App\Models\Perfil;

new #[Title('Relación Instituto - Cargo')] class extends Component
{
    use WithPagination;

    public $buscarInstituto = '';
    public $buscarCargo     = '';

    public $institutos = [];
    public $cargos     = [];
    public $turnos     = [];
    public $perfiles   = [];

    // Nuevo registro
    public $newInstitutoId = '';
    public $newInstitutoNombre = '';
    public $newCargoId     = '';
    public $newTurnoId     = '';
    public $newPerfilId    = '';

    // Edición
    public $editId         = null;
    public $editInstitutoId = '';
    public $editInstitutoNombre = '';
    public $editCargoId    = '';
    public $editTurnoId    = '';
    public $editPerfilId   = '';
    
    public function mount()
    {
        $this->institutos = Instituto::orderBy('nombre')->get()->toArray();
        $this->cargos     = Cargo::orderBy('nombre_cargo')->get()->toArray();
        $this->turnos     = Turno::orderBy('nombre_turno')->get()->toArray();
        $this->perfiles   = Perfil::orderBy('nombre_perfil')->get()->toArray();
    }

    public function updatedBuscarInstituto() { $this->resetPage(); }
    public function updatedBuscarCargo()     { $this->resetPage(); }

    public function getRowsProperty()
    {
        $query = RelInstitutoCargo::query()
            ->with(['instituto', 'cargo', 'turno', 'perfil']);

        if ($this->buscarInstituto) {
            $query->whereHas('instituto', fn($q) =>
                $q->where('nombre', 'like', '%' . $this->buscarInstituto . '%'));
        }

        if ($this->buscarCargo) {
            $query->whereHas('cargo', fn($q) =>
                $q->where('nombre_cargo', 'like', '%' . $this->buscarCargo . '%'));
        }

        return $query->paginate(10);
    }
    public function formatPerfil($texto): string
    {
        if (!$texto) return '';
        return trim(str_replace(['/', ';'], "\n", $texto));
    }

    public function getPerfilTexto($id): string
    {
        if (!$id) return 'Sin perfil configurado';
        foreach ($this->perfiles as $p) {
            if ($p['idtb_perfil'] == $id) return $this->formatPerfil($p['nombre_perfil']);
        }
        return 'Sin perfil configurado';
    }
    public function editar($id)
    {
        $rel = RelInstitutoCargo::findOrFail($id);
        $this->editId          = $rel->id;
        $this->editInstitutoId = $rel->instituto_superior_id;
        $this->editInstitutoNombre = $rel->instituto->nombre ?? '-';
        $this->editCargoId     = $rel->cargo_id;
        $this->editTurnoId     = $rel->turno_id;
        $this->editPerfilId    = $rel->perfil_id;

        $this->dispatch('modal-show', name: 'edit-modal');
    }

    public function guardarEdicion()
    {
        $this->validate([
            'editInstitutoId' => 'required',
            'editCargoId'     => 'required',
        ]);

        RelInstitutoCargo::find($this->editId)->update([
            'instituto_superior_id' => $this->editInstitutoId,
            'cargo_id'              => $this->editCargoId,
            'turno_id'              => $this->editTurnoId  ?: null,
            'perfil_id'             => $this->editPerfilId ?: null,
        ]);

        $this->dispatch('modal-close', name: 'edit-modal');
        $this->dispatch('toast', variant: 'success', heading: 'Éxito', text: 'Registro actualizado.');
    }

    public function guardarNuevo()
    {
        $this->validate([
            'newInstitutoId' => 'required',
            'newCargoId'     => 'required',
        ]);

        RelInstitutoCargo::create([
            'instituto_superior_id' => $this->newInstitutoId,
            'cargo_id'              => $this->newCargoId,
            'turno_id'              => $this->newTurnoId  ?: null,
            'perfil_id'             => $this->newPerfilId ?: null,
        ]);

        $this->resetNewRecord();
        $this->dispatch('modal-close', name: 'create-modal');
        $this->dispatch('toast', variant: 'success', heading: 'Éxito', text: 'Nuevo registro creado.');
    }
    #[On('perfilSeleccionado')]
    public function recibirPerfil($id): void
    {
        if ($this->editId) {
            $this->editPerfilId = $id;
        } else {
            $this->newPerfilId = $id;
        }
    }

    public function eliminar($id)
    {
        RelInstitutoCargo::findOrFail($id)->delete();
        $this->dispatch('toast', variant: 'success', heading: 'Éxito', text: 'Registro eliminado.');
    }

    public function resetNewRecord()
    {
        $this->newInstitutoId = '';
        $this->newCargoId     = '';
        $this->newTurnoId     = '';
        $this->newPerfilId    = '';
    }
};
?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl" level="1" class="!text-zinc-900 dark:!text-white font-bold">
            Relación Instituto - Cargo
        </flux:heading>

        <flux:modal.trigger name="create-modal">
            <flux:button variant="primary" icon="plus" wire:click="resetNewRecord">
                Nuevo Registro
            </flux:button>
        </flux:modal.trigger>
    </div>

    {{-- FILTROS --}}
    <flux:card>
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            <flux:field>
                <flux:label class="!text-zinc-900 font-bold">Buscar Instituto</flux:label>
                <flux:input wire:model.live.debounce.400ms="buscarInstituto" icon="magnifying-glass" size="sm" />
            </flux:field>

            <flux:field>
                <flux:label class="!text-zinc-900 font-bold">Buscar Cargo</flux:label>
                <flux:input wire:model.live.debounce.400ms="buscarCargo" icon="magnifying-glass" size="sm" />
            </flux:field>
        </div>
    </flux:card>

    {{-- TABLA --}}
    <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900 shadow-sm">
        <flux:table :paginate="$this->rows">
            <flux:table.columns>
                <flux:table.column class="!text-zinc-900 font-bold">Instituto</flux:table.column>
                <flux:table.column class="!text-zinc-900 font-bold">Cargo</flux:table.column>
                <flux:table.column class="!text-zinc-900 font-bold">Horas</flux:table.column>
                <flux:table.column class="!text-zinc-900 font-bold">Perfil</flux:table.column>
                <flux:table.column class="!text-zinc-900 font-bold">Turno</flux:table.column>
                <flux:table.column class="w-16 text-center"></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach($this->rows as $row)
                    <flux:table.row :wire:key="'row-'.$row->id">
                        <flux:table.cell class="text-sm !text-zinc-900 font-medium">
                            {{ $row->instituto->nombre ?? '-' }}
                        </flux:table.cell>

                        <flux:table.cell class="text-sm !text-zinc-900 font-bold">
                            {{ $row->cargo->nombre_cargo ?? '-' }}
                        </flux:table.cell>
                         <flux:table.cell class="text-sm !text-zinc-900 font-bold">
                            {{ $row->cargo->hora_catedra ?? '-' }}
                        </flux:table.cell>
                         <flux:table.cell class="text-sm !text-zinc-900 font-bold">
                             @if($row->perfil_id)
                                <flux:badge variant="solid" color="white" size="sm" class="font-mono !text-white bg-zinc-900">#{{ $row->perfil_id }}</flux:badge>
                            @else
                                <span class="text-zinc-400 text-[10px] italic">Sin ID</span>
                            @endif
                        </flux:table.cell>
                         <flux:table.cell class="text-sm !text-zinc-900 font-bold">
                               {{ $row->turno->nombre_turno ?? '-' }}
                        </flux:table.cell>
                        <flux:table.cell class="text-center">
                            <div class="flex justify-center gap-1">
                                <flux:button
                                    wire:click="editar({{ $row->id }})"
                                    variant="ghost" size="xs" icon="pencil-square"
                                    class="!text-zinc-900"
                                />
                                <flux:button
                                    wire:click="eliminar({{ $row->id }})"
                                    wire:confirm="¿Eliminar este registro?"
                                    variant="ghost" size="xs" icon="trash"
                                    class="!text-red-500"
                                />
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </div>

    {{-- MODAL NUEVO --}}
    <flux:modal name="create-modal" class="md:w-[500px]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Nuevo Registro</flux:heading>
                <flux:subheading>Relación Instituto - Cargo</flux:subheading>
            </div>

            <flux:field>
                <flux:label>Instituto</flux:label>
                <flux:select wire:model="newInstitutoId" searchable size="sm">
                    <option value="">Seleccione...</option>
                    @foreach($institutos as $i)
                        <option value="{{ $i['id'] }}">{{ $i['nombre'] }}</option>
                    @endforeach
                </flux:select>
                <flux:error name="newInstitutoId" />
            </flux:field>

            <flux:field>
                <flux:label>Cargo</flux:label>
                <flux:select wire:model="newCargoId" searchable size="sm">
                    <option value="">Seleccione...</option>
                    @foreach($cargos as $c)
                        <option value="{{ $c['id'] }}">{{ $c['nombre_cargo'] }}</option>
                    @endforeach
                </flux:select>
                <flux:error name="newCargoId" />
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

             <flux:field>
                 <livewire:perfil />
                 <div class="mt-4">
                     <div class="w-full text-xs">
                         {{ $this->getPerfilTexto($newPerfilId) }}
                     </div>
                 </div>
             </flux:field>
            <div class="flex justify-end gap-2 pt-4 border-t border-zinc-100 dark:border-zinc-800">
                <flux:modal.close>
                    <flux:button variant="ghost" size="sm">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" wire:click="guardarNuevo" size="sm">
                    Guardar Registro
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- MODAL EDICIÓN --}}
    <flux:modal name="edit-modal" class="md:w-[500px]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Editar Registro</flux:heading>
                <flux:subheading>Actualizar detalles</flux:subheading>
            </div>

            <flux:field>
                <flux:label>Instituto</flux:label>
                   <div class="rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm font-medium !text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800">
                    {{ $editInstitutoNombre }}
                </div>
            </flux:field>

            <flux:field>
                <flux:label>Cargo</flux:label>
                <flux:select wire:model="editCargoId" searchable size="sm">
                    <option value="">Seleccione...</option>
                    @foreach($cargos as $c)
                        <option value="{{ $c['id'] }}">{{ $c['nombre_cargo'] }}</option>
                    @endforeach
                </flux:select>
                <flux:error name="editCargoId" />
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

             <flux:field>
                 <livewire:perfil />
                 <div class="mt-4">
                     <div class="w-full text-xs">
                         {{ $this->getPerfilTexto($editPerfilId) }}
                     </div>
                 </div>
             </flux:field>
            <div class="flex justify-end gap-2 pt-4 border-t border-zinc-100 dark:border-zinc-800">
                <flux:modal.close>
                    <flux:button variant="ghost" size="sm">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" wire:click="guardarEdicion" size="sm">
                    Guardar Cambios
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
