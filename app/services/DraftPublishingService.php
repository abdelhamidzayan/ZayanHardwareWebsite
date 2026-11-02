<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\App;
use App\Models\Database;
use App\Models\PostDraft;\nuse App\Services\AudioWavPolicy;
use PDO;
use Throwable;

final class DraftPublishingService
{
    public static function publish(
        string $draftId,
        int $userId,
        int $expectedRevision,
        ?callable $commitTransaction = null
    ): int {
        $pdo = Database::connection();
        $commit = $commitTransaction !== null
            ? \Closure::fromCallable($commitTransaction)
            : static function (PDO $connection): void { $connection->commit(); };
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
        $issueId = null;
        $commitStarted = false;
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
                'UPDATE issues SET audio_path = ?, audio_original_path = ?, audio_original_mime_type = ?,
                    audio_original_file_size = ?, audio_mime_type = ?, audio_file_size = ?,
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
                    if (empty($item['rendered_path']) || empty($item['rendered_mime_type'])) {
                        throw new \\DomainException('draft_audio_trim_required');
                    }
                    $renderedRelativeSource = ltrim(str_replace('\\\\', '/', (string) $item['rendered_path']), '/');
                    if ($renderedRelativeSource === '' || str_contains($renderedRelativeSource, '..')) {
                        throw new \\DomainException('draft_media_path_invalid');
                    }
                    $renderedSource = realpath($privateRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $renderedRelativeSource));
                    if ($renderedSource === false || !str_starts_with($renderedSource, $privateRoot . DIRECTORY_SEPARATOR) || !is_file($renderedSource)) {
                        throw new \\DomainException('draft_media_missing');
                    }
                    $renderedMeta = AudioWavPolicy::inspect($renderedSource);
                    if ((string) $item['rendered_mime_type'] !== 'audio/wav') {
                        throw new \\DomainException('draft_audio_render_invalid');
                    }
                    $renderedFilename = bin2hex(random_bytes(16)) . '-trimmed.wav';
                    $renderedDestination = $issueDirectory . DIRECTORY_SEPARATOR . $renderedFilename;
                    if (!rename($renderedSource, $renderedDestination)) {
                        throw new \\DomainException('draft_media_move_failed');
                    }
                    $publishedAudioPath = 'issues/' . $issueId . '/' . $renderedFilename;
                    $moved[] = ['from' => $renderedSource, 'to' => $renderedDestination];

                    $audioMeta = is_array($payload['audio'] ?? null) ? $payload['audio'] : [];
                    $sourceDuration = max(0, (int) ($audioMeta['duration_ms'] ?? 0));
                    $trimStart = max(0, (int) ($audioMeta['trim_start_ms'] ?? 0));
                    $trimEndRaw = $audioMeta['trim_end_ms'] ?? $sourceDuration;
                    $trimEnd = max($trimStart, (int) $trimEndRaw);
                    if ($sourceDuration < 100 || $trimEnd > $sourceDuration || $trimStart >= $trimEnd) {
                        throw new \\DomainException('draft_audio_trim_invalid');
                    }
                    $audioEditorState = is_array($item['editor_state'] ?? null)
                        ? $item['editor_state']
                        : json_decode((string) ($item['editor_state'] ?? ''), true);
                    if (!is_array($audioEditorState)
                        || ($audioEditorState['kind'] ?? null) !== 'audio-trim'
                        || (int) ($audioEditorState['trim_start_ms'] ?? -1) !== $trimStart
                        || (int) ($audioEditorState['trim_end_ms'] ?? -1) !== $trimEnd
                        || (int) ($audioEditorState['source_duration_ms'] ?? -1) !== $sourceDuration) {
                        throw new \\DomainException('draft_audio_trim_not_confirmed');
                    }
                    if (abs(($trimEnd - $trimStart) - $renderedMeta['duration_ms']) > 1500) {
                        throw new \\DomainException('draft_audio_render_duration_mismatch');
                    }
                    $audioUpdate->execute([
                        $publishedAudioPath,
                        $newRelative,
                        (string) $item['mime_type'],
                        (int) $item['file_size'],
                        'audio/wav',
                        $renderedMeta['file_size'],
                        $renderedMeta['duration_ms'],
                        $trimStart > 0 ? $trimStart : null,
                        $trimEnd,
                        $issueId,
                        $userId,
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
            $commitStarted = true;
            ($commit)($pdo);
            return $issueId;
        } catch (Throwable $error) {
            $rollbackConfirmed = !$commitStarted;
            if ($pdo->inTransaction()) {
                try {
                    $pdo->rollBack();
                    $rollbackConfirmed = true;
                } catch (Throwable $rollbackError) {
                    $rollbackConfirmed = false;
                    App::logException('CRITICAL', 'draft.publish_rollback_failed', $rollbackError, [
                        'draft_id' => $draftId,
                        'issue_id' => $issueId,
                        'user_id' => $userId,
                    ]);
                }
            }

            if ($rollbackConfirmed) {
                $restoreFailures = self::restoreMovedFiles($moved);
                if ($restoreFailures !== []) {
                    foreach ($moved as $move) {
                        self::recordRecoveryForAbsolutePath($move['from'], 'draft', null, 'draft_publish_restore_failed');
                        self::recordRecoveryForAbsolutePath($move['to'], 'issue', $issueId, 'draft_publish_orphan_after_rollback');
                    }
                    App::log('CRITICAL', 'Draft publishing rollback could not restore every media file; files were retained for reconciliation.', [
                        'draft_id' => $draftId,
                        'issue_id' => $issueId,
                        'restore_failures' => $restoreFailures,
                    ], 'audit');
                    throw new \DomainException('draft_publish_restore_incomplete', 0, $error);
                }
                self::removeEmptyIssueDirectory($createdDirectory);
                throw $error;
            }

            $state = $issueId !== null ? self::inspectPublishState($draftId, $userId, $issueId) : 'unknown';
            if ($state === 'committed') {
                $missing = [];
                foreach ($moved as $move) {
                    if (!is_file($move['to']) && is_file($move['from'])) {
                        @copy($move['from'], $move['to']);
                    }
                    if (!is_file($move['to'])) $missing[] = $move;
                }
                if ($missing === []) {
                    // Commit succeeded despite its lost acknowledgement.
                    // Return the ID so the UI cannot invite duplicate publishing.
                    return $issueId;
                }
                foreach ($missing as $move) {
                    self::recordRecoveryForAbsolutePath($move['to'], 'issue', $issueId, 'published_media_missing');
                    self::recordRecoveryForAbsolutePath($move['from'], 'draft', null, 'published_media_restore_source');
                }
                App::log('CRITICAL', 'Draft publish committed but issue media could not all be verified.', [
                    'draft_id' => $draftId,
                    'issue_id' => $issueId,
                    'missing_count' => count($missing),
                ], 'audit');
                throw new \DomainException('draft_publish_committed_media_incomplete', 0, $error);
            }

            // A snapshot showing the draft but not the issue is not sufficient
            // to prove rollback after a lost COMMIT acknowledgement; the server
            // may still be completing that commit. Treat it as unknown below.

            // If the commit outcome cannot be resolved, retain the issue-side
            // files and copy them back to the draft side. Reconciliation can
            // later remove whichever copy is not referenced by the DB.
            foreach ($moved as $move) {
                if (is_file($move['to']) && !is_file($move['from'])) {
                    $sourceDirectory = dirname($move['from']);
                    if (!is_dir($sourceDirectory)) @mkdir($sourceDirectory, 0750, true);
                    if (!@copy($move['to'], $move['from'])) {
                        App::log('CRITICAL', 'Draft publish ambiguous outcome could not copy media back to draft storage.', [
                            'draft_id' => $draftId,
                            'issue_id' => $issueId,
                        ], 'audit');
                    }
                }
                self::recordRecoveryForAbsolutePath($move['to'], 'issue', $issueId, 'draft_publish_commit_outcome_unknown');
                self::recordRecoveryForAbsolutePath($move['from'], 'draft', null, 'draft_publish_commit_outcome_unknown');
            }
            App::logException('CRITICAL', 'draft.publish_commit_outcome_unknown', $error, [
                'draft_id' => $draftId,
                'issue_id' => $issueId,
                'user_id' => $userId,
                'commit_started' => $commitStarted,
            ]);
            throw new \DomainException('draft_publish_outcome_unknown', 0, $error);
        }
    }

    private static function inspectPublishState(string $draftId, int $userId, int $issueId): string
    {
        try {
            $pdo = Database::newConnection();
            $issue = $pdo->prepare('SELECT id FROM issues WHERE id = ? AND user_id = ? LIMIT 1');
            $issue->execute([$issueId, $userId]);
            $issueExists = $issue->fetchColumn() !== false;

            $draft = $pdo->prepare('SELECT id FROM post_drafts WHERE id = ? AND user_id = ? LIMIT 1');
            $draft->execute([$draftId, $userId]);
            $draftExists = $draft->fetchColumn() !== false;

            if ($issueExists && !$draftExists) return 'committed';
        } catch (Throwable $error) {
            App::logException('CRITICAL', 'draft.publish_state_inspection_failed', $error, [
                'draft_id' => $draftId,
                'issue_id' => $issueId,
                'user_id' => $userId,
            ]);
        }
        return 'unknown';
    }

    private static function recordRecoveryForAbsolutePath(
        string $absolutePath,
        string $aggregateType,
        ?int $aggregateId,
        string $reason
    ): void {
        $root = rtrim(App::root('storage/private'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!str_starts_with($absolutePath, $root)) {
            App::log('CRITICAL', 'draft.publish_recovery_path_outside_storage', [
                'aggregate_type' => $aggregateType,
                'aggregate_id' => $aggregateId,
                'reason_code' => $reason,
            ], 'audit');
            return;
        }

        $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($absolutePath, strlen($root)));
        $validDraft = preg_match('#^drafts/[a-f0-9-]{36}/[a-z0-9._-]{1,120}$#i', $relative) === 1;
        $validIssue = preg_match('#^issues/[1-9][0-9]*/[a-z0-9._-]{1,120}$#i', $relative) === 1;
        if (($aggregateType === 'draft' && !$validDraft) || ($aggregateType === 'issue' && !$validIssue)) {
            App::log('CRITICAL', 'draft.publish_recovery_path_rejected', [
                'aggregate_type' => $aggregateType,
                'aggregate_id' => $aggregateId,
                'reason_code' => $reason,
            ], 'audit');
            return;
        }

        try {
            $lastErrorCode = str_contains($reason, 'commit_outcome_unknown') ? 'commit_unknown' : 'restore_failed';
            Database::newConnection()->prepare(
                "INSERT INTO storage_recovery_tasks
                    (aggregate_type, aggregate_id, file_path, file_path_hash, reason_code,
                     status, attempts, last_attempt_at, last_error_code)
                 VALUES (?, ?, ?, ?, ?, 'pending', 1, UTC_TIMESTAMP(), ?)
                 ON DUPLICATE KEY UPDATE
                    aggregate_type = VALUES(aggregate_type),
                    aggregate_id = VALUES(aggregate_id),
                    reason_code = VALUES(reason_code),
                    status = 'pending',
                    attempts = attempts + 1,
                    last_attempt_at = UTC_TIMESTAMP(),
                    last_error_code = VALUES(last_error_code),
                    completed_at = NULL"
            )->execute([
                $aggregateType,
                $aggregateId,
                $relative,
                hash('sha256', $relative),
                $reason,
                $lastErrorCode,
            ]);
        } catch (Throwable $error) {
            App::logException('CRITICAL', 'draft.publish_recovery_task_write_failed', $error, [
                'aggregate_type' => $aggregateType,
                'aggregate_id' => $aggregateId,
                'reason_code' => $reason,
            ]);
        }
    }

    private static function removeEmptyIssueDirectory(?string $directory): void
    {
        if ($directory === null || !is_dir($directory)) return;
        $entries = array_values(array_diff(scandir($directory) ?: [], ['.', '..']));
        if ($entries === []) @rmdir($directory);
    }

    /**
     * Restore moved draft files after a failed publish.
     * Failed restorations are reported to the caller; destination files are never deleted.
     *
     * @param array<int, array{from:string,to:string}> $moved
     * @return array<int, array{from:string,to:string,reason:string}>
     */
    public static function restoreMovedFiles(array $moved): array
    {
        $failures = [];
        foreach (array_reverse($moved) as $move) {
            $from = (string) ($move['from'] ?? '');
            $to = (string) ($move['to'] ?? '');
            if ($from === '' || $to === '') {
                $failures[] = ['from' => $from, 'to' => $to, 'reason' => 'invalid_move_record'];
                continue;
            }
            if (!is_file($to)) {
                if (!is_file($from)) {
                    $failures[] = ['from' => $from, 'to' => $to, 'reason' => 'both_paths_missing'];
                }
                continue;
            }
            if (!@rename($to, $from)) {
                $failures[] = ['from' => $from, 'to' => $to, 'reason' => 'restore_rename_failed'];
            }
        }
        return $failures;
    }
}
