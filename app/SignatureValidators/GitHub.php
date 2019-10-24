<?php declare(strict_types = 1);

namespace App\SignatureValidators;

use Exception;
use Illuminate\Http\Request;
use Spatie\WebhookClient\SignatureValidator\SignatureValidator;
use Spatie\WebhookClient\WebhookConfig;

class GitHub implements SignatureValidator
{
    public function isValid(Request $request, WebhookConfig $config): bool
    {
        [$algo, $hash] = explode('=', $request->header('X-Hub-Signature'));

        if ('sha1' !== $algo) {
            throw new Exception('Unexpected signature algorithm ' . $algo);
        }

        if (!is_string($config->signingSecret)) {
            throw new Exception('Expected signingSecret to be a string');
        }

        return $hash === hash_hmac($algo, $request->getContent(), $config->signingSecret);
    }
}
