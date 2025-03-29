<?php
class ExtendedStaff {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }

    // Authentication & Security
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

    // Rental Data Retrieval
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

    // Status Management
    public function updateRentalStatus($rentalId, $newStatus, $staffId) {
        $allowedStatuses = ['ready_for_pickup','picked_up', 'active', 'returned', 'overdue', 'pending_return'];
        if (!in_array($newStatus, $allowedStatuses)) {
            throw new Exception("Invalid status");
        }

        $currentStatus = $this->getCurrentRentalStatus($rentalId);
        if (!$this->isValidStatusTransition($currentStatus, $newStatus)) {
            throw new Exception("Invalid status transition from $currentStatus to $newStatus");
        }

        try {
            $this->conn->beginTransaction();
            $sql = "UPDATE rentals SET 
                        status = :status,
                        admin_id = :staff_id,
                        updated_at = NOW()
                    WHERE id = :rental_id";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':status' => $newStatus,
                ':staff_id' => $staffId,
                ':rental_id' => $rentalId
            ]);
            
            if ($newStatus === 'picked_up') {
                $stmt = $this->conn->prepare("
                    UPDATE rentals SET 
                        handover_date = NOW(),
                        end_date = DATE_ADD(NOW(), INTERVAL number_of_periods DAY)
                    WHERE id = ?
                ");
                $stmt->execute([$rentalId]);
            }

            $this->conn->commit();
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    // Financial Calculations
// In ExtendedStaff class, replace calculateRentalFees method with this:
public function calculateRentalFees($rentalId, $staffId) {
    $rental = $this->getSpecificRental($rentalId, $staffId);
    $product = $this->getDeviceDetails($rental['product_id']);
    
    $dailyRate = $this->calculateDailyRate(
        $rental['rental_price'],
        $product['rental_period']
    );
    
    $calculation = [
        'daily_rate' => $dailyRate,
        'number_of_periods' => $rental['number_of_periods'],
        'rent_cost_per_day' => $dailyRate
    ];

    if ($rental['status'] === 'ready_for_pickup') {
        // Only show basic agreement details
        $calculation['total_cost'] = $dailyRate * $rental['number_of_periods'];
    } 
    elseif ($rental['status'] === 'pending_return') {
        // Calculate actual usage up to pending_return status
        $start = new DateTime($rental['handover_date']);
        $end = $rental['returned_date'] ? new DateTime($rental['returned_date']) : new DateTime();
        $daysRented = $end->diff($start)->days;

        // Base cost is now based on actual days rented, not agreed periods
        $baseCost = $dailyRate * $daysRented;
        
        // Calculate overdue if exceeds original agreed period
        $overdueDays = max(0, $daysRented - $rental['number_of_periods']);
        $overdueFee = $overdueDays * $dailyRate * 1.5; // 150% fee

        $calculation['total_days_rented'] = $daysRented;
        $calculation['total_cost'] = $baseCost;
        $calculation['overdue_days'] = $overdueDays;
        $calculation['overdue_fee'] = $overdueFee;
        $calculation['total_calculated'] = $baseCost + $overdueFee;
    }

    return $calculation;
}

    public function calculateDailyRate($rentalPrice, $period) {
        $divisor = match($period) {
            'Week' => 7,
            'Month' => 30,
            default => 1
        };
        return $rentalPrice / $divisor;
    }

    // Receipt Handling
    public function generateReceipt($rentalId, $staffId, $fees) {
        try {
            $this->conn->beginTransaction();
            $rental = $this->getSpecificRental($rentalId, $staffId);
            
            $receiptStmt = $this->conn->prepare("
                INSERT INTO receipts 
                (rental_id, issued_by, total_amount, payment_status, context, created_at)
                VALUES (?, ?, ?, ?, 'return', NOW())
            ");
            $receiptStmt->execute([
                $rentalId,
                $staffId,
                $fees['total'],
                'pending'
            ]);
            
            $this->conn->commit();
            return $this->conn->lastInsertId();
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw new Exception("Receipt generation failed: " . $e->getMessage());
        }
    }

    public function saveReceipt($data, $staffId) {
        $this->conn->beginTransaction();
        try {
            $totalAmount = filter_var($data['total_amount'], FILTER_VALIDATE_FLOAT);
            if ($totalAmount === false || $totalAmount < 0) {
                throw new Exception("Invalid total amount");
            }
    
            // Allow total_amount = 0 only if payment_status is 'paid'
            if ($totalAmount == 0 && $data['payment_status'] !== 'paid') {
                throw new Exception("Total amount cannot be zero unless payment status is 'paid'");
            }
    
            if (empty($data['receipt_id'])) {
                $stmt = $this->conn->prepare("
                    INSERT INTO receipts 
                    (rental_id, issued_by, total_amount, payment_status, created_at)
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $data['rental_id'],
                    $staffId,
                    $totalAmount, 
                    $data['payment_status']
                ]);
                $receiptId = $this->conn->lastInsertId();
            } else {
                $stmt = $this->conn->prepare("
                    UPDATE receipts 
                    SET total_amount = ?, payment_status = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $totalAmount,
                    $data['payment_status'],
                    $data['receipt_id']
                ]);
                $receiptId = $data['receipt_id'];
            }
    
            $this->conn->commit();
            return $receiptId;
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }
    public function getRentalReceipt($rentalId) {
        $stmt = $this->conn->prepare("
            SELECT * FROM receipts 
            WHERE rental_id = ?
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$rentalId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getReceiptItems($receiptId) {
        $stmt = $this->conn->prepare("
            SELECT * FROM receipt_items 
            WHERE receipt_id = ?
        ");
        $stmt->execute([$receiptId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getReceiptItemTypes() {
        return [
            'base_rent', 'overdue', 'penalty', 'damage_fee',
            'extension', 'replacement', 'refund', 'partial_payment', 'other'
        ];
    }

    // Private helper methods
    private function getCurrentRentalStatus($rentalId) {
        $stmt = $this->conn->prepare("SELECT status FROM rentals WHERE id = :id");
        $stmt->execute([':id' => $rentalId]);
        return $stmt->fetchColumn();
    }

    private function isValidStatusTransition($current, $new) {
        $allowedTransitions = [
            'pending_confirmation' => ['ready_for_pickup'],
            'handed_over_to_admin' => ['ready_for_pickup'],
            'ready_for_pickup' => ['picked_up'],
            'picked_up' => ['active', 'returned', 'pending_return', 'overdue'],
            'active' => ['returned', 'overdue'],
            'overdue' => ['returned'],
            'pending_return' => ['returned']
        ];
        return isset($allowedTransitions[$current]) && 
               in_array($new, $allowedTransitions[$current]);
    }

    private function calculateOverdueDays($rentalId, $staffId) {
        $rental = $this->getSpecificRental($rentalId, $staffId);
        $endDate = new DateTime($rental['end_date'] ?: date('Y-m-d'));
        $returnDate = new DateTime($rental['returned_date'] ?: date('Y-m-d'));
        return $returnDate > $endDate ? $endDate->diff($returnDate)->days : 0;
    }
}
?>


