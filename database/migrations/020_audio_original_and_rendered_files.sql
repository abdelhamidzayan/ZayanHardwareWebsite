-- 020 — Preserve original recordings separately from the published trimmed WAV.
-- Apply once to an installation whose schema is at migration 019.
-- New installations receive the same columns from database/fresh_install.sql.
ALTER TABLE issues
    ADD COLUMN audio_original_path VARCHAR(255) NULL AFTER audio_path,
    ADD COLUMN audio_original_mime_type VARCHAR(100) NULL AFTER audio_original_path,
    ADD COLUMN audio_original_file_size BIGINT UNSIGNED NULL AFTER audio_original_mime_type;
