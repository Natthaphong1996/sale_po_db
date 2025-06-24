<?php
// Language: PHP
// File: config_db.php
// Description: Establishes a database connection using mysqli with error handling and UTF-8 charset.

// --- Database Credentials ---
$servername = "localhost"; // Database server (e.g., localhost)
$username   = "root";       // Database username
$password   = "";           // Database password (if empty, use "")
$dbname     = "sale_po_db"; // Your database name

// --- Create and Validate Connection ---
// The @ suppresses the default PHP warning, allowing for custom error handling.
$conn = @new mysqli($servername, $username, $password, $dbname);

// Check for connection errors
if ($conn->connect_error) {
    // A user-friendly error message is better for production environments
    // For development, die() is okay.
    http_response_code(503); // Service Unavailable
    die("Database Connection Failed: Unable to connect to the database server. Please try again later.");
}

// Set the character set to utf8mb4 for full UTF-8 support (including emojis)
if (!$conn->set_charset("utf8mb4")) {
     // Handle charset error if needed, though it's rare to fail.
     error_log("Error loading character set utf8mb4: " . $conn->error);
}

// The $conn object is now ready to be used by other scripts.
?>
