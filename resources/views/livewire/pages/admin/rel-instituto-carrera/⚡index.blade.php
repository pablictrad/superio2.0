<?php
use Livewire\Volt\Component;
use App\Models\RelInstitutoSupCarrera;
use App\Models\Instituto;
use App\Models\Carrera;
use Livewire\Attributes\Title;
use Livewire\WithPagination;

new #[Title('Relación Instituto - Carrera')] class extends Component
{
    use WithPagination;

    public $buscarInstituto = '';
    public $buscarCarrera = '';

    public $institutos = [];
    public $carreras = [];

    // Propiedades para nuevo registro
    public $newInstitutoId = '';
    public $newCarreraId = '';

    // Propiedades para edición
    public $editId = null;
    public $editInstitutoId = '';
    public $editCarreraId = '';

    public function mount()
    {
        $this->institutos = Instituto::orderBy('nombre')->get()->toArray();
        $this->carreras = Carrera::orderBy('nombre')->get()->toArray();
    }

    public function updatedBuscarInstituto() { $this->resetPage(); }
    public function updatedBuscarCarrera() { $this->resetPage(); }

    public function getRowsProperty()
    {
        $query = RelInstitutoSupCarrera::query()
            ->with(['instituto', 'carrera']);

        if ($this->buscarInstituto) {
            $query->whereHas('instituto', fn($q) =>
                $q->where('nombre', 'like', '%' . $this->buscarInstituto . '%'));
        }

        if ($this->buscarCarrera) {
            $query->whereHas('carrera', fn($q) =>
                $q->where('nombre', 'like', '%' . $this->buscarCarrera . '%'));
        }

        return $query->paginate(10);
    }

    public function editar($id)
    {
        $rel = RelInstitutoSupCarrera::findOrFail($id);
        $this->editId = $rel->id;
        $this->editInstitutoId = $rel->instituto_id;
        $this->editCarreraId = $rel->carrera_id;

        $this->dispatch('modal-show', name: 'edit-modal');
    }

    public function guardarEdicion()
    {
        $this->validate([
            'editInstitutoId' => 'required',
            'editCarreraId' => 'required',
        ]);

        RelInstitutoSupCarrera::find($this->editId)->update([
            'instituto_id' => $this->editInstitutoId,
            'carrera_id' => $this->editCarreraId,
        ]);

        $this->dispatch('modal-close', name: 'edit-modal');
        $this->dispatch('toast', variant: 'success', heading: 'Éxito', text: 'Relación actualizada correctamente.');
    }

    public function guardarNuevo()
    {
        $this->validate([
            'newInstitutoId' => 'required',
            'newCarreraId' => 'required',
        ]);

        // Verificar si ya existe
        $existe = RelInstitutoSupCarrera::where('instituto_id', $this->newInstitutoId)
            ->where('carrera_id', $this->newCarreraId)
            ->exists();

        if ($existe) {
            $this->dispatch('toast', variant: 'error', heading: 'Error', text: 'Esta relación ya existe.');
            return;
        }

        RelInstitutoSupCarrera::create([
            'instituto_id' => $this->newInstitutoId,
            'carrera_id' => $this->newCarreraId,
        ]);

        $this->resetNewRecord();
        $this->dispatch('modal-close', name: 'create-modal');
        $this->dispatch('toast', variant: 'success', heading: 'Éxito', text: 'Relación creada correctamente.');
    }

    public function eliminar($id)
    {
        RelInstitutoSupCarrera::destroy($id);
        $this->dispatch('toast', variant: 'success', heading: 'Éxito', text: 'Relación eliminada.');
    }

    public function resetNewRecord()
    {
        $this->newInstitutoId = '';
        $this->newCarreraId = '';
    }
};
?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl" level="1" class="!text-zinc-900 dark:!text-white font-bold">Relación Instituto - Carrera</flux:heading>
        
        <flux:modal.trigger name="create-modal">
            <flux:button variant="primary" icon="plus" wire:click="resetNewRecord">Vincular Carrera</flux:button>
        </flux:modal.trigger>
    </div>

    <flux:card>
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            <flux:field>
                <flux:label class="!text-zinc-900 font-bold">Filtrar por Instituto</flux:label>
                <flux:input wire:model.live.debounce.400ms="buscarInstituto" icon="building-library" placeholder="Nombre del instituto..." size="sm" />
            </flux:field>

            <flux:field>
                <flux:label class="!text-zinc-900 font-bold">Filtrar por Carrera</flux:label>
                <flux:input wire:model.live.debounce.400ms="buscarCarrera" icon="academic-cap" placeholder="Nombre de la carrera..." size="sm" />
            </flux:field>
        </div>
    </flux:card>

    <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900 shadow-sm">
        <flux:table :paginate="$this->rows">
            <flux:table.columns>
                <flux:table.column class="!text-zinc-900 font-bold">Instituto Superior</flux:table.column>
                <flux:table.column class="!text-zinc-900 font-bold">Carrera Profesional</flux:table.column>
                <flux:table.column class="w-24 text-center"></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach($this->rows as $row)
                    <flux:table.row :wire:key="'row-'.$row->id">
                        <flux:table.cell class="text-xs !text-zinc-900 font-medium">
                            {{ $row->instituto->nombre ?? 'N/A' }}
                        </flux:table.cell>

                        <flux:table.cell class="text-xs !text-zinc-900 font-bold">
                            {{ $row->carrera->nombre ?? 'N/A' }}
                        </flux:table.cell>

                        <flux:table.cell class="flex justify-center gap-2">
                            <flux:button wire:click="editar({{ $row->id }})" variant="ghost" size="xs" icon="pencil-square" class="!text-zinc-900" />
                            <flux:modal.trigger name="delete-modal-{{ $row->id }}">
                                <flux:button variant="ghost" size="xs" icon="trash" class="text-red-600 hover:text-red-700" />
                            </flux:modal.trigger>

                            <flux:modal name="delete-modal-{{ $row->id }}" class="md:w-[400px]">
                                <div class="space-y-6">
                                    <flux:heading size="lg">¿Eliminar relación?</flux:heading>
                                    <flux:subheading>Esta acción desvinculará la carrera del instituto.</flux:subheading>
                                    <div class="flex justify-end gap-2">
                                        <flux:modal.close>
                                            <flux:button variant="ghost">Cancelar</flux:button>
                                        </flux:modal.close>
                                        <flux:button variant="danger" wire:click="eliminar({{ $row->id }})">Confirmar</flux:button>
                                    </div>
                                </div>
                            </flux:modal>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </div>

    {{-- MODAL PARA NUEVO REGISTRO --}}
    <flux:modal name="create-modal" class="md:w-[600px]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Vincular Carrera a Instituto</flux:heading>
                <flux:subheading>Cree una nueva relación académica</flux:subheading>
            </div>

            <div class="space-y-6">
                <flux:field>
                    <flux:label>Instituto Superior</flux:label>
                    <flux:select wire:model="newInstitutoId" searchable size="sm" placeholder="Seleccione instituto...">
                        <option value="">Seleccione...</option>
                        @foreach($institutos as $i)
                            <option value="{{ $i['id'] }}">{{ $i['nombre'] }}</option>
                        @endforeach
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>Carrera Profesional</flux:label>
                    <flux:select wire:model="newCarreraId" searchable size="sm" placeholder="Seleccione carrera...">
                        <option value="">Seleccione...</option>
                        @foreach($carreras as $c)
                            <option value="{{ $c['id'] }}">{{ $c['nombre'] }}</option>
                        @endforeach
                    </flux:select>
                </flux:field>
            </div>

            <div class="flex justify-end gap-2 pt-6 border-t border-zinc-100 dark:border-zinc-800">
                <flux:modal.close>
                    <flux:button variant="ghost" size="sm">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" wire:click="guardarNuevo" size="sm">Vincular</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- MODAL PARA EDICION --}}
    <flux:modal name="edit-modal" class="md:w-[600px]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Editar Vinculación</flux:heading>
                <flux:subheading>Actualice los datos de la relación</flux:subheading>
            </div>

            <div class="space-y-6">
                <flux:field>
                    <flux:label>Instituto Superior</flux:label>
                    <flux:select wire:model="editInstitutoId" searchable size="sm">
                        @foreach($institutos as $i)
                            <option value="{{ $i['id'] }}">{{ $i['nombre'] }}</option>
                        @endforeach
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>Carrera Profesional</flux:label>
                    <flux:select wire:model="editCarreraId" searchable size="sm">
                        @foreach($carreras as $c)
                            <option value="{{ $c['id'] }}">{{ $c['nombre'] }}</option>
                        @endforeach
                    </flux:select>
                </flux:field>
            </div>

            <div class="flex justify-end gap-2 pt-6 border-t border-zinc-100 dark:border-zinc-800">
                <flux:modal.close>
                    <flux:button variant="ghost" size="sm">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" wire:click="guardarEdicion" size="sm">Guardar Cambios</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
