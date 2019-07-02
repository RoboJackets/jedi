#!/bin/bash

cd "${0%/*}"

composer install --no-interaction --no-progress --no-suggest --no-dev --optimize-autoloader --classmap-authoritative
php artisan migrate --no-interaction
php artisan config:cache --no-interaction
php artisan view:clear --no-interaction
php artisan route:clear --no-interaction
php artisan vendor:publish --provider="Laravel\Horizon\HorizonServiceProvider" --no-interaction
php artisan cache:clear --no-interaction
export PATH=$PATH:/bin
npm ci --no-progress
npm run production --no-progress
php artisan up
php artisan horizon:terminate
