-- Persistent atomic rate-limit buckets, shared by all PHP workers.
CREATE TABLE IF NOT EXISTS rate_limit_buckets (
    bucket VARCHAR(190) NOT NULL,
    window_started_at DATETIME NOT NULL,
    hit_count INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (bucket),
    KEY idx_rate_limit_buckets_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
