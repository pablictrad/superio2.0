<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Zona extends Model
{
  public $timestamps = false;
  protected $table = 'tb_zona'; 
  protected $fillable = ['nombre_zona'];
  
  public function institutos()
  {
      return $this->hasMany(Instituto::class);
  }
}
