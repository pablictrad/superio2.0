<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Situacion_revista extends Model
{
    protected $table = 'tb_situacion_revista';

    protected $primaryKey = 'idtb_situacion_revista';

    public $timestamps = false;

    protected $fillable = ['nombre_situacion_revista'];
   public function revista()
   {
       return $this->hasMany(CargosPorLlamado::class);
   }
}
