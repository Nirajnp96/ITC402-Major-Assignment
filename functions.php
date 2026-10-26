<?php
/**
 * Shared helpers for the Lautoka Private Hospital Online Booking System.
 *
 * No database is used:
 *   - Clinician (staff) accounts are defined in get_staff() below.
 *   - Registered patients are stored in data/users.json (passwords hashed).
 *   - Appointments are stored in data/appointments.json.
 * Files are shared between all users, so patients and clinicians (who have
 * separate PHP sessions) always see the same bookings.
 */

date_default_timezone_set('Pacific/Fiji');

const HOSPITAL_NAME = 'Lautoka Private Hospital';
const SYSTEM_NAME   = 'Online Booking System';

// ---- Session (secure defaults) -------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

define('APPOINTMENTS_FILE', __DIR__ . '/data/appointments.json');
define('USERS_FILE',        __DIR__ . '/data/users.json');

// ---- Static reference data -------------------------------------------------
/** Clinician accounts (configured by the hospital, not self-registered). */
function get_staff(): array
{
    return [
        'clinician1' => ['password' => 'Clinician@123', 'name' => 'Dr. Sarah Tui',   'specialty' => 'General Medicine'],
        'clinician2' => ['password' => 'Clinician@123', 'name' => 'Dr. James Wong',  'specialty' => 'Surgery'],
        'clinician3' => ['password' => 'Clinician@123', 'name' => 'Dr. Priya Sharma', 'specialty' => 'Internal Medicine'],
    ];
}

// ---- Output / security helpers -------------------------------------------

/** Escape for HTML output (prevents XSS). */
function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_valid(): bool
{
    return isset($_POST['csrf'], $_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], (string) $_POST['csrf']);
}

function flash(string $type, string $msg): void
{
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function take_flash(): ?array
{
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/** Inline SVG hospital logo. */
function logo_svg(int $size = 40): string
{
    return '<svg class="logo" width="' . $size . '" height="' . $size . '" viewBox="0 0 48 48" aria-hidden="true">'
        . '<rect width="48" height="48" rx="12" fill="currentColor" opacity=".15"/>'
        . '<path d="M20 10h8v10h10v8H28v10h-8V28H10v-8h10z" fill="currentColor"/></svg>';
}

// ---- Authentication --------------------------------------------------------

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function home_for(string $role): string
{
    return $role === 'clinician' ? 'clinician.php' : 'booking.php';
}

/** Make sure someone with the given role is logged in, else go to login. */
function require_role(string $role): array
{
    $u = current_user();
    if (!$u) {
        redirect('index.php');
    }
    if ($u['role'] !== $role) {
        redirect(home_for($u['role']));
    }
    return $u;
}

/**
 * Check credentials. Returns the session user array on success, or null.
 * Patients: hashed password from data/users.json. Clinicians: staff list.
 */
function authenticate(string $username, string $password, string $role): ?array
{
    $username = strtolower($username);

    if ($role === 'clinician') {
        $staff = get_staff();
        if (isset($staff[$username]) && hash_equals($staff[$username]['password'], $password)) {
            return ['username' => $username, 'name' => $staff[$username]['name'],
                    'role' => 'clinician', 'email' => '', 'phone' => ''];
        }
        return null;
    }

    $patients = json_read(USERS_FILE, ['patients' => []])['patients'];
    $p = $patients[$username] ?? null;
    if ($p && password_verify($password, $p['password_hash'])) {
        return ['username' => $username, 'name' => $p['name'],
                'role' => 'patient', 'email' => $p['email'], 'phone' => $p['phone']];
    }
    return null;
}

// ---- JSON file storage with locking ---------------------------------------

function json_ensure(string $file, array $default): void
{
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    if (!file_exists($file)) {
        file_put_contents($file, json_encode($default));
    }
}

function json_read(string $file, array $default): array
{
    json_ensure($file, $default);
    $fp = fopen($file, 'r');
    flock($fp, LOCK_SH);
    $raw = stream_get_contents($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    $data = json_decode($raw ?: '', true);
    return is_array($data) ? $data : $default;
}

/**
 * Read-modify-write under an exclusive lock so two people saving at the same
 * time cannot overwrite each other. $fn receives the data by reference and
 * its return value is passed back to the caller.
 */
function json_update(string $file, array $default, callable $fn)
{
    json_ensure($file, $default);
    $fp = fopen($file, 'c+');
    flock($fp, LOCK_EX);
    $raw  = stream_get_contents($fp);
    $data = json_decode($raw ?: '', true);
    if (!is_array($data)) {
        $data = $default;
    }
    $result = $fn($data);
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($data, JSON_PRETTY_PRINT));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    return $result;
}

function load_data(): array
{
    $d = json_read(APPOINTMENTS_FILE, ['next_id' => 1, 'appointments' => []]);
    $d['appointments'] = $d['appointments'] ?? [];
    return $d;
}

function update_data(callable $fn)
{
    return json_update(APPOINTMENTS_FILE, ['next_id' => 1, 'appointments' => []], $fn);
}

/**
 * Register a new patient. Returns true on success, false if the username
 * is already taken (checked inside the lock to avoid a race).
 */
function register_patient(string $username, array $details, string $password): bool
{
    $username = strtolower($username);
    return json_update(USERS_FILE, ['patients' => []], function (&$data) use ($username, $details, $password) {
        $data['patients'] = $data['patients'] ?? [];
        if (isset($data['patients'][$username]) || isset(get_staff()[$username])) {
            return false;
        }
        $data['patients'][$username] = [
            'name'          => $details['name'],
            'email'         => $details['email'],
            'phone'         => $details['phone'],
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'created_at'    => date('Y-m-d H:i:s'),
        ];
        return true;
    });
}

/** Sort appointments by date then time. */
function sort_appointments(array $list): array
{
    usort($list, function ($a, $b) {
        return strcmp($a['date'] . $a['time'], $b['date'] . $b['time']);
    });
    return $list;
}

/**
 * Can a patient still cancel this appointment? Only while it is "Not Arrived",
 * has not been completed by the clinician and has not started yet.
 */
function can_patient_cancel(array $a): bool
{
    return empty($a['completed'])
        && ($a['status'] ?? '') === 'Not Arrived'
        && strtotime($a['date'] . ' ' . $a['time']) > time();
}

/**
 * Cancel one of the patient's OWN appointments (checked and saved inside a
 * single file lock). Returns 'ok', 'not_allowed' or 'not_found'.
 * The slot becomes free again because the clash check ignores "Cancelled".
 */
function cancel_appointment(string $id, string $username): string
{
    return update_data(function (&$data) use ($id, $username) {
        foreach ($data['appointments'] as &$a) {
            if ($a['id'] !== $id || $a['patient_username'] !== $username) {
                continue;
            }
            if (!can_patient_cancel($a)) {
                return 'not_allowed';
            }
            $a['status']       = 'Cancelled';
            $a['cancelled_by'] = 'patient';
            $a['cancelled_at'] = date('Y-m-d H:i:s');
            return 'ok';
        }
        return 'not_found';
    });
}

// ---- Validation ------------------------------------------------------------

/** Shared rules for name / phone / email (used by registration and booking). */
function validate_person(array $in, string $nameKey): array
{
    $errors = [];

    $name = trim($in[$nameKey] ?? '');
    if ($name === '') {
        $errors[$nameKey] = 'Full name is required.';
    } elseif (!preg_match("/^[A-Za-z][A-Za-z .'-]{1,59}$/", $name)) {
        $errors[$nameKey] = 'Name must be 2-60 letters (spaces, . \' - allowed).';
    }

    $phone = trim($in['phone'] ?? '');
    if ($phone === '') {
        $errors['phone'] = 'Phone number is required.';
    } elseif (!preg_match('/^\+?[0-9 \-]{7,15}$/', $phone)) {
        $errors['phone'] = 'Enter a valid phone number (7-15 digits, optional leading +).';
    }

    $email = trim($in['email'] ?? '');
    if ($email === '') {
        $errors['email'] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
        $errors['email'] = 'Enter a valid email address.';
    }
    return $errors;
}

