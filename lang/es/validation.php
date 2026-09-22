<?php

return [

    'required' => 'El campo :attribute es obligatorio.',
    'string' => 'El campo :attribute debe ser una cadena de texto.',
    'max' => [
        'string' => 'El campo :attribute no puede tener más de :max caracteres.',
    ],
    'unique' => 'El :attribute ya está registrado.',
    'boolean' => 'El campo :attribute debe ser verdadero o falso.',

    'attributes' => [
        'nombre' => 'nombre',
        'descripcion' => 'descripción',
        'activo' => 'estado',
        'username' => 'usuario',
        'password' => 'contraseña',
        'empresa_id' => 'empresa',
        'categoria_id' => 'categoría',
        'marca_id' => 'marca',
        'unidad_medida' => 'unidad de medida',
    ],

];