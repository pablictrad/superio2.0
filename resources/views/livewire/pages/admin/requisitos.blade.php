<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;

new class extends Component
{
    use WithFileUploads;

    // Paleta de colores disponible para las tarjetas
    public array $colores = [
        'indigo' => 'Índigo',
        'green'  => 'Verde',
        'red'    => 'Rojo',
        'amber'  => 'Ámbar',
        'blue'   => 'Azul',
        'purple' => 'Violeta',
        'pink'   => 'Rosa',
    ];

    // Form novedad
    public $novedad_id = null;
    public string $titulo = '';
    public string $contenido = '';
    public string $color = 'indigo';
    public bool $modalAbierto = false;

    // Form PDF
    public $nuevoPdf = null;
    public string $tituloPdf = '';

    #[Computed(cache: false)]
    public function novedades()
    {
        return DB::table('tb_requisitos_novedades')
            ->orderBy('orden')
            ->orderByDesc('id')
            ->get();
    }

    #[Computed(cache: false)]
    public function documentos()
    {
        return DB::table('tb_requisitos_documentos')->orderBy('id', 'desc')->get();
    }

    public function abrirCrear()
    {
        $this->reset(['novedad_id', 'titulo', 'contenido']);
        $this->color = 'indigo';
        $this->modalAbierto = true;
    }

    public function editar($id)
    {
        $n = DB::table('tb_requisitos_novedades')->find($id);
        if (!$n) return;

        $this->novedad_id = $n->id;
        $this->titulo     = $n->titulo;
        $this->contenido  = $n->contenido;
        $this->color      = $n->color;
        $this->modalAbierto = true;
    }

    public function guardar()
    {
        $this->validate([
            'titulo'    => 'required|string|max:150',
            'contenido' => 'required|string|max:5000',
            'color'     => 'required|in:' . implode(',', array_keys($this->colores)),
        ]);

        if ($this->novedad_id) {
            DB::table('tb_requisitos_novedades')
                ->where('id', $this->novedad_id)
                ->update([
                    'titulo'     => $this->titulo,
                    'contenido'  => $this->contenido,
                    'color'      => $this->color,
                    'updated_at' => now(),
                ]);
            session()->flash('ok', 'Novedad actualizada.');
        } else {
            $siguienteOrden = (DB::table('tb_requisitos_novedades')->max('orden') ?? -1) + 1;

            DB::table('tb_requisitos_novedades')->insert([
                'titulo'     => $this->titulo,
                'contenido'  => $this->contenido,
                'color'      => $this->color,
                'orden'      => $siguienteOrden,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            session()->flash('ok', 'Novedad publicada.');
        }

        $this->cerrarModal();
    }

    public function eliminar($id)
    {
        DB::table('tb_requisitos_novedades')->where('id', $id)->delete();
        session()->flash('ok', 'Novedad eliminada.');
    }

    public function subirOrden($id)
    {
        $this->moverOrden($id, -1);
    }

    public function bajarOrden($id)
    {
        $this->moverOrden($id, 1);
    }

    private function moverOrden($id, int $direccion)
    {
        // Lista actual en el orden en que se muestra
        $lista = DB::table('tb_requisitos_novedades')
            ->orderBy('orden')
            ->orderByDesc('id')
            ->get(['id', 'orden']);

        $posicion = $lista->search(fn ($n) => $n->id == $id);
        if ($posicion === false) return;

        $destino = $posicion + $direccion;
        if ($destino < 0 || $destino >= $lista->count()) return;

        $actual  = $lista[$posicion];
        $vecino  = $lista[$destino];

        // Aseguramos valores de "orden" distintos y consistentes con la nueva posición,
        // reasignando secuencialmente 0..n-1 tras el intercambio.
        $ids = $lista->pluck('id')->values();
        $ids->splice($posicion, 1);
        $ids->splice($destino, 0, [$actual->id]);

        foreach ($ids->values() as $i => $novedadId) {
            DB::table('tb_requisitos_novedades')
                ->where('id', $novedadId)
                ->update(['orden' => $i]);
        }
    }

    public function cerrarModal()
    {
        $this->modalAbierto = false;
        $this->reset(['novedad_id', 'titulo', 'contenido']);
        $this->color = 'indigo';
    }

    public function subirPdf()
    {
        $this->validate([
            'tituloPdf' => 'required|string|max:150',
            'nuevoPdf'  => 'required|file|mimes:pdf|max:10240',
        ]);

        $ruta = $this->nuevoPdf->store('requisitos', 'public');

        DB::table('tb_requisitos_documentos')->insert([
            'titulo'     => $this->tituloPdf,
            'archivo'    => $ruta,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->reset(['nuevoPdf', 'tituloPdf']);
        session()->flash('ok', 'PDF subido correctamente.');
    }

    public function eliminarPdf($id)
    {
        $doc = DB::table('tb_requisitos_documentos')->find($id);

        if ($doc) {
            Storage::disk('public')->delete($doc->archivo);
            DB::table('tb_requisitos_documentos')->where('id', $id)->delete();
        }

        session()->flash('ok', 'PDF eliminado.');
    }

    // Mapeo color -> clases Tailwind (se declaran literales para que el JIT las detecte)
    public function claseSwatch(string $color): string
    {
        return match ($color) {
            'green'  => 'bg-green-500',
            'red'    => 'bg-red-500',
            'amber'  => 'bg-amber-500',
            'blue'   => 'bg-blue-500',
            'purple' => 'bg-purple-500',
            'pink'   => 'bg-pink-500',
            default  => 'bg-indigo-500',
        };
    }

    public function claseTarjeta(string $color): array
    {
        return match ($color) {
            'green'  => ['bg' => 'bg-green-50',  'border' => 'border-green-300',  'title' => 'text-green-800'],
            'red'    => ['bg' => 'bg-red-50',    'border' => 'border-red-300',    'title' => 'text-red-800'],
            'amber'  => ['bg' => 'bg-amber-50',  'border' => 'border-amber-300',  'title' => 'text-amber-800'],
            'blue'   => ['bg' => 'bg-blue-50',   'border' => 'border-blue-300',   'title' => 'text-blue-800'],
            'purple' => ['bg' => 'bg-purple-50', 'border' => 'border-purple-300', 'title' => 'text-purple-800'],
            'pink'   => ['bg' => 'bg-pink-50',   'border' => 'border-pink-300',   'title' => 'text-pink-800'],
            default  => ['bg' => 'bg-indigo-50', 'border' => 'border-indigo-300', 'title' => 'text-indigo-800'],
        };
    }
}; ?>

<div class="p-6 bg-white rounded-xl shadow-lg border border-gray-100 max-w-5xl mx-auto my-8">
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-xl font-black text-slate-800">Requisitos / Novedades (público)</h2>
        <button
            wire:click="abrirCrear"
            class="bg-indigo-600 hover:bg-indigo-700 text-white font-black px-4 py-2 rounded-lg text-sm">
            + Nueva tarjeta
        </button>
    </div>

    @if (session('ok'))
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-700 text-sm font-bold px-4 py-2">
            {{ session('ok') }}
        </div>
    @endif

    {{-- Grilla de tarjetas --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-10">
        @forelse ($this->novedades as $i => $n)
            @php $c = $this->claseTarjeta($n->color); @endphp
            <div wire:key="nov-{{ $n->id }}"
                 class="rounded-xl border-2 {{ $c['border'] }} {{ $c['bg'] }} p-4 flex flex-col justify-between shadow-sm">
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-slate-800 text-white text-[10px] font-black">
                            {{ $i + 1 }}
                        </span>
                        <div class="flex items-center gap-1">
                            <button
                                wire:click="subirOrden({{ $n->id }})"
                                @if($i === 0) disabled @endif
                                title="Subir prioridad"
                                class="w-6 h-6 flex items-center justify-center rounded bg-white/70 border border-black/10 text-slate-600 hover:bg-white disabled:opacity-30 disabled:cursor-not-allowed text-xs font-black">
                                ▲
                            </button>
                            <button
                                wire:click="bajarOrden({{ $n->id }})"
                                @if($i === $this->novedades->count() - 1) disabled @endif
                                title="Bajar prioridad"
                                class="w-6 h-6 flex items-center justify-center rounded bg-white/70 border border-black/10 text-slate-600 hover:bg-white disabled:opacity-30 disabled:cursor-not-allowed text-xs font-black">
                                ▼
                            </button>
                        </div>
                    </div>
                    <h3 class="font-black text-sm {{ $c['title'] }} mb-2">{{ $n->titulo }}</h3>
                    <p class="text-xs text-gray-700 whitespace-pre-line">{{ Str::limit($n->contenido, 160) }}</p>
                </div>
                <div class="flex items-center justify-end gap-3 mt-4 pt-3 border-t border-black/5">
                    <button wire:click="editar({{ $n->id }})"
                            class="text-xs font-black uppercase text-slate-600 hover:text-slate-900">
                        Editar
                    </button>
                    <button wire:click="eliminar({{ $n->id }})"
                            wire:confirm="¿Eliminar esta tarjeta?"
                            class="text-xs font-black uppercase text-red-600 hover:text-red-800">
                        Eliminar
                    </button>
                </div>
            </div>
        @empty
            <div class="col-span-full text-center text-gray-400 italic text-sm py-8">
                No hay tarjetas publicadas todavía.
            </div>
        @endforelse
    </div>

    <hr class="my-8 border-gray-200">

    {{-- Subida de PDFs --}}
    <div class="mb-8">
        <label class="block text-sm font-black text-slate-700 mb-2">Subir PDF informativo</label>
        <div class="flex flex-col sm:flex-row gap-3">
            <input type="text" wire:model="tituloPdf" placeholder="Título del documento"
                   class="flex-1 rounded-lg border border-gray-300 p-2 text-sm">
            <input type="file" wire:model="nuevoPdf" accept="application/pdf" class="flex-1 text-sm">
            <button wire:click="subirPdf" wire:loading.attr="disabled"
                    class="bg-emerald-600 hover:bg-emerald-700 text-white font-black px-4 py-2 rounded-lg text-sm disabled:opacity-50">
                Subir
            </button>
        </div>
        @error('tituloPdf') <span class="text-red-600 text-xs font-bold block mt-1">{{ $message }}</span> @enderror
        @error('nuevoPdf') <span class="text-red-600 text-xs font-bold block mt-1">{{ $message }}</span> @enderror
        <div wire:loading wire:target="nuevoPdf" class="text-xs text-gray-400 mt-1">Subiendo...</div>
    </div>

    <div>
        <label class="block text-sm font-black text-slate-700 mb-2">Documentos publicados</label>
        <div class="divide-y divide-gray-100 border border-gray-200 rounded-lg">
            @forelse ($this->documentos as $doc)
                <div wire:key="doc-{{ $doc->id }}" class="flex items-center justify-between px-4 py-3">
                    <a href="{{ asset('storage/' . $doc->archivo) }}" target="_blank"
                       class="text-indigo-600 hover:underline text-sm font-bold">
                        {{ $doc->titulo }}
                    </a>
                    <button wire:click="eliminarPdf({{ $doc->id }})" wire:confirm="¿Eliminar este documento?"
                            class="text-red-600 hover:text-red-800 text-xs font-black uppercase">
                        Eliminar
                    </button>
                </div>
            @empty
                <div class="px-4 py-6 text-center text-gray-400 text-sm italic">
                    No hay documentos cargados.
                </div>
            @endforelse
        </div>
    </div>

    {{-- Modal crear/editar tarjeta --}}
    @if($modalAbierto)
        <div class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4" wire:click.self="cerrarModal">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg p-6">
                <h3 class="text-lg font-black text-slate-800 mb-4">
                    {{ $novedad_id ? 'Editar tarjeta' : 'Nueva tarjeta' }}
                </h3>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-black text-slate-600 mb-1">Título</label>
                        <input type="text" wire:model="titulo"
                               class="w-full rounded-lg border border-gray-300 p-2 text-sm">
                        @error('titulo') <span class="text-red-600 text-xs font-bold">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-black text-slate-600 mb-1">Contenido</label>
                        <textarea wire:model="contenido" rows="5"
                                  class="w-full rounded-lg border border-gray-300 p-2 text-sm"></textarea>
                        @error('contenido') <span class="text-red-600 text-xs font-bold">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-black text-slate-600 mb-2">Color</label>
                        <div class="flex flex-wrap gap-2">
                            @foreach($colores as $key => $label)
                                <button type="button"
                                        wire:click="$set('color', '{{ $key }}')"
                                        class="flex items-center gap-2 px-3 py-1.5 rounded-full border text-xs font-bold
                                            {{ $color === $key ? 'border-slate-800 bg-slate-100' : 'border-gray-200' }}">
                                    <span class="w-3 h-3 rounded-full {{ $this->claseSwatch($key) }}"></span>
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3 mt-6">
                    <button wire:click="cerrarModal"
                            class="px-4 py-2 rounded-lg text-sm font-black text-slate-500 hover:bg-gray-100">
                        Cancelar
                    </button>
                    <button wire:click="guardar"
                            class="px-4 py-2 rounded-lg text-sm font-black bg-indigo-600 hover:bg-indigo-700 text-white">
                        Guardar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>