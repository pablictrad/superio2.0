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
        'nuevo_rel_instituto_cargo_id',
    ];

    public function llamado()
    {
        return $this->belongsTo(Llamado::class);
    }

    public function situacion_revista()
    {
        return $this->belongsTo(Situacion_revista::class);
    }
    public function rel_instituto_cargo()
    {
        return $this->belongsTo(RelInstitutoCargo::class, 'nuevo_rel_instituto_cargo_id');
    }
    public function rel_carrera_cargo()
    {
        return $this->belongsTo(RelCarreraCargo::class, 'nuevo_rel_carrera_cargo_id');
    }
}
