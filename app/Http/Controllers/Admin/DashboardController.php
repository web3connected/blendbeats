<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AffiliatePayout;
use App\Models\Comment;
use App\Models\DjBattle;
use App\Models\DjProfile;
use App\Models\MediaFile;
use App\Models\Mix;
use App\Models\Post;
use App\Models\Product;
use App\Models\ShoppingCart;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $today = now()->startOfDay();

        return view('admin.dashboard', [
            'generatedAt' => now(),
            'summaryCards' => [
                $this->summaryCard('Users', User::query()->count(), 'Registered public accounts', 'fas fa-users', 'primary', route('admin.users.index')),
                $this->summaryCard('DJ Profiles', DjProfile::query()->count(), 'Public and draft DJ profiles', 'fas fa-headphones', 'success', url('/dj-hub')),
                $this->summaryCard('Published Mixes', Mix::query()->public()->count(), 'Live mixes available on the site', 'fas fa-compact-disc', 'info', url('/mixes')),
                $this->summaryCard('BlendNews Stories', Post::query()->count(), 'Draft, review, and published posts', 'fas fa-newspaper', 'warning', route('admin.blendnews.index')),
                $this->summaryCard('Products', Product::query()->count(), 'Commerce catalog items', 'fas fa-shopping-bag', 'secondary', route('admin.products.index')),
                $this->summaryCard('Open Carts', ShoppingCart::query()->where('status', 'open')->count(), 'Active shopping sessions', 'fas fa-shopping-cart', 'dark', route('admin.carts.index')),
                $this->summaryCard('Media Files', MediaFile::query()->count(), 'Uploaded media assets', 'fas fa-photo-video', 'purple', null),
                $this->summaryCard('Admins', Admin::query()->count(), 'Admin center accounts', 'fas fa-user-shield', 'teal', route('admin.admincenter.adminusers.index')),
            ],
            'activityCards' => [
                $this->metric('New Users Today', User::query()->where('created_at', '>=', $today)->count(), 'fas fa-user-plus', 'primary'),
                $this->metric('New Mixes Today', Mix::query()->where('created_at', '>=', $today)->count(), 'fas fa-music', 'info'),
                $this->metric('Stories In Review', Post::query()->review()->count(), 'fas fa-edit', 'warning'),
                $this->metric('Pending Comments', Comment::query()->where('status', Comment::STATUS_PENDING)->count(), 'fas fa-comments', 'danger'),
            ],
            'contentSnapshot' => [
                $this->statusLine('Published stories', Post::query()->published()->count()),
                $this->statusLine('Draft stories', Post::query()->draft()->count()),
                $this->statusLine('Featured stories', Post::query()->featured()->count()),
                $this->statusLine('Breaking stories', Post::query()->breaking()->count()),
            ],
            'commerceSnapshot' => [
                $this->statusLine('Active products', Product::query()->where('status', 'active')->count()),
                $this->statusLine('Draft products', Product::query()->where('status', 'draft')->count()),
                $this->statusLine('Open carts', ShoppingCart::query()->where('status', 'open')->count()),
                $this->statusLine('Pending affiliate payouts', $this->pendingAffiliatePayouts()),
            ],
            'communitySnapshot' => [
                $this->statusLine('Active DJs', DjProfile::query()->where('profile_status', 'active')->count()),
                $this->statusLine('Battle-ready DJs', DjProfile::query()->where('battle_enabled', true)->count()),
                $this->statusLine('Verified DJs', DjProfile::query()->where('verification_status', 'verified')->count()),
                $this->statusLine('Wallets funded', Wallet::query()->where('available_balance', '>', 0)->count()),
            ],
            'battleSnapshot' => [
                $this->statusLine('Pending challenges', DjBattle::query()->where('status', DjBattle::STATUS_PENDING)->count()),
                $this->statusLine('Recording battles', DjBattle::query()->where('status', DjBattle::STATUS_RECORDING)->count()),
                $this->statusLine('Voting battles', DjBattle::query()->where('status', DjBattle::STATUS_VOTING)->count()),
                $this->statusLine('Completed battles', DjBattle::query()->where('status', DjBattle::STATUS_COMPLETED)->count()),
            ],
            'recentUsers' => User::query()
                ->latest()
                ->limit(5)
                ->get(['id', 'name', 'email', 'created_at']),
            'recentPosts' => Post::query()
                ->with('author:id,name,email')
                ->latest()
                ->limit(5)
                ->get(['id', 'author_id', 'title', 'status', 'published_at', 'created_at']),
            'recentMixes' => Mix::query()
                ->with('user:id,name,email')
                ->latest()
                ->limit(5)
                ->get(['id', 'user_id', 'title', 'genre', 'is_public', 'published_at', 'created_at']),
            'quickActions' => [
                [
                    'label' => 'Create News Story',
                    'description' => 'Draft or publish a BlendNews article.',
                    'href' => route('admin.blendnews.create'),
                    'icon' => 'fas fa-plus',
                    'theme' => 'primary',
                ],
                [
                    'label' => 'Manage Users',
                    'description' => 'Review public accounts and profiles.',
                    'href' => route('admin.users.index'),
                    'icon' => 'fas fa-users',
                    'theme' => 'info',
                ],
                [
                    'label' => 'Manage Products',
                    'description' => 'Update merch and marketplace items.',
                    'href' => route('admin.products.index'),
                    'icon' => 'fas fa-shopping-bag',
                    'theme' => 'success',
                ],
                [
                    'label' => 'Site Analytics',
                    'description' => 'Review traffic, users, referrers, and admin activity.',
                    'href' => route('admin.admincenter.site-analytics.index'),
                    'icon' => 'fas fa-chart-area',
                    'theme' => 'secondary',
                ],
                [
                    'label' => 'Battle Admin',
                    'description' => 'Open the battle operations dashboard.',
                    'href' => route('admin.battle-admin.dashboard'),
                    'icon' => 'fas fa-trophy',
                    'theme' => 'danger',
                ],
            ],
        ]);
    }

    private function summaryCard(
        string $label,
        int|float $value,
        string $detail,
        string $icon,
        string $theme,
        ?string $href,
    ): array {
        return [
            'label' => $label,
            'value' => number_format($value, is_float($value) ? 1 : 0),
            'detail' => $detail,
            'icon' => $icon,
            'theme' => $theme,
            'href' => $href,
        ];
    }

    private function metric(string $label, int|float $value, string $icon, string $theme): array
    {
        return [
            'label' => $label,
            'value' => number_format($value, is_float($value) ? 1 : 0),
            'icon' => $icon,
            'theme' => $theme,
        ];
    }

    private function statusLine(string $label, int|float $value): array
    {
        return [
            'label' => $label,
            'value' => number_format($value, is_float($value) ? 1 : 0),
        ];
    }

    private function pendingAffiliatePayouts(): int
    {
        return AffiliatePayout::query()
            ->whereIn('status', [
                AffiliatePayout::STATUS_REQUESTED,
                AffiliatePayout::STATUS_APPROVED,
                AffiliatePayout::STATUS_PROCESSING,
            ])
            ->count();
    }
}
