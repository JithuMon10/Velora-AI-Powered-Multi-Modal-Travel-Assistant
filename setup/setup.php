#!/usr/bin/env php
<?php
/**
 * Velora interactive setup script.
 *
 * Usage: php setup/setup.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

$root = realpath(__DIR__ . '/..') ?: dirname(__DIR__);
$envPath = $root . DIRECTORY_SEPARATOR . '.env';
$currentEnv = file_exists($envPath) ? parseEnvFile($envPath) : [];

println("\nVelora Project Setup");
println(str_repeat('=', 40));
println('This wizard will create/update your .env file and optionally set up the MySQL database.');

if (file_exists($envPath)) {
    $overwrite = (string)prompt('An existing .env was found. Overwrite it? (y/N)', 'n');
    if (!in_array(strtolower($overwrite), ['y', 'yes'], true)) {
        println('Aborting without changes.');
        exit(0);
    }
}

// --- Collect configuration values ---
$tomtomKey = promptRequired('TomTom API key (required)', $currentEnv['VELORA_TOMTOM_KEY'] ?? '');
$geminiKey = promptRequired('Google Gemini API key (required)', $currentEnv['VELORA_GEMINI_KEY'] ?? '');
$dbHost    = prompt('MySQL host', $currentEnv['VELORA_DB_HOST'] ?? '127.0.0.1');
$dbPort    = prompt('MySQL port', $currentEnv['VELORA_DB_PORT'] ?? '3306');
$dbName    = prompt('MySQL database name', $currentEnv['VELORA_DB_NAME'] ?? 'velora_db');
$dbUser    = prompt('MySQL user', $currentEnv['VELORA_DB_USER'] ?? 'velora_user');
$dbPass    = promptRequired('MySQL password for that user', $currentEnv['VELORA_DB_PASS'] ?? '');
$sqlite    = prompt('Optional SQLite fallback path (leave blank for default)', $currentEnv['VELORA_SQLITE_PATH'] ?? '');

$envData = [
    'VELORA_TOMTOM_KEY' => $tomtomKey,
    'VELORA_GEMINI_KEY' => $geminiKey,
    'VELORA_DB_HOST'    => $dbHost,
    'VELORA_DB_PORT'    => $dbPort,
    'VELORA_DB_NAME'    => $dbName,
    'VELORA_DB_USER'    => $dbUser,
    'VELORA_DB_PASS'    => $dbPass,
    'VELORA_SQLITE_PATH'=> $sqlite,
];

writeEnv($envPath, $envData);
println("\n✔ .env file written to {$envPath}");

// --- Optional database bootstrap ---
$setupDb = (string)prompt('Run MySQL database bootstrap now? (y/N)', 'n');
if (in_array(strtolower($setupDb), ['y', 'yes'], true)) {
    println('\nMySQL bootstrap will create the database and user if they do not exist.');
    $adminUser = prompt('MySQL admin user (with CREATE privileges)', 'root');
    $adminPass = prompt('MySQL admin password (leave blank if none)');
    try {
        bootstrapDatabase($dbHost, (int)$dbPort, $adminUser, $adminPass, $dbName, $dbUser, $dbPass, $root);
        println('✔ MySQL database configured.');
    } catch (Throwable $e) {
        fwrite(STDERR, "MySQL bootstrap failed: {$e->getMessage()}\n");
    }
} else {
    println('Skipping MySQL bootstrap. Remember to create the database manually.');
}

println("\nNext steps:");
println(' 1. Start the PHP server: php -S localhost:9000 -t backend/php');
println(' 2. Ensure GraphHopper is running (see README)');
println(' 3. Import station data via `import_all_stations.sql` if not already done.');
println('\nSetup complete!');

// -----------------------------------------------------------------------------
function println(string $msg): void {
    fwrite(STDOUT, $msg . PHP_EOL);
}

function prompt(string $question, string $default = ''): string {
    $suffix = $default !== '' ? " [{$default}]" : '';
    fwrite(STDOUT, $question . $suffix . ': ');
    $input = fgets(STDIN);
    $value = $input === false ? '' : trim($input);
    return $value === '' ? $default : $value;
}

function promptRequired(string $question, string $default = ''): string {
    do {
        $value = prompt($question, $default);
        if ($value !== '') {
            return $value;
        }
        println('This value is required. Please enter a value.');
    } while (true);
}

function parseEnvFile(string $path): array {
    $result = [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $result[trim($key)] = trim(trim($value), "\"'");
    }
    return $result;
}

function writeEnv(string $path, array $data): void {
    $lines = [
        '# Velora environment file',
        '# Generated on ' . date('c'),
        '',
    ];
    foreach ($data as $key => $value) {
        $lines[] = sprintf('%s=%s', $key, formatEnvValue($value));
    }
    $content = implode(PHP_EOL, $lines) . PHP_EOL;
    file_put_contents($path, $content);
}

function formatEnvValue(string $value): string {
    if ($value === '') {
        return '';
    }
    if (preg_match('/\s|"/', $value)) {
        $escaped = str_replace('"', '\\"', $value);
        return '"' . $escaped . '"';
    }
    return $value;
}

function bootstrapDatabase(string $host, int $port, string $adminUser, string $adminPass, string $dbName, string $dbUser, string $dbPass, string $rootPath): void {
    $dsn = sprintf('mysql:host=%s;port=%d', $host, $port);
    $pdo = new PDO($dsn, $adminUser, $adminPass === '' ? null : $adminPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $pdo->exec(sprintf('CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci', $dbName));
    $pdo->exec(sprintf("CREATE USER IF NOT EXISTS '%s'@'%%' IDENTIFIED BY '%s'", addslashes($dbUser), addslashes($dbPass)));
    $pdo->exec(sprintf("GRANT ALL PRIVILEGES ON `%s`.* TO '%s'@'%%'", $dbName, addslashes($dbUser)));
    $pdo->exec('FLUSH PRIVILEGES');

    // Offer to import seed data if SQL file exists
    $seedFile = $rootPath . DIRECTORY_SEPARATOR . 'import_all_stations.sql';
    if (is_file($seedFile)) {
        $runSeed = (string)prompt('Import import_all_stations.sql into the database? (y/N)', 'n');
        if (in_array(strtolower($runSeed), ['y', 'yes'], true)) {
            $sql = file_get_contents($seedFile);
            if ($sql !== false) {
                $pdo->exec('USE `' . $dbName . '`');
                $pdo->exec($sql);
                println('✔ Station data imported.');
            }
        }
    }
}
