<?php
require_once __DIR__ . '/functions.php';

// ---- Clinician update data & validation ------------------------------------
const STATUS_OPTIONS = ['Not Arrived', 'Arrived', 'Cancelled'];

function validate_clinician_update(array $in): array
{
    $errors = [];
    if (!in_array($in['status'] ?? '', STATUS_OPTIONS, true)) {
        $errors['status'] = 'Please choose a valid status.';
    }
    if (mb_strlen(trim($in['notes'] ?? '')) > 1000) {
        $errors['notes'] = 'Notes cannot exceed 1000 characters.';
    }
    return $errors;
}

$user = require_role('clinician');
$tab  = ($_GET['tab'] ?? 'dashboard') === 'all' ? 'all' : 'dashboard';

$rowErrors = [];   // errors keyed by appointment id
$rowOld    = [];   // submitted values keyed by appointment id

// ---- Handle Save / Complete -------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $apptId = $_POST['appointment_id'] ?? '';
    $action = $_POST['action'] ?? '';

    if (!csrf_valid()) {
        flash('error', 'Session expired. Please try again.');
        redirect('clinician.php');
    }
    if (!in_array($action, ['save', 'complete'], true)) {
        flash('error', 'Invalid action.');
        redirect('clinician.php');
    }

    $errs   = validate_clinician_update($_POST);
    $status = $_POST['status'] ?? '';

    // A visit can only be completed once the patient has arrived or it was cancelled.
    if (!$errs && $action === 'complete' && $status === 'Not Arrived') {
        $errs['status'] = 'Set the status to Arrived or Cancelled before completing.';
    }

    if ($errs) {
        $rowErrors[$apptId] = $errs;
        $rowOld[$apptId] = ['status' => $status, 'notes' => $_POST['notes'] ?? ''];
    } else {
        $notes = trim($_POST['notes'] ?? '');
        $found = update_data(function (&$data) use ($apptId, $action, $status, $notes, $user) {
            foreach ($data['appointments'] as &$a) {
                // Clinicians may only change their own, not-yet-completed appointments.
                if ($a['id'] === $apptId && $a['clinician'] === $user['username'] && empty($a['completed'])) {
                    $a['status'] = $status;
                    $a['notes']  = $notes;
                    if ($action === 'complete') {
                        $a['completed']    = true;
                        $a['completed_at'] = date('Y-m-d H:i:s');
                    }
                    return true;
                }
            }
            return false;
        });

        if ($found) {
            flash('success', $action === 'complete'
                ? "Appointment $apptId completed and removed from the dashboard."
                : "Appointment $apptId saved.");
        } else {
            flash('error', 'Appointment not found or already completed.');
        }
        redirect('clinician.php?tab=' . $tab);
    }
}

// ---- Load this clinician's appointments ------------------------------------
$data = load_data();
$mine = array_values(array_filter($data['appointments'], function ($a) use ($user) {
    return $a['clinician'] === $user['username'];
}));
$mine   = sort_appointments($mine);
$active = array_values(array_filter($mine, function ($a) { return empty($a['completed']); }));
$today  = date('Y-m-d');
$todayCount   = count(array_filter($active, function ($a) use ($today) { return $a['date'] === $today; }));
$arrivedCount = count(array_filter($active, function ($a) { return $a['status'] === 'Arrived'; }));
$flash  = take_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Clinician Dashboard - <?= h(HOSPITAL_NAME) ?> - <?= h(SYSTEM_NAME) ?></title>
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
            <h1>Clinician Dashboard</h1>
            <p>Update patient status, record notes and complete visits.</p>
        </div>

        <div class="stats">
            <div class="stat"><span class="stat-num" id="stat-active"><?= count($active) ?></span><span class="stat-label">Active bookings</span></div>
            <div class="stat"><span class="stat-num" id="stat-today"><?= $todayCount ?></span><span class="stat-label">Today</span></div>
            <div class="stat"><span class="stat-num" id="stat-arrived"><?= $arrivedCount ?></span><span class="stat-label">Patients arrived</span></div>
        </div>

        <nav class="tabs" aria-label="Clinician tabs">
            <a href="clinician.php?tab=dashboard" id="tab-dashboard" class="tab <?= $tab === 'dashboard' ? 'active' : '' ?>">Dashboard <span class="count"><?= count($active) ?></span></a>
            <a href="clinician.php?tab=all" id="tab-all" class="tab <?= $tab === 'all' ? 'active' : '' ?>">View Appointments <span class="count"><?= count($mine) ?></span></a>
        </nav>

        <?php if ($flash): ?>
            <div class="alert alert-<?= h($flash['type']) ?>" id="flash-msg" role="status"><?= h($flash['msg']) ?></div>
        <?php endif; ?>

        <?php if ($tab === 'dashboard'): ?>
        <!-- ===================== DASHBOARD TAB ===================== -->
        <section>
            <?php if (!$active): ?>
                <div class="card"><p class="muted" id="no-bookings">No active bookings. Completed appointments are removed from this dashboard.</p></div>
            <?php endif; ?>

            <?php foreach ($active as $a):
                $id        = $a['id'];
                $curStatus = $rowOld[$id]['status'] ?? $a['status'];
                $curNotes  = $rowOld[$id]['notes']  ?? $a['notes'];
                $e         = $rowErrors[$id] ?? [];
                $cls       = strtolower(str_replace(' ', '-', $a['status'])); ?>
            <article class="card appt" id="appt-<?= h($id) ?>">
                <div class="appt-head">
                    <div>
                        <span class="appt-id"><?= h($id) ?></span>
                        <h3><?= h($a['patient_name']) ?></h3>
                        <span class="chip"><?= h($a['type'] ?? '') ?></span>
                    </div>
                    <div class="appt-when">
                        <span class="appt-date"><?= h($a['date']) ?></span>
                        <span class="appt-time"><?= h($a['time']) ?></span>
                        <span class="badge badge-<?= h($cls) ?>"><?= h($a['status']) ?></span>
                    </div>
                </div>

                <dl class="appt-meta">
                    <div><dt>Phone</dt><dd><?= h($a['phone']) ?></dd></div>
                    <div><dt>Email</dt><dd><?= h($a['email']) ?></dd></div>
                    <div class="wide"><dt>Reason for visit</dt><dd><?= h($a['reason']) ?></dd></div>
                </dl>

                <form method="post" action="clinician.php?tab=dashboard" novalidate>
                    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="appointment_id" value="<?= h($id) ?>">

                    <div class="grid grid-status">
                        <div class="field">
                            <label for="status-<?= h($id) ?>">Status</label>
                            <select id="status-<?= h($id) ?>" name="status">
                                <?php foreach (STATUS_OPTIONS as $s): ?>
                                    <option value="<?= h($s) ?>" <?= $curStatus === $s ? 'selected' : '' ?>><?= h($s) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="error" id="status-error-<?= h($id) ?>"><?= h($e['status'] ?? '') ?></span>
                        </div>

                        <div class="field">
                            <label for="notes-<?= h($id) ?>">Clinician notes</label>
                            <textarea id="notes-<?= h($id) ?>" name="notes" rows="3" maxlength="1000"><?= h($curNotes) ?></textarea>
                            <span class="error" id="notes-error-<?= h($id) ?>"><?= h($e['notes'] ?? '') ?></span>
                        </div>
                    </div>

                    <div class="btn-row">
                        <button type="submit" name="action" value="save" id="save-<?= h($id) ?>" class="btn btn-primary">Save</button>
                        <button type="submit" name="action" value="complete" id="complete-<?= h($id) ?>" class="btn btn-success">Complete &amp; Remove</button>
                    </div>
                </form>
            </article>
            <?php endforeach; ?>
        </section>

        <?php else: ?>
        <!-- ===================== VIEW APPOINTMENTS TAB ===================== -->
        <section class="card">
            <h2 class="section-title first">All My Appointments</h2>
            <?php if (!$mine): ?>
                <p class="muted" id="no-appointments">No appointments have been booked with you yet.</p>
            <?php else: ?>
                <div class="table-wrap">
                <table class="table" id="all-appointments">
                    <thead>
                        <tr><th>Ref</th><th>Patient</th><th>Date</th><th>Time</th><th>Type</th><th>Reason</th><th>Status</th><th>Notes</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($mine as $a):
                        $label = !empty($a['completed']) ? 'Completed' : $a['status'];
                        $cls   = strtolower(str_replace(' ', '-', $label)); ?>
                        <tr>
                            <td><strong><?= h($a['id']) ?></strong></td>
                            <td><?= h($a['patient_name']) ?></td>
                            <td><?= h($a['date']) ?></td>
                            <td><?= h($a['time']) ?></td>
                            <td><?= h($a['type'] ?? '') ?></td>
                            <td><?= h($a['reason']) ?></td>
                            <td><span class="badge badge-<?= h($cls) ?>"><?= h($label) ?></span></td>
                            <td><?= h($a['notes']) ?></td>
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
</body>
</html>
