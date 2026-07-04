<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Instituto;

class RelCarreraEspacio extends Model
{
   protected $table = 'nuevo_rel_carrera_espacio';
   public $timestamps = false;
   protected $fillable = ['carrera_id', 'espacio_id', 'turno_id', 'periodo_id', 'perfil_id', 'hora_catedra', 'anio','instituto_id'];

    public function instituto()
    {
         return $this->belongsTo(Instituto::class, 'instituto_id', 'id');
    }
    public function carrera()
    {
         return $this->belongsTo(Carrera::class);
    }
    public function espacio()
    {
         return $this->belongsTo(Espacio_curricular::class, 'espacio_id', 'idEspacioCurricular');
    }
    public function turno()
    {       
          return $this->belongsTo(Turno::class);
    }
    public function periodo()
    {
          return $this->belongsTo(Periodo::class, 'periodo_id', 'idtb_periodo_cursado');
    }
    public function perfil()
    {
          return $this->belongsTo(Perfil::class, 'perfil_id', 'idtb_perfil');
    }

}
