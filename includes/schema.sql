-- FuelFinder — schema Postgres (account + metriche)
-- Applicato una tantum dal setup. L'app non lo esegue mai.

CREATE TABLE IF NOT EXISTS users (
    id             BIGSERIAL PRIMARY KEY,
    email          TEXT NOT NULL,
    password_hash  TEXT NOT NULL,
    is_admin       BOOLEAN NOT NULL DEFAULT FALSE,
    email_verified BOOLEAN NOT NULL DEFAULT FALSE,
    verify_token   TEXT,
    verify_expires TIMESTAMPTZ,
    created_at     TIMESTAMPTZ NOT NULL DEFAULT now(),
    last_login     TIMESTAMPTZ
);
-- Email case-insensitive univoca (niente dipendenza da citext)
CREATE UNIQUE INDEX IF NOT EXISTS users_email_lower_uidx ON users (lower(email));

-- Token "ricordami" persistente (sopravvive ai restart del container)
CREATE TABLE IF NOT EXISTS auth_tokens (
    id         BIGSERIAL PRIMARY KEY,
    user_id    BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token_hash TEXT NOT NULL,
    expires_at TIMESTAMPTZ NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS auth_tokens_hash_idx ON auth_tokens (token_hash);
CREATE INDEX IF NOT EXISTS auth_tokens_user_idx ON auth_tokens (user_id);

-- Reset password (in DB solo l'hash del token, scadenza 1 ora)
CREATE TABLE IF NOT EXISTS password_resets (
    id         BIGSERIAL PRIMARY KEY,
    user_id    BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token_hash TEXT NOT NULL,
    expires_at TIMESTAMPTZ NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS password_resets_hash_idx ON password_resets (token_hash);

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

-- Tentativi di login per il rate-limit (ip_hash anonimo)
CREATE TABLE IF NOT EXISTS login_attempts (
    id      BIGSERIAL PRIMARY KEY,
    ip_hash TEXT NOT NULL,
    email   TEXT,
    ts      TIMESTAMPTZ NOT NULL DEFAULT now(),
    success BOOLEAN NOT NULL DEFAULT FALSE
);
CREATE INDEX IF NOT EXISTS login_attempts_iphash_ts_idx ON login_attempts (ip_hash, ts);
