<?php
// ภาษา: PHP
// ชื่อไฟล์: update_product_modal.php
// คอมเมนต์: โมดอลสำหรับอัปเดตข้อมูลสินค้า (Update Product Modal)
// ใช้กับ Bootstrap 5.3 CDN
?>

<!-- Update Product Modal (with explicit closing tags) -->
<div class="modal fade" id="updateProductModal" tabindex="-1" aria-labelledby="updateProductModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" action="update_product.php" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="updateProductModalLabel">Update Product</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <!-- รหัสสินค้า (hidden) -->
        <input type="hidden" name="prod_id" id="updateProdId">

        <!-- Product Code -->
        <div class="mb-3">
          <label for="updateProdCode" class="form-label">Product Code</label>
          <input type="text" class="form-control" name="prod_code" id="updateProdCode" required>
        </div>

        <!-- Product Type -->
        <?php
        // ดึงประเภทสินค้า
        $type_sql = "SELECT type_id, type_name FROM prod_type_list WHERE status = 'active' ORDER BY type_name";
        $type_result = $conn->query($type_sql);
        ?>
        <div class="mb-3">
          <label for="updateTypeId" class="form-label">Product Type</label>
          <select name="type_id" id="updateTypeId" class="form-select" required>
            <option value="">Select Type</option>
            <?php while ($type = $type_result->fetch_assoc()): ?>
              <option value="<?= $type['type_id'] ?>"><?= htmlspecialchars($type['type_name'], ENT_QUOTES) ?></option>
            <?php endwhile; ?>
          </select>
        </div>

        <!-- Description -->
        <div class="mb-3">
          <label for="updateProdDesc" class="form-label">Description</label>
          <input type="text" class="form-control" name="prod_desc" id="updateProdDesc">
        </div>

        <!-- Dimensions -->
        <div class="row mb-3">
          <div class="col">
            <label for="updateThickness" class="form-label">Thickness</label>
            <input type="number" class="form-control" name="thickness" id="updateThickness" required>
          </div>
          <div class="col">
            <label for="updateWidth" class="form-label">Width</label>
            <input type="number" class="form-control" name="width" id="updateWidth" required>
          </div>
          <div class="col">
            <label for="updateLength" class="form-label">Length</label>
            <input type="number" class="form-control" name="length" id="updateLength" required>
          </div>
        </div>

        <!-- Price -->
        <div class="mb-3">
          <label for="updatePrice" class="form-label">Price</label>
          <input type="number" step="0.01" class="form-control" name="price" id="updatePrice" required>
        </div>

      </div> <!-- /.modal-body -->
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Update</button>
      </div>
    </form> <!-- /.modal-content -->
  </div> <!-- /.modal-dialog -->
</div> <!-- /.modal fade -->
