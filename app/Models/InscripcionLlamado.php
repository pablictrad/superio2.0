<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InscripcionLlamado extends Model
{
    protected $table = 'inscripciones_llamado';

    protected $fillable = [
        'llamado_id',
        'apellido',
        'nombre',
        'dni',
        'telefono',
        'email',
        'domicilio',
        'titulos',
        'observaciones',
        'orden',
        'estado',
        'puntaje',
    ];

    protected $casts = [
        'puntaje' => 'decimal:2',
        'orden'   => 'integer',
    ];

    // ── Relación con el llamado ────────────────────────────────────
    public function llamado(): BelongsTo
    {
        return $this->belongsTo(NuevoLlamado::class, 'llamado_id');
    }

    // ── Scopes de estado ──────────────────────────────────────────
    public function scopeHabilitados($query)
    {
        return $query->where('estado', 'habilitado')->orderByDesc('puntaje')->orderBy('apellido');
    }

    public function scopeSinClasificar($query)
    {
        return $query->where('estado', 'sin_clasificar')->orderBy('apellido');
    }

    public function scopePendientes($query)
    {
        return $query->where('estado', 'pendiente')->orderBy('apellido');
    }

    // ── Accessor: nombre completo ──────────────────────────────────
    public function getNombreCompletoAttribute(): string
    {
        return $this->apellido . ' ' . $this->nombre;
    }
}
