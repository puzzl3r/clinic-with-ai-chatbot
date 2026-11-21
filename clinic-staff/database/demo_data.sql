USE clinic_scheduler;
-- DEMONSTRATION DATA ONLY. Every patient and appointment below is fictional.
-- Safe to import repeatedly: existing real records are preserved, demo patients are
-- matched by email, and demo appointments are matched by unique reference code.
-- Five example variables: branch, appointment date, appointment time, status, patient/booking volume.

DROP TEMPORARY TABLE IF EXISTS demo_digits;
CREATE TEMPORARY TABLE demo_digits (n INT PRIMARY KEY);
INSERT INTO demo_digits (n) VALUES (0),(1),(2),(3),(4),(5),(6),(7),(8),(9);

DROP TEMPORARY TABLE IF EXISTS demo_patient_map;
CREATE TEMPORARY TABLE demo_patient_map (
    demo_no INT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    phone VARCHAR(40) NOT NULL,
    patient_id INT NULL
);

INSERT INTO demo_patient_map (demo_no, email, full_name, phone)
SELECT
    t.n * 10 + u.n + 1,
    CONCAT('demo.patient', LPAD(t.n * 10 + u.n + 1, 3, '0'), '@example.test'),
    ELT(t.n * 10 + u.n + 1,
        'Alex Santos','Jamie Dela Cruz','Taylor Torres','Morgan Reyes','Casey Bautista',
        'Jordan Lim','Riley Cruz','Avery Villanueva','Cameron Tan','Skyler Garcia',
        'Drew Gonzales','Reese Navarro','Parker Mendoza','Quinn Aquino','Rowan Perez',
        'Emery Ramos','Sage Castillo','Finley Diaz','Dakota Flores','Harper Rivera',
        'Mika Santos','Paolo Dela Cruz','Tala Torres','Enzo Reyes','Lia Bautista',
        'Kai Lim','Nina Cruz','Theo Villanueva','Maya Tan','Luis Garcia',
        'Ella Gonzales','Rafa Navarro','Sofia Mendoza','Nico Aquino','Lea Perez',
        'Andrei Ramos','Isla Castillo','Marco Diaz','Luna Flores','Gab Rivera',
        'Ava Santos','Leo Dela Cruz','Ivy Torres','Eli Reyes','Zoe Bautista',
        'Sam Lim','Ana Cruz','Ben Villanueva','Mia Tan','Max Garcia',
        'Lily Gonzales','Ian Navarro','Rose Mendoza','Jack Aquino','Emma Perez',
        'Lucas Ramos','Chloe Castillo','Ethan Diaz','Grace Flores','Noah Rivera'
    ),
    CONCAT('+63917', LPAD(1000000 + (t.n * 10 + u.n + 1) * 7919, 7, '0'))
FROM demo_digits t
CROSS JOIN demo_digits u
WHERE t.n * 10 + u.n + 1 <= 60;

SET @demo_branch1 = (SELECT id FROM branches WHERE active = 1 ORDER BY sort_order, id LIMIT 1);
SET @demo_branch2 = (SELECT id FROM branches WHERE active = 1 ORDER BY sort_order, id LIMIT 1 OFFSET 1);
SET @demo_branch2 = IFNULL(@demo_branch2, @demo_branch1);

DROP TEMPORARY TABLE IF EXISTS demo_days;
CREATE TEMPORARY TABLE demo_days (
    day_no INT AUTO_INCREMENT PRIMARY KEY,
    appointment_date DATE NOT NULL UNIQUE
);

INSERT INTO demo_days (appointment_date)
SELECT DATE_ADD(CURDATE(), INTERVAL (t.n * 10 + u.n - 45) DAY) AS demo_date
FROM demo_digits t
CROSS JOIN demo_digits u
WHERE DAYOFWEEK(DATE_ADD(CURDATE(), INTERVAL (t.n * 10 + u.n - 45) DAY)) <> 1
ORDER BY demo_date
LIMIT 60;

DROP TEMPORARY TABLE IF EXISTS demo_appointments;
CREATE TEMPORARY TABLE demo_appointments (
    seed_no INT PRIMARY KEY,
    reference_code VARCHAR(40) NOT NULL UNIQUE,
    patient_id INT NULL,
    branch_id INT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    status VARCHAR(20) NOT NULL,
    notes VARCHAR(255) NOT NULL,
    UNIQUE KEY demo_branch_slot (branch_id, appointment_date, appointment_time)
);

INSERT INTO demo_appointments (
    seed_no, reference_code, patient_id, branch_id,
    appointment_date, appointment_time, status, notes
)
SELECT
    seed.seed_no,
    CONCAT('DEMO-AN-', LPAD(seed.seed_no, 5, '0')),
    NULL,
    CASE WHEN seed.position_in_day = 1 THEN @demo_branch2 ELSE @demo_branch1 END,
    seed.appointment_date,
    ELT(seed.slot_no,
        '09:00:00','09:30:00','10:00:00','10:30:00','11:00:00','11:30:00',
        '13:00:00','13:30:00','14:00:00','14:30:00','15:00:00','15:30:00'
    ),
    CASE
        WHEN seed.appointment_date < DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            THEN CASE WHEN seed.seed_no % 9 = 0 THEN 'cancelled' ELSE 'completed' END
        WHEN seed.appointment_date < CURDATE()
            THEN CASE seed.seed_no % 7
                WHEN 0 THEN 'cancelled'
                WHEN 1 THEN 'pending'
                WHEN 2 THEN 'confirmed'
                ELSE 'completed'
            END
        ELSE CASE WHEN seed.seed_no % 5 IN (0, 1) THEN 'pending' ELSE 'confirmed' END
    END,
    'Fictional seed record for analytics demonstration'
FROM (
    SELECT
        (d.day_no - 1) * 3 + j.n + 1 AS seed_no,
        d.day_no,
        d.appointment_date,
        j.n AS position_in_day,
        CASE j.n
            WHEN 0 THEN ELT(1 + ((d.day_no - 1) % 6), 3, 3, 2, 4, 3, 1)
            WHEN 1 THEN ELT(1 + ((d.day_no - 1) % 5), 2, 5, 2, 8, 2)
            ELSE 7 + ((d.day_no - 1) % 4)
        END AS slot_no
    FROM demo_days d
    CROSS JOIN demo_digits j
    WHERE j.n < 3
) AS seed;

START TRANSACTION;

INSERT INTO patients (full_name, email, phone)
SELECT m.full_name, m.email, m.phone
FROM demo_patient_map m
WHERE NOT EXISTS (SELECT 1 FROM patients p WHERE p.email = m.email);

UPDATE demo_patient_map m
SET m.patient_id = (SELECT MIN(p.id) FROM patients p WHERE p.email = m.email);

UPDATE demo_appointments a
JOIN demo_patient_map m ON m.demo_no = ((a.seed_no - 1) % 60) + 1
SET a.patient_id = m.patient_id;

INSERT INTO appointments (
    reference_code, patient_id, branch_id,
    appointment_date, appointment_time, status, notes
)
SELECT
    a.reference_code, a.patient_id, a.branch_id,
    a.appointment_date, a.appointment_time, a.status, a.notes
FROM demo_appointments a
WHERE a.patient_id IS NOT NULL
  AND a.branch_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM appointments x WHERE x.reference_code = a.reference_code
  )
ORDER BY a.seed_no;

COMMIT;

DROP TEMPORARY TABLE IF EXISTS demo_appointments;
DROP TEMPORARY TABLE IF EXISTS demo_days;
DROP TEMPORARY TABLE IF EXISTS demo_patient_map;
DROP TEMPORARY TABLE IF EXISTS demo_digits;
