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
        <button id="btnAddNode" class="primary">+ Uzel</button>
        <button id="btnAddEdge">+ Vazba</button>
        <button id="btnSaveView">Uložit view</button>
        <button id="btnExport">Export JSON</button>
        <a class="button" href="/report.php" target="_blank">Report / PDF</a>
    </div>
</header>

<main class="layout">
    <aside class="sidebar">
        <section class="panel">
            <h2>Pohled</h2>
            <label>Uložený view</label>
            <select id="viewSelect"></select>

            <label>Dynamický režim</label>
            <select id="modeSelect">
                <option value="saved">Uložený / celý graf</option>
                <option value="hardware">Hardware view</option>
                <option value="data">Data view</option>
                <option value="process">Process view</option>
                <option value="supplier">Supplier view</option>
                <option value="critical">Kritická a vysoká aktiva</option>
                <option value="personal_data">Osobní data</option>
                <option value="impact">Impact vybraného uzlu</option>
            </select>
            <button id="btnReload">Načíst graf</button>
        </section>

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
            <div><span class="dot supplier"></span> dodavatel</div>
        </section>
    </aside>

    <section class="canvas-wrap">
        <div id="cy"></div>
    </section>
</main>

<div id="nodeModal" class="modal hidden">
    <div class="modal-content large">
        <div class="modal-header">
            <h2 id="nodeModalTitle">Uzel</h2>
            <button class="icon" data-close="nodeModal">×</button>
        </div>
        <form id="nodeForm" class="form-grid">
            <input type="hidden" name="id">

            <label>Název<input name="name" required></label>
            <label>Typ<select name="type" id="nodeTypeSelect"></select></label>
            <label>Kritičnost<select name="criticality"><option></option><option>low</option><option>medium</option><option>high</option><option>critical</option></select></label>
            <label>Prostředí<select name="environment"><option></option><option>prod</option><option>test</option><option>dev</option><option>archive</option><option>unknown</option></select></label>

            <label class="span2">Popis<textarea name="description" rows="3"></textarea></label>

            <label>Owner<input name="owner"></label>
            <label>Business owner<input name="business_owner"></label>
            <label>Technical owner<input name="technical_owner"></label>
            <label>Stav<select name="status"><option></option><option>active</option><option>planned</option><option>retired</option><option>unknown</option></select></label>

            <label>Důvěrnost<select name="confidentiality"><option></option><option>low</option><option>medium</option><option>high</option><option>critical</option></select></label>
            <label>Integrita<select name="integrity_level"><option></option><option>low</option><option>medium</option><option>high</option><option>critical</option></select></label>
            <label>Dostupnost<select name="availability"><option></option><option>low</option><option>medium</option><option>high</option><option>critical</option></select></label>
            <label>Lifecycle<select name="lifecycle_state"><option></option><option>production</option><option>test</option><option>development</option><option>archived</option><option>unknown</option></select></label>

            <label>RTO [h]<input name="rto_hours" type="number" min="0"></label>
            <label>RPO [h]<input name="rpo_hours" type="number" min="0"></label>
            <label>MTD [h]<input name="mtd_hours" type="number" min="0"></label>
            <label>Lokalita<input name="location"></label>

            <label>Citlivost dat<select name="data_sensitivity"><option></option><option value="public">veřejná</option><option value="private">soukromá</option><option value="secret">tajná</option></select></label>
            <label class="span2">Kategorie dat<input name="data_categories" placeholder="personal, financial, business..."></label>
            <label>Revize po měsících<input name="review_frequency_months" type="number" min="0"></label>
            <label>Poslední revize<input name="last_reviewed_at" type="date"></label>

            <label class="span4">Good-to-know poznámky<textarea name="good_to_know" rows="4"></textarea></label>

            <h3 class="span4 section-title">Hrozby a rizika</h3>
            <label class="span2">Hrozby<textarea name="threats" rows="3" placeholder="např. výpadek infrastruktury, ransomware, chyba dodavatele..."></textarea></label>
            <label class="span2">Rizikové scénáře<textarea name="risk_scenarios" rows="3" placeholder="co se může stát a jaký bude dopad..."></textarea></label>
            <label>Pravděpodobnost 1–5<input name="risk_likelihood" type="number" min="1" max="5"></label>
            <label>Dopad 1–5<input name="risk_impact" type="number" min="1" max="5"></label>
            <label class="span2">Opatření / kontroly<input name="risk_controls" placeholder="backup, monitoring, DR plán..."></label>
            <label class="span4">Reziduální riziko<textarea name="residual_risk" rows="2"></textarea></label>

            <div class="form-actions span4">
                <button type="button" data-close="nodeModal">Zavřít</button>
                <button type="submit" class="primary">Uložit</button>
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
            <label>Kritičnost<select name="criticality"><option></option><option>low</option><option>medium</option><option>high</option><option>critical</option></select></label>
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
            <h2>Uložit view</h2>
            <button class="icon" data-close="viewModal">×</button>
        </div>
        <form id="viewForm" class="form-grid small">
            <input type="hidden" name="id">
            <label class="span2">Název<input name="name" required></label>
            <label class="span2">Popis<textarea name="description" rows="3"></textarea></label>
            <div class="form-actions span2">
                <button type="button" data-close="viewModal">Zavřít</button>
                <button type="submit" class="primary">Uložit</button>
            </div>
        </form>
    </div>
</div>

<div id="toast" class="toast hidden"></div>
<script src="/assets/app.js"></script>
</body>
</html>
