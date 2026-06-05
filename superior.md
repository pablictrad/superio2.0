# **Sistema de Llamados Docentes**  
## **Tecnologías utilizadas**  
- Laravel 13  
- Livewire 4  
- Volt  
- Flux UI  
- TailwindCSS  
# **Arquitectura General**  
El sistema administra la creación y publicación de llamados docentes para Nivel Superior.  
Los llamados se crean desde:  
- crear.blade.php  
Y se publican automáticamente en:  
- publico.blade.php  
Ambas vistas comparten la misma estructura visual de tabla para mantener consistencia entre el panel administrativo y la vista pública.  
# **Flujo General del Sistema**  
crear.blade.php  
    ↓  
Carga de llamados y detalles  
    ↓  
Guardado en:  
- nuevo_llamado  
- nuevo_espacios_por_llamado  
- nuevo_cargo_por_llamado  
    ↓  
Publicación  
    ↓  
publico.blade.php  
   
# **Archivo: crear.blade.php**  
## **Función principal**  
Este archivo administra:  
- Creación de llamados  
- Edición de llamados  
- Eliminación  
- Publicación  
- Gestión dinámica de detalles  
- Historial administrativo  
Implementado con:  
- Livewire Volt  
- Componentes reactivos  
- Queries dinámicas  
- Formularios dependientes  
# **Funcionalidades principales**  
## **1. Cabecera del llamado**  
Permite definir:  
- Tipo de llamado  
- Estado  
- Fecha inicio  
- Fecha fin  
- URL de inscripción  
- Descripción  
El estado se actualiza automáticamente según la fecha de cierre.  
## **2. Ubicación académica**  
Carga dinámica de:  
- Zona  
- Instituto  
- Carrera  
Relaciones:  
Zona  
  → Instituto  
      → Carrera  
          → Espacio/Cargo  
   
## **3. Detalles del llamado**  
Cada llamado puede contener múltiples:  
- Espacios curriculares  
- Cargos  
Cada detalle incluye:  
- Horas cátedra  
- Año  
- Período  
- Turno  
- Perfil  
- Situación de revista  
- Horario  
## **4. Tabla de detalles agregados**  
La tabla previa muestra los detalles antes de publicar.  
Columnas:  
- Carrera  
- Tipo / Nombre  
- Hs / Año  
- Turno / Período  
- Perfil  
- Situación de Revista  
- Horario  
- Acciones  
# **Archivo: publico.blade.php**  
## **Función principal**  
Muestra únicamente los llamados:  
->where('nuevo_llamado.publicado', true)  
   
La vista pública reutiliza la misma lógica visual de:  
- Institutos  
- Carreras  
- Espacios  
- Perfiles  
- Fechas  
- Estados  
# **Consistencia visual entre tablas**  
## **Regla principal**  
La tabla de publico.blade.php debe verse igual que la tabla de historial de crear.blade.php.  
La única diferencia permitida:  
- publico.blade.php  
 NO debe incluir:  
- columna de acciones  
# **Diseño recomendado de columnas**  
Las columnas más importantes y con mayor contenido deben tener más ancho:  
| | | |  
|-|-|-|  
| **Columna** | **Prioridad** | **Motivo** |   
| Espacios / Cargos | Alta | Contiene el nombre principal |   
| Perfil | Alta | Contiene texto extenso |   
| Instituto | Media | Puede contener nombres largos |   
| Carreras | Media | Puede listar varias carreras |   
| Vigencia / Inscripción | Baja | Información corta |   
| ID / Zona | Baja | Información compacta |   
# **Configuración recomendada de anchos**  
## **Tabla administrativa**  
<colgroup>  
    <col class="w-[100px]">  
    <col class="w-[190px]">  
    <col class="w-[190px]">  
    <col class="w-[420px]">  
    <col class="w-[300px]">  
    <col class="w-[170px]">  
    <col class="w-[150px]">  
</colgroup>  
   
## **Tabla pública**  
Debe usar exactamente la misma proporción visual:  
<colgroup>  
    <col class="w-[100px]">  
    <col class="w-[190px]">  
    <col class="w-[190px]">  
    <col class="w-[420px]">  
    <col class="w-[300px]">  
    <col class="w-[170px]">  
</colgroup>  
   
# **Reglas visuales importantes**  
## **1. Table Layout**  
Usar:  
table-fixed  
   
para mantener anchos consistentes.  
## **2. Responsive**  
Contenedor recomendado:  
overflow-x-auto  
   
## **3. Texto largo**  
Las columnas:  
- Espacios / Cargos  
- Perfil  
deben permitir:  
break-words  
whitespace-normal  
   
## **4. Consistencia de estilos**  
Mantener iguales:  
- padding  
- tamaños de texto  
- bordes  
- colores  
- sombras  
- hover  
- badges  
- tipografías  
entre ambas vistas.  
# **Estructura de datos**  
## **Tabla: nuevo_llamado**  
Cabecera del llamado.  
Campos importantes:  
- idtb_zona  
- idtipo_llamado  
- fecha_ini  
- fecha_fin  
- publicado  
- descripcion  
- url_form  
## **Tabla: nuevo_espacios_por_llamado**  
Relaciona:  
- llamado  
- instituto  
- espacio curricular  
## **Tabla: nuevo_cargo_por_llamado**  
Relaciona:  
- llamado  
- instituto  
- cargo  
# **Lógica de publicación**  
## **Publicar llamado**  
Método:  
public function publicar($id)  
{  
    DB::table('nuevo_llamado')  
        ->where('id', $id)  
        ->update(['publicado' => true]);  
   
    return $this->redirect(  
        route('admin.llamados.publico'),  
        navigate: true  
    );  
}  
   
# **Cierre automático de llamados**  
Los llamados vencidos cambian automáticamente a:  
Cerrado  
   
mediante:  
wire:poll.30s="cerrarVencidos"  
   
# **Objetivo visual del sistema**  
El sistema prioriza:  
- lectura rápida  
- claridad académica  
- visualización de perfiles  
- diferenciación de cargos y espacios  
- diseño administrativo moderno  
- coherencia entre administración y vista pública  
# **Archivos complementarios**  
Otros archivos ayudan al funcionamiento general:  
- modelos  
- relaciones  
- migraciones  
- consultas DB  
- layouts  
- componentes Flux  
- rutas Livewire  
- controladores auxiliares  
Pero:  
## **Los archivos principales del flujo son:**  
crear.blade.php  
publico.blade.php  
   
# **Recomendación futura**  
Para evitar duplicar código:  
## **Crear componente reutilizable**  
Ejemplo:  
resources/views/components/tabla-llamados.blade.php  
   
y reutilizarlo en:  
- crear.blade.php  
- publico.blade.php  
Beneficios:  
- mismo diseño  
- mantenimiento simple  
- menos errores visuales  
- columnas sincronizadas  
- estilos unificados  
   
