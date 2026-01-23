<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/site_auth.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    site_require_csrf();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($email === '' || $password === '') {
        $error = 'Email and password are required.';
    } else {
        $user = site_login($email, $password);
        if ($user) {
            header('Location: /dashboard.php');
            exit;
        }
        $error = 'Invalid credentials.';
    }
}

$csrf = site_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Sign In - Oil Patch Depot</title>
  <link rel="stylesheet" href="/assets/css/site.css" />
</head>
<body>
  <?php require __DIR__ . '/partials/site-header.php'; ?>

  <main class="page">
    <section class="panel" style="max-width:520px;">
      <h2>Sign in</h2>
      <p class="meta">Access orders, favorites, and account details.</p>
      <form method="POST" class="form-grid">
        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES); ?>" />
        <div>
          <label for="email">Email</label>
          <input id="email" name="email" type="email" required />
        </div>
        <div>
          <label for="password">Password</label>
          <input id="password" name="password" type="password" required />
        </div>
        <?php if ($error): ?>
          <div class="notice"><?php echo htmlspecialchars($error, ENT_QUOTES); ?></div>
        <?php endif; ?>
        <button class="btn" type="submit">Sign in</button>
      </form>
      <p class="meta">Need an account? <a href="/register.php">Create one</a>.</p>
    </section>
  </main>

  <?php require __DIR__ . '/partials/site-footer.php'; ?>
</body>
</html>
