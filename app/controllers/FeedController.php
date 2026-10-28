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

        // The member feed must reflect persisted database records, not Figma demo fixtures.
        $issues = Issue::feed();

        foreach ($issues as &$issue) {
            $name = trim((string) ($issue['author'] ?? ''));
            $issue['initials'] = $name !== '' ? mb_substr($name, 0, 2, 'UTF-8') : '؟';
            $issue['expert'] = ($issue['tech_level'] ?? '') === 'board'
                || in_array($issue['role'] ?? '', ['admin', 'moderator'], true);
            $issue['role'] = match ($issue['role'] ?? '') {
                'admin' => 'إدارة المنصة',
                'moderator' => 'مشرف فني',
                default => ($issue['tech_level'] ?? '') === 'board' ? 'فني بورد' : 'فني صيانة',
            };

            $createdAt = strtotime((string) ($issue['created_at'] ?? '') . ' UTC');
            $age = $createdAt === false ? 0 : max(0, time() - $createdAt);
            if ($age < 60) {
                $issue['time'] = 'منذ لحظات';
            } elseif ($age < 3600) {
                $issue['time'] = 'منذ ' . max(1, (int) floor($age / 60)) . ' دقيقة';
            } elseif ($age < 86400) {
                $issue['time'] = 'منذ ' . max(1, (int) floor($age / 3600)) . ' ساعة';
            } else {
                $issue['time'] = 'منذ ' . max(1, (int) floor($age / 86400)) . ' يوم';
            }

            $description = trim(strip_tags((string) ($issue['description'] ?? '')));
            $issue['excerpt'] = mb_strimwidth($description, 0, 180, '…', 'UTF-8');
            $issue['solved'] = ($issue['status'] ?? '') === 'resolved';
            $issue['comments'] = (int) ($issue['comments'] ?? 0);
            $issue['audio'] = '';
            $issue['audio_src'] = '';
        }
        unset($issue);

        return $this->view('feed/index', [
            'title'  => 'الرئيسية',
            'active' => 'home',
            'issues' => $issues,
        ]);
    }
}
