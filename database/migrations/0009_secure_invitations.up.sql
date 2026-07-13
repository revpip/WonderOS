CREATE TABLE wonder_user_invitations (
    uuid UUID PRIMARY KEY,
    email TEXT NOT NULL,
    display_name TEXT NOT NULL,
    role TEXT NOT NULL CHECK (role IN ('viewer','researcher','editor','administrator')),
    token_hash CHAR(64) NOT NULL UNIQUE,
    invited_by UUID NOT NULL REFERENCES wonder_users(uuid),
    expires_at TIMESTAMPTZ NOT NULL,
    accepted_at TIMESTAMPTZ NULL,
    revoked_at TIMESTAMPTZ NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CHECK (btrim(email) <> ''),
    CHECK (btrim(display_name) <> '')
);

CREATE UNIQUE INDEX wonder_user_invitations_pending_email_unique
ON wonder_user_invitations (lower(email))
WHERE accepted_at IS NULL AND revoked_at IS NULL;

CREATE INDEX wonder_user_invitations_expiry_idx ON wonder_user_invitations (expires_at);
CREATE INDEX wonder_user_invitations_inviter_idx ON wonder_user_invitations (invited_by, created_at DESC);