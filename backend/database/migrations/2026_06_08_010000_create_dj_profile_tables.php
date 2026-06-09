<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dj_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('dj_name');
            $table->string('handle')->unique();
            $table->string('profile_headline')->nullable();
            $table->text('bio')->nullable();
            $table->enum('dj_type', [
                'battle_dj',
                'club_dj',
                'radio_dj',
                'mobile_event_dj',
                'producer_dj',
                'turntablist',
                'open_format',
            ])->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->boolean('booking_enabled')->default(false);
            $table->boolean('battle_enabled')->default(false);
            $table->enum('profile_status', ['draft', 'active', 'paused', 'archived'])->default('draft');
            $table->enum('visibility', ['public', 'followers', 'private'])->default('public');
            $table->enum('verification_status', ['unverified', 'pending', 'verified', 'rejected'])->default('unverified');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['profile_status', 'visibility']);
            $table->index(['city', 'state', 'country']);
            $table->index(['lat', 'lng']);
            $table->index('verification_status');
        });

        Schema::create('dj_genres', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('dj_profile_genres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dj_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dj_genre_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['dj_profile_id', 'dj_genre_id']);
            $table->index(['dj_profile_id', 'is_primary']);
        });

        Schema::create('dj_social_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dj_profile_id')->constrained()->cascadeOnDelete();
            $table->enum('platform', [
                'website',
                'instagram',
                'tiktok',
                'youtube',
                'soundcloud',
                'mixcloud',
                'twitch',
                'spotify',
            ]);
            $table->string('url');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['dj_profile_id', 'platform']);
            $table->index(['platform', 'created_at']);
        });

        Schema::create('dj_booking_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dj_profile_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('available_for_bookings')->default(false);
            $table->string('booking_email')->nullable();
            $table->boolean('show_booking_email')->default(false);
            $table->enum('rate_type', ['hourly', 'event', 'negotiable'])->nullable();
            $table->unsignedInteger('minimum_rate_cents')->nullable();
            $table->string('currency', 3)->default('USD');
            $table->unsignedSmallInteger('travel_radius_miles')->nullable();
            $table->boolean('will_travel')->default(false);
            $table->json('available_for')->nullable();
            $table->text('technical_rider_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('dj_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dj_profile_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['banner', 'intro_video', 'featured_mix', 'featured_track', 'gallery']);
            $table->string('title')->nullable();
            $table->string('url');
            $table->string('mime_type')->nullable();
            $table->unsignedInteger('size_bytes')->nullable();
            $table->string('alt_text')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['dj_profile_id', 'type']);
            $table->index(['type', 'is_primary']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dj_media');
        Schema::dropIfExists('dj_booking_settings');
        Schema::dropIfExists('dj_social_links');
        Schema::dropIfExists('dj_profile_genres');
        Schema::dropIfExists('dj_genres');
        Schema::dropIfExists('dj_profiles');
    }
};
