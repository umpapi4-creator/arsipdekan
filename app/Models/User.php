<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'username', 'password', 'role', 'prodi_key'];
    protected $hidden = ['password', 'remember_token'];
    protected $casts = ['password' => 'hashed'];

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function prodi(): ?array
    {
        return $this->prodi_key ? config('prodi.items.'.$this->prodi_key) : null;
    }
}
