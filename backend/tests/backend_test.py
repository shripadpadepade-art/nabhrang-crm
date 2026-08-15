"""
Nabhrang (native PHP 8 + MySQL, server-rendered) E2E HTTP tests.
There are no JSON /api endpoints: we exercise the .php pages and form POSTs with a cookie jar
plus CSRF token extraction, exactly like a browser would.

Covered modules:
  - Public site (index.php, blog.php, album.php)
  - Admin auth (admin/login.php) + CSRF
  - Admin content (blogs.php, events.php)
  - Admin settings (settings.php)
  - Member lifecycle (register -> login -> payment w/ UTR -> admin verify -> card)
  - Admin reports CSV export
  - Soft delete (blog archive)
  - Security sanity (unauthenticated redirects, uploads dir)
"""
import os
import re
import time
import uuid
from pathlib import Path

import pytest
import requests
from bs4 import BeautifulSoup
from dotenv import dotenv_values

frontend_env = dotenv_values("/app/frontend/.env")
base_url = os.environ.get("REACT_APP_BACKEND_URL") or frontend_env.get("REACT_APP_BACKEND_URL")
if not base_url:
    raise RuntimeError("REACT_APP_BACKEND_URL missing from env and /app/frontend/.env")
BASE_URL = base_url.rstrip("/")

PHP_ERROR_RE = re.compile(
    r"(Warning</b>|Notice</b>|Fatal error|Parse error|Deprecated</b>|"
    r"Warning:\s|Notice:\s|Deprecated:\s|Uncaught\s\w*Exception|<b>Warning</b>)"
)

RUN = uuid.uuid4().hex[:6]
ADMIN_USER = "admin"
ADMIN_PASS = "password123"


def creds_file_ok():
    p = Path("/app/memory/test_credentials.md")
    return p.exists() and "password123" in p.read_text(encoding="utf-8")


def csrf_of(html, form_index=0):
    soup = BeautifulSoup(html, "html.parser")
    tokens = soup.select('input[name="csrf"]')
    assert tokens, "no csrf token found in page"
    return tokens[form_index].get("value")


def form_payload(html, selector="form"):
    """Serialize every named field of a form (browser-like) so partial POSTs don't wipe data."""
    soup = BeautifulSoup(html, "html.parser")
    form = soup.select_one(selector)
    assert form is not None, f"form not found for selector {selector}"
    data = {}
    for el in form.find_all(["input", "textarea", "select"]):
        name = el.get("name")
        if not name:
            continue
        tag = el.name
        if tag == "input":
            itype = (el.get("type") or "text").lower()
            if itype in ("file", "submit", "button", "image"):
                continue
            if itype in ("checkbox", "radio"):
                if el.has_attr("checked"):
                    data[name] = el.get("value", "on")
                continue
            data[name] = el.get("value", "")
        elif tag == "textarea":
            data[name] = el.text
        else:
            opt = form.select_one(f'select[name="{name}"] option[selected]')
            options = el.find_all("option")
            data[name] = (opt or (options[0] if options else {})).get("value", "") if options else ""
    return data


def assert_clean(resp, label):
    assert resp.status_code == 200, f"{label} -> HTTP {resp.status_code}"
    leak = PHP_ERROR_RE.search(resp.text)
    assert not leak, f"{label} leaks PHP diagnostics: {leak.group(0)}"


def db_exec(sql):
    """Direct DB access for verifying soft deletes and cleaning up TEST_ rows."""
    return os.popen('mysql -uroot nabhrang -Nse "%s"' % sql.replace('"', '\\"')).read().strip()


def purge_test_data():
    db_exec("DELETE FROM blogs WHERE title_mr LIKE 'TEST_%'")
    db_exec("DELETE FROM events WHERE title_mr LIKE 'TEST_%'")
    db_exec("DELETE FROM gallery_albums WHERE title_mr LIKE 'TEST_%'")
    db_exec("DELETE FROM payments WHERE member_id IN (SELECT id FROM members WHERE email LIKE 'TEST_%')")
    db_exec("DELETE FROM members WHERE email LIKE 'TEST_%'")


@pytest.fixture(scope="session", autouse=True)
def clean_slate():
    purge_test_data()
    yield
    purge_test_data()


@pytest.fixture(scope="class")
def public():
    s = requests.Session()
    return s


@pytest.fixture(scope="class")
def admin(public):
    s = requests.Session()
    page = s.get(f"{BASE_URL}/admin/login.php")
    r = s.post(
        f"{BASE_URL}/admin/login.php",
        data={"csrf": csrf_of(page.text), "username": ADMIN_USER, "password": ADMIN_PASS},
        allow_redirects=True,
    )
    if "admin/login.php" in r.url and "logout" not in r.text:
        pytest.fail(f"Admin login failed with seeded credentials: {r.status_code} {r.url}")
    return s


@pytest.fixture(scope="class")
def member_state():
    return {
        "email": f"TEST_{RUN}@example.test",
        "password": "TestPass!2026",
        "name": "चाचणी सदस्य",
        "phone": "9876500" + RUN[:3].encode().hex()[:3],
    }


class TestNabhrangE2E:
    # ---------- Public site ----------
    def test_01_homepage_marathi_no_php_errors(self, public):
        r = public.get(f"{BASE_URL}/")
        assert_clean(r, "homepage")
        assert 'lang="mr"' in r.text
        assert "नभरंग" in r.text
        assert 'data-testid="hero-title"' in r.text

    def test_02_public_pages_reachable(self, public):
        # blog.php / album.php are detail pages; without a slug they must render a clean 404 page
        for path, marker in [("/blog.php", "blog-not-found"), ("/album.php", None)]:
            r = public.get(f"{BASE_URL}{path}")
            assert r.status_code in (200, 404), f"{path} -> {r.status_code}"
            assert not PHP_ERROR_RE.search(r.text), f"{path} leaks PHP diagnostics"
            if marker:
                assert marker in r.text

    def test_03_member_register_and_login_pages_load(self, public):
        for path in ["/member/register.php", "/member/login.php", "/admin/login.php"]:
            assert_clean(public.get(f"{BASE_URL}{path}"), path)

    # ---------- Security sanity ----------
    def test_04_admin_pages_redirect_when_unauthenticated(self):
        s = requests.Session()
        for path in ["/admin/index.php", "/admin/blogs.php", "/admin/payments.php",
                     "/admin/settings.php", "/admin/reports.php", "/admin/members.php"]:
            r = s.get(f"{BASE_URL}{path}", allow_redirects=False)
            assert r.status_code in (301, 302), f"{path} did not redirect ({r.status_code})"
            assert "/admin/login.php" in r.headers.get("Location", "")

    def test_05_member_pages_redirect_when_unauthenticated(self):
        s = requests.Session()
        for path in ["/member/index.php", "/member/card.php"]:
            r = s.get(f"{BASE_URL}{path}", allow_redirects=False)
            assert r.status_code in (301, 302), f"{path} did not redirect"
            assert "/member/login.php" in r.headers.get("Location", "")

    def test_06_no_php_files_in_uploads(self):
        php_files = list(Path("/app/uploads").rglob("*.php")) if Path("/app/uploads").exists() else []
        assert not php_files, f"PHP files present in uploads: {php_files}"

    # ---------- Admin auth ----------
    def test_07_admin_login_wrong_password_marathi_error(self):
        s = requests.Session()
        page = s.get(f"{BASE_URL}/admin/login.php")
        r = s.post(f"{BASE_URL}/admin/login.php",
                   data={"csrf": csrf_of(page.text), "username": ADMIN_USER, "password": "wrong-pass"})
        assert r.status_code == 200
        assert re.search(r"[\u0900-\u097F]", r.text), "no Devanagari error text rendered"
        assert "login-error" in r.text or "error" in r.text

    def test_08_admin_login_csrf_rejected(self):
        s = requests.Session()
        s.get(f"{BASE_URL}/admin/login.php")
        r = s.post(f"{BASE_URL}/admin/login.php",
                   data={"csrf": "bogus", "username": ADMIN_USER, "password": ADMIN_PASS})
        assert r.status_code == 419, f"expected 419 on bad CSRF, got {r.status_code}"

    def test_09_admin_credentials_file_present(self):
        assert creds_file_ok(), "/app/memory/test_credentials.md missing or lacks admin password"

    def test_10_admin_dashboard_loads(self, admin):
        r = admin.get(f"{BASE_URL}/admin/index.php")
        assert_clean(r, "admin dashboard")
        assert re.search(r"[\u0900-\u097F]", r.text)

    def test_11_all_admin_pages_load(self, admin):
        for path in ["/admin/blogs.php", "/admin/events.php", "/admin/gallery.php", "/admin/videos.php",
                     "/admin/publications.php", "/admin/notifications.php", "/admin/members.php",
                     "/admin/payments.php", "/admin/settings.php", "/admin/reports.php",
                     "/admin/content.php"]:
            assert_clean(admin.get(f"{BASE_URL}{path}"), path)

    # ---------- Admin content: blog ----------
    def test_12_create_blog_and_verify_public(self, admin, public):
        title = f"TEST_ब्लॉग_{RUN}"
        page = admin.get(f"{BASE_URL}/admin/blogs.php")
        data = form_payload(page.text, 'form[enctype="multipart/form-data"]')
        data.update({
            "csrf": csrf_of(page.text),
            "action": "save",
            "title_mr": title,
            "content_mr": "चाचणी मजकूर " + RUN,
            "short_description_mr": "चाचणी परिचय",
            "category": "चाचणी",
            "author": "QA",
            "status": "published",
        })
        r = admin.post(f"{BASE_URL}/admin/blogs.php", data=data)
        assert_clean(r, "blog save")
        assert title in r.text, "created blog not listed in admin"

        # public homepage blog section
        home = public.get(f"{BASE_URL}/")
        assert_clean(home, "homepage after blog")
        assert title in home.text, "published blog missing from public homepage"

        # public blog detail page via slug link
        soup = BeautifulSoup(home.text, "html.parser")
        link = next((a["href"] for a in soup.find_all("a", href=True) if "blog.php?slug=" in a["href"]), None)
        assert link, "no blog detail link on homepage"
        detail = public.get(f"{BASE_URL}{link}" if link.startswith("/") else f"{BASE_URL}/{link}")
        assert_clean(detail, "blog detail")
        assert 'data-testid="blog-title"' in detail.text

    def test_13_soft_delete_blog_archives_not_hard_delete(self, admin, public):
        title = f"TEST_ब्लॉग_{RUN}"
        page = admin.get(f"{BASE_URL}/admin/blogs.php")
        soup = BeautifulSoup(page.text, "html.parser")
        row = next((a for a in soup.select('[data-testid="blog-row"]') if title in a.get_text()), None)
        assert row is not None, "created blog row not found in admin list"
        r = admin.post(f"{BASE_URL}/admin/blogs.php",
                       data={"csrf": row.select_one('input[name="csrf"]').get("value"),
                             "id": row.select_one('input[name="id"]').get("value"),
                             "action": "delete"})
        assert_clean(r, "blog delete")
        assert title not in r.text, "archived blog still in admin active list"
        # gone from public
        home = public.get(f"{BASE_URL}/")
        assert title not in home.text, "archived blog still visible publicly"
        # still present in DB with status archived (soft delete)
        out = db_exec("SELECT status FROM blogs WHERE title_mr='%s'" % title)
        assert out == "archived", f"expected DB row status 'archived', got '{out}' (hard delete?)"

    # ---------- Admin content: event ----------
    def test_14_create_event_and_verify_public(self, admin, public):
        title = f"TEST_कार्यक्रम_{RUN}"
        page = admin.get(f"{BASE_URL}/admin/events.php")
        data = form_payload(page.text, 'form[enctype="multipart/form-data"]')
        data.update({
            "csrf": csrf_of(page.text),
            "action": "save",
            "title_mr": title,
            "description_mr": "चाचणी कार्यक्रम",
            "event_date": time.strftime("%Y-%m-%d", time.localtime(time.time() + 864000)),
            "event_time": "18:30",
            "venue": "चाचणी सभागृह",
            "status": "published",
        })
        r = admin.post(f"{BASE_URL}/admin/events.php", data=data)
        assert_clean(r, "event save")
        assert title in r.text, "created event not listed in admin"
        home = public.get(f"{BASE_URL}/")
        assert title in home.text, "published event missing from public homepage"

    # ---------- Admin settings ----------
    def test_15_settings_save_persists(self, admin, public):
        page = admin.get(f"{BASE_URL}/admin/settings.php")
        assert 'data-testid="settings-maintenance-toggle"' in page.text, "maintenance toggle missing"
        data = form_payload(page.text, 'form[enctype="multipart/form-data"]')
        original = data.get("tagline_mr", "")
        new_tagline = f"चाचणी टॅगलाइन {RUN}"
        data["csrf"] = csrf_of(page.text)
        data["tagline_mr"] = new_tagline
        data.pop("maintenance_mode", None)  # keep site live
        r = admin.post(f"{BASE_URL}/admin/settings.php", data=data)
        assert_clean(r, "settings save")
        reload_page = admin.get(f"{BASE_URL}/admin/settings.php")
        assert new_tagline in reload_page.text, "setting did not persist after reload"
        home = public.get(f"{BASE_URL}/")
        assert new_tagline in home.text, "saved setting not reflected on public site"
        # restore
        data["tagline_mr"] = original
        data["csrf"] = csrf_of(reload_page.text)
        admin.post(f"{BASE_URL}/admin/settings.php", data=data)

    # ---------- Member lifecycle (P0) ----------
    def test_16_member_registration(self, member_state):
        s = requests.Session()
        page = s.get(f"{BASE_URL}/member/register.php")
        soup = BeautifulSoup(page.text, "html.parser")
        type_opt = [o.get("value") for o in soup.select('select[name="membership_type_id"] option') if o.get("value")]
        assert type_opt, "no active membership type available for registration"
        data = {
            "csrf": csrf_of(page.text),
            "full_name": member_state["name"],
            "phone": "9876543210",
            "city": "पुणे",
            "email": member_state["email"],
            "password": member_state["password"],
            "membership_type_id": type_opt[0],
        }
        r = s.post(f"{BASE_URL}/member/register.php", data=data, allow_redirects=True)
        assert_clean(r, "member register")
        assert "registration-error" not in r.text, "registration returned an error"
        assert 'data-testid="member-dashboard-title"' in r.text, "not logged in after registration"
        assert member_state["email"] in r.text

    def test_17_member_login(self, member_state):
        s = requests.Session()
        page = s.get(f"{BASE_URL}/member/login.php")
        r = s.post(f"{BASE_URL}/member/login.php",
                   data={"csrf": csrf_of(page.text), "email": member_state["email"],
                         "password": member_state["password"]}, allow_redirects=True)
        assert_clean(r, "member login")
        assert 'data-testid="member-dashboard-title"' in r.text, "member login failed"
        member_state["session"] = s

    def test_18_member_submits_payment_with_utr(self, member_state):
        s = member_state.get("session")
        assert s is not None, "member session missing (login test must pass first)"
        dash = s.get(f"{BASE_URL}/member/index.php")
        assert 'data-testid="payment-utr-input"' in dash.text, "payment form not shown for new member"
        utr = f"UTR{RUN.upper()}0001"
        r = s.post(f"{BASE_URL}/member/index.php",
                   data={"csrf": csrf_of(dash.text, -1), "amount": "500", "utr": utr,
                         "payment_date": time.strftime("%Y-%m-%d")}, allow_redirects=True)
        assert_clean(r, "payment submit")
        assert "payment-error" not in r.text, "payment submission returned error"
        assert 'data-testid="payment-success"' in r.text, "no success notice after payment submit"
        assert utr in r.text, "submitted UTR not shown in payment history"
        assert "submitted" in r.text
        member_state["utr"] = utr

    def test_19_admin_sees_and_verifies_payment(self, admin, member_state):
        utr = member_state.get("utr")
        assert utr, "no UTR from member payment test"
        page = admin.get(f"{BASE_URL}/admin/payments.php?q={utr}")
        assert_clean(page, "admin payments search")
        assert utr in page.text, "pending payment not visible to admin"
        soup = BeautifulSoup(page.text, "html.parser")
        row = next((a for a in soup.select('[data-testid="admin-payment-row"]') if utr in a.get_text()), None)
        assert row is not None, "payment row not found"
        pid = row.select_one('input[name="id"]')
        assert pid is not None, "no verify form on pending payment row"
        r = admin.post(f"{BASE_URL}/admin/payments.php",
                       data={"csrf": row.select_one('input[name="csrf"]').get("value"),
                             "id": pid.get("value"), "action": "verify", "note": "QA verified"})
        assert_clean(r, "payment verify")
        assert 'data-testid="payment-action-success"' in r.text, "no confirmation after verify"
        after = admin.get(f"{BASE_URL}/admin/payments.php?q={utr}")
        assert "verified" in after.text

    def test_20_member_approved_and_card_renders(self, member_state):
        s = member_state.get("session")
        dash = s.get(f"{BASE_URL}/member/index.php")
        assert_clean(dash, "member dashboard after verification")
        assert "approved" in dash.text, "member status is not approved after payment verification"
        assert 'data-testid="member-card-link"' in dash.text, "membership card link not shown"
        card = s.get(f"{BASE_URL}/member/card.php")
        assert_clean(card, "membership card")
        assert re.search(r"[A-Z]{2}-\d{4}-\d{5}", card.text), "membership id not rendered on card"
        assert member_state["name"] in card.text or re.search(r"[\u0900-\u097F]", card.text)

    # ---------- Reports / CSV ----------
    def test_21_reports_page_and_csv_exports(self, admin):
        page = admin.get(f"{BASE_URL}/admin/reports.php")
        assert_clean(page, "reports page")
        for kind in ["members", "payments"]:
            r = admin.get(f"{BASE_URL}/admin/reports.php?export={kind}")
            assert r.status_code == 200, f"{kind} export -> {r.status_code}"
            assert "text/csv" in r.headers.get("Content-Type", ""), \
                f"{kind} export Content-Type = {r.headers.get('Content-Type')}"
            assert "attachment" in r.headers.get("Content-Disposition", "")
            body = r.content.decode("utf-8-sig")
            assert body.strip(), f"{kind} CSV empty"
            assert "," in body.splitlines()[0], f"{kind} CSV header malformed"

    # ---------- Soft delete member ----------
    def test_22_member_soft_delete_archives(self, admin, member_state):
        page = admin.get(f"{BASE_URL}/admin/members.php?q={member_state['email']}")
        assert_clean(page, "admin members search")
        assert member_state["email"] in page.text, "member not listed in admin"
        soup = BeautifulSoup(page.text, "html.parser")
        row = next((a for a in soup.select('[data-testid="member-row"]')
                    if member_state["email"] in a.get_text()), None)
        assert row is not None, "member row not found in admin list"
        assert row.find("button", attrs={"data-testid": "archive-member-button"}), \
            "archive button not available for the member"
        r = admin.post(f"{BASE_URL}/admin/members.php",
                       data={"csrf": row.select_one('input[name="csrf"]').get("value"),
                             "id": row.select_one('input[name="id"]').get("value"),
                             "action": "archive"})
        assert_clean(r, "member archive")
        def rows_for(status):
            resp = admin.get(f"{BASE_URL}/admin/members.php?q={member_state['email']}&status={status}")
            s = BeautifulSoup(resp.text, "html.parser")
            return [a for a in s.select('[data-testid="member-row"]')
                    if member_state["email"] in a.get_text()]

        assert not rows_for("approved"), "archived member still listed as approved"
        assert rows_for("archived"), "member hard-deleted instead of archived"
        # archived member must not be able to log in
        s = requests.Session()
        lp = s.get(f"{BASE_URL}/member/login.php")
        lr = s.post(f"{BASE_URL}/member/login.php",
                    data={"csrf": csrf_of(lp.text), "email": member_state["email"],
                          "password": member_state["password"]}, allow_redirects=True)
        assert 'data-testid="member-login-error"' in lr.text, "archived member can still log in"


class TestMaintenanceAndGallery:
    """Maintenance mode toggle + public gallery album page."""

    @pytest.fixture(scope="class")
    def admin_s(self):
        s = requests.Session()
        page = s.get(f"{BASE_URL}/admin/login.php")
        s.post(f"{BASE_URL}/admin/login.php",
               data={"csrf": csrf_of(page.text), "username": ADMIN_USER, "password": ADMIN_PASS})
        return s

    def test_23_maintenance_mode_toggle(self, admin_s):
        page = admin_s.get(f"{BASE_URL}/admin/settings.php")
        data = form_payload(page.text, 'form[enctype="multipart/form-data"]')
        data["csrf"] = csrf_of(page.text)
        data["maintenance_mode"] = "1"
        try:
            r = admin_s.post(f"{BASE_URL}/admin/settings.php", data=data)
            assert_clean(r, "settings save (maintenance on)")
            pub = requests.get(f"{BASE_URL}/")
            assert pub.status_code == 503, f"public site should return 503 in maintenance, got {pub.status_code}"
            assert "maintenance-page" in pub.text
            adm = admin_s.get(f"{BASE_URL}/admin/index.php")
            assert adm.status_code == 200, "admin must stay reachable during maintenance"
        finally:
            page2 = admin_s.get(f"{BASE_URL}/admin/settings.php")
            data2 = form_payload(page2.text, 'form[enctype="multipart/form-data"]')
            data2["csrf"] = csrf_of(page2.text)
            data2.pop("maintenance_mode", None)
            admin_s.post(f"{BASE_URL}/admin/settings.php", data=data2)
        assert requests.get(f"{BASE_URL}/").status_code == 200, "site not restored after disabling maintenance"

    def test_24_gallery_album_page(self, admin_s):
        page = admin_s.get(f"{BASE_URL}/admin/gallery.php")
        assert_clean(page, "admin gallery")
        title = f"TEST_अल्बम_{RUN}"
        data = form_payload(page.text, 'form[enctype="multipart/form-data"]')
        data.update({"csrf": csrf_of(page.text), "action": "create_album", "title_mr": title,
                     "description_mr": "चाचणी अल्बम", "status": "active"})
        r = admin_s.post(f"{BASE_URL}/admin/gallery.php", data=data)
        assert_clean(r, "album save")
        soup = BeautifulSoup(requests.get(f"{BASE_URL}/").text, "html.parser")
        link = next((a["href"] for a in soup.find_all("a", href=True) if "album.php" in a["href"]), None)
        if link:
            detail = requests.get(f"{BASE_URL}{link}")
            assert detail.status_code in (200, 404)
            assert not PHP_ERROR_RE.search(detail.text), "album page leaks PHP diagnostics"
