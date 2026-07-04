<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dj_booking_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('dj_booking_settings', 'booking_default_timezone')) {
                $table->string('booking_default_timezone', 100)->nullable()->after('currency');
            }

            if (! Schema::hasColumn('dj_booking_settings', 'booking_min_notice_hours')) {
                $table->unsignedSmallInteger('booking_min_notice_hours')->default(24)->after('booking_default_timezone');
            }

            if (! Schema::hasColumn('dj_booking_settings', 'booking_max_advance_days')) {
                $table->unsignedSmallInteger('booking_max_advance_days')->default(180)->after('booking_min_notice_hours');
            }

            if (! Schema::hasColumn('dj_booking_settings', 'booking_auto_accept')) {
                $table->boolean('booking_auto_accept')->default(false)->after('booking_max_advance_days');
            }
        });

        Schema::create('dj_booking_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('dj_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dj_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('event_name', 150);
            $table->string('event_type', 100);
            $table->date('event_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('timezone', 100)->nullable();

            $table->string('location_name', 150)->nullable();
            $table->string('location_address', 255)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('postal_code', 30)->nullable();
            $table->string('country', 100)->nullable();

            $table->unsignedInteger('expected_crowd_size')->nullable();
            $table->string('music_style', 150)->nullable();
            $table->json('requested_services')->nullable();
            $table->text('message')->nullable();

            $table->unsignedInteger('hourly_rate_tokens')->nullable();
            $table->decimal('hourly_rate_amount', 10, 2)->nullable();
            $table->decimal('estimated_hours', 6, 2)->nullable();
            $table->decimal('estimated_total_amount', 10, 2)->nullable();
            $table->string('currency', 3)->nullable();

            $table->string('contact_name', 150);
            $table->string('contact_email', 150);
            $table->string('contact_phone', 50)->nullable();

            $table->string('status')->default('pending');
            $table->string('payment_status')->default('unpaid');

            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('paid_at')->nullable();

            $table->text('internal_notes')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['dj_profile_id', 'status']);
            $table->index(['dj_user_id', 'status']);
            $table->index('event_date');
            $table->index('payment_status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dj_booking_requests');

        Schema::table('dj_booking_settings', function (Blueprint $table): void {
            foreach ([
                'booking_auto_accept',
                'booking_max_advance_days',
                'booking_min_notice_hours',
                'booking_default_timezone',
            ] as $column) {
                if (Schema::hasColumn('dj_booking_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
