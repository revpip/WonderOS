CREATE EXTENSION IF NOT EXISTS pgcrypto;

CREATE TABLE wonder_users (
    uuid UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email TEXT NOT NULL,
    display_name TEXT NOT NULL CHECK (btrim(display_name) <> ''),
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL CHECK (role IN ('viewer','researcher','editor','administrator')),
    status TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active','suspended')),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE UNIQUE INDEX wonder_users_email_ci_unique_idx ON wonder_users (lower(email));

CREATE TABLE wonder_sessions (
    uuid UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_uuid UUID NOT NULL REFERENCES wonder_users(uuid) ON DELETE CASCADE,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at TIMESTAMPTZ NOT NULL,
    revoked_at TIMESTAMPTZ NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CHECK (expires_at > created_at)
);

CREATE INDEX wonder_sessions_user_idx ON wonder_sessions(user_uuid);
CREATE INDEX wonder_sessions_active_idx ON wonder_sessions(token_hash, expires_at) WHERE revoked_at IS NULL;
