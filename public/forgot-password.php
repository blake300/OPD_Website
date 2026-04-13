<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/site_auth.php';

$message = '';
$messageClass = 'notice';
$submitted = false;
$method = $_POST['method'] ?? $_GET['method'] ?? 'email';
if ($method !== 'sms') {
    $method = 'email';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    site_require_csrf();

    if ($method === 'sms') {
        $phone = trim($_POST['phone'] ?? '');
        $result = site_request_password_reset_sms($phone);
        $message = 'If that cell phone is associated with an account, you will receive a reset link by text shortly. The link expires in 30 minutes.';
        $submitted = true;
        if (!empty($result['error'])) {
            // Surface validation + admin-lock errors; swallow others to prevent enumeration.
            if (strpos($result['error'], 'locked') !== false || strpos($result['error'], '10 digits') !== false) {
                $message = $result['error'];
                $messageClass = 'notice is-error';
                $submitted = false;
            }
        }
    } else {
        $email = trim($_POST['email'] ?? '');
        $result = site_request_password_reset($email);
        $message = 'If that email is associated with an account, you will receive a reset link shortly. The link expires in 30 minutes.';
        $submitted = true;
        if (!empty($result['error'])) {
            if (strpos($result['error'], 'locked') !== false) {
                $message = $result['error'];
                $messageClass = 'notice is-error';
            }
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
  <style>
    .reset-method-tabs { display:flex; gap:8px; margin-bottom:16px; }
    .reset-method-tabs a {
      flex:1; text-align:center; padding:10px 14px; border:1px solid #dee2e6;
      border-radius:6px; text-decoration:none; color:#555; background:#f8f9fa;
      font-size:14px;
    }
    .reset-method-tabs a.is-active { background:#c0392b; color:#fff; border-color:#c0392b; }
  </style>
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
        <div class="reset-method-tabs">
          <a href="/forgot-password.php?method=email" class="<?php echo $method === 'email' ? 'is-active' : ''; ?>">Email</a>
          <a href="/forgot-password.php?method=sms" class="<?php echo $method === 'sms' ? 'is-active' : ''; ?>">Text Message</a>
        </div>
        <?php if ($method === 'sms'): ?>
          <p class="meta">Enter the cell phone number on your account and we'll text you a reset link.</p>
          <form method="POST" class="form-grid">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES); ?>" />
            <input type="hidden" name="method" value="sms" />
            <div>
              <label for="phone">Cell Phone</label>
              <input id="phone" name="phone" type="tel" inputmode="numeric" pattern="[0-9\-\(\)\s\.\+]{10,}" maxlength="20" required placeholder="(555) 123-4567" />
              <small class="meta" style="display:block;margin-top:4px;">Must be a 10-digit US number.</small>
            </div>
            <button class="btn" type="submit">Text Reset Link</button>
          </form>
        <?php else: ?>
          <p class="meta">Enter your email and we'll send you a reset link.</p>
          <form method="POST" class="form-grid">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES); ?>" />
            <input type="hidden" name="method" value="email" />
            <div>
              <label for="email">Email</label>
              <input id="email" name="email" type="email" required />
            </div>
            <button class="btn" type="submit">Send Reset Link</button>
          </form>
        <?php endif; ?>
      <?php endif; ?>
      <p class="meta"><a href="/login.php">Back to sign in</a></p>
    </section>
  </main>

  <?php require __DIR__ . '/partials/site-footer.php'; ?>
</body>
</html>
