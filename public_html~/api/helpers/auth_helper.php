<?php
// =====================================================
// Auth Helper Functions
// =====================================================

function generateToken() {
    return bin2hex(random_bytes(32)); // 64 char token
}

function createSession(PDO $pdo, int $userId, int $daysValid = 7) {
    $token = generateToken();
    $expiresAt = date('Y-m-d H:i:s', strtotime("+{$daysValid} days"));

    $stmt = $pdo->prepare("INSERT INTO auth_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
    $stmt->execute([$userId, $token, $expiresAt]);

    return $token;
}

// Reads Authorization: Bearer <token> header, validates it,
// returns the logged-in user array, or sends 401 and exits.
function requireAuth(PDO $pdo) {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

    if (!$authHeader || stripos($authHeader, 'Bearer ') !== 0) {
        http_response_code(401);
        echo json_encode(['error' => 'Missing or invalid Authorization header']);
        exit;
    }

    $token = trim(substr($authHeader, 7));

    $stmt = $pdo->prepare("
        SELECT u.id, u.name, u.email, u.role, u.permissions, u.is_active
        FROM auth_tokens t
        JOIN users u ON u.id = t.user_id
        WHERE t.token = ? AND t.expires_at > NOW()
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user || !$user['is_active']) {
        http_response_code(401);
        echo json_encode(['error' => 'Session expired or invalid, please log in again']);
        exit;
    }

    return $user;
}

// Restrict endpoint to specific roles, e.g. requireRole($user, ['admin'])
function requireRole(array $user, array $allowedRoles) {
    if (!in_array($user['role'], $allowedRoles)) {
        http_response_code(403);
        echo json_encode(['error' => 'You do not have permission to perform this action']);
        exit;
    }
}

// Default permission sets per role — used when a user has no custom overrides.
function defaultPermissionsForRole(string $role): array {
    $defaults = [
        'admin' => ['manage_clients' => true, 'manage_vendors' => true, 'manage_bookings' => true, 'manage_ledger' => true, 'manage_users' => true],
        'accountant' => ['manage_clients' => true, 'manage_vendors' => true, 'manage_bookings' => true, 'manage_ledger' => true, 'manage_users' => false],
        'operations' => ['manage_clients' => true, 'manage_vendors' => true, 'manage_bookings' => true, 'manage_ledger' => false, 'manage_users' => false],
        'viewer' => ['manage_clients' => false, 'manage_vendors' => false, 'manage_bookings' => false, 'manage_ledger' => false, 'manage_users' => false],
    ];
    return $defaults[$role] ?? $defaults['viewer'];
}

// Checks a specific capability, respecting custom per-user overrides
// stored in users.permissions (JSON), falling back to role defaults.
// $user here should include 'permissions' (raw JSON string or null) and 'role'.
function userHasPermission(array $user, string $capability): bool {
    $defaults = defaultPermissionsForRole($user['role']);
    $custom = null;

    if (!empty($user['permissions'])) {
        $decoded = is_string($user['permissions']) ? json_decode($user['permissions'], true) : $user['permissions'];
        if (is_array($decoded)) {
            $custom = $decoded;
        }
    }

    if ($custom !== null && array_key_exists($capability, $custom)) {
        return (bool)$custom[$capability];
    }

    return $defaults[$capability] ?? false;
}

// Like requireRole, but checks a specific capability instead of a fixed role list.
function requirePermission(array $user, string $capability) {
    if (!userHasPermission($user, $capability)) {
        http_response_code(403);
        echo json_encode(['error' => 'You do not have permission to perform this action']);
        exit;
    }
}

function jsonInput() {
    $data = json_decode(file_get_contents('php://input'), true);
    return $data ?? [];
}
