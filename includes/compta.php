<?php
/**
 * Comptabilité : journal des factures, avoirs et retenues sur caution d'une période,
 * totaux par mois et par moyen de paiement, export CSV (Excel) pour le comptable.
 */

const COMPTA_TYPES = ['facture' => 'Facture', 'avoir' => 'Avoir', 'caution' => 'Retenue sur caution'];

/** Nom affiché du client (société ou association en priorité). */
function compta_client(array $b): string
{
    $name = trim($b['first_name'] . ' ' . $b['last_name']);
    $org = $b['company'] !== '' ? $b['company'] : ((int)$b['is_asso'] && $b['asso_name'] !== '' ? $b['asso_name'] : '');
    return $org !== '' ? $org . ' (' . $name . ')' : $name;
}

/**
 * Écritures entre deux dates (incluse, exclue), triées par date.
 * Montants en euros ; les avoirs sont négatifs.
 */
function compta_entries(DateTimeImmutable $from, DateTimeImmutable $to): array
{
    $a = $from->format(BK_FMT);
    $z = $to->format(BK_FMT);
    $rate = (float)bk_num('bk_vat_rate', 20);
    $out = [];

    $st = db()->prepare("SELECT * FROM bookings WHERE invoice_number != '' AND paid_at >= ? AND paid_at < ?");
    $st->execute([$a, $z]);
    foreach ($st as $b) {
        $out[] = [
            'type' => 'facture', 'number' => $b['invoice_number'], 'date' => $b['paid_at'], 'b' => $b,
            'ht' => round((float)$b['total_ht'], 2), 'tva' => round((float)$b['vat'], 2), 'ttc' => round((float)$b['total_ttc'], 2),
            'gift' => round((float)$b['gift_amount'], 2), 'paid' => round(bk_due($b), 2),
            'provider' => $b['provider'] === 'giftcard' ? '' : $b['provider'], 'payment_ref' => $b['payment_ref'],
        ];
    }

    $st = db()->prepare("SELECT * FROM bookings WHERE credit_number != '' AND cancelled_at >= ? AND cancelled_at < ?");
    $st->execute([$a, $z]);
    foreach ($st as $b) {
        $giftBack = (int)$b['gift_restored'] ? (float)$b['gift_amount'] : 0.0;
        $ttc = round((float)$b['refunded_amount'] + $giftBack, 2);
        $ht = round($ttc / (1 + $rate / 100), 2);
        $out[] = [
            'type' => 'avoir', 'number' => $b['credit_number'], 'date' => $b['cancelled_at'], 'b' => $b,
            'ht' => -$ht, 'tva' => -round($ttc - $ht, 2), 'ttc' => -$ttc,
            'gift' => -round($giftBack, 2), 'paid' => -round((float)$b['refunded_amount'], 2),
            'provider' => (float)$b['refunded_amount'] > 0 ? $b['provider'] : '', 'payment_ref' => $b['payment_ref'],
        ];
    }

    $st = db()->prepare("SELECT * FROM bookings WHERE deposit_captured > 0 AND deposit_captured_at >= ? AND deposit_captured_at < ?");
    $st->execute([$a, $z]);
    foreach ($st as $b) {
        $amt = round((float)$b['deposit_captured'], 2);
        $out[] = [
            'type' => 'caution', 'number' => '', 'date' => $b['deposit_captured_at'], 'b' => $b,
            'ht' => $amt, 'tva' => 0.0, 'ttc' => $amt, 'gift' => 0.0, 'paid' => $amt,
            'provider' => 'stripe', 'payment_ref' => $b['deposit_pi'], 'note' => $b['deposit_reason'],
        ];
    }

    usort($out, fn($x, $y) => [$x['date'], $x['number']] <=> [$y['date'], $y['number']]);
    return $out;
}

/** Totaux : par mois (Y-m) et par moyen d'encaissement. */
function compta_totals(array $entries): array
{
    $zero = ['factures' => 0, 'ht' => 0.0, 'tva' => 0.0, 'ttc' => 0.0, 'avoirs_ttc' => 0.0, 'cautions' => 0.0, 'net_ht' => 0.0, 'net_tva' => 0.0, 'net_ttc' => 0.0];
    $months = [];
    $all = $zero;
    $by = [];
    foreach ($entries as $en) {
        $m = substr($en['date'], 0, 7);
        $months[$m] ??= $zero;
        foreach ([&$months[$m], &$all] as &$t) {
            if ($en['type'] === 'facture') {
                $t['factures']++;
                $t['ht'] += $en['ht'];
                $t['tva'] += $en['tva'];
                $t['ttc'] += $en['ttc'];
            } elseif ($en['type'] === 'avoir') {
                $t['avoirs_ttc'] += $en['ttc'];
            } else {
                $t['cautions'] += $en['ttc'];
            }
            if ($en['type'] !== 'caution') {
                $t['net_ht'] += $en['ht'];
                $t['net_tva'] += $en['tva'];
                $t['net_ttc'] += $en['ttc'];
            }
        }
        unset($t);
        if ($en['provider'] !== '' && abs($en['paid']) > 0) {
            $by[provider_label($en['provider'])] = ($by[provider_label($en['provider'])] ?? 0) + $en['paid'];
        }
        if (abs($en['gift']) > 0) {
            $by['bon cadeau'] = ($by['bon cadeau'] ?? 0) + $en['gift'];
        }
    }
    ksort($months);
    $round = function (array $t) {
        foreach ($t as $k => $v) {
            $t[$k] = is_float($v) ? round($v, 2) : $v;
        }
        return $t;
    };
    return ['months' => array_map($round, $months), 'all' => $round($all), 'by' => array_map(fn($v) => round($v, 2), $by)];
}

/** Nombre au format français pour Excel (virgule décimale, sans séparateur de milliers). */
function compta_num(float $v): string
{
    return number_format($v, 2, ',', '');
}

/** Envoie le journal en CSV (point-virgule, UTF-8 avec BOM : s'ouvre directement dans Excel). */
function compta_csv(array $entries, string $filename): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $f = fopen('php://output', 'w');
    fwrite($f, "\xEF\xBB\xBF");
    // Texte commençant par = + - @ : neutralisé (pas de formule Excel injectée via un nom de client) ; les montants restent des nombres
    $cell = fn($v) => preg_match('/^-?\d+(,\d+)?$/', (string)$v) || !preg_match('/^[=+\-@\t\r]/', (string)$v) ? (string)$v : "'" . $v;
    fputcsv($f, ['Type', 'Numéro', 'Date', 'Réservation', 'Client', 'Date de l’événement', 'Montant HT', 'TVA', 'Montant TTC',
        'Dont bon cadeau', 'Encaissé / remboursé', 'Moyen de paiement', 'Référence paiement', 'Commentaire'], ';', '"', '');
    foreach ($entries as $en) {
        $b = $en['b'];
        fputcsv($f, array_map($cell, [
            COMPTA_TYPES[$en['type']], $en['number'], bk_dt($en['date'])->format('d/m/Y'), $b['ref'], compta_client($b),
            bk_dt($b['start_at'])->format('d/m/Y'), compta_num($en['ht']), compta_num($en['tva']), compta_num($en['ttc']),
            compta_num($en['gift']), compta_num($en['paid']), $en['provider'] !== '' ? provider_label($en['provider']) : '', $en['payment_ref'],
            $en['type'] === 'caution' ? 'Indemnité pour dégradation, hors champ de la TVA — ' . ($en['note'] ?? '') : '',
        ]), ';', '"', '');
    }
    fclose($f);
}
