-- Admin-selected posts for the automatically rotating home-page carousel.
CREATE TABLE IF NOT EXISTS featured_home_issues (
    issue_id BIGINT UNSIGNED NOT NULL,
    selected_by BIGINT UNSIGNED NOT NULL,
    selected_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (issue_id),
    KEY idx_featured_home_selected_at (selected_at),
    CONSTRAINT fk_featured_home_issue FOREIGN KEY (issue_id) REFERENCES issues(id) ON DELETE CASCADE,
    CONSTRAINT fk_featured_home_admin FOREIGN KEY (selected_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
