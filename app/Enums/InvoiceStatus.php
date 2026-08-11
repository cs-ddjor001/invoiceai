<?php

namespace App\Enums;

/**
 * The invoice's position in the pipeline. The source app only ever used "pending" and
 * "matched" because extraction ran synchronously inside the request; queueing it in Phase 5
 * makes the intermediate states real and observable.
 */
enum InvoiceStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Extracted = 'extracted';
    case Matched = 'matched';
    case Failed = 'failed';
}
