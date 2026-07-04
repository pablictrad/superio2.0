<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RelInstitutoCargo extends Model
{
    protected $table = 'nuevo_rel_instituto_cargo';

    protected $fillable = [
        'instituto_superior_id',
        'cargo_id',
        'perfil_id',
        'turno_id',
    ];

    public function instituto()
    {
        return $this->belongsTo(Instituto::class, 'instituto_superior_id');
    }

    public function cargo()
    {
        return $this->belongsTo(Cargo::class, 'cargo_id');
    }

    public function perfil()
    {
        return $this->belongsTo(Perfil::class, 'perfil_id');
    }

    public function turno()
    {
        return $this->belongsTo(Turno::class, 'turno_id');
    }
}
