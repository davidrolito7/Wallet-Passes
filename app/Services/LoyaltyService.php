<?php

namespace App\Services;

use App\Models\LoyaltyCard;
use App\Models\LoyaltyMilestone;
use App\Models\LoyaltyProgram;
use App\Models\MilestoneRedemption;
use App\Models\RewardRedemption;
use App\Models\StampTransaction;

class LoyaltyService
{
    public function __construct(
        private GoogleWalletService $google,
        private AppleWalletService $apple,
    ) {}

    public function createCard(LoyaltyProgram $program, string $holderName, ?string $holderEmail = null, ?string $holderIdentifier = null): LoyaltyCard
    {
        $card = LoyaltyCard::create([
            'loyalty_program_id' => $program->id,
            'holder_name'        => $holderName,
            'holder_email'       => $holderEmail,
            'holder_identifier'  => $holderIdentifier,
            'stamps_collected'   => 0,
        ]);

        // Eager-load relations needed by wallet services (business color, icon, etc.)
        $card->load('loyaltyProgram.business', 'loyaltyProgram.milestones');

        $googlePass = $this->google->createPass($card);
        $card->update(['google_pass_id' => $googlePass->id]);

        if ($this->apple->isConfigured()) {
            $applePass = $this->apple->createPass($card);
            if ($applePass) {
                $card->update(['apple_pass_id' => $applePass->id]);
            }
        }

        return $card->fresh();
    }

    /**
     * Add stamps to a card, mark milestones reached, and push wallet update.
     *
     * @return array{card: LoyaltyCard, milestones: \Illuminate\Support\Collection<int, LoyaltyMilestone>}
     */
    public function addStamp(LoyaltyCard $card, int $count = 1, ?string $note = null, ?string $recordedBy = null): array
    {
        $program = $card->loyaltyProgram;
        $previousTotal = $card->stamps_collected;
        $newTotal = min($previousTotal + $count, $program->total_stamps);

        $card->update([
            'stamps_collected' => $newTotal,
            'last_stamp_at'    => now(),
        ]);

        StampTransaction::create([
            'loyalty_card_id' => $card->id,
            'stamps_added'    => $count,
            'stamps_after'    => $newTotal,
            'note'            => $note,
            'recorded_by'     => $recordedBy,
        ]);

        // Mark card as completed when total stamps reached
        if ($newTotal >= $program->total_stamps && ! $card->is_completed) {
            $card->update([
                'is_completed' => true,
                'completed_at' => now(),
            ]);
        }

        // Check which milestones were crossed in this stamp range
        $triggeredMilestones = $this->triggerMilestones($card, $previousTotal, $newTotal, $recordedBy);

        // Push wallet update synchronously — MobilePass::boot() fires PushPassUpdateJob
        // (spatie package) which calls patchObject on the Google Wallet API immediately
        // when MOBILE_PASS_QUEUE_CONNECTION is null.
        $this->pushWalletUpdate($card->fresh());

        return [
            'card'       => $card->fresh(),
            'milestones' => $triggeredMilestones,
        ];
    }

    /**
     * Redeem the final program reward (resets the card for a new cycle).
     */
    public function redeemReward(LoyaltyCard $card, ?string $redeemedBy = null): RewardRedemption
    {
        $program = $card->loyaltyProgram;

        $redemption = RewardRedemption::create([
            'loyalty_card_id' => $card->id,
            'reward_title'    => $program->reward_title,
            'redeemed_by'     => $redeemedBy,
            'redeemed_at'     => now(),
        ]);

        // Reset card for the next cycle
        $card->update([
            'stamps_collected' => 0,
            'is_completed'     => false,
            'completed_at'     => null,
        ]);

        $this->pushWalletUpdate($card->fresh());

        return $redemption;
    }

    /**
     * Redeem a specific intermediate milestone reward.
     */
    public function redeemMilestone(LoyaltyCard $card, LoyaltyMilestone $milestone, ?string $redeemedBy = null): MilestoneRedemption
    {
        return MilestoneRedemption::create([
            'loyalty_card_id'     => $card->id,
            'loyalty_milestone_id' => $milestone->id,
            'redeemed_by'         => $redeemedBy,
            'triggered_at'        => now(),
        ]);
    }

    private function pushWalletUpdate(LoyaltyCard $card): void
    {
        if ($card->google_pass_id) {
            $this->google->updatePass($card);
        }

        if ($card->apple_pass_id) {
            $this->apple->updatePass($card);
        }
    }

    /**
     * Find all milestones crossed between previousTotal and newTotal and record them.
     *
     * @return \Illuminate\Support\Collection<int, LoyaltyMilestone>
     */
    private function triggerMilestones(LoyaltyCard $card, int $previousTotal, int $newTotal, ?string $recordedBy): \Illuminate\Support\Collection
    {
        $milestones = $card->loyaltyProgram->milestones()
            ->whereBetween('stamp_count', [$previousTotal + 1, $newTotal])
            ->get();

        $triggered = collect();

        foreach ($milestones as $milestone) {
            if ($milestone->wasAlreadyTriggeredFor($card)) {
                continue;
            }

            MilestoneRedemption::create([
                'loyalty_card_id'      => $card->id,
                'loyalty_milestone_id' => $milestone->id,
                'redeemed_by'          => $recordedBy,
                'triggered_at'         => now(),
            ]);

            $triggered->push($milestone);
        }

        return $triggered;
    }
}
