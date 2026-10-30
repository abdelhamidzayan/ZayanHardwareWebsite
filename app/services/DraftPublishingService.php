<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\App;
use App\Models\Database;
use App\Models\PostDraft;

final class DraftPublishingService
{
    public static function publish(string $draftId, int $userId, int $expectedRevision): int
    {
        $pdo = Database::connection();
        $draft = PostDraft::findForUser($draftId, $userId);
        if (!$draft) throw new \DomainException('draft_not_found');
        if ((int) $draft['revision'] !== $expectedRevision) throw new \DomainException('draft_revision_conflict');

        $payload = $draft['payload'];
        $type = (string) ($payload['content_type'] ?? 'technical_post');
        $mode = (string) ($payload['details_mode'] ?? 'text');
        $title = trim((string) ($payload['title'] ?? ''));
        $description = trim((string) ($payload['description'] ?? ''));
        $manufacturerId = filter_var($payload['manufacturer_id'] ?? null, FILTER_VALIDATE_INT);
        $modelId = filter_var($payload['device_model_id'] ?? null, FILTER_VALIDATE_INT);
        $category = trim((string) ($payload['category'] ?? ''));

        if (!in_array($type, ['technical_post', 'help_request'], true)
            || !in_array($mode, ['text', 'audio'], true)
            || mb_strlen($title) < 5 || mb_strlen($title) > 180
            || $manufacturerId === false || $manufacturerId < 1
            || $modelId === false || $modelId < 1) {
            throw new \DomainException('draft_fields_invalid');
        }
        if ($mode === 'text' && (mb_strlen($description) < 10 || mb_strlen($description) > 10000)) {
            throw new \DomainException('draft_text_invalid');
        }
        if ($mode === 'audio' && $description !== '') {
            throw new \DomainException('draft_mode_conflict');
        }
        if ($type === 'help_request' && ($category === '' || mb_strlen($category) > 40)) {
            throw new \DomainException('draft_help_category_invalid');
        }

        $steps = $payload['steps'] ?? [];
        if (!is_array($steps) || count($steps) > 50) throw new \DomainException('draft_steps_invalid');
        $normalizedSteps = [];
        foreach ($steps as $step) {
            if (!is_string($step)) continue;
            $step = trim($step);
            if ($step !== '') {
                if (mb_strlen($step) > 2000) throw new \DomainException('draft_steps_invalid');
                $normalizedSteps[] = $step;
            }
        }

        $pdo->beginTransaction();
        $moved = [];
        $createdDirectory = null;
        try {
            $lock = $pdo->prepare('SELECT revision FROM post_drafts WHERE id = ? AND user_id = ? FOR UPDATE');
            $lock->execute([$draftId, $userId]);
            $lockedRevision = $lock->fetchColumn();
            if ($lockedRevision === false || (int) $lockedRevision !== $expectedRevision) {
                throw new \DomainException('draft_revision_conflict');
            }

            $manufacturer = $pdo->prepare('SELECT id FROM device_manufacturers WHERE id = ? AND is_active = 1');
            $manufacturer->execute([(int) $manufacturerId]);
            if (!$manufacturer->fetchColumn()) throw new \DomainException('device_manufacturer_unavailable');

            $model = $pdo->prepare(
                'SELECT id, commercial_name, model_code FROM device_models
                 WHERE id = ? AND manufacturer_id = ? AND is_active = 1'
            );
            $model->execute([(int) $modelId, (int) $manufacturerId]);
            $modelRow = $model->fetch();
            if (!$modelRow) throw new \DomainException('device_model_unavailable');

            $mediaRows = $pdo->prepare(
                'SELECT id, media_uuid, media_kind, storage_path, rendered_path, mime_type, rendered_mime_type, file_size, editor_state,
                        sort_order, duration_ms
                 FROM post_draft_media WHERE draft_id = ? ORDER BY sort_order, id FOR UPDATE'
            );
            $mediaRows->execute([$draftId]);
            $media = $mediaRows->fetchAll();
            $audio = array_values(array_filter($media, static fn (array $item): bool => $item['media_kind'] === 'audio'));
            if (($mode === 'audio' && count($audio) !== 1) || ($mode === 'text' && count($audio) !== 0)) {
                throw new \DomainException('draft_audio_required_or_conflict');
            }
            if (count(array_filter($media, static fn (array $item): bool => in_array($item['media_kind'], ['image', 'video'], true))) > 8) {
                throw new \DomainException('draft_media_limit');
            }

            $deviceLabel = (string) $modelRow['commercial_name'];
            if (!empty($modelRow['model_code'])) $deviceLabel .= ' (' . (string) $modelRow['model_code'] . ')';
            $deviceLabel = mb_substr($deviceLabel, 0, 120);
            $stepsJson = $normalizedSteps
                ? json_encode($normalizedSteps, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
                : null;

            $insertIssue = $pdo->prepare(
                'INSERT INTO issues
                    (user_id, content_type, title, device_model, category, description, measurements,
                     manufacturer_id, device_model_id, details_mode, steps_json, status)
                 VALUES (?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, \'open\')'
            );
            $insertIssue->execute([
                $userId,
                $type,
                $title,
                $deviceLabel,
                $category !== '' ? $category : null,
                $mode === 'text' ? $description : '',
                (int) $manufacturerId,
                (int) $modelId,
                $mode,
                $stepsJson,
            ]);
            $issueId = (int) $pdo->lastInsertId();

            $privateRootPath = App::root('storage/private');
            if (!is_dir($privateRootPath) && !mkdir($privateRootPath, 0750, true) && !is_dir($privateRootPath)) {
                throw new \DomainException('draft_storage_unavailable');
            }
            $privateRoot = realpath($privateRootPath);
            if ($privateRoot === false) throw new \DomainException('draft_storage_unavailable');
            $issueDirectory = App::root('storage/private/issues/' . $issueId);
            if (!is_dir($issueDirectory) && !mkdir($issueDirectory, 0750, true) && !is_dir($issueDirectory)) {
                throw new \DomainException('draft_storage_unavailable');
            }
            $createdDirectory = $issueDirectory;

            $insertAttachment = $pdo->prepare(
                'INSERT INTO issue_attachments
                    (issue_id, uploaded_by, file_path, mime_type, file_size, is_inline,
                     original_file_path, original_mime_type, rendered_file_path, rendered_mime_type, editor_state, sort_order)
                 VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?)'
            );
            $audioUpdate = $pdo->prepare(
                'UPDATE issues SET audio_path = ?, audio_mime_type = ?, audio_file_size = ?,
                    audio_duration_ms = ?, audio_trim_start_ms = ?, audio_trim_end_ms = ?
                 WHERE id = ? AND user_id = ?'
            );

            foreach ($media as $item) {
                $relative = ltrim(str_replace('\\', '/', (string) $item['storage_path']), '/');
                if ($relative === '' || str_contains($relative, '..')) throw new \DomainException('draft_media_path_invalid');
                $source = realpath($privateRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative));
                if ($source === false || !str_starts_with($source, $privateRoot . DIRECTORY_SEPARATOR) || !is_file($source)) {
                    throw new \DomainException('draft_media_missing');
                }

                $extension = strtolower(pathinfo($source, PATHINFO_EXTENSION));
                if (!preg_match('/^[a-z0-9]{2,6}$/', $extension)) throw new \DomainException('draft_media_path_invalid');
                $filename = bin2hex(random_bytes(16)) . '.' . $extension;
                $destination = $issueDirectory . DIRECTORY_SEPARATOR . $filename;
                if (!rename($source, $destination)) throw new \DomainException('draft_media_move_failed');

                $newRelative = 'issues/' . $issueId . '/' . $filename;
                $moved[] = ['from' => $source, 'to' => $destination];

                $renderedRelative = null;
                $displayRelative = $newRelative;
                $displaySize = (int) $item['file_size'];
                $displayMime = (string) $item['mime_type'];
                $renderedMime = null;
                if ($item['media_kind'] === 'image' && !empty($item['rendered_path'])) {
                    $renderedSourceRelative = ltrim(str_replace('\\', '/', (string) $item['rendered_path']), '/');
                    if ($renderedSourceRelative === '' || str_contains($renderedSourceRelative, '..')) {
                        throw new \DomainException('draft_media_path_invalid');
                    }
                    $renderedSource = realpath($privateRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $renderedSourceRelative));
                    if ($renderedSource === false || !str_starts_with($renderedSource, $privateRoot . DIRECTORY_SEPARATOR) || !is_file($renderedSource)) {
                        throw new \DomainException('draft_media_missing');
                    }
                    $renderedExtension = strtolower(pathinfo($renderedSource, PATHINFO_EXTENSION));
                    if (!preg_match('/^[a-z0-9]{2,6}$/', $renderedExtension)) {
                        throw new \DomainException('draft_media_path_invalid');
                    }
                    $renderedFilename = bin2hex(random_bytes(16)) . '-preview.' . $renderedExtension;
                    $renderedDestination = $issueDirectory . DIRECTORY_SEPARATOR . $renderedFilename;
                    if (!rename($renderedSource, $renderedDestination)) {
                        throw new \DomainException('draft_media_move_failed');
                    }
                    $renderedRelative = 'issues/' . $issueId . '/' . $renderedFilename;
                    $displayRelative = $renderedRelative;
                    $displaySize = (int) filesize($renderedDestination);
                    $renderedMime = (string) ($item['rendered_mime_type'] ?? 'image/webp');
                    $displayMime = $renderedMime;
                    $moved[] = ['from' => $renderedSource, 'to' => $renderedDestination];
                }

                if ($item['media_kind'] === 'audio') {
                    $audioMeta = is_array($payload['audio'] ?? null) ? $payload['audio'] : [];
                    $duration = max(0, (int) ($audioMeta['duration_ms'] ?? $item['duration_ms'] ?? 0));
                    $trimStart = max(0, (int) ($audioMeta['trim_start_ms'] ?? 0));
                    $trimEndRaw = $audioMeta['trim_end_ms'] ?? null;
                    $trimEnd = $trimEndRaw === null ? null : max($trimStart, (int) $trimEndRaw);
                    if ($duration > 0) {
                        $trimStart = min($trimStart, $duration);
                        if ($trimEnd !== null) $trimEnd = min(max($trimEnd, $trimStart), $duration);
                    }
                    $audioUpdate->execute([
                        $newRelative, (string) $item['mime_type'], (int) $item['file_size'],
                        $duration > 0 ? $duration : null, $trimStart > 0 ? $trimStart : null,
                        $trimEnd, $issueId, $userId,
                    ]);
                    continue;
                }

                $editorState = $item['editor_state'];
                if ($editorState !== null && !is_string($editorState)) {
                    $editorState = json_encode($editorState, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                }
                $insertAttachment->execute([
                    $issueId, $userId, $displayRelative, $displayMime, $displaySize,
                    $newRelative, (string) $item['mime_type'], $renderedRelative, $renderedMime, $editorState, (int) $item['sort_order'],
                ]);
            }

            $pdo->prepare('DELETE FROM post_drafts WHERE id = ? AND user_id = ?')->execute([$draftId, $userId]);
            $pdo->commit();
            return $issueId;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            foreach (array_reverse($moved) as $move) {
                if (is_file($move['to'])) @rename($move['to'], $move['from']);
            }
            if ($createdDirectory !== null && is_dir($createdDirectory)) @rmdir($createdDirectory);
            throw $e;
        }
    }
}
