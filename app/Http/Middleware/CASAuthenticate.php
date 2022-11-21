<?php

declare(strict_types=1);

// phpcs:disable SlevomatCodingStandard.ControlStructures.RequireSingleLineCondition.RequiredSingleLineCondition

namespace App\Http\Middleware;

use App\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RoboJackets\AuthStickler;

/**
 * Authenticates users against a CAS server (e.g. GT Login Service).
 */
class CASAuthenticate
{
    /**
     * The CAS manager.
     *
     * @var \Subfission\Cas\CasManager
     */
    protected $cas;

    public function __construct()
    {
        $this->cas = app('cas');
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        //Check to ensure the request isn't already authenticated through the API guard
        if (! Auth::guard('api')->check()) {
            if ($this->cas->isAuthenticated() && null === $request->user()) {
                $username = AuthStickler::check($this->cas);

                $user = User::where('uid', '=', $username)->first();

                if (null === $user) {
                    $user = new User();
                    $user->uid = $username;
                    $user->save();
                }

                Auth::login($user);

                return $next($request);
            }

            if (null === $request->user() && ($request->ajax() || $request->wantsJson())) {
                abort(401);
            }

            if (null === $request->user()) {
                $this->cas->authenticate();
            }
        }

        return $next($request);
    }
}
