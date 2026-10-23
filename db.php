<?php
// Identical application code for XAMPP, GitHub and hosting.
// Only config.local.php changes per environment and stays outside Git.
$configPath = __DIR__ . '/config.local.php';
if (!is_file($configPath)) {
    http_response_code(503);
    exit('Database configuration is missing. Create config.local.php.');
}
$config = require $configPath;
foreach (['host','username','password','database'] as $field) {
    if (!isset($config[$field]) || str_starts_with((string)$config[$field], 'REPLACE_WITH_')) {
        http_response_code(503);
        exit('Please complete the database configuration in config.local.php.');
    }
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $conn = new mysqli($config['host'], $config['username'], $config['password'], $config['database']);
    $conn->set_charset('utf8mb4');
} catch (Throwable $error) {
    error_log('Database connection error: ' . $error->getMessage());
    http_response_code(503);
    exit('The database is not available. Please check the hosting MySQL settings.');
}
