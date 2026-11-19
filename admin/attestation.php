<?php
/** Attestation d'assurance déposée par un client (administration uniquement). */
require __DIR__ . '/_init.php';
require_perm('reservations');

$b = bk_get((int)($_GET['id'] ?? 0));
$path = $b ? insurance_path($b) : null;
if (!$path) {
    http_response_code(404);
    exit('Attestation introuvable.');
}
$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$types = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'heic' => 'image/heic'];
$name = 'attestation-assurance-' . $b['ref'] . '.' . $ext;
header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
header('Content-Length: ' . filesize($path));
// PDF et photo affichés dans le navigateur ; tout autre format téléchargé
header('Content-Disposition: ' . (in_array($ext, ['pdf', 'jpg'], true) ? 'inline' : 'attachment') . '; filename="' . $name . '"');
header('X-Content-Type-Options: nosniff');
if ($ext === 'jpg') {
    header("Content-Security-Policy: default-src 'none'; img-src 'self'; sandbox"); // remplace celle de l'administration
}
readfile($path);
