<?php
function app_config(): array
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/config.php';
    }
    return $config;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = app_config();
    $path = $config['db_path'];
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA busy_timeout = 5000');

    init_db($pdo);
    return $pdo;
}

function init_db(PDO $pdo): void
{
    $schema = file_get_contents(__DIR__ . '/schema.sql');
    $pdo->exec($schema);
    ensure_schema_upgrades($pdo);
    seed_demo_data($pdo);
}


function ensure_schema_upgrades(PDO $pdo): void
{
    $columns = [];
    foreach ($pdo->query('PRAGMA table_info(nodes)')->fetchAll() as $col) {
        $columns[$col['name']] = true;
    }

    $missing = [
        'threats' => 'TEXT',
        'risk_scenarios' => 'TEXT',
        'risk_likelihood' => 'INTEGER',
        'risk_impact' => 'INTEGER',
        'risk_controls' => 'TEXT',
        'residual_risk' => 'TEXT',
    ];
    foreach ($missing as $name => $type) {
        if (!isset($columns[$name])) {
            $pdo->exec('ALTER TABLE nodes ADD COLUMN ' . $name . ' ' . $type);
        }
    }
}

function seed_demo_data(PDO $pdo): void
{
    $count = (int)$pdo->query('SELECT COUNT(*) FROM nodes')->fetchColumn();
    if ($count > 0) {
        return;
    }

    $now = gmdate('c');
    $nodes = [
        ['hardware', 'scsap8cp', 'Produkční SAP server', 'high', 'prod'],
        ['software', 'SAP ECC', 'Core SAP systém', 'critical', 'prod'],
        ['software', 'ALICE', 'Pojistný systém / funkcionalita v SAP', 'critical', 'prod'],
        ['data', 'Pojistné smlouvy', 'Data pojistných smluv a klientů', 'critical', 'prod'],
        ['process', 'Správa smluv', 'Proces správy pojistných smluv', 'critical', 'prod'],
        ['supplier', 'SV Informatik', 'Poskytovatel ICT služeb', 'high', 'prod'],
    ];

    $stmt = $pdo->prepare('INSERT INTO nodes (type, name, description, criticality, environment, confidentiality, integrity_level, availability, data_sensitivity, status, lifecycle_state, rto_hours, rpo_hours, mtd_hours, threats, risk_scenarios, risk_likelihood, risk_impact, risk_controls, residual_risk, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    foreach ($nodes as $n) {
        $stmt->execute([$n[0], $n[1], $n[2], $n[3], $n[4], 'high', 'high', 'high', $n[0] === 'data' ? 'private' : null, 'active', 'production', $n[3] === 'critical' ? 8 : 24, $n[3] === 'critical' ? 24 : 48, $n[3] === 'critical' ? 48 : 72, 'Výpadek, nedostupnost, ztráta nebo kompromitace služby/dat', 'Narušení podpůrného ICT aktiva může omezit dostupnost procesu nebo zpracování dat.', $n[3] === 'critical' ? 3 : 2, $n[3] === 'critical' ? 4 : 3, 'Zálohování, monitoring, provozní dohled, řízení změn', 'medium', $now, $now]);
    }

    $edges = [
        [1, 2, 'hosts', 'Server hostuje SAP ECC'],
        [2, 3, 'contains', 'SAP ECC obsahuje ALICE'],
        [3, 4, 'processes_data', 'ALICE zpracovává pojistné smlouvy'],
        [3, 5, 'supports_process', 'ALICE podporuje správu smluv'],
        [2, 6, 'provided_by', 'SAP provozně poskytuje SV Informatik'],
    ];
    $stmt = $pdo->prepare('INSERT INTO edges (source_node_id, target_node_id, type, description, criticality, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
    foreach ($edges as $e) {
        $stmt->execute([$e[0], $e[1], $e[2], $e[3], 'high', $now, $now]);
    }

    $positions = [
        [1, 1, 80, 150], [1, 2, 320, 150], [1, 3, 560, 150], [1, 4, 800, 60], [1, 5, 800, 240], [1, 6, 320, 360],
    ];
    $stmt = $pdo->prepare('INSERT OR REPLACE INTO view_node_positions (view_id, node_id, x, y) VALUES (?, ?, ?, ?)');
    foreach ($positions as $p) {
        $stmt->execute($p);
    }
}

function json_response($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === '' || $raw === false) {
        return [];
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        json_response(['ok' => false, 'error' => 'Invalid JSON body'], 400);
    }
    return $data;
}

function now_iso(): string
{
    return gmdate('c');
}

function log_change(PDO $pdo, string $action, string $entityType, ?int $entityId, $before, $after): void
{
    $stmt = $pdo->prepare('INSERT INTO change_log (action, entity_type, entity_id, before_json, after_json, created_at, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $action,
        $entityType,
        $entityId,
        $before === null ? null : json_encode($before, JSON_UNESCAPED_UNICODE),
        $after === null ? null : json_encode($after, JSON_UNESCAPED_UNICODE),
        now_iso(),
        'local-user',
    ]);
}
