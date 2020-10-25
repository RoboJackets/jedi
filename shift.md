This pull request includes the changes for upgrading to Laravel 6.x. Feel free to commit any additional changes to the `master` branch.

**Before merging**, you need to:

- Checkout the `master` branch
- Review **all** pull request comments for additional changes
- Update your dependencies for Laravel 6
- Run `composer update` (if the scripts fail, add `--no-scripts`)
- Thoroughly test your application ([no tests?](https://confidentlaravel.com))

If you need help with your upgrade, check out the [Human Shifts](https://laravelshift.com/human-shifts). You may also join the [Shifty Coders](https://laravelshift.com/shifty-coders) Slack workspace to level-up your Laravel skills.

:x: Shift could not upgrade the following files since they differed from the default Laravel version. You will need to compare these files against the [default Laravel 6 versions](https://github.com/laravel-shift/laravel-6.0/) and merge any changes:

- [ ] app/Http/Kernel.php
- [ ] bootstrap/app.php
- [ ] resources/lang/en/passwords.php
- [ ] resources/lang/en/validation.php

:warning: Shift upgraded your configuration files by defaulting them and merging your _true customizations_. These include values which are not changeable through core `ENV` variables. This should make [maintaining your config files easier](https://jasonmccreary.me/articles/maintaining-laravel-config-files/).

You should [review this commit]({#commit:30a34d8035008bfdc7e61337c21d03a7037073e6}) for any additional customizations. If you have a lot of customizations, you may wish to undo this commit with `git revert` and make these [config file changes](https://github.com/laravel-shift/laravel-6.0/pull/7/commits/e3379a9dcc2b08adf5934a99148366838371bc68) manually.

:information_source: Laravel 6 changed the default Redis client from `predis` to `phpredis`. You may keep using `predis` by setting `REDIS_CLIENT=predis` for your environment.

However, if possible, consider switching to [phpredis](https://github.com/phpredis/phpredis) to gain the performance of its PHP extension and avoid using the deprecated `predis` dependency which will be removed in Laravel 7.0.

:information_source: Shift [updated your dependencies]({#commit:f37f0d5ac3800856cf7cc1d88d9a3e017ca2e29b}) for Laravel 6. While many of the popular packages are reviewed, you may have to update additional packages in order for your application to be compatible with Laravel 6.

Watch [dealing with dependencies](https://laravelshift.com/videos/update-incompatible-composer-dependencies) for tips on handling package incompatibilities.

:information_source: Laravel 6 now requires Carbon 2. While Shift reviewed your application for common breaking changes, you may want to review the [Carbon 2 migration notes](https://carbon.nesbot.com/docs/#api-carbon-2) for additional changes.

:information_source: Laravel 6 made [performance optimizations](https://github.com/laravel/framework/pull/28153) for _integer_ key types. If you are using a _string_ as your model's primary key, you may set the `$keyType` property on your model.

```php
/**
 * The "type" of the primary key ID.
 *
 * @var string
 */
protected $keyType = 'string';
```

:information_source: The `mandrill` and `sparkpost` mail drivers, as well as the `rackspace` storage driver were removed in Laravel 6. If you were using these drivers, you may adopt a community maintained package which provides the driver.

:information_source: Previous versions of Laravel would retry jobs indefinitely. Beginning with Laravel 6, the `php artisan queue:work` now tries a job one time by default. If you want to force jobs to be tried indefinitely, you may pass the `--tries=0` option.

:warning: Shift detected you are using a Laravel package like Horizon or Nova which may need to have its published assets regenerated after upgrading. Be sure to use `artisan` to _republish_ these assets as well as `php artisan view:clear` to avoid any errors.