# PHP — Secure vs Insecure Examples

## 1. SQL Injection

### ❌ Insecure
```php
$email = $_POST['email'];
$result = mysqli_query($conn, "SELECT * FROM users WHERE email = '$email'");
// Input: ' OR '1'='1 — dumps entire table
```

### ✅ Secure
```php
// PDO with prepared statement — input is never part of the SQL
$stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email');
$stmt->execute([':email' => $_POST['email']]);
$user = $stmt->fetch();
```

---

## 2. XSS (Cross-Site Scripting)

### ❌ Insecure
```php
echo "Hello, " . $_GET['name']; // Input: <script>alert(1)</script>
```

### ✅ Secure
```php
// htmlspecialchars converts < > " ' & to HTML entities
echo "Hello, " . htmlspecialchars($_GET['name'], ENT_QUOTES, 'UTF-8');
```

---

## 3. Password Hashing

### ❌ Insecure
```php
$hash = md5($password);       // Broken — fast, rainbow-table vulnerable
$hash = sha1($password);      // Also broken
$hash = md5($password . $salt); // Still not enough
```

### ✅ Secure
```php
// password_hash uses bcrypt by default — slow by design, salted automatically
$hash = password_hash($password, PASSWORD_BCRYPT);

// Verification
if (password_verify($inputPassword, $storedHash)) {
    // Login success
}
```

---

## 4. Session Security

### ❌ Insecure
```php
session_start();
$_SESSION['user_id'] = $userId; // Session ID not regenerated after login
```

### ✅ Secure
```php
session_start();

// Regenerate ID after login to prevent session fixation
session_regenerate_id(true);
$_SESSION['user_id'] = $userId;
$_SESSION['created_at'] = time();

// In php.ini or at runtime:
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);    // HTTPS only
ini_set('session.cookie_samesite', 'Strict');
```

---

## 5. CSRF Protection

### ✅ Secure
```php
// Generate token on page load
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// In your form:
// <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

// Validate on form submission
function validateCsrf(): void {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        die('CSRF validation failed');
    }
}
```

---

## 6. File Upload Validation

### ❌ Insecure
```php
move_uploaded_file($_FILES['file']['tmp_name'], 'uploads/' . $_FILES['file']['name']);
// Attacker uploads shell.php — now has remote code execution
```

### ✅ Secure
```php
$allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
$maxSize = 5 * 1024 * 1024; // 5MB

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($_FILES['file']['tmp_name']); // Check real MIME, not extension

if (!in_array($mime, $allowedMimes, true)) {
    die('File type not allowed');
}
if ($_FILES['file']['size'] > $maxSize) {
    die('File too large');
}

// Random name, store outside webroot ideally
$safeName = bin2hex(random_bytes(16)) . '.jpg';
move_uploaded_file($_FILES['file']['tmp_name'], '/var/uploads/' . $safeName);
```

---

## 7. Local File Inclusion (LFI)

### ❌ Insecure
```php
$page = $_GET['page'];
include($page . '.php'); // Input: ../../etc/passwd%00
```

### ✅ Secure
```php
$allowed = ['home', 'about', 'contact'];
$page = $_GET['page'] ?? 'home';

if (!in_array($page, $allowed, true)) {
    $page = 'home'; // Fallback to safe default
}

include(__DIR__ . '/pages/' . $page . '.php');
```

---

## 8. Input Validation with filter_var

### ✅ Secure
```php
// Validate and sanitize common input types
$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
$age   = filter_input(INPUT_POST, 'age', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1, 'max_range' => 120]
]);
$url   = filter_input(INPUT_POST, 'url', FILTER_VALIDATE_URL);

if ($email === false || $email === null) {
    die('Invalid email');
}
```

---

## 9. Error Handling (Production)

### ❌ Insecure
```php
// In production — leaks DB structure, file paths, PHP version
ini_set('display_errors', 1);
error_reporting(E_ALL);
```

### ✅ Secure
```php
// Production settings — log errors, never display them
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/var/log/php_errors.log');
error_reporting(E_ALL);

// Generic error response to user
set_exception_handler(function ($e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An unexpected error occurred']);
});
```
