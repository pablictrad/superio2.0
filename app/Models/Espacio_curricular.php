<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Espacio_curricular extends Model
{
    protected $table = 'tb_espacioscurriculares';
    protected $primaryKey = 'idespaciocurricular';
    public $timestamps = false;
    protected $fillable = ['nombre_espacio'];

        public function relCarreraEspacio()
        {
            return $this->hasMany(RelCarreraEspacio::class);
        }
}
