<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /**
     * Users can have Sanctum API tokens.
     *
     * @use \Laravel\Sanctum\HasApiTokens<\Laravel\Sanctum\PersonalAccessToken>
     */
    use HasApiTokens;

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<string>
     */
    protected $appends = [
        'name',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'username',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string,string>
     */
    protected $casts = [
        'admin' => 'boolean',
    ];

    /**
     * Get the is_active flag for the User.
     */
    public function getNameAttribute(): string
    {
        return $this->username;
    }
}
