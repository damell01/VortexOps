<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Shows saved with Whatnot's relative link ("/dashboard/live/…") couldn't be
 * edited: the form's URL field rejected the stored value. Make them whole, as
 * App\Models\Show::normalizeWhatnotUrl() now does for every write.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('shows')->where('detail_url', 'like', '/%')->where('detail_url', 'not like', '//%')
            ->orderBy('id')->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('shows')->where('id', $row->id)->update(['detail_url' => 'https://www.whatnot.com' . $row->detail_url]);
                }
            });
        DB::table('shows')->where(fn ($q) => $q->where('detail_url', 'like', 'www.whatnot.com%')->orWhere('detail_url', 'like', 'whatnot.com%'))
            ->orderBy('id')->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('shows')->where('id', $row->id)->update(['detail_url' => 'https://' . $row->detail_url]);
                }
            });
    }

    public function down(): void
    {
        // Nothing to undo: whole links are what every reader expects.
    }
};
