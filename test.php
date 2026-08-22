<?php
require_once 'db.php';

if ($conn) {
    echo "<h2 style='color:green;'>✅ Database connection successful!</h2>";
    echo "<p>Connected to: <strong>lost_found_db</strong></p>";
    echo "<p>You can now proceed to build the system.</p>";
} else {
    echo "<h2 style='color:red;'>❌ Connection failed!</h2>";
    echo "<p>" . mysqli_connect_error() . "</p>";
}
?>
