<?php

namespace Database\Seeders;

use App\Models\PoLine;
use App\Models\PurchaseOrder;
use App\Models\Vendor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

/**
 * Streams the ADS purchase-order CSVs into `vendors`, `purchase_orders` and `po_lines`.
 *
 * The CSVs are line-item shaped: every row is a PO line, and the PO header fields repeat on
 * every row sharing a PO_NUMBER (39 distinct POs across 238 rows). So the import runs in
 * passes, collapsing each entity out of the same stream: vendors, then PO headers, then the
 * lines themselves.
 */
class PurchaseOrderSeeder extends Seeder
{
    /**
     * Rows per write. Irrelevant at 238 rows, but the pattern is the point: chunking keeps one
     * statement from growing past MySQL's max_allowed_packet when the file is large.
     */
    private const CHUNK_SIZE = 500;

    /** @var list<string> */
    private const CSV_FILES = [
        'Invoice_PO_Data.csv',
        'Invoice_PO_Data_2.csv',
    ];

    public function run(): void
    {
        $this->seedVendors();
        $this->seedPurchaseOrders();
        $this->seedPoLines();
    }

    /**
     * Every configured CSV as one lazy stream of header-keyed rows.
     *
     * LazyCollection wraps a generator: rows are produced one at a time as the pipeline pulls
     * them, and nothing retains the whole file. Memory stays flat at 238 rows or 2 million,
     * unlike `collect(file($path))`, which loads everything before the first map runs.
     *
     * @return LazyCollection<int, array<string, string>>
     */
    private function csvRows(): LazyCollection
    {
        return LazyCollection::make(function () {
            foreach (self::CSV_FILES as $file) {
                $path = database_path('data/'.$file);

                $handle = is_readable($path) ? fopen($path, 'r') : false;

                if ($handle === false) {
                    $this->command->warn("Skipping unreadable CSV: {$path}");

                    continue;
                }

                $header = fgetcsv($handle, escape: '');

                if (! is_array($header)) {
                    fclose($handle);

                    continue;
                }

                // fgetcsv can hand back null cells, and array_combine needs string keys.
                $header = array_map(strval(...), $header);

                // fgetcsv parses quoting, so "MORIN-WRIGHT, ALISON" stays one field instead
                // of splitting on its comma. (escape: '' opts out of the deprecated backslash
                // escape mechanism, so quotes alone govern the parse.)
                while (($row = fgetcsv($handle, escape: '')) !== false) {
                    if (count($row) !== count($header)) {
                        continue;
                    }

                    yield array_combine($header, array_map(strval(...), $row));
                }

                fclose($handle);
            }
        });
    }

    /**
     * Pass 1: one vendor per distinct PO_VENDOR_ID.
     */
    private function seedVendors(): void
    {
        $this->csvRows()
            ->filter(fn (array $row) => filled($row['PO_VENDOR_ID'] ?? null))
            // unique() on a lazy collection remembers only the keys it has seen, not the
            // rows: the first row wins, later repeats are dropped as they stream past.
            ->unique(fn (array $row) => $row['PO_VENDOR_ID'])
            ->map(fn (array $row) => [
                'external_id' => (int) $row['PO_VENDOR_ID'],
                'name' => $this->normalizeVendorName($row['PO_VENDOR_NAME']) ?? 'UNKNOWN',
            ])
            ->chunk(self::CHUNK_SIZE)
            ->each(function (LazyCollection $chunk) {
                // upsert($rows, $uniqueBy, $update): inserts rows whose $uniqueBy values are
                // new and updates the $update columns on rows that already exist. It needs a
                // unique index on the $uniqueBy columns (vendors.external_id has one) and,
                // called on the model, it fills created_at/updated_at too. Re-running the
                // seeder therefore converges instead of duplicating: that is idempotency.
                Vendor::upsert($chunk->all(), ['external_id'], ['name']);
            });

        $this->command->info('Vendors: '.Vendor::count());
    }

    /**
     * Pass 2: one purchase order per distinct PO_NUMBER, linked to its pass-1 vendor.
     */
    private function seedPurchaseOrders(): void
    {
        // 39 vendors, so load the whole external_id => id map once rather than query per PO.
        $vendorIds = Vendor::pluck('id', 'external_id');

        $this->csvRows()
            ->filter(fn (array $row) => filled($row['PO_NUMBER'] ?? null))
            ->unique(fn (array $row) => $row['PO_NUMBER'])
            ->map(fn (array $row) => [
                'po_number' => trim($row['PO_NUMBER']),
                'vendor_id' => $vendorIds[(int) $row['PO_VENDOR_ID']] ?? null,
                'vendor_name' => $this->normalizeVendorName($row['PO_VENDOR_NAME']),
                'po_date' => $this->parseDate($row['PO_DATE']),
                'status' => $this->nullIfBlank($row['PO_STATUS']),
                'buyer_name' => $this->nullIfBlank($row['PO_BUYER_NAME']),
            ])
            ->chunk(self::CHUNK_SIZE)
            ->each(function (LazyCollection $chunk) {
                PurchaseOrder::upsert(
                    $chunk->all(),
                    ['po_number'],
                    ['vendor_id', 'vendor_name', 'po_date', 'status', 'buyer_name'],
                );
            });

        $this->command->info('Purchase orders: '.PurchaseOrder::count());
    }

    /**
     * Pass 3: every CSV row becomes a PO line. No unique() here, because unlike passes 1
     * and 2 each row really is its own entity.
     */
    private function seedPoLines(): void
    {
        // po_number => purchase_orders.id, the mirror of pass 2's vendor lookup.
        $purchaseOrderIds = PurchaseOrder::pluck('id', 'po_number');

        // po_lines carries no unique index, so upsert() would have nothing to match on and
        // would degenerate into a plain insert, doubling the table on every run. Clearing
        // first is the honest way to stay idempotent for reference data the seeder owns.
        PoLine::query()->delete();
        DB::table('po_lines')->truncate();

        $now = now();

        $this->csvRows()
            ->filter(fn (array $row) => filled($row['PO_NUMBER'] ?? null))
            ->map(fn (array $row) => [
                'purchase_order_id' => $purchaseOrderIds[trim($row['PO_NUMBER'])] ?? null,
                'line_num' => $this->nullIfBlank($row['PO_LINE_NUM']),
                'part_number' => $this->nullIfBlank($row['PART_NUMBER']),
                'description' => $this->nullIfBlank($row['PART_DESCRIPTION']),
                'uom' => $this->nullIfBlank($row['UNIT_OF_MEASURE']),
                'qty_ordered' => $this->nullIfBlank($row['PO_QTY_ORDERED']),
                'qty_delivered' => $this->nullIfBlank($row['PO_QTY_DELIVERED']),
                'qty_cancelled' => $this->nullIfBlank($row['PO_QTY_CANCEL']),
                'unit_price' => $this->nullIfBlank($row['PO_UNIT_PRICE']),
                'amt_invoiced' => $this->nullIfBlank($row['PO_AMT_INVOICED']),
                'status' => $this->nullIfBlank($row['PO_LINE_LOCATION_STATUS']),
                'clin' => $this->nullIfBlank($row['CLIN']),

                // insert() is a query-builder call and does not run Eloquent's timestamp
                // handling, so these have to be set by hand.
                'created_at' => $now,
                'updated_at' => $now,
            ])
            // A line whose PO never made it into pass 2 has nothing to hang off.
            ->filter(fn (array $row) => $row['purchase_order_id'] !== null)
            ->chunk(self::CHUNK_SIZE)
            ->each(fn (LazyCollection $chunk) => PoLine::insert($chunk->all()));

        $this->command->info('Purchase order lines: '.PoLine::count());
    }

    /**
     * The source data has runs of internal whitespace ("DRAEGER  INC").
     */
    private function normalizeVendorName(?string $name): ?string
    {
        $name = preg_replace('/\s+/', ' ', trim((string) $name));

        return $name === '' ? null : $name;
    }

    /**
     * PO_DATE arrives as "2023-12-04 00:00:00"; the column is a plain date.
     */
    private function parseDate(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : Carbon::parse($value)->toDateString();
    }

    private function nullIfBlank(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
