<?php
require_once __DIR__ . '/db.php';
$pdo = db();
$nodes = $pdo->query('SELECT * FROM nodes ORDER BY type, name')->fetchAll();
$edges = $pdo->query('SELECT e.*, s.name AS source_name, s.type AS source_type, t.name AS target_name, t.type AS target_type FROM edges e JOIN nodes s ON s.id = e.source_node_id JOIN nodes t ON t.id = e.target_node_id ORDER BY s.name, e.type, t.name')->fetchAll();
$edgeLabels = [
    'contains' => 'obsahuje', 'hosts' => 'hostuje', 'runs_on' => 'běží na', 'stores' => 'ukládá',
    'processes_data' => 'zpracovává data', 'uses_data' => 'používá data', 'supports_process' => 'podporuje proces',
    'supports_function' => 'podporuje funkci', 'depends_on' => 'závisí na', 'provided_by' => 'poskytuje',
    'supplied_by' => 'dodává', 'manufactured_by' => 'vyrobil', 'connected_to' => 'propojeno s',
    'backed_up_by' => 'zálohuje se pomocí', 'monitored_by' => 'monitoruje se pomocí',
    'administered_by' => 'spravuje', 'integrates_with' => 'integruje se s', 'authenticates_via' => 'autentizuje přes',
];
function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function level_num_report($v): int { return ['low'=>1,'medium'=>2,'high'=>3,'critical'=>4][(string)$v] ?? 1; }
function rto_factor_report($hours): int {
    if ($hours === null || $hours === '') return 1;
    $h = (float)$hours;
    if ($h <= 4) return 5;
    if ($h <= 8) return 4;
    if ($h <= 24) return 3;
    if ($h <= 72) return 2;
    return 1;
}
function asset_score_report(array $n): int {
    $likelihood = max(1, min(5, (int)($n['risk_likelihood'] ?? 1)));
    $impact = max(1, min(5, (int)($n['risk_impact'] ?? 1)));
    $base = $likelihood * $impact;
    $crit = level_num_report($n['criticality'] ?? null);
    $cia = max(level_num_report($n['confidentiality'] ?? null), level_num_report($n['integrity_level'] ?? null), level_num_report($n['availability'] ?? null));
    $rto = rto_factor_report($n['rto_hours'] ?? null);
    return $base + ($crit * 2) + ($cia * 2) + ($rto * 2);
}
function score_level_report(int $score): string {
    if ($score >= 35) return 'critical';
    if ($score >= 27) return 'high';
    if ($score >= 18) return 'medium';
    return 'low';
}
$byNodeOut = [];
$byNodeIn = [];
foreach ($edges as $e) {
    $byNodeOut[(int)$e['source_node_id']][] = $e;
    $byNodeIn[(int)$e['target_node_id']][] = $e;
}
$heat = [];
for ($l=1;$l<=5;$l++) for ($i=1;$i<=5;$i++) $heat[$l][$i] = [];
$ranked = [];
foreach ($nodes as $n) {
    $l = max(1, min(5, (int)($n['risk_likelihood'] ?? 1)));
    $i = max(1, min(5, (int)($n['risk_impact'] ?? 1)));
    $score = asset_score_report($n);
    $n['_score'] = $score;
    $n['_level'] = score_level_report($score);
    $heat[$l][$i][] = $n;
    $ranked[] = $n;
}
usort($ranked, fn($a,$b) => $b['_score'] <=> $a['_score']);
$countsByType = [];
foreach ($nodes as $n) $countsByType[$n['type']] = ($countsByType[$n['type']] ?? 0) + 1;
?>
<!doctype html>
<html lang="cs">
<head>
<meta charset="utf-8">
<title>DORA evidence IT aktiv - report</title>
<style>
    body { font-family: Arial, sans-serif; color: #111827; margin: 32px; line-height: 1.42; }
    h1 { margin-bottom: 0; }
    h2 { margin-top: 32px; border-bottom: 2px solid #e5e7eb; padding-bottom: 6px; }
    h3 { margin-bottom: 4px; }
    .muted { color: #6b7280; }
    .toolbar { position: sticky; top: 0; background: white; padding: 10px 0; border-bottom: 1px solid #e5e7eb; }
    button, .button { padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; background: #f8fafc; cursor: pointer; text-decoration: none; color: #111827; display: inline-block; }
    table { width: 100%; border-collapse: collapse; margin: 12px 0 20px; font-size: 13px; }
    th, td { border: 1px solid #e5e7eb; padding: 6px 8px; vertical-align: top; }
    th { background: #f8fafc; text-align: left; }
    .kpi { display: flex; gap: 12px; flex-wrap: wrap; margin: 18px 0; }
    .card { border: 1px solid #e5e7eb; border-radius: 10px; padding: 12px; min-width: 140px; background: #f9fafb; }
    .badge { display: inline-block; padding: 2px 7px; border-radius: 999px; font-size: 12px; background: #e5e7eb; }
    .low { background: #dcfce7; }
    .medium { background: #fef3c7; }
    .high { background: #fed7aa; }
    .critical { background: #fecaca; font-weight: bold; }
    .heatmap td { width: 16%; height: 72px; text-align: center; }
    .heatmap .count { font-size: 20px; font-weight: bold; }
    .asset-block { break-inside: avoid; page-break-inside: avoid; border: 1px solid #e5e7eb; border-radius: 10px; padding: 12px; margin: 14px 0; }
    ul { margin-top: 4px; }
    @media print { .toolbar { display: none; } body { margin: 16mm; } a { color: inherit; text-decoration: none; } }
</style>
</head>
<body>
<div class="toolbar"><button onclick="window.print()">Tisk / uložit jako PDF</button> <a class="button" href="/report_docx.php">Export DOCX / Word</a> <button onclick="location.href='/'">Zpět do aplikace</button></div>
<h1>DORA evidence IT aktiv - report</h1>
<p class="muted">Vygenerováno: <?= h(date('Y-m-d H:i')) ?>. Report je tisknutelný do PDF přes prohlížeč nebo exportovatelný do DOCX pro Microsoft Word.</p>

<h2>1. Manažerské shrnutí</h2>
<p>Evidence obsahuje <?= count($nodes) ?> uzlů a <?= count($edges) ?> vazeb. Graf zachycuje ICT a informační aktiva, procesy, dodavatele a jejich závislosti. Kritičnost je odvozena z evidované kritičnosti aktiva, CIA hodnocení, RTO a základního rizikového hodnocení pravděpodobnost × dopad.</p>
<div class="kpi">
<?php foreach ($countsByType as $type => $cnt): ?><div class="card"><strong><?= h($cnt) ?></strong><br><?= h($type) ?></div><?php endforeach; ?>
</div>

<h2>2. Heatmapa rizik</h2>
<p class="muted">Svisle pravděpodobnost, vodorovně dopad. Číslo v buňce znamená počet assetů v dané kombinaci.</p>
<table class="heatmap">
<tr><th>Pravděp. \ Dopad</th><?php for($i=1;$i<=5;$i++): ?><th><?= $i ?></th><?php endfor; ?></tr>
<?php for($l=5;$l>=1;$l--): ?>
<tr><th><?= $l ?></th><?php for($i=1;$i<=5;$i++): $count=count($heat[$l][$i]); $class=score_level_report($l*$i + 10); ?><td class="<?= h($class) ?>"><div class="count"><?= $count ?></div><small><?php foreach(array_slice($heat[$l][$i],0,3) as $n) echo h($n['name']).'<br>'; if($count>3) echo '…'; ?></small></td><?php endfor; ?></tr>
<?php endfor; ?>
</table>

<h2>3. Nejkritičtější aktiva podle kombinovaného skóre</h2>
<table>
<tr><th>Asset</th><th>Typ</th><th>Kritičnost</th><th>RTO</th><th>Pravd.</th><th>Dopad</th><th>Skóre</th><th>Úroveň</th></tr>
<?php foreach (array_slice($ranked,0,20) as $n): ?><tr>
<td><?= h($n['name']) ?></td><td><?= h($n['type']) ?></td><td><?= h($n['criticality']) ?></td><td><?= h($n['rto_hours']) ?></td><td><?= h($n['risk_likelihood']) ?></td><td><?= h($n['risk_impact']) ?></td><td><?= h($n['_score']) ?></td><td><span class="badge <?= h($n['_level']) ?>"><?= h($n['_level']) ?></span></td>
</tr><?php endforeach; ?>
</table>

<h2>4. Seznam aktiv, atributy a vazby</h2>
<?php foreach ($ranked as $n): ?>
<section class="asset-block">
<h3><?= h($n['name']) ?> <span class="badge <?= h($n['_level']) ?>"><?= h($n['_level']) ?> / <?= h($n['_score']) ?></span></h3>
<p><?= nl2br(h($n['description'])) ?></p>
<table>
<tr><th>Typ</th><td><?= h($n['type']) ?></td><th>Kritičnost</th><td><?= h($n['criticality']) ?></td></tr>
<tr><th>Owner</th><td><?= h($n['owner']) ?></td><th>Prostředí</th><td><?= h($n['environment']) ?></td></tr>
<tr><th>C/I/A</th><td><?= h($n['confidentiality']) ?> / <?= h($n['integrity_level']) ?> / <?= h($n['availability']) ?></td><th>RTO/RPO/MTD</th><td><?= h($n['rto_hours']) ?> / <?= h($n['rpo_hours']) ?> / <?= h($n['mtd_hours']) ?> h</td></tr>
<tr><th>Citlivost dat</th><td><?= h($n['data_sensitivity']) ?></td><th>Kategorie dat</th><td><?= h($n['data_categories']) ?></td></tr>
<tr><th>Hrozby</th><td colspan="3"><?= nl2br(h($n['threats'])) ?></td></tr>
<tr><th>Rizikové scénáře</th><td colspan="3"><?= nl2br(h($n['risk_scenarios'])) ?></td></tr>
<tr><th>Opatření</th><td colspan="3"><?= nl2br(h($n['risk_controls'])) ?></td></tr>
</table>
<?php if (!empty($byNodeOut[(int)$n['id']])): ?><strong>Odchozí vazby</strong><ul><?php foreach ($byNodeOut[(int)$n['id']] as $e): ?><li><?= h($edgeLabels[$e['type']] ?? $e['type']) ?> → <?= h($e['target_name']) ?> <span class="muted">(<?= h($e['target_type']) ?>)</span></li><?php endforeach; ?></ul><?php endif; ?>
<?php if (!empty($byNodeIn[(int)$n['id']])): ?><strong>Příchozí vazby</strong><ul><?php foreach ($byNodeIn[(int)$n['id']] as $e): ?><li><?= h($e['source_name']) ?> <span class="muted">(<?= h($e['source_type']) ?>)</span> → <?= h($edgeLabels[$e['type']] ?? $e['type']) ?></li><?php endforeach; ?></ul><?php endif; ?>
</section>
<?php endforeach; ?>

<h2>5. Metodická poznámka ke skóre</h2>
<p>První verze skóre je záměrně jednoduchá: základ tvoří pravděpodobnost × dopad. K tomu se přičítá váha deklarované kritičnosti, nejvyšší hodnota CIA a faktor RTO. Krátké RTO zvyšuje skóre, protože signalizuje nízkou toleranci výpadku. Skóre je podpůrné a nenahrazuje formální risk assessment.</p>
</body>
</html>
