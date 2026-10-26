# Lautoka Private Hospital – Online Booking System

A lightweight web application that lets patients book appointments with
hospital clinicians online, and lets clinicians manage the bookings made with
them. It is written in plain **PHP, HTML, CSS and JavaScript** and needs **no
database** – all data is stored in JSON files.

Developed for the ITC402 Major Assignment.

---

## Features

### Patients
- Register for an account (self-service) and log in securely
- Book an appointment by choosing the **type**, **clinician**, **date** and **time slot**
- Contact details are pre-filled from the patient's registration
- View **My Appointments** with live status (*Not Arrived*, *Arrived*, *Cancelled*, *Completed*)

### Clinicians
- Log in with a hospital-issued staff account
- **Dashboard** showing active bookings, today's bookings and patients who have arrived
- Update an appointment's status (*Not Arrived / Arrived / Cancelled*) and add clinical notes
- **Complete** a visit once the patient has arrived (or the visit was cancelled)
- **View Appointments** tab listing every appointment, including completed ones

### Booking rules
| Rule | Value |
|------|-------|
| Appointment types | Medical consultation, Surgical consultation, Normal Check-up, Specialist booking |
| Time slots | 30-minute slots, 09:00 – 16:30 (12:00 – 13:00 lunch break excluded) |
| Closed | Sundays |
| Booking window | Today up to 90 days ahead; no past dates or times |
| Double booking | Prevented – a clinician's slot can only be booked once (cancelled slots are released) |

### Security and quality
- Passwords for patients are **hashed** (`password_hash` / `password_verify`)
- **CSRF tokens** on every form; session ID regenerated on login; `HttpOnly` + `SameSite` cookies
- All output is HTML-escaped to prevent **XSS**
- **Client-side validation** (`assets/validation.js`) for instant feedback, repeated on the **server** so it can never be bypassed
- JSON files are written under an **exclusive file lock** so simultaneous bookings cannot overwrite each other
- Role checks: patients and clinicians can only reach their own pages; clinicians can only edit their own appointments
- Automated **Selenium** UI tests for both the patient and clinician flows

---

## Technology

| Layer | Technology |
|-------|-----------|
| Back end | PHP (7.3 or newer; developed on PHP 8.x) |
| Front end | HTML5, CSS3, vanilla JavaScript |
| Storage | JSON files in `data/` (no database) |
| Testing | Python 3 + Selenium WebDriver |
| Version control | Git and GitHub |

---

## Project structure

```
hospital-booking/
├── index.php            # Login page (patients and clinicians)
├── register.php         # Patient registration
├── booking.php          # Patient area: book appointment / My Appointments
├── clinician.php        # Clinician area: dashboard / View Appointments
├── logout.php           # Ends the session
├── functions.php        # Shared helpers: sessions, CSRF, auth, JSON storage, validation
├── assets/
│   ├── style.css        # Application styles
│   └── validation.js    # Client-side form validation
├── data/
│   ├── users.json       # Registered patients (hashed passwords)
│   ├── appointments.json# All appointments
│   └── .htaccess        # Blocks direct web access to the data files (Apache)
├── AutomateTest/
│   ├── testpatients.py  # Selenium tests – patient flow
│   ├── testclinicians.py# Selenium tests – clinician flow
│   └── requirements.txt # Python dependencies for the tests
├── .gitignore
├── .gitattributes
└── README.md
```

---

## Running the application locally

### Requirements
- **PHP 7.3 or newer** with the `mbstring` and `session` extensions (enabled by default in XAMPP/WAMP)
- A web browser

### Option A – XAMPP / WAMP / MAMP (recommended)

1. Install [XAMPP](https://www.apachefriends.org/) (or WAMP/MAMP).
2. Clone the repository **into the web server's document root**:
   ```bash
   cd C:\xampp\htdocs          # macOS/Linux: /opt/lampp/htdocs or /var/www/html
   git clone https://github.com/Nirajnp96/ITC402-Major-Assignment.git hospital-booking
   ```
3. Start **Apache** from the XAMPP Control Panel.
4. Make sure the `data/` folder is writable by the web server (automatic on Windows; on Linux/macOS run `chmod -R 775 data`).
5. Open <http://localhost/hospital-booking/> in your browser.

This option enforces `data/.htaccess`, so the JSON files cannot be downloaded through the browser.

### Option B – PHP built-in server (quick development only)

From the project folder:

```bash
php -S localhost:8000
```

Then open <http://localhost:8000/>.

> **Note:** the built-in server ignores `.htaccess`, so files in `data/` are
> reachable by URL. Use this option for local development only – never for a
> real deployment.

### Logging in

**Clinicians** (accounts are configured in `get_staff()` in `functions.php`):

| Username | Password | Name | Specialty |
|----------|----------|------|-----------|
| `clinician1` | `Clinician@123` | Dr. Sarah Tui | General Medicine |
| `clinician2` | `Clinician@123` | Dr. James Wong | Surgery |
| `clinician3` | `Clinician@123` | Dr. Priya Sharma | Internal Medicine |

**Patients:** click **Create an account** on the login page. Passwords need 8–64
characters with an uppercase letter, a lowercase letter and a number.

On the login page choose the matching **role** (Patient / Clinician) before signing in.

> These demo credentials are for local testing only. Change them in
> `functions.php` before any real-world use.

---

## Running the automated tests

The tests drive a real Chrome browser with Selenium.

```bash
pip install -r AutomateTest/requirements.txt
cd AutomateTest
python testpatients.py
python testclinicians.py
```

- The app must already be running at `http://localhost/hospital-booking/`
  (change `BASE_URL` at the top of each test file if yours differs).
- Google Chrome must be installed (Selenium 4.6+ downloads the matching driver automatically).
- Screenshots are saved into `*-screenshots/` folders, which are git-ignored.
- The tests **register new patients and create appointments**, so they change
  the files in `data/`. Run them on a scratch copy, or restore the data
  afterwards with `git checkout -- data/`.

---

## Version control workflow

This project is managed with Git and hosted on GitHub.

- `main` always holds working, reviewed code.
- New work is done on a short-lived branch (e.g. `feature/<name>` or `fix/<name>`), then merged into `main` with a merge commit so the history shows when and why the feature was added.
- Commit messages follow the style `type: short summary` (`feat`, `fix`, `docs`, `chore`, `test`), with a longer explanation in the body where needed.

```bash
git switch -c feature/my-change     # create a branch
git add -A && git commit            # commit work
git switch main
git merge --no-ff feature/my-change # merge it back
git push origin main
```

---

## Author

Nirajnp96 – ITC402 Major Assignment.
