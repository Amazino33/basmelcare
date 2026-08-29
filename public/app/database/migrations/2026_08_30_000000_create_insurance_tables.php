<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Monthly health cover: a customer pays a premium, and draws medicine against
 * it when they need it.
 *
 * This is insurance, not a discount card, and the difference is the whole
 * design. A discount costs the pharmacy a known fraction of a sale. Cover
 * costs whatever the customer needs - so every limit below exists to keep that
 * bounded, because the pharmacy carries the risk and pays for the stock in
 * advance.
 *
 *   monthly_cover   A hard ceiling per period. Without it a single subscriber
 *                   on chemotherapy would take a year of everybody's premiums.
 *   waiting_days    Nobody can subscribe on the morning they fall ill, claim
 *                   the full cover that afternoon, and never pay again.
 *   grace_days      Cover continues briefly past a missed payment, because
 *                   people pay late; after that it lapses.
 *   copay_percent   The subscriber still pays a share, so "free" medicine is
 *                   not collected for a cupboard at home.
 *   excluded_categories
 *                   Cover is for medicine. Cosmetics and provisions are not
 *                   what the premium was collected for.
 *
 * Unused cover does not roll over. That is what makes the pool work: this
 * month's premiums pay this month's claims.
 *
 * Nothing here is switched on by the migration. The whole feature sits behind
 * the insurance_enabled setting, off until the pharmacy decides to sell it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('insurance_plans')) {
            Schema::create('insurance_plans', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code')->unique();
                $table->text('description')->nullable();

                $table->decimal('monthly_premium', 12, 2);
                $table->decimal('monthly_cover', 12, 2);
                $table->unsignedTinyInteger('copay_percent')->default(0);

                $table->unsignedSmallInteger('waiting_days')->default(30);
                $table->unsignedSmallInteger('grace_days')->default(7);

                // Category ids the plan will not pay for.
                $table->json('excluded_categories')->nullable();

                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('insurance_subscriptions')) {
            Schema::create('insurance_subscriptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $table->foreignId('insurance_plan_id')->constrained()->restrictOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();

                // pending  - signed up, first premium not yet paid
                // waiting  - paid, still inside the waiting period
                // active   - paid and claimable
                // lapsed   - premium overdue past the grace period
                // cancelled- ended deliberately
                $table->string('status')->default('pending');

                $table->timestamp('started_at')->nullable();
                $table->timestamp('waiting_until')->nullable();

                // The period the cover figure below belongs to. Cover is spent
                // within a period and does not carry forward.
                $table->date('period_start')->nullable();
                $table->date('period_end')->nullable();
                $table->decimal('cover_used', 12, 2)->default(0);

                $table->timestamp('cancelled_at')->nullable();
                $table->string('cancelled_reason')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                // A customer holds at most one live subscription; the partial
                // rule cannot be expressed portably, so it is enforced in the
                // model and asserted in the tests.
                $table->index(['customer_id', 'status']);
                $table->index(['status', 'period_end']);
            });
        }

        if (! Schema::hasTable('insurance_premiums')) {
            Schema::create('insurance_premiums', function (Blueprint $table) {
                $table->id();
                $table->foreignId('insurance_subscription_id')->constrained()->cascadeOnDelete();

                $table->decimal('amount', 12, 2);
                $table->date('period_start');
                $table->date('period_end');

                // cash / card / transfer at the counter, or paystack online.
                $table->string('method');
                $table->string('reference')->nullable();

                $table->timestamp('paid_at');
                $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
                $table->timestamps();

                // One payment per period: paying twice for August must not
                // silently buy two Augusts' worth of cover.
                $table->unique(['insurance_subscription_id', 'period_start']);
            });
        }

        if (! Schema::hasTable('insurance_claims')) {
            Schema::create('insurance_claims', function (Blueprint $table) {
                $table->id();
                $table->foreignId('insurance_subscription_id')->constrained()->cascadeOnDelete();

                // A claim comes from a counter sale or an online order.
                $table->foreignId('sale_id')->nullable()->constrained()->cascadeOnDelete();
                $table->foreignId('order_id')->nullable()->constrained()->cascadeOnDelete();

                $table->decimal('amount', 12, 2);
                $table->date('period_start');

                // What the medicine cost the pharmacy, captured at the time.
                // Premiums minus this is whether the scheme is worth running,
                // and batch costs change, so it cannot be recomputed later.
                $table->decimal('cost_amount', 12, 2)->default(0);

                $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
                $table->timestamps();

                $table->index(['insurance_subscription_id', 'period_start']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('insurance_claims');
        Schema::dropIfExists('insurance_premiums');
        Schema::dropIfExists('insurance_subscriptions');
        Schema::dropIfExists('insurance_plans');
    }
};
