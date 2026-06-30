<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Evidence IT aktiv / DORA asset map</title>
    <link rel="stylesheet" href="/assets/style.css">
    <script src="https://unpkg.com/cytoscape@3.30.3/dist/cytoscape.min.js"></script>
</head>
<body>
<header class="topbar">
    <div>
        <h1>Evidence IT aktiv</h1>
        <span>DORA ICT / information asset map</span>
    </div>
    <div class="top-actions">
        <button id="btnShowGraph" class="primary">Graf</button>
        <button id="btnShowAssetsTable">Assety tabulka</button>
        <button id="btnShowEdgesTable">Vazby tabulka</button>
        <button id="btnAddNode">+ Uzel</button>
        <button id="btnAddEdge">+ Vazba</button>
        <button id="btnExport">Export JSON</button>
        <a class="button" href="/report.php" target="_blank">Report / PDF</a>
        <a class="button" href="/report_docx.php">Report DOCX</a>
    </div>
</header>

<main id="graphView" class="layout">
    <aside class="sidebar" id="sidebar">
        <button id="btnToggleSidebar" class="sidebar-toggle" type="button" title="Skrýt levý panel">‹</button>
        <section class="panel">
            <h2>Filtr v UI</h2>
            <input id="searchBox" type="search" placeholder="Hledat název / popis...">
            <label>Typ uzlu</label>
            <select id="typeFilter">
                <option value="">vše</option>
            </select>
            <label>Kritičnost</label>
            <select id="criticalityFilter">
                <option value="">vše</option>
                <option value="critical">critical</option>
                <option value="high">high</option>
                <option value="medium">medium</option>
                <option value="low">low</option>
            </select>
            <button id="btnClearFilter">Vyčistit filtr</button>
        </section>

        <section class="panel selected-panel">
            <h2>Výběr</h2>
            <div id="selectedInfo">Nic není vybráno.</div>
            <div class="small-actions">
                <button id="btnEditSelected">Editovat</button>
                <button id="btnDeleteSelected" class="danger">Smazat</button>
            </div>
        </section>

        <section class="panel legend">
            <h2>Legenda</h2>
            <div><span class="dot hardware"></span> hardware</div>
            <div><span class="dot software"></span> software</div>
            <div><span class="dot data"></span> data</div>
            <div><span class="dot process"></span> proces</div>
            <div><span class="dot business-function"></span> business funkce</div>
            <div><span class="dot third-party"></span> 3. strana</div>
            <div><span class="dot documentation"></span> dokumentace / ICT služba</div>
        </section>

        <section class="panel">
            <h2>Pohled</h2>
            <label>Uložený view</label>
            <select id="viewSelect"></select>
            <div class="view-actions">
                <button id="btnSaveView">Uložit view</button>
                <button id="btnCloneView">Nový view z aktuálního</button>
                <button id="btnDeleteView" class="danger subtle-danger">Smazat view</button>
            </div>

            <label>Dynamický režim</label>
            <select id="modeSelect">
                <option value="saved">Uložený / celý graf</option>
                <option value="hardware">Hardware view</option>
                <option value="data">Data view</option>
                <option value="process">Process view</option>
                <option value="third_party">3. strany view</option>
                <option value="critical">Kritická a vysoká aktiva</option>
                <option value="personal_data">Osobní data</option>
                <option value="impact">Impact vybraného uzlu</option>
            </select>
            <button id="btnReload">Načíst graf</button>

            <div class="snap-box">
                <label class="checkline"><input id="snapToGrid" type="checkbox"> Snap to grid</label>
                <label>Velikost mřížky [px]<input id="gridSize" type="number" min="10" max="200" step="5" value="40"></label>
                <button id="btnSnapNow">Zarovnat aktuální view</button>
            </div>
        </section>

        <section class="panel model-panel">
            <h2>Model / projekt</h2>
            <label>Aktuální model</label>
            <select id="modelSelect"></select>
            <div class="model-actions">
                <button id="btnNewModel">Nový prázdný</button>
                <button id="btnCopyModel">Kopie aktuálního</button>
                <button id="btnDeleteModel" class="danger subtle-danger">Smazat model</button>
                <button id="btnDownloadModel">Stáhnout DB</button>
                <button id="btnUploadModel">Nahrát DB</button>
                <input id="modelUploadInput" type="file" accept=".sqlite,.db,application/vnd.sqlite3,application/octet-stream" class="hidden">
            </div>
            <p class="hint">Každý model je samostatný SQLite soubor v <code>/data/models</code>. <code>demo.sqlite</code> je ukázkový model; lze jej zkopírovat, použít jako inspiraci nebo smazat, pokud existuje jiný model. Smazání přesune soubor do <code>/data/deleted</code>.</p>
        </section>
    </aside>

    <section class="canvas-wrap">
        <div id="cy"></div>
    </section>
</main>

<section id="assetsTableView" class="table-view hidden">
    <div class="table-toolbar">
        <div>
            <h2>Assety jako tabulka</h2>
            <p>Edituj přímo v buňkách. Změny se ukládají až tlačítkem Save. Podporuje filtrování, sortování a vložení TSV z Excelu.</p>
        </div>
        <div>
            <input id="assetsTableFilter" type="search" placeholder="Filtrovat assety...">
            <button id="btnAddAssetRow">+ Řádek</button>
            <button id="btnDeleteAssetRows" class="danger subtle-danger">Smazat řádku</button>
            <button id="btnSaveAssetsTable" class="primary">Save</button>
            <button id="btnReloadAssetsTable">Reload</button>
            <button id="btnToggleAssetRowsCompact" type="button">Plné zobrazení řádků</button>
            <button id="btnImportAssetsCsv">Import CSV</button>
            <button id="btnExportAssetsCsv">Export CSV</button>
            <button id="btnCopyAssetsTable">Kopírovat tabulku</button>
            <input id="assetsCsvInput" type="file" accept=".csv,text/csv,text/plain" class="hidden">
        </div>
    </div>
    <div class="table-wrap">
        <table id="assetsGrid" class="data-grid"></table>
    </div>
</section>

<section id="edgesTableView" class="table-view hidden">
    <div class="table-toolbar">
        <div>
            <h2>Vazby jako tabulka</h2>
            <p>Edituj přímo v buňkách. Zdroj a cíl se editují přes ID uzlu; názvy jsou pomocné read-only sloupce. Ukládá se až tlačítkem Save.</p>
        </div>
        <div>
            <input id="edgesTableFilter" type="search" placeholder="Filtrovat vazby...">
            <button id="btnAddEdgeRow">+ Řádek</button>
            <button id="btnDeleteEdgeRows" class="danger subtle-danger">Smazat řádku</button>
            <button id="btnSaveEdgesTable" class="primary">Save</button>
            <button id="btnReloadEdgesTable">Reload</button>
            <button id="btnCopyEdgesTable">Kopírovat tabulku</button>
        </div>
    </div>
    <div class="table-wrap">
        <table id="edgesGrid" class="data-grid"></table>
    </div>
</section>

<div id="nodeModal" class="modal hidden">
    <div class="modal-content large asset-modal-content">
        <div class="modal-header asset-modal-header">
            <div class="asset-modal-heading">
                <h2 id="nodeModalTitle">Nový asset</h2>
                <div id="nodeModalSubtitle" class="asset-modal-subtitle">Nový asset</div>
            </div>
            <div class="modal-header-actions">
                <button type="submit" form="nodeForm" class="primary">Uložit</button>
                <button type="button" data-close="nodeModal">Zavřít</button>
            </div>
        </div>
        <form id="nodeForm" class="form-grid asset-form">
            <input type="hidden" name="id">

            <h3 class="span4 section-title first">Základní informace</h3>
            <label>Název<input name="name" required></label>
            <label>Typ<select name="type" id="nodeTypeSelect"></select></label>
            <label>Kritičnost <span class="help" title="Celkový význam aktiva pro provoz, procesy a zpracování dat.">ⓘ</span><select name="criticality" data-choice="criticality"></select></label>
            <label>Prostředí <span class="help" title="Provozní prostředí aktiva, například prod, test, dev nebo archiv.">ⓘ</span><select name="environment" data-choice="environment"></select></label>

            <label class="span2 desc-field">Popis<textarea name="description" rows="3"></textarea></label>
            <div class="span2 owner-grid">
                <label>Owner <span class="help" title="Vlastník aktiva z hlediska odpovědnosti za evidenci a rozhodování.">ⓘ</span><input name="owner"></label>
                <label>Business owner <span class="help" title="Business vlastník procesu/služby, který aktivum používá nebo za něj věcně odpovídá.">ⓘ</span><input name="business_owner"></label>
                <label>Technical owner <span class="help" title="Technický vlastník nebo tým odpovědný za provoz a technickou správu aktiva.">ⓘ</span><input name="technical_owner"></label>
                <label>Vendor / manufacturer <span class="help" title="Výrobce nebo vendor produktu či technologie. Například Microsoft u M365, SAP u SAP ECC.">ⓘ</span><input name="vendor_manufacturer"></label>
            </div>

            <h3 class="span4 section-title compact">Klasifikace a odolnost</h3>
            <label>Stav<select name="status" data-choice="status"></select></label>
            <label>Důvěrnost (CIA) <span class="help" title="Confidentiality — jak závažné by bylo neoprávněné zpřístupnění informací.">ⓘ</span><select name="confidentiality" data-choice="cia"></select></label>
            <label>Integrita (CIA) <span class="help" title="Integrity — jak závažné by bylo neoprávněné nebo chybné pozměnění dat.">ⓘ</span><select name="integrity_level" data-choice="cia"></select></label>
            <label>Dostupnost (CIA) <span class="help" title="Availability — jak závažná by byla nedostupnost aktiva nebo služby.">ⓘ</span><select name="availability" data-choice="cia"></select></label>

            <label>Lifecycle <span class="help" title="Fáze životního cyklu aktiva, například production, test, development nebo archived.">ⓘ</span><select name="lifecycle_state" data-choice="lifecycle_state"></select></label>
            <label>RTO [h] <span class="help" title="Recovery Time Objective — cílová doba obnovy. Do kolika hodin musí být aktivum nebo služba obnovena.">ⓘ</span><input name="rto_hours" type="number" min="0"></label>
            <label>RPO [h] <span class="help" title="Recovery Point Objective — přípustná ztráta dat. O kolik hodin dat maximálně smíme přijít.">ⓘ</span><input name="rpo_hours" type="number" min="0"></label>
            <label>MTD [h] <span class="help" title="Maximum Tolerable Downtime — maximálně tolerovatelná doba výpadku. Delší výpadek je už nepřijatelný.">ⓘ</span><input name="mtd_hours" type="number" min="0"></label>

            <h3 class="span4 section-title compact">Data, lokalita a revize</h3>
            <label>Lokalita<input name="location"></label>
            <label>Citlivost dat <span class="help" title="Klasifikace citlivosti dat z pohledu přístupu a ochrany.">ⓘ</span><select name="data_sensitivity" data-choice="data_sensitivity"></select></label>
            <label>Revize po měsících <span class="help" title="Interval pravidelného přezkumu údajů o aktivu.">ⓘ</span><input name="review_frequency_months" type="number" min="0"></label>
            <label>Poslední revize <span class="help" title="Datum posledního přezkumu správnosti a úplnosti údajů o aktivu.">ⓘ</span><input name="last_reviewed_at" type="date"></label>

            <label class="span4">Kategorie dat<input name="data_categories" placeholder="personal, financial, business..."></label>
            <label class="span4">Good-to-know poznámky<textarea name="good_to_know" rows="4"></textarea></label>

            <h3 class="span4 section-title">Hrozby a rizika</h3>
            <label class="span2">Hrozby<textarea name="threats" rows="3" placeholder="např. výpadek infrastruktury, ransomware, chyba dodavatele..."></textarea></label>
            <label class="span2">Rizikové scénáře<textarea name="risk_scenarios" rows="3" placeholder="co se může stát a jaký bude dopad..."></textarea></label>
            <label>Pravděpodobnost 1–5<input name="risk_likelihood" type="number" min="1" max="5"></label>
            <label>Dopad 1–5<input name="risk_impact" type="number" min="1" max="5"></label>
            <label class="span2">Opatření / kontroly<input name="risk_controls" placeholder="backup, monitoring, DR plán..."></label>
            <label class="span4">Reziduální riziko<textarea name="residual_risk" rows="2"></textarea></label>

            <section id="nodeEdgesSection" class="span4 node-edges-section hidden">
                <div class="node-edge-section-head">
                    <div>
                        <div class="section-title node-edge-title">Vazby assetu <span class="help" title="Orientované vazby ve směru Asset A → typ vazby → Asset B. Aktuální asset je vždy uzamčený.">ⓘ</span></div>
                    </div>
                    <div class="node-edge-toolbar">
                        <button type="button" id="btnAddOutgoingNodeEdge" title="Přidat vazbu z aktuálního assetu na jiný asset">+ Odchozí</button>
                        <button type="button" id="btnAddIncomingNodeEdge" title="Přidat vazbu z jiného assetu na aktuální asset">+ Příchozí</button>
                        <button type="button" id="btnReloadNodeEdges" class="secondary" title="Znovu načíst vazby z databáze">↻</button>
                    </div>
                </div>
                <div class="node-edge-wrap">
                    <table id="nodeEdgesGrid" class="node-edges-grid">
                        <colgroup>
                            <col class="edge-col-asset">
                            <col class="edge-col-type">
                            <col class="edge-col-asset">
                            <col class="edge-col-criticality">
                            <col class="edge-col-description">
                            <col class="edge-col-action">
                        </colgroup>
                        <thead>
                            <tr>
                                <th>Asset A</th>
                                <th>Typ vazby</th>
                                <th>Asset B</th>
                                <th>Kritičnost</th>
                                <th>Popis</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </section>

            <div class="form-actions span4 sticky-actions asset-modal-footer">
                <div class="asset-modal-footer-left">
                    <button type="button" id="btnDeleteNodeFromModal" class="danger subtle-danger">Smazat asset</button>
                </div>
                <div class="asset-modal-footer-right">
                    <button type="button" data-close="nodeModal">Zavřít</button>
                    <button type="submit" class="primary">Uložit</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div id="edgeModal" class="modal hidden">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Vazba</h2>
            <button class="icon" data-close="edgeModal">×</button>
        </div>
        <form id="edgeForm" class="form-grid small">
            <input type="hidden" name="id">
            <label>Zdroj<select name="source_node_id" id="edgeSource"></select></label>
            <label>Cíl<select name="target_node_id" id="edgeTarget"></select></label>
            <label>Typ vazby<select name="type" id="edgeTypeSelect"></select></label>
            <label>Kritičnost<select name="criticality" data-choice="criticality"></select></label>
            <label class="span2">Popis<textarea name="description" rows="3"></textarea></label>
            <div class="form-actions span2">
                <button type="button" data-close="edgeModal">Zavřít</button>
                <button type="submit" class="primary">Uložit</button>
            </div>
        </form>
    </div>
</div>

<div id="viewModal" class="modal hidden">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="viewModalTitle">Uložit view</h2>
            <button class="icon" data-close="viewModal">×</button>
        </div>
        <form id="viewForm" class="form-grid small">
            <input type="hidden" name="id">
            <input type="hidden" name="source_view_id">
            <input type="hidden" name="mode" value="save">
            <label class="span2">Název<input name="name" required></label>
            <label class="span2">Popis<textarea name="description" rows="3"></textarea></label>
            <div class="form-actions span2">
                <button type="button" data-close="viewModal">Zavřít</button>
                <button type="submit" class="primary">Uložit</button>
            </div>
        </form>
    </div>
</div>


<div id="csvImportModal" class="modal hidden">
    <div class="modal-content large">
        <div class="modal-header">
            <h2>Import assetů z CSV</h2>
            <button class="icon" data-close="csvImportModal">×</button>
        </div>
        <div class="csv-import-body">
            <p id="csvImportSummary" class="hint">Nahraj CSV exportované z Excelu ve struktuře assetové tabulky.</p>
            <label class="inline-check"><input id="csvUpdateById" type="checkbox"> Aktualizovat existující assety podle ID místo vložení jako nové</label>
            <div id="csvImportWarnings" class="csv-warnings hidden"></div>
            <div class="csv-preview-wrap">
                <table id="csvPreviewGrid" class="data-grid csv-preview-grid"></table>
            </div>
            <div class="form-actions">
                <button type="button" data-close="csvImportModal">Zavřít</button>
                <button type="button" id="btnConfirmAssetsCsvImport" class="primary" disabled>Importovat do DB</button>
            </div>
        </div>
    </div>
</div>

<div id="toast" class="toast hidden"></div>
<script src="/assets/app.js"></script>
</body>
</html>
