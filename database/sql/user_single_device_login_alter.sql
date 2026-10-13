-- User single-device login schema change (run manually — do not use php artisan migrate)
-- Stores the active session/token id for each user so older logins can be invalidated.

ALTER TABLE `users`
    ADD COLUMN `current_session_token` VARCHAR(100) NULL DEFAULT NULL;
