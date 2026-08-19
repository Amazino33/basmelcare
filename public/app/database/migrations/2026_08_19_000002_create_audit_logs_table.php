<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('auditable_type');
            // String, not integer: app_settings is keyed by its `key` column.
            $table->string('auditable_id');
            // Snapshot of the record's name so the entry stays readable after deletion.
            $table->string('auditable_label')->nullable();

            $table->string('event', 10);          // created | updated | deleted
            $table->string('field')->nullable();  // null for created/deleted
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
