-- Zayan Hardware: durable records for filesystem compensation/reconciliation failures.
-- This table stores paths and reason codes only; it never performs file operations.
-- Apply after 017. No existing content rows are modified or removed.

CREATE TABLE storage_recovery_tasks (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    aggregate_type VARCHAR(24) NOT NULL,
    aggregate_id BIGINT UNSIGNED NULL,
    file_path VARCHAR(500) NOT NULL,
    file_path_hash CHAR(64) NOT NULL,
    reason_code VARCHAR(80) NOT NULL,
    status ENUM('pending', 'completed', 'blocked') NOT NULL DEFAULT 'pending',
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    last_attempt_at DATETIME NULL,
    last_error_code VARCHAR(64) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_storage_recovery_file_hash (file_path_hash),
    KEY idx_storage_recovery_status_created (status, created_at),
    KEY idx_storage_recovery_aggregate (aggregate_type, aggregate_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TRIGGER trg_storage_recovery_tasks_no_delete
BEFORE DELETE ON storage_recovery_tasks
FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Storage recovery audit tasks must be retained';
