<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Turno extends Model
{
    public $timestamps = false;
    protected $table = 'tb_turnos';
    protected $fillable = ['nombre_turnos'];

    public function cargoPorLlamado()
    {
        return $this->hasMany(CargoPorLlamado::class);
    }
    public function relCarreraEspacio()
    {
        return $this->hasMany(RelCarreraEspacio::class);
    }

}
