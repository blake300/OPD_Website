<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/store.php';
require_once __DIR__ . '/../src/site_auth.php';

$user = site_require_auth();
$message = '';
$messageIsError = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    site_require_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $isAjax = !empty($_POST['_ajax']);
        if ($name === '') {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Equipment name is required.']);
                exit;
            }
            $message = 'Equipment name is required.';
            $messageIsError = true;
        } else {
            $equipId = site_equipment_create($user['id'], [
                'name' => $name,
                'serial' => $_POST['serial'] ?? '',
                'location' => $_POST['location'] ?? '',
                'notes' => $_POST['notes'] ?? '',
                'contactName' => $_POST['contactName'] ?? '',
                'contactPhone' => $_POST['contactPhone'] ?? '',
                'contactEmail' => $_POST['contactEmail'] ?? '',
                'quantity' => $_POST['quantity'] ?? '1',
                'price' => $_POST['price'] ?? '',
            ]);
            // AJAX create returns JSON so JS can upload queued images
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['equipmentId' => $equipId]);
                exit;
            }
            $message = 'Equipment added. You can now add photos below.';
            // Redirect to avoid resubmission, include new equipment id for photo upload
            header('Location: /dashboard-equipment.php?msg=created&eid=' . urlencode($equipId));
            exit;
        }
    }

    if ($action === 'update') {
        $eqId = $_POST['equipmentId'] ?? '';
        $eq = site_equipment_get($eqId);
        if ($eq && $eq['userId'] === $user['id']) {
            site_equipment_update($eqId, $user['id'], [
                'name' => $_POST['name'] ?? '',
                'serial' => $_POST['serial'] ?? '',
                'location' => $_POST['location'] ?? '',
                'notes' => $_POST['notes'] ?? '',
                'contactName' => $_POST['contactName'] ?? '',
                'contactPhone' => $_POST['contactPhone'] ?? '',
                'contactEmail' => $_POST['contactEmail'] ?? '',
                'quantity' => $_POST['quantity'] ?? '1',
                'price' => $_POST['price'] ?? '',
            ]);
            header('Location: /dashboard-equipment.php?msg=updated&edit=' . urlencode($eqId));
            exit;
        } else {
            $message = 'Cannot edit this equipment.';
            $messageIsError = true;
        }
    }

    if ($action === 'delete') {
        site_equipment_delete($_POST['id'] ?? '', $user['id']);
        $message = 'Equipment removed.';
    }
}

// Handle query-string messages
$msg = $_GET['msg'] ?? '';
if ($msg === 'created') {
    $message = 'Equipment added. You can now add photos below.';
} elseif ($msg === 'updated') {
    $message = 'Equipment updated.';
}

$equipment = site_equipment_list($user['id']);
$myEquipmentText = site_get_my_equipment_text();
$csrf = site_csrf_token();

// If editing, load images for equipment
$editId = $_GET['edit'] ?? ($_GET['eid'] ?? '');
$editItem = null;
$editImages = [];
if ($editId !== '') {
    $editItem = site_equipment_get($editId);
    if ($editItem && $editItem['userId'] === $user['id']) {
        $editImages = site_equipment_images($editId);
    } else {
        $editItem = null;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>My Equipment - Oil Patch Depot</title>
  <link rel="stylesheet" href="/assets/css/site.css" />
  <style>
    .equip-info-text {
      background: #f0f4f8;
      border: 1px solid #d0d7de;
      border-radius: 8px;
      padding: 16px 20px;
      margin-bottom: 20px;
      color: #444;
      line-height: 1.5;
    }
    .equip-status-badge {
      display: inline-block;
      padding: 3px 10px;
      border-radius: 12px;
      font-size: 12px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .equip-status-badge.pending { background: #fff3cd; color: #856404; }
    .equip-status-badge.active { background: #d4edda; color: #155724; }
    .equip-status-badge.declined { background: #f8d7da; color: #721c24; }
    .equip-images-grid {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      margin-top: 12px;
    }
    .equip-image-card {
      position: relative;
      width: 120px;
      border: 2px solid #dee2e6;
      border-radius: 8px;
      overflow: hidden;
    }
    .equip-image-card.is-primary {
      border-color: #28a745;
    }
    .equip-image-card img {
      width: 100%;
      height: 90px;
      object-fit: cover;
      display: block;
    }
    .equip-image-card .equip-image-actions {
      display: flex;
      gap: 4px;
      padding: 4px;
      background: #f8f9fa;
    }
    .equip-image-card .equip-image-actions button {
      flex: 1;
      font-size: 11px;
      padding: 2px 4px;
      cursor: pointer;
      border: 1px solid #ccc;
      border-radius: 4px;
      background: #fff;
    }
    .equip-image-card .equip-image-actions button:hover {
      background: #e9ecef;
    }
    .equip-image-card .primary-label {
      font-size: 10px;
      text-align: center;
      background: #28a745;
      color: #fff;
      padding: 1px 0;
    }
    .equip-upload-area {
      border: 2px dashed #ccc;
      border-radius: 8px;
      padding: 20px;
      text-align: center;
      margin-top: 12px;
      cursor: pointer;
      transition: border-color 0.2s;
    }
    .equip-upload-area:hover {
      border-color: #999;
    }
    .equip-upload-area.drag-over {
      border-color: #28a745;
      background: #f0fff4;
    }
    .equip-card-list {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }
    .equip-card {
      border: 1px solid #dee2e6;
      border-radius: 8px;
      overflow: hidden;
    }
    .equip-card-header {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 12px 16px;
      background: #f8f9fa;
      cursor: pointer;
    }
    .equip-card-header:hover {
      background: #e9ecef;
    }
    .equip-card-thumb {
      width: 50px;
      height: 50px;
      object-fit: cover;
      border-radius: 6px;
      flex-shrink: 0;
    }
    .equip-card-thumb-placeholder {
      width: 50px;
      height: 50px;
      background: #dee2e6;
      border-radius: 6px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 11px;
      color: #888;
      flex-shrink: 0;
    }
    .equip-card-info {
      flex: 1;
      min-width: 0;
    }
    .equip-card-info h3 {
      margin: 0;
      font-size: 15px;
    }
    .equip-card-info .meta {
      font-size: 13px;
      color: #666;
      margin-top: 2px;
    }
    .equip-card-actions {
      display: flex;
      gap: 8px;
      flex-shrink: 0;
    }
    .equip-card-body {
      display: none;
      padding: 16px;
      border-top: 1px solid #dee2e6;
    }
    .equip-card-body.is-open {
      display: block;
    }
    .equip-detail-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 8px 16px;
      font-size: 14px;
    }
    .equip-detail-grid dt {
      font-weight: 600;
      color: #555;
    }
    .equip-detail-grid dd {
      margin: 0;
      color: #333;
    }
  </style>
</head>
<body>
  <?php require __DIR__ . '/partials/site-header.php'; ?>

  <main class="page dashboard">
    <div class="dashboard-layout">
      <?php require __DIR__ . '/partials/dashboard-nav.php'; ?>

      <div class="dashboard-content">
        <?php if ($myEquipmentText !== ''): ?>
          <div class="equip-info-text">
            <?php echo nl2br(htmlspecialchars($myEquipmentText, ENT_QUOTES)); ?>
          </div>
        <?php endif; ?>

        <?php if ($message): ?>
          <div class="notice <?php echo $messageIsError ? 'is-error' : ''; ?>">
            <?php echo htmlspecialchars($message, ENT_QUOTES); ?>
          </div>
        <?php endif; ?>

        <!-- Add / Edit Equipment Form -->
        <section class="panel">
          <h2><?php echo $editItem ? 'Edit Equipment' : 'List Equipment For Sale'; ?></h2>
          <form method="POST" class="form-grid cols-2">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES); ?>" />
            <?php if ($editItem): ?>
              <input type="hidden" name="action" value="update" />
              <input type="hidden" name="equipmentId" value="<?php echo htmlspecialchars($editItem['id'], ENT_QUOTES); ?>" />
            <?php else: ?>
              <input type="hidden" name="action" value="create" />
            <?php endif; ?>
            <div>
              <label for="name">Equipment Name *</label>
              <input id="name" name="name" required value="<?php echo htmlspecialchars($editItem['name'] ?? '', ENT_QUOTES); ?>" />
            </div>
            <div>
              <label for="serial">Serial</label>
              <input id="serial" name="serial" value="<?php echo htmlspecialchars($editItem['serial'] ?? '', ENT_QUOTES); ?>" />
            </div>
            <div>
              <label for="location">Location of Equipment</label>
              <input id="location" name="location" value="<?php echo htmlspecialchars($editItem['location'] ?? '', ENT_QUOTES); ?>" />
            </div>
            <div>
              <label for="quantity">Quantity</label>
              <input id="quantity" name="quantity" type="number" min="1" value="<?php echo htmlspecialchars((string) ($editItem['quantity'] ?? '1'), ENT_QUOTES); ?>" />
            </div>
            <div>
              <label for="price">Price</label>
              <input id="price" name="price" type="number" step="0.01" min="0" placeholder="0.00" value="<?php echo $editItem && $editItem['price'] !== null ? htmlspecialchars(number_format((float) $editItem['price'], 2, '.', ''), ENT_QUOTES) : ''; ?>" />
            </div>
            <div>
              <label for="contactName">Contact Name for Equipment</label>
              <input id="contactName" name="contactName" value="<?php echo htmlspecialchars($editItem['contactName'] ?? '', ENT_QUOTES); ?>" />
            </div>
            <div>
              <label for="contactPhone">Contact Phone for Equipment</label>
              <input id="contactPhone" name="contactPhone" type="tel" value="<?php echo htmlspecialchars($editItem['contactPhone'] ?? '', ENT_QUOTES); ?>" />
            </div>
            <div>
              <label for="contactEmail">Contact Email for Equipment</label>
              <input id="contactEmail" name="contactEmail" type="email" value="<?php echo htmlspecialchars($editItem['contactEmail'] ?? '', ENT_QUOTES); ?>" />
            </div>
            <div style="grid-column: 1 / -1;">
              <label for="notes">Description of Equipment</label>
              <textarea id="notes" name="notes" rows="4"><?php echo htmlspecialchars($editItem['notes'] ?? '', ENT_QUOTES); ?></textarea>
            </div>

            <!-- Photos (below description) -->
            <?php if ($editItem): ?>
            <div style="grid-column: 1 / -1;" id="photos-section">
              <label>Equipment Photos</label>
              <p class="meta" style="margin-top:0;">Upload up to 8 photos. The first image will be the primary photo. Click "Primary" on any image to make it the main photo.</p>
              <div id="equip-images" class="equip-images-grid">
                <?php foreach ($editImages as $img): ?>
                  <div class="equip-image-card <?php echo !empty($img['isPrimary']) ? 'is-primary' : ''; ?>" data-id="<?php echo htmlspecialchars($img['id'], ENT_QUOTES); ?>">
                    <img src="<?php echo htmlspecialchars($img['url'], ENT_QUOTES); ?>" alt="Equipment photo" />
                    <?php if (!empty($img['isPrimary'])): ?>
                      <div class="primary-label">Primary</div>
                    <?php endif; ?>
                    <div class="equip-image-actions">
                      <?php if (empty($img['isPrimary'])): ?>
                        <button type="button" onclick="setPrimaryImage('<?php echo htmlspecialchars($img['id'], ENT_QUOTES); ?>')">Primary</button>
                      <?php endif; ?>
                      <button type="button" onclick="deleteImage('<?php echo htmlspecialchars($img['id'], ENT_QUOTES); ?>')">Remove</button>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
              <div id="equip-upload-error" class="notice is-error" style="display:none;" role="alert"></div>
              <?php if (count($editImages) < 8): ?>
                <div class="equip-upload-area" id="upload-area">
                  <p id="upload-area-text">Click or drag photos here to upload</p>
                  <input type="file" id="photo-input" accept="image/jpeg,image/png,image/webp,image/gif" multiple style="display:none" />
                </div>
              <?php endif; ?>
            </div>
            <?php elseif (!isset($_GET['eid'])): ?>
            <div style="grid-column: 1 / -1;" id="photos-section">
              <label>Equipment Photos</label>
              <p class="meta" style="margin-top:0;">Select up to 8 photos. They will be uploaded when you click "Add Equipment".</p>
              <div id="equip-images" class="equip-images-grid"></div>
              <div id="equip-upload-error" class="notice is-error" style="display:none;" role="alert"></div>
              <div class="equip-upload-area" id="upload-area">
                <p id="upload-area-text">Click or drag photos here to select</p>
                <input type="file" id="photo-input" accept="image/jpeg,image/png,image/webp,image/gif" multiple style="display:none" />
              </div>
            </div>
            <?php endif; ?>

            <div>
              <label>Status</label>
              <input type="text" readonly disabled value="<?php echo htmlspecialchars($editItem['status'] ?? 'Pending Approval', ENT_QUOTES); ?>" />
            </div>
            <div style="display: flex; align-items: flex-end; gap: 8px;">
              <button class="btn" type="submit"><?php echo $editItem ? 'Save Changes' : 'Add Equipment'; ?></button>
              <?php if ($editItem): ?>
                <a class="btn-outline" href="/dashboard-equipment.php">Cancel</a>
              <?php endif; ?>
            </div>
          </form>
        </section>

        <!-- New equipment just created - show photo upload link -->
        <?php if (!$editItem && isset($_GET['eid'])): ?>
          <?php
          $newEid = $_GET['eid'];
          $newEquip = site_equipment_get($newEid);
          if ($newEquip && $newEquip['userId'] === $user['id']):
          ?>
          <div class="notice">
            Equipment created! <a href="/dashboard-equipment.php?edit=<?php echo urlencode($newEid); ?>#photos-section">Click here to add photos</a>.
          </div>
          <?php endif; ?>
        <?php endif; ?>

        <!-- Equipment List -->
        <section class="panel">
          <h2>My Equipment</h2>
          <?php if (!$equipment): ?>
            <div class="notice">No equipment listed yet.</div>
          <?php else: ?>
            <div class="equip-card-list">
              <?php foreach ($equipment as $item): ?>
                <?php
                  $statusClass = 'pending';
                  $statusText = $item['status'] ?? 'Pending Approval';
                  if (strtolower($statusText) === 'active') $statusClass = 'active';
                  if (strtolower($statusText) === 'declined') $statusClass = 'declined';
                ?>
                <div class="equip-card">
                  <div class="equip-card-header" onclick="toggleEquipCard(this)">
                    <?php if (!empty($item['primaryImageUrl'])): ?>
                      <img class="equip-card-thumb" src="<?php echo htmlspecialchars($item['primaryImageUrl'], ENT_QUOTES); ?>" alt="" />
                    <?php else: ?>
                      <div class="equip-card-thumb-placeholder">No img</div>
                    <?php endif; ?>
                    <div class="equip-card-info">
                      <h3><?php echo htmlspecialchars($item['name'] ?? '', ENT_QUOTES); ?></h3>
                      <div class="meta">
                        <?php if ($item['price'] !== null && $item['price'] !== ''): ?>
                          $<?php echo number_format((float) $item['price'], 2); ?> &middot;
                        <?php endif; ?>
                        Qty: <?php echo (int) ($item['quantity'] ?? 1); ?>
                        &middot;
                        <span class="equip-status-badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($statusText, ENT_QUOTES); ?></span>
                      </div>
                    </div>
                    <div class="equip-card-actions" onclick="event.stopPropagation()">
                      <a class="btn-outline" href="/dashboard-equipment.php?edit=<?php echo urlencode($item['id']); ?>">Edit</a>
                      <form method="POST" style="display:inline" onsubmit="return confirm('Remove this equipment?')">
                        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES); ?>" />
                        <input type="hidden" name="action" value="delete" />
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($item['id'], ENT_QUOTES); ?>" />
                        <button class="btn-outline" type="submit">Remove</button>
                      </form>
                    </div>
                  </div>
                  <div class="equip-card-body">
                    <dl class="equip-detail-grid">
                      <dt>Serial</dt>
                      <dd><?php echo htmlspecialchars($item['serial'] ?? '-', ENT_QUOTES); ?></dd>
                      <dt>Location of Equipment</dt>
                      <dd><?php echo htmlspecialchars($item['location'] ?? '-', ENT_QUOTES); ?></dd>
                      <dt>Description of Equipment</dt>
                      <dd><?php echo nl2br(htmlspecialchars($item['notes'] ?? '-', ENT_QUOTES)); ?></dd>
                      <dt>Contact Name</dt>
                      <dd><?php echo htmlspecialchars($item['contactName'] ?? '-', ENT_QUOTES); ?></dd>
                      <dt>Contact Phone</dt>
                      <dd><?php echo htmlspecialchars($item['contactPhone'] ?? '-', ENT_QUOTES); ?></dd>
                      <dt>Contact Email</dt>
                      <dd><?php echo htmlspecialchars($item['contactEmail'] ?? '-', ENT_QUOTES); ?></dd>
                    </dl>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>
      </div>
    </div>
  </main>

  <?php require __DIR__ . '/partials/site-footer.php'; ?>

  <script>
  function toggleEquipCard(header) {
    var body = header.nextElementSibling;
    if (body) {
      body.classList.toggle('is-open');
    }
  }

  (function() {
    var csrf = <?php echo json_encode($csrf); ?>;
    var isEditMode = <?php echo $editItem ? 'true' : 'false'; ?>;
    var equipmentId = <?php echo $editItem ? json_encode($editItem['id']) : 'null'; ?>;
    var uploadArea = document.getElementById('upload-area');
    var photoInput = document.getElementById('photo-input');
    var imagesContainer = document.getElementById('equip-images');
    var pendingFiles = []; // Queue for create mode

    // Wire up upload area (both create and edit modes)
    if (uploadArea && photoInput) {
      uploadArea.addEventListener('click', function() {
        photoInput.click();
      });
      uploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        uploadArea.classList.add('drag-over');
      });
      uploadArea.addEventListener('dragleave', function() {
        uploadArea.classList.remove('drag-over');
      });
      uploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        uploadArea.classList.remove('drag-over');
        if (e.dataTransfer.files.length) {
          handleFiles(e.dataTransfer.files);
        }
      });
      photoInput.addEventListener('change', function() {
        if (photoInput.files.length) {
          handleFiles(photoInput.files);
          photoInput.value = '';
        }
      });
    }

    function showUploadError(msg) {
      var errEl = document.getElementById('equip-upload-error');
      if (errEl) {
        errEl.textContent = msg;
        errEl.style.display = '';
        setTimeout(function() { errEl.style.display = 'none'; }, 6000);
      }
    }

    function setUploadLoading(isLoading) {
      if (!uploadArea) return;
      if (isLoading) {
        uploadArea.style.opacity = '0.6';
        uploadArea.style.pointerEvents = 'none';
      } else {
        uploadArea.style.opacity = '';
        uploadArea.style.pointerEvents = '';
      }
      var text = document.getElementById('upload-area-text');
      if (text) {
        text.textContent = isLoading ? 'Uploading...' : (isEditMode ? 'Click or drag photos here to upload' : 'Click or drag photos here to select');
      }
    }

    function handleFiles(files) {
      if (isEditMode) {
        // Edit mode: upload immediately to server
        uploadFilesToServer(files, equipmentId);
      } else {
        // Create mode: queue files and show previews
        queueFiles(files);
      }
    }

    // ── Create mode: queue files with preview ──
    function queueFiles(files) {
      Array.from(files).forEach(function(file) {
        if (!file.type.startsWith('image/')) return;
        if (pendingFiles.length >= 8) {
          showUploadError('Maximum 8 images per equipment listing.');
          return;
        }
        pendingFiles.push(file);
        showPreview(file);
      });
    }

    function showPreview(file) {
      if (!imagesContainer) return;
      var card = document.createElement('div');
      card.className = 'equip-image-card';
      var img = document.createElement('img');
      img.alt = 'Preview';
      var reader = new FileReader();
      reader.onload = function(e) { img.src = e.target.result; };
      reader.readAsDataURL(file);
      card.appendChild(img);
      var actions = document.createElement('div');
      actions.className = 'equip-image-actions';
      var removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.textContent = 'Remove';
      removeBtn.addEventListener('click', function() {
        var idx = pendingFiles.indexOf(file);
        if (idx !== -1) pendingFiles.splice(idx, 1);
        card.remove();
      });
      actions.appendChild(removeBtn);
      card.appendChild(actions);
      imagesContainer.appendChild(card);
    }

    // ── Create mode: intercept form submit ──
    if (!isEditMode) {
      var form = document.querySelector('form.form-grid');
      if (form) {
        form.addEventListener('submit', function(e) {
          if (pendingFiles.length === 0) return; // No queued files, normal submit
          e.preventDefault();
          var submitBtn = form.querySelector('button[type="submit"]');
          if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Saving...'; }

          var formData = new FormData(form);
          formData.append('_ajax', '1');

          fetch('/dashboard-equipment.php', {
            method: 'POST',
            body: formData
          })
          .then(function(r) { return r.json(); })
          .then(function(data) {
            if (data.error) {
              showUploadError(data.error);
              if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Add Equipment'; }
              return;
            }
            // Equipment created, now upload queued images
            return uploadQueuedImages(data.equipmentId);
          })
          .catch(function() {
            showUploadError('Failed to create equipment.');
            if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Add Equipment'; }
          });
        });
      }
    }

    function uploadQueuedImages(newEquipId) {
      var uploads = pendingFiles.map(function(file) {
        var fd = new FormData();
        fd.append('image', file);
        fd.append('equipmentId', newEquipId);
        fd.append('_csrf', csrf);
        return fetch('/api/equipment_upload.php', { method: 'POST', body: fd })
          .then(function(r) { return r.json(); });
      });
      return Promise.all(uploads).then(function() {
        window.location.href = '/dashboard-equipment.php?msg=created&eid=' + encodeURIComponent(newEquipId);
      }).catch(function() {
        // Equipment was created, images may have partially uploaded
        window.location.href = '/dashboard-equipment.php?msg=created&eid=' + encodeURIComponent(newEquipId);
      });
    }

    // ── Edit mode: upload immediately ──
    function uploadFilesToServer(files, eqId) {
      Array.from(files).forEach(function(file) {
        if (!file.type.startsWith('image/')) return;
        var formData = new FormData();
        formData.append('image', file);
        formData.append('equipmentId', eqId);
        formData.append('_csrf', csrf);

        setUploadLoading(true);
        fetch('/api/equipment_upload.php', {
          method: 'POST',
          body: formData
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
          if (data.error) {
            setUploadLoading(false);
            showUploadError(data.error);
            return;
          }
          window.location.href = '/dashboard-equipment.php?edit=' + encodeURIComponent(eqId) + '#photos-section';
        })
        .catch(function() {
          setUploadLoading(false);
          showUploadError('Upload failed. Please try again.');
        });
      });
    }

    // ── Edit mode: image management ──
    window.setPrimaryImage = function(imageId) {
      if (!equipmentId) return;
      fetch('/api/equipment_images.php', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
        body: JSON.stringify({ action: 'setPrimary', equipmentId: equipmentId, imageId: imageId })
      })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.ok) {
          window.location.href = '/dashboard-equipment.php?edit=' + encodeURIComponent(equipmentId) + '#photos-section';
        }
      });
    };

    window.deleteImage = function(imageId) {
      if (!equipmentId) return;
      if (!confirm('Remove this photo?')) return;
      fetch('/api/equipment_images.php?equipmentId=' + encodeURIComponent(equipmentId) + '&imageId=' + encodeURIComponent(imageId), {
        method: 'DELETE',
        headers: { 'X-CSRF-Token': csrf }
      })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.ok) {
          window.location.href = '/dashboard-equipment.php?edit=' + encodeURIComponent(equipmentId) + '#photos-section';
        }
      });
    };
  })();
  </script>
</body>
</html>
