@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header fw-bold">Recuperar contraseña</div>

                <div class="card-body">
                    @php
                        $step = (int) session('password_reset_step', 1);
                        $savedEmail = session('password_reset_email');
                    @endphp

                    @if (session('status'))
                        <div class="alert alert-success" role="alert">
                            {{ session('status') }}
                        </div>
                    @endif

                    @if ($step === 1)
                        <p class="text-muted mb-3">Paso 1 de 3: ingresa tu correo para enviar el código de verificación.</p>

                        <form method="POST" action="{{ route('password.email') }}">
                            @csrf

                            <div class="row mb-3">
                                <label for="email_request" class="col-md-4 col-form-label text-md-end">Correo electronico</label>

                                <div class="col-md-6">
                                    <input
                                        id="email_request"
                                        type="email"
                                        class="form-control @error('email') is-invalid @enderror"
                                        name="email"
                                        value="{{ old('email', $savedEmail) }}"
                                        required
                                        autocomplete="email"
                                        autofocus
                                        placeholder="correo@ejemplo.com"
                                    >

                                    @error('email')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>

                            <div class="row mb-0">
                                <div class="col-md-6 offset-md-4">
                                    <button type="submit" class="btn btn-primary">Enviar código</button>
                                </div>
                            </div>
                        </form>
                    @elseif ($step === 2)
                        <p class="text-muted mb-3">Paso 2 de 3: ingresa el código de verificación enviado a tu correo.</p>

                        <form method="POST" action="{{ route('password.verify-code') }}" class="mb-3">
                            @csrf

                            <div class="row mb-3">
                                <label for="email_verify" class="col-md-4 col-form-label text-md-end">Correo electronico</label>

                                <div class="col-md-6">
                                    <input
                                        id="email_verify"
                                        type="email"
                                        class="form-control @error('email') is-invalid @enderror"
                                        name="email"
                                        value="{{ old('email', $savedEmail) }}"
                                        required
                                        autocomplete="email"
                                    >

                                    @error('email')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>

                            <div class="row mb-3">
                                <label for="code" class="col-md-4 col-form-label text-md-end">Código de verificación</label>

                                <div class="col-md-6">
                                    <input
                                        id="code"
                                        type="text"
                                        class="form-control @error('code') is-invalid @enderror"
                                        name="code"
                                        value="{{ old('code') }}"
                                        required
                                        maxlength="6"
                                        inputmode="numeric"
                                        placeholder="123456"
                                    >

                                    @error('code')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>

                            <div class="row mb-0">
                                <div class="col-md-6 offset-md-4 d-flex gap-2">
                                    <button type="submit" class="btn btn-success">Verificar código</button>
                                </div>
                            </div>
                        </form>

                        <form method="POST" action="{{ route('password.email') }}">
                            @csrf
                            <input type="hidden" name="email" value="{{ $savedEmail }}">
                            <div class="row mb-0">
                                <div class="col-md-6 offset-md-4">
                                    <button type="submit" class="btn btn-outline-secondary btn-sm">Reenviar código</button>
                                </div>
                            </div>
                        </form>
                    @else
                        <p class="text-muted mb-3">Paso 3 de 3: ahora cambia tu contraseña.</p>

                        <form method="POST" action="{{ route('password.update') }}">
                            @csrf

                            <div class="row mb-3">
                                <label for="email_reset" class="col-md-4 col-form-label text-md-end">Correo electronico</label>

                                <div class="col-md-6">
                                    <input
                                        id="email_reset"
                                        type="email"
                                        class="form-control @error('email') is-invalid @enderror"
                                        name="email"
                                        value="{{ old('email', $savedEmail) }}"
                                        required
                                        autocomplete="email"
                                        readonly
                                    >

                                    @error('email')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>

                            <div class="row mb-3">
                                <label for="password" class="col-md-4 col-form-label text-md-end">Nueva contraseña</label>

                                <div class="col-md-6">
                                    <input
                                        id="password"
                                        type="password"
                                        class="form-control @error('password') is-invalid @enderror"
                                        name="password"
                                        required
                                        autocomplete="new-password"
                                    >

                                    @error('password')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>

                            <div class="row mb-3">
                                <label for="password-confirm" class="col-md-4 col-form-label text-md-end">Confirmar contraseña</label>

                                <div class="col-md-6">
                                    <input
                                        id="password-confirm"
                                        type="password"
                                        class="form-control"
                                        name="password_confirmation"
                                        required
                                        autocomplete="new-password"
                                    >
                                </div>
                            </div>

                            <div class="row mb-0">
                                <div class="col-md-6 offset-md-4">
                                    <button type="submit" class="btn btn-primary">Cambiar contraseña</button>
                                </div>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
