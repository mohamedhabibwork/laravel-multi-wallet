<?php

namespace HWallet\LaravelMultiWallet\Tests\Models;

use HWallet\LaravelMultiWallet\Traits\HasWallets;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * @mixin \HWallet\LaravelMultiWallet\Traits\HasWallets
 */
class User extends Authenticatable
{
    use HasFactory, HasWallets;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \HWallet\LaravelMultiWallet\Database\Factories\UserFactory::new();
    }
}
