<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Domicilio extends Model
{
    protected $table = 'tb_domicilio';
    protected $primaryKey = 'idtb_domicilio';
    protected $fillable = [
        'calle', 'barrio', 'numcasa_piso', 'piso', 'manzana',
        'localidad_id', 'docente_id', 'tipoestado_id',
        'archivo_dni', 'archivo_factura', 'archivo_certifdomicilio',
    ];

    public function localidad()
    {
        return $this->belongsTo(Localidad::class, 'localidad_id');
    }

    public function docente()
    {
        return $this->belongsTo(Docente::class, 'docente_id');
    }
}