<?php
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Title;
use App\Models\Espacio_curricular;
use App\Models\Cargo;
use App\Models\Carrera;
use App\Models\Instituto;

new #[Title('ABM de Catálogos')] class extends Component
{
    use WithPagination;

    // Pestaña activa
    public $tab = 'espacios';

    /* ============================================================
     |  ESPACIOS CURRICULARES
     * ============================================================ */
    public $buscarEspacio = '';

    public $espNombre = '';

    public $espEditId     = null;
    public $espEditNombre = '';

    public function updatedBuscarEspacio() { $this->resetPage('espaciosPage'); }

    public function getEspaciosRowsProperty()
    {
        return Espacio_curricular::query()
            ->when($this->buscarEspacio, fn($q) =>
                $q->where('nombre_espacio', 'like', '%' . $this->buscarEspacio . '%'))
            ->orderBy('nombre_espacio')
            ->paginate(10, ['*'], 'espaciosPage');
    }

    public function crearEspacio()
    {
        $this->validate([
            'espNombre' => 'required|string|max:255',
        ], [], ['espNombre' => 'Nombre del espacio']);

        $existe = Espacio_curricular::where('nombre_espacio', $this->espNombre)->exists();
        if ($existe) {
            $this->dispatch('toast', variant: 'error', heading: 'Error', text: 'Ya existe un espacio curricular con ese nombre.');
            return;
        }

        Espacio_curricular::create(['nombre_espacio' => $this->espNombre]);

        $this->resetEspacioForm();
        $this->dispatch('modal-close', name: 'create-espacio-modal');
        $this->dispatch('toast', variant: 'success', heading: 'Éxito', text: 'Espacio curricular creado.');
    }

    public function editarEspacio($id)
    {
        $row = Espacio_curricular::findOrFail($id);
        $this->espEditId     = $row->getKey();
        $this->espEditNombre = $row->nombre_espacio;

        $this->dispatch('modal-show', name: 'edit-espacio-modal');
    }

    public function guardarEdicionEspacio()
    {
        $this->validate([
            'espEditNombre' => 'required|string|max:255',
        ], [], ['espEditNombre' => 'Nombre del espacio']);

        Espacio_curricular::find($this->espEditId)->update([
            'nombre_espacio' => $this->espEditNombre,
        ]);

        $this->dispatch('modal-close', name: 'edit-espacio-modal');
        $this->dispatch('toast', variant: 'success', heading: 'Éxito', text: 'Espacio curricular actualizado.');
    }

    public function eliminarEspacio($id)
    {
        Espacio_curricular::findOrFail($id)->delete();
        $this->dispatch('toast', variant: 'success', heading: 'Éxito', text: 'Espacio curricular eliminado.');
    }

    public function resetEspacioForm()
    {
        $this->espNombre = '';
    }

    /* ============================================================
     |  CARGOS
     * ============================================================ */
    public $buscarCargo = '';

    public $cargoNombre       = '';
    public $cargoHoraCatedra  = '';
    public $cargoEsPorCarrera = false;

    public $cargoEditId            = null;
    public $cargoEditNombre        = '';
    public $cargoEditHoraCatedra   = '';
    public $cargoEditEsPorCarrera  = false;

    public function updatedBuscarCargo() { $this->resetPage('cargosPage'); }

    public function getCargosRowsProperty()
    {
        return Cargo::query()
            ->when($this->buscarCargo, fn($q) =>
                $q->where('nombre_cargo', 'like', '%' . $this->buscarCargo . '%'))
            ->orderBy('nombre_cargo')
            ->paginate(10, ['*'], 'cargosPage');
    }

    public function crearCargo()
    {
        $this->validate([
            'cargoNombre'      => 'required|string|max:255',
            'cargoHoraCatedra' => 'nullable|integer|min:0',
        ], [], ['cargoNombre' => 'Nombre del cargo', 'cargoHoraCatedra' => 'Horas cátedra']);

        $existe = Cargo::where('nombre_cargo', $this->cargoNombre)->exists();
        if ($existe) {
            $this->dispatch('toast', variant: 'error', heading: 'Error', text: 'Ya existe un cargo con ese nombre.');
            return;
        }

        Cargo::create([
            'nombre_cargo'   => $this->cargoNombre,
            'hora_catedra'   => $this->cargoHoraCatedra ?: null,
            'es_por_carrera' => $this->cargoEsPorCarrera,
        ]);

        $this->resetCargoForm();
        $this->dispatch('modal-close', name: 'create-cargo-modal');
        $this->dispatch('toast', variant: 'success', heading: 'Éxito', text: 'Cargo creado.');
    }

    public function editarCargo($id)
    {
        $row = Cargo::findOrFail($id);
        $this->cargoEditId           = $row->getKey();
        $this->cargoEditNombre       = $row->nombre_cargo;
        $this->cargoEditHoraCatedra  = $row->hora_catedra;
        $this->cargoEditEsPorCarrera = (bool) $row->es_por_carrera;

        $this->dispatch('modal-show', name: 'edit-cargo-modal');
    }

    public function guardarEdicionCargo()
    {
        $this->validate([
            'cargoEditNombre'      => 'required|string|max:255',
            'cargoEditHoraCatedra' => 'nullable|integer|min:0',
        ], [], ['cargoEditNombre' => 'Nombre del cargo', 'cargoEditHoraCatedra' => 'Horas cátedra']);

        Cargo::find($this->cargoEditId)->update([
            'nombre_cargo'   => $this->cargoEditNombre,
            'hora_catedra'   => $this->cargoEditHoraCatedra ?: null,
            'es_por_carrera' => $this->cargoEditEsPorCarrera,
        ]);

        $this->dispatch('modal-close', name: 'edit-cargo-modal');
        $this->dispatch('toast', variant: 'success', heading: 'Éxito', text: 'Cargo actualizado.');
    }

    public function eliminarCargo($id)
    {
        Cargo::findOrFail($id)->delete();
        $this->dispatch('toast', variant: 'success', heading: 'Éxito', text: 'Cargo eliminado.');
    }

    public function resetCargoForm()
    {
        $this->cargoNombre       = '';
        $this->cargoHoraCatedra  = '';
        $this->cargoEsPorCarrera = false;
    }

    /* ============================================================
     |  CARRERAS
     * ============================================================ */
    public $buscarCarrera = '';

    public $carreraNombre = '';

    public $carreraEditId     = null;
    public $carreraEditNombre = '';

    public function updatedBuscarCarrera() { $this->resetPage('carrerasPage'); }

    public function getCarrerasRowsProperty()
    {
        return Carrera::query()
            ->when($this->buscarCarrera, fn($q) =>
                $q->where('nombre', 'like', '%' . $this->buscarCarrera . '%'))
            ->orderBy('nombre')
            ->paginate(10, ['*'], 'carrerasPage');
    }

    public function crearCarrera()
    {
        $this->validate([
            'carreraNombre' => 'required|string|max:255',
        ], [], ['carreraNombre' => 'Nombre de la carrera']);

        $existe = Carrera::where('nombre', $this->carreraNombre)->exists();
        if ($existe) {
            $this->dispatch('toast', variant: 'error', heading: 'Error', text: 'Ya existe una carrera con ese nombre.');
            return;
        }

        Carrera::create(['nombre' => $this->carreraNombre]);

        $this->resetCarreraForm();
        $this->dispatch('modal-close', name: 'create-carrera-modal');
        $this->dispatch('toast', variant: 'success', heading: 'Éxito', text: 'Carrera creada.');
    }

    public function editarCarrera($id)
    {
        $row = Carrera::findOrFail($id);
        $this->carreraEditId     = $row->getKey();
        $this->carreraEditNombre = $row->nombre;

        $this->dispatch('modal-show', name: 'edit-carrera-modal');
    }

    public function guardarEdicionCarrera()
    {
        $this->validate([
            'carreraEditNombre' => 'required|string|max:255',
        ], [], ['carreraEditNombre' => 'Nombre de la carrera']);

        Carrera::find($this->carreraEditId)->update([
            'nombre' => $this->carreraEditNombre,
        ]);

        $this->dispatch('modal-close', name: 'edit-carrera-modal');
        $this->dispatch('toast', variant: 'success', heading: 'Éxito', text: 'Carrera actualizada.');
    }

    public function eliminarCarrera($id)
    {
        Carrera::findOrFail($id)->delete();
        $this->dispatch('toast', variant: 'success', heading: 'Éxito', text: 'Carrera eliminada.');
    }

    public function resetCarreraForm()
    {
        $this->carreraNombre = '';
    }

    /* ============================================================
     |  INSTITUTOS
     * ============================================================ */
    public $buscarInstituto = '';

    public $institutoNombre = '';

    public $institutoEditId     = null;
    public $institutoEditNombre = '';

    public function updatedBuscarInstituto() { $this->resetPage('institutosPage'); }

    public function getInstitutosRowsProperty()
    {
        return Instituto::query()
            ->when($this->buscarInstituto, fn($q) =>
                $q->where('nombre', 'like', '%' . $this->buscarInstituto . '%'))
            ->orderBy('nombre')
            ->paginate(10, ['*'], 'institutosPage');
    }

    public function crearInstituto()
    {
        $this->validate([
            'institutoNombre' => 'required|string|max:255',
        ], [], ['institutoNombre' => 'Nombre del instituto']);

        $existe = Instituto::where('nombre', $this->institutoNombre)->exists();
        if ($existe) {
            $this->dispatch('toast', variant: 'error', heading: 'Error', text: 'Ya existe un instituto con ese nombre.');
            return;
        }

        Instituto::create(['nombre' => $this->institutoNombre]);

        $this->resetInstitutoForm();
        $this->dispatch('modal-close', name: 'create-instituto-modal');
        $this->dispatch('toast', variant: 'success', heading: 'Éxito', text: 'Instituto creado.');
    }

    public function editarInstituto($id)
    {
        $row = Instituto::findOrFail($id);
        $this->institutoEditId     = $row->getKey();
        $this->institutoEditNombre = $row->nombre;

        $this->dispatch('modal-show', name: 'edit-instituto-modal');
    }

    public function guardarEdicionInstituto()
    {
        $this->validate([
            'institutoEditNombre' => 'required|string|max:255',
        ], [], ['institutoEditNombre' => 'Nombre del instituto']);

        Instituto::find($this->institutoEditId)->update([
            'nombre' => $this->institutoEditNombre,
        ]);

        $this->dispatch('modal-close', name: 'edit-instituto-modal');
        $this->dispatch('toast', variant: 'success', heading: 'Éxito', text: 'Instituto actualizado.');
    }

    public function eliminarInstituto($id)
    {
        Instituto::findOrFail($id)->delete();
        $this->dispatch('toast', variant: 'success', heading: 'Éxito', text: 'Instituto eliminado.');
    }

    public function resetInstitutoForm()
    {
        $this->institutoNombre = '';
    }
};
?>

<div class="space-y-6">
    <flux:heading size="xl" level="1" class="!text-zinc-900 dark:!text-white font-bold">
        ABM de Catálogos
    </flux:heading>

    <div class="flex flex-wrap gap-2 border-b border-zinc-200 dark:border-zinc-700 pb-2">
        <flux:button
            wire:click="$set('tab', 'espacios')"
            variant="{{ $tab === 'espacios' ? 'primary' : 'ghost' }}"
            icon="book-open" size="sm">
            Espacios Curriculares
        </flux:button>
        <flux:button
            wire:click="$set('tab', 'cargos')"
            variant="{{ $tab === 'cargos' ? 'primary' : 'ghost' }}"
            icon="briefcase" size="sm">
            Cargos
        </flux:button>
        <flux:button
            wire:click="$set('tab', 'carreras')"
            variant="{{ $tab === 'carreras' ? 'primary' : 'ghost' }}"
            icon="academic-cap" size="sm">
            Carreras
        </flux:button>
        <flux:button
            wire:click="$set('tab', 'institutos')"
            variant="{{ $tab === 'institutos' ? 'primary' : 'ghost' }}"
            icon="building-library" size="sm">
            Institutos
        </flux:button>
    </div>

        {{-- ============================================================ --}}
        {{-- PESTAÑA: ESPACIOS CURRICULARES                                --}}
        {{-- ============================================================ --}}
        @if($tab === 'espacios')
            <div class="space-y-6 pt-4">
                <div class="flex items-center justify-between">
                    <flux:field class="w-full max-w-sm">
                        <flux:input wire:model.live.debounce.400ms="buscarEspacio" icon="magnifying-glass" placeholder="Buscar espacio curricular..." size="sm" />
                    </flux:field>

                    <flux:modal.trigger name="create-espacio-modal">
                        <flux:button variant="primary" icon="plus" wire:click="resetEspacioForm">
                            Nuevo Espacio
                        </flux:button>
                    </flux:modal.trigger>
                </div>

                <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900 shadow-sm">
                    <flux:table :paginate="$this->espaciosRows">
                        <flux:table.columns>
                            <flux:table.column class="!text-zinc-900 font-bold">Espacio Curricular</flux:table.column>
                            <flux:table.column class="w-20 text-center"></flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @foreach($this->espaciosRows as $row)
                                <flux:table.row :wire:key="'esp-'.$row->getKey()">
                                    <flux:table.cell class="text-sm !text-zinc-900 font-medium">
                                        {{ $row->nombre_espacio }}
                                    </flux:table.cell>
                                    <flux:table.cell class="text-center">
                                        <div class="flex justify-center gap-1">
                                            <flux:button wire:click="editarEspacio({{ $row->getKey() }})" variant="ghost" size="xs" icon="pencil-square" class="!text-zinc-900" />
                                            <flux:button wire:click="eliminarEspacio({{ $row->getKey() }})" wire:confirm="¿Eliminar este espacio curricular?" variant="ghost" size="xs" icon="trash" class="!text-red-500" />
                                        </div>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                </div>
            </div>

        {{-- ============================================================ --}}
        {{-- PESTAÑA: CARGOS                                                --}}
        {{-- ============================================================ --}}
        @elseif($tab === 'cargos')
            <div class="space-y-6 pt-4">
                <div class="flex items-center justify-between">
                    <flux:field class="w-full max-w-sm">
                        <flux:input wire:model.live.debounce.400ms="buscarCargo" icon="magnifying-glass" placeholder="Buscar cargo..." size="sm" />
                    </flux:field>

                    <flux:modal.trigger name="create-cargo-modal">
                        <flux:button variant="primary" icon="plus" wire:click="resetCargoForm">
                            Nuevo Cargo
                        </flux:button>
                    </flux:modal.trigger>
                </div>

                <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900 shadow-sm">
                    <flux:table :paginate="$this->cargosRows">
                        <flux:table.columns>
                            <flux:table.column class="!text-zinc-900 font-bold">Cargo</flux:table.column>
                            <flux:table.column class="!text-zinc-900 font-bold">Horas Cátedra</flux:table.column>
                            <flux:table.column class="!text-zinc-900 font-bold">Por Carrera</flux:table.column>
                            <flux:table.column class="w-20 text-center"></flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @foreach($this->cargosRows as $row)
                                <flux:table.row :wire:key="'cargo-'.$row->getKey()">
                                    <flux:table.cell class="text-sm !text-zinc-900 font-medium">
                                        {{ $row->nombre_cargo }}
                                    </flux:table.cell>
                                    <flux:table.cell class="text-sm !text-zinc-900">
                                        {{ $row->hora_catedra ?? '-' }}
                                    </flux:table.cell>
                                    <flux:table.cell class="text-sm">
                                        @if($row->es_por_carrera)
                                            <flux:badge variant="solid" color="lime" size="sm">Sí</flux:badge>
                                        @else
                                            <flux:badge variant="solid" color="zinc" size="sm">No</flux:badge>
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell class="text-center">
                                        <div class="flex justify-center gap-1">
                                            <flux:button wire:click="editarCargo({{ $row->getKey() }})" variant="ghost" size="xs" icon="pencil-square" class="!text-zinc-900" />
                                            <flux:button wire:click="eliminarCargo({{ $row->getKey() }})" wire:confirm="¿Eliminar este cargo?" variant="ghost" size="xs" icon="trash" class="!text-red-500" />
                                        </div>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                </div>
            </div>

        {{-- ============================================================ --}}
        {{-- PESTAÑA: CARRERAS                                              --}}
        {{-- ============================================================ --}}
        @elseif($tab === 'carreras')
            <div class="space-y-6 pt-4">
                <div class="flex items-center justify-between">
                    <flux:field class="w-full max-w-sm">
                        <flux:input wire:model.live.debounce.400ms="buscarCarrera" icon="magnifying-glass" placeholder="Buscar carrera..." size="sm" />
                    </flux:field>

                    <flux:modal.trigger name="create-carrera-modal">
                        <flux:button variant="primary" icon="plus" wire:click="resetCarreraForm">
                            Nueva Carrera
                        </flux:button>
                    </flux:modal.trigger>
                </div>

                <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900 shadow-sm">
                    <flux:table :paginate="$this->carrerasRows">
                        <flux:table.columns>
                            <flux:table.column class="!text-zinc-900 font-bold">Carrera</flux:table.column>
                            <flux:table.column class="w-20 text-center"></flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @foreach($this->carrerasRows as $row)
                                <flux:table.row :wire:key="'carrera-'.$row->getKey()">
                                    <flux:table.cell class="text-sm !text-zinc-900 font-medium">
                                        {{ $row->nombre }}
                                    </flux:table.cell>
                                    <flux:table.cell class="text-center">
                                        <div class="flex justify-center gap-1">
                                            <flux:button wire:click="editarCarrera({{ $row->getKey() }})" variant="ghost" size="xs" icon="pencil-square" class="!text-zinc-900" />
                                            <flux:button wire:click="eliminarCarrera({{ $row->getKey() }})" wire:confirm="¿Eliminar esta carrera?" variant="ghost" size="xs" icon="trash" class="!text-red-500" />
                                        </div>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                </div>
            </div>

        {{-- ============================================================ --}}
        {{-- PESTAÑA: INSTITUTOS                                            --}}
        {{-- ============================================================ --}}
        @elseif($tab === 'institutos')
            <div class="space-y-6 pt-4">
                <div class="flex items-center justify-between">
                    <flux:field class="w-full max-w-sm">
                        <flux:input wire:model.live.debounce.400ms="buscarInstituto" icon="magnifying-glass" placeholder="Buscar instituto..." size="sm" />
                    </flux:field>

                    <flux:modal.trigger name="create-instituto-modal">
                        <flux:button variant="primary" icon="plus" wire:click="resetInstitutoForm">
                            Nuevo Instituto
                        </flux:button>
                    </flux:modal.trigger>
                </div>

                <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900 shadow-sm">
                    <flux:table :paginate="$this->institutosRows">
                        <flux:table.columns>
                            <flux:table.column class="!text-zinc-900 font-bold">Instituto Superior</flux:table.column>
                            <flux:table.column class="w-20 text-center"></flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @foreach($this->institutosRows as $row)
                                <flux:table.row :wire:key="'instituto-'.$row->getKey()">
                                    <flux:table.cell class="text-sm !text-zinc-900 font-medium">
                                        {{ $row->nombre }}
                                    </flux:table.cell>
                                    <flux:table.cell class="text-center">
                                        <div class="flex justify-center gap-1">
                                            <flux:button wire:click="editarInstituto({{ $row->getKey() }})" variant="ghost" size="xs" icon="pencil-square" class="!text-zinc-900" />
                                            <flux:button wire:click="eliminarInstituto({{ $row->getKey() }})" wire:confirm="¿Eliminar este instituto?" variant="ghost" size="xs" icon="trash" class="!text-red-500" />
                                        </div>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                </div>
            </div>
        @endif

    {{-- ============================================================ --}}
    {{-- MODALES: ESPACIOS CURRICULARES                                 --}}
    {{-- ============================================================ --}}
    <flux:modal name="create-espacio-modal" class="md:w-[480px]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Nuevo Espacio Curricular</flux:heading>
                <flux:subheading>Cree un nuevo espacio curricular</flux:subheading>
            </div>

            <flux:field>
                <flux:label>Nombre</flux:label>
                <flux:input wire:model="espNombre" size="sm" placeholder="Ej: Matemática I" />
                <flux:error name="espNombre" />
            </flux:field>

            <div class="flex justify-end gap-2 pt-4 border-t border-zinc-100 dark:border-zinc-800">
                <flux:modal.close>
                    <flux:button variant="ghost" size="sm">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" wire:click="crearEspacio" size="sm">Guardar</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="edit-espacio-modal" class="md:w-[480px]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Editar Espacio Curricular</flux:heading>
                <flux:subheading>Actualice el nombre</flux:subheading>
            </div>

            <flux:field>
                <flux:label>Nombre</flux:label>
                <flux:input wire:model="espEditNombre" size="sm" />
                <flux:error name="espEditNombre" />
            </flux:field>

            <div class="flex justify-end gap-2 pt-4 border-t border-zinc-100 dark:border-zinc-800">
                <flux:modal.close>
                    <flux:button variant="ghost" size="sm">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" wire:click="guardarEdicionEspacio" size="sm">Guardar Cambios</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- ============================================================ --}}
    {{-- MODALES: CARGOS                                                --}}
    {{-- ============================================================ --}}
    <flux:modal name="create-cargo-modal" class="md:w-[480px]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Nuevo Cargo</flux:heading>
                <flux:subheading>Cree un nuevo cargo</flux:subheading>
            </div>

            <flux:field>
                <flux:label>Nombre</flux:label>
                <flux:input wire:model="cargoNombre" size="sm" placeholder="Ej: Bedel, Profesor Titular..." />
                <flux:error name="cargoNombre" />
            </flux:field>

            <flux:field>
                <flux:label>Horas Cátedra</flux:label>
                <flux:input wire:model="cargoHoraCatedra" type="number" size="sm" placeholder="Ej: 4" />
                <flux:error name="cargoHoraCatedra" />
            </flux:field>

            <flux:field>
                <flux:checkbox wire:model="cargoEsPorCarrera" label="Es por carrera (ej: Bedel)" />
            </flux:field>

            <div class="flex justify-end gap-2 pt-4 border-t border-zinc-100 dark:border-zinc-800">
                <flux:modal.close>
                    <flux:button variant="ghost" size="sm">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" wire:click="crearCargo" size="sm">Guardar</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="edit-cargo-modal" class="md:w-[480px]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Editar Cargo</flux:heading>
                <flux:subheading>Actualice los datos del cargo</flux:subheading>
            </div>

            <flux:field>
                <flux:label>Nombre</flux:label>
                <flux:input wire:model="cargoEditNombre" size="sm" />
                <flux:error name="cargoEditNombre" />
            </flux:field>

            <flux:field>
                <flux:label>Horas Cátedra</flux:label>
                <flux:input wire:model="cargoEditHoraCatedra" type="number" size="sm" />
                <flux:error name="cargoEditHoraCatedra" />
            </flux:field>

            <flux:field>
                <flux:checkbox wire:model="cargoEditEsPorCarrera" label="Es por carrera (ej: Bedel)" />
            </flux:field>

            <div class="flex justify-end gap-2 pt-4 border-t border-zinc-100 dark:border-zinc-800">
                <flux:modal.close>
                    <flux:button variant="ghost" size="sm">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" wire:click="guardarEdicionCargo" size="sm">Guardar Cambios</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- ============================================================ --}}
    {{-- MODALES: CARRERAS                                              --}}
    {{-- ============================================================ --}}
    <flux:modal name="create-carrera-modal" class="md:w-[480px]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Nueva Carrera</flux:heading>
                <flux:subheading>Cree una nueva carrera</flux:subheading>
            </div>

            <flux:field>
                <flux:label>Nombre</flux:label>
                <flux:input wire:model="carreraNombre" size="sm" placeholder="Ej: Profesorado de Educación Primaria" />
                <flux:error name="carreraNombre" />
            </flux:field>

            <div class="flex justify-end gap-2 pt-4 border-t border-zinc-100 dark:border-zinc-800">
                <flux:modal.close>
                    <flux:button variant="ghost" size="sm">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" wire:click="crearCarrera" size="sm">Guardar</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="edit-carrera-modal" class="md:w-[480px]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Editar Carrera</flux:heading>
                <flux:subheading>Actualice el nombre</flux:subheading>
            </div>

            <flux:field>
                <flux:label>Nombre</flux:label>
                <flux:input wire:model="carreraEditNombre" size="sm" />
                <flux:error name="carreraEditNombre" />
            </flux:field>

            <div class="flex justify-end gap-2 pt-4 border-t border-zinc-100 dark:border-zinc-800">
                <flux:modal.close>
                    <flux:button variant="ghost" size="sm">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" wire:click="guardarEdicionCarrera" size="sm">Guardar Cambios</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- ============================================================ --}}
    {{-- MODALES: INSTITUTOS                                            --}}
    {{-- ============================================================ --}}
    <flux:modal name="create-instituto-modal" class="md:w-[480px]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Nuevo Instituto</flux:heading>
                <flux:subheading>Cree un nuevo instituto superior</flux:subheading>
            </div>

            <flux:field>
                <flux:label>Nombre</flux:label>
                <flux:input wire:model="institutoNombre" size="sm" placeholder="Ej: Instituto Superior N° 1" />
                <flux:error name="institutoNombre" />
            </flux:field>

            <div class="flex justify-end gap-2 pt-4 border-t border-zinc-100 dark:border-zinc-800">
                <flux:modal.close>
                    <flux:button variant="ghost" size="sm">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" wire:click="crearInstituto" size="sm">Guardar</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="edit-instituto-modal" class="md:w-[480px]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Editar Instituto</flux:heading>
                <flux:subheading>Actualice el nombre</flux:subheading>
            </div>

            <flux:field>
                <flux:label>Nombre</flux:label>
                <flux:input wire:model="institutoEditNombre" size="sm" />
                <flux:error name="institutoEditNombre" />
            </flux:field>

            <div class="flex justify-end gap-2 pt-4 border-t border-zinc-100 dark:border-zinc-800">
                <flux:modal.close>
                    <flux:button variant="ghost" size="sm">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" wire:click="guardarEdicionInstituto" size="sm">Guardar Cambios</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
