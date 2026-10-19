<?php
if (!defined('ABSPATH')) exit;
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The plugin's own custom tables. The data is admin-only and changes on every write, so object caching is not used.

/*
 * Jäsenmaksut kirjanpitoon: raportti rahastonhoitajalle ja kirjanpitäjälle (Vuosiraportti-sivun toinen välilehti).
 * Lisäosa ei tee kirjanpitoa, vaan kokoaa jäsenmaksuista valmiin vientiehdotuksen (debet pankkitili / kredit
 * jäsenmaksutuotot) maksupäivittäin tai kuukausittain sekä erittelyt, joita kirjanpitäjä tarvitsee.
 */

/** Aikavälin oletus: kuluva tilikausi (alkuvuodesta edellinen), loppu enintään tänään. */
function jasrek_kp_range() {
    $today = current_time('Y-m-d');
    $vuosi = ((int) current_time('n') <= 2) ? (int) current_time('Y') - 1 : (int) current_time('Y');
    $alku  = jasrek_get('alku', '');
    $loppu = jasrek_get('loppu', '');
    $ok = function ($d) { return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && checkdate((int) substr($d, 5, 2), (int) substr($d, 8, 2), (int) substr($d, 0, 4)); };
    if (!$ok($alku)) $alku = $vuosi . '-01-01';
    if (!$ok($loppu)) $loppu = $vuosi . '-12-31';
    if ($loppu < $alku) { $t = $alku; $alku = $loppu; $loppu = $t; }
    $ryhma = jasrek_get('ryhma', 'paiva') === 'kk' ? 'kk' : 'paiva';
    return array($alku, $loppu, $ryhma, $today);
}

/** Raportin tiedot aikavälille. Maksettu summa = maksettu_summa (jos kirjattu) tai laskun summa. */
function jasrek_kp_data($alku, $loppu, $ryhma) {
    global $wpdb;
    $s = jasrek_settings();
    $lajit = jasrek_jasenlajit();
    $maksut = $wpdb->get_results($wpdb->prepare(
        "SELECT r.id, r.maksettu, r.summa, r.maksettu_summa, r.postimaksu, r.viite, r.maksaja, r.nimi AS rnimi, l.nimi AS lasku, "
        . "m.etunimi, m.sukunimi, m.tunnus, COALESCE(NULLIF(r.jasenlaji, ''), m.jasenlaji, '') AS laji "
        . "FROM {$wpdb->jasrek_rivit} r JOIN {$wpdb->jasrek_laskut} l ON l.id = r.lasku_id LEFT JOIN {$wpdb->jasrek_jasenet} m ON m.id = r.jasen_id "
        . "WHERE r.maksettu BETWEEN %s AND %s ORDER BY r.maksettu, l.laskupvm, r.id", $alku, $loppu));

    $paid = function ($r) { return round($r->maksettu_summa !== null ? (float) $r->maksettu_summa : (float) $r->summa, 2); };
    // Postimaksun osuus: enintään maksettu summa (vajaa maksu kohdistetaan ensin jäsenmaksuun ei – yksinkertaisesti postimaksu ensin)
    $posti = function ($r) use ($paid) { return round(min((float) $r->postimaksu, max(0, $paid($r))), 2); };

    $viennit = array();
    $luokat = array();
    $poikk = array();
    foreach ($maksut as $r) {
        $key = $ryhma === 'kk' ? substr($r->maksettu, 0, 7) : $r->maksettu;
        if (!isset($viennit[$key])) $viennit[$key] = array('pvm' => $ryhma === 'kk' ? $key . '-01' : $key, 'n' => 0, 'summa' => 0.0, 'posti' => 0.0, 'laskut' => array());
        $viennit[$key]['n']++;
        $viennit[$key]['summa'] += $paid($r);
        $viennit[$key]['posti'] += $posti($r);
        $viennit[$key]['laskut'][$r->lasku] = true;

        $k = isset($lajit[$r->laji]) ? $r->laji : '';
        if (!isset($luokat[$k])) $luokat[$k] = array('n' => 0, 'summa' => 0.0, 'posti' => 0.0);
        $luokat[$k]['n']++;
        $luokat[$k]['summa'] += $paid($r);
        $luokat[$k]['posti'] += $posti($r);

        if ($r->maksettu_summa !== null && abs((float) $r->maksettu_summa - (float) $r->summa) >= 0.005) $poikk[] = $r;
    }
    uksort($luokat, function ($a, $b) use ($lajit) {
        $ka = array_search($a, array_keys($lajit), true); $kb = array_search($b, array_keys($lajit), true);
        return ($ka === false ? 999 : $ka) - ($kb === false ? 999 : $kb);
    });

    // Avoimet saatavat aikavälin lopussa: laskut päivätty viimeistään loppupäivänä, eikä maksettu siihen mennessä
    $avoimet = $wpdb->get_results($wpdb->prepare(
        "SELECT r.id, r.summa, r.viite, r.nimi AS rnimi, COALESCE(r.erapaiva, l.erapaiva) AS era, l.nimi AS lasku, l.laskupvm, m.etunimi, m.sukunimi, m.tunnus "
        . "FROM {$wpdb->jasrek_rivit} r JOIN {$wpdb->jasrek_laskut} l ON l.id = r.lasku_id LEFT JOIN {$wpdb->jasrek_jasenet} m ON m.id = r.jasen_id "
        . "WHERE l.laskupvm <= %s AND (r.maksettu IS NULL OR r.maksettu > %s) AND r.summa > 0 ORDER BY l.laskupvm, l.id, m.sukunimi, m.etunimi", $loppu, $loppu));
    $avoin_laskut = array();
    foreach ($avoimet as $r) {
        if (!isset($avoin_laskut[$r->lasku])) $avoin_laskut[$r->lasku] = array('laskupvm' => $r->laskupvm, 'n' => 0, 'summa' => 0.0);
        $avoin_laskut[$r->lasku]['n']++;
        $avoin_laskut[$r->lasku]['summa'] += (float) $r->summa;
    }

    $tilit = array(
        'pankki' => trim((string) $s['kp_pankki']) !== '' ? trim((string) $s['kp_pankki']) : 'Pankkitili',
        'tuotot' => trim((string) $s['kp_tuotot']) !== '' ? trim((string) $s['kp_tuotot']) : 'Jäsenmaksutuotot',
        'posti'  => trim((string) $s['kp_posti']) !== '' ? trim((string) $s['kp_posti']) : 'Postimaksut',
    );
    return compact('maksut', 'viennit', 'luokat', 'poikk', 'avoimet', 'avoin_laskut', 'tilit', 'paid', 'posti');
}

/** Vientiehdotuksen rivit: jäsenmaksuosuus ja postimaksut omille riveilleen (debet pankkitili). */
function jasrek_kp_vientirivit($d, $ryhma) {
    $rows = array();
    foreach ($d['viennit'] as $v) {
        $kk = $ryhma === 'kk';
        $pvm = $kk ? wp_date('t.n.Y', strtotime($v['pvm'] . ' 12:00')) : jasrek_date($v['pvm']);
        $tun = $kk ? jasrek_kp_kuukausi($v['pvm']) : '';
        $sel = 'Jäsenmaksut' . ($kk ? ' ' . $tun : '') . ', ' . $v['n'] . ' kpl (' . implode(', ', array_keys($v['laskut'])) . ')';
        $jm = round($v['summa'] - $v['posti'], 2);
        if ($jm != 0.0) $rows[] = array('pvm' => $pvm, 'ymd' => $kk ? gmdate('Y-m-t', strtotime($v['pvm'])) : $v['pvm'], 'selite' => $sel, 'debet' => $d['tilit']['pankki'], 'kredit' => $d['tilit']['tuotot'], 'summa' => $jm);
        if ($v['posti'] > 0) $rows[] = array('pvm' => $pvm, 'ymd' => $kk ? gmdate('Y-m-t', strtotime($v['pvm'])) : $v['pvm'], 'selite' => 'Paperilaskujen postimaksut' . ($kk ? ' ' . $tun : ''), 'debet' => $d['tilit']['pankki'], 'kredit' => $d['tilit']['posti'], 'summa' => round($v['posti'], 2));
    }
    return $rows;
}

function jasrek_kp_kuukausi($ymd) {
    $k = array(1 => 'tammikuu', 'helmikuu', 'maaliskuu', 'huhtikuu', 'toukokuu', 'kesäkuu', 'heinäkuu', 'elokuu', 'syyskuu', 'lokakuu', 'marraskuu', 'joulukuu');
    return $k[(int) substr($ymd, 5, 2)] . ' ' . substr($ymd, 0, 4);
}

function jasrek_kp_nimi($r) {
    $n = trim((string) $r->etunimi . ' ' . (string) $r->sukunimi);
    return $n !== '' ? $n : (string) $r->rnimi;
}

/* ------------------------------------------------------------------
 * Excel
 * ------------------------------------------------------------------ */

add_action('admin_post_jasrek_kp_xlsx', 'jasrek_handle_kp_xlsx');
function jasrek_handle_kp_xlsx() {
    jasrek_check('jasrek_kp_xlsx');
    require_once JASREK_DIR . 'includes/xlsx.php';
    list($alku, $loppu, $ryhma) = jasrek_kp_range();
    $d = jasrek_kp_data($alku, $loppu, $ryhma);
    $x = new JASREK_Xlsx();
    $T = function ($v) { return JASREK_Xlsx::text($v); };
    $sumf = function ($col, $money = false) { return JASREK_Xlsx::formula("SUM({$col}2:{$col}{last})", $money ? JASREK_Xlsx::S_BOLD_MONEY : JASREK_Xlsx::S_BOLD); };
    $yht = JASREK_Xlsx::text('Yhteensä', JASREK_Xlsx::S_BOLD);

    $rows = array();
    foreach (jasrek_kp_vientirivit($d, $ryhma) as $v) $rows[] = array(JASREK_Xlsx::date($v['ymd']), $T($v['selite']), $T($v['debet']), $T($v['kredit']), JASREK_Xlsx::money($v['summa']));
    $x->add_sheet('Vientiehdotus', array('Päivä', 'Selite', 'Debet', 'Kredit', 'Summa €'), $rows, array(12, 60, 20, 22, 14), array($yht, '', '', '', $sumf('E', true)));

    $rows = array();
    foreach ($d['maksut'] as $r) $rows[] = array(JASREK_Xlsx::date($r->maksettu), $T(jasrek_kp_nimi($r)), $T(jasrek_tunnus_on() ? (string) $r->tunnus : ''), $T($r->lasku), $T(jasrek_viite_format($r->viite)),
        $T(jasrek_report_laji_label(isset(jasrek_jasenlajit()[$r->laji]) ? $r->laji : '')), JASREK_Xlsx::money($r->summa), JASREK_Xlsx::money(($d['paid'])($r)), JASREK_Xlsx::money(($d['posti'])($r)), $T((string) $r->maksaja));
    $x->add_sheet('Maksut', array('Maksupäivä', 'Jäsen', jasrek_tunnus_on() ? jasrek_tunnus_nimi() : '', 'Lasku', 'Viite', 'Jäsenluokka', 'Laskutettu €', 'Maksettu €', 'Josta postimaksu €', 'Maksaja'),
        $rows, array(12, 26, 12, 24, 14, 18, 13, 13, 16, 24), array($yht, '', '', '', '', '', $sumf('G', true), $sumf('H', true), $sumf('I', true), ''));

    $rows = array();
    foreach ($d['luokat'] as $k => $v) $rows[] = array($T(jasrek_report_laji_label($k)), (int) $v['n'], JASREK_Xlsx::money($v['summa'] - $v['posti']), JASREK_Xlsx::money($v['posti']), JASREK_Xlsx::money($v['summa']));
    $x->add_sheet('Jäsenluokittain', array('Jäsenluokka', 'Maksuja', 'Jäsenmaksut €', 'Postimaksut €', 'Yhteensä €'), $rows, array(26, 10, 15, 15, 14),
        array($yht, $sumf('B'), $sumf('C', true), $sumf('D', true), $sumf('E', true)));

    if ($d['poikk']) {
        $rows = array();
        foreach ($d['poikk'] as $r) $rows[] = array(JASREK_Xlsx::date($r->maksettu), $T(jasrek_kp_nimi($r)), $T($r->lasku), JASREK_Xlsx::money($r->summa), JASREK_Xlsx::money($r->maksettu_summa), JASREK_Xlsx::money((float) $r->maksettu_summa - (float) $r->summa), $T((string) $r->maksaja));
        $x->add_sheet('Poikkeavat maksut', array('Maksupäivä', 'Jäsen', 'Lasku', 'Laskutettu €', 'Maksettu €', 'Erotus €', 'Maksaja'), $rows, array(12, 26, 24, 13, 13, 12, 24),
            array($yht, '', '', $sumf('D', true), $sumf('E', true), $sumf('F', true), ''));
    }

    $rows = array();
    foreach ($d['avoimet'] as $r) $rows[] = array($T(jasrek_kp_nimi($r)), $T($r->lasku), JASREK_Xlsx::date($r->laskupvm), JASREK_Xlsx::date($r->era), $T(jasrek_viite_format($r->viite)), JASREK_Xlsx::money($r->summa));
    $x->add_sheet('Avoimet ' . jasrek_date($loppu), array('Jäsen', 'Lasku', 'Laskun pvm', 'Eräpäivä', 'Viite', 'Avoinna €'), $rows, array(26, 24, 12, 12, 14, 13),
        array($yht, '', '', '', '', $sumf('F', true)));

    $bin = $x->build();
    jasrek_log('vienti', 0, 'vienti', 'Jäsenmaksut kirjanpitoon ' . jasrek_date($alku) . '–' . jasrek_date($loppu) . ' Exceliin');
    nocache_headers();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="jasenmaksut-kirjanpitoon-' . $alku . '-' . $loppu . '_' . jasrek_file_stamp() . '.xlsx"');
    header('Content-Length: ' . strlen($bin));
    echo $bin; // phpcs:ignore -- binääritiedosto
    exit;
}

/* ------------------------------------------------------------------
 * Sivu (Vuosiraportti → Jäsenmaksut kirjanpitoon)
 * ------------------------------------------------------------------ */

/** Välilehdet Vuosiraportti-sivun yläreunaan. */
function jasrek_report_tabs($current) {
    $tabs = array('' => 'Vuosiraportti', 'kirjanpito' => 'Jäsenmaksut kirjanpitoon');
    echo '<nav class="nav-tab-wrapper jasrek-noprint jasrek-navtabs">';
    foreach ($tabs as $k => $label) {
        $url = admin_url('admin.php?page=jasrek-raportti' . ($k !== '' ? '&view=' . $k : ''));
        echo '<a class="nav-tab' . ($current === $k ? ' nav-tab-active' : '') . '" href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
    }
    echo '</nav>';
}

function jasrek_page_kirjanpito() {
    list($alku, $loppu, $ryhma, $today) = jasrek_kp_range();
    $d = jasrek_kp_data($alku, $loppu, $ryhma);
    $s = jasrek_settings();
    $vrows = jasrek_kp_vientirivit($d, $ryhma);
    $tot = 0.0; foreach ($vrows as $v) $tot += $v['summa'];
    $totposti = 0.0; foreach ($d['luokat'] as $v) $totposti += $v['posti'];
    $avoin = 0.0; foreach ($d['avoimet'] as $r) $avoin += (float) $r->summa;
    $vuosi = (int) substr($alku, 0, 4);
    $q = array('alku' => $alku, 'loppu' => $loppu, 'ryhma' => $ryhma);
    $tilikausi = $alku === $vuosi . '-01-01' && $loppu === $vuosi . '-12-31';
    ?>
    <div class="wrap jasrek-wrap jasrek-report">
        <?php jasrek_report_tabs('kirjanpito'); ?>
        <h1 class="wp-heading-inline">Jäsenmaksut kirjanpitoon</h1>
        <hr class="wp-header-end">
        <p class="jasrek-report-sub"><?php echo esc_html($s['yhdistys']); ?> · <?php echo esc_html(jasrek_date($alku) . '–' . jasrek_date($loppu)); ?><?php echo $loppu > $today ? ' (kesken, tilanne ' . esc_html(jasrek_date($today)) . ')' : ''; ?> · tulostettu <?php echo esc_html(jasrek_date($today)); ?></p>

        <form method="get" class="jasrek-panel jasrek-noprint jasrek-kp-form">
            <input type="hidden" name="page" value="jasrek-raportti">
            <input type="hidden" name="view" value="kirjanpito">
            <p><label for="jasrek-kp-alku">Alkaen</label> <input type="date" id="jasrek-kp-alku" name="alku" value="<?php echo esc_attr($alku); ?>"></p>
            <p><label for="jasrek-kp-loppu">Päättyen</label> <input type="date" id="jasrek-kp-loppu" name="loppu" value="<?php echo esc_attr($loppu); ?>"></p>
            <p><label for="jasrek-kp-ryhma">Vientiehdotus</label>
               <select id="jasrek-kp-ryhma" name="ryhma"><option value="paiva" <?php selected($ryhma, 'paiva'); ?>>maksupäivittäin</option><option value="kk" <?php selected($ryhma, 'kk'); ?>>kuukausittain</option></select></p>
            <p><button class="button button-primary">Näytä</button>
               <button type="button" class="button" data-jasrek-print>🖨 Tulosta</button>
               <a class="button" href="<?php echo esc_url(wp_nonce_url(add_query_arg(array_merge(array('action' => 'jasrek_kp_xlsx'), $q), admin_url('admin-post.php')), 'jasrek_kp_xlsx')); ?>">Vie Exceliin</a></p>
            <p class="jasrek-kp-pikat">Valitse:
                <?php foreach (array($vuosi - 1, $vuosi) as $y): ?>
                    <a href="<?php echo esc_url(add_query_arg(array('alku' => $y . '-01-01', 'loppu' => $y . '-12-31', 'ryhma' => $ryhma))); ?>">tilikausi <?php echo (int) $y; ?></a> ·
                <?php endforeach; ?>
                <?php $kk1 = gmdate('Y-m-01', strtotime(current_time('Y-m-01') . ' -1 month')); ?>
                <a href="<?php echo esc_url(add_query_arg(array('alku' => $kk1, 'loppu' => gmdate('Y-m-t', strtotime($kk1)), 'ryhma' => $ryhma))); ?>">edellinen kuukausi (<?php echo esc_html(jasrek_kp_kuukausi($kk1)); ?>)</a> ·
                <a href="<?php echo esc_url(add_query_arg(array('alku' => current_time('Y-m-01'), 'loppu' => current_time('Y-m-t'), 'ryhma' => $ryhma))); ?>">kuluva kuukausi</a></p>
        </form>

        <div class="jasrek-cards">
            <div class="jasrek-card jasrek-card-ok"><span class="jasrek-card-num"><?php echo esc_html(jasrek_money($tot)); ?></span><span class="jasrek-card-label">Jäsenmaksuja saatu (<?php echo count($d['maksut']); ?> maksua)</span></div>
            <?php if ($totposti > 0): ?><div class="jasrek-card"><span class="jasrek-card-num"><?php echo esc_html(jasrek_money($totposti)); ?></span><span class="jasrek-card-label">josta postimaksuja</span></div><?php endif; ?>
            <div class="jasrek-card<?php echo $avoin > 0 ? ' jasrek-card-warn' : ''; ?>"><span class="jasrek-card-num"><?php echo esc_html(jasrek_money($avoin)); ?></span><span class="jasrek-card-label">Avoinna <?php echo esc_html(jasrek_date($loppu)); ?> (<?php echo count($d['avoimet']); ?> kpl)</span></div>
        </div>

        <div class="jasrek-panel">
            <h2>Vientiehdotus</h2>
            <?php if (!$vrows): ?>
                <p class="jasrek-muted">Aikavälillä ei ole kirjattuja jäsenmaksuja.</p>
            <?php else: ?>
            <table class="widefat striped jasrek-table jasrek-kp-viennit">
                <thead><tr><th>Päivä</th><th>Selite</th><th>Debet</th><th>Kredit</th><th class="jasrek-r">Summa</th></tr></thead>
                <tbody>
                <?php foreach ($vrows as $v): ?>
                    <tr><td><?php echo esc_html($v['pvm']); ?></td><td><?php echo esc_html($v['selite']); ?></td><td><?php echo esc_html($v['debet']); ?></td><td><?php echo esc_html($v['kredit']); ?></td><td class="jasrek-r"><?php echo esc_html(jasrek_money($v['summa'])); ?></td></tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot><tr><th colspan="4">Yhteensä</th><th class="jasrek-r"><?php echo esc_html(jasrek_money($tot)); ?></th></tr></tfoot>
            </table>
            <p class="description">Ehdotus kahdenkertaiseen kirjanpitoon: rahaa tuli pankkitilille (debet), ja se on yhdistyksen tuottoa (kredit). <?php echo $ryhma === 'kk' ? 'Yksi vienti kuukaudessa, päivänä kuukauden viimeinen päivä.' : 'Yksi vienti jokaista maksupäivää kohti, joten rivit on helppo täsmäyttää tiliotteeseen.'; ?>
                Päivä on maksun kirjauspäivä (pankkiaineistosta tai käsin merkitty). Tilien nimet tai numerot voi vaihtaa kirjanpitäjän tilikartan mukaisiksi <a class="jasrek-noprint" href="<?php echo esc_url(admin_url('admin.php?page=jasrek-asetukset#jasrek-kirjanpito')); ?>">asetuksissa</a>.
                Jokainen maksu jäsenittäin on Excelin välilehdellä <em>Maksut</em>.</p>
            <?php endif; ?>
        </div>

        <?php if ($d['luokat']): ?>
        <div class="jasrek-panel">
            <h2>Jäsenluokittain</h2>
            <table class="widefat striped jasrek-table jasrek-num">
                <thead><tr><th>Jäsenluokka</th><th>Maksuja</th><th>Jäsenmaksut</th><?php if ($totposti > 0): ?><th>Postimaksut</th><?php endif; ?><th>Yhteensä</th></tr></thead>
                <tbody>
                <?php $n = 0; foreach ($d['luokat'] as $k => $v): $n += $v['n']; ?>
                    <tr><td><?php echo esc_html(jasrek_report_laji_label($k)); ?></td><td><?php echo (int) $v['n']; ?></td><td><?php echo esc_html(jasrek_money($v['summa'] - $v['posti'])); ?></td>
                        <?php if ($totposti > 0): ?><td><?php echo esc_html(jasrek_money($v['posti'])); ?></td><?php endif; ?><td><?php echo esc_html(jasrek_money($v['summa'])); ?></td></tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot><tr><th>Yhteensä</th><th><?php echo (int) $n; ?></th><th><?php echo esc_html(jasrek_money($tot - $totposti)); ?></th><?php if ($totposti > 0): ?><th><?php echo esc_html(jasrek_money($totposti)); ?></th><?php endif; ?><th><?php echo esc_html(jasrek_money($tot)); ?></th></tr></tfoot>
            </table>
            <p class="description">Jäsenluokka laskutushetken mukaan.</p>
        </div>
        <?php endif; ?>

        <?php if ($d['poikk']): ?>
        <div class="jasrek-panel">
            <h2>Poikkeavat maksut</h2>
            <table class="widefat striped jasrek-table jasrek-num">
                <thead><tr><th>Jäsen</th><th>Lasku</th><th>Maksupäivä</th><th>Laskutettu</th><th>Maksettu</th><th>Erotus</th></tr></thead>
                <tbody>
                <?php foreach ($d['poikk'] as $r): $e = (float) $r->maksettu_summa - (float) $r->summa; ?>
                    <tr><td><?php echo esc_html(jasrek_kp_nimi($r)); ?><?php if ($r->maksaja): ?><div class="jasrek-sub">maksaja <?php echo esc_html($r->maksaja); ?></div><?php endif; ?></td><td><?php echo esc_html($r->lasku); ?></td><td><?php echo esc_html(jasrek_date($r->maksettu)); ?></td>
                        <td><?php echo esc_html(jasrek_money($r->summa)); ?></td><td><?php echo esc_html(jasrek_money($r->maksettu_summa)); ?></td><td><strong><?php echo esc_html(($e > 0 ? '+' : '') . html_entity_decode(jasrek_money($e))); ?></strong></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p class="description">Maksut, joiden summa poikkeaa laskusta. Vientiehdotuksessa on mukana todellinen maksettu summa. Ylimaksu voidaan palauttaa tai hyvittää seuraavassa laskussa, alimaksu periä erikseen – sovi käsittely kirjanpitäjän kanssa.</p>
        </div>
        <?php endif; ?>

        <div class="jasrek-panel">
            <h2>Avoimet jäsenmaksut <?php echo esc_html(jasrek_date($loppu)); ?></h2>
            <?php if (!$d['avoimet']): ?>
                <p class="jasrek-muted">Ei avoimia jäsenmaksuja.</p>
            <?php else: ?>
            <table class="widefat striped jasrek-table jasrek-num">
                <thead><tr><th>Lasku</th><th>Laskun pvm</th><th>Avoinna kpl</th><th>Avoinna</th></tr></thead>
                <tbody>
                <?php foreach ($d['avoin_laskut'] as $nimi => $v): ?>
                    <tr><td><?php echo esc_html($nimi); ?></td><td><?php echo esc_html(jasrek_date($v['laskupvm'])); ?></td><td><?php echo (int) $v['n']; ?></td><td><?php echo esc_html(jasrek_money($v['summa'])); ?></td></tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot><tr><th>Yhteensä</th><th></th><th><?php echo count($d['avoimet']); ?></th><th><?php echo esc_html(jasrek_money($avoin)); ?></th></tr></tfoot>
            </table>
            <p class="description">Laskut, jotka on päivätty viimeistään <?php echo esc_html(jasrek_date($loppu)); ?> ja joita ei ollut maksettu siihen päivään mennessä. Tilinpäätöksessä nämä ovat jäsenmaksusaamisia, jos yhdistys kirjaa saamiset (sovi kirjanpitäjän kanssa). Jäsenittäinen luettelo on Excelissä.</p>
            <?php endif; ?>
        </div>
        <p class="description jasrek-noprint">Raportti on apu kirjanpitoon, ei kirjanpito. Lisäosa ei korvaa yhdistyksen kirjanpitoa eikä tilinpäätöstä.<?php echo $tilikausi ? '' : ''; ?></p>
    </div>
    <?php
}
