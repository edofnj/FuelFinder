-- Account unico fmenegazzi (Zitadel): collegamento utente locale ↔ utente dell'account unico.
-- password_hash facoltativa (gli utenti dell'account unico non hanno password locale);
-- session_version: incrementata al collegamento per invalidare le sessioni aperte con la vecchia password.
ALTER TABLE users ADD COLUMN IF NOT EXISTS zitadel_sub text;
ALTER TABLE users ADD COLUMN IF NOT EXISTS session_version integer NOT NULL DEFAULT 0;
ALTER TABLE users ALTER COLUMN password_hash DROP NOT NULL;
CREATE UNIQUE INDEX IF NOT EXISTS users_zitadel_sub_uidx ON users (zitadel_sub);
