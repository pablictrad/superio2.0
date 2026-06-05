<div>    
  
        <flux:button variant="primary" size="xs" wire:click="$set('mostrarModalPerfiles', true)">
            Seleccionar Perfil
        </flux:button>
   

    <flux:modal wire:model="mostrarModalPerfiles" class="">
      
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="lg">Seleccione un Perfil</flux:heading>
                    <flux:text class="mt-1">Busque y seleccione un perfil</flux:text>
                </div>
                <flux:button variant="primary" size="xs" class="mt-4 px-2 py-1 text-xs"  wire:click="nuevoPerfil">+ Agregar Perfil</flux:button>
            </div>

            <flux:input wire:model.live.debounce.300ms="buscarPerfil" placeholder="Buscar perfil..." />

            <div class="overflow-auto border rounded-xl">
                <table class="min-w-full text-sm">
                    <thead class="bg-zinc-100 dark:bg-zinc-800">
                        <tr>
                            <th class="px-2 py-3 text-left">ID</th>
                            <th class="px-4 py-3 text-left">Nombre</th>
                            <th class="px-4 py-3 text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($this->perfilesFiltrados as $per)
                            <tr class="border-t">
                                <td class="px-2 py-3">{{ $per->idtb_perfil }}</td>
                                <td class="px-4 py-3 whitespace-pre-line">{{ $per->nombre_perfil }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-col items-center gap-1">
                                        <flux:button size="xs" variant="primary"  class="px-2 py-1 text-xs" wire:click="seleccionarPerfil({{ $per->idtb_perfil }})">Seleccionar</flux:button>
                                        <flux:button size="xs" variant="filled" class="px-2 py-1 text-xs" wire:click="editarPerfil({{ $per->idtb_perfil }})">Editar</flux:button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-6 text-center text-zinc-500">No se encontraron perfiles</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </flux:modal>

    <flux:modal wire:model="mostrarModalFormularioPerfil" class="md:w-3xl">
        <div class="space-y-6">
            <flux:heading size="lg">{{ $modoEdicion ? 'Editar Perfil' : 'Agregar Perfil' }}</flux:heading>
            <flux:textarea wire:model="nombrePerfilForm" rows="10" maxlength="1000" label="Nombre del Perfil" />
            <div class="flex justify-end">
                <flux:button variant="primary" wire:click="guardarPerfil">Guardar</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
