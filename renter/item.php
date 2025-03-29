<?php
    require_once '../include_renter/item.php';
    require_once 'renter_class.php';
require_once '../db/db.php';


$renter = new Renter($conn);
$renter->authenticateRenter();
$userId = $_SESSION['id'];


    // CSRF token generation
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$userId = $_SESSION['id'];
$productId = (int)$_GET['id'];


// Fetch product data
$product = $renter->getProductById($product_id);
if (!$product) {
    die("Product not found.");
}

// Handle add to cart
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_to_cart'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = "Invalid CSRF token.";
    } else {
        $message = $renter->addToCart($userId, $productId);
    }
}

// Format product data for display
$productData = [
    'id' => $product['id'],
    'owner_id' => $product['owner_id'],
    'name' => htmlspecialchars($product['name']),
    'brand' => htmlspecialchars($product['brand']),
    'description' => htmlspecialchars($product['description']),
    'rental_price' => number_format($product['rental_price'], 2),
    'status' => htmlspecialchars($product['status']),
    'created_at' => $product['created_at'],
    'updated_at' => $product['updated_at'],
    'image' => $product['image'],
    'quantity' => $product['quantity'],
    'category' => htmlspecialchars($product['category']),
    'rental_period' => htmlspecialchars($product['rental_period']),
];

    
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Rentbox</title>
    <link rel="icon" type="image/png" href="../images/rb logo white.png">
    <link href="../vendor/bootstrap-5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="../vendor/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/renter/style.css">
</head>

<body>

    <div class="container-fluid image-bg m-0 p-0">
        
        <!-- Navbar Section -->
        <?php require_once '../includes/navbarr.php'; ?>
        
        <!-- Body Section -->
        <div class="bg-body-secondary p-4 shadow-lg">
            <div class="row container-fluid m-0 p-0 gap-3">
                
                <!-- Image Carousel -->
                <div class="col-4 bg-body p-4 rounded-3 shadow-sm m-0">
                    <div id="carouselIndicators" class="carousel carousel-dark slide">
                        <div class="carousel-indicators">
                            <?php
                            foreach ($images as $index => $image) {
                                echo '<button type="button" data-bs-target="#carouselIndicators" data-bs-slide-to="' . $index . '" class="' . ($index === 0 ? 'active' : '') . '" aria-current="true" aria-label="Slide ' . ($index + 1) . '"></button>';
                            }
                            ?>
                        </div>
                        <div class="carousel-inner border rounded-3 object-fit-cover shadow-sm border border-3">
                            <?php
                            foreach ($images as $index => $image) {
                                echo '<div class="carousel-item ' . ($index === 0 ? 'active' : '') . '">';
                                echo '<img src="../img/uploads/' . htmlspecialchars($image) . '" class="d-block w-100" alt="...">';
                                echo '</div>';
                            }
                            ?>
                        </div>
                        <button class="carousel-control-prev" type="button" data-bs-target="#carouselIndicators" data-bs-slide="prev">
                            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Previous</span>
                        </button>
                        <button class="carousel-control-next" type="button" data-bs-target="#carouselIndicators" data-bs-slide="next">
                            <span class="carousel-control-next-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Next</span>
                        </button>
                    </div>
                </div>

                <!-- Product Info Section -->
                <div class="col-5 bg-body p-4 rounded-3 shadow-sm m-0">
                    <div class="m-0 p-0">
                        <div class="d-flex justify-content-end gap-1">
                            <a href="#" class="link-success link-offset-2 link-underline-opacity-25 link-underline-opacity-100-hover">Browse</a>
                            <p class="text-secondary"> > </p>
                            <a href="#" class="link-success link-offset-2 link-underline-opacity-25 link-underline-opacity-100-hover">
                                <?php echo htmlspecialchars($product['name'] ?? 'Product Name Not Available'); ?>
                            </a>
                        </div>
                        <div class="d-flex gap-2 mb-2 align-items-end">
                            <h1 class="display-6 fw-bold m-0 p-0"><?php echo htmlspecialchars($product['name'] ?? 'Product Name Not Available'); ?></h1>
                            <div class="m-0 p-0">
                                <a href="#" class="btn btn-outline-secondary m-0 opacity-50 m-0 p-1"><small class="m-0 p-0"><?php echo htmlspecialchars($product['category'] ?? 'Category Not Available'); ?></small></a>
                            </div>
                        </div>
                        <div class="d-flex">
                            <p class="me-1 text-decoration-none"><?php echo $average_rating; ?></p>
                            <i class="bi bi-star-fill text-warning"></i>
                            <p class="mx-2">|</p>
                            <a href="#" class="me-1 text-decoration-none text-success"><?php echo count($comments); ?> Reviews</a>
                        </div>

                        <!-- Price and Availability Section -->
                        <div class="row d-flex align-items-bottom">
                            <div class="border col-5 rounded-3 shadow-sm p-0 mb-3 mt-2 ms-3">
                                <div class="m-0 <?php echo $availabilityClass; ?> align-items-stretch border text-center">
                                    <p class="active fs-6 my-2"><?php echo $availabilityText; ?></p>
                                </div>

                                <div class="d-flex justify-content-center align-items-center">
                                    <p class="fs-3 fw-bold m-0 p-0 active">₱<?php echo htmlspecialchars($product['rental_price']); ?></p>
                                    <p class="fs-3 fw-bold m-0 p-0 active">/</p>
                                    <p class="fs-3 fw-bold m-0 p-0 active"><?php echo htmlspecialchars($product['rental_period']); ?></p>
                                </div>
                            </div>
                        </div>

                        <!-- Product Description Section -->
                        <small class="text-secondary p-0 mb-2">Product Description:</small>
                        <p class="fs-6 p-0 mb-3"><?php echo htmlspecialchars($product_description); ?></p>

                        <!-- Additional Product Info -->
                        <div class="row d-flex">
                            <div class="col-4">
                                <small class="text-secondary p-0 mb-2">Brand/Model:</small>
                                <div class="d-flex gap-2 mt-1 mb-3">
                                    <a href="#" class="btn btn-outline-success m-0 p-1">Apple</a>
                                    <a href="#" class="btn btn-outline-success m-0 p-1">Airpods Pro 1</a>
                                </div>

                                <small class="text-secondary p-0 mb-2">Comes with:</small>
                                <div class="d-flex gap-2 mt-1">
                                    <a href="#" class="btn btn-outline-success m-0 p-1">Case</a>
                                    <a href="#" class="btn btn-outline-success m-0 p-1">IOS Charger</a>
                                </div>
                            </div>

                            <!-- Reservation Form -->
                            <div class="col-8 d-flex pe-2 d-flex flex-column">
                                <div class="row mb-5 ps-3 pe-5 me-5">
                                <small class="text-secondary p-0 mb-2">Rental Duration:</small>
                                <div class="d-flex flex-column m-0 p-0">
                                <input 
                                type="number" 
                                id="rentalPeriods" 
                                name="rental_periods" 
                                min="1" 
                                value="1" 
                                class="form-control"
                                required
                                onchange="calculateTotal()" 
                                oninput="calculateTotal()">
                                    <small class="text-muted">Number of <?= strtolower($product['rental_period']) ?><?= $product['rental_period'] === 'Day' ? 's' : '' ?></small>
                                </div>
                                </div>



                                <div class="d-flex justify-content-end">
                                <!-- Add to Cart Form -->
                                <form method="post" action="">
                                    <input type="hidden" name="add_to_cart" value="1">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                                    <button type="submit" class="px-3 py-2 btn rounded-pill shadow-sm btn-light px-3 border ms-auto">
                                    <i class="bi bi-bag-plus pe-1"></i>
                                        Add to Cart
                                    </button>
                                </form>

    <!-- Rent Now Form (Checkout) -->
    <form method="post" action="checkout.php" class="d-inline">
                                    <!-- CSRF Token -->
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                                    <!-- Direct Checkout Indicator -->
                                    <input type="hidden" name="direct_checkout" value="1">
                                    <input type="hidden" name="rental_periods" value="1" id="hiddenRentalPeriods">
                                    <!-- Product Details -->
                                    <input type="hidden" name="product_id" value="<?= $product['id']; ?>">
                                    <button type="submit" class="px-3 py-2 btn rounded-pill shadow-sm btn-success d-flex align-items-center gap-2" <?php echo ($productData['quantity'] < 1) ? 'disabled' : ''; ?>>
                                        Checkout
                                        <span class="mb-0 ps-1 fw-bold" id="checkoutTotalPrice">₱<?php echo $productData['rental_price']; ?></span>
                                    </button>
                                </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Advertisements Section -->
                <div class="col bg-body p-4 rounded-3 shadow-sm m-0">
                    <img src="" alt="advertisement" class="img-thumbnail">
                    promo code
                </div>
            </div>

            <!-- Product Ratings and Owner Information Section -->
            <div class="row container-fluid mt-3 mx-0 bg-body rounded-3 shadow-sm p-4 gap-3 d-flex align-items-center">
                <div class="col-1 d-flex justify-content-center align-items-center ps-4">
                    <?php
                        // Fetch owner's profile picture from the users table
                        $query = "SELECT profile_picture FROM users WHERE id = ?";
                        $stmt = $conn->prepare($query);
                        $stmt->execute([$owner_id]);
                        $owner_profile_picture = $stmt->fetchColumn();
                        $profile_picture = $owner_profile_picture ? "../" . htmlspecialchars($owner_profile_picture) : "images/user/pfp.png";
                    ?>
                    <img src="<?php echo $profile_picture; ?>" alt="Owner Profile Picture" class="rounded-circle border shadow-sm img-thumbnail">
                </div>
                <div class="col-2 d-flex flex-column border-end m-0 p-0 align-self-start">
                    <a href="#" class="fs-5 text-decoration-none text-dark fw-bold m-0 p-0"><?php echo htmlspecialchars($owner_name); ?></a>
                    <p class="fs-6 text-secondary p-0"><?php echo $active_status; ?></p> <!-- Active status -->
                    <div class="d-flex gap-2">

                        <a href="review.php?owner_id=<?php echo $product['owner_id']; ?>" class="btn btn-outline-secondary m-0 px-2">View Profile</a>
                    </div>
                </div>
                <div class="col-7 ps-0 m-0 flex-grow-1">
                    <div class="row">
                        <div class="col">
                            <div class="row">
                                <div class="col d-flex flex-column justify-content-between">
                                    <p class="fs-6 text-secondary m-0 p-0">Rating</p>
                                    <p class="fs-6 text-secondary m-0 p-0">Rentals</p>
                                </div>
                                <div class="col d-flex flex-column justify-content-between">
                                    <a href="#" class="fs-6 text-success m-0 p-0 text-decoration-none"><?php echo $total_ratings; ?></a> <!-- Total Ratings -->
                                    <a href="#" class="fs-6 text-success mt-3 p-0 text-decoration-none"><?php echo $rental_count; ?> Rentals</a> <!-- Rental Count -->
                                </div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="row">
                                <div class="col d-flex flex-column justify-content-between">
                                    <p class="fs-6 text-secondary m-0 p-0">Joined</p>
                                </div>
                                <div class="col d-flex flex-column justify-content-between">
                                    <a href="#" class="fs-6 text-success m-0 p-0 text-decoration-none"><?php echo $joined_duration; ?></a> <!-- Joined Duration -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Product Specification Section -->
            <div class="row container-fluid mt-3 mx-0 bg-body rounded-3 shadow-sm p-4 d-flex align-items-start">
                <p class="fs-5 text-decoration-none text-dark fw-bold mb-2 p-0">Product Specification</p>

                <div class="col-2 d-flex flex-column gap-3">
                    <small class="text-secondary pt-1">Category</small>
                    <small class="text-secondary pt-1">Available Stock</small>
                </div>

                <div class="col d-flex flex-column gap-3">
                    <div class="d-flex gap-1 p-0 m-0">
                        <a href="#" class="p-0 m-0 link-success link-offset-2 link-underline-opacity-25 link-underline-opacity-100-hover"><?php echo htmlspecialchars($category); ?></a>
                    </div>
                    <p class="fs-6 text-success m-0 p-0"><?php echo htmlspecialchars($quantity); ?></p>  
                </div>

                <p class="fs-5 text-decoration-none text-dark fw-bold mt-5 mb-3 p-0">Owner Condition</p>
                <p class="m-0 ps-2"><?php echo htmlspecialchars($condition_description); ?></p> <!-- Display the dynamic condition description -->
            </div>

            <!-- Ratings Section -->
            <div class="row container-fluid mt-3 mx-0 bg-body rounded-3 shadow-sm pe-5 p-4 d-flex align-items-start">
                <p class="fs-5 text-decoration-none text-dark fw-bold mb-2 p-0">Ratings</p>

                <div class="row container-fluid mt-3">
                    <div class="col-1">
                        <div class="d-flex flex-column align-items-center">
                            <p class="fs-5 fw-bold m-0 p-0"><?php echo $average_rating; ?> out of 5</p>
                            <div class="d-flex gap-1">
                                <?php
                                // Display filled and empty stars based on average rating
                                for ($i = 0; $i < 5; $i++) {
                                    echo $i < $average_rating ? '<i class="bi bi-star-fill text-warning"></i>' : '<i class="bi bi-star text-warning"></i>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-11 d-flex align-items-center gap-2">
                        <!-- Filter buttons for ratings -->
                        <a href="?id=<?php echo $product_id; ?>&rating=all" class="btn btn-small btn-outline-success">All</a>
                        <a href="?id=<?php echo $product_id; ?>&rating=5" class="btn btn-small btn-outline-success">5 stars</a>
                        <a href="?id=<?php echo $product_id; ?>&rating=4" class="btn btn-small btn-outline-success">4 stars</a>
                        <a href="?id=<?php echo $product_id; ?>&rating=3" class="btn btn-small btn-outline-success">3 stars</a>
                        <a href="?id=<?php echo $product_id; ?>&rating=2" class="btn btn-small btn-outline-success">2 stars</a>
                        <a href="?id=<?php echo $product_id; ?>&rating=1" class="btn btn-small btn-outline-success">1 star</a>
                    </div>
                </div>

                <!-- Review Section -->
                <?php if (empty($comments)): ?>
                    <p class="text-center">No comments available for this product.</p> <!-- Message when no comments exist -->
                <?php else: ?>
                    <?php foreach ($comments as $comment): ?>
                <div class="mt-3 d-flex">
                    <!-- Check if the profile picture exists, otherwise use the default -->
                    <img src="<?= htmlspecialchars($profilePic) ?>" alt="" class="rounded-circle border me-3 p-0" style="width: 40px; height: 40px;">
                    <div class="d-flex flex-column">
                    <p class="fs-6 fw-bold m-0 p-0"><?php echo htmlspecialchars($comment['name']); ?></p>
                        <div class="d-flex gap-1 m-0">
                            <!-- Dynamically display the rating stars -->
                            <?php
                            $rating = $comment['rating'];
                            for ($i = 0; $i < 5; $i++) {
                                echo $i < $rating ? '<i class="bi bi-star-fill text-warning"></i>' : '<i class="bi bi-star text-warning"></i>';
                            }
                            ?>
                            <div class="d-flex gap-1 m-0">
                                <p class="text-secondary">|</p>
                                <p class="text-secondary"><?php echo date('Y-m-d H:i', strtotime($comment['created_at'])); ?></p>
                            </div>
                        </div>
                        <p class="mt-2"><?php echo htmlspecialchars($comment['comment']); ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div>
        <?php require_once '../includes/footer.php' ?>

    </div>
    <script src="../vendor/bootstrap-5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
function calculateTotal() {
    const periods = parseInt(document.getElementById('rentalPeriods').value) || 1;
    const pricePerPeriod = <?php echo floatval($product['rental_price']); ?>;
    const totalPrice = periods * pricePerPeriod;
    

    document.getElementById('checkoutTotalPrice').textContent = '₱' + totalPrice.toFixed(2);
    document.getElementById('hiddenRentalPeriods').value = periods;
}
</script>
</body>
<script src="vendor/bootstrap-5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</html>