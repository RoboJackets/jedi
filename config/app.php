<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Facade;

return [

    'providers' => ServiceProvider::defaultProviders()->merge([
        /*
         * Laravel Framework Service Providers...
         */

        /*
         * Package Service Providers...
         */
        Subfission\Cas\CasServiceProvider::class,

        /*
         * Application Service Providers...
         */
        Illuminate\Foundation\Support\Providers\AuthServiceProvider::class,
        Illuminate\Foundation\Support\Providers\EventServiceProvider::class,
        App\Providers\HorizonServiceProvider::class,
        App\Providers\RouteServiceProvider::class,
    ])->toArray(),

    'aliases' => Facade::defaultAliases()->merge([
        'Cas' => Subfission\Cas\Facades\Cas::class,
        'Redis' => Illuminate\Support\Facades\Redis::class,
    ])->toArray(),

];
