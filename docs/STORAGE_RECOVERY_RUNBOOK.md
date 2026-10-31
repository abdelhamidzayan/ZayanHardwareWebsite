# Storage Recovery Task — Safe Triage Runbook

## Purpose and limits

Migration 018 creates `storage_recovery_tasks`. This table is a durable **journal of filesystem recovery situations**. It records relative paths, reason codes, attempts, and status; it does not run a worker, restore files, or delete files.

There is currently **no automatic recovery worker**. Treat every task as an investigation record, not an instruction to unlink a file.

## Non-negotiable safety rules

- Never delete a technical attachment, published file, draft source, audio file, or help attachment solely because a task says `pending`.
- Never assume a database rollback succeeded when a commit outcome is ambiguous.
- Never follow a symlink while checking a recovery path. A draft UUID folder symlink is an explicit rejection condition.
- Never treat an absent current attachment reference as enough proof that a file is disposable. Review its aggregate, historical content/edit history, and backup state first.
- Do not run `find ... -delete`, `rm`, bulk-cleanup scripts, or a guessed background worker over `storage/private`.
- Keep all inspections read-only until a reviewed, tested reconciliation procedure is implemented and approved.

## Task types and path families

| `aggregate_type` | Expected relative path | Typical reasons |
|---|---|---|
| `issue` | `issues/{issue_id}/{filename}` | `commit_outcome_unknown`, `compensation_failed`, `draft_publish_orphan_after_rollback`, `draft_publish_commit_outcome_unknown`, `published_media_missing` |
| `draft` | `drafts/{uuid}/{filename}` or `drafts/{uuid}/__directory__` | `draft_media_cleanup_failed`, `draft_media_storage_root_unavailable`, `draft_media_directory_unavailable`, `draft_media_directory_rejected`, `draft_publish_restore_failed`, `draft_publish_commit_outcome_unknown` |
| `help_request` | `help-requests/{request_id}/{filename}` | `commit_outcome_unknown`, `compensation_failed` |

The `__directory__` suffix is a directory-cleanup sentinel, not a media filename. More reason codes may be added by future code; inspect their implementation before interpreting them.

## 1. List pending and blocked tasks

Run against the intended database using an account with read-only access where possible:

```sql
SELECT
    id,
    aggregate_type,
    aggregate_id,
    file_path,
    file_path_hash,
    SHA2(file_path, 256) AS calculated_path_hash,
    reason_code,
    status,
    attempts,
    last_attempt_at,
    last_error_code,
    created_at,
    completed_at
FROM storage_recovery_tasks
WHERE status IN ('pending', 'blocked')
ORDER BY created_at, id;
```

Confirm the stored `file_path_hash` equals `SHA2(file_path, 256)`. A mismatch is a metadata-integrity problem; do not construct a filesystem path from that row.

## 2. Check database references before examining files

For each `file_path`, use read-only queries to check the exact path in every applicable storage reference:

```sql
SELECT 'issue_attachments.file_path' AS reference_source, id
FROM issue_attachments WHERE file_path = 'issues/123/example.jpg'
UNION ALL
SELECT 'issue_attachments.original_file_path', id
FROM issue_attachments WHERE original_file_path = 'issues/123/example.jpg'
UNION ALL
SELECT 'issue_attachments.rendered_file_path', id
FROM issue_attachments WHERE rendered_file_path = 'issues/123/example.jpg'
UNION ALL
SELECT 'issues.audio_path', id
FROM issues WHERE audio_path = 'issues/123/example.jpg'
UNION ALL
SELECT 'post_draft_media.storage_path', id
FROM post_draft_media WHERE storage_path = 'issues/123/example.jpg'
UNION ALL
SELECT 'post_draft_media.rendered_path', id
FROM post_draft_media WHERE rendered_path = 'issues/123/example.jpg'
UNION ALL
SELECT 'help_request_attachments.file_path', id
FROM help_request_attachments WHERE file_path = 'issues/123/example.jpg';
```

Replace the example path with the exact validated relative path. The query intentionally does not filter out soft-deleted or historical rows. A reference in any row means the file must be preserved. For paths belonging to a different aggregate, the same path columns still need to be checked; do not limit inspection solely to the row's `aggregate_type`.

Also inspect the relevant aggregate and historical/edit records before deciding a path is unreferenced. A task's `aggregate_id` is a clue, not proof of ownership or proof that a file can be removed.

## 3. Inspect the filesystem without following links

The private storage root is `storage/private/`. Use a read-only file listing/stat operation and verify:

- the path is relative and contains no `..` segment or absolute prefix;
- the canonical path remains strictly below the real `storage/private` root;
- every parent directory is the expected aggregate directory;
- the file is not a symlink and is a regular file;
- a draft path's UUID directory is not itself a symlink;
- the file's size and checksum are recorded in the recovery notes before any further action.

If the storage root is missing, inaccessible, outside the expected root, or a symlink boundary is detected, stop and leave the task unresolved.

For a `drafts/{uuid}/__directory__` task, inspect the directory itself and all its entries. Do not treat the sentinel as a file path and do not remove the directory as a shortcut.

## 4. Triage decision

| Database reference | File state | Safe decision |
|---|---|---|
| Referenced | Present and contained under private root | Preserve the file. Investigate why the task remains pending; do not unlink. |
| Referenced | Missing | Treat as a possible content-loss incident. Restore from a verified private-media backup if available; keep the task open until the reference is healthy. |
| No reference found | Present | Preserve it. Check historical/edit records and backup state; absence of a direct reference is not deletion approval. |
| No reference found | Missing | Record the evidence and investigate the original transaction outcome. Do not infer that all related content is safe to discard. |
| Any state | Hash mismatch, symlink, unexpected path, inaccessible root, or ambiguous database state | Stop; keep unresolved and escalate for manual review. |

## 5. Status updates

Do not manually change `status`, `attempts`, `completed_at`, or delete rows as part of ad-hoc triage. The application code has narrowly scoped completion behavior for draft cleanup, but there is no general-purpose reconciliation command yet.

A future reconciliation tool must:
1. acquire a per-task lock or use a transaction with a conditional status transition;
2. verify the path hash and aggregate path contract;
3. inspect every database reference and historical-content dependency;
4. verify the canonical filesystem boundary and reject symlinks;
5. preserve all referenced technical files;
6. record the exact action, error code, timestamp, and operator/audit identity;
7. be idempotent and have integration tests for missing roots, symlinks, DB connection failure, concurrent attempts, and ambiguous commits.

Until that tool has been reviewed and tested, retain the file and task rather than guessing.

## Current implementation state

- Migration 018 creates the journal table and a trigger preventing physical deletion of recovery-task rows.
- `ContentWriteService`, `DraftPublishingService`, `PostDraft`, and `HelpRequestMediaService` write recovery tasks through separate database connections so the journal can survive a failed content transaction.
- `PostDraft` has specific cleanup paths that can complete a draft cleanup task after its guarded cleanup check succeeds. This is not a general worker and must not be generalized without tests.
- No Phase 1 claim is made that all pending tasks have been reconciled or that an automated cleanup worker exists.
