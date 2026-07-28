<?php
/**
 * gestionar-inscriptos.blade.php
 *
 * Panel de administración de inscriptos por llamado.
 * Permite al admin:
 *   - Ver listado de inscriptos
 *   - Cambiar estado (pendiente / habilitado / sin_clasificar)
 *   - Asignar puntaje y orden
 *   - Editar observaciones
 *   - Disparar la generación del PDF
 *
 * Se incluye en crear.blade.php dentro del modal de edición o como
 * panel propio accesible desde la columna Acciones del historial.
 *
 * Uso: @livewire('gestionar-inscriptos', ['llamadoId' => $item->id])
 */
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\DB;


new class extends Component {

    public int    $llamadoId     = 0;
    public bool   $modalAbierto  = false;
    public $llamadoInfo          = null;

    // Lista de inscriptos del llamado actual
    public array  $inscriptos    = [];

    // Edición inline
    public ?int   $editandoId    = null;
    public string $editEstado    = 'pendiente';
    public string $editPuntaje   = '';
    public string $editOrden     = '';
    public string $editObs       = '';

    // Mensaje de estado
    public string $mensaje       = '';
    public string $mensajeTipo   = ''; // 'ok' | 'err'
    // Estado del LOM asociado a este llamado
    public bool $lomExiste     = false;
    public bool $lomPublicado  = false;

    // Modal de documentación del inscripto
    public bool  $docModalAbierto = false;
    public ?int  $docInscriptoId  = null;
    public $docInscripto          = null;
    public array $docTitulos      = [];
    public array $docCertificados = [];
    public $docDomicilio          = null; // fila de tb_domicilio del docente (DNI, factura, certificado, localidad)

    /* ── ABRIR PANEL ──────────────────────────────────────────────── */
  
    #[On('abrirPanel')]
    public function abrirPanel(int $id): void
    {
         \Log::info('abrirPanel recibido', ['id' => $id]);  // ← temporal
        $this->llamadoId  = $id;
        $this->modalAbierto = true;
        $this->mensaje    = '';
        $this->editandoId = null;
        $this->cargarInscriptos();
        $this->cargarInfoLlamado();
        $this->cargarInfoLom();
    }

    private function cargarInfoLlamado(): void
    {
        $this->llamadoInfo = DB::table('nuevo_llamado')
            ->leftJoin('tipo_llamado', 'nuevo_llamado.idtipo_llamado', '=', 'tipo_llamado.id')
            ->leftJoin('tb_zona',      'nuevo_llamado.idtb_zona',      '=', 'tb_zona.id')
            ->where('nuevo_llamado.id', $this->llamadoId)
            ->select(
                'nuevo_llamado.id',
                'tipo_llamado.nombre as tipo_nombre',
                'tb_zona.nombre_zona',
                'nuevo_llamado.idtb_tipoestado'
            )
            ->first();
    }
    private function cargarInfoLom(): void
    {
        $lom = DB::table('tb_lom')->where('llamado_id', $this->llamadoId)->first();

        $this->lomExiste    = (bool) $lom;
        $this->lomPublicado = $lom ? ((int) $lom->idtb_tipoestado === 8) : false;
    }

    private function cargarInscriptos(): void
    {
        $rows = DB::table('inscripciones_llamado')
            ->where('llamado_id', $this->llamadoId)
            ->orderByRaw("
            CASE estado
                WHEN 'habilitado' THEN 1
                WHEN 'pendiente' THEN 2
                WHEN 'sin_clasificar' THEN 3
                ELSE 4
            END
        ")
            ->orderByDesc('puntaje')
            ->orderBy('apellido')
            ->get();

        // Enriquecer con zona real del docente (para auditar el filtro aplicado en la inscripción)
        foreach ($rows as $ins) {
            $ins->zona_texto = null;

            if (!empty($ins->docente_id)) {
                $domicilioId = DB::table('tb_docentes')->where('id', $ins->docente_id)->value('domicilio_id');
                if ($domicilioId) {
                    $localidadId = DB::table('tb_domicilio')->where('idtb_domicilio', $domicilioId)->value('localidad_id');
                    if ($localidadId) {
                        $ins->zona_texto = $this->resolverZonaTextoDeLocalidad((int) $localidadId);
                    }
                }
            }
        }


        $this->inscriptos = $rows
        ->map(fn ($item) => (array) $item)
        ->values()
        ->all();
        }

    /* ── ZONA: resolver texto (ej. "IV") desde localidad, con excepción por localidad ── */
    private function resolverZonaTextoDeLocalidad(int $localidadId): ?string
    {
        $localidad = DB::table('tb_localidades')->where('id', $localidadId)->first();
        if (!$localidad) {
            return null;
        }

        return $localidad->zona_override
            ?: DB::table('tb_departamentos')->where('iddepartamento', $localidad->iddepartamento)->value('zona');
    }

    /* ── INICIAR EDICIÓN INLINE ───────────────────────────────────── */
    public function editarInscripto(int $id): void
    {
        $ins = collect($this->inscriptos)->firstWhere('id', $id);
        if (!$ins) return;

        $ins = (object) $ins;
        $this->editandoId  = $id;
        $this->editEstado  = $ins->estado;
        $this->editPuntaje = $ins->puntaje  ?? '';
        $this->editOrden   = $ins->orden    ?? '';
        $this->editObs     = $ins->observaciones ?? '';
    }

    /* ── GUARDAR EDICIÓN ──────────────────────────────────────────── */
    public function guardarEdicion(): void
    {
        $this->validate([
            'editEstado'  => 'required|in:pendiente,habilitado,sin_clasificar',
            'editPuntaje' => 'nullable|numeric|min:0|max:100',
            'editOrden'   => 'nullable|integer|min:1',
            'editObs'     => 'nullable|max:500',
        ], [
            'editEstado.in'       => 'Estado no válido.',
            'editPuntaje.numeric' => 'El puntaje debe ser un número.',
            'editPuntaje.max'     => 'El puntaje máximo es 100.',
        ]);

        DB::table('inscripciones_llamado')
            ->where('id', $this->editandoId)
            ->update([
                'estado'        => $this->editEstado,
                'puntaje'       => $this->editPuntaje !== '' ? (float) $this->editPuntaje : null,
                'orden'         => $this->editOrden   !== '' ? (int)   $this->editOrden   : null,
                'observaciones' => trim($this->editObs) ?: null,
                'updated_at'    => now(),
            ]);

        $this->editandoId = null;
        $this->setMensaje('ok', 'Inscripto actualizado correctamente.');
        $this->cargarInscriptos();
    }

    /* ── CANCELAR EDICIÓN ─────────────────────────────────────────── */
    public function cancelarEdicion(): void
    {
        $this->editandoId = null;
    }

    /* ── ELIMINAR INSCRIPTO ───────────────────────────────────────── */
    public function eliminarInscripto(int $id): void
    {
        DB::table('inscripciones_llamado')->where('id', $id)->delete();
        $this->setMensaje('ok', 'Inscripto eliminado.');
        $this->cargarInscriptos();
    }

    /* ── VER DOCUMENTACIÓN DEL INSCRIPTO ──────────────────────────── */
    public function verDocumentacion(int $id): void
    {
        $ins = collect($this->inscriptos)->firstWhere('id', $id);
        if (!$ins) return;

        $this->docInscripto    = (object) $ins;
        $this->docInscriptoId  = $id;
        $this->docTitulos      = [];
        $this->docCertificados = [];

        if (!empty($this->docInscripto->docente_id)) {
            $this->docTitulos = DB::table('tb_docente_titulos')
                ->where('docente_id', $this->docInscripto->docente_id)
                ->orderBy('nombre_titulo')
                ->get()
                ->toArray();

            $this->docCertificados = DB::table('tb_docente_certificados')
                ->where('docente_id', $this->docInscripto->docente_id)
                ->orderBy('nombre_certificado')
                ->get()
                ->toArray();

            $domicilioId = DB::table('tb_docentes')
                ->where('id', $this->docInscripto->docente_id)
                ->value('domicilio_id');

            $this->docDomicilio = $domicilioId
                ? DB::table('tb_domicilio')
                    ->leftJoin('tb_localidades', 'tb_domicilio.localidad_id', '=', 'tb_localidades.id')
                    ->leftJoin('tb_departamentos', 'tb_localidades.iddepartamento', '=', 'tb_departamentos.iddepartamento')
                    ->where('tb_domicilio.idtb_domicilio', $domicilioId)
                    ->select(
                        'tb_domicilio.*',
                        'tb_localidades.localidad as localidad_nombre',
                        'tb_localidades.zona_override',
                        'tb_departamentos.zona as zona_departamento'
                    )
                    ->first()
                : null;
        } else {
            $this->docDomicilio = null;
        }

        $this->docModalAbierto = true;
    }

    public function cerrarDocModal(): void
    {
        $this->docModalAbierto = false;
        $this->docInscriptoId  = null;
        $this->docInscripto    = null;
        $this->docTitulos      = [];
        $this->docCertificados = [];
        $this->docDomicilio    = null;
    }

    /* ── CERRAR PANEL ─────────────────────────────────────────────── */
    public function cerrarPanel(): void
    {
        $this->modalAbierto = false;
        $this->editandoId   = null;
    }
    public function publicarLom(): void
    {
        $lom = DB::table('tb_lom')->where('llamado_id', $this->llamadoId)->first();

        if (!$lom) {
            $this->setMensaje('err', 'Todavía no se generó el LOM para este llamado. Creá el LOM primero.');
            return;
        }

        if ($this->stats['habilitados'] + $this->stats['sin_clasificar'] === 0) {
            $this->setMensaje('err', 'No hay inscriptos Habilitados ni Sin Clasificar para publicar.');
            return;
        }

        DB::table('tb_lom')
            ->where('idtb_lom', $lom->idtb_lom)
            ->update(['idtb_tipoestado' => 8]);

        $this->lomPublicado = true;
        $this->setMensaje('ok', 'LOM publicado correctamente. Ya está disponible en el listado público.');
    }

    /* ── HELPER MENSAJE ───────────────────────────────────────────── */
    private function setMensaje(string $tipo, string $texto): void
    {
        $this->mensajeTipo = $tipo;
        $this->mensaje     = $texto;
    }

    /* ── ESTADÍSTICAS RÁPIDAS ─────────────────────────────────────── */
    public function getStatsProperty(): array
    {
        $todos  = collect($this->inscriptos);
        return [
            'total'         => $todos->count(),
            'habilitados'   => $todos->where('estado', 'habilitado')->count(),
            'pendientes'    => $todos->where('estado', 'pendiente')->count(),
            'sin_clasificar'=> $todos->where('estado', 'sin_clasificar')->count(),
        ];
    }
};
?>

<div>
    {{-- ╔══════════════════════════════════════════════════════════════╗
         ║  PANEL MODAL DE GESTIÓN DE INSCRIPTOS                       ║
         ╚══════════════════════════════════════════════════════════════╝ --}}
    @if($modalAbierto)
    <div
        class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 backdrop-blur-sm"
        x-data
        x-on:keydown.escape.window="$wire.cerrarPanel()"
    >
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-5xl mx-4 flex flex-col max-h-[92vh]">

            {{-- ── HEADER ────────────────────────────────────────────── --}}
            <div class="flex items-center justify-between px-6 py-4 border-b shrink-0 bg-slate-800 rounded-t-2xl">
                <div>
                    <h2 class="text-base font-black text-white uppercase tracking-tight">
                        Gestión de Inscriptos
                    </h2>
                    @if($llamadoInfo)
                    <p class="text-xs text-slate-300 mt-0.5">
                        Llamado #{{ $llamadoInfo->id }}
                        @if($llamadoInfo->tipo_nombre) · {{ $llamadoInfo->tipo_nombre }} @endif
                        @if($llamadoInfo->nombre_zona)  · {{ $llamadoInfo->nombre_zona }}  @endif
                    </p>
                    @endif
                    Gestión de Inscriptos
                </div>
                <div class="flex items-center gap-3">
                   
                    <button
                        wire:click="cerrarPanel"
                        class="text-slate-400 hover:text-white hover:bg-slate-700 rounded-full p-1.5 transition"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>

            {{-- ── ESTADÍSTICAS ───────────────────────────────────────── --}}
            <div class="grid grid-cols-4 divide-x divide-gray-100 border-b shrink-0">
                <div class="px-5 py-3 text-center">
                    <div class="text-2xl font-black text-gray-800">{{ $this->stats['total'] }}</div>
                    <div class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Total</div>
                </div>
                <div class="px-5 py-3 text-center">
                    <div class="text-2xl font-black text-green-700">{{ $this->stats['habilitados'] }}</div>
                    <div class="text-[10px] font-bold text-green-400 uppercase tracking-widest">Habilitados</div>
                </div>
                <div class="px-5 py-3 text-center">
                    <div class="text-2xl font-black text-amber-600">{{ $this->stats['pendientes'] }}</div>
                    <div class="text-[10px] font-bold text-amber-400 uppercase tracking-widest">Pendientes</div>
                </div>
                <div class="px-5 py-3 text-center">
                    <div class="text-2xl font-black text-red-600">{{ $this->stats['sin_clasificar'] }}</div>
                    <div class="text-[10px] font-bold text-red-300 uppercase tracking-widest">Sin Clasificar</div>
                </div>
            </div>

            {{-- ── MENSAJE FLASH ──────────────────────────────────────── --}}
            @if($mensaje)
            <div class="mx-6 mt-4 shrink-0 px-4 py-2.5 rounded-lg text-sm font-semibold flex items-center gap-2
                {{ $mensajeTipo === 'ok' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200' }}">
                @if($mensajeTipo === 'ok')
                    <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                @else
                    <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                @endif
                {{ $mensaje }}
            </div>
            @endif

            {{-- ── TABLA DE INSCRIPTOS ────────────────────────────────── --}}
            <div class="overflow-y-auto flex-1 px-6 pb-6">
                @if(empty($inscriptos))
                    <div class="flex flex-col items-center justify-center py-16 text-center">
                        <svg class="w-12 h-12 text-gray-200 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        <p class="text-gray-400 font-bold uppercase text-sm tracking-widest">Sin inscriptos aún</p>
                        <p class="text-gray-300 text-xs mt-1">Las inscripciones aparecerán aquí cuando los docentes se postulen.</p>
                    </div>
                @else
                <table class="w-full text-sm mt-4">
                    <thead>
                        <tr class="bg-gray-50 border-y border-gray-200">
                            <th class="px-3 py-2.5 text-left text-[10px] font-black uppercase text-gray-500 tracking-wider w-8">Ord.</th>
                            <th class="px-3 py-2.5 text-left text-[10px] font-black uppercase text-gray-500 tracking-wider">Apellido y Nombre</th>
                            <th class="px-3 py-2.5 text-left text-[10px] font-black uppercase text-gray-500 tracking-wider w-24">DNI</th>
                            <th class="px-3 py-2.5 text-left text-[10px] font-black uppercase text-gray-500 tracking-wider w-28">Teléfono</th>
                            <th class="px-3 py-2.5 text-left text-[10px] font-black uppercase text-gray-500 tracking-wider">Email</th>
                              <th class="px-3 py-2.5 text-left text-[10px] font-black uppercase text-gray-500 tracking-wider">Domicilio</th>
                            <th class="px-3 py-2.5 text-left text-[10px] font-black uppercase text-gray-500 tracking-wider w-24">Localidad / Zona</th>
                            <th class="px-3 py-2.5 text-center text-[10px] font-black uppercase text-gray-500 tracking-wider w-24">Puntaje</th>
                            <th class="px-3 py-2.5 text-center text-[10px] font-black uppercase text-gray-500 tracking-wider w-28">Estado</th>
                            <th class="px-3 py-2.5 text-center text-[10px] font-black uppercase text-gray-500 tracking-wider w-20">Acc.</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($inscriptos as $ins)
                        @php $ins = (object)$ins; @endphp
                        <tr wire:key="ins-{{ $ins->id }}"
                            class="{{ $editandoId === $ins->id ? 'bg-yellow-50' : 'hover:bg-slate-50' }} transition">

                            @if($editandoId === $ins->id)
                            {{-- FILA EN MODO EDICIÓN --}}
                            <td class="px-3 py-2 text-center">
                                <input wire:model="editOrden" type="number" min="1"
                                    class="w-14 border border-gray-300 rounded px-1 py-1 text-xs text-center">
                            </td>
                            <td class="px-3 py-2">
                                <div class="font-bold text-gray-800 text-xs">{{ $ins->apellido }} {{ $ins->nombre }}</div>
                                <input wire:model="editObs" type="text" placeholder="Observaciones..."
                                    class="mt-1 w-full border border-gray-300 rounded px-2 py-1 text-xs text-gray-600">
                            </td>
                            <td class="px-3 py-2 text-xs text-gray-600">{{ $ins->dni }}</td>
                            <td class="px-3 py-2 text-xs text-gray-500">{{ $ins->telefono ?? '—' }}</td>
                            <td class="px-3 py-2 text-xs text-gray-500">{{ $ins->email ?? '—' }}</td>
                            <td class="px-3 py-2 text-xs text-gray-500">{{ $ins->domicilio ?? '—' }}</td>
                            <td class="px-3 py-2 text-xs">
                                <div class="text-gray-600">{{ $ins->localidad ?? '—' }}</div>
                                @if($ins->zona_texto)
                                    <span class="text-[9px] px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 font-bold uppercase">Zona {{ $ins->zona_texto }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-center">
                                <input wire:model="editPuntaje" type="number" step="0.01" min="0" max="100"
                                    placeholder="0.00"
                                    class="w-20 border border-gray-300 rounded px-2 py-1 text-xs text-center">
                                @error('editPuntaje') <p class="text-red-500 text-[9px] mt-0.5">{{ $message }}</p> @enderror
                            </td>
                            <td class="px-3 py-2 text-center">
                                <select wire:model="editEstado"
                                    class="border border-gray-300 rounded px-2 py-1 text-xs w-full">
                                    <option value="pendiente">Pendiente</option>
                                    <option value="habilitado">Habilitado</option>
                                    <option value="sin_clasificar">Sin Clasificar</option>
                                </select>
                                @error('editEstado') <p class="text-red-500 text-[9px] mt-0.5">{{ $message }}</p> @enderror
                            </td>
                            <td class="px-3 py-2 text-center">
                                <div class="flex justify-center items-center gap-1">
                                    <button wire:click="guardarEdicion"
                                        class="bg-green-600 hover:bg-green-700 text-white rounded px-2 py-1 text-[9px] font-black uppercase transition">
                                        Guardar
                                    </button>
                                    <button wire:click="cancelarEdicion"
                                        class="bg-gray-300 hover:bg-gray-400 text-gray-700 rounded px-2 py-1 text-[9px] font-black uppercase transition">
                                        ✕
                                    </button>
                                </div>
                            </td>

                            @else
                            {{-- FILA NORMAL --}}
                            <td class="px-3 py-2 text-center text-xs font-black text-indigo-600">
                                {{ $ins->orden ? str_pad($ins->orden, 2, '0', STR_PAD_LEFT) : '—' }}
                            </td>
                            <td class="px-3 py-2">
                                <div class="font-bold text-gray-800 text-xs">{{ $ins->apellido }} {{ $ins->nombre }}</div>
                                <div class="flex gap-1 mt-1">
                                    @if(!empty($ins->docente_id))
                                        <span class="text-[9px] px-1.5 py-0.5 rounded bg-indigo-50 text-indigo-600 font-bold uppercase">Vinculado</span>
                                    @endif
                                    @if(!empty($ins->tiene_legajo))
                                        <span class="text-[9px] px-1.5 py-0.5 rounded bg-blue-50 text-blue-600 font-bold uppercase">Legajo</span>
                                    @endif
                                    @if(!empty($ins->presento_f2))
                                        <span class="text-[9px] px-1.5 py-0.5 rounded bg-purple-50 text-purple-600 font-bold uppercase">F2</span>
                                    @endif
                                    @if(empty($ins->zona_texto) && !empty($ins->docente_id))
                                        <span class="text-[9px] px-1.5 py-0.5 rounded bg-gray-50 text-gray-400 font-bold uppercase" title="No se pudo determinar la zona del docente">Zona ?</span>
                                    @endif
                                </div>
                                @if($ins->observaciones)
                                    <div class="text-[10px] text-gray-400 italic mt-0.5">{{ $ins->observaciones }}</div>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-xs text-gray-600 font-mono">{{ $ins->dni }}</td>
                            <td class="px-3 py-2 text-xs text-gray-500">{{ $ins->telefono ?? '—' }}</td>
                            <td class="px-3 py-2 text-xs text-gray-500">{{ $ins->email ?? '—' }}</td>
                            <td class="px-3 py-2 text-xs text-gray-500">{{ $ins->domicilio ?? '—' }}</td>
                            <td class="px-3 py-2 text-xs">
                                <div class="text-gray-600">{{ $ins->localidad ?? '—' }}</div>
                                @if($ins->zona_texto)
                                    <span class="text-[9px] px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 font-bold uppercase">Zona {{ $ins->zona_texto }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-center">
                                @if($ins->puntaje !== null)
                                    <span class="font-black text-sm
                                        {{ $ins->estado === 'habilitado' ? 'text-green-700' : 'text-gray-500' }}">
                                        {{ number_format($ins->puntaje, 2) }}
                                    </span>
                                @else
                                    <span class="text-gray-300 text-xs">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-center">
                                @php
                                    $estadoClasses = match($ins->estado) {
                                        'habilitado'    => 'bg-green-100 text-green-700 border-green-200',
                                        'sin_clasificar'=> 'bg-red-100 text-red-700 border-red-200',
                                        default         => 'bg-amber-100 text-amber-700 border-amber-200',
                                    };
                                    $estadoLabel = match($ins->estado) {
                                        'habilitado'    => 'Habilitado',
                                        'sin_clasificar'=> 'Sin Clasif.',
                                        default         => 'Pendiente',
                                    };
                                @endphp
                                <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-black uppercase border {{ $estadoClasses }}">
                                    {{ $estadoLabel }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-center">
                                <div class="flex justify-center items-center gap-1">
                                    <button wire:click="editarInscripto({{ $ins->id }})"
                                        class="text-indigo-500 hover:text-indigo-700 p-1 rounded hover:bg-indigo-50 transition"
                                        title="Editar">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </button>
                                    <button wire:click="verDocumentacion({{ $ins->id }})"
                                        class="text-slate-500 hover:text-slate-800 p-1 rounded hover:bg-slate-100 transition"
                                        title="Ver documentación">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                    </button>
                                    <button
                                        onclick="confirm('¿Eliminar este inscripto?') || event.stopImmediatePropagation()"
                                        wire:click="eliminarInscripto({{ $ins->id }})"
                                        class="text-red-400 hover:text-red-600 p-1 rounded hover:bg-red-50 transition"
                                        title="Eliminar">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                            @endif

                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @endif
            </div>

            {{-- ── FOOTER ─────────────────────────────────────────────── --}}
            <div class="flex items-center justify-between px-6 py-4 border-t bg-gray-50 rounded-b-2xl shrink-0">
                <p class="text-xs text-gray-900">
                    El LOM se genera con los inscriptos en estado <strong>Habilitado</strong> y <strong>Sin Clasificar</strong>.
                    Los <em>Pendientes</em> no aparecen en la publicación oficial.
                </p>
                <div class="flex gap-2">
                    @if(!$lomExiste)
         <span class="px-3 py-2 text-[10px] font-bold text-gray-400 uppercase italic">
             LOM no generado
         </span>
                @elseif($lomPublicado)
                    <span class="px-5 py-2 text-xs font-black text-green-700 uppercase bg-green-50 border border-green-200 rounded-lg">
                        ✓ LOM Publicado
                    </span>
                @else
                    <button wire:click="publicarLom"
                        wire:confirm="¿Publicar el LOM con los inscriptos actuales (Habilitados + Sin Clasificar)?"
                        class="px-5 py-2 text-xs font-black text-white uppercase bg-indigo-600 hover:bg-indigo-700 rounded-lg transition">
                        Publicar LOM
                    </button>
                @endif
                    <button wire:click="cerrarPanel"
                        class="px-5 py-2 text-xs font-bold text-gray-500 hover:text-gray-800 uppercase transition">
                        Cerrar
                    </button>
                   
                </div>
            </div>

        </div>
    </div>

    {{-- ╔══════════════════════════════════════════════════════════════╗
         ║  MODAL DE DOCUMENTACIÓN DEL INSCRIPTO                       ║
         ╚══════════════════════════════════════════════════════════════╝ --}}
    @if($docModalAbierto && $docInscripto)
    <div
        class="fixed inset-0 z-[70] flex items-center justify-center bg-black/50 backdrop-blur-sm"
        x-data
        x-on:keydown.escape.window="$wire.cerrarDocModal()"
    >
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl mx-4 flex flex-col max-h-[88vh]">

            {{-- ── HEADER ────────────────────────────────────────────── --}}
            <div class="flex items-center justify-between px-6 py-4 border-b shrink-0 bg-slate-800 rounded-t-2xl">
                <div>
                    <h2 class="text-base font-black text-white uppercase tracking-tight">Documentación</h2>
                    <p class="text-xs text-slate-300 mt-0.5">
                        {{ $docInscripto->apellido }} {{ $docInscripto->nombre }} · DNI {{ $docInscripto->dni }}
                    </p>
                </div>
                <button wire:click="cerrarDocModal"
                    class="text-slate-400 hover:text-white hover:bg-slate-700 rounded-full p-1.5 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="overflow-y-auto flex-1 px-6 py-5 space-y-6">

                @if(empty($docInscripto->docente_id))
                    <div class="flex items-center gap-2 px-4 py-3 rounded-lg bg-amber-50 border border-amber-200 text-amber-700 text-xs font-semibold">
                        <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.72-1.36 3.486 0l6.517 11.59c.75 1.334-.213 2.98-1.743 2.98H3.483c-1.53 0-2.493-1.646-1.743-2.98l6.517-11.59zM11 14a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V7a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                        Este inscripto no tiene un docente vinculado en el sistema: no hay documentación digital asociada.
                    </div>
                @else

                    {{-- Datos generales --}}
                    <div>
                        <h3 class="text-[10px] font-black uppercase text-gray-400 tracking-widest mb-2">Datos generales</h3>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="px-3 py-2.5 rounded-lg border border-gray-100 bg-gray-50 flex items-center justify-between">
                                <span class="text-xs font-semibold text-gray-600">Legajo</span>
                                @if(!empty($docInscripto->tiene_legajo))
                                    <span class="text-[10px] font-black uppercase text-green-700 bg-green-100 px-2 py-0.5 rounded-full">Sí</span>
                                @else
                                    <span class="text-[10px] font-black uppercase text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full">No</span>
                                @endif
                            </div>
                            <div class="px-3 py-2.5 rounded-lg border border-gray-100 bg-gray-50 flex items-center justify-between">
                                <span class="text-xs font-semibold text-gray-600">Formulario F2</span>
                                @if(!empty($docInscripto->presento_f2))
                                    @if(!empty($docInscripto->f2_path))
                                        <a href="{{ asset('storage/'.$docInscripto->f2_path) }}" target="_blank"
                                            class="text-[10px] font-black uppercase text-indigo-600 hover:text-indigo-800 underline">
                                            Ver archivo
                                        </a>
                                    @else
                                        <span class="text-[10px] font-black uppercase text-green-700 bg-green-100 px-2 py-0.5 rounded-full">Sí</span>
                                    @endif
                                @else
                                    <span class="text-[10px] font-black uppercase text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full">No</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Domicilio: localidad, zona, DNI y comprobante --}}
                    <div>
                        <h3 class="text-[10px] font-black uppercase text-gray-400 tracking-widest mb-2">Domicilio y Zona</h3>
                        @if(!$docDomicilio)
                            <p class="text-xs text-gray-300 italic">Sin datos de domicilio cargados.</p>
                        @else
                        <div class="grid grid-cols-2 gap-3">
                            <div class="px-3 py-2.5 rounded-lg border border-gray-100 bg-gray-50">
                                <span class="text-[10px] font-semibold text-gray-500 uppercase block mb-0.5">Localidad</span>
                                <span class="text-xs font-bold text-gray-700">{{ $docDomicilio->localidad_nombre ?? '—' }}</span>
                                @php
                                    $zonaTexto = $docDomicilio->zona_override ?: $docDomicilio->zona_departamento;
                                @endphp
                                @if($zonaTexto)
                                    <span class="ml-1 text-[9px] px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 font-bold uppercase">Zona {{ $zonaTexto }}</span>
                                @endif
                            </div>
                            <div class="px-3 py-2.5 rounded-lg border border-gray-100 bg-gray-50 flex items-center justify-between">
                                <span class="text-xs font-semibold text-gray-600">DNI</span>
                                @if($docDomicilio->archivo_dni)
                                    <a href="{{ asset('storage/'.$docDomicilio->archivo_dni) }}" target="_blank"
                                        class="text-[10px] font-black uppercase text-indigo-600 hover:text-indigo-800 underline">
                                        Ver archivo
                                    </a>
                                @else
                                    <span class="text-[10px] font-black uppercase text-red-500 bg-red-50 px-2 py-0.5 rounded-full">Falta</span>
                                @endif
                            </div>
                            <div class="px-3 py-2.5 rounded-lg border border-gray-100 bg-gray-50 flex items-center justify-between">
                                <span class="text-xs font-semibold text-gray-600">Factura de servicios</span>
                                @if($docDomicilio->archivo_factura)
                                    <a href="{{ asset('storage/'.$docDomicilio->archivo_factura) }}" target="_blank"
                                        class="text-[10px] font-black uppercase text-indigo-600 hover:text-indigo-800 underline">
                                        Ver archivo
                                    </a>
                                @else
                                    <span class="text-[10px] font-black uppercase text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full">No presentó</span>
                                @endif
                            </div>
                            <div class="px-3 py-2.5 rounded-lg border border-gray-100 bg-gray-50 flex items-center justify-between">
                                <span class="text-xs font-semibold text-gray-600">Certificado de domicilio</span>
                                @if($docDomicilio->archivo_certifdomicilio)
                                    <a href="{{ asset('storage/'.$docDomicilio->archivo_certifdomicilio) }}" target="_blank"
                                        class="text-[10px] font-black uppercase text-indigo-600 hover:text-indigo-800 underline">
                                        Ver archivo
                                    </a>
                                @else
                                    <span class="text-[10px] font-black uppercase text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full">No presentó</span>
                                @endif
                            </div>
                        </div>
                        @if(!$docDomicilio->archivo_factura && !$docDomicilio->archivo_certifdomicilio)
                            <p class="mt-2 text-[10px] text-red-500 font-bold">⚠ No presentó ni factura ni certificado de domicilio.</p>
                        @endif
                        @endif
                    </div>

                    {{-- Títulos --}}
                    <div>
                        <h3 class="text-[10px] font-black uppercase text-gray-400 tracking-widest mb-2">
                            Títulos ({{ count($docTitulos) }})
                        </h3>
                        @if(empty($docTitulos))
                            <p class="text-xs text-gray-300 italic">No cargó títulos en el sistema.</p>
                        @else
                            <div class="space-y-2">
                                @foreach($docTitulos as $t)
                                @php $t = is_array($t) ? (object) $t : $t; @endphp
                                <div class="flex items-center justify-between px-3 py-2.5 rounded-lg border border-gray-100 bg-white shadow-sm">
                                    <div>
                                        <div class="text-xs font-bold text-gray-800">{{ $t->nombre_titulo }}</div>
                                        <div class="text-[10px] text-gray-400">
                                            {{ $t->institucion ?? '—' }} @if($t->anio_egreso) · {{ $t->anio_egreso }} @endif
                                        </div>
                                    </div>
                                    @if($t->archivo_path)
                                        <a href="{{ asset('storage/'.$t->archivo_path) }}" target="_blank"
                                            class="text-[10px] font-black uppercase text-indigo-600 hover:text-indigo-800 underline shrink-0 ml-3">
                                            Ver archivo
                                        </a>
                                    @else
                                        <span class="text-[10px] text-gray-300 shrink-0 ml-3">Sin archivo</span>
                                    @endif
                                </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    {{-- Certificados --}}
                    <div>
                        <h3 class="text-[10px] font-black uppercase text-gray-400 tracking-widest mb-2">
                            Certificados ({{ count($docCertificados) }})
                        </h3>
                        @if(empty($docCertificados))
                            <p class="text-xs text-gray-300 italic">No cargó certificados en el sistema.</p>
                        @else
                            <div class="space-y-2">
                                @foreach($docCertificados as $c)
                                @php $c = is_array($c) ? (object) $c : $c; @endphp
                                <div class="flex items-center justify-between px-3 py-2.5 rounded-lg border border-gray-100 bg-white shadow-sm">
                                    <div>
                                        <div class="text-xs font-bold text-gray-800">{{ $c->nombre_certificado }}</div>
                                        @if($c->tipo)
                                            <div class="text-[10px] text-gray-400">{{ $c->tipo }}</div>
                                        @endif
                                    </div>
                                    @if($c->archivo_path)
                                        <a href="{{ asset('storage/'.$c->archivo_path) }}" target="_blank"
                                            class="text-[10px] font-black uppercase text-indigo-600 hover:text-indigo-800 underline shrink-0 ml-3">
                                            Ver archivo
                                        </a>
                                    @else
                                        <span class="text-[10px] text-gray-300 shrink-0 ml-3">Sin archivo</span>
                                    @endif
                                </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                @endif
            </div>

            <div class="flex items-center justify-end px-6 py-4 border-t bg-gray-50 rounded-b-2xl shrink-0">
                <button wire:click="cerrarDocModal"
                    class="px-5 py-2 text-xs font-bold text-gray-500 hover:text-gray-800 uppercase transition">
                    Cerrar
                </button>
            </div>

        </div>
    </div>
    @endif

    @endif
</div>
