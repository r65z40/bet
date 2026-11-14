<?php
/** Devis établis en ligne par les clients : suivi (réservé, en attente, expiré). */
require __DIR__ . '/_init.php';
require_perm('reservations');

$f = (string)($_GET['f'] ?? 'tous');
$rows = db()->query('SELECT q.*, b.status AS b_status, b.ref AS b_ref FROM quotes q LEFT JOIN bookings b ON b.id = q.booking_id ORDER BY q.id DESC LIMIT 500')->fetchAll();
$state = function (array $q): array {
    if (($q['b_status'] ?? '') === 'confirmed') {
        return ['reserve', 'Réservé', 'background:#e7f6ec;color:#1d7a46'];
    }
    if (devis_expired($q)) {
        return ['expire', 'Expiré', ''];
    }
    return ['attente', ($q['b_status'] ?? '') !== '' ? 'Paiement non finalisé' : 'En attente', 'background:#fff3dc;color:#8a5a00'];
};
$count = ['tous' => count($rows), 'attente' => 0, 'reserve' => 0, 'expire' => 0];
foreach ($rows as $q) {
    $count[$state($q)[0]]++;
}
$list = array_filter($rows, fn($q) => $f === 'tous' || $state($q)[0] === $f);
$conv = $count['reserve'] + $count['attente'] + $count['expire'] ? round(100 * $count['reserve'] / max(1, $count['tous'])) : 0;
admin_header('Devis', 'devis');
?>
<h1>Devis en ligne</h1>
<p class="intro">Les devis que vos visiteurs ont établis eux-mêmes depuis la page de réservation (bouton « Recevoir un devis »). Un devis en attente est une bonne occasion de relancer : le client a déjà choisi sa date.</p>
<div class="kpis" style="margin-bottom:16px">
  <div class="kpi"><b><?= $count['tous'] ?></b><span>devis</span></div>
  <div class="kpi"><b><?= $count['attente'] ?></b><span>en attente</span></div>
  <div class="kpi"><b><?= $count['reserve'] ?></b><span>réservés (<?= $conv ?> %)</span></div>
</div>
<p class="actions" style="margin-bottom:12px">
  <?php foreach (['tous' => 'Tous', 'attente' => 'En attente', 'reserve' => 'Réservés', 'expire' => 'Expirés'] as $k => $l): ?>
    <a class="btn <?= $f === $k ? '' : 'light' ?> sm" href="devis.php?f=<?= $k ?>"><?= $l ?> (<?= $count[$k] ?>)</a>
  <?php endforeach; ?>
</p>
<div class="card">
<?php if (!$list): ?><p class="muted">Aucun devis pour l’instant.</p><?php else: ?>
  <div style="overflow-x:auto"><table>
    <tr><th>Devis</th><th>Client</th><th>Événement</th><th class="num">Montant TTC</th><th>État</th><th></th></tr>
    <?php foreach ($list as $q): [, $sl, $sc] = $state($q); ?>
    <tr>
      <td><strong><?= e($q['number']) ?></strong><br><span class="muted" style="font-size:.85rem">le <?= e(bk_dt($q['created_at'])->format('d/m/Y H\hi')) ?></span></td>
      <td><?= e($q['first_name'] . ' ' . $q['last_name']) ?><?= $q['company'] !== '' ? '<br><span class="muted">' . e($q['company']) . '</span>' : '' ?><br>
        <a href="mailto:<?= e($q['email']) ?>"><?= e($q['email']) ?></a><?= $q['phone'] !== '' ? ' · <a href="tel:' . e(preg_replace('/[^0-9+]/', '', $q['phone'])) . '">' . e($q['phone']) . '</a>' : '' ?></td>
      <td><?= e(devis_slot_label($q)) ?><br><span class="muted" style="font-size:.85rem"><?= e($q['event_type']) ?> · <?= (int)$q['guests'] ?> pers.</span>
        <?= $q['message'] !== '' ? '<br><span class="muted" style="font-size:.85rem">« ' . e(mb_strimwidth($q['message'], 0, 120, '…')) . ' »</span>' : '' ?></td>
      <td class="num"><strong><?= money2((float)$q['total_ttc']) ?></strong></td>
      <td><span class="pill" style="<?= $sc ?>"><?= e($sl) ?></span><?= !devis_expired($q) && ($q['b_status'] ?? '') !== 'confirmed' ? '<br><span class="muted" style="font-size:.8rem">jusqu’au ' . e(bk_dt($q['valid_until'])->format('d/m')) . '</span>' : '' ?></td>
      <td style="white-space:nowrap">
        <a class="btn light sm" href="../devis.php?n=<?= e(rawurlencode($q['number'])) ?>&amp;t=<?= e($q['token']) ?>" target="_blank">Voir</a>
        <?php if ($q['booking_id']): ?><a class="btn light sm" href="reservation.php?id=<?= (int)$q['booking_id'] ?>">Réservation</a><?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table></div>
<?php endif; ?>
</div>
<?php admin_footer();
