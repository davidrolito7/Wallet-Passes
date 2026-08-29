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

    public function generateApplePass(LoyaltyCard $card): bool
    {
        if (! $this->apple->isConfigured()) {
            return false;
        }

        $card->loadMissing('loyaltyProgram.business', 'loyaltyProgram.milestones');

        if ($card->apple_pass_id) {
            $this->apple->updatePass($card);
            return true;
        }

        $applePass = $this->apple->createPass($card);

        if ($applePass) {
            $card->update(['apple_pass_id' => $applePass->id]);
            return true;
        }

        return false;
    }

    public function createCard(LoyaltyProgram $program, string $firstName, string $lastName = '', ?string $birthDate = null, ?string $holderIdentifier = null): LoyaltyCard
    {
        $firstPrizeSystem = $program->prizeSystems()->first();

        $card = LoyaltyCard::create([
            'loyalty_program_id'      => $program->id,
            'current_prize_system_id' => $firstPrizeSystem?->id,
            'first_name'              => $firstName,
            'last_name'               => $lastName,
            'birth_date'              => $birthDate,
            'holder_identifier'       => $holderIdentifier,
            'stamps_collected'        => 0,
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

        $this->pushWalletUpdate($card->fresh(), sendVisitMessage: true);

        return [
            'card'       => $card->fresh(),
            'milestones' => $triggeredMilestones,
        ];
    }

    /**
     * Redeem the final program reward. Advances to the next prize system,
     * cycling back to the first when all systems have been completed.
     *
     * $restampCount lets the same action leave the new cycle already started (e.g. a QR
     * rescan of a completed card counts as both the redemption AND that visit's stamp) —
     * one DB write, one wallet push, instead of redeeming and then stamping separately.
     */
    public function redeemReward(LoyaltyCard $card, ?string $redeemedBy = null, int $restampCount = 0): RewardRedemption
    {
        $program       = $card->loyaltyProgram;
        $currentSystem = $card->resolvedPrizeSystem();

        $redemption = RewardRedemption::create([
            'loyalty_card_id' => $card->id,
            'reward_title'    => $currentSystem?->reward_title ?? $program->reward_title,
            'redeemed_by'     => $redeemedBy,
            'redeemed_at'     => now(),
        ]);

        // Advance to the next prize system; wrap back to the first when exhausted
        $nextSystem = $currentSystem
            ? $program->prizeSystems()
                ->where('sort_order', '>', $currentSystem->sort_order)
                ->orderBy('sort_order')
                ->first()
            : null;

        if (! $nextSystem) {
            $nextSystem = $program->prizeSystems()->orderBy('sort_order')->first();
        }

        $newStamps = min(max($restampCount, 0), $program->total_stamps);

        $card->update([
            'stamps_collected'        => $newStamps,
            'is_completed'            => $newStamps >= $program->total_stamps,
            'completed_at'            => $newStamps >= $program->total_stamps ? now() : null,
            'current_prize_system_id' => $nextSystem?->id,
            'last_stamp_at'           => $newStamps > 0 ? now() : $card->last_stamp_at,
        ]);

        if ($newStamps > 0) {
            StampTransaction::create([
                'loyalty_card_id' => $card->id,
                'stamps_added'    => $newStamps,
                'stamps_after'    => $newStamps,
                'note'            => 'Nuevo ciclo tras canje de premio',
                'recorded_by'     => $redeemedBy,
            ]);
        }

        $this->pushWalletUpdate($card->fresh(), sendVisitMessage: $newStamps > 0);

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

    /**
     * Send a one-off message to all wallet passes the card has (Google + Apple).
     *
     * Google Wallet: uses addmessage endpoint → push notification on Android.
     * Apple Wallet:  updates the next_reward field with changeMessage → notification on iPhone.
     *                If $notify is false, Apple is skipped (no silent update available).
     */
    /**
     * Send a one-off message to all wallet passes (Google Wallet + Apple Wallet).
     *
     * Google: addMessage endpoint → notificación push en Android.
     * Apple:  actualiza el campo back 'wallet_msg' con changeMessage '%@' → push en iPhone.
     *         Si $notify es false, Apple se omite (no hay actualización silenciosa disponible).
     */
    public function sendMessage(LoyaltyCard $card, string $message, bool $notify = true): void
    {
        if ($card->google_pass_id) {
            $this->google->sendMessage($card, $message, $notify);
        }

        if ($card->apple_pass_id) {
            $this->apple->sendMessage($card, $message, $notify);
        }
    }

    private function pushWalletUpdate(LoyaltyCard $card, bool $notify = true, bool $sendVisitMessage = false): void
    {
        if ($card->google_pass_id) {
            $this->google->updatePass($card, notify: $notify, sendVisitMessage: $sendVisitMessage);
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
        $prizeSystem = $card->resolvedPrizeSystem();

        if (! $prizeSystem) {
            return collect();
        }

        $milestones = $prizeSystem->milestones()
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
