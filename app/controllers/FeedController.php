<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Models\DemoData;
use App\Services\AuthService;

final class FeedController extends Controller
{
    public function index(): Response
    {
        if (!AuthService::user()) return $this->redirect('/login');

        $category = $this->request->query['category'] ?? '';
        $slug     = is_string($category) ? strtolower($category) : '';

        $issues = DemoData::issues();
        if ($slug !== '') {
            $issues = array_values(array_filter(
                $issues,
                static fn (array $issue): bool => strtolower($issue['category']) === $slug
            ));
        }

        return $this->view('feed/index', [
            'title'          => 'الرئيسية',
            'active'         => 'home',
            'categories'     => DemoData::categories(),
            'activeCategory' => $slug,
            'issues'         => $issues,
        ]);
    }
}
