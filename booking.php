<?php
require_once __DIR__ . '/functions.php';

// ---- Booking data ----------------------------------------------------------
const APPOINTMENT_TYPES = [
    'Medical consultation',
    'Surgical consultation',
    'Normal Check-up',
    'Specialist booking',
];

/** Clinicians a patient can book with: username => [name, specialty]. */
function get_clinicians(): array
{
    $list = [];
    foreach (get_staff() as $username => $s) {
        $list[$username] = ['name' => $s['name'], 'specialty' => $s['specialty']];
    }
    return $list;
}

/** 30-minute slots from 09:00 to 16:30 (lunch 12:00-13:00 excluded). */
function get_time_slots(): array
{
    $slots = [];
    for ($t = strtotime('09:00'); $t <= strtotime('16:30'); $t += 1800) {
        if ((int) date('G', $t) === 12) {
            continue;
        }
        $slots[] = date('H:i', $t);
    }
    return $slots;
}

// ---- Booking validation ----------------------------------------------------
function validate_booking(array $in): array
{
    $errors = validate_person($in, 'patient_name');

    $type = $in['type'] ?? '';
    if ($type === '') {
        $errors['type'] = 'Please select an appointment type.';
    } elseif (!in_array($type, APPOINTMENT_TYPES, true)) {
        $errors['type'] = 'Selected appointment type is not valid.';
    }

    $clinician = $in['clinician'] ?? '';
    if ($clinician === '') {
        $errors['clinician'] = 'Please select a clinician.';
    } elseif (!array_key_exists($clinician, get_clinicians())) {
        $errors['clinician'] = 'Selected clinician is not valid.';
    }

    $date = $in['date'] ?? '';
    $d = DateTime::createFromFormat('Y-m-d', $date);
    if ($date === '') {
        $errors['date'] = 'Appointment date is required.';
    } elseif (!$d || $d->format('Y-m-d') !== $date) {
        $errors['date'] = 'Enter a valid date.';
    } else {
        $today = new DateTime('today');
        $max   = (new DateTime('today'))->modify('+90 days');
        $d->setTime(0, 0);
        if ($d < $today) {
            $errors['date'] = 'Date cannot be in the past.';
        } elseif ($d > $max) {
            $errors['date'] = 'Appointments can only be booked up to 90 days ahead.';
        } elseif ((int) $d->format('w') === 0) {
            $errors['date'] = 'The hospital clinic is closed on Sundays.';
        }
    }

    $time = $in['time'] ?? '';
    if ($time === '') {
        $errors['time'] = 'Please select a time slot.';
    } elseif (!in_array($time, get_time_slots(), true)) {
        $errors['time'] = 'Selected time slot is not valid.';
    } elseif (!isset($errors['date']) && $date === date('Y-m-d') && $time <= date('H:i')) {
        $errors['time'] = 'That time has already passed today.';
    }

    $reason = trim($in['reason'] ?? '');
    $len = mb_strlen($reason);
    if ($reason === '') {
        $errors['reason'] = 'Reason for visit is required.';
    } elseif ($len < 5 || $len > 300) {
        $errors['reason'] = 'Reason must be between 5 and 300 characters.';
    }

    return $errors;
}

$user   = require_role('patient');
$tab    = ($_GET['tab'] ?? 'book') === 'my' ? 'my' : 'book';
$errors = [];
$formError = '';

$clinicians = get_clinicians();
$slots      = get_time_slots();

// Form values, pre-filled from the patient's registration details
$old = [
    'type' => '', 'clinician' => '', 'date' => '', 'time' => '',
    'patient_name' => $user['name'], 'phone' => $user['phone'], 'email' => $user['email'],
    'reason' => '',
];

// ---- Handle booking submission ---------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tab = 'book';
    foreach ($old as $k => $_) {
        $old[$k] = trim($_POST[$k] ?? '');
    }

    if (!csrf_valid()) {
        $formError = 'Session expired. Please try again.';
    } else {
        $errors = validate_booking($_POST);

        if (!$errors) {
            // Check for a clash and save in one locked operation.
            $result = update_data(function (&$data) use ($old, $user, $clinicians) {
                foreach ($data['appointments'] as $a) {
                    if ($a['clinician'] === $old['clinician']
                        && $a['date'] === $old['date']
                        && $a['time'] === $old['time']
                        && $a['status'] !== 'Cancelled') {
                        return false; // slot already taken
                    }
                }
                $id = 'APT-' . str_pad((string) $data['next_id'], 4, '0', STR_PAD_LEFT);
                $data['next_id']++;
                $data['appointments'][] = [
                    'id'               => $id,
                    'patient_username' => $user['username'],
                    'patient_name'     => $old['patient_name'],
                    'phone'            => $old['phone'],
                    'email'            => $old['email'],
                    'type'             => $old['type'],
                    'clinician'        => $old['clinician'],
                    'clinician_name'   => $clinicians[$old['clinician']]['name'],
                    'date'             => $old['date'],
                    'time'             => $old['time'],
                    'reason'           => $old['reason'],
                    'status'           => 'Not Arrived',
                    'notes'            => '',
                    'completed'        => false,
                    'created_at'       => date('Y-m-d H:i:s'),
                ];
                return $id;
            });

            if ($result === false) {
                $errors['time'] = 'That slot is already booked with this clinician. Please choose another time.';
            } else {
                flash('success', 'Appointment ' . $result . ' booked successfully.');
                redirect('booking.php?tab=my');
            }
        }
    }
}

// ---- Data for "My Appointments" tab ----------------------------------------
$data = load_data();
$mine = array_filter($data['appointments'], function ($a) use ($user) {
    return $a['patient_username'] === $user['username'];
});
$mine  = sort_appointments(array_values($mine));
$flash = take_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Book Appointment - <?= h(HOSPITAL_NAME) ?> - <?= h(SYSTEM_NAME) ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <header class="topbar">
        <div class="topbar-inner">
            <a class="topbar-brand" href="<?= h(home_for($user['role'])) ?>">
                <?= logo_svg(38) ?>
                <span>
                    <strong><?= h(HOSPITAL_NAME) ?></strong>
                    <small><?= h(SYSTEM_NAME) ?></small>
                </span>
            </a>
            <div class="topbar-user">
                <div class="user-chip">
                    <span class="avatar"><?= h(strtoupper(substr(preg_replace('/^Dr\.?\s+/i', '', $user['name']), 0, 1))) ?></span>
                    <span class="user-text">
                        <span id="welcome">Welcome, <?= h($user['name']) ?></span>
                        <small><?= $user['role'] === 'clinician' ? 'Clinician' : 'Patient' ?></small>
                    </span>
                </div>
                <a href="logout.php" id="logout-link" class="btn btn-outline-light">Log out</a>
            </div>
        </div>
    </header>

    <main class="container">
        <div class="page-head">
            <h1>Appointments</h1>
            <p>Book a visit with one of our clinicians and keep track of your bookings.</p>
        </div>

        <nav class="tabs" aria-label="Patient tabs">
            <a href="booking.php?tab=book" id="tab-book" class="tab <?= $tab === 'book' ? 'active' : '' ?>">Book Appointment</a>
            <a href="booking.php?tab=my" id="tab-my" class="tab <?= $tab === 'my' ? 'active' : '' ?>">My Appointments <span class="count"><?= count($mine) ?></span></a>
        </nav>

        <?php if ($flash): ?>
            <div class="alert alert-<?= h($flash['type']) ?>" id="flash-msg" role="status"><?= h($flash['msg']) ?></div>
        <?php endif; ?>

        <?php if ($tab === 'book'): ?>
        <!-- ===================== BOOK TAB ===================== -->
        <section class="card">
            <?php if ($formError): ?>
                <div class="alert alert-error" role="alert"><?= h($formError) ?></div>
            <?php endif; ?>

            <form id="booking-form" method="post" action="booking.php" novalidate>
                <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">

                <h2 class="section-title">Appointment details</h2>
                <div class="grid">
                    <div class="field">
                        <label for="type">Appointment type</label>
                        <select id="type" name="type">
                            <option value="">Select appointment type</option>
                            <?php foreach (APPOINTMENT_TYPES as $t): ?>
                                <option value="<?= h($t) ?>" <?= $old['type'] === $t ? 'selected' : '' ?>><?= h($t) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="error" id="type-error"><?= h($errors['type'] ?? '') ?></span>
                    </div>

                    <div class="field">
                        <label for="clinician">Clinician</label>
                        <select id="clinician" name="clinician">
                            <option value="">Select clinician</option>
                            <?php foreach ($clinicians as $uname => $c): ?>
                                <option value="<?= h($uname) ?>" <?= $old['clinician'] === $uname ? 'selected' : '' ?>><?= h($c['name']) ?> &ndash; <?= h($c['specialty']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="error" id="clinician-error"><?= h($errors['clinician'] ?? '') ?></span>
                    </div>

                    <div class="field">
                        <label for="date">Preferred date</label>
                        <input type="date" id="date" name="date"
                               min="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d', strtotime('+90 days')) ?>"
                               value="<?= h($old['date']) ?>">
                        <span class="error" id="date-error"><?= h($errors['date'] ?? '') ?></span>
                    </div>

                    <div class="field">
                        <label for="time">Time slot</label>
                        <select id="time" name="time">
                            <option value="">Select time</option>
                            <?php foreach ($slots as $s): ?>
                                <option value="<?= h($s) ?>" <?= $old['time'] === $s ? 'selected' : '' ?>><?= h($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="error" id="time-error"><?= h($errors['time'] ?? '') ?></span>
                    </div>
                </div>

                <h2 class="section-title">Patient details</h2>
                <div class="grid">
                    <div class="field">
                        <label for="patient_name">Full name</label>
                        <input type="text" id="patient_name" name="patient_name" maxlength="60" value="<?= h($old['patient_name']) ?>">
                        <span class="error" id="patient_name-error"><?= h($errors['patient_name'] ?? '') ?></span>
                    </div>

                    <div class="field">
                        <label for="phone">Phone</label>
                        <input type="tel" id="phone" name="phone" maxlength="20" placeholder="+679 1234567" value="<?= h($old['phone']) ?>">
                        <span class="error" id="phone-error"><?= h($errors['phone'] ?? '') ?></span>
                    </div>

                    <div class="field">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" maxlength="100" value="<?= h($old['email']) ?>">
                        <span class="error" id="email-error"><?= h($errors['email'] ?? '') ?></span>
                    </div>
                </div>

                <div class="field">
                    <label for="reason">Reason for visit</label>
                    <textarea id="reason" name="reason" rows="4" maxlength="300" placeholder="Briefly describe your symptoms or the purpose of your visit"><?= h($old['reason']) ?></textarea>
                    <span class="error" id="reason-error"><?= h($errors['reason'] ?? '') ?></span>
                </div>

                <div class="btn-row">
                    <button type="submit" id="book-btn" class="btn btn-primary">Save Booking</button>
                </div>
            </form>
        </section>

        <?php else: ?>
        <!-- ===================== MY APPOINTMENTS TAB ===================== -->
        <section class="card">
            <h2 class="section-title first">My Appointments</h2>
            <?php if (!$mine): ?>
                <p class="muted" id="no-appointments">You have no appointments yet.
                    <a href="booking.php?tab=book">Book one now</a>.</p>
            <?php else: ?>
                <div class="table-wrap">
                <table class="table" id="my-appointments">
                    <thead>
                        <tr><th>Ref</th><th>Date</th><th>Time</th><th>Type</th><th>Clinician</th><th>Reason</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($mine as $a):
                        $label = !empty($a['completed']) ? 'Completed' : $a['status'];
                        $cls   = strtolower(str_replace(' ', '-', $label)); ?>
                        <tr>
                            <td><strong><?= h($a['id']) ?></strong></td>
                            <td><?= h($a['date']) ?></td>
                            <td><?= h($a['time']) ?></td>
                            <td><?= h($a['type'] ?? '') ?></td>
                            <td><?= h($a['clinician_name']) ?></td>
                            <td><?= h($a['reason']) ?></td>
                            <td><span class="badge badge-<?= h($cls) ?>"><?= h($label) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>
    </main>

    <footer class="site-footer">
        &copy; <?= date('Y') ?> <?= h(HOSPITAL_NAME) ?> &middot; Lautoka, Fiji &middot; For emergencies, please call or visit our Emergency Department directly.
    </footer>
    <script src="assets/validation.js"></script>
</body>
</html>
