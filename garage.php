<?php
require_once __DIR__ . '/includes/config.php'; // bootstrap db/metrics/auth + sessione
header('Content-Type: application/json');

if (!isLoggedIn()) { http_response_code(401); echo json_encode(['ok' => false, 'error' => 'auth']); exit; }
$uid    = currentUserId();
$action = $_REQUEST['action'] ?? ($_SERVER['REQUEST_METHOD'] === 'GET' ? 'list' : '');

// Le azioni che modificano richiedono CSRF
if ($action !== 'list' && !csrfCheck($_POST['csrf'] ?? '')) {
    echo json_encode(['ok' => false, 'error' => 'csrf']); exit;
}
function validTipo($t) { return in_array($t, ['benzina', 'gasolio', 'gpl', 'metano'], true); }

try {
    if ($action === 'list') {
        $st = pdo()->prepare('SELECT id, nome, tipo, consumo FROM vehicles WHERE user_id = :u ORDER BY id');
        $st->execute([':u' => $uid]);
        $rows = array_map(function ($v) {
            return ['id' => (string)$v['id'], 'nome' => $v['nome'], 'tipo' => $v['tipo'], 'consumo' => (float)$v['consumo']];
        }, $st->fetchAll());
        echo json_encode(['ok' => true, 'vehicles' => $rows]); exit;
    }

    if ($action === 'add' || $action === 'update') {
        $nome    = trim($_POST['nome'] ?? '');
        $tipo    = $_POST['tipo'] ?? '';
        $consumo = (float)($_POST['consumo'] ?? 0);
        if ($nome === '' || mb_strlen($nome) > 60 || !validTipo($tipo) || $consumo <= 0 || $consumo > 50) {
            echo json_encode(['ok' => false, 'error' => 'invalid']); exit;
        }
        if ($action === 'add') {
            $st = pdo()->prepare('INSERT INTO vehicles (user_id, nome, tipo, consumo) VALUES (:u,:n,:t,:c) RETURNING id, nome, tipo, consumo');
            $st->execute([':u' => $uid, ':n' => $nome, ':t' => $tipo, ':c' => $consumo]);
            $v = $st->fetch();
            track('garage_add', ['fuel' => $tipo]);
            echo json_encode(['ok' => true, 'vehicle' => ['id' => (string)$v['id'], 'nome' => $v['nome'], 'tipo' => $v['tipo'], 'consumo' => (float)$v['consumo']]]); exit;
        } else {
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) { echo json_encode(['ok' => false, 'error' => 'invalid']); exit; }
            $st = pdo()->prepare('UPDATE vehicles SET nome=:n, tipo=:t, consumo=:c, updated_at=now() WHERE id=:id AND user_id=:u');
            $st->execute([':n' => $nome, ':t' => $tipo, ':c' => $consumo, ':id' => $id, ':u' => $uid]);
            echo json_encode(['ok' => true]); exit;
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        pdo()->prepare('DELETE FROM vehicles WHERE id=:id AND user_id=:u')->execute([':id' => $id, ':u' => $uid]);
        echo json_encode(['ok' => true]); exit;
    }

    echo json_encode(['ok' => false, 'error' => 'bad_action']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server']);
}
