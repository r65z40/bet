<?php
/** Formulaire de contact de la page d'accueil (envoi en arrière-plan, ou formulaire classique sans JavaScript). */
require __DIR__ . '/includes/bootstrap.php';

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ./#contact', true, 302);
    exit;
}
$json = str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
$err = contact_send($_POST);
if ($json) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($err === null ? 200 : 422);
    echo json_encode($err === null ? ['ok' => true] : ['error' => $err], JSON_UNESCAPED_UNICODE);
    exit;
}
header('Location: ./?' . ($err === null ? 'contact=ok' : 'contact=err') . '#contact', true, 303);
