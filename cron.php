<?php
/**
 * Tâche planifiée : rappels J-7, demandes d'avis, paiements abandonnés, envois groupés, purge.
 *  - OVH : Tâches planifiées - Cron › commande « www/cron.php », toutes les heures (exécution en ligne de commande)
 *  - Ou par un service externe : https://votre-site/cron.php?k=<clé secrète affichée dans l'admin>
 */
define('NO_CRON', true);
require __DIR__ . '/includes/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    $k = (string)($_GET['k'] ?? '');
    if ($k === '' || !hash_equals(s('bk_cron_token'), $k)) {
        http_response_code(404);
        exit;
    }
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
}
$r = run_scheduled_tasks(true); // appel explicite (OVH ou URL à clé secrète) : toujours exécuté
echo date('d/m/Y H:i') . ' — ' . (!empty($r['skipped']) ? 'déjà exécuté récemment' : json_encode($r, JSON_UNESCAPED_UNICODE)) . "\n";
