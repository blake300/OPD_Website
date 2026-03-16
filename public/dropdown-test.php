<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Dropdown Test</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; padding: 32px; color: #333; }
    h1 { margin-bottom: 8px; font-size: 22px; }
    .subtitle { color: #888; margin-bottom: 32px; font-size: 14px; }
    .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; max-width: 1200px; }
    .card { background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 20px; }
    .card h2 { font-size: 15px; margin-bottom: 4px; }
    .card .desc { font-size: 12px; color: #999; margin-bottom: 12px; }
    .card label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; }
    .result { margin-top: 10px; font-size: 12px; color: #666; min-height: 18px; }

    /* ── Shared ── */
    select, input[type="text"] { width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; background: #fff; }
    select:focus, input:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 2px rgba(37,99,235,.15); }

    /* ── Option 1: Native select with indentation ── */

    /* ── Option 2: Grouped optgroup ── */

    /* ── Option 3: Custom searchable dropdown ── */
    .searchable-wrap { position: relative; }
    .searchable-input { cursor: pointer; }
    .searchable-list { display: none; position: absolute; top: 100%; left: 0; right: 0; max-height: 260px; overflow-y: auto; background: #fff; border: 1px solid #ccc; border-top: none; border-radius: 0 0 4px 4px; z-index: 10; box-shadow: 0 4px 12px rgba(0,0,0,.1); }
    .searchable-list.open { display: block; }
    .searchable-item { padding: 6px 10px; cursor: pointer; font-size: 13px; }
    .searchable-item:hover, .searchable-item.active { background: #e8f0fe; }
    .searchable-item[data-depth="1"] { padding-left: 24px; color: #555; }
    .searchable-item[data-depth="2"] { padding-left: 42px; color: #777; font-size: 12px; }
    .searchable-parent { font-weight: 600; }

    /* ── Option 4: Tree with expand/collapse ── */
    .tree-wrap { position: relative; }
    .tree-input { cursor: pointer; }
    .tree-panel { display: none; position: absolute; top: 100%; left: 0; right: 0; max-height: 300px; overflow-y: auto; background: #fff; border: 1px solid #ccc; border-top: none; border-radius: 0 0 4px 4px; z-index: 10; box-shadow: 0 4px 12px rgba(0,0,0,.1); }
    .tree-panel.open { display: block; }
    .tree-node { font-size: 13px; }
    .tree-label { display: flex; align-items: center; padding: 5px 10px; cursor: pointer; }
    .tree-label:hover { background: #e8f0fe; }
    .tree-toggle { width: 18px; flex-shrink: 0; font-size: 10px; color: #999; text-align: center; }
    .tree-text { flex: 1; }
    .tree-children { display: none; }
    .tree-children.open { display: block; }
    .tree-node[data-depth="1"] .tree-label { padding-left: 24px; }
    .tree-node[data-depth="2"] .tree-label { padding-left: 42px; }
    .tree-selected .tree-text { color: #2563eb; font-weight: 600; }

    /* ── Option 5: Breadcrumb drill-down ── */
    .drill-wrap { position: relative; }
    .drill-input { cursor: pointer; }
    .drill-panel { display: none; position: absolute; top: 100%; left: 0; right: 0; max-height: 300px; overflow-y: auto; background: #fff; border: 1px solid #ccc; border-top: none; border-radius: 0 0 4px 4px; z-index: 10; box-shadow: 0 4px 12px rgba(0,0,0,.1); }
    .drill-panel.open { display: block; }
    .drill-breadcrumb { padding: 6px 10px; font-size: 12px; color: #2563eb; border-bottom: 1px solid #eee; }
    .drill-breadcrumb span { cursor: pointer; }
    .drill-breadcrumb span:hover { text-decoration: underline; }
    .drill-item { padding: 6px 10px; cursor: pointer; font-size: 13px; display: flex; justify-content: space-between; }
    .drill-item:hover { background: #e8f0fe; }
    .drill-arrow { color: #999; font-size: 11px; }

    /* ── Option 6: Inline path display ── */

    /* ── Option 7: Multi-column cascading ── */
    .cascade-wrap { position: relative; }
    .cascade-input { cursor: pointer; }
    .cascade-panel { display: none; position: absolute; top: 100%; left: 0; right: 0; min-width: 500px; background: #fff; border: 1px solid #ccc; border-top: none; border-radius: 0 0 4px 4px; z-index: 10; box-shadow: 0 4px 12px rgba(0,0,0,.1); }
    .cascade-panel.open { display: flex; }
    .cascade-col { flex: 1; max-height: 260px; overflow-y: auto; border-right: 1px solid #eee; }
    .cascade-col:last-child { border-right: none; }
    .cascade-col-item { padding: 6px 10px; cursor: pointer; font-size: 13px; display: flex; justify-content: space-between; }
    .cascade-col-item:hover, .cascade-col-item.active { background: #e8f0fe; }
    .cascade-col-item .arr { color: #999; font-size: 11px; }

    /* ── Option 8: Tag/chip style ── */
    .chip-wrap { position: relative; }
    .chip-display { min-height: 38px; padding: 4px 8px; border: 1px solid #ccc; border-radius: 4px; cursor: pointer; display: flex; align-items: center; flex-wrap: wrap; gap: 4px; background: #fff; }
    .chip-display:focus-within { border-color: #2563eb; box-shadow: 0 0 0 2px rgba(37,99,235,.15); }
    .chip { background: #e8f0fe; color: #2563eb; padding: 2px 8px; border-radius: 12px; font-size: 12px; display: flex; align-items: center; gap: 4px; }
    .chip-x { cursor: pointer; font-size: 14px; line-height: 1; }
    .chip-placeholder { color: #999; font-size: 13px; }
    .chip-panel { display: none; position: absolute; top: 100%; left: 0; right: 0; max-height: 260px; overflow-y: auto; background: #fff; border: 1px solid #ccc; border-top: none; border-radius: 0 0 4px 4px; z-index: 10; box-shadow: 0 4px 12px rgba(0,0,0,.1); }
    .chip-panel.open { display: block; }
    .chip-item { padding: 6px 10px; cursor: pointer; font-size: 13px; }
    .chip-item:hover { background: #e8f0fe; }
    .chip-item[data-depth="1"] { padding-left: 24px; color: #555; }
    .chip-item[data-depth="2"] { padding-left: 42px; color: #777; font-size: 12px; }
    .chip-item.parent-label { font-weight: 600; }

    /* ── Option 9: Radio tree ── */
    .radio-tree { max-height: 240px; overflow-y: auto; border: 1px solid #ccc; border-radius: 4px; padding: 8px; background: #fff; }
    .radio-tree label { display: block; padding: 3px 0; cursor: pointer; font-size: 13px; }
    .radio-tree label:hover { color: #2563eb; }
    .radio-tree .depth-1 { padding-left: 20px; }
    .radio-tree .depth-2 { padding-left: 40px; font-size: 12px; color: #666; }
    .radio-tree .parent-label { font-weight: 600; }

    /* ── Option 10: Accordion sections ── */
    .accordion { border: 1px solid #ccc; border-radius: 4px; overflow: hidden; background: #fff; }
    .acc-section { border-bottom: 1px solid #eee; }
    .acc-section:last-child { border-bottom: none; }
    .acc-header { padding: 8px 12px; cursor: pointer; font-size: 13px; font-weight: 600; display: flex; justify-content: space-between; align-items: center; background: #fafafa; }
    .acc-header:hover { background: #f0f0f0; }
    .acc-arrow { font-size: 10px; transition: transform .2s; }
    .acc-arrow.open { transform: rotate(90deg); }
    .acc-body { display: none; }
    .acc-body.open { display: block; }
    .acc-child { padding: 5px 12px 5px 28px; cursor: pointer; font-size: 13px; }
    .acc-child:hover { background: #e8f0fe; }
    .acc-grandchild { padding: 4px 12px 4px 46px; cursor: pointer; font-size: 12px; color: #666; }
    .acc-grandchild:hover { background: #e8f0fe; color: #333; }
    .acc-selected { color: #2563eb; font-weight: 600; }
  </style>
</head>
<body>
  <h1>Dropdown Style Comparison</h1>
  <p class="subtitle">10 indented hierarchical dropdown styles — click each to test. Selection shown below.</p>

  <div class="grid">

    <!-- 1. Native Select with Unicode Indentation -->
    <div class="card">
      <h2>1. Native Select — Indented</h2>
      <p class="desc">Standard &lt;select&gt; with Unicode indent characters. Maximum compatibility.</p>
      <label>Accounting Code</label>
      <select id="dd1" onchange="showResult('r1', this.value)">
        <option value="">Select...</option>
        <option value="Operations">Operations</option>
        <option value="Operations > Field Labor">&nbsp;&nbsp;&nbsp;&nbsp;Field Labor</option>
        <option value="Operations > Field Labor > Overtime">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Overtime</option>
        <option value="Operations > Field Labor > Regular">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Regular</option>
        <option value="Operations > Equipment">&nbsp;&nbsp;&nbsp;&nbsp;Equipment</option>
        <option value="Operations > Equipment > Rental">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Rental</option>
        <option value="Operations > Equipment > Purchase">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Purchase</option>
        <option value="Operations > Chemicals">&nbsp;&nbsp;&nbsp;&nbsp;Chemicals</option>
        <option value="Operations > Chemicals > Treatment">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Treatment</option>
        <option value="Operations > Chemicals > Inhibitor">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Inhibitor</option>
        <option value="Maintenance">Maintenance</option>
        <option value="Maintenance > Wellhead">&nbsp;&nbsp;&nbsp;&nbsp;Wellhead</option>
        <option value="Maintenance > Wellhead > Repair">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Repair</option>
        <option value="Maintenance > Wellhead > Replace">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Replace</option>
        <option value="Maintenance > Downhole">&nbsp;&nbsp;&nbsp;&nbsp;Downhole</option>
        <option value="Maintenance > Downhole > Pump">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Pump</option>
        <option value="Maintenance > Downhole > Rods">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Rods</option>
        <option value="Maintenance > Surface">&nbsp;&nbsp;&nbsp;&nbsp;Surface</option>
        <option value="Maintenance > Surface > Flowline">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Flowline</option>
        <option value="Maintenance > Surface > Tank">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Tank</option>
        <option value="Capital">Capital</option>
        <option value="Capital > New Well">&nbsp;&nbsp;&nbsp;&nbsp;New Well</option>
        <option value="Capital > New Well > Drilling">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Drilling</option>
        <option value="Capital > New Well > Completion">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Completion</option>
        <option value="Capital > Infrastructure">&nbsp;&nbsp;&nbsp;&nbsp;Infrastructure</option>
        <option value="Capital > Infrastructure > Pipeline">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Pipeline</option>
        <option value="Capital > Infrastructure > Facility">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Facility</option>
      </select>
      <div class="result" id="r1"></div>
    </div>

    <!-- 2. Grouped Optgroup -->
    <div class="card">
      <h2>2. Native Select — Optgroup</h2>
      <p class="desc">Uses &lt;optgroup&gt; for parents. Browser-native grouping, no JS needed.</p>
      <label>Accounting Code</label>
      <select id="dd2" onchange="showResult('r2', this.value)">
        <option value="">Select...</option>
        <optgroup label="Operations">
          <option value="Operations > Field Labor">Field Labor</option>
          <option value="Operations > Field Labor > Overtime">&nbsp;&nbsp;↳ Overtime</option>
          <option value="Operations > Field Labor > Regular">&nbsp;&nbsp;↳ Regular</option>
          <option value="Operations > Equipment">Equipment</option>
          <option value="Operations > Equipment > Rental">&nbsp;&nbsp;↳ Rental</option>
          <option value="Operations > Equipment > Purchase">&nbsp;&nbsp;↳ Purchase</option>
          <option value="Operations > Chemicals">Chemicals</option>
          <option value="Operations > Chemicals > Treatment">&nbsp;&nbsp;↳ Treatment</option>
          <option value="Operations > Chemicals > Inhibitor">&nbsp;&nbsp;↳ Inhibitor</option>
        </optgroup>
        <optgroup label="Maintenance">
          <option value="Maintenance > Wellhead">Wellhead</option>
          <option value="Maintenance > Wellhead > Repair">&nbsp;&nbsp;↳ Repair</option>
          <option value="Maintenance > Wellhead > Replace">&nbsp;&nbsp;↳ Replace</option>
          <option value="Maintenance > Downhole">Downhole</option>
          <option value="Maintenance > Downhole > Pump">&nbsp;&nbsp;↳ Pump</option>
          <option value="Maintenance > Downhole > Rods">&nbsp;&nbsp;↳ Rods</option>
          <option value="Maintenance > Surface">Surface</option>
          <option value="Maintenance > Surface > Flowline">&nbsp;&nbsp;↳ Flowline</option>
          <option value="Maintenance > Surface > Tank">&nbsp;&nbsp;↳ Tank</option>
        </optgroup>
        <optgroup label="Capital">
          <option value="Capital > New Well">New Well</option>
          <option value="Capital > New Well > Drilling">&nbsp;&nbsp;↳ Drilling</option>
          <option value="Capital > New Well > Completion">&nbsp;&nbsp;↳ Completion</option>
          <option value="Capital > Infrastructure">Infrastructure</option>
          <option value="Capital > Infrastructure > Pipeline">&nbsp;&nbsp;↳ Pipeline</option>
          <option value="Capital > Infrastructure > Facility">&nbsp;&nbsp;↳ Facility</option>
        </optgroup>
      </select>
      <div class="result" id="r2"></div>
    </div>

    <!-- 3. Searchable Dropdown -->
    <div class="card">
      <h2>3. Searchable Dropdown</h2>
      <p class="desc">Type to filter. Indented hierarchy with keyboard support.</p>
      <label>Accounting Code</label>
      <div class="searchable-wrap" id="dd3-wrap">
        <input type="text" class="searchable-input" id="dd3" placeholder="Search or select..." autocomplete="off" />
        <div class="searchable-list" id="dd3-list"></div>
      </div>
      <div class="result" id="r3"></div>
    </div>

    <!-- 4. Expandable Tree -->
    <div class="card">
      <h2>4. Expandable Tree</h2>
      <p class="desc">Click arrows to expand/collapse branches. Select any level.</p>
      <label>Accounting Code</label>
      <div class="tree-wrap" id="dd4-wrap">
        <input type="text" class="tree-input" id="dd4" placeholder="Select..." readonly />
        <div class="tree-panel" id="dd4-panel"></div>
      </div>
      <div class="result" id="r4"></div>
    </div>

    <!-- 5. Breadcrumb Drill-Down -->
    <div class="card">
      <h2>5. Breadcrumb Drill-Down</h2>
      <p class="desc">Navigate level by level. Breadcrumb trail to go back up.</p>
      <label>Accounting Code</label>
      <div class="drill-wrap" id="dd5-wrap">
        <input type="text" class="drill-input" id="dd5" placeholder="Select..." readonly />
        <div class="drill-panel" id="dd5-panel"></div>
      </div>
      <div class="result" id="r5"></div>
    </div>

    <!-- 6. Path Display Select -->
    <div class="card">
      <h2>6. Full Path in Option</h2>
      <p class="desc">Each option shows the complete path. Simple, no nesting ambiguity.</p>
      <label>Accounting Code</label>
      <select id="dd6" onchange="showResult('r6', this.value)">
        <option value="">Select...</option>
        <option value="Operations">Operations</option>
        <option value="Operations > Field Labor">Operations › Field Labor</option>
        <option value="Operations > Field Labor > Overtime">Operations › Field Labor › Overtime</option>
        <option value="Operations > Field Labor > Regular">Operations › Field Labor › Regular</option>
        <option value="Operations > Equipment">Operations › Equipment</option>
        <option value="Operations > Equipment > Rental">Operations › Equipment › Rental</option>
        <option value="Operations > Equipment > Purchase">Operations › Equipment › Purchase</option>
        <option value="Operations > Chemicals">Operations › Chemicals</option>
        <option value="Operations > Chemicals > Treatment">Operations › Chemicals › Treatment</option>
        <option value="Operations > Chemicals > Inhibitor">Operations › Chemicals › Inhibitor</option>
        <option value="Maintenance">Maintenance</option>
        <option value="Maintenance > Wellhead">Maintenance › Wellhead</option>
        <option value="Maintenance > Wellhead > Repair">Maintenance › Wellhead › Repair</option>
        <option value="Maintenance > Wellhead > Replace">Maintenance › Wellhead › Replace</option>
        <option value="Maintenance > Downhole">Maintenance › Downhole</option>
        <option value="Maintenance > Downhole > Pump">Maintenance › Downhole › Pump</option>
        <option value="Maintenance > Downhole > Rods">Maintenance › Downhole › Rods</option>
        <option value="Maintenance > Surface">Maintenance › Surface</option>
        <option value="Maintenance > Surface > Flowline">Maintenance › Surface › Flowline</option>
        <option value="Maintenance > Surface > Tank">Maintenance › Surface › Tank</option>
        <option value="Capital">Capital</option>
        <option value="Capital > New Well">Capital › New Well</option>
        <option value="Capital > New Well > Drilling">Capital › New Well › Drilling</option>
        <option value="Capital > New Well > Completion">Capital › New Well › Completion</option>
        <option value="Capital > Infrastructure">Capital › Infrastructure</option>
        <option value="Capital > Infrastructure > Pipeline">Capital › Infrastructure › Pipeline</option>
        <option value="Capital > Infrastructure > Facility">Capital › Infrastructure › Facility</option>
      </select>
      <div class="result" id="r6"></div>
    </div>

    <!-- 7. Multi-Column Cascade -->
    <div class="card">
      <h2>7. Multi-Column Cascade</h2>
      <p class="desc">macOS Finder-style columns. Each selection reveals the next level.</p>
      <label>Accounting Code</label>
      <div class="cascade-wrap" id="dd7-wrap">
        <input type="text" class="cascade-input" id="dd7" placeholder="Select..." readonly />
        <div class="cascade-panel" id="dd7-panel"></div>
      </div>
      <div class="result" id="r7"></div>
    </div>

    <!-- 8. Chip/Tag Multi-Select -->
    <div class="card">
      <h2>8. Chip/Tag Selector</h2>
      <p class="desc">Multi-select with removable chips. Good for assigning multiple codes.</p>
      <label>Accounting Codes</label>
      <div class="chip-wrap" id="dd8-wrap">
        <div class="chip-display" id="dd8-display"><span class="chip-placeholder">Click to select...</span></div>
        <div class="chip-panel" id="dd8-panel"></div>
      </div>
      <div class="result" id="r8"></div>
    </div>

    <!-- 9. Radio Tree (Always Visible) -->
    <div class="card">
      <h2>9. Inline Radio Tree</h2>
      <p class="desc">Always-visible tree with radio buttons. No dropdown interaction needed.</p>
      <label>Accounting Code</label>
      <div class="radio-tree" id="dd9"></div>
      <div class="result" id="r9"></div>
    </div>

    <!-- 10. Accordion Sections -->
    <div class="card">
      <h2>10. Accordion Panels</h2>
      <p class="desc">Expand/collapse sections. Clean separation between top-level groups.</p>
      <label>Accounting Code</label>
      <div class="accordion" id="dd10"></div>
      <div class="result" id="r10"></div>
    </div>

  </div>

<script>
// ── Shared data ──
const hierarchy = [
  { label: 'Operations', children: [
    { label: 'Field Labor', children: [
      { label: 'Overtime' }, { label: 'Regular' }
    ]},
    { label: 'Equipment', children: [
      { label: 'Rental' }, { label: 'Purchase' }
    ]},
    { label: 'Chemicals', children: [
      { label: 'Treatment' }, { label: 'Inhibitor' }
    ]}
  ]},
  { label: 'Maintenance', children: [
    { label: 'Wellhead', children: [
      { label: 'Repair' }, { label: 'Replace' }
    ]},
    { label: 'Downhole', children: [
      { label: 'Pump' }, { label: 'Rods' }
    ]},
    { label: 'Surface', children: [
      { label: 'Flowline' }, { label: 'Tank' }
    ]}
  ]},
  { label: 'Capital', children: [
    { label: 'New Well', children: [
      { label: 'Drilling' }, { label: 'Completion' }
    ]},
    { label: 'Infrastructure', children: [
      { label: 'Pipeline' }, { label: 'Facility' }
    ]}
  ]}
]

function flattenHierarchy(nodes, depth, pathPrefix) {
  const result = []
  nodes.forEach(node => {
    const path = pathPrefix ? pathPrefix + ' > ' + node.label : node.label
    result.push({ label: node.label, depth, path, hasChildren: !!(node.children && node.children.length) })
    if (node.children) {
      result.push(...flattenHierarchy(node.children, depth + 1, path))
    }
  })
  return result
}

const flat = flattenHierarchy(hierarchy, 0, '')

function showResult(id, val) {
  document.getElementById(id).textContent = val ? 'Selected: ' + val : ''
}

// ── 3. Searchable Dropdown ──
;(function () {
  const input = document.getElementById('dd3')
  const list = document.getElementById('dd3-list')
  const wrap = document.getElementById('dd3-wrap')

  function render(filter) {
    list.innerHTML = ''
    const f = (filter || '').toLowerCase()
    flat.forEach(item => {
      if (f && !item.label.toLowerCase().includes(f) && !item.path.toLowerCase().includes(f)) return
      const div = document.createElement('div')
      div.className = 'searchable-item' + (item.depth === 0 ? ' searchable-parent' : '')
      div.dataset.depth = item.depth
      div.textContent = item.label
      div.addEventListener('click', () => {
        input.value = item.path
        list.classList.remove('open')
        showResult('r3', item.path)
      })
      list.appendChild(div)
    })
  }

  input.addEventListener('focus', () => { render(input.value); list.classList.add('open') })
  input.addEventListener('input', () => { render(input.value); list.classList.add('open') })
  document.addEventListener('click', e => { if (!wrap.contains(e.target)) list.classList.remove('open') })
  render('')
})()

// ── 4. Expandable Tree ──
;(function () {
  const input = document.getElementById('dd4')
  const panel = document.getElementById('dd4-panel')
  const wrap = document.getElementById('dd4-wrap')

  function buildTree(nodes, depth, pathPrefix) {
    const container = document.createElement('div')
    nodes.forEach(node => {
      const path = pathPrefix ? pathPrefix + ' > ' + node.label : node.label
      const el = document.createElement('div')
      el.className = 'tree-node'
      el.dataset.depth = depth
      const label = document.createElement('div')
      label.className = 'tree-label'
      const toggle = document.createElement('span')
      toggle.className = 'tree-toggle'
      if (node.children && node.children.length) toggle.textContent = '▶'
      const text = document.createElement('span')
      text.className = 'tree-text'
      text.textContent = node.label
      label.appendChild(toggle)
      label.appendChild(text)
      el.appendChild(label)

      let childContainer = null
      if (node.children && node.children.length) {
        childContainer = buildTree(node.children, depth + 1, path)
        childContainer.className = 'tree-children'
        el.appendChild(childContainer)
        toggle.addEventListener('click', e => {
          e.stopPropagation()
          childContainer.classList.toggle('open')
          toggle.textContent = childContainer.classList.contains('open') ? '▼' : '▶'
        })
      }

      text.addEventListener('click', () => {
        panel.querySelectorAll('.tree-selected').forEach(s => s.classList.remove('tree-selected'))
        label.classList.add('tree-selected')
        input.value = path
        showResult('r4', path)
        panel.classList.remove('open')
      })

      container.appendChild(el)
    })
    return container
  }

  panel.appendChild(buildTree(hierarchy, 0, ''))
  input.addEventListener('click', () => panel.classList.toggle('open'))
  document.addEventListener('click', e => { if (!wrap.contains(e.target)) panel.classList.remove('open') })
})()

// ── 5. Breadcrumb Drill-Down ──
;(function () {
  const input = document.getElementById('dd5')
  const panel = document.getElementById('dd5-panel')
  const wrap = document.getElementById('dd5-wrap')
  let currentPath = []

  function renderLevel(nodes, path) {
    currentPath = path
    panel.innerHTML = ''
    if (path.length > 0) {
      const bc = document.createElement('div')
      bc.className = 'drill-breadcrumb'
      const root = document.createElement('span')
      root.textContent = '⌂ Root'
      root.addEventListener('click', () => renderLevel(hierarchy, []))
      bc.appendChild(root)
      let ref = hierarchy
      path.forEach((seg, i) => {
        bc.appendChild(document.createTextNode(' › '))
        const s = document.createElement('span')
        s.textContent = seg
        const subPath = path.slice(0, i + 1)
        s.addEventListener('click', () => {
          let r = hierarchy
          subPath.forEach(p => { r = r.find(n => n.label === p).children || [] })
          renderLevel(r, subPath)
        })
        bc.appendChild(s)
        ref = ref.find(n => n.label === seg)
        if (ref) ref = ref.children || []
      })
      panel.appendChild(bc)
    }
    nodes.forEach(node => {
      const item = document.createElement('div')
      item.className = 'drill-item'
      const text = document.createElement('span')
      text.textContent = node.label
      item.appendChild(text)
      if (node.children && node.children.length) {
        const arrow = document.createElement('span')
        arrow.className = 'drill-arrow'
        arrow.textContent = '▶'
        item.appendChild(arrow)
      }
      item.addEventListener('click', () => {
        const newPath = [...path, node.label]
        const fullPath = newPath.join(' > ')
        if (node.children && node.children.length) {
          renderLevel(node.children, newPath)
        } else {
          input.value = fullPath
          showResult('r5', fullPath)
          panel.classList.remove('open')
        }
      })
      panel.appendChild(item)
    })
  }

  input.addEventListener('click', () => { renderLevel(hierarchy, []); panel.classList.toggle('open') })
  document.addEventListener('click', e => { if (!wrap.contains(e.target)) panel.classList.remove('open') })
})()

// ── 7. Multi-Column Cascade ──
;(function () {
  const input = document.getElementById('dd7')
  const panel = document.getElementById('dd7-panel')
  const wrap = document.getElementById('dd7-wrap')

  function renderCol(nodes, colIndex, pathSoFar) {
    // Remove columns to the right
    while (panel.children.length > colIndex) panel.removeChild(panel.lastChild)
    const col = document.createElement('div')
    col.className = 'cascade-col'
    nodes.forEach(node => {
      const item = document.createElement('div')
      item.className = 'cascade-col-item'
      const text = document.createElement('span')
      text.textContent = node.label
      item.appendChild(text)
      if (node.children && node.children.length) {
        const arr = document.createElement('span')
        arr.className = 'arr'
        arr.textContent = '▶'
        item.appendChild(arr)
      }
      item.addEventListener('click', () => {
        col.querySelectorAll('.active').forEach(a => a.classList.remove('active'))
        item.classList.add('active')
        const newPath = pathSoFar ? pathSoFar + ' > ' + node.label : node.label
        input.value = newPath
        showResult('r7', newPath)
        if (node.children && node.children.length) {
          renderCol(node.children, colIndex + 1, newPath)
        } else {
          while (panel.children.length > colIndex + 1) panel.removeChild(panel.lastChild)
        }
      })
      col.appendChild(item)
    })
    panel.appendChild(col)
  }

  input.addEventListener('click', () => {
    if (panel.classList.contains('open')) { panel.classList.remove('open'); return }
    panel.innerHTML = ''
    renderCol(hierarchy, 0, '')
    panel.classList.add('open')
  })
  document.addEventListener('click', e => { if (!wrap.contains(e.target)) panel.classList.remove('open') })
})()

// ── 8. Chip/Tag Multi-Select ──
;(function () {
  const display = document.getElementById('dd8-display')
  const panel = document.getElementById('dd8-panel')
  const wrap = document.getElementById('dd8-wrap')
  const selected = new Set()

  function renderPanel() {
    panel.innerHTML = ''
    flat.forEach(item => {
      const div = document.createElement('div')
      div.className = 'chip-item' + (item.depth === 0 ? ' parent-label' : '')
      div.dataset.depth = item.depth
      div.textContent = (selected.has(item.path) ? '✓ ' : '') + item.label
      div.addEventListener('click', e => {
        e.stopPropagation()
        if (selected.has(item.path)) selected.delete(item.path)
        else selected.add(item.path)
        renderDisplay()
        renderPanel()
      })
      panel.appendChild(div)
    })
  }

  function renderDisplay() {
    display.innerHTML = ''
    if (selected.size === 0) {
      display.innerHTML = '<span class="chip-placeholder">Click to select...</span>'
    } else {
      selected.forEach(path => {
        const chip = document.createElement('span')
        chip.className = 'chip'
        chip.textContent = path.split(' > ').pop()
        const x = document.createElement('span')
        x.className = 'chip-x'
        x.textContent = '×'
        x.addEventListener('click', e => { e.stopPropagation(); selected.delete(path); renderDisplay(); renderPanel() })
        chip.appendChild(x)
        display.appendChild(chip)
      })
    }
    showResult('r8', [...selected].join(', '))
  }

  display.addEventListener('click', () => { renderPanel(); panel.classList.toggle('open') })
  document.addEventListener('click', e => { if (!wrap.contains(e.target)) panel.classList.remove('open') })
})()

// ── 9. Radio Tree ──
;(function () {
  const container = document.getElementById('dd9')
  flat.forEach(item => {
    const label = document.createElement('label')
    label.className = 'depth-' + item.depth + (item.depth === 0 ? ' parent-label' : '')
    const radio = document.createElement('input')
    radio.type = 'radio'
    radio.name = 'dd9-radio'
    radio.value = item.path
    radio.addEventListener('change', () => showResult('r9', item.path))
    label.appendChild(radio)
    label.appendChild(document.createTextNode(' ' + item.label))
    container.appendChild(label)
  })
})()

// ── 10. Accordion ──
;(function () {
  const container = document.getElementById('dd10')
  hierarchy.forEach(top => {
    const section = document.createElement('div')
    section.className = 'acc-section'
    const header = document.createElement('div')
    header.className = 'acc-header'
    header.innerHTML = '<span>' + top.label + '</span><span class="acc-arrow">▶</span>'
    const body = document.createElement('div')
    body.className = 'acc-body'

    header.addEventListener('click', () => {
      body.classList.toggle('open')
      header.querySelector('.acc-arrow').classList.toggle('open')
    })

    if (top.children) {
      top.children.forEach(child => {
        const childDiv = document.createElement('div')
        childDiv.className = 'acc-child'
        childDiv.textContent = child.label
        childDiv.addEventListener('click', () => {
          container.querySelectorAll('.acc-selected').forEach(s => s.classList.remove('acc-selected'))
          childDiv.classList.add('acc-selected')
          showResult('r10', top.label + ' > ' + child.label)
        })
        body.appendChild(childDiv)
        if (child.children) {
          child.children.forEach(gc => {
            const gcDiv = document.createElement('div')
            gcDiv.className = 'acc-grandchild'
            gcDiv.textContent = gc.label
            gcDiv.addEventListener('click', e => {
              e.stopPropagation()
              container.querySelectorAll('.acc-selected').forEach(s => s.classList.remove('acc-selected'))
              gcDiv.classList.add('acc-selected')
              showResult('r10', top.label + ' > ' + child.label + ' > ' + gc.label)
            })
            body.appendChild(gcDiv)
          })
        }
      })
    }

    section.appendChild(header)
    section.appendChild(body)
    container.appendChild(section)
  })
})()
</script>
</body>
</html>
