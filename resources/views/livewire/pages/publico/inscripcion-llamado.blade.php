<?php
/**
 * inscripcion-llamado.blade.php
 *
 * Modal de inscripción docente con:
 *  - Búsqueda por DNI (trae datos si el docente ya existe)
 *  - Datos personales completos (apellido, nombre, dni, tel, email, domicilio, localidad)
 *  - Posee legajo: sí / no
 *  - Localidad (vinculada a tb_localidades -> tb_departamentos -> zona) con
 *    bloqueo total si la zona del docente no coincide con la del llamado
 *  - F2 obligatorio (sin toggle sí/no, se exige el archivo directamente)
 *  - DNI obligatorio + comprobante de residencia (factura o certificado, alcanza 1 de los 2)
 *    Todo esto se guarda en tb_domicilio, vinculado por tb_docentes.domicilio_id
 *  - Subida de títulos con nombre exacto (anti-duplicado)
 *  - Subida de certificados con nombre exacto (anti-duplicado)
 *  - Genera constancia PDF al finalizar
 *
 * Uso: @livewire('inscripcion-llamado') en publico.blade.php
 * El botón de la tabla dispara: $dispatchTo('inscripcion-llamado', 'abrirModal', { id: X })
 */

use Livewire\Attributes\On;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;

new class extends Component {
    use WithFileUploads;

    // ── Estado del modal ───────────────────────────────────────────
    public bool   $modalAbierto  = false;
    public int    $llamadoId     = 0;
    public $llamado              = null;

    // ── Pasos del wizard ───────────────────────────────────────────
    // 1: buscar DNI  2: datos personales  3: títulos/certs  4: constancia
    public int $paso = 1;

    // ── Paso 1: búsqueda DNI ───────────────────────────────────────
    public string $dniBusqueda  = '';
    public bool   $docenteExiste = false;
    public $docenteEncontrado   = null;

    // ── Paso 2: datos personales ───────────────────────────────────
    public string $apellido   = '';
    public string $nombre     = '';
    public string $dni        = '';
    public string $telefono   = '';
    public string $email      = '';
    public string $localidad  = ''; // texto legacy, se guarda en inscripciones_llamado.localidad
    public bool   $tieneLegajo = false;

    // Domicilio estructurado (tb_domicilio)
    public string $calle       = '';
    public string $numcasapiso = '';
    public string $piso        = ''; // opcional (depto/piso, ej. "3B")
    public string $barrio      = '';
    public string $manzana     = ''; // opcional

    // F2 — obligatorio, sin toggle sí/no
    public $archivoF2          = null;

    // DNI — obligatorio (salvo que ya lo tenga cargado en tb_domicilio)
    public $archivoDni                    = null;
    public ?string $dniPathExistente      = null;

    // Comprobante de residencia en la zona — alcanza con uno de los dos
    public $archivoFactura                = null;
    public ?string $facturaPathExistente  = null;
    public $archivoCertifDomicilio        = null;
    public ?string $certifDomicilioPathExistente = null;

    // Localidad -> resuelve zona (tb_localidades -> tb_departamentos -> tb_zona)
    public ?int  $localidadId = null;
    public ?int  $zonaDocente = null;
    public ?string $zonaTexto = null;
    public bool  $zonaValida  = true;

    // Domicilio ya cargado: se muestra bloqueado salvo que se solicite cambio de zona
    public bool $domicilioExistente    = false;
    public bool $domicilioAprobado     = false; // solo si tipoestado_id === 2 (Aprobado)
    public bool $solicitandoCambioZona = false;
    public ?int $domicilioIdOriginal   = null;

    // ── Paso 3: títulos ────────────────────────────────────────────
    // Títulos ya guardados en BD para este docente
    public array  $titulosExistentes     = [];
    // Títulos a agregar en esta sesión (antes de guardar)
    public array  $titulosPendientes     = [];
    // Formulario nuevo título
    public string $nuevoTituloNombre     = '';
    public string $nuevoTituloInstitucion= '';
    public string $nuevoTituloAnio       = '';
    public string $nuevoTituloRegistro   = '';
    public $nuevoTituloArchivo           = null;
    public string $errorTitulo           = '';

    // ── Paso 3: certificados ───────────────────────────────────────
    public array  $certExistentes        = [];
    public array  $certPendientes        = [];
    public string $nuevoCertNombre       = '';
    public string $nuevoCertTipo         = '';
    public string $nuevoCertAnio         = '';
    public $nuevoCertArchivo             = null;
    public string $errorCert             = '';

    // ── Paso 4: resultado ──────────────────────────────────────────
    public string $codigoConstancia      = '';
    public string $mensajeErr            = '';

    // ── Tipos de Antecedentes ───────────────────────────────────────
    public array $tiposCert = [
        'Capacitación',
        'Certificado de Servicio',
        'Concepto Anual',
        'Postíttulos(Actualización, especialización, maestría, doctorado...)',
        'Otro',
    ];

    /* ═══════════════════════════════════════════════════════════════
       ABRIR MODAL
    ═══════════════════════════════════════════════════════════════ */
    #[On('abrirModal')]
    public function abrirModal(int $id): void
    {
        $this->resetTodo();
        $this->llamadoId = $id;

        $this->llamado = DB::table('nuevo_llamado')
            ->leftJoin('tipo_llamado', 'nuevo_llamado.idtipo_llamado', '=', 'tipo_llamado.id')
            ->leftJoin('tb_zona',      'nuevo_llamado.idtb_zona',      '=', 'tb_zona.id')
            ->where('nuevo_llamado.id', $id)
            ->where('nuevo_llamado.publicado', true)
            ->select(
                'nuevo_llamado.id',
                'nuevo_llamado.fecha_fin',
                'nuevo_llamado.idtb_tipoestado',
                'tipo_llamado.nombre as tipo_nombre',
                'tb_zona.nombre_zona'
            )
            ->first();

        $this->modalAbierto = true;
        $this->paso = 1;
    }

    /* ═══════════════════════════════════════════════════════════════
       PASO 1 — BUSCAR POR DNI
    ═══════════════════════════════════════════════════════════════ */
    public function buscarDni(): void
    {
        $this->mensajeErr  = '';
        $this->errorTitulo = '';
        $this->errorCert   = '';

        $dni = preg_replace('/[^0-9]/', '', $this->dniBusqueda);

        $this->validate([
            'dniBusqueda' => 'required|digits_between:6,9',
        ], [
            'dniBusqueda.required'        => 'Ingrese su DNI para continuar.',
            'dniBusqueda.digits_between'  => 'El DNI debe tener entre 6 y 9 dígitos.',
        ]);
          // Verificar inscripción duplicada ANTES de avanzar de pantalla
            $yaInscripto = DB::table('inscripciones_llamado')
                ->where('llamado_id', $this->llamadoId)
                ->where('dni', $dni)
                ->exists();

            if ($yaInscripto) {
                $this->mensajeErr = 'Ya existe una inscripción con ese DNI para este llamado.';
                return;
            }
        $docente = DB::table('tb_docentes')->where('dni', $dni)->first();

        if ($docente) {
            $this->docenteExiste      = true;
            $this->docenteEncontrado  = $docente;
            $this->apellido   = $docente->apellido;
            $this->nombre     = $docente->nombre;
            $this->dni        = $docente->dni;
            $this->telefono   = $docente->telefono ?? '';
            $this->email      = $docente->email    ?? '';
            $this->tieneLegajo = (bool) $docente->tiene_legajo;

            $domicilio = $docente->domicilio_id
                ? DB::table('tb_domicilio')->where('idtb_domicilio', $docente->domicilio_id)->first()
                : null;

            if ($domicilio) {
                $this->cargarDatosDomicilio($domicilio);
                $this->domicilioExistente  = true;
                $this->domicilioAprobado   = ((int) ($domicilio->tipoestado_id ?? 0)) === 2;
                $this->domicilioIdOriginal = $domicilio->idtb_domicilio;
                $this->actualizarZonaDocente();
            }

            // Cargar títulos y certs existentes
            $this->titulosExistentes = DB::table('tb_docente_titulos')
                ->where('docente_id', $docente->id)
                ->get()->toArray();

            $this->certExistentes = DB::table('tb_docente_certificados')
                ->where('docente_id', $docente->id)
                ->get()->toArray();
        } else {
            $this->docenteExiste     = false;
            $this->docenteEncontrado = null;
            $this->dni = $dni;
            $this->titulosExistentes = [];
            $this->certExistentes    = [];
        }

        $this->paso = 2;
        $this->dispatch('paso-cambiado');
    }

    /* ═══════════════════════════════════════════════════════════════
       PASO 2 → 3 — VALIDAR DATOS PERSONALES
    ═══════════════════════════════════════════════════════════════ */
    public function updatedArchivoF2(): void
    {
        $this->resetValidation('archivoF2');
    }

    public function updatedArchivoDni(): void
    {
        $this->resetValidation('archivoDni');
    }

    public function irPaso3(): void
    {
        $this->mensajeErr = '';

        // Solo se considera "ya presentado" si el domicilio existe Y está aprobado.
        // Si está pendiente o rechazado, se le vuelve a pedir.
        $editaDomicilio = !($this->domicilioExistente && $this->domicilioAprobado) || $this->solicitandoCambioZona;

        $reglasBase = [
            'apellido'  => 'required|min:2|max:100',
            'nombre'    => 'required|min:2|max:100',
            'dni'       => 'required|digits_between:6,9',
            'telefono'  => 'nullable|max:30',
            'email'     => 'nullable|email|max:150',
            'archivoF2' => 'required|file|mimes:pdf|max:5120',
            'archivoDni' => ($this->dniPathExistente && $this->domicilioAprobado)
                ? 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240'
                : 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ];

        $reglasDomicilio = $editaDomicilio ? [
            'calle'                  => 'required|max:75',
            'numcasapiso'            => 'required|max:11',
            'piso'                   => 'nullable|max:45',
            'barrio'                 => 'required|max:45',
            'manzana'                => 'nullable|max:45',
            'localidadId'            => 'required',
            'archivoFactura'         => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'archivoCertifDomicilio' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ] : [];

        $this->validate(array_merge($reglasBase, $reglasDomicilio), [
            'apellido.required'    => 'El apellido es obligatorio.',
            'nombre.required'      => 'El nombre es obligatorio.',
            'dni.required'         => 'El DNI es obligatorio.',
            'dni.digits_between'   => 'El DNI debe tener entre 6 y 9 dígitos numéricos.',
            'email.email'          => 'El correo electrónico no es válido.',
            'localidadId.required' => 'Seleccione su localidad.',
            'calle.required'       => 'La calle es obligatoria.',
            'numcasapiso.required' => 'El número de casa es obligatorio.',
            'barrio.required'      => 'El barrio es obligatorio.',
            'archivoF2.required'   => 'Debe adjuntar el formulario F2 para continuar. Es un requisito obligatorio.',
            'archivoF2.mimes'      => 'El F2 debe ser un archivo PDF.',
            'archivoF2.max'        => 'El archivo F2 no puede superar 5MB.',
            'archivoDni.required'  => 'Debe adjuntar copia de su DNI.',
        ]);

        // Al menos uno de los dos: factura de servicios o certificado de domicilio
        // (solo se exige cuando se está cargando/actualizando el domicilio)
        if ($editaDomicilio) {
            $tieneProbanteZona = $this->archivoFactura || $this->archivoCertifDomicilio
                || $this->facturaPathExistente || $this->certifDomicilioPathExistente;

            if (!$tieneProbanteZona) {
                $this->addError('archivoFactura', 'Debe adjuntar factura de servicios o certificado de domicilio (al menos uno de los dos).');
                return;
            }
        }

        // Bloqueo total por zona
        $this->actualizarZonaDocente();
        if (!$this->zonaValida) {
            if ($this->domicilioExistente && $this->domicilioAprobado && !$this->solicitandoCambioZona) {
                $this->mensajeErr = 'Su domicilio registrado pertenece a una zona distinta a la de este llamado. Si se mudó, use el botón "Solicitar cambio de zona".';
            } else {
                $this->mensajeErr = 'No puede inscribirse: su localidad pertenece a una zona distinta a la de este llamado.';
            }
            return;
        }

        $this->paso = 3;
        $this->dispatch('paso-cambiado');
    }

    /* ═══════════════════════════════════════════════════════════════
       ZONA — resolver a partir de la localidad y validar contra el llamado
    ═══════════════════════════════════════════════════════════════ */
    public function updatedLocalidadId(): void
    {
        $this->localidad = $this->localidadId
            ? (string) DB::table('tb_localidades')->where('id', $this->localidadId)->value('localidad')
            : '';
        $this->actualizarZonaDocente();
    }

    private function resolverZonaIdDeLocalidad(int $localidadId): ?int
    {
        $localidad = DB::table('tb_localidades')->where('id', $localidadId)->first();
        if (!$localidad) {
            $this->zonaTexto = null;
            return null;
        }

        // 1. Excepción puntual (ej: Desiderio Tello -> Zona VII)
        $textoZona = $localidad->zona_override ?? null;

        // 2. Si no hay excepción, tomar la zona del departamento
        if (!$textoZona) {
            $textoZona = DB::table('tb_departamentos')
                ->where('iddepartamento', $localidad->iddepartamento)
                ->value('zona');
        }

        $this->zonaTexto = $textoZona ?: null;

        if (!$textoZona) {
            return null;
        }

        // 3. Convertir el texto romano ('IV') al id real de tb_zona
        return DB::table('tb_zona')
            ->whereRaw('UPPER(nombre_zona) = ?', [mb_strtoupper(trim($textoZona))])
            ->value('id');
    }

    private function actualizarZonaDocente(): void
    {
        if (!$this->localidadId) {
            $this->zonaDocente = null;
            $this->zonaValida  = true;
            return;
        }

        $this->zonaDocente = $this->resolverZonaIdDeLocalidad($this->localidadId);
        $this->validarZona();
    }

    private function validarZona(): void
    {
        if (!$this->llamado || !$this->zonaDocente) {
            $this->zonaValida = true;
            return;
        }

        $zonaLlamado = DB::table('nuevo_llamado')->where('id', $this->llamadoId)->value('idtb_zona');
        $this->zonaValida = ((int) $this->zonaDocente === (int) $zonaLlamado);
    }

    /* ═══════════════════════════════════════════════════════════════
       DOMICILIO EXISTENTE — cargar / bloquear / solicitar cambio de zona
    ═══════════════════════════════════════════════════════════════ */
    private function cargarDatosDomicilio(object $domicilio): void
    {
        $this->localidadId = $domicilio->localidad_id;
        $this->localidad    = $domicilio->localidad_id
            ? (string) DB::table('tb_localidades')->where('id', $domicilio->localidad_id)->value('localidad')
            : '';
        $this->calle       = $domicilio->calle        ?? '';
        $this->numcasapiso = $domicilio->numcasa_piso !== null ? (string) $domicilio->numcasa_piso : '';
        $this->piso        = $domicilio->piso          ?? '';
        $this->barrio      = $domicilio->barrio        ?? '';
        $this->manzana     = $domicilio->manzana       ?? '';
        $this->dniPathExistente             = $domicilio->archivo_dni;
        $this->facturaPathExistente         = $domicilio->archivo_factura;
        $this->certifDomicilioPathExistente = $domicilio->archivo_certifdomicilio;
    }

    /**
     * El docente indica que se mudó: habilita los campos de domicilio para
     * cargar la dirección nueva. No se pisa el domicilio vigente hasta que
     * el admin apruebe el cambio (queda como fila nueva en tb_domicilio,
     * estado "Cambio de zona solicitado").
     */
    public function solicitarCambioZona(): void
    {
        $this->solicitandoCambioZona = true;

        $this->calle       = '';
        $this->numcasapiso = '';
        $this->piso        = '';
        $this->barrio      = '';
        $this->manzana     = '';
        $this->localidadId = null;
        $this->zonaDocente = null;
        $this->zonaValida  = true;

        // El comprobante de domicilio debe ser nuevo (prueba la dirección nueva).
        // El DNI no cambia por una mudanza, se conserva el ya cargado.
        $this->facturaPathExistente         = null;
        $this->certifDomicilioPathExistente = null;
    }

    public function cancelarCambioZona(): void
    {
        $this->solicitandoCambioZona = false;

        if ($this->domicilioIdOriginal) {
            $domicilio = DB::table('tb_domicilio')->where('idtb_domicilio', $this->domicilioIdOriginal)->first();
            if ($domicilio) {
                $this->cargarDatosDomicilio($domicilio);
                $this->actualizarZonaDocente();
            }
        }
    }

    /* ═══════════════════════════════════════════════════════════════
       PASO 3 — AGREGAR TÍTULO
    ═══════════════════════════════════════════════════════════════ */
    public function updatedNuevoTituloArchivo(): void
    {
        $this->resetValidation('nuevoTituloArchivo');
    }

    public function agregarTitulo(): void
    {
        $this->errorTitulo = '';

        // El archivo es obligatorio únicamente si todavía no cargó ningún título
        // (ni registrado en el sistema, ni pendiente en esta misma sesión).
        $esPrimerTitulo = empty($this->titulosExistentes) && empty($this->titulosPendientes);

        $this->validate([
            'nuevoTituloNombre'      => 'required|min:3|max:200',
            'nuevoTituloInstitucion' => 'nullable|max:200',
            'nuevoTituloAnio'        => 'required|digits:4|integer|min:1950|max:' . date('Y'),
        
            'nuevoTituloArchivo'     => $esPrimerTitulo
                ? 'required|file|mimes:pdf,jpg,jpeg,png|max:10240'
                : 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ], [
            'nuevoTituloNombre.required' => 'Ingrese el nombre del título.',
            'nuevoTituloAnio.required'   => 'El año de egreso es obligatorio.',
            'nuevoTituloAnio.digits'     => 'El año debe tener 4 dígitos.',
            'nuevoTituloAnio.max'        => 'El año no puede ser posterior al actual.',
            'nuevoTituloRegistro.required' => 'El número de registro es obligatorio.',
           
            'nuevoTituloRegistro.min'      => 'El número de registro debe ser un valor positivo.',
            'nuevoTituloRegistro.max'      => 'El número de registro no puede superar 99999.',
            'nuevoTituloArchivo.required' => 'Debe adjuntar el archivo: es su primer título registrado.',
            'nuevoTituloArchivo.mimes'   => 'El archivo debe ser PDF, JPG o PNG.',
            'nuevoTituloArchivo.max'     => 'El archivo no puede superar 10MB.',
        ]);

        $nombreNormalizado = mb_strtoupper(trim($this->nuevoTituloNombre));

        // Verificar contra existentes en BD
        $existeEnBD = collect($this->titulosExistentes)
            ->contains(fn($t) => mb_strtoupper(trim((object)$t instanceof \stdClass ? $t->nombre_titulo : $t['nombre_titulo'])) === $nombreNormalizado);

        // Verificar contra pendientes en sesión
        $existeEnPendientes = collect($this->titulosPendientes)
            ->contains(fn($t) => mb_strtoupper(trim($t['nombre_titulo'])) === $nombreNormalizado);

        if ($existeEnBD || $existeEnPendientes) {
            $this->errorTitulo = 'Ya existe un título con ese nombre registrado. No puede agregarlo nuevamente.';
            return;
        }

        $archivoPath = null;
        $archivoNombre = null;
        if ($this->nuevoTituloArchivo) {
            $archivoPath   = $this->nuevoTituloArchivo->store('docentes/titulos', 'public');
            $archivoNombre = $this->nuevoTituloArchivo->getClientOriginalName();
        }

        $this->titulosPendientes[] = [
            'nombre_titulo'           => $nombreNormalizado,
            'institucion'             => trim($this->nuevoTituloInstitucion) ?: null,
            'anio_egreso'             => $this->nuevoTituloAnio ?: null,
            'num_registro' => $this->nuevoTituloRegistro ?: null,
            'archivo_path'            => $archivoPath,
            'archivo_nombre_original' => $archivoNombre,
        ];

        $this->nuevoTituloNombre      = '';
        $this->nuevoTituloInstitucion = '';
        $this->nuevoTituloAnio        = '';
        $this->nuevoTituloRegistro    = '';
        $this->nuevoTituloArchivo     = null;
    }

    public function quitarTituloPendiente(int $index): void
    {
        // Eliminar archivo temporal si existe
        if (!empty($this->titulosPendientes[$index]['archivo_path'])) {
            Storage::disk('public')->delete($this->titulosPendientes[$index]['archivo_path']);
        }
        array_splice($this->titulosPendientes, $index, 1);
    }

    /* ═══════════════════════════════════════════════════════════════
       PASO 3 — AGREGAR CERTIFICADO
    ═══════════════════════════════════════════════════════════════ */
    /* ── Limpieza en vivo de errores al corregir cada campo ────────── */
    public function updatedNuevoCertArchivo(): void
    {
        $this->resetValidation('nuevoCertArchivo');
    }

    public function updatedNuevoCertTipo(): void
    {
        $this->resetValidation('nuevoCertTipo');
    }

    public function agregarCert(): void
    {
        $this->errorCert = '';

        $this->validate([
            'nuevoCertNombre'  => 'required|min:3|max:200',
            'nuevoCertTipo'    => 'required|max:50',
            'nuevoCertAnio'    => 'nullable|digits:4|integer|min:1950|max:' . date('Y'),
            'nuevoCertArchivo' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ], [
            'nuevoCertNombre.required'  => 'Ingrese el nombre del certificado.',
            'nuevoCertTipo.required'    => 'Seleccione una categoría.',
            'nuevoCertAnio'    => 'El año es obligatorio.',
            'nuevoCertAnio'      => 'El año debe tener 4 dígitos.',
            'nuevoCertAnio.max'         => 'El año no puede ser posterior al actual.',
            'nuevoCertArchivo.required' => 'Debe adjuntar el archivo del certificado.',
            'nuevoCertArchivo.mimes'    => 'El archivo debe ser PDF, JPG o PNG.',
            'nuevoCertArchivo.max'      => 'El archivo no puede superar 10MB.',
        ]);

        $nombreNorm = mb_strtoupper(trim($this->nuevoCertNombre));

        $existeEnBD = collect($this->certExistentes)
            ->contains(fn($c) => mb_strtoupper(trim((object)$c instanceof \stdClass ? $c->nombre_certificado : $c['nombre_certificado'])) === $nombreNorm);

        $existeEnPend = collect($this->certPendientes)
            ->contains(fn($c) => mb_strtoupper(trim($c['nombre_certificado'])) === $nombreNorm);

        if ($existeEnBD || $existeEnPend) {
            $this->errorCert = 'Ya existe un certificado con ese nombre. No puede agregarlo nuevamente.';
            return;
        }

        $archivoPath = null;
        $archivoNombre = null;
        if ($this->nuevoCertArchivo) {
            $archivoPath   = $this->nuevoCertArchivo->store('docentes/certificados', 'public');
            $archivoNombre = $this->nuevoCertArchivo->getClientOriginalName();
        }

        $this->certPendientes[] = [
            'nombre_certificado'      => $nombreNorm,
            'tipo'                    => $this->nuevoCertTipo ?: null,
            // AJUSTAR: confirmar el nombre de la columna en tb_docente_certificados (se asume "anio").
            'anio'                    => $this->nuevoCertAnio ?: null,
            'archivo_path'            => $archivoPath,
            'archivo_nombre_original' => $archivoNombre,
        ];

        $this->nuevoCertNombre  = '';
        $this->nuevoCertTipo    = '';
        $this->nuevoCertAnio    = '';
        $this->nuevoCertArchivo = null;
    }

    public function quitarCertPendiente(int $index): void
    {
        if (!empty($this->certPendientes[$index]['archivo_path'])) {
            Storage::disk('public')->delete($this->certPendientes[$index]['archivo_path']);
        }
        array_splice($this->certPendientes, $index, 1);
    }

    /* ═══════════════════════════════════════════════════════════════
       GUARDAR TODO — PASO 3 → 4
    ═══════════════════════════════════════════════════════════════ */
    public function inscribirse(): void
    {
        $this->mensajeErr = '';

        // Verificar llamado activo
        $llamado = DB::table('nuevo_llamado')
            ->where('id', $this->llamadoId)
            ->where('publicado', true)
            ->first();

        if (!$llamado || $llamado->idtb_tipoestado != 8) {
            $this->mensajeErr = 'Este llamado ya está cerrado. No es posible inscribirse.';
            return;
        }

        $dni = preg_replace('/[^0-9]/', '', $this->dni);

        // Verificar inscripción duplicada en este llamado
        $yaInscripto = DB::table('inscripciones_llamado')
            ->where('llamado_id', $this->llamadoId)
            ->where('dni', $dni)
            ->exists();

        if ($yaInscripto) {
            $this->mensajeErr = 'Ya existe una inscripción con ese DNI para este llamado.';
            return;
        }

        // Título obligatorio: al menos uno, ya sea registrado previamente o cargado en esta sesión.
        // Certificados siguen siendo opcionales.
        if (empty($this->titulosExistentes) && empty($this->titulosPendientes)) {
            $this->mensajeErr = 'Debe cargar al menos un título para poder inscribirse.';
            return;
        }

        DB::transaction(function () use ($dni) {

            // 1. Crear o actualizar el docente
            $docenteId = DB::table('tb_docentes')
                ->where('dni', $dni)
                ->value('id');

            $datosDocente = [
                'dni'          => $dni,
                'apellido'     => mb_strtoupper(trim($this->apellido)),
                'nombre'       => ucwords(mb_strtolower(trim($this->nombre))),
                'telefono'     => trim($this->telefono) ?: null,
                'email'        => trim($this->email)    ?: null,
                'tiene_legajo' => $this->tieneLegajo,
                'updated_at'   => now(),
            ];

            if ($docenteId) {
                DB::table('tb_docentes')->where('id', $docenteId)->update($datosDocente);
            } else {
                $datosDocente['created_at'] = now();
                $docenteId = DB::table('tb_docentes')->insertGetId($datosDocente);
            }

            // 2. Guardar títulos pendientes
            foreach ($this->titulosPendientes as $tit) {
                // Doble verificación en BD (anti-race-condition)
                $existe = DB::table('tb_docente_titulos')
                    ->where('docente_id', $docenteId)
                    ->whereRaw('UPPER(nombre_titulo) = ?', [mb_strtoupper($tit['nombre_titulo'])])
                    ->exists();
                if (!$existe) {
                    DB::table('tb_docente_titulos')->insert([
                        'docente_id'              => $docenteId,
                        'nombre_titulo'           => $tit['nombre_titulo'],
                        'institucion'             => $tit['institucion'],
                        'anio_egreso'             => $tit['anio_egreso'],
                        'num_registro'            => $tit['num_registro'],                     
                        'archivo_path'            => $tit['archivo_path'],
                        'archivo_nombre_original' => $tit['archivo_nombre_original'],
                        'created_at'              => now(),
                        'updated_at'              => now(),
                    ]);
                }
            }

            // 3. Guardar certificados pendientes
            foreach ($this->certPendientes as $cert) {
                $existe = DB::table('tb_docente_certificados')
                    ->where('docente_id', $docenteId)
                    ->whereRaw('UPPER(nombre_certificado) = ?', [mb_strtoupper($cert['nombre_certificado'])])
                    ->exists();
                if (!$existe) {
                    DB::table('tb_docente_certificados')->insert([
                        'docente_id'              => $docenteId,
                        'nombre_certificado'      => $cert['nombre_certificado'],
                        'tipo'                    => $cert['tipo'],
                        // AJUSTAR: mismo nombre de columna que en agregarCert().
                        'anio'                    => $cert['anio'],
                        'archivo_path'            => $cert['archivo_path'],
                        'archivo_nombre_original' => $cert['archivo_nombre_original'],
                        'created_at'              => now(),
                        'updated_at'              => now(),
                    ]);
                }
            }

            // 4. Archivo F2
            $f2Path = null;
            if ($this->archivoF2) {
                $f2Path = $this->archivoF2->store('docentes/f2', 'public');
            }

            // 4.1 Archivos de domicilio (conserva el existente si no subió uno nuevo)
            $dniPath = $this->dniPathExistente;
            if ($this->archivoDni) {
                $dniPath = $this->archivoDni->store('docentes/dni', 'public');
            }

            $facturaPath = $this->facturaPathExistente;
            if ($this->archivoFactura) {
                $facturaPath = $this->archivoFactura->store('docentes/domicilio', 'public');
            }

            $certifPath = $this->certifDomicilioPathExistente;
            if ($this->archivoCertifDomicilio) {
                $certifPath = $this->archivoCertifDomicilio->store('docentes/domicilio', 'public');
            }

            // 4.2 Domicilio: reutilizar vigente, crear nuevo, o versionar por cambio de zona
            $domicilioIdVigente = DB::table('tb_docentes')->where('id', $docenteId)->value('domicilio_id');

            if ($this->domicilioExistente && $this->domicilioAprobado && !$this->solicitandoCambioZona) {
                // Domicilio ya cargado y aprobado: no se toca tb_domicilio
                $domicilioId = $domicilioIdVigente;
            } else {
                $datosDomicilio = [
                    'calle'                   => trim($this->calle),
                    'numcasa_piso'            => (int) preg_replace('/\D/', '', $this->numcasapiso) ?: null,
                    'piso'                    => trim($this->piso)  ?: null,
                    'barrio'                  => trim($this->barrio),
                    'manzana'                 => trim($this->manzana) ?: null,
                    'localidad_id'            => $this->localidadId,
                    'docente_id'              => $docenteId,
                    'archivo_dni'             => $dniPath,
                    'archivo_factura'         => $facturaPath,
                    'archivo_certifdomicilio' => $certifPath,
                    'created_at'              => now(),
                    'updated_at'              => now(),
                ];

                if ($this->solicitandoCambioZona) {
                    // Fila NUEVA (historial): no pisa la vigente hasta que el admin la apruebe
                    $datosDomicilio['tipoestado_id'] = 4; // Cambio de zona solicitado
                    $domicilioId = DB::table('tb_domicilio')
    ->insertGetId($datosDomicilio, 'idtb_domicilio');
                    // tb_docentes.domicilio_id sigue apuntando al domicilio vigente a propósito
                } else {
                    // Docente nuevo o sin domicilio previo
                    $datosDomicilio['tipoestado_id'] = 1; // Pendiente de verificación
                    $domicilioId = DB::table('tb_domicilio')
    ->insertGetId($datosDomicilio, 'idtb_domicilio');
                    DB::table('tb_docentes')->where('id', $docenteId)->update(['domicilio_id' => $domicilioId]);
                }
            }

            // Domicilio compuesto (texto legible, para el campo legacy inscripciones_llamado.domicilio)
            $domicilioTexto = trim(implode(', ', array_filter([
                trim($this->calle) . ' ' . trim($this->numcasapiso),
                $this->piso ? 'Piso ' . trim($this->piso) : null,
                $this->barrio ? 'B° ' . trim($this->barrio) : null,
                $this->manzana ? 'Mz ' . trim($this->manzana) : null,
            ])));

            // 5. Generar código de constancia único
            $codigo = strtoupper('INS-' . date('Y') . '-' . str_pad($this->llamadoId, 4, '0', STR_PAD_LEFT) . '-' . strtoupper(Str::random(6)));

            // 6. Insertar inscripción
            DB::table('inscripciones_llamado')->insert([
                'llamado_id'             => $this->llamadoId,
                'docente_id'             => $docenteId,
                'apellido'               => mb_strtoupper(trim($this->apellido)),
                'nombre'                 => ucwords(mb_strtolower(trim($this->nombre))),
                'dni'                    => $dni,
                'telefono'               => trim($this->telefono) ?: null,
                'email'                  => trim($this->email)    ?: null,
                'domicilio'              => $domicilioTexto ?: null,
                'localidad'              => trim($this->localidad) ?: null,
                'tiene_legajo'           => $this->tieneLegajo,
                'presento_f2'            => true, // siempre true: el F2 es obligatorio para llegar a este punto
                'f2_path'                => $f2Path,
                'estado'                 => 'pendiente',
                'codigo_constancia'      => $codigo,
                'constancia_generada_at' => now(),
                'created_at'             => now(),
                'updated_at'             => now(),
            ]);

            $this->codigoConstancia = $codigo;
        });

        $this->paso = 4;
        $this->dispatch('paso-cambiado');
    }

    /* ═══════════════════════════════════════════════════════════════
       HELPERS
    ═══════════════════════════════════════════════════════════════ */
    public function cerrarModal(): void
    {
        $this->modalAbierto = false;
        $this->resetTodo();
    }

    public function volverPaso(int $p): void
    {
        if ($p < $this->paso && $this->paso < 4) {
            $this->paso = $p;
            $this->dispatch('paso-cambiado');
        }
    }

    private function resetTodo(): void
    {
        $this->reset([
            'llamadoId','llamado','paso',
            'dniBusqueda','docenteExiste','docenteEncontrado',
            'apellido','nombre','dni','telefono','email',
            'calle','numcasapiso','piso','barrio','manzana','localidad',
            'tieneLegajo','archivoF2',
            'archivoDni','dniPathExistente',
            'archivoFactura','facturaPathExistente',
            'archivoCertifDomicilio','certifDomicilioPathExistente',
            'localidadId','zonaDocente','zonaTexto','zonaValida',
            'domicilioExistente','domicilioAprobado','solicitandoCambioZona','domicilioIdOriginal',
            'titulosExistentes','titulosPendientes',
            'nuevoTituloNombre','nuevoTituloInstitucion','nuevoTituloAnio','nuevoTituloRegistro','nuevoTituloArchivo',
            'errorTitulo',
            'certExistentes','certPendientes',
            'nuevoCertNombre','nuevoCertTipo','nuevoCertAnio','nuevoCertArchivo','errorCert',
            'codigoConstancia','mensajeErr',
        ]);
        $this->paso = 1;
    }
};
?>

<div>
@if($modalAbierto)
<div
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm"
    x-data
    x-on:keydown.escape.window="$wire.cerrarModal()"
>
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl mx-4 flex flex-col max-h-[94vh]">

        {{-- ══ HEADER ══════════════════════════════════════════════════ --}}
        <div class="flex items-center justify-between px-6 py-4 border-b shrink-0 bg-slate-800 rounded-t-2xl">
            <div>
                <h2 class="text-base font-black text-white uppercase tracking-tight">
                    Inscripción a La Convocatoria
                </h2>
                @if($llamado)
                <p class="text-xs text-slate-300 mt-0.5">
                    #{{ $llamado->id }}
                    @if($llamado->tipo_nombre) · {{ $llamado->tipo_nombre }} @endif
                    @if($llamado->nombre_zona)  · {{ $llamado->nombre_zona }} @endif
                    · Cierra: {{ \Carbon\Carbon::parse($llamado->fecha_fin)->format('d/m/Y H:i') }}
                </p>
                @endif
            </div>
            <button wire:click="cerrarModal"
                class="text-slate-400 hover:text-white hover:bg-slate-700 rounded-full p-1.5 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- ══ STEPPER ══════════════════════════════════════════════════ --}}
        @if($paso < 4)
        <div class="flex items-center px-6 pt-4 pb-2 shrink-0 gap-0">
            @php
                $pasos = ['DNI', 'Datos', 'Documentos'];
            @endphp
            @foreach($pasos as $i => $label)
                @php $num = $i + 1; @endphp
                <div class="flex items-center {{ $loop->last ? '' : 'flex-1' }}">
                    <button
                        wire:click="volverPaso({{ $num }})"
                        class="flex items-center gap-1.5 group"
                        @if($num >= $paso) disabled @endif
                    >
                        <span class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-black border-2 transition
                            {{ $paso === $num ? 'bg-indigo-600 border-indigo-600 text-white' :
                               ($paso > $num  ? 'bg-green-500 border-green-500 text-white cursor-pointer' :
                                               'bg-white border-gray-300 text-gray-400') }}">
                            {{ $paso > $num ? '✓' : $num }}
                        </span>
                        <span class="text-[10px] font-black uppercase tracking-wider
                            {{ $paso === $num ? 'text-indigo-700' : ($paso > $num ? 'text-green-600' : 'text-gray-400') }}">
                            {{ $label }}
                        </span>
                    </button>
                    @if(!$loop->last)
                    <div class="flex-1 h-px mx-2 {{ $paso > $num ? 'bg-green-400' : 'bg-gray-200' }}"></div>
                    @endif
                </div>
            @endforeach
        </div>
        @endif

        {{-- ══ BODY ═════════════════════════════════════════════════════ --}}
        <div class="overflow-y-auto flex-1 px-6 py-5" x-on:paso-cambiado.window="$el.scrollTop = 0">

           

            {{-- ══════════════════════════════════════════════════════════
                 PASO 1 — BÚSQUEDA POR DNI
            ══════════════════════════════════════════════════════════ --}}
            @if($paso === 1)
            <div class="flex flex-col items-center py-6 text-center">
                <div class="w-16 h-16 rounded-2xl bg-indigo-100 flex items-center justify-center mb-4">
                    <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0"/>
                    </svg>
                </div>
                <h3 class="text-lg font-black text-gray-800 mb-1">Ingrese su DNI</h3>
                <p class="text-sm text-gray-500 mb-6 max-w-xs">
                    Si ya se inscribió antes, sus datos se cargarán automáticamente.
                </p>

                <div class="w-full max-w-xs">
                    <div class="relative">
                        <input
                            wire:model="dniBusqueda"
                            wire:keydown.enter="buscarDni"
                            type="text"
                            inputmode="numeric"
                            maxlength="9"
                            placeholder="Ej: 28564343"
                            class="w-full border-2 border-gray-300 rounded-xl px-4 py-3 text-lg font-black text-center tracking-widest
                                   focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition
                                   @error('dniBusqueda') border-red-400 bg-red-50 @enderror"
                            autofocus
                        >
                    </div>
                     {{-- Error global --}}
                    @if($mensajeErr)
                    <div class="mb-4 flex items-start gap-2 p-3 bg-red-50 border border-red-200 rounded-xl text-red-700 text-sm">
                        <svg class="w-4 h-4 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        <span class="font-semibold">{{ $mensajeErr }}</span>
                    </div>
                    @endif
                    @error('dniBusqueda')
                        <p class="mt-1 text-xs text-red-600 font-semibold">{{ $message }}</p>
                    @enderror

                    <button
                        wire:click="buscarDni"
                        wire:loading.attr="disabled"
                        class="mt-4 w-full bg-indigo-600 hover:bg-indigo-700 text-white font-black py-3 rounded-xl
                               text-sm uppercase tracking-wide shadow-md transition disabled:opacity-50 flex items-center justify-center gap-2"
                    >
                        <span wire:loading.remove wire:target="buscarDni">
                            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            Buscar y continuar
                        </span>
                        <span wire:loading wire:target="buscarDni" class="flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            Buscando...
                        </span>
                    </button>
                </div>
            </div>
            @endif

            {{-- ══════════════════════════════════════════════════════════
                 PASO 2 — DATOS PERSONALES
            ══════════════════════════════════════════════════════════ --}}
            @if($paso === 2)

            {{-- Banner docente encontrado --}}
            @if($docenteExiste)
            <div class="mb-4 flex items-center gap-3 p-3 bg-green-50 border border-green-200 rounded-xl">
                <div class="w-8 h-8 rounded-full bg-green-500 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <div>
                    <p class="text-xs font-black text-green-800 uppercase">Docente encontrado</p>
                    <p class="text-xs text-green-700">Sus datos fueron cargados. Revíselos y actualícelos si es necesario.</p>
                </div>
            </div>
            @else
            <div class="mb-4 flex items-center gap-3 p-3 bg-blue-50 border border-blue-200 rounded-xl">
                <div class="w-8 h-8 rounded-full bg-blue-500 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                </div>
                <div>
                    <p class="text-xs font-black text-blue-800 uppercase">Nuevo docente</p>
                    <p class="text-xs text-blue-700">Complete sus datos personales para continuar.</p>
                </div>
            </div>
            @endif

            @php $editaDomicilio = !($domicilioExistente && $domicilioAprobado) || $solicitandoCambioZona; @endphp

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">

                {{-- Apellido --}}
                <div>
                    <label class="block text-xs font-black text-gray-600 mb-1 uppercase tracking-wide">
                        Apellido <span class="text-red-500">*</span>
                    </label>
                    <input wire:model="apellido" type="text" placeholder="Ingrese su apellido"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400 transition
                               @error('apellido') border-red-400 bg-red-50 @enderror">
                    @error('apellido') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                </div>

                {{-- Nombre --}}
                <div>
                    <label class="block text-xs font-black text-gray-600 mb-1 uppercase tracking-wide">
                        Nombre/s <span class="text-red-500">*</span>
                    </label>
                    <input wire:model="nombre" type="text" placeholder="Ingrese su nombre completo"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400 transition
                               @error('nombre') border-red-400 bg-red-50 @enderror">
                    @error('nombre') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                </div>

                {{-- DNI (readonly si ya existe) --}}
                <div>
                    <label class="block text-xs font-black text-gray-600 mb-1 uppercase tracking-wide">
                        DNI <span class="text-red-500">*</span>
                    </label>
                    <input wire:model="dni" type="text" maxlength="9" placeholder="28564343"
                        {{ $docenteExiste ? 'readonly' : '' }}
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono font-bold
                               {{ $docenteExiste ? 'bg-gray-100 text-gray-500 cursor-not-allowed' : 'focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400' }}
                               transition @error('dni') border-red-400 bg-red-50 @enderror">
                    @error('dni') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                </div>

                {{-- Teléfono --}}
                <div>
                    <label class="block text-xs font-black text-gray-600 mb-1 uppercase tracking-wide">
                        Teléfono
                    </label>
                    <input wire:model="telefono" type="text" placeholder="3804-123456"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400 transition">
                </div>

                {{-- Email --}}
                <div class="sm:col-span-2">
                    <label class="block text-xs font-black text-gray-600 mb-1 uppercase tracking-wide">
                        Correo Electrónico
                    </label>
                    <input wire:model="email" type="email" placeholder="ejemplo@correo.com"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400 transition
                               @error('email') border-red-400 bg-red-50 @enderror">
                    @error('email') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- ══ DOMICILIO Y LOCALIDAD ══════════════════════════════ --}}
            <div class="mt-4 border border-gray-200 rounded-xl p-4 bg-gray-50/60">
                <div class="flex items-center justify-between mb-3">
                    <label class="block text-xs font-black text-gray-600 uppercase tracking-wide">
                        Domicilio y Localidad
                    </label>
                    @if($domicilioExistente && $domicilioAprobado && !$solicitandoCambioZona)
                        <span class="text-[10px] font-black uppercase text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full">
                            🔒 Registrado
                        </span>
                    @elseif($domicilioExistente && !$domicilioAprobado && !$solicitandoCambioZona)
                        <span class="text-[10px] font-black uppercase text-amber-600 bg-amber-50 px-2 py-0.5 rounded-full">
                            Pendiente de aprobación — puede corregirlo
                        </span>
                    @elseif($solicitandoCambioZona)
                        <span class="text-[10px] font-black uppercase text-amber-600 bg-amber-50 px-2 py-0.5 rounded-full">
                            Nuevo domicilio propuesto
                        </span>
                    @endif
                </div>

                @if($domicilioExistente && $domicilioAprobado && !$solicitandoCambioZona)
                    {{-- MODO BLOQUEADO: domicilio ya cargado, solo lectura --}}
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
                        <div class="sm:col-span-3">
                            <span class="block text-[10px] font-bold text-gray-400 uppercase">Localidad</span>
                            <span class="text-gray-700 font-medium">
                                {{ $localidad ?: '—' }}
                                @if($zonaTexto)
                                    <span class="text-xs text-gray-400 font-normal">(Zona {{ $zonaTexto }})</span>
                                @endif
                            </span>
                        </div>
                        <div class="sm:col-span-2">
                            <span class="block text-[10px] font-bold text-gray-400 uppercase">Calle</span>
                            <span class="text-gray-700">{{ $calle ?: '—' }} {{ $numcasapiso }}</span>
                        </div>
                        <div>
                            <span class="block text-[10px] font-bold text-gray-400 uppercase">Piso/Depto</span>
                            <span class="text-gray-700">{{ $piso ?: '—' }}</span>
                        </div>
                        <div class="sm:col-span-2">
                            <span class="block text-[10px] font-bold text-gray-400 uppercase">Barrio</span>
                            <span class="text-gray-700">{{ $barrio ?: '—' }}</span>
                        </div>
                        <div>
                            <span class="block text-[10px] font-bold text-gray-400 uppercase">Manzana</span>
                            <span class="text-gray-700">{{ $manzana ?: '—' }}</span>
                        </div>
                    </div>

               
                @else
                    {{-- MODO EDITABLE: docente nuevo o solicitando cambio de zona --}}
                    @if($solicitandoCambioZona)
                    <div class="mb-3 bg-amber-50 border border-amber-200 rounded-lg p-2.5 flex items-center justify-between gap-3">
                        <p class="text-xs text-amber-700">
                            Complete su nueva dirección. Quedará sujeta a aprobación de la comisión.
                        </p>
                        <button type="button" wire:click="cancelarCambioZona"
                            class="text-xs font-bold text-amber-700 underline shrink-0">
                            Cancelar
                        </button>
                    </div>
                    @endif

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="sm:col-span-3">
                                @if(!$zonaValida)
                            <div class="mt-3 bg-red-50 border-2 border-red-300 rounded-xl p-3">
                                <p class="text-xs font-black text-red-700">
                                    ⚠ Su localidad pertenece a una zona distinta a la de este llamado. No puede continuar con la inscripción.
                                </p>
                            </div>
                            @endif
                            <label class="block text-[10px] font-bold text-gray-500 mb-1 uppercase">
                                Localidad <span class="text-red-500">*</span>
                            </label>
                            <select wire:model.live="localidadId"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400 transition
                                       @error('localidadId') border-red-400 bg-red-50 @enderror">
                                <option value="">Seleccione su localidad...</option>
                                @foreach(DB::table('tb_localidades')->orderBy('localidad')->get() as $loc)
                                    <option value="{{ $loc->id }}">{{ $loc->localidad }}</option>
                                @endforeach
                            </select>
                            @error('localidadId') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                        </div>
                        
                                <div class="sm:col-span-2">
                            <label class="block text-[10px] font-bold text-gray-500 mb-1 uppercase">
                                Calle <span class="text-red-500">*</span>
                            </label>
                            <input wire:model="calle" type="text" placeholder="Ej: Av. Ortiz de Ocampo"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400 transition
                                       @error('calle') border-red-400 bg-red-50 @enderror">
                            @error('calle') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 mb-1 uppercase">
                                N° Casa <span class="text-red-500">*</span>
                            </label>
                            <input wire:model="numcasapiso" type="text" placeholder="1700"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400 transition
                                       @error('numcasapiso') border-red-400 bg-red-50 @enderror">
                            @error('numcasapiso') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 mb-1 uppercase">
                                Piso/Depto <span class="text-gray-400 normal-case">(opcional)</span>
                            </label>
                            <input wire:model="piso" type="text" placeholder="3B"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400 transition">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-[10px] font-bold text-gray-500 mb-1 uppercase">
                                Barrio <span class="text-red-500">*</span>
                            </label>
                            <input wire:model="barrio" type="text" placeholder="Ej: Evita"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400 transition
                                       @error('barrio') border-red-400 bg-red-50 @enderror">
                            @error('barrio') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                        </div>

                        <div class="sm:col-span-3">
                            <label class="block text-[10px] font-bold text-gray-500 mb-1 uppercase">
                                Manzana <span class="text-gray-400 normal-case">(opcional)</span>
                            </label>
                            <input wire:model="manzana" type="text" placeholder="Ej: Mz 14"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400 transition">
                        </div>
                    </div>

                 
                @endif
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">

                {{-- Legajo --}}
                <div class="sm:col-span-2">
                    <label class="block text-xs font-black text-gray-600 mb-2 uppercase tracking-wide">
                        ¿Posee legajo en la institución? <span class="text-red-500">*</span>
                    </label>
                    <div class="flex gap-3">
                        <label class="flex-1 flex items-center gap-3 p-3 border-2 rounded-xl cursor-pointer transition
                            {{ $tieneLegajo ? 'border-green-500 bg-green-50' : 'border-gray-200 hover:border-gray-300' }}">
                            <input type="radio" wire:model.live="tieneLegajo" value="1"
                                class="h-4 w-4 text-green-600 border-gray-300">
                            <div>
                                <p class="text-sm font-black {{ $tieneLegajo ? 'text-green-700' : 'text-gray-600' }}">Sí, poseo legajo</p>
                                <p class="text-[10px] text-gray-400">Ya estoy registrado en la institución</p>
                            </div>
                        </label>
                        <label class="flex-1 flex items-center gap-3 p-3 border-2 rounded-xl cursor-pointer transition
                            {{ !$tieneLegajo ? 'border-slate-500 bg-slate-50' : 'border-gray-200 hover:border-gray-300' }}">
                            <input type="radio" wire:model.live="tieneLegajo" value="0"
                                class="h-4 w-4 text-slate-600 border-gray-300">
                            <div>
                                <p class="text-sm font-black {{ !$tieneLegajo ? 'text-slate-700' : 'text-gray-600' }}">No poseo legajo</p>
                                <p class="text-[10px] text-gray-400">Primera vez en la institución</p>
                            </div>
                        </label>
                    </div>
                </div>

                {{-- F2 --}}
                <div class="sm:col-span-2">
                    <label class="block text-xs font-black text-gray-600 mb-2 uppercase tracking-wide">
                        Formulario F2 <span class="text-red-500">*</span>
                    </label>
                    <p class="text-[10px] text-gray-400 mb-2">
                        Declaración Jurada de cargos (F2), actualizada al momento de la inscripción — requisito excluyente.
                    </p>

                    <div class="bg-indigo-50 border-2 rounded-xl p-3
                        {{ $errors->has('archivoF2') ? 'border-red-400 bg-red-50' : 'border-indigo-200' }}">
                        <label class="block text-xs font-black text-indigo-700 mb-1.5 uppercase">
                            Adjuntar F2 firmado (PDF, máx. 5MB)
                        </label>
                        <input type="file" wire:model="archivoF2" accept=".pdf"
                            class="w-full border border-indigo-300 rounded-lg text-xs p-2 bg-white shadow-sm">
                        <div wire:loading wire:target="archivoF2" class="text-xs text-indigo-500 mt-1">Subiendo...</div>
                        @error('archivoF2') <p class="mt-1 text-xs text-red-600 font-bold">{{ $message }}</p> @enderror
                        @if($archivoF2)
                        <p class="mt-1 text-xs text-green-600 font-bold">✓ Archivo listo para subir</p>
                        @endif
                    </div>
                </div>

                {{-- DNI --}}
                <div class="sm:col-span-2">
                    <label class="block text-xs font-black text-gray-600 mb-2 uppercase tracking-wide">
                        DNI (anverso y reverso) <span class="text-red-500">*</span>
                    </label>
                    @if($dniPathExistente)
                        <p class="text-xs text-green-600 font-bold mb-1">✓ Ya tiene DNI cargado.</p>
                    @endif
                    <div class="bg-indigo-50 border-2 rounded-xl p-3 {{ $errors->has('archivoDni') ? 'border-red-400 bg-red-50' : 'border-indigo-200' }}">
                        <label class="block text-xs font-black text-indigo-700 mb-1.5 uppercase">
                            {{ $dniPathExistente ? 'Reemplazar DNI (opcional)' : 'Adjuntar DNI (PDF/JPG/PNG, máx. 10MB)' }}
                        </label>
                        <input type="file" wire:model="archivoDni" accept=".pdf,.jpg,.jpeg,.png"
                            class="w-full border border-indigo-300 rounded-lg text-xs p-2 bg-white shadow-sm">
                        <div wire:loading wire:target="archivoDni" class="text-xs text-indigo-500 mt-1">Subiendo...</div>
                        @error('archivoDni') <p class="mt-1 text-xs text-red-600 font-bold">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Factura o Certificado de domicilio (alcanza con uno) --}}
                <div class="sm:col-span-2">
                    <label class="block text-xs font-black text-gray-600 mb-2 uppercase tracking-wide">
                        Comprobante de residencia en la zona
                        @if($editaDomicilio) <span class="text-red-500">*</span> @endif
                    </label>

                    @if($editaDomicilio)
                        <p class="text-[10px] text-gray-400 mb-2">
                            Adjunte factura de servicios (luz/agua/gas), contrato de alquiler, o certificado de domicilio. Alcanza con uno de los dos campos siguientes.
                        </p>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="bg-indigo-50 border-2 rounded-xl p-3 {{ $errors->has('archivoFactura') ? 'border-red-400 bg-red-50' : 'border-indigo-200' }}">
                                <label class="block text-xs font-black text-indigo-700 mb-1.5 uppercase">
                                    {{ $facturaPathExistente ? 'Reemplazar factura (opcional)' : 'Factura de servicios / contrato' }}
                                </label>
                                <input type="file" wire:model="archivoFactura" accept=".pdf,.jpg,.jpeg,.png"
                                    class="w-full border border-indigo-300 rounded-lg text-xs p-2 bg-white shadow-sm">
                                <div wire:loading wire:target="archivoFactura" class="text-xs text-indigo-500 mt-1">Subiendo...</div>
                                @if($facturaPathExistente)<p class="mt-1 text-xs text-green-600 font-bold">✓ Ya tiene cargado</p>@endif
                            </div>

                            <div class="bg-indigo-50 border-2 rounded-xl p-3">
                                <label class="block text-xs font-black text-indigo-700 mb-1.5 uppercase">
                                    {{ $certifDomicilioPathExistente ? 'Reemplazar certificado (opcional)' : 'Certificado de domicilio' }}
                                </label>
                                <input type="file" wire:model="archivoCertifDomicilio" accept=".pdf,.jpg,.jpeg,.png"
                                    class="w-full border border-indigo-300 rounded-lg text-xs p-2 bg-white shadow-sm">
                                <div wire:loading wire:target="archivoCertifDomicilio" class="text-xs text-indigo-500 mt-1">Subiendo...</div>
                                @if($certifDomicilioPathExistente)<p class="mt-1 text-xs text-green-600 font-bold">✓ Ya tiene cargado</p>@endif
                            </div>
                        </div>
                        @error('archivoFactura') <p class="mt-1 text-xs text-red-600 font-bold">{{ $message }}</p> @enderror
                    @else
                        <div class="bg-gray-50 border border-gray-200 rounded-xl p-3 text-xs text-gray-500">
                            ✓ Comprobante ya presentado junto con el domicilio registrado.
                        </div>
                    @endif
                </div>

            </div>
            @endif

            {{-- ══════════════════════════════════════════════════════════
                 PASO 3 — TÍTULOS Y CERTIFICADOS
            ══════════════════════════════════════════════════════════ --}}
            @if($paso === 3)

            {{-- ── TÍTULOS ─────────────────────────────────────────── --}}
            <div class="mb-6">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-black text-gray-700 uppercase tracking-wide flex items-center gap-2">
                        <span class="w-6 h-6 rounded-full bg-indigo-600 text-white text-xs flex items-center justify-center font-black">T</span>
                        Títulos
                    </h3>
                    @if(count($titulosExistentes) > 0 || count($titulosPendientes) > 0)
                    <span class="bg-indigo-100 text-indigo-700 text-xs font-black px-2 py-0.5 rounded-full">
                        {{ count($titulosExistentes) + count($titulosPendientes) }} registrado/s
                    </span>
                    @endif
                </div>

                @if(count($titulosExistentes) === 0 && count($titulosPendientes) === 0)
                <div class="mb-3 p-2.5 bg-red-50 border border-red-200 rounded-lg text-xs text-red-700 font-semibold flex items-center gap-2">
                    <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    Debe cargar al menos un título para poder inscribirse. Es obligatorio la primera vez.
                </div>
                @endif

                <div class="grid grid-cols-1 lg:grid-cols-5 gap-4">
                    {{-- Formulario nuevo título (izquierda) --}}
                    <div class="lg:col-span-3 lg:order-1">
                        <div class="bg-slate-50 border border-slate-200 rounded-xl p-4" x-data="{ archivoPendiente: false }">
                            <p class="text-[10px] font-black text-slate-500 uppercase mb-3">Agregar título</p>

                            @if($errorTitulo)
                            <div class="mb-3 p-2.5 bg-red-50 border border-red-200 rounded-lg text-xs text-red-700 font-semibold flex items-center gap-2">
                                <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                </svg>
                                {{ $errorTitulo }}
                            </div>
                            @endif

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div class="sm:col-span-2">
                                    <label class="block text-[10px] font-black text-gray-500 mb-1 uppercase">
                                        Nombre del título <span class="text-red-500">*</span>
                                    </label>
                                    <input wire:model="nuevoTituloNombre" type="text"
                                        placeholder="Ej: Profesor de Matemática, Lic. en Ciencias de la Educación"
                                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400 transition
                                               @error('nuevoTituloNombre') border-red-400 bg-red-50 @enderror">
                                    <p class="text-[9px] text-gray-400 mt-0.5">Escríbalo exactamente como figura en el título. Se usará para evitar duplicados.</p>
                                    @error('nuevoTituloNombre') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block text-[10px] font-black text-gray-500 mb-1 uppercase">Institución</label>
                                    <input wire:model="nuevoTituloInstitucion" type="text" placeholder="Ej: UNLaR"
                                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400 transition">
                                </div>
                                <div class="sm:col-span-4">
                                    <label class="block text-[10px] font-black text-gray-500 mb-1 uppercase">
                                        Año de egreso <span class="text-red-500">*</span>
                                    </label>
                                    <input wire:model="nuevoTituloAnio" type="number" min="1950" max="{{ date('Y') }}" placeholder="{{ date('Y') }}"
                                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400 transition
                                               @error('nuevoTituloAnio') border-red-400 bg-red-50 @enderror">
                                    @error('nuevoTituloAnio') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                                    <label class="block text-[10px] font-black text-gray-500 mb-1 uppercase">
                                       N° de Registro Provincial <span class="text-red-500">*</span>
                                    </label>
                                     <input wire:model="nuevoTituloRegistro" type="number"  placeholder="ingrese el número de registro del título"
                                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400 transition
                                               @error('nuevoTituloRegistro') border-red-400 bg-red-50 @enderror">
                                    @error('nuevoTituloRegistro') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block text-[10px] font-black text-gray-500 mb-1 uppercase">
                                        Adjuntar copia del título (PDF, JPG, PNG — máx. 10MB)
                                        @if(count($titulosExistentes) === 0 && count($titulosPendientes) === 0)
                                            <span class="text-red-500">*</span>
                                        @else
                                            <span class="text-gray-400 normal-case font-normal">(opcional)</span>
                                        @endif
                                    </label>
                                    <input type="file" wire:model="nuevoTituloArchivo" accept=".pdf,.jpg,.jpeg,.png"
                                        x-on:change="archivoPendiente = $event.target.files.length > 0"
                                        class="w-full border border-gray-300 rounded-lg text-xs p-2 bg-white shadow-sm
                                               @error('nuevoTituloArchivo') border-red-400 bg-red-50 @enderror">
                                    <div wire:loading wire:target="nuevoTituloArchivo" class="text-xs text-indigo-500 mt-1">Subiendo archivo...</div>
                                    @error('nuevoTituloArchivo') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror

                                    <div x-show="archivoPendiente" x-cloak
                                        class="mt-2 p-2 bg-amber-50 border border-amber-300 rounded-lg text-[11px] text-amber-800 font-semibold flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                        </svg>
                                        Seleccionaste un archivo pero todavía no lo agregaste. Hacé clic en "Agregar título" para guardarlo.
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-end mt-3">
                                <button wire:click="agregarTitulo"
                                    x-on:click="archivoPendiente = false"
                                    wire:loading.attr="disabled"
                                    wire:target="agregarTitulo,nuevoTituloArchivo"
                                    class="bg-indigo-600 hover:bg-indigo-700 text-white font-black px-5 py-2 rounded-lg text-xs uppercase shadow-sm transition disabled:opacity-50 flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"/>
                                    </svg>
                                    Agregar título
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Documentos ya agregados (derecha, visibles sin scrollear) --}}
                    <div class="lg:col-span-2 lg:order-2">
                        <div class="lg:sticky lg:top-0 space-y-3">
                            {{-- Títulos existentes en BD --}}
                            @if(count($titulosExistentes) > 0)
                            <div>
                                <p class="text-[10px] font-black text-gray-400 uppercase mb-1.5">Ya registrados en el sistema</p>
                                @foreach($titulosExistentes as $tit)
                                @php $tit = (object)$tit; @endphp
                                <div class="flex items-center gap-2 p-2.5 bg-gray-50 border border-gray-200 rounded-lg mb-1.5">
                                    <svg class="w-4 h-4 text-green-500 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-bold text-gray-800 truncate">{{ $tit->nombre_titulo }}</p>
                                        @if($tit->institucion)
                                        <p class="text-[10px] text-gray-500">{{ $tit->institucion }} @if($tit->anio_egreso) · {{ $tit->anio_egreso }} @endif</p>
                                        @if($tit->num_registro)
                                        <p class="text-[10px] text-gray-500">Registro: {{ $tit->num_registro }}</p>
                                        @endif
                                        @endif
                                    </div>
                                    @if($tit->archivo_path)
                                    <a href="{{ Storage::url($tit->archivo_path) }}" target="_blank"
                                        class="text-indigo-500 hover:text-indigo-700 transition shrink-0" title="Ver archivo">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/>
                                        </svg>
                                    </a>
                                    @endif
                                </div>
                                @endforeach
                            </div>
                            @endif

                            {{-- Títulos pendientes (sesión actual) --}}
                            @if(count($titulosPendientes) > 0)
                            <div>
                                <p class="text-[10px] font-black text-amber-600 uppercase mb-1.5">A agregar en esta inscripción</p>
                                @foreach($titulosPendientes as $i => $tit)
                                <div class="flex items-center gap-2 p-2.5 bg-amber-50 border border-amber-200 rounded-lg mb-1.5">
                                    <svg class="w-4 h-4 text-amber-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9"/>
                                    </svg>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-bold text-gray-800 truncate">{{ $tit['nombre_titulo'] }}</p>
                                        @if($tit['institucion'])
                                        <p class="text-[10px] text-gray-500">{{ $tit['institucion'] }} @if($tit['anio_egreso']) · {{ $tit['anio_egreso'] }} @endif</p>
                                       
                                        @endif
                                    </div>
                                    <button wire:click="quitarTituloPendiente({{ $i }})"
                                        class="text-red-400 hover:text-red-600 transition shrink-0">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                        </svg>
                                    </button>
                                </div>
                                @endforeach
                            </div>
                            @endif

                            @if(count($titulosExistentes) === 0 && count($titulosPendientes) === 0)
                            <div class="p-4 border border-dashed border-gray-200 rounded-lg text-center">
                                <p class="text-[11px] text-gray-400">Los títulos que agregues van a aparecer acá.</p>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="border-t border-gray-100 my-4"></div>

            {{-- ── CERTIFICADOS ────────────────────────────────────── --}}
            <div class="mb-2">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-black text-gray-700 uppercase tracking-wide flex items-center gap-2">
                        <span class="w-6 h-6 rounded-full bg-teal-600 text-white text-xs flex items-center justify-center font-black">C</span>
                        Antecedentes
                    </h3>
                    @if(count($certExistentes) > 0 || count($certPendientes) > 0)
                    <span class="bg-teal-100 text-teal-700 text-xs font-black px-2 py-0.5 rounded-full">
                        {{ count($certExistentes) + count($certPendientes) }} registrado/s
                    </span>
                    @endif
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-5 gap-4">
                    {{-- Formulario nuevo certificado (izquierda) --}}
                    <div class="lg:col-span-3 lg:order-1">
                        <div class="bg-slate-50 border border-slate-200 rounded-xl p-4" x-data="{ archivoPendiente: false }">
                            <p class="text-[10px] font-black text-slate-500 uppercase mb-3">Agregar certificado</p>

                            @if($errorCert)
                            <div class="mb-3 p-2.5 bg-red-50 border border-red-200 rounded-lg text-xs text-red-700 font-semibold flex items-center gap-2">
                                <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                </svg>
                                {{ $errorCert }}
                            </div>
                            @endif

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div class="sm:col-span-2">
                                    <label class="block text-[10px] font-black text-gray-500 mb-1 uppercase">
                                        Nombre del certificado <span class="text-red-500">*</span>
                                    </label>
                                    <input wire:model="nuevoCertNombre" type="text"
                                        placeholder="Ej: Capacitación en TIC para docentes — MEC 2023"
                                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-400 focus:border-teal-400 transition
                                               @error('nuevoCertNombre') border-red-400 bg-red-50 @enderror">
                                    <p class="text-[9px] text-gray-400 mt-0.5">Escríbalo exactamente como figura en el certificado.</p>
                                    @error('nuevoCertNombre') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black text-gray-500 mb-1 uppercase">
                                        Tipo (categoría) <span class="text-red-500">*</span>
                                    </label>
                                    <select wire:model="nuevoCertTipo"
                                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-400 focus:border-teal-400 transition
                                               @error('nuevoCertTipo') border-red-400 bg-red-50 @enderror">
                                        <option value="">Seleccione...</option>
                                        @foreach($tiposCert as $tipo)
                                            <option value="{{ $tipo }}">{{ $tipo }}</option>
                                        @endforeach
                                    </select>
                                    @error('nuevoCertTipo') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                                </div>
                                <!-- <div>
                                    <label class="block text-[10px] font-black text-gray-500 mb-1 uppercase">
                                        Año <span class="text-red-500">*</span>
                                    </label>
                                    <input wire:model="nuevoCertAnio" type="number" min="1950" max="{{ date('Y') }}" placeholder="{{ date('Y') }}"
                                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-400 focus:border-teal-400 transition
                                               @error('nuevoCertAnio') border-red-400 bg-red-50 @enderror">
                                    @error('nuevoCertAnio') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                                </div> -->
                                <div class="sm:col-span-2">
                                    <label class="block text-[10px] font-black text-gray-500 mb-1 uppercase">
                                        Adjuntar (PDF, JPG, PNG — máx. 10MB) <span class="text-red-500">*</span>
                                    </label>
                                    <input type="file" wire:model="nuevoCertArchivo" accept=".pdf,.jpg,.jpeg,.png"
                                        x-on:change="archivoPendiente = $event.target.files.length > 0"
                                        class="w-full border border-gray-300 rounded-lg text-xs p-2 bg-white shadow-sm">
                                    <div wire:loading wire:target="nuevoCertArchivo" class="text-xs text-teal-500 mt-1">Subiendo archivo...</div>
                                    @error('nuevoCertArchivo') <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p> @enderror

                                    <div x-show="archivoPendiente" x-cloak
                                        class="mt-2 p-2 bg-amber-50 border border-amber-300 rounded-lg text-[11px] text-amber-800 font-semibold flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                        </svg>
                                        Seleccionaste un archivo pero todavía no lo agregaste. Hacé clic en "Agregar certificado" para guardarlo.
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-end mt-3">
                                <button wire:click="agregarCert"
                                    x-on:click="archivoPendiente = false"
                                    wire:loading.attr="disabled"
                                    wire:target="agregarCert,nuevoCertArchivo"
                                    class="bg-teal-600 hover:bg-teal-700 text-white font-black px-5 py-2 rounded-lg text-xs uppercase shadow-sm transition disabled:opacity-50 flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"/>
                                    </svg>
                                    Agregar certificado
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Documentos ya agregados (derecha, visibles sin scrollear) --}}
                    <div class="lg:col-span-2 lg:order-2">
                        <div class="lg:sticky lg:top-0 space-y-3">
                            {{-- Certs existentes --}}
                            @if(count($certExistentes) > 0)
                            <div>
                                <p class="text-[10px] font-black text-gray-400 uppercase mb-1.5">Ya registrados en el sistema</p>
                                @foreach($certExistentes as $cert)
                                @php $cert = (object)$cert; @endphp
                                <div class="flex items-center gap-2 p-2.5 bg-gray-50 border border-gray-200 rounded-lg mb-1.5">
                                    <svg class="w-4 h-4 text-teal-500 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-bold text-gray-800 truncate">{{ $cert->nombre_certificado }}</p>
                                        @if($cert->tipo || ($cert->anio ?? null)) <p class="text-[10px] text-gray-500">{{ $cert->tipo }} @if($cert->anio ?? null) · {{ $cert->anio }} @endif</p> @endif
                                    </div>
                                    @if($cert->archivo_path)
                                    <a href="{{ Storage::url($cert->archivo_path) }}" target="_blank"
                                        class="text-teal-500 hover:text-teal-700 transition shrink-0" title="Ver archivo">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/>
                                        </svg>
                                    </a>
                                    @endif
                                </div>
                                @endforeach
                            </div>
                            @endif

                            {{-- Certs pendientes --}}
                            @if(count($certPendientes) > 0)
                            <div>
                                <p class="text-[10px] font-black text-amber-600 uppercase mb-1.5">A agregar en esta inscripción</p>
                                @foreach($certPendientes as $i => $cert)
                                <div class="flex items-center gap-2 p-2.5 bg-amber-50 border border-amber-200 rounded-lg mb-1.5">
                                    <svg class="w-4 h-4 text-amber-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9"/>
                                    </svg>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-bold text-gray-800 truncate">{{ $cert['nombre_certificado'] }}</p>
                                        @if($cert['tipo'] || ($cert['anio'] ?? null)) <p class="text-[10px] text-gray-500">{{ $cert['tipo'] }} @if($cert['anio'] ?? null) · {{ $cert['anio'] }} @endif</p> @endif
                                    </div>
                                    <button wire:click="quitarCertPendiente({{ $i }})"
                                        class="text-red-400 hover:text-red-600 transition shrink-0">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                        </svg>
                                    </button>
                                </div>
                                @endforeach
                            </div>
                            @endif

                            @if(count($certExistentes) === 0 && count($certPendientes) === 0)
                            <div class="p-4 border border-dashed border-gray-200 rounded-lg text-center">
                                <p class="text-[11px] text-gray-400">Los certificados que agregues van a aparecer acá.</p>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            @endif

            {{-- ══════════════════════════════════════════════════════════
                 PASO 4 — CONSTANCIA
            ══════════════════════════════════════════════════════════ --}}
            @if($paso === 4)
            <div class="flex flex-col items-center py-6 text-center">
                <div class="w-20 h-20 rounded-2xl bg-green-100 flex items-center justify-center mb-4 shadow-inner">
                    <svg class="w-10 h-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h3 class="text-xl font-black text-gray-800 mb-1">¡Inscripción Registrada!</h3>
                <p class="text-sm text-gray-500 max-w-sm mb-6">
                    Su inscripción fue enviada exitosamente. Descargue su constancia como comprobante.
                </p>

                {{-- Código --}}
                <div class="bg-slate-800 text-white rounded-2xl px-8 py-5 mb-6 shadow-lg">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Código de constancia</p>
                    <p class="text-2xl font-black tracking-widest text-white">{{ $codigoConstancia }}</p>
                    <p class="text-[10px] text-slate-400 mt-1">Guarde este código como comprobante de inscripción.</p>
                </div>

                {{-- Botón descargar --}}
                <a
                    href="{{ route('constancia.descargar', $codigoConstancia) }}"
                    target="_blank"
                    class="flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white font-black px-8 py-3 rounded-xl text-sm uppercase shadow-md transition mb-3"
                >
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/>
                    </svg>
                    Descargar constancia PDF
                </a>

                <button wire:click="cerrarModal"
                    class="text-sm text-gray-400 hover:text-gray-600 font-semibold transition">
                    Cerrar ventana
                </button>
            </div>
            @endif

        </div>{{-- /body --}}

        {{-- ══ FOOTER ═══════════════════════════════════════════════════ --}}
        @if($paso < 4)
        <div class="flex items-center justify-between px-6 py-4 border-t bg-gray-50 rounded-b-2xl shrink-0">
            <button wire:click="cerrarModal"
                class="px-4 py-2 text-xs font-bold text-gray-500 hover:text-gray-800 uppercase transition">
                Cancelar
            </button>

            <div class="flex items-center gap-2">
                @if($paso === 2)
                <button wire:click="irPaso3"
                    wire:loading.attr="disabled"
                    wire:target="irPaso3,archivoF2,archivoDni,archivoFactura,archivoCertifDomicilio"
                    @if(!$zonaValida) disabled @endif
                    class="bg-indigo-600 hover:bg-indigo-700 text-white font-black px-6 py-2.5 rounded-xl text-sm uppercase shadow-md transition disabled:opacity-40 disabled:cursor-not-allowed flex items-center gap-2">
                    <span wire:loading.remove wire:target="irPaso3,archivoF2,archivoDni,archivoFactura,archivoCertifDomicilio">
                        Continuar
                        <svg class="w-4 h-4 inline ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                        </svg>
                    </span>
                    <span wire:loading wire:target="irPaso3,archivoF2,archivoDni,archivoFactura,archivoCertifDomicilio" class="flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        Procesando...
                    </span>
                </button>
                @endif

                @if($paso === 3)
                @php
                    $tieneAlgunTitulo = count($titulosExistentes) + count($titulosPendientes) > 0;
                    $tieneAlgunCert   = count($certExistentes) + count($certPendientes) > 0;
                @endphp
                <p class="text-[10px] {{ $tieneAlgunTitulo ? 'text-gray-400' : 'text-red-500 font-bold' }} mr-2">
                    {{ $tieneAlgunTitulo ? 'Certificados opcionales.' : 'Debe agregar al menos un título para continuar.' }}
                </p>
                <button wire:click="inscribirse"
                    wire:key="btn-inscribirse-{{ $tieneAlgunCert ? 'con-cert' : 'sin-cert' }}"
                    wire:loading.attr="disabled"
                    wire:target="inscribirse"
                    @if(!$tieneAlgunTitulo) disabled @endif
                    @if(!$tieneAlgunCert) wire:confirm="No cargó ningún certificado. ¿Está seguro de que quiere inscribirse sin certificados?" @endif
                    class="bg-green-600 hover:bg-green-700 text-white font-black px-6 py-2.5 rounded-xl text-sm uppercase shadow-md transition disabled:opacity-40 disabled:cursor-not-allowed flex items-center gap-2">
                    <span wire:loading.remove wire:target="inscribirse">
                        <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Enviar inscripción
                    </span>
                    <span wire:loading wire:target="inscribirse" class="flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        Guardando...
                    </span>
                </button>
                @endif
            </div>
        </div>
        @endif

    </div>{{-- /modal card --}}
</div>{{-- /overlay --}}
@endif
</div>