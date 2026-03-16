# Security Updates - High Priority Fixes

This document outlines the high priority security fixes that have been implemented.

## Files Created

### 1. `src/validation.php`
Input validation and sanitization functions:
- `opd_validate_email()` - Validates email format
- `opd_validate_password()` - Enforces strong password requirements (min 12 chars, uppercase, lowercase, number, special char)
- `opd_sanitize_name()` - Sanitizes name input
- `opd_validate_name()` - Validates name length

### 2. `src/rate_limit.php`
Rate limiting to prevent brute force attacks:
- `opd_check_rate_limit()` - Checks if request should be blocked
- `opd_record_failed_attempt()` - Records failed login attempts
- `opd_reset_rate_limit()` - Clears rate limit on successful login
- Automatically creates `rate_limit` table in database
- Locks account for 15 minutes after 5 failed attempts within 15 minutes

### 3. `src/security_init.php`
Global security enforcement:
- `opd_enforce_https()` - Redirects HTTP to HTTPS (except localhost)
- `opd_set_security_headers()` - Sets security headers:
  - X-Frame-Options: DENY (clickjacking prevention)
  - X-XSS-Protection: enabled
  - X-Content-Type-Options: nosniff
  - Content-Security-Policy
  - Strict-Transport-Security (HSTS)
  - Referrer-Policy
- Sets session timeout to 1 hour

## Files Modified

### 1. `src/site_auth.php` (Customer Authentication)

**Changes to `site_login()`:**
- ✅ Fixed timing attack vulnerability (always verifies password even if user doesn't exist)
- ✅ Added rate limiting (5 attempts per 15 minutes)
- ✅ Records failed attempts and resets on success

**Changes to `site_register()`:**
- ✅ Added email validation
- ✅ Added name validation and sanitization
- ✅ Added password strength validation (12+ chars with complexity requirements)
- ✅ Fixed user enumeration (generic error message instead of "Email already registered")

### 2. `src/auth.php` (Admin Authentication)

**Changes to `opd_login()`:**
- ✅ Fixed timing attack vulnerability
- ✅ Added rate limiting (separate from customer login)
- ✅ Added explicit role check (admin/manager only)
- ✅ Records failed attempts and resets on success

## How to Use

### 1. Include Security Init in Public Pages

Add this line at the top of all public PHP pages (after `<?php` declaration):

```php
<?php
require_once __DIR__ . '/../src/security_init.php';
```

Example for `public/login.php`:
```php
<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/security_init.php';
require_once __DIR__ . '/../src/site_auth.php';
// ... rest of your code
```

### 2. Update Login Forms to Show Rate Limit Messages

The login functions now return `null` on failure (including rate limiting). To show proper error messages, you need to check the rate limit separately in your login page:

```php
<?php
// In your login form handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    // Check rate limit before attempting login
    $rateLimitCheck = opd_check_rate_limit($email, 'customer_login');
    if (!$rateLimitCheck['allowed']) {
        $error = $rateLimitCheck['message'];
    } else {
        $user = site_login($email, $password);
        if ($user) {
            header('Location: /dashboard.php');
            exit;
        } else {
            $error = 'Invalid email or password';
        }
    }
}
?>
```

### 3. Password Requirements for Registration Forms

Update your registration forms to show password requirements:

```html
<label for="password">Password</label>
<input type="password" name="password" id="password" required>
<small>
    Password must be at least 12 characters and include:
    uppercase letter, lowercase letter, number, and special character
</small>
```

### 4. Database Migration

The `rate_limit` table is created automatically on first use, but you can manually create it if needed:

```sql
CREATE TABLE IF NOT EXISTS rate_limit (
    id VARCHAR(255) PRIMARY KEY,
    identifier VARCHAR(255) NOT NULL,
    type VARCHAR(50) NOT NULL,
    attempts INT NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    last_attempt DATETIME NOT NULL,
    INDEX idx_identifier_type (identifier, type),
    INDEX idx_locked_until (locked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## Security Improvements Summary

| Issue | Status | Impact |
|-------|--------|--------|
| Timing attacks in login | ✅ Fixed | Prevents user enumeration |
| Brute force attacks | ✅ Fixed | Rate limiting (5 attempts/15min) |
| Weak passwords | ✅ Fixed | Strong password requirements |
| User enumeration | ✅ Fixed | Generic error messages |
| Missing input validation | ✅ Fixed | Email, name, password validation |
| HTTP connections | ✅ Fixed | HTTPS enforcement |
| Missing security headers | ✅ Fixed | CSP, HSTS, X-Frame-Options, etc. |
| Unlimited sessions | ✅ Fixed | 1-hour session timeout |

## Testing Checklist

- [ ] Test customer login with correct credentials
- [ ] Test customer login with wrong password (should fail)
- [ ] Test customer login rate limiting (try 6 times with wrong password)
- [ ] Test admin login with correct credentials
- [ ] Test admin login rate limiting
- [ ] Test registration with weak password (should reject)
- [ ] Test registration with strong password (should succeed)
- [ ] Test registration with invalid email (should reject)
- [ ] Verify HTTP redirects to HTTPS in production
- [ ] Verify security headers are set (use browser dev tools)

## Next Steps (Medium Priority)

1. Add email verification for new accounts
2. Implement password reset flow
3. Add database transaction for cart merge
4. Add security event logging
5. Implement CAPTCHA for repeated failed attempts

## Notes

- Rate limiting uses the database, not sessions or files
- HTTPS enforcement is disabled on localhost for development
- Session timeout is set to 1 hour (3600 seconds)
- Admin and customer logins have separate rate limit counters
- Old rate limit records (24+ hours) are automatically cleaned up
