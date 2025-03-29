<div class="modal fade" id="addModal" tabindex="-1" aria-labelledby="addModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form action="gadget.php" method="POST" enctype="multipart/form-data">
          <div class="modal-header">
            <h5 class="modal-title" id="addModalLabel">Add New Gadget</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">

            <div class="form-floating mb-3">
              <input type="text" class="form-control" id="productName" name="name" placeholder="Gadget Name" required>
              <label for="productName">Gadget Name</label>
            </div>
            
            <div class="form-floating mb-3">
              <input type="text" class="form-control" id="productBrand" name="brand" placeholder="Brand" required>
              <label for="productBrand">Brand</label>
            </div>

            <div class="form-floating mb-3">
              <input type="text" class="form-control" id="comesWith" name="comes_with" placeholder="Comes with (optional)">
              <label for="comesWith">Comes with (optional)</label>
            </div>
            
            <div class="form-floating mb-3">
              <textarea class="form-control" placeholder="Description" id="productDescription" name="description" style="height: 100px" required></textarea>
              <label for="productDescription">Description</label>
            </div>
            
            <div class="input-group mb-3">
              <span class="input-group-text">PHP</span>
              <input type="number" step="0.01" class="form-control" name="rental_price" placeholder="Rental Price" min="0" required>
            </div>
            
            <div class="mb-3">
              <label for="rentalPeriod" class="form-label">Rental Period</label>
              <select class="form-select" id="rentalPeriod" name="rental_period" required>
                <option value="" disabled selected>Select Rental Period</option>
                <option value="Day">Day</option>
                <option value="Week">Week</option>
                <option value="Month">Month</option>
              </select>
            </div>

            <div class="mb-3">
              <label for="productQuantity" class="form-label">Quantity</label>
              <input type="number" class="form-control" id="productQuantity" name="quantity" min="1" required>
            </div>
            
            <div class="mb-3">
              <label for="productCategory" class="form-label">Category</label>
              <select class="form-select" id="productCategory" name="category" required>
                <option value="" disabled selected>Select Category</option>
                <option value="Mobile Phones">Mobile Phones</option>
                <option value="Laptops">Laptops</option>
                <option value="Tablets">Tablets</option>
                <option value="Cameras">Cameras</option>
                <option value="Accessories">Accessories</option>
                <option value="Gaming Consoles">Gaming Consoles</option>
                <option value="Audio Devices">Audio Devices</option>
                <option value="Drones">Drones</option>
              </select>
            </div>

            <div class="mb-3">
              <label for="overduePrice" class="form-label">Overdue Price per Day</label>
              <input type="number" class="form-control" id="overduePrice" name="overdue_price" step="0.01" min="0" placeholder="Price if overdue for a day" required>
            </div>

            <div class="mb-3">
              <label for="realPrice" class="form-label">Real Price of the Gadget</label>
              <input type="number" class="form-control" id="realPrice" name="real_price" step="0.01" min="0" placeholder="Real Price" required>
            </div>

            <div class="mb-3">
              <label for="conditionDescription" class="form-label">Condition Description</label>
              <textarea class="form-control" id="conditionDescription" name="condition_description" style="height: 100px" placeholder="Condition of the gadget" required></textarea>
            </div>
            
            <div class="mb-3">
              <label for="productImage" class="form-label">Upload Image</label>
              <input class="form-control" type="file" id="productImage" name="image" accept="image/*">
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-success" name="add_product">Add Product</button>
          </div>
      </form>
    </div>
  </div>
</div>