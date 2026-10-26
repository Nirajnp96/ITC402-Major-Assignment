<?php
require_once __DIR__ . '/functions.php';

// ---- Registration validation (name/phone/email rules are shared in functions.php) ---
function validate_register(array $in): array
{
    $errors = validate_person($in, 'full_name');

    $username = trim($in['username'] ?? '');
    if ($username === '') {
        $errors['username'] = 'Username is required.';
    } elseif (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $username)) {
        $errors['username'] = 'Username must be 3-30 characters (letters, numbers, underscore only).';
    }

    $pw = $in['password'] ?? '';
    if ($pw === '') {
        $errors['password'] = 'Password is required.';
    } elseif (strlen($pw) < 8 || strlen($pw) > 64) {
        $errors['password'] = 'Password must be 8-64 characters.';
    } elseif (!preg_match('/[A-Z]/', $pw) || !preg_match('/[a-z]/', $pw) || !preg_match('/[0-9]/', $pw)) {
        $errors['password'] = 'Password needs an uppercase letter, a lowercase letter and a number.';
    }

    $confirm = $in['confirm_password'] ?? '';
    if ($confirm === '') {
        $errors['confirm_password'] = 'Please confirm your password.';
    } elseif ($pw !== $confirm) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }
    return $errors;
}

// Logged-in users don't need to register.
if ($u = current_user()) {
    redirect(home_for($u['role']));
}

$errors    = [];
$formError = '';
$old = ['full_name' => '', 'username' => '', 'email' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($old as $k => $_) {
        $old[$k] = trim($_POST[$k] ?? '');
    }

    if (!csrf_valid()) {
        $formError = 'Session expired. Please try again.';
    } else {
        $errors = validate_register($_POST);

        if (!$errors) {
            $ok = register_patient($old['username'], [
                'name'  => $old['full_name'],
                'email' => $old['email'],
                'phone' => $old['phone'],
            ], $_POST['password']);

            if ($ok) {
                flash('success', 'Registration successful. Please log in with your new account.');
                redirect('index.php');
            }
            $errors['username'] = 'That username is already taken. Please choose another.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Patient Registration - <?= h(HOSPITAL_NAME) ?> - <?= h(SYSTEM_NAME) ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="auth-body">
    <div class="auth-layout">
        <aside class="auth-hero">
            <div class="hero-content">
                <?= logo_svg(64) ?>
                <h1><?= h(HOSPITAL_NAME) ?></h1>
                <p class="hero-sub"><?= h(SYSTEM_NAME) ?></p>
                <ul class="hero-list">
                    <li>Register once and book whenever you need care</li>
                    <li>Your details are pre-filled for faster booking</li>
                    <li>View your appointment history in one place</li>
                </ul>
            </div>
        </aside>

        <main class="auth-panel">
            <div class="auth-card auth-card-wide">
                <h2>Patient registration</h2>
                <p class="auth-lead">Create your account to start booking appointments.</p>

                <?php if ($formError): ?>
                    <div class="alert alert-error" role="alert"><?= h($formError) ?></div>
                <?php endif; ?>

                <form id="register-form" method="post" action="register.php" novalidate>
                    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">

                    <div class="field">
                        <label for="full_name">Full name</label>
                        <input type="text" id="full_name" name="full_name" maxlength="60" value="<?= h($old['full_name']) ?>">
                        <span class="error" id="full_name-error"><?= h($errors['full_name'] ?? '') ?></span>
                    </div>

                    <div class="grid">
                        <div class="field">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" maxlength="100" value="<?= h($old['email']) ?>">
                            <span class="error" id="email-error"><?= h($errors['email'] ?? '') ?></span>
                        </div>
                        <div class="field">
                            <label for="phone">Phone</label>
                            <input type="tel" id="phone" name="phone" maxlength="20" placeholder="+679 1234567" value="<?= h($old['phone']) ?>">
                            <span class="error" id="phone-error"><?= h($errors['phone'] ?? '') ?></span>
                        </div>
                    </div>

                    <div class="field">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" maxlength="30" autocomplete="username" value="<?= h($old['username']) ?>">
                        <span class="error" id="username-error"><?= h($errors['username'] ?? '') ?></span>
                    </div>

                    <div class="grid">
                        <div class="field">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" maxlength="64" autocomplete="new-password">
                            <span class="hint">8+ characters with upper, lower case and a number</span>
                            <span class="error" id="password-error"><?= h($errors['password'] ?? '') ?></span>
                        </div>
                        <div class="field">
                            <label for="confirm_password">Confirm password</label>
                            <input type="password" id="confirm_password" name="confirm_password" maxlength="64" autocomplete="new-password">
                            <span class="error" id="confirm_password-error"><?= h($errors['confirm_password'] ?? '') ?></span>
                        </div>
                    </div>

                    <button type="submit" id="register-btn" class="btn btn-primary btn-block">Create account</button>
                </form>

                <p class="auth-switch">Already registered? <a href="index.php" id="login-link">Back to sign in</a></p>
            </div>
            <footer class="site-footer">
        &copy; <?= date('Y') ?> <?= h(HOSPITAL_NAME) ?> &middot; Lautoka, Fiji &middot; For emergencies, please call or visit our Emergency Department directly.
    </footer>
        </main>
    </div>

    <script src="assets/validation.js"></script>
</body>
</html>
