<?php

declare(strict_types=1);

// phpcs:disable SlevomatCodingStandard.ControlStructures.RequireSingleLineCondition.RequiredSingleLineCondition

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
            if ($this->cas->isAuthenticated() && $request->user() === null) {
                $username = $this->cas->user();

                $user = User::where('uid', '=', $username)->first();

                if ($user === null) {
                    $user = new User();
                    $user->uid = $username;
                    $user->save();
                }

                Auth::login($user);

                return $next($request);
            }

            if ($request->user() === null && ($request->ajax() || $request->wantsJson())) {
                abort(401);
            }

            if ($request->user() === null) {
                $this->cas->authenticate();
            }
        }

        return $next($request);
    }
}
