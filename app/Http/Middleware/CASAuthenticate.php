<?php declare(strict_types = 1);

// phpcs:disable SlevomatCodingStandard.TypeHints.DisallowMixedTypeHint.DisallowedMixedTypeHint

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use App\User;
use Illuminate\Http\Request;

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
                $user = User::where('uid', $this->cas->user())->first();
                if (is_a($user, \App\User::class)) {
                    Auth::login($user);
                } elseif (is_a($user, 'Illuminate\Http\Response')) {
                    return $user;
                }

                return response('Unauthorized.', 401);
            }

            if ($request->ajax() || $request->wantsJson()) {
                return response('Unauthorized', 401);
            }
            $this->cas->authenticate();
        }
        //User is authenticated, no update needed or already updated
        return $next($request);
    }
}
