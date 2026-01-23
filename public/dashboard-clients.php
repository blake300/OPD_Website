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
        site_simple_create('clients', $user['id'], [
            'name' => $_POST['name'] ?? '',
            'email' => $_POST['email'] ?? '',
            'phone' => $_POST['phone'] ?? '',
            'status' => $_POST['status'] ?? 'active',
            'notes' => $_POST['notes'] ?? ''
        ]);
        $message = 'Client added.';
    }
    if ($action === 'delete') {
        site_simple_delete('clients', $_POST['id'] ?? '');
        $message = 'Client removed.';
    }
}

$clients = site_simple_list('clients', $user['id']);
$csrf = site_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Clients - Oil Patch Depot</title>
  <link rel="stylesheet" href="/assets/css/site.css" />
</head>
<body>
  <?php require __DIR__ . '/partials/site-header.php'; ?>

  <main class="page dashboard">
    <div class="dashboard-layout">
      <?php require __DIR__ . '/partials/dashboard-nav.php'; ?>

      <div class="dashboard-content">
        <section class="panel">
          <h2>Clients</h2>
          <?php if ($message): ?>
            <div class="notice"><?php echo htmlspecialchars($message, ENT_QUOTES); ?></div>
          <?php endif; ?>
          <form method="POST" class="form-grid cols-2">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES); ?>" />
            <input type="hidden" name="action" value="create" />
            <div>
              <label for="name">Client name</label>
              <input id="name" name="name" required />
            </div>
            <div>
              <label for="email">Email</label>
              <input id="email" name="email" type="email" />
            </div>
            <div>
              <label for="phone">Phone</label>
              <input id="phone" name="phone" />
            </div>
            <div>
              <label for="status">Status</label>
              <input id="status" name="status" value="active" />
            </div>
            <div style="grid-column: 1 / -1;">
              <label for="notes">Notes</label>
              <textarea id="notes" name="notes"></textarea>
            </div>
            <button class="btn" type="submit">Add client</button>
          </form>
        </section>

        <section class="panel">
          <h2>Client list</h2>
          <?php if (!$clients): ?>
            <div class="notice">No clients saved.</div>
          <?php else: ?>
            <table class="table">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Email</th>
                  <th>Status</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($clients as $client): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($client['name'] ?? '', ENT_QUOTES); ?></td>
                    <td><?php echo htmlspecialchars($client['email'] ?? '', ENT_QUOTES); ?></td>
                    <td><?php echo htmlspecialchars($client['status'] ?? '', ENT_QUOTES); ?></td>
                    <td>
                      <form method="POST">
                        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES); ?>" />
                        <input type="hidden" name="action" value="delete" />
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($client['id'], ENT_QUOTES); ?>" />
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
