<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use Database\Seeders\AdminRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FeaturedSlotManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_and_update_featured_slots(): void
    {
        $this->seed(AdminRoleSeeder::class);

        $admin = Admin::query()->create([
            'name' => 'Featured Admin',
            'email' => 'featured-admin@example.com',
            'password' => 'password',
            'role' => 'super-admin',
            'is_active' => true,
        ]);
        $admin->syncRoles(['super-admin']);

        $this->actingAs($admin, 'admin')
            ->get('/admin/admincenter/featuredslots')
            ->assertOk()
            ->assertSee('Featured Slots')
            ->assertSee('Campaign Options')
            ->assertSee('Visibility Pricing')
            ->assertSee('$25.00 / day')
            ->assertSee('$5.99 / day')
            ->assertSee('Slot 1')
            ->assertDontSee('Select a public DJ');

        $this->actingAs($admin, 'admin')
            ->post('/admin/admincenter/featuredslots/options', [
                'name' => '7 Day Spotlight',
                'duration_days' => 7,
                'is_active' => '1',
                'sort_order' => 1,
                'description' => 'One week featured placement.',
            ])
            ->assertRedirect(route('admin.admincenter.featuredslots.index'));

        $optionId = DB::table('featured_slot_campaign_options')->where('name', '7 Day Spotlight')->value('id');

        $this->actingAs($admin, 'admin')
            ->put('/admin/admincenter/featuredslots/1', [
                'campaign_option_ids' => [$optionId],
            ])
            ->assertRedirect(route('admin.admincenter.featuredslots.index'));

        $this->assertDatabaseHas('featured_slot_campaign_option_slot', [
            'slot_number' => 1,
            'featured_slot_campaign_option_id' => $optionId,
        ]);

        $this->assertDatabaseMissing('dj_featured_status', [
            'slot_number' => 1,
        ]);
    }

    public function test_super_admin_can_access_featured_slots_without_explicit_new_permission(): void
    {
        $this->seed(AdminRoleSeeder::class);

        $admin = Admin::query()->create([
            'name' => 'System Admin',
            'email' => 'system-admin@example.com',
            'password' => 'password',
            'role' => 'super-admin',
            'is_active' => true,
        ]);
        $admin->syncRoles(['super-admin']);
        $admin->revokePermissionTo('featuredslots.view');

        $this->actingAs($admin, 'admin')
            ->get('/admin/admincenter/featuredslots')
            ->assertOk()
            ->assertSee('Featured Slots');
    }
}
