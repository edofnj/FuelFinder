-- Solo account unico fmenegazzi: niente più login, reset password, verifica email e "ricordami" locali.
-- Le password degli account non ancora collegati sono già su account.fmenegazzi.it (import del 06/10/2026):
-- le copie locali e le tabelle del vecchio login non servono più.
BEGIN;
UPDATE users SET password_hash = NULL, verify_token = NULL, verify_expires = NULL;
DROP TABLE IF EXISTS auth_tokens, password_resets, login_attempts;
COMMIT;
