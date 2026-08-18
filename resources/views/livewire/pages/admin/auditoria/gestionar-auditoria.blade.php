<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\DB;

new #[Title('Auditoría de Movimientos')] class extends Component
{
    use WithPagination;

    public string $buscar        = '';
    public string $filtroAccion  = '';
    public string $filtroUsuario = '';
    public string $desde         = '';
    public string $hasta         = '';

    public array $usuarios = [];
    public array $acciones = [];

    public function mount(): void
    {
        // AJUSTAR: confirmar que el nombre de rol de super admin sea exactamente 'super_admin'
        // (coincide con el valor insertado en tb_roles).
        $rol = DB::table('users')
            ->leftJoin('tb_roles', 'users.rol_id', '=', 'tb_roles.id')
            ->where('users.id', auth()->id())
            ->value('tb_roles.nombre_rol');

        abort_unless($rol === 'super_admin', 403, 'No tenés permiso para ver esta sección.');

        $this->usuarios = DB::table('users')->orderBy('name')->get(['id', 'name', 'email'])->toArray();
        $this->acciones = DB::table('tb_auditoria')->distinct()->orderBy('accion')->pluck('accion')->toArray();
    }

    #[Computed]
    public function movimientos()
    {
        return DB::table('tb_auditoria as a')
            ->leftJoin('users as u', 'a.usuario_id', '=', 'u.id')
            ->when($this->buscar !== '', function ($q) {
                $b = $this->buscar;
                $q->where(function ($sub) use ($b) {
                    $sub->where('a.detalle', 'ilike', "%{$b}%")
                        ->orWhere('u.name', 'ilike', "%{$b}%")
                        ->orWhere('u.email', 'ilike', "%{$b}%");
                });
            })
            ->when($this->filtroAccion !== '', fn ($q) => $q->where('a.accion', $this->filtroAccion))
            ->when($this->filtroUsuario !== '', fn ($q) => $q->where('a.usuario_id', $this->filtroUsuario))
            ->when($this->desde !== '', fn ($q) => $q->whereDate('a.created_at', '>=', $this->desde))
            ->when($this->hasta !== '', fn ($q) => $q->whereDate('a.created_at', '<=', $this->hasta))
            ->select('a.*', 'u.name as usuario_nombre', 'u.email as usuario_email')
            ->orderByDesc('a.created_at')
            ->paginate(30);
    }

    public function updatedBuscar(): void { $this->resetPage(); }
    public function updatedFiltroAccion(): void { $this->resetPage(); }
    public function updatedFiltroUsuario(): void { $this->resetPage(); }
    public function updatedDesde(): void { $this->resetPage(); }
    public function updatedHasta(): void { $this->resetPage(); }

    public function limpiarFiltros(): void
    {
        $this->reset(['buscar', 'filtroAccion', 'filtroUsuario', 'desde', 'hasta']);
        $this->resetPage();
    }
}; ?>

<div class="p-6 max-w-7xl mx-auto">
    <div class="mb-6">
        <h1 class="text-2xl font-black text-gray-800">Auditoría de Movimientos</h1>
        <p class="text-sm text-gray-500 mt-1">Registro de accesos y acciones administrativas del sistema.</p>
    </div>

    {{-- ── FILTROS ─────────────────────────────────────────────── --}}
    <div class="bg-white border border-gray-200 rounded-xl p-4 mb-4 grid grid-cols-1 md:grid-cols-5 gap-3">
        <div class="md:col-span-2">
            <label class="block text-[10px] font-black text-gray-500 uppercase mb-1">Buscar</label>
            <input type="text" wire:model.live.debounce.300ms="buscar" placeholder="Detalle, usuario o email..."
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-[10px] font-black text-gray-500 uppercase mb-1">Acción</label>
            <select wire:model.live="filtroAccion" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">Todas</option>
                @foreach($acciones as $accion)
                    <option value="{{ $accion }}">{{ $accion }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-[10px] font-black text-gray-500 uppercase mb-1">Usuario</label>
            <select wire:model.live="filtroUsuario" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">Todos</option>
                @foreach($usuarios as $u)
                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex gap-2">
            <div class="flex-1">
                <label class="block text-[10px] font-black text-gray-500 uppercase mb-1">Desde</label>
                <input type="date" wire:model.live="desde" class="w-full border border-gray-300 rounded-lg px-2 py-2 text-xs">
            </div>
            <div class="flex-1">
                <label class="block text-[10px] font-black text-gray-500 uppercase mb-1">Hasta</label>
                <input type="date" wire:model.live="hasta" class="w-full border border-gray-300 rounded-lg px-2 py-2 text-xs">
            </div>
        </div>
        <div class="md:col-span-5 flex justify-end">
            <button wire:click="limpiarFiltros" class="text-xs text-gray-500 hover:text-gray-700 underline">
                Limpiar filtros
            </button>
        </div>
    </div>

    {{-- ── TABLA ───────────────────────────────────────────────── --}}
    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-2.5 text-left text-[10px] font-black text-gray-500 uppercase">Fecha</th>
                    <th class="px-4 py-2.5 text-left text-[10px] font-black text-gray-500 uppercase">Usuario</th>
                    <th class="px-4 py-2.5 text-left text-[10px] font-black text-gray-500 uppercase">Acción</th>
                    <th class="px-4 py-2.5 text-left text-[10px] font-black text-gray-500 uppercase">Entidad</th>
                    <th class="px-4 py-2.5 text-left text-[10px] font-black text-gray-500 uppercase">Detalle</th>
                    <th class="px-4 py-2.5 text-left text-[10px] font-black text-gray-500 uppercase">IP</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($this->movimientos as $m)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2.5 text-xs text-gray-500 whitespace-nowrap">
                            {{ \Carbon\Carbon::parse($m->created_at)->format('d/m/Y H:i') }}
                        </td>
                        <td class="px-4 py-2.5 text-xs">
                            <span class="font-semibold text-gray-800">{{ $m->usuario_nombre ?? 'Sistema' }}</span>
                            @if($m->usuario_email)
                                <span class="block text-[10px] text-gray-400">{{ $m->usuario_email }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-xs">
                            <span class="px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700 font-bold text-[10px] uppercase">
                                {{ $m->accion }}
                            </span>
                        </td>
                        <td class="px-4 py-2.5 text-xs text-gray-500">
                            {{ $m->entidad ?? '—' }}
                            @if($m->entidad_id) <span class="text-gray-400">#{{ $m->entidad_id }}</span> @endif
                        </td>
                        <td class="px-4 py-2.5 text-xs text-gray-700 max-w-md truncate" title="{{ $m->detalle }}">
                            {{ $m->detalle ?? '—' }}
                        </td>
                        <td class="px-4 py-2.5 text-xs text-gray-400">{{ $m->ip ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-10 text-center text-gray-400 text-sm">
                            No hay movimientos registrados con esos filtros.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $this->movimientos->links() }}
    </div>
</div>
