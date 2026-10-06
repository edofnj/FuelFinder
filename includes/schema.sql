-- FuelFinder — schema Postgres (account + metriche)
-- Applicato una tantum dal setup. L'app non lo esegue mai.

CREATE TABLE IF NOT EXISTS users (
    id             BIGSERIAL PRIMARY KEY,
    email          TEXT NOT NULL,
    password_hash  TEXT,                       -- vecchio login locale, non più usato (sempre NULL)
    zitadel_sub    TEXT,                       -- id utente su account.fmenegazzi.it
    session_version INTEGER NOT NULL DEFAULT 0, -- incrementata per invalidare le sessioni aperte
    is_admin       BOOLEAN NOT NULL DEFAULT FALSE,
    email_verified BOOLEAN NOT NULL DEFAULT FALSE,
    verify_token   TEXT,                       -- vecchia verifica email locale, non più usata
    verify_expires TIMESTAMPTZ,
    created_at     TIMESTAMPTZ NOT NULL DEFAULT now(),
    last_login     TIMESTAMPTZ
);
-- Email case-insensitive univoca (niente dipendenza da citext)
CREATE UNIQUE INDEX IF NOT EXISTS users_email_lower_uidx ON users (lower(email));
CREATE UNIQUE INDEX IF NOT EXISTS users_zitadel_sub_uidx ON users (zitadel_sub);

-- Garage veicoli server-side
CREATE TABLE IF NOT EXISTS vehicles (
    id         BIGSERIAL PRIMARY KEY,
    user_id    BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    nome       TEXT NOT NULL,
    tipo       TEXT NOT NULL,
    consumo    NUMERIC(5,2) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS vehicles_user_idx ON vehicles (user_id);

-- Log eventi unico per le metriche (visitor_hash anonimo, salt giornaliero)
CREATE TABLE IF NOT EXISTS events (
    id            BIGSERIAL PRIMARY KEY,
    ts            TIMESTAMPTZ NOT NULL DEFAULT now(),
    visitor_hash  TEXT,
    user_id       BIGINT,
    type          TEXT NOT NULL,
    page          TEXT,
    country       TEXT,
    fuel          TEXT,
    radius        INTEGER,
    mode          TEXT,
    results       INTEGER,
    ua_device     TEXT,
    ua_browser    TEXT,
    ua_os         TEXT,
    referrer_host TEXT,
    lang          TEXT,
    meta          JSONB
);
CREATE INDEX IF NOT EXISTS events_ts_idx ON events (ts);
CREATE INDEX IF NOT EXISTS events_type_idx ON events (type);
CREATE INDEX IF NOT EXISTS events_visitor_idx ON events (visitor_hash);
CREATE INDEX IF NOT EXISTS events_user_idx ON events (user_id);
