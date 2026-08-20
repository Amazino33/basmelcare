<?php

namespace App\Models\Concerns;

/**
 * Stores `name` uppercase and trimmed.
 *
 * Normalising on write (rather than uppercasing on read) keeps the stored value
 * and the displayed value identical, so a case-sensitive query, an export or a
 * report can never disagree with what staff see on screen.
 */
trait NormalisesName
{
    public function setNameAttribute(?string $value): void
    {
        $this->attributes['name'] = $value === null || trim($value) === ''
            ? $value
            : strtoupper(trim($value));
    }
}
