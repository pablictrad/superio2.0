<?php
/**
 * trayecto-formativo.blade.php
 *
 * Formulario PÚBLICO de inscripción al Trayecto Formativo (sin login),
 * migrado del módulo Blade/Bootstrap de Sage (TrayectoController) a
 * Livewire Volt, siguiendo el mismo patrón de wizard que
 * publico/inscripcion-llamado.blade.php: búsqueda/alta por DNI, sin
 * autenticación, validación por DNI en vez de por sesión.
 *
 * Pasos (equivalentes a los 3 tabs de la vista vieja formulario.blade.php):
 *   1. Información del trayecto + búsqueda por DNI
 *   2. Datos personales (snapshot, standalone — sin FK a tb_docentes)
 *   3. Inscripción (nivel/estamento) + Documentación (F2/certificación/concepto)
 *
 * La tabla tb_trayecto_formativo es única y genérica: la convocatoria se
 * distingue por la columna `cohorte` (ver config/trayecto.php), no por
 * el nombre de la tabla.
 *
 * Nota de diseño: como los 3 documentos (F2/certificación/concepto) viven
 * como columnas en la misma fila de inscripción (a diferencia de Sage, que
 * los guardaba en una tabla aparte por DNI), pertenecen al DNI y no a una
 * inscripción puntual. Por eso subirDocumento()/eliminarDocumento() escriben
 * sobre TODAS las filas de un mismo (dni, cohorte) a la vez (UPDATE ... WHERE
 * dni = ? AND cohorte = ?), nunca sobre una sola fila por id. Así, en el caso
 * de "Nivel Primario" con 2 inscripciones, ambas filas quedan siempre con el
 * mismo valor y alcanza con leer cualquiera de las dos para mostrarlo.
 */

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Support\TrayectoConfig;

new #[Layout('layouts.publico')] class extends Component {
    use WithFileUploads;

    // ── Paso actual: 1 información/DNI, 2 datos personales, 3 inscripción/documentos ──
    public int $paso = 1;

    public int $cohorteActiva = 2025;

    public string $nombreTrayecto = '';

    // Flag global (tabla tb_trayecto_config) que el admin togglea desde el panel.
    public bool $trayectoHabilitado = true;

    // ── Paso 1 ──────────────────────────────────────────────────────
    public string $dniBusqueda = '';
    public string $mensajeErr  = '';
    public string $mensajeOk   = '';

    // ── Paso 2: datos personales (snapshot, standalone) ─────────────
    public string $dni       = '';
    public string $apellido  = '';
    public string $nombre    = '';
    public string $telefono  = '';
    public string $email     = '';
    public string $fechaNac  = '';
    public string $domicilio = '';
    public string $barrio    = '';

    // ── Paso 3: inscripción ──────────────────────────────────────────
    public array   $inscripciones = []; // filas de tb_trayecto_formativo del dni en la cohorte activa
    public string  $nivel         = '';
    public string  $estamento     = '';

    // Institución: catálogo real importado desde bdexportados (924 instituciones, sin nivel —
    // se manejan por CUE). Búsqueda por nombre/CUE en vez de combo filtrado por nivel.
    // Ya NO es vestigial: se persiste en institucion_trayecto_id (opcional) en guardarInscripcion().
    public ?int   $institucionId          = null;
    public string $institucionBusqueda    = '';
    public array  $institucionesEncontradas = [];

    public $archivoF2             = null;
    public $archivoCertificacion  = null;
    public $archivoConcepto       = null;

    public bool $inscripcionFinalizada = false;

    // DNI que ya alcanzó el máximo de inscripciones para la convocatoria activa
    // (bloqueado en el paso 1) — se guarda aparte para poder ofrecer la descarga
    // de su constancia sin necesidad de re-cargar $this->dni ni $inscripciones.
    public string $dniBloqueado = '';

    public array $estamentosPorNivel = [
        'Inicial' => [
            'ESTAMENTO SUPERVISOR/A',
            'ESTAMENTO DIRECTOR/A. PRIMERA CATEGORÍA',
            'ESTAMENTO VICE-DIRECTOR/A. PRIMERA CATEGORÍA',
            'ESTAMENTO DIRECTOR/A - SEGUNDA CATEGORIA',
        ],
        'Primario' => [
            'ESTAMENTO SUPERVISOR/A',
            'ESTAMENTO DIRECTOR/A. PRIMERA CATEGORÍA',
            'ESTAMENTO VICE-DIRECTOR/A. PRIMERA CATEGORÍA',
            'ESTAMENTO DIRECTOR/A - SEGUNDA CATEGORIA',
            'ESTAMENTO - DIRECTOR/A - TERCERA CATEGORIA',
        ],
        'Secundario' => [
            'ESTAMENTO SUPERVISOR/A',
            'DIRECTOR/A.',
            'VICERRECTOR/A',
            'VICERRECTOR/A. ESCUELAS TÉCNICAS',
            'VICERRECTOR/A. ESCUELAS DE ARTE',
        ],
        'Especial' => [
            'ESTAMENTO DIRECTOR/A. PRIMERA CATEGORÍA',
        ],
    ];

    public function mount(): void
    {
        $this->cohorteActiva     = (int) config('trayecto.cohorte_activa');
        $this->nombreTrayecto    = (string) config('trayecto.nombre');
        $this->trayectoHabilitado = TrayectoConfig::habilitado();
    }

    /* ═══════════════════════════════════════════════════════════════
       PASO 1 — BUSCAR/DAR DE ALTA POR DNI
    ═══════════════════════════════════════════════════════════════ */
    public function buscarDni(): void
    {
        $this->mensajeErr   = '';
        $this->mensajeOk    = '';
        $this->dniBloqueado = '';

        // Defensa en profundidad: la vista ya oculta el formulario si está
        // deshabilitado, pero esto evita que se pueda invocar la acción igual.
        if (!$this->trayectoHabilitado) {
            $this->mensajeErr = 'La inscripción al Trayecto Formativo no está habilitada en este momento.';
            return;
        }

        $this->validate([
            'dniBusqueda' => 'required|digits_between:6,8',
        ], [
            'dniBusqueda.required'       => 'Ingrese su DNI para continuar.',
            'dniBusqueda.digits_between' => 'El DNI debe tener entre 6 y 8 dígitos.',
        ]);

        $dni = preg_replace('/[^0-9]/', '', $this->dniBusqueda);
        $this->dni = $dni;

        $this->cargarInscripciones();

        // ── Duplicados: si el DNI ya tiene CUALQUIER inscripción registrada para
        //    esta convocatoria, se bloquea acá mismo en el paso 1 (sin excepción
        //    para Nivel Primario) y se ofrece la descarga de su constancia.
        if (count($this->inscripciones) > 0) {
            $this->mensajeErr   = 'Ya posees una inscripción activa a este trayecto para la convocatoria ' . $this->cohorteActiva . '. No es posible registrar una nueva inscripción con este DNI.';
            $this->dniBloqueado = $dni;
            $this->dni = '';
            $this->inscripciones = [];
            return;
        }

        // Prellenar datos personales desde la inscripción más reciente de este DNI (si existe).
        $ultima = collect($this->inscripciones)->sortByDesc('id')->first();

        if ($ultima) {
            $this->apellido  = $ultima['apellido'];
            $this->nombre    = $ultima['nombre'];
            $this->telefono  = $ultima['telefono'] ?? '';
            $this->email     = $ultima['email'] ?? '';
            $this->fechaNac  = $ultima['fecha_nac'] ?? '';
            $this->domicilio = $ultima['domicilio'] ?? '';
            $this->barrio    = $ultima['barrio'] ?? '';
        }

        $this->paso = 2;
    }

    private function cargarInscripciones(): void
    {
        $this->inscripciones = DB::table('tb_trayecto_formativo')
            ->where('dni', $this->dni)
            ->where('cohorte', $this->cohorteActiva)
            ->orderByDesc('id')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /* ═══════════════════════════════════════════════════════════════
       PASO 2 → 3 — DATOS PERSONALES
    ═══════════════════════════════════════════════════════════════ */
    public function irPaso3(): void
    {
        $this->mensajeErr = '';

        $this->validate([
            'apellido'  => 'required|string|min:2|max:100',
            'nombre'    => 'required|string|min:2|max:100',
            'dni'       => 'required|digits_between:6,9',
            'telefono'  => 'required|string|max:30',
            'email'     => 'required|email|max:150',
            'fechaNac'  => 'nullable|date|before_or_equal:today',
            'domicilio' => 'required|string|max:200',
            'barrio'    => 'required|string|max:100',
        ], [
            'telefono.required'  => 'El teléfono es obligatorio.',
            'email.required'     => 'El email es obligatorio.',
            'domicilio.required' => 'El domicilio es obligatorio.',
            'barrio.required'    => 'El barrio es obligatorio.',
        ]);

        $this->paso = 3;
    }

    public function volverPaso(int $p): void
    {
        if ($p < $this->paso) {
            $this->paso = $p;
            $this->mensajeErr = '';
            $this->mensajeOk  = '';
        }
    }

    /* ═══════════════════════════════════════════════════════════════
       PASO 3 — NIVEL / INSTITUCIÓN / ESTAMENTO
    ═══════════════════════════════════════════════════════════════ */
    public function updatedNivel(): void
    {
        $this->estamento = '';
    }

    /**
     * Instituciones no están vinculadas al nivel (se manejan por CUE), así que en
     * vez de un combo filtrado se busca por nombre o CUE contra las 924 instituciones
     * importadas.
     */
    public function updatedInstitucionBusqueda(): void
    {
        $this->institucionId = null;

        $q = trim($this->institucionBusqueda);

        $this->institucionesEncontradas = $q !== ''
            ? DB::table('tb_instituciones_trayecto')
                ->where('activo', true)
                ->where(function ($sub) use ($q) {
                    $sub->where('nombre', 'ilike', "%{$q}%")
                        ->orWhere('cue', 'ilike', "%{$q}%");
                })
                ->orderBy('nombre')
                ->limit(15)
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all()
            : [];
    }

    public function seleccionarInstitucion(int $id, string $nombre): void
    {
        $this->institucionId              = $id;
        $this->institucionBusqueda        = $nombre;
        $this->institucionesEncontradas   = [];
    }

    /**
     * Alta de inscripción (equivalente a TrayectoController::guardar()).
     * Regla: 1 inscripción por DNI dentro de la cohorte activa, salvo
     * nivel = 'Primario' (valor normalizado) que permite hasta 2.
     */
    public function guardarInscripcion(): void
    {
        $this->mensajeErr = '';
        $this->mensajeOk  = '';

        $niveles = config('trayecto.niveles');

        $this->validate([
            'nivel'         => 'required|in:' . implode(',', $niveles),
            'estamento'     => 'required|string|max:255',
            'institucionId' => 'required|integer|exists:tb_instituciones_trayecto,id',
        ], [
            'nivel.required'         => 'Seleccioná un nivel.',
            'nivel.in'               => 'Nivel inválido.',
            'estamento.required'     => 'Seleccioná un estamento.',
            'institucionId.required' => 'Seleccioná una institución de la lista de resultados.',
            'institucionId.exists'   => 'La institución seleccionada no es válida.',
        ]);

        $nivelMultiple = config('trayecto.nivel_multiple');
        $limite = $this->nivel === $nivelMultiple
            ? (int) config('trayecto.max_inscripciones_nivel_multiple')
            : (int) config('trayecto.max_inscripciones_default');

        $existentes = DB::table('tb_trayecto_formativo')
            ->where('dni', $this->dni)
            ->where('cohorte', $this->cohorteActiva)
            ->count();

        if ($existentes >= $limite) {
            $this->mensajeErr = $this->nivel === $nivelMultiple
                ? 'El agente ya tiene ' . $limite . ' inscripciones en ' . $nivelMultiple . ' para esta convocatoria.'
                : 'Ya existe una inscripción para este DNI en esta convocatoria. No se puede crear otra.';
            return;
        }

        DB::table('tb_trayecto_formativo')->insert([
            'cohorte'                 => $this->cohorteActiva,
            'dni'                     => $this->dni,
            'apellido'                => trim($this->apellido),
            'nombre'                  => trim($this->nombre),
            'telefono'                => trim($this->telefono) ?: null,
            'email'                   => trim($this->email) ?: null,
            'fecha_nac'               => $this->fechaNac ?: null,
            'domicilio'               => trim($this->domicilio) ?: null,
            'barrio'                  => trim($this->barrio) ?: null,
            'nivel'                   => $this->nivel,
            'estamento'               => $this->estamento,
            'institucion_trayecto_id' => $this->institucionId,
            'created_at'              => now(),
            'updated_at'              => now(),
        ]);

        $this->nivel                    = '';
        $this->estamento                = '';
        $this->institucionId            = null;
        $this->institucionBusqueda      = '';
        $this->institucionesEncontradas = [];
        $this->cargarInscripciones();
        $this->mensajeOk = 'Inscripción registrada correctamente.';
    }

    /**
     * Baja de inscripción — validada por DNI (no por sesión, a diferencia de Sage).
     *
     * Los documentos (f2/certificacion_servicio/concepto) están replicados en
     * TODAS las filas de un mismo (dni, cohorte) — ver subirDocumento()/eliminarDocumento().
     * Por eso, al borrar una fila, los archivos físicos solo se eliminan si no
     * queda ninguna otra fila hermana que todavía los referencie.
     */
    public function eliminarInscripcion(int $id): void
    {
        $fila = DB::table('tb_trayecto_formativo')
            ->where('id', $id)
            ->where('dni', $this->dni)
            ->where('cohorte', $this->cohorteActiva)
            ->first();

        if (!$fila) {
            $this->mensajeErr = 'No se pudo eliminar la inscripción.';
            return;
        }

        DB::table('tb_trayecto_formativo')->where('id', $id)->delete();

        $this->inscripcionFinalizada = false;

        $quedanHermanas = DB::table('tb_trayecto_formativo')
            ->where('dni', $this->dni)
            ->where('cohorte', $this->cohorteActiva)
            ->exists();

        if (!$quedanHermanas) {
            foreach (['f2_path', 'certificacion_servicio_path', 'concepto_path'] as $col) {
                if (!empty($fila->$col) && Storage::disk('public')->exists($fila->$col)) {
                    Storage::disk('public')->delete($fila->$col);
                }
            }
        }

        $this->cargarInscripciones();
        $this->mensajeOk = 'Inscripción eliminada correctamente.';
    }

    /* ═══════════════════════════════════════════════════════════════
       DOCUMENTOS: F2 / CERTIFICACIÓN DE SERVICIO / CONCEPTO
    ═══════════════════════════════════════════════════════════════ */
    private function reglasPdf(bool $requerido): array
    {
        return [
            $requerido ? 'required' : 'nullable',
            'file',
            'mimes:pdf',
            'max:10240', // 10 MB, igual que Sage
            function ($attribute, $value, $fail) {
                $path = $value->getRealPath();

                if (function_exists('finfo_open')) {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    if ($finfo) {
                        $mime = finfo_file($finfo, $path);
                        finfo_close($finfo);
                        if ($mime !== 'application/pdf') {
                            $fail('El archivo no es un PDF válido (MIME).');
                            return;
                        }
                    }
                }

                $head = @file_get_contents($path, false, null, 0, 1024);
                if (!is_string($head) || !preg_match('/%PDF-\d\.\d/', $head)) {
                    $fail('El archivo no es un PDF válido (firma).');
                }
            },
        ];
    }

    /** @param  'f2'|'certificacion_servicio'|'concepto'  $tipo */
    public function subirDocumento(string $tipo): void
    {
        $this->mensajeErr = '';

        $campos = [
            'f2'                     => 'archivoF2',
            'certificacion_servicio' => 'archivoCertificacion',
            'concepto'               => 'archivoConcepto',
        ];

        if (!isset($campos[$tipo])) {
            return;
        }

        $propiedad = $campos[$tipo];

        $this->validate([
            $propiedad => $this->reglasPdf(true),
        ], [], [$propiedad => 'archivo']);

        if (empty($this->inscripciones)) {
            $this->mensajeErr = 'Primero tenés que registrar una inscripción antes de subir documentación.';
            return;
        }

        $path = $this->$propiedad->store("trayecto/{$tipo}", 'public');

        // El documento pertenece al DNI, no a una inscripción puntual: se replica en
        // TODAS las filas de este (dni, cohorte) para que lectura y escritura queden
        // siempre consistentes entre sí (caso Primario con 2 inscripciones incluido).
        DB::table('tb_trayecto_formativo')
            ->where('dni', $this->dni)
            ->where('cohorte', $this->cohorteActiva)
            ->update([
                "{$tipo}_path" => $path,
                'updated_at'   => now(),
            ]);

        $this->$propiedad = null;
        $this->cargarInscripciones();
        $this->mensajeOk = 'Documento subido correctamente.';
    }

    /** @param  'f2'|'certificacion_servicio'|'concepto'  $tipo */
    public function eliminarDocumento(string $tipo): void
    {
        $columna = "{$tipo}_path";

        $pathActual = $this->documento($columna);
        if (!$pathActual) {
            return;
        }

        if (Storage::disk('public')->exists($pathActual)) {
            Storage::disk('public')->delete($pathActual);
        }

        // Limpia la columna en TODAS las filas de este (dni, cohorte) — mismo criterio que subirDocumento().
        DB::table('tb_trayecto_formativo')
            ->where('dni', $this->dni)
            ->where('cohorte', $this->cohorteActiva)
            ->update([$columna => null, 'updated_at' => now()]);

        $this->inscripcionFinalizada = false;
        $this->cargarInscripciones();
        $this->mensajeOk = 'Documento eliminado.';
    }

    /**
     * Valor de una columna de documento para el DNI actual. Como subirDocumento()/
     * eliminarDocumento() mantienen la columna sincronizada en todas las filas del
     * mismo (dni, cohorte), alcanza con leerla de la primera fila cargada.
     *
     * Devuelve null también cuando el path está guardado pero el archivo físico
     * no existe (caso de los 221 registros migrados de Sage, cohorte 2025, cuyos
     * PDFs nunca se copiaron a este entorno) — así el wizard lo trata como "no hay
     * documento subido todavía" y deja subir uno nuevo con el flujo normal, en vez
     * de mostrar "✓ Documento cargado" con un link roto.
     */
    public function documento(string $columna): ?string
    {
        $path = $this->inscripciones[0][$columna] ?? null;

        if (!$path || !Storage::disk('public')->exists($path)) {
            return null;
        }

        return $path;
    }

    /* ═══════════════════════════════════════════════════════════════
       CIERRE DE INSCRIPCIÓN
    ═══════════════════════════════════════════════════════════════ */
    /**
     * Confirma el cierre de la inscripción una vez cargada toda la
     * documentación obligatoria (F2, certificación de servicio y concepto).
     * Validación defensiva del lado servidor: la UI ya evita llegar acá
     * si falta algún documento o inscripción, pero se revalida por si
     * el estado cambió entre pasos (otra pestaña, sesión larga, etc.).
     */
    public function finalizarInscripcion(): void
    {
        $this->mensajeErr = '';
        $this->mensajeOk  = '';

        if (empty($this->inscripciones)) {
            $this->mensajeErr = 'Primero tenés que agregar tu inscripción (nivel/estamento).';
            return;
        }

        $faltantes = array_filter([
            $this->documento('f2_path')                     ? null : 'Declaración Jurada de cargos F2',
            $this->documento('certificacion_servicio_path') ? null : 'Certificación de servicio actualizada',
            $this->documento('concepto_path')                ? null : 'Concepto elevado por la institución',
        ]);

        if (!empty($faltantes)) {
            $this->mensajeErr = 'Falta subir la siguiente documentación obligatoria: ' . implode(', ', $faltantes) . '.';
            return;
        }

        $this->inscripcionFinalizada = true;
        $this->mensajeOk = '';
        $this->dispatch('inscripcion-exitosa');
    }
}; ?>

<div class="max-w-4xl mx-auto py-8 px-4"
    x-on:inscripcion-exitosa.window="setTimeout(() => { window.location.href = '{{ route('home') }}' }, 8000)">

    <div class="text-center mb-8">
        <p class="text-xs font-bold uppercase tracking-widest text-indigo-500 mb-1">{{ $nombreTrayecto }}</p>
        <h1 class="text-2xl font-black text-gray-800">Trayecto Formativo para Aspirantes a Cargos Directivos y Supervisivos</h1>
        <p class="text-sm text-gray-500 mt-1">Convocatoria — Año {{ $cohorteActiva }}</p>
    </div>

    @if(!$trayectoHabilitado)
        <div class="bg-white border-2 border-gray-200 rounded-2xl p-10 text-center space-y-3">
            <div class="text-4xl">🔒</div>
            <h2 class="text-lg font-black text-gray-700">Inscripción no disponible</h2>
            <p class="text-sm text-gray-500 max-w-md mx-auto">La inscripción al Trayecto Formativo no está habilitada en este momento. Volvé a intentarlo más adelante o contactate con la institución.</p>
            <a href="{{ route('home') }}" class="inline-block text-indigo-600 font-bold text-sm pt-2">Volver a la pantalla principal</a>
        </div>
    @else

    {{-- Indicador de pasos --}}
    <div class="flex items-center justify-center gap-2 mb-8">
        @foreach (['Información', 'Datos Personales', 'Inscripción'] as $i => $etiqueta)
            <button
                type="button"
                wire:click="volverPaso({{ $i + 1 }})"
                @if($i + 1 >= $paso) disabled @endif
                class="flex items-center gap-2 px-3 py-1.5 rounded-full text-sm font-bold
                    {{ $paso === $i + 1 ? 'bg-indigo-600 text-white' : ($i + 1 < $paso ? 'bg-indigo-100 text-indigo-700 cursor-pointer' : 'bg-gray-100 text-gray-400') }}">
                <span>{{ $i + 1 }}</span> {{ $etiqueta }}
            </button>
            @if(!$loop->last) <span class="text-gray-300">—</span> @endif
        @endforeach
    </div>

    @if($mensajeErr)
        <div class="bg-red-50 border-2 border-red-300 text-red-700 rounded-xl p-3 mb-4 text-sm font-bold">{{ $mensajeErr }}</div>
    @endif
    @if($mensajeOk)
        <div class="bg-green-50 border-2 border-green-300 text-green-700 rounded-xl p-3 mb-4 text-sm font-bold">{{ $mensajeOk }}</div>
    @endif

    {{-- ══════════════════ PASO 1 — INFORMACIÓN + DNI ══════════════════ --}}
    @if($paso === 1)
        <div class="bg-white border-2 border-indigo-100 rounded-2xl p-6 shadow-sm space-y-4">
            <div class="bg-red-50 border-2 border-red-200 rounded-xl p-4">
                <h3 class="font-bold text-red-700 mb-2">Condiciones</h3>
                <ul class="list-disc list-inside text-sm text-gray-700 space-y-1">
                    <li>Poseer título docente y ser titular para el nivel y modalidad educativa que concursa.</li>
                    <li>Revistar en ejercicio en los cargos durante los dos años anteriores a la convocatoria.</li>
                    <li>Estar inscripto en JUETAENO.</li>
                    <li>Haber sido designado en el cargo por orden de mérito.</li>
                    <li>Tener concepto no menor a Muy Bueno en los últimos dos años.</li>
                    <li>Cursar el Trayecto Formativo Docente, obligatorio y vinculante al examen de concurso.</li>
                </ul>
            </div>
            <div class="bg-indigo-50 border-2 border-indigo-200 rounded-xl p-4">
                <h3 class="font-bold text-indigo-700 mb-2">Documentación requerida</h3>
                <ul class="list-disc list-inside text-sm text-gray-700 space-y-1">
                    <li>Declaración Jurada de cargos F2</li>
                    <li>Certificación de servicio actualizada</li>
                    <li>Concepto elevado por la/las instituciones educativas correspondientes</li>
                </ul>
            </div>

            <div class="pt-2">
                <label class="block text-sm font-bold text-gray-700 mb-2">Ingresá tu DNI para continuar</label>
                <div class="flex gap-2">
                    <input
                        type="text"
                        wire:model="dniBusqueda"
                        wire:keydown.enter="buscarDni"
                        x-on:keydown="if (!/[0-9]/.test($event.key) && !['Backspace','Delete','ArrowLeft','ArrowRight','Tab','Enter','Home','End'].includes($event.key) && !$event.metaKey && !$event.ctrlKey) { $event.preventDefault() }"
                        x-on:input="$event.target.value = $event.target.value.replace(/[^0-9]/g, '').slice(0, 8)"
                        x-on:paste="$event.preventDefault(); const t = (($event.clipboardData || window.clipboardData).getData('text') || '').replace(/[^0-9]/g, '').slice(0, 8); $event.target.value = t; $event.target.dispatchEvent(new Event('input'))"
                        maxlength="8"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        placeholder="Ej: 28564343"
                        class="flex-1 border-2 border-gray-300 rounded-xl px-4 py-3 text-lg font-black text-center tracking-widest focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition @error('dniBusqueda') border-red-400 @enderror">
                    <button wire:click="buscarDni" wire:loading.attr="disabled" wire:target="buscarDni"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-6 rounded-xl transition">
                        <span wire:loading.remove wire:target="buscarDni">Continuar</span>
                        <span wire:loading wire:target="buscarDni">Buscando...</span>
                    </button>
                </div>
                @error('dniBusqueda') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror

                @if($dniBloqueado)
                    <div class="text-center pt-2">
                        <a href="{{ route('trayecto.constancia.descargar', ['dni' => $dniBloqueado, 'cohorte' => $cohorteActiva]) }}"
                           target="_blank"
                           class="inline-flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white font-bold px-6 py-2.5 rounded-xl shadow-sm transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v8.586l2.293-2.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L9 12.586V4a1 1 0 011-1zm-7 14a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd" />
                            </svg>
                            Descargar constancia de inscripción
                        </a>
                    </div>
                @endif
            </div>

            <div class="flex justify-start pt-2">
                <a href="{{ route('home') }}" class="text-gray-500 font-bold px-4 py-2 hover:text-gray-700 transition">Volver / Cerrar</a>
            </div>
        </div>
    @endif

    {{-- ══════════════════ PASO 2 — DATOS PERSONALES ══════════════════ --}}
    @if($paso === 2)
        <div class="bg-white border-2 border-indigo-100 rounded-2xl p-6 shadow-sm space-y-4">
            <h3 class="font-bold text-gray-800">Datos del Docente</h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Apellido</label>
                    <input type="text" wire:model="apellido" class="w-full border-2 border-gray-300 rounded-xl px-3 py-2 @error('apellido') border-red-400 @enderror">
                    @error('apellido') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nombre</label>
                    <input type="text" wire:model="nombre" class="w-full border-2 border-gray-300 rounded-xl px-3 py-2 @error('nombre') border-red-400 @enderror">
                    @error('nombre') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">DNI</label>
                    <input type="text" wire:model="dni" readonly class="w-full border-2 border-gray-200 bg-gray-100 rounded-xl px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fecha de nacimiento</label>
                    <input type="date" wire:model="fechaNac" class="w-full border-2 border-gray-300 rounded-xl px-3 py-2 @error('fechaNac') border-red-400 @enderror">
                    @error('fechaNac') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Teléfono <span class="text-red-500">*</span></label>
                    <input type="text" wire:model="telefono" required class="w-full border-2 border-gray-300 rounded-xl px-3 py-2 @error('telefono') border-red-400 @enderror">
                    @error('telefono') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email <span class="text-red-500">*</span></label>
                    <input type="email" wire:model="email" required class="w-full border-2 border-gray-300 rounded-xl px-3 py-2 @error('email') border-red-400 @enderror">
                    @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Domicilio (calle y número) <span class="text-red-500">*</span></label>
                    <input type="text" wire:model="domicilio" required class="w-full border-2 border-gray-300 rounded-xl px-3 py-2 @error('domicilio') border-red-400 @enderror">
                    @error('domicilio') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Barrio <span class="text-red-500">*</span></label>
                    <input type="text" wire:model="barrio" required class="w-full border-2 border-gray-300 rounded-xl px-3 py-2 @error('barrio') border-red-400 @enderror">
                    @error('barrio') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex justify-between pt-2">
                <button wire:click="volverPaso(1)" class="text-gray-500 font-bold px-4 py-2">Volver</button>
                <button wire:click="irPaso3" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-6 py-2 rounded-xl">Continuar</button>
            </div>
        </div>
    @endif

    {{-- ══════════════════ PASO 3 — INSCRIPCIÓN + DOCUMENTACIÓN ══════════════════ --}}
    @if($paso === 3)
        @if($inscripcionFinalizada)
            {{-- Cierre exitoso: se oculta todo el formulario y solo queda esta pantalla de confirmación --}}
            <div class="bg-green-50 border-2 border-green-300 rounded-2xl p-8 text-center space-y-4">
                <div class="text-5xl">✓</div>
                <h2 class="text-xl font-black text-green-700">¡Inscripción exitosa!</h2>
                <p class="text-sm text-gray-600">Tu inscripción al Trayecto Formativo quedó registrada correctamente.</p>
                <a href="{{ route('trayecto.constancia.descargar', ['dni' => $dni, 'cohorte' => $cohorteActiva]) }}"
                   target="_blank"
                   class="inline-flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white font-black px-8 py-4 rounded-2xl shadow-lg ring-4 ring-green-300 animate-pulse text-base transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v8.586l2.293-2.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L9 12.586V4a1 1 0 011-1zm-7 14a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd" />
                    </svg>
                    Descargar constancia de inscripción
                </a>
                <p class="text-xs text-gray-400 pt-2">Serás redirigido a la pantalla principal en unos segundos…</p>
            </div>
        @else
        <div class="space-y-6">

            {{-- Alta de inscripción --}}
            <div class="bg-white border-2 border-indigo-100 rounded-2xl p-6 shadow-sm space-y-4">
                <h3 class="font-bold text-gray-800">Inscripción al Trayecto Formativo</h3>
                <p class="text-xs text-gray-500">Solo se permite la inscripción en un nivel, salvo Nivel Primario que admite hasta 2.</p>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Niveles/Modalidad</label>
                        @php
                            $etiquetasNivel = [
                                'Inicial'    => 'Nivel Inicial',
                                'Primario'   => 'Nivel Primario',
                                'Secundario' => 'Nivel Secundario',
                                'Especial'   => 'Modalidad Especial',
                            ];
                        @endphp
                        <select wire:model.live="nivel" class="w-full border-2 border-gray-300 rounded-xl px-3 py-2 @error('nivel') border-red-400 @enderror">
                            <option value="">Seleccione…</option>
                            @foreach(config('trayecto.niveles') as $n)
                                <option value="{{ $n }}">{{ $etiquetasNivel[$n] ?? $n }}</option>
                            @endforeach
                        </select>
                        @error('nivel') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="relative">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Institución <span class="text-red-500">*</span></label>
                        <input type="text" wire:model.live.debounce.300ms="institucionBusqueda"
                            placeholder="Buscar por nombre o CUE…"
                            class="w-full border-2 border-gray-300 rounded-xl px-3 py-2 @error('institucionId') border-red-400 @enderror">

                        @if($institucionId)
                            <p class="mt-1 text-xs text-green-600 font-bold">✓ {{ $institucionBusqueda }}</p>
                        @elseif(!empty($institucionesEncontradas))
                            <ul class="absolute z-10 w-full bg-white border-2 border-gray-200 rounded-xl mt-1 max-h-56 overflow-y-auto shadow-lg">
                                @foreach($institucionesEncontradas as $inst)
                                    <li wire:click="seleccionarInstitucion({{ $inst['id'] }}, '{{ addslashes($inst['nombre']) }}')"
                                        class="px-3 py-2 text-sm hover:bg-indigo-50 cursor-pointer border-b last:border-0">
                                        {{ $inst['nombre'] }} <span class="text-xs text-gray-400">(CUE {{ $inst['cue'] }})</span>
                                    </li>
                                @endforeach
                            </ul>
                        @elseif(trim($institucionBusqueda) !== '')
                            <p class="mt-1 text-xs text-gray-400">Sin resultados.</p>
                        @endif
                        @error('institucionId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Estamento</label>
                        <select wire:model="estamento" @disabled(!$nivel) class="w-full border-2 border-gray-300 rounded-xl px-3 py-2 disabled:bg-gray-100 @error('estamento') border-red-400 @enderror">
                            <option value="">Seleccione…</option>
                            @foreach($estamentosPorNivel[$nivel] ?? [] as $est)
                                <option value="{{ $est }}">{{ $est }}</option>
                            @endforeach
                        </select>
                        @error('estamento') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="text-end">
                    <button wire:click="guardarInscripcion" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-6 py-2 rounded-xl">Agregar inscripción</button>
                </div>
            </div>

            {{-- Mi inscripción --}}
            <div class="bg-white border-2 border-gray-100 rounded-2xl shadow-sm overflow-hidden">
                <div class="px-6 py-3 bg-gray-50 font-bold text-gray-700">Mi inscripción</div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500">
                            <tr>
                                <th class="text-left px-4 py-2">Nivel</th>
                                <th class="text-left px-4 py-2">Estamento</th>
                                <th class="text-left px-4 py-2">Opción</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($inscripciones as $insc)
                                <tr class="border-t">
                                    <td class="px-4 py-2">{{ $insc['nivel'] }}</td>
                                    <td class="px-4 py-2">{{ $insc['estamento'] }}</td>
                                    <td class="px-4 py-2">
                                        <button wire:click="eliminarInscripcion({{ $insc['id'] }})"
                                            wire:confirm="¿Está seguro que quiere eliminar la inscripción de {{ $insc['apellido'] }}, {{ $insc['nombre'] }} (DNI {{ $insc['dni'] }}) — {{ $insc['nivel'] }} / {{ $insc['estamento'] }}? Esta acción no se puede deshacer."
                                            class="text-red-600 hover:text-red-800 font-bold text-xs">Eliminar</button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-4 py-4 text-center text-gray-400">No hay inscripción cargada.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Documentación --}}
            @php $pasoAnteriorCompleto = !empty($inscripciones); @endphp
            <div class="bg-white border-2 border-gray-100 rounded-2xl p-6 shadow-sm space-y-4 {{ !$pasoAnteriorCompleto ? 'opacity-60' : '' }}">
                <div class="flex items-center justify-between gap-2">
                    <h3 class="font-bold text-gray-800">Subir Documentación Requerida</h3>
                    @if(!$pasoAnteriorCompleto)
                        <span class="inline-flex items-center gap-1 text-xs font-bold text-gray-500 bg-gray-100 px-2.5 py-1 rounded-full">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 1a4 4 0 00-4 4v2H5a2 2 0 00-2 2v7a2 2 0 002 2h10a2 2 0 002-2V9a2 2 0 00-2-2h-1V5a4 4 0 00-4-4zm2 6V5a2 2 0 10-4 0v2h4z" clip-rule="evenodd" />
                            </svg>
                            Bloqueado
                        </span>
                    @endif
                </div>
                @if(!$pasoAnteriorCompleto)
                    <p class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">Completá primero la inscripción (Nivel / Estamento) de arriba para habilitar la carga de documentos.</p>
                @endif

                @foreach([
                    'f2'                     => ['label' => 'Declaración Jurada de cargos F2', 'file' => 'archivoF2'],
                    'certificacion_servicio' => ['label' => 'Certificación de servicio actualizada', 'file' => 'archivoCertificacion'],
                    'concepto'               => ['label' => 'Concepto elevado por la institución', 'file' => 'archivoConcepto'],
                ] as $tipo => $cfg)
                    @php
                        $subido        = $this->documento("{$tipo}_path");
                        $seleccionado  = ${$cfg['file']};
                        $deshabilitado = $subido || !$pasoAnteriorCompleto;
                    @endphp
                    <div class="border-2 border-indigo-100 rounded-xl p-4">
                        <div class="flex items-center justify-between gap-4 flex-wrap">
                            <div>
                                <p class="font-bold text-sm text-gray-700">{{ $cfg['label'] }}</p>
                                @if($subido)
                                    <p class="text-xs text-green-600 font-bold">✓ Documento cargado</p>
                                @elseif($seleccionado)
                                    <p class="text-xs text-indigo-600 font-bold">📄 {{ $seleccionado->getClientOriginalName() }} — listo para subir</p>
                                @elseif(!$pasoAnteriorCompleto)
                                    <p class="text-xs text-gray-400">Disponible después de agregar la inscripción</p>
                                @else
                                    <p class="text-xs text-gray-400">Sin cargar (PDF, máx. 10MB)</p>
                                @endif
                            </div>
                            <div class="flex items-center gap-2">
                                <input type="file" id="archivo-{{ $tipo }}" wire:model="{{ $cfg['file'] }}" accept="application/pdf" class="hidden" @disabled($deshabilitado)>
                                <label for="archivo-{{ $tipo }}"
                                    @if($deshabilitado)
                                        class="inline-flex items-center gap-2 bg-gray-100 text-gray-400 text-xs font-black px-4 py-2.5 rounded-lg cursor-not-allowed shadow-sm border-2 border-gray-200 opacity-60 pointer-events-none transition"
                                    @elseif($seleccionado)
                                        class="inline-flex items-center gap-2 bg-gray-200 text-gray-500 text-xs font-black px-4 py-2.5 rounded-lg cursor-pointer shadow-sm border-2 border-gray-300 opacity-70 transition"
                                    @else
                                        class="inline-flex items-center gap-2 bg-amber-400 hover:bg-amber-500 text-amber-950 text-xs font-black px-4 py-2.5 rounded-lg cursor-pointer shadow-sm border-2 border-amber-500 transition"
                                    @endif>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                        <path d="M9.25 13.25a.75.75 0 001.5 0V4.636l2.955 3.129a.75.75 0 001.09-1.03l-4.25-4.5a.75.75 0 00-1.09 0l-4.25 4.5a.75.75 0 101.09 1.03L9.25 4.636v8.614z" />
                                        <path d="M3.5 12.75a.75.75 0 00-1.5 0v2.5A2.75 2.75 0 004.75 18h10.5A2.75 2.75 0 0018 15.25v-2.5a.75.75 0 00-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5z" />
                                    </svg>
                                    Seleccionar archivo
                                </label>
                                <button wire:click="subirDocumento('{{ $tipo }}')" wire:loading.attr="disabled" wire:target="{{ $cfg['file'] }},subirDocumento('{{ $tipo }}')"
                                    @disabled($deshabilitado)
                                    class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold px-3 py-2 rounded-lg disabled:bg-gray-200 disabled:text-gray-400 disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:bg-gray-200">Subir</button>
                                @if($subido)
                                    <a href="{{ Storage::url($subido) }}" target="_blank" class="text-xs font-bold text-indigo-600">Ver</a>
                                    <button wire:click="eliminarDocumento('{{ $tipo }}')"
                                        wire:confirm="¿Eliminar este documento?"
                                        class="text-xs font-bold text-red-600">Eliminar</button>
                                @endif
                            </div>
                        </div>
                        @error($cfg['file']) <p class="mt-2 text-xs text-red-600 font-bold">{{ $message }}</p> @enderror
                    </div>
                @endforeach
            </div>

            {{-- Cierre de inscripción --}}
            @php
                $documentacionCompleta = $this->documento('f2_path') && $this->documento('certificacion_servicio_path') && $this->documento('concepto_path');
                $puedeFinalizar        = $pasoAnteriorCompleto && $documentacionCompleta;
            @endphp
            <div class="bg-white border-2 border-indigo-100 rounded-2xl p-6 shadow-sm">
                    <div class="text-center space-y-3">
                        <p class="text-xs text-gray-500">Una vez cargada toda la documentación obligatoria, cerrá tu inscripción para generar la constancia.</p>
                        <button
                            @disabled(!$puedeFinalizar)
                            type="button"
                            x-on:click="
                                const faltantes = [];
                                @if(empty($inscripciones)) faltantes.push('Inscripción (nivel / estamento)'); @endif
                                @if(!$this->documento('f2_path')) faltantes.push('Declaración Jurada de cargos F2'); @endif
                                @if(!$this->documento('certificacion_servicio_path')) faltantes.push('Certificación de servicio actualizada'); @endif
                                @if(!$this->documento('concepto_path')) faltantes.push('Concepto elevado por la institución'); @endif
                                if (faltantes.length > 0) {
                                    alert('Falta completar lo siguiente antes de inscribirte:\n- ' + faltantes.join('\n- '));
                                    return;
                                }
                                if (confirm('¿Está seguro de cerrar la inscripción?')) {
                                    $wire.finalizarInscripcion();
                                }
                            "
                            class="bg-emerald-600 hover:bg-emerald-700 text-white font-black px-8 py-3 rounded-xl shadow-sm transition disabled:bg-gray-300 disabled:cursor-not-allowed disabled:hover:bg-gray-300">
                            Inscribirme
                        </button>
                        @if(!$puedeFinalizar)
                            <p class="text-xs text-gray-400">Completá la inscripción y subí los 3 documentos obligatorios para habilitar este paso.</p>
                        @endif
                    </div>
            </div>

            <div class="flex justify-start">
                <button wire:click="volverPaso(2)" class="text-gray-500 font-bold px-4 py-2">Volver</button>
            </div>
        </div>
        @endif
    @endif
    @endif
</div>
