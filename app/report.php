<?php
require_once __DIR__ . '/db.php';
$pdo = db();
$modelName = function_exists('current_model_name') ? current_model_name() : 'model.sqlite';
$nodes = $pdo->query('SELECT * FROM nodes ORDER BY type, name')->fetchAll();
$edges = $pdo->query('SELECT e.*, s.name AS source_name, s.type AS source_type, t.name AS target_name, t.type AS target_type FROM edges e JOIN nodes s ON s.id = e.source_node_id JOIN nodes t ON t.id = e.target_node_id ORDER BY s.name, e.type, t.name')->fetchAll();
$nodeLabels = [
    'hardware' => 'Hardware', 'software' => 'Software', 'data' => 'Data', 'process' => 'Proces',
    'business_function' => 'Business funkce', 'third_party' => '3. strana (dodavatel)', 'network' => 'Síť',
    'location' => 'Lokalita', 'documentation' => 'Dokumentace', 'ict_service' => 'ICT služba',
];
$edgeLabels = [
    'contains' => 'obsahuje', 'hosts' => 'hostuje', 'runs_on' => 'běží na', 'stores' => 'ukládá',
    'processes_data' => 'zpracovává data', 'uses_data' => 'používá data', 'supports_process' => 'podporuje proces',
    'supports_function' => 'podporuje funkci', 'depends_on' => 'závisí na', 'provided_by' => 'poskytuje',
    'supplied_by' => 'dodává', 'manufactured_by' => 'vyrobil', 'connected_to' => 'propojeno s',
    'backed_up_by' => 'zálohuje se pomocí', 'monitored_by' => 'monitoruje se pomocí',
    'administered_by' => 'spravuje', 'integrates_with' => 'integruje se s', 'authenticates_via' => 'autentizuje přes',
];
$levelLabels = ['low' => 'Nízká', 'medium' => 'Střední', 'high' => 'Vysoká', 'critical' => 'Kritická', 'unrated' => 'Nehodnoceno'];
$sensitivityLabels = ['public' => 'Veřejná', 'private' => 'Privátní', 'secret' => 'Tajná'];
function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function label_report(array $labels, $value): string { $v = (string)($value ?? ''); return $v === '' ? '—' : ($labels[$v] ?? $v); }
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
function valid_risk_level_report($v): ?int {
    if ($v === null || $v === '') return null;
    if (!is_numeric($v)) return null;
    $i = (int)$v;
    return ($i >= 1 && $i <= 5) ? $i : null;
}
function asset_score_report(array $n): ?int {
    $likelihood = valid_risk_level_report($n['risk_likelihood'] ?? null);
    $impact = valid_risk_level_report($n['risk_impact'] ?? null);
    if ($likelihood === null || $impact === null) return null;
    $base = $likelihood * $impact;
    $crit = level_num_report($n['criticality'] ?? null);
    $cia = max(level_num_report($n['confidentiality'] ?? null), level_num_report($n['integrity_level'] ?? null), level_num_report($n['availability'] ?? null));
    $rto = rto_factor_report($n['rto_hours'] ?? null);
    return $base + ($crit * 2) + ($cia * 2) + ($rto * 2);
}
function score_level_report(?int $score): string {
    if ($score === null) return 'unrated';
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
$unrated = [];
foreach ($nodes as $n) {
    $l = valid_risk_level_report($n['risk_likelihood'] ?? null);
    $i = valid_risk_level_report($n['risk_impact'] ?? null);
    $score = asset_score_report($n);
    $n['_score'] = $score;
    $n['_level'] = score_level_report($score);
    if ($l === null || $i === null) $unrated[] = $n; else $heat[$l][$i][] = $n;
    $ranked[] = $n;
}
usort($ranked, fn($a,$b) => ($b['_score'] ?? -1) <=> ($a['_score'] ?? -1));
$countsByType = [];
foreach ($nodes as $n) $countsByType[$n['type']] = ($countsByType[$n['type']] ?? 0) + 1;
ksort($countsByType);
$ratedCount = count($nodes) - count($unrated);
?>
<!doctype html>
<html lang="cs">
<head>
<meta charset="utf-8">
<title>DORA evidence IT aktiv - report</title>
<style>
    :root { --bg:#f3f7fc; --card:#ffffff; --text:#0f172a; --muted:#64748b; --line:#dbe5f0; --head:#eaf2fb; --accent:#2563eb; }
    * { box-sizing: border-box; }
    body { font-family: Inter, ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; color: var(--text); margin: 0; line-height: 1.42; background: var(--bg); }
    .page { max-width: 1180px; margin: 0 auto; padding: 28px 36px 46px; }
    .report-title { margin: 0 0 4px; font-size: 26px; letter-spacing: -.04em; }
    .report-meta { color: var(--muted); font-size: 13px; margin-bottom: 18px; }
    .toolbar { position: sticky; top: 0; z-index: 5; background: rgba(243,247,252,.96); padding: 10px 36px; border-bottom: 1px solid var(--line); backdrop-filter: blur(10px); }
    button, .button { padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 10px; background: #fff; cursor: pointer; text-decoration: none; color: #111827; display: inline-block; font-weight: 750; }
    .report-card { background: var(--card); border: 1px solid var(--line); border-radius: 16px; padding: 18px; margin: 14px 0; box-shadow: 0 10px 26px rgba(15,23,42,.06); }
    h2 { margin: 0 0 14px; padding-bottom: 9px; border-bottom: 1px solid var(--line); font-size: 19px; letter-spacing: -.03em; }
    h3 { margin: 0 0 8px; font-size: 16px; }
    p { margin: 8px 0 10px; }
    .muted { color: var(--muted); }
    .kpi-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-top: 14px; }
    .kpi { border: 1px solid var(--line); border-radius: 12px; padding: 13px 14px; background: #f8fbff; min-height: 64px; }
    .kpi strong { display: block; font-size: 23px; line-height: 1; margin-bottom: 4px; }
    .kpi span { color: #334155; font-size: 12px; font-weight: 700; }
    table { width: 100%; border-collapse: collapse; margin: 10px 0 14px; font-size: 12px; }
    th, td { border: 1px solid var(--line); padding: 7px 8px; vertical-align: top; }
    th { background: var(--head); color: #1e293b; text-align: left; font-weight: 800; }
    .badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 800; background: #e5e7eb; white-space: nowrap; }
    .low { background: #dcfce7; }
    .medium { background: #fdecc8; }
    .high { background: #fed7aa; }
    .critical { background: #fecaca; font-weight: 900; }
    .unrated { background: #e5e7eb; color: #374151; }
    .heatmap-card, .heatmap, .heatmap-wrap { break-inside: avoid; page-break-inside: avoid; }
    .heatmap { table-layout: fixed; }
    .heatmap td { height: 68px; text-align: center; }
    .heatmap .count { font-size: 19px; font-weight: 900; line-height: 1.1; }
    .heatmap small { color: #334155; display:block; margin-top:3px; line-height:1.25; }
    .type-table td:last-child { text-align: right; font-weight: 800; }
    .asset-block { break-inside: avoid; page-break-inside: avoid; }
    .asset-block h3 { display: flex; gap: 8px; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--line); padding-bottom: 8px; }
    .asset-title { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    ul { margin: 5px 0 10px 20px; padding: 0; }
    @media print {
        body { background: #fff; }
        .toolbar { display: none; }
        .page { max-width: none; padding: 14mm; }
        .report-card { box-shadow: none; margin: 8mm 0; }
        a { color: inherit; text-decoration: none; }
        .heatmap-card { break-inside: avoid; page-break-inside: avoid; }
    }
    @media (max-width: 900px) { .page { padding: 18px; } .kpi-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
</style>
</head>
<body>
<div class="toolbar"><button onclick="window.print()">Tisk / uložit jako PDF</button> <a class="button" href="/report_docx.php">Export DOCX / Word</a> <button onclick="location.href='/'">Zpět do aplikace</button></div>
<div class="page">
<header>
    <h1 class="report-title">Report digitální provozní odolnosti</h1>
    <div class="report-meta">Model: <?= h($modelName) ?> · vygenerováno <?= h(date('Y-m-d H:i')) ?></div>
</header>

<section class="report-card">
    <h2>Manažerské shrnutí</h2>
    <p>Model obsahuje <strong><?= count($nodes) ?> aktiv</strong> a <strong><?= count($edges) ?> vazeb</strong>. Kritičnost, CIA atributy a parametry obnovy slouží k orientačnímu prioritizačnímu pohledu pro DORA asset mapu. Položky bez pravděpodobnosti nebo dopadu jsou vedené jako <strong>nehodnocené</strong>, aby nebyly omylem zařazené do nízkého rizika.</p>
    <div class="kpi-grid">
        <div class="kpi"><strong><?= count($nodes) ?></strong><span>assetů</span></div>
        <div class="kpi"><strong><?= count($edges) ?></strong><span>vazeb</span></div>
        <div class="kpi"><strong><?= $ratedCount ?></strong><span>hodnocených rizik</span></div>
        <div class="kpi"><strong><?= count($unrated) ?></strong><span>nehodnocených</span></div>
    </div>
</section>

<section class="report-card">
    <h2>Počty assetů podle typu</h2>
    <table class="type-table">
        <tr><th>Typ</th><th>Počet</th></tr>
        <?php foreach ($countsByType as $type => $cnt): ?><tr><td><?= h(label_report($nodeLabels, $type)) ?></td><td><?= h($cnt) ?></td></tr><?php endforeach; ?>
    </table>
</section>

<section class="report-card heatmap-card">
    <h2>Heatmapa rizik</h2>
    <p class="muted">Svisle pravděpodobnost, vodorovně dopad. Číslo v buňce znamená počet assetů v dané kombinaci.</p>
    <div class="heatmap-wrap">
    <table class="heatmap">
    <tr><th>Pravděp. \ Dopad</th><?php for($i=1;$i<=5;$i++): ?><th><?= $i ?></th><?php endfor; ?></tr>
    <?php for($l=5;$l>=1;$l--): ?>
    <tr><th><?= $l ?></th><?php for($i=1;$i<=5;$i++): $count=count($heat[$l][$i]); $class=score_level_report($l*$i + 10); ?><td class="<?= h($class) ?>"><div class="count"><?= $count ?></div><small><?php foreach(array_slice($heat[$l][$i],0,3) as $n) echo h($n['name']).'<br>'; if($count>3) echo '…'; ?></small></td><?php endfor; ?></tr>
    <?php endfor; ?>
    </table>
    </div>
</section>

<?php if (count($unrated)): ?>
<section class="report-card">
    <h2>Nehodnocená aktiva</h2>
    <p class="muted">Tato aktiva nemají vyplněnou pravděpodobnost nebo dopad a nejsou proto zařazena do heatmapy jako nízké riziko.</p>
    <ul><?php foreach (array_slice($unrated,0,30) as $n): ?><li><?= h($n['name']) ?> <span class="muted">(<?= h(label_report($nodeLabels, $n['type'])) ?>)</span></li><?php endforeach; ?><?php if(count($unrated)>30): ?><li>…</li><?php endif; ?></ul>
</section>
<?php endif; ?>

<section class="report-card">
    <h2>Nejkritičtější aktiva podle kombinovaného skóre</h2>
    <table>
    <tr><th>Asset</th><th>Typ</th><th>Kritičnost</th><th>RTO</th><th>Pravd.</th><th>Dopad</th><th>Skóre</th><th>Úroveň</th></tr>
    <?php foreach (array_slice($ranked,0,20) as $n): ?><tr>
    <td><?= h($n['name']) ?></td><td><?= h(label_report($nodeLabels, $n['type'])) ?></td><td><?= h(label_report($levelLabels, $n['criticality'])) ?></td><td><?= h($n['rto_hours']) ?></td><td><?= h($n['risk_likelihood']) ?></td><td><?= h($n['risk_impact']) ?></td><td><?= h($n['_score'] ?? '—') ?></td><td><span class="badge <?= h($n['_level']) ?>"><?= h(label_report($levelLabels, $n['_level'])) ?></span></td>
    </tr><?php endforeach; ?>
    </table>
</section>

<section class="report-card">
    <h2>Seznam aktiv, atributy a vazby</h2>
    <?php foreach ($ranked as $n): ?>
    <section class="report-card asset-block">
    <h3><span class="asset-title"><?= h($n['name']) ?></span> <span class="badge <?= h($n['_level']) ?>"><?= h(label_report($levelLabels, $n['_level'])) ?> / <?= h($n['_score'] ?? '—') ?></span></h3>
    <?php if (trim((string)$n['description']) !== ''): ?><p><?= nl2br(h($n['description'])) ?></p><?php endif; ?>
    <table>
    <tr><th>Typ</th><td><?= h(label_report($nodeLabels, $n['type'])) ?></td><th>Kritičnost</th><td><?= h(label_report($levelLabels, $n['criticality'])) ?></td></tr>
    <tr><th>Owner</th><td><?= h($n['owner']) ?></td><th>Prostředí</th><td><?= h($n['environment'] ?: '—') ?></td></tr>
    <tr><th>C/I/A</th><td><?= h(label_report($levelLabels, $n['confidentiality'])) ?> / <?= h(label_report($levelLabels, $n['integrity_level'])) ?> / <?= h(label_report($levelLabels, $n['availability'])) ?></td><th>RTO/RPO/MTD</th><td><?= h($n['rto_hours']) ?> / <?= h($n['rpo_hours']) ?> / <?= h($n['mtd_hours']) ?> h</td></tr>
    <tr><th>Citlivost dat</th><td><?= h(label_report($sensitivityLabels, $n['data_sensitivity'])) ?></td><th>Kategorie dat</th><td><?= h($n['data_categories']) ?></td></tr>
    <tr><th>Hrozby</th><td colspan="3"><?= nl2br(h($n['threats'])) ?></td></tr>
    <tr><th>Rizikové scénáře</th><td colspan="3"><?= nl2br(h($n['risk_scenarios'])) ?></td></tr>
    <tr><th>Opatření</th><td colspan="3"><?= nl2br(h($n['risk_controls'])) ?></td></tr>
    </table>
    <?php if (!empty($byNodeOut[(int)$n['id']])): ?><strong>Odchozí vazby</strong><ul><?php foreach ($byNodeOut[(int)$n['id']] as $e): ?><li><?= h(label_report($edgeLabels, $e['type'])) ?> → <?= h($e['target_name']) ?> <span class="muted">(<?= h(label_report($nodeLabels, $e['target_type'])) ?>)</span></li><?php endforeach; ?></ul><?php endif; ?>
    <?php if (!empty($byNodeIn[(int)$n['id']])): ?><strong>Příchozí vazby</strong><ul><?php foreach ($byNodeIn[(int)$n['id']] as $e): ?><li><?= h($e['source_name']) ?> <span class="muted">(<?= h(label_report($nodeLabels, $e['source_type'])) ?>)</span> → <?= h(label_report($edgeLabels, $e['type'])) ?></li><?php endforeach; ?></ul><?php endif; ?>
    </section>
    <?php endforeach; ?>
</section>

<section class="report-card">
    <h2>Metodická poznámka ke skóre</h2>
    <p>První verze skóre je záměrně jednoduchá: základ tvoří pravděpodobnost × dopad. K tomu se přičítá váha deklarované kritičnosti, nejvyšší hodnota CIA a faktor RTO. Krátké RTO zvyšuje skóre, protože signalizuje nízkou toleranci výpadku. Pokud pravděpodobnost nebo dopad nejsou vyplněny, asset je označen jako nehodnocený a není zařazen do heatmapy jako nízké riziko. Skóre je podpůrné a nenahrazuje formální risk assessment.</p>
</section>
</div>
</body>
</html>
