<?php

namespace App\Jobs;

use App\Models\LoyaltyCard;
use App\Services\AppleWalletService;
use App\Services\GoogleWalletService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateWalletPassJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $loyaltyCardId) {}

    public function handle(GoogleWalletService $google, AppleWalletService $apple): void
    {
        $card = LoyaltyCard::with('loyaltyProgram.business')->find($this->loyaltyCardId);

        if (! $card) {
            return;
        }

        // Calling updatePass() patches content['googleObjectPayload'] on the MobilePass model.
        // MobilePass::boot() listens to the `updated` event and dispatches PushPassUpdateJob
        // (from the spatie package) which calls patchObject on the Google Wallet API.
        if ($card->google_pass_id) {
            $google->updatePass($card);
        }

        if ($card->apple_pass_id) {
            $apple->updatePass($card);
        }
    }
}
