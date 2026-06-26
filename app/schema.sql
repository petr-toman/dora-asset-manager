PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS nodes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    type TEXT NOT NULL DEFAULT 'software',
    name TEXT NOT NULL,
    description TEXT,

    owner TEXT,
    business_owner TEXT,
    technical_owner TEXT,
    vendor_manufacturer TEXT,

    criticality TEXT,
    confidentiality TEXT,
    integrity_level TEXT,
    availability TEXT,

    rto_hours INTEGER,
    rpo_hours INTEGER,
    mtd_hours INTEGER,

    data_sensitivity TEXT,
    data_categories TEXT,

    environment TEXT,
    location TEXT,
    status TEXT,
    lifecycle_state TEXT,

    good_to_know TEXT,

    last_reviewed_at TEXT,
    review_frequency_months INTEGER,

    threats TEXT,
    risk_scenarios TEXT,
    risk_likelihood INTEGER,
    risk_impact INTEGER,
    risk_controls TEXT,
    residual_risk TEXT,

    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS edges (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    source_node_id INTEGER NOT NULL,
    target_node_id INTEGER NOT NULL,
    type TEXT NOT NULL,
    description TEXT,
    criticality TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (source_node_id) REFERENCES nodes(id) ON DELETE CASCADE,
    FOREIGN KEY (target_node_id) REFERENCES nodes(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_edges_source ON edges(source_node_id);
CREATE INDEX IF NOT EXISTS idx_edges_target ON edges(target_node_id);
CREATE INDEX IF NOT EXISTS idx_edges_type ON edges(type);
CREATE INDEX IF NOT EXISTS idx_nodes_type ON nodes(type);
CREATE INDEX IF NOT EXISTS idx_nodes_criticality ON nodes(criticality);

CREATE TABLE IF NOT EXISTS views (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    description TEXT,
    filter_json TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS view_node_positions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    view_id INTEGER NOT NULL,
    node_id INTEGER NOT NULL,
    x REAL NOT NULL,
    y REAL NOT NULL,
    width REAL,
    height REAL,
    visible INTEGER DEFAULT 1,
    collapsed INTEGER DEFAULT 0,
    UNIQUE(view_id, node_id),
    FOREIGN KEY (view_id) REFERENCES views(id) ON DELETE CASCADE,
    FOREIGN KEY (node_id) REFERENCES nodes(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS change_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    action TEXT NOT NULL,
    entity_type TEXT NOT NULL,
    entity_id INTEGER,
    before_json TEXT,
    after_json TEXT,
    created_at TEXT NOT NULL,
    created_by TEXT
);

INSERT OR IGNORE INTO views (id, name, description, filter_json, created_at, updated_at)
VALUES (1, 'Celková mapa', 'Výchozí pohled na celý graf aktiv a vazeb.', '{}', datetime('now'), datetime('now'));
