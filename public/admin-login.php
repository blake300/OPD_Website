<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    opd_require_csrf();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($email === '' || $password === '') {
        $error = 'Email and password are required.';
    } else {
        $user = opd_login($email, $password);
        if ($user) {
            header('Location: /admin.php');
            exit;
        }
        $error = 'Invalid credentials or inactive user.';
    }
}

$csrf = opd_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>OPD Admin Login</title>
  <link rel="stylesheet" href="/assets/css/login.css" />
</head>
<body>
  <main class="login">
    <section class="login-card">
      <div class="login-brand">
        <span class="brand-badge">OPD</span>
        <div>
          <div class="brand-title">Admin Access</div>
          <div class="brand-sub">Oil Patch Depot</div>
        </div>
      </div>
      <h1>Sign in</h1>
      <p>Use your admin credentials to continue.</p>
      <form method="POST" class="login-form">
        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES); ?>" />
        <label for="email">Email</label>
        <input id="email" name="email" type="email" autocomplete="username" required />
        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required />
        <?php if ($error): ?>
          <div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES); ?></div>
        <?php endif; ?>
        <button type="submit" class="primary-btn">Sign in</button>
      </form>
    </section>
  </main>
</body>
</html>
