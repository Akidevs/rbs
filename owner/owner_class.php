<?php
require_once __DIR__ . '/../db/db.php';
class owner {

    //Database Connection
    private $conn;
    private $userId;

    public function __construct($conn) {
        $this->conn = $conn;

    }
    
    public function getUserId() {
        return $this->userId;
    }


    //From the previous Functions page
    // Generate a CSRF token and store it in the session
    public function generateCsrfToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    // Verify the provided CSRF token against the stored session token
    public function verifyCsrfToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    

    // Handle image uploads for owners
    public function handleImageUpload($file, $allowed_extensions, $max_file_size) {
        if ($file && $file['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $file['tmp_name'];
            $file_name = basename($file['name']);
            $file_size = $file['size'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            // Log detected file extension
            $this->logError("Detected file extension: " . $file_ext);

            if (!in_array($file_ext, $allowed_extensions)) {
                return ['success' => false, 'message' => 'Invalid image extension. Allowed extensions: jpg, jpeg, png, gif.'];
            }

            if ($file_size > $max_file_size) {
                return ['success' => false, 'message' => 'Image size exceeds the maximum allowed size of 2MB.'];
            }

            // Validate image type
            $image_info = getimagesize($file_tmp);
            if ($image_info === false) {
                return ['success' => false, 'message' => 'Uploaded file is not a valid image.'];
            }

            $new_filename = uniqid() . '.' . $file_ext;
            $upload_dir = __DIR__ . '/../img/uploads/';

            if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true)) {
                return ['success' => false, 'message' => 'Failed to create upload directory.'];
            }

            if (!is_writable($upload_dir)) {
                return ['success' => false, 'message' => 'Upload directory is not writable.'];
            }

            $destination = $upload_dir . $new_filename;

            if (move_uploaded_file($file_tmp, $destination)) {
                return ['success' => true, 'filename' => $new_filename];
            } else {
                return ['success' => false, 'message' => 'Failed to move uploaded file.'];
            }
        }

        return ['success' => false, 'message' => 'No image uploaded or there was an upload error.'];
    }

    // Log errors into a file
    public function logError($message) {
        $log_file = __DIR__ . '/error_log.txt';
        $current_time = date('Y-m-d H:i:s');
        $formatted_message = "[{$current_time}] {$message}\n";
        file_put_contents($log_file, $formatted_message, FILE_APPEND);
    }



    //All Reports Page
    // Authentication check to ensure user is logged in and is an owner
    public function authenticateOwner() {
        // Ensure the session is started
        if (session_status() === PHP_SESSION_NONE) {
            session_start(); // Add this line
        }
    
        // Check if user is logged in
        if (!isset($_SESSION['id'])) { // Fix syntax: Remove extra ')'
            header('Location: ../owner/login.php');
            exit();
        }
    
        // Assign owner_id from session
        $this->userId = $_SESSION['id']; // Critical line
    
        // Validate role
        if ($_SESSION['role'] !== 'owner') {
            header('Location: ../owner/login.php');
            exit();
        }
    }

    // Get total income data for monthly chart
    public function getMonthlyIncomeData($year) {
        $incomeData = array_fill(0, 12, 0);
        $stmt = $this->conn->prepare("SELECT MONTH(created_at) AS month, SUM(total_cost) AS total 
                                    FROM rentals 
                                    WHERE YEAR(created_at) = ? AND owner_id = ? AND status IN ('completed', 'returned')
                                    GROUP BY MONTH(created_at)");
        $stmt->execute([$year, $this->userId]);
        while ($row = $stmt->fetch()) {
            $incomeData[$row['month'] - 1] = $row['total'];
        }
        return $incomeData;
    }

    // Get weekly earnings for the current month
    public function getWeeklyIncomeData($month, $year) {
        $weeksData = array_fill(0, 4, 0);
        $stmt = $this->conn->prepare("SELECT WEEK(created_at, 1) - WEEK(DATE_FORMAT(created_at, '%Y-%m-01'), 1) + 1 AS week_number,
                                    SUM(total_cost) AS total
                            FROM rentals
                            WHERE MONTH(created_at) = ? AND YEAR(created_at) = ?
                            AND owner_id = ? AND status IN ('completed', 'returned')
                            GROUP BY week_number");
        $stmt->execute([$month, $year, $this->userId]);
        while ($row = $stmt->fetch()) {
            if ($row['week_number'] <= 4) {
                $weeksData[$row['week_number'] - 1] = $row['total'];
            }
        }
        return $weeksData;
    }

    // Get rental frequency by gadget category
    public function getRentalFrequency() {
        $stmt = $this->conn->prepare("SELECT p.category, COUNT(r.id) AS count 
                                    FROM rentals r 
                                    JOIN products p ON r.product_id = p.id 
                                    WHERE r.owner_id = ? AND r.status IN ('completed', 'returned')
                                    GROUP BY p.category");
        $stmt->execute([$this->userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get maintenance issues
    public function getMaintenanceIssues() {
        $stmt = $this->conn->prepare("SELECT p.name AS gadget, gc.condition_description AS issue_reported, gc.reported_at 
                                    FROM gadget_conditions gc
                                    JOIN products p ON gc.product_id = p.id
                                    WHERE p.owner_id = ?");
        $stmt->execute([$this->userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get transaction history
    public function getTransactionHistory() {
        $stmt = $this->conn->prepare("SELECT r.created_at AS date, p.name AS gadget, u.name AS renter, 
                                    r.total_cost AS amount, r.status AS payment_status
                                    FROM rentals r
                                    JOIN products p ON r.product_id = p.id
                                    JOIN users u ON r.renter_id = u.id
                                    WHERE p.owner_id = ?
                                    ORDER BY r.created_at DESC");
        $stmt->execute([$this->userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get gadget availability
    public function getGadgetAvailability() {
        $available = $this->conn->prepare("SELECT SUM(quantity) FROM products WHERE owner_id = ? AND status = 'approved'");
        $available->execute([$this->userId]);
        $available = $available->fetchColumn();

        $rented = $this->conn->prepare("SELECT COUNT(*) FROM rentals WHERE owner_id = ? AND status = 'renting'");
        $rented->execute([$this->userId]);
        $rented = $rented->fetchColumn();

        $inMaintenance = $this->conn->prepare("SELECT COUNT(DISTINCT product_id) FROM gadget_conditions gc
                                                JOIN products p ON gc.product_id = p.id WHERE p.owner_id = ?");
        $inMaintenance->execute([$this->userId]);
        $inMaintenance = $inMaintenance->fetchColumn();

        return [
            'available' => $available,
            'rented' => $rented,
            'inMaintenance' => $inMaintenance
        ];
    }

    // Get average ratings per category
    public function getRatings() {
        $stmt = $this->conn->prepare("SELECT p.category, AVG(c.rating) AS avg_rating
                                    FROM comments c
                                    JOIN products p ON c.product_id = p.id
                                    WHERE p.owner_id = ?
                                    GROUP BY p.category");
        $stmt->execute([$this->userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Calculate commission and net earnings
    public function calculateEarnings() {
        $totalEarnings = $this->conn->prepare("SELECT SUM(total_cost) FROM rentals WHERE status IN ('completed', 'returned') AND owner_id = ?");
        $totalEarnings->execute([$this->userId]);
        $totalEarnings = $totalEarnings->fetchColumn();

        $commissionRate = 0.10;
        $commission = $totalEarnings * $commissionRate;
        $netEarnings = $totalEarnings - $commission;

        return [
            'totalEarnings' => $totalEarnings,
            'commission' => $commission,
            'netEarnings' => $netEarnings
        ];
    }






//Dashboard Page

    // Fetch the user's name from the session
    public function getUserName() {
        $query = "SELECT name FROM users WHERE id = :userId";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['userId' => $this->userId]);
        $user = $stmt->fetch();
        return $user ? $user['name'] : 'User';
    }

    // Fetch the earnings for the month
    public function getTotalEarningsForMonth() {
        try {
            $earningsQuery = "SELECT SUM(total_cost) AS total_earnings FROM rentals WHERE MONTH(start_date) = MONTH(CURRENT_DATE) AND owner_id = :userId";
            $earningsStmt = $this->conn->prepare($earningsQuery);
            $earningsStmt->execute(['userId' => $this->userId]);
            $earnings = $earningsStmt->fetch();
            return $earnings['total_earnings'] ?? 0.00;
        } catch (Exception $e) {
            $this->logError("Earnings Fetch Error: " . $e->getMessage());
            return 0.00;
        }
    }


    // Fetch rental count for the logged-in owner
    public function getTotalRentals() {
        try {
            $rentalQuery = "SELECT COUNT(id) AS total_rentals FROM rentals WHERE status = 'approved' AND owner_id = :userId";
            $rentalStmt = $this->conn->prepare($rentalQuery);
            $rentalStmt->execute(['userId' => $this->userId]);
            $rentalData = $rentalStmt->fetch();
            return $rentalData['total_rentals'] ?? 0;
        } catch (Exception $e) {
            $this->logError("Rentals Fetch Error: " . $e->getMessage());
            return 0;
        }
    }


    // Fetch top earning gadgets for the logged-in owner
    public function getTopEarningGadgets() {
        try {
            $gadgetsQuery = "SELECT p.name, COUNT(r.id) AS rentals_count, p.image 
                            FROM products p 
                            JOIN rentals r ON p.id = r.product_id 
                            WHERE p.owner_id = :userId
                            GROUP BY p.name 
                            ORDER BY rentals_count DESC LIMIT 2";
            $gadgetsStmt = $this->conn->prepare($gadgetsQuery);
            $gadgetsStmt->execute(['userId' => $this->userId]);
            return $gadgetsStmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logError("Top Gadgets Fetch Error: " . $e->getMessage());
            return [];
        }
    }

    // Fetch listed gadgets for the logged-in owner
    public function getListedGadgets() {
        try {
            $productsQuery = "SELECT * FROM products WHERE owner_id = :userId";
            $productsStmt = $this->conn->prepare($productsQuery);
            $productsStmt->execute(['userId' => $this->userId]);
            return $productsStmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logError("Products Fetch Error: " . $e->getMessage());
            return [];
        }
    }


    // Fetch alerts for the user (you can customize this based on your needs)

    public function getAlerts() {
        $alertsQuery = "SELECT * FROM support_requests WHERE status = 'open' ORDER BY created_at DESC LIMIT 3";
        $alertsStmt = $this->conn->query($alertsQuery);
        return $alertsStmt->fetchAll(PDO::FETCH_ASSOC);
    }




    //File Dispute Page
    // Method to fetch rentals for the dispute page
    public function getRentalsForDispute() {
        $stmt = $this->conn->prepare("SELECT r.id, p.name, u.name AS renter_name
                                    FROM rentals r
                                    JOIN products p ON r.product_id = p.id
                                    JOIN users u ON r.renter_id = u.id
                                    WHERE r.owner_id = :userId");
        $stmt->bindParam(':userId', $this->userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Method to file a dispute
    public function fileDispute($rental_id, $reason, $description) {
        $errors = [];
        // Validate inputs
        if (empty($rental_id)) $errors[] = "Rental selection is required.";
        if (empty($reason)) $errors[] = "Reason is required.";
        if (empty($description)) $errors[] = "Description is required.";

        if (empty($errors)) {
            // Insert the dispute into the database
            $stmt = $this->conn->prepare("INSERT INTO disputes (rental_id, initiated_by, reason, description, status) 
                                        VALUES (:rental_id, :initiated_by, :reason, :description, 'open')");
            $stmt->bindParam(':rental_id', $rental_id, PDO::PARAM_INT);
            $stmt->bindParam(':initiated_by', $this->userId, PDO::PARAM_INT);
            $stmt->bindParam(':reason', $reason, PDO::PARAM_STR);
            $stmt->bindParam(':description', $description, PDO::PARAM_STR);

            if ($stmt->execute()) {
                $_SESSION['success'] = "Dispute filed successfully.";
            } else {
                $_SESSION['error'] = "Failed to file dispute.";
            }

            header('Location: file_dispute.php');
            exit();
        } else {
            $_SESSION['error'] = implode('<br>', $errors);
            header('Location: file_dispute.php');
            exit();
        }
    }


//add gadget

    public function handleAddProduct($data, $file) {
        try {
            if (!isset($data['csrf_token']) || !$this->verifyCsrfToken($data['csrf_token'])) {
                throw new Exception("CSRF token verification failed.");
            }
    
            // Retrieve and sanitize form data
            $name = trim($data['name']);
            $brand = trim($data['brand']);
            $description = trim($data['description']);
            $rental_price = floatval($data['rental_price']);
            
            // Validate rental period exactly
            $rental_period = isset($data['rental_period']) ? trim($data['rental_period']) : null;
            $valid_rental_periods = ['Day', 'Week', 'Month'];
            if (!in_array($rental_period, $valid_rental_periods)) {
                throw new Exception("Invalid rental period. Must be Day, Week, or Month.");
            }
    
            $category = trim($data['category']);
            $valid_categories = ['Mobile Phones', 'Laptops', 'Tablets', 'Cameras', 'Accessories', 'Gaming Consoles', 'Audio Devices', 'Drones'];
            if (!in_array($category, $valid_categories)) {
                throw new Exception("Invalid category.");
            }
    
            $quantity = intval($data['quantity']);
            $overdue_price = floatval($data['overdue_price']);  
            $real_price = floatval($data['real_price']);
            $condition_description = trim($data['condition_description']);
    
            // Detailed validation
            $errors = [];
            if (empty($name)) $errors[] = "Name is required";
            if (empty($brand)) $errors[] = "Brand is required";
            if (empty($description)) $errors[] = "Description is required";
            if ($rental_price <= 0) $errors[] = "Rental price must be positive";
            if ($quantity <= 0) $errors[] = "Quantity must be positive";
            if (empty($condition_description)) $errors[] = "Condition description is required";
    
            if (!empty($errors)) {
                throw new Exception("Validation failed: " . implode(", ", $errors));
            }
    
            $comes_with = trim($data['comes_with'] ?? '');
            $comes_with = ($comes_with === '') ? null : $comes_with;
    
            // Handle image upload (keep your existing image upload logic)
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            $max_file_size = 2 * 1024 * 1024;
            $image_filename = null;
            if (isset($file['image']) && $file['image']['error'] !== UPLOAD_ERR_NO_FILE) {
                $imageUpload = $this->handleImageUpload($file['image'], $allowed_extensions, $max_file_size);
                if ($imageUpload['success']) {
                    $image_filename = $imageUpload['filename'];
                } else {
                    throw new Exception("Image upload failed: " . htmlspecialchars($imageUpload['message']));
                }
            }
    
            // Insert into database (keep your existing insert logic)
            $owner_id = $_SESSION['id'];
            $stmt = $this->conn->prepare("INSERT INTO products 
            (owner_id, name, brand, comes_with, description, rental_price, rental_period, status, 
            created_at, updated_at, image, quantity, category, overdue_price, real_price, condition_description) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW(), ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $owner_id,          
                $name,             
                $brand,            
                $comes_with,        
                $description,       
                $rental_price,      
                $rental_period,    
                $image_filename,    
                $quantity,          
                $category,          
                $overdue_price,     
                $real_price,        
                $condition_description 
            ]);
    
            $_SESSION['success'] = "Product added successfully! Awaiting approval.";
            header("Location: gadget.php");
            exit();
        } catch (Exception $e) {
            $this->logError("Add Product Error: " . $e->getMessage());
            $_SESSION['error'] = $e->getMessage();
            header("Location: gadget.php");
            exit();
        }
    }

    // Handle Edit Product
    public function handleEditProduct($data, $file) {
        try {
            if (!isset($data['csrf_token']) || !$this->verifyCsrfToken($data['csrf_token'])) {
                throw new Exception("CSRF token verification failed.");
            }

            // Retrieve and sanitize form data
            $product_id = intval($data['product_id']);
            $name = trim($data['name']);
            $brand = trim($data['brand']);
            $description = trim($data['description']);
            $rental_price = floatval($data['rental_price']);
            $rental_period = isset($data['rental_period']) ? trim($data['rental_period']) : null;
            $category = trim($data['category']);
            $quantity = intval($data['quantity']);

            if (empty($name) || empty($brand) || empty($description) || $rental_price <= 0 || empty($rental_period) || empty($category) || $quantity <= 0) {
                throw new Exception("Validation failed: Missing or invalid fields.");
            }

            // Handle image upload
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            $max_file_size = 2 * 1024 * 1024;
            $image_filename = null;
            if (isset($file['image']) && $file['image']['error'] !== UPLOAD_ERR_NO_FILE) {
                $imageUpload = $this->handleImageUpload($file['image'], $allowed_extensions, $max_file_size);
                if ($imageUpload['success']) {
                    $image_filename = $imageUpload['filename'];
                } else {
                    $this->logError("Add Product Image Upload Error: " . $imageUpload['message']);
                    throw new Exception("Image upload failed: " . htmlspecialchars($imageUpload['message']));
                }
            }

            // Prepare SQL statement
            if ($image_filename) {
                $sql = "UPDATE products SET name = ?, brand = ?, description = ?, rental_price = ?, rental_period = ?, image = ?, quantity = ?, category = ?, status = 'pending', updated_at = NOW() WHERE id = ? AND owner_id = ?";
                $params = [$name, $brand, $description, $rental_price, $rental_period, $image_filename, $quantity, $category, $product_id, $_SESSION['id']];
            } else {
                $sql = "UPDATE products SET name = ?, brand = ?, description = ?, rental_price = ?, rental_period = ?, quantity = ?, category = ?, status = 'pending', updated_at = NOW() WHERE id = ? AND owner_id = ?";
                $params = [$name, $brand, $description, $rental_price, $rental_period, $quantity, $category, $product_id, $_SESSION['id']];
            }

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);

            $_SESSION['success'] = "Product updated successfully! Awaiting approval.";
            header("Location: gadget.php");
            exit();
        } catch (Exception $e) {
            $this->logError("Edit Product Error: " . $e->getMessage());
            $_SESSION['error'] = $e->getMessage();
            header("Location: gadget.php");
            exit();
        }
    }

    // Handle Delete Product
    public function handleDeleteProduct($data) {
        try {
            if (!isset($data['csrf_token']) || !$this->verifyCsrfToken($data['csrf_token'])) {
                throw new Exception("CSRF token verification failed.");
            }

            $product_id = intval($data['product_id']);
            $stmt = $this->conn->prepare("DELETE FROM products WHERE id = ? AND owner_id = ?");
            $stmt->execute([$product_id, $_SESSION['id']]);

            $_SESSION['success'] = "Product deleted successfully!";
            header("Location: gadget.php");
            exit();
        } catch (Exception $e) {
            $this->logError("Delete Product Error: " . $e->getMessage());
            $_SESSION['error'] = $e->getMessage();
            header("Location: gadget.php");
            exit();
        }
    }


    //Gadget Assessment Page
    // Handle gadget assessment
    public function handleGadgetAssessment($data, $file, $ownerId) {
        try {
            // CSRF token verification if needed
            if (!isset($data['csrf_token']) || !$this->verifyCsrfToken($data['csrf_token'])) {
                throw new Exception("CSRF token verification failed.");
            }

            // Sanitize and validate input
            $product_id = intval($data['product_id']);
            $condition_description = trim($data['condition_description']);

            if (empty($condition_description)) {
                throw new Exception("Condition description cannot be empty.");
            }

            // Handle file upload
            $photo = null;  // Photo is optional
            if (isset($file['photo']) && $file['photo']['error'] === UPLOAD_ERR_OK) {
                $fileTmpPath = $file['photo']['tmp_name'];
                $fileName = $file['photo']['name'];
                $fileSize = $file['photo']['size'];
                $fileType = $file['photo']['type'];
                $fileNameCmps = explode(".", $fileName);
                $fileExtension = strtolower(end($fileNameCmps));

                // Sanitize file name
                $newFileName = md5(time() . $fileName) . '.' . $fileExtension;

                // Check allowed file extensions
                $allowedfileExtensions = ['jpg', 'gif', 'png', 'jpeg'];
                if (in_array($fileExtension, $allowedfileExtensions)) {
                    // Directory for uploaded file
                    $uploadFileDir = '../uploads/gadget_conditions/';
                    $dest_path = $uploadFileDir . $newFileName;

                    if (!move_uploaded_file($fileTmpPath, $dest_path)) {
                        throw new Exception("There was an error uploading the file.");
                    } else {
                        $photo = $newFileName;
                    }
                } else {
                    throw new Exception("Upload failed. Allowed file types: " . implode(',', $allowedfileExtensions));
                }
            }

            // Insert into gadget_conditions table
            $stmt = $this->conn->prepare("INSERT INTO gadget_conditions (product_id, owner_id, condition_description, photo) VALUES (:product_id, :owner_id, :condition_description, :photo)");
            $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
            $stmt->bindParam(':owner_id', $ownerId, PDO::PARAM_INT);
            $stmt->bindParam(':condition_description', $condition_description, PDO::PARAM_STR);
            $stmt->bindParam(':photo', $photo, PDO::PARAM_STR);

            if ($stmt->execute()) {
                $_SESSION['success'] = "Gadget condition assessed successfully.";
            } else {
                $_SESSION['error'] = "Failed to assess gadget condition.";
            }

        } catch (Exception $e) {
            $this->logError("Gadget Assessment Error: " . $e->getMessage());
            $_SESSION['error'] = $e->getMessage();
        }

        header('Location: gadgets_assessment.php');
        exit();
    }

    // Fetch products for the owner
    public function getOwnerProducts($ownerId) {
        // Add error handling and filter by status (optional)
        try {
            $stmt = $this->conn->prepare("
                SELECT * 
                FROM products 
                WHERE owner_id = :ownerId 
                  AND status IN ('available', 'rented', 'under_maintenance') 
            ");
            $stmt->execute([':ownerId' => $ownerId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->logError("getOwnerProducts Error: " . $e->getMessage());
            return [];
        }
    }

    // Fetch existing gadget condition assessments for the owner
    public function getOwnerAssessments($ownerId) {
        $stmt = $this->conn->prepare("SELECT gc.*, p.name AS product_name FROM gadget_conditions gc JOIN products p ON gc.product_id = p.id WHERE gc.owner_id = :ownerId ORDER BY gc.reported_at DESC");
        $stmt->bindParam(':ownerId', $ownerId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }




    //Rentals Pages
    // Fetch rentals for the owner
    public function getOwnerRentals($ownerId) {
        $sql = "SELECT r.*, p.name AS product_name, u.name AS renter_name
                FROM rentals r
                INNER JOIN products p ON r.product_id = p.id
                INNER JOIN users u ON r.renter_id = u.id
                WHERE r.owner_id = :ownerId
                ORDER BY r.created_at DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':ownerId', $ownerId, PDO::PARAM_INT);
        $stmt->execute();
        $rentals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
        foreach ($rentals as &$rental) {
            if (in_array($rental['status'], ['completed', 'returned'])) {
                $rental['remaining_days'] = 'Completed';
            } elseif ($rental['status'] === 'cancelled') {
                $rental['remaining_days'] = 'Cancelled';
            } elseif (!empty($rental['end_date'])) {
                // Use time-agnostic date comparison
                $today = new DateTime('today');
                $endDate = new DateTime($rental['end_date']);
                $endDate->setTime(0, 0, 0); // Force to midnight
                
                $interval = $today->diff($endDate);
                $absoluteDays = $interval->days;
    
                if ($interval->invert) { // Past due
                    if ($rental['status'] !== 'overdue' && !in_array($rental['status'], ['completed', 'returned'])) {
                        // Update status to overdue
                        $updateSql = "UPDATE rentals SET status = 'overdue', updated_at = NOW() WHERE id = :rentalId";
                        $updateStmt = $this->conn->prepare($updateSql);
                        $updateStmt->bindParam(':rentalId', $rental['id'], PDO::PARAM_INT);
                        $updateStmt->execute();
                        $rental['status'] = 'overdue';
                    }
                    $rental['remaining_days'] = 'Overdue by ' . $absoluteDays . ' day' . ($absoluteDays !== 1 ? 's' : '');
                } elseif ($absoluteDays > 0) {
                    $rental['remaining_days'] = $absoluteDays . ' day' . ($absoluteDays !== 1 ? 's left' : ' left');
                } else {
                    $rental['remaining_days'] = 'Due Today';
                }
            } else {
                $rental['remaining_days'] = 'N/A';
            }
        }
        unset($rental);
    
        return $rentals;
    }

    // Define the status flow
    public function getStatusFlows() {
        return [
            'pending_confirmation' => 'Pending Confirmation',
            'approved' => 'Approved',
            'ready_for_pickup' => 'With Admin',
            'handed_over_to_admin' => 'Admin Verified',
            'picked_up' => 'With Renter',
            'returned' => 'Return Completed'
        ];
    }
    //View Rental Page
    // Rental Management Methods
    public function getRentalDetails($rentalId) {
        $sql = "SELECT r.*, p.name AS product_name, p.brand, p.rental_period, 
                u.name AS renter_name, p.quantity AS product_quantity, p.image AS product_image
                FROM rentals r
                INNER JOIN products p ON r.product_id = p.id
                INNER JOIN users u ON r.renter_id = u.id
                WHERE r.id = ? AND r.owner_id = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$rentalId, $this->userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function hasOwnerReview($rentalId) {
        $stmt = $this->conn->prepare("SELECT * FROM renter_reviews WHERE rental_id = ? AND owner_id = ?");
        $stmt->execute([$rentalId, $this->userId]);
        return (bool)$stmt->fetch();
    }

    public function getStatusFlow() {
        return [
            'pending_confirmation' => 'Pending Confirmation',
            'approved' => 'Approved',
            'handed_over_to_admin' => 'Admin Verified',
            'ready_for_pickup' => 'With Admin',
            'picked_up' => 'With Renter',
            'returned' => 'Returned',
            'cancelled' => 'Cancelled'  // Add this line
        ];
    }

    


    public function isStatusActive($statusKey, $currentStatus, $statusFlow) {
        $statusKeys = array_keys($statusFlow);
        $currentIndex = array_search($currentStatus, $statusKeys);
        $statusIndex = array_search($statusKey, $statusKeys);
        
        return $statusIndex <= $currentIndex;
    }

    public function handleRentalAction($rentalId, $action, $postData, $files) {
        if (!isset($postData['csrf_token']) || !$this->verifyCsrfToken($postData['csrf_token'])) {
            throw new Exception("CSRF token verification failed.");
        }

        if ($action === 'approve') {
            $stmt = $this->conn->prepare("
        INSERT INTO staff_assignments (rental_id, status)
        VALUES (?, 'pending')
    ");
    $stmt->execute([$rentalId]);
            // Set the new status (e.g., 'approved' or 'waiting_admin_acceptance')
            $newStatus = 'waiting_admin_acceptance';
    
            // Update the rental status
            $stmt = $this->conn->prepare("
                UPDATE rentals 
                SET status = ? 
                WHERE id = ?
            ");
            $stmt->execute([$newStatus, $rentalId]);
    
            // Insert into admin_assignments
            $stmt = $this->conn->prepare("
                INSERT INTO admin_assignments (rental_id, status)
                VALUES (?, 'pending')
            ");
            $stmt->execute([$rentalId]);
        }
        
        try {
            $this->conn->beginTransaction();
            
            switch ($action) {
                case 'approve': 
                    $this->approveRental($rentalId); 
                    break;
                case 'cancel': 
                    $this->cancelRental($rentalId); 
                    break;
                case 'handover_to_admin': 
                    $this->handleAdminHandover($rentalId, $files); 
                    break;
                case 'upload_proof': 
                    $this->handleProofUpload($rentalId, $files['proof_file'], $postData['proof_type']); 
                    break;
                case 'start_renting': $this->startRentingPeriod($rentalId); break;
                case 'end_rent': $this->endRentalPeriod($rentalId); break;
                case 'submit_feedback': $this->submitFeedback(
                    $rentalId,
                    $postData['renter_rating'],
                    $postData['renter_comment'],
                    $postData['product_rating'],
                    $postData['product_comment']
                );
                break;
                default: throw new Exception("Invalid action");
            }

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            $this->logError("Rental Action Failed: " . $e->getMessage());
            throw $e;
        }
    }

    public function hasGivenFeedbackToRenter($rentalId) {
        $stmt = $this->conn->prepare("
            SELECT id FROM renter_reviews 
            WHERE rental_id = ? AND owner_id = ?
        ");
        $stmt->execute([$rentalId, $this->userId]);
        return $stmt->rowCount() > 0;
    }

    public function hasReceivedFeedbackFromRenter($rentalId) {
        $stmt = $this->conn->prepare("
            SELECT id FROM owner_reviews 
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

    public function handleAdminHandover($rentalId, $files) {
        // 1. Create admin assignments
        $admins = $this->conn->query("SELECT id FROM users WHERE role = 'admin'")
                   ->fetchAll(PDO::FETCH_COLUMN);
        
        $stmt = $this->conn->prepare("
            INSERT INTO admin_assignments (rental_id, admin_id)
            VALUES (?, ?)
        ");
        
        foreach ($admins as $adminId) {
            $stmt->execute([$rentalId, $adminId]);
        }

        $staff = $this->conn->query("SELECT id FROM users WHERE role = 'staff'")
        ->fetchAll(PDO::FETCH_COLUMN);

$stmtStaff = $this->conn->prepare("
 INSERT INTO staff_assignments (rental_id, staff_id)
 VALUES (?, ?)
");

foreach ($staff as $staffId) {
 $stmtStaff->execute([$rentalId, $staffId]);
}
        
        // 2. Update rental status
        $this->conn->prepare("
            UPDATE rentals 
            SET status = 'waiting_admin_acceptance' 
            WHERE id = ?
        ")->execute([$rentalId]);

        $this->conn->prepare("
        UPDATE rentals 
        SET handover_date = NOW()  -- Set the handover date
        WHERE id = ?
    ")->execute([$rentalId]);
        
        // 3. Upload proof
        $proofUrl = $this->uploadFile($files['handover_proof']);
        $this->conn->prepare("
            INSERT INTO proofs (rental_id, proof_type, proof_url)
            VALUES (?, 'owner_handover', ?)
        ")->execute([$rentalId, $proofUrl]);
    }

    private function uploadFile($file) {
        // Create the directory if it doesn't exist
        $uploadDir = '../uploads/proofs/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $filename = uniqid() . '_' . basename($file['name']);
        $targetPath = $uploadDir . $filename;
        
        // Debug information
        error_log("Attempting to move uploaded file from {$file['tmp_name']} to {$targetPath}");
        
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new Exception("File upload failed. Check file permissions.");
        }
        
        // Return just the filename if that's what you're storing in the database
        return $filename;
    }


    

    private function approveRental($rentalId) {
        $rental = $this->getRentalDetails($rentalId);
        
        // Verify product availability first
        $stmt = $this->conn->prepare("SELECT quantity FROM products WHERE id = ?");
        $stmt->execute([$rental['product_id']]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$product || $product['quantity'] < 1) {
            throw new Exception("Product no longer available");
        }
        
        // Update rental status using safe parameter binding
        $stmt = $this->conn->prepare("
            UPDATE rentals 
            SET status = 'approved', updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$rentalId]);
        
        // Update product quantity
        $this->conn->prepare("
            UPDATE products 
            SET quantity = quantity - 1 
            WHERE id = ?
        ")->execute([$rental['product_id']]);
    }

    private function cancelRental($rentalId) {
        $rental = $this->getRentalDetails($rentalId);
        
        $stmt = $this->conn->prepare("
            UPDATE rentals 
            SET status = 'cancelled', updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$rentalId]);
    
        if ($rental['status'] === 'approved') {
            $this->conn->prepare("
                UPDATE products 
                SET quantity = quantity + 1 
                WHERE id = ?
            ")->execute([$rental['product_id']]);
        }
    }
    private function handleProofUpload($rentalId, $file, $proofType) {
        $allowedTypes = ['owner_handover', 'renter_pickup', 'return'];
        if (!in_array($proofType, $allowedTypes)) {
            throw new Exception("Invalid proof type");
        }
    
        $uploadPath = '../uploads/proofs/';
        $filename = uniqid() . '_' . basename($file['name']);
        $targetFile = $uploadPath . $filename;
    
        if (!move_uploaded_file($file['tmp_name'], $targetFile)) {
            throw new Exception("Failed to upload proof");
        }
    
        $stmt = $this->conn->prepare("
            INSERT INTO proofs 
            (rental_id, proof_type, proof_url, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$rentalId, $proofType, $filename]);
    }

    private function startRentingPeriod($rentalId) {
        $rental = $this->getRentalDetails($rentalId);
        $period = strtolower($rental['rental_period']);
        $startDate = date('Y-m-d');
        $endDate = date('Y-m-d', strtotime("+1 $period"));

        $stmt = $this->conn->prepare("
            UPDATE rentals SET status = 'renting', start_date = ?, end_date = ?, updated_at = NOW()
            WHERE id = ?");
        $stmt->execute([$startDate, $endDate, $rentalId]);
    }

    private function endRentalPeriod($rentalId) {
        $stmt = $this->conn->prepare("
            UPDATE rentals SET status = 'completed', actual_end_date = NOW(), updated_at = NOW()
            WHERE id = ?");
        $stmt->execute([$rentalId]);
    }


    private function getRenterIdFromRental($rentalId) {
        $stmt = $this->conn->prepare("SELECT renter_id FROM rentals WHERE id = ?");
        $stmt->execute([$rentalId]);
        return $stmt->fetchColumn();
    }

    private function getProductIdFromRental($rentalId) {
        $stmt = $this->conn->prepare("SELECT product_id FROM rentals WHERE id = ?");
        $stmt->execute([$rentalId]);
        return $stmt->fetchColumn();
    }

    public function submitFeedback(
        $rentalId,
        $renterRating,
        $renterComment,
        $productRating,
        $productComment
    ) {
        // Check if userId is set, if not re-authenticate
        if (!isset($this->userId) || empty($this->userId)) {
            // Re-authenticate if needed
            $this->authenticateOwner();
            
            if (!isset($this->userId) || empty($this->userId)) {
                throw new Exception("Owner ID is not set. Please log in again.");
            }
        }
        
        try {
            $this->conn->beginTransaction();
    
            // Get IDs
            $renterId = $this->getRenterIdFromRental($rentalId);
            $productId = $this->getProductIdFromRental($rentalId);
    
            // Insert renter review
            $stmt = $this->conn->prepare("
                INSERT INTO renter_reviews 
                (renter_id, owner_id, rental_id, rating, comment, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $renterId,
                $this->userId, // Now properly checked and set
                $rentalId,
                $renterRating,
                $renterComment
            ]);
    
            // Insert product review - FIXED to match schema
            $stmt = $this->conn->prepare("
                INSERT INTO comments 
                (product_id, renter_id, rating, comment, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $productId,
                $renterId, // Changed from owner_id to renter_id to match schema
                $productRating,
                $productComment
            ]);
    
            // Mark feedback complete
            $stmt = $this->conn->prepare("
                UPDATE rentals SET feedback_completed = 1 
                WHERE id = ?
            ");
            $stmt->execute([$rentalId]);
    
            $this->conn->commit();
    
        } catch (PDOException $e) {
            $this->conn->rollBack();
            throw new Exception("Failed to save feedback: " . $e->getMessage());
        }
    }

    public function getProofs($rentalId) {
        $stmt = $this->conn->prepare("
            SELECT id, rental_id, proof_type, proof_url, created_at 
            FROM proofs 
            WHERE rental_id = ?
        ");
        $stmt->execute([$rentalId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    

// In owner_class.php
public function calculateRemainingDays($endDate) {
    if (empty($endDate)) return 'N/A';
    
    // Set both dates to midnight for accurate comparison
    $today = new DateTime('today');
    $end = new DateTime($endDate);
    $end->setTime(0, 0, 0);
    
    $interval = $today->diff($end);
    $absoluteDays = $interval->days;

    if ($interval->invert) { // Past due
        return 'Overdue by ' . $absoluteDays . ' day' . ($absoluteDays !== 1 ? 's' : '');
    } elseif ($absoluteDays > 0) {
        return $absoluteDays . ' day' . ($absoluteDays !== 1 ? 's left' : ' left');
    }
    return 'Due Today';
}

}

?>
