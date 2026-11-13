<?php
/** Codes promo et bons cadeaux. */
require __DIR__ . '/_init.php';
require_perm('tarifs');

$vue = ($_GET['vue'] ?? 'promo') === 'cadeaux' ? 'cadeaux' : 'promo';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $do = (string)($_POST['do'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    $st = db()->prepare('SELECT * FROM promo_codes WHERE id = ?');
    $st->execute([$id]);
    $row = $st->fetch();

    if ($do === 'add') {
        $kind = isset(PROMO_KINDS[$_POST['kind'] ?? '']) ? $_POST['kind'] : 'percent';
        $code = promo_normalize((string)($_POST['code'] ?? ''));
        $code = $code !== '' ? $code : promo_generate($kind === 'gift' ? 'CADEAU' : 'RN6');
        $value = round((float)str_replace(',', '.', (string)($_POST['value'] ?? '0')), 2);
        $from = (string)($_POST['valid_from'] ?? '');
        $to = (string)($_POST['valid_to'] ?? '');
        if ($kind === 'gift' && $to === '') {
            $to = bk_now()->modify('+1 year')->format('Y-m-d'); // validité d'un an par défaut
        }
        $errors = [];
        if (strlen($code) < 4 || strlen($code) > 30) $errors[] = 'Le code doit faire entre 4 et 30 caractères (lettres, chiffres, tirets).';
        if (promo_find($code)) $errors[] = 'Ce code existe déjà.';
        if ($value <= 0 || ($kind === 'percent' && $value > 100)) $errors[] = 'Valeur invalide.';
        if ($kind === 'gift' && strlen(preg_replace('/[^A-Z0-9]/', '', $code)) < 10) $errors[] = 'Un bon cadeau a de la valeur : laissez le code vide pour en générer un long et impossible à deviner.';
        foreach ([$from, $to] as $d) if ($d !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) $errors[] = 'Date invalide.';
        if ($errors) {
            foreach ($errors as $e) flash($e, 'err');
            redirect('codes.php?vue=' . ($kind === 'gift' ? 'cadeaux' : 'promo'));
        }
        db()->prepare('INSERT INTO promo_codes(code, kind, value, balance, max_uses, valid_from, valid_to, min_total, label, created_at) VALUES(?,?,?,?,?,?,?,?,?,?)')
            ->execute([$code, $kind, $value, $kind === 'gift' ? $value : 0, max(0, (int)($_POST['max_uses'] ?? 0)), $from, $to,
                max(0, round((float)str_replace(',', '.', (string)($_POST['min_total'] ?? '0')), 2)), mb_substr(trim((string)($_POST['label'] ?? '')), 0, 120), date('Y-m-d H:i:s')]);
        admin_log(($kind === 'gift' ? 'Bon cadeau créé : ' . money2($value) : 'Code promo créé') . ' — ' . $code);
        flash(($kind === 'gift' ? 'Bon cadeau' : 'Code') . ' ' . $code . ' créé.');
        redirect('codes.php?vue=' . ($kind === 'gift' ? 'cadeaux' : 'promo'));
    }
    if ($row && $do === 'toggle') {
        db()->prepare('UPDATE promo_codes SET active = 1 - active WHERE id = ?')->execute([$id]);
        admin_log('Code ' . $row['code'] . ($row['active'] ? ' désactivé' : ' activé'));
    }
    if ($row && $do === 'delete') {
        if (promo_uses($row['code']) > 0 || db()->query("SELECT COUNT(*) FROM bookings WHERE promo_code = " . db()->quote($row['code']) . " OR gift_code = " . db()->quote($row['code']))->fetchColumn() > 0) {
            flash('Ce code a déjà servi : désactivez-le plutôt que de le supprimer (il apparaît sur des factures).', 'err');
        } else {
            db()->prepare('DELETE FROM promo_codes WHERE id = ?')->execute([$id]);
            admin_log('Code ' . $row['code'] . ' supprimé');
            flash('Code supprimé.');
        }
    }
    if ($row && $do === 'send' && $row['kind'] === 'gift') {
        $to = trim((string)($_POST['to'] ?? ''));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            flash('Adresse e-mail invalide.', 'err');
        } else {
            $ok = send_mail($to, 'Votre bon cadeau — ' . s('site_name'), mail_layout('Un bon cadeau pour vous !', gift_card_html($row, true)), mail_reply_to());
            admin_log('Bon cadeau ' . $row['code'] . ' envoyé à ' . $to);
            flash($ok ? 'Bon cadeau envoyé à ' . $to . '.' : 'Échec de l’envoi : ' . mail_last_error(), $ok ? 'ok' : 'err');
        }
    }
    redirect('codes.php?vue=' . ($row && $row['kind'] === 'gift' ? 'cadeaux' : $vue));
}

$rows = db()->prepare($vue === 'cadeaux' ? "SELECT * FROM promo_codes WHERE kind = 'gift' ORDER BY id DESC" : "SELECT * FROM promo_codes WHERE kind != 'gift' ORDER BY id DESC");
$rows->execute();
$rows = $rows->fetchAll();
admin_header('Codes promo & bons cadeaux', 'tarifs');
?>
<h1>Tarifs & options</h1>
<?= tarifs_tabs('codes') ?>
<nav class="tabs" style="margin-top:-6px">
  <a href="?vue=promo" class="<?= $vue === 'promo' ? 'on' : '' ?>">🏷 Codes promo</a>
  <a href="?vue=cadeaux" class="<?= $vue === 'cadeaux' ? 'on' : '' ?>">🎁 Bons cadeaux</a>
</nav>

<form method="post" class="card">
  <?= csrf_field() ?>
  <?php if ($vue === 'promo'): ?>
    <h2>Créer un code promo</h2>
    <div class="grid g3">
      <?= field('code', 'Code (vide = généré)', '', 'text', 'Ex. NOEL2026, ASSO-AUXERRE') ?>
      <?= field('kind', 'Type de remise', 'percent', 'select', '', ['percent' => 'Pourcentage', 'amount' => 'Montant en € TTC']) ?>
      <?= field('value', 'Valeur (% ou €)', '') ?>
    </div>
    <div class="grid g4">
      <?= field('valid_from', 'Valable à partir du', '', 'date') ?>
      <?= field('valid_to', 'Jusqu’au', '', 'date') ?>
      <?= field('max_uses', 'Utilisations max. (0 = illimité)', '0', 'number') ?>
      <?= field('min_total', 'Minimum de réservation (€ TTC)', '0', 'number') ?>
    </div>
    <?= field('label', 'Note interne (facultatif)', '') ?>
  <?php else: ?>
    <h2>Créer un bon cadeau</h2>
    <p class="muted" style="margin-top:-6px">Montant prépayé (vendu ou offert par vous), utilisable en une ou plusieurs fois lors d’une réservation en ligne. Le code est généré automatiquement (long et impossible à deviner).</p>
    <input type="hidden" name="kind" value="gift">
    <div class="grid g3">
      <?= field('value', 'Montant (€ TTC)', '') ?>
      <?= field('valid_to', 'Valable jusqu’au (vide = 1 an)', '', 'date') ?>
      <?= field('label', 'Pour / de la part de (note)', '', 'text', 'Ex. « Offert à M. Dupont par sa famille »') ?>
    </div>
  <?php endif; ?>
  <button class="btn gold" name="do" value="add">＋ Créer</button>
</form>

<div class="card">
  <h2><?= $vue === 'promo' ? 'Codes promo' : 'Bons cadeaux' ?></h2>
  <?php if (!$rows): ?><p class="muted">Aucun pour l’instant.</p><?php else: ?>
  <div style="overflow-x:auto"><table>
    <?php if ($vue === 'promo'): ?>
      <tr><th>Code</th><th>Remise</th><th>Conditions</th><th class="num">Utilisé</th><th></th></tr>
    <?php else: ?>
      <tr><th>Code</th><th class="num">Montant</th><th class="num">Solde</th><th>Validité</th><th></th></tr>
    <?php endif; ?>
    <?php foreach ($rows as $r): $uses = (int)db()->query('SELECT COUNT(*) FROM bookings WHERE status = \'confirmed\' AND (promo_code = ' . db()->quote($r['code']) . ' OR gift_code = ' . db()->quote($r['code']) . ')')->fetchColumn(); ?>
      <tr style="<?= $r['active'] ? '' : 'opacity:.5' ?>">
        <td><code style="font-size:1rem"><?= e($r['code']) ?></code><?= $r['active'] ? '' : ' <span class="pill">désactivé</span>' ?><?= $r['label'] !== '' ? '<br><small class="muted">' . e($r['label']) . '</small>' : '' ?></td>
        <?php if ($vue === 'promo'): ?>
          <td><?= $r['kind'] === 'percent' ? '−' . e(rating_label((string)(float)$r['value'])) . ' %' : '−' . money((float)$r['value']) ?></td>
          <td><small><?= $r['valid_from'] !== '' ? 'dès le ' . e(bk_dt($r['valid_from'])->format('d/m/Y')) . ' ' : '' ?><?= $r['valid_to'] !== '' ? 'jusqu’au ' . e(bk_dt($r['valid_to'])->format('d/m/Y')) . ' ' : '' ?><?= (float)$r['min_total'] > 0 ? '· dès ' . money((float)$r['min_total']) . ' ' : '' ?><?= (int)$r['max_uses'] > 0 ? '· ' . (int)$r['max_uses'] . ' fois max.' : '' ?></small></td>
          <td class="num"><?= $uses ?></td>
        <?php else: ?>
          <td class="num"><?= money2((float)$r['value']) ?></td>
          <td class="num"><strong><?= money2((float)$r['balance']) ?></strong></td>
          <td><?= $r['valid_to'] !== '' ? 'jusqu’au ' . e(bk_dt($r['valid_to'])->format('d/m/Y')) : '—' ?></td>
        <?php endif; ?>
        <td style="white-space:nowrap">
          <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="btn light sm" name="do" value="toggle"><?= $r['active'] ? 'Désactiver' : 'Activer' ?></button>
            <?php if (!$uses): ?><button class="btn danger sm" name="do" value="delete" onclick="return confirm('Supprimer ce code ?')">✕</button><?php endif; ?>
          </form>
          <?php if ($r['kind'] === 'gift'): ?>
            <a class="btn light sm" href="bon-cadeau.php?id=<?= (int)$r['id'] ?>" target="_blank">🖨 Imprimer</a>
            <form method="post" style="display:inline-flex;gap:4px;margin-top:4px"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <input type="email" name="to" placeholder="e-mail du bénéficiaire" style="width:190px;padding:6px 8px" required><button class="btn light sm" name="do" value="send">✉ Envoyer</button></form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </table></div>
  <?php endif; ?>
</div>
<?php admin_footer();
