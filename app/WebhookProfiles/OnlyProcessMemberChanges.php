<?php declare(strict_types = 1);

namespace App\WebhookProfiles;

use Illuminate\Http\Request;
use Spatie\WebhookClient\WebhookProfile\WebhookProfile;

class OnlyProcessMemberChanges implements WebhookProfile
{
    public function shouldProcess(Request $request): bool
    {
        return 'member_' === substr($request->action, 0, 7);
    }
}
