<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a show's title says it offered — giveaways, $1 starts, free packs.
 *
 * Separate from show_format, which is a single classification a person
 * chooses. Tags are read from the title by rules (App\Support\ShowTitleTags),
 * a show can carry several, and title_tags_version records which version of
 * the rules produced them so a rule change re-tags old shows overnight.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shows', function (Blueprint $table) {
            if (! Schema::hasColumn('shows', 'title_tags')) {
                $table->json('title_tags')->nullable()->after('show_format');
            }
            if (! Schema::hasColumn('shows', 'title_tags_version')) {
                $table->unsignedSmallInteger('title_tags_version')->nullable()->after('title_tags');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shows', function (Blueprint $table) {
            $table->dropColumn(['title_tags', 'title_tags_version']);
        });
    }
};
