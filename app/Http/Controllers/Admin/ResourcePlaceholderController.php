<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class ResourcePlaceholderController extends Controller
{
    public function __invoke(string $resource): View
    {
        abort_unless(array_key_exists($resource, $this->resources()), 404);

        return view('admin.resources.placeholder', [
            'resource' => $this->resources()[$resource],
        ]);
    }

    /**
     * @return array<string, array{title: string, icon: string, description: string}>
     */
    private function resources(): array
    {
        return [
            'users' => [
                'title' => 'Users',
                'icon' => 'fas fa-users',
                'description' => 'Manage public accounts, DJs, fans, and user status.',
            ],
            'roles' => [
                'title' => 'Roles',
                'icon' => 'fas fa-user-shield',
                'description' => 'Prepare administrator roles and future permission assignments.',
            ],
            'permissions' => [
                'title' => 'Permissions',
                'icon' => 'fas fa-key',
                'description' => 'Centralize authorization capabilities for admin modules.',
            ],
            'settings' => [
                'title' => 'Settings',
                'icon' => 'fas fa-cogs',
                'description' => 'Configure platform-wide operational settings.',
            ],
            'content' => [
                'title' => 'Content Management',
                'icon' => 'fas fa-file-alt',
                'description' => 'Organize future pages, posts, media, and editorial workflows.',
            ],
            'reports' => [
                'title' => 'Reporting',
                'icon' => 'fas fa-chart-line',
                'description' => 'Expose future platform metrics, moderation reports, and exports.',
            ],
        ];
    }
}
