<?php
require_once __DIR__ . '/functions.php';

// ---- Login validation ------------------------------------------------------
function validate_login(array $in): array
{
    $errors = [];
    $username = trim($in['username'] ?? '');
    $password = $in['password'] ?? '';
    $role     = $in['role'] ?? '';

    if ($username === '') {
        $errors['username'] = 'Username is required.';
    } elseif (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $username)) {
        $errors['username'] = 'Username must be 3-30 characters (letters, numbers, underscore only).';
    }

    if ($password === '') {
        $errors['password'] = 'Password is required.';
    } elseif (strlen($password) < 6 || strlen($password) > 64) {
        $errors['password'] = 'Password must be 6-64 characters.';
    }

    if ($role === '') {
        $errors['role'] = 'Please select your role.';
    } elseif (!in_array($role, ['patient', 'clinician'], true)) {
        $errors['role'] = 'Invalid role selected.';
    }
    return $errors;
}

// Already logged in? Send them to their page.
if ($u = current_user()) {
    redirect(home_for($u['role']));
}

$errors   = [];
$old      = ['username' => '', 'role' => ''];
$loginMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        $loginMsg = 'Session expired. Please try again.';
    } else {
        $old['username'] = trim($_POST['username'] ?? '');
        $old['role']     = $_POST['role'] ?? '';
        $errors = validate_login($_POST);

        if (!$errors) {
            $acct = authenticate($old['username'], $_POST['password'], $old['role']);

            // Same message for wrong user / password / role so nothing is leaked.
            if ($acct) {
                session_regenerate_id(true);
                $_SESSION['user'] = $acct;
                redirect(home_for($acct['role']));
            }
            $loginMsg = 'Invalid username, password or role.';
        }
    }
}
$flash = take_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - <?= h(HOSPITAL_NAME) ?> - <?= h(SYSTEM_NAME) ?></title>
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
                    <li>Book consultations, check-ups and specialist visits online</li>
                    <li>Choose your preferred clinician, date and time</li>
                    <li>Track the status of your appointments anytime</li>
                </ul>
            </div>
        </aside>

        <main class="auth-panel">
            <div class="auth-card">
                <h2>Sign in</h2>
                <p class="auth-lead">Access your account to manage appointments.</p>

                <?php if ($flash): ?>
                    <div class="alert alert-<?= h($flash['type']) ?>" id="flash-msg" role="status"><?= h($flash['msg']) ?></div>
                <?php endif; ?>
                <?php if ($loginMsg): ?>
                    <div class="alert alert-error" id="login-error" role="alert"><?= h($loginMsg) ?></div>
                <?php endif; ?>

                <form id="login-form" method="post" action="index.php" novalidate>
                    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">

                    <fieldset class="field role-field">
                        <legend>I am a</legend>
                        <div class="role-toggle" role="radiogroup" aria-label="Select your role">
                            <input type="radio" id="role-patient" name="role" value="patient" <?= $old['role'] === 'patient' ? 'checked' : '' ?>>
                            <label for="role-patient">Patient</label>
                            <input type="radio" id="role-clinician" name="role" value="clinician" <?= $old['role'] === 'clinician' ? 'checked' : '' ?>>
                            <label for="role-clinician">Clinician</label>
                        </div>
                        <span class="error" id="role-error"><?= h($errors['role'] ?? '') ?></span>
                    </fieldset>

                    <div class="field">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" maxlength="30"
                               autocomplete="username" value="<?= h($old['username']) ?>">
                        <span class="error" id="username-error"><?= h($errors['username'] ?? '') ?></span>
                    </div>

                    <div class="field">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" maxlength="64"
                               autocomplete="current-password">
                        <span class="error" id="password-error"><?= h($errors['password'] ?? '') ?></span>
                    </div>

                    <button type="submit" id="login-btn" class="btn btn-primary btn-block">Log in</button>
                </form>

                <p class="auth-switch">New patient? <a href="register.php" id="register-link">Create an account</a></p>
            </div>
            <footer class="site-footer">
        &copy; <?= date('Y') ?> <?= h(HOSPITAL_NAME) ?> &middot; Lautoka, Fiji &middot; For emergencies, please call or visit our Emergency Department directly.
    </footer>
        </main>
    </div>

    <script src="assets/validation.js"></script>
</body>
</html>
