import os
import random
import re
import time
import unittest
from datetime import date, timedelta

from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.support.ui import Select, WebDriverWait

BASE_URL = "http://localhost/hospital-booking/"
PAUSE = 0.5   # seconds between actions so you can watch the test

# Open the browser once for the whole script
driver = webdriver.Chrome()
driver.maximize_window()

# ---- screenshots: one is saved after EVERY action ----
SHOT_DIR = "Test-Patient screenshots"
os.makedirs(SHOT_DIR, exist_ok=True)
shot_no = [0]
current_test = ["setup"]


def snap(label):
    shot_no[0] += 1
    label = re.sub(r"\W+", "_", label).strip("_")
    driver.save_screenshot("%s/%s_%03d_%s_%s.png" % (SHOT_DIR, "patient", shot_no[0], current_test[0], label))

# ---- test data ----
PASSWORD = "Test@1234"


# Tom Kumar = registered patient who books the appointment
TOM = {"name": "Tom Kumar", "username": "tom_kumar",
       "email": "tomkumar@gmail.com", "phone": "7654321"}
# Jack Prasad = registered patient who tests the appointment form validation
JACK = {"name": "Jack Prasad", "username": "jack_prasad",
        "email": "jackprasad@gmail.com", "phone": "7123456"}
# Dan Chand = never registered, so login must be rejected
DAN = {"name": "Dan Chand", "username": "dan_chand"}

# random future weekday + time slot so re-runs don't clash
d = date.today() + timedelta(days=random.randint(2, 80))
while d.weekday() == 6:
    d += timedelta(days=1)
BOOK_DATE = d.isoformat()
BOOK_TIME = random.choice(["09:00", "09:30", "10:00", "10:30", "11:00", "14:00", "15:00"])
REASON = "Headache for three days"


class PatientTests(unittest.TestCase):

    @classmethod
    def setUpClass(cls):
        cls.driver = driver
        cls.wait = WebDriverWait(driver, 10)
        # create the patients used by the tests
        cls.register(TOM)
        cls.register(JACK)

    def setUp(self):
        current_test[0] = self._testMethodName

    def tearDown(self):
        snap("final_result")

    # ---- helpers ----
    @classmethod
    def open(cls, page):
        cls.driver.get(BASE_URL + page)
        time.sleep(PAUSE)
        snap("open_" + page)

    @classmethod
    def type(cls, id_, text):
        box = cls.wait.until(EC.presence_of_element_located((By.ID, id_)))
        box.clear()
        box.send_keys(text)
        time.sleep(PAUSE)
        snap("type_" + id_)

    @classmethod
    def select(cls, id_, value):
        Select(cls.driver.find_element(By.ID, id_)).select_by_value(value)
        time.sleep(PAUSE)
        snap("select_" + id_)

    @classmethod
    def click(cls, id_):
        cls.wait.until(EC.element_to_be_clickable((By.ID, id_))).click()
        time.sleep(PAUSE)
        snap("click_" + id_)

    @classmethod
    def text(cls, id_):
        return cls.wait.until(EC.presence_of_element_located((By.ID, id_))).text

    @classmethod
    def wait_text(cls, id_, part):
        cls.wait.until(lambda dr: part.lower() in dr.find_element(By.ID, id_).text.lower())
        return cls.text(id_)

    @classmethod
    def register(cls, p):
        cls.open("register.php")
        cls.type("full_name", p["name"])
        cls.type("email", p["email"])
        cls.type("phone", p["phone"])
        cls.type("username", p["username"])
        cls.type("password", PASSWORD)
        cls.type("confirm_password", PASSWORD)
        cls.click("register-btn")
        # first run: "Registration successful"; later runs: username already exists (also fine)
        cls.wait.until(lambda dr: "registration successful" in dr.page_source.lower()
                       or "already taken" in dr.page_source.lower())

    @classmethod
    def login(cls, username, password):
        cls.open("logout.php")
        cls.select("role", "patient")
        cls.type("username", username)
        cls.type("password", password)
        cls.click("login-btn")

    # ---- tests (run in numbered order) ----
    def test_01_patient_login_rejected(self):
        """Unsuccessful: Dan Chand (not registered) cannot log in"""
        self.login(DAN["username"], PASSWORD)
        self.assertIn("Invalid username", self.wait_text("login-error", "invalid"))

    def test_02_booking_form_validation_and_logout(self):
        """Unsuccessful: Jack Prasad logs in, empty booking form is rejected, then logs out"""
        self.login(JACK["username"], PASSWORD)
        self.wait.until(EC.url_contains("booking.php"))
        self.assertIn(JACK["name"], self.text("welcome"))

        self.click("book-btn")                       # submit with nothing selected
        self.assertIn("appointment type", self.text("type-error"))
        self.assertIn("select a clinician", self.text("clinician-error"))
        self.assertIn("date is required", self.text("date-error"))
        self.assertIn("time slot", self.text("time-error"))
        self.assertIn("Reason for visit is required", self.text("reason-error"))

        self.click("logout-link")                    # log out
        self.wait.until(EC.url_contains("index.php"))
        self.assertTrue(self.driver.find_elements(By.ID, "login-form"))

    def test_03_patient_login_and_book_appointment(self):
        """Successful: Tom Kumar logs in and books a Medical consultation with clinician1"""
        self.login(TOM["username"], PASSWORD)
        self.wait.until(EC.url_contains("booking.php"))
        self.assertIn(TOM["name"], self.text("welcome"))

        self.select("type", "Medical consultation")
        self.select("clinician", "clinician1")
        self.driver.execute_script(
            "arguments[0].value = arguments[1];", self.driver.find_element(By.ID, "date"), BOOK_DATE)
        snap("date_set")
        self.select("time", BOOK_TIME)
        self.type("reason", REASON)
        self.click("book-btn")

        msg = self.wait_text("flash-msg", "booked successfully")
        self.assertTrue(re.search(r"APT-\d{4}", msg))

    def test_04_my_appointments_shows_not_arrived(self):
        """Successful: Tom Kumar's booking appears in My Appointments as Not Arrived"""
        self.login(TOM["username"], PASSWORD)
        self.wait.until(EC.url_contains("booking.php"))
        self.click("tab-my")
        self.wait.until(EC.url_contains("tab=my"))
        table = self.text("my-appointments")
        self.assertIn(REASON, table)
        self.assertIn(BOOK_DATE, table)
        self.assertIn("Not Arrived", table)


if __name__ == "__main__":
    unittest.main(verbosity=2, exit=False)   # run all the tests
    driver.quit()                            # close the browser
