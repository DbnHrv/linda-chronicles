<?php
/**
 * ProcessModel.php
 * Database operations for all 18 processes
 */

class ProcessModel {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // ── PROCESS 1 ──────────────────────────────────────────────
    public function getInternshipSubmissionByUserId($user_id) {
        $stmt = $this->pdo->prepare("SELECT * FROM internship_submissions WHERE user_id=? ORDER BY submission_date DESC LIMIT 1");
        $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getAllInternshipSubmissions() {
        $stmt = $this->pdo->prepare("
            SELECT s.*, u.first_name, u.last_name, u.email
            FROM internship_submissions s
            JOIN users u ON s.user_id = u.id
            ORDER BY s.submission_date DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── PROCESS 2 ──────────────────────────────────────────────
    public function getHRPolicies() {
        $stmt = $this->pdo->prepare("SELECT * FROM hr_policies WHERE is_active=1 ORDER BY created_at DESC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addHRPolicy($title, $description, $category = null) {
        $stmt = $this->pdo->prepare("
            INSERT INTO hr_policies (title,description,category,created_by,is_active)
            VALUES (?,?,?,?,1)
        ");
        return $stmt->execute([$title,$description,$category,$_SESSION['user_id']]);
    }

    // ── PROCESS 3 ──────────────────────────────────────────────
    public function reviewInternshipSubmission($submission_id, $status, $remarks = '') {
        $stmt = $this->pdo->prepare("
            UPDATE internship_submissions
            SET status=?, remarks=?, reviewed_by=?, reviewed_date=NOW()
            WHERE id=?
        ");
        return $stmt->execute([$status,$remarks,$_SESSION['user_id'],$submission_id]);
    }

    // ── PROCESS 4 ──────────────────────────────────────────────
    public function scheduleInterview($intern_id, $interview_date, $location = '') {
        $stmt = $this->pdo->prepare("
            INSERT INTO interview_invitations (intern_id,hr_personnel_id,interview_date,interview_location,status)
            VALUES (?,?,?,?,'Pending')
        ");
        return $stmt->execute([$intern_id,$_SESSION['user_id'],$interview_date,$location]);
    }

    public function getInterviewsByHR() {
        $stmt = $this->pdo->prepare("
            SELECT ii.*, u.first_name, u.last_name, u.email
            FROM interview_invitations ii
            JOIN users u ON ii.intern_id = u.id
            ORDER BY ii.interview_date DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getInterviewsByIntern($intern_id) {
        $stmt = $this->pdo->prepare("
            SELECT ii.*, u.first_name AS hr_first, u.last_name AS hr_last
            FROM interview_invitations ii
            JOIN users u ON ii.hr_personnel_id = u.id
            WHERE ii.intern_id=?
            ORDER BY ii.interview_date DESC
        ");
        $stmt->execute([$intern_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── PROCESS 5 & 6 ──────────────────────────────────────────
    public function createSchedule($intern_id, $start_date, $end_date, $data = []) {
        $stmt = $this->pdo->prepare("
            INSERT INTO internship_schedules
            (intern_id,start_date,end_date,schedule_type,work_hours_per_week,location,department,daily_schedule,notes,created_by)
            VALUES (?,?,?,?,?,?,?,?,?,?)
        ");
        return $stmt->execute([
            $intern_id, $start_date, $end_date,
            $data['schedule_type'] ?? 'Full-Time',
            $data['work_hours_per_week'] ?? 40,
            $data['location'] ?? '',
            $data['department'] ?? '',
            $data['daily_schedule'] ?? '',
            $data['notes'] ?? '',
            $_SESSION['user_id']
        ]);
    }

    public function getAllSchedules() {
        $stmt = $this->pdo->prepare("
            SELECT s.*, u.first_name, u.last_name
            FROM internship_schedules s
            JOIN users u ON s.intern_id = u.id
            ORDER BY s.created_at DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getScheduleByIntern($intern_id) {
        $stmt = $this->pdo->prepare("SELECT * FROM internship_schedules WHERE intern_id=? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$intern_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ── PROCESS 7 ──────────────────────────────────────────────
    public function createOrientationSession($intern_id, $orientation_date, $venue = '', $content = '') {
        $stmt = $this->pdo->prepare("
            INSERT INTO orientation_sessions (intern_id,orientation_date,facilitator_id,venue,content,status)
            VALUES (?,?,?,?,?,'Scheduled')
        ");
        return $stmt->execute([$intern_id,$orientation_date,$_SESSION['user_id'],$venue,$content]);
    }

    public function getAllOrientations() {
        $stmt = $this->pdo->prepare("
            SELECT o.*, u.first_name, u.last_name
            FROM orientation_sessions o
            JOIN users u ON o.intern_id = u.id
            ORDER BY o.orientation_date DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── PROCESS 8 ──────────────────────────────────────────────
    public function assignTask($intern_id, $title, $description, $deadline = null) {
        $stmt = $this->pdo->prepare("
            INSERT INTO intern_tasks (intern_id,task_title,task_description,task_deadline,assigned_by,status)
            VALUES (?,?,?,?,?,'Pending')
        ");
        return $stmt->execute([$intern_id,$title,$description,$deadline,$_SESSION['user_id']]);
    }

    public function getTasksByIntern($intern_id) {
        $stmt = $this->pdo->prepare("
            SELECT t.*, u.first_name AS assigned_by_name
            FROM intern_tasks t
            JOIN users u ON t.assigned_by = u.id
            WHERE t.intern_id=?
            ORDER BY t.created_at DESC
        ");
        $stmt->execute([$intern_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllTasks() {
        $stmt = $this->pdo->prepare("
            SELECT t.*, u.first_name, u.last_name
            FROM intern_tasks t
            JOIN users u ON t.intern_id = u.id
            ORDER BY t.created_at DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── PROCESS 9 ──────────────────────────────────────────────
    public function startInventoryCount($notes = '') {
        $stmt = $this->pdo->prepare("
            INSERT INTO inventory_counts (conducted_by,status,notes) VALUES (?,'In Progress',?)
        ");
        $stmt->execute([$_SESSION['user_id'],$notes]);
        return $this->pdo->lastInsertId();
    }

    public function addInventoryItem($inventory_id, $product_id, $quantity, $batch_number = '') {
        $stmt = $this->pdo->prepare("
            INSERT INTO inventory_items (inventory_id,product_id,quantity,batch_number) VALUES (?,?,?,?)
        ");
        return $stmt->execute([$inventory_id,$product_id,$quantity,$batch_number]);
    }

    public function completeInventoryCount($inventory_id) {
        $stmt = $this->pdo->prepare("UPDATE inventory_counts SET status='Completed',completed_at=NOW() WHERE id=?");
        return $stmt->execute([$inventory_id]);
    }

    public function getInventoryCountsByUser($user_id) {
        $stmt = $this->pdo->prepare("SELECT * FROM inventory_counts WHERE conducted_by=? ORDER BY created_at DESC");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── PROCESS 10 ─────────────────────────────────────────────
    public function createInventoryReport($inventory_id, $total_items, $details = '') {
        $stmt = $this->pdo->prepare("
            INSERT INTO inventory_reports (inventory_id,total_items,report_details,created_by,verification_status)
            VALUES (?,?,?,?,'Pending')
        ");
        $stmt->execute([$inventory_id,$total_items,$details,$_SESSION['user_id']]);
        return $this->pdo->lastInsertId();
    }

    public function getInventoryReports() {
        $stmt = $this->pdo->prepare("
            SELECT r.*, u.first_name, u.last_name, ic.notes AS count_notes
            FROM inventory_reports r
            JOIN users u ON r.created_by = u.id
            JOIN inventory_counts ic ON r.inventory_id = ic.id
            ORDER BY r.created_at DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── PROCESS 11 ─────────────────────────────────────────────
    public function verifyInventoryReport($report_id, $status, $remarks = '') {
        $stmt = $this->pdo->prepare("
            UPDATE inventory_reports
            SET verification_status=?, verified_by=?, verified_at=NOW(), remarks=?
            WHERE id=?
        ");
        return $stmt->execute([$status,$_SESSION['user_id'],$remarks,$report_id]);
    }

    // ── PROCESS 12 ─────────────────────────────────────────────
    public function createStockRequisition($product_id, $quantity, $reason = '') {
        $stmt = $this->pdo->prepare("
            INSERT INTO stock_requisitions (product_id,quantity_needed,reason,requested_by,status)
            VALUES (?,?,?,?,'Pending')
        ");
        return $stmt->execute([$product_id,$quantity,$reason,$_SESSION['user_id']]);
    }

    public function getRequisitionsByUser($user_id) {
        $stmt = $this->pdo->prepare("
            SELECT sr.*, p.product_name, p.product_code
            FROM stock_requisitions sr
            JOIN products p ON sr.product_id = p.id
            WHERE sr.requested_by=?
            ORDER BY sr.created_at DESC
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── PROCESS 13 ─────────────────────────────────────────────
    public function getPendingRequisitions() {
        $stmt = $this->pdo->prepare("
            SELECT sr.*, p.product_name, p.product_code, p.current_stock,
                   u.first_name, u.last_name
            FROM stock_requisitions sr
            JOIN products p ON sr.product_id = p.id
            JOIN users u ON sr.requested_by = u.id
            WHERE sr.status='Pending'
            ORDER BY sr.created_at DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function verifyStockRequisition($requisition_id, $status, $remarks = '') {
        $stmt = $this->pdo->prepare("
            UPDATE stock_requisitions SET status=?, verified_by=?, verified_at=NOW(), remarks=? WHERE id=?
        ");
        return $stmt->execute([$status,$_SESSION['user_id'],$remarks,$requisition_id]);
    }

    // ── PROCESS 14 ─────────────────────────────────────────────
    public function getApprovedRequisitions() {
        $stmt = $this->pdo->prepare("
            SELECT sr.*, p.product_name, p.product_code, p.unit_price,
                   u.first_name, u.last_name
            FROM stock_requisitions sr
            JOIN products p ON sr.product_id = p.id
            JOIN users u ON sr.requested_by = u.id
            WHERE sr.status='Approved'
            ORDER BY sr.created_at DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPurchaseOrders() {
        $stmt = $this->pdo->prepare("
            SELECT po.*, sr.product_id, p.product_name, u.first_name, u.last_name
            FROM purchase_orders po
            LEFT JOIN stock_requisitions sr ON po.requisition_id = sr.id
            LEFT JOIN products p ON sr.product_id = p.id
            JOIN users u ON po.created_by = u.id
            ORDER BY po.po_date DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── PROCESS 15 ─────────────────────────────────────────────
    public function getPrescriptionsByCustomer($customer_id) {
        $stmt = $this->pdo->prepare("SELECT * FROM prescriptions WHERE customer_id=? ORDER BY upload_date DESC");
        $stmt->execute([$customer_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllPendingPrescriptions() {
        $stmt = $this->pdo->prepare("
            SELECT p.*, u.first_name, u.last_name
            FROM prescriptions p
            JOIN users u ON p.customer_id = u.id
            WHERE p.status='Pending'
            ORDER BY p.upload_date DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── PROCESS 16 ─────────────────────────────────────────────
    public function checkProductAvailability($product_id, $quantity_needed) {
        $stmt = $this->pdo->prepare("SELECT id, product_name, current_stock FROM products WHERE id=? AND is_active=1");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$product) return ['available'=>false,'quantity'=>0,'product_name'=>''];
        return [
            'available'    => $product['current_stock'] >= $quantity_needed,
            'quantity'     => $product['current_stock'],
            'product_name' => $product['product_name']
        ];
    }

    public function getVerifiedPrescriptions() {
        $stmt = $this->pdo->prepare("
            SELECT p.*, u.first_name, u.last_name
            FROM prescriptions p
            JOIN users u ON p.customer_id = u.id
            WHERE p.status IN ('Verified','Approved')
            ORDER BY p.upload_date DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── PROCESS 17 ─────────────────────────────────────────────
    public function dispenseMedicine($prescription_id, $product_id, $quantity) {
        // Check stock
        $stmt = $this->pdo->prepare("SELECT current_stock FROM products WHERE id=?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$product || $product['current_stock'] < $quantity) {
            throw new Exception('Insufficient stock available');
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO dispensed_medicines (prescription_id,product_id,quantity,dispensed_by)
            VALUES (?,?,?,?)
        ");
        $stmt->execute([$prescription_id,$product_id,$quantity,$_SESSION['user_id']]);

        // Deduct stock
        $stmt = $this->pdo->prepare("UPDATE products SET current_stock=current_stock-? WHERE id=?");
        $stmt->execute([$quantity,$product_id]);

        // Mark prescription as dispensed
        $stmt = $this->pdo->prepare("UPDATE prescriptions SET status='Dispensed' WHERE id=?");
        $stmt->execute([$prescription_id]);

        return true;
    }

    // ── PROCESS 18 ─────────────────────────────────────────────
    public function getDispensedPrescriptions($customer_id) {
        $stmt = $this->pdo->prepare("
            SELECT p.*, SUM(dm.quantity) AS total_dispensed,
                   GROUP_CONCAT(pr.product_name SEPARATOR ', ') AS medicines
            FROM prescriptions p
            LEFT JOIN dispensed_medicines dm ON p.id = dm.prescription_id
            LEFT JOIN products pr ON dm.product_id = pr.id
            WHERE p.customer_id=? AND p.status='Dispensed'
            GROUP BY p.id
            ORDER BY p.upload_date DESC
        ");
        $stmt->execute([$customer_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createPayment($prescription_id, $amount, $payment_method) {
        $stmt = $this->pdo->prepare("
            INSERT INTO payments (prescription_id,customer_id,amount,payment_method,status)
            VALUES (?,?,?,?,'Pending')
        ");
        $stmt->execute([$prescription_id,$_SESSION['user_id'],$amount,$payment_method]);
        return $this->pdo->lastInsertId();
    }

    public function getPaymentsByCustomer($customer_id) {
        $stmt = $this->pdo->prepare("
            SELECT pay.*, pr.patient_name, pr.doctor_name
            FROM payments pay
            JOIN prescriptions pr ON pay.prescription_id = pr.id
            WHERE pay.customer_id=?
            ORDER BY pay.created_at DESC
        ");
        $stmt->execute([$customer_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLastInsertId() {
        return $this->pdo->lastInsertId();
    }
}
?>
