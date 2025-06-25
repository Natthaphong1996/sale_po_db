<?php
// Language: PHP
// File: customer_actions.php (Fixed)
// This file should ONLY contain the HTML structure for the modals.
// All processing logic has been moved to dedicated action files
// (e.g., add_customer.php, update_customer.php) to prevent "headers already sent" errors.
?>

<!-- Add/Update Customer Modal -->
<div class="modal fade" id="customerModal" tabindex="-1" aria-labelledby="customerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="customerModalLabel">Add New Customer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <!-- The form's action attribute will be set by JavaScript in customers_list.php -->
            <form id="customerForm" method="post">
                <div class="modal-body">
                    <!-- Hidden input for the customer ID, used for updates -->
                    <input type="hidden" name="customerId" id="customerId">
                    <div class="mb-3">
                        <label for="customerName" class="form-label">Customer Name</label>
                        <input type="text" class="form-control" id="customerName" name="customerName" required placeholder="Enter customer name">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Status Change Confirmation Modal -->
<div class="modal fade" id="statusChangeModal" tabindex="-1" aria-labelledby="statusChangeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="statusChangeModalLabel">Confirm Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="modal-body-text">Are you sure you want to change status for <strong class="customer-name"></strong>?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <!-- The href and button class will be set by JavaScript -->
                <a href="#" class="btn btn-danger confirm-status-change">Confirm</a>
            </div>
        </div>
    </div>
</div>
