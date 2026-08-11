<?php

namespace App\Enums;

/**
 * How the invoice arrived. Phase 5 creates Upload rows; Phase 6's Mailpit intake creates
 * Email rows.
 */
enum InvoiceSource: string
{
    case Upload = 'upload';
    case Email = 'email';
}
