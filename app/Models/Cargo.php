<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cargo extends Model
{
    protected $table = 'tb_cargos';
    public $timestamps = false;
    protected $fillable = ['nombre_cargo'];   

    public function cargoPorLlamado()
    {
        return $this->hasMany(CargoPorLlamado::class);
    }
}
