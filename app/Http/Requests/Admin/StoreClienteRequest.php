<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->hasRole('Administrador');
    }

    protected function prepareForValidation(): void
    {
        $telefono = preg_replace('/\D+/', '', (string) $this->input('telefono', ''));

        $this->merge([
            'email' => strtolower(trim((string) $this->input('email', ''))),
            'telefono' => $telefono !== '' ? $telefono : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'nombres' => ['required', 'string', 'max:100'],
            'segundo_nombre' => ['nullable', 'string', 'max:100'],
            'apellidos' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:clientes,email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'telefono' => ['nullable', 'string', 'regex:/^(?:[67]\d{7}|[234]\d{6})$/'],
            'direccion' => ['nullable', 'string'],
            'empresa' => ['nullable', 'string', 'max:120'],
        ];
    }
}
