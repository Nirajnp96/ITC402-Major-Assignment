/**
 * Client-side validation (instant feedback). The same rules are enforced
 * again on the server in functions.php, so this is a convenience,
 * never the only line of defence.
 */
(function () {
    'use strict';

    var USERNAME_RE = /^[A-Za-z0-9_]{3,30}$/;
    var NAME_RE = /^[A-Za-z][A-Za-z .'-]{1,59}$/;
    var PHONE_RE = /^\+?[0-9 \-]{7,15}$/;
    var EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    function setError(id, msg) {
        var span = document.getElementById(id + '-error');
        var input = document.getElementById(id);
        if (span) { span.textContent = msg || ''; }
        if (input) { input.classList.toggle('invalid', !!msg); }
        return !msg;
    }

    function val(id) {
        var el = document.getElementById(id);
        return el ? el.value.trim() : '';
    }

    function raw(id) {
        var el = document.getElementById(id);
        return el ? el.value : '';
    }

    function guard(form, validate) {
        form.addEventListener('submit', function (e) {
            if (!validate()) {
                e.preventDefault();
                var firstBad = form.querySelector('.invalid');
                if (firstBad) { firstBad.focus(); }
            }
        });
    }

    // Each check returns an error message or '' when valid.
    function checkName(v) {
        if (v === '') return 'Full name is required.';
        if (!NAME_RE.test(v)) return "Name must be 2-60 letters (spaces, . ' - allowed).";
        return '';
    }
    function checkPhone(v) {
        if (v === '') return 'Phone number is required.';
        if (!PHONE_RE.test(v)) return 'Enter a valid phone number (7-15 digits, optional leading +).';
        return '';
    }
    function checkEmail(v) {
        if (v === '') return 'Email is required.';
        if (!EMAIL_RE.test(v)) return 'Enter a valid email address.';
        return '';
    }
    function checkUsername(v) {
        if (v === '') return 'Username is required.';
        if (!USERNAME_RE.test(v)) return 'Username must be 3-30 characters (letters, numbers, underscore only).';
        return '';
    }

    // ---------------- Login form ----------------
    var login = document.getElementById('login-form');
    if (login) {
        guard(login, function () {
            var ok = true;
            ok = setError('role', document.querySelector('input[name="role"]:checked') ? '' : 'Please select your role.') && ok;
            ok = setError('username', checkUsername(val('username'))) && ok;

            var p = raw('password'), pMsg = '';
            if (p === '') pMsg = 'Password is required.';
            else if (p.length < 6 || p.length > 64) pMsg = 'Password must be 6-64 characters.';
            ok = setError('password', pMsg) && ok;
            return ok;
        });
    }

    // ---------------- Registration form ----------------
    var reg = document.getElementById('register-form');
    if (reg) {
        guard(reg, function () {
            var ok = true;
            ok = setError('full_name', checkName(val('full_name'))) && ok;
            ok = setError('email', checkEmail(val('email'))) && ok;
            ok = setError('phone', checkPhone(val('phone'))) && ok;
            ok = setError('username', checkUsername(val('username'))) && ok;

            var p = raw('password'), pMsg = '';
            if (p === '') pMsg = 'Password is required.';
            else if (p.length < 8 || p.length > 64) pMsg = 'Password must be 8-64 characters.';
            else if (!/[A-Z]/.test(p) || !/[a-z]/.test(p) || !/[0-9]/.test(p))
                pMsg = 'Password needs an uppercase letter, a lowercase letter and a number.';
            ok = setError('password', pMsg) && ok;

            var c = raw('confirm_password'), cMsg = '';
            if (c === '') cMsg = 'Please confirm your password.';
            else if (c !== p) cMsg = 'Passwords do not match.';
            ok = setError('confirm_password', cMsg) && ok;
            return ok;
        });
    }

    // ---------------- Booking form ----------------
    var booking = document.getElementById('booking-form');
    if (booking) {
        guard(booking, function () {
            var ok = true;

            ok = setError('type', val('type') === '' ? 'Please select an appointment type.' : '') && ok;
            ok = setError('clinician', val('clinician') === '' ? 'Please select a clinician.' : '') && ok;

            var date = val('date'), dateMsg = '';
            if (date === '') {
                dateMsg = 'Appointment date is required.';
            } else {
                var d = new Date(date + 'T00:00:00');
                var today = new Date(); today.setHours(0, 0, 0, 0);
                var max = new Date(today); max.setDate(max.getDate() + 90);
                if (isNaN(d.getTime())) dateMsg = 'Enter a valid date.';
                else if (d < today) dateMsg = 'Date cannot be in the past.';
                else if (d > max) dateMsg = 'Appointments can only be booked up to 90 days ahead.';
                else if (d.getDay() === 0) dateMsg = 'The hospital clinic is closed on Sundays.';
            }
            ok = setError('date', dateMsg) && ok;

            ok = setError('time', val('time') === '' ? 'Please select a time slot.' : '') && ok;
            ok = setError('patient_name', checkName(val('patient_name'))) && ok;
            ok = setError('phone', checkPhone(val('phone'))) && ok;
            ok = setError('email', checkEmail(val('email'))) && ok;

            var reason = val('reason'), reasonMsg = '';
            if (reason === '') reasonMsg = 'Reason for visit is required.';
            else if (reason.length < 5 || reason.length > 300) reasonMsg = 'Reason must be between 5 and 300 characters.';
            ok = setError('reason', reasonMsg) && ok;

            return ok;
        });
    }

    // Ask for confirmation before a patient cancels an appointment
    var cancelForms = document.querySelectorAll('form.cancel-form');
    for (var i = 0; i < cancelForms.length; i++) {
        cancelForms[i].addEventListener('submit', function (e) {
            if (!window.confirm(this.getAttribute('data-confirm') || 'Cancel this appointment?')) {
                e.preventDefault();
            }
        });
    }
})();
