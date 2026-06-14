<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('base_price_cents')->default(0);
            $table->unsignedInteger('sale_price_cents')->nullable();
            $table->string('vendor_name')->nullable();
            $table->string('source_type')->default('internal');
            $table->text('external_product_url')->nullable();
            $table->text('affiliate_tracking_url')->nullable();
            $table->text('image_url')->nullable();
            $table->string('category')->nullable();
            $table->string('status')->default('draft');
            $table->boolean('requires_customization')->default(false);
            $table->string('fulfillment_type')->default('internal');
            $table->decimal('commission_rate', 5, 2)->nullable();
            $table->json('customization_schema')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'source_type']);
            $table->index(['category', 'status']);
            $table->index(['fulfillment_type', 'status']);
        });

        Schema::create('shopping_carts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_id')->nullable()->index();
            $table->string('status')->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('shopping_cart_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shopping_cart_id')->constrained('shopping_carts')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('source_type');
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->json('selected_options')->nullable();
            $table->json('custom_design_data')->nullable();
            $table->unsignedInteger('unit_price_cents')->default(0);
            $table->unsignedInteger('estimated_total_cents')->default(0);
            $table->string('vendor_name')->nullable();
            $table->boolean('external_checkout_required')->default(false);
            $table->text('affiliate_tracking_url')->nullable();
            $table->string('fulfillment_type')->default('internal');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['shopping_cart_id', 'fulfillment_type']);
            $table->index(['source_type', 'external_checkout_required']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopping_cart_items');
        Schema::dropIfExists('shopping_carts');
        Schema::dropIfExists('products');
    }
};
