<?php
function app_config(): array
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/config.php';
    }
    return $config;
}

function app_data_dir(): string
{
    $config = app_config();
    $base = $config['data_dir'] ?? null;
    if (!$base) {
        $legacyPath = $config['db_path'] ?? (getenv('DORA_DB_PATH') ?: __DIR__ . '/../data/assets.sqlite');
        $base = dirname($legacyPath);
    }
    return rtrim($base, '/');
}

function models_dir(): string
{
    return app_data_dir() . '/models';
}

function deleted_models_dir(): string
{
    return app_data_dir() . '/deleted';
}

function current_model_file(): string
{
    return app_data_dir() . '/current_model.txt';
}

function demo_model_name(): string
{
    return 'demo.sqlite';
}

function sanitize_model_name(string $name): string
{
    $name = trim($name);
    $name = preg_replace('/\.sqlite$/i', '', $name);
    $name = preg_replace('/[^A-Za-z0-9_-]+/', '-', $name);
    $name = trim($name, '-_');
    if ($name === '') {
        json_response(['ok' => false, 'error' => 'Neplatný název modelu'], 400);
    }
    return $name . '.sqlite';
}

function is_valid_model_file_name(string $name): bool
{
    return (bool)preg_match('/^[A-Za-z0-9_-]+\.sqlite$/', trim(basename($name)));
}

function validate_model_file_name(string $name): string
{
    $name = trim(basename($name));
    if (!is_valid_model_file_name($name)) {
        json_response(['ok' => false, 'error' => 'Neplatný název modelu'], 400);
    }
    return $name;
}

function model_path(string $name): string
{
    return models_dir() . '/' . validate_model_file_name($name);
}

function safe_model_path(string $name): ?string
{
    $name = trim(basename($name));
    if (!is_valid_model_file_name($name)) {
        return null;
    }
    return models_dir() . '/' . $name;
}

function ensure_app_dirs(): void
{
    foreach ([app_data_dir(), models_dir(), deleted_models_dir()] as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }
}

function existing_model_files(): array
{
    $files = glob(models_dir() . '/*.sqlite') ?: [];
    return array_values(array_filter($files, 'is_file'));
}

function choose_fallback_model(): string
{
    $files = existing_model_files();
    if (!$files) {
        return demo_model_name();
    }

    $nonDemo = array_values(array_filter($files, fn($f) => basename($f) !== demo_model_name()));
    $pool = $nonDemo ?: $files;
    usort($pool, function ($a, $b) {
        $ta = filemtime($a) ?: 0;
        $tb = filemtime($b) ?: 0;
        if ($ta === $tb) return strnatcasecmp(basename($b), basename($a));
        return $tb <=> $ta;
    });
    return basename($pool[0]);
}

function ensure_model_environment(): void
{
    ensure_app_dirs();

    $legacyPath = app_config()['db_path'] ?? (app_data_dir() . '/assets.sqlite');
    $models = existing_model_files();
    $wasEmpty = count($models) === 0;

    // Backward compatibility: when an older single-file DB exists and no model exists yet,
    // keep it as a normal model document called assets.sqlite.
    if ($wasEmpty && $legacyPath && file_exists($legacyPath)) {
        copy($legacyPath, models_dir() . '/assets.sqlite');
        $models = existing_model_files();
    }

    // Demo is created for a fresh models directory. If the user later deletes it while
    // other models exist, we do not recreate it on every request.
    $demoPath = models_dir() . '/' . demo_model_name();
    if ($wasEmpty && !file_exists($demoPath)) {
        init_sqlite_file($demoPath, true);
        $models = existing_model_files();
    }

    if (!$models) {
        init_sqlite_file($demoPath, true);
        $models = existing_model_files();
    }

    $current = null;
    if (file_exists(current_model_file())) {
        $current = trim((string)file_get_contents(current_model_file()));
    }

    $currentPath = $current && is_valid_model_file_name($current) ? safe_model_path($current) : null;
    if (!$currentPath || !file_exists($currentPath)) {
        $current = choose_fallback_model();
        file_put_contents(current_model_file(), $current);
    }
}

function init_sqlite_file(string $path, bool $withDemoData = false): void
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    init_db($pdo);
    if ($withDemoData) {
        seed_demo_data($pdo);
    }
}

function list_models(): array
{
    ensure_model_environment();
    $files = glob(models_dir() . '/*.sqlite') ?: [];
    $models = array_map('basename', $files);
    sort($models, SORT_NATURAL | SORT_FLAG_CASE);
    return $models;
}

function current_model_name(): string
{
    ensure_model_environment();
    return trim((string)file_get_contents(current_model_file())) ?: choose_fallback_model();
}

function set_current_model(string $name): void
{
    $name = validate_model_file_name($name);
    if (!file_exists(model_path($name))) {
        json_response(['ok' => false, 'error' => 'Model neexistuje'], 404);
    }
    file_put_contents(current_model_file(), $name);
}

function unique_model_name(string $requested): string
{
    $requested = sanitize_model_name($requested);
    $base = preg_replace('/\.sqlite$/', '', $requested);
    $candidate = $requested;
    $i = 2;
    while (file_exists(model_path($candidate))) {
        $candidate = $base . '-' . gmdate('Ymd-His') . ($i > 2 ? '-' . $i : '') . '.sqlite';
        $i++;
    }
    return $candidate;
}

function current_db_path(): string
{
    return model_path(current_model_name());
}

function db(): PDO
{
    static $pdo = null;
    static $pdoPath = null;

    $path = current_db_path();
    if ($pdo instanceof PDO && $pdoPath === $path) {
        return $pdo;
    }

    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdoPath = $path;
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
}


function seed_demo_data(PDO $pdo): void
{
    $count = (int)$pdo->query('SELECT COUNT(*) AS c FROM nodes')->fetch()['c'];
    if ($count > 0) return;

    $now = now_iso();
    $nodes = [
        ['software','SAP ECC','Core pojistný a účetní systém pro ukázku DORA evidence.','IT','Finance / pojištění','SAP tým','SAP','critical','high','high','critical',8,24,48,'private','personal, financial, business','prod','SVI datacenter','active','production','Demo asset: hlavní systém pro ukázku vazeb.','2026-06-26',12,'výpadek infrastruktury, ransomware, chyba rozhraní','Nedostupnost SAP zastaví zpracování smluv a účetních dat.',3,5,'backup, monitoring, správa změn, DR postup','Střední až vysoké podle aktuálního DR testu'],
        ['software','ALICE','Pojistná aplikační funkcionalita v rámci SAP ECC.','IT','Správa pojištění','ALICE tým','msg.PIA','high','high','high','high',8,24,48,'private','personal, business','prod','SAP ECC','active','production','Demo komponenta SAP ECC.','2026-06-26',12,'aplikační chyba, výpadek dodavatele','Nemožnost zpracovat pojistné smlouvy.',3,4,'aplikační monitoring, incident management','Střední'],
        ['hardware','scsap8cp','Produkční server hostující SAP aplikační vrstvu.','IT','IT infrastruktura','Infra tým','SV Informatik','critical','medium','high','critical',4,null,24,'','technological','prod','SVI datacenter','active','production','Demo HW uzel.','2026-06-26',12,'výpadek serveru, storage, síť','Výpadek serveru ovlivní hostovaný software.',2,5,'monitoring, HA, backup konfigurace','Střední'],
        ['data','Pojistné smlouvy','Datová sada pojistných smluv a klientských údajů.','Business','Správa smluv','Data owner','SVP','critical','critical','high','high',null,24,48,'private','personal, financial, business','prod','SAP / DB','active','production','Demo datové aktivum.','2026-06-26',12,'únik dat, nekonzistence dat, ztráta záloh','Ohrožení důvěrnosti nebo integrity smluvních dat.',3,5,'přístupová práva, backup, logování','Vysoké bez pravidelné revize oprávnění'],
        ['process','Správa pojistných smluv','Business proces správy pojistných smluv.','Business','Správa pojištění','Procesní vlastník','SVP','high','medium','high','high',8,24,48,'private','personal, business','prod','Praha','active','production','Demo proces.','2026-06-26',12,'nedostupnost systému, chyba dat','Zpoždění nebo zastavení správy smluv.',3,4,'náhradní postupy, monitoring incidentů','Střední'],
        ['supplier','SV Informatik','ICT provider pro hosting a infrastrukturní služby.','IT','IT','Vendor manager','SV Informatik','high','medium','medium','high',24,null,72,'','technological','prod','externí poskytovatel','active','production','Demo dodavatel.','2026-06-26',12,'výpadek poskytovatele, SLA selhání','Závislost na externím poskytovateli ICT služeb.',2,5,'SLA, reporting, exit plán','Střední'],
    ];
    $stmt = $pdo->prepare('INSERT INTO nodes (type,name,description,owner,business_owner,technical_owner,vendor_manufacturer,criticality,confidentiality,integrity_level,availability,rto_hours,rpo_hours,mtd_hours,data_sensitivity,data_categories,environment,location,status,lifecycle_state,good_to_know,last_reviewed_at,review_frequency_months,threats,risk_scenarios,risk_likelihood,risk_impact,risk_controls,residual_risk,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    foreach ($nodes as $n) {
        $n[] = $now; $n[] = $now;
        $stmt->execute($n);
    }
    $ids = [];
    foreach ($pdo->query('SELECT id, name FROM nodes')->fetchAll() as $row) $ids[$row['name']] = (int)$row['id'];
    $edges = [
        ['SAP ECC','ALICE','contains','SAP ECC obsahuje aplikační komponentu ALICE.','high'],
        ['scsap8cp','SAP ECC','hosts','Produkční server hostuje SAP ECC.','critical'],
        ['SAP ECC','Pojistné smlouvy','processes_data','SAP ECC zpracovává datovou sadu pojistných smluv.','critical'],
        ['ALICE','Pojistné smlouvy','processes_data','ALICE pracuje s pojistnými smlouvami.','high'],
        ['SAP ECC','Správa pojistných smluv','supports_process','SAP ECC podporuje proces správy smluv.','critical'],
        ['Pojistné smlouvy','Správa pojistných smluv','supports_process','Datová sada je využívána procesem správy smluv.','high'],
        ['SAP ECC','SV Informatik','provided_by','Hosting/provoz je poskytován externím ICT providerem.','high'],
    ];
    $estmt = $pdo->prepare('INSERT INTO edges (source_node_id,target_node_id,type,description,criticality,created_at,updated_at) VALUES (?,?,?,?,?,?,?)');
    foreach ($edges as [$src,$tgt,$type,$desc,$crit]) {
        if (isset($ids[$src], $ids[$tgt])) $estmt->execute([$ids[$src], $ids[$tgt], $type, $desc, $crit, $now, $now]);
    }
    $positions = [
        'scsap8cp' => [120, 220], 'SAP ECC' => [360, 220], 'ALICE' => [600, 130], 'Pojistné smlouvy' => [620, 330], 'Správa pojistných smluv' => [880, 330], 'SV Informatik' => [360, 480],
    ];
    $pstmt = $pdo->prepare('INSERT OR REPLACE INTO view_node_positions (view_id,node_id,x,y,visible,collapsed) VALUES (1,?,?,?,?,0)');
    foreach ($positions as $name => [$x,$y]) {
        if (isset($ids[$name])) $pstmt->execute([$ids[$name], $x, $y, 1]);
    }
}

function ensure_schema_upgrades(PDO $pdo): void
{
    $columns = [];
    foreach ($pdo->query('PRAGMA table_info(nodes)')->fetchAll() as $col) {
        $columns[$col['name']] = true;
    }

    $missing = [
        'vendor_manufacturer' => 'TEXT',
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
