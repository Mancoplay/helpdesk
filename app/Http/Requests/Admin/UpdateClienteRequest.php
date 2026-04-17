<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->hasRole('Administrador');
    }

    protected function prepareForValidation(): void
    {
        $telefono = preg_replace('/\D+/', '', (string) $this->input('telefono', ''));

        $this->merge([
            'email' => trim((string) $this->input('email', '')),
            'telefono' => $telefono !== '' ? $telefono : null,
        ]);
    }

    public function rules(): array
    {
        $cliente = $this->route('cliente');
        $linkedUser = $cliente ? User::where('email', $cliente->email)->first() : null;

        return [
            'nombres' => ['required', 'string', 'max:100'],
            'segundo_nombre' => ['nullable', 'string', 'max:100'],
            'apellidos' => ['required', 'string', 'max:100'],
            'email' => [
                'required',
                'email:rfc',
                'max:255',
                Rule::unique('clientes', 'email')->ignore($cliente?->id),
                Rule::unique('users', 'email')->ignore($linkedUser?->id),
            ],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'telefono' => ['nullable', 'string', 'regex:/^(?:[67]\d{7}|[234]\d{6})$/'],
            'direccion' => ['nullable', 'string'],
            'empresa' => ['nullable', 'string', 'max:120'],
        ];
    }
}
