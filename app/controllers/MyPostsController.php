<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Models\Issue;
use App\Services\AuthService;

final class MyPostsController extends Controller
{
    public function index(): Response
    {
        $user = AuthService::user();
        if (!$user) return $this->redirect('/login');

        $type = $this->request->query['type'] ?? '';
        $status = $this->request->query['status'] ?? '';
        $fromDate = $this->request->query['from'] ?? '';
        $toDate = $this->request->query['to'] ?? '';
        $type = is_string($type) ? $type : '';
        $status = is_string($status) ? $status : '';

        $validDate = static function (mixed $value): string {
            if (!is_string($value) || !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $value)) return '';
            [$year, $month, $day] = array_map('intval', explode('-', $value));
            return checkdate($month, $day, $year) ? $value : '';
        };
        $fromDate = $validDate($fromDate);
        $toDate = $validDate($toDate);
        if ($fromDate !== '' && $toDate !== '' && $fromDate > $toDate) {
            $fromDate = '';
            $toDate = '';
        }

        return $this->view('my-posts/index', [
            'title' => 'مشاركاتي',
            'active' => 'profile',
            'currentUser' => $user,
            'items' => Issue::myPosts((int) $user['id'], $type, $status, $fromDate, $toDate),
            'selectedType' => $type,
            'selectedStatus' => $status,
            'selectedFromDate' => $fromDate,
            'selectedToDate' => $toDate,
        ]);
    }
}
