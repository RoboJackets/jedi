<?php

declare(strict_types=1);

namespace App;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    /**
     * The accessors to append to the model's array form.
     *
     * @var array<string>
     */
    protected $appends = [
        'name',
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
     * The attributes that should be mutated to dates.
     *
     * @var array<string>
     */
    protected $dates = [
        'created_at',
        'updated_at',
    ];

    /**
     *  Get the email events associated with this user
     */
    public function emailEvents(): HasMany
    {
        return $this->hasMany(EmailEvent::class, 'uid', 'uid');
    }

    /**
     * Get the is_active flag for the User.
     */
    public function getNameAttribute(): string
    {
        return $this->uid;
    }
}
