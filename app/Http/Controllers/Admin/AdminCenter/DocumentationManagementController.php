<?php

namespace App\Http\Controllers\Admin\AdminCenter;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class DocumentationManagementController extends Controller
{
    public function __invoke(): View
    {
        $articles = collect(config('documentation.articles', []));
        $categories = collect(config('documentation.categories', []))
            ->map(function (array $category) use ($articles): array {
                $category['article_count'] = $articles
                    ->where('category', $category['slug'])
                    ->count();

                return $category;
            });

        return view('admin.documentation.index', [
            'source' => config('documentation.source'),
            'categories' => $categories,
            'articles' => $articles,
            'stats' => [
                'categories' => $categories->count(),
                'articles' => $articles->count(),
                'active' => $articles->where('status', 'active')->count(),
                'foundation' => $articles->where('status', 'foundation')->count(),
                'future' => $articles->where('status', 'future')->count(),
            ],
        ]);
    }
}
