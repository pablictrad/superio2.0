<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tipo_llamado extends Model
{
    protected $table = 'tipo_llamado';
    protected $fillable = ['nombre'];

    public function llamados()
    {
        return $this->hasMany(Llamado::class);
    }   
}
