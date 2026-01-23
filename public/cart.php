<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/store.php';
require_once __DIR__ . '/../src/site_auth.php';

function cart_setting(PDO $pdo, string $key, float $default): float
{
    $stmt = $pdo->prepare('SELECT value FROM settings WHERE `key` = ? LIMIT 1');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    if (!$row) {
        return $default;
    }
    $value = is_numeric($row['value']) ? (float) $row['value'] : $default;
    return $value;
}

$user = site_current_user();
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    site_require_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'update') {
        $key = $_POST['key'] ?? '';
        $qty = (int) ($_POST['quantity'] ?? 1);
        if ($key !== '') {
            site_update_cart_item($key, $qty);
            $message = 'Cart updated.';
        }
    }
    if ($action === 'remove') {
        $key = $_POST['key'] ?? '';
        if ($key !== '') {
            site_remove_cart_item($key);
            $message = 'Item removed.';
        }
    }
}

$items = site_cart_items();
$total = site_cart_total($items);
$csrf = site_csrf_token();

$clients = [];
$accountingStructure = ['location' => [], 'code1' => [], 'code2' => []];
$userAddress = ['address' => '', 'city' => '', 'state' => '', 'zip' => ''];
$pdo = opd_db();
$shippingStandard = cart_setting($pdo, 'shipping_standard', 0.0);
$shippingSameDay = cart_setting($pdo, 'shipping_same_day', 0.0);

if ($user) {
    $clients = site_simple_list('clients', $user['id']);
    $accountingStructure = site_get_accounting_structure($user['id']);
    $stmt = $pdo->prepare('SELECT address, city, state, zip FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$user['id']]);
    $row = $stmt->fetch();
    if ($row) {
        $userAddress = [
            'address' => $row['address'] ?? '',
            'city' => $row['city'] ?? '',
            'state' => $row['state'] ?? '',
            'zip' => $row['zip'] ?? '',
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Cart - Oil Patch Depot</title>
  <link rel="stylesheet" href="/assets/css/site.css" />
</head>
<body>
  <?php require __DIR__ . '/partials/site-header.php'; ?>

  <main class="page cart-page">
    <div class="cart-header">
      <div>
        <h2>Your cart</h2>
        <p class="meta">Review items before checkout.</p>
      </div>
      <div class="cart-actions">
        <button class="btn-outline" type="button" data-back>Back</button>
        <button class="btn-outline" type="button" data-back>Continue shopping</button>
      </div>
    </div>

    <?php if ($message): ?>
      <div class="notice"><?php echo htmlspecialchars($message, ENT_QUOTES); ?></div>
    <?php endif; ?>

    <?php if (!$items): ?>
      <div class="notice">Your cart is empty.</div>
    <?php else: ?>
      <div class="cart-layout">
        <section class="panel cart-panel">
          <div id="cart-message" class="notice is-error" style="display:none;"></div>

          <div class="cart-client">
            <label for="cart-client-select">Client</label>
            <select id="cart-client-select" <?php echo $user && $clients ? '' : 'disabled'; ?>>
              <option value="">Select a client (optional)</option>
              <?php foreach ($clients as $client): ?>
                <option value="<?php echo htmlspecialchars($client['id'] ?? '', ENT_QUOTES); ?>">
                  <?php echo htmlspecialchars($client['name'] ?? '', ENT_QUOTES); ?>
                </option>
              <?php endforeach; ?>
            </select>
            <?php if (!$user): ?>
              <div class="meta">Sign in to select a client.</div>
            <?php elseif (!$clients): ?>
              <div class="meta">No clients saved yet.</div>
            <?php endif; ?>
          </div>

          <div class="cart-groups">
            <div class="cart-groups-header">
              <div>
                <h3>Accounting groups</h3>
                <p class="meta">Enter accounting codes once and assign them to items below.</p>
              </div>
              <button class="btn-outline" type="button" id="add-group">Add group</button>
            </div>
            <div id="accounting-group-list" class="cart-groups-list"></div>
          </div>

          <div class="cart-items">
            <?php foreach ($items as $item): ?>
              <?php
                $imageUrl = trim((string) ($item['imageUrl'] ?? ''));
                $hasImage = $imageUrl !== '';
                $itemKey = (string) $item['key'];
                $itemQty = (int) $item['quantity'];
              ?>
              <div class="cart-item" data-item-key="<?php echo htmlspecialchars($itemKey, ENT_QUOTES); ?>" data-item-qty="<?php echo $itemQty; ?>">
                <div class="cart-item-row">
                  <div class="cart-item-media">
                    <?php if ($hasImage): ?>
                      <img src="<?php echo htmlspecialchars($imageUrl, ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($item['name'], ENT_QUOTES); ?>" />
                    <?php else: ?>
                      <div class="image-placeholder">No image</div>
                    <?php endif; ?>
                  </div>
                  <div class="cart-item-info">
                    <div class="cart-item-name"><?php echo htmlspecialchars($item['name'], ENT_QUOTES); ?></div>
                    <div class="cart-item-price">$<?php echo number_format((float) $item['price'], 2); ?></div>
                  </div>
                  <div class="cart-item-actions">
                    <form method="POST" class="cart-qty-form">
                      <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES); ?>" />
                      <input type="hidden" name="action" value="update" />
                      <input type="hidden" name="key" value="<?php echo htmlspecialchars($itemKey, ENT_QUOTES); ?>" />
                      <label class="visually-hidden" for="qty-<?php echo htmlspecialchars($itemKey, ENT_QUOTES); ?>">Quantity</label>
                      <select id="qty-<?php echo htmlspecialchars($itemKey, ENT_QUOTES); ?>" name="quantity" class="cart-qty-select">
                        <?php for ($i = 1; $i <= 25; $i++): ?>
                          <option value="<?php echo $i; ?>" <?php echo $i === $itemQty ? 'selected' : ''; ?>>
                            <?php echo $i; ?>
                          </option>
                        <?php endfor; ?>
                      </select>
                    </form>
                    <form method="POST" class="cart-remove-form">
                      <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES); ?>" />
                      <input type="hidden" name="action" value="remove" />
                      <input type="hidden" name="key" value="<?php echo htmlspecialchars($itemKey, ENT_QUOTES); ?>" />
                      <button class="btn-outline" type="submit">Remove</button>
                    </form>
                  </div>
                </div>
                <div class="cart-item-groups">
                  <div class="item-group-grid"></div>
                  <div class="item-group-error">Group quantities must match the item quantity.</div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </section>

        <aside class="panel cart-summary">
          <h3>Order summary</h3>
          <div class="summary-line">
            <span>Subtotal</span>
            <span id="summary-subtotal" data-subtotal="<?php echo number_format($total, 2, '.', ''); ?>">
              $<?php echo number_format($total, 2); ?>
            </span>
          </div>

          <div class="summary-section">
            <h4>Shipping address</h4>
            <div class="form-grid">
              <div>
                <label for="ship-address">Address</label>
                <input id="ship-address" name="ship-address" value="<?php echo htmlspecialchars($userAddress['address'], ENT_QUOTES); ?>" />
              </div>
              <div>
                <label for="ship-city">City</label>
                <input id="ship-city" name="ship-city" value="<?php echo htmlspecialchars($userAddress['city'], ENT_QUOTES); ?>" />
              </div>
              <div>
                <label for="ship-state">State</label>
                <input id="ship-state" name="ship-state" value="<?php echo htmlspecialchars($userAddress['state'], ENT_QUOTES); ?>" />
              </div>
              <div>
                <label for="ship-zip">Zip</label>
                <input id="ship-zip" name="ship-zip" value="<?php echo htmlspecialchars($userAddress['zip'], ENT_QUOTES); ?>" />
              </div>
            </div>
            <div class="meta" id="shipping-note">Enter a shipping address to choose delivery options.</div>
          </div>

          <div class="summary-section">
            <h4>Shipping method</h4>
            <label class="radio-row">
              <input type="radio" name="shipping_method" value="pickup" data-cost="0" checked />
              <span>Pickup in Ada, OK</span>
              <span class="radio-cost">$0.00</span>
            </label>
            <label class="radio-row">
              <input type="radio" name="shipping_method" value="standard" data-cost="<?php echo number_format($shippingStandard, 2, '.', ''); ?>" />
              <span>Standard Shipping</span>
              <span class="radio-cost">$<?php echo number_format($shippingStandard, 2); ?></span>
            </label>
            <label class="radio-row">
              <input type="radio" name="shipping_method" value="same_day" data-cost="<?php echo number_format($shippingSameDay, 2, '.', ''); ?>" />
              <span>Same Day Oklahoma Delivery (If Order by 10am)</span>
              <span class="radio-cost">$<?php echo number_format($shippingSameDay, 2); ?></span>
            </label>
          </div>

          <div class="summary-line">
            <span>Shipping</span>
            <span id="summary-shipping">$0.00</span>
          </div>
          <div class="summary-line summary-total">
            <span>Total</span>
            <span id="summary-total">$<?php echo number_format($total, 2); ?></span>
          </div>

          <a class="btn" href="/checkout.php" id="checkout-button">Checkout</a>
        </aside>
      </div>
    <?php endif; ?>
  </main>

  <?php require __DIR__ . '/partials/site-footer.php'; ?>

  <script>
    (function(){
      const isSignedIn = <?php echo $user ? 'true' : 'false'; ?>;
      const csrfToken = '<?php echo htmlspecialchars($csrf, ENT_QUOTES); ?>';
      const accountingStructure = <?php echo json_encode($accountingStructure, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
      const groupStorageBase = 'opd_cart_groups_v1';
      const assignmentStorageBase = 'opd_cart_assignments_v1';
      const clientStorageKey = 'opd_cart_client_v1';

      const messageEl = document.getElementById('cart-message');
      const groupList = document.getElementById('accounting-group-list');
      const addGroupButton = document.getElementById('add-group');
      const itemEls = Array.from(document.querySelectorAll('.cart-item'));
      const clientSelect = document.getElementById('cart-client-select');

      const shippingNote = document.getElementById('shipping-note');
      const shippingInputs = Array.from(document.querySelectorAll('input[name="shipping_method"]'));
      const summarySubtotal = document.getElementById('summary-subtotal');
      const summaryShipping = document.getElementById('summary-shipping');
      const summaryTotal = document.getElementById('summary-total');
      const checkoutButton = document.getElementById('checkout-button');

      const addressFields = [
        document.getElementById('ship-address'),
        document.getElementById('ship-city'),
        document.getElementById('ship-state'),
        document.getElementById('ship-zip')
      ].filter(Boolean);

      const subtotalValue = summarySubtotal ? parseFloat(summarySubtotal.dataset.subtotal || '0') : 0;

      function showMessage(text) {
        if (!messageEl) return;
        messageEl.textContent = text;
        messageEl.style.display = 'block';
      }

      function clearMessage() {
        if (!messageEl) return;
        messageEl.textContent = '';
        messageEl.style.display = 'none';
      }

      function updateCheckoutLink() {
        if (!checkoutButton) return;
        const base = '/checkout.php';
        checkoutButton.href = activeClientId ? `${base}?clientId=${encodeURIComponent(activeClientId)}` : base;
      }

      async function fetchServerAccounting(clientId) {
        if (!isSignedIn) return null;
        const query = clientId ? `?clientId=${encodeURIComponent(clientId)}` : '';
        const resp = await fetch(`/api/cart_accounting.php${query}`);
        if (!resp.ok) {
          return null;
        }
        const data = await resp.json().catch(() => null);
        if (!data || !Array.isArray(data.groups)) {
          return null;
        }
        return data;
      }

      function saveServerAccounting() {
        if (!isSignedIn) {
          return Promise.resolve();
        }
        const payload = {
          clientId: activeClientId || null,
          groups,
          assignments
        };
        return fetch('/api/cart_accounting.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
          },
          body: JSON.stringify(payload)
        }).then(async (resp) => {
          const data = await resp.json().catch(() => ({}));
          if (!resp.ok) {
            throw new Error(data.error || 'Save failed');
          }
          return data;
        });
      }

      function queueSaveToServer() {
        if (!isSignedIn) return;
        if (saveTimer) {
          clearTimeout(saveTimer);
        }
        saveTimer = setTimeout(() => {
          saveServerAccounting().catch((err) => {
            showMessage(`Unable to save accounting groups: ${err.message}`);
          });
        }, 400);
      }

      function cleanAssignments() {
        const currentKeys = itemEls.map((itemEl) => itemEl.dataset.itemKey);
        Object.keys(assignments).forEach((key) => {
          if (!currentKeys.includes(key)) {
            delete assignments[key];
          }
        });
        saveAssignments();
      }

      async function hydrateAccounting() {
        isHydrating = true;
        let loadedFromServer = false;
        let serverData = null;
        try {
          serverData = await fetchServerAccounting(activeClientId);
        } catch (err) {
          serverData = null;
        }
        if (serverData && Array.isArray(serverData.groups) && serverData.groups.length) {
          groups = serverData.groups;
          assignments = serverData.assignments || {};
          localStorage.setItem(activeGroupStorageKey, JSON.stringify(groups));
          localStorage.setItem(activeAssignmentStorageKey, JSON.stringify(assignments));
          loadedFromServer = true;
        } else {
          groups = loadGroups();
          assignments = loadAssignments();
        }
        cleanAssignments();
        renderGroups();
        renderItems();
        isHydrating = false;
        if (isSignedIn && !loadedFromServer) {
          queueSaveToServer();
        }
      }

      document.querySelectorAll('[data-back]').forEach((btn) => {
        btn.addEventListener('click', () => {
          history.back();
        });
      });

      document.querySelectorAll('.cart-qty-select').forEach((select) => {
        select.addEventListener('change', () => {
          select.form.submit();
        });
      });

      function groupStorageKeyForClient(clientId) {
        return clientId ? `${groupStorageBase}_${clientId}` : groupStorageBase;
      }

      function assignmentStorageKeyForClient(clientId) {
        return clientId ? `${assignmentStorageBase}_${clientId}` : assignmentStorageBase;
      }

      let activeClientId = '';
      let activeGroupStorageKey = groupStorageBase;
      let activeAssignmentStorageKey = assignmentStorageBase;
      let isHydrating = false;
      let saveTimer = null;

      function setActiveClient(clientId) {
        activeClientId = clientId || '';
        activeGroupStorageKey = groupStorageKeyForClient(activeClientId);
        activeAssignmentStorageKey = assignmentStorageKeyForClient(activeClientId);
        updateCheckoutLink();
      }

      if (clientSelect) {
        const savedClient = localStorage.getItem(clientStorageKey);
        if (savedClient) {
          clientSelect.value = savedClient;
        }
        setActiveClient(clientSelect.value);
        clientSelect.addEventListener('change', async () => {
          localStorage.setItem(clientStorageKey, clientSelect.value);
          setActiveClient(clientSelect.value);
          await hydrateAccounting();
        });
      } else {
        setActiveClient('');
      }

      function flattenOptions(nodes, prefix) {
        const list = [];
        (nodes || []).forEach((node) => {
          const label = prefix ? `${prefix} > ${node.label || ''}` : (node.label || '');
          if (label.trim() !== '') {
            list.push({ value: label, label });
          }
          if (node.children && node.children.length) {
            list.push(...flattenOptions(node.children, label));
          }
        });
        return list;
      }

      const accountingOptions = {
        location: flattenOptions(accountingStructure.location || [], ''),
        code1: flattenOptions(accountingStructure.code1 || [], ''),
        code2: flattenOptions(accountingStructure.code2 || [], '')
      };

      function defaultGroups() {
        return [{ location: '', code1: '', code2: '' }];
      }

      function loadGroups() {
        try {
          const saved = JSON.parse(localStorage.getItem(activeGroupStorageKey) || 'null');
          if (Array.isArray(saved) && saved.length) {
            return saved;
          }
        } catch (err) {
          return defaultGroups();
        }
        return defaultGroups();
      }

      function saveGroups() {
        localStorage.setItem(activeGroupStorageKey, JSON.stringify(groups));
        if (!isHydrating) {
          queueSaveToServer();
        }
      }

      function groupLabel(index) {
        const group = groups[index] || {};
        const parts = [group.location, group.code1, group.code2].filter(Boolean);
        const suffix = parts.length ? ' - ' + parts.join(' / ') : '';
        return `Group ${index + 1}${suffix}`;
      }

      function buildGroupOptions(selectedIndex) {
        const fragment = document.createDocumentFragment();
        groups.forEach((group, index) => {
          const option = document.createElement('option');
          option.value = String(index);
          option.textContent = groupLabel(index);
          if (index === selectedIndex) {
            option.selected = true;
          }
          fragment.appendChild(option);
        });
        return fragment;
      }

      function buildAccountingSelect(category, value, index) {
        const select = document.createElement('select');
        select.dataset.category = category;
        const blank = document.createElement('option');
        blank.value = '';
        blank.textContent = 'Select';
        select.appendChild(blank);
        (accountingOptions[category] || []).forEach((optionData) => {
          const option = document.createElement('option');
          option.value = optionData.value;
          option.textContent = optionData.label;
          if (optionData.value === value) {
            option.selected = true;
          }
          select.appendChild(option);
        });
        select.addEventListener('change', (event) => {
          if (!isSignedIn) {
            showMessage('Sign in to use accounting codes.');
            event.target.value = value || '';
            return;
          }
          clearMessage();
          groups[index][category] = event.target.value;
          saveGroups();
          renderItems();
        });
        return select;
      }

      let groups = loadGroups();
      let assignments = {};

      function loadAssignments() {
        try {
          const saved = JSON.parse(localStorage.getItem(activeAssignmentStorageKey) || '{}');
          if (saved && typeof saved === 'object') {
            return saved;
          }
        } catch (err) {
          return {};
        }
        return {};
      }

      function saveAssignments() {
        localStorage.setItem(activeAssignmentStorageKey, JSON.stringify(assignments));
        if (!isHydrating) {
          queueSaveToServer();
        }
      }

      function renderGroups() {
        if (!groupList) return;
        groupList.innerHTML = '';

        groups.forEach((group, index) => {
          const row = document.createElement('div');
          row.className = 'cart-group-row';

          const header = document.createElement('div');
          header.className = 'cart-group-title';
          header.textContent = `Group ${index + 1}`;
          row.appendChild(header);

          const fields = document.createElement('div');
          fields.className = 'cart-group-fields';

          const locationField = document.createElement('div');
          locationField.className = 'cart-group-field';
          locationField.appendChild(document.createElement('label')).textContent = 'Location';
          locationField.appendChild(buildAccountingSelect('location', group.location, index));

          const code1Field = document.createElement('div');
          code1Field.className = 'cart-group-field';
          code1Field.appendChild(document.createElement('label')).textContent = 'Code 1';
          code1Field.appendChild(buildAccountingSelect('code1', group.code1, index));

          const code2Field = document.createElement('div');
          code2Field.className = 'cart-group-field';
          code2Field.appendChild(document.createElement('label')).textContent = 'Code 2';
          code2Field.appendChild(buildAccountingSelect('code2', group.code2, index));

          fields.appendChild(locationField);
          fields.appendChild(code1Field);
          fields.appendChild(code2Field);
          row.appendChild(fields);

          if (groups.length > 1) {
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'btn-outline';
            remove.textContent = 'Remove group';
            remove.addEventListener('click', () => {
              if (!isSignedIn) {
                showMessage('Sign in to use accounting codes.');
                return;
              }
              groups.splice(index, 1);
              if (!groups.length) {
                groups = defaultGroups();
              }
              saveGroups();
              renderGroups();
              renderItems();
            });
            row.appendChild(remove);
          }

          groupList.appendChild(row);
        });
      }

      function normalizeAssignments(itemKey, itemQty) {
        let itemAssignments = assignments[itemKey];
        if (!Array.isArray(itemAssignments) || !itemAssignments.length) {
          itemAssignments = [{ groupIndex: 0, qty: itemQty }];
        }
        itemAssignments = itemAssignments.map((entry) => {
          const groupIndex = Math.min(entry.groupIndex || 0, groups.length - 1);
          const qty = Number.isFinite(entry.qty) ? entry.qty : 1;
          return { groupIndex, qty };
        });
        if (itemAssignments.length === 1) {
          itemAssignments[0].qty = itemQty;
        }
        assignments[itemKey] = itemAssignments;
        return itemAssignments;
      }

      function updateItemMismatch(itemEl, itemAssignments) {
        const itemQty = parseInt(itemEl.dataset.itemQty || '1', 10);
        const hasMultiple = itemAssignments.length > 1;
        const qtyInputs = Array.from(itemEl.querySelectorAll('.item-group-qty'));
        const errorEl = itemEl.querySelector('.item-group-error');
        let mismatch = false;

        if (hasMultiple) {
          const totalQty = itemAssignments.reduce((sum, entry) => sum + (parseInt(entry.qty || '0', 10) || 0), 0);
          mismatch = totalQty !== itemQty;
        }

        itemEl.classList.toggle('is-mismatch', mismatch);
        itemEl.classList.toggle('is-single-group', !hasMultiple);
        qtyInputs.forEach((input) => {
          input.classList.toggle('is-mismatch', mismatch);
        });
        if (errorEl) {
          errorEl.style.display = mismatch ? 'block' : 'none';
        }
        return mismatch;
      }

      function renderItemGroups(itemEl) {
        const itemKey = itemEl.dataset.itemKey;
        const itemQty = parseInt(itemEl.dataset.itemQty || '1', 10);
        const grid = itemEl.querySelector('.item-group-grid');
        if (!grid || !itemKey) return;

        const itemAssignments = normalizeAssignments(itemKey, itemQty);
        grid.innerHTML = '';

        itemAssignments.forEach((entry, idx) => {
          const card = document.createElement('div');
          card.className = 'item-group-card';

          const groupLabelEl = document.createElement('label');
          groupLabelEl.textContent = 'Group';
          card.appendChild(groupLabelEl);

          const groupRow = document.createElement('div');
          groupRow.className = 'item-group-select-row';

          const select = document.createElement('select');
          select.appendChild(buildGroupOptions(entry.groupIndex));
          select.addEventListener('change', () => {
            entry.groupIndex = parseInt(select.value || '0', 10);
            saveAssignments();
            renderItems();
          });
          groupRow.appendChild(select);

          if (idx === itemAssignments.length - 1) {
            const addBtn = document.createElement('button');
            addBtn.type = 'button';
            addBtn.className = 'btn-outline btn-icon';
            addBtn.textContent = '+';
            addBtn.title = 'Add group';
            addBtn.addEventListener('click', () => {
              itemAssignments.push({ groupIndex: 0, qty: 1 });
              assignments[itemKey] = itemAssignments;
              saveAssignments();
              renderItems();
            });
            groupRow.appendChild(addBtn);
          }

          card.appendChild(groupRow);

          const qtyWrap = document.createElement('div');
          qtyWrap.className = 'item-group-qty-row';
          const qtyLabel = document.createElement('label');
          qtyLabel.textContent = 'Qty';
          const qtyInput = document.createElement('input');
          qtyInput.type = 'number';
          qtyInput.min = '0';
          qtyInput.value = entry.qty;
          qtyInput.className = 'item-group-qty';
          qtyInput.addEventListener('input', () => {
            entry.qty = parseInt(qtyInput.value || '0', 10);
            saveAssignments();
            updateItemMismatch(itemEl, itemAssignments);
          });
          qtyWrap.appendChild(qtyLabel);
          qtyWrap.appendChild(qtyInput);
          card.appendChild(qtyWrap);

          if (itemAssignments.length > 1) {
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'btn-outline';
            removeBtn.textContent = 'Remove';
            removeBtn.addEventListener('click', () => {
              itemAssignments.splice(idx, 1);
              if (!itemAssignments.length) {
                itemAssignments.push({ groupIndex: 0, qty: itemQty });
              }
              assignments[itemKey] = itemAssignments;
              saveAssignments();
              renderItems();
            });
            card.appendChild(removeBtn);
          }

          grid.appendChild(card);
        });

        updateItemMismatch(itemEl, itemAssignments);
      }

      function renderItems() {
        itemEls.forEach((itemEl) => renderItemGroups(itemEl));
        saveAssignments();
      }

      function updateShippingState() {
        const hasAddress = addressFields.every((field) => field.value.trim() !== '');
        shippingInputs.forEach((input) => {
          input.disabled = !hasAddress;
        });
        if (shippingNote) {
          shippingNote.style.display = hasAddress ? 'none' : 'block';
        }
        updateSummaryTotals();
      }

      function updateSummaryTotals() {
        const selected = shippingInputs.find((input) => input.checked && !input.disabled);
        const shippingCost = selected ? parseFloat(selected.dataset.cost || '0') : 0;
        if (summaryShipping) {
          summaryShipping.textContent = `$${shippingCost.toFixed(2)}`;
        }
        if (summaryTotal) {
          summaryTotal.textContent = `$${(subtotalValue + shippingCost).toFixed(2)}`;
        }
      }

      if (addGroupButton) {
        addGroupButton.addEventListener('click', () => {
          if (!isSignedIn) {
            showMessage('Sign in to use accounting codes.');
            return;
          }
          groups.push({ location: '', code1: '', code2: '' });
          saveGroups();
          renderGroups();
          renderItems();
        });
      }

      hydrateAccounting();

      addressFields.forEach((field) => {
        field.addEventListener('input', updateShippingState);
      });
      shippingInputs.forEach((input) => {
        input.addEventListener('change', updateSummaryTotals);
      });
      updateShippingState();

      if (checkoutButton) {
        checkoutButton.addEventListener('click', (event) => {
          const hasMismatch = itemEls.some((itemEl) => itemEl.classList.contains('is-mismatch'));
          if (hasMismatch) {
            event.preventDefault();
            showMessage('Group quantities must match item quantities before checkout.');
          } else {
            clearMessage();
          }
        });
      }
    })();
  </script>
</body>
</html>
