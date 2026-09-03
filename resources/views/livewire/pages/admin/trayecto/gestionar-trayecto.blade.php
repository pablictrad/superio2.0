<?php
/**
 * gestionar-trayecto.blade.php
 *
 * Panel admin del Trayecto Formativo (equivalente a inscriptosTrayecto.blade.php
 * de Sage): listado con filtro por cohorte/nivel/estamento, búsqueda por DNI o
 * nombre, y ver/descargar/eliminar documentación.
 *
 * Sage no tenía workflow de aprobación de documentos para este módulo (a
 * diferencia de F2 de llamados), así que acá tampoco se agrega uno: solo se
 * lista, se puede ver/descargar el PDF y eliminarlo.
 */

use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Support\Auditoria;
use App\Support\TrayectoConfig;

new class extends Component {
    use WithPagination;

    public int    $cohorte         = 2025;
    public string $filtroNivel     = '';
    public string $filtroEstamento = '';
    public string $busqueda        = '';

    public bool $trayectoHabilitado = true;

    public function mount(): void
    {
        $this->cohorte           = (int) config('trayecto.cohorte_activa');
        $this->trayectoHabilitado = TrayectoConfig::habilitado();
    }

    /**
     * Prende/apaga la inscripción pública al Trayecto Formativo (botón en la
     * pantalla principal + acceso a /trayecto/registrar). No afecta datos ya
     * cargados, solo si se puede seguir inscribiendo.
     */
    public function toggleHabilitado(): void
    {
        $nuevoValor = !$this->trayectoHabilitado;

        TrayectoConfig::setHabilitado($nuevoValor);
        $this->trayectoHabilitado = $nuevoValor;

        Auditoria::registrar($nuevoValor ? 'habilitar_trayecto' : 'deshabilitar_trayecto', 'trayecto_config');

        $this->dispatch('toast', variant: 'success', heading: 'Éxito', text: $nuevoValor
            ? 'Inscripción al Trayecto Formativo habilitada.'
            : 'Inscripción al Trayecto Formativo deshabilitada.');
    }

    public function updatingCohorte(): void { $this->resetPage(); }
    public function updatingFiltroNivel(): void { $this->resetPage(); }
    public function updatingFiltroEstamento(): void { $this->resetPage(); }
    public function updatingBusqueda(): void { $this->resetPage(); }

    #[Computed(cache: false)]
    public function cohortesDisponibles()
    {
        return DB::table('tb_trayecto_formativo')
            ->select('cohorte')
            ->distinct()
            ->orderByDesc('cohorte')
            ->pluck('cohorte');
    }

    #[Computed(cache: false)]
    public function estamentosDisponibles()
    {
        return DB::table('tb_trayecto_formativo')
            ->where('cohorte', $this->cohorte)
            ->select('estamento')
            ->distinct()
            ->orderBy('estamento')
            ->pluck('estamento');
    }

    #[Computed(cache: false)]
    public function inscripciones()
    {
        return DB::table('tb_trayecto_formativo as t')
            ->leftJoin('tb_instituciones_trayecto as i', 'i.id', '=', 't.institucion_trayecto_id')
            ->where('t.cohorte', $this->cohorte)
            ->when($this->filtroNivel, fn ($q) => $q->where('t.nivel', $this->filtroNivel))
            ->when($this->filtroEstamento, fn ($q) => $q->where('t.estamento', $this->filtroEstamento))
            ->when(trim($this->busqueda) !== '', function ($q) {
                $b = '%' . trim($this->busqueda) . '%';
                $q->where(function ($sub) use ($b) {
                    $sub->where('t.dni', 'ilike', $b)
                        ->orWhere('t.apellido', 'ilike', $b)
                        ->orWhere('t.nombre', 'ilike', $b);
                });
            })
            ->select('t.*', 'i.nombre as institucion_nombre', 'i.cue as institucion_cue')
            ->orderBy('t.apellido')
            ->orderBy('t.nombre')
            ->paginate(15);
    }

    /**
     * Los documentos están replicados en todas las filas de un mismo (dni, cohorte)
     * (ver nota en publico/trayecto-formativo.blade.php). Al borrar una fila, los
     * archivos físicos solo se eliminan si no queda ninguna fila hermana que
     * todavía los referencie.
     */
    public function eliminarInscripcion(int $id): void
    {
        $fila = DB::table('tb_trayecto_formativo')->where('id', $id)->first();
        if (!$fila) {
            return;
        }

        DB::table('tb_trayecto_formativo')->where('id', $id)->delete();

        $quedanHermanas = DB::table('tb_trayecto_formativo')
            ->where('dni', $fila->dni)
            ->where('cohorte', $fila->cohorte)
            ->exists();

        if (!$quedanHermanas) {
            foreach (['f2_path', 'certificacion_servicio_path', 'concepto_path'] as $col) {
                if (!empty($fila->$col) && Storage::disk('public')->exists($fila->$col)) {
                    Storage::disk('public')->delete($fila->$col);
                }
            }
        }

        Auditoria::registrar('eliminar_trayecto', 'trayecto_formativo', $id, "DNI {$fila->dni} · cohorte {$fila->cohorte}");

        $this->dispatch('toast', variant: 'success', heading: 'Eliminado', text: 'Inscripción eliminada correctamente.');
    }

    /**
     * Los documentos pertenecen al DNI, no a una fila puntual: están replicados
     * en TODAS las filas de un mismo (dni, cohorte) (ver publico/trayecto-formativo.blade.php).
     * Por eso acá también se limpia por (dni, cohorte), no por id individual.
     */
    public function eliminarDocumento(int $id, string $tipo): void
    {
        $columna = "{$tipo}_path";
        $fila = DB::table('tb_trayecto_formativo')->where('id', $id)->first();

        if (!$fila || empty($fila->$columna)) {
            return;
        }

        if (Storage::disk('public')->exists($fila->$columna)) {
            Storage::disk('public')->delete($fila->$columna);
        }

        DB::table('tb_trayecto_formativo')
            ->where('dni', $fila->dni)
            ->where('cohorte', $fila->cohorte)
            ->update([$columna => null, 'updated_at' => now()]);

        Auditoria::registrar('eliminar_documento_trayecto', 'trayecto_formativo', $id, "{$tipo} (dni {$fila->dni}, cohorte {$fila->cohorte})");

        $this->dispatch('toast', variant: 'success', heading: 'Eliminado', text: 'Documento eliminado.');
    }

    /**
     * true solo si hay path guardado Y el archivo físico existe en storage.
     * Los 221 registros migrados de Sage (cohorte 2025) tienen path pero nunca
     * se copiaron los PDFs físicos a este entorno — este chequeo evita que el
     * listado ofrezca un link roto o rompa el render por un archivo faltante.
     */
    public function documentoExiste(?string $path): bool
    {
        return !empty($path) && Storage::disk('public')->exists($path);
    }
}; ?>

<div class="p-6 space-y-4">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div class="flex items-center gap-3">
            <flux:heading size="lg">Trayecto Formativo — Inscriptos</flux:heading>
            @if($trayectoHabilitado)
                <flux:badge color="green" size="sm">Inscripción pública habilitada</flux:badge>
            @else
                <flux:badge color="zinc" size="sm">Inscripción pública deshabilitada</flux:badge>
            @endif
        </div>

        <flux:button size="sm" variant="{{ $trayectoHabilitado ? 'danger' : 'primary' }}" wire:click="toggleHabilitado"
            wire:confirm="{{ $trayectoHabilitado ? '¿Deshabilitar la inscripción pública al Trayecto Formativo? El botón dejará de verse en la pantalla principal y no se podrá inscribir nadie más hasta que lo vuelvas a habilitar.' : '¿Habilitar la inscripción pública al Trayecto Formativo?' }}">
            {{ $trayectoHabilitado ? 'Deshabilitar inscripción pública' : 'Habilitar inscripción pública' }}
        </flux:button>
    </div>

    <div class="flex flex-wrap items-end gap-3">
        <div class="w-32">
            <flux:select wire:model.live="cohorte" label="Cohorte">
                @foreach($this->cohortesDisponibles as $c)
                    <flux:select.option value="{{ $c }}">{{ $c }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div class="w-48">
            <flux:select wire:model.live="filtroNivel" label="Nivel">
                <flux:select.option value="">(Todos)</flux:select.option>
                @foreach(config('trayecto.niveles') as $n)
                    <flux:select.option value="{{ $n }}">{{ $n }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div class="w-64">
            <flux:select wire:model.live="filtroEstamento" label="Estamento">
                <flux:select.option value="">(Todos)</flux:select.option>
                @foreach($this->estamentosDisponibles as $e)
                    <flux:select.option value="{{ $e }}">{{ $e }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div class="flex-1 min-w-[200px]">
            <flux:input wire:model.live.debounce.300ms="busqueda" placeholder="Buscar por DNI, apellido o nombre..." label="Búsqueda" />
        </div>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>DNI</flux:table.column>
            <flux:table.column>Apellido y Nombre</flux:table.column>
            <flux:table.column>Nivel</flux:table.column>
            <flux:table.column>Estamento</flux:table.column>
            <flux:table.column>Institución</flux:table.column>
            <flux:table.column>F2</flux:table.column>
            <flux:table.column>Cert. Servicio</flux:table.column>
            <flux:table.column>Concepto</flux:table.column>
            <flux:table.column>Teléfono</flux:table.column>
            <flux:table.column>Email</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse($this->inscripciones as $insc)
                <flux:table.row wire:key="trayecto-{{ $insc->id }}">
                    <flux:table.cell>{{ $insc->dni }}</flux:table.cell>
                    <flux:table.cell>{{ $insc->apellido }}, {{ $insc->nombre }}</flux:table.cell>
                    <flux:table.cell>{{ $insc->nivel }}</flux:table.cell>
                    <flux:table.cell>{{ $insc->estamento }}</flux:table.cell>
                    <flux:table.cell>
                        @if($insc->institucion_nombre)
                            {{ $insc->institucion_nombre }} <span class="text-xs text-zinc-400">(CUE {{ $insc->institucion_cue }})</span>
                        @else
                            <span class="text-zinc-400 text-xs">—</span>
                        @endif
                    </flux:table.cell>

                    @foreach(['f2' => 'f2_path', 'certificacion_servicio' => 'certificacion_servicio_path', 'concepto' => 'concepto_path'] as $tipo => $col)
                        <flux:table.cell>
                            @if(empty($insc->$col))
                                <span class="text-zinc-400 text-xs">—</span>
                            @elseif($this->documentoExiste($insc->$col))
                                <div class="flex items-center gap-2">
                                    <a href="{{ Storage::url($insc->$col) }}" target="_blank" class="text-indigo-600 text-xs font-bold">Ver</a>
                                    <button wire:click="eliminarDocumento({{ $insc->id }}, '{{ $tipo }}')"
                                        wire:confirm="¿Eliminar este documento?"
                                        class="text-red-600 text-xs font-bold">Borrar</button>
                                </div>
                            @else
                                <div class="flex items-center gap-2">
                                    <flux:badge color="amber" size="sm">Archivo no disponible (dato migrado)</flux:badge>
                                    <button wire:click="eliminarDocumento({{ $insc->id }}, '{{ $tipo }}')"
                                        wire:confirm="El archivo físico no existe en este entorno. ¿Quitar la referencia igual?"
                                        class="text-red-600 text-xs font-bold">Borrar</button>
                                </div>
                            @endif
                        </flux:table.cell>
                    @endforeach

                    <flux:table.cell>{{ $insc->telefono ?? '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $insc->email ?? '—' }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:button size="sm" variant="danger" wire:click="eliminarInscripcion({{ $insc->id }})" wire:confirm="¿Eliminar esta inscripción y sus documentos? Esta acción no se puede deshacer.">
                            Eliminar
                        </flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="11">
                        <p class="text-center text-zinc-400 py-6">No hay inscripciones para los filtros seleccionados.</p>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <div>
        {{ $this->inscripciones->links() }}
    </div>
</div>
