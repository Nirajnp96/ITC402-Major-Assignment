import os
import random
import re
import time
import unittest
from datetime import date, timedelta

from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import Select, WebDriverWait
from selenium.webdriver.support import expected_conditions as EC

BASE_URL = "http://localhost/hospital-booking/"
PAUSE = 0.5
SHOW = 3

# start browser
driver = webdriver.Chrome()
driver.maximize_window()

# screenshot folder
SHOT_DIR = "Clinician-screenshots"
os.makedirs(SHOT_DIR, exist_ok=True)
shot_no = [0]
current_test = ["setup"]


def snap(label):
    shot_no[0] += 1
    clean = re.sub(r"\W+", "_", label).strip("_")
    name = f"{SHOT_DIR}/clinician_{shot_no[0]:03d}_{current_test[0]}_{clean}.png"
    driver.save_screenshot(name)


# test data
PASSWORD = "Test@1234"

TOM = {
    "name": "Tom Kumar",
    "username": "tom_kumar",
    "email": "tomkumar@gmail.com",
    "phone": "7654321"
}

CLINICIAN_USER = "clinician1"
CLINICIAN_PASS = "Clinician@123"
CLINICIAN_NAME = "Dr. Sarah Tui"

# random booking date
d = date.today() + timedelta(days=random.randint(2, 80))
while d.weekday() == 6:
    d += timedelta(days=1)

BOOK_DATE = d.isoformat()
BOOK_TIME = random.choice(["09:00", "09:30", "10:00", "10:30", "11:00", "14:00", "15:00"])
REASON = "Knee pain when walking"


class ClinicianTests(unittest.TestCase):
    appt_id = None

    @classmethod
    def setUpClass(cls):
        cls.driver = driver
        cls.wait = WebDriverWait(driver, 10)

    def setUp(self):
        current_test[0] = self._testMethodName

    def tearDown(self):
        snap("final_result")

    # helpers
    def open(self, page):
        self.driver.get(BASE_URL + page)
        time.sleep(PAUSE)
        snap("open_" + page)

    def type(self, element_id, text):
        field = self.wait.until(EC.presence_of_element_located((By.ID, element_id)))
        field.clear()
        field.send_keys(text)
        time.sleep(PAUSE)
        snap("type_" + element_id)

    def select(self, element_id, value):
        Select(self.driver.find_element(By.ID, element_id)).select_by_value(value)
        time.sleep(PAUSE)
        snap("select_" + element_id)

    def choose_role(self, role):
        """Click the Patient / Clinician radio button on the login page."""
        self.wait.until(EC.element_to_be_clickable(
            (By.CSS_SELECTOR, "label[for='role-%s']" % role))).click()
        time.sleep(PAUSE)
        snap("choose_role_" + role)

    def click(self, element_id):
        self.wait.until(EC.element_to_be_clickable((By.ID, element_id))).click()
        time.sleep(PAUSE)
        snap("click_" + element_id)

    def text(self, element_id):
        return self.wait.until(EC.presence_of_element_located((By.ID, element_id))).text

    def wait_text(self, element_id, part):
        self.wait.until(lambda dr: part.lower() in dr.find_element(By.ID, element_id).text.lower())
        return self.text(element_id)

    def show(self, label, element_id=None):
        if element_id:
            self.driver.execute_script(
                "arguments[0].scrollIntoView({block:'center'});",
                self.driver.find_element(By.ID, element_id)
            )
        snap(label)
        time.sleep(SHOW)

    def login(self, username, password, role):
        self.open("logout.php")
        self.choose_role(role)
        self.type("username", username)
        self.type("password", password)
        self.click("login-btn")

    # tests
    def test_01_register_patient_book_and_logout(self):
        self.open("register.php")
        self.type("full_name", TOM["name"])
        self.type("email", TOM["email"])
        self.type("phone", TOM["phone"])
        self.type("username", TOM["username"])
        self.type("password", PASSWORD)
        self.type("confirm_password", PASSWORD)
        self.click("register-btn")

        self.wait.until(
            lambda dr: "registration successful" in dr.page_source.lower()
            or "already taken" in dr.page_source.lower()
        )

        self.login(TOM["username"], PASSWORD, "patient")
        self.wait.until(EC.url_contains("booking.php"))
        self.assertIn(TOM["name"], self.text("welcome"))

        self.select("type", "Medical consultation")
        self.select("clinician", "clinician1")

        self.driver.execute_script(
            "arguments[0].value = arguments[1];",
            self.driver.find_element(By.ID, "date"),
            BOOK_DATE
        )
        snap("date_set")

        self.select("time", BOOK_TIME)
        self.type("reason", REASON)
        self.click("book-btn")

        msg = self.wait_text("flash-msg", "booked successfully")
        found = re.search(r"APT-\d{4}", msg)
        self.assertTrue(found)
        ClinicianTests.appt_id = found.group(0)

        table = self.text("my-appointments")
        self.show("my_appointments", "my-appointments")
        self.assertIn(self.appt_id, table)
        self.assertIn(REASON, table)

        self.click("logout-link")
        self.wait.until(EC.url_contains("index.php"))

    def test_02_clinician_validation_and_logout(self):
        self.assertIsNotNone(self.appt_id)
        self.login(CLINICIAN_USER, CLINICIAN_PASS, "clinician")
        self.wait.until(EC.url_contains("clinician.php"))
        self.assertIn(CLINICIAN_NAME, self.text("welcome"))

        self.select("status-" + self.appt_id, "Not Arrived")
        self.click("complete-" + self.appt_id)

        err = "status-error-" + self.appt_id
        self.assertIn("before completing", self.wait_text(err, "before completing"))
        self.show("error_message", err)

        self.click("logout-link")
        self.wait.until(EC.url_contains("index.php"))

    def test_03_clinician_completes_process(self):
        self.assertIsNotNone(self.appt_id)
        self.login(CLINICIAN_USER, CLINICIAN_PASS, "clinician")
        self.wait.until(EC.url_contains("clinician.php"))

        card = self.text("appt-" + self.appt_id)
        self.assertIn(TOM["name"], card)
        self.assertIn(REASON, card)

        self.select("status-" + self.appt_id, "Arrived")
        self.type("notes-" + self.appt_id, "Patient arrived on time.")
        self.click("save-" + self.appt_id)
        self.assertIn("saved", self.wait_text("flash-msg", "saved"))
        self.show("saved_message", "flash-msg")

        self.select("status-" + self.appt_id, "Arrived")
        self.click("complete-" + self.appt_id)
        self.assertIn("completed", self.wait_text("flash-msg", "completed"))
        self.show("completed_message", "flash-msg")

        self.assertEqual(len(self.driver.find_elements(By.ID, "appt-" + self.appt_id)), 0)

        self.click("tab-all")
        table = self.wait_text("all-appointments", self.appt_id)
        self.show("view_appointments", "all-appointments")
        self.assertIn("Completed", table)

        self.click("logout-link")
        self.wait.until(EC.url_contains("index.php"))


if __name__ == "__main__":
    unittest.main(verbosity=2, exit=False)
    driver.quit()
