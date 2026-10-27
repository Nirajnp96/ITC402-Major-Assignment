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
- **Cancel** an upcoming appointment from *My Appointments* (only while it is still *Not Arrived* and has not started); the time slot is released for other patients

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
- Role checks: patients and clinicians can only reach their own pages; clinicians can only edit their own appointments, and patients can only cancel their own
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
- **PHP 7.3 or newer**
- A web browser

### XAMPP

1. Install [XAMPP](https://www.apachefriends.org/) (or WAMP/MAMP).
2. Clone the repository **into the web server's document root**:
   ```bash
   cd C:\xampp\htdocs 
   git clone https://github.com/Nirajnp96/ITC402-Major-Assignment.git hospital-booking
   ```
3. Start **Apache** from the XAMPP Control Panel.
4. Open <http://localhost/hospital-booking/> in your browser.

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
- Google Chrome must be installed (Selenium 4.6+ downloads the matching driver automatically).
- Screenshots are saved into `*-screenshots/` folders, which are git-ignored.
- The tests **register new patients and create appointments**, so they change
  the files in `data/`. Run them on a scratch copy, or restore the data
  afterwards with `git checkout -- data/`.

---

## Author

Niraj Padyachi – ITC402 Major Assignment.
Sonal Chandra - ITC402 Major Assignment
