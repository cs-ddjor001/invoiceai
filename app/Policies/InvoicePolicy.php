<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    /**
     * Runs before every other method in this policy.
     *
     * Returning true grants the ability outright and the specific method never runs;
     * returning false denies it outright. Returning **null** means "no opinion" and lets the
     * specific method decide — which is why the admin check lives here once instead of being
     * repeated as the first line of all seven methods below.
     */
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * An AP rep may only open invoices billed by a vendor assigned to them.
     *
     * This is the question middleware cannot answer: `role:ap` knows the user is an AP rep,
     * but not *which* invoice is being requested, so it cannot compare the two.
     */
    public function view(User $user, Invoice $invoice): bool
    {
        if (! $user->isAp()) {
            return false;
        }

        // ?-> because vendor_id is nullable — an invoice may arrive before its vendor is
        // resolved. null === $user->id is false, so an unassigned invoice stays hidden.
        return $invoice->vendor?->user_id === $user->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Invoice $invoice): bool
    {
        if (! $user->isAp()) {
            return false;
        }

        return $invoice->vendor?->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Invoice $invoice): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Invoice $invoice): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Invoice $invoice): bool
    {
        return false;
    }
}
