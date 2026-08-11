<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\VendorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * @property int $id
 * @property int|null $external_id
 * @property string $name
 * @property int|null $user_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['external_id', 'name', 'user_id'])]
class Vendor extends Model
{
    /** @use HasFactory<VendorFactory> */
    use HasFactory;

    /**
     * The AP rep this vendor is assigned to.
     *
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return HasMany<PurchaseOrder, $this>
     */
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * The addresses this vendor sends invoices from. Phase 6 resolves inbound mail through
     * this relation.
     *
     * @return HasMany<VendorEmail, $this>
     */
    public function emails(): HasMany
    {
        return $this->hasMany(VendorEmail::class);
    }

    /**
     * Every PO line belonging to this vendor, reached through `purchase_orders`. There is no
     * vendor_id on po_lines — hasManyThrough walks the intermediate table for you, so this is
     * one query instead of loading POs and looping over them.
     *
     * @return HasManyThrough<PoLine, PurchaseOrder, $this>
     */
    public function poLines(): HasManyThrough
    {
        return $this->hasManyThrough(PoLine::class, PurchaseOrder::class);
    }
}
