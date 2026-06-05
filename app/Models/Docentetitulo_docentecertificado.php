<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Docentetitulo_docentecertificado extends Model
{
     protected $table = 'tb_docente_titulos';
 
    protected $fillable = [
        'docente_id',
        'nombre_titulo',
        'institucion',
        'anio_egreso',
        'archivo_path',
        'archivo_nombre_original',
    ];
 
    public function docente(): BelongsTo
    {
        return $this->belongsTo(Docente::class, 'docente_id');
    }
}
 
 
/**
 * DocenteCertificado
 *
 * Certificado adicional (capacitaciones, desempeño, antigüedad, etc.)
 * El campo nombre_certificado + docente_id es UNIQUE para evitar duplicados.
 */
class DocenteCertificado extends Model
{
    protected $table = 'tb_docente_certificados';
 
    protected $fillable = [
        'docente_id',
        'nombre_certificado',
        'tipo',
        'archivo_path',
        'archivo_nombre_original',
    ];
 
    public function docente(): BelongsTo
    {
        return $this->belongsTo(Docente::class, 'docente_id');
    }
}
