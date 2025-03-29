<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../db/db.php';

function log_error($message) {
    $logFile = '../logs/error_log.txt';
    $formattedMessage = "[" . date('Y-m-d H:i:s') . "] " . $message . "\n";
    file_put_contents($logFile, $formattedMessage, FILE_APPEND);
}

if (!isset($_SESSION['id'])) {
    header('Location: ../renter/login.php');
    exit();
}

$userId = $_SESSION['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // CSRF Token Validation
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception("Invalid CSRF token");
        }

        $conn->beginTransaction();
        $isDirectCheckout = isset($_POST['direct_checkout']);

        // Direct Checkout Flow
        if ($isDirectCheckout) {
            // Validate direct checkout parameters
            if (empty($_POST['product_id']) || empty($_POST['rental_periods'])) {
                throw new Exception("Missing required fields for direct checkout.");
            }

            $productId = (int)$_POST['product_id'];
            $periods = (int)$_POST['rental_periods'];

            if ($periods < 1) {
                throw new Exception("Invalid number of rental periods.");
            }

            // Validate product existence
            $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
            $stmt->execute([$productId]);
            $product = $stmt->fetch();

            if (!$product) throw new Exception("Product not found");
            if ($product['quantity'] < 1) throw new Exception("Product out of stock");

            // Calculate dates
            $startDate = date('Y-m-d');
            $endDate = date('Y-m-d', strtotime("+$periods days"));

            // Calculate total cost
            $totalCost = $product['rental_price'] * $periods;

            // Create rental record
            $stmt = $conn->prepare("INSERT INTO rentals (
                product_id, renter_id, owner_id, 
                rental_price, total_cost, payment_method, 
                status, number_of_periods
            ) VALUES (?, ?, ?, ?, ?, 'cod', 'pending_confirmation', ?)");
            
            $stmt->execute([
                $productId,
                $userId,
                $product['owner_id'],
                $product['rental_price'],
                $totalCost,
                $periods
            ]);
        }
        // Cart Checkout Flow
        else {
            // Get cart items with period information
            $stmt = $conn->prepare("
                SELECT c.product_id, c.number_of_periods, p.owner_id, p.rental_price
                FROM cart_items c
                INNER JOIN products p ON c.product_id = p.id
                WHERE c.renter_id = :userId
            ");
            $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $cartItems = $stmt->fetchAll();

            if (empty($cartItems)) {
                throw new Exception("Your cart is empty.");
            }

            foreach ($cartItems as $item) {
                // Validate periods
                $periods = (int)$item['number_of_periods'];
                if ($periods < 1) {
                    throw new Exception("Invalid rental duration for product ID: " . $item['product_id']);
                }

                // Calculate dates
                $startDate = date('Y-m-d');
                $endDate = date('Y-m-d', strtotime("+$periods days"));

                // Calculate total cost
                $totalCost = $item['rental_price'] * $periods;

                // Create rental record
                $stmt = $conn->prepare("
                INSERT INTO rentals (
                    product_id, renter_id, owner_id,
                    rental_price, total_cost, status, 
                    payment_method, number_of_periods
                ) VALUES (
                    :product_id, :renter_id, :owner_id,
                    :rental_price, :total_cost, 'pending_confirmation',
                    'cod', :number_of_periods
                )
            ");
                
                $stmt->execute([
                    ':product_id' => $item['product_id'],
                    ':renter_id' => $userId,
                    ':owner_id' => $item['owner_id'],
                    ':start_date' => $startDate,
                    ':end_date' => $endDate,
                    ':rental_price' => $item['rental_price'],
                    ':total_cost' => $totalCost,
                    ':number_of_periods' => $item['number_of_periods']
                ]);
            }

            // Clear cart after successful processing
            $stmt = $conn->prepare("DELETE FROM cart_items WHERE renter_id = :userId");
            $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
            $stmt->execute();
        }

        $conn->commit();
        $_SESSION['success_message'] = "Checkout successful! Awaiting owner approval.";
        header('Location: checkout_success.php');
        exit();

    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error_message'] = "Checkout failed: " . $e->getMessage();
        log_error("Checkout failed for user ID: $userId - " . $e->getMessage());
        header('Location: checkout.php');
        exit();
    }
} else {
    header('Location: checkout.php');
    exit();
}
?>