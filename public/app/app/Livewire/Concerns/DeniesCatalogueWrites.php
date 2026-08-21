<?php

namespace App\Livewire\Concerns;

/**
 * The pharmacist needs to see the catalogue to know what can be dispensed, but
 * maintaining it is inventory work, not clinical work.
 *
 * Guard the ACTION, not just the button — a Livewire method stays callable
 * whether or not the control that calls it is rendered.
 */
trait DeniesCatalogueWrites
{
    /** Roles that may add, change or remove products and categories. */
    private const CATALOGUE_EDITORS = ['admin', 'branch_manager', 'inventory_manager'];

    public function canEditCatalogue(): bool
    {
        return (bool) array_intersect(auth()->user()->role ?? [], self::CATALOGUE_EDITORS);
    }

    /**
     * Call at the top of every write action:
     *     if ($this->blockedFromCatalogue()) return;
     */
    protected function blockedFromCatalogue(): bool
    {
        if ($this->canEditCatalogue()) {
            return false;
        }

        $this->error('You have view-only access to the catalogue.');

        return true;
    }
}
