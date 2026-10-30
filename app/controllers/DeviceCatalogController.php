<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Response;
use App\Models\DeviceCatalog;
use App\Services\AuthService;

final class DeviceCatalogController extends Controller
{
    public function manufacturers(): Response
    {
        if (!AuthService::user()) return Response::json(['error' => 'unauthenticated'], 401);
        return Response::json(['manufacturers' => DeviceCatalog::manufacturers()]);
    }

    public function models(): Response
    {
        if (!AuthService::user()) return Response::json(['error' => 'unauthenticated'], 401);
        $manufacturerId = filter_var($this->request->query['manufacturer_id'] ?? null, FILTER_VALIDATE_INT);
        $query = $this->request->query['q'] ?? '';
        if ($manufacturerId === false || $manufacturerId === null || !is_string($query) || mb_strlen(trim($query)) < 1) {
            return Response::json(['models' => []]);
        }
        return Response::json(['models' => DeviceCatalog::searchModels((int) $manufacturerId, trim($query))]);
    }

    public function suggest(): Response
    {
        $user = AuthService::user();
        if (!$user) return Response::json(['error' => 'unauthenticated'], 401);
        if (!Csrf::verify($this->request->input('_token'))) return Response::json(['error' => 'csrf_failed'], 419);

        $manufacturerId = filter_var($this->request->input('manufacturer_id'), FILTER_VALIDATE_INT);
        $commercialName = trim($this->string('commercial_name'));
        $modelCode = $this->string('model_code');
        $aliases = $this->string('aliases');
        $note = $this->string('note');

        if ($manufacturerId === false || $manufacturerId < 1
            || mb_strlen($commercialName) < 2 || mb_strlen($commercialName) > 140
            || mb_strlen($modelCode) > 120 || mb_strlen($aliases) > 500
            || mb_strlen($note) > 500) {
            return Response::json(['error' => 'validation_failed', 'message' => 'راجع اسم الجهاز ورقم الموديل والحقول الاختيارية.'], 422);
        }

        try {
            $suggestionId = DeviceCatalog::submitSuggestion(
                (int) $manufacturerId,
                (int) $user['id'],
                $commercialName,
                trim($modelCode),
                trim($aliases),
                trim($note)
            );
        } catch (\DomainException $error) {
            $message = match ($error->getMessage()) {
                'manufacturer_unavailable' => 'الشركة المصنعة غير متاحة حاليًا.',
                'model_already_exists' => 'الموديل موجود بالفعل في قائمة الأجهزة.',
                'suggestion_already_pending' => 'تم إرسال اقتراح مطابق من قبل وهو في انتظار المراجعة.',
                default => 'تعذر إرسال اقتراح الموديل.',
            };
            return Response::json(['error' => $error->getMessage(), 'message' => $message], 422);
        }

        return Response::json([
            'submitted' => true,
            'suggestion_id' => $suggestionId,
            'message' => 'تم إرسال اقتراح الموديل للمراجعة. هيظهر في الاختيارات بعد موافقة الإدارة.',
        ], 201);
    }

    public function adminIndex(): Response
    {
        $user = AuthService::user();
        if (!$user || ($user['role'] ?? '') !== 'admin') return $this->redirect('/login');

        return $this->view('admin/device-models', [
            'title' => 'مراجعة موديلات الأجهزة',
            'active' => '',
            'currentUser' => $user,
            'suggestions' => DeviceCatalog::pendingSuggestions(),
        ]);
    }

    public function moderate(int $id): Response
    {
        $user = AuthService::user();
        if (!$user || ($user['role'] ?? '') !== 'admin') return $this->redirect('/login');
        if (!Csrf::verify($this->request->input('_token'))) {
            $this->flash('error', 'انتهت صلاحية الصفحة. أعد المحاولة.');
            return $this->redirect('/admin/device-model-suggestions');
        }

        $decision = $this->request->input('decision');
        if (!in_array($decision, ['approve', 'reject'], true)) {
            $this->flash('error', 'الاختيار غير صالح.');
            return $this->redirect('/admin/device-model-suggestions');
        }
        try {
            DeviceCatalog::moderateSuggestion($id, (int) $user['id'], $decision === 'approve');
            $this->flash('success', $decision === 'approve' ? 'تم اعتماد الموديل وإضافته للاختيارات.' : 'تم رفض اقتراح الموديل.');
        } catch (\DomainException $error) {
            $message = $error->getMessage() === 'model_already_exists'
                ? 'الموديل موجود بالفعل. لم تتم إضافته مرة أخرى.'
                : 'الاقتراح غير موجود أو تمت مراجعته بالفعل.';
            $this->flash('error', $message);
        }
        return $this->redirect('/admin/device-model-suggestions');
    }

    private function string(string $key): string
    {
        $value = $this->request->input($key, '');
        return is_string($value) ? $value : '';
    }
}
