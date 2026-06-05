<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CargoPorLlamado extends Model
{
   
protected $table = 'nuevo_cargo_por_llamado';
    protected $fillable = [
        'llamado_id',
        'nuevo_rel_carrera_cargo_id',
        'situacion_revista_id',
        'horario_cargo',
    ];

    public function llamado()
    {
        return $this->belongsTo(Llamado::class);
    }

    public function cargo()
    {
        return $this->belongsTo(Cargo::class);
    }

    public function turno()
    {
        return $this->belongsTo(Turno::class);
    }
    public function situacion_revista()
    {
        return $this->belongsTo(Situacion_revista::class);
    }
}
