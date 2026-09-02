<?php
/**
 * gestionar-instituciones-trayecto.blade.php
 *
 * ABM del catálogo tb_instituciones_trayecto (cue, nombre, sector, ambito,
 * activo), mismo patrón de modales (create/edit) que abm-catalogos.blade.php.
 *
 * Las instituciones NO están vinculadas a un nivel educativo — se identifican
 * por CUE (Código Único de Establecimiento), igual que en el padrón real
 * importado desde bdexportados/instituciones_base.sql (924 instituciones,
 * cruzadas con sectores.sql y ambitos.sql para los campos de texto).
 */

use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use App\Support\Auditoria;

new class extends Component {
    use WithPagination;

    public string $buscar = '';

    // Alta
    public string $cue    = '';
    public string $nombre = '';
    public string $sector = '';
    public string $ambito = '';

    // Edición
    public ?int   $editId     = null;
    public string $editCue    = '';
    public string $editNombre = '';
    public string $editSector = '';
    public string $editAmbito = '';

    public array $sectores = ['Estatal', 'Privado'];
    public array $ambitos  = ['Urbano', 'Rural', 'Rural Aglomerado', 'Rural Disperso', 'Sin Información'];

    public function updatedBuscar(): void
    {
        $this->resetPage();
    }

    #[Computed(cache: false)]
    public function instituciones()
    {
        return DB::table('tb_instituciones_trayecto')
            ->when(trim($this->buscar) !== '', function ($q) {
                $b = '%' . trim($this->buscar) . '%';
                $q->where(function ($sub) use ($b) {
                    $sub->where('nombre', 'ilike', $b)->orWhere('cue', 'ilike', $b);
                });
            })
            ->orderBy('nombre')
            ->paginate(15);
    }

    public function crear(): void
    {
        $this->validate([
            'cue'    => 'required|string|max:20',
            'nombre' => 'required|string|max:255',
            'sector' => 'nullable|string|max:20',
            'ambito' => 'nullable|string|max:30',
        ], [], ['cue' => 'CUE', 'nombre' => 'Nombre']);

        $existe = DB::table('tb_instituciones_trayecto')->where('cue', $this->cue)->exists();

        if ($existe) {
            $this->dispatch('toast', variant: 'error', heading: 'Error', text: 'Ya existe una institución con ese CUE.');
            return;
        }

        $id = DB::table('tb_instituciones_trayecto')->insertGetId([
            'cue'        => trim($this->cue),
            'nombre'     => trim($this->nombre),
            'sector'     => $this->sector ?: null,
            'ambito'     => $this->ambito ?: null,
            'activo'     => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Auditoria::registrar('crear_institucion_trayecto', 'institucion_trayecto', $id, "{$this->nombre} (CUE {$this->cue})");

        $this->reset(['cue', 'nombre', 'sector', 'ambito']);
        $this->dispatch('modal-close', name: 'create-institucion-modal');
        $this->dispatch('toast', variant: 'success', heading: 'Éxito', text: 'Institución creada.');
    }

    public function editar(int $id): void
    {
        $row = DB::table('tb_instituciones_trayecto')->where('id', $id)->first();
        if (!$row) {
            return;
        }

        $this->editId     = $row->id;
        $this->editCue    = $row->cue;
        $this->editNombre = $row->nombre;
        $this->editSector = $row->sector ?? '';
        $this->editAmbito = $row->ambito ?? '';

        $this->dispatch('modal-show', name: 'edit-institucion-modal');
    }

    public function guardarEdicion(): void
    {
        $this->validate([
            'editCue'    => 'required|string|max:20',
            'editNombre' => 'required|string|max:255',
            'editSector' => 'nullable|string|max:20',
            'editAmbito' => 'nullable|string|max:30',
        ], [], ['editCue' => 'CUE', 'editNombre' => 'Nombre']);

        $duplicado = DB::table('tb_instituciones_trayecto')
            ->where('cue', $this->editCue)
            ->where('id', '!=', $this->editId)
            ->exists();

        if ($duplicado) {
            $this->dispatch('toast', variant: 'error', heading: 'Error', text: 'Ya existe otra institución con ese CUE.');
            return;
        }

        DB::table('tb_instituciones_trayecto')->where('id', $this->editId)->update([
            'cue'        => trim($this->editCue),
            'nombre'     => trim($this->editNombre),
            'sector'     => $this->editSector ?: null,
            'ambito'     => $this->editAmbito ?: null,
            'updated_at' => now(),
        ]);

        Auditoria::registrar('editar_institucion_trayecto', 'institucion_trayecto', $this->editId, "{$this->editNombre} (CUE {$this->editCue})");

        $this->dispatch('modal-close', name: 'edit-institucion-modal');
        $this->dispatch('toast', variant: 'success', heading: 'Éxito', text: 'Institución actualizada.');
    }

    public function toggleActivo(int $id): void
    {
        $row = DB::table('tb_instituciones_trayecto')->where('id', $id)->first();
        if (!$row) {
            return;
        }

        DB::table('tb_instituciones_trayecto')->where('id', $id)->update([
            'activo'     => !$row->activo,
            'updated_at' => now(),
        ]);

        Auditoria::registrar($row->activo ? 'deshabilitar_institucion_trayecto' : 'habilitar_institucion_trayecto', 'institucion_trayecto', $id);

        $this->dispatch('toast', variant: 'success', heading: 'Éxito', text: $row->activo ? 'Institución deshabilitada.' : 'Institución habilitada.');
    }
}; ?>

<div class="p-6 space-y-4">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <flux:heading size="lg">Instituciones — Trayecto Formativo</flux:heading>

        <flux:modal.trigger name="create-institucion-modal">
            <flux:button variant="primary" icon="plus">Nueva institución</flux:button>
        </flux:modal.trigger>
    </div>

    <flux:input wire:model.live.debounce.300ms="buscar" placeholder="Buscar por nombre o CUE..." class="max-w-sm" />

    <flux:table>
        <flux:table.columns>
            <flux:table.column>CUE</flux:table.column>
            <flux:table.column>Nombre</flux:table.column>
            <flux:table.column>Sector</flux:table.column>
            <flux:table.column>Ámbito</flux:table.column>
            <flux:table.column>Estado</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse($this->instituciones as $inst)
                <flux:table.row wire:key="inst-{{ $inst->id }}">
                    <flux:table.cell>{{ $inst->cue }}</flux:table.cell>
                    <flux:table.cell>{{ $inst->nombre }}</flux:table.cell>
                    <flux:table.cell>{{ $inst->sector ?? '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $inst->ambito ?? '—' }}</flux:table.cell>
                    <flux:table.cell>
                        @if($inst->activo)
                            <flux:badge color="green" size="sm">Activo</flux:badge>
                        @else
                            <flux:badge color="zinc" size="sm">Inactivo</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex gap-2">
                            <flux:button size="sm" wire:click="editar({{ $inst->id }})">Editar</flux:button>
                            <flux:button size="sm" variant="{{ $inst->activo ? 'danger' : 'primary' }}" wire:click="toggleActivo({{ $inst->id }})">
                                {{ $inst->activo ? 'Deshabilitar' : 'Habilitar' }}
                            </flux:button>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6">
                        <p class="text-center text-zinc-400 py-6">Todavía no hay instituciones cargadas.</p>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <div>
        {{ $this->instituciones->links() }}
    </div>

    {{-- Modal: crear --}}
    <flux:modal name="create-institucion-modal" class="md:w-[480px]">
        <div class="space-y-4">
            <flux:heading size="lg">Nueva institución</flux:heading>

            <flux:input wire:model="cue" label="CUE" />
            <flux:input wire:model="nombre" label="Nombre" />

            <flux:select wire:model="sector" label="Sector">
                <flux:select.option value="">—</flux:select.option>
                @foreach($sectores as $s)
                    <flux:select.option value="{{ $s }}">{{ $s }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="ambito" label="Ámbito">
                <flux:select.option value="">—</flux:select.option>
                @foreach($ambitos as $a)
                    <flux:select.option value="{{ $a }}">{{ $a }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" wire:click="crear">Guardar</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Modal: editar --}}
    <flux:modal name="edit-institucion-modal" class="md:w-[480px]">
        <div class="space-y-4">
            <flux:heading size="lg">Editar institución</flux:heading>

            <flux:input wire:model="editCue" label="CUE" />
            <flux:input wire:model="editNombre" label="Nombre" />

            <flux:select wire:model="editSector" label="Sector">
                <flux:select.option value="">—</flux:select.option>
                @foreach($sectores as $s)
                    <flux:select.option value="{{ $s }}">{{ $s }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="editAmbito" label="Ámbito">
                <flux:select.option value="">—</flux:select.option>
                @foreach($ambitos as $a)
                    <flux:select.option value="{{ $a }}">{{ $a }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" wire:click="guardarEdicion">Guardar</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
