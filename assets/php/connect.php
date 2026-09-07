<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
define('DB_CHARSET', 'utf8mb4');

// Database configurations
$databases = [
    'rdr3' => [
        'name' => 'rdr3_nativedb',
        'table' => 'rdr3_natives' // or whatever your RDR3 table name is
    ],
    'gta5' => [
        'name' => 'gta5_nativedb',
        'table' => 'gta5_natives' // or whatever your GTA5 table name is
    ]
];

// Detect which database to use based on the current script
function getCurrentDB() {
    $script = basename($_SERVER['PHP_SELF'], '.php');
    
    // Map scripts to database keys
    $scriptMap = [
        'rdr3' => 'rdr3',
        'gta' => 'gta5',
    ];
    
    // Check if the script name matches any of our database keys
    foreach ($scriptMap as $scriptName => $dbKey) {
        if (strpos($script, $scriptName) !== false) {
            return $dbKey;
        }
    }
    
    // Default to rdr3
    return 'rdr3';
}

// Get database connection with table selection
function getDBConnection($dbKey = null) {
    global $databases;
    
    // Auto-detect if not specified
    if ($dbKey === null) {
        $dbKey = getCurrentDB();
    }
    
    // Check if the database exists
    if (!isset($databases[$dbKey])) {
        throw new Exception("Database configuration not found for: " . $dbKey);
    }
    
    $dbName = $databases[$dbKey]['name'];
    
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . $dbName . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        
        // Store the table name for this connection
        $pdo->tableName = $databases[$dbKey]['table'];
        $pdo->dbKey = $dbKey;
        
        return $pdo;
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}

// Get the current database table name
function getTableName($pdo = null) {
    global $pdo;
    
    if ($pdo === null) {
        $pdo = getDBConnection();
    }
    
    return $pdo->tableName ?? 'rdr3_natives';
}

// Helper function to get PDO and table name together
function getDBWithTable() {
    $pdo = getDBConnection();
    return [
        'pdo' => $pdo,
        'table' => $pdo->tableName,
        'dbKey' => $pdo->dbKey
    ];
}

// Create the global PDO connection (for backward compatibility)
try {
    $pdo = getDBConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// You can also manually switch databases like this:
function switchDatabase($dbKey) {
    global $pdo;
    $pdo = getDBConnection($dbKey);
    return $pdo;
}