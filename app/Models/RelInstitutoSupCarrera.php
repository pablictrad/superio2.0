<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RelInstitutoSupCarrera extends Model
{
    protected $table = 'rel_instsup_carrera';
    public $timestamps = false;
    protected $fillable = ['instituto_id', 'carrera_id'];    

     public function instituto()
     {
          return $this->belongsTo(Instituto::class);
     }
     public function carrera()
     {
          return $this->belongsTo(Carrera::class);
     }

}
