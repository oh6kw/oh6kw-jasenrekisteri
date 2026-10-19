<?php
if (!defined('ABSPATH')) exit;
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The plugin's own custom tables (members, invoices, audit log). The data is admin-only and changes on every write, so object caching is not used.

/*
 * Vuosiraportti vuosikokoukseen: jäsenmäärät jäsenluokittain sekä jäsenmaksujen laskutus ja kertymä.
 */

function jasrek_report_years() {
    global $wpdb;
    $now = (int) current_time('Y');
    $min = (int) substr((string) $wpdb->get_var("SELECT MIN(laskupvm) FROM {$wpdb->jasrek_laskut}"), 0, 4);
    $minj = (int) substr((string) $wpdb->get_var("SELECT MIN(liittynyt) FROM {$wpdb->jasrek_jasenet} WHERE liittynyt IS NOT NULL AND liittynyt > '1900-01-01'"), 0, 4);
    $from = min(array_filter(array($min ?: $now, $minj ?: $now, $now - 1)));
    $from = max($from, $now - 30);
    return range($now, $from);
}

function jasrek_report_data($year) {
    global $wpdb;
    $year  = (int) $year;
    $today = current_time('Y-m-d');
    $alku  = sprintf('%04d-01-01', $year);
    $loppu = sprintf('%04d-12-31', $year);
    $raja  = $loppu > $today ? $today : $loppu;   // kuluva vuosi: tilanne tänään
    $edel  = sprintf('%04d-12-31', $year - 1);
    $lajit = jasrek_jasenlajit();

    $active = function ($m, $d) {
        if ($m->liittynyt && $m->liittynyt !== '0000-00-00' && $m->liittynyt > $d) return false;
        if ($m->tila === 'aktiivinen') return true;
        if ($m->tila !== 'eronnut') return false;
        return $m->eronnut_pvm && $m->eronnut_pvm > $d;
    };

    $keys = array_merge(array_keys($lajit), array(''));
    $j = array();
    foreach ($keys as $k) $j[$k] = array('alku' => 0, 'liittyi' => 0, 'erosi' => 0, 'loppu' => 0);
    $ero_ei_pvm = 0; $ei_liitt = 0;
    foreach ($wpdb->get_results("SELECT id, jasenlaji, tila, liittynyt, eronnut_pvm FROM {$wpdb->jasrek_jasenet}") as $m) {
        $k = isset($lajit[$m->jasenlaji]) ? $m->jasenlaji : '';
        if ($active($m, $edel)) $j[$k]['alku']++;
        if ($active($m, $raja)) {
            $j[$k]['loppu']++;
            if (!$m->liittynyt || $m->liittynyt === '0000-00-00') $ei_liitt++;
        }
        if (in_array($m->tila, array('aktiivinen', 'eronnut'), true) && $m->liittynyt && $m->liittynyt >= $alku && $m->liittynyt <= $raja) $j[$k]['liittyi']++;
        if ($m->tila === 'eronnut') {
            if (!$m->eronnut_pvm) $ero_ei_pvm++;
            elseif ($m->eronnut_pvm >= $alku && $m->eronnut_pvm <= $raja) $j[$k]['erosi']++;
        }
    }
    if (!array_sum($j[''])) unset($j['']);

    // Vuoden laskut (laskun päiväyksen mukaan)
    $laskut = $wpdb->get_results($wpdb->prepare(
        "SELECT l.id, l.nimi, l.laskupvm, l.erapaiva, COUNT(r.id) AS n, COALESCE(SUM(r.summa), 0) AS laskutettu, "
        . "SUM(r.maksettu IS NOT NULL) AS n_mak, COALESCE(SUM(CASE WHEN r.maksettu IS NOT NULL THEN COALESCE(r.maksettu_summa, r.summa) ELSE 0 END), 0) AS maksettu, "
        . "COALESCE(SUM(CASE WHEN r.maksettu IS NULL THEN r.summa ELSE 0 END), 0) AS avoin, "
        . "COALESCE(SUM(CASE WHEN r.maksettu IS NULL AND COALESCE(r.erapaiva, l.erapaiva) < %s THEN r.summa ELSE 0 END), 0) AS erant "
        . "FROM {$wpdb->jasrek_laskut} l LEFT JOIN {$wpdb->jasrek_rivit} r ON r.lasku_id = l.id WHERE l.laskupvm BETWEEN %s AND %s GROUP BY l.id, l.nimi, l.laskupvm, l.erapaiva ORDER BY l.laskupvm, l.id",
        $today, $alku, $loppu
    ));

    // Luokittain (jäsenluokka laskutushetkellä)
    $luokat = array();
    foreach ($wpdb->get_results($wpdb->prepare(
        "SELECT COALESCE(NULLIF(r.jasenlaji, ''), m.jasenlaji, '') AS laji, COUNT(*) AS n, SUM(r.summa) AS laskutettu, "
        . "SUM(r.maksettu IS NOT NULL) AS n_mak, SUM(CASE WHEN r.maksettu IS NOT NULL THEN COALESCE(r.maksettu_summa, r.summa) ELSE 0 END) AS maksettu, "
        . "SUM(CASE WHEN r.maksettu IS NULL THEN r.summa ELSE 0 END) AS avoin "
        . "FROM {$wpdb->jasrek_rivit} r JOIN {$wpdb->jasrek_laskut} l ON l.id = r.lasku_id LEFT JOIN {$wpdb->jasrek_jasenet} m ON m.id = r.jasen_id "
        . "WHERE l.laskupvm BETWEEN %s AND %s GROUP BY COALESCE(NULLIF(r.jasenlaji, ''), m.jasenlaji, '')", $alku, $loppu)) as $row) {
        $k = isset($lajit[$row->laji]) ? $row->laji : '';
        if (!isset($luokat[$k])) $luokat[$k] = array('n' => 0, 'laskutettu' => 0, 'n_mak' => 0, 'maksettu' => 0, 'avoin' => 0);
        foreach ($luokat[$k] as $f => $v) $luokat[$k][$f] += (float) $row->$f;
    }
    uksort($luokat, function ($a, $b) use ($lajit) {
        $ka = array_search($a, array_keys($lajit), true); $kb = array_search($b, array_keys($lajit), true);
        return ($ka === false ? 999 : $ka) - ($kb === false ? 999 : $kb);
    });

    // Kassaan vuoden aikana (maksupäivän mukaan, myös aiempien vuosien laskuista)
    $kassa = $wpdb->get_results($wpdb->prepare(
        "SELECT l.nimi, COUNT(*) AS n, SUM(COALESCE(r.maksettu_summa, r.summa)) AS summa, SUM(r.postimaksu) AS posti FROM {$wpdb->jasrek_rivit} r JOIN {$wpdb->jasrek_laskut} l ON l.id = r.lasku_id "
        . "WHERE r.maksettu BETWEEN %s AND %s GROUP BY l.id, l.nimi ORDER BY MIN(l.laskupvm), l.id", $alku, $loppu
    ));

    $posti = 0;
    foreach ($kassa as $k) $posti += (float) $k->posti;

    return compact('year', 'raja', 'edel', 'j', 'ero_ei_pvm', 'ei_liitt', 'laskut', 'luokat', 'kassa', 'posti', 'today');
}

function jasrek_report_laji_label($k) {
    return $k === '' ? 'Ilman jäsenluokkaa' : jasrek_laji_nimi($k);
}

/* ------------------------------------------------------------------
 * Excel
 * ------------------------------------------------------------------ */

add_action('admin_post_jasrek_report_xlsx', 'jasrek_handle_report_xlsx');
function jasrek_handle_report_xlsx() {
    jasrek_check('jasrek_report_xlsx');
    require_once JASREK_DIR . 'includes/xlsx.php';
    $year = jasrek_request_int('vuosi');
    if ($year < 1900) $year = (int) current_time('Y');
    $d = jasrek_report_data($year);
    $x = new JASREK_Xlsx();
    $T = function ($v) { return JASREK_Xlsx::text($v); };
    $sumf = function ($col, $money = false) { return JASREK_Xlsx::formula("SUM({$col}2:{$col}{last})", $money ? JASREK_Xlsx::S_BOLD_MONEY : JASREK_Xlsx::S_BOLD); };

    $rows = array();
    foreach ($d['j'] as $k => $v) $rows[] = array($T(jasrek_report_laji_label($k)), $v['alku'], $v['liittyi'], $v['erosi'], $v['loppu']);
    $x->add_sheet('Jäsenmäärät ' . $year,
        array('Jäsenluokka', 'Jäseniä ' . jasrek_date($d['edel']), 'Liittyi ' . $year, 'Erosi ' . $year, 'Jäseniä ' . jasrek_date($d['raja'])),
        $rows, array(26, 18, 14, 14, 18),
        array(JASREK_Xlsx::text('Yhteensä', JASREK_Xlsx::S_BOLD), $sumf('B'), $sumf('C'), $sumf('D'), $sumf('E')));

    $rows = array();
    foreach ($d['laskut'] as $l) $rows[] = array($T($l->nimi), JASREK_Xlsx::date($l->laskupvm), JASREK_Xlsx::date($l->erapaiva), (int) $l->n, (int) $l->n_mak,
        JASREK_Xlsx::money($l->laskutettu), JASREK_Xlsx::money($l->maksettu), JASREK_Xlsx::money($l->avoin), JASREK_Xlsx::money($l->erant));
    $x->add_sheet('Laskut ' . $year,
        array('Lasku', 'Laskun pvm', 'Eräpäivä', 'Laskuja', 'Maksettu kpl', 'Laskutettu €', 'Maksettu €', 'Avoinna €', 'Josta erääntynyt €'),
        $rows, array(28, 12, 12, 10, 12, 14, 14, 14, 18),
        array(JASREK_Xlsx::text('Yhteensä', JASREK_Xlsx::S_BOLD), '', '', $sumf('D'), $sumf('E'), $sumf('F', true), $sumf('G', true), $sumf('H', true), $sumf('I', true)));

    $rows = array();
    foreach ($d['luokat'] as $k => $v) $rows[] = array($T(jasrek_report_laji_label($k)), (int) $v['n'], (int) $v['n_mak'],
        JASREK_Xlsx::money($v['laskutettu']), JASREK_Xlsx::money($v['maksettu']), JASREK_Xlsx::money($v['avoin']));
    $x->add_sheet('Luokittain ' . $year,
        array('Jäsenluokka', 'Laskuja', 'Maksettu kpl', 'Laskutettu €', 'Maksettu €', 'Avoinna €'),
        $rows, array(26, 10, 12, 14, 14, 14),
        array(JASREK_Xlsx::text('Yhteensä', JASREK_Xlsx::S_BOLD), $sumf('B'), $sumf('C'), $sumf('D', true), $sumf('E', true), $sumf('F', true)));

    $rows = array();
    foreach ($d['kassa'] as $k) $rows[] = array($T($k->nimi), (int) $k->n, JASREK_Xlsx::money($k->summa), JASREK_Xlsx::money($k->posti), JASREK_Xlsx::money($k->summa - $k->posti));
    $x->add_sheet('Kertymä ' . $year, array('Lasku', 'Maksuja', 'Kertynyt € (maksupäivä ' . $year . ')', 'Josta postimaksuja €', 'Jäsenmaksuja €'), $rows, array(30, 10, 26, 20, 16),
        array(JASREK_Xlsx::text('Yhteensä', JASREK_Xlsx::S_BOLD), $sumf('B'), $sumf('C', true), $sumf('D', true), $sumf('E', true)));

    $rows = array();
    foreach (jasrek_merkkipaivat($year) as $mp) $rows[] = array($mp['pvm'] !== '' ? JASREK_Xlsx::date($mp['pvm']) : $T('vuoden aikana'), $T($mp['m']->etunimi . ' ' . $mp['m']->sukunimi), $T(jasrek_merkkipaiva_teksti($mp)), $T(jasrek_laji_nimi((string) $mp['m']->jasenlaji)));
    if ($rows) $x->add_sheet('Merkkipäivät ' . $year, array('Päivä', 'Jäsen', 'Merkkipäivä', 'Jäsenluokka'), $rows, array(12, 28, 22, 18));

    $bin = $x->build();
    jasrek_log('vienti', 0, 'vienti', 'Vuosiraportti ' . $year . ' Exceliin');
    nocache_headers();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="vuosiraportti-' . $year . '_' . jasrek_file_stamp() . '.xlsx"');
    header('Content-Length: ' . strlen($bin));
    echo $bin; // phpcs:ignore -- binääritiedosto
    exit;
}

/* ------------------------------------------------------------------
 * Sivu
 * ------------------------------------------------------------------ */

function jasrek_page_report() {
    if (jasrek_get('view') === 'kirjanpito') {
        jasrek_page_kirjanpito();
        return;
    }
    $years = jasrek_report_years();
    $def = ((int) current_time('n') <= 6) ? (int) current_time('Y') - 1 : (int) current_time('Y');
    $year = jasrek_get_int('vuosi', $def);
    if (!in_array($year, $years, true)) $year = $def;
    $d = jasrek_report_data($year);
    $s = jasrek_settings();
    $kesken = $d['raja'] < sprintf('%04d-12-31', $year);
    $tot = array('alku' => 0, 'liittyi' => 0, 'erosi' => 0, 'loppu' => 0);
    foreach ($d['j'] as $v) foreach ($tot as $f => $x) $tot[$f] += $v[$f];
    $sum = function ($rows, $f) { $t = 0; foreach ($rows as $r) $t += (float) (is_array($r) ? $r[$f] : $r->$f); return $t; };
    $kassa = $sum($d['kassa'], 'summa');
    ?>
    <div class="wrap jasrek-wrap jasrek-report">
        <?php jasrek_report_tabs(''); ?>
        <h1 class="wp-heading-inline">Vuosiraportti <?php echo (int) $year; ?></h1>
        <form method="get" class="jasrek-noprint jasrek-inline">
            <input type="hidden" name="page" value="jasrek-raportti">
            <select name="vuosi" data-jasrek-autosubmit>
                <?php foreach ($years as $y): ?><option value="<?php echo (int) $y; ?>" <?php selected($year, $y); ?>><?php echo (int) $y; ?></option><?php endforeach; ?>
            </select>
            <noscript><button class="button">Näytä</button></noscript>
            <button type="button" class="button" data-jasrek-print>🖨 Tulosta</button>
            <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=jasrek_report_xlsx&vuosi=' . $year), 'jasrek_report_xlsx')); ?>">Vie Exceliin</a>
            <a class="button" target="_blank" href="<?php echo esc_url(jasrek_voters_url(array('otsikko' => 'Vuosikokous ' . ((int) $year + 1)))); ?>" title="Tulostettava luettelo äänioikeutetuista allekirjoitussarakkeineen">🗳 Äänioikeusluettelo</a>
        </form>
        <hr class="wp-header-end">
        <p class="jasrek-report-sub"><?php echo esc_html($s['yhdistys']); ?> · <?php echo $kesken ? 'tilanne ' . esc_html(jasrek_date($d['raja'])) . ' (vuosi kesken)' : 'tilikausi 1.1.–31.12.' . (int) $year; ?> · tulostettu <?php echo esc_html(jasrek_date($d['today'])); ?></p>

        <div class="jasrek-cards">
            <div class="jasrek-card"><span class="jasrek-card-num"><?php echo (int) $tot['loppu']; ?></span><span class="jasrek-card-label">Jäseniä <?php echo esc_html(jasrek_date($d['raja'])); ?> (<?php echo ($tot['loppu'] - $tot['alku'] >= 0 ? '+' : '') . (int) ($tot['loppu'] - $tot['alku']); ?>)</span></div>
            <div class="jasrek-card jasrek-card-ok"><span class="jasrek-card-num"><?php echo (int) $tot['liittyi']; ?></span><span class="jasrek-card-label">Liittyi vuonna <?php echo (int) $year; ?></span></div>
            <div class="jasrek-card"><span class="jasrek-card-num"><?php echo (int) $tot['erosi']; ?></span><span class="jasrek-card-label">Erosi vuonna <?php echo (int) $year; ?></span></div>
            <div class="jasrek-card jasrek-card-ok"><span class="jasrek-card-num"><?php echo esc_html(jasrek_money($kassa)); ?></span><span class="jasrek-card-label">Jäsenmaksuja kertyi <?php echo (int) $year; ?></span></div>
        </div>

        <div class="jasrek-panel">
            <h2>Jäsenmäärä jäsenluokittain</h2>
            <table class="widefat striped jasrek-table jasrek-num">
                <thead><tr><th>Jäsenluokka</th><th>Jäseniä <?php echo esc_html(jasrek_date($d['edel'])); ?></th><th>Liittyi</th><th>Erosi</th><th>Jäseniä <?php echo esc_html(jasrek_date($d['raja'])); ?></th><th>Muutos</th></tr></thead>
                <tbody>
                <?php foreach ($d['j'] as $k => $v): $mu = $v['loppu'] - $v['alku']; ?>
                    <tr><td><?php echo esc_html(jasrek_report_laji_label($k)); ?></td><td><?php echo (int) $v['alku']; ?></td><td><?php echo (int) $v['liittyi']; ?></td><td><?php echo (int) $v['erosi']; ?></td>
                        <td><strong><?php echo (int) $v['loppu']; ?></strong></td><td><?php echo ($mu > 0 ? '+' : '') . (int) $mu; ?></td></tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot><tr><th>Yhteensä</th><th><?php echo (int) $tot['alku']; ?></th><th><?php echo (int) $tot['liittyi']; ?></th><th><?php echo (int) $tot['erosi']; ?></th><th><?php echo (int) $tot['loppu']; ?></th><th><?php echo ($tot['loppu'] - $tot['alku'] > 0 ? '+' : '') . (int) ($tot['loppu'] - $tot['alku']); ?></th></tr></tfoot>
            </table>
            <p class="description">Jäsenmäärät lasketaan liittymis- ja eroamispäivistä. Jäsenluokka on jäsenen nykyinen luokka.
                <?php if ($d['ei_liitt']): ?><?php echo (int) $d['ei_liitt']; ?> jäseneltä puuttuu liittymispäivä – heidät lasketaan jäseniksi koko vuoden.<?php endif; ?>
                <?php if ($d['ero_ei_pvm']): ?><br><strong><?php echo (int) $d['ero_ei_pvm']; ?> eronnutta ilman eroamispäivää</strong> ei ole mukana luvuissa. Lisää eroamispäivä jäsenen tietoihin, jos ero osui tälle vuodelle.<?php endif; ?></p>
        </div>

        <div class="jasrek-panel">
            <h2>Jäsenmaksut <?php echo (int) $year; ?></h2>
            <?php if (!$d['laskut']): ?>
                <p class="jasrek-muted">Vuodelle <?php echo (int) $year; ?> päivättyjä laskuja ei ole.</p>
            <?php else: ?>
            <table class="widefat striped jasrek-table jasrek-num">
                <thead><tr><th>Lasku</th><th>Eräpäivä</th><th>Laskuja</th><th>Maksettu</th><th>Laskutettu</th><th>Maksettu €</th><th>Avoinna</th></tr></thead>
                <tbody>
                <?php foreach ($d['laskut'] as $l): ?>
                    <tr><td><?php echo esc_html($l->nimi); ?></td><td><?php echo esc_html(jasrek_date($l->erapaiva)); ?></td><td><?php echo (int) $l->n; ?></td><td><?php echo (int) $l->n_mak; ?></td>
                        <td><?php echo esc_html(jasrek_money($l->laskutettu)); ?></td><td><?php echo esc_html(jasrek_money($l->maksettu)); ?></td>
                        <td><?php echo esc_html(jasrek_money($l->avoin)); ?><?php if ($l->erant > 0): ?><div class="jasrek-sub">josta erääntynyt <?php echo esc_html(jasrek_money($l->erant)); ?></div><?php endif; ?></td></tr>
                <?php endforeach; ?>
                </tbody>
                <?php if (count($d['laskut']) > 1): ?>
                <tfoot><tr><th>Yhteensä</th><th></th><th><?php echo (int) $sum($d['laskut'], 'n'); ?></th><th><?php echo (int) $sum($d['laskut'], 'n_mak'); ?></th>
                    <th><?php echo esc_html(jasrek_money($sum($d['laskut'], 'laskutettu'))); ?></th><th><?php echo esc_html(jasrek_money($sum($d['laskut'], 'maksettu'))); ?></th><th><?php echo esc_html(jasrek_money($sum($d['laskut'], 'avoin'))); ?></th></tr></tfoot>
                <?php endif; ?>
            </table>

            <h3>Jäsenluokittain</h3>
            <table class="widefat striped jasrek-table jasrek-num">
                <thead><tr><th>Jäsenluokka</th><th>Laskuja</th><th>Maksettu</th><th>Laskutettu</th><th>Maksettu €</th><th>Avoinna</th></tr></thead>
                <tbody>
                <?php foreach ($d['luokat'] as $k => $v): ?>
                    <tr><td><?php echo esc_html(jasrek_report_laji_label($k)); ?></td><td><?php echo (int) $v['n']; ?></td><td><?php echo (int) $v['n_mak']; ?></td>
                        <td><?php echo esc_html(jasrek_money($v['laskutettu'])); ?></td><td><?php echo esc_html(jasrek_money($v['maksettu'])); ?></td><td><?php echo esc_html(jasrek_money($v['avoin'])); ?></td></tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot><tr><th>Yhteensä</th><th><?php echo (int) $sum($d['luokat'], 'n'); ?></th><th><?php echo (int) $sum($d['luokat'], 'n_mak'); ?></th>
                    <th><?php echo esc_html(jasrek_money($sum($d['luokat'], 'laskutettu'))); ?></th><th><?php echo esc_html(jasrek_money($sum($d['luokat'], 'maksettu'))); ?></th><th><?php echo esc_html(jasrek_money($sum($d['luokat'], 'avoin'))); ?></th></tr></tfoot>
            </table>
            <p class="description">Laskut, joiden päiväys on vuonna <?php echo (int) $year; ?>. Jäsenluokka laskutushetken mukaan. Avoinna-sarake näyttää tämänhetkisen tilanteen.</p>
            <?php endif; ?>
        </div>

        <div class="jasrek-panel">
            <h2>Jäsenmaksukertymä <?php echo (int) $year; ?></h2>
            <?php if (!$d['kassa']): ?>
                <p class="jasrek-muted">Vuonna <?php echo (int) $year; ?> ei kirjattu maksuja.</p>
            <?php else: ?>
            <table class="widefat striped jasrek-table jasrek-num">
                <?php $pc = $d['posti'] > 0; ?>
                <thead><tr><th>Lasku</th><th>Maksuja</th><th>Kertynyt</th><?php if ($pc): ?><th>Josta postimaksuja</th><th>Jäsenmaksuja</th><?php endif; ?></tr></thead>
                <tbody>
                <?php foreach ($d['kassa'] as $k): ?>
                    <tr><td><?php echo esc_html($k->nimi); ?></td><td><?php echo (int) $k->n; ?></td><td><?php echo esc_html(jasrek_money($k->summa)); ?></td>
                        <?php if ($pc): ?><td><?php echo esc_html(jasrek_money($k->posti)); ?></td><td><?php echo esc_html(jasrek_money($k->summa - $k->posti)); ?></td><?php endif; ?></tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot><tr><th>Yhteensä</th><th><?php echo (int) $sum($d['kassa'], 'n'); ?></th><th><?php echo esc_html(jasrek_money($kassa)); ?></th>
                    <?php if ($pc): ?><th><?php echo esc_html(jasrek_money($d['posti'])); ?></th><th><?php echo esc_html(jasrek_money($kassa - $d['posti'])); ?></th><?php endif; ?></tr></tfoot>
            </table>
            <p class="description">Maksupäivän mukaan vuonna <?php echo (int) $year; ?> kirjatut maksut, myös aiempien vuosien laskuista. Vertaa kirjanpidon jäsenmaksutuottoihin.</p>
            <?php endif; ?>
        </div>

        <?php if (jasrek_merkki_on()): $mps = jasrek_merkkipaivat($year); ?>
        <div class="jasrek-panel" id="merkkipaivat">
            <h2>Merkkipäivät <?php echo (int) $year; ?></h2>
            <?php if (!$mps): ?>
                <p class="jasrek-muted">Ei merkkipäiviä vuonna <?php echo (int) $year; ?>.</p>
            <?php else: ?>
            <table class="widefat striped jasrek-table">
                <thead><tr><th>Päivä</th><th>Jäsen</th><th>Merkkipäivä</th><th>Jäsenluokka</th></tr></thead>
                <tbody>
                <?php foreach ($mps as $mp): ?>
                    <tr><td><?php echo esc_html(jasrek_merkkipaiva_pvm($mp)); ?></td>
                        <td><?php echo esc_html($mp['m']->etunimi . ' ' . $mp['m']->sukunimi); ?><?php if (jasrek_tunnus_on() && $mp['m']->tunnus): ?> <span class="jasrek-call"><?php echo esc_html($mp['m']->tunnus); ?></span><?php endif; ?></td>
                        <td><strong><?php echo esc_html(jasrek_merkkipaiva_teksti($mp)); ?></strong></td>
                        <td><?php echo esc_html(jasrek_laji_nimi((string) $mp['m']->jasenlaji)); ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
            <p class="description">Voimassa olevat jäsenet. Syntymävuosi tai -päivä merkitään jäsenen tietoihin (vapaaehtoinen). Vuodet valitaan <a href="<?php echo esc_url(admin_url('admin.php?page=jasrek-asetukset#jasrek-merkkipaivat')); ?>">asetuksissa</a>.</p>
        </div>
        <?php endif; ?>
    </div>
    <?php
}
