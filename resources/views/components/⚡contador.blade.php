<?php

use Livewire\Component;

new class extends Component
{
    public $contador = 0;

    public function incrementar()
    {
        $this->contador++;
    }

    public function decrementar()
    {
        $this->contador--;
    }

   
};
?>

<div>
   <h1>contador: {{ $contador }}</h1>
   <button wire:click="incrementar">Incrementar</button>
   <button wire:click="decrementar">Decrementar</button>    
</div>