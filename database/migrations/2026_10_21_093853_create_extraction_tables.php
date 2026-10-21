<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('host', 253)->unique();
            $table->string('origin', 300);
            $table->string('name')->nullable();
            $table->json('signals')->nullable();
            $table->timestamp('last_preflight_at')->nullable();
            $table->timestamp('opted_out_at')->nullable(); // store owner asked to be excluded
            $table->timestamps();
        });

        Schema::create('extraction_jobs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('guest_token', 64)->nullable()->index(); // sha256 of the anonymous session token
            $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
            $table->string('input_url', 2048);
            $table->string('status', 16)->default('queued')->index();
            $table->json('options');
            $table->json('preflight')->nullable();
            $table->string('methods_used')->nullable();
            $table->unsignedInteger('products_found')->default(0);
            $table->unsignedInteger('variations_found')->default(0);
            $table->unsignedInteger('products_estimated')->nullable();
            $table->json('warnings')->nullable();
            $table->string('error_code', 64)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('cancel_requested_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('extraction_job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('products')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->string('external_id', 64);
            $table->string('type', 16);
            $table->string('name', 500);
            $table->string('slug', 300)->nullable();
            $table->string('sku', 191)->nullable();
            $table->text('permalink');
            $table->decimal('regular_price', 20, 6)->nullable();
            $table->decimal('sale_price', 20, 6)->nullable();
            $table->decimal('price', 20, 6)->nullable();
            $table->decimal('price_min', 20, 6)->nullable();
            $table->decimal('price_max', 20, 6)->nullable();
            $table->char('currency', 3)->nullable();
            $table->string('stock_status', 16);
            $table->integer('stock_quantity')->nullable();
            $table->json('categories')->nullable();
            $table->json('tags')->nullable();
            $table->json('brands')->nullable();
            $table->json('attributes')->nullable();
            $table->json('variation_attributes')->nullable();
            $table->json('images')->nullable();
            $table->mediumText('short_description_html')->nullable();
            $table->mediumText('description_html')->nullable();
            $table->mediumText('description_text')->nullable();
            $table->decimal('rating_average', 4, 2)->nullable();
            $table->unsignedInteger('review_count')->nullable();
            $table->string('method', 16);
            $table->json('warnings')->nullable();
            $table->char('content_hash', 64);
            $table->timestamps();

            $table->index(['extraction_job_id', 'parent_id', 'position']);
            $table->index(['extraction_job_id', 'sku']);
            $table->index(['extraction_job_id', 'stock_status']);
        });

        Schema::create('exports', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('extraction_job_id')->constrained()->cascadeOnDelete();
            $table->string('format', 16);
            $table->json('options')->nullable();
            $table->string('status', 16)->default('queued');
            $table->string('disk', 32)->nullable();
            $table->string('path')->nullable();
            $table->string('filename')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->unsignedInteger('row_count')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exports');
        Schema::dropIfExists('products');
        Schema::dropIfExists('extraction_jobs');
        Schema::dropIfExists('stores');
    }
};
