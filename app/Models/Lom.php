<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lom extends Model
{
    protected $table = 'tb_lom';
    protected $primaryKey = 'idtb_lom';

    protected $fillable = [
        'id_instituto_superior',
        'idtipo_llamado',
        'idcarrera',
        'idtb_cargo',
        'idespaciocurricular',
        'imglom',
        'idtb_tipoestado',
        'idtb_zona',
        'pdf',
        'mes',
        'CUE',
        'idusuariocrear',
        'idusuarioeditar',
        'llamado_id',
    ];

    public function zona()
    {
        return $this->belongsTo(Zona::class, 'idtb_zona', 'id');
    }

    public function instituto()
    {
        return $this->belongsTo(InstitutoSuperior::class, 'id_instituto_superior', 'id');
    }

    public function carrera()
    {
        return $this->belongsTo(Carrera::class, 'idcarrera', 'id');
    }

    public function tipoLlamado()
    {
        return $this->belongsTo(TipoLlamado::class, 'idtipo_llamado', 'id');
    }
}