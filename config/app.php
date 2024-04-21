<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Facade;

return [

    'aliases' => Facade::defaultAliases()->merge([
        'Cas' => Subfission\Cas\Facades\Cas::class,
        'Redis' => Illuminate\Support\Facades\Redis::class,
    ])->toArray(),

];
