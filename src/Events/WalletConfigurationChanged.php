<?php

namespace HWallet\LaravelMultiWallet\Events;

use HWallet\LaravelMultiWallet\Models\Wallet;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WalletConfigurationChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Wallet $wallet;

    public array $oldConfiguration;

    public array $newConfiguration;

    public array $changedFields;

    public \DateTime $changedAt;

    public function __construct(Wallet $wallet, array $oldConfiguration, array $newConfiguration)
    {
        $this->wallet = $wallet;
        $this->oldConfiguration = $oldConfiguration;
        $this->newConfiguration = $newConfiguration;
        $this->changedFields = $this->calculateChangedFields($oldConfiguration, $newConfiguration);
        $this->changedAt = new \DateTime;
    }

    /**
     * Calculate which fields have changed
     */
    protected function calculateChangedFields(array $old, array $new): array
    {
        $changed = [];

        foreach ($new as $key => $value) {
            if (! isset($old[$key]) || $old[$key] !== $value) {
                $changed[$key] = [
                    'old' => $old[$key] ?? null,
                    'new' => $value,
                ];
            }
        }

        return $changed;
    }

    /**
     * Get the event data as array
     */
    public function toArray(): array
    {
        return [
            'wallet_id' => $this->wallet->id,
            'old_configuration' => $this->oldConfiguration,
            'new_configuration' => $this->newConfiguration,
            'changed_fields' => $this->changedFields,
            'changed_at' => $this->changedAt->format('Y-m-d H:i:s'),
        ];
    }
}
