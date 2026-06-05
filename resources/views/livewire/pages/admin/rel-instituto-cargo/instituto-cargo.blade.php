<?php
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\RelInstitutoCargo;
use App\Models\Instituto;
use App\Models\Cargo;

new #[Title('Relación Instituto - Cargo')] class extends Component
{
    use WithPagination;

    public $buscarInstituto = '';
    public $buscarCargo     = '';

    public $institutos = [];
    public $cargos     = [];

    // Nuevo registro
    public $newInstitutoId = '';
    public $newCargoId     = '';

    // Edición
    public $editId         = null;
    public $editInstitutoId = '';
    public $editCargoId    = '';

    public function mount()
    {
        $this->institutos = Instituto::orderBy('nombre')->get()->toArray();
        $this->cargos     = Cargo::orderBy('nombre_cargo')->get()->toArray();
    }

    public function updatedBuscarInstituto() { $this->resetPage(); }
    public function updatedBuscarCargo()     { $this->resetPage(); }

    public function getRowsProperty()
    {
        $query = RelInstitutoCargo::query()
            ->with(['instituto', 'cargo']);

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

    public function editar($id)
    {
        $rel = RelInstitutoCargo::findOrFail($id);
        $this->editId          = $rel->id;
        $this->editInstitutoId = $rel->instituto_superior_id;
        $this->editCargoId     = $rel->cargo_id;

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
        ]);

        $this->resetNewRecord();
        $this->dispatch('modal-close', name: 'create-modal');
        $this->dispatch('toast', variant: 'success', heading: 'Éxito', text: 'Nuevo registro creado.');
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
                <flux:select wire:model="editInstitutoId" searchable size="sm">
                    <option value="">Seleccione...</option>
                    @foreach($institutos as $i)
                        <option value="{{ $i['id'] }}">{{ $i['nombre'] }}</option>
                    @endforeach
                </flux:select>
                <flux:error name="editInstitutoId" />
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
