<?php

use Spatie\WebhookClient\WebhookProfile\WebhookProfile;

class OnlyProcessMemberChanges implements WebhookProfile
{
    public function shouldProcess(Request $request): bool {
        return substr($request->action, 0, 7) === 'member_';
    }
}
