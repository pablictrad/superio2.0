<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use App\Support\Auditoria;

/**
 * gestionar-docentes.blade.php
 *
 * Listado de docentes (tabla real tb_docentes) + legajo digital con
 * revisión de documentación por ítem individual:
 *   - Domicilio (DNI + comprobante de zona) -> tb_domicilio.tipoestado_id
 *   - Títulos                                -> tb_docente_titulos.estado_id
 *   - Certificados                           -> tb_docente_certificados.estado_id
 *   - F2 (por cada inscripción a una convocatoria)  -> inscripciones_llamado.f2_estado_id
 *
 * Estados (tb_estado_documento): 1 Pendiente, 2 Aprobado, 3 Rechazado,
 * 4 Cambio de zona solicitado (solo domicilio), 5 Reemplazado (solo domicilio)
 */
new class extends Component {
    use WithPagination;

    public string $busqueda        = '';
    public bool   $soloPendientes  = false;

    public ?int  $docenteId    = null;
    public bool  $modalDetalle = false;

    // Rechazo inline: qué documento se está rechazando y por qué
    public ?string $rechazandoTipo = null; // 'titulo' | 'certificado' | 'domicilio' | 'f2'
    public ?int    $rechazandoId   = null;
    public string  $observacionRechazo = '';

    public function updatingBusqueda(): void
    {
        $this->resetPage();
    }

    public function updatingSoloPendientes(): void
    {
        $this->resetPage();
    }

    /* ═══════════════════════════════════════════════════════════════
       LISTADO
    ═══════════════════════════════════════════════════════════════ */
    #[Computed(cache: false)]
    public function docentes()
    {
        $query = DB::table('tb_docentes as d')
            ->leftJoin('tb_domicilio as dom', 'd.domicilio_id', '=', 'dom.idtb_domicilio')
            ->select(
                'd.id', 'd.dni', 'd.apellido', 'd.nombre', 'd.email', 'd.telefono',
                'd.tiene_legajo',
                'dom.tipoestado_id as domicilio_estado_id'
            )
            ->selectSub(function ($q) {
                $q->from('inscripciones_llamado as il')
                  ->selectRaw('COUNT(*)')
                  ->whereColumn('il.docente_id', 'd.id');
            }, 'total_inscripciones')
            ->selectSub(function ($q) {
                $q->from('inscripciones_llamado as il')
                  ->selectRaw('MAX(puntaje)')
                  ->whereColumn('il.docente_id', 'd.id');
            }, 'mejor_puntaje')
            ->selectSub(function ($q) {
                $q->from('tb_docente_titulos as t')
                  ->selectRaw('COUNT(*)')
                  ->whereColumn('t.docente_id', 'd.id')
                  ->where('t.estado_id', 1);
            }, 'titulos_pendientes')
            ->selectSub(function ($q) {
                $q->from('tb_docente_certificados as c')
                  ->selectRaw('COUNT(*)')
                  ->whereColumn('c.docente_id', 'd.id')
                  ->where('c.estado_id', 1);
            }, 'certificados_pendientes')
            ->selectSub(function ($q) {
                $q->from('inscripciones_llamado as il')
                  ->selectRaw('COUNT(*)')
                  ->whereColumn('il.docente_id', 'd.id')
                  ->where('il.f2_estado_id', 1);
            }, 'f2_pendientes');

        if ($this->busqueda) {
            $b = '%' . strtoupper($this->busqueda) . '%';
            $query->where(function ($q) use ($b) {
                $q->whereRaw('UPPER(d.dni) LIKE ?', [$b])
                  ->orWhereRaw('UPPER(d.apellido) LIKE ?', [$b])
                  ->orWhereRaw('UPPER(d.nombre) LIKE ?', [$b]);
            });
        }

      if ($this->soloPendientes) {
            $query->where(function ($q) {

                $q->whereRaw("
                    (
                        SELECT COUNT(*)
                        FROM tb_docente_titulos t
                        WHERE t.docente_id = d.id
                        AND t.estado_id = 1
                    ) > 0
                ")

                ->orWhereRaw("
                    (
                        SELECT COUNT(*)
                        FROM tb_docente_certificados c
                        WHERE c.docente_id = d.id
                        AND c.estado_id = 1
                    ) > 0
                ")

                ->orWhereRaw("
                    (
                        SELECT COUNT(*)
                        FROM inscripciones_llamado il
                        WHERE il.docente_id = d.id
                        AND il.f2_estado_id = 1
                    ) > 0
                ")

                ->orWhere('dom.tipoestado_id', 1)
                ->orWhere('dom.tipoestado_id', 3);
            });
        }

        return $query->orderBy('d.apellido')->paginate(15);
    }

    /* ═══════════════════════════════════════════════════════════════
       DETALLE DEL DOCENTE
    ═══════════════════════════════════════════════════════════════ */
    #[Computed(cache: false)]
    public function docente()
    {
        return $this->docenteId
            ? DB::table('tb_docentes')->where('id', $this->docenteId)->first()
            : null;
    }

    #[Computed(cache: false)]
    public function domicilios()
    {
        if (!$this->docenteId) {
            return collect();
        }

        return DB::table('tb_domicilio as dom')
            ->leftJoin('tb_localidades', 'dom.localidad_id', '=', 'tb_localidades.id')
            ->leftJoin('tb_departamentos', 'tb_localidades.iddepartamento', '=', 'tb_departamentos.iddepartamento')
            ->leftJoin('tb_estado_documento as est', 'dom.tipoestado_id', '=', 'est.id')
            ->where('dom.docente_id', $this->docenteId)
            ->select(
                'dom.*',
                'tb_localidades.localidad as localidad_nombre',
                'tb_localidades.zona_override',
                'tb_departamentos.zona as zona_departamento',
                'est.nombre as estado_nombre'
            )
            ->orderByDesc('dom.created_at')
            ->get();
    }

    #[Computed(cache: false)]
    public function titulos()
    {
        if (!$this->docenteId) {
            return collect();
        }

        return DB::table('tb_docente_titulos as t')
            ->leftJoin('tb_estado_documento as est', 't.estado_id', '=', 'est.id')
            ->where('t.docente_id', $this->docenteId)
            ->select('t.*', 'est.nombre as estado_nombre')
            ->orderBy('t.nombre_titulo')
            ->get();
    }

    #[Computed(cache: false)]
    public function certificados()
    {
        if (!$this->docenteId) {
            return collect();
        }

        return DB::table('tb_docente_certificados as c')
            ->leftJoin('tb_estado_documento as est', 'c.estado_id', '=', 'est.id')
            ->where('c.docente_id', $this->docenteId)
            ->select('c.*', 'est.nombre as estado_nombre')
            ->orderBy('c.nombre_certificado')
            ->get();
    }

    #[Computed(cache: false)]
    public function inscripciones()
    {
        if (!$this->docenteId) {
            return collect();
        }

        $inscripciones = DB::table('inscripciones_llamado as il')
            ->leftJoin('nuevo_llamado as l', 'il.llamado_id', '=', 'l.id')
            ->leftJoin('tipo_llamado as tl', 'l.idtipo_llamado', '=', 'tl.id')
            ->leftJoin('tb_zona as z', 'l.idtb_zona', '=', 'z.id')
            ->leftJoin('tb_tipoestado as te', 'l.idtb_tipoestado', '=', 'te.idtb_tipoestado')
            ->leftJoin('tb_estado_documento as est', 'il.f2_estado_id', '=', 'est.id')
            ->where('il.docente_id', $this->docenteId)
            ->select(
                'il.id', 'il.llamado_id', 'il.puntaje', 'il.estado', 'il.orden',
                'il.f2_path', 'il.f2_estado_id', 'il.f2_observacion_verificacion',
                'est.nombre as f2_estado_nombre',
                // AJUSTAR: si nuevo_llamado tiene columna propia de número/código, reemplazar aquí.
                'il.llamado_id as convocatoria_numero',
                'l.descripcion as convocatoria_nombre',
                'l.fecha_ini as convocatoria_fecha_ini',
                'l.fecha_fin as convocatoria_fecha_fin',
                'tl.nombre as convocatoria_tipo',
                'z.nombre_zona as convocatoria_zona',
                'te.nombre_tipoestado as convocatoria_estado_nombre'
            )
            ->orderByDesc('il.created_at')
            ->get();

        $llamadoIds = $inscripciones->pluck('llamado_id')->filter()->unique()->values();

        $cargosPorLlamado   = collect();
        $espaciosPorLlamado = collect();

        if ($llamadoIds->isNotEmpty()) {
            // AJUSTAR: confirmar el nombre exacto de la FK (aparece truncada en Navicat
            // como "nuevo_rel_in..."); se asume nuevo_rel_instituto_cargo_id.
            $cargosPorLlamado = DB::table('nuevo_cargo_por_llamado as ncl')
                ->join('nuevo_rel_instituto_cargo as ric', 'ncl.nuevo_rel_instituto_cargo_id', '=', 'ric.id')
                ->leftJoin('tb_instituto_superior as ins', 'ncl.instituto_id', '=', 'ins.id')
                ->leftJoin('tb_cargos as c', 'ric.cargo_id', '=', 'c.id')
                ->leftJoin('tb_turnos as t', 'ric.turno_id', '=', 't.id')
                ->whereIn('ncl.llamado_id', $llamadoIds)
                ->select(
                    'ncl.llamado_id',
                    'ins.nombre as instituto_nombre',
                    'c.nombre_cargo',
                    't.nombre_turno',
                    'ncl.horario_cargo'
                )
                ->get()
                ->groupBy('llamado_id');

            // AJUSTAR: ídem, se asume nuevo_rel_carrera_espacio_id como FK.
            $espaciosPorLlamado = DB::table('nuevo_espacios_por_llamado as nel')
                ->join('nuevo_rel_carrera_espacio as rce', 'nel.nuevo_rel_carrera_espacio_id', '=', 'rce.id')
                ->leftJoin('tb_instituto_superior as ins', 'nel.instituto_id', '=', 'ins.id')
                ->leftJoin('tb_carreras as car', 'rce.carrera_id', '=', 'car.id')
                ->leftJoin('tb_espacioscurriculares as esp', 'rce.espacio_id', '=', 'esp.idespaciocurricular')
                ->leftJoin('tb_turnos as t', 'rce.turno_id', '=', 't.id')
                ->whereIn('nel.llamado_id', $llamadoIds)
                ->select(
                    'nel.llamado_id',
                    'ins.nombre as instituto_nombre',
                    'car.nombre as carrera_nombre',
                    'esp.nombre_espacio',
                    't.nombre_turno',
                    'rce.anio',
                    'nel.horario_espacio'
                )
                ->get()
                ->groupBy('llamado_id');
        }

        return $inscripciones->map(function ($insc) use ($cargosPorLlamado, $espaciosPorLlamado) {
            $insc = is_array($insc) ? (object) $insc : $insc;
            $insc->cargos   = $cargosPorLlamado->get($insc->llamado_id, collect());
            $insc->espacios = $espaciosPorLlamado->get($insc->llamado_id, collect());
            return $insc;
        });
    }

    public function verDetalle(int $docenteId): void
    {
        $this->docenteId    = $docenteId;
        $this->modalDetalle = true;
        $this->cancelarRechazo();
    }

    public function cerrarModal(): void
    {
        $this->modalDetalle = false;
        $this->docenteId    = null;
        $this->cancelarRechazo();
    }

    /* ═══════════════════════════════════════════════════════════════
       REVISIÓN: aprobar / rechazar por ítem
    ═══════════════════════════════════════════════════════════════ */
    public function aprobarDocumento(string $tipo, int $id): void
    {
        $this->actualizarEstado($tipo, $id, 2, null); // 2 = Aprobado
    }

    public function iniciarRechazo(string $tipo, int $id): void
    {
        $this->rechazandoTipo    = $tipo;
        $this->rechazandoId      = $id;
        $this->observacionRechazo = '';
    }

    public function cancelarRechazo(): void
    {
        $this->rechazandoTipo     = null;
        $this->rechazandoId       = null;
        $this->observacionRechazo = '';
    }

    public function confirmarRechazo(): void
    {
        $this->validate([
            'observacionRechazo' => 'required|min:3|max:255',
        ], [
            'observacionRechazo.required' => 'Indicá el motivo del rechazo (ej: "DNI ilegible", "Título sin institución visible").',
            'observacionRechazo.min'      => 'El motivo debe tener al menos 3 caracteres.',
        ]);

        $this->actualizarEstado($this->rechazandoTipo, $this->rechazandoId, 3, $this->observacionRechazo); // 3 = Rechazado
        $this->cancelarRechazo();
    }

    private function actualizarEstado(string $tipo, int $id, int $estadoId, ?string $observacion): void
    {
        match ($tipo) {
            'titulo' => DB::table('tb_docente_titulos')->where('id', $id)->update([
                'estado_id'                => $estadoId,
                'observacion_verificacion' => $observacion,
                'verificado_at'            => now(),
            ]),
            'certificado' => DB::table('tb_docente_certificados')->where('id', $id)->update([
                'estado_id'                => $estadoId,
                'observacion_verificacion' => $observacion,
                'verificado_at'            => now(),
            ]),
            'domicilio' => DB::table('tb_domicilio')->where('idtb_domicilio', $id)->update([
                'tipoestado_id'             => $estadoId,
                'observacion_verificacion'  => $observacion,
                'verificado_at'             => now(),
            ]),
            'f2' => DB::table('inscripciones_llamado')->where('id', $id)->update([
                'f2_estado_id'                => $estadoId,
                'f2_observacion_verificacion' => $observacion,
                'f2_verificado_at'            => now(),
            ]),
            default => null,
        };

        $accion = match ($estadoId) {
            2 => "aprobar_{$tipo}",
            3 => "rechazar_{$tipo}",
            default => "actualizar_{$tipo}",
        };

        Auditoria::registrar($accion, $tipo, $id, $observacion);
    }
}; ?>

<div>
    <div class="mb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <flux:heading size="lg">Docentes</flux:heading>
        <div class="flex items-center gap-3">
            <flux:checkbox wire:model.live="soloPendientes" label="Solo con documentación pendiente" />
            <flux:input
                wire:model.live.debounce.400ms="busqueda"
                placeholder="Buscar por DNI o apellido..."
                icon="magnifying-glass"
                class="max-w-xs"
            />
        </div>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>DNI</flux:table.column>
            <flux:table.column>Apellido y Nombre</flux:table.column>
            <flux:table.column>Inscripciones</flux:table.column>
            <flux:table.column>Mejor puntaje</flux:table.column>
            <flux:table.column>Domicilio</flux:table.column>
            <flux:table.column>Pendientes</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($this->docentes as $docente)
                @php
                    $docente = is_array($docente) ? (object) $docente : $docente;
                    $totalPendientes = $docente->titulos_pendientes + $docente->certificados_pendientes + $docente->f2_pendientes;
                @endphp
                <flux:table.row wire:key="docente-{{ $docente->id }}">
                    <flux:table.cell>{{ $docente->dni }}</flux:table.cell>
                    <flux:table.cell>{{ $docente->apellido }}, {{ $docente->nombre }}</flux:table.cell>
                    <flux:table.cell>{{ $docente->total_inscripciones }}</flux:table.cell>
                    <flux:table.cell>{{ $docente->mejor_puntaje ?? '-' }}</flux:table.cell>
                    <flux:table.cell>
                        @if($docente->domicilio_estado_id == 2)
                            <flux:badge color="green" size="sm">Vigente</flux:badge>
                        @elseif($docente->domicilio_estado_id == 3)
                            <flux:badge color="red" size="sm">Rechazado</flux:badge>
                        @elseif($docente->domicilio_estado_id == 4)
                            <flux:badge color="amber" size="sm">Cambio solicitado</flux:badge>
                        @elseif($docente->domicilio_estado_id == 1)
                            <flux:badge color="zinc" size="sm">Pendiente</flux:badge>
                        @else
                            <span class="text-xs text-gray-300">—</span>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        @if($totalPendientes > 0)
                            <flux:badge color="amber" size="sm">{{ $totalPendientes }} por revisar</flux:badge>
                        @else
                            <span class="text-xs text-gray-300">—</span>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:button size="sm" wire:click="verDetalle({{ $docente->id }})">
                            Ver legajo
                        </flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <div class="mt-4">
        {{ $this->docentes->links() }}
    </div>

    {{-- ═══════════════════════════════════════════════════════
         MODAL DE DETALLE / LEGAJO
    ═══════════════════════════════════════════════════════ --}}
    <flux:modal wire:model="modalDetalle" class="max-w-4xl" name="detalle-docente">
        <div class="space-y-6">
            <flux:heading size="lg">Legajo del docente</flux:heading>

            @if ($this->docente)
                <div class="grid grid-cols-2 gap-2 text-sm">
                    <div><span class="font-semibold">DNI:</span> {{ $this->docente->dni }}</div>
                    <div><span class="font-semibold">Nombre:</span> {{ $this->docente->apellido }}, {{ $this->docente->nombre }}</div>
                    <div><span class="font-semibold">Email:</span> {{ $this->docente->email ?? '—' }}</div>
                    <div><span class="font-semibold">Teléfono:</span> {{ $this->docente->telefono ?? '—' }}</div>
                </div>

                <flux:separator />

                {{-- ── DOMICILIO ─────────────────────────────────────────── --}}
                <div>
                    <flux:heading size="sm" class="mb-2">Domicilio</flux:heading>
                    @forelse ($this->domicilios as $dom)
                        <div class="rounded-lg border p-3 mb-2 {{ $dom->tipoestado_id == 2 ? 'border-green-200 bg-green-50/30' : ($dom->tipoestado_id == 3 ? 'border-red-200 bg-red-50/30' : 'border-gray-200') }}">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium">
                                    {{ $dom->localidad_nombre ?? '—' }}
                                    @php $zonaTexto = $dom->zona_override ?: $dom->zona_departamento; @endphp
                                    @if($zonaTexto)
                                        <span class="text-xs text-gray-400">(Zona {{ $zonaTexto }})</span>
                                    @endif
                                </span>
                                <flux:badge size="sm" color="{{ match((int) $dom->tipoestado_id) {
                                    2 => 'green', 3 => 'red', 4 => 'amber', 5 => 'zinc', default => 'zinc',
                                } }}">
                                    {{ $dom->estado_nombre ?? 'Pendiente de verificación' }}
                                </flux:badge>
                            </div>

                            <p class="mt-1 text-xs text-gray-500">
                                @php
                                    $direccion = trim(implode(', ', array_filter([
                                        trim(($dom->calle ?? '') . ' ' . ($dom->numCasa_piso ?? '')),
                                        $dom->piso ? 'Piso/Depto ' . $dom->piso : null,
                                        $dom->barrio ? 'B° ' . $dom->barrio : null,
                                        $dom->manzana ? 'Mz ' . $dom->manzana : null,
                                    ])));
                                @endphp
                                {{ $direccion ?: 'Sin dirección detallada' }}
                            </p>

                            <div class="mt-2 flex flex-wrap gap-3 text-xs">
                                @if($dom->archivo_dni)
                                    <a href="{{ asset('storage/'.$dom->archivo_dni) }}" target="_blank" class="text-indigo-600 underline">Ver DNI</a>
                                @endif
                                @if($dom->archivo_factura)
                                    <a href="{{ asset('storage/'.$dom->archivo_factura) }}" target="_blank" class="text-indigo-600 underline">Ver factura</a>
                                @endif
                                @if($dom->archivo_certifdomicilio)
                                    <a href="{{ asset('storage/'.$dom->archivo_certifdomicilio) }}" target="_blank" class="text-indigo-600 underline">Ver certificado</a>
                                @endif
                            </div>

                            @if($dom->observacion_verificacion)
                                <p class="mt-1 text-xs text-red-600 italic">Motivo: {{ $dom->observacion_verificacion }}</p>
                            @endif

                            @if(in_array((int) $dom->tipoestado_id, [1, 4]))
                                @if($rechazandoTipo === 'domicilio' && $rechazandoId === $dom->idtb_domicilio)
                                    <div class="mt-2 flex gap-2 items-start">
                                        <flux:input wire:model="observacionRechazo" placeholder="Motivo del rechazo..." class="flex-1" size="sm" />
                                        <flux:button size="sm" variant="danger" wire:click="confirmarRechazo">Confirmar</flux:button>
                                        <flux:button size="sm" variant="ghost" wire:click="cancelarRechazo">Cancelar</flux:button>
                                    </div>
                                    @error('observacionRechazo') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                                @else
                                    <div class="mt-2 flex gap-2">
                                        <flux:button size="sm" variant="primary" wire:click="aprobarDocumento('domicilio', {{ $dom->idtb_domicilio }})">Aprobar</flux:button>
                                        <flux:button size="sm" variant="danger" wire:click="iniciarRechazo('domicilio', {{ $dom->idtb_domicilio }})">Rechazar</flux:button>
                                    </div>
                                @endif
                            @endif
                        </div>
                    @empty
                        <p class="text-xs text-gray-400 italic">Sin domicilio cargado.</p>
                    @endforelse
                </div>

                <flux:separator />

                {{-- ── TÍTULOS ───────────────────────────────────────────── --}}
                <div>
                    <flux:heading size="sm" class="mb-2">Títulos</flux:heading>
                    @forelse ($this->titulos as $tit)
                        <div class="rounded-lg border p-3 mb-2 {{ $tit->estado_id == 2 ? 'border-green-200 bg-green-50/30' : ($tit->estado_id == 3 ? 'border-red-200 bg-red-50/30' : 'border-gray-200') }}">
                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="text-sm font-medium">{{ $tit->nombre_titulo }}</span>
                                    <span class="text-xs text-gray-400">{{ $tit->institucion ? '· '.$tit->institucion : '' }} {{ $tit->anio_egreso ? '('.$tit->anio_egreso.')' : '' }}</span>
                                </div>
                                <flux:badge size="sm" color="{{ match((int) $tit->estado_id) { 2 => 'green', 3 => 'red', default => 'zinc' } }}">
                                    {{ $tit->estado_nombre ?? 'Pendiente de verificación' }}
                                </flux:badge>
                            </div>

                            @if($tit->archivo_path)
                                <a href="{{ asset('storage/'.$tit->archivo_path) }}" target="_blank" class="text-xs text-indigo-600 underline">Ver archivo</a>
                            @endif

                            @if($tit->observacion_verificacion)
                                <p class="mt-1 text-xs text-red-600 italic">Motivo: {{ $tit->observacion_verificacion }}</p>
                            @endif

                            @if($tit->estado_id == 1)
                                @if($rechazandoTipo === 'titulo' && $rechazandoId === $tit->id)
                                    <div class="mt-2 flex gap-2 items-start">
                                        <flux:input wire:model="observacionRechazo" placeholder="Motivo del rechazo..." class="flex-1" size="sm" />
                                        <flux:button size="sm" variant="danger" wire:click="confirmarRechazo">Confirmar</flux:button>
                                        <flux:button size="sm" variant="ghost" wire:click="cancelarRechazo">Cancelar</flux:button>
                                    </div>
                                    @error('observacionRechazo') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                                @else
                                    <div class="mt-2 flex gap-2">
                                        <flux:button size="sm" variant="primary" wire:click="aprobarDocumento('titulo', {{ $tit->id }})">Aprobar</flux:button>
                                        <flux:button size="sm" variant="danger" wire:click="iniciarRechazo('titulo', {{ $tit->id }})">Rechazar</flux:button>
                                    </div>
                                @endif
                            @endif
                        </div>
                    @empty
                        <p class="text-xs text-gray-400 italic">Sin títulos cargados.</p>
                    @endforelse
                </div>

                <flux:separator />

                {{-- ── CERTIFICADOS ──────────────────────────────────────── --}}
                <div>
                    <flux:heading size="sm" class="mb-2">Certificados</flux:heading>
                    @forelse ($this->certificados as $cert)
                        <div class="rounded-lg border p-3 mb-2 {{ $cert->estado_id == 2 ? 'border-green-200 bg-green-50/30' : ($cert->estado_id == 3 ? 'border-red-200 bg-red-50/30' : 'border-gray-200') }}">
                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="text-sm font-medium">{{ $cert->nombre_certificado }}</span>
                                    <span class="text-xs text-gray-400">{{ $cert->tipo ? '· '.$cert->tipo : '' }}</span>
                                </div>
                                <flux:badge size="sm" color="{{ match((int) $cert->estado_id) { 2 => 'green', 3 => 'red', default => 'zinc' } }}">
                                    {{ $cert->estado_nombre ?? 'Pendiente de verificación' }}
                                </flux:badge>
                            </div>

                            @if($cert->archivo_path)
                                <a href="{{ asset('storage/'.$cert->archivo_path) }}" target="_blank" class="text-xs text-indigo-600 underline">Ver archivo</a>
                            @endif

                            @if($cert->observacion_verificacion)
                                <p class="mt-1 text-xs text-red-600 italic">Motivo: {{ $cert->observacion_verificacion }}</p>
                            @endif

                            @if($cert->estado_id == 1)
                                @if($rechazandoTipo === 'certificado' && $rechazandoId === $cert->id)
                                    <div class="mt-2 flex gap-2 items-start">
                                        <flux:input wire:model="observacionRechazo" placeholder="Motivo del rechazo..." class="flex-1" size="sm" />
                                        <flux:button size="sm" variant="danger" wire:click="confirmarRechazo">Confirmar</flux:button>
                                        <flux:button size="sm" variant="ghost" wire:click="cancelarRechazo">Cancelar</flux:button>
                                    </div>
                                    @error('observacionRechazo') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                                @else
                                    <div class="mt-2 flex gap-2">
                                        <flux:button size="sm" variant="primary" wire:click="aprobarDocumento('certificado', {{ $cert->id }})">Aprobar</flux:button>
                                        <flux:button size="sm" variant="danger" wire:click="iniciarRechazo('certificado', {{ $cert->id }})">Rechazar</flux:button>
                                    </div>
                                @endif
                            @endif
                        </div>
                    @empty
                        <p class="text-xs text-gray-400 italic">Sin certificados cargados.</p>
                    @endforelse
                </div>

                <flux:separator />

                {{-- ── HISTORIAL DE INSCRIPCIONES A CONVOCATORIAS + F2 ──────── --}}
                <div>
                    <flux:heading size="sm" class="mb-2">Historial de convocatorias y F2</flux:heading>
                    @forelse ($this->inscripciones as $insc)
                        <div class="rounded-lg border p-3 mb-2 {{ $insc->f2_estado_id == 2 ? 'border-green-200 bg-green-50/30' : ($insc->f2_estado_id == 3 ? 'border-red-200 bg-red-50/30' : 'border-gray-200') }}">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium">
                                    {{ $insc->convocatoria_nombre ?? 'Convocatoria #'.$insc->convocatoria_numero }}
                                    <span class="text-xs text-gray-400">N° {{ $insc->convocatoria_numero }}</span>
                                </span>
                                <flux:badge size="sm" color="{{ $insc->puntaje ? 'green' : 'zinc' }}">
                                    Puntaje: {{ $insc->puntaje ?? 'Sin calificar' }}
                                </flux:badge>
                            </div>

                            <div class="mt-1 flex flex-wrap gap-2 text-xs text-gray-500">
                                @if($insc->convocatoria_tipo)
                                    <span class="px-1.5 py-0.5 rounded bg-gray-100">{{ $insc->convocatoria_tipo }}</span>
                                @endif
                                @if($insc->convocatoria_zona)
                                    <span class="px-1.5 py-0.5 rounded bg-slate-100">Zona {{ $insc->convocatoria_zona }}</span>
                                @endif
                                @if($insc->convocatoria_estado_nombre)
                                    <span class="px-1.5 py-0.5 rounded bg-blue-50 text-blue-600">{{ $insc->convocatoria_estado_nombre }}</span>
                                @endif
                                @if($insc->estado)
                                    <span class="px-1.5 py-0.5 rounded {{ $insc->estado === 'habilitado' ? 'bg-green-50 text-green-600' : ($insc->estado === 'sin_clasificar' ? 'bg-red-50 text-red-600' : 'bg-amber-50 text-amber-600') }}">
                                        {{ ucfirst(str_replace('_', ' ', $insc->estado)) }}
                                    </span>
                                @endif
                                @if($insc->orden)
                                    <span class="px-1.5 py-0.5 rounded bg-gray-100">Orden: {{ $insc->orden }}</span>
                                @endif
                            </div>

                            @if($insc->convocatoria_fecha_ini || $insc->convocatoria_fecha_fin)
                                <p class="mt-1 text-[11px] text-gray-400">
                                    {{ $insc->convocatoria_fecha_ini ? \Carbon\Carbon::parse($insc->convocatoria_fecha_ini)->format('d/m/Y') : '—' }}
                                    al
                                    {{ $insc->convocatoria_fecha_fin ? \Carbon\Carbon::parse($insc->convocatoria_fecha_fin)->format('d/m/Y H:i') : '—' }}
                                </p>
                            @endif

                            {{-- ── Cargos de la convocatoria (institutos, turno) ────── --}}
                            @if($insc->cargos->isNotEmpty())
                                <div class="mt-2 space-y-1">
                                    @foreach($insc->cargos as $cargo)
                                        <div class="text-xs text-gray-600 bg-gray-50 rounded px-2 py-1">
                                            <span class="font-medium">{{ $cargo->nombre_cargo ?? 'Cargo sin definir' }}</span>
                                            @if($cargo->instituto_nombre)
                                                · {{ $cargo->instituto_nombre }}
                                            @endif
                                            @if($cargo->nombre_turno)
                                                · Turno {{ $cargo->nombre_turno }}
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            {{-- ── Espacios curriculares de la convocatoria (carrera, turno) ── --}}
                            @if($insc->espacios->isNotEmpty())
                                <div class="mt-2 space-y-1">
                                    @foreach($insc->espacios as $esp)
                                        <div class="text-xs text-gray-600 bg-gray-50 rounded px-2 py-1">
                                            <span class="font-medium">{{ $esp->nombre_espacio ?? 'Espacio sin definir' }}</span>
                                            @if($esp->carrera_nombre)
                                                · {{ $esp->carrera_nombre }}
                                            @endif
                                            @if($esp->instituto_nombre)
                                                · {{ $esp->instituto_nombre }}
                                            @endif
                                            @if($esp->nombre_turno)
                                                · Turno {{ $esp->nombre_turno }}
                                            @endif
                                            @if($esp->anio)
                                                · {{ $esp->anio }}° año
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            <div class="mt-2 flex items-center gap-2">
                                <span class="text-xs text-gray-500">F2:</span>
                                @if($insc->f2_path)
                                    <a href="{{ asset('storage/'.$insc->f2_path) }}" target="_blank" class="text-xs text-indigo-600 underline">Ver archivo</a>
                                @endif
                                <flux:badge size="sm" color="{{ match((int) $insc->f2_estado_id) { 2 => 'green', 3 => 'red', default => 'zinc' } }}">
                                    {{ $insc->f2_estado_nombre ?? 'Pendiente de verificación' }}
                                </flux:badge>
                            </div>

                            @if($insc->f2_observacion_verificacion)
                                <p class="mt-1 text-xs text-red-600 italic">Motivo: {{ $insc->f2_observacion_verificacion }}</p>
                            @endif

                            @if($insc->f2_estado_id == 1)
                                @if($rechazandoTipo === 'f2' && $rechazandoId === $insc->id)
                                    <div class="mt-2 flex gap-2 items-start">
                                        <flux:input wire:model="observacionRechazo" placeholder="Motivo del rechazo..." class="flex-1" size="sm" />
                                        <flux:button size="sm" variant="danger" wire:click="confirmarRechazo">Confirmar</flux:button>
                                        <flux:button size="sm" variant="ghost" wire:click="cancelarRechazo">Cancelar</flux:button>
                                    </div>
                                    @error('observacionRechazo') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                                @else
                                    <div class="mt-2 flex gap-2">
                                        <flux:button size="sm" variant="primary" wire:click="aprobarDocumento('f2', {{ $insc->id }})">Aprobar</flux:button>
                                        <flux:button size="sm" variant="danger" wire:click="iniciarRechazo('f2', {{ $insc->id }})">Rechazar</flux:button>
                                    </div>
                                @endif
                            @endif
                        </div>
                    @empty
                        <p class="text-xs text-gray-400 italic">Sin inscripciones a convocatorias registradas.</p>
                    @endforelse
                </div>
            @endif

            <div class="flex justify-end">
                <flux:button wire:click="cerrarModal" variant="ghost">Cerrar</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
