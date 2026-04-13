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
  <title>Accounting Codes - <?php echo htmlspecialchars(opd_site_name(), ENT_QUOTES); ?></title>
  <link rel="stylesheet" href="/assets/css/site.css?v=20260326d" />
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

          <div id="acct-notification" class="notice" style="display:none;" role="alert"></div>
          <!-- Build marker: if this text is missing from View Source, the page is cached. -->
          <div style="font-size:11px;color:#888;margin-bottom:6px;">Import build: v3-deepest-zip (2026-04-13)</div>

          <div id="accounting-hierarchy">
            <div class="hier-row">
              <div class="hier-header">
                <h3>Location</h3>
                <div class="hier-header-actions">
                  <button class="btn-outline import-btn" type="button" data-import-button="location" title="CSV columns: Location, Sub Location, Sub Sub Location, Zip, Coordinates">Excel Import</button>
                  <button class="btn-outline template-btn" type="button" data-template="location">Excel Template</button>
                  <input id="import-location" class="import-input" type="file" accept=".csv" data-import-category="location" data-import-label="Location" data-import-child="Sub Location" data-import-grandchild="Sub Sub Location" />
                  <label class="require-sub-label">
                    <input type="checkbox" id="require-sub-location" data-category="location" />
                    <span>Require Sub</span>
                  </label>
                </div>
              </div>
              <div class="hier-list" data-key="location"></div>
              <div class="hier-actions">
                <button class="btn" type="button" data-action="add" data-target="location">Add item</button>
              </div>
            </div>

            <div class="hier-row">
              <div class="hier-header">
                <h3>Code 1</h3>
                <div class="hier-header-actions">
                  <button class="btn-outline import-btn" type="button" data-import-button="code1" title="CSV columns: Code 1, Sub Code 1, Sub Sub Code 1">Excel Import</button>
                  <button class="btn-outline template-btn" type="button" data-template="code1">Excel Template</button>
                  <input id="import-code1" class="import-input" type="file" accept=".csv" data-import-category="code1" data-import-label="Code 1" data-import-child="Sub Code 1" data-import-grandchild="Sub Sub Code 1" />
                  <label class="require-sub-label">
                    <input type="checkbox" id="require-sub-code1" data-category="code1" />
                    <span>Require Sub</span>
                  </label>
                </div>
              </div>
              <div class="hier-list" data-key="code1"></div>
              <div class="hier-actions">
                <button class="btn" type="button" data-action="add" data-target="code1">Add item</button>
              </div>
            </div>

            <div class="hier-row">
              <div class="hier-header">
                <h3>Code 2</h3>
                <div class="hier-header-actions">
                  <button class="btn-outline import-btn" type="button" data-import-button="code2" title="CSV columns: Code 2, Sub Code 2, Sub Sub Code 2">Excel Import</button>
                  <button class="btn-outline template-btn" type="button" data-template="code2">Excel Template</button>
                  <input id="import-code2" class="import-input" type="file" accept=".csv" data-import-category="code2" data-import-label="Code 2" data-import-child="Sub Code 2" data-import-grandchild="Sub Sub Code 2" />
                  <label class="require-sub-label">
                    <input type="checkbox" id="require-sub-code2" data-category="code2" />
                    <span>Require Sub</span>
                  </label>
                </div>
              </div>
              <div class="hier-list" data-key="code2"></div>
              <div class="hier-actions">
                <button class="btn" type="button" data-action="add" data-target="code2">Add item</button>
              </div>
            </div>

            <div style="margin-top:1rem;">
              <button id="save-structure" class="btn">Save</button>
            </div>
          </div>

          <style>
            .hier-row { margin-bottom: 1rem; border: 1px solid #e6e6e6; padding: 0.75rem; border-radius:4px }
            .template-btn { height: 28px; padding: 4px 10px; font-size: 12px; border-radius: 6px }
            .hier-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.25rem; gap: 12px; flex-wrap: wrap }
            .hier-header-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap }
            .import-btn { height: 28px; padding: 4px 10px; font-size: 12px; border-radius: 6px; background: #16a34a; color: #fff; border-color: #16a34a }
            .import-btn:hover { background: #15803d; border-color: #15803d }
            .import-input { display: none }
            .hier-header h3 { margin: 0 }
            .require-sub-label { display: flex; align-items: center; gap: 6px; font-size: 13px; color: #666; cursor: pointer }
            .require-sub-label input { margin: 0 }
            .require-sub-label span { user-select: none }
            .hier-list { margin-top:0.5rem }
            .hier-item { padding:8px; border:1px solid #ddd; margin:6px 0; border-radius:4px; background:#fff }
            .hier-item-row { display:flex; align-items:center; gap:8px; flex-wrap:nowrap }
            .hier-item-row .btn-outline { height: 32px; padding: 5px 10px; font-size: 12px; border-radius: 6px; white-space: nowrap }
            .hier-item-row input[type="text"] { flex:1; min-width:120px }
            .hier-item-row input[type="text"]::placeholder { color:#999; opacity:1 }
            .hier-item-row .location-extras { display:flex; gap:8px; align-items:center; flex-wrap:nowrap }
            .hier-item-row .location-extras input { flex:0 0 auto; width:100px }
            .hier-item-row .location-extras input.coord-input { width:150px }
            [data-category="location"] > .hier-item-row > input[type="text"] { flex:2; min-width:180px }
            .hier-children { margin-left:1.25rem; margin-top:8px; padding-left:12px; border-left:2px solid #f0f0f0 }
            .hier-item.is-collapsed > .hier-children { display:none }
            .hier-toggle { width:28px; height:28px; padding:0; font-size:12px; line-height:1; display:inline-flex; align-items:center; justify-content:center; border-radius:6px; flex-shrink:0 }
            .hier-actions { margin-top:0.5rem }
            .btn-danger { background:#e04; color:#fff; border:none; padding:5px 10px; border-radius:6px; font-size:12px }
            .hier-item-row .btn-outline:last-of-type { color:#b91c1c; border-color:#fecaca }
            .hier-item-row .btn-outline:last-of-type:hover { background:#fef2f2; border-color:#f87171 }
            @media (max-width: 720px) {
              .hier-item-row { flex-wrap: wrap }
              .hier-item-row .location-extras { flex-wrap: wrap; width: 100% }
              .hier-item-row input[type="text"] { min-width: 0; width: 100% }
              .hier-item-row .location-extras input { width: 100% }
              .hier-item-row .location-extras input.coord-input { width: 100% }
            }
          </style>

          <script nonce="<?php echo opd_csp_nonce(); ?>">
            (function(){
              const maxDepth = 2; // root = 0, child =1, grandchild=2

              function showAcctNotice(msg, isError) {
                var el = document.getElementById('acct-notification');
                if (el) {
                  el.textContent = msg;
                  el.className = 'notice' + (isError ? ' is-error' : '');
                  el.style.display = '';
                  el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                  setTimeout(function() { el.style.display = 'none'; }, 5000);
                }
              }

              const importButtons = Array.from(document.querySelectorAll('[data-import-button]'));
              const templateButtons = Array.from(document.querySelectorAll('[data-template]'));
              const importInputs = Array.from(document.querySelectorAll('input[data-import-category]'));

              function makeInput(value=''){
                const input = document.createElement('input');
                input.type = 'text';
                input.value = value;
                input.placeholder = 'Label';
                return input;
              }

              function makeItem(nodeData, depth, category){
                const item = document.createElement('div');
                item.className = 'hier-item is-collapsed';
                item.dataset.category = category;
                const row = document.createElement('div');
                row.className = 'hier-item-row';
                const toggleBtn = document.createElement('button');
                toggleBtn.type = 'button';
                toggleBtn.className = 'btn-outline hier-toggle';
                toggleBtn.textContent = '^';
                toggleBtn.setAttribute('aria-expanded', 'false');
                toggleBtn.setAttribute('aria-label', 'Expand');
                toggleBtn.title = 'Expand';
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

                // Add extra fields for location category
                if (category === 'location') {
                  const extras = document.createElement('div');
                  extras.className = 'location-extras';

                  const zipInput = document.createElement('input');
                  zipInput.type = 'text';
                  zipInput.placeholder = 'Zip (optional)';
                  zipInput.value = nodeData.zip || '';
                  zipInput.dataset.field = 'zip';
                  extras.appendChild(zipInput);

                  const coordInput = document.createElement('input');
                  coordInput.type = 'text';
                  coordInput.placeholder = 'Coordinates (optional)';
                  coordInput.value = nodeData.coordinate || '';
                  coordInput.dataset.field = 'coordinate';
                  coordInput.className = 'coord-input';
                  extras.appendChild(coordInput);

                  row.appendChild(extras);
                }

                const addBtn = document.createElement('button');
                addBtn.type = 'button';
                addBtn.className = 'btn-outline';
                addBtn.textContent = 'Add child';
                if (depth < maxDepth) {
                  addBtn.addEventListener('click', ()=>{
                    const child = makeItem({label:''}, depth+1, category);
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
                  nodeData.children.forEach(child=> wrap.appendChild(makeItem(child, depth+1, category)));
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
                // Include requireSub settings
                data.requireSub = {
                  location: document.getElementById('require-sub-location')?.checked || false,
                  code1: document.getElementById('require-sub-code1')?.checked || false,
                  code2: document.getElementById('require-sub-code2')?.checked || false
                };
                return data;
              }

              function serializeItem(el){
                const label = (el.querySelector(':scope > .hier-item-row > input[type=text]')||{value:''}).value;
                const res = { label };

                // Capture location extra fields if present
                const extras = el.querySelector(':scope > .hier-item-row > .location-extras');
                if (extras) {
                  const zipInput = extras.querySelector('input[data-field="zip"]');
                  const coordInput = extras.querySelector('input[data-field="coordinate"]');
                  if (zipInput && zipInput.value) res.zip = zipInput.value;
                  if (coordInput && coordInput.value) res.coordinate = coordInput.value;
                }

                const wrap = el.querySelector(':scope > .hier-children');
                if (wrap){ res.children = []; wrap.querySelectorAll(':scope > .hier-item').forEach(c=> res.children.push(serializeItem(c))); }
                return res;
              }

              function loadStructure(data){
                document.querySelectorAll('.hier-list').forEach(list=>{ list.innerHTML=''; const key=list.dataset.key; const arr=(data&&data[key])||[]; arr.forEach(item=> list.appendChild(makeItem(item,0,key))); });
                // Load requireSub settings
                const requireSub = data && data.requireSub ? data.requireSub : {};
                const locCheckbox = document.getElementById('require-sub-location');
                const code1Checkbox = document.getElementById('require-sub-code1');
                const code2Checkbox = document.getElementById('require-sub-code2');
                if (locCheckbox) locCheckbox.checked = !!requireSub.location;
                if (code1Checkbox) code1Checkbox.checked = !!requireSub.code1;
                if (code2Checkbox) code2Checkbox.checked = !!requireSub.code2;
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

              const addButtons = document.querySelectorAll('button[data-action="add"][data-target]');
              addButtons.forEach((btn) => {
                btn.addEventListener('click', () => {
                  const target = btn.dataset.target || '';
                  if (!target) return;
                  const list = document.querySelector(`.hier-list[data-key="${target}"]`);
                  if (!list) return;
                  list.appendChild(makeItem({ label: '' }, 0, target));
                });
              });

              importButtons.forEach((btn) => {
                btn.addEventListener('click', () => {
                  const category = btn.dataset.importButton || '';
                  if (!category) return;
                  const input = importInputs.find((el) => el.dataset.importCategory === category);
                  if (!input) return;
                  input.value = '';
                  input.click();
                });
              });

              importInputs.forEach((input) => {
                input.addEventListener('change', async () => {
                  const file = input.files && input.files[0];
                  if (!file) return;
                  const lowerName = (file.name || '').toLowerCase();
                  if (!lowerName.endsWith('.csv')) {
                    showAcctNotice('Please upload a CSV file (.csv).', true);
                    input.value = '';
                    return;
                  }
                  const category = input.dataset.importCategory || '';
                  if (!category) return;
                  try {
                    const text = await file.text();
                    const labels = [
                      input.dataset.importLabel || '',
                      input.dataset.importChild || '',
                      input.dataset.importGrandchild || ''
                    ];
                    const structure = mergeCategoryFromCsv(category, text, labels);
                    await saveStructureToServer(structure);
                    showAcctNotice('Import complete and saved.', false);
                  } catch (err) {
                    showAcctNotice('Import error: ' + err.message, true);
                  } finally {
                    input.value = '';
                  }
                });
              });

              document.getElementById('save-structure').addEventListener('click', async ()=>{
                try{
                  await saveStructureToServer(getStructure());
                  showAcctNotice('Accounting codes saved.', false);
                }catch(err){ showAcctNotice('Save error: ' + err.message, true); }
              });

              const categoryTemplates = {
                location: { file: 'location-template.csv', headers: ['Location', 'Sub Location', 'Sub Sub Location', 'Zip', 'Coordinates'], example: ['Warehouse A', 'Zone 1', 'Shelf 3', '90210', '34.0901,-118.4065'] },
                code1: { file: 'code1-template.csv', headers: ['Code 1', 'Sub Code 1', 'Sub Sub Code 1'], example: ['Division A', 'Team 2', ''] },
                code2: { file: 'code2-template.csv', headers: ['Code 2', 'Sub Code 2', 'Sub Sub Code 2'], example: ['Project X', 'Phase 1', 'Task 5'] }
              };

              templateButtons.forEach((btn) => {
                btn.addEventListener('click', () => {
                  const category = btn.dataset.template || '';
                  const tmpl = categoryTemplates[category];
                  if (!tmpl) return;
                  const csvEscape = (v) => {
                    const s = String(v == null ? '' : v);
                    return /[",\r\n]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s;
                  };
                  const csv = [tmpl.headers.map(csvEscape).join(','), tmpl.example.map(csvEscape).join(',')].join('\n');
                  const blob = new Blob([csv], { type: 'text/csv' });
                  const url = URL.createObjectURL(blob);
                  const a = document.createElement('a');
                  a.href = url;
                  a.download = tmpl.file;
                  a.click();
                  URL.revokeObjectURL(url);
                });
              });

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
                  if ((ch === '\n' || ch === '\r') && !inQuotes) {
                    if (ch === '\r' && text[i + 1] === '\n') {
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

              function normalizeHeaderToken(value){
                return String(value || '')
                  .trim()
                  .toLowerCase()
                  .replace(/[^a-z0-9]/g, '');
              }

              function isHeaderRow(row, labels){
                if (!row || row.length < 2) return false;
                const tokens = row.slice(0, 3).map(normalizeHeaderToken);
                return labels.every((label, index) => tokens[index] === normalizeHeaderToken(label));
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

              function mergeCategoryFromCsv(category, text, labels){
                // Strip UTF-8 BOM that Excel adds when saving CSVs — without
                // this the first column's header/value gets an invisible \uFEFF
                // prefix and header detection and label matching fail silently.
                if (text.charCodeAt(0) === 0xFEFF) text = text.slice(1);
                const rows = parseCsv(text);
                if (!rows.length) throw new Error('CSV is empty.');
                const dataRows = isHeaderRow(rows[0], labels) ? rows.slice(1) : rows;
                if (!dataRows.length) throw new Error('CSV has no data rows.');
                const structure = getStructure();
                if (!Array.isArray(structure[category])) {
                  structure[category] = [];
                }
                const target = structure[category];
                // Location rows support multiple column layouts:
                //   Location, Sub Location, Sub Sub Location, Zip, Coordinates
                //   Location, Sub Location,                   Zip, Coordinates
                //   Location,                                 Zip, Coordinates
                //   (with coordinates optionally split across Lat, Lng cells)
                // Detect the ZIP column by content (5-digit US zip) rather than
                // by position so users can import any of these layouts without
                // having to reshape the file to match the template exactly.
                const isZip = (s) => /^\d{5}(-\d{4})?$/.test(String(s || '').trim());
                dataRows.forEach((row) => {
                  const cells = row.map((c) => (c || '').trim());
                  const level1 = cells[0] || '';
                  if (!level1) return;

                  let zip = '';
                  let coord = '';
                  let level2 = '';
                  let level3 = '';

                  if (category === 'location') {
                    // Find the first cell from column 1 onward that looks like
                    // a zip code. Cells before it are sub-location labels;
                    // cells after it form the coordinate.
                    let zipIdx = -1;
                    for (let i = 1; i < cells.length; i++) {
                      if (isZip(cells[i])) { zipIdx = i; break; }
                    }
                    if (zipIdx === -1) {
                      // No zip found — fall back to hierarchical positions.
                      level2 = cells[1] || '';
                      level3 = cells[2] || '';
                    } else {
                      if (zipIdx >= 2) level2 = cells[1] || '';
                      if (zipIdx >= 3) level3 = cells[2] || '';
                      zip = cells[zipIdx];
                      coord = cells.slice(zipIdx + 1).filter((c) => c !== '').join(',');
                    }
                  } else {
                    level2 = cells[1] || '';
                    level3 = cells[2] || '';
                  }

                  const root = ensureNode(target, level1);
                  if (!root) return;
                  let deepest = root;
                  if (level2) {
                    root.children = root.children || [];
                    const child = ensureNode(root.children, level2);
                    if (child) {
                      deepest = child;
                      if (level3) {
                        child.children = child.children || [];
                        const grandchild = ensureNode(child.children, level3);
                        if (grandchild) deepest = grandchild;
                      }
                    }
                  }
                  if (category === 'location') {
                    if (zip) deepest.zip = zip;
                    if (coord) deepest.coordinate = coord;
                  }
                });
                loadStructure(structure);
                return structure;
              }

              // init: load from server
              (async function(){
                try{
                  const resp = await fetch('/api/accounting_structure.php');
                  if (resp.ok){
                    const json = await resp.json();
                    loadStructure(json);
                    return;
                  }
                }catch(e){ /* ignore */ }
                loadStructure({ location: [], code1: [], code2: [] });
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
