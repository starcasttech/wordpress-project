<?php
/**
 * Database Import Script
 * Upload this to public_html/ and visit http://starcast.co.za/import-db.php
 * DELETE THIS FILE AFTER USE!
 */

// Database credentials from remote wp-config.php
$db_host = 'localhost';
$db_name = 'starcas1_wp147';
$db_user = 'starcas1_wp147';
$db_pass = '0]t)pS28Gm';
$sql_file = __DIR__ . '/starcast-deploy.sql';

echo "<h1>Database Import Tool</h1>";
echo "<p>Starting import...</p>";
flush();

// Check if SQL file exists
if (!file_exists($sql_file)) {
    die("<p style='color:red;'>ERROR: SQL file not found at: $sql_file</p>");
}

echo "<p>SQL file found: " . basename($sql_file) . " (" . round(filesize($sql_file)/1024/1024, 2) . " MB)</p>";
flush();

// Connect to database
echo "<p>Connecting to database...</p>";
flush();

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("<p style='color:red;'>Connection failed: " . $conn->connect_error . "</p>");
}

echo "<p style='color:green;'>Connected successfully!</p>";
flush();

// Read SQL file
echo "<p>Reading SQL file...</p>";
flush();

$sql = file_get_contents($sql_file);

if (!$sql) {
    die("<p style='color:red;'>ERROR: Could not read SQL file</p>");
}

echo "<p>SQL file loaded (" . strlen($sql) . " bytes)</p>";
flush();

// Execute SQL
echo "<p>Importing database (this may take 1-2 minutes)...</p>";
flush();

// Split into individual queries
$queries = explode(';', $sql);
$success_count = 0;
$error_count = 0;

foreach ($queries as $query) {
    $query = trim($query);
    if (empty($query)) continue;

    if ($conn->query($query) === TRUE) {
        $success_count++;
    } else {
        $error_count++;
        if ($error_count < 10) {
            echo "<p style='color:orange;'>Warning: " . substr($conn->error, 0, 200) . "</p>";
        }
    }

    // Show progress every 100 queries
    if ($success_count % 100 == 0) {
        echo "<p>Processed $success_count queries...</p>";
        flush();
    }
}

$conn->close();

echo "<hr>";
echo "<h2 style='color:green;'>✓ Import Complete!</h2>";
echo "<p>Successful queries: $success_count</p>";
echo "<p>Errors: $error_count</p>";
echo "<hr>";
echo "<h3>Next Steps:</h3>";
echo "<ol>";
echo "<li><strong>DELETE THIS FILE:</strong> import-db.php (IMPORTANT FOR SECURITY!)</li>";
echo "<li>Visit: <a href='http://starcast.co.za/wp/wp-admin'>http://starcast.co.za/wp/wp-admin</a></li>";
echo "<li>Login with your WordPress admin credentials</li>";
echo "<li>Go to Appearance > Themes > Activate Kadence</li>";
echo "<li>Go to Plugins > Activate all required plugins</li>";
echo "</ol>";
?>
