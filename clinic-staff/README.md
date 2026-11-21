# CarePoint Clinic Adjustment Scheduler

A PHP + MySQL final-project prototype for a clinic adjustment appointment workflow. The public website intentionally offers only:
1. **Adjustment with Main Branch**
2. **Adjustment with Lapu Lapu Branch**

## Features
- Responsive public booking page and branch selection.
- Date and 30-minute appointment slot selection.
- Patient details validation, unique booking reference, and pending status on submission.
- MySQL persistence for patients, branches, admins, and appointments.
- Staff login, appointment status management, patient directory, and dashboard.
- Dedicated Descriptive, Predictive, and Prescriptive analytics pages in the staff navigation, with filters, charts, CSV exports, and a session action plan.
- Descriptive analytics: booking totals, status breakdown, daily activity, branch comparison, weekday demand, and slot heatmap.
- Predictive analytics: weekday-baseline demand forecast with trend adjustment, planning range, scenario slider, and backtest fit.
- Prescriptive analytics: rule-based scheduling recommendations, capacity planner, pending confirmation queue, and dismiss/undo action plan.
- Prepared SQL statements, HTML escaping, CSRF protection on forms, password hashing, and session regeneration at login.

## Requirements
- PHP 8.1+ (PDO MySQL and mbstring extensions recommended)
- MySQL 8+ or MariaDB
- XAMPP, WAMP, Laragon, or another PHP/MySQL environment
- Internet connection for Google Fonts and Chart.js CDN (the website still works without fonts; charts require Chart.js)

## Setup with XAMPP
1. Extract the `clinic-adjustment-scheduler` folder into `C:\\xampp\\htdocs\\`.
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. Open `http://localhost/phpmyadmin`.
4. Import `database/schema.sql`. It creates the `clinic_scheduler` database and its tables.
5. Optional: import `database/demo_data.sql` after `schema.sql` to populate fictional demonstration records for analytics. The seed is idempotent: it preserves existing records, matches demo patients by email, and skips demo appointment references that already exist. The demo data is not real patient data.
6. Open `config/config.php` and set your local database values. Default XAMPP values are:
   - `DB_HOST`: `127.0.0.1`
   - `DB_NAME`: `clinic_scheduler`
   - `DB_USER`: `root`
   - `DB_PASS`: empty string
7. Visit `http://localhost/clinic-adjustment-scheduler/`.

The default `BASE_URL` is `/clinic-adjustment-scheduler`, matching the folder name in the steps above. If you rename the folder, update `BASE_URL` in `config/config.php` to match. If installed at the web root, set it to an empty string.

## Demo admin login
- Username: `admin`
- Password: `password`

**Important:** This is a classroom demo credential. Change the password hash before deploying publicly. To generate a new hash, run:
```php
<?php echo password_hash('YOUR_NEW_PASSWORD', PASSWORD_DEFAULT);
```
Then update `admins.password_hash` for your admin account. Do not use demo data or demo credentials with real patient information.

## Appointment rules in this prototype
- Clinic hours: 9:00 AM to 4:00 PM, Monday through Saturday.
- Appointments are 30 minutes.
- 12:00 PM and 12:30 PM are blocked for lunch.
- Each branch has its own available slots.
- New requests start as `pending`; staff can mark them `confirmed`, `completed`, or `cancelled`.
- The sample app prevents most double-bookings by checking slot availability, but a production system should also enforce concurrency-safe slot reservations at the database level and include stronger audit logging, email notifications, privacy notices, backups, and role-based access controls.

## Business Analytics case study
**Problem background:** Clinic staff need to understand appointment demand at two locations and make better decisions about appointment confirmation, staffing, and slot availability.

**Suggested five variables / dimensions to collect (minimum 50 records each):**
1. Branch (categorical)
2. Appointment date (date/time)
3. Appointment time slot (categorical/time)
4. Appointment status (categorical)
5. Patient / booking record (record identifier and booking volume)

The optional `demo_data.sql` provides 60 fictional patients and 180 appointment records so you can demonstrate five fields with at least 50 rows. For your final report, explain that this is synthetic data, not actual clinic records. If your instructor expects data from a real business, obtain permission and replace the demo data with anonymized, approved records.

### Descriptive analytics
The **Descriptive** page summarizes what has happened: total bookings, pending/confirmed/completed/cancelled counts, daily booking and completion trends, branch comparison, weekday demand, and an appointment-slot heatmap. Period and branch filters apply to the charts, record table, and CSV export.

### Predictive analytics
The **Predictive** page estimates upcoming demand using weekday averages from the prior 56 days of non-cancelled bookings, a capped recent-trend adjustment, and an optional scenario shift. It shows a planning range, branch outlook, daily forecast versus already-booked appointments, and a holdout backtest fit. This is a transparent classroom baseline, not a guaranteed prediction or clinical forecast.

### Prescriptive analytics
The **Prescriptive** page turns current data into rule-based recommendations: clear pending queues, follow up aging requests, reduce cancellations, protect peak-slot coverage, balance branch demand, and watch projected capacity. Staff can add actions to a session plan, mark them complete, dismiss with undo, confirm upcoming pending requests, model staffed slot capacity, and export the action plan. Recommendations support human decisions and never change clinic hours automatically.

## Suggested presentation flow
1. Explain the clinic scheduling problem.
2. Demonstrate selecting each of the two branches and booking an appointment.
3. Show how the new record appears in the admin appointment list.
4. Change a pending appointment to confirmed.
5. Walk through the Descriptive page charts, slot heatmap, and record table.
6. Explain the Predictive page weekday baseline, scenario slider, planning range, and backtest limitations.
7. Explain the Prescriptive page recommendations, capacity planner, and session action plan.
8. Show the five variables and demonstrate that the sample dataset has more than 50 records per variable.

## Project structure
```text
clinic-adjustment-scheduler/
├── admin/                  # Login, dashboard, appointments, patients, logout
├── api/slots.php            # Available appointment slots endpoint
├── assets/css/style.css     # Responsive blue-and-white UI
├── assets/js/app.js         # Slot refresh and reference copy
├── config/                  # DB connection and shared helpers
├── database/schema.sql      # Schema and initial branches/admin
├── database/demo_data.sql   # Optional fictional seed records
├── includes/                # Shared header/footer
├── book.php                 # Booking workflow
└── index.php                # Public branch selection
```
