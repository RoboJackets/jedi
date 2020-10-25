<?php

declare(strict_types=1);

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailEvent extends Model
{
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
     *  Get the user associated with the event (if any)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uid', 'uid');
    }
}
