<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Messages sent to many customers at once.
 *
 * Recipients are written out as rows rather than resolved at send time, for
 * two reasons. Sending several hundred messages in one web request times out,
 * exactly as the Cloudinary upload did, so it has to go in batches that can
 * stop and resume. And a broadcast is a thing that happened: who it reached,
 * how each one was delivered, and who it failed for, all need to survive being
 * asked about later.
 *
 * Nobody is messaged as a group. Each person gets their own message, so one
 * customer never sees another's number.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broadcasts', function (Blueprint $table) {
            $table->id();
            $table->text('message');
            // Stored on the public site's disk so the WhatsApp gateway can
            // fetch it: media is sent as a URL, not an upload.
            $table->string('image_path')->nullable();
            // all | wholesale | retail | recent
            $table->string('audience', 20)->default('all');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('broadcast_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('broadcast_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            // Kept on the row: a customer may change their number afterwards,
            // and the record should say where the message actually went.
            $table->string('phone', 30);
            // pending | whatsapp | sms | failed
            $table->string('status', 20)->default('pending');
            // Whether the picture actually arrived. SMS cannot carry one, so a
            // feature-phone customer gets the words and not the image.
            $table->boolean('image_sent')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['broadcast_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_recipients');
        Schema::dropIfExists('broadcasts');
    }
};
