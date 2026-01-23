<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/store.php';
require_once __DIR__ . '/../src/site_auth.php';

$user = site_require_auth();
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    site_require_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        site_simple_create('accounting_codes', $user['id'], [
            'code' => $_POST['code'] ?? '',
            'description' => $_POST['description'] ?? '',
            'status' => $_POST['status'] ?? 'active'
        ]);
        $message = 'Accounting code added.';
    }
    if ($action === 'delete') {
        site_simple_delete('accounting_codes', $_POST['id'] ?? '');
        $message = 'Accounting code removed.';
    }
}

$codes = site_simple_list('accounting_codes', $user['id']);
$csrf = site_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Accounting Codes - Oil Patch Depot</title>
  <link rel="stylesheet" href="/assets/css/site.css" />
</head>
<body>
  <?php require __DIR__ . '/partials/site-header.php'; ?>

  <main class="page dashboard">
    <div class="dashboard-layout">
      <?php require __DIR__ . '/partials/dashboard-nav.php'; ?>

      <div class="dashboard-content">
        <section class="panel">
          <h2>Accounting codes</h2>
          <p>Manage three top-level categories. Each item may have up to two nested sublevels (children and grandchildren).</p>

          <div class="upload-card">
            <h3>Upload accounting codes</h3>
            <p class="meta">Import a JSON export or a CSV with columns: category, level1, level2, level3.</p>
            <div class="form-grid cols-2">
              <div class="span-2">
                <label for="accounting-upload">File</label>
                <input id="accounting-upload" type="file" accept=".json,.csv" />
              </div>
              <label class="checkbox-row span-2" for="upload-save">
                <input id="upload-save" type="checkbox" checked />
                Save to server after import
              </label>
              <div class="span-2 form-actions">
                <button id="import-structure" class="btn-outline" type="button">Import file</button>
                <button id="download-template" class="btn-outline" type="button">Download CSV template</button>
              </div>
            </div>
          </div>

          <div id="accounting-hierarchy">
            <div class="hier-row">
              <h3>Location</h3>
              <div class="hier-list" data-key="location"></div>
              <div class="hier-actions">
                <button class="btn" data-action="add" data-target="location">Add item</button>
              </div>
            </div>

            <div class="hier-row">
              <h3>Code 1</h3>
              <div class="hier-list" data-key="code1"></div>
              <div class="hier-actions">
                <button class="btn" data-action="add" data-target="code1">Add item</button>
              </div>
            </div>

            <div class="hier-row">
              <h3>Code 2</h3>
              <div class="hier-list" data-key="code2"></div>
              <div class="hier-actions">
                <button class="btn" data-action="add" data-target="code2">Add item</button>
              </div>
            </div>

            <div style="margin-top:1rem;">
              <button id="save-structure" class="btn">Save (local)</button>
              <button id="save-server" class="btn">Save to server</button>
              <button id="export-structure" class="btn-outline">Export JSON</button>
              <button id="clear-structure" class="btn-danger">Clear</button>
            </div>
          </div>

          <style>
            .upload-card { margin-bottom: 1rem; border: 1px dashed #e2e2e2; padding: 1rem; border-radius: 6px; background: #fcfcfc }
            .upload-card h3 { margin: 0 0 0.25rem }
            .upload-card .meta { margin: 0 0 0.75rem }
            .hier-row { margin-bottom: 1rem; border: 1px solid #e6e6e6; padding: 0.75rem; border-radius:4px }
            .hier-list { margin-top:0.5rem }
            .hier-item { padding:8px; border:1px solid #ddd; margin:6px 0; border-radius:4px; background:#fff }
            .hier-item-row { display:flex; align-items:center; gap:8px }
            .hier-item-row input[type="text"] { flex:1 }
            .hier-children { margin-left:1.25rem; margin-top:8px; padding-left:12px; border-left:2px solid #f0f0f0 }
            .hier-item.is-collapsed > .hier-children { display:none }
            .hier-toggle { width:24px; height:24px; padding:0; font-size:12px; line-height:1; display:inline-flex; align-items:center; justify-content:center }
            .hier-actions { margin-top:0.5rem }
            .btn-danger { background:#e04; color:#fff; border:none; padding:6px 10px; border-radius:4px }
          </style>

          <script>
            (function(){
              const STORAGE_KEY = 'accounting_hierarchy_v1';
              const maxDepth = 2; // root = 0, child =1, grandchild=2

              const uploadInput = document.getElementById('accounting-upload');
              const uploadSave = document.getElementById('upload-save');

              function makeInput(value=''){
                const input = document.createElement('input');
                input.type = 'text';
                input.value = value;
                input.placeholder = 'Label';
                return input;
              }

              function makeItem(nodeData, depth){
                const item = document.createElement('div');
                item.className = 'hier-item';
                const row = document.createElement('div');
                row.className = 'hier-item-row';
                const toggleBtn = document.createElement('button');
                toggleBtn.type = 'button';
                toggleBtn.className = 'btn-outline hier-toggle';
                toggleBtn.textContent = 'v';
                toggleBtn.setAttribute('aria-expanded', 'true');
                toggleBtn.setAttribute('aria-label', 'Collapse');
                toggleBtn.title = 'Collapse';
                toggleBtn.addEventListener('click', () => {
                  const collapsed = item.classList.toggle('is-collapsed');
                  toggleBtn.textContent = collapsed ? '^' : 'v';
                  toggleBtn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                  toggleBtn.setAttribute('aria-label', collapsed ? 'Expand' : 'Collapse');
                  toggleBtn.title = collapsed ? 'Expand' : 'Collapse';
                });
                row.appendChild(toggleBtn);

                const input = makeInput(nodeData.label || '');
                row.appendChild(input);

                const addBtn = document.createElement('button');
                addBtn.type = 'button';
                addBtn.className = 'btn-outline';
                addBtn.textContent = 'Add child';
                if (depth < maxDepth) {
                  addBtn.addEventListener('click', ()=>{
                    const child = makeItem({label:''}, depth+1);
                    let wrap = item.querySelector('.hier-children');
                    if (!wrap){ wrap = document.createElement('div'); wrap.className='hier-children'; item.appendChild(wrap); }
                    wrap.appendChild(child);
                    item.classList.remove('is-collapsed');
                    toggleBtn.textContent = 'v';
                    toggleBtn.setAttribute('aria-expanded', 'true');
                    toggleBtn.setAttribute('aria-label', 'Collapse');
                    toggleBtn.title = 'Collapse';
                  });
                  row.appendChild(addBtn);
                }

                const removeBtn = document.createElement('button');
                removeBtn.type='button'; removeBtn.className='btn-outline'; removeBtn.textContent='Remove';
                removeBtn.addEventListener('click', ()=>{ item.remove(); });
                row.appendChild(removeBtn);

                item.appendChild(row);

                if (nodeData.children && nodeData.children.length){
                  const wrap = document.createElement('div'); wrap.className='hier-children';
                  nodeData.children.forEach(child=> wrap.appendChild(makeItem(child, depth+1)));
                  item.appendChild(wrap);
                }

                // store label on change
                input.addEventListener('input', ()=>{ item._dirty = true });

                return item;
              }

              function getStructure(){
                const data = {};
                document.querySelectorAll('.hier-list').forEach(list=>{
                  const key = list.dataset.key;
                  data[key] = [];
                  list.querySelectorAll(':scope > .hier-item').forEach(el=>{
                    data[key].push(serializeItem(el));
                  });
                });
                return data;
              }

              function serializeItem(el){
                const label = (el.querySelector('input[type=text]')||{value:''}).value;
                const res = { label };
                const wrap = el.querySelector(':scope > .hier-children');
                if (wrap){ res.children = []; wrap.querySelectorAll(':scope > .hier-item').forEach(c=> res.children.push(serializeItem(c))); }
                return res;
              }

              function loadStructure(data){
                document.querySelectorAll('.hier-list').forEach(list=>{ list.innerHTML=''; const key=list.dataset.key; const arr=(data&&data[key])||[]; arr.forEach(item=> list.appendChild(makeItem(item,0))); });
              }

              function saveStructureToServer(structure){
                return fetch('/api/accounting_structure.php', {
                  method: 'POST',
                  headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': '<?php echo htmlspecialchars($csrf, ENT_QUOTES); ?>'
                  },
                  body: JSON.stringify(structure)
                }).then(async (resp) => {
                  const data = await resp.json().catch(() => ({}));
                  if (!resp.ok) throw new Error(data.error || 'Save failed');
                  return data;
                });
              }

              document.addEventListener('click', (e)=>{
                const btn = e.target.closest('button[data-action]');
                if (!btn) return;
                const target = btn.dataset.target;
                if (btn.dataset.action === 'add'){
                  const list = document.querySelector('.hier-list[data-key="'+target+'"]');
                  list.appendChild(makeItem({label:''},0));
                }
              });

              document.getElementById('save-structure').addEventListener('click', ()=>{
                localStorage.setItem(STORAGE_KEY, JSON.stringify(getStructure()));
                alert('Saved locally');
              });

              document.getElementById('save-server').addEventListener('click', async ()=>{
                try{
                  await saveStructureToServer(getStructure());
                  alert('Saved to server');
                }catch(err){ alert('Save error: '+err.message); }
              });

              document.getElementById('export-structure').addEventListener('click', ()=>{
                const blob = new Blob([JSON.stringify(getStructure(),null,2)],{type:'application/json'});
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a'); a.href=url; a.download='accounting-structure.json'; a.click(); URL.revokeObjectURL(url);
              });

              document.getElementById('clear-structure').addEventListener('click', ()=>{
                if (!confirm('Clear saved structure and UI?')) return; localStorage.removeItem(STORAGE_KEY); loadStructure({});
              });

              document.getElementById('download-template').addEventListener('click', ()=>{
                const template = [
                  'category,level1,level2,level3',
                  'location,Warehouse A,Zone 1,Shelf 3',
                  'code1,Division A,Team 2,',
                  'code2,Project X,Phase 1,Task 5'
                ].join('\\n');
                const blob = new Blob([template], {type: 'text/csv'});
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'accounting-codes-template.csv';
                a.click();
                URL.revokeObjectURL(url);
              });

              document.getElementById('import-structure').addEventListener('click', async ()=>{
                const file = uploadInput && uploadInput.files ? uploadInput.files[0] : null;
                if (!file) {
                  alert('Choose a JSON or CSV file to import.');
                  return;
                }
                try{
                  const text = await file.text();
                  const lowerName = file.name.toLowerCase();
                  let structure = null;
                  if (lowerName.endsWith('.json')) {
                    structure = JSON.parse(text);
                  } else if (lowerName.endsWith('.csv')) {
                    structure = buildStructureFromCsv(text);
                  } else {
                    throw new Error('Unsupported file type. Use .json or .csv.');
                  }

                  validateStructure(structure);
                  loadStructure(structure);

                  if (uploadSave && uploadSave.checked) {
                    await saveStructureToServer(structure);
                    alert('Import complete and saved to server.');
                  } else {
                    alert('Import complete. Click Save to server to persist.');
                  }
                }catch(err){
                  alert('Import error: ' + err.message);
                }
              });

              function normalizeCategory(raw){
                const value = String(raw || '').trim().toLowerCase();
                if (!value) return '';
                if (value === 'location' || value === 'loc') return 'location';
                if (value === 'code1' || value === 'code 1' || value === 'code_1') return 'code1';
                if (value === 'code2' || value === 'code 2' || value === 'code_2') return 'code2';
                return '';
              }

              function parseCsv(text){
                const rows = [];
                let row = [];
                let field = '';
                let inQuotes = false;
                for (let i = 0; i < text.length; i++) {
                  const ch = text[i];
                  if (ch === '"') {
                    if (inQuotes && text[i + 1] === '"') {
                      field += '"';
                      i++;
                    } else {
                      inQuotes = !inQuotes;
                    }
                    continue;
                  }
                  if (ch === ',' && !inQuotes) {
                    row.push(field);
                    field = '';
                    continue;
                  }
                  if ((ch === '\\n' || ch === '\\r') && !inQuotes) {
                    if (ch === '\\r' && text[i + 1] === '\\n') {
                      i++;
                    }
                    row.push(field);
                    field = '';
                    if (row.some(cell => cell.trim() !== '')) {
                      rows.push(row);
                    }
                    row = [];
                    continue;
                  }
                  field += ch;
                }
                row.push(field);
                if (row.some(cell => cell.trim() !== '')) {
                  rows.push(row);
                }
                return rows;
              }

              function ensureNode(list, label){
                const name = label.trim();
                if (!name) return null;
                const existing = list.find(item => (item.label || '').toLowerCase() === name.toLowerCase());
                if (existing) return existing;
                const node = { label: name, children: [] };
                list.push(node);
                return node;
              }

              function buildStructureFromCsv(text){
                const rows = parseCsv(text);
                if (!rows.length) throw new Error('CSV is empty.');
                const first = rows[0].map(cell => cell.trim().toLowerCase());
                const hasHeader = first[0] === 'category';
                const dataRows = hasHeader ? rows.slice(1) : rows;
                const structure = { location: [], code1: [], code2: [] };

                dataRows.forEach((row) => {
                  const category = normalizeCategory(row[0] || '');
                  const level1 = (row[1] || '').trim();
                  const level2 = (row[2] || '').trim();
                  const level3 = (row[3] || '').trim();
                  if (!category || !level1) return;
                  const root = ensureNode(structure[category], level1);
                  if (!root) return;
                  if (level2) {
                    root.children = root.children || [];
                    const child = ensureNode(root.children, level2);
                    if (child && level3) {
                      child.children = child.children || [];
                      ensureNode(child.children, level3);
                    }
                  }
                });

                return structure;
              }

              function validateStructure(structure){
                if (!structure || typeof structure !== 'object') {
                  throw new Error('Invalid structure.');
                }
                ['location','code1','code2'].forEach((key) => {
                  if (!Array.isArray(structure[key])) {
                    throw new Error('Missing or invalid key: ' + key);
                  }
                });
              }

              // init: load from server first, fallback to local
              (async function(){
                try{
                  const resp = await fetch('/api/accounting_structure.php');
                  if (resp.ok){
                    const json = await resp.json();
                    loadStructure(json);
                    return;
                  }
                }catch(e){ /* ignore */ }
                try{
                  const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || 'null');
                  if (saved) loadStructure(saved);
                }catch(e){ /* ignore */ }
              })();
            })();
          </script>
        </section>
      </div>
    </div>
  </main>

  <?php require __DIR__ . '/partials/site-footer.php'; ?>
</body>
</html>
