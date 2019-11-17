<?php declare(strict_types = 1);

// phpcs:disable SlevomatCodingStandard.TypeHints.DisallowMixedTypeHint.DisallowedMixedTypeHint

namespace App\Http\Middleware;

use App\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CASAuthenticate
{
    /**
     * The CAS manager
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
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        //Check to ensure the request isn't already authenticated through the API guard
        if (! Auth::guard('api')->check()) {
            if ($this->cas->isAuthenticated()) {
                $user = User::where('uid', '=', $this->cas->user())->first();
                if (is_a($user, \App\User::class)) {
                    Auth::login($user);
                    return $next($request);
                }

                abort(401);
            }

            if ($request->ajax() || $request->wantsJson()) {
                abort(401);
            }
            $this->cas->authenticate();
        }

        return $next($request);
    }
}
