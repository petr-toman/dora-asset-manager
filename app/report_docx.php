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

function docx_level_num($v): int { return ['low'=>1,'medium'=>2,'high'=>3,'critical'=>4][(string)$v] ?? 1; }
function docx_rto_factor($hours): int {
    if ($hours === null || $hours === '') return 1;
    $h = (float)$hours;
    if ($h <= 4) return 5;
    if ($h <= 8) return 4;
    if ($h <= 24) return 3;
    if ($h <= 72) return 2;
    return 1;
}
function docx_asset_score(array $n): int {
    $likelihood = max(1, min(5, (int)($n['risk_likelihood'] ?? 1)));
    $impact = max(1, min(5, (int)($n['risk_impact'] ?? 1)));
    $base = $likelihood * $impact;
    $crit = docx_level_num($n['criticality'] ?? null);
    $cia = max(docx_level_num($n['confidentiality'] ?? null), docx_level_num($n['integrity_level'] ?? null), docx_level_num($n['availability'] ?? null));
    $rto = docx_rto_factor($n['rto_hours'] ?? null);
    return $base + ($crit * 2) + ($cia * 2) + ($rto * 2);
}
function docx_score_level(int $score): string {
    if ($score >= 35) return 'critical';
    if ($score >= 27) return 'high';
    if ($score >= 18) return 'medium';
    return 'low';
}
function x($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function val($v): string { return trim((string)($v ?? '')) !== '' ? (string)$v : '—'; }
function shade_for_level(string $level): string {
    return match ($level) {
        'critical' => 'FECACA',
        'high' => 'FED7AA',
        'medium' => 'FEF3C7',
        default => 'DCFCE7',
    };
}
function paragraph(string $text = '', string $style = '', array $opts = []): string {
    $styleXml = $style !== '' ? '<w:pPr><w:pStyle w:val="'.x($style).'"/></w:pPr>' : '';
    if (!empty($opts['bold'])) {
        $rPr = '<w:rPr><w:b/></w:rPr>';
    } else {
        $rPr = '';
    }
    $parts = preg_split("/\r\n|\r|\n/", (string)$text);
    $runs = '';
    foreach ($parts as $idx => $part) {
        if ($idx > 0) $runs .= '<w:r><w:br/></w:r>';
        $space = preg_match('/^\s|\s$/u', $part) ? ' xml:space="preserve"' : '';
        $runs .= '<w:r>'.$rPr.'<w:t'.$space.'>'.x($part).'</w:t></w:r>';
    }
    return '<w:p>'.$styleXml.$runs.'</w:p>';
}
function cell(string $content, array $opts = []): string {
    $shade = isset($opts['shade']) ? '<w:shd w:fill="'.x($opts['shade']).'"/>' : '';
    $width = isset($opts['width']) ? '<w:tcW w:w="'.(int)$opts['width'].'" w:type="dxa"/>' : '';
    $vAlign = '<w:vAlign w:val="top"/>';
    $tcPr = '<w:tcPr>'.$width.$shade.$vAlign.'</w:tcPr>';
    if (str_starts_with($content, '<w:p') || str_starts_with($content, '<w:tbl')) {
        $body = $content;
    } else {
        $body = paragraph($content, '', ['bold' => !empty($opts['bold'])]);
    }
    return '<w:tc>'.$tcPr.$body.'</w:tc>';
}
function row(array $cells, bool $header = false): string {
    $trPr = $header ? '<w:trPr><w:tblHeader/></w:trPr>' : '';
    $xml = '<w:tr>'.$trPr;
    foreach ($cells as $c) {
        if (is_array($c)) $xml .= cell($c[0] ?? '', $c[1] ?? []);
        else $xml .= cell((string)$c);
    }
    return $xml.'</w:tr>';
}
function table(array $rows, array $opts = []): string {
    $xml = '<w:tbl><w:tblPr><w:tblStyle w:val="TableGrid"/><w:tblW w:w="0" w:type="auto"/><w:tblLook w:firstRow="1" w:lastRow="0" w:firstColumn="0" w:lastColumn="0" w:noHBand="0" w:noVBand="1"/></w:tblPr>';
    foreach ($rows as $i => $r) $xml .= row($r, $i === 0 && !empty($opts['header']));
    return $xml.'</w:tbl>';
}
function kv_table(array $pairs): string {
    $rows = [];
    foreach ($pairs as $p) $rows[] = [[$p[0], ['bold'=>true, 'width'=>2500, 'shade'=>'F8FAFC']], val($p[1])];
    return table($rows);
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
    $score = docx_asset_score($n);
    $n['_score'] = $score;
    $n['_level'] = docx_score_level($score);
    $heat[$l][$i][] = $n;
    $ranked[] = $n;
}
usort($ranked, fn($a,$b) => $b['_score'] <=> $a['_score']);
$countsByType = [];
foreach ($nodes as $n) $countsByType[$n['type']] = ($countsByType[$n['type']] ?? 0) + 1;

$body = '';
$body .= paragraph('DORA evidence IT aktiv - report', 'Title');
$body .= paragraph('Vygenerováno: '.date('Y-m-d H:i').'. Tento dokument byl vytvořen z aplikace Evidence IT aktiv.', 'Subtitle');

$body .= paragraph('1. Manažerské shrnutí', 'Heading1');
$body .= paragraph('Evidence obsahuje '.count($nodes).' uzlů a '.count($edges).' vazeb. Graf zachycuje ICT a informační aktiva, procesy, dodavatele a jejich závislosti. Kritičnost je odvozena z evidované kritičnosti aktiva, CIA hodnocení, RTO a základního rizikového hodnocení pravděpodobnost × dopad.');
$typeRows = [[['Typ uzlu', ['bold'=>true, 'shade'=>'E2E8F0']], ['Počet', ['bold'=>true, 'shade'=>'E2E8F0']]]];
foreach ($countsByType as $type => $cnt) $typeRows[] = [$type, (string)$cnt];
$body .= table($typeRows, ['header'=>true]);

$body .= paragraph('2. Heatmapa rizik', 'Heading1');
$body .= paragraph('Svisle pravděpodobnost, vodorovně dopad. Číslo v buňce znamená počet assetů v dané kombinaci. V buňkách jsou uvedeny maximálně tři příklady assetů.');
$heatRows = [[['Pravd. \ Dopad', ['bold'=>true, 'shade'=>'E2E8F0']]]];
for ($i=1;$i<=5;$i++) $heatRows[0][] = [(string)$i, ['bold'=>true, 'shade'=>'E2E8F0']];
for ($l=5;$l>=1;$l--) {
    $r = [[(string)$l, ['bold'=>true, 'shade'=>'E2E8F0']]];
    for ($i=1;$i<=5;$i++) {
        $count = count($heat[$l][$i]);
        $names = array_map(fn($n) => $n['name'], array_slice($heat[$l][$i], 0, 3));
        $txt = $count."\n".implode("\n", $names).($count > 3 ? "\n…" : '');
        $r[] = [$txt, ['shade'=>shade_for_level(docx_score_level($l*$i + 10))]];
    }
    $heatRows[] = $r;
}
$body .= table($heatRows, ['header'=>true]);

$body .= paragraph('3. Nejkritičtější aktiva podle kombinovaného skóre', 'Heading1');
$topRows = [[['Asset', ['bold'=>true, 'shade'=>'E2E8F0']], ['Typ', ['bold'=>true, 'shade'=>'E2E8F0']], ['Kritičnost', ['bold'=>true, 'shade'=>'E2E8F0']], ['RTO', ['bold'=>true, 'shade'=>'E2E8F0']], ['Pravd.', ['bold'=>true, 'shade'=>'E2E8F0']], ['Dopad', ['bold'=>true, 'shade'=>'E2E8F0']], ['Skóre', ['bold'=>true, 'shade'=>'E2E8F0']], ['Úroveň', ['bold'=>true, 'shade'=>'E2E8F0']]]];
foreach (array_slice($ranked,0,20) as $n) {
    $topRows[] = [val($n['name']), val($n['type']), val($n['criticality']), val($n['rto_hours']), val($n['risk_likelihood']), val($n['risk_impact']), (string)$n['_score'], [$n['_level'], ['shade'=>shade_for_level($n['_level'])]]];
}
$body .= table($topRows, ['header'=>true]);

$body .= paragraph('4. Seznam aktiv, atributy a vazby', 'Heading1');
foreach ($ranked as $n) {
    $body .= paragraph($n['name'].' — '.$n['_level'].' / '.$n['_score'], 'Heading2');
    if (trim((string)$n['description']) !== '') $body .= paragraph($n['description']);
    $body .= kv_table([
        ['Typ', $n['type']], ['Kritičnost', $n['criticality']], ['Owner', $n['owner']], ['Business owner', $n['business_owner']], ['Technical owner', $n['technical_owner']], ['Vendor / manufacturer', $n['vendor_manufacturer']], ['Prostředí', $n['environment']], ['Stav', $n['status']], ['Lifecycle', $n['lifecycle_state']], ['C/I/A', val($n['confidentiality']).' / '.val($n['integrity_level']).' / '.val($n['availability'])], ['RTO/RPO/MTD', val($n['rto_hours']).' / '.val($n['rpo_hours']).' / '.val($n['mtd_hours']).' h'], ['Citlivost dat', $n['data_sensitivity']], ['Kategorie dat', $n['data_categories']], ['Lokalita', $n['location']], ['Poslední revize', $n['last_reviewed_at']], ['Hrozby', $n['threats']], ['Rizikové scénáře', $n['risk_scenarios']], ['Opatření / kontroly', $n['risk_controls']], ['Reziduální riziko', $n['residual_risk']], ['Good-to-know poznámky', $n['good_to_know']],
    ]);
    if (!empty($byNodeOut[(int)$n['id']])) {
        $body .= paragraph('Odchozí vazby', 'Heading3');
        $rows = [[['Typ vazby', ['bold'=>true, 'shade'=>'E2E8F0']], ['Cíl', ['bold'=>true, 'shade'=>'E2E8F0']], ['Typ cíle', ['bold'=>true, 'shade'=>'E2E8F0']], ['Popis', ['bold'=>true, 'shade'=>'E2E8F0']]]];
        foreach ($byNodeOut[(int)$n['id']] as $e) $rows[] = [$edgeLabels[$e['type']] ?? $e['type'], $e['target_name'], $e['target_type'], $e['description']];
        $body .= table($rows, ['header'=>true]);
    }
    if (!empty($byNodeIn[(int)$n['id']])) {
        $body .= paragraph('Příchozí vazby', 'Heading3');
        $rows = [[['Zdroj', ['bold'=>true, 'shade'=>'E2E8F0']], ['Typ zdroje', ['bold'=>true, 'shade'=>'E2E8F0']], ['Typ vazby', ['bold'=>true, 'shade'=>'E2E8F0']], ['Popis', ['bold'=>true, 'shade'=>'E2E8F0']]]];
        foreach ($byNodeIn[(int)$n['id']] as $e) $rows[] = [$e['source_name'], $e['source_type'], $edgeLabels[$e['type']] ?? $e['type'], $e['description']];
        $body .= table($rows, ['header'=>true]);
    }
}

$body .= paragraph('5. Metodická poznámka ke skóre', 'Heading1');
$body .= paragraph('První verze skóre je záměrně jednoduchá: základ tvoří pravděpodobnost × dopad. K tomu se přičítá váha deklarované kritičnosti, nejvyšší hodnota CIA a faktor RTO. Krátké RTO zvyšuje skóre, protože signalizuje nízkou toleranci výpadku. Skóre je podpůrné a nenahrazuje formální risk assessment.');

$documentXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:wpc="http://schemas.microsoft.com/office/word/2010/wordprocessingCanvas" xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006" xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:m="http://schemas.openxmlformats.org/officeDocument/2006/math" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:wp14="http://schemas.microsoft.com/office/word/2010/wordprocessingDrawing" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" xmlns:w10="urn:schemas-microsoft-com:office:word" xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:w14="http://schemas.microsoft.com/office/word/2010/wordml" xmlns:w15="http://schemas.microsoft.com/office/word/2012/wordml" mc:Ignorable="w14 w15 wp14"><w:body>'.$body.'<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1134" w:right="850" w:bottom="1134" w:left="850" w:header="708" w:footer="708" w:gutter="0"/></w:sectPr></w:body></w:document>';

$stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/><w:qFormat/><w:rPr><w:rFonts w:ascii="Aptos" w:hAnsi="Aptos" w:eastAsia="Aptos" w:cs="Aptos"/><w:sz w:val="21"/><w:color w:val="111827"/></w:rPr><w:pPr><w:spacing w:after="120"/></w:pPr></w:style><w:style w:type="paragraph" w:styleId="Title"><w:name w:val="Title"/><w:qFormat/><w:rPr><w:b/><w:sz w:val="40"/><w:color w:val="0F172A"/></w:rPr><w:pPr><w:spacing w:after="180"/></w:pPr></w:style><w:style w:type="paragraph" w:styleId="Subtitle"><w:name w:val="Subtitle"/><w:qFormat/><w:rPr><w:i/><w:sz w:val="22"/><w:color w:val="64748B"/></w:rPr></w:style><w:style w:type="paragraph" w:styleId="Heading1"><w:name w:val="heading 1"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:qFormat/><w:rPr><w:b/><w:sz w:val="30"/><w:color w:val="1D4ED8"/></w:rPr><w:pPr><w:spacing w:before="360" w:after="180"/><w:outlineLvl w:val="0"/></w:pPr></w:style><w:style w:type="paragraph" w:styleId="Heading2"><w:name w:val="heading 2"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:qFormat/><w:rPr><w:b/><w:sz w:val="25"/><w:color w:val="0F172A"/></w:rPr><w:pPr><w:spacing w:before="280" w:after="120"/><w:outlineLvl w:val="1"/></w:pPr></w:style><w:style w:type="paragraph" w:styleId="Heading3"><w:name w:val="heading 3"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:qFormat/><w:rPr><w:b/><w:sz w:val="22"/><w:color w:val="334155"/></w:rPr><w:pPr><w:spacing w:before="180" w:after="80"/><w:outlineLvl w:val="2"/></w:pPr></w:style><w:style w:type="table" w:styleId="TableGrid"><w:name w:val="Table Grid"/><w:tblPr><w:tblBorders><w:top w:val="single" w:sz="4" w:space="0" w:color="CBD5E1"/><w:left w:val="single" w:sz="4" w:space="0" w:color="CBD5E1"/><w:bottom w:val="single" w:sz="4" w:space="0" w:color="CBD5E1"/><w:right w:val="single" w:sz="4" w:space="0" w:color="CBD5E1"/><w:insideH w:val="single" w:sz="4" w:space="0" w:color="CBD5E1"/><w:insideV w:val="single" w:sz="4" w:space="0" w:color="CBD5E1"/></w:tblBorders><w:tblCellMar><w:top w:w="80" w:type="dxa"/><w:left w:w="80" w:type="dxa"/><w:bottom w:w="80" w:type="dxa"/><w:right w:w="80" w:type="dxa"/></w:tblCellMar></w:tblPr></w:style></w:styles>';

$contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/><Override PartName="/word/settings.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml"/></Types>';
$rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>';
$docRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings" Target="settings.xml"/></Relationships>';
$settings = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:settings xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:zoom w:percent="100"/><w:defaultTabStop w:val="720"/></w:settings>';

if (!class_exists('ZipArchive')) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "PHP extension ZipArchive není dostupné. Sestav Docker image znovu; Dockerfile v této verzi instaluje rozšíření zip.";
    exit;
}

$tmp = tempnam(sys_get_temp_dir(), 'dora-report-');
$zip = new ZipArchive();
if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Nepodařilo se vytvořit dočasný DOCX soubor.';
    exit;
}
$zip->addFromString('[Content_Types].xml', $contentTypes);
$zip->addFromString('_rels/.rels', $rels);
$zip->addFromString('word/document.xml', $documentXml);
$zip->addFromString('word/_rels/document.xml.rels', $docRels);
$zip->addFromString('word/styles.xml', $stylesXml);
$zip->addFromString('word/settings.xml', $settings);
$zip->close();

$filename = 'dora-assets-report-'.date('Ymd-His').'.docx';
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Content-Length: '.filesize($tmp));
header('Cache-Control: no-store, no-cache, must-revalidate');
readfile($tmp);
unlink($tmp);
