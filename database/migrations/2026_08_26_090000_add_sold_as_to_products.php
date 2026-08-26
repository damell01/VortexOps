<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * How a product is sold: Auction, BIN, or both.
 *
 * The cost sheet has carried this since before the app did, and the importer
 * had nowhere to put it, so it appended "Sold as: Both" to the notes field.
 * That kept the information and made it useless — you cannot filter a note,
 * report on one, or see it in a column.
 *
 * The backfill reads those notes back out and then removes the line it wrote,
 * because leaving both would give the same fact two homes that can disagree.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'sold_as')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('sold_as')->nullable()->after('product_type');
            });
        }

        DB::table('products')
            ->whereNotNull('notes')
            ->where('notes', 'like', '%Sold as:%')
            ->orderBy('id')
            ->chunkById(200, function ($products) {
                foreach ($products as $product) {
                    $notes = (string) $product->notes;

                    if (! preg_match('/^\s*Sold as:\s*(.+?)\s*$/mi', $notes, $match)) {
                        continue;
                    }

                    $value = trim($match[1]);

                    // Take the line out of the notes, then tidy the blank line
                    // it leaves behind rather than shipping a gap.
                    $remaining = preg_replace('/^\s*Sold as:.*$/mi', '', $notes);
                    $remaining = trim(preg_replace("/\n{3,}/", "\n\n", (string) $remaining));

                    DB::table('products')->where('id', $product->id)->update([
                        'sold_as' => $product->sold_as ?: $value,
                        'notes'   => $remaining === '' ? null : $remaining,
                    ]);
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'sold_as')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('sold_as');
            });
        }
    }
};
