<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Estado extends Model
{
    protected $table = 'tb_tipoestado';

    protected $primaryKey = 'idtb_tipoestado';

    public $timestamps = false;

    protected $fillable = ['nombre_tipoestado'];

    public function estado()
    {
        return $this->hasMany(Llamado::class);
    }
}
