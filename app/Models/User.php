<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;

    #[Fillable(['name', 'email', 'password', 'role', 'phone'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function deliveries()
    {
        return $this->hasMany(Delivery::class);
    }

    public function isManager(): bool
    {
        return in_array($this->role, ['manager', 'admin'], true);
    }

    public function isAdmin(): bool
    {
        return $this->isManager();
    }

    public function isStaff(): bool
    {
        return in_array($this->role, ['manager', 'staff', 'admin'], true);
    }

    public function isEmployee(): bool
    {
        return in_array($this->role, ['staff', 'employee'], true);
    }

    public function isCustomer(): bool
    {
        return $this->role === 'customer';
    }
}
