<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Models\DemoData;
use App\Models\Database;
use App\Services\AuthService;

final class FeedController extends Controller
{
    public function index(): Response
    {
        if (!AuthService::user()) return $this->redirect('/login');

        $category = $this->request->query['category'] ?? '';
        $slug     = is_string($category) ? strtolower($category) : '';

        $issues = DemoData::issues();

        $featuredStmt = Database::connection()->query(
            "SELECT i.id, i.title, i.device_model, i.category, i.description, i.created_at,
                    u.display_name AS author_name,
                    (SELECT COUNT(*) FROM issue_replies r WHERE r.issue_id = i.id AND r.deleted_at IS NULL) AS reply_count
             FROM featured_home_issues fp
             JOIN issues i ON i.id = fp.issue_id
             JOIN users u ON u.id = i.user_id
             ORDER BY RAND()
             LIMIT 12"
        );
        $featuredIssues = $featuredStmt->fetchAll();
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
            'featuredIssues' => $featuredIssues,
        ]);
    }
}
