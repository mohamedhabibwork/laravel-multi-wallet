<?php

namespace HWallet\LaravelMultiWallet\Listeners;

use HWallet\LaravelMultiWallet\Events\SuspiciousActivityDetected;
use HWallet\LaravelMultiWallet\Events\TransferCompleted;
use HWallet\LaravelMultiWallet\Events\WalletBalanceChanged;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendWebhookNotification implements ShouldQueue
{
    public function handleWalletBalanceChanged(WalletBalanceChanged $event): void
    {
        // Only send webhook for significant balance changes
        if (abs($event->change) >= config('multi-wallet.webhook_threshold', 1000)) {
            $this->sendWebhook('wallet.balance_changed', [
                'wallet_id' => $event->wallet->id,
                'balance_type' => $event->balanceType,
                'change' => $event->change,
                'new_balance' => $event->newBalance,
                'reason' => $event->reason,
            ]);
        }
    }

    public function handleTransferCompleted(TransferCompleted $event): void
    {
        $this->sendWebhook('transfer.completed', [
            'transfer_id' => $event->transfer->id,
            'from_type' => $event->transfer->from_type,
            'from_id' => $event->transfer->from_id,
            'to_type' => $event->transfer->to_type,
            'to_id' => $event->transfer->to_id,
            'amount' => $event->transfer->deposit->amount ?? 0,
            'fee' => $event->transfer->fee,
        ]);
    }

    public function handleSuspiciousActivityDetected(SuspiciousActivityDetected $event): void
    {
        $this->sendWebhook('security.suspicious_activity', [
            'wallet_id' => $event->wallet->id,
            'activity_type' => $event->activityType,
            'risk_score' => $event->riskScore,
            'details' => $event->details,
        ]);
    }

    private function sendWebhook(string $event, array $data): void
    {
        $webhookUrl = config('multi-wallet.webhook_url');

        if (! $webhookUrl) {
            return;
        }

        try {
            $response = Http::timeout(10)->post($webhookUrl, [
                'event' => $event,
                'timestamp' => now()->toISOString(),
                'data' => $data,
            ]);

            if (! $response->successful()) {
                Log::error('Webhook failed', [
                    'event' => $event,
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Webhook exception', [
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
