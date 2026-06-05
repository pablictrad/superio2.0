<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Carrera extends Model
{
     protected $table = 'tb_carreras';
   public $timestamps = false;
   protected $fillable = ['nombre_carrera', 'titulo'];    

    public function institutos()
    {
        return $this->belongsToMany(Instituto::class, 'rel_instsup_carrera', 'carrera_id', 'instituto_id');
    }
     public function relCarreraEspacio()
     {
           return $this->hasMany(RelCarreraEspacio::class);
     }
}
