<?php

use Livewire\Volt\Component;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;
    protected $queryString = [
        'page'
    ];
    // Listas para combos
    public $zonas              = [];
    public $tipos_llamado      = [];
    public $estados            = [];
    public $situaciones_revista = [];
    public $zona_detalle_id = '';

    // Combos dependientes
    public $institutos = [];
    public $carreras   = [];
    public $espacios   = [];
    public $cargos     = [];

    // Datos de la cabecera
    public $idtb_zona        = '';
    public $idtipo_llamado   = '';
    public $idtb_tipoestado  = 8;
    public $fecha_ini        = '';
    public $fecha_fin        = '';
    public $url_form         = '';
    public $descripcion      = '';

    // Datos del detalle
    public $instituto_id                 = '';
    public $carrera_id                   = '';
    public $es_cargo                     = false;
    public $cargo_es_por_carrera         = false;
    public $nuevo_rel_carrera_espacio_id = '';
    public $nuevo_rel_carrera_cargo_id   = '';
    public $horario_espacio              = '';
    public $situacion_revista_id         = '';

     // Preview de la relación seleccionada (solo lectura, datos de nuevo_rel_carrera_espacio/cargo)
    public array $rel_preview = [];
    
    // Lista temporal de detalles
    public $detalles_agregados = [];
    public $ultimo_cierre      = null;

    // Modal edición
    public $modalAbierto            = false;
    public $editando                = null;
    public $editando_detalle_index = null;

    public function mount()
    {
        $this->zonas               = DB::table('tb_zona')->orderBy('nombre_zona')->get()->toArray();
        $this->tipos_llamado       = DB::table('tipo_llamado')->orderBy('nombre')->get()->toArray();
        $this->estados             = DB::table('tb_tipoestado')->orderBy('nombre_tipoestado')->get()->toArray();
        $this->situaciones_revista = DB::table('tb_situacion_revista')->orderBy('nombre_situacion_revista')->get()->toArray();
    }

    private function tz(): string
    {
        return config('app.timezone', 'America/Argentina/Buenos_Aires');
    }

    private function parsearFecha(string $value): \Carbon\Carbon
    {
        return \Carbon\Carbon::createFromFormat('Y-m-d\TH:i', $value, $this->tz());
    }

    public function updatedIdtbZona($value)
    {
        $this->institutos = $value
            ? DB::table('tb_instituto_superior')->where('zona_id', $value)->orderBy('nombre')->get()->toArray()
            : [];
        $this->instituto_id = '';
        $this->carreras     = [];
        $this->carrera_id   = '';
        $this->espacios     = [];
        $this->cargos       = [];
    }

    public function updatedZonaDetalleId($value)
    {
        $this->institutos = $value
            ? DB::table('tb_instituto_superior')->where('zona_id', $value)->orderBy('nombre')->get()->toArray()
            : [];
        $this->instituto_id = '';
        $this->carreras     = [];
        $this->carrera_id   = '';
        $this->espacios     = [];
        $this->cargos       = [];
    }
    public function updatedInstitutoId($value)
    {
        $this->carrera_id           = '';
        $this->espacios             = [];
        $this->cargos               = [];
        $this->cargo_es_por_carrera = false;
        $this->nuevo_rel_carrera_espacio_id = '';
        $this->nuevo_rel_carrera_cargo_id   = '';

        if (!$value) {
            $this->carreras = [];
            return;
        }

        if ($this->es_cargo) {
            $this->carreras = [];
            $this->cargarCargosPorInstituto((int)$value);
        } else {
            $this->carreras = DB::table('tb_carreras')
                ->join('rel_instsup_carrera', 'tb_carreras.id', '=', 'rel_instsup_carrera.carrera_id')
                ->where('rel_instsup_carrera.instituto_id', $value)
                ->select('tb_carreras.*')
                ->orderBy('tb_carreras.nombre')
                ->get()->toArray();
        }
    }

    private function cargarCargosPorInstituto(int $institutoId): void
    {
        $this->cargos = DB::table('nuevo_rel_carrera_cargo')
            ->join('tb_cargos', 'nuevo_rel_carrera_cargo.cargo_id', '=', 'tb_cargos.id')
            ->join('rel_instsup_carrera', 'nuevo_rel_carrera_cargo.carrera_id', '=', 'rel_instsup_carrera.carrera_id')
            ->where('rel_instsup_carrera.instituto_id', $institutoId)
            ->select(
                'nuevo_rel_carrera_cargo.id',
                'tb_cargos.nombre_cargo',
                'tb_cargos.es_por_carrera',
                'nuevo_rel_carrera_cargo.carrera_id',
            )
            ->distinct()
            ->orderBy('tb_cargos.nombre_cargo')
            ->get()->toArray();
    }

    public function updatedNuevoRelCarreraCargoId($value)
    {
        $this->cargo_es_por_carrera = false;
        $this->carreras             = [];
        $this->carrera_id           = '';

        if (!$value) return;

        $cargo = collect($this->cargos)->firstWhere('id', (int)$value);
        $esPorCarrera = $cargo ? (is_array($cargo) ? $cargo['es_por_carrera'] : $cargo->es_por_carrera) : false;

        if ($esPorCarrera) {
            $this->cargo_es_por_carrera = true;
            $this->carreras = DB::table('tb_carreras')
                ->join('rel_instsup_carrera', 'tb_carreras.id', '=', 'rel_instsup_carrera.carrera_id')
                ->where('rel_instsup_carrera.instituto_id', $this->instituto_id)
                ->select('tb_carreras.*')
                ->orderBy('tb_carreras.nombre')
                ->get()->toArray();
        }
    }

    public function updatedCarreraId($value)
    {
        // En modo cargo no aplica (los cargos se cargan por instituto)
        if ($this->es_cargo) return;

        if ($value) {
            $this->espacios = DB::table('nuevo_rel_carrera_espacio')
                ->join('tb_espacioscurriculares', 'nuevo_rel_carrera_espacio.espacio_id', '=', 'tb_espacioscurriculares.idEspacioCurricular')
                ->where('nuevo_rel_carrera_espacio.carrera_id', $value)
                ->select('nuevo_rel_carrera_espacio.id', 'tb_espacioscurriculares.nombre_espacio')
                ->get()->toArray();
        } else {
            $this->espacios = [];
        }
        $this->nuevo_rel_carrera_espacio_id = '';
    }

    public function updatedIdtipoLlamado($value)
    {
        $this->es_cargo             = ((int)$value === 1);
        $this->cargo_es_por_carrera = false;
        $this->nuevo_rel_carrera_espacio_id = '';
        $this->nuevo_rel_carrera_cargo_id   = '';
        $this->carrera_id = '';
        $this->carreras   = [];
        $this->espacios   = [];
        $this->cargos     = [];

        // Si ya hay instituto y es cargo, cargar cargos de ese instituto
        if ($this->es_cargo && $this->instituto_id) {
            $this->cargarCargosPorInstituto((int)$this->instituto_id);
        }
    }

    public function actualizarEstadoPorFecha($value)
    {
        if (!$value) return;
        try {
            $this->idtb_tipoestado = $this->parsearFecha($value)->lte(\Carbon\Carbon::now($this->tz())) ? 9 : 8;
        } catch (\Exception $e) {}
    }

    public function checkCurrentStatus()
    {
        if (!$this->fecha_fin) return;
        try {
            $this->idtb_tipoestado = $this->parsearFecha($this->fecha_fin)->lte(\Carbon\Carbon::now($this->tz())) ? 9 : 8;
        } catch (\Exception $e) {}
    }

    public function cerrarVencidos()
    {
        $ahora = \Carbon\Carbon::now($this->tz())->format('Y-m-d H:i:00');

        DB::table('nuevo_llamado')
            ->where('idtb_tipoestado', 8)
            ->where('fecha_fin', '<=', $ahora)
            ->update(['idtb_tipoestado' => 9]);

        $this->ultimo_cierre = $ahora;
    }

    public function agregarDetalle()
    {
        $this->validate([
            'instituto_id'         => 'required',
            'horario_espacio'      => 'required',
            'situacion_revista_id' => 'required',
        ]);

        $sit_revista      = DB::table('tb_situacion_revista')
            ->where('idtb_situacion_revista', $this->situacion_revista_id)
            ->value('nombre_situacion_revista');
        $instituto_nombre = DB::table('tb_instituto_superior')
            ->where('id', $this->instituto_id)->value('nombre');

        if ($this->es_cargo) {
            $this->validate(['nuevo_rel_carrera_cargo_id' => 'required']);
            if ($this->cargo_es_por_carrera) {
                $this->validate(['carrera_id' => 'required']);
            }

            $rel = DB::table('nuevo_rel_carrera_cargo')
                ->join('tb_cargos',  'nuevo_rel_carrera_cargo.cargo_id', '=', 'tb_cargos.id')
                ->leftJoin('tb_perfil', 'nuevo_rel_carrera_cargo.perfil_id', '=', 'tb_perfil.idtb_perfil')
                ->where('nuevo_rel_carrera_cargo.id', $this->nuevo_rel_carrera_cargo_id)
                ->select(
                    'tb_cargos.nombre_cargo as nombre',
                    'tb_cargos.es_por_carrera',
                    'tb_cargos.hora_catedra',
                    'tb_perfil.nombre_perfil as perfil',
                )->first();

            if (!$rel) {
                session()->flash('error', 'No se encontró la información del cargo seleccionado.');
                return;
            }

            $carreraId     = $this->cargo_es_por_carrera ? $this->carrera_id : null;
            $carreraNombre = $carreraId
                ? DB::table('tb_carreras')->where('id', $carreraId)->value('nombre')
                : null;

            $nuevo_item = [
                'instituto_id'         => $this->instituto_id,
                'instituto_nombre'     => $instituto_nombre,
                'carrera_id'           => $carreraId,
                'carrera_nombre'       => $carreraNombre,
                'tipo'                 => 'Cargo',
                'id_rel'               => $this->nuevo_rel_carrera_cargo_id,
                'nombre'               => $rel->nombre,
                'hora_catedra'         => $rel->hora_catedra,
                'anio'                 => null,
                'periodo'              => null,
                'turno'                => null,
                'perfil'               => $rel->perfil,
                'horario_espacio'      => $this->horario_espacio,
                'situacion_revista_id' => $this->situacion_revista_id,
                'situacion_revista'    => $sit_revista,
            ];
        } else {
            $this->validate([
                'carrera_id'                   => 'required',
                'nuevo_rel_carrera_espacio_id' => 'required',
            ]);

            $rel = DB::table('nuevo_rel_carrera_espacio')
                ->join('tb_espacioscurriculares', 'nuevo_rel_carrera_espacio.espacio_id', '=', 'tb_espacioscurriculares.idEspacioCurricular')
                ->leftJoin('tb_periodo_cursado',  'nuevo_rel_carrera_espacio.periodo_id', '=', 'tb_periodo_cursado.idtb_periodo_cursado')
                ->leftJoin('tb_turnos',           'nuevo_rel_carrera_espacio.turno_id',   '=', 'tb_turnos.id')
                ->leftJoin('tb_perfil',           'nuevo_rel_carrera_espacio.perfil_id',  '=', 'tb_perfil.idtb_perfil')
                ->where('nuevo_rel_carrera_espacio.id', $this->nuevo_rel_carrera_espacio_id)
                ->select(
                    'tb_espacioscurriculares.nombre_espacio as nombre',
                    'nuevo_rel_carrera_espacio.hora_catedra',
                    'nuevo_rel_carrera_espacio.anio',
                    'tb_periodo_cursado.nombre_periodo as periodo',
                    'tb_turnos.nombre_turno as turno',
                    'tb_perfil.nombre_perfil as perfil',
                )->first();

            if (!$rel) {
                session()->flash('error', 'No se encontró la información del espacio seleccionado.');
                return;
            }

            $nuevo_item = [
                'instituto_id'         => $this->instituto_id,
                'instituto_nombre'     => $instituto_nombre,
                'carrera_id'           => $this->carrera_id,
                'carrera_nombre'       => DB::table('tb_carreras')->where('id', $this->carrera_id)->value('nombre'),
                'tipo'                 => 'Espacio',
                'id_rel'               => $this->nuevo_rel_carrera_espacio_id,
                'nombre'               => $rel->nombre,
                'hora_catedra'         => $rel->hora_catedra,
                'anio'                 => $rel->anio,
                'periodo'              => $rel->periodo,
                'turno'                => $rel->turno,
                'perfil'               => $rel->perfil,
                'horario_espacio'      => $this->horario_espacio,
                'situacion_revista_id' => $this->situacion_revista_id,
                'situacion_revista'    => $sit_revista,
            ];
        }

        if ($this->editando_detalle_index !== null) {
            $this->detalles_agregados[$this->editando_detalle_index] = $nuevo_item;
            $this->editando_detalle_index = null;
        } else {
            $this->detalles_agregados[] = $nuevo_item;
        }

        $this->horario_espacio              = '';
        $this->situacion_revista_id         = '';
        $this->nuevo_rel_carrera_espacio_id = '';
        $this->nuevo_rel_carrera_cargo_id   = '';
        $this->carrera_id                   = '';
        $this->cargo_es_por_carrera         = false;
    }

    public function cargarDetalle($index)
    {
        $det = $this->detalles_agregados[$index];
        $this->editando_detalle_index = $index;

        $zona_id = DB::table('tb_instituto_superior')
            ->where('id', $det['instituto_id'])
            ->value('zona_id');

        $this->zona_detalle_id = $zona_id;
        $this->institutos = DB::table('tb_instituto_superior')
            ->where('zona_id', $zona_id)
            ->orderBy('nombre')
            ->get()->toArray();

        $this->instituto_id = $det['instituto_id'];
        $this->es_cargo     = ($det['tipo'] === 'Cargo');

        if ($this->es_cargo) {
            $this->cargarCargosPorInstituto((int)$this->instituto_id);
            $this->nuevo_rel_carrera_cargo_id   = $det['id_rel'];
            $this->nuevo_rel_carrera_espacio_id = '';
            $this->carreras                     = [];

            // Detectar si es Bedel
            $esPorCarrera = DB::table('nuevo_rel_carrera_cargo')
                ->join('tb_cargos', 'nuevo_rel_carrera_cargo.cargo_id', '=', 'tb_cargos.id')
                ->where('nuevo_rel_carrera_cargo.id', $det['id_rel'])
                ->value('tb_cargos.es_por_carrera');

            $this->cargo_es_por_carrera = (bool) $esPorCarrera;
            if ($this->cargo_es_por_carrera && !empty($det['carrera_id'])) {
                $this->carreras = DB::table('tb_carreras')
                    ->join('rel_instsup_carrera', 'tb_carreras.id', '=', 'rel_instsup_carrera.carrera_id')
                    ->where('rel_instsup_carrera.instituto_id', $this->instituto_id)
                    ->select('tb_carreras.*')
                    ->orderBy('tb_carreras.nombre')
                    ->get()->toArray();
                $this->carrera_id = $det['carrera_id'];
            } else {
                $this->carrera_id = '';
            }
        } else {
            $this->cargo_es_por_carrera = false;
            $this->carreras = DB::table('tb_carreras')
                ->join('rel_instsup_carrera', 'tb_carreras.id', '=', 'rel_instsup_carrera.carrera_id')
                ->where('rel_instsup_carrera.instituto_id', $this->instituto_id)
                ->select('tb_carreras.*')
                ->orderBy('tb_carreras.nombre')
                ->get()->toArray();
            $this->carrera_id = $det['carrera_id'];
            $this->espacios = DB::table('nuevo_rel_carrera_espacio')
                ->join('tb_espacioscurriculares', 'nuevo_rel_carrera_espacio.espacio_id', '=', 'tb_espacioscurriculares.idEspacioCurricular')
                ->where('nuevo_rel_carrera_espacio.carrera_id', $det['carrera_id'])
                ->select('nuevo_rel_carrera_espacio.id', 'tb_espacioscurriculares.nombre_espacio')
                ->get()->toArray();
            $this->nuevo_rel_carrera_espacio_id = $det['id_rel'];
            $this->nuevo_rel_carrera_cargo_id   = '';
        }

        $this->horario_espacio      = $det['horario_espacio'];
        $this->situacion_revista_id = $det['situacion_revista_id'];
    }

    public function quitarDetalle($index)
    {
        unset($this->detalles_agregados[$index]);
        $this->detalles_agregados = array_values($this->detalles_agregados);
    }

    public function guardar()
    {
        $this->validate([
            'idtb_zona'          => 'required',
            'idtipo_llamado'     => 'required',
            'fecha_ini'          => 'required',
            'fecha_fin'          => 'required',
            'idtb_tipoestado'    => 'required',
            'detalles_agregados' => 'required|array|min:1',
        ]);

        $fechaIni = $this->parsearFecha($this->fecha_ini)->format('Y-m-d H:i:00');
        $fechaFin = $this->parsearFecha($this->fecha_fin)->format('Y-m-d H:i:00');

        DB::transaction(function () use ($fechaIni, $fechaFin) {
            $llamadoId = DB::table('nuevo_llamado')->insertGetId([
                'idtb_zona'       => $this->idtb_zona,
                'idtipo_llamado'  => $this->idtipo_llamado,
                'fecha_ini'       => $fechaIni,
                'fecha_fin'       => $fechaFin,
                'idtb_tipoestado' => $this->idtb_tipoestado,
                'url_form'        => $this->url_form,
                'descripcion'     => $this->descripcion,
                'publicado'       => false,
                'created_at'      => now(),
            ]);

           foreach ($this->detalles_agregados as $detalle) {
                if ($detalle['tipo'] === 'Espacio') {
                    DB::table('nuevo_espacios_por_llamado')->insert([
                        'llamado_id'                   => $llamadoId,
                        'instituto_id'                 => $detalle['instituto_id'],
                        'nuevo_rel_carrera_espacio_id' => $detalle['id_rel'],
                        'horario_espacio'              => $detalle['horario_espacio'],
                        'situacion_revista_id'         => $detalle['situacion_revista_id'],
                        'created_at'                   => now(),
                    ]);
                } else {
                    DB::table('nuevo_cargo_por_llamado')->insert([
                        'llamado_id'                 => $llamadoId,
                        'instituto_id'               => $detalle['instituto_id'],
                        'nuevo_rel_carrera_cargo_id' => $detalle['id_rel'],
                        'horario_cargo'              => $detalle['horario_espacio'],
                        'situacion_revista_id'       => $detalle['situacion_revista_id'],
                        'created_at'                 => now(),
                    ]);
                }
            }
        });
 
        session()->flash('success', 'Llamado guardado exitosamente. Podés publicarlo desde el historial.');
        $this->detalles_agregados = [];
        $this->reset([
            'idtb_zona', 'idtipo_llamado', 'fecha_ini', 'fecha_fin',
            'url_form', 'descripcion',
            'instituto_id', 'carrera_id',
            'nuevo_rel_carrera_espacio_id', 'nuevo_rel_carrera_cargo_id',
            'horario_espacio', 'situacion_revista_id',
            'editando_detalle_index',
        ]);
        $this->es_cargo             = false;
        $this->cargo_es_por_carrera = false;
        $this->idtb_tipoestado      = 8;
    }
 
    public function publicar($id)
    {
        DB::table('nuevo_llamado')->where('id', $id)->update(['publicado' => true]);
        return $this->redirect(route('admin.llamados.publico'), navigate: true);
    }
 
    public function eliminar($id)
    {
        DB::transaction(function () use ($id) {
            DB::table('nuevo_espacios_por_llamado')->where('llamado_id', $id)->delete();
            DB::table('nuevo_cargo_por_llamado')->where('llamado_id', $id)->delete();
            DB::table('nuevo_llamado')->where('id', $id)->delete();
        });
        session()->flash('success', 'Llamado eliminado correctamente.');
    }
 
    public function abrirEditar($id)
    {
        $llamado = DB::table('nuevo_llamado')->where('id', $id)->first();
 
        $this->editando        = $id;
        $this->idtb_zona       = $llamado->idtb_zona;
        $this->idtipo_llamado  = $llamado->idtipo_llamado;
        $this->idtb_tipoestado = $llamado->idtb_tipoestado;
        $this->fecha_ini       = \Carbon\Carbon::parse($llamado->fecha_ini)->format('Y-m-d\TH:i');
        $this->fecha_fin       = \Carbon\Carbon::parse($llamado->fecha_fin)->format('Y-m-d\TH:i');
        $this->url_form        = $llamado->url_form ?? '';
        $this->descripcion     = $llamado->descripcion ?? '';
     
        // Detectar modo por tipo de llamado
        $this->es_cargo             = ($llamado->idtipo_llamado == 1);
        $this->cargo_es_por_carrera = false;

        $this->detalles_agregados       = [];
        $this->editando_detalle_index   = null;
        $this->zona_detalle_id          = '';
        $this->instituto_id             = '';
        $this->carrera_id               = '';
        $this->nuevo_rel_carrera_espacio_id = '';
        $this->nuevo_rel_carrera_cargo_id   = '';
        $this->horario_espacio          = '';
        $this->situacion_revista_id     = '';
        $this->institutos = [];
        $this->carreras   = [];
        $this->espacios   = [];
        $this->cargos     = [];
 
        // Cargar espacios
        $espacios = DB::table('nuevo_espacios_por_llamado')
            ->join('nuevo_rel_carrera_espacio', 'nuevo_espacios_por_llamado.nuevo_rel_carrera_espacio_id', '=', 'nuevo_rel_carrera_espacio.id')
            ->join('tb_espacioscurriculares',   'nuevo_rel_carrera_espacio.espacio_id',  '=', 'tb_espacioscurriculares.idEspacioCurricular')
            ->join('tb_carreras',               'nuevo_rel_carrera_espacio.carrera_id',  '=', 'tb_carreras.id')
            ->leftJoin('tb_periodo_cursado',    'nuevo_rel_carrera_espacio.periodo_id',  '=', 'tb_periodo_cursado.idtb_periodo_cursado')
            ->leftJoin('tb_turnos',             'nuevo_rel_carrera_espacio.turno_id',    '=', 'tb_turnos.id')
            ->leftJoin('tb_perfil',             'nuevo_rel_carrera_espacio.perfil_id',   '=', 'tb_perfil.idtb_perfil')
            ->join('tb_instituto_superior', 'nuevo_espacios_por_llamado.instituto_id', '=', 'tb_instituto_superior.id')
            ->join('tb_situacion_revista',      'nuevo_espacios_por_llamado.situacion_revista_id', '=', 'tb_situacion_revista.idtb_situacion_revista')
            ->where('nuevo_espacios_por_llamado.llamado_id', $id)
            ->select(
                'nuevo_espacios_por_llamado.instituto_id',
                'tb_instituto_superior.nombre as instituto_nombre',
                'nuevo_rel_carrera_espacio.carrera_id',
                'tb_carreras.nombre as carrera_nombre',
                'tb_espacioscurriculares.nombre_espacio as nombre',
                'nuevo_rel_carrera_espacio.id as id_rel',
                'nuevo_rel_carrera_espacio.hora_catedra',
                'nuevo_rel_carrera_espacio.anio',
                'tb_periodo_cursado.nombre_periodo as periodo',
                'tb_turnos.nombre_turno as turno',
                'tb_perfil.nombre_perfil as perfil',
                'nuevo_espacios_por_llamado.horario_espacio',
                'nuevo_espacios_por_llamado.situacion_revista_id',
                'tb_situacion_revista.nombre_situacion_revista as situacion_revista'
            )->get();
            
 
        foreach ($espacios as $e) {
            $this->detalles_agregados[] = [
                'instituto_id'         => $e->instituto_id,
                'instituto_nombre'     => $e->instituto_nombre,
                'carrera_id'           => $e->carrera_id,
                'carrera_nombre'       => $e->carrera_nombre,
                'tipo'                 => 'Espacio',
                'id_rel'               => $e->id_rel,
                'nombre'               => $e->nombre,
                'hora_catedra'        => $e->hora_catedra,
                'anio'                 => $e->anio,
                'periodo'              => $e->periodo,
                'turno'                => $e->turno,
                'perfil'               => $e->perfil,
                'horario_espacio'      => $e->horario_espacio,
                'situacion_revista_id' => $e->situacion_revista_id,
                'situacion_revista'    => $e->situacion_revista,
            ];
        }
 
        // Cargar cargos — leftJoin en carreras (pueden ser por instituto sin carrera)
        $cargos = DB::table('nuevo_cargo_por_llamado')
            ->join('nuevo_rel_carrera_cargo',  'nuevo_cargo_por_llamado.nuevo_rel_carrera_cargo_id', '=', 'nuevo_rel_carrera_cargo.id')
            ->join('tb_cargos',                'nuevo_rel_carrera_cargo.cargo_id',   '=', 'tb_cargos.id')
            ->leftJoin('tb_carreras',          'nuevo_rel_carrera_cargo.carrera_id', '=', 'tb_carreras.id')
            ->leftJoin('tb_perfil',            'nuevo_rel_carrera_cargo.perfil_id',  '=', 'tb_perfil.idtb_perfil')
            ->join('tb_instituto_superior',    'nuevo_cargo_por_llamado.instituto_id', '=', 'tb_instituto_superior.id')
            ->join('tb_situacion_revista',     'nuevo_cargo_por_llamado.situacion_revista_id', '=', 'tb_situacion_revista.idtb_situacion_revista')
            ->where('nuevo_cargo_por_llamado.llamado_id', $id)
            ->select(
                'nuevo_cargo_por_llamado.instituto_id',
                'tb_instituto_superior.nombre as instituto_nombre',
                'nuevo_rel_carrera_cargo.carrera_id',
                'tb_carreras.nombre as carrera_nombre',
                'tb_cargos.nombre_cargo as nombre',
                'nuevo_rel_carrera_cargo.id as id_rel',
                'tb_cargos.hora_catedra',
                'tb_perfil.nombre_perfil as perfil',
                'nuevo_cargo_por_llamado.horario_cargo as horario_espacio',
                'nuevo_cargo_por_llamado.situacion_revista_id',
                'tb_situacion_revista.nombre_situacion_revista as situacion_revista'
            )->get();
         
 
        foreach ($cargos as $c) {
            $this->detalles_agregados[] = [
                'instituto_id'         => $c->instituto_id,
                'instituto_nombre'     => $c->instituto_nombre,
                'carrera_id'           => $c->carrera_id,
                'carrera_nombre'       => $c->carrera_nombre,
                'tipo'                 => 'Cargo',
                'id_rel'               => $c->id_rel,
                'nombre'               => $c->nombre,
                'hora_catedra'         => $c->hora_catedra,
                'anio'                 => null,
                'periodo'              => null,
                'turno'                => null,
                'perfil'               => $c->perfil,
                'horario_espacio'      => $c->horario_espacio,
                'situacion_revista_id' => $c->situacion_revista_id,
                'situacion_revista'    => $c->situacion_revista,
            ];
        }
 
        $this->institutos = DB::table('tb_instituto_superior')->where('zona_id', $this->idtb_zona)->orderBy('nombre')->get()->toArray();
        $this->modalAbierto = true;
    }
 
    public function guardarEdicion()
    {
        $this->validate([
            'idtb_zona'          => 'required',
            'idtipo_llamado'     => 'required',
            'fecha_ini'          => 'required',
            'fecha_fin'          => 'required',
            'detalles_agregados' => 'required|array|min:1',
        ]);
 
        DB::transaction(function () {
            DB::table('nuevo_llamado')->where('id', $this->editando)->update([
                'idtb_zona'       => $this->idtb_zona,
                'idtipo_llamado'  => $this->idtipo_llamado,
                'fecha_ini'       => $this->parsearFecha($this->fecha_ini)->format('Y-m-d H:i:00'),
                'fecha_fin'       => $this->parsearFecha($this->fecha_fin)->format('Y-m-d H:i:00'),
                'idtb_tipoestado' => $this->idtb_tipoestado,
                'url_form'        => $this->url_form,
                'descripcion'     => $this->descripcion,
                'id_usuario_editar' => auth()->id(),
                'updated_at'      => now(),
            ]);
 
            DB::table('nuevo_espacios_por_llamado')->where('llamado_id', $this->editando)->delete();
            DB::table('nuevo_cargo_por_llamado')->where('llamado_id', $this->editando)->delete();
 
            foreach ($this->detalles_agregados as $detalle) {
                if ($detalle['tipo'] === 'Espacio') {
                    DB::table('nuevo_espacios_por_llamado')->insert([
                        'llamado_id'                   => $this->editando,
                        'instituto_id'                 => $detalle['instituto_id'],
                        'nuevo_rel_carrera_espacio_id' => $detalle['id_rel'],
                        'horario_espacio'              => $detalle['horario_espacio'],
                        'situacion_revista_id'         => $detalle['situacion_revista_id'],
                        'created_at'                   => now(),
                    ]);
                } else {
                    DB::table('nuevo_cargo_por_llamado')->insert([
                        'llamado_id'                 => $this->editando,
                        'instituto_id'               => $detalle['instituto_id'],
                        'nuevo_rel_carrera_cargo_id' => $detalle['id_rel'],
                        'horario_cargo'              => $detalle['horario_espacio'],
                        'situacion_revista_id'       => $detalle['situacion_revista_id'],
                        'created_at'                 => now(),
                    ]);
                }
            }
        });
 
        $this->cerrarModal();
        session()->flash('success', 'Llamado actualizado correctamente.');
    }
 
    public function cerrarModal()
    {
        $this->modalAbierto           = false;
        $this->editando               = null;
        $this->editando_detalle_index = null;
        $this->idtb_tipoestado        = 8;
        $this->detalles_agregados     = [];
        $this->zona_detalle_id        = '';
        $this->institutos             = [];
        $this->instituto_id           = '';
        $this->carreras               = [];
        $this->carrera_id             = '';
        $this->espacios               = [];
        $this->cargos                 = [];
        $this->es_cargo               = false;
        $this->cargo_es_por_carrera   = false;
        $this->nuevo_rel_carrera_espacio_id = '';
        $this->nuevo_rel_carrera_cargo_id   = '';
        $this->horario_espacio        = '';
        $this->situacion_revista_id   = '';
        $this->reset(['idtb_zona', 'idtipo_llamado', 'fecha_ini', 'fecha_fin', 'url_form', 'descripcion']);
    }
 
    #[Computed(cache: false)]
    public function llamados()
    {
        $ahora          = \Carbon\Carbon::now($this->tz());
        $ESTADO_CERRADO = 9;
 
        $rows = DB::table('nuevo_llamado')
            ->leftJoin('tb_zona',       'nuevo_llamado.idtb_zona',       '=', 'tb_zona.id')
            ->leftJoin('tipo_llamado',  'nuevo_llamado.idtipo_llamado',  '=', 'tipo_llamado.id')
            ->leftJoin('tb_tipoestado', 'nuevo_llamado.idtb_tipoestado', '=', 'tb_tipoestado.idtb_tipoestado')
            ->select(
                'nuevo_llamado.*',
                'tb_zona.nombre_zona',
                'tipo_llamado.nombre as tipo_nombre',
                'tb_tipoestado.nombre_tipoestado as estado_nombre'
            )
            ->orderBy('nuevo_llamado.id', 'desc')
            ->paginate(10);
 
        if ($rows->count() === 0) return $rows;
        $ids = $rows->getCollection()->pluck('id')->toArray();
 
        $espaciosPorLlamado = DB::table('nuevo_espacios_por_llamado')
            ->join('nuevo_rel_carrera_espacio',  'nuevo_espacios_por_llamado.nuevo_rel_carrera_espacio_id', '=', 'nuevo_rel_carrera_espacio.id')
            ->join('tb_espacioscurriculares',    'nuevo_rel_carrera_espacio.espacio_id',  '=', 'tb_espacioscurriculares.idEspacioCurricular')
            ->join('tb_carreras',                'nuevo_rel_carrera_espacio.carrera_id',  '=', 'tb_carreras.id')
            ->join('tb_periodo_cursado',         'nuevo_rel_carrera_espacio.periodo_id',  '=', 'tb_periodo_cursado.idtb_periodo_cursado')
            ->join('tb_turnos',                  'nuevo_rel_carrera_espacio.turno_id',    '=', 'tb_turnos.id')
            ->join('tb_perfil',                  'nuevo_rel_carrera_espacio.perfil_id',   '=', 'tb_perfil.idtb_perfil')
            ->join('tb_situacion_revista',       'nuevo_espacios_por_llamado.situacion_revista_id', '=', 'tb_situacion_revista.idtb_situacion_revista')
            ->whereIn('nuevo_espacios_por_llamado.llamado_id', $ids)
            ->select(
                'nuevo_espacios_por_llamado.llamado_id',
                'tb_espacioscurriculares.nombre_espacio as detalle',
                'tb_carreras.nombre as carrera',
                'nuevo_rel_carrera_espacio.hora_catedra',
                'nuevo_rel_carrera_espacio.anio',
                'tb_periodo_cursado.nombre_periodo as periodo',
                'tb_turnos.nombre_turno as turno',
                'tb_perfil.nombre_perfil as perfil',
                'nuevo_espacios_por_llamado.horario_espacio',
                'tb_situacion_revista.nombre_situacion_revista as situacion_revista',
                DB::raw("'Espacio' as tipo")
            )
            ->get()->groupBy('llamado_id');
 
        // Cargos: leftJoin en carreras (pueden ser por instituto sin carrera)
        // Sin joins a periodo/turno porque los cargos no tienen esos datos
        $cargosPorLlamado = DB::table('nuevo_cargo_por_llamado')
            ->join('nuevo_rel_carrera_cargo',  'nuevo_cargo_por_llamado.nuevo_rel_carrera_cargo_id', '=', 'nuevo_rel_carrera_cargo.id')
            ->join('tb_cargos',                'nuevo_rel_carrera_cargo.cargo_id',   '=', 'tb_cargos.id')
            ->leftJoin('tb_carreras',          'nuevo_rel_carrera_cargo.carrera_id', '=', 'tb_carreras.id')
            ->leftJoin('tb_perfil',            'nuevo_rel_carrera_cargo.perfil_id',  '=', 'tb_perfil.idtb_perfil')
            ->join('tb_situacion_revista',     'nuevo_cargo_por_llamado.situacion_revista_id', '=', 'tb_situacion_revista.idtb_situacion_revista')
            ->whereIn('nuevo_cargo_por_llamado.llamado_id', $ids)
            ->select(
                'nuevo_cargo_por_llamado.llamado_id',
                'tb_cargos.nombre_cargo as detalle',
                'tb_carreras.nombre as carrera',
                'tb_cargos.hora_catedra',
                DB::raw('NULL as anio'),
                DB::raw('NULL as periodo'),
                DB::raw('NULL as turno'),
                'tb_perfil.nombre_perfil as perfil',
                'nuevo_cargo_por_llamado.horario_cargo as horario_espacio',
                'tb_situacion_revista.nombre_situacion_revista as situacion_revista',
                DB::raw("'Cargo' as tipo")
            )
            ->get()->groupBy('llamado_id');
 
        $institutosPorLlamado = DB::table('nuevo_espacios_por_llamado')
            ->join('tb_instituto_superior', 'nuevo_espacios_por_llamado.instituto_id', '=', 'tb_instituto_superior.id')
            ->whereIn('nuevo_espacios_por_llamado.llamado_id', $ids)
            ->select('nuevo_espacios_por_llamado.llamado_id', 'tb_instituto_superior.nombre')
            ->union(
                DB::table('nuevo_cargo_por_llamado')
                    ->join('tb_instituto_superior', 'nuevo_cargo_por_llamado.instituto_id', '=', 'tb_instituto_superior.id')
                    ->whereIn('nuevo_cargo_por_llamado.llamado_id', $ids)
                    ->select('nuevo_cargo_por_llamado.llamado_id', 'tb_instituto_superior.nombre')
            )
            ->get()->groupBy('llamado_id');
 
        foreach ($rows as $item) {
            if (\Carbon\Carbon::parse($item->fecha_fin, $this->tz())->lte($ahora) && $item->idtb_tipoestado != $ESTADO_CERRADO) {
                $item->idtb_tipoestado = $ESTADO_CERRADO;
                $item->estado_nombre   = 'Cerrado';
            }
 
            $espacios = $espaciosPorLlamado->get($item->id, collect());
            $cargos   = $cargosPorLlamado->get($item->id, collect());
            $todos    = $espacios->merge($cargos);
            $item->nombres_institutos = $institutosPorLlamado->get($item->id, collect())
                            ->pluck('nombre')->unique()->values()->toArray();
            $item->detalles = $todos->values()->toArray();
            $item->nombres_carreras = $todos->pluck('carrera')->filter()->unique()->values()->toArray();
        }
 
        return $rows;
    }
}; 
?>

<div class="p-6 bg-white rounded-xl shadow-lg border border-gray-100 max-w-6xl mx-auto my-8">
    <div class="hidden">
         @livewire('pages.admin.lom.gestionar-inscriptos')
    </div>
   
    {{-- HEADER --}}
    <div class="flex justify-between items-center mb-6 border-b pb-4">
        <h1 class="text-3xl font-bold text-gray-800">Crear Nuevo Llamado</h1>
        <div class="flex items-center space-x-2">
            <span class="text-sm font-medium text-gray-500 uppercase">Estado actual:</span>
            @if($idtb_tipoestado == 8)
                <span class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs font-bold uppercase border border-green-200">Abierto</span>
            @else
                <span class="bg-red-100 text-red-700 px-3 py-1 rounded-full text-xs font-bold uppercase border border-red-200">Cerrado</span>
            @endif
        </div>
    </div>

    {{-- FLASH --}}
    @if (session()->has('success'))
        <div class="mb-6 p-4 bg-green-50 border-l-4 border-green-500 text-green-700 shadow-sm rounded-r-lg">
            <div class="flex items-center">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                <span class="font-bold">{{ session('success') }}</span>
            </div>
        </div>
    @endif

    @if (session()->has('error'))
        <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-500 text-red-700 shadow-sm rounded-r-lg">
            <div class="flex items-center">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                <span class="font-bold">{{ session('error') }}</span>
            </div>
        </div>
    @endif

    <form wire:submit.prevent="guardar" class="space-y-8">

        {{-- SECCIÓN CABECERA --}}
        <div class="bg-gray-50 p-6 rounded-xl border border-gray-200 shadow-inner">
            <h2 class="text-xl font-semibold text-indigo-700 mb-6 flex items-center">
                <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                Datos del Llamado
            </h2>

            <div class="grid grid-cols-1 md:flex ml-4 lg:grid-cols-3 gap-6">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Tipo de Llamado</label>
                    <select wire:model.live="idtipo_llamado" class="w-auto min-w-22.5 border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        <option value="">Seleccione tipo...</option>
                        @foreach($tipos_llamado as $t)
                            <option value="{{ $t->id }}">{{ $t->nombre }}</option>
                        @endforeach
                    </select>
                    @error('idtipo_llamado') <span class="text-red-500 text-xs italic">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Estado</label>
                    <div class="w-auto min-w-22.5 border border-gray-300 rounded-lg shadow-sm bg-gray-100 text-gray-600 text-sm px-3 py-2">
                        @if($idtb_tipoestado == 8)
                            <span class="text-green-700 font-bold">● Abierto</span>
                        @else
                            <span class="text-red-700 font-bold">● Cerrado</span>
                        @endif
                    </div>
                    <input type="hidden" wire:model="idtb_tipoestado">
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Fecha Inicio</label>
                    <input type="datetime-local" wire:model="fecha_ini"
                           class="w-auto min-w-22.5 border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    @error('fecha_ini') <span class="text-red-500 text-xs italic">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Fecha Fin</label>
                    <input type="datetime-local" wire:model="fecha_fin"
                           x-on:change="$wire.actualizarEstadoPorFecha($event.target.value)"
                           class="w-auto min-w-22.5 border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    @error('fecha_fin') <span class="text-red-500 text-xs italic">{{ $message }}</span> @enderror
                </div>

                <div class="lg:col-span-2">
                    <label class="block text-sm font-bold text-gray-700 mb-1">Link de Inscripción</label>
                    <input type="url" wire:model.blur="url_form" placeholder="https://forms.gle/..."
                           class="w-auto min-w-22.5 border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                </div>
            </div>
        </div>

        {{-- SECCIÓN UBICACIÓN Y DETALLE --}}
        <div class="bg-blue-50 p-6 rounded-xl border border-blue-200 shadow-inner">
            <h2 class="text-xl font-semibold text-blue-800 mb-6 flex items-center">
                <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                Ubicación y Detalle
            </h2>

            <div class="grid grid-cols-1 md:flex ml-4 lg:grid-cols-3 gap-6 mb-6">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Zona</label>
                    <select wire:model.live="idtb_zona" class="w-auto min-w-22.5 border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                        <option value="">Seleccione zona...</option>
                        @foreach($zonas as $z)
                            <option value="{{ $z->id }}">{{ $z->nombre_zona }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Instituto</label>
                    <select wire:model.live="instituto_id" class="w-auto min-w-22.5 border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm {{ empty($institutos) ? 'bg-gray-100' : '' }}">
                        <option value="">Seleccione instituto...</option>
                        @foreach($institutos as $i)
                            <option value="{{ $i->id }}">{{ $i->nombre }}</option>
                        @endforeach
                    </select>
                </div>

                @if(!$es_cargo || $cargo_es_por_carrera)
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">
                        Carrera
                        @if($cargo_es_por_carrera)
                            <span class="text-purple-600 text-xs font-black ml-1">(requerida — Bedel)</span>
                        @endif
                    </label>
                    <select wire:model.live="carrera_id" class="w-auto min-w-22.5 border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm {{ empty($carreras) ? 'bg-gray-100' : '' }}">
                        <option value="">Seleccione carrera...</option>
                        @foreach($carreras as $c)
                            <option value="{{ $c->id }}">{{ $c->nombre }}</option>
                        @endforeach
                    </select>
                    @error('carrera_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                @endif
            </div>

            

                <div class="mb-4">
                   @if($idtipo_llamado == 2)

                        <label class="block text-sm font-bold text-gray-700 mb-1">
                            Unidad Curricular
                        </label>

                        <select wire:model.live="nuevo_rel_carrera_espacio_id"
                            class="w-auto min-w-22.5 border-gray-300 rounded-lg shadow-sm
                                focus:border-blue-500 focus:ring-blue-500 text-sm">

                            <option value="">Seleccione unidad curricular...</option>

                            @foreach($espacios as $e)
                                <option value="{{ $e->id }}">
                                    {{ $e->nombre_espacio }}
                                </option>
                            @endforeach
                        </select>

                    @elseif($idtipo_llamado == 1)

                        <label class="block text-sm font-bold text-gray-700 mb-1">
                            Cargo
                        </label>

                        <select wire:model.live="nuevo_rel_carrera_cargo_id"
                            class="w-auto min-w-22.5 border-gray-300 rounded-lg shadow-sm
                                focus:border-blue-500 focus:ring-blue-500 text-sm">

                            <option value="">Seleccione cargo...</option>

                            @foreach($cargos as $ca)
                                <option value="{{ $ca->id }}">
                                    {{ $ca->nombre_cargo }}
                                </option>
                            @endforeach
                        </select>

                    @else

                        <div class="text-sm text-gray-500 italic">
                            Seleccione primero el tipo de llamado.
                        </div>

                    @endif
                </div>

                <div class="grid grid-cols-1 md:flex gap-1 ml-4 pt-4 border-t border-gray-100">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">Situación de Revista</label>
                        <select wire:model="situacion_revista_id"
                                class="w-auto min-w-22.5 border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                            <option value="">Seleccione...</option>
                            @foreach($situaciones_revista as $s)
                                <option value="{{ $s->idtb_situacion_revista }}">{{ $s->nombre_situacion_revista }}</option>
                            @endforeach
                        </select>
                        @error('situacion_revista_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">Horario</label>
                        <input type="text" wire:model="horario_espacio"
                               placeholder="Ej: Lunes y Miércoles 8-10hs"
                               class="w-auto min-w-22.5 border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                        @error('horario_espacio') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="flex justify-end mt-4">
                    <button type="button" wire:click="agregarDetalle"
                            class="bg-blue-800 hover:bg-blue-900 text-white font-bold py-2 px-6 rounded-lg shadow-md transition flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"></path></svg>
                        Añadir
                    </button>
                </div>
            </div>
     

        {{-- TABLA DE DETALLES AGREGADOS --}}
        @if(!empty($detalles_agregados))
            <div class="mt-8 border-t pt-6">
                <h3 class="text-sm font-black text-gray-500 uppercase mb-4 tracking-widest flex items-center">
                    <span class="mr-2">Detalles a publicar {{ $editando ? "(Llamado #$editando)" : "(Nuevo)" }}</span>
                    <span class="bg-blue-100 text-blue-800 px-2 py-0.5 rounded text-xs">{{ count($detalles_agregados) }}</span>
                </h3>
                <div class="overflow-hidden rounded-xl border border-gray-200 shadow-lg">
                    <table class="min-w-auto divide-y divide-gray-200">
                        <thead class="bg-slate-800 text-white table-fixed">
                            <tr class="border-b hover:bg-slate-50 hover:shadow-sm transition-all">
                                <th class="px-4 py-3 text-left text-xs font-bold uppercase">Carrera</th>
                                <th class="px-4 py-3 text-left text-xs font-bold uppercase">Tipo / Nombre</th>
                                <th class="px-4 py-3 text-left text-xs font-bold uppercase">Hs / Año</th>
                                <th class="px-4 py-3 text-left text-xs font-bold uppercase">Turno / Período</th>
                                <th class="px-4 py-3 text-left text-xs font-bold uppercase">Perfil</th>
                                <th class="px-4 py-3 text-left text-xs font-bold uppercase">Sit. Revista</th>
                                <th class="px-4 py-3 text-left text-xs font-bold uppercase">Horario</th>
                                <th class="px-4 py-3 text-center text-xs font-bold uppercase">Acc.</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($detalles_agregados as $index => $det)
                                <tr class="hover:bg-yellow-50 transition">
                                    <td class="px-4 py-3 text-xs text-gray-700 font-medium">{{ $det['carrera_nombre'] }}</td>
                                    <td class="px-4 py-3 text-xs">
                                        <span class="px-2 py-1 rounded text-xs font-bold {{ $det['tipo'] == 'Cargo' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800' }}">
                                            {{ $det['tipo'] }}
                                        </span>
                                       
                                    </td>
                                    <td class="px-4 py-3 text-xs text-gray-700">

                                        @if($det['tipo'] == 'Espacio')
                                            <div>Año: {{ $det['anio'] ?? '-' }}</div>
                                        @endif

                                    </td>
                                   <td class="px-4 py-3 text-xs text-gray-700">

                                        <div>{{ $det['turno'] }}</div>

                                        @if($det['tipo'] == 'Espacio')
                                            <div>{{ $det['periodo'] ?? '' }}</div>
                                        @endif

                                    </td>
                                    <td class="px-4 py-3 text-xs text-gray-700">{{ $det['perfil'] }}</td>
                                    <td class="px-4 py-3 text-xs text-gray-700">{{ $det['situacion_revista'] }}</td>
                                    <td class="px-4 py-3 text-xs text-gray-500 italic">{{ $det['horario_espacio'] }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <button type="button" wire:click="quitarDetalle({{ $index }})"
                                                class="text-red-600 hover:text-red-900 font-bold p-1 rounded hover:bg-red-50 transition">
                                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- BOTÓN GUARDAR --}}
        <div class="flex justify-end pt-8 border-t border-gray-100">
            <button type="submit"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white font-black py-4 px-12 rounded-xl shadow-xl hover:shadow-2xl transform hover:-translate-y-1 transition duration-200 flex items-center text-lg">
                <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                Publicar Llamado Ahora
            </button>
        </div>
    </form>

    {{-- HISTORIAL DE LLAMADOS --}}
    <div wire:poll.30s="cerrarVencidos" class="mt-20">
        <h2 class="text-3xl font-black text-gray-800 mb-8 flex items-center pb-2 border-b-4 border-indigo-500 w-fit">
            <svg class="w-8 h-8 mr-3 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
            Historial de Llamados
        </h2>

        <div class="overflow-x-auto rounded-2xl border border-gray-300 shadow-2xl">

           <table class="min-w-full border border-gray-300 table-fixed text-center">
                <thead class="bg-gray-900 text-white">
                    <tr class="border-b">
                          <th class="w-[6%] px-2 py-4 text-center whitespace-nowrap text-xs font-black uppercase border-r border-gray-700">ID / Zona</th>
                    <th class="w-[12%] px-2 py-4 text-center whitespace-nowrap text-xs font-black uppercase border-r border-gray-700">Instituto</th>
                    <th class="w-[12%] px-2 py-4 text-center whitespace-nowrap text-xs font-black uppercase border-r border-gray-700">Carreras</th>
                    <th class="w-[30%] px-2 py-4 text-center whitespace-nowrap text-xs font-black uppercase border-r border-gray-700">Espacios / Cargos</th>
                    <th class="w-[30%] px-2 py-4 text-center whitespace-nowrap text-xs font-black uppercase border-r border-gray-700">Perfil</th>
                    <th class="w-[10%] px-2 py-4 text-center whitespace-nowrap text-xs font-black uppercase border-r border-gray-700">Inscripción</th>           
                    <th class="w-[10%] px-2 py-4 text-center whitespace-nowrap text-xs font-black uppercase border-r border-gray-700">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 align-top text-center">

                    @forelse($this->llamados as $item)
                    <tr wire:key="llamado-{{ $item->id }}" class="border-b hover:bg-slate-50 hover:shadow-sm transition-all">
                        <td class="px-1 py-4 align-top text-center overflow-hidden">
                            <div class="text-[10px] font-black text-indigo-600 mb-1">#{{ $item->id }}</div>
                            <div class="inline-block bg-indigo-100 text-indigo-800 text-[10px] font-black px-1 rounded uppercase tracking-tighter mb-1">
                                {{ $item->nombre_zona }}
                            </div>
                               <div class="mt-3 mb-2 text-center">
                                    @if($item->idtb_tipoestado == 8)
                                        <div class="flex flex-col items-center justify-center">
                                            <span class="bg-green-200 text-green-700 px-2 py-1 rounded-full text-[10px] font-black uppercase mb-1 border border-green-200 shadow-sm animate-pulse">Abierto</span>
                                            <a href="{{ $item->url_form }}" target="_blank" 
                                               class="inline-flex items-center bg-blue-600 hover:bg-blue-700 text-white font-black px-2 py-2 rounded-lg text-[10px] uppercase transition-all shadow-md hover:shadow-lg transform hover:-translate-y-0.5">
                                                Postularme
                                            </a>
                                        </div>
                                    @else
                                        <span class="bg-red-200 text-red-600 px-2 py-1 rounded-full text-[10px] font-black uppercase border border-red-100">Cerrado</span>
                                    @endif
                                </div>  
                            <div class="text-xs font-bold text-gray-500">{{ $item->descripcion }}</div>
                        </td>

                        <td class="px-2 py-4 align-top border-r border-gray-100">
                            @foreach($item->nombres_institutos as $instituto)
                                <div class="text-sm font-bold text-gray-800 py-0.5">{{ $instituto }}</div>
                            @endforeach
                        </td>

                        <td class="px-2 py-4 align-top border-r border-gray-100">
                            @if(empty($item->nombres_carreras) && $item->idtipo_llamado == 1)
                                <div class="text-xs text-gray-400 italic">Por instituto</div>
                            @else
                                @foreach($item->nombres_carreras as $carrera)
                                    <div class="text-sm font-bold text-gray-800 py-0.5">{{ $carrera }}</div>
                                @endforeach
                            @endif
                        </td>

                        <td class="px-2 py-2 align-top border-r border-gray-100">
                            <div class="flex flex-col">
                                @foreach($item->detalles as $det)
                                    <div class="min-h-[120px] border-b border-gray-100 pb-2 mb-2">
                                        
                                        <div class="text-sm font-black font-bold text-slate-700">
                                            {{ $det->detalle }}
                                        </div>
                                        @if(!empty($det->hora_catedra))
                                            <div class="break-words whitespace-normal text-xs text-gray-500">
                                                <span class="font-bold">Hs. Cátedra:</span> {{ $det->hora_catedra }}
                                            </div>
                                        @endif
                                        <div class="text-xs text-gray-500 space-y-1">
                                            <div>
                                                <span class="font-bold">Sit Revista:</span>
                                                {{ $det->situacion_revista }}
                                            </div>
                                            @if($det->tipo === 'Espacio')
                                                @if($det->periodo)
                                                <div>
                                                    <span class="font-bold">Período:</span> {{ $det->periodo }}
                                                </div>
                                                @endif
                                                @if($det->anio)
                                                <div>
                                                    <span class="font-bold">Curso:</span> {{ $det->anio }}° Año
                                                    @if($det->turno)
                                                        <span class="font-bold ml-1">Turno:</span> {{ $det->turno }}
                                                    @endif
                                                </div>
                                                @endif
                                            @endif
                                            @if($det->horario_espacio)
                                            <div class="break-words whitespace-normal">
                                                <span class="font-bold">Horario:</span> {{ $det->horario_espacio }}
                                            </div>
                                            @endif
                                            
                                        </div>

                                    </div>
                                @endforeach
                            </div>
                        </td>

                        <td class="px-2 py-2 align-top border-r border-gray-100">
                            <div class="flex flex-col">
                                @foreach($item->detalles as $det)

                                    <div class="min-h-[120px] border-b border-gray-100 pb-2 mb-2">
                                        <div class="text-xs text-gray-700 break-words whitespace-normal leading-relaxed">
                                            {{ $det->perfil }}
                                        </div>
                                    </div>

                                @endforeach
                            </div>
                        </td>
                        <td class="px-2 py-4 align-top">
                                <div class="space-y-3 text-center">
                                    <div class="bg-gray-50 px-1 py-2 rounded border border-gray-100">
                                        <div class="text-[9px] font-black text-gray-400 uppercase mb-0.5 tracking-tighter">
                                            Inicia
                                        </div>
                                        <div class="text-[11px] font-black text-gray-700">
                                            {{ \Carbon\Carbon::parse($item->fecha_ini)->format('d/m/y H:i') }}
                                        </div>
                                    </div>
                                    <div class="bg-gray-50 px-1 py-2 rounded border border-red-100">
                                        <div class="text-[9px] font-black text-red-400 uppercase mb-0.5 tracking-tighter">
                                            Finaliza
                                        </div>

                                        <div class="text-[11px] font-black text-red-700">
                                            {{ \Carbon\Carbon::parse($item->fecha_fin)->format('d/m/y H:i') }}
                                        </div>
                                    </div>
                                </div>                   
                            </td>

                            {{-- ACCIONES --}}
                            <td class="px-2 py-4 align-top text-center overflow-hidden break-word whitespace-normal">
                                <div class="flex flex-col items-center space-y-1.5">
                                    @if(!$item->publicado)
                                        <button wire:click="publicar({{ $item->id }})"
                                                class="w-auto min-w-22.5 px-3 bg-yellow-500 hover:bg-yellow-600 text-white font-black py-2 rounded text-sm uppercase transition-all shadow-sm">
                                            Publicar
                                        </button>
                                    @else
                                        <div class="w-auto min-w-22.5 px-3 bg-green-50 text-green-700 text-sm font-black py-1.5 rounded border border-green-100 uppercase italic text-center">
                                            Publicado
                                        </div>
                                    @endif
                                    <button wire:click="abrirEditar({{ $item->id }})"
                                            class="w-auto min-w-22.5 px-3 bg-slate-800 hover:bg-slate-900 text-white font-black py-2 rounded text-sm uppercase transition-all shadow-sm">
                                        Editar
                                    </button>
                                    <button
                                        wire:click="$dispatchTo('pages.admin.lom.gestionar-inscriptos', 'abrirPanel', { id: {{ $item->id }} })"
                                        class="w-auto min-w-22.5 px-3 bg-teal-600 hover:bg-teal-700 text-white
                                            font-black py-2 rounded text-sm uppercase transition-all shadow-sm
                                            flex items-center justify-center gap-1">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857
                                                    M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002
                                                    5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        </svg>
                                        Inscriptos
                                    </button>
                                                                                            <button onclick="confirm('¿Está seguro de eliminar este llamado?') || event.stopImmediatePropagation()"
                                            wire:click="eliminar({{ $item->id }})"
                                            class="w-auto min-w-22.5 px-3 bg-white hover:bg-red-50 text-red-600 font-black py-2 rounded text-[10px] uppercase transition-all border border-red-100">
                                        Eliminar
                                    </button>
                                </div>
                            </td>

                        </tr>

                    @empty
                        <tr class="border-b hover:bg-slate-50 transition-all">
                            <td colspan="6" class="px-4 py-16 text-center bg-gray-50">
                                <div class="flex flex-col items-center">
                                    <svg class="w-12 h-12 text-gray-200 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                    </svg>
                                    <span class="text-gray-400 font-black uppercase tracking-widest text-sm">No se han encontrado registros de llamados</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse

                </tbody>
            </table>

            <div class="mt-6">
                {{ $this->llamados->links() }}
            </div>

        </div>
    </div>

    {{-- MODAL EDICIÓN --}}
    @if($modalAbierto)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl mx-4 p-6 max-h-[90vh] flex flex-col">
                <div class="flex justify-between items-center mb-4 border-b pb-3 shrink-0">
                    <div class="flex items-center">
                        <span class="bg-indigo-600 text-white px-3 py-1 rounded-lg text-sm font-black mr-3 shadow-md">LLAMADO #{{ $editando }}</span>
                        <h2 class="text-xl font-black text-gray-800 uppercase tracking-tight">Gestión de Edición</h2>
                    </div>
                    <button wire:click="cerrarModal" class="text-gray-400 hover:text-gray-600 text-2xl font-bold leading-none transition-colors hover:bg-gray-100 rounded-full p-1">✕</button>
                </div>

                <div class="overflow-y-auto flex-1 pr-2 space-y-6 custom-scrollbar min-h-0">
                    <div class="bg-gray-50 p-4 rounded-xl border border-gray-200 shadow-sm">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-700 mb-1 uppercase tracking-wider">Zona</label>
                                <select wire:model.live="idtb_zona" class="w-auto min-w-22.5 border-gray-300 rounded-lg text-sm shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                    <option value="">Seleccione...</option>
                                    @foreach($zonas as $z)
                                        <option value="{{ $z->id }}">{{ $z->nombre_zona }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 mb-1 uppercase tracking-wider">Tipo de Llamado</label>
                                <select wire:model="idtipo_llamado" class="w-auto min-w-22.5 border-gray-300 rounded-lg text-sm shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                    <option value="">Seleccione...</option>
                                    @foreach($tipos_llamado as $t)
                                        <option value="{{ $t->id }}">{{ $t->nombre }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 mb-1 uppercase tracking-wider">Estado</label>
                                <div class="w-auto min-w-22.5 border border-gray-300 rounded-lg bg-gray-100 text-sm px-3 py-2 flex items-center">
                                    @if($idtb_tipoestado == 8)
                                        <span class="flex items-center text-green-700 font-black">
                                            <span class="w-2 h-2 bg-green-500 rounded-full mr-2 animate-pulse"></span>Abierto
                                        </span>
                                    @else
                                        <span class="flex items-center text-red-700 font-black">
                                            <span class="w-2 h-2 bg-red-500 rounded-full mr-2"></span>Cerrado
                                        </span>
                                    @endif
                                </div>
                                <input type="hidden" wire:model="idtb_tipoestado">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 mb-1 uppercase tracking-wider">Fecha Inicio</label>
                                <input type="datetime-local" wire:model="fecha_ini"
                                       class="w-auto min-w-22.5 border-gray-300 rounded-lg text-sm shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                @error('fecha_ini') <span class="text-red-500 text-[10px] font-bold">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 mb-1 uppercase tracking-wider">Fecha Fin</label>
                                <input type="datetime-local" wire:model="fecha_fin"
                                       x-on:change="$wire.actualizarEstadoPorFecha($event.target.value)"
                                       class="w-auto min-w-22.5 border-gray-300 rounded-lg text-sm shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                @error('fecha_fin') <span class="text-red-500 text-[10px] font-bold">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 mb-1 uppercase tracking-wider">URL Formulario</label>
                                <input type="url" wire:model.blur="url_form" placeholder="https://..."
                                       class="w-auto min-w-22.5 border-gray-300 rounded-lg text-sm shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            <div class="md:col-span-3">
                                <label class="block text-xs font-bold text-gray-700 mb-1 uppercase tracking-wider">Descripción</label>
                                <textarea wire:model.blur="descripcion" rows="2"
                                          class="w-auto min-w-22.5 border-gray-300 rounded-lg text-sm shadow-sm focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                            </div>
                        </div>
                    </div>

                    {{-- AGREGAR DETALLE (MODO COMPACTO) --}}
                    <div class="bg-blue-50 p-4 rounded-xl border border-blue-200">
                        <h3 class="text-xs font-black text-blue-800 mb-3 flex items-center uppercase tracking-widest">
                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            Agregar Carrera / Espacio / Cargo
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-3">
                            <div>
                                <select wire:model.live="zona_detalle_id" class="w-full border-gray-300 rounded-lg shadow-sm text-[11px] font-bold">
                                    <option value="">ZONA...</option>
                                    @foreach($zonas as $z)
                                        <option value="{{ $z->id }}">{{ $z->nombre_zona }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <select wire:model.live="instituto_id" class="w-full border-gray-300 rounded-lg shadow-sm text-[11px] font-bold">
                                    <option value="">INSTITUTO...</option>
                                    @foreach($institutos as $i)
                                        <option value="{{ $i->id }}">{{ $i->nombre }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @if(!$es_cargo || $cargo_es_por_carrera)
                            <div>
                                <select wire:model.live="carrera_id" class="w-full border-gray-300 rounded-lg shadow-sm text-[11px] font-bold">
                                    <option value="">CARRERA{{ $cargo_es_por_carrera ? ' (Bedel)' : '' }}...</option>
                                    @foreach($carreras as $c)
                                        <option value="{{ $c->id }}">{{ $c->nombre }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @endif
                        </div>
                        <div class="bg-white p-3 rounded-lg border border-blue-100 shadow-sm">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div>
                                @if($idtipo_llamado == 2)

                                        <label class="block text-sm font-bold text-gray-700 mb-1">
                                            Unidad Curricular
                                        </label>

                                        <select wire:model.live="nuevo_rel_carrera_espacio_id"
                                            class="w-auto min-w-22.5 border-gray-300 rounded-lg shadow-sm
                                                focus:border-blue-500 focus:ring-blue-500 text-sm">

                                            <option value="">Seleccione unidad curricular...</option>

                                            @foreach($espacios as $e)
                                                <option value="{{ $e->id }}">
                                                    {{ $e->nombre_espacio }}
                                                </option>
                                            @endforeach
                                        </select>

                                    @elseif($idtipo_llamado == 1)

                                        <label class="block text-sm font-bold text-gray-700 mb-1">
                                            Cargo
                                        </label>

                                        <select wire:model.live="nuevo_rel_carrera_cargo_id"
                                            class="w-auto min-w-22.5 border-gray-300 rounded-lg shadow-sm
                                                focus:border-blue-500 focus:ring-blue-500 text-sm">

                                            <option value="">Seleccione cargo...</option>

                                            @foreach($cargos as $ca)
                                                <option value="{{ $ca->id }}">
                                                    {{ $ca->nombre_cargo }}
                                                </option>
                                            @endforeach
                                        </select>

                                    @else

                                        <div class="text-sm text-gray-500 italic">
                                            Seleccione primero el tipo de llamado.
                                        </div>

                                    @endif
                                </div>
                                <div>
                                    <select wire:model="situacion_revista_id" class="w-auto min-w-22.5 border-gray-300 rounded-lg shadow-sm text-[11px]">
                                        <option value="">SIT. REVISTA...</option>
                                        @foreach($situaciones_revista as $s)
                                            <option value="{{ $s->idtb_situacion_revista }}">{{ $s->nombre_situacion_revista }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="md:col-span-2">
                                    <div class="flex gap-2">
                                        <input type="text" wire:model="horario_espacio" placeholder="Horario (Ej: Lun 8-10hs)" class="flex-1 border-gray-300 rounded-lg shadow-sm text-[11px]">
                                        @if($editando_detalle_index !== null)
                                            <button type="button" wire:click="$set('editando_detalle_index', null)" class="bg-gray-400 hover:bg-gray-500 text-white font-black px-3 rounded-lg shadow-sm transition uppercase text-[10px]">Cancelar</button>
                                            <button type="button" wire:click="agregarDetalle" class="bg-green-600 hover:bg-green-700 text-white font-black px-4 rounded-lg shadow-sm transition uppercase text-[10px] flex items-center">Guardar</button>
                                        @else
                                            <button type="button" wire:click="agregarDetalle" class="bg-blue-700 hover:bg-blue-800 text-white font-black px-4 rounded-lg shadow-sm transition uppercase text-[10px] flex items-center">Añadir</button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- LISTADO DE DETALLES --}}
                    @if(!empty($detalles_agregados))
                        <div class="border rounded-xl overflow-hidden shadow-sm">
                            <table class="min-w-22.5 divide-y divide-gray-200">
                                <thead class="bg-slate-800 text-white">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-[9px] font-black uppercase">Carrera / Ítem</th>
                                        <th class="px-3 py-2 text-left text-[9px] font-black uppercase">Detalle</th>
                                        <th class="px-3 py-2 text-center text-[9px] font-black uppercase">Acción</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-100">
                                    @foreach($detalles_agregados as $index => $det)
                                        <tr wire:key="det-{{ $index }}" class="hover:bg-indigo-50 transition-colors text-[10px] {{ $editando_detalle_index === $index ? 'bg-yellow-50 border-2 border-yellow-200' : '' }}">
                                            <td class="px-3 py-2">
                                                <div class="font-black text-gray-800 uppercase leading-tight">{{ $det['carrera_nombre'] }}</div>
                                                <div class="text-[9px] font-bold text-indigo-600 italic mb-1">{{ $det['instituto_nombre'] }}</div>
                                                <div class="flex items-center mt-1">
                                                    <span class="px-1.5 py-0.5 rounded text-[8px] font-black uppercase {{ $det['tipo'] == 'Cargo' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700' }}">{{ $det['tipo'] }}</span>
                                                    <span class="ml-1.5 font-bold text-gray-500">{{ $det['nombre'] }}</span>
                                                </div>
                                            </td>
                                            <td class="px-3 py-2">
                                                @if($det['tipo'] === 'Espacio')
                                                    <div class="font-bold text-gray-700">
                                                        
                                                        {{ isset($det['anio']) && $det['anio'] ? '- '.$det['anio'].'° año' : '' }}
                                                        {{ isset($det['turno']) && $det['turno'] ? '('.$det['turno'].')' : '' }}
                                                    </div>
                                                    @if(isset($det['periodo']) && $det['periodo'])
                                                        <div class="text-[9px] text-gray-400">{{ $det['periodo'] }}</div>
                                                    @endif
                                                @else
                                                    <div class="font-bold text-gray-700">
                                                  
                                                    </div>
                                                @endif
                                                @if(isset($det['hora_catedra']) && $det['hora_catedra'])
                                                    <div class="text-[9px] text-indigo-600 font-bold mt-0.5">
                                                        <span class="text-gray-500">Hs. Cátedra:</span> {{ $det['hora_catedra'] }}
                                                    </div>
                                                @endif
                                                <div class="text-[9px] italic text-gray-400 mt-0.5">{{ $det['horario_espacio'] }}</div>
                                            </td>
                                            <td class="px-3 py-2 text-center">
                                                <div class="flex justify-center items-center space-x-2">
                                                    <button type="button" wire:click="cargarDetalle({{ $index }})" class="text-blue-500 hover:text-blue-700 transition-colors p-1" title="Editar este ítem">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                                    </button>
                                                    <button type="button" wire:click="quitarDetalle({{ $index }})" class="text-red-400 hover:text-red-600 transition-colors p-1" title="Eliminar este ítem">
                                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                <div class="flex justify-end gap-3 mt-4 pt-3 border-t shrink-0">
                    <button wire:click="cerrarModal" class="px-5 py-2 text-xs font-black text-gray-500 hover:text-gray-700 uppercase tracking-widest transition-colors">Cancelar</button>
                    <button wire:click="guardarEdicion" wire:loading.attr="disabled" class="bg-indigo-600 hover:bg-indigo-700 text-white font-black px-8 py-2 rounded-lg text-xs uppercase tracking-widest shadow-md transition-all transform hover:-translate-y-0.5 disabled:opacity-50 disabled:cursor-not-allowed flex items-center">
                        <span wire:loading.remove>Actualizar Llamado</span>
                        <span wire:loading class="flex items-center">
                            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            Procesando...
                        </span>
                    </button>
                </div>
            </div>
        </div>
    @endif

</div>
