<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host     = 'localhost';
$username = 'root';
$password = '';
$dbname   = 'contacts_db';

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$dbname`");

    $sql = "CREATE TABLE IF NOT EXISTS `contacts` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `name` VARCHAR(100) NOT NULL,
      `phone` VARCHAR(30) DEFAULT NULL,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql);

    // If an email column exists from older installations, drop it
    $emailCol = $pdo->query("SHOW COLUMNS FROM `contacts` LIKE 'email'")->fetch();
    if ($emailCol) {
        $pdo->exec("ALTER TABLE `contacts` DROP COLUMN `email`");
    }

    echo "✅ Database '$dbname' and table 'contacts' created (or already existed).<br>";
    echo "Current row count: " . $pdo->query("SELECT COUNT(*) FROM contacts")->fetchColumn();
} catch (PDOException $e) {
    die("❌ Error: " . $e->getMessage());
}