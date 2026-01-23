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
        site_simple_create('equipment', $user['id'], [
            'name' => $_POST['name'] ?? '',
            'serial' => $_POST['serial'] ?? '',
            'status' => $_POST['status'] ?? 'active',
            'location' => $_POST['location'] ?? '',
            'notes' => $_POST['notes'] ?? ''
        ]);
        $message = 'Equipment added.';
    }
    if ($action === 'delete') {
        site_simple_delete('equipment', $_POST['id'] ?? '');
        $message = 'Equipment removed.';
    }
}

$equipment = site_simple_list('equipment', $user['id']);
$csrf = site_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Your Equipment - Oil Patch Depot</title>
  <link rel="stylesheet" href="/assets/css/site.css" />
</head>
<body>
  <?php require __DIR__ . '/partials/site-header.php'; ?>

  <main class="page dashboard">
    <div class="dashboard-layout">
      <?php require __DIR__ . '/partials/dashboard-nav.php'; ?>

      <div class="dashboard-content">
        <section class="panel">
          <h2>Your equipment</h2>
          <?php if ($message): ?>
            <div class="notice"><?php echo htmlspecialchars($message, ENT_QUOTES); ?></div>
          <?php endif; ?>
          <form method="POST" class="form-grid cols-2">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES); ?>" />
            <input type="hidden" name="action" value="create" />
            <div>
              <label for="name">Equipment name</label>
              <input id="name" name="name" required />
            </div>
            <div>
              <label for="serial">Serial</label>
              <input id="serial" name="serial" />
            </div>
            <div>
              <label for="status">Status</label>
              <input id="status" name="status" value="active" />
            </div>
            <div>
              <label for="location">Location</label>
              <input id="location" name="location" />
            </div>
            <div style="grid-column: 1 / -1;">
              <label for="notes">Notes</label>
              <textarea id="notes" name="notes"></textarea>
            </div>
            <button class="btn" type="submit">Add equipment</button>
          </form>
        </section>

        <section class="panel">
          <h2>Equipment list</h2>
          <?php if (!$equipment): ?>
            <div class="notice">No equipment saved.</div>
          <?php else: ?>
            <table class="table">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Serial</th>
                  <th>Status</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($equipment as $item): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($item['name'] ?? '', ENT_QUOTES); ?></td>
                    <td><?php echo htmlspecialchars($item['serial'] ?? '', ENT_QUOTES); ?></td>
                    <td><?php echo htmlspecialchars($item['status'] ?? '', ENT_QUOTES); ?></td>
                    <td>
                      <form method="POST">
                        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES); ?>" />
                        <input type="hidden" name="action" value="delete" />
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($item['id'], ENT_QUOTES); ?>" />
                        <button class="btn-outline" type="submit">Remove</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </section>
      </div>
    </div>
  </main>

  <?php require __DIR__ . '/partials/site-footer.php'; ?>
</body>
</html>
