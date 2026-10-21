<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Store owners asking to be excluded. Verified by publishing the token on their own domain.
        Schema::create('opt_out_requests', function (Blueprint $table) {
            $table->id();
            $table->string('host', 253)->index();
            $table->string('email');
            $table->string('token', 64)->unique();
            $table->timestamp('verified_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });

        // Invoices are kept for accounting when an account is deleted, without the personal link.
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opt_out_requests');
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
