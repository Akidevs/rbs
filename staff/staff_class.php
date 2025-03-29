<?php
class Staff {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }

    private function getRandomStaffId() {
        $stmt = $this->conn->query("
            SELECT id FROM users 
            WHERE role = 'staff' 
            ORDER BY RAND() 
            LIMIT 1
        ");
        return $stmt->fetchColumn();
    }
    public function checkStaffLogin() {
        if (!isset($_SESSION['id'])) {
            header("Location: ../login.php");
            exit();
        }
    }

    public function verifyCsrfToken($token) {
        if (!hash_equals($_SESSION['csrf_token'], $token)) {
            throw new Exception("Invalid CSRF token");
        }
    }

    public function generateCsrfToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    // Assignment Handling
    public function getPendingAssignments() {
        $query = "SELECT 
                    aa.id AS assignment_id,
                    r.id AS rental_id,
                    p.name AS product_name,
                    u.name AS owner_name
                FROM admin_assignments aa
                JOIN rentals r ON aa.rental_id = r.id
                JOIN products p ON r.product_id = p.id
                JOIN users u ON r.owner_id = u.id
                WHERE aa.status = 'pending'
                AND r.status IN ('approved', 'waiting_admin_acceptance')";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function acceptAssignment($assignmentId, $staffId) {
        $this->conn->beginTransaction();
        try {
            $stmt = $this->conn->prepare("SELECT rental_id FROM admin_assignments WHERE id = ?");
            $stmt->execute([$assignmentId]);
            $rentalId = $stmt->fetchColumn();
    
            if (!$rentalId) throw new Exception("Assignment not found");
    
            $stmt = $this->conn->prepare("
                UPDATE admin_assignments 
                SET admin_id = ?, status = 'accepted' 
                WHERE id = ?
            ");
            $stmt->execute([$staffId, $assignmentId]);
    
            $stmt = $this->conn->prepare("
            UPDATE rentals 
            SET status = 'handed_over_to_admin' 
            WHERE id = ?
        ");
        $stmt->execute([$rentalId]);
    
            $stmt = $this->conn->prepare("
                UPDATE admin_assignments 
                SET status = 'rejected' 
                WHERE rental_id = ? AND id != ?
            ");
            $stmt->execute([$rentalId, $assignmentId]);
    
            $this->conn->commit();
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw new Exception("Assignment acceptance failed: " . $e->getMessage());
        }
    }

    // Rental Management
    public function getResponsibleDevices($staffId) {
        $sql = "SELECT r.id AS rental_id, p.id AS product_id, p.name AS product_name, p.brand, p.image, 
                       CONCAT(u_owner.first_name, ' ', u_owner.last_name) AS owner_name,
                       CONCAT(u_renter.first_name, ' ', u_renter.last_name) AS renter_name,
                       r.start_date, r.end_date, r.status, sa.assigned_at
                FROM staff_assignments sa
                JOIN rentals r ON sa.rental_id = r.id
                JOIN products p ON r.product_id = p.id
                JOIN users u_owner ON r.owner_id = u_owner.id
                JOIN users u_renter ON r.renter_id = u_renter.id
                WHERE sa.staff_id = :staff_id 
                AND sa.status = 'accepted'";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':staff_id', $staffId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    

    public function updateDeviceStatus($productId, $newStatus, $staffId) {
        $allowedStatuses = ['available', 'rented', 'under_maintenance'];
        if (!in_array($newStatus, $allowedStatuses)) {
            throw new Exception("Invalid status");
        }

        $sql = "UPDATE products SET status = :status 
                WHERE id = :product_id AND id IN (
                    SELECT p.id FROM admin_handovers ah
                    JOIN rentals r ON ah.rental_id = r.id
                    JOIN products p ON r.product_id = p.id
                    WHERE ah.admin_id = :staff_id
                )";
                
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':status' => $newStatus,
            ':product_id' => $productId,
            ':staff_id' => $staffId
        ]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception("Update failed or unauthorized");
        }
    }

    // Pickup Confirmations
    public function getSpecificRental($rentalId, $staffId) {
        $sql = "SELECT r.*, 
                       p.name AS product_name, 
                       p.image, 
                       p.rental_period,
                       p.overdue_price,
                       owner.name AS owner_name, 
                       renter.name AS renter_name
                FROM rentals r
                JOIN products p ON r.product_id = p.id
                JOIN users owner ON r.owner_id = owner.id
                JOIN users renter ON r.renter_id = renter.id
                JOIN staff_assignments sa ON r.id = sa.rental_id
                WHERE r.id = :rental_id
                AND sa.staff_id = :staff_id
                AND sa.status = 'accepted'";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':rental_id' => $rentalId,
            ':staff_id' => $staffId
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // UI Helpers
    public function getStatusBadgeClass($status) {
        $classes = [
            'picked_up' => 'bg-primary',
            'active' => 'bg-success',
            'pending_return' => 'bg-warning',
            'returned' => 'bg-secondary',
            'overdue' => 'bg-danger'
        ];
        return $classes[$status] ?? 'bg-secondary';
    }

    public function getRentalQueue() {
        $query = "SELECT r.*, p.name AS product_name, u.name AS renter_name 
                  FROM rentals r
                  JOIN products p ON r.product_id = p.id
                  JOIN users u ON r.renter_id = u.id
                  WHERE r.status IN ('pending_confirmation', 'approved', 'ready_for_pickup')
                  ORDER BY r.created_at DESC";
        return $this->conn->query($query)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getReturnQueue() {
        $query = "SELECT r.*, p.name AS product_name, u.name AS renter_name 
                  FROM rentals r
                  JOIN products p ON r.product_id = p.id
                  JOIN users u ON r.renter_id = u.id
                  WHERE r.status = 'pending_return'
                  ORDER BY r.end_date ASC";
        return $this->conn->query($query)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getOverdueRentals() {
        $query = "SELECT r.*, p.name AS product_name, u.name AS renter_name 
                  FROM rentals r
                  JOIN products p ON r.product_id = p.id
                  JOIN users u ON r.renter_id = u.id
                  WHERE r.status = 'overdue' 
                  OR (r.end_date < CURDATE() AND r.returned_date IS NULL)
                  ORDER BY r.end_date ASC";
        return $this->conn->query($query)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countTransactions($staffId) {
        $query = "SELECT COUNT(*) FROM admin_handovers WHERE admin_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$staffId]);
        return $stmt->fetchColumn();
    }

    public function getDailyRevenue() {
        $query = "SELECT SUM(total_cost) AS revenue 
                FROM rentals 
                WHERE DATE(created_at) = CURDATE()";
        $result = $this->conn->query($query);
        return $result->fetchColumn() ?? 0;
    }

    public function formatStatus($status) {
        $statusMap = [
            'pending_confirmation' => 'Pending Confirmation',
            'approved' => 'Approved',
            'ready_for_pickup' => 'Ready for Pickup',
            'picked_up' => 'Picked Up',
            'returned' => 'Returned',
            'overdue' => 'Overdue',
            'pending_return' => 'Pending Return'
        ];
        return $statusMap[$status] ?? ucwords(str_replace('_', ' ', $status));
    }

    public function calculateOverdueFee($rentalId) {
        $query = "SELECT DATEDIFF(CURDATE(), end_date) * overdue_price 
                FROM rentals r
                JOIN products p ON r.product_id = p.id
                WHERE r.id = ? AND end_date < CURDATE()";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$rentalId]);
        return $stmt->fetchColumn() ?? 0;
    }



    public function getAllProducts($filters) {
        $conditions = [];
        $params = [];
        
        // Add your existing filter logic here
        if (!empty($filters['status'])) {
            $conditions[] = "p.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['category'])) {
            $conditions[] = "p.category = ?";
            $params[] = $filters['category'];
        }
        
        if (!empty($filters['search'])) {
            $conditions[] = "(p.name LIKE ? OR p.brand LIKE ? OR u.name LIKE ?)";
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
        }
        
        // Include products that have been handed over to this staff member
        $conditions[] = "(p.id IN (
            SELECT product_id FROM rentals r
            JOIN staff_assignments sa ON r.id = sa.rental_id
            WHERE sa.staff_id = ? AND sa.status = 'accepted'
        ) OR p.status = 'available')";
        $params[] = $_SESSION['id'];
        
        $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
        
        $sql = "
            SELECT p.*, u.name as owner_name, 
                   (SELECT r.id FROM rentals r WHERE r.product_id = p.id AND r.status = 'handed_over_to_admin' LIMIT 1) as active_rental_id
            FROM products p
            LEFT JOIN users u ON p.owner_id = u.id
            $whereClause
            ORDER BY 
                CASE WHEN active_rental_id IS NOT NULL THEN 1 ELSE 2 END, 
                p.created_at DESC
        ";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    
    public function getProductCategories() {
        $query = "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_NAME = 'products' AND COLUMN_NAME = 'category'";
        $result = $this->conn->query($query)->fetch(PDO::FETCH_ASSOC);
        preg_match("/enum\((.*)\)/", $result['COLUMN_TYPE'], $matches);
        return array_map(function($val) { 
            return trim($val, "'"); 
        }, explode(',', $matches[1]));
    }

    public function countUserRentals($userId) {
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM rentals WHERE renter_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetchColumn();
    }

    public function obfuscateEmail($email) {
        $parts = explode("@", $email);
        if (count($parts) !== 2) return '*****';
        return substr($parts[0], 0, 2) . str_repeat('*', strlen($parts[0]) - 2) . '@' . $parts[1];
    }

    public function getAssignedRentals($staffId) {
        $query = "SELECT r.*, p.name AS product_name 
                FROM rentals r
                JOIN products p ON r.product_id = p.id
                WHERE r.admin_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$staffId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function processCheckIn($rentalId, $staffId, $conditionNotes, $photo) {
        $this->conn->beginTransaction();
        try {
            // Update rental status
            $stmt = $this->conn->prepare("UPDATE rentals SET 
                status = 'returned', returned_date = NOW()
                WHERE id = ?");
            $stmt->execute([$rentalId]);

            // Record condition
            $photoPath = $this->uploadConditionPhoto($photo);
            $stmt = $this->conn->prepare("INSERT INTO gadget_conditions 
                (product_id, owner_id, condition_description, photo)
                SELECT product_id, owner_id, ?, ? FROM rentals WHERE id = ?");
            $stmt->execute([$conditionNotes, $photoPath, $rentalId]);

            // Update product status
            $stmt = $this->conn->prepare("UPDATE products 
                SET status = 'available' 
                WHERE id = (SELECT product_id FROM rentals WHERE id = ?)");
            $stmt->execute([$rentalId]);

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Check-in error: " . $e->getMessage());
            return false;
        }
    }

    private function uploadConditionPhoto($file) {
        $uploadDir = '../uploads/condition_photos/';
        $filename = uniqid() . '_' . basename($file['name']);
        move_uploaded_file($file['tmp_name'], $uploadDir . $filename);
        return $uploadDir . $filename;
    }


    public function getDeviceDetails($productId) {
        $stmt = $this->conn->prepare("
            SELECT p.*, u.name AS owner_name 
            FROM products p
            JOIN users u ON p.owner_id = u.id
            WHERE p.id = ?
        ");
        $stmt->execute([$productId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getPendingHandovers($staffId) {
        $query = "SELECT 
                    sa.id AS assignment_id,
                    r.id AS rental_id,
                    p.name AS product_name,
                    u_owner.name AS owner_name
                FROM staff_assignments sa
                JOIN rentals r ON sa.rental_id = r.id
                JOIN products p ON r.product_id = p.id
                JOIN users u_owner ON r.owner_id = u_owner.id
                WHERE sa.status = 'pending'
                AND r.status IN ('approved', 'waiting_admin_acceptance')";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function acceptHandover($assignmentId, $rentalId, $staffId) {
        $this->conn->beginTransaction();
        try {
            // Update staff assignment
            $stmt = $this->conn->prepare("
                UPDATE staff_assignments 
                SET staff_id = :staff_id, 
                    status = 'accepted', 
                    assigned_at = NOW() 
                WHERE id = :assignment_id
            ");
            $stmt->execute([
                ':staff_id' => $staffId,
                ':assignment_id' => $assignmentId
            ]);
    
            // Update rental status
            $stmt = $this->conn->prepare("
                UPDATE rentals 
                SET status = 'handed_over_to_admin' 
                WHERE id = :rental_id
            ");
            $stmt->execute([':rental_id' => $rentalId]);
    
            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw new Exception("Failed to accept handover: " . $e->getMessage());
        }
    }



    public function getApprovedProducts($staffId, array $filters = []) {
        $sql = "SELECT 
                    p.*, 
                    u.name AS owner_name,
                    (SELECT id FROM rentals WHERE product_id = p.id AND status NOT IN ('returned','cancelled') LIMIT 1) AS active_rental_id
                FROM products p
                JOIN users u ON p.owner_id = u.id
                WHERE p.status = 'available'";
        
        // Remove the line that references p.approved_by
        // AND p.approved_by = :staff_id
        
        $params = [];
        
        // Add filters
        if (!empty($filters['status'])) {
            $sql .= " AND p.status = :status";
            $params[':status'] = $filters['status'];
        }
        
        if (!empty($filters['category'])) {
            $sql .= " AND p.category = :category";
            $params[':category'] = $filters['category'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (p.name LIKE :search OR p.brand LIKE :search)";
            $params[':search'] = "%{$filters['search']}%";
        }
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

  
}