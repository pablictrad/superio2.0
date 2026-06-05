<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Llamado extends Model
{
    protected $table = 'nuevo_llamado';
    protected $fillable = [ 'idtb_zona', 'idtipo_llamado', 
    'fecha_ini', 'fecha_fin', 'idtb_tipoestado',
     'descripcion', 'url_form', 'nombre_img',
     'mes','id_usuario_crear','id_usuario_editar', 'publicado'
];
    public $timestamps = false;
    
    public function cargos()
    {
        return $this->hasMany(CargoPorLlamado::class);
    }
    public function espacios()
    {
        return $this->hasMany(EspacioPorLlamado::class);
    }
    
    public function estado()
    {
        return $this->belongsTo(Estado::class);
    }
    public function tipo_llamado()
    {
        return $this->belongsTo(Tipo_llamado::class);
    }
   
    public function zona()
    {
        return $this->belongsTo(Zona::class, 'idtb_zona');
    }   
}
