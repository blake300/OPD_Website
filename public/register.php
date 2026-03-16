<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/site_auth.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    site_require_csrf();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($name === '' || $email === '' || $password === '') {
        $error = 'All fields are required.';
    } else {
        $result = site_register($name, $email, $password);
        if (!empty($result['error'])) {
            $error = $result['error'];
        } else {
            site_login($email, $password);
            header('Location: /dashboard.php');
            exit;
        }
    }
}

$csrf = site_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Create Account - <?php echo htmlspecialchars(opd_site_name(), ENT_QUOTES); ?></title>
  <link rel="stylesheet" href="/assets/css/site.css" />
</head>
<body>
  <?php require __DIR__ . '/partials/site-header.php'; ?>

  <main class="page">
    <section class="panel" style="max-width:520px;">
      <h2>Create account</h2>
      <p class="meta">Set up your purchasing profile.</p>
      <form method="POST" class="form-grid">
        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES); ?>" />
        <div>
          <label for="name">Full name</label>
          <input id="name" name="name" required />
        </div>
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
        <button class="btn" type="submit">Create account</button>
      </form>
      <p class="meta">Already have an account? <a href="/login.php">Sign in</a>.</p>
    </section>
  </main>

  <?php require __DIR__ . '/partials/site-footer.php'; ?>
</body>
</html>
