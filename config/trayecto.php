<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Nombre del Trayecto Formativo
    |--------------------------------------------------------------------------
    |
    | Título mostrado en el encabezado del formulario público y en la
    | constancia de inscripción.
    |
    */

    'nombre' => 'GESTIÓN ESCOLAR: entre la tradición y El Desafío de Innovar.',

    /*
    |--------------------------------------------------------------------------
    | Cohorte activa del Trayecto Formativo
    |--------------------------------------------------------------------------
    |
    | Determina en qué convocatoria (año) se inscribe el formulario público
    | y contra qué cohorte se evalúa la regla de 1 (o 2, si nivel = 'Primario')
    | inscripciones por DNI. Para abrir la convocatoria de un año nuevo alcanza
    | con cambiar este valor (vía .env) — la tabla tb_trayecto_formativo es
    | única y genérica, no se crea una tabla nueva por año.
    |
    */

    'cohorte_activa' => env('TRAYECTO_COHORTE_ACTIVA', 2025),

    /*
    |--------------------------------------------------------------------------
    | Niveles normalizados
    |--------------------------------------------------------------------------
    |
    | Mismos valores usados en tb_trayecto_formativo.nivel y en
    | tb_instituciones_trayecto.nivel, para que cualquier filtro/join entre
    | ambas tablas funcione sin traducir strings.
    |
    */

    'niveles' => ['Inicial', 'Primario', 'Secundario', 'Especial'],

    // Nivel que permite hasta 2 inscripciones por DNI dentro de la misma cohorte (el resto permite 1).
    'nivel_multiple' => 'Primario',
    'max_inscripciones_nivel_multiple' => 2,
    'max_inscripciones_default' => 1,

];
