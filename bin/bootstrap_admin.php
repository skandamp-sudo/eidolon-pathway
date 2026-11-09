<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/organizations/organization.php';

function parse_cli_options(array $argv): array
{
    $options = [];
    foreach (array_slice($argv, 1) as $arg) {
        if (str_starts_with($arg, '--')) {
            $parts = explode('=', substr($arg, 2), 2);
            $options[$parts[0]] = $parts[1] ?? true;
        }
    }
    return $options;
}

try {
    $options = parse_cli_options($argv);

    $email = strtolower(trim((string) ($options['email'] ?? '')));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
        fwrite(STDERR, "Error: Specify a valid email with --email=admin@example.test\n");
        exit(1);
    }

    $name = trim((string) ($options['name'] ?? 'Platform Administrator'));
    if (strlen($name) < 2 || strlen($name) > 100) {
        fwrite(STDERR, "Error: Name must be between 2 and 100 characters.\n");
        exit(1);
    }

    $providedPassword = $options['password'] ?? null;
    $generated = false;
    if (is_string($providedPassword) && strlen($providedPassword) >= 12 && strlen($providedPassword) <= 72) {
        $password = $providedPassword;
    } else {
        // Cryptographically secure 24-character random password
        $password = bin2hex(random_bytes(12));
        $generated = true;
    }

    $pdo = \Pathway\database();

    // Check if user already exists
    $stmt = $pdo->prepare('SELECT id, role FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $existing = $stmt->fetch();
    if ($existing) {
        fwrite(STDERR, "Error: An account with this email already exists (ID: {$existing['id']}, Role: {$existing['role']}).\n");
        exit(1);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'admin')");
        $stmt->execute([$name, $email, $hash]);
        $adminId = (int) $pdo->lastInsertId();

        \Pathway\Organizations\record_audit(
            $pdo,
            $adminId,
            'admin.bootstrap',
            'user',
            $adminId,
            ['email' => $email, 'name' => $name, 'generated_password' => $generated]
        );

        $pdo->exec('COMMIT');
    } catch (\Throwable $error) {
        $pdo->exec('ROLLBACK');
        throw $error;
    }

    echo "=== SECURE ADMIN BOOTSTRAP ===\n";
    echo "Admin Account ID : {$adminId}\n";
    echo "Name             : {$name}\n";
    echo "Email            : {$email}\n";
    echo "Role             : admin\n";
    if ($generated) {
        echo "One-Time Password: {$password}\n";
        echo "Important: Store this one-time password securely. It is not saved in plaintext.\n";
    } else {
        echo "Password         : [Provided password accepted]\n";
    }
    echo "Status           : Bootstrap completed successfully.\n";

} catch (\Throwable $error) {
    fwrite(STDERR, "Bootstrap failed: " . $error->getMessage() . "\n");
    exit(1);
}
