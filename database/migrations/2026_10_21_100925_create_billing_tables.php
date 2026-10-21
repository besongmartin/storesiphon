<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 32)->unique();
            $table->string('name', 64);
            $table->decimal('price_usd', 10, 2)->default(0);
            $table->unsignedSmallInteger('period_days')->default(30);
            $table->unsignedInteger('max_products_per_job');
            $table->unsignedInteger('product_quota');         // products per period
            $table->unsignedTinyInteger('concurrent_jobs')->default(1);
            $table->unsignedSmallInteger('retention_days')->default(7);
            $table->json('export_formats');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        // Prepaid, time-boxed access bought with a confirmed invoice.
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained();
            $table->uuid('invoice_id')->nullable()->unique(); // one period per invoice, never two
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->index();
            $table->string('source', 16)->default('invoice'); // invoice | admin
            $table->timestamps();
            $table->index(['user_id', 'ends_at']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();                    // our order reference (sent to the provider)
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained();
            $table->string('provider', 16);
            $table->string('provider_invoice_id', 128)->nullable()->unique();
            $table->text('checkout_url')->nullable();
            $table->decimal('fiat_amount', 12, 2);
            $table->char('fiat_currency', 3);
            $table->decimal('btc_amount', 18, 8)->nullable();
            $table->decimal('btc_paid', 18, 8)->nullable();
            $table->decimal('rate', 18, 2)->nullable();       // fiat per BTC at invoice creation
            $table->timestamp('rate_at')->nullable();
            $table->string('status', 16)->index();            // pending | detected | confirming | confirmed | expired | underpaid | overpaid | failed | refunded
            $table->string('provider_status', 32)->nullable();
            $table->string('provider_additional_status', 32)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamp('granted_at')->nullable();      // access granted exactly once
            $table->timestamp('last_checked_at')->nullable();
            $table->boolean('needs_review')->default(false)->index();
            $table->text('review_note')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        // Append-only record of every provider notification (idempotency by delivery id).
        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 16);
            $table->string('delivery_id', 128);
            $table->string('type', 64);
            $table->string('provider_invoice_id', 128)->nullable()->index();
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['provider', 'delivery_id']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete(); // null = system
            $table->string('action', 64)->index();
            $table->string('subject_type', 64);
            $table->string('subject_id', 64);
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->text('note')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['subject_type', 'subject_id']);
        });

        // Launch plans (architecture D10); prices are editable data.
        $now = now();
        DB::table('plans')->insert([
            ['slug' => 'free', 'name' => 'Free', 'price_usd' => 0, 'period_days' => 30, 'max_products_per_job' => 25, 'product_quota' => 250, 'concurrent_jobs' => 1, 'retention_days' => 7, 'export_formats' => json_encode(['csv', 'xlsx', 'json']), 'is_active' => true, 'sort' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'starter', 'name' => 'Starter', 'price_usd' => 19, 'period_days' => 30, 'max_products_per_job' => 5000, 'product_quota' => 5000, 'concurrent_jobs' => 1, 'retention_days' => 30, 'export_formats' => json_encode(['csv', 'xlsx', 'json']), 'is_active' => true, 'sort' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'pro', 'name' => 'Pro', 'price_usd' => 49, 'period_days' => 30, 'max_products_per_job' => 50000, 'product_quota' => 50000, 'concurrent_jobs' => 2, 'retention_days' => 30, 'export_formats' => json_encode(['csv', 'xlsx', 'json']), 'is_active' => true, 'sort' => 3, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
    }
};
