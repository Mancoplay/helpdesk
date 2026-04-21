@extends('layouts.app')

@section('title', 'Iniciar sesión')

@section('content')
<div class="login-box" style="width: 420px;">
    <div class="card card-outline card-primary shadow">
        <div class="card-header text-center">
            <a href="{{ url('/') }}" class="h4 text-decoration-none"><b>Help</b>Desk</a>
        </div>
        <div class="card-body">
            <p class="login-box-msg">Inicia sesión para continuar</p>

            <form id="loginForm" method="POST" action="{{ route('login') }}">
                @csrf

                <div class="input-group mb-3">
                    <input
                        id="email"
                        type="email"
                        class="form-control @error('email') is-invalid @enderror"
                        name="email"
                        value="{{ old('email') }}"
                        placeholder="Correo"
                        required
                        autocomplete="email"
                        autofocus
                    >
                    <div class="input-group-text"><span class="fas fa-envelope"></span></div>
                    @error('email')
                        <span class="invalid-feedback d-block" role="alert"><strong>{{ $message }}</strong></span>
                    @enderror
                    @if (session('disabled_account_error'))
                        <span id="disabledAccountError" class="invalid-feedback d-block" role="alert"><strong>{{ session('disabled_account_error') }}</strong></span>
                    @endif
                </div>

                <div class="input-group mb-3">
                    <input
                        id="password"
                        type="password"
                        class="form-control @error('password') is-invalid @enderror"
                        name="password"
                        placeholder="Contraseña"
                        required
                        autocomplete="current-password"
                    >
                    <div class="input-group-text"><span class="fas fa-lock"></span></div>
                    @error('password')
                        <span class="invalid-feedback d-block" role="alert"><strong>{{ $message }}</strong></span>
                    @enderror
                </div>

                <div class="row">
                    <div class="col-7">
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                            <label class="form-check-label" for="remember">Recordarme</label>
                        </div>
                    </div>
                    <div class="col-5">
                        <button type="submit" class="btn btn-primary w-100">Entrar</button>
                    </div>
                </div>

                @if (Route::has('password.request'))
                    <p class="mb-1 mt-2">
                        <a href="{{ route('password.request') }}">¿Olvidaste tu contraseña?</a>
                    </p>
                @endif
            </form>
        </div>
    </div>
</div>
@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const disabledError = document.getElementById('disabledAccountError');
        const loginForm = document.getElementById('loginForm');

        if (!disabledError || !loginForm) {
            return;
        }

        const clearDisabledError = function () {
            disabledError.remove();
        };

        loginForm.addEventListener('submit', clearDisabledError, true);

        const emailInput = document.getElementById('email');
        const passwordInput = document.getElementById('password');

        [emailInput, passwordInput].forEach(function (input) {
            if (!input) {
                return;
            }

            input.addEventListener('input', clearDisabledError);
            input.addEventListener('change', clearDisabledError);
        });
    });
</script>
@endpush
@endsection


