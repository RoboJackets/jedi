<?php

declare(strict_types=1);

namespace App;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    /**
     *  Get the email events associated with this user
     */
    public function emailEvents(): HasMany
    {
        return $this->hasMany(EmailEvent::class, 'uid', 'uid');
    }
}
