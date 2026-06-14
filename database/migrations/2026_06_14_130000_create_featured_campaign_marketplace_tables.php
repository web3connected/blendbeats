<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const GROUP_SIZE = 4;

    private const GROUP_WEIGHTS = [35, 25, 15, 10, 8, 7];

    private const MAX_DAILY_PRICE_CENTS = 2500;

    private const MIN_DAILY_PRICE_CENTS = 599;

    public function up(): void
    {
        Schema::create('featured_slot_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('group_key', 8)->unique();
            $table->unsignedTinyInteger('slot_count')->default(self::GROUP_SIZE);
            $table->string('template_type')->default('featured_dj');
            $table->unsignedTinyInteger('rotation_weight')->default(1);
            $table->unsignedInteger('daily_price_cents')->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('featured_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('featured_slot_group_id')->constrained('featured_slot_groups')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['status', 'start_date', 'end_date']);
        });

        Schema::create('featured_campaign_slots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('featured_campaign_id')->constrained('featured_campaigns')->cascadeOnDelete();
            $table->unsignedTinyInteger('group_slot_number');
            $table->string('claim_status')->default('open');
            $table->foreignId('claimed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['featured_campaign_id', 'group_slot_number'], 'featured_campaign_slot_unique');
            $table->index(['claim_status', 'group_slot_number']);
        });

        Schema::table('dj_featured_status', function (Blueprint $table): void {
            $table->foreignId('featured_campaign_slot_id')
                ->nullable()
                ->after('featured_slot_campaign_option_id')
                ->constrained('featured_campaign_slots')
                ->nullOnDelete();
        });

        $this->seedDefaultMarketplace();
    }

    public function down(): void
    {
        Schema::table('dj_featured_status', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('featured_campaign_slot_id');
        });

        Schema::dropIfExists('featured_campaign_slots');
        Schema::dropIfExists('featured_campaigns');
        Schema::dropIfExists('featured_slot_groups');
    }

    private function seedDefaultMarketplace(): void
    {
        $now = now();
        $campaignTitles = [
            1 => 'Premium Featured DJs',
            2 => 'High Visibility DJ Rotation',
            3 => 'Major Community Spotlight',
            4 => 'Community Featured DJs',
            5 => 'Entry Promotion Rotation',
            6 => 'Basic Featured DJ Campaign',
        ];

        foreach (range(1, 6) as $groupNumber) {
            $groupKey = chr(64 + $groupNumber);
            $groupId = DB::table('featured_slot_groups')->insertGetId([
                'name' => "Group {$groupKey} Template",
                'group_key' => $groupKey,
                'slot_count' => self::GROUP_SIZE,
                'template_type' => 'featured_dj',
                'rotation_weight' => self::GROUP_WEIGHTS[$groupNumber - 1],
                'daily_price_cents' => $this->dailyPriceForGroup($groupNumber),
                'sort_order' => $groupNumber,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $campaignId = DB::table('featured_campaigns')->insertGetId([
                'featured_slot_group_id' => $groupId,
                'title' => $campaignTitles[$groupNumber],
                'description' => "Default marketplace campaign using the Group {$groupKey} slot template.",
                'status' => 'active',
                'sort_order' => $groupNumber,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach (range(1, self::GROUP_SIZE) as $slotNumber) {
                DB::table('featured_campaign_slots')->insert([
                    'featured_campaign_id' => $campaignId,
                    'group_slot_number' => $slotNumber,
                    'claim_status' => 'open',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    private function dailyPriceForGroup(int $group): int
    {
        $weight = self::GROUP_WEIGHTS[$group - 1] ?? min(self::GROUP_WEIGHTS);
        $maxWeight = max(self::GROUP_WEIGHTS);
        $minWeight = min(self::GROUP_WEIGHTS);

        if ($maxWeight === $minWeight) {
            return self::MIN_DAILY_PRICE_CENTS;
        }

        $visibilityRatio = ($weight - $minWeight) / ($maxWeight - $minWeight);

        return (int) round(self::MIN_DAILY_PRICE_CENTS + ($visibilityRatio * (self::MAX_DAILY_PRICE_CENTS - self::MIN_DAILY_PRICE_CENTS)));
    }
};
