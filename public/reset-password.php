<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/site_auth.php';

$rawToken = trim($_GET['token'] ?? '');
$message = '';
$messageClass = 'notice';
$tokenValid = false;
$success = false;

if ($rawToken !== '') {
    $check = site_validate_reset_token($rawToken);
    $tokenValid = $check['valid'];
    if (!$tokenValid) {
        $message = $check['error'] ?? 'Invalid or expired reset link.';
        $messageClass = 'notice is-error';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    site_require_csrf();
    $rawToken = trim($_POST['token'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';

    if ($password !== $passwordConfirm) {
        $message = 'Passwords do not match.';
        $messageClass = 'notice is-error';
        $tokenValid = site_validate_reset_token($rawToken)['valid'];
    } else {
        $result = site_apply_password_reset($rawToken, $password);
        if (!empty($result['error'])) {
            $message = $result['error'];
            $messageClass = 'notice is-error';
            $tokenValid = site_validate_reset_token($rawToken)['valid'];
        } else {
            $success = true;
            $message = 'Your password has been changed. You can now sign in.';
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
  <title>Reset Password - <?php echo htmlspecialchars(opd_site_name(), ENT_QUOTES); ?></title>
  <link rel="stylesheet" href="/assets/css/site.css?v=20260315c" />
</head>
<body>
  <?php require __DIR__ . '/partials/site-header.php'; ?>

  <main class="page">
    <section class="panel" style="max-width:520px;">
      <h2>Reset your password</h2>
      <?php if ($message): ?>
        <div class="<?php echo htmlspecialchars($messageClass, ENT_QUOTES); ?>"><?php echo htmlspecialchars($message, ENT_QUOTES); ?></div>
      <?php endif; ?>
      <?php if ($success): ?>
        <p class="meta"><a href="/login.php">Sign in</a></p>
      <?php elseif ($tokenValid): ?>
        <form method="POST" class="form-grid">
          <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES); ?>" />
          <input type="hidden" name="token" value="<?php echo htmlspecialchars($rawToken, ENT_QUOTES); ?>" />
          <div>
            <label for="password">New password</label>
            <input id="password" name="password" type="password" required />
          </div>
          <div>
            <label for="password_confirm">Confirm new password</label>
            <input id="password_confirm" name="password_confirm" type="password" required />
          </div>
          <button class="btn" type="submit">Set New Password</button>
        </form>
      <?php else: ?>
        <p class="meta"><a href="/forgot-password.php">Request a new reset link</a></p>
      <?php endif; ?>
    </section>
  </main>

  <?php require __DIR__ . '/partials/site-footer.php'; ?>
</body>
</html>
