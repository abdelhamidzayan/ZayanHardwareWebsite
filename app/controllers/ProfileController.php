<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Models\User;
use App\Services\AuthService;

final class ProfileController extends Controller
{
    private const ROLES = ['member' => 'فنّي', 'moderator' => 'مشرف', 'admin' => 'مدير'];
    private const EXPERIENCE = [
        'lt1' => 'أقل من سنة',
        '1-3' => 'من 1 إلى 3 سنوات',
        '3-5' => 'من 3 إلى 5 سنوات',
        '5-10' => 'من 5 إلى 10 سنوات',
        'gt10' => 'أكثر من 10 سنوات',
    ];

    public function show(string $username): Response
    {
        $username = strtolower($username);
        if (preg_match('/^[a-z0-9_]{3,20}$/', $username) !== 1) return $this->notFound();

        $profile = User::findProfileByUsername($username);
        if ($profile === null || $profile['account_status'] !== 'active') return $this->notFound();

        $me = AuthService::user();
        $isOwner = $me !== null && (int) $me['id'] === (int) $profile['id'];
        $words = preg_split('/\s+/u', trim($profile['display_name'])) ?: [''];
        $initials = mb_strtoupper(mb_substr($words[0], 0, 1) . (isset($words[1]) ? mb_substr($words[1], 0, 1) : ''));
        $isBoard = ($profile['tech_level'] ?? '') === 'board';

        return $this->view('profile/show', [
            'title' => $profile['display_name'],
            'active' => $isOwner ? 'profile' : '',
            'showHeader' => false,
            'skillRatings' => User::skillRatings((int) $profile['id']),
            'profile' => [
                'display_name' => $profile['display_name'],
                'username' => $profile['username'],
                'initials' => $initials,
                'role_label' => self::ROLES[$profile['role']] ?? 'فنّي',
                'device' => ['iphone' => 'iPhone', 'android' => 'Android'][$profile['device_focus'] ?? ''] ?? '',
                'level' => ['fast' => 'فني فاست', 'board' => 'فني بورد'][$profile['tech_level'] ?? ''] ?? '',
                'level_verified' => (int) ($profile['level_verified'] ?? 0),
                'experience' => $isBoard ? (self::EXPERIENCE[$profile['board_experience'] ?? ''] ?? '') : '',
                'workshop' => (string) ($profile['workshop_name'] ?? ''),
                'joined' => substr((string) $profile['created_at'], 0, 10),
                'is_owner' => $isOwner,
            ],
        ]);
    }
}
