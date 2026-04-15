-- ========================================================================
-- LINDA CHRONICLES — PHARMACY MANAGEMENT & INTERNSHIP SYSTEM
-- Complete Database Schema — Run this entire file in phpMyAdmin
-- ========================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

-- ── ALTER STOCK_REQUISITIONS TABLE ───────────────────────────────────────
-- Modify product_id to be nullable and update foreign key constraint
ALTER TABLE stock_requisitions DROP FOREIGN KEY stock_requisitions_ibfk_1;
ALTER TABLE stock_requisitions MODIFY product_id INT NULL;
ALTER TABLE stock_requisitions ADD CONSTRAINT stock_requisitions_ibfk_1 FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL;

-- ── ROLES ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS roles (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    role_name   VARCHAR(50) UNIQUE NOT NULL,
    description VARCHAR(255),
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO roles (id, role_name, description) VALUES
(1, 'Customer',             'Regular customer'),
(2, 'Intern',               'Pharmacy intern'),
(3, 'Pharmacist Assistant', 'Assistant to pharmacist'),
(4, 'Pharmacy Technician',  'Manages inventory and stock'),
(5, 'Pharmacist',           'Licensed pharmacist'),
(6, 'HR Personnel',         'Human resources personnel');

-- ── USERS ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    first_name  VARCHAR(50)  NOT NULL,
    middle_name VARCHAR(50),
    last_name   VARCHAR(50)  NOT NULL,
    email       VARCHAR(100) UNIQUE NOT NULL,
    password    VARCHAR(255) NOT NULL,
    role_id     INT NOT NULL,
    is_active   BOOLEAN DEFAULT TRUE,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample users (password: password123)
INSERT IGNORE INTO users (id, first_name, last_name, email, password, role_id) VALUES
(1, 'Juan',  'Customer',    'customer@pharmacy.local',    '$2y$10$u9lR4dHqnrMfT0.X5lVx..TdJ2mWzQh6mQvQ0pj3yL2T0F3Ql5Hra', 1),
(2, 'John',  'Intern',      'intern@pharmacy.local',      '$2y$10$u9lR4dHqnrMfT0.X5lVx..TdJ2mWzQh6mQvQ0pj3yL2T0F3Ql5Hra', 2),
(3, 'Ana',   'Assistant',   'assistant@pharmacy.local',   '$2y$10$u9lR4dHqnrMfT0.X5lVx..TdJ2mWzQh6mQvQ0pj3yL2T0F3Ql5Hra', 3),
(4, 'Mark',  'Technician',  'technician@pharmacy.local',  '$2y$10$u9lR4dHqnrMfT0.X5lVx..TdJ2mWzQh6mQvQ0pj3yL2T0F3Ql5Hra', 4),
(5, 'Mike',  'Pharmacist',  'pharmacist@pharmacy.local',  '$2y$10$u9lR4dHqnrMfT0.X5lVx..TdJ2mWzQh6mQvQ0pj3yL2T0F3Ql5Hra', 5),
(6, 'Sarah', 'HR',          'hr@pharmacy.local',          '$2y$10$u9lR4dHqnrMfT0.X5lVx..TdJ2mWzQh6mQvQ0pj3yL2T0F3Ql5Hra', 6);

-- ── MANUFACTURERS (must exist before products) ────────────────────────────
CREATE TABLE IF NOT EXISTS manufacturers (
    id                INT PRIMARY KEY AUTO_INCREMENT,
    manufacturer_name VARCHAR(150) NOT NULL,
    contact_person    VARCHAR(100),
    phone             VARCHAR(20),
    email             VARCHAR(100),
    address           VARCHAR(255),
    city              VARCHAR(100),
    country           VARCHAR(100),
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO manufacturers (id, manufacturer_name, contact_person, phone, email) VALUES
(1, 'PharmaCorp Inc.',   'Dr. Santos', '09171234567', 'sales@pharmacorp.com'),
(2, 'MediSupply Co.',    'Ms. Reyes',  '09281234567', 'orders@medisupply.com'),
(3, 'HealthPlus Pharma', 'Mr. Cruz',   '09391234567', 'supply@healthplus.com');

-- ── PRODUCTS (must exist before inventory_items, stock_requisitions) ──────
CREATE TABLE IF NOT EXISTS products (
    id               INT PRIMARY KEY AUTO_INCREMENT,
    product_code     VARCHAR(50)  UNIQUE NOT NULL,
    product_name     VARCHAR(150) NOT NULL,
    generic_name     VARCHAR(150),
    description      LONGTEXT,
    form             VARCHAR(50),
    pack_size        INT DEFAULT 1,
    category         VARCHAR(100),
    manufacturer_id  INT,
    unit_price       DECIMAL(10,2) DEFAULT 0.00,
    cost_price       DECIMAL(10,2) DEFAULT 0.00,
    current_stock    INT DEFAULT 0,
    reorder_level    INT DEFAULT 50,
    reorder_quantity INT DEFAULT 100,
    expiry_date      DATE,
    batch_number     VARCHAR(50),
    is_active        BOOLEAN DEFAULT TRUE,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (manufacturer_id) REFERENCES manufacturers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO products (id, product_code, product_name, generic_name, description, form, pack_size, category, manufacturer_id, unit_price, cost_price, current_stock, reorder_level) VALUES
(1, 'MED-001', 'Amoxicillin 500mg',  'Amoxicillin',   'Antibiotic capsule',           'Capsule', 100, 'Antibiotics',      1, 15.00, 8.00, 200, 50),
(2, 'MED-002', 'Paracetamol 500mg',  'Paracetamol',   'Pain reliever and fever reducer','Tablet', 500, 'Analgesics',       1,  5.00, 2.50, 500,100),
(3, 'MED-003', 'Metformin 500mg',    'Metformin HCl', 'Oral diabetes medicine',        'Tablet',  100, 'Antidiabetics',    2, 12.00, 6.00, 150, 50),
(4, 'MED-004', 'Losartan 50mg',      'Losartan',      'Blood pressure medication',     'Tablet',  100, 'Antihypertensives',2, 18.00, 9.00, 100, 30),
(5, 'MED-005', 'Cetirizine 10mg',    'Cetirizine',    'Antihistamine for allergies',   'Tablet',  100, 'Antihistamines',   3,  8.00, 4.00, 300, 80);

-- ── PROCESS 1: INTERNSHIP SUBMISSIONS ────────────────────────────────────
CREATE TABLE IF NOT EXISTS internship_submissions (
    id                       INT PRIMARY KEY AUTO_INCREMENT,
    user_id                  INT NOT NULL,
    school_name              VARCHAR(150) NOT NULL,
    course                   VARCHAR(100) NOT NULL,
    year_level               VARCHAR(20),
    student_id_number        VARCHAR(50),
    phone_number             VARCHAR(20),
    contact_email            VARCHAR(100),
    coe_file                 VARCHAR(255),
    resume_file              VARCHAR(255),
    cover_letter_file        VARCHAR(255),
    parent_consent_file      VARCHAR(255),
    medical_certificate_file VARCHAR(255),
    school_endorsement_file  VARCHAR(255),
    tor_file                 VARCHAR(255),
    moa_file                 VARCHAR(255),
    submission_date          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status                   ENUM('Pending','Under Review','Approved','Rejected') DEFAULT 'Pending',
    reviewed_by              INT,
    reviewed_date            DATETIME,
    rejection_reason         TEXT,
    remarks                  TEXT,
    FOREIGN KEY (user_id)     REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── PROCESS 2: HR POLICIES ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS hr_policies (
    id             INT PRIMARY KEY AUTO_INCREMENT,
    title          VARCHAR(200) NOT NULL,
    category       VARCHAR(50),
    description    LONGTEXT,
    attachment_file VARCHAR(255),
    version        INT DEFAULT 1,
    effective_date DATE,
    created_by     INT NOT NULL,
    updated_by     INT,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active      BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── PROCESS 4: INTERVIEW INVITATIONS ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS interview_invitations (
    id               INT PRIMARY KEY AUTO_INCREMENT,
    intern_id        INT NOT NULL,
    hr_personnel_id  INT NOT NULL,
    interview_date   DATETIME NOT NULL,
    interview_location VARCHAR(255),
    interview_notes  TEXT,
    status           ENUM('Pending','Accepted','Declined','Completed','Cancelled') DEFAULT 'Pending',
    acceptance_date  DATETIME,
    decline_reason   TEXT,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (intern_id)       REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (hr_personnel_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── PROCESS 5 & 6: INTERNSHIP SCHEDULES ──────────────────────────────────
CREATE TABLE IF NOT EXISTS internship_schedules (
    id                   INT PRIMARY KEY AUTO_INCREMENT,
    intern_id            INT NOT NULL,
    mentor_id            INT,
    start_date           DATE NOT NULL,
    end_date             DATE NOT NULL,
    schedule_type        ENUM('Full-Time','Part-Time','Flexible') DEFAULT 'Full-Time',
    work_hours_per_week  INT DEFAULT 40,
    location             VARCHAR(255),
    department           VARCHAR(100),
    daily_schedule       TEXT,
    status               ENUM('Scheduled','Ongoing','Completed','On Leave','Terminated') DEFAULT 'Scheduled',
    notes                TEXT,
    created_by           INT,
    created_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (intern_id)  REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (mentor_id)  REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── PROCESS 7: ORIENTATION SESSIONS ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS orientation_sessions (
    id                  INT PRIMARY KEY AUTO_INCREMENT,
    intern_id           INT NOT NULL,
    orientation_date    DATETIME NOT NULL,
    facilitator_id      INT,
    venue               VARCHAR(255),
    content             TEXT,
    materials_provided  TEXT,
    status              ENUM('Scheduled','Completed','Cancelled') DEFAULT 'Scheduled',
    intern_acknowledged BOOLEAN DEFAULT FALSE,
    acknowledged_at     DATETIME,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (intern_id)      REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (facilitator_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── PROCESS 8: INTERN TASKS ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS intern_tasks (
    id               INT PRIMARY KEY AUTO_INCREMENT,
    intern_id        INT NOT NULL,
    task_title       VARCHAR(200) NOT NULL,
    task_description TEXT,
    task_deadline    DATE,
    assigned_by      INT NOT NULL,
    status           ENUM('Pending','In Progress','Completed','Overdue') DEFAULT 'Pending',
    completion_notes TEXT,
    proof_file       VARCHAR(255),
    completed_at     DATETIME,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (intern_id)   REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── PROCESS 9: INVENTORY COUNTS ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS inventory_counts (
    id           INT PRIMARY KEY AUTO_INCREMENT,
    conducted_by INT NOT NULL,
    status       ENUM('In Progress','Completed','Cancelled') DEFAULT 'In Progress',
    notes        TEXT,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME,
    FOREIGN KEY (conducted_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── PROCESS 9: INVENTORY ITEMS ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS inventory_items (
    id           INT PRIMARY KEY AUTO_INCREMENT,
    inventory_id INT NOT NULL,
    product_id   INT NOT NULL,
    quantity     INT NOT NULL DEFAULT 0,
    batch_number VARCHAR(50),
    notes        TEXT,
    recorded_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (inventory_id) REFERENCES inventory_counts(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id)   REFERENCES products(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── PROCESS 10: INVENTORY REPORTS ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS inventory_reports (
    id                   INT PRIMARY KEY AUTO_INCREMENT,
    inventory_id         INT NOT NULL,
    total_items          INT DEFAULT 0,
    report_details       LONGTEXT,
    created_by           INT NOT NULL,
    created_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    verification_status  ENUM('Pending','Verified','Rejected') DEFAULT 'Pending',
    verified_by          INT,
    verified_at          DATETIME,
    remarks              TEXT,
    FOREIGN KEY (inventory_id) REFERENCES inventory_counts(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by)   REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (verified_by)  REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── PROCESS 12 & 13: STOCK REQUISITIONS ──────────────────────────────────
CREATE TABLE IF NOT EXISTS stock_requisitions (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    product_id      INT,
    quantity_needed INT NOT NULL,
    reason          TEXT,
    requested_by    INT NOT NULL,
    status          ENUM('Pending','Approved','Rejected','PO Generated') DEFAULT 'Pending',
    verified_by     INT,
    verified_at     DATETIME,
    remarks         TEXT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id)   REFERENCES products(id) ON DELETE SET NULL,
    FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (verified_by)  REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS purchase_orders (
    id                     INT PRIMARY KEY AUTO_INCREMENT,
    po_number              VARCHAR(50) UNIQUE,
    requisition_id         INT,
    supplier_id            INT,
    created_by             INT NOT NULL,
    po_date                TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    required_delivery_date DATE,
    actual_delivery_date   DATE,
    total_amount           DECIMAL(12,2),
    delivery_address       TEXT,
    notes                  TEXT,
    status                 ENUM('Draft','Sent','Acknowledged','Delivered','Cancelled') DEFAULT 'Draft',
    FOREIGN KEY (requisition_id) REFERENCES stock_requisitions(id) ON DELETE SET NULL,
    FOREIGN KEY (supplier_id)    REFERENCES manufacturers(id)      ON DELETE SET NULL,
    FOREIGN KEY (created_by)     REFERENCES users(id)              ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── PROCESS 15: PRESCRIPTIONS ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS prescriptions (
    id                  INT PRIMARY KEY AUTO_INCREMENT,
    customer_id         INT NOT NULL,
    prescription_image  VARCHAR(255) NOT NULL,
    patient_name        VARCHAR(150),
    doctor_name         VARCHAR(150),
    doctor_license      VARCHAR(50),
    prescription_date   DATE,
    upload_date         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expiry_date         DATE,
    status              ENUM('Pending','Verified','Approved','Rejected','Expired','Dispensed') DEFAULT 'Pending',
    verified_by         INT,
    verified_date       DATETIME,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── PROCESS 17: DISPENSED MEDICINES ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS dispensed_medicines (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    prescription_id INT NOT NULL,
    product_id      INT NOT NULL,
    quantity        INT NOT NULL,
    dispensed_by    INT NOT NULL,
    dispensed_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    payment_status  ENUM('Pending','Verified') DEFAULT 'Pending',
    notes           TEXT,
    FOREIGN KEY (prescription_id) REFERENCES prescriptions(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id)      REFERENCES products(id) ON DELETE RESTRICT,
    FOREIGN KEY (dispensed_by)    REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── PROCESS 18: PAYMENTS ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS payments (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    prescription_id INT NOT NULL,
    customer_id     INT NOT NULL,
    amount          DECIMAL(10,2) NOT NULL,
    payment_method  ENUM('Credit Card','Debit Card','Cash','Bank Transfer') NOT NULL,
    transaction_id  VARCHAR(100),
    status          ENUM('Pending','Completed','Failed','Refunded') DEFAULT 'Pending',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    paid_at         DATETIME,
    FOREIGN KEY (prescription_id) REFERENCES prescriptions(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id)     REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
