<?php
// Account unico fmenegazzi: /oidc?start[&register=1|&force=1][&next=/...] avvia il login,
// /oidc?code=...&state=... è il ritorno da account.fmenegazzi.it, /oidc?logout esce ovunque.
require_once __DIR__ . '/includes/config.php'; // bootstrap db/metrics/auth + sessione
require_once __DIR__ . '/includes/i18n.php';

if (isset($_GET['logout'])) oidcLogout();
if (isset($_GET['start'])) {
    // Già dentro: torna indietro (force=1: login comunque, per collegare un vecchio account all'account unico)
    if (isLoggedIn() && empty($_GET['register']) && empty($_GET['force'])) { header('Location: ' . safeNext($_GET['next'] ?? '/')); exit; }
    oidcStart($_GET['next'] ?? '/', !empty($_GET['register']));
}
if (isset($_GET['state'])) oidcCallback();
header('Location: /account');
exit;
