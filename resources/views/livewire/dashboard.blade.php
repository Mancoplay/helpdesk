<?php

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;

new #[Title('Dashboard - Help Desk')] class extends Component {
    
    public function render()
    {
        return view('livewire.dashboard');
    }


?>

<div>
    @section('header', 'Dashboard')
    
    <div class="row">
        <div class="col-lg-3 col-6">
            <div class="small-box bg-info">
                <div class="inner">
                    <h3>{{ Auth::user()->roles->first()->name ?? 'Sin rol' }}</h3>
                    <p>Tu Rol Actual</p>
                </div>
                <div class="icon">
                    <i class="fas fa-user-tag"></i>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-6">
            <div class="small-box bg-success">
                <div class="inner">
                    <h3>{{ Auth::user()->name }}</h3>
                    <p>Usuario</p>
                </div>
                <div class="icon">
                    <i class="fas fa-user"></i>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-6">
            <div class="small-box bg-warning">
                <div class="inner">
                    <h3>{{ App\Models\User::count() }}</h3>
                    <p>Total Usuarios</p>
                </div>
                <div class="icon">
                    <i class="fas fa-users"></i>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-6">
            <div class="small-box bg-danger">
                <div class="inner">
                    <h3>{{ Spatie\Permission\Models\Role::count() }}</h3>
                    <p>Roles</p>
                </div>
                <div class="icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card mt-4">
        <div class="card-header">
            <h3 class="card-title">Información de Usuario</h3>
        </div>
        <div class="card-body">
            <table class="table table-bordered">
                <tr>
                    <th>Nombre</th>
                    <td>{{ Auth::user()->name }}</td>
                </tr>
                <tr>
                    <th>Email</th>
                    <td>{{ Auth::user()->email }}</td>
                </tr>
                <tr>
                    <th>Rol</th>
                    <td>
                        @foreach(Auth::user()->roles as $role)
                            <span class="badge bg-primary">{{ $role->name }}</span>
                        @endforeach
                    </td>
                </tr>
                <tr>
                    <th>Permisos</th>
                    <td>
                        @foreach(Auth::user()->getAllPermissions() as $permission)
                            <span class="badge bg-success">{{ $permission->name }}</span>
                        @endforeach
                    </td>
                </tr>
            </table>
        </div>
    </div>
</div>