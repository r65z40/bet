<?php
/** Réservations : liste filtrable, planning mensuel, blocages de créneaux. */
require __DIR__ . '/_init.php';
require_perm('reservations');
bk_expire_pending();
// Agenda Google relu à l'ouverture (au plus une fois par minute) : un événement ajouté depuis l'iPhone apparaît tout de suite
if ($_SERVER['REQUEST_METHOD'] === 'GET' && s('bk_gcal_url') !== '' && time() - (int)s('_gcal_last_try') > 60 && can_finish_early()) {
    save_setting('_gcal_last_try', (string)time());
    after_response(fn() => gcal_sync_if_needed(true), 'relecture agenda Google (admin)');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $do = (string)($_POST['do'] ?? '');
    if ($do === 'block') {
        $from = (string)($_POST['from'] ?? '');
        $to = (string)($_POST['to'] ?? '');
        $label = mb_substr(trim((string)($_POST['label'] ?? '')), 0, 120);
        $ok = preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $from) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $to) && $to > $from;
        if (!$ok) {
            flash('Dates de blocage invalides (la fin doit être après le début).', 'err');
        } else {
            db()->prepare('INSERT INTO booking_blocks(start_at, end_at, label) VALUES(?,?,?)')
                ->execute([str_replace('T', ' ', $from) . ':00', str_replace('T', ' ', $to) . ':00', $label]);
            $st = db()->prepare('SELECT * FROM booking_blocks WHERE id = ?');
            $st->execute([(int)db()->lastInsertId()]);
            $blk = $st->fetch();
            after_response(fn() => gcal_sync_block($blk), 'Google Agenda (blocage)');
            flash('Créneau bloqué : il n’est plus réservable sur le site.');
        }
    } elseif ($do === 'unblock') {
        $st = db()->prepare('SELECT * FROM booking_blocks WHERE id = ?');
        $st->execute([(int)($_POST['id'] ?? 0)]);
        if ($bl = $st->fetch()) {
            after_response(fn() => gcal_sync_block($bl, true), 'Google Agenda (blocage retiré)');
        }
        db()->prepare('DELETE FROM booking_blocks WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]);
        flash('Blocage supprimé.');
    } elseif ($do === 'purge_tests' && admin_can('reglages')) {
        require_reauth('reservations.php?vue=liste&f=annulees');
        $n = 0;
        $st = db()->prepare("SELECT * FROM bookings WHERE status IN ('cancelled', 'expired') OR (status = 'pending' AND (hold_until IS NULL OR hold_until < ?)) ORDER BY id DESC");
        $st->execute([bk_now()->format(BK_FMT)]);
        foreach ($st->fetchAll() as $b) {
            try {
                bk_purge($b);
                $n++;
            } catch (Throwable) {
            }
        }
        admin_log($n . ' réservation(s) annulée(s) ou abandonnée(s) supprimée(s) définitivement (nettoyage des tests)');
        flash($n . ' réservation(s) supprimée(s) définitivement.');
        redirect('reservations.php?vue=liste');
    }
    redirect('reservations.php?' . http_build_query(array_intersect_key($_GET, ['vue' => 1, 'm' => 1, 'f' => 1])));
}

$vue = ($_GET['vue'] ?? 'liste') === 'planning' ? 'planning' : 'liste';
$filters = ['avenir' => 'À venir', 'attente' => 'Non payées', 'passees' => 'Passées', 'annulees' => 'Annulées', 'toutes' => 'Toutes'];
$f = (string)($_GET['f'] ?? 'avenir');
if (!isset($filters[$f])) {
    $f = 'avenir';
}
$q = trim((string)($_GET['q'] ?? ''));
$now = bk_now()->format(BK_FMT);

// Indicateurs
$kpi = fn($sql, $p = []) => (function () use ($sql, $p) { $st = db()->prepare($sql); $st->execute($p); return (float)$st->fetchColumn(); })();
$monthStart = bk_now()->format('Y-m-01 00:00:00');
$yearStart = bk_now()->format('Y-01-01 00:00:00');
$upcoming = (int)$kpi("SELECT COUNT(*) FROM bookings WHERE status = 'confirmed' AND end_at > ?", [$now]);
$caMonth = $kpi("SELECT COALESCE(SUM(total_ttc - refunded_amount), 0) FROM bookings WHERE paid_at >= ? AND status IN ('confirmed','cancelled')", [$monthStart]);
$caYear = $kpi("SELECT COALESCE(SUM(total_ttc - refunded_amount), 0) FROM bookings WHERE paid_at >= ? AND status IN ('confirmed','cancelled')", [$yearStart]);
$pendingCount = (int)$kpi("SELECT COUNT(*) FROM bookings WHERE status = 'pending'");

admin_header('Réservations', 'resa');
?>
<h1>Réservations</h1>
<p class="intro"><a href="../reservation.php" target="_blank">Voir la page de réservation ↗</a></p>

<?php if (!payment_providers()): ?><div class="flash flash-err">Aucun moyen de paiement n’est configuré : <a href="reglages.php?s=paiement">activer Stripe ou PayPal</a>.</div><?php endif; ?>
<?php if (s('_gcal_error') !== ''): ?><div class="flash flash-err">Agenda Google : <?= e(s('_gcal_error')) ?> (<a href="reglages.php?s=agenda">vérifier</a>)</div><?php endif; ?>
<?php if (s('_mail_last_error') !== ''): ?><div class="flash flash-err">Envoi d’e-mail en échec : <?= e(s('_mail_last_error')) ?> (<a href="reglages.php?s=emails">vérifier</a>)</div><?php endif; ?>

<div class="kpis">
  <div class="kpi"><b><?= $upcoming ?></b><span>Réservations à venir</span></div>
  <div class="kpi"><b><?= money($caMonth) ?></b><span>Encaissé ce mois</span></div>
  <div class="kpi"><b><?= money($caYear) ?></b><span>Encaissé en <?= bk_now()->format('Y') ?> <small>(TTC, remboursements déduits)</small></span></div>
  <div class="kpi"><b><?= $pendingCount ?></b><span>Paiements en cours</span></div>
</div>

<nav class="tabs">
  <a href="?vue=liste" class="<?= $vue === 'liste' ? 'on' : '' ?>">☰ Liste</a>
  <a href="?vue=planning" class="<?= $vue === 'planning' ? 'on' : '' ?>">📅 Planning</a>
</nav>

<?php if ($vue === 'liste'):
    $where = match ($f) {
        'avenir' => ["status = 'confirmed' AND end_at > ?", [$now], 'start_at ASC'],
        'attente' => ["status IN ('pending','expired')", [], 'created_at DESC'],
        'passees' => ["status = 'confirmed' AND end_at <= ?", [$now], 'start_at DESC'],
        'annulees' => ["status = 'cancelled'", [], 'start_at DESC'],
        default => ['1 = 1', [], 'start_at DESC'],
    };
    [$sql, $params, $order] = $where;
    if ($q !== '') {
        $sql .= " AND (ref LIKE ? OR last_name LIKE ? OR first_name LIKE ? OR email LIKE ? OR phone LIKE ? OR company LIKE ?)";
        $params = array_merge($params, array_fill(0, 6, '%' . $q . '%'));
    }
    $st = db()->prepare("SELECT * FROM bookings WHERE $sql ORDER BY $order LIMIT 300");
    $st->execute($params);
    $rows = $st->fetchAll();
?>
<div class="card">
  <form class="actions" style="justify-content:space-between;margin-bottom:12px">
    <input type="hidden" name="vue" value="liste">
    <nav class="tabs" style="margin:0"><?php foreach ($filters as $k => $label): ?><a href="?vue=liste&amp;f=<?= $k ?>" class="<?= $k === $f ? 'on' : '' ?>"><?= e($label) ?></a><?php endforeach; ?></nav>
    <span class="actions"><input type="hidden" name="f" value="<?= e($f) ?>"><input type="text" name="q" value="<?= e($q) ?>" placeholder="Nom, réf., e-mail…" style="width:220px"><button class="btn light sm">Rechercher</button></span>
  </form>
  <?php if (!$rows): ?><p class="muted">Aucune réservation.</p><?php else: ?>
  <div style="overflow-x:auto"><table>
    <tr><th>Date</th><th>Client</th><th>Événement</th><th class="num">Total TTC</th><th>Statut</th><th></th></tr>
    <?php foreach ($rows as $b): [$sl, $sc] = BOOKING_STATUSES[$b['status']] ?? ['?', 'gray']; $s = bk_dt($b['start_at']); ?>
    <tr>
      <td><strong><?= e(fr_date($s)) ?></strong><br><small class="muted"><?= $b['mode'] === 'day' ? (int)$b['hours'] . ' h dès ' . $s->format('H\hi') : $s->format('H\hi') . '–' . bk_dt($b['end_at'])->format('H\hi') ?></small></td>
      <td><?= e($b['first_name'] . ' ' . $b['last_name']) ?><?= $b['company'] ? '<br><small class="muted">' . e($b['company']) . '</small>' : '' ?><br><small class="muted"><?= e($b['phone']) ?></small></td>
      <td><?= e($b['event_type']) ?><br><small class="muted"><?= (int)$b['guests'] ?> pers. · <?= e($b['ref']) ?></small></td>
      <td class="num"><?= money2((float)$b['total_ttc']) ?><?= (float)$b['refunded_amount'] > 0 ? '<br><small class="muted">remb. ' . money2((float)$b['refunded_amount']) . '</small>' : '' ?></td>
      <td><span class="pill <?= $sc === 'green' ? 'gold' : '' ?>" style="<?= $sc === 'red' ? 'background:#fdecea;color:#b42318' : ($sc === 'green' ? 'background:#e7f6ec;color:#1d7a46' : '') ?>"><?= e($sl) ?></span></td>
      <td><a class="btn light sm" href="reservation.php?id=<?= (int)$b['id'] ?>">Ouvrir</a></td>
    </tr>
    <?php endforeach; ?>
  </table></div>
  <?php endif; ?>
  <?php $nTest = (int)db()->query("SELECT COUNT(*) FROM bookings WHERE status IN ('cancelled', 'expired')")->fetchColumn();
  if (admin_can('reglages') && $nTest && in_array($f, ['annulees', 'attente'], true)): ?>
  <form method="post" style="margin-top:16px;padding-top:14px;border-top:1px solid var(--line)">
    <?= csrf_field() ?>
    <p class="muted" style="margin:0 0 8px"><strong>Nettoyer les tests :</strong> supprime définitivement les <?= $nTest ?> réservation(s) annulée(s) ou abandonnée(s), avec leurs factures et avoirs (liste, planning, statistiques, comptabilité). À n’utiliser qu’avant l’ouverture ou pour des tests : les factures des vrais clients doivent être conservées.</p>
    <?= reauth_field() ?>
    <button class="btn danger sm" name="do" value="purge_tests" onclick="return confirm('Supprimer définitivement <?= $nTest ?> réservation(s) annulée(s) ou abandonnée(s) ? Irréversible.')">🗑 Supprimer les <?= $nTest ?> réservation(s) annulée(s) / abandonnée(s)</button>
  </form>
  <?php endif; ?>
</div>

<?php else:
    $m = preg_match('/^\d{4}-\d{2}$/', (string)($_GET['m'] ?? '')) ? $_GET['m'] : bk_now()->format('Y-m');
    $first = bk_dt($m . '-01');
    $last = $first->modify('last day of this month')->setTime(23, 59, 59);
    $gridStart = $first->modify('-' . ((int)$first->format('N') - 1) . ' days');
    $items = [];
    // $html reçoit les horaires à afficher pour chaque jour : « 05:15–06:15 », « dès 10:00 », « jusqu'à 10:00 » ou « Journée »
    $add = function (string $s, string $e, callable $html) use (&$items) {
        $sd = substr($s, 0, 10);
        $st = substr($s, 11, 5);
        $et = substr($e, 11, 5);
        // une fin à minuit appartient au jour précédent
        $ed = $et === '00:00' && substr($e, 0, 10) > $sd ? bk_dt(substr($e, 0, 10))->modify('-1 day')->format('Y-m-d') : substr($e, 0, 10);
        $etShown = $et === '00:00' ? '24:00' : $et;
        for ($d = bk_dt($sd); $d->format('Y-m-d') <= $ed; $d = $d->modify('+1 day')) {
            $k = $d->format('Y-m-d');
            $from = $k === $sd ? $st : '00:00';
            $to = $k === $ed ? $etShown : '24:00';
            $label = $from === '00:00' && $to === '24:00' ? 'Journée' : ($from === '00:00' ? 'jusqu’à ' . $to : ($to === '24:00' ? 'dès ' . $from : $from . '–' . $to));
            $items[$k][] = $html($label);
        }
    };
    $st = db()->prepare("SELECT * FROM bookings WHERE start_at <= ? AND end_at >= ? AND (status = 'confirmed' OR (status = 'pending' AND hold_until > ?)) ORDER BY start_at");
    $st->execute([$last->modify('+7 days')->format(BK_FMT), $gridStart->format(BK_FMT), $now]);
    foreach ($st as $b) {
        $add($b['start_at'], $b['end_at'], fn($h) => '<a class="ev ' . ($b['status'] === 'pending' ? 'ev-pend' : 'ev-ok') . '" href="reservation.php?id=' . (int)$b['id'] . '">'
            . e($h . ' ' . $b['last_name']) . '<small>' . e($b['event_type']) . ($b['status'] === 'pending' ? ' · paiement en cours' : '') . '</small></a>');
    }
    $st = db()->prepare('SELECT * FROM booking_blocks WHERE start_at <= ? AND end_at >= ?');
    $st->execute([$last->modify('+7 days')->format(BK_FMT), $gridStart->format(BK_FMT)]);
    foreach ($st as $bl) {
        $add($bl['start_at'], $bl['end_at'], fn($h) => '<span class="ev ev-block">⛔ ' . e($h . ' ' . ($bl['label'] ?: 'Bloqué')) . '</span>');
    }
    $st = db()->prepare('SELECT * FROM ext_events WHERE start_at <= ? AND end_at >= ?');
    $st->execute([$last->modify('+7 days')->format(BK_FMT), $gridStart->format(BK_FMT)]);
    foreach ($st as $ev) {
        $add($ev['start_at'], $ev['end_at'], fn($h) => '<span class="ev ev-gcal" title="Agenda Google : à modifier dans Google Agenda">📅 ' . e($h) . '<small>' . e($ev['summary'] ?: 'Occupé') . '</small></span>');
    }
    $months = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
?>
<style>
.pl-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px}
.pl{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));border-top:1px solid var(--line);border-left:1px solid var(--line)}
.pl>div{border-right:1px solid var(--line);border-bottom:1px solid var(--line);min-height:104px;padding:6px;font-size:.8rem;background:#fff}
.pl .dow{min-height:0;background:#f4f6f8;font-weight:800;text-align:center;color:var(--muted)}
.pl .out{background:#fafbfc;color:#b7c0ca}
.pl .today{box-shadow:inset 0 0 0 2px var(--gold)}
.pl .num{font-weight:800;margin-bottom:4px;display:block}
.ev{display:block;border-radius:6px;padding:3px 6px;margin-bottom:3px;text-decoration:none;line-height:1.25;overflow:hidden;text-overflow:ellipsis}
.ev small{display:block;opacity:.8}
.ev-ok{background:#e7f6ec;color:#14532d}.ev-pend{background:#fff3d6;color:#7a5a12}.ev-block{background:#eef1f4;color:#425166}.ev-gcal{background:#e8eefc;color:#1e3a8a}
@media(max-width:700px){.pl>div{min-height:70px;padding:3px;font-size:.68rem}.ev small{display:none}}
</style>
<div class="card">
  <div class="pl-head">
    <a class="btn light sm" href="?vue=planning&amp;m=<?= $first->modify('-1 month')->format('Y-m') ?>">‹</a>
    <h2 style="margin:0;text-transform:capitalize"><?= $months[(int)$first->format('n')] . ' ' . $first->format('Y') ?></h2>
    <a class="btn light sm" href="?vue=planning&amp;m=<?= $first->modify('+1 month')->format('Y-m') ?>">›</a>
  </div>
  <div class="legend"><span><i style="background:#e7f6ec"></i>Réservation payée</span><span><i style="background:#fff3d6"></i>Paiement en cours</span><span><i style="background:#e8eefc"></i>Agenda Google</span><span><i style="background:#eef1f4"></i>Blocage</span></div>
  <div class="pl">
    <?php foreach (['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'] as $d): ?><div class="dow"><?= $d ?></div><?php endforeach; ?>
    <?php for ($d = $gridStart; $d <= $last || (int)$d->format('N') !== 1; $d = $d->modify('+1 day')): $k = $d->format('Y-m-d'); ?>
      <div class="<?= $d->format('m') !== $first->format('m') ? 'out' : '' ?> <?= $k === bk_now()->format('Y-m-d') ? 'today' : '' ?>"><span class="num"><?= (int)$d->format('j') ?></span><?= implode('', $items[$k] ?? []) ?></div>
    <?php endfor; ?>
  </div>
</div>
<?php endif; ?>

<div class="grid g2">
  <form method="post" class="card">
    <?= csrf_field() ?>
    <h2>Bloquer un créneau</h2>
    <p class="muted" style="margin-top:-6px">Travaux, location prise par téléphone… Le temps mort s’applique aussi autour. (Vous pouvez aussi simplement ajouter l’événement dans votre agenda Google.)</p>
    <div class="grid g2">
      <?= field('from', 'Début', '', 'datetime-local') ?>
      <?= field('to', 'Fin', '', 'datetime-local') ?>
    </div>
    <?= field('label', 'Motif (visible par vous seul)', '') ?>
    <button class="btn" name="do" value="block">⛔ Bloquer</button>
  </form>
  <div class="card">
    <h2>Blocages à venir</h2>
    <?php $blocks = db()->prepare('SELECT * FROM booking_blocks WHERE end_at > ? ORDER BY start_at'); $blocks->execute([$now]); $blocks = $blocks->fetchAll(); ?>
    <?php if (!$blocks): ?><p class="muted">Aucun blocage ajouté depuis ce site.</p><?php endif; ?>
    <?php foreach ($blocks as $bl): ?>
      <form method="post" class="item-row" style="grid-template-columns:1fr auto;align-items:center">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$bl['id'] ?>">
        <div><strong><?= e(bk_dt($bl['start_at'])->format('d/m/Y H\hi')) ?> → <?= e(bk_dt($bl['end_at'])->format('d/m/Y H\hi')) ?></strong><br><small class="muted"><?= e($bl['label']) ?></small></div>
        <button class="btn danger sm" name="do" value="unblock" onclick="return confirm('Supprimer ce blocage ?')">✕</button>
      </form>
    <?php endforeach; ?>
    <?php $gev = db()->prepare('SELECT * FROM ext_events WHERE end_at > ? ORDER BY start_at LIMIT 100'); $gev->execute([$now]); $gev = $gev->fetchAll(); ?>
    <h3 style="margin:20px 0 4px;font-size:1rem">📅 Depuis votre agenda Google <span class="muted" style="font-weight:normal;font-size:.85rem">(<?= count($gev) ?>)</span></h3>
    <p class="muted" style="margin:0 0 8px;font-size:.85rem"><?= s('bk_gcal_block_day') === '0' ? 'Ces horaires sont bloqués (avec le temps mort).' : 'Chaque journée concernée est bloquée pour les locations 24 h / 48 h / 72 h ; à l’heure, seuls ces horaires (avec le temps mort).' ?>
      Pour en retirer un, supprimez-le dans Google Agenda.<?= s('_gcal_last_ok') !== '' || s('_gcal_last_try') !== '' ? ' Dernière lecture : ' . e(date('d/m à H:i', (int)(s('_gcal_last_ok') ?: s('_gcal_last_try')))) . '.' : '' ?></p>
    <?php if (!$gev): ?><p class="muted"><?= s('bk_gcal_url') === '' ? 'Lecture de l’agenda Google non configurée (Réglages › Agenda Google).' : 'Aucun événement à venir.' ?></p><?php endif; ?>
    <?php foreach ($gev as $ev): $sd = bk_dt($ev['start_at']); $edd = bk_dt($ev['end_at']); $allDay = $sd->format('H:i') === '00:00' && $edd->format('H:i') === '00:00'; ?>
      <div class="item-row" style="grid-template-columns:1fr;background:#f3f6fd">
        <div><strong><?= $allDay ? e(fr_date($sd)) . ($edd->modify('-1 day') > $sd ? ' → ' . e(fr_date($edd->modify('-1 day'))) : '') . ' — journée entière'
          : e($sd->format('d/m/Y H\hi')) . ' → ' . e($sd->format('Y-m-d') === $edd->format('Y-m-d') ? $edd->format('H\hi') : $edd->format('d/m/Y H\hi')) ?></strong><br><small class="muted"><?= e($ev['summary'] ?: 'Occupé') ?></small></div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php admin_footer();
