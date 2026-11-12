<?php
/** Bon cadeau imprimable (à remettre en main propre). */
require __DIR__ . '/_init.php';
require_perm('tarifs');
$st = db()->prepare("SELECT * FROM promo_codes WHERE id = ? AND kind = 'gift'");
$st->execute([(int)($_GET['id'] ?? 0)]);
$g = $st->fetch();
if (!$g) {
    http_response_code(404);
    exit('Bon cadeau introuvable.');
}
?><!doctype html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>Bon cadeau <?= e($g['code']) ?></title>
<style>body{margin:0;padding:30px 16px;background:#eef1f4;font-family:Arial,sans-serif}.wrap{max-width:620px;margin:auto}.print{text-align:right;margin-bottom:12px}.print button{padding:10px 16px;border:0;border-radius:8px;background:#071c35;color:#fff;font-weight:bold;cursor:pointer}@media print{body{background:#fff;padding:0}.print{display:none}}</style>
</head><body><div class="wrap"><div class="print"><button onclick="window.print()">Imprimer</button></div><?= gift_card_html($g) ?></div></body></html>
