<?php
/**
 * Demo seed data loader.
 *
 * The demo dataset is intentionally stored outside db.php so normal requests do not
 * carry a large inline seed array. db.php loads this file only when creating a new
 * demo model with seed data.
 */
function seed_demo_data(PDO $pdo): void
{
    $count = (int)$pdo->query('SELECT COUNT(*) AS c FROM nodes')->fetch()['c'];
    if ($count > 0) {
        return;
    }

    $seedPath = __DIR__ . '/demo_seed_data.json';
    if (!is_file($seedPath)) {
        throw new RuntimeException('Demo seed data file not found: ' . $seedPath);
    }

    $raw = file_get_contents($seedPath);
    $data = json_decode($raw === false ? '' : $raw, true);
    if (!is_array($data)) {
        throw new RuntimeException('Demo seed data JSON is invalid.');
    }

    $nodes = $data['nodes'] ?? [];
    $edges = $data['edges'] ?? [];
    $views = $data['views'] ?? [];
    $positions = $data['view_node_positions'] ?? [];

    $now = now_iso();

    $pdo->beginTransaction();
    try {
        // Replace the automatically created default view with the seed view(s).
        $pdo->exec('DELETE FROM view_node_positions');
        $pdo->exec('DELETE FROM edges');
        $pdo->exec('DELETE FROM nodes');
        $pdo->exec('DELETE FROM views');

        $viewStmt = $pdo->prepare('INSERT INTO views (id, name, description, filter_json, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)');
        foreach ($views as $view) {
            $viewStmt->execute([
                isset($view['id']) ? (int)$view['id'] : null,
                $view['name'] ?? 'Celková mapa',
                $view['description'] ?? null,
                $view['filter_json'] ?? '{}',
                $view['created_at'] ?? $now,
                $view['updated_at'] ?? $now,
            ]);
        }

        // Ensure at least one default view exists even if the seed JSON is incomplete.
        $existingViews = (int)$pdo->query('SELECT COUNT(*) AS c FROM views')->fetch()['c'];
        if ($existingViews === 0) {
            $viewStmt->execute([1, 'Celková mapa', 'Výchozí pohled na celý graf aktiv a vazeb.', '{}', $now, $now]);
        }

        $nodeStmt = $pdo->prepare('INSERT INTO nodes (
            id, type, name, description, owner, business_owner, technical_owner, vendor_manufacturer,
            criticality, confidentiality, integrity_level, availability, rto_hours, rpo_hours, mtd_hours,
            data_sensitivity, data_categories, environment, location, status, lifecycle_state, good_to_know,
            last_reviewed_at, review_frequency_months, threats, risk_scenarios, risk_likelihood, risk_impact,
            risk_controls, residual_risk, created_at, updated_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )');

        foreach ($nodes as $node) {
            $nodeStmt->execute([
                isset($node['id']) ? (int)$node['id'] : null,
                $node['type'] ?? 'software',
                $node['name'] ?? 'Bez názvu',
                $node['description'] ?? null,
                $node['owner'] ?? null,
                $node['business_owner'] ?? null,
                $node['technical_owner'] ?? null,
                $node['vendor_manufacturer'] ?? null,
                $node['criticality'] ?? null,
                $node['confidentiality'] ?? null,
                $node['integrity_level'] ?? null,
                $node['availability'] ?? null,
                nullable_int($node['rto_hours'] ?? null),
                nullable_int($node['rpo_hours'] ?? null),
                nullable_int($node['mtd_hours'] ?? null),
                $node['data_sensitivity'] ?? null,
                $node['data_categories'] ?? null,
                $node['environment'] ?? null,
                $node['location'] ?? null,
                $node['status'] ?? 'active',
                $node['lifecycle_state'] ?? null,
                $node['good_to_know'] ?? null,
                $node['last_reviewed_at'] ?? null,
                nullable_int($node['review_frequency_months'] ?? null),
                $node['threats'] ?? null,
                $node['risk_scenarios'] ?? null,
                nullable_int($node['risk_likelihood'] ?? null),
                nullable_int($node['risk_impact'] ?? null),
                $node['risk_controls'] ?? null,
                $node['residual_risk'] ?? null,
                $node['created_at'] ?? $now,
                $node['updated_at'] ?? $now,
            ]);
        }

        $edgeStmt = $pdo->prepare('INSERT INTO edges (id, source_node_id, target_node_id, type, description, criticality, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($edges as $edge) {
            $edgeStmt->execute([
                isset($edge['id']) ? (int)$edge['id'] : null,
                (int)($edge['source_node_id'] ?? 0),
                (int)($edge['target_node_id'] ?? 0),
                $edge['type'] ?? 'depends_on',
                $edge['description'] ?? null,
                $edge['criticality'] ?? null,
                $edge['created_at'] ?? $now,
                $edge['updated_at'] ?? $now,
            ]);
        }

        $positionStmt = $pdo->prepare('INSERT INTO view_node_positions (id, view_id, node_id, x, y, width, height, visible, collapsed) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($positions as $position) {
            $positionStmt->execute([
                isset($position['id']) ? (int)$position['id'] : null,
                (int)($position['view_id'] ?? 1),
                (int)($position['node_id'] ?? 0),
                (float)($position['x'] ?? 0),
                (float)($position['y'] ?? 0),
                $position['width'] ?? null,
                $position['height'] ?? null,
                isset($position['visible']) ? (int)$position['visible'] : 1,
                isset($position['collapsed']) ? (int)$position['collapsed'] : 0,
            ]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
