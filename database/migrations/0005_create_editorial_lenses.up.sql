CREATE TABLE editorial_lenses (
    slug TEXT PRIMARY KEY CHECK (slug ~ '^[a-z0-9]+(-[a-z0-9]+)*$'),
    name TEXT NOT NULL CHECK (btrim(name) <> ''),
    description TEXT NOT NULL CHECK (btrim(description) <> ''),
    relationship_types TEXT[] NOT NULL DEFAULT '{}',
    relationship_statuses TEXT[] NOT NULL DEFAULT '{}',
    minimum_confidence NUMERIC(3,2) NOT NULL DEFAULT 0 CHECK (minimum_confidence BETWEEN 0 AND 1),
    status TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active','deprecated')),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

INSERT INTO editorial_lenses (slug,name,description,relationship_types,relationship_statuses,minimum_confidence) VALUES
('symbolism','Symbolism','Symbolic and associative meanings across the knowledge graph.',ARRAY['symbolises','associated_with'],ARRAY[]::TEXT[],0.50),
('geography','Geography','Location, containment and place-based relationships.',ARRAY['located_in'],ARRAY[]::TEXT[],0.50),
('historic-influence','Historic Influence','Influence, inheritance and historical development.',ARRAY['influenced','part_of'],ARRAY[]::TEXT[],0.60),
('approved-knowledge','Approved Knowledge','Only relationships that have completed editorial approval.',ARRAY[]::TEXT[],ARRAY['approved'],0.00),
('high-confidence','High Confidence','Only relationships with strong editorial confidence.',ARRAY[]::TEXT[],ARRAY[]::TEXT[],0.80),
('mythology','Mythology','Symbolic, associative and influence relationships useful for mythic interpretation.',ARRAY['symbolises','associated_with','influenced'],ARRAY[]::TEXT[],0.55),
('travel','Travel','Place, containment and association relationships useful for destination discovery.',ARRAY['located_in','part_of','associated_with'],ARRAY[]::TEXT[],0.55);
