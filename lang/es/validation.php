<?php

return [
    'required' => 'El campo :attribute es obligatorio.',
    'email' => 'El campo :attribute debe ser una direccion de correo valida.',
    'string' => 'El campo :attribute debe ser una cadena de texto.',
    'min' => [
        'string' => 'El campo :attribute debe tener al menos :min caracteres.',
    ],
    'max' => [
        'string' => 'El campo :attribute no debe superar :max caracteres.',
    ],

    'attributes' => [
        'email' => 'correo',
        'password' => 'contrasena',
    ],
];