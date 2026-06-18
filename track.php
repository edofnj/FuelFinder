<?php
require_once __DIR__ . '/includes/config.php'; // bootstrap db/metrics/auth
header('Content-Type: application/json');

// Beacon per eventi lato client. Anonimo permesso. Solo type in allowlist.
$type  = $_POST['type'] ?? '';
$allow = ['maps_click', 'pwa_install', 'tutorial_done', 'garage_open', 'sos_click'];
if (!in_array($type, $allow, true)) { echo json_encode(['ok' => false]); exit; }

track($type, [
    'country' => isset($_POST['country']) ? substr((string)$_POST['country'], 0, 4) : null,
    'page'    => isset($_POST['page']) ? substr((string)$_POST['page'], 0, 40) : null,
]);
echo json_encode(['ok' => true]);
