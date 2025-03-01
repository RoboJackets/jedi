<?php

declare(strict_types=1);

namespace App\WebhookProfiles;

use Illuminate\Http\Request;
use Spatie\WebhookClient\WebhookProfile\WebhookProfile;

class OnlyProcessMemberChanges implements WebhookProfile
{
    #[\Override]
    public function shouldProcess(Request $request): bool
    {
        return substr($request->action, 0, 7) === 'member_';
    }
}
