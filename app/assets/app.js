const api = 'api.php';
let cy;
let meta = { node_types: {}, edge_types: {} };
let graph = { nodes: [], edges: [] };
let selected = null;
let currentViewId = 1;

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
                'text-max-width': 130,
                'font-size': 11,
                'text-valign': 'center',
                'text-halign': 'center',
                'width': 150,
                'height': 64,
                'background-color': '#e5e7eb',
                'border-width': 2,
                'border-color': '#94a3b8',
                'color': '#111827',
                'padding': '8px'
            }},
            { selector: 'node[type="hardware"]', style: { 'background-color': '#e2e8f0', 'border-color': '#64748b' }},
            { selector: 'node[type="software"]', style: { 'background-color': '#dbeafe', 'border-color': '#2563eb' }},
            { selector: 'node[type="data"]', style: { 'background-color': '#dcfce7', 'border-color': '#16a34a' }},
            { selector: 'node[type="process"]', style: { 'background-color': '#fef3c7', 'border-color': '#f59e0b' }},
            { selector: 'node[type="business_function"]', style: { 'background-color': '#ffedd5', 'border-color': '#ea580c' }},
            { selector: 'node[type="supplier"], node[type="provider"], node[type="manufacturer"]', style: { 'background-color': '#f3e8ff', 'border-color': '#9333ea' }},
            { selector: 'node[criticality="critical"]', style: { 'border-width': 4 }},
            { selector: 'edge', style: {
                'curve-style': 'bezier',
                'target-arrow-shape': 'triangle',
                'arrow-scale': .9,
                'width': 2,
                'line-color': '#94a3b8',
                'target-arrow-color': '#94a3b8',
                'label': 'data(label)',
                'font-size': 9,
                'text-background-color': '#ffffff',
                'text-background-opacity': .85,
                'text-background-padding': 2,
                'text-rotation': 'autorotate'
            }},
            { selector: ':selected', style: { 'border-color': '#ef4444', 'line-color': '#ef4444', 'target-arrow-color': '#ef4444' }},
            { selector: '.faded', style: { 'opacity': .12 }},
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

async function saveNodePosition(node) {
    try {
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
    if (id) {
        const data = await fetchJson(`${api}?action=get_node&id=${id}`);
        fillForm($('#nodeForm'), data.node);
        $('#nodeModalTitle').textContent = `Uzel: ${data.node.name}`;
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

async function submitNode(evt) {
    evt.preventDefault();
    const data = formData(evt.target);
    const saved = await postJson('save_node', data);
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
    await postJson('save_view', data);
    closeModal('viewModal');
    await loadViews();
    toast('View uloženo');
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

function openSaveViewModal() {
    clearForm($('#viewForm'));
    const selectedOption = $('#viewSelect').selectedOptions[0];
    $('#viewForm').elements.id.value = $('#viewSelect').value || '';
    $('#viewForm').elements.name.value = selectedOption ? selectedOption.textContent : 'Nový view';
    openModal('viewModal');
}

async function main() {
    await loadMeta();
    await loadViews();
    await loadGraph();

    $('#btnAddNode').addEventListener('click', () => openNodeModalById(null));
    $('#btnAddEdge').addEventListener('click', () => openEdgeModalById(null));
    $('#btnReload').addEventListener('click', loadGraph);
    $('#viewSelect').addEventListener('change', loadGraph);
    $('#modeSelect').addEventListener('change', loadGraph);
    $('#btnEditSelected').addEventListener('click', editSelected);
    $('#btnDeleteSelected').addEventListener('click', deleteSelected);
    $('#btnExport').addEventListener('click', exportJson);
    $('#btnSaveView').addEventListener('click', openSaveViewModal);
    $('#btnClearFilter').addEventListener('click', () => { $('#searchBox').value=''; $('#typeFilter').value=''; $('#criticalityFilter').value=''; applyUiFilter(); });
    $('#searchBox').addEventListener('input', applyUiFilter);
    $('#typeFilter').addEventListener('change', applyUiFilter);
    $('#criticalityFilter').addEventListener('change', applyUiFilter);

    $('#nodeForm').addEventListener('submit', submitNode);
    $('#edgeForm').addEventListener('submit', submitEdge);
    $('#viewForm').addEventListener('submit', submitView);
    $$('[data-close]').forEach(btn => btn.addEventListener('click', () => closeModal(btn.dataset.close)));
}

main().catch(e => toast(e.message));
