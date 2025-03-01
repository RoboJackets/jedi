<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CasAuthenticate
{
    /**
     * Auth facade.
     *
     * @var \Illuminate\Contracts\Auth\Guard
     *
     * @phan-read-only
     */
    protected $auth;

    /**
     * CAS library interface.
     *
     * @var \Subfission\Cas\CasManager
     *
     * @phan-read-only
     */
    protected $cas;

    public function __construct(Guard $auth)
    {
        $this->auth = $auth;
        // @phan-suppress-next-line PhanUndeclaredClassReference
        $this->cas = app('cas');
    }

    /**
     * Handle an incoming request.
     *
     * @phan-suppress PhanTypeMismatchReturn
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Run the user update only if they don't have an active session
        if ($this->cas->isAuthenticated() && $request->user() === null) {
            $user = User::where('username', $this->cas->user())->first();
            if ($user === null) {
                $user = new User();
            }
            $user->username = $this->cas->user();
            $user->save();

            Auth::login($user);
        }

        if ($this->cas->isAuthenticated() && $request->user() !== null) {
            // User is authenticated and already has an existing session
            return $next($request);
        }

        // User is not authenticated and does not have an existing session
        if ($request->ajax() || $request->wantsJson()) {
            return response('Unauthorized', 401);
        }

        return $this->cas->authenticate();
    }
}
