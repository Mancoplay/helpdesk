<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmpleadoRequest extends FormRequest
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
        $empleado = $this->route('empleado');
        $linkedUser = $empleado ? User::find($empleado->id) : null;

        return [
            'nombres' => ['required', 'string', 'max:100'],
            'apellidos' => ['required', 'string', 'max:100'],
            'email' => [
                'required',
                'email:rfc',
                'max:255',
                Rule::unique('users', 'email')->ignore($linkedUser?->id),
            ],
            'telefono' => ['nullable', 'string', 'regex:/^(?:[67]\d{7}|[234]\d{6})$/'],
            'direccion' => ['nullable', 'string'],
            'cargo' => ['nullable', 'string', 'max:100'],
            'departamento_id' => ['nullable', Rule::exists('departamentos', 'id')->where(fn ($query) => $query->where('activo', true))],
            'departamento_ids' => ['nullable', 'array'],
            'departamento_ids.*' => ['integer', Rule::exists('departamentos', 'id')->where(fn ($query) => $query->where('activo', true))],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ];
    }
}
