# Inscripciones Docentes + Generación de PDF  
## Sistema de Llamados — Nivel Superior

---

## Archivos entregados

| Archivo | Destino en el proyecto |
|---------|------------------------|
| `2024_01_01_000001_create_inscripciones_llamado_table.php` | `database/migrations/` |
| `LlamadoPdfController.php` | `app/Http/Controllers/` |
| `llamado.blade.php` | `resources/views/pdf/` *(crear carpeta `pdf`)* |
| `inscripcion-llamado.blade.php` | `resources/views/livewire/` |
| `gestionar-inscriptos.blade.php` | `resources/views/livewire/` |
| `routes_snippet.php` | Agregar a `routes/web.php` |
| `integracion_snippets.blade.php` | Referencia para modificar vistas existentes |

---

## 1. Instalar dependencia PDF

```bash
composer require barryvdh/laravel-dompdf
php artisan vendor:publish --provider="Barryvdh\DomPDF\ServiceProvider"
```

---

## 2. Ejecutar migración

```bash
php artisan migrate
```

Crea la tabla `inscripciones_llamado` con los campos:

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `llamado_id` | FK | Referencia a `nuevo_llamado.id` |
| `apellido` | string | Apellido del docente (se guarda en MAYÚSCULAS) |
| `nombre` | string | Nombre/s del docente |
| `dni` | string | Solo dígitos, unique por llamado |
| `telefono` | string\|null | |
| `email` | string\|null | |
| `domicilio` | string\|null | |
| `titulos` | text\|null | Antecedentes libres |
| `observaciones` | text\|null | Motivo de sin_clasificar, notas admin |
| `orden` | smallint\|null | Puesto en el ranking |
| `estado` | enum | `pendiente` / `habilitado` / `sin_clasificar` |
| `puntaje` | decimal(6,2)\|null | Asignado por la comisión |

**Restricción única:** un mismo DNI no puede inscribirse dos veces al mismo llamado.

---

## 3. Agregar rutas

En `routes/web.php`, dentro del grupo `auth` del admin, agregar:

```php
use App\Http\Controllers\LlamadoPdfController;

Route::get('/admin/llamados/{llamadoId}/pdf', [LlamadoPdfController::class, 'generar'])
     ->name('admin.llamados.pdf');
```

---

## 4. Modificar `publico.blade.php`

### 4.1 Agregar el componente de inscripción

Al comienzo del `<div>` raíz (antes de la tabla), agregar:

```blade
@livewire('inscripcion-llamado')
```

### 4.2 Reemplazar el botón "Postularme"

Buscar el botón que apunta a `$item->url_form` y reemplazarlo por:

```blade
<button
    wire:click="$dispatchTo('inscripcion-llamado', 'abrirModal', { id: {{ $item->id }} })"
    @if($item->idtb_tipoestado != 8) disabled @endif
    class="inline-flex items-center bg-blue-600 hover:bg-blue-700 disabled:bg-gray-300
           disabled:cursor-not-allowed text-white font-black px-2 py-2 rounded-lg
           text-[10px] uppercase transition-all shadow-md">
    Inscribirme
</button>
```

---

## 5. Modificar `crear.blade.php`

### 5.1 Agregar el componente de gestión

Al comienzo del `<div>` raíz (antes del formulario):

```blade
@livewire('gestionar-inscriptos')
```

### 5.2 Agregar botón "Inscriptos" en la columna de acciones

Dentro del `<div class="flex flex-col items-center space-y-1.5">` del historial, agregar (después del botón Editar):

```blade
<button
    wire:click="$dispatchTo('gestionar-inscriptos', 'abrirPanel', { id: {{ $item->id }} })"
    class="w-auto min-w-22.5 px-3 bg-teal-600 hover:bg-teal-700 text-white
           font-black py-2 rounded text-sm uppercase transition-all shadow-sm
           flex items-center justify-center gap-1">
    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857
                 M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002
                 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
    </svg>
    Inscriptos
</button>
```

---

## 6. Flujo completo

```
DOCENTE (sin login)
  ↓
publico.blade.php  →  botón "Inscribirme"
  ↓
Modal inscripcion-llamado  →  formulario (apellido, nombre, DNI, tel, email, domicilio, títulos)
  ↓
inscripciones_llamado  (estado: 'pendiente')
  ↓
ADMIN
  ↓
crear.blade.php  →  botón "Inscriptos" (columna acciones)
  ↓
Modal gestionar-inscriptos
  ↓
  ├─ Cambiar estado: pendiente / habilitado / sin_clasificar
  ├─ Asignar puntaje y orden
  └─ Botón "Generar PDF"
       ↓
  LlamadoPdfController  →  resources/views/pdf/llamado.blade.php
       ↓
  PDF descargable (landscape A4) con:
    ├─ Cabecera institucional
    ├─ Datos del llamado (zona, tipo, fechas, descripción)
    ├─ Tabla de espacios/cargos (instituto, carrera, nombre, hs, año, período, turno, sit.revista, horario)
    ├─ Perfil requerido
    ├─ Listado habilitados (orden, apellido/nombre, DNI, tel, email, domicilio, puntaje)
    ├─ Listado sin clasificar (apellido/nombre, DNI, unidad, carrera, motivo)
    └─ Sección de firmas (Vocal 1 / Vocal 2 / Presidente Comisión)
```

---

## 7. Notas importantes

- El PDF solo incluye inscriptos con estado `habilitado` o `sin_clasificar`. Los `pendientes` no aparecen hasta que el admin los clasifique.
- El puntaje determina el orden en el ranking del PDF (mayor puntaje primero). Si dos docentes empatan en puntaje, se ordenan alfabéticamente por apellido.
- El campo `url_form` de `nuevo_llamado` puede mantenerse como respaldo externo; simplemente ya no se usa como botón principal en la vista pública.
- La restricción única `(llamado_id, dni)` en la base de datos impide doble inscripción aunque el usuario intente burlar la validación frontend.

---

## 8. Futuro (roadmap)

- [ ] Registro de docentes con cuenta propia (ver/editar sus inscripciones)  
- [ ] Notificación por email al inscribirse  
- [ ] Carga de documentación adjunta (títulos en PDF)  
- [ ] Exportar inscriptos a Excel  
- [ ] Firma digital en el PDF  
