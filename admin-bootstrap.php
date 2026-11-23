<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (empty($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin-login.php'); exit;
}
$host = 'localhost'; $db = 'seasonverse_db'; $user = 'root'; $pass = '';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    error_log('SeasonVerse admin DB error: ' . $e->getMessage());
    http_response_code(500); exit('Database connection failed. Check XAMPP MySQL and seasonverse_db configuration.');
}
// AI chat history is currently stored by user-ai.php in the legacy 'seasonverse' database.
$aiPdo = null;
try { $aiPdo = new PDO('mysql:host=localhost;dbname=seasonverse;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]); } catch (PDOException $e) { $aiPdo = null; }
function sv_table_exists(PDO $pdo, string $table): bool {
    $q=$pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?'); $q->execute([$table]); return (bool)$q->fetchColumn();
}
function sv_column_exists(PDO $pdo, string $table, string $column): bool {
    $q=$pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?'); $q->execute([$table,$column]); return (bool)$q->fetchColumn();
}
function sv_add_column_if_missing(PDO $pdo, string $table, string $column, string $definition): void {
    if (sv_table_exists($pdo,$table) && !sv_column_exists($pdo,$table,$column)) { $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition"); }
}
