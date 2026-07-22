<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'phone', 'role', 'position',
        'employment_date', 'salary', 'address',
        'emergency_contact_name', 'emergency_contact_phone', 'status', 'branch_id',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'employment_date' => 'date',
            'salary' => 'decimal:2',
            'role' => 'array',
        ];
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->role ?? []);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function isBranchManager(): bool
    {
        return $this->hasRole('branch_manager');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
