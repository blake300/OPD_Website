<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/store.php';
require_once __DIR__ . '/../src/site_auth.php';

$user = site_require_auth();
$csrf = site_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Favorites - <?php echo htmlspecialchars(opd_site_name(), ENT_QUOTES); ?></title>
  <link rel="stylesheet" href="/assets/css/site.css" />
</head>
<body>
  <?php require __DIR__ . '/partials/site-header.php'; ?>

  <main class="page dashboard">
    <div class="dashboard-layout">
      <?php require __DIR__ . '/partials/dashboard-nav.php'; ?>

      <div class="dashboard-content">
        <section class="panel favorites-panel">
          <div class="section-title">
            <div>
              <h2>Favorites</h2>
              <p class="meta">Organize favorites, set accounting splits, and push items to the cart.</p>
            </div>
            <div class="favorites-header-actions">
              <button class="btn" type="button" id="favorites-add-all" disabled>Add All</button>
            </div>
          </div>

          <div id="favorites-message" class="notice is-error" style="display:none;"></div>

          <div class="favorites-layout">
            <aside class="favorites-categories">
              <div class="favorites-categories-header">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                  <div class="eyebrow">Categories</div>
                  <button class="btn-outline" type="button" id="favorites-edit-btn" style="font-size: 12px; padding: 4px 12px;">Edit</button>
                </div>
                <p class="meta">Select a group to view items.</p>
              </div>
              <div id="favorites-category-list" class="favorites-category-list"></div>
            </aside>

            <div class="favorites-items">
              <div class="favorites-items-header">
                <h3 id="favorites-category-title">Select a category</h3>
                <p class="meta" id="favorites-category-meta"></p>
              </div>
              <div id="favorites-items-list"></div>
            </div>
          </div>

          <div id="favorites-editor" class="favorites-editor" hidden>
            <div class="favorites-editor-header">
              <div>
                <div class="eyebrow">Edit</div>
                <h3>Favorite Categories</h3>
                <p class="meta">Drag to reorder categories for the dashboard and product favorites.</p>
              </div>
            </div>
            <div id="favorites-editor-list" class="favorites-editor-list"></div>
            <div class="favorites-editor-actions">
              <button class="btn-outline" type="button" id="favorites-editor-add">+</button>
              <button class="btn" type="button" id="favorites-editor-save">Save changes</button>
              <button class="btn-outline" type="button" id="favorites-editor-cancel">Cancel</button>
            </div>
          </div>
        </section>
      </div>
    </div>
  </main>

  <?php require __DIR__ . '/partials/site-footer.php'; ?>
  <script>
    (function () {
      var csrfToken = <?php echo json_encode($csrf); ?>;
      var categoryListEl = document.getElementById('favorites-category-list');
      var itemsListEl = document.getElementById('favorites-items-list');
      var categoryTitleEl = document.getElementById('favorites-category-title');
      var categoryMetaEl = document.getElementById('favorites-category-meta');
      var messageEl = document.getElementById('favorites-message');
      var editBtn = document.getElementById('favorites-edit-btn');
      var addAllBtn = document.getElementById('favorites-add-all');
      var editor = document.getElementById('favorites-editor');
      var editorList = document.getElementById('favorites-editor-list');
      var editorAdd = document.getElementById('favorites-editor-add');
      var editorSave = document.getElementById('favorites-editor-save');
      var editorCancel = document.getElementById('favorites-editor-cancel');

      var categories = [];
      var itemsState = [];
      var selectedCategoryId = '';
      var accountingStructure = { location: [], code1: [], code2: [] };

      function showMessage(text, isError) {
        if (!messageEl) {
          return;
        }
        messageEl.textContent = text;
        messageEl.className = isError ? 'notice is-error' : 'notice';
        messageEl.style.display = 'block';
      }

      function clearMessage() {
        if (!messageEl) {
          return;
        }
        messageEl.style.display = 'none';
        messageEl.textContent = '';
      }

      function fetchJson(url, options) {
        return fetch(url, options || {}).then(function (resp) {
          return resp.json().catch(function () {
            return {};
          }).then(function (data) {
            if (!resp.ok) {
              throw new Error(data.error || 'Request failed');
            }
            return data;
          });
        });
      }

      function loadCategories() {
        return fetchJson('/api/favorites.php').then(function (data) {
          categories = Array.isArray(data.categories) ? data.categories : [];
          renderCategories();
          if (!selectedCategoryId && categories.length) {
            selectCategory(categories[0].id || '');
          }
        });
      }

      function loadAccounting() {
        return fetchJson('/api/accounting_structure.php').then(function (data) {
          if (data && typeof data === 'object') {
            accountingStructure = data;
          }
        });
      }

      function loadItems(categoryId) {
        if (!categoryId) {
          itemsState = [];
          renderItems();
          return Promise.resolve();
        }
        return fetchJson('/api/favorites.php?categoryId=' + encodeURIComponent(categoryId))
          .then(function (data) {
            itemsState = Array.isArray(data.items) ? data.items : [];
            renderItems();
          });
      }

      function selectCategory(categoryId) {
        selectedCategoryId = categoryId;
        renderCategories();
        var selected = categories.find(function (cat) { return cat.id === categoryId; });
        if (categoryTitleEl) {
          categoryTitleEl.textContent = selected ? selected.name : 'Favorites';
        }
        if (categoryMetaEl) {
          categoryMetaEl.textContent = selected ? 'Edit quantities and accounting splits.' : '';
        }
        addAllBtn.disabled = true;
        loadItems(categoryId);
      }

      function renderCategories() {
        if (!categoryListEl) {
          return;
        }
        categoryListEl.innerHTML = '';
        if (!categories.length) {
          var empty = document.createElement('div');
          empty.className = 'notice';
          empty.textContent = 'No categories yet.';
          categoryListEl.appendChild(empty);
          return;
        }
        categories.forEach(function (category) {
          var button = document.createElement('button');
          button.type = 'button';
          button.className = 'favorite-category-btn';
          if (category.id === selectedCategoryId) {
            button.classList.add('is-active');
          }
          button.textContent = category.name || 'Category';
          button.addEventListener('click', function () {
            selectCategory(category.id || '');
          });
          categoryListEl.appendChild(button);
        });
      }

      function normalizeNodeLabel(value) {
        return String(value || '').trim();
      }

      function parseAccountingPath(value) {
        if (Array.isArray(value)) {
          return value.map(function (part) { return normalizeNodeLabel(part); }).filter(Boolean);
        }
        if (typeof value === 'string') {
          var trimmed = value.trim();
          if (!trimmed) {
            return [];
          }
          return trimmed.split(' > ').map(function (part) { return normalizeNodeLabel(part); }).filter(Boolean);
        }
        return [];
      }

      function joinAccountingPath(parts) {
        return parts.filter(Boolean).join(' > ');
      }

      function findChildNode(nodes, label) {
        var target = normalizeNodeLabel(label);
        if (!target) {
          return null;
        }
        return (nodes || []).find(function (node) {
          return normalizeNodeLabel(node.label) === target;
        }) || null;
      }

      function getNodesAtLevel(category, pathParts, level) {
        var nodes = Array.isArray(accountingStructure[category]) ? accountingStructure[category] : [];
        for (var i = 0; i < level; i += 1) {
          var parentLabel = pathParts[i];
          if (!parentLabel) {
            return [];
          }
          var parent = findChildNode(nodes, parentLabel);
          if (!parent || !Array.isArray(parent.children)) {
            return [];
          }
          nodes = parent.children;
        }
        return nodes;
      }

      function isAtDeepestLevel(category, pathValue) {
        if (!pathValue) return true;
        var pathParts = parseAccountingPath(pathValue);
        if (!pathParts.length) return true;
        var nodes = Array.isArray(accountingStructure[category]) ? accountingStructure[category] : [];
        for (var i = 0; i < pathParts.length; i += 1) {
          var node = findChildNode(nodes, pathParts[i]);
          if (!node) return true;
          if (i === pathParts.length - 1) {
            return !Array.isArray(node.children) || node.children.length === 0;
          }
          nodes = node.children || [];
        }
        return true;
      }

      function buildCascadingOptions(nodes, pathParts, level, fragment) {
        var indent = '';
        for (var i = 0; i < level; i++) { indent += '\u00A0\u00A0'; }
        nodes.forEach(function (node) {
          var label = normalizeNodeLabel(node.label);
          if (!label) return;

          var option = document.createElement('option');
          var currentPath = pathParts.slice(0, level).concat([label]);
          option.value = joinAccountingPath(currentPath);
          option.textContent = indent + label;
          option.dataset.level = String(level);

          var fullPath = joinAccountingPath(pathParts);
          var optionPath = joinAccountingPath(currentPath);
          if (fullPath === optionPath || fullPath.indexOf(optionPath + ' > ') === 0) {
            option.selected = fullPath === optionPath;
          }

          fragment.appendChild(option);

          // If this node is in the current path, show its children
          if (pathParts[level] === label && Array.isArray(node.children) && node.children.length > 0) {
            buildCascadingOptions(node.children, pathParts, level + 1, fragment);
          }
        });
      }

      function buildAccountingCategoryField(category, label, split) {
        var field = document.createElement('div');
        field.className = 'favorite-split-field';
        var title = document.createElement('label');
        title.textContent = label;
        field.appendChild(title);

        var currentValue = split[category] || '';
        var nodes = Array.isArray(accountingStructure[category]) ? accountingStructure[category] : [];

        if (typeof AccordionDropdown !== 'undefined') {
          AccordionDropdown.create(field, nodes, {
            value: currentValue,
            placeholder: 'Select ' + label.toLowerCase() + '...',
            onChange: function (path) {
              split[category] = path;
              renderItems();
            }
          });
        } else {
          var pathParts = parseAccountingPath(currentValue);
          var select = document.createElement('select');
          select.className = 'favorite-split-select accounting-cascading-select';
          select.dataset.category = category;
          var blank = document.createElement('option');
          blank.value = ''; blank.textContent = 'Select';
          select.appendChild(blank);
          var rootNodes = Array.isArray(accountingStructure[category]) ? accountingStructure[category] : [];
          var fragment = document.createDocumentFragment();
          buildCascadingOptions(rootNodes, pathParts, 0, fragment);
          select.appendChild(fragment);
          var cv = joinAccountingPath(pathParts);
          if (cv) select.value = cv;
          select.addEventListener('change', function (event) {
            split[category] = event.target.value;
            renderItems();
          });
          field.appendChild(select);
        }

        return field;
      }

      function normalizeSplits(item) {
        var splits = Array.isArray(item.splits) ? item.splits : [];
        if (!splits.length) {
          splits = [{ location: '', code1: '', code2: '', qty: item.quantity || 1 }];
        }
        return splits.map(function (split) {
          return {
            location: split.location || '',
            code1: split.code1 || '',
            code2: split.code2 || '',
            qty: typeof split.qty === 'number' ? split.qty : parseInt(split.qty || '0', 10)
          };
        });
      }

      function splitQtySum(splits) {
        return splits.reduce(function (sum, split) {
          return sum + (parseInt(split.qty || '0', 10) || 0);
        }, 0);
      }

      function renderItems() {
        if (!itemsListEl) {
          return;
        }
        itemsListEl.innerHTML = '';
        if (!itemsState.length) {
          itemsListEl.innerHTML = '<div class="notice">No favorites in this category yet.</div>';
          addAllBtn.disabled = true;
          return;
        }
        addAllBtn.disabled = false;

        itemsState.forEach(function (item) {
          item.quantity = item.quantity || 1;
          item.splits = normalizeSplits(item);

          var card = document.createElement('div');
          card.className = 'favorite-item-card';
          card.dataset.entryId = item.id;

          var main = document.createElement('div');
          main.className = 'favorite-item-main';

          var media = document.createElement('div');
          media.className = 'favorite-item-media';
          if (item.imageUrl) {
            var img = document.createElement('img');
            img.src = item.imageUrl;
            img.alt = item.name || 'Product';
            media.appendChild(img);
          } else {
            var placeholder = document.createElement('div');
            placeholder.className = 'image-placeholder';
            placeholder.textContent = 'No image';
            media.appendChild(placeholder);
          }
          main.appendChild(media);

          var info = document.createElement('div');
          info.className = 'favorite-item-info';
          var name = document.createElement('div');
          name.className = 'favorite-item-name';
          name.textContent = item.name || 'Product';
          var price = document.createElement('div');
          price.className = 'favorite-item-price';
          price.textContent = '$' + Number(item.price || 0).toFixed(2);
          info.appendChild(name);
          info.appendChild(price);
          main.appendChild(info);

          var actions = document.createElement('div');
          actions.className = 'favorite-item-actions';
          var qtyLabel = document.createElement('label');
          qtyLabel.textContent = 'Qty';
          var qtyInput = document.createElement('input');
          qtyInput.type = 'number';
          qtyInput.min = '1';
          qtyInput.value = item.quantity;
          qtyInput.addEventListener('input', function () {
            item.quantity = parseInt(qtyInput.value || '1', 10) || 1;
            renderItems();
          });
          actions.appendChild(qtyLabel);
          actions.appendChild(qtyInput);

          var addBtn = document.createElement('button');
          addBtn.type = 'button';
          addBtn.className = 'btn-outline';
          addBtn.textContent = 'Add';
          addBtn.addEventListener('click', function () {
            clearMessage();
            fetchJson('/api/favorites.php', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
              },
              body: JSON.stringify({ action: 'add_to_cart', entryIds: [item.id] })
            }).then(function () {
              showMessage('Item added to cart.', false);
            }).catch(function (err) {
              showMessage(err.message || 'Unable to add to cart.', true);
            });
          });
          actions.appendChild(addBtn);
          main.appendChild(actions);

          card.appendChild(main);

          var splitsWrap = document.createElement('div');
          splitsWrap.className = 'favorite-item-splits';

          item.splits.forEach(function (split, splitIndex) {
            var row = document.createElement('div');
            row.className = 'favorite-split-row';

            row.appendChild(buildAccountingCategoryField('location', 'Location', split));
            row.appendChild(buildAccountingCategoryField('code1', 'Code 1', split));
            row.appendChild(buildAccountingCategoryField('code2', 'Code 2', split));

            var qtyWrap = document.createElement('div');
            qtyWrap.className = 'favorite-split-qty';
            var qtyTitle = document.createElement('label');
            qtyTitle.textContent = 'Qty';
            var qtyField = document.createElement('input');
            qtyField.type = 'number';
            qtyField.min = '0';
            qtyField.value = split.qty;
            qtyField.addEventListener('input', function () {
              split.qty = parseInt(qtyField.value || '0', 10) || 0;
              renderItems();
            });
            qtyWrap.appendChild(qtyTitle);
            qtyWrap.appendChild(qtyField);
            row.appendChild(qtyWrap);

            var controls = document.createElement('div');
            controls.className = 'favorite-split-controls';
            if (splitIndex === item.splits.length - 1) {
              var addSplitBtn = document.createElement('button');
              addSplitBtn.type = 'button';
              addSplitBtn.className = 'btn-outline';
              addSplitBtn.textContent = '+';
              addSplitBtn.addEventListener('click', function () {
                item.splits.push({ location: '', code1: '', code2: '', qty: 0 });
                renderItems();
              });
              controls.appendChild(addSplitBtn);
            }
            if (item.splits.length > 1) {
              var removeSplitBtn = document.createElement('button');
              removeSplitBtn.type = 'button';
              removeSplitBtn.className = 'btn-outline';
              removeSplitBtn.textContent = 'Remove';
              removeSplitBtn.addEventListener('click', function () {
                item.splits.splice(splitIndex, 1);
                if (!item.splits.length) {
                  item.splits.push({ location: '', code1: '', code2: '', qty: item.quantity });
                }
                renderItems();
              });
              controls.appendChild(removeSplitBtn);
            }
            row.appendChild(controls);

            splitsWrap.appendChild(row);
          });

          var mismatch = splitQtySum(item.splits) !== item.quantity;
          if (mismatch) {
            card.classList.add('is-mismatch');
          } else {
            card.classList.remove('is-mismatch');
          }

          var saveRow = document.createElement('div');
          saveRow.className = 'favorite-item-save';
          var error = document.createElement('div');
          error.className = 'favorite-item-error';
          error.textContent = 'Split quantities must equal item quantity.';
          if (!mismatch) {
            error.style.display = 'none';
          }
          saveRow.appendChild(error);

          var saveBtn = document.createElement('button');
          saveBtn.type = 'button';
          saveBtn.className = 'btn';
          saveBtn.textContent = 'Save changes';
          saveBtn.addEventListener('click', function () {
            if (mismatch) {
              showMessage('Split quantities must equal item quantity.', true);
              return;
            }
            clearMessage();
            fetchJson('/api/favorites.php', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
              },
              body: JSON.stringify({
                action: 'save_items',
                items: [{ id: item.id, quantity: item.quantity, splits: item.splits }]
              })
            }).then(function () {
              showMessage('Favorites updated.', false);
            }).catch(function (err) {
              showMessage(err.message || 'Unable to save favorites.', true);
            });
          });
          saveRow.appendChild(saveBtn);

          card.appendChild(splitsWrap);
          card.appendChild(saveRow);
          itemsListEl.appendChild(card);
        });
      }

      function openEditor() {
        if (!editor || !editorList) {
          return;
        }
        editor.hidden = false;
        renderEditor();
      }

      function closeEditor() {
        if (editor) {
          editor.hidden = true;
        }
      }

      function wireEditorRowDrag(row) {
        var handle = row.querySelector('[data-drag-handle]');
        if (handle) {
          handle.addEventListener('mousedown', function (event) {
            event.preventDefault();
          });
          handle.style.cursor = 'grab';
          row.draggable = true;
        }

        row.addEventListener('dragstart', function (event) {
          if (handle) {
            handle.style.cursor = 'grabbing';
          }
          row.classList.add('is-dragging');
          editorList._dragging = row;
          if (event.dataTransfer) {
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', 'category');
          }
        });

        row.addEventListener('dragover', function (event) {
          if (!editorList._dragging || editorList._dragging === row) {
            return;
          }
          event.preventDefault();
          var rect = row.getBoundingClientRect();
          var isAfter = event.clientY > rect.top + rect.height / 2;
          row.classList.toggle('drag-over-top', !isAfter);
          row.classList.toggle('drag-over-bottom', isAfter);
        });

        row.addEventListener('dragleave', function () {
          row.classList.remove('drag-over-top', 'drag-over-bottom');
        });

        row.addEventListener('drop', function (event) {
          if (!editorList._dragging || editorList._dragging === row) {
            return;
          }
          event.preventDefault();
          var rect = row.getBoundingClientRect();
          var isAfter = event.clientY > rect.top + rect.height / 2;
          var anchor = isAfter ? row.nextSibling : row;
          editorList.insertBefore(editorList._dragging, anchor);
          editorList.querySelectorAll('.favorite-editor-row').forEach(function (node) {
            node.classList.remove('drag-over-top', 'drag-over-bottom');
          });
        });

        row.addEventListener('dragend', function () {
          if (handle) {
            handle.style.cursor = 'grab';
          }
          row.classList.remove('is-dragging');
          editorList._dragging = null;
          editorList.querySelectorAll('.favorite-editor-row').forEach(function (node) {
            node.classList.remove('drag-over-top', 'drag-over-bottom');
          });
        });
      }

      function renderEditor() {
        editorList.innerHTML = '';
        categories.forEach(function (category) {
          var row = document.createElement('div');
          row.className = 'favorite-editor-row';
          row.dataset.categoryId = category.id || '';

          var input = document.createElement('input');
          input.type = 'text';
          input.value = category.name || '';
          input.placeholder = 'Category name';

          var handle = document.createElement('button');
          handle.type = 'button';
          handle.className = 'drag-handle';
          handle.textContent = '::';
          handle.dataset.dragHandle = 'true';

          var remove = document.createElement('button');
          remove.type = 'button';
          remove.className = 'btn-outline';
          remove.textContent = 'Remove';

          row.appendChild(input);
          row.appendChild(handle);
          row.appendChild(remove);

          if (category.isDefault) {
            input.readOnly = true;
            input.classList.add('is-readonly');
            remove.disabled = true;
            handle.disabled = true;
            row.classList.add('is-locked');
          } else {
            row.draggable = false;
            wireEditorRowDrag(row);
            remove.addEventListener('click', function () {
              row.remove();
            });
          }

          editorList.appendChild(row);
        });
      }

      function saveEditor() {
        var payload = [];
        editorList.querySelectorAll('.favorite-editor-row').forEach(function (row) {
          var input = row.querySelector('input');
          if (!input || input.readOnly) {
            return;
          }
          var name = input.value.trim();
          if (!name) {
            return;
          }
          payload.push({
            id: row.dataset.categoryId || '',
            name: name
          });
        });

        fetchJson('/api/favorites.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
          },
          body: JSON.stringify({ action: 'save_categories', categories: payload })
        }).then(function (data) {
          categories = Array.isArray(data.categories) ? data.categories : [];
          renderCategories();
          closeEditor();
        }).catch(function (err) {
          showMessage(err.message || 'Unable to save categories.', true);
        });
      }

      if (editBtn) {
        editBtn.addEventListener('click', openEditor);
      }
      if (editorAdd) {
        editorAdd.addEventListener('click', function () {
          var row = document.createElement('div');
          row.className = 'favorite-editor-row';
          row.dataset.categoryId = '';
          row.draggable = false;
          var input = document.createElement('input');
          input.type = 'text';
          input.placeholder = 'Category name';
          var handle = document.createElement('button');
          handle.type = 'button';
          handle.className = 'drag-handle';
          handle.textContent = '::';
          handle.dataset.dragHandle = 'true';
          var remove = document.createElement('button');
          remove.type = 'button';
          remove.className = 'btn-outline';
          remove.textContent = 'Remove';
          remove.addEventListener('click', function () {
            row.remove();
          });
          row.appendChild(input);
          row.appendChild(handle);
          row.appendChild(remove);
          wireEditorRowDrag(row);
          editorList.appendChild(row);
        });
      }
      if (editorSave) {
        editorSave.addEventListener('click', saveEditor);
      }
      if (editorCancel) {
        editorCancel.addEventListener('click', closeEditor);
      }

      if (addAllBtn) {
        addAllBtn.addEventListener('click', function () {
          if (!itemsState.length) {
            return;
          }
          var entryIds = itemsState.map(function (item) { return item.id; });
          fetchJson('/api/favorites.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({ action: 'add_to_cart', entryIds: entryIds })
          }).then(function () {
            showMessage('All items added to cart.', false);
          }).catch(function (err) {
            showMessage(err.message || 'Unable to add all items.', true);
          });
        });
      }

      Promise.all([loadCategories(), loadAccounting()]).catch(function (err) {
        showMessage(err.message || 'Unable to load favorites.', true);
      });
    })();
  </script>
</body>
</html>
