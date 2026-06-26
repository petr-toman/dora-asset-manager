<?php
require_once __DIR__ . '/db.php';

try {
    $pdo = db();
    $action = $_GET['action'] ?? $_POST['action'] ?? 'get_graph';

    switch ($action) {
        case 'meta':
            json_response([
                'ok' => true,
                'node_types' => node_types(),
                'edge_types' => edge_types(),
                'criticalities' => ['low', 'medium', 'high', 'critical'],
                'cia_levels' => ['low', 'medium', 'high', 'critical'],
                'data_sensitivities' => ['public', 'private', 'secret'],
                'data_categories' => ['personal', 'company_internal', 'general', 'financial', 'business', 'administrative', 'technological'],
                'environments' => ['prod', 'test', 'dev', 'archive', 'unknown'],
                'statuses' => ['active', 'planned', 'retired', 'unknown'],
                'lifecycle_states' => ['production', 'test', 'development', 'archived', 'unknown'],
                'risk_levels' => [1, 2, 3, 4, 5],
            ]);

        case 'get_graph':
            get_graph($pdo);
            break;

        case 'get_node':
            get_node($pdo);
            break;

        case 'save_node':
            save_node($pdo);
            break;

        case 'delete_node':
            delete_node($pdo);
            break;

        case 'save_edge':
            save_edge($pdo);
            break;

        case 'delete_edge':
            delete_edge($pdo);
            break;

        case 'save_position':
            save_position($pdo);
            break;

        case 'get_views':
            get_views($pdo);
            break;

        case 'save_view':
            save_view($pdo);
            break;

        case 'clone_view':
            clone_view($pdo);
            break;

        case 'delete_view':
            delete_view($pdo);
            break;

        case 'export_json':
            export_json($pdo);
            break;

        case 'risk_summary':
            risk_summary($pdo);
            break;

        case 'get_nodes_table':
            get_nodes_table($pdo);
            break;

        case 'get_edges_table':
            get_edges_table($pdo);
            break;

        case 'change_log':
            change_log($pdo);
            break;

        default:
            json_response(['ok' => false, 'error' => 'Unknown action: ' . $action], 404);
    }
} catch (Throwable $e) {
    json_response(['ok' => false, 'error' => $e->getMessage()], 500);
}

function node_types(): array
{
    return [
        'hardware' => 'Hardware',
        'software' => 'Software',
        'data' => 'Data',
        'process' => 'Proces',
        'business_function' => 'Business funkce',
        'supplier' => 'Dodavatel',
        'provider' => 'Poskytovatel',
        'manufacturer' => 'Výrobce',
        'network' => 'Síť',
        'location' => 'Lokalita',
        'documentation' => 'Dokumentace',
        'ict_service' => 'ICT služba',
    ];
}

function edge_types(): array
{
    return [
        'contains' => 'obsahuje',
        'hosts' => 'hostuje',
        'runs_on' => 'běží na',
        'stores' => 'ukládá',
        'processes_data' => 'zpracovává data',
        'uses_data' => 'používá data',
        'supports_process' => 'podporuje proces',
        'supports_function' => 'podporuje funkci',
        'depends_on' => 'závisí na',
        'provided_by' => 'poskytuje',
        'supplied_by' => 'dodává',
        'manufactured_by' => 'vyrobil',
        'connected_to' => 'propojeno s',
        'backed_up_by' => 'zálohuje se pomocí',
        'monitored_by' => 'monitoruje se pomocí',
        'administered_by' => 'spravuje',
        'integrates_with' => 'integruje se s',
        'authenticates_via' => 'autentizuje přes',
    ];
}

function get_graph(PDO $pdo): void
{
    $viewId = (int)($_GET['view_id'] ?? 1);
    $mode = $_GET['mode'] ?? 'saved';
    $nodeId = isset($_GET['node_id']) ? (int)$_GET['node_id'] : null;

    $nodes = $pdo->query('SELECT * FROM nodes ORDER BY type, name')->fetchAll();
    $edges = $pdo->query('SELECT * FROM edges ORDER BY id')->fetchAll();

    if ($mode !== 'saved') {
        [$nodes, $edges] = filter_dynamic_graph($nodes, $edges, $mode, $nodeId);
    }

    $positions = [];
    $stmt = $pdo->prepare('SELECT node_id, x, y FROM view_node_positions WHERE view_id = ?');
    $stmt->execute([$viewId]);
    foreach ($stmt->fetchAll() as $p) {
        $positions[(int)$p['node_id']] = ['x' => (float)$p['x'], 'y' => (float)$p['y']];
    }

    json_response([
        'ok' => true,
        'nodes' => $nodes,
        'edges' => $edges,
        'positions' => $positions,
        'edge_type_labels' => edge_types(),
    ]);
}

function filter_dynamic_graph(array $nodes, array $edges, string $mode, ?int $nodeId): array
{
    $nodeById = [];
    foreach ($nodes as $n) {
        $nodeById[(int)$n['id']] = $n;
    }

    $keepNodeIds = [];
    $keepEdgeIds = [];

    $addNode = function ($id) use (&$keepNodeIds, $nodeById) {
        $id = (int)$id;
        if (isset($nodeById[$id])) {
            $keepNodeIds[$id] = true;
        }
    };

    foreach ($nodes as $n) {
        $type = $n['type'];
        $id = (int)$n['id'];
        if ($mode === 'hardware' && $type === 'hardware') $addNode($id);
        if ($mode === 'data' && $type === 'data') $addNode($id);
        if ($mode === 'process' && in_array($type, ['process', 'business_function'], true)) $addNode($id);
        if ($mode === 'supplier' && in_array($type, ['supplier', 'provider', 'manufacturer'], true)) $addNode($id);
        if ($mode === 'critical' && in_array($n['criticality'], ['high', 'critical'], true)) $addNode($id);
        if ($mode === 'personal_data' && $type === 'data' && str_contains((string)$n['data_categories'], 'personal')) $addNode($id);
    }

    if ($mode === 'impact' && $nodeId) {
        $addNode($nodeId);
    }

    $changed = true;
    $depth = in_array($mode, ['hardware', 'data', 'process', 'supplier', 'impact'], true) ? 3 : 1;
    for ($i = 0; $i < $depth && $changed; $i++) {
        $changed = false;
        foreach ($edges as $e) {
            $s = (int)$e['source_node_id'];
            $t = (int)$e['target_node_id'];
            $type = $e['type'];
            $relevant = false;

            if (isset($keepNodeIds[$s]) || isset($keepNodeIds[$t])) {
                $relevant = true;
            }
            if ($mode === 'hardware' && in_array($type, ['hosts', 'runs_on', 'processes_data', 'supports_process', 'contains'], true)) $relevant = $relevant && true;
            if ($mode === 'data' && in_array($type, ['processes_data', 'uses_data', 'stores', 'hosts', 'provided_by', 'supports_process'], true)) $relevant = $relevant && true;
            if ($mode === 'process' && in_array($type, ['supports_process', 'supports_function', 'processes_data', 'uses_data', 'hosts', 'contains'], true)) $relevant = $relevant && true;
            if ($mode === 'supplier' && in_array($type, ['provided_by', 'supplied_by', 'manufactured_by', 'depends_on', 'supports_process', 'processes_data'], true)) $relevant = $relevant && true;

            if ($relevant) {
                if (!isset($keepNodeIds[$s]) || !isset($keepNodeIds[$t])) $changed = true;
                $addNode($s);
                $addNode($t);
                $keepEdgeIds[(int)$e['id']] = true;
            }
        }
    }

    $nodes = array_values(array_filter($nodes, fn($n) => isset($keepNodeIds[(int)$n['id']])));
    $edges = array_values(array_filter($edges, fn($e) => isset($keepNodeIds[(int)$e['source_node_id']]) && isset($keepNodeIds[(int)$e['target_node_id']])));

    return [$nodes, $edges];
}

function get_node(PDO $pdo): void
{
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare('SELECT * FROM nodes WHERE id = ?');
    $stmt->execute([$id]);
    $node = $stmt->fetch();
    if (!$node) json_response(['ok' => false, 'error' => 'Node not found'], 404);
    json_response(['ok' => true, 'node' => $node]);
}

function save_node(PDO $pdo): void
{
    $data = read_json_body();
    $id = isset($data['id']) && $data['id'] !== '' ? (int)$data['id'] : null;
    $now = now_iso();

    $fields = ['type','name','description','owner','business_owner','technical_owner','criticality','confidentiality','integrity_level','availability','rto_hours','rpo_hours','mtd_hours','data_sensitivity','data_categories','environment','location','status','lifecycle_state','good_to_know','last_reviewed_at','review_frequency_months','threats','risk_scenarios','risk_likelihood','risk_impact','risk_controls','residual_risk'];
    if (trim((string)($data['name'] ?? '')) === '') {
        json_response(['ok' => false, 'error' => 'Name is required'], 400);
    }

    $before = null;
    if ($id) {
        $stmt = $pdo->prepare('SELECT * FROM nodes WHERE id = ?');
        $stmt->execute([$id]);
        $before = $stmt->fetch();
        if (!$before) json_response(['ok' => false, 'error' => 'Node not found'], 404);

        $sets = [];
        $values = [];
        foreach ($fields as $f) {
            $sets[] = "$f = ?";
            $values[] = normalize_value($data[$f] ?? null);
        }
        $sets[] = 'updated_at = ?';
        $values[] = $now;
        $values[] = $id;
        $sql = 'UPDATE nodes SET ' . implode(', ', $sets) . ' WHERE id = ?';
        $pdo->prepare($sql)->execute($values);
        $action = 'update_node';
    } else {
        $cols = $fields;
        $cols[] = 'created_at';
        $cols[] = 'updated_at';
        $values = [];
        foreach ($fields as $f) {
            $values[] = normalize_value($data[$f] ?? null);
        }
        $values[] = $now;
        $values[] = $now;
        $sql = 'INSERT INTO nodes (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
        $pdo->prepare($sql)->execute($values);
        $id = (int)$pdo->lastInsertId();
        $action = 'create_node';
    }

    $stmt = $pdo->prepare('SELECT * FROM nodes WHERE id = ?');
    $stmt->execute([$id]);
    $after = $stmt->fetch();
    log_change($pdo, $action, 'node', $id, $before, $after);
    json_response(['ok' => true, 'node' => $after]);
}

function normalize_value($v)
{
    if ($v === '') return null;
    return $v;
}

function delete_node(PDO $pdo): void
{
    $data = read_json_body();
    $id = (int)($data['id'] ?? 0);
    $stmt = $pdo->prepare('SELECT * FROM nodes WHERE id = ?');
    $stmt->execute([$id]);
    $before = $stmt->fetch();
    if (!$before) json_response(['ok' => false, 'error' => 'Node not found'], 404);
    $pdo->prepare('DELETE FROM nodes WHERE id = ?')->execute([$id]);
    log_change($pdo, 'delete_node', 'node', $id, $before, null);
    json_response(['ok' => true]);
}

function save_edge(PDO $pdo): void
{
    $data = read_json_body();
    $id = isset($data['id']) && $data['id'] !== '' ? (int)$data['id'] : null;
    $source = (int)($data['source_node_id'] ?? 0);
    $target = (int)($data['target_node_id'] ?? 0);
    $type = trim((string)($data['type'] ?? 'depends_on'));
    if (!$source || !$target || $source === $target) {
        json_response(['ok' => false, 'error' => 'Source and target are required and must differ'], 400);
    }
    $now = now_iso();
    $before = null;

    if ($id) {
        $stmt = $pdo->prepare('SELECT * FROM edges WHERE id = ?');
        $stmt->execute([$id]);
        $before = $stmt->fetch();
        if (!$before) json_response(['ok' => false, 'error' => 'Edge not found'], 404);
        $pdo->prepare('UPDATE edges SET source_node_id=?, target_node_id=?, type=?, description=?, criticality=?, updated_at=? WHERE id=?')
            ->execute([$source, $target, $type, normalize_value($data['description'] ?? null), normalize_value($data['criticality'] ?? null), $now, $id]);
        $action = 'update_edge';
    } else {
        $pdo->prepare('INSERT INTO edges (source_node_id, target_node_id, type, description, criticality, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$source, $target, $type, normalize_value($data['description'] ?? null), normalize_value($data['criticality'] ?? null), $now, $now]);
        $id = (int)$pdo->lastInsertId();
        $action = 'create_edge';
    }

    $stmt = $pdo->prepare('SELECT * FROM edges WHERE id = ?');
    $stmt->execute([$id]);
    $after = $stmt->fetch();
    log_change($pdo, $action, 'edge', $id, $before, $after);
    json_response(['ok' => true, 'edge' => $after]);
}

function delete_edge(PDO $pdo): void
{
    $data = read_json_body();
    $id = (int)($data['id'] ?? 0);
    $stmt = $pdo->prepare('SELECT * FROM edges WHERE id = ?');
    $stmt->execute([$id]);
    $before = $stmt->fetch();
    if (!$before) json_response(['ok' => false, 'error' => 'Edge not found'], 404);
    $pdo->prepare('DELETE FROM edges WHERE id = ?')->execute([$id]);
    log_change($pdo, 'delete_edge', 'edge', $id, $before, null);
    json_response(['ok' => true]);
}

function save_position(PDO $pdo): void
{
    $data = read_json_body();
    $viewId = (int)($data['view_id'] ?? 1);
    $nodeId = (int)($data['node_id'] ?? 0);
    $x = (float)($data['x'] ?? 0);
    $y = (float)($data['y'] ?? 0);

    $stmt = $pdo->prepare('SELECT * FROM view_node_positions WHERE view_id = ? AND node_id = ?');
    $stmt->execute([$viewId, $nodeId]);
    $before = $stmt->fetch();

    $pdo->prepare('INSERT INTO view_node_positions (view_id, node_id, x, y) VALUES (?, ?, ?, ?) ON CONFLICT(view_id, node_id) DO UPDATE SET x=excluded.x, y=excluded.y')
        ->execute([$viewId, $nodeId, $x, $y]);

    $stmt->execute([$viewId, $nodeId]);
    $after = $stmt->fetch();
    log_change($pdo, 'move_node', 'position', $nodeId, $before, $after);
    json_response(['ok' => true]);
}

function get_views(PDO $pdo): void
{
    $views = $pdo->query('SELECT * FROM views ORDER BY name')->fetchAll();
    json_response(['ok' => true, 'views' => $views]);
}

function save_view(PDO $pdo): void
{
    $data = read_json_body();
    $id = isset($data['id']) && $data['id'] !== '' ? (int)$data['id'] : null;
    $name = trim((string)($data['name'] ?? ''));
    if ($name === '') json_response(['ok' => false, 'error' => 'View name is required'], 400);
    $now = now_iso();
    $filter = $data['filter_json'] ?? '{}';
    if (is_array($filter)) $filter = json_encode($filter, JSON_UNESCAPED_UNICODE);

    $before = null;
    if ($id) {
        $stmt = $pdo->prepare('SELECT * FROM views WHERE id = ?');
        $stmt->execute([$id]);
        $before = $stmt->fetch();
        if (!$before) json_response(['ok' => false, 'error' => 'View not found'], 404);
        $pdo->prepare('UPDATE views SET name=?, description=?, filter_json=?, updated_at=? WHERE id=?')
            ->execute([$name, normalize_value($data['description'] ?? null), $filter, $now, $id]);
        $action = 'update_view';
    } else {
        $pdo->prepare('INSERT INTO views (name, description, filter_json, created_at, updated_at) VALUES (?, ?, ?, ?, ?)')
            ->execute([$name, normalize_value($data['description'] ?? null), $filter, $now, $now]);
        $id = (int)$pdo->lastInsertId();
        $action = 'create_view';
    }

    $stmt = $pdo->prepare('SELECT * FROM views WHERE id = ?');
    $stmt->execute([$id]);
    $after = $stmt->fetch();
    log_change($pdo, $action, 'view', $id, $before, $after);
    json_response(['ok' => true, 'id' => $id, 'view' => $after]);
}

function clone_view(PDO $pdo): void
{
    $data = read_json_body();
    $sourceId = (int)($data['source_view_id'] ?? 0);
    if ($sourceId <= 0) json_response(['ok' => false, 'error' => 'Source view is required'], 400);

    $stmt = $pdo->prepare('SELECT * FROM views WHERE id = ?');
    $stmt->execute([$sourceId]);
    $source = $stmt->fetch();
    if (!$source) json_response(['ok' => false, 'error' => 'Source view not found'], 404);

    $name = trim((string)($data['name'] ?? ''));
    if ($name === '') json_response(['ok' => false, 'error' => 'View name is required'], 400);
    $description = normalize_value($data['description'] ?? $source['description'] ?? null);
    $filter = $data['filter_json'] ?? $source['filter_json'] ?? '{}';
    if (is_array($filter)) $filter = json_encode($filter, JSON_UNESCAPED_UNICODE);
    $now = now_iso();

    $pdo->beginTransaction();
    try {
        $pdo->prepare('INSERT INTO views (name, description, filter_json, created_at, updated_at) VALUES (?, ?, ?, ?, ?)')
            ->execute([$name, $description, $filter, $now, $now]);
        $newId = (int)$pdo->lastInsertId();

        $copy = $pdo->prepare('INSERT INTO view_node_positions (view_id, node_id, x, y, width, height, visible, collapsed)
            SELECT ?, node_id, x, y, width, height, visible, collapsed FROM view_node_positions WHERE view_id = ?');
        $copy->execute([$newId, $sourceId]);

        $stmt = $pdo->prepare('SELECT * FROM views WHERE id = ?');
        $stmt->execute([$newId]);
        $after = $stmt->fetch();
        log_change($pdo, 'clone_view', 'view', $newId, $source, $after);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    json_response(['ok' => true, 'id' => $newId, 'view' => $after]);
}

function delete_view(PDO $pdo): void
{
    $data = read_json_body();
    $id = (int)($data['id'] ?? 0);
    if ($id === 1) {
        json_response(['ok' => false, 'error' => 'Výchozí view Celková mapa nelze smazat.'], 400);
    }
    if ($id <= 0) json_response(['ok' => false, 'error' => 'View id is required'], 400);

    $count = (int)$pdo->query('SELECT COUNT(*) FROM views')->fetchColumn();
    if ($count <= 1) {
        json_response(['ok' => false, 'error' => 'Nelze smazat poslední view.'], 400);
    }

    $stmt = $pdo->prepare('SELECT * FROM views WHERE id = ?');
    $stmt->execute([$id]);
    $before = $stmt->fetch();
    if (!$before) json_response(['ok' => false, 'error' => 'View not found'], 404);

    $pdo->prepare('DELETE FROM views WHERE id = ?')->execute([$id]);
    log_change($pdo, 'delete_view', 'view', $id, $before, null);
    json_response(['ok' => true]);
}

function export_json(PDO $pdo): void
{
    json_response([
        'ok' => true,
        'exported_at' => now_iso(),
        'nodes' => $pdo->query('SELECT * FROM nodes ORDER BY id')->fetchAll(),
        'edges' => $pdo->query('SELECT * FROM edges ORDER BY id')->fetchAll(),
        'views' => $pdo->query('SELECT * FROM views ORDER BY id')->fetchAll(),
        'view_node_positions' => $pdo->query('SELECT * FROM view_node_positions ORDER BY view_id, node_id')->fetchAll(),
        'change_log' => $pdo->query('SELECT * FROM change_log ORDER BY id')->fetchAll(),
    ]);
}


function get_nodes_table(PDO $pdo): void
{
    $rows = $pdo->query('SELECT * FROM nodes ORDER BY id')->fetchAll();
    json_response(['ok' => true, 'nodes' => $rows]);
}

function get_edges_table(PDO $pdo): void
{
    $sql = 'SELECT e.*, s.name AS source_name, t.name AS target_name
            FROM edges e
            LEFT JOIN nodes s ON s.id = e.source_node_id
            LEFT JOIN nodes t ON t.id = e.target_node_id
            ORDER BY e.id';
    $rows = $pdo->query($sql)->fetchAll();
    json_response(['ok' => true, 'edges' => $rows]);
}

function change_log(PDO $pdo): void
{
    $rows = $pdo->query('SELECT * FROM change_log ORDER BY id DESC LIMIT 100')->fetchAll();
    json_response(['ok' => true, 'changes' => $rows]);
}


function risk_summary(PDO $pdo): void
{
    $nodes = $pdo->query('SELECT * FROM nodes ORDER BY name')->fetchAll();
    $items = [];
    $heatmap = [];
    for ($l = 1; $l <= 5; $l++) {
        for ($i = 1; $i <= 5; $i++) {
            $heatmap[$l][$i] = 0;
        }
    }
    foreach ($nodes as $n) {
        $likelihood = (int)($n['risk_likelihood'] ?? 0);
        $impact = (int)($n['risk_impact'] ?? 0);
        if ($likelihood < 1 || $likelihood > 5) $likelihood = 1;
        if ($impact < 1 || $impact > 5) $impact = 1;
        $score = asset_risk_score($n);
        $level = score_level($score);
        $heatmap[$likelihood][$impact]++;
        $items[] = [
            'id' => (int)$n['id'],
            'name' => $n['name'],
            'type' => $n['type'],
            'criticality' => $n['criticality'],
            'rto_hours' => $n['rto_hours'],
            'risk_likelihood' => $likelihood,
            'risk_impact' => $impact,
            'score' => $score,
            'level' => $level,
        ];
    }
    usort($items, fn($a, $b) => $b['score'] <=> $a['score']);
    json_response(['ok' => true, 'items' => $items, 'heatmap' => $heatmap]);
}

function asset_risk_score(array $n): int
{
    $likelihood = max(1, min(5, (int)($n['risk_likelihood'] ?? 1)));
    $impact = max(1, min(5, (int)($n['risk_impact'] ?? 1)));
    $base = $likelihood * $impact;
    $crit = level_num($n['criticality'] ?? null);
    $cia = max(level_num($n['confidentiality'] ?? null), level_num($n['integrity_level'] ?? null), level_num($n['availability'] ?? null));
    $rto = rto_factor($n['rto_hours'] ?? null);
    return $base + ($crit * 2) + ($cia * 2) + ($rto * 2);
}

function level_num($v): int
{
    return match ((string)$v) {
        'critical' => 4,
        'high' => 3,
        'medium' => 2,
        'low' => 1,
        default => 1,
    };
}

function rto_factor($hours): int
{
    if ($hours === null || $hours === '') return 1;
    $h = (float)$hours;
    if ($h <= 4) return 5;
    if ($h <= 8) return 4;
    if ($h <= 24) return 3;
    if ($h <= 72) return 2;
    return 1;
}

function score_level(int $score): string
{
    if ($score >= 35) return 'critical';
    if ($score >= 27) return 'high';
    if ($score >= 18) return 'medium';
    return 'low';
}
