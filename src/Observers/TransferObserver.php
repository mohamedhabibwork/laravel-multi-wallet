<?php

namespace HWallet\LaravelMultiWallet\Observers;

use HWallet\LaravelMultiWallet\Enums\TransferStatus;
use HWallet\LaravelMultiWallet\Events\TransferCompleted;
use HWallet\LaravelMultiWallet\Events\TransferFailed;
use HWallet\LaravelMultiWallet\Events\TransferPending;
use HWallet\LaravelMultiWallet\Models\Transfer;
use Illuminate\Support\Facades\Log;

class TransferObserver
{
    /**
     * Handle the Transfer "created" event.
     */
    public function created(Transfer $transfer): void
    {
        // Fire transfer pending event for new transfers
        if ($transfer->status === TransferStatus::PENDING) {
            event(new TransferPending($transfer));
        }

        // Log transfer creation if audit logging is enabled
        if (config('multi-wallet.audit_logging_enabled', true)) {
            Log::info('Transfer created', [
                'transfer_id' => $transfer->id,
                'from_type' => $transfer->from_type,
                'from_id' => $transfer->from_id,
                'to_type' => $transfer->to_type,
                'to_id' => $transfer->to_id,
                'status' => $transfer->status,
                'fee' => $transfer->fee,
                'discount' => $transfer->discount,
                'uuid' => $transfer->uuid,
            ]);
        }
    }

    /**
     * Handle the Transfer "updated" event.
     */
    public function updated(Transfer $transfer): void
    {
        // Check if status changed
        if ($transfer->wasChanged('status')) {
            $oldStatus = $transfer->getOriginal('status');
            $newStatus = $transfer->status;

            // Fire appropriate events based on status change
            if ($newStatus === TransferStatus::CONFIRMED) {
                event(new TransferCompleted($transfer));
            } elseif ($newStatus === TransferStatus::REJECTED) {
                event(new TransferFailed($transfer, 'Transfer was rejected'));
            } elseif ($newStatus === TransferStatus::PENDING && $oldStatus !== TransferStatus::PENDING) {
                event(new TransferPending($transfer));
            }
        }

        if (config('multi-wallet.audit_logging_enabled', true)) {
            $changes = $transfer->getChanges();

            if (! empty($changes)) {
                Log::info('Transfer updated', [
                    'transfer_id' => $transfer->id,
                    'changes' => $changes,
                    'original' => $transfer->getOriginal(),
                ]);
            }
        }
    }

    /**
     * Handle the Transfer "deleted" event.
     */
    public function deleted(Transfer $transfer): void
    {
        if (config('multi-wallet.audit_logging_enabled', true)) {
            Log::warning('Transfer deleted', [
                'transfer_id' => $transfer->id,
                'from_type' => $transfer->from_type,
                'from_id' => $transfer->from_id,
                'to_type' => $transfer->to_type,
                'to_id' => $transfer->to_id,
                'status' => $transfer->status,
            ]);
        }
    }
}
