<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When a product's image was last put into Cloudinary.
 *
 * Progress was being worked out by asking Cloudinary whether each file
 * existed - one API call per product before a single upload could start.
 * With a few hundred images that is slow enough to be its own problem, and it
 * made resuming an interrupted run as expensive as the run itself.
 *
 * Null means "not up there, or changed since it was" and is the thing the
 * upload command works through.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->timestamp('image_synced_at')->nullable()->after('image');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('image_synced_at');
        });
    }
};
