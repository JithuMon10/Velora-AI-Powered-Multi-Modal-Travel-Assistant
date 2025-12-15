<?php
// setup_database.php - Database setup and sample data importer
require_once __DIR__ . '/config.php';

// Database connection function
function get_pdo(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/plain');
echo "=== Database Setup ===\n";

try {
    $pdo = get_pdo();
    
    // 1. Create missing tables
    $tables = [
        'bus_stations' => "
            CREATE TABLE IF NOT EXISTS `bus_stations` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `name` varchar(255) NOT NULL,
                `lat` decimal(10,8) NOT NULL,
                `lon` decimal(11,8) NOT NULL,
                `city` varchar(100) DEFAULT NULL,
                `state` varchar(100) DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_lat_lon` (`lat`,`lon`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ",
        'railway_stations' => "
            CREATE TABLE IF NOT EXISTS `railway_stations` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `name` varchar(255) NOT NULL,
                `lat` decimal(10,8) NOT NULL,
                `lon` decimal(11,8) NOT NULL,
                `city` varchar(100) DEFAULT NULL,
                `state` varchar(100) DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_lat_lon` (`lat`,`lon`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ",
        'airports' => "
            CREATE TABLE IF NOT EXISTS `airports` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `name` varchar(255) NOT NULL,
                `iata_code` varchar(3) DEFAULT NULL,
                `lat` decimal(10,8) NOT NULL,
                `lon` decimal(11,8) NOT NULL,
                `city` varchar(100) DEFAULT NULL,
                `country` varchar(100) DEFAULT 'India',
                PRIMARY KEY (`id`),
                KEY `idx_lat_lon` (`lat`,`lon`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        "
    ];

    echo "1. Creating missing tables...\n";
    foreach ($tables as $table => $sql) {
        try {
            $pdo->exec($sql);
            echo "   Created table: $table\n";
        } catch (PDOException $e) {
            echo "   Error creating $table: " . $e->getMessage() . "\n";
        }
    }

    // 2. Add sample data if tables are empty
    echo "\n2. Adding sample data...\n";
    
    // Sample bus stations (Kerala)
    $busStations = [
        ['Ernakulam KSRTC Bus Stand', 9.9816358, 76.2998842, 'Kochi', 'Kerala'],
        ['Thiruvananthapuram Central', 8.4875, 76.9525, 'Thiruvananthapuram', 'Kerala'],
        ['Kozhikode Mofussil Bus Stand', 11.2588, 75.7804, 'Kozhikode', 'Kerala'],
        ['Thrissur Sakthan Stand', 10.5276, 76.2144, 'Thrissur', 'Kerala'],
        ['Kannur Central Bus Station', 11.8745, 75.3704, 'Kannur', 'Kerala']
    ];

    $stmt = $pdo->prepare("INSERT IGNORE INTO bus_stations (name, lat, lon, city, state) VALUES (?, ?, ?, ?, ?)");
    foreach ($busStations as $station) {
        $stmt->execute($station);
    }
    echo "   Added " . count($busStations) . " bus stations\n";

    // Sample railway stations (Kerala)
    $railwayStations = [
        ['Ernakulam Junction', 9.967427, 76.292229, 'Kochi', 'Kerala'],
        ['Thiruvananthapuram Central', 8.4875, 76.9525, 'Thiruvananthapuram', 'Kerala'],
        ['Kozhikode Railway Station', 11.2734, 75.8004, 'Kozhikode', 'Kerala'],
        ['Thrissur Railway Station', 10.5276, 76.2144, 'Thrissur', 'Kerala'],
        ['Kannur Railway Station', 11.8762, 75.3733, 'Kannur', 'Kerala']
    ];

    $stmt = $pdo->prepare("INSERT IGNORE INTO railway_stations (name, lat, lon, city, state) VALUES (?, ?, ?, ?, ?)");
    foreach ($railwayStations as $station) {
        $stmt->execute($station);
    }
    echo "   Added " . count($railwayStations) . " railway stations\n";

    // Sample airports (Kerala)
    $airports = [
        ['Cochin International Airport', 'COK', 10.1550, 76.4010, 'Kochi', 'India'],
        ['Trivandrum International Airport', 'TRV', 8.4821, 76.9204, 'Thiruvananthapuram', 'India'],
        ['Calicut International Airport', 'CCJ', 11.1368, 75.9553, 'Kozhikode', 'India'],
        ['Kannur International Airport', 'CNN', 11.9890, 75.5489, 'Kannur', 'India']
    ];

    $stmt = $pdo->prepare("INSERT IGNORE INTO airports (name, iata_code, lat, lon, city, country) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($airports as $airport) {
        $stmt->execute($airport);
    }
    echo "   Added " . count($airports) . " airports\n";

    // 3. Add sample stops if empty
    $count = $pdo->query("SELECT COUNT(*) FROM stops")->fetchColumn();
    if ($count == 0) {
        echo "\n3. Adding sample bus stops...\n";
        
        $sampleStops = [
            ['KSRTC Bus Stand', 'KSRTC', 'Kochi', 9.9816, 76.2999],
            ['Aluva Bus Stand', 'KSRTC', 'Kochi', 10.1077, 76.3516],
            ['Kakkanad Junction', 'Private', 'Kochi', 10.0168, 76.3419],
            ['Vytilla Hub', 'KURTC', 'Kochi', 9.9674, 76.3151],
            ['Fort Kochi', 'Private', 'Kochi', 9.9674, 76.2458]
        ];

        $stmt = $pdo->prepare("INSERT INTO stops (stop_name, operator_name, city, latitude, longitude) VALUES (?, ?, ?, ?, ?)");
        foreach ($sampleStops as $stop) {
            $stmt->execute($stop);
        }
        echo "   Added " . count($sampleStops) . " sample bus stops\n";
    } else {
        echo "\n3. Stops table already contains $count records\n";
    }

    echo "\n=== Setup Complete ===\n";
    echo "1. Database tables have been created\n";
    echo "2. Sample data has been added\n";
    echo "3. You can now use the application\n";
    echo "\nNext steps:\n";
    echo "1. Try accessing the application again\n";
    echo "2. If you need more data, you can import additional records\n";
    echo "3. Check the application logs if you encounter any issues\n";

} catch (PDOException $e) {
    echo "\n!!! Error: " . $e->getMessage() . "\n";
    echo "\nPlease check your database configuration in config.php and ensure:\n";
    echo "1. The database server is running\n";
    echo "2. The database 'velora_db' exists\n";
    echo "3. The user has proper permissions\n";
}
