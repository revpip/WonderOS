CREATE TABLE relationship_types (
    type VARCHAR(80) PRIMARY KEY,
    inverse_type VARCHAR(80) NOT NULL,
    label VARCHAR(120) NOT NULL,
    inverse_label VARCHAR(120) NOT NULL,
    description TEXT NOT NULL,
    symmetric BOOLEAN NOT NULL DEFAULT FALSE,
    status VARCHAR(20) NOT NULL DEFAULT 'active' CHECK (status IN ('active','deprecated')),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CHECK ((symmetric = TRUE AND type = inverse_type) OR symmetric = FALSE)
);

INSERT INTO relationship_types (type, inverse_type, label, inverse_label, description, symmetric) VALUES
('associated_with','associated_with','associated with','associated with','A broad, non-causal association used only when a more precise type is unavailable.',TRUE),
('roosts_in','hosts_roost_of','roosts in','hosts roost of','The source regularly rests or shelters within the target.',FALSE),
('hosts_roost_of','roosts_in','hosts roost of','roosts in','The source provides a regular resting or shelter location for the target.',FALSE),
('symbolises','symbolised_by','symbolises','symbolised by','The source represents the target within a stated cultural or editorial context.',FALSE),
('symbolised_by','symbolises','symbolised by','symbolises','The source is represented by the target within a stated cultural or editorial context.',FALSE),
('located_in','contains_location','located in','contains location','The source is geographically situated within the target.',FALSE),
('contains_location','located_in','contains location','located in','The source geographically contains the target.',FALSE),
('part_of','has_part','part of','has part','The source forms a constituent part of the target.',FALSE),
('has_part','part_of','has part','part of','The source contains the target as a constituent part.',FALSE),
('influenced','influenced_by','influenced','influenced by','The source materially shaped the target.',FALSE),
('influenced_by','influenced','influenced by','influenced','The source was materially shaped by the target.',FALSE);
