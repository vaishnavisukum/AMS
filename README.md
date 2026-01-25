# QR-Based Attendance Management System (AMS)

QR-powered attendance with student, faculty, and admin portals. Includes timetable sync, campus attendance, rotating QR security, and fair attendance rules.

## Environment & Dependencies

- Run on XAMPP (Apache + MySQL + PHP 7.4+) on Windows; place repo in `C:\xampp\htdocs\AMS-ai`
- MySQL 5.7+, PHP extensions: `mysqli` (DB), `gd` (QR), `openssl` (signing)
- Config files: `config/database.php` (DB creds), `config/config.php` (BASE_URL, SECRET_KEY, session)
- Keep Apache and MySQL running in XAMPP before use

## Quick Setup

1. Import DB: create `ams_db`, then import `database/schema.sql` (plus any migrations in `database/`).
2. Configure: update DB creds in `config/database.php`; set `BASE_URL` and a strong `SECRET_KEY` in `config/config.php`.
3. Start services: Apache + MySQL in XAMPP; open `http://localhost/AMS-ai/`.
4. Optional: run `php sync_database.php` to verify triggers and data integrity.

## Apply Migrations (recommended order)

If running manually:

- `database/schema.sql`
- `database/migration_add_lecture_date.sql`
- `database/migration_add_auto_absent.sql`
- `database/add_past_lectures.sql`
  Then run `php sync_database.php` to validate triggers and fill any missing sessions.

## Database Integration & Setup Module

- Core schema: import [database/schema.sql](database/schema.sql) first; then apply migrations in [database/](database) (e.g., migration_add_auto_absent.sql, migration_add_lecture_date.sql, add_past_lectures.sql) to stay in sync with production logic.
- Config: set DB credentials in [config/database.php](config/database.php) and base URL/secret in [config/config.php](config/config.php).
- Sync integrity: run `php sync_database.php` after migrations to rebuild missing sessions, fix status mismatches, and validate triggers.
- Attendance rule enforcement: all calculations rely on `attendance_sessions.status = 'completed'`; ensure timetable entries reflect actual held lectures before running reports.

## Entry Points

- Main site: http://localhost/AMS-ai/index.html
- Student registration: http://localhost/AMS-ai/student_signup.html
- Login path: index.html → login (role-based redirect to dashboards)

## Critical Attendance Rule (must-keep)

- Students are ONLY marked absent for lectures that were actually held (`attendance_sessions.status = 'completed'`).
- `not_started` or `cancelled` lectures never penalize students.
- Auto-absent runs when faculty ends a session (completed status).
- Verify anytime: `php verify_attendance_rule.php` (shows completed vs pending counts).

## Session Lifecycle

- not_started → scheduled, not counted
- active → scanning open, still not counted
- completed → counted; auto-absent for no-shows runs here
- cancelled → not counted

## QR Rules and Localhost Note

- QR rotates ~30s, signed, and bound to a single active session; validated server-side.
- On localhost, phone cannot reach PC directly; workaround: take a photo/screenshot of the QR from the faculty dashboard and hold it up to the Student Dashboard QR scanner on the same machine to scan.

## Manual Attendance Edits (faculty)

- Use modify attendance after ending a session (e.g., headcount mismatch).
- Edits are logged with who/what/when; keep reasons concise.

## Admin Quick Tasks

- Add faculty and manage subjects/timetable from admin dashboard.
- View below-75% list, attendance logs, and subject records filter (by subject/date/status).
- Campus attendance and statistics available under admin reporting.

## User Flows

### Student

1. Register or login → dashboard
2. Scan rotating QR during active session (camera permission required)
3. Attendance recorded server-side → view history and graphs

### Faculty

1. Login → schedule or start attendance for a subject (optionally from timetable)
2. Display QR (auto-rotates ~30s, signed and expiring)
3. End session → system marks no-shows absent; manual edits allowed via modify attendance
4. View lecture stats, attendance graphs, and subject records; export/filter where available

### Admin

1. Login → add faculty, manage subjects and timetable
2. Monitor below-75% students, subject records filter, campus attendance, logs
3. Review or export attendance statistics and history

## Feature Highlights

- Rotating, signed QR codes with expiry and replay protection
- Modify attendance (faculty) with audit trail
- Attendance graphs on dashboards (per subject and overall)
- Campus attendance auto-marked when a student attends any class
- Low-attendance list (default <75%) using completed sessions only
- Subject records filter (by subject/date/status) for admin/faculty
- Timetable ↔ attendance_sessions sync via triggers; prevents orphan records
- Manual and QR-based marking, headcount validation at session end

## System Architecture

- Tables: users, subjects, timetable, attendance_sessions, subject_attendance, campus_attendance, attendance_logs
- APIs (core):
  - Auth: `/api/login.php`, `/api/logout.php`, `/api/session_status.php`
  - Faculty: start/end attendance, get_qr, active_sessions, session_attendance, modify/mark manual (`api/faculty/*.php`)
  - Student: scan_qr, get_attendance_history
  - Admin: get_all_attendance, get_attendance_logs, get_statistics, get_low_attendance_students
  - Common: get_subjects, get_timetable, get_campus_qr

## Verification & Maintenance

- Verify rule: `php verify_attendance_rule.php` (completed vs pending lectures, student counts)
- Resync/validate DB: `php sync_database.php` (checks triggers, orphaned data, status mismatches)
- Backups: export DB regularly; keep `config/` secrets safe

## Default Credentials (change immediately)

- Admin: admin / password
- Faculty: faculty1 / password, faculty2 / password
- Students: student1 / password, student2 / password, student3 / password
- Change all after first login; update `SECRET_KEY` before production

## Security Checklist

- Force HTTPS in production; secure cookies via `config/config.php`
- Rotate `SECRET_KEY`; restrict config file permissions
- Disable error display in production; use prepared statements (already used)

## Troubleshooting (quick)

- Camera/QR issues: allow camera, ensure session active, QR not expired
- DB errors: confirm MySQL running, creds in `config/database.php`, DB imported
- Session issues: clear cookies, ensure correct BASE_URL, restart Apache/MySQL

## License

Educational use only.

---

Built with PHP, MySQL, HTML, CSS, JavaScript.
