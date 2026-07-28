<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Localidad extends Model
{
    protected $table = 'tb_localidades';
    protected $fillable = ['localidad', 'idepartamento', 'codigopostal', 'zona_override'];

    public function departamento()
    {
        return $this->belongsTo(Departamento::class, 'idepartamento', 'iddepartamento');
    }

    /**
     * Resuelve la zona real de esta localidad:
     * excepción puntual (zona_override) o, si no hay, la del departamento.
     * Devuelve el id de tb_zona (no el texto romano).
     */
    public function getZonaIdAttribute(): ?int
    {
        $texto = $this->zona_override ?: optional($this->departamento)->zona;

        if (!$texto) {
            return null;
        }

        return Zona::whereRaw('UPPER(nombre_zona) = ?', [mb_strtoupper(trim($texto))])->value('id');
    }
}
