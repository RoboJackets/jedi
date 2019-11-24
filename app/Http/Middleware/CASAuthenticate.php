<?php declare(strict_types = 1);

// phpcs:disable SlevomatCodingStandard.TypeHints.DisallowMixedTypeHint.DisallowedMixedTypeHint

namespace App\Http\Middleware;

use App\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RoboJackets\ErrorPages\BadNetwork;
use RoboJackets\ErrorPages\DuoNotEnabled;
use RoboJackets\ErrorPages\DuoOutage;
use RoboJackets\ErrorPages\EduroamISSDisabled;
use RoboJackets\ErrorPages\EduroamNonGatech;
use RoboJackets\ErrorPages\Unauthorized;
use RoboJackets\ErrorPages\UsernameContainsDomain;

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
            if ($this->cas->isAuthenticated() && null === $request->user()) {
                $username = strtolower($this->cas->user());

                if (false !== strpos($username, '@')) {
                    foreach (array_keys($_COOKIE) as $key) {
                        setcookie($key, '', time() - 3600);
                    }
                    UsernameContainsDomain::render($username);
                    exit;
                }

                if ('duo-two-factor' !== $this->cas->getAttribute('authn_method')) {
                    if (in_array(
                        '/gt/central/services/iam/two-factor/duo-user',
                        $this->cas->getAttribute('gtAccountEntitlement')
                    )
                    ) {
                        DuoOutage::render();
                        exit;
                    }
                    DuoNotEnabled::render();
                    exit;
                }

                $network = NetworkCheck::detect();
                if (NetworkCheck::EDUROAM_ISS_DISABLED === $network) {
                    EduroamISSDisabled::render();
                    exit;
                }
                if (NetworkCheck::GTOTHER === $network) {
                    BadNetwork::render('GTother', $username, phpCAS::getAttribute('eduPersonPrimaryAffiliation'));
                    exit;
                }
                if (NetworkCheck::GTVISITOR === $network) {
                    BadNetwork::render('GTvisitor', $username, phpCAS::getAttribute('eduPersonPrimaryAffiliation'));
                    exit;
                }
                if (NetworkCheck::EDUROAM_NON_GATECH_V4 === $network
                    || NetworkCheck::EDUROAM_NON_GATECH_V6 === $network
                ) {
                    EduroamNonGatech::render($username, phpCAS::getAttribute('eduPersonPrimaryAffiliation'));
                    exit;
                }

                $user = User::where('uid', '=', $username)->first();

                if (null === $user) {
                    $user = new User();
                    $user->uid = $username;
                    $user->save();
                }

                Auth::login($user);
                return $next($request);
            }

            if ($request->ajax() || $request->wantsJson()) {
                abort(401);
            }

            $this->cas->authenticate();
        }

        return $next($request);
    }
}
