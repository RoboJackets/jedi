<?php declare(strict_types = 1);

namespace App\SignatureValidators;

use Illuminate\Http\Request;
use Spatie\WebhookClient\SignatureValidator\SignatureValidator;
use Spatie\WebhookClient\WebhookConfig;

class GitHub implements SignatureValidator
{
    public function isValid(Request $request, WebhookConfig $config): bool
    {
        [$algo, $hash] = explode($request->header('X-Hub-Signature'));

        return $hash === hash_hmac($algo, $request->getContent(), $config->signingSecret);
    }
}
