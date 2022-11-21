<?php

declare(strict_types=1);

namespace App\SignatureValidators;

use Exception;
use Illuminate\Http\Request;
use Spatie\WebhookClient\SignatureValidator\SignatureValidator;
use Spatie\WebhookClient\WebhookConfig;

class GitHub implements SignatureValidator
{
    /**
     * Verifies a signature on a request from GitHub.
     */
    public function isValid(Request $request, WebhookConfig $config): bool
    {
        $header = $request->header('X-Hub-Signature');
        $secret = $config->signingSecret;
        $payload = $request->getContent();

        if (! is_string($header)) {
            throw new Exception('Header is not a string, possibly missing');
        }

        if (! is_string($payload)) {
            throw new Exception('Payload is not a string');
        }

        [$algo, $hash] = explode('=', $header);

        if ('sha1' !== $algo) {
            throw new Exception('Unexpected signature algorithm '.$algo);
        }

        return $hash === hash_hmac($algo, $payload, $secret);
    }
}
