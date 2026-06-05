<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EspacioPorLlamado extends Model
{
    protected $table = 'nuevo_espacios_por_llamado';
    protected $fillable = [
        'llamado_id',
        'nuevo_rel_carrera_espacio_id',
        'situacion_revista_id',
        'horario_espacio',
    ];

    public function llamado()
    {
        return $this->belongsTo(Llamado::class);
    }

    public function relCarreraEspacio()
    {
        return $this->belongsTo(RelCarreraEspacio::class, 'nuevo_rel_carrera_espacio_id');
    }

    public function situacion_revista()
    {
        return $this->belongsTo(Situacion_revista::class);
    }
}
