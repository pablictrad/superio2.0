<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Docente extends Model
{
    protected $table = 'tb_docentes';

    protected $fillable = [
        'dni',
        'apellido',
        'nombre',
        'telefono',
        'email',
        'domicilio',
        'localidad',
        'tiene_legajo',
    ];   protected $casts = [
        'tiene_legajo' => 'boolean',
    ];
 
    // ── Relaciones ─────────────────────────────────────────────────
    public function titulos(): HasMany
    {
        return $this->hasMany(DocenteTitulo::class, 'docente_id');
    }
 
    public function certificados(): HasMany
    {
        return $this->hasMany(DocenteCertificado::class, 'docente_id');
    }
 
    public function inscripciones(): HasMany
    {
        return $this->hasMany(InscripcionLlamado::class, 'docente_id');
    }
 
    // ── Accessor ───────────────────────────────────────────────────
    public function getNombreCompletoAttribute(): string
    {
        return $this->apellido . ', ' . $this->nombre;
    }
    public function domicilioActual()
    {
        return $this->belongsTo(Domicilio::class, 'domicilio_id', 'idtb_domicilio');
    }

    public function domicilios(): HasMany
    {
        return $this->hasMany(Domicilio::class, 'docente_id');
    }
}
