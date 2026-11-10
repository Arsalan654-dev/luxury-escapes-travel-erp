<?php
function generateToken() { return bin2hex(random_bytes(32)); }

function createSession(PDO $pdo, int $userId, int $daysValid = 7) {
    $token = generateToken();
    $expiresAt = date('Y-m-d H:i:s', strtotime("+{$daysValid} days"));
    $stmt = $pdo->prepare("INSERT INTO auth_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
    $stmt->execute([$userId, $token, $expiresAt]);
    return $token;
}

function requireAuth(PDO $pdo) {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    if (!$authHeader || stripos($authHeader, 'Bearer ') !== 0) { http_response_code(401); echo json_encode(['error' => 'Missing Authorization header']); exit; }
    $token = trim(substr($authHeader, 7));
    $stmt = $pdo->prepare("SELECT u.id, u.name, u.email, u.role, u.permissions, u.is_active FROM auth_tokens t JOIN users u ON u.id = t.user_id WHERE t.token = ? AND t.expires_at > NOW() LIMIT 1");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    if (!$user || !$user['is_active']) { http_response_code(401); echo json_encode(['error' => 'Session expired, please log in again']); exit; }
    return $user;
}

function requireRole(array $user, array $allowedRoles) {
    if (!in_array($user['role'], $allowedRoles)) { http_response_code(403); echo json_encode(['error' => 'Permission denied']); exit; }
}

function defaultGranularForRole(string $role): array {
    $modules = ['clients','vendors','hotel_invoices','hotel_vouchers','transport','transport_vouchers','visa','packages','ledger','vendor_ledger','payments','reports','users'];
    $all = fn() => ['create'=>true,'view'=>true,'edit'=>true,'delete'=>true];
    $ro = fn() => ['create'=>false,'view'=>true,'edit'=>false,'delete'=>false];
    $none = fn() => ['create'=>false,'view'=>false,'edit'=>false,'delete'=>false];
    $d = [];
    foreach ($modules as $m) {
        if ($role==='admin') $d[$m]=$all();
        elseif ($role==='manager') $d[$m]=($m==='users'?$ro():$all());
        elseif ($role==='accountant') $d[$m]=(in_array($m,['users'])?$none():$all());
        elseif ($role==='operations') { if(in_array($m,['users','reports'])) $d[$m]=$none(); elseif(in_array($m,['ledger','vendor_ledger','payments'])) $d[$m]=$ro(); else $d[$m]=['create'=>true,'view'=>true,'edit'=>true,'delete'=>false]; }
        else $d[$m]=$ro();
    }
    return $d;
}

function defaultPermissionsForRole(string $role): array {
    $map = ['admin'=>['manage_clients'=>true,'manage_vendors'=>true,'manage_bookings'=>true,'manage_ledger'=>true,'manage_users'=>true],'manager'=>['manage_clients'=>true,'manage_vendors'=>true,'manage_bookings'=>true,'manage_ledger'=>true,'manage_users'=>false],'accountant'=>['manage_clients'=>true,'manage_vendors'=>true,'manage_bookings'=>true,'manage_ledger'=>true,'manage_users'=>false],'operations'=>['manage_clients'=>true,'manage_vendors'=>true,'manage_bookings'=>true,'manage_ledger'=>false,'manage_users'=>false],'viewer'=>['manage_clients'=>false,'manage_vendors'=>false,'manage_bookings'=>false,'manage_ledger'=>false,'manage_users'=>false]];
    return $map[$role] ?? $map['viewer'];
}

function userHasPermission(array $user, string $capability): bool {
    $defaults = defaultPermissionsForRole($user['role']);
    $custom = null;
    if (!empty($user['permissions'])) { $decoded = is_string($user['permissions']) ? json_decode($user['permissions'], true) : $user['permissions']; if (is_array($decoded)) $custom = $decoded; }
    if ($custom !== null && array_key_exists($capability, $custom)) return (bool)$custom[$capability];
    if (array_key_exists($capability, $defaults)) return $defaults[$capability] ?? false;
    if (strpos($capability, '.') !== false) {
        list($module, $action) = explode('.', $capability, 2);
        $granular = ($custom['_granular'] ?? null) ?: defaultGranularForRole($user['role']);
        return (bool)($granular[$module][$action] ?? false);
    }
    return false;
}

function requirePermission(array $user, string $capability) {
    if (!userHasPermission($user, $capability)) { http_response_code(403); echo json_encode(['error' => 'You do not have permission to perform this action']); exit; }
}

function userCanDo(array $user, string $module, string $action): bool { return userHasPermission($user, "$module.$action"); }

function jsonInput() { return json_decode(file_get_contents('php://input'), true) ?? []; }
