<?php
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;
use App\Models\RelCarreraEspacio;
use App\Models\Turno;
use App\Models\Periodo;
use App\Models\Perfil;
use App\Models\Espacio_curricular;
use App\Models\Carrera;
use App\Models\Instituto;
use Livewire\Attributes\Title;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

new #[Title('Relación Carrera - Espacio')] class extends Component
{
    use WithPagination;

    public $buscarCarrera = '';
    public $buscarEspacio = '';
    public $buscarPerfil  = '';

    // Búsqueda dentro de los combos de Espacio Curricular
    public $buscarEspacioNuevo = '';
    public $buscarEspacioEdit  = '';

    public $turnos    = [];
    public $periodos  = [];
    public $carreras  = [];
    public $espacios  = [];
    public $perfiles  = [];
    public $institutos = [];

    // Listas filtradas reactivas
    public $newCarrerasFiltered  = [];
    public $editCarrerasFiltered = [];

    // Propiedades para nuevo registro
    public $newCarreraId    = '';
    public $newInstitutoId  = '';

    // Cada fila representa un Espacio Curricular a relacionar con la carrera seleccionada.
    // Estructura: ['key' => uuid, 'espacio_id' => '', 'anio' => '', 'hora_catedra' => '',
    //              'periodo_id' => '', 'turno_id' => '', 'perfil_id' => '']
    public $newEspacios = [];

    // Propiedades para edición
    public $editId              = null;
    public $editCarreraId       = '';
    public $editCarreraNombre   = '';
    public $editEspacioId       = '';
    public $editAnio            = '';
    public $editHoraCatedra     = '';
    public $editPeriodoId       = '';
    public $editTurnoId         = '';
    public $editPerfilId        = '';
    public $editInstitutoId     = '';
    public $editInstitutoNombre = '';

    public function mount(): void
    {
        $this->turnos    = Turno::orderBy('nombre_turno')->get()->toArray();
        $this->periodos  = Periodo::orderBy('nombre_periodo')->get()->toArray();
        $this->carreras  = Carrera::orderBy('nombre')->get()->toArray();
        $this->espacios  = Espacio_curricular::orderBy('nombre_espacio')->get()->toArray();
        $this->perfiles  = Perfil::orderBy('nombre_perfil')->get()->toArray();
        $this->institutos = Instituto::orderBy('nombre')->get()->toArray();
        $this->newEspacios = [$this->emptyEspacioRow()];
    }

    public function updatedBuscarCarrera(): void { $this->resetPage(); }
    public function updatedBuscarEspacio(): void { $this->resetPage(); }
    public function updatedBuscarPerfil(): void  { $this->resetPage(); }

    // ── Filtrado de carreras por instituto (NUEVO) ─────────────────
    public function updatedNewInstitutoId($value): void
    {
      $this->newCarreraId = '';
        if (!$value) {
        $this->newCarrerasFiltered = [];
        return;
        }
        $this->newCarrerasFiltered = DB::table('tb_carreras')
        ->join('rel_instsup_carrera', 'tb_carreras.id', '=', 'rel_instsup_carrera.carrera_id')
        ->where('rel_instsup_carrera.instituto_id', $value)
        ->select('tb_carreras.id', 'tb_carreras.nombre')
        ->orderBy('tb_carreras.nombre')
        ->get()->toArray();
            
    }

    #[Computed(cache: false)]
    public function getRowsProperty()
    {
        $query = RelCarreraEspacio::query()
            ->with(['carrera', 'espacio', 'perfil', 'periodo', 'turno', 'instituto']);

        if ($this->buscarCarrera) {
            $query->whereHas('carrera', fn($q) =>
                $q->where('nombre', 'like', '%' . $this->buscarCarrera . '%'));
        }

        if ($this->buscarEspacio) {
            $query->whereHas('espacio', fn($q) =>
                $q->where('nombre_espacio', 'like', '%' . $this->buscarEspacio . '%'));
        }

        if ($this->buscarPerfil) {
            $query->whereHas('perfil', fn($q) =>
                $q->where('nombre_perfil', 'like', '%' . $this->buscarPerfil . '%'));
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

    private function emptyEspacioRow(): array
    {
        return [
            'key'          => (string) Str::uuid(),
            'espacio_id'   => '',
            'buscar'       => '',
            'anio'         => '',
            'hora_catedra' => '',
            'periodo_id'   => '',
            'turno_id'     => '',
            'perfil_id'    => '',          
        ];
    }

    public function agregarEspacioNuevo(): void
    {
        $this->newEspacios[] = $this->emptyEspacioRow();
    }

    public function quitarEspacioNuevo(string $key): void
    {
        // Siempre debe quedar al menos una fila
        if (count($this->newEspacios) <= 1) {
            return;
        }

        $this->newEspacios = array_values(array_filter(
            $this->newEspacios,
            fn($row) => $row['key'] !== $key
        ));
    }   
    #[Computed(cache: false)]
    public function espaciosNuevoFiltrados($index)
    {
        $buscar = $this->newEspacios[$index]['buscar'] ?? '';

        $seleccionados = collect($this->newEspacios)
            ->pluck('espacio_id')
            ->filter()
            ->values()
            ->toArray();

        return array_values(array_filter($this->espacios, function ($e) use ($buscar, $seleccionados, $index) {

            if ($buscar && !str_contains(
                strtolower($e['nombre_espacio']),
                strtolower($buscar)
            )) {
                return false;
            }

            // No mostrar en otras filas un espacio ya seleccionado
            $seleccionadoEnOtraFila = collect($this->newEspacios)
                ->except($index)
                ->pluck('espacio_id')
                ->contains($e['idespaciocurricular']);

            return !$seleccionadoEnOtraFila;
        }));
    }
    #[Computed(cache: false)]
    public function espaciosEditFiltrados()
    {
        if (!$this->buscarEspacioEdit) return $this->espacios;

        return array_values(array_filter($this->espacios, fn($e) =>
            str_contains(strtolower($e['nombre_espacio']), strtolower($this->buscarEspacioEdit))
        ));
    }

    public function editar($id): void
    {
        $rel = RelCarreraEspacio::findOrFail($id);
        $this->editId              = $rel->id;
        $this->editCarreraId       = $rel->carrera_id;
        $this->editCarreraNombre   = $rel->carrera->nombre ?? '-';
        $this->editEspacioId       = $rel->espacio_id;
        $this->editAnio            = $rel->anio;
        $this->editHoraCatedra     = $rel->hora_catedra;
        $this->editPeriodoId       = $rel->periodo_id;
        $this->editTurnoId         = $rel->turno_id;
        $this->editPerfilId        = $rel->perfil_id;
        $this->editInstitutoId     = $rel->instituto_id;
        $this->editInstitutoNombre = $rel->instituto->nombre ?? '-';
        $this->buscarEspacioEdit   = '';

        $this->dispatch('modal-show', name: 'edit-modal');
    }

    public function guardarEdicion(): void
    {
        $this->validate([
            'editCarreraId'  => 'required',
            'editEspacioId'  => 'required',
            'editAnio'       => 'required',
            'editHoraCatedra'=> 'required',
        ]);

        RelCarreraEspacio::find($this->editId)->update([
            'instituto_id' => $this->editInstitutoId,
            'carrera_id'   => $this->editCarreraId,
            'espacio_id'   => $this->editEspacioId,
            'anio'         => $this->editAnio,
            'hora_catedra' => $this->editHoraCatedra,
            'periodo_id'   => $this->editPeriodoId  ?: null,
            'turno_id'     => $this->editTurnoId    ?: null,
            'perfil_id'    => $this->editPerfilId   ?: null,
        ]);

        $this->dispatch('modal-close', name: 'edit-modal');
        $this->dispatch('toast', variant: 'success', heading: 'Éxito', text: 'Registro actualizado.');
    }

    public function guardarNuevo(): void
    {
        $this->validate([
            'newCarreraId'                => 'required',
            'newEspacios'                 => 'required|array|min:1',
            'newEspacios.*.espacio_id'    => 'required',
            'newEspacios.*.anio'          => 'required|numeric',
            'newEspacios.*.hora_catedra'  => 'required|numeric',
        ], [
            'newEspacios.*.espacio_id.required'   => 'Seleccione el Espacio Curricular en todas las filas.',
            'newEspacios.*.anio.required'         => 'Complete el Año en todas las filas.',
            'newEspacios.*.hora_catedra.required' => 'Complete las Horas en todas las filas.',
        ]);

        // Evita cargar el mismo espacio curricular dos veces para la misma carrera
        $espacioIds = array_column($this->newEspacios, 'espacio_id');
        if (count($espacioIds) !== count(array_unique($espacioIds))) {
            $this->addError('newEspacios', 'No puede repetir el mismo Espacio Curricular en varias filas.');
            return;
        }

        foreach ($this->newEspacios as $row) {
            RelCarreraEspacio::create([
                'carrera_id'   => $this->newCarreraId,
                'espacio_id'   => $row['espacio_id'],
                'anio'         => $row['anio'],
                'hora_catedra' => $row['hora_catedra'],
                'periodo_id'   => $row['periodo_id']  ?: null,
                'turno_id'     => $row['turno_id']    ?: null,
                'perfil_id'    => $row['perfil_id']   ?: null,
                'instituto_id' => $this->newInstitutoId ?: null,
            ]);
        }

        $cantidad = count($this->newEspacios);

        $this->resetNewRecord();
        $this->dispatch('modal-close', name: 'create-modal');
        $this->dispatch('toast', variant: 'success', heading: 'Éxito', text: $cantidad > 1
            ? "{$cantidad} registros creados."
            : 'Nuevo registro creado.');
    }

    #[On('perfilSeleccionado')]
    public function recibirPerfil($id, $rowKey = null): void
    {
        // Viene de una fila del modal de "nuevo registro"
        if ($rowKey !== null) {
            foreach ($this->newEspacios as $i => $row) {
                if ($row['key'] === $rowKey) {
                    $this->newEspacios[$i]['perfil_id'] = $id;
                    break;
                }
            }
            return;
        }

        // Viene del modal de edición (no tiene rowKey)
        if ($this->editId) {
            $this->editPerfilId = $id;
        }
    }

    public function resetNewRecord(): void
    {
        $this->newCarreraId       = '';
        $this->newInstitutoId     = '';
        $this->newCarrerasFiltered = [];
        $this->buscarEspacioNuevo  = '';
        $this->newEspacios         = [$this->emptyEspacioRow()];
    }
};
?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl" level="1" class="!text-zinc-900 dark:!text-white font-bold">Relación Carrera - Espacio Curricular</flux:heading>
        
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
                <flux:label class="!text-zinc-900 font-bold">Buscar Espacio Curricular</flux:label>
                <flux:input wire:model.live.debounce.400ms="buscarEspacio" icon="magnifying-glass" size="sm" />
            </flux:field>

           
        </div>
    </flux:card>

    <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900 shadow-sm">
        <flux:table :paginate="$this->rows" class="table-fixed w-full">
            <flux:table.columns>
                <flux:table.column class="w-24 max-w-[140px] !text-zinc-900 font-bold">Instituto</flux:table.column>
                <flux:table.column class="w-24 max-w-[140px] !text-zinc-900 font-bold">Carrera</flux:table.column>
                <flux:table.column class="w-24 !text-zinc-900 font-bold">Espacio Curricular</flux:table.column>
                <flux:table.column class="w-10 text-center !text-zinc-900 font-bold">Año</flux:table.column>
                <flux:table.column class="w-10 text-center !text-zinc-900 font-bold">Perfil ID</flux:table.column>
                <flux:table.column class="w-10 text-center !text-zinc-900 font-bold">Hs</flux:table.column>
                <flux:table.column class="w-10 !text-zinc-900 font-bold">Periodo</flux:table.column>
                <flux:table.column class="w-10 !text-zinc-900 font-bold">Turno</flux:table.column>
                <flux:table.column class="w-10 text-center"></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach($this->rows as $row)
                    <flux:table.row :wire:key="'row-'.$row->id">
                        <flux:table.cell class="text-xs !text-zinc-900 font-medium truncate whitespace-normal break-all">
                            {{ $row->instituto->nombre ?? '' }}
                        </flux:table.cell>

                        <flux:table.cell class="text-xs !text-zinc-900 font-medium truncate whitespace-normal break-all">
                            {{ $row->carrera->nombre ?? '' }}
                        </flux:table.cell>

                        <flux:table.cell class="text-xs !text-zinc-900 font-bold truncate whitespace-normal break-all">
                            {{ $row->espacio->nombre_espacio ?? '' }}
                        </flux:table.cell>

                        <flux:table.cell class="text-center text-xs !text-zinc-900">
                            {{ $row->anio }}
                        </flux:table.cell>

                        <flux:table.cell class="text-center">
                            @if($row->perfil_id)
                                <flux:badge variant="solid" color="white" size="sm" class="font-mono !text-white bg-zinc-900">#{{ $row->perfil_id }}</flux:badge>
                            @else
                                <span class="text-zinc-400 text-[10px] italic">Sin ID</span>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell class="text-center text-xs !text-zinc-900">
                            {{ $row->hora_catedra }}
                        </flux:table.cell>

                        <flux:table.cell class="text-xs !text-zinc-900">
                            {{ $row->periodo->nombre_periodo ?? '-' }}
                        </flux:table.cell>

                        <flux:table.cell class="text-xs !text-zinc-900">
                            {{ $row->turno->nombre_turno ?? '-' }}
                        </flux:table.cell>

                        <flux:table.cell class="text-center">
                            <flux:button wire:click="editar({{ $row->id }})" variant="ghost" size="xs" icon="pencil-square" class="!text-zinc-900" />
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
                <flux:subheading>Relación Carrera - Espacio Curricular</flux:subheading>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <flux:field>
                    <flux:label>Instituto</flux:label>
                   <flux:select wire:model.live="newInstitutoId" size="sm">
                        <option value="">Seleccione...</option>

                        @foreach($institutos as $i)
                            <option value="{{ $i['id'] }}">
                                {{ $i['nombre'] }}
                            </option>
                        @endforeach
                    </flux:select>                   
                </flux:field>
                <flux:field>
                  <flux:label>Carrera</flux:label>

                    <select
                        wire:model.live="newCarreraId"
                        class="w-full border rounded px-2 py-2"
                    >
                        <option value="">Seleccione...</option>

                        @foreach($newCarrerasFiltered as $c)
                            <option value="{{ $c->id }}">
                                {{ $c->nombre }}
                            </option>
                        @endforeach
                    </select>
                </flux:field>

            </div>

            <flux:field>
                <flux:label>Buscar Espacio Curricular</flux:label>
                <flux:input wire:model.live.debounce.300ms="buscarEspacioNuevo" icon="magnifying-glass" placeholder="Buscar espacio..." size="sm" />
                <flux:description>El filtro aplica a todas las filas de abajo.</flux:description>
            </flux:field>

            @error('newEspacios')
                <flux:callout variant="danger" icon="exclamation-triangle" heading="{{ $message }}" />
            @enderror

            <div class="space-y-4">
                @foreach($newEspacios as $index => $row)
                    <div wire:key="new-espacio-{{ $row['key'] }}" class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-4 space-y-4">
                        <div class="flex items-center justify-between">
                            <flux:subheading class="!text-zinc-900 font-semibold">Espacio {{ $index + 1 }}</flux:subheading>
                            @if(count($newEspacios) > 1)
                                <flux:button wire:click="quitarEspacioNuevo('{{ $row['key'] }}')" variant="ghost" size="xs" icon="trash" class="!text-red-600" />
                            @endif
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <flux:field>
                                <flux:label>Espacio Curricular</flux:label>
                                <flux:select wire:model="newEspacios.{{ $index }}.espacio_id" size="sm">
                                    <option value="">Seleccione...</option>
                                    @foreach($this->espaciosNuevoFiltrados as $e)
                                        <option value="{{ $e['idespaciocurricular'] }}">{{ $e['nombre_espacio'] }}</option>
                                    @endforeach
                                </flux:select>
                            </flux:field>

                            <flux:field>
                                <flux:label>Año</flux:label>
                                <flux:input wire:model="newEspacios.{{ $index }}.anio" type="number" size="sm" />
                            </flux:field>

                            <flux:field>
                                <flux:label>Horas</flux:label>
                                <flux:input wire:model="newEspacios.{{ $index }}.hora_catedra" type="number" size="sm" />
                            </flux:field>

                            <flux:field>
                                <flux:label>Periodo</flux:label>
                                <flux:select wire:model="newEspacios.{{ $index }}.periodo_id" size="sm">
                                    <option value="">Seleccione...</option>
                                    @foreach($periodos as $p)
                                        <option value="{{ $p['idtb_periodo_cursado'] }}">{{ $p['nombre_periodo'] }}</option>
                                    @endforeach
                                </flux:select>
                            </flux:field>

                            <flux:field>
                                <flux:label>Turno</flux:label>
                                <flux:select wire:model="newEspacios.{{ $index }}.turno_id" size="sm">
                                    <option value="">Seleccione...</option>
                                    @foreach($turnos as $t)
                                        <option value="{{ $t['id'] }}">{{ $t['nombre_turno'] }}</option>
                                    @endforeach
                                </flux:select>
                            </flux:field>
                        </div>

                        <flux:field>
                            <livewire:perfil :rowKey="$row['key']" :key="'perfil-new-'.$row['key']" />
                            <div class="mt-2">
                                <div class="w-full text-xs !text-zinc-900">
                                    {{ $this->getPerfilTexto($row['perfil_id']) }}
                                </div>
                            </div>
                        </flux:field>
                    </div>
                @endforeach
            </div>

            <flux:button wire:click="agregarEspacioNuevo" variant="ghost" size="sm" icon="plus">
                Agregar otro Espacio Curricular
            </flux:button>

            <div class="flex justify-end gap-2 pt-6 border-t border-zinc-100 dark:border-zinc-800">
                <flux:modal.close>
                    <flux:button variant="ghost" size="sm">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" wire:click="guardarNuevo" size="sm">Guardar Registro{{ count($newEspacios) > 1 ? 's' : '' }}</flux:button>
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
                    <flux:label>Instituto</flux:label>
                    <div class="rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm font-medium !text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800">
                        {{ $editInstitutoNombre }}
                    </div>
                </flux:field>
                <flux:field>
                    <flux:label>Carrera</flux:label>
                    <div class="rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm font-medium !text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800">
                        {{ $editCarreraNombre }}
                    </div>
                </flux:field>

                <flux:field>
                    <flux:label>Espacio Curricular</flux:label>
                    <flux:input wire:model.live.debounce.300ms="buscarEspacioEdit" icon="magnifying-glass" placeholder="Buscar espacio..." size="sm" class="mb-2" />
                    <flux:select wire:model="editEspacioId" size="sm">
                        @foreach($this->espaciosEditFiltrados as $e)
                            <flux:select.option value="{{ $e['idespaciocurricular'] }}">{{ $e['nombre_espacio'] }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>Año</flux:label>
                    <flux:input wire:model="editAnio" type="number" size="sm" />
                </flux:field>

                <flux:field>
                    <flux:label>Horas</flux:label>
                    <flux:input wire:model="editHoraCatedra" type="number" size="sm" />
                </flux:field>

                <flux:field>
                    <flux:label>Periodo</flux:label>
                    <flux:select wire:model="editPeriodoId" size="sm">
                        <option value="">Seleccione...</option>
                        @foreach($periodos as $p)
                            <option value="{{ $p['idtb_periodo_cursado'] }}">{{ $p['nombre_periodo'] }}</option>
                        @endforeach
                    </flux:select>
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