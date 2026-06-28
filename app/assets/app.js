const api = 'api.php';
let cy;
let meta = { node_types: {}, edge_types: {} };
let graph = { nodes: [], edges: [] };
let allNodeLookup = new Map();
let selected = null;
let currentViewId = 1;
let currentNodeEdges = [];
let currentNodeDeletedEdges = [];
let nodeEdgesDirty = false;

const $ = (sel) => document.querySelector(sel);
const $$ = (sel) => Array.from(document.querySelectorAll(sel));

function toast(msg) {
    const t = $('#toast');
    t.textContent = msg;
    t.classList.remove('hidden');
    setTimeout(() => t.classList.add('hidden'), 2600);
}

async function fetchJson(url, options = {}) {
    const res = await fetch(url, options);
    const data = await res.json();
    if (!data.ok) throw new Error(data.error || 'Neznámá chyba');
    return data;
}

async function postJson(action, payload) {
    return fetchJson(`${api}?action=${encodeURIComponent(action)}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });
}

function fillSelect(select, options, includeEmpty = true) {
    select.innerHTML = '';
    if (includeEmpty) select.append(new Option('', ''));
    Object.entries(options).forEach(([value, label]) => select.append(new Option(label, value)));
}

async function loadMeta() {
    meta = await fetchJson(`${api}?action=meta`);
    fillSelect($('#nodeTypeSelect'), meta.node_types, false);
    fillSelect($('#typeFilter'), meta.node_types, true);
    fillSelect($('#edgeTypeSelect'), meta.edge_types, false);
}

async function loadViews() {
    const data = await fetchJson(`${api}?action=get_views`);
    const select = $('#viewSelect');
    select.innerHTML = '';
    data.views.forEach(v => select.append(new Option(v.name, v.id)));
    if (![...select.options].some(o => o.value == currentViewId)) currentViewId = data.views[0]?.id || 1;
    select.value = currentViewId;
}

async function loadNodeLookup() {
    const data = await fetchJson(`${api}?action=get_node_lookup`);
    allNodeLookup = new Map((data.nodes || []).map(n => [String(n.id), n]));
    return allNodeLookup;
}

async function loadModels() {
    const data = await fetchJson(`${api}?action=get_models`);
    const select = $('#modelSelect');
    if (!select) return data;
    select.innerHTML = '';
    data.models.forEach(name => {
        const label = name === data.demo ? `${name} (demo)` : name;
        select.append(new Option(label, name));
    });
    select.value = data.current;
    return data;
}

async function reloadAfterModelChange(message) {
    sessionStorage.setItem('doraPendingToast', message || 'Model změněn');
    window.location.reload();
}

function showPendingToast() {
    const msg = sessionStorage.getItem('doraPendingToast');
    if (msg) {
        sessionStorage.removeItem('doraPendingToast');
        toast(msg);
    }
}

function initSidebarToggle() {
    const collapsed = localStorage.getItem('doraSidebarCollapsed') === '1';
    document.body.classList.toggle('sidebar-collapsed', collapsed);
    const btn = $('#btnToggleSidebar');
    if (!btn) return;
    const update = () => {
        const isCollapsed = document.body.classList.contains('sidebar-collapsed');
        btn.textContent = isCollapsed ? '›' : '‹';
        btn.title = isCollapsed ? 'Rozbalit levý panel' : 'Skrýt levý panel';
        localStorage.setItem('doraSidebarCollapsed', isCollapsed ? '1' : '0');
        setTimeout(() => { if (cy) cy.resize(); }, 180);
    };
    update();
    btn.addEventListener('click', () => {
        document.body.classList.toggle('sidebar-collapsed');
        update();
    });
}

async function switchModel() {
    const name = $('#modelSelect').value;
    await postJson('switch_model', { name });
    await reloadAfterModelChange(`Model přepnut: ${name}`);
}

async function createNewModel() {
    const name = prompt('Název nového prázdného modelu:', `novy-model-${new Date().toISOString().slice(0,10)}`);
    if (!name) return;
    const res = await postJson('create_model', { name });
    await reloadAfterModelChange(`Vytvořen nový model: ${res.model}`);
}

async function copyCurrentModel() {
    const current = $('#modelSelect').value || 'model.sqlite';
    const base = current.replace(/\.sqlite$/i, '');
    const def = `${base}-kopie-${new Date().toISOString().replace(/[-:T]/g,'').slice(0,14)}`;
    const name = prompt('Název kopie aktuálního modelu:', def);
    if (!name) return;
    const res = await postJson('copy_model', { name });
    await reloadAfterModelChange(`Vytvořena kopie modelu: ${res.model}`);
}

async function deleteCurrentModel() {
    const name = $('#modelSelect').value;
    if (!name) return;
    if (!confirm(`Smazat model „${name}“? Soubor se přesune do /data/deleted, assety z ostatních modelů zůstanou beze změny.`)) return;
    const res = await postJson('delete_model', { name });
    await reloadAfterModelChange(`Model přesunut do koše: ${res.deleted}`);
}

function downloadCurrentModel() {
    const name = $('#modelSelect').value || '';
    window.location.href = `${api}?action=download_model&name=${encodeURIComponent(name)}`;
}

function openUploadModelDialog() {
    $('#modelUploadInput').value = '';
    $('#modelUploadInput').click();
}

async function uploadModelFile(evt) {
    const file = evt.target.files && evt.target.files[0];
    if (!file) return;
    const suggested = file.name.replace(/\.(db|sqlite)$/i, '') || 'import-model';
    const name = prompt('Název importovaného modelu:', suggested);
    if (!name) return;
    const form = new FormData();
    form.append('db_file', file);
    form.append('name', name);
    const res = await fetchJson(`${api}?action=upload_model`, { method: 'POST', body: form });
    await reloadAfterModelChange(`Model importován: ${res.model}`);
}


function nodeLabel(n) {
    const type = meta.node_types[n.type] || n.type;
    const crit = n.criticality ? `\n${n.criticality}` : '';
    return `${n.name}\n${type}${crit}`;
}

function cyElements(data) {
    const positions = data.positions || {};
    const nodes = data.nodes.map((n, idx) => ({
        data: { id: `n${n.id}`, dbid: n.id, label: nodeLabel(n), type: n.type, name: n.name, criticality: n.criticality || '', description: n.description || '' },
        position: positions[n.id] || { x: 100 + (idx % 6) * 180, y: 100 + Math.floor(idx / 6) * 130 }
    }));
    const edges = data.edges.map(e => ({
        data: { id: `e${e.id}`, dbid: e.id, source: `n${e.source_node_id}`, target: `n${e.target_node_id}`, type: e.type, label: data.edge_type_labels[e.type] || e.type, description: e.description || '' }
    }));
    return [...nodes, ...edges];
}

function initCy(elements) {
    if (cy) cy.destroy();
    cy = cytoscape({
        container: $('#cy'),
        elements,
        wheelSensitivity: 0.18,
        layout: { name: 'preset', fit: true, padding: 50 },
        style: [
            { selector: 'node', style: {
                'shape': 'round-rectangle',
                'label': 'data(label)',
                'text-wrap': 'wrap',
                'text-max-width': 146,
                'font-size': 11,
                'font-family': 'Inter, Segoe UI, Arial, sans-serif',
                'font-weight': 800,
                'text-valign': 'center',
                'text-halign': 'center',
                'width': 170,
                'height': 74,
                'background-color': '#ffffff',
                'background-opacity': 0.98,
                'border-width': 2,
                'border-color': '#94a3b8',
                'border-opacity': .95,
                'color': '#172033',
                'padding': '10px',
                'shadow-blur': 14,
                'shadow-color': '#334155',
                'shadow-opacity': .16,
                'shadow-offset-x': 0,
                'shadow-offset-y': 7
            }},
            { selector: 'node[type="hardware"]', style: { 'background-color': '#f8fafc', 'border-color': '#64748b' }},
            { selector: 'node[type="software"]', style: { 'background-color': '#eff6ff', 'border-color': '#2563eb' }},
            { selector: 'node[type="data"]', style: { 'background-color': '#ecfdf5', 'border-color': '#059669' }},
            { selector: 'node[type="process"]', style: { 'background-color': '#fff7ed', 'border-color': '#d97706' }},
            { selector: 'node[type="business_function"]', style: { 'background-color': '#fff1e8', 'border-color': '#ea580c' }},
            { selector: 'node[type="supplier"], node[type="provider"], node[type="manufacturer"]', style: { 'background-color': '#f5f3ff', 'border-color': '#7c3aed' }},
            { selector: 'node[type="network"], node[type="location"], node[type="ict_service"], node[type="documentation"]', style: { 'background-color': '#ecfeff', 'border-color': '#0891b2' }},
            { selector: 'node[criticality="low"]', style: { 'border-width': 2 }},
            { selector: 'node[criticality="medium"]', style: { 'border-width': 3 }},
            { selector: 'node[criticality="high"]', style: { 'border-width': 4, 'shadow-color': '#d97706', 'shadow-opacity': .22 }},
            { selector: 'node[criticality="critical"]', style: { 'border-width': 5, 'shadow-color': '#dc2626', 'shadow-opacity': .28 }},
            { selector: 'edge', style: {
                'curve-style': 'bezier',
                'target-arrow-shape': 'triangle',
                'arrow-scale': 1.02,
                'width': 2.2,
                'line-color': '#64748b',
                'target-arrow-color': '#64748b',
                'label': 'data(label)',
                'font-size': 9,
                'font-family': 'Inter, Segoe UI, Arial, sans-serif',
                'font-weight': 800,
                'color': '#334155',
                'text-background-color': '#ffffff',
                'text-background-opacity': .92,
                'text-background-padding': 4,
                'text-border-opacity': .45,
                'text-border-width': 1,
                'text-border-color': '#cbd5e1',
                'text-rotation': 'autorotate',
                'shadow-blur': 8,
                'shadow-color': '#64748b',
                'shadow-opacity': .14,
                'shadow-offset-x': 0,
                'shadow-offset-y': 2
            }},
            { selector: 'edge[type="contains"]', style: { 'line-style': 'dashed', 'line-color': '#0891b2', 'target-arrow-color': '#0891b2' }},
            { selector: 'edge[type="processes_data"], edge[type="uses_data"]', style: { 'line-color': '#059669', 'target-arrow-color': '#059669' }},
            { selector: 'edge[type="supports_process"], edge[type="supports_function"]', style: { 'line-color': '#d97706', 'target-arrow-color': '#d97706' }},
            { selector: 'edge[type="provided_by"], edge[type="supplied_by"], edge[type="manufactured_by"]', style: { 'line-color': '#7c3aed', 'target-arrow-color': '#7c3aed' }},
            { selector: 'edge[type="hosts"], edge[type="runs_on"]', style: { 'line-color': '#2563eb', 'target-arrow-color': '#2563eb' }},
            { selector: ':selected', style: {
                'border-color': '#0f172a',
                'line-color': '#0f172a',
                'target-arrow-color': '#0f172a',
                'shadow-blur': 24,
                'shadow-color': '#2563eb',
                'shadow-opacity': .38,
                'underlay-color': '#2563eb',
                'underlay-opacity': .10,
                'underlay-padding': 8
            }},
            { selector: '.faded', style: { 'opacity': .20 }},
            { selector: '.hiddenByFilter', style: { 'display': 'none' }}
        ]
    });

    cy.on('select', 'node, edge', evt => {
        selected = evt.target;
        updateSelectedInfo();
    });
    cy.on('unselect', 'node, edge', () => {
        if (cy.$(':selected').length === 0) {
            selected = null;
            updateSelectedInfo();
        }
    });
    cy.on('dbltap', 'node', evt => openNodeModalById(evt.target.data('dbid')));
    cy.on('dbltap', 'edge', evt => openEdgeModalById(evt.target.data('dbid')));
    cy.on('dragfree', 'node', evt => saveNodePosition(evt.target));
}

async function loadGraph() {
    currentViewId = Number($('#viewSelect').value || 1);
    const mode = $('#modeSelect').value;
    const selectedNodeId = selected?.isNode?.() ? selected.data('dbid') : '';
    const url = `${api}?action=get_graph&view_id=${currentViewId}&mode=${encodeURIComponent(mode)}&node_id=${encodeURIComponent(selectedNodeId || '')}`;
    const data = await fetchJson(url);
    graph.nodes = data.nodes;
    graph.edges = data.edges;
    initCy(cyElements(data));
    refreshEdgeNodeSelects();
    applyUiFilter();
}

function refreshEdgeNodeSelects() {
    const source = $('#edgeSource');
    const target = $('#edgeTarget');
    source.innerHTML = '';
    target.innerHTML = '';
    graph.nodes.forEach(n => {
        const label = `${n.name} (${meta.node_types[n.type] || n.type})`;
        source.append(new Option(label, n.id));
        target.append(new Option(label, n.id));
    });
}

function updateSelectedInfo() {
    const el = $('#selectedInfo');
    if (!selected) {
        el.textContent = 'Nic není vybráno.';
        return;
    }
    if (selected.isNode()) {
        el.innerHTML = `<strong>${escapeHtml(selected.data('name'))}</strong><br>typ: ${escapeHtml(selected.data('type'))}<br>kritičnost: ${escapeHtml(selected.data('criticality') || '-')}`;
    } else {
        el.innerHTML = `<strong>Vazba</strong><br>${escapeHtml(selected.data('label'))}<br>${escapeHtml(selected.data('description') || '')}`;
    }
}

function escapeHtml(s) {
    return String(s).replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
}

function gridSize() {
    const val = Number($('#gridSize')?.value || localStorage.getItem('doraGridSize') || 40);
    if (!Number.isFinite(val) || val < 10) return 40;
    return Math.min(200, Math.max(10, val));
}

function isSnapToGridEnabled() {
    return !!$('#snapToGrid')?.checked;
}

function snapValue(value, size = gridSize()) {
    return Math.round(Number(value || 0) / size) * size;
}

function snappedPosition(pos) {
    const size = gridSize();
    return { x: snapValue(pos.x, size), y: snapValue(pos.y, size) };
}

async function snapCurrentViewToGrid() {
    if (!cy) return;
    const nodes = cy.nodes().filter(n => n.data('dbid') && !n.hasClass('hiddenByFilter'));
    nodes.forEach(node => node.position(snappedPosition(node.position())));
    await saveVisiblePositions();
    toast(`Aktuální view zarovnáno na mřížku ${gridSize()} px`);
}

function initSnapControls() {
    const snap = $('#snapToGrid');
    const size = $('#gridSize');
    if (!snap || !size) return;
    snap.checked = localStorage.getItem('doraSnapToGrid') === '1';
    size.value = localStorage.getItem('doraGridSize') || size.value || '40';
    snap.addEventListener('change', () => {
        localStorage.setItem('doraSnapToGrid', snap.checked ? '1' : '0');
        toast(snap.checked ? 'Snap to grid zapnutý' : 'Snap to grid vypnutý');
    });
    size.addEventListener('change', () => {
        const normalized = gridSize();
        size.value = normalized;
        localStorage.setItem('doraGridSize', String(normalized));
        toast(`Velikost mřížky: ${normalized} px`);
    });
    $('#btnSnapNow')?.addEventListener('click', snapCurrentViewToGrid);
}

async function saveNodePosition(node) {
    try {
        if (isSnapToGridEnabled()) {
            node.position(snappedPosition(node.position()));
        }
        await postJson('save_position', { view_id: currentViewId, node_id: node.data('dbid'), x: node.position('x'), y: node.position('y') });
    } catch (e) {
        toast('Pozice se neuložila: ' + e.message);
    }
}

function openModal(id) { $('#' + id).classList.remove('hidden'); }
function closeModal(id) { $('#' + id).classList.add('hidden'); }

function clearForm(form) { form.reset(); [...form.elements].forEach(e => { if (e.name === 'id') e.value = ''; }); }

async function openNodeModalById(id) {
    clearForm($('#nodeForm'));
    currentNodeEdges = [];
    currentNodeDeletedEdges = [];
    nodeEdgesDirty = false;
    $('#nodeEdgesSection').classList.add('hidden');
    $('#nodeEdgesGrid tbody').innerHTML = '';
    if (id) {
        await loadNodeLookup();
        const data = await fetchJson(`${api}?action=get_node&id=${id}`);
        fillForm($('#nodeForm'), data.node);
        $('#nodeModalTitle').textContent = `Uzel: ${data.node.name}`;
        await loadNodeEdges(id);
    } else {
        $('#nodeModalTitle').textContent = 'Nový uzel';
        $('#nodeForm').elements.type.value = 'software';
        $('#nodeForm').elements.criticality.value = 'medium';
        $('#nodeForm').elements.status.value = 'active';
    }
    openModal('nodeModal');
}

function openEdgeModalById(id) {
    clearForm($('#edgeForm'));
    if (id) {
        const e = graph.edges.find(x => Number(x.id) === Number(id));
        if (e) fillForm($('#edgeForm'), e);
    } else if (cy && cy.$('node:selected').length >= 2) {
        const nodes = cy.$('node:selected');
        $('#edgeForm').elements.source_node_id.value = nodes[0].data('dbid');
        $('#edgeForm').elements.target_node_id.value = nodes[1].data('dbid');
        $('#edgeForm').elements.type.value = 'depends_on';
    } else if (selected && selected.isNode()) {
        $('#edgeForm').elements.source_node_id.value = selected.data('dbid');
    }
    openModal('edgeModal');
}

function fillForm(form, data) {
    Object.entries(data).forEach(([k, v]) => {
        if (form.elements[k]) form.elements[k].value = v ?? '';
    });
}

function formData(form) {
    const data = {};
    [...form.elements].forEach(el => {
        if (!el.name) return;
        data[el.name] = el.value;
    });
    return data;
}


function currentNodeIdFromForm() {
    return Number($('#nodeForm').elements.id.value || 0);
}

async function loadNodeEdges(id) {
    const data = await fetchJson(`${api}?action=get_node_edges&id=${encodeURIComponent(id)}`);
    currentNodeEdges = (data.edges || []).map(e => ({ ...e, _rowid: 'edge-' + e.id, _state: 'clean', _deleted: false }));
    currentNodeDeletedEdges = [];
    nodeEdgesDirty = false;
    $('#nodeEdgesSection').classList.remove('hidden');
    renderNodeEdgesTable();
}

function nodeDisplayName(id) {
    const n = allNodeLookup.get(String(id));
    return n ? `${n.name} (#${n.id})` : `#${id}`;
}

function makeNodeOptionSelect(value, ariaLabel) {
    const select = document.createElement('select');
    select.dataset.field = ariaLabel;
    select.append(new Option('', ''));
    [...allNodeLookup.values()]
        .sort((a, b) => String(a.name || '').localeCompare(String(b.name || ''), 'cs'))
        .forEach(n => select.append(new Option(`${n.name} (#${n.id})`, n.id)));
    select.value = value ? String(value) : '';
    return select;
}

function makeEdgeTypeSelect(value) {
    const select = document.createElement('select');
    select.className = 'node-edge-type';
    select.append(new Option('', ''));
    Object.entries(meta.edge_types || {}).forEach(([k, v]) => select.append(new Option(v, k)));
    select.value = value || '';
    return select;
}

function makeCriticalitySelect(value) {
    const select = document.createElement('select');
    select.className = 'node-edge-criticality';
    select.append(new Option('', ''));
    (meta.criticalities || ['low','medium','high','critical']).forEach(v => select.append(new Option(v, v)));
    select.value = value || '';
    return select;
}

function renderNodeEdgesTable() {
    const tbody = $('#nodeEdgesGrid tbody');
    tbody.innerHTML = '';
    const currentId = currentNodeIdFromForm();
    const currentName = nodeDisplayName(currentId);
    if (!currentId) {
        $('#nodeEdgesSection').classList.add('hidden');
        return;
    }
    const rows = currentNodeEdges.filter(r => !r._deleted);
    if (!rows.length) {
        const tr = document.createElement('tr');
        const td = document.createElement('td');
        td.colSpan = 6;
        td.className = 'node-edge-empty';
        td.textContent = 'Zatím žádné vazby. Přidej odchozí nebo příchozí vazbu.';
        tr.appendChild(td);
        tbody.appendChild(tr);
        return;
    }
    rows.forEach(row => {
        const tr = document.createElement('tr');
        tr.dataset.rowid = row._rowid;
        if (row._state === 'new') tr.classList.add('new-row');
        if (row._state === 'dirty') tr.classList.add('dirty-row');

        const sourceTd = document.createElement('td');
        const targetTd = document.createElement('td');
        const typeTd = document.createElement('td');
        const critTd = document.createElement('td');
        const descTd = document.createElement('td');
        const actionTd = document.createElement('td');

        if (Number(row.source_node_id) === currentId) {
            sourceTd.textContent = currentName;
            sourceTd.className = 'node-edge-current';
            const sel = makeNodeOptionSelect(row.target_node_id, 'target_node_id');
            sel.addEventListener('change', () => updateNodeEdgeRow(row._rowid, 'target_node_id', sel.value));
            targetTd.appendChild(sel);
        } else {
            const sel = makeNodeOptionSelect(row.source_node_id, 'source_node_id');
            sel.addEventListener('change', () => updateNodeEdgeRow(row._rowid, 'source_node_id', sel.value));
            sourceTd.appendChild(sel);
            targetTd.textContent = currentName;
            targetTd.className = 'node-edge-current';
        }

        const typeSelect = makeEdgeTypeSelect(row.type);
        typeSelect.addEventListener('change', () => updateNodeEdgeRow(row._rowid, 'type', typeSelect.value));
        typeTd.appendChild(typeSelect);

        const critSelect = makeCriticalitySelect(row.criticality || '');
        critSelect.addEventListener('change', () => updateNodeEdgeRow(row._rowid, 'criticality', critSelect.value));
        critTd.appendChild(critSelect);

        const desc = document.createElement('input');
        desc.type = 'text';
        desc.value = row.description || '';
        desc.placeholder = 'Popis vazby...';
        desc.addEventListener('change', () => updateNodeEdgeRow(row._rowid, 'description', desc.value));
        descTd.appendChild(desc);

        const del = document.createElement('button');
        del.type = 'button';
        del.className = 'danger icon-trash';
        del.title = 'Smazat vazbu';
        del.textContent = '🗑';
        del.addEventListener('click', () => deleteNodeEdgeRow(row._rowid));
        actionTd.appendChild(del);

        [sourceTd, typeTd, targetTd, critTd, descTd, actionTd].forEach(td => tr.appendChild(td));
        tbody.appendChild(tr);
    });
}

function updateNodeEdgeRow(rowid, field, value) {
    const row = currentNodeEdges.find(r => r._rowid === rowid);
    if (!row) return;
    row[field] = value;
    if (row._state !== 'new') row._state = 'dirty';
    nodeEdgesDirty = true;
    renderNodeEdgesTable();
}

function addNodeEdge(direction) {
    const currentId = currentNodeIdFromForm();
    if (!currentId) { toast('Vazby lze přidávat až po uložení nového assetu.'); return; }
    const row = {
        id: '',
        source_node_id: direction === 'incoming' ? '' : currentId,
        target_node_id: direction === 'incoming' ? currentId : '',
        type: 'depends_on',
        criticality: '',
        description: '',
        _rowid: 'new-edge-' + Date.now() + '-' + Math.random().toString(16).slice(2),
        _state: 'new',
        _deleted: false
    };
    currentNodeEdges.push(row);
    nodeEdgesDirty = true;
    renderNodeEdgesTable();
}

function deleteNodeEdgeRow(rowid) {
    const row = currentNodeEdges.find(r => r._rowid === rowid);
    if (!row) return;
    if (row.id) currentNodeDeletedEdges.push(Number(row.id));
    row._deleted = true;
    nodeEdgesDirty = true;
    renderNodeEdgesTable();
}

function validateAndCollectNodeEdges() {
    const currentId = currentNodeIdFromForm();
    const rows = [];
    const errors = [];
    const validEdgeTypes = new Set(Object.keys(meta.edge_types || {}));
    currentNodeEdges.filter(r => !r._deleted).forEach((r, idx) => {
        const source = Number(r.source_node_id || 0);
        const target = Number(r.target_node_id || 0);
        const type = String(r.type || '').trim();
        if (!source || !target || !type) errors.push(`Vazba ${idx + 1}: zdroj, cíl a typ jsou povinné.`);
        if (source && !allNodeLookup.has(String(source))) errors.push(`Vazba ${idx + 1}: zdrojový asset #${source} neexistuje.`);
        if (target && !allNodeLookup.has(String(target))) errors.push(`Vazba ${idx + 1}: cílový asset #${target} neexistuje.`);
        if (type && !validEdgeTypes.has(type)) errors.push(`Vazba ${idx + 1}: neplatný typ vazby ${type}.`);
        if (source && target && source === target) errors.push(`Vazba ${idx + 1}: zdroj a cíl nesmí být stejný asset.`);
        if (source !== currentId && target !== currentId) errors.push(`Vazba ${idx + 1}: musí obsahovat aktuálně otevřený asset.`);
        rows.push({
            id: r.id || '',
            source_node_id: source || '',
            target_node_id: target || '',
            type,
            criticality: r.criticality || '',
            description: r.description || ''
        });
    });
    return { ok: errors.length === 0, errors, rows, delete_ids: currentNodeDeletedEdges.slice() };
}

async function saveNodeEdgesIfNeeded() {
    if ($('#nodeEdgesSection').classList.contains('hidden')) return;
    if (!nodeEdgesDirty) return;
    const collected = validateAndCollectNodeEdges();
    if (!collected.ok) {
        toast(collected.errors[0]);
        throw new Error(collected.errors.join('\n'));
    }
    const changedRows = currentNodeEdges
        .filter(r => !r._deleted && (r._state === 'new' || r._state === 'dirty'))
        .map(r => ({
            id: r.id || '',
            source_node_id: Number(r.source_node_id || 0),
            target_node_id: Number(r.target_node_id || 0),
            type: r.type || '',
            criticality: r.criticality || '',
            description: r.description || ''
        }));
    if (!changedRows.length && !currentNodeDeletedEdges.length) return;
    await postJson('batch_save_edges', { rows: changedRows, delete_ids: currentNodeDeletedEdges });
    nodeEdgesDirty = false;
}

async function submitNode(evt) {
    evt.preventDefault();
    try {
        const data = formData(evt.target);
        const saved = await postJson('save_node', data);
        if (saved.node && $('#nodeForm').elements.id.value) {
            await saveNodeEdgesIfNeeded();
        }
        closeModal('nodeModal');
        selected = null;
        await loadGraph();
        if (saved.node && cy) {
            const node = cy.$(`#n${saved.node.id}`);
            if (node && node.length) {
                node.select();
                cy.animate({ center: { eles: node }, zoom: Math.max(cy.zoom(), 1) }, { duration: 250 });
            }
        }
        toast('Uzel uložen');
    } catch (e) {
        toast('Uložení selhalo: ' + e.message);
    }
}

async function submitEdge(evt) {
    evt.preventDefault();
    const data = formData(evt.target);
    const saved = await postJson('save_edge', data);
    closeModal('edgeModal');
    selected = null;
    await loadGraph();
    if (saved.edge && cy) {
        const edge = cy.$(`#e${saved.edge.id}`);
        if (edge && edge.length) edge.select();
    }
    toast('Vazba uložena');
}

async function submitView(evt) {
    evt.preventDefault();
    const data = formData(evt.target);
    let saved;
    if (data.mode === 'clone') {
        await saveVisiblePositions();
        saved = await postJson('clone_view', data);
        currentViewId = Number(saved.id);
        toast('Nový view vytvořen z aktuálního');
    } else {
        saved = await postJson('save_view', data);
        currentViewId = Number(saved.id || currentViewId);
        toast('View uloženo');
    }
    closeModal('viewModal');
    await loadViews();
    $('#viewSelect').value = currentViewId;
    await loadGraph();
}

async function deleteSelected() {
    if (!selected) return;
    if (selected.isNode()) {
        if (!confirm(`Smazat uzel ${selected.data('name')} včetně jeho vazeb?`)) return;
        await postJson('delete_node', { id: selected.data('dbid') });
        toast('Uzel smazán');
    } else {
        if (!confirm('Smazat vybranou vazbu?')) return;
        await postJson('delete_edge', { id: selected.data('dbid') });
        toast('Vazba smazána');
    }
    selected = null;
    await loadGraph();
    updateSelectedInfo();
}

function editSelected() {
    if (!selected) return;
    if (selected.isNode()) openNodeModalById(selected.data('dbid'));
    else openEdgeModalById(selected.data('dbid'));
}

function applyUiFilter() {
    if (!cy) return;
    const q = $('#searchBox').value.trim().toLowerCase();
    const type = $('#typeFilter').value;
    const crit = $('#criticalityFilter').value;
    cy.elements().removeClass('hiddenByFilter');
    cy.nodes().forEach(n => {
        const text = `${n.data('name')} ${n.data('description')} ${n.data('type')}`.toLowerCase();
        const hide = (q && !text.includes(q)) || (type && n.data('type') !== type) || (crit && n.data('criticality') !== crit);
        if (hide) n.addClass('hiddenByFilter');
    });
    cy.edges().forEach(e => {
        if (e.source().hasClass('hiddenByFilter') || e.target().hasClass('hiddenByFilter')) e.addClass('hiddenByFilter');
    });
}

async function exportJson() {
    const data = await fetchJson(`${api}?action=export_json`);
    const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = `dora-assets-export-${new Date().toISOString().slice(0,10)}.json`;
    a.click();
    URL.revokeObjectURL(a.href);
}

async function saveVisiblePositions() {
    if (!cy) return;
    const positions = cy.nodes().filter(n => n.data('dbid')).map(node => {
        const pos = isSnapToGridEnabled() ? snappedPosition(node.position()) : node.position();
        return { view_id: currentViewId, node_id: node.data('dbid'), x: pos.x, y: pos.y };
    });
    if (!positions.length) return;
    await postJson('save_positions', { view_id: currentViewId, positions });
}

function openSaveViewModal() {
    clearForm($('#viewForm'));
    const selectedOption = $('#viewSelect').selectedOptions[0];
    $('#viewModalTitle').textContent = 'Uložit aktuální view';
    $('#viewForm').elements.mode.value = 'save';
    $('#viewForm').elements.id.value = $('#viewSelect').value || '';
    $('#viewForm').elements.source_view_id.value = '';
    $('#viewForm').elements.name.value = selectedOption ? selectedOption.textContent : 'Celková mapa';
    $('#viewForm').elements.description.value = '';
    openModal('viewModal');
}

function openCloneViewModal() {
    clearForm($('#viewForm'));
    const selectedOption = $('#viewSelect').selectedOptions[0];
    const baseName = selectedOption ? selectedOption.textContent : 'Celková mapa';
    $('#viewModalTitle').textContent = 'Nový view z aktuálního';
    $('#viewForm').elements.mode.value = 'clone';
    $('#viewForm').elements.id.value = '';
    $('#viewForm').elements.source_view_id.value = $('#viewSelect').value || currentViewId || 1;
    $('#viewForm').elements.name.value = baseName + ' - kopie';
    $('#viewForm').elements.description.value = 'Kopie view: ' + baseName;
    openModal('viewModal');
}

async function deleteCurrentView() {
    const id = Number($('#viewSelect').value || 1);
    const selectedOption = $('#viewSelect').selectedOptions[0];
    const name = selectedOption ? selectedOption.textContent : 'aktuální view';
    if (id === 1) {
        toast('Výchozí view Celková mapa nelze smazat');
        return;
    }
    if (!confirm(`Smazat view „${name}“? Assety ani vazby se nesmažou, smaže se jen uložený pohled a pozice.`)) return;
    await postJson('delete_view', { id });
    currentViewId = 1;
    await loadViews();
    $('#viewSelect').value = currentViewId;
    await loadGraph();
    toast('View smazáno');
}

const nodeColumns = [
  ['_sel','', false], ['id','ID', false], ['type','Typ', true], ['name','Název', true], ['description','Popis', true],
  ['owner','Owner', true], ['business_owner','Business owner', true], ['technical_owner','Technical owner', true], ['vendor_manufacturer','Vendor/manufacturer', true],
  ['criticality','Kritičnost', true], ['confidentiality','Důvěrnost', true], ['integrity_level','Integrita', true], ['availability','Dostupnost', true],
  ['rto_hours','RTO h', true], ['rpo_hours','RPO h', true], ['mtd_hours','MTD h', true],
  ['data_sensitivity','Citlivost dat', true], ['data_categories','Kategorie dat', true],
  ['environment','Prostředí', true], ['location','Lokalita', true], ['status','Stav', true], ['lifecycle_state','Lifecycle', true],
  ['last_reviewed_at','Poslední revize', true], ['review_frequency_months','Revize měs.', true],
  ['threats','Hrozby', true], ['risk_scenarios','Rizikové scénáře', true], ['risk_likelihood','Pravděp. 1-5', true], ['risk_impact','Dopad 1-5', true],
  ['risk_controls','Kontroly', true], ['residual_risk','Reziduální riziko', true], ['good_to_know','Good-to-know', true]
];

const edgeColumns = [
  ['_sel','', false], ['id','ID', false], ['source_node_id','Zdroj ID', true], ['source_name','Zdroj název', false],
  ['target_node_id','Cíl ID', true], ['target_name','Cíl název', false], ['type','Typ vazby', true],
  ['criticality','Kritičnost', true], ['description','Popis', true]
];

let tableSort = { assetsGrid: { field: 'id', dir: 1 }, edgesGrid: { field: 'id', dir: 1 } };
let tableRows = { assetsGrid: [], edgesGrid: [] };
let tableDeleted = { assetsGrid: [], edgesGrid: [] };
let tempRowSeq = 1;

const numberFields = new Set(['rto_hours','rpo_hours','mtd_hours','review_frequency_months','risk_likelihood','risk_impact','source_node_id','target_node_id']);
const requiredNodeFields = new Set(['type','name']);
const requiredEdgeFields = new Set(['source_node_id','target_node_id','type']);
const choiceSets = {
    node: {
        type: () => Object.keys(meta.node_types || {}),
        criticality: () => ['', ...(meta.criticalities || ['low','medium','high','critical'])],
        confidentiality: () => ['', ...(meta.cia_levels || ['low','medium','high','critical'])],
        integrity_level: () => ['', ...(meta.cia_levels || ['low','medium','high','critical'])],
        availability: () => ['', ...(meta.cia_levels || ['low','medium','high','critical'])],
        data_sensitivity: () => ['', ...(meta.data_sensitivities || ['public','private','secret'])],
        environment: () => ['', ...(meta.environments || ['prod','test','dev','archive','unknown'])],
        status: () => ['', ...(meta.statuses || ['active','planned','retired','unknown'])],
        lifecycle_state: () => ['', ...(meta.lifecycle_states || ['production','test','development','archived','unknown'])]
    },
    edge: {
        type: () => Object.keys(meta.edge_types || {}),
        criticality: () => ['', ...(meta.criticalities || ['low','medium','high','critical'])]
    }
};

function showView(view) {
    $('#graphView').classList.toggle('hidden', view !== 'graph');
    $('#assetsTableView').classList.toggle('hidden', view !== 'assets');
    $('#edgesTableView').classList.toggle('hidden', view !== 'edges');
    if (view === 'graph' && cy) setTimeout(() => cy.resize(), 50);
    if (view === 'assets') loadAssetsTable();
    if (view === 'edges') loadEdgesTable();
}

async function loadAssetsTable() {
    const data = await fetchJson(`${api}?action=get_nodes_table`);
    tableRows.assetsGrid = data.nodes.map(r => ({...r, _rowid: 'db-' + r.id, _state: 'clean'}));
    tableDeleted.assetsGrid = [];
    renderEditableTable('assetsGrid', nodeColumns, tableRows.assetsGrid, 'node');
}

async function loadEdgesTable() {
    await loadNodeLookup();
    const data = await fetchJson(`${api}?action=get_edges_table`);
    tableRows.edgesGrid = data.edges.map(r => ({...r, _rowid: 'db-' + r.id, _state: 'clean'}));
    tableDeleted.edgesGrid = [];
    renderEditableTable('edgesGrid', edgeColumns, tableRows.edgesGrid, 'edge');
}

function renderEditableTable(tableId, columns, rows, kind) {
    const table = $('#' + tableId);
    const filterId = tableId === 'assetsGrid' ? '#assetsTableFilter' : '#edgesTableFilter';
    const filter = ($(filterId)?.value || '').trim().toLowerCase();
    const sort = tableSort[tableId];
    let visibleRows = [...rows];
    if (filter) visibleRows = visibleRows.filter(r => Object.entries(r).filter(([k]) => !k.startsWith('_')).map(([,v]) => v ?? '').join(' ').toLowerCase().includes(filter));
    visibleRows.sort((a,b) => compareValues(a[sort.field], b[sort.field]) * sort.dir);

    const thead = document.createElement('thead');
    const hr = document.createElement('tr');
    columns.forEach(([field, label]) => {
        const th = document.createElement('th');
        if (field === '_sel') {
            const cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.addEventListener('change', () => table.querySelectorAll('tbody input.row-select').forEach(x => x.checked = cb.checked));
            th.appendChild(cb);
        } else {
            th.textContent = label + (sort.field === field ? (sort.dir > 0 ? ' ▲' : ' ▼') : '');
            th.dataset.field = field;
            th.addEventListener('click', () => {
                if (tableSort[tableId].field === field) tableSort[tableId].dir *= -1;
                else tableSort[tableId] = { field, dir: 1 };
                renderEditableTable(tableId, columns, tableRows[tableId], kind);
            });
        }
        hr.appendChild(th);
    });
    thead.appendChild(hr);

    const tbody = document.createElement('tbody');
    visibleRows.forEach(row => {
        const tr = document.createElement('tr');
        tr.dataset.rowid = row._rowid;
        if (row._state === 'new') tr.classList.add('new-row');
        if (row._state === 'dirty') tr.classList.add('dirty-row');
        columns.forEach(([field, label, editable]) => {
            const td = document.createElement('td');
            td.dataset.field = field;
            td.dataset.kind = kind;
            td.dataset.rowid = row._rowid;
            if (field === '_sel') {
                const cb = document.createElement('input');
                cb.type = 'checkbox';
                cb.className = 'row-select';
                td.appendChild(cb);
                td.classList.add('selector-cell');
            } else {
                td.textContent = row[field] ?? '';
                if (editable) {
                    td.contentEditable = 'true';
                    td.classList.add('editable-cell');
                    td.addEventListener('focus', () => td.dataset.before = td.textContent);
                    td.addEventListener('blur', () => updateRowFromCell(td));
                    td.addEventListener('keydown', tableCellKeydown);
                    td.addEventListener('paste', tableCellPaste);
                } else {
                    td.classList.add('readonly-cell');
                }
            }
            tr.appendChild(td);
        });
        tbody.appendChild(tr);
    });

    table.innerHTML = '';
    table.appendChild(thead);
    table.appendChild(tbody);
}

function compareValues(a, b) {
    const na = Number(a), nb = Number(b);
    if (!Number.isNaN(na) && !Number.isNaN(nb)) return na - nb;
    return String(a ?? '').localeCompare(String(b ?? ''), 'cs', { numeric: true, sensitivity: 'base' });
}

function tableCellKeydown(evt) {
    if (evt.key === 'Enter' && !evt.shiftKey) {
        evt.preventDefault();
        updateRowFromCell(evt.target);
        moveToCell(evt.target, 1, 0);
    }
    if (evt.key === 'Tab') {
        evt.preventDefault();
        updateRowFromCell(evt.target);
        moveToCell(evt.target, 0, evt.shiftKey ? -1 : 1);
    }
}

function moveToCell(td, rowDelta, colDelta) {
    const tr = td.parentElement;
    const rows = Array.from(tr.parentElement.querySelectorAll('tr'));
    const r = rows.indexOf(tr);
    const c = Array.from(tr.children).indexOf(td);
    const next = rows[r + rowDelta]?.children[c + colDelta] || rows[r + rowDelta]?.children[c] || tr.children[c + colDelta];
    if (next && next.isContentEditable) next.focus();
}

function tableCellPaste(evt) {
    const text = evt.clipboardData?.getData('text/plain') || '';
    if (!text.includes('\t') && !text.includes('\n')) return;
    evt.preventDefault();
    const start = evt.target;
    const table = start.closest('table');
    const tableId = table.id;
    const kind = start.dataset.kind;
    const columns = kind === 'node' ? nodeColumns : edgeColumns;
    let rows = Array.from(table.querySelectorAll('tbody tr'));
    const startRow = rows.indexOf(start.parentElement);
    const startCol = Array.from(start.parentElement.children).indexOf(start);
    const matrix = text.replace(/\r/g, '').split('\n').filter((line, idx, arr) => !(idx === arr.length - 1 && line === '')).map(line => line.split('\t'));

    while (startRow + matrix.length > rows.length) {
        addTableRow(kind, false);
        renderEditableTable(tableId, columns, tableRows[tableId], kind);
        rows = Array.from(table.querySelectorAll('tbody tr'));
    }

    matrix.forEach((line, rOff) => {
        const tr = rows[startRow + rOff];
        if (!tr) return;
        line.forEach((value, cOff) => {
            const cell = tr.children[startCol + cOff];
            if (cell && cell.isContentEditable) {
                cell.textContent = value;
                updateRowFromCell(cell, false);
            }
        });
    });
    renderEditableTable(tableId, columns, tableRows[tableId], kind);
    toast('Data vložena do tabulky. Ulož je tlačítkem Save.');
}

function updateRowFromCell(td, repaint = true) {
    const table = td.closest('table');
    const tableId = table.id;
    const row = tableRows[tableId].find(r => r._rowid === td.dataset.rowid);
    if (!row) return;
    const field = td.dataset.field;
    row[field] = td.textContent.trim();
    if (row._state !== 'new') row._state = 'dirty';
    if (repaint) {
        td.classList.add('dirty-cell');
        td.parentElement.classList.add('dirty-row');
    }
}

function addTableRow(kind, repaint = true) {
    const tableId = kind === 'node' ? 'assetsGrid' : 'edgesGrid';
    const row = kind === 'node'
        ? { _rowid: 'new-' + tempRowSeq++, _state: 'new', id: '', type: 'software', name: '', criticality: 'medium' }
        : { _rowid: 'new-' + tempRowSeq++, _state: 'new', id: '', source_node_id: '', source_name: '', target_node_id: '', target_name: '', type: 'depends_on', criticality: '' };
    tableRows[tableId].push(row);
    if (repaint) renderEditableTable(tableId, kind === 'node' ? nodeColumns : edgeColumns, tableRows[tableId], kind);
}

function deleteSelectedTableRows(kind) {
    const tableId = kind === 'node' ? 'assetsGrid' : 'edgesGrid';
    const table = $('#' + tableId);
    const checked = Array.from(table.querySelectorAll('tbody input.row-select:checked')).map(cb => cb.closest('tr').dataset.rowid);
    if (!checked.length) { toast('Nejdřív vyber řádku checkboxem vlevo.'); return; }
    if (!confirm(`Označit ${checked.length} řádků ke smazání? Z DB se smažou až po Save.`)) return;
    for (const rowid of checked) {
        const idx = tableRows[tableId].findIndex(r => r._rowid === rowid);
        if (idx >= 0) {
            const row = tableRows[tableId][idx];
            if (row.id) tableDeleted[tableId].push(Number(row.id));
            tableRows[tableId].splice(idx, 1);
        }
    }
    renderEditableTable(tableId, kind === 'node' ? nodeColumns : edgeColumns, tableRows[tableId], kind);
    toast('Řádky jsou označené ke smazání. Potvrď tlačítkem Save.');
}

function rowPayload(row, columns) {
    const payload = {};
    columns.forEach(([field]) => {
        if (field === '_sel') return;
        payload[field] = row[field] ?? '';
    });
    return payload;
}

function validateTable(kind) {
    const tableId = kind === 'node' ? 'assetsGrid' : 'edgesGrid';
    const columns = kind === 'node' ? nodeColumns : edgeColumns;
    const rows = tableRows[tableId];
    const errors = [];
    const nodeIds = allNodeLookup.size ? allNodeLookup : new Map(graph.nodes.map(n => [String(n.id), n]));
    rows.forEach((row, index) => {
        const required = kind === 'node' ? requiredNodeFields : requiredEdgeFields;
        required.forEach(field => {
            if (String(row[field] ?? '').trim() === '') errors.push({ rowid: row._rowid, field, message: `Řádek ${index + 1}: ${field} je povinné.` });
        });
        Object.entries(choiceSets[kind]).forEach(([field, getter]) => {
            const val = String(row[field] ?? '').trim();
            const allowed = getter().map(String);
            if (val !== '' && !allowed.includes(val)) errors.push({ rowid: row._rowid, field, message: `Řádek ${index + 1}: neplatná hodnota ${field}: ${val}` });
        });
        numberFields.forEach(field => {
            const val = String(row[field] ?? '').trim();
            if (val !== '' && (Number.isNaN(Number(val)) || Number(val) < 0)) errors.push({ rowid: row._rowid, field, message: `Řádek ${index + 1}: ${field} musí být nezáporné číslo.` });
        });
        ['risk_likelihood','risk_impact'].forEach(field => {
            const val = String(row[field] ?? '').trim();
            if (val !== '' && (Number(val) < 1 || Number(val) > 5)) errors.push({ rowid: row._rowid, field, message: `Řádek ${index + 1}: ${field} musí být 1–5.` });
        });
        if (kind === 'edge') {
            const s = String(row.source_node_id ?? '').trim();
            const t = String(row.target_node_id ?? '').trim();
            if (s && t && s === t) errors.push({ rowid: row._rowid, field: 'target_node_id', message: `Řádek ${index + 1}: zdroj a cíl nesmí být stejné.` });
            if (s && !nodeIds.has(s)) errors.push({ rowid: row._rowid, field: 'source_node_id', message: `Řádek ${index + 1}: zdroj ID ${s} neexistuje.` });
            if (t && !nodeIds.has(t)) errors.push({ rowid: row._rowid, field: 'target_node_id', message: `Řádek ${index + 1}: cíl ID ${t} neexistuje.` });
        }
    });
    markTableValidation(tableId, errors);
    return errors;
}

function markTableValidation(tableId, errors) {
    const table = $('#' + tableId);
    table.querySelectorAll('.invalid-cell').forEach(td => td.classList.remove('invalid-cell'));
    table.querySelectorAll('tr').forEach(tr => tr.classList.remove('error-row'));
    errors.forEach(err => {
        const td = table.querySelector(`td[data-rowid="${CSS.escape(err.rowid)}"][data-field="${CSS.escape(err.field)}"]`);
        if (td) {
            td.classList.add('invalid-cell');
            td.title = err.message;
            td.parentElement.classList.add('error-row');
        }
    });
}

async function saveTable(kind) {
    const tableId = kind === 'node' ? 'assetsGrid' : 'edgesGrid';
    document.activeElement?.blur?.();
    if (kind === 'edge') await loadNodeLookup();
    const errors = validateTable(kind);
    if (errors.length) {
        toast(`Validace selhala: ${errors[0].message}`);
        return;
    }
    const columns = kind === 'node' ? nodeColumns : edgeColumns;
    const changed = tableRows[tableId].filter(r => r._state === 'new' || r._state === 'dirty').map(r => rowPayload(r, columns));
    const deleted = tableDeleted[tableId];
    if (!changed.length && !deleted.length) { toast('Žádné změny k uložení.'); return; }
    try {
        if (kind === 'node') await postJson('batch_save_nodes', { rows: changed, delete_ids: deleted });
        else await postJson('batch_save_edges', { rows: changed, delete_ids: deleted });
        toast('Tabulka uložena');
        await loadGraph();
        if (kind === 'node') await loadAssetsTable(); else await loadEdgesTable();
    } catch (e) {
        toast('Uložení selhalo: ' + e.message);
    }
}

async function copyWholeTable(tableId) {
    const table = $('#' + tableId);
    const lines = [];
    table.querySelectorAll('tr').forEach(tr => {
        const cells = Array.from(tr.children).filter(c => !c.querySelector?.('input.row-select'));
        lines.push(cells.map(c => c.textContent.replace(/\n/g, ' ')).join('\t'));
    });
    await navigator.clipboard.writeText(lines.join('\n'));
    toast('Tabulka zkopírována do schránky');
}

let pendingCsvImport = { rows: [], updateById: false, errors: [] };

function openAssetsCsvImportDialog() {
    const input = $('#assetsCsvInput');
    pendingCsvImport = { rows: [], updateById: !!$('#csvUpdateById')?.checked, errors: [] };
    if (input) {
        input.value = '';
        input.click();
    }
}

async function previewAssetsCsvFile(evt) {
    const file = evt.target.files && evt.target.files[0];
    if (!file) return;
    const updateById = !!$('#csvUpdateById')?.checked;
    const form = new FormData();
    form.append('csv_file', file);
    form.append('update_by_id', updateById ? '1' : '0');
    try {
        const data = await fetchJson(`${api}?action=preview_assets_csv`, { method: 'POST', body: form });
        pendingCsvImport = { rows: data.rows || [], updateById, errors: data.errors || [] };
        renderCsvImportPreview(data);
        openModal('csvImportModal');
    } catch (e) {
        toast('CSV import preview selhal: ' + e.message);
    }
}

function renderCsvImportPreview(data) {
    const summary = $('#csvImportSummary');
    const warnings = $('#csvImportWarnings');
    const confirmBtn = $('#btnConfirmAssetsCsvImport');
    const errors = data.errors || [];
    const unknown = data.unknown_headers || [];
    summary.innerHTML = `<strong>Soubor načten.</strong> Řádků: ${data.total_rows || 0}, chyb: ${errors.length}. Režim: ${data.update_by_id ? 'aktualizovat podle ID' : 'vložit jako nové assety'}.`;
    if (unknown.length) {
        warnings.classList.remove('hidden');
        warnings.innerHTML = `<strong>Ignorované sloupce:</strong> ${unknown.map(escapeHtml).join(', ')}`;
    } else {
        warnings.classList.add('hidden');
        warnings.textContent = '';
    }
    confirmBtn.disabled = errors.length > 0 || !data.rows || data.rows.length === 0;

    const table = $('#csvPreviewGrid');
    const errorByRow = new Map();
    errors.forEach(e => {
        const key = String(e.row_number || '');
        if (!errorByRow.has(key)) errorByRow.set(key, []);
        errorByRow.get(key).push(e);
    });
    const previewCols = [
        ['_status','Stav'], ['_csv_row_number','CSV ř.'], ['id','ID'], ['type','Typ'], ['name','Název'], ['criticality','Kritičnost'],
        ['owner','Owner'], ['business_owner','Business owner'], ['technical_owner','Technical owner'], ['vendor_manufacturer','Vendor/manufacturer'],
        ['rto_hours','RTO h'], ['rpo_hours','RPO h'], ['mtd_hours','MTD h'], ['_errors','Chyby']
    ];
    const thead = document.createElement('thead');
    const hr = document.createElement('tr');
    previewCols.forEach(([, label]) => { const th = document.createElement('th'); th.textContent = label; hr.appendChild(th); });
    thead.appendChild(hr);
    const tbody = document.createElement('tbody');
    (data.rows || []).slice(0, 200).forEach(row => {
        const csvRow = String(row._csv_row_number || '');
        const rowErrors = errorByRow.get(csvRow) || [];
        const tr = document.createElement('tr');
        if (rowErrors.length) tr.classList.add('error-row');
        previewCols.forEach(([field]) => {
            const td = document.createElement('td');
            if (field === '_status') td.textContent = rowErrors.length ? 'CHYBA' : 'OK';
            else if (field === '_errors') td.textContent = rowErrors.map(e => e.message).join(' | ');
            else td.textContent = row[field] ?? '';
            if (field === '_status' && rowErrors.length) td.classList.add('invalid-cell');
            tr.appendChild(td);
        });
        tbody.appendChild(tr);
    });
    table.innerHTML = '';
    table.appendChild(thead);
    table.appendChild(tbody);
    if ((data.rows || []).length > 200) {
        const tr = document.createElement('tr');
        const td = document.createElement('td');
        td.colSpan = previewCols.length;
        td.textContent = `Preview zobrazuje prvních 200 řádků z ${data.rows.length}.`;
        td.className = 'readonly-cell';
        tr.appendChild(td);
        tbody.appendChild(tr);
    }
}

async function confirmAssetsCsvImport() {
    if (!pendingCsvImport.rows.length) { toast('Není co importovat.'); return; }
    if (pendingCsvImport.errors && pendingCsvImport.errors.length) { toast('Import nelze potvrdit, CSV obsahuje chyby.'); return; }
    if (!confirm(`Importovat ${pendingCsvImport.rows.length} assetů do aktuálního modelu?`)) return;
    try {
        const res = await postJson('import_assets_csv', { rows: pendingCsvImport.rows, update_by_id: pendingCsvImport.updateById });
        closeModal('csvImportModal');
        toast(`CSV import dokončen: vytvořeno ${res.created}, aktualizováno ${res.updated}.`);
        await loadGraph();
        await loadAssetsTable();
    } catch (e) {
        toast('CSV import selhal: ' + e.message);
    }
}

function exportAssetsCsv() {
    window.location.href = `${api}?action=export_assets_csv`;
}

async function rePreviewCsvIfLoaded() {
    const input = $('#assetsCsvInput');
    if (input?.files?.[0]) await previewAssetsCsvFile({ target: input });
}

async function main() {
    initSidebarToggle();
    showPendingToast();
    await loadMeta();
    await loadModels();
    await loadViews();
    initSnapControls();
    await loadGraph();

    $('#btnShowGraph').addEventListener('click', () => showView('graph'));
    $('#btnShowAssetsTable').addEventListener('click', () => showView('assets'));
    $('#btnShowEdgesTable').addEventListener('click', () => showView('edges'));
    $('#btnReloadAssetsTable').addEventListener('click', loadAssetsTable);
    $('#btnReloadEdgesTable').addEventListener('click', loadEdgesTable);
    $('#btnAddAssetRow').addEventListener('click', () => addTableRow('node'));
    $('#btnAddEdgeRow').addEventListener('click', () => addTableRow('edge'));
    $('#btnDeleteAssetRows').addEventListener('click', () => deleteSelectedTableRows('node'));
    $('#btnDeleteEdgeRows').addEventListener('click', () => deleteSelectedTableRows('edge'));
    $('#btnSaveAssetsTable').addEventListener('click', () => saveTable('node'));
    $('#btnSaveEdgesTable').addEventListener('click', () => saveTable('edge'));
    $('#assetsTableFilter').addEventListener('input', () => renderEditableTable('assetsGrid', nodeColumns, tableRows.assetsGrid, 'node'));
    $('#edgesTableFilter').addEventListener('input', () => renderEditableTable('edgesGrid', edgeColumns, tableRows.edgesGrid, 'edge'));
    $('#btnCopyAssetsTable').addEventListener('click', () => copyWholeTable('assetsGrid'));
    $('#btnCopyEdgesTable').addEventListener('click', () => copyWholeTable('edgesGrid'));
    $('#btnImportAssetsCsv').addEventListener('click', openAssetsCsvImportDialog);
    $('#btnExportAssetsCsv').addEventListener('click', exportAssetsCsv);
    $('#assetsCsvInput').addEventListener('change', previewAssetsCsvFile);
    $('#btnConfirmAssetsCsvImport').addEventListener('click', confirmAssetsCsvImport);
    $('#csvUpdateById').addEventListener('change', rePreviewCsvIfLoaded);

    $('#btnAddNode').addEventListener('click', () => openNodeModalById(null));
    $('#btnAddEdge').addEventListener('click', () => openEdgeModalById(null));
    $('#btnReload').addEventListener('click', loadGraph);
    $('#viewSelect').addEventListener('change', loadGraph);
    $('#modeSelect').addEventListener('change', loadGraph);
    $('#btnEditSelected').addEventListener('click', editSelected);
    $('#btnDeleteSelected').addEventListener('click', deleteSelected);
    $('#btnExport').addEventListener('click', exportJson);
    $('#btnSaveView').addEventListener('click', openSaveViewModal);
    $('#btnCloneView').addEventListener('click', openCloneViewModal);
    $('#btnDeleteView').addEventListener('click', deleteCurrentView);
    $('#modelSelect').addEventListener('change', switchModel);
    $('#btnNewModel').addEventListener('click', createNewModel);
    $('#btnCopyModel').addEventListener('click', copyCurrentModel);
    $('#btnDeleteModel').addEventListener('click', deleteCurrentModel);
    $('#btnDownloadModel').addEventListener('click', downloadCurrentModel);
    $('#btnUploadModel').addEventListener('click', openUploadModelDialog);
    $('#modelUploadInput').addEventListener('change', uploadModelFile);
    $('#btnClearFilter').addEventListener('click', () => { $('#searchBox').value=''; $('#typeFilter').value=''; $('#criticalityFilter').value=''; applyUiFilter(); });
    $('#searchBox').addEventListener('input', applyUiFilter);
    $('#typeFilter').addEventListener('change', applyUiFilter);
    $('#criticalityFilter').addEventListener('change', applyUiFilter);

    $('#btnAddOutgoingNodeEdge').addEventListener('click', () => addNodeEdge('outgoing'));
    $('#btnAddIncomingNodeEdge').addEventListener('click', () => addNodeEdge('incoming'));
    $('#btnReloadNodeEdges').addEventListener('click', async () => { const id = currentNodeIdFromForm(); if (id) await loadNodeEdges(id); });

    $('#nodeForm').addEventListener('submit', submitNode);
    $('#edgeForm').addEventListener('submit', submitEdge);
    $('#viewForm').addEventListener('submit', submitView);
    $$('[data-close]').forEach(btn => btn.addEventListener('click', () => closeModal(btn.dataset.close)));
}

main().catch(e => toast(e.message));
