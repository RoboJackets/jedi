#!/bin/bash

cd "${0%/*}"

composer install --no-interaction --no-progress --no-suggest --no-dev --optimize-autoloader --classmap-authoritative
php artisan migrate --no-interaction
php artisan config:cache --no-interaction
php artisan view:clear --no-interaction
php artisan route:clear --no-interaction
php artisan vendor:publish --provider="Laravel\Horizon\HorizonServiceProvider" --no-interaction
php artisan cache:clear --no-interaction
# Patch Horizon not cooperating with being at /
sed -i 's#base:"/"+window.Horizon.path+"/"#base:"/"#' public/vendor/horizon/app.js
sed -i 's#"/"+Horizon.path+##g' public/vendor/horizon/app.js
sed -i 's#"/"+t.Horizon.path+##g' public/vendor/horizon/app.js
php artisan up
php artisan horizon:terminate
php artisan bugsnag:deploy --repository "https://github.gatech.edu/RoboJackets/jedi" --revision $(git rev-parse HEAD) --provider "github-enterprise" --builder "rj-dc-00"
