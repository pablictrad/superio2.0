<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Periodo extends Model
{
    protected $table = 'tb_periodo_cursado';
    protected $primaryKey = 'idtb_periodo_cursado';
    public $timestamps = false;
    protected $fillable = ['nombre_periodo'];

    public function relCarreraEspacio()
    {
        return $this->hasMany(RelCarreraEspacio::class);
    }
    
}
