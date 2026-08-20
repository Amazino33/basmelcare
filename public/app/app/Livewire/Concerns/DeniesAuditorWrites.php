<?php

namespace App\Livewire\Concerns;

/**
 * An auditor exists to observe the money, not move it. Routes let them reach
 * these pages; this stops them acting on what they find there.
 *
 * Guard the ACTION, not just the button — hiding a control in Blade is
 * presentation, and a Livewire method stays callable regardless.
 */
trait DeniesAuditorWrites
{
    /** Roles that carry real authority; holding one overrides auditor read-only. */
    private const OPERATIONAL = [
        'admin', 'branch_manager', 'pharmacist', 'sales', 'cashier', 'inventory_manager',
    ];

    public function isReadOnlyAuditor(): bool
    {
        $roles = auth()->user()->role ?? [];

        return in_array('auditor', $roles, true)
            && ! array_intersect($roles, self::OPERATIONAL);
    }

    /**
     * Call at the top of every write action:
     *     if ($this->blockedAsAuditor()) return;
     */
    protected function blockedAsAuditor(): bool
    {
        if (! $this->isReadOnlyAuditor()) {
            return false;
        }

        $this->error('Auditors have view-only access.');

        return true;
    }
}
