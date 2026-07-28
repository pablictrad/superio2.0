<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Departamento extends Model
{
    protected $table = 'tb_departamentos';
    protected $primaryKey = 'iddepartamento';
    protected $fillable = ['nombre_dpto', 'idprovincia', 'zona'];

    public function localidades()
    {
        return $this->hasMany(Localidad::class, 'idepartamento', 'iddepartamento');
    }
}