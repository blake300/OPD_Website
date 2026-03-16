<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/site_auth.php';
require_once __DIR__ . '/../src/store.php';
require_once __DIR__ . '/../src/seo.php';

$loginError = '';
$registerError = '';
$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    site_require_csrf();

    // Get redirect URL from query string (passed through hidden field)
    $redirectUrl = trim($_POST['redirect'] ?? $_GET['redirect'] ?? '');
    // Validate redirect URL - only allow relative paths starting with /
    if ($redirectUrl !== '' && (strpos($redirectUrl, '/') !== 0 || strpos($redirectUrl, '//') === 0)) {
        $redirectUrl = '';
    }
    $defaultRedirect = $redirectUrl !== '' ? $redirectUrl : '/dashboard.php';

    if ($action === 'login') {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $rememberMe = !empty($_POST['remember_me']);

        if ($email === '' || $password === '') {
            $loginError = 'Email and password are required.';
        } else {
            $user = site_login($email, $password);
            if ($user) {
                if ($rememberMe) {
                    site_remember_me();
                }
                header('Location: ' . $defaultRedirect);
                exit;
            }
            $loginError = 'Invalid credentials.';
        }
    } elseif ($action === 'register') {
        $email = trim($_POST['register_email'] ?? '');
        $password = $_POST['register_password'] ?? '';
        $rememberMe = !empty($_POST['register_remember_me']);

        if ($email === '' || $password === '') {
            $registerError = 'Email and password are required.';
        } else {
            // Use email as name for simplified registration
            $result = site_register($email, $email, $password);
            if (!empty($result['error'])) {
                $registerError = $result['error'];
            } else {
                site_login($email, $password);
                if ($rememberMe) {
                    site_remember_me();
                }
                // Link any pending vendor/client invitations for this email
                $linked = site_link_pending_invitations($result['id'], $email);
                $regRedirect = $defaultRedirect;
                if ($redirectUrl === '' && $linked['linkedClients'] > 0) {
                    $regRedirect = '/dashboard-clients.php';
                } elseif ($redirectUrl === '' && $linked['linkedVendors'] > 0) {
                    $regRedirect = '/dashboard-vendors.php';
                }
                header('Location: ' . $regRedirect);
                exit;
            }
        }
    }
}

$csrf = site_csrf_token();
$redirect = $_GET['redirect'] ?? '';
$isCheckoutRedirect = strpos($redirect, 'checkout') !== false;
$isEquipmentRedirect = !empty($_GET['equip']);
$serviceCheckoutFlag = !empty($_GET['service']);
$cartHasService = site_cart_has_any_service_items(site_cart_items());
$showServiceNotice = $serviceCheckoutFlag || ($isCheckoutRedirect && $cartHasService);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Sign In - <?php echo htmlspecialchars(opd_site_name(), ENT_QUOTES); ?></title>
  <link rel="stylesheet" href="/assets/css/site.css?v=20260315c" />
  <?php opd_seo_meta(['title' => 'Sign In - ' . opd_site_name(), 'canonical' => '/login.php', 'description' => 'Sign in to your ' . opd_site_name() . ' account to manage orders, favorites, and purchasing.']); ?>
  <style>
    .guest-checkout-banner {
      background: #f8f9fa;
      border: 1px solid #dee2e6;
      border-radius: 8px;
      padding: 20px 24px;
      margin-bottom: 24px;
      text-align: center;
    }
    .guest-checkout-banner p {
      margin: 0 0 12px 0;
      color: #666;
      font-size: 15px;
    }
    .guest-checkout-banner .btn {
      min-width: 200px;
    }
  </style>
</head>
<body>
  <?php require __DIR__ . '/partials/site-header.php'; ?>

  <main class="page">
    <section class="panel">
      <?php if ($isEquipmentRedirect): ?>
        <div class="notice notice-info" style="margin-bottom: 20px; text-align: center; font-size: 15px; padding: 16px;">
          Must sign in to list equipment
        </div>
      <?php elseif ($showServiceNotice): ?>
        <div class="notice notice-info" style="margin-bottom: 20px; text-align: center; font-size: 15px; padding: 16px;">
          Must be registered, with an active payment method, to book services
        </div>
      <?php elseif ($isCheckoutRedirect): ?>
        <div class="guest-checkout-banner">
          <p>Don't want to create an account? No problem!</p>
          <a class="btn btn-primary" href="/checkout.php?guest=1">Continue as Guest</a>
        </div>
      <?php endif; ?>
      <div class="grid cols-2 auth-grid">
        <div class="card">
          <h2>Sign in</h2>
          <p class="meta">Access orders, favorites, and account details.</p>
          <form method="POST" class="form-grid">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES); ?>" />
            <input type="hidden" name="action" value="login" />
            <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect, ENT_QUOTES); ?>" />
            <div>
              <label for="email">Email</label>
              <input id="email" name="email" type="email" required />
            </div>
            <div>
              <label for="password">Password</label>
              <input id="password" name="password" type="password" required />
            </div>
            <div>
              <label class="checkbox-label">
                <input type="checkbox" name="remember_me" value="1" />
                <span>Keep me signed in</span>
              </label>
            </div>
            <?php if ($loginError): ?>
              <div class="notice is-error"><?php echo htmlspecialchars($loginError, ENT_QUOTES); ?></div>
            <?php endif; ?>
            <button class="btn" type="submit">Sign in</button>
          </form>
        </div>

        <div class="card">
          <h2>Create an Account</h2>
          <p class="meta">Set up your purchasing profile.</p>
          <form method="POST" class="form-grid">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES); ?>" />
            <input type="hidden" name="action" value="register" />
            <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect, ENT_QUOTES); ?>" />
            <div>
              <label for="register_email">Email</label>
              <input id="register_email" name="register_email" type="email" required />
            </div>
            <div>
              <label for="register_password">Password</label>
              <input id="register_password" name="register_password" type="password" required />
            </div>
            <div>
              <label class="checkbox-label">
                <input type="checkbox" name="register_remember_me" value="1" />
                <span>Keep me signed in</span>
              </label>
            </div>
            <?php if ($registerError): ?>
              <div class="notice is-error"><?php echo htmlspecialchars($registerError, ENT_QUOTES); ?></div>
            <?php endif; ?>
            <button class="btn" type="submit">Create account</button>
          </form>
        </div>
      </div>
    </section>
  </main>

  <?php require __DIR__ . '/partials/site-footer.php'; ?>
  <script>
    document.querySelectorAll('form').forEach(function(form) {
      form.addEventListener('submit', function() {
        var btn = form.querySelector('button[type="submit"]');
        if (btn) {
          btn.disabled = true;
          btn.textContent = 'Please wait…';
        }
      });
    });
  </script>
</body>
</html>
