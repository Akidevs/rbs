<?php
require_once __DIR__ . '/../db/db.php';
class renter {
    public function generateCsrfToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    public function verifyCsrfToken($token) {
        if (empty($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }


    //Database Connection
    private $conn;
    private $userId;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    // Authentication check to ensure user is logged in and is an owner
    public function authenticateRenter() {
        if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'renter') {
            header("Location: /rb/login.php");
            exit();
        }
        $this->userId = $_SESSION['id'];
    }



    public function logError($message) {
        $log_file = __DIR__ . '/error_log.txt';
        $current_time = date('Y-m-d H:i:s');
        $formatted_message = "[{$current_time}] {$message}\n";
        file_put_contents($log_file, $formatted_message, FILE_APPEND);
    }





    //Browse Page
    // Search and paginate products excluding 'pending_confirmation' status
    public function searchProducts(
        string $searchTerm = '',
        int $perPage = 8,
        int $page = 1,
        bool $excludeOwnProducts = false,
        int $currentUserId = null
    ) {
        // Debug info
        error_log("searchProducts called with: searchTerm=$searchTerm, excludeOwnProducts=$excludeOwnProducts, currentUserId=$currentUserId");
        
        $offset = ($page - 1) * $perPage;
        
        // Start with a basic query
        $sql = "
            SELECT 
                p.*,
                COALESCE((
                    SELECT AVG(r.rating) 
                    FROM reviews r 
                    JOIN rentals rl ON r.rental_id = rl.id 
                    WHERE rl.product_id = p.id
                ), 0) AS average_rating,
                COALESCE((
                    SELECT COUNT(r.id) 
                    FROM reviews r 
                    JOIN rentals rl ON r.rental_id = rl.id 
                    WHERE rl.product_id = p.id
                ), 0) AS rating_count
            FROM products p
            WHERE (p.name LIKE :search OR p.description LIKE :search)
            AND p.status IN ('available', 'rented')
        ";
        
        $countSql = "
            SELECT COUNT(*) 
            FROM products p
            WHERE (p.name LIKE :search OR p.description LIKE :search)
            AND p.status IN ('available', 'rented')
        ";
    
        // Add the owner exclusion condition
        if ($excludeOwnProducts && $currentUserId !== null) {
            $sql .= " AND p.owner_id != :owner_id";
            $countSql .= " AND p.owner_id != :owner_id";
            error_log("Added exclusion condition for owner_id=$currentUserId");
        }
        
        $sql .= " ORDER BY p.created_at DESC LIMIT :limit OFFSET :offset";
        
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':search', '%' . $searchTerm . '%', PDO::PARAM_STR);
            
            if ($excludeOwnProducts && $currentUserId !== null) {
                $stmt->bindValue(':owner_id', $currentUserId, PDO::PARAM_INT);
            }
            
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            
            // Log the final SQL with parameters
            error_log("Final SQL: " . $sql);
            error_log("Parameters: search=" . '%' . $searchTerm . '%' . ", limit=$perPage, offset=$offset" . 
                     ($excludeOwnProducts && $currentUserId !== null ? ", owner_id=$currentUserId" : ""));
            
            $stmt->execute();
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Count query
            $countStmt = $this->conn->prepare($countSql);
            $countStmt->bindValue(':search', '%' . $searchTerm . '%', PDO::PARAM_STR);
            
            if ($excludeOwnProducts && $currentUserId !== null) {
                $countStmt->bindValue(':owner_id', $currentUserId, PDO::PARAM_INT);
            }
            
            $countStmt->execute();
            $totalProducts = $countStmt->fetchColumn();
            
            // Format the results
            $formatted = array_map(function($product) {
                return [
                    'id' => $product['id'],
                    'owner_id' => $product['owner_id'],
                    'name' => htmlspecialchars($product['name']),
                    'description' => htmlspecialchars($product['description']),
                    'rental_price' => number_format($product['rental_price'], 2),
                    'image' => $product['image'],
                    'average_rating' => round($product['average_rating'], 1),
                    'rating_count' => (int)$product['rating_count']
                ];
            }, $products);
            
            error_log("Found " . count($formatted) . " products, total pages: " . ceil($totalProducts / $perPage));
            
            return [
                'products' => $formatted,
                'totalPages' => ceil($totalProducts / $perPage)
            ];
            
        } catch (PDOException $e) {
            error_log("Search error: " . $e->getMessage());
            return false;
        }
    }





    //File Dispute Page
    // Fetch user's rentals for dispute selection
public function getRentalsForDispute($userId) {
    $stmt = $this->conn->prepare("SELECT r.id, p.name 
                                  FROM rentals r 
                                  JOIN products p ON r.product_id = p.id 
                                  WHERE r.renter_id = :userId");
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// File a dispute
public function fileDispute($userId, $rental_id, $reason, $description) {
    $stmt = $this->conn->prepare("INSERT INTO disputes (rental_id, initiated_by, reason, description) 
                                  VALUES (:rental_id, :initiated_by, :reason, :description)");
    $stmt->bindParam(':rental_id', $rental_id, PDO::PARAM_INT);
    $stmt->bindParam(':initiated_by', $userId, PDO::PARAM_INT);
    $stmt->bindParam(':reason', $reason, PDO::PARAM_STR);
    $stmt->bindParam(':description', $description, PDO::PARAM_STR);
    return $stmt->execute();
}



//Item Page
// Method to fetch product by ID
public function getProductById($productId) {
    $stmt = $this->conn->prepare("SELECT * FROM products WHERE id = ? AND status IN ('available', 'rented')");
    $stmt->execute([$productId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Method to add item to cart
public function addToCart($userId, $productId) {
    // Get period input from POST
    $periods = isset($_POST['rental_periods']) ? (int)$_POST['rental_periods'] : 1;
    
    // Validate periods
    if ($periods < 1) {
        return "Invalid rental duration";
    }

    // Check product availability
    $product = $this->getProductById($productId);
    if (!$product || $product['quantity'] < 1) {
        return "Product is currently unavailable.";
    }

    // Calculate dates
    $startDate = date('Y-m-d');
    $endDate = date('Y-m-d', strtotime("+$periods days"));

    // Check existing cart items
    $stmt = $this->conn->prepare("SELECT * FROM cart_items 
        WHERE renter_id = :userId AND product_id = :productId");
    $stmt->execute([':userId' => $userId, ':productId' => $productId]);
    
    if ($stmt->fetch()) {
        return "Item is already in your cart.";
    }

    // Insert with calculated dates and periods
    $stmt = $this->conn->prepare("INSERT INTO cart_items 
        (renter_id, product_id, start_date, end_date, number_of_periods, created_at, updated_at)
        VALUES (:userId, :productId, :startDate, :endDate, :periods, NOW(), NOW())");
    
    if ($stmt->execute([
        ':userId' => $userId,
        ':productId' => $productId,
        ':startDate' => $startDate,
        ':endDate' => $endDate,
        ':periods' => $periods
    ])) {
        return "Item added to cart successfully.";
    } else {
        return "Failed to add item to cart.";
    }
}

public function getCartItems($userId) {
    $sql = "SELECT c.*, p.name, p.image, p.rental_price, p.category, 
                   p.status, p.description, p.quantity, p.rental_period
            FROM cart_items c
            INNER JOIN products p ON c.product_id = p.id
            WHERE c.renter_id = :userId";
    $stmt = $this->conn->prepare($sql);
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $subtotal = 0;
    $allAvailable = true;
    foreach ($cartItems as $item) {
        if ($item['quantity'] < 1) {
            $allAvailable = false;
        }
        // Calculate cost based on stored periods
        $subtotal += $item['rental_price'] * $item['number_of_periods'];
    }

    return ['cartItems' => $cartItems, 'subtotal' => $subtotal, 'allAvailable' => $allAvailable];
}



//Profile Page
// Add these methods to your existing Renter class

public function getUserData($userId) {
    $sql = "SELECT id, first_name, last_name, email, role, created_at, profile_picture 
            FROM users 
            WHERE id = :user_id";
    $stmt = $this->conn->prepare($sql);
    $stmt->execute(['user_id' => $userId]);
    return $stmt->fetch();
}

public function getVerificationData($userId) {
    $stmt = $this->conn->prepare("SELECT * FROM user_verification WHERE user_id = :user_id");
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

public function updateProfilePicture($userId, $file) {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowedTypes)) {
        return false;
    }

    $uploadDir = '../uploads/profile_pictures/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $filename = time() . '_' . basename($file['name']);
    $destination = $uploadDir . $filename;

    if (move_uploaded_file($file['tmp_name'], $destination)) {
        $sql = "UPDATE users SET profile_picture = :picture WHERE id = :user_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            'picture' => 'uploads/profile_pictures/' . $filename,
            'user_id' => $userId
        ]);
        return 'uploads/profile_pictures/' . $filename;
    }

    return false;
}

// In renter_class.php

public function getRentals($renterId) {
    $stmt = $this->conn->prepare("
        SELECT r.*, 
               p.name AS product_name,
               p.image,
               p.brand,
               CONCAT_WS(' ', u.first_name, u.last_name) AS owner_name
        FROM rentals r
        JOIN products p ON r.product_id = p.id
        JOIN users u ON p.owner_id = u.id
        WHERE r.renter_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$renterId]);
    $rentals = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rentals as &$rental) {
        // Initialize remaining_days with default value
        $rental['remaining_days'] = 'N/A';
        
        // Handle date display
        $showDates = in_array($rental['status'], [
            'picked_up', 'completed', 'returned', 
            'overdue', 'pending_return'
        ]);
        $rental['display_start'] = $showDates ? $rental['start_date'] : 'N/A';
        $rental['display_end'] = $showDates ? $rental['end_date'] : 'N/A';

        // Use original values for calculations
        $originalEndDate = $rental['end_date'];
        $originalStatus = $rental['status'];

        // Calculate remaining days
        if (in_array($originalStatus, ['completed', 'returned'])) {
            $rental['remaining_days'] = 'Completed';
        } elseif ($originalStatus === 'cancelled') {
            $rental['remaining_days'] = 'Cancelled';
        } elseif (!empty($originalEndDate)) {
            try {
                $today = new DateTime('today');
                $endDate = new DateTime($originalEndDate);
                $endDate->setTime(0, 0, 0);
                
                $interval = $today->diff($endDate);
                $absoluteDays = $interval->days;

                if ($interval->invert) {
                    if ($originalStatus !== 'overdue' && !in_array($originalStatus, ['completed', 'returned'])) {
                        $updateStmt = $this->conn->prepare("
                            UPDATE rentals 
                            SET status = 'overdue', 
                                updated_at = NOW() 
                            WHERE id = ?
                        ");
                        $updateStmt->execute([$rental['id']]);
                        $rental['status'] = 'overdue';
                    }
                    $rental['remaining_days'] = 'Overdue by ' . $absoluteDays . ' day' . ($absoluteDays !== 1 ? 's' : '');
                } elseif ($absoluteDays > 0) {
                    $rental['remaining_days'] = $absoluteDays . ' day' . ($absoluteDays !== 1 ? 's left' : ' left');
                } else {
                    $rental['remaining_days'] = 'Due Today';
                }
            } catch (Exception $e) {
                // Handle invalid dates gracefully
                $rental['remaining_days'] = 'N/A';
            }
        }
    }
    unset($rental);

    return $rentals;
}

public function getStatusBadgeColor($status) {
    switch ($status) {
        case 'pending_confirmation': return 'warning';
        case 'approved': return 'primary';
        case 'delivery_in_progress': return 'info';
        case 'delivered': return 'info';
        case 'renting': return 'info';
        case 'completed': return 'success';
        case 'returned': return 'success';
        case 'cancelled': return 'danger';
        case 'overdue': return 'danger';
        default: return 'secondary';
    }
}

public function getRemainingDaysBadgeColor($remainingDays) {
    if (strpos($remainingDays, 'Overdue') === 0) return 'danger';
    if (strpos($remainingDays, 'day') !== false) return 'info';
    if ($remainingDays === 'Due Today') return 'warning';
    if ($remainingDays === 'Completed') return 'success';
    if ($remainingDays === 'Cancelled') return 'secondary';
    return 'secondary';
}



//Rental Details
// Get rental details
public function getRentalDetails($renterId, $rentalId) {
    try {
        $stmt = $this->conn->prepare("
            SELECT r.*, p.name AS product_name, p.brand, p.image, 
                   p.rental_period, p.rental_price, 
                   CONCAT_WS(' ', u.first_name, u.last_name) AS owner_name
            FROM rentals r
            INNER JOIN products p ON r.product_id = p.id
            INNER JOIN users u ON r.owner_id = u.id
            WHERE r.id = ? AND r.renter_id = ?
        ");
        $stmt->execute([$rentalId, $renterId]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Error fetching rental details: " . $e->getMessage());
        return false;
    }
}
public function getProofs($rentalId) {
    try {
        $stmt = $this->conn->prepare("
            SELECT p.* 
            FROM proofs p
            JOIN rentals r ON p.rental_id = r.id
            WHERE p.rental_id = :rental_id
            AND r.renter_id = :renter_id
            ORDER BY p.created_at DESC
        ");
        $stmt->execute([
            ':rental_id' => $rentalId,
            ':renter_id' => $this->userId
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $this->logError("getProofs Error: " . $e->getMessage());
        return [];
    }
}

// Fetch proofs


// Check feedback
public function checkFeedback($productId, $renterId) {
    try {
        $stmt = $this->conn->prepare("
            SELECT * FROM comments 
            WHERE product_id = ? AND renter_id = ?
        ");
        $stmt->execute([$productId, $renterId]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Error checking feedback: " . $e->getMessage());
        return false;
    }
}


public function hasReceivedFeedbackFromOwner($rentalId) {
    $stmt = $this->conn->prepare("
        SELECT id FROM renter_reviews 
        WHERE rental_id = ?
    ");
    $stmt->execute([$rentalId]);
    return $stmt->rowCount() > 0;
}

public function updateRentalStatus($rentalId, $status) {
    $stmt = $this->conn->prepare("
        UPDATE rentals 
        SET status = ?, updated_at = NOW()
        WHERE id = ?
    ");
    return $stmt->execute([$status, $rentalId]);
}


// Check owner review
public function checkOwnerReview($rentalId, $renterId) {
    try {
        $stmt = $this->conn->prepare("
            SELECT * FROM owner_reviews 
            WHERE rental_id = ? AND renter_id = ?
        ");
        $stmt->execute([$rentalId, $renterId]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Error checking owner review: " . $e->getMessage());
        return false;
    }
}

public function endRental($rentalId) {
    try {
        $this->conn->beginTransaction();
        
        // Freeze end date to today without changing status
        $stmt = $this->conn->prepare("
            UPDATE rentals 
            SET end_date = CURDATE(),
                updated_at = NOW()
            WHERE id = ? 
            AND renter_id = ?
            AND status = 'picked_up'
        ");
        $stmt->execute([$rentalId, $this->userId]);
        
        $this->conn->commit();
        
    } catch (Exception $e) {
        $this->conn->rollBack();
        throw new Exception("Failed to end rental: " . $e->getMessage());
    }
}


public function isStatusActive($statusKey, $currentStatus, $statusFlow) {
    $statusKeys = array_keys($statusFlow);
    $currentIndex = array_search($currentStatus, $statusKeys);
    $statusIndex = array_search($statusKey, $statusKeys);
    
    // Special case for return initiated
    if ($currentStatus === 'return_pending' && $statusKey === 'return_pending') {
        return true;
    }
    
    return $statusIndex <= $currentIndex;
}

//changepassword
public function changePassword($userId, $currentPassword, $newPassword) {
    // Verify current password
    $stmt = $this->conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!password_verify($currentPassword, $user['password'])) {
        return false;
    }
    
    // Update to new password
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $this->conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    return $stmt->execute([$hashedPassword, $userId]);
}


public function getRentalsByStatus($userId, $statuses = []) {
    $query = "SELECT r.*, p.name AS product_name, p.image AS product_image, 
                     u.name AS owner_name, DATEDIFF(r.end_date, CURDATE()) AS days_remaining 
              FROM rentals r
              JOIN products p ON r.product_id = p.id
              JOIN users u ON r.owner_id = u.id
              WHERE r.renter_id = ?";
    
    if (!empty($statuses)) {
        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
        $query .= " AND r.status IN ($placeholders)";
    }
    
    $query .= " ORDER BY r.created_at DESC";
    
    $stmt = $this->conn->prepare($query);
    $params = [$userId];
    if (!empty($statuses)) {
        $params = array_merge($params, $statuses);
    }
    $stmt->execute($params);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

}
?>