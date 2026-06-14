<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('profiles')) {
            return;
        }

        Schema::create('profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('contact_email')->nullable();
            $table->string('phone')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('timezone')->nullable();
            $table->string('website_url')->nullable();
            $table->string('instagram_url')->nullable();
            $table->string('youtube_url')->nullable();
            $table->string('soundcloud_url')->nullable();
            $table->string('spotify_url')->nullable();
            $table->text('bio')->nullable();
            $table->date('birthdate')->nullable();
            $table->boolean('marketing_opt_in')->default(false);
            $table->timestamps();

            $table->index(['city', 'state', 'country']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};
