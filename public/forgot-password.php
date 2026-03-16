<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/site_auth.php';

$message = '';
$messageClass = 'notice';
$submitted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    site_require_csrf();
    $email = trim($_POST['email'] ?? '');
    $result = site_request_password_reset($email);
    // Always show the same message to prevent email enumeration
    $message = 'If that email is associated with an account, you will receive a reset link shortly. The link expires in 30 minutes.';
    $submitted = true;
    if (!empty($result['error'])) {
        // Only surface admin-lock errors — others are swallowed to prevent enumeration
        if (strpos($result['error'], 'locked') !== false) {
            $message = $result['error'];
            $messageClass = 'notice is-error';
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
  <title>Forgot Password - <?php echo htmlspecialchars(opd_site_name(), ENT_QUOTES); ?></title>
  <link rel="stylesheet" href="/assets/css/site.css?v=20260315c" />
</head>
<body>
  <?php require __DIR__ . '/partials/site-header.php'; ?>

  <main class="page">
    <section class="panel" style="max-width:520px;">
      <h2>Forgot your password?</h2>
      <?php if ($message): ?>
        <div class="<?php echo htmlspecialchars($messageClass, ENT_QUOTES); ?>"><?php echo htmlspecialchars($message, ENT_QUOTES); ?></div>
      <?php endif; ?>
      <?php if (!$submitted): ?>
        <p class="meta">Enter your email and we'll send you a reset link.</p>
        <form method="POST" class="form-grid">
          <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES); ?>" />
          <div>
            <label for="email">Email</label>
            <input id="email" name="email" type="email" required />
          </div>
          <button class="btn" type="submit">Send Reset Link</button>
        </form>
      <?php endif; ?>
      <p class="meta"><a href="/login.php">Back to sign in</a></p>
    </section>
  </main>

  <?php require __DIR__ . '/partials/site-footer.php'; ?>
</body>
</html>
