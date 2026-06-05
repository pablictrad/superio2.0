<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Perfil extends Model
{
    protected $table = 'tb_perfil';
    protected $primaryKey = 'idtb_perfil';
    public $timestamps = false;
    protected $fillable = ['nombre_perfil'];

    public function relCarreraEspacio()
    {
        return $this->hasMany(RelCarreraEspacio::class);
    }
    
}

    
