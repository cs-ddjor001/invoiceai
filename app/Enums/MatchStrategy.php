<?php

namespace App\Enums;

/**
 * Which matcher produced an `invoice_matches` row. Part of the unique key on that table, so
 * every strategy can record its own verdict for the same invoice/PO pair — that side-by-side
 * comparison is what the project exists to make.
 */
enum MatchStrategy: string
{
    case Exact = 'exact';
    case Fuzzy = 'fuzzy';
    case Ai = 'ai';
}
