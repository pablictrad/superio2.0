<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Perfil as PerfilModel;

class Perfil extends Component
{
    public $mostrarModalPerfiles = false;
    public $mostrarModalFormularioPerfil = false;
    public $buscarPerfil = '';
    public $perfilSeleccionado = null;
    public $idPerfilForm = null;
    public $nombrePerfilForm = '';
    public $modoEdicion = false;
  
    public function getPerfilesFiltradosProperty()
    {
        return PerfilModel::query()
            ->when($this->buscarPerfil, function ($q) {
                $q->where('nombre_perfil', 'like', '%' . $this->buscarPerfil . '%')
                  ->orWhere('idtb_perfil', 'like', '%' . $this->buscarPerfil . '%');
            })
            ->orderBy('idtb_perfil')
            ->limit(50)
            ->get();
    }

    public function seleccionarPerfil($id)
    {
        $this->perfilSeleccionado = $id;
        $this->mostrarModalPerfiles = false;
        $this->dispatch('perfilSeleccionado', id: $id);
    }

    public function nuevoPerfil()
    {
        $this->reset(['idPerfilForm', 'nombrePerfilForm']);
        $this->modoEdicion = false;
        $this->mostrarModalFormularioPerfil = true;
    }

    public function editarPerfil($id)
    {
        $perfil = PerfilModel::findOrFail($id);
        $this->idPerfilForm = $perfil->idtb_perfil;
        $this->nombrePerfilForm = $perfil->nombre_perfil;
        $this->modoEdicion = true;
        $this->mostrarModalFormularioPerfil = true;
    }

    public function guardarPerfil()
    {
        $this->validate(['nombrePerfilForm' => 'required|max:1000']);

        PerfilModel::updateOrCreate(
            ['idtb_perfil' => $this->idPerfilForm],
            ['nombre_perfil' => $this->nombrePerfilForm]
        );

        $this->mostrarModalFormularioPerfil = false;
    }

    public function render()
    {
        return view('livewire.perfil');
    }
}
