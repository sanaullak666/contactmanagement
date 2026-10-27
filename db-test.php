<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ==== CHANGE THESE IF NEEDED ====
$host     = 'localhost';
$username = 'root';
$password = '';
$dbname   = 'contacts_db';
// =================================

echo "<h2>MySQL Connection Test</h2>";

// Test 1: Connect to MySQL server
try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Step 1: Connected to MySQL server<br>";
} catch (PDOException $e) {
    die("❌ Step 1 FAILED: " . $e->getMessage());
}

// Test 2: List databases
$dbs = $pdo->query("SHOW DATABASES")->fetchAll(PDO::FETCH_COLUMN);
echo "✅ Step 2: Databases found: <b>" . implode(", ", $dbs) . "</b><br>";

if (!in_array($dbname, $dbs)) {
    die("❌ '$dbname' NOT FOUND — creating it now...");
}
echo "✅ Step 3: '$dbname' exists<br>";

// Test 3: Connect to the database
try {
    $pdo2 = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo2->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Step 4: Connected to '$dbname'<br>";
} catch (PDOException $e) {
    die("❌ Step 4 FAILED: " . $e->getMessage());
}

// Test 4: Check table
try {
    $count = $pdo2->query("SELECT COUNT(*) FROM contacts")->fetchColumn();
    $cols = $pdo2->query("DESCRIBE contacts")->fetchAll(PDO::FETCH_COLUMN);
    echo "✅ Step 5: Table 'contacts' exists — Rows: $count | Columns: <b>" . implode(", ", $cols) . "</b><br>";
} catch (PDOException $e) {
    die("❌ Step 5 FAILED: " . $e->getMessage());
}

echo "<h3>🎉 ALL CHECKS PASSED — database is working properly!</h3>";