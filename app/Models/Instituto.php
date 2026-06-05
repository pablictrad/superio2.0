<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Instituto extends Model
{
    public $timestamps = false;
    protected $table = 'tb_instituto_superior';
    protected $fillable = ['nombre',
     'zona_id',
    'idLocalidad',
    'cue_instsup',
    'habilitado'
    ];

    public function zona(){
        return $this->belongsTo(Zona::class);
    }


    public function carreras()
    {
        return $this->belongsToMany(Carrera::class, 'rel_instsup_carrera', 'instituto_id', 'carrera_id');
    }
    public function relCarreraEspacios()
    {
        return $this->hasMany(RelCarreraEspacio::class, 'instituto_id', 'idtb_instituto');
    }
    public function relInstitutoCargos()
    {
        return $this->hasMany(RelInstitutoCargo::class, 'instituto_id', 'idtb_instituto');
    }
}
