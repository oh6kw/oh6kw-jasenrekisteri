<?php
if (!defined('ABSPATH')) exit;
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The plugin's own custom tables (members, invoices, audit log). The data is admin-only and changes on every write, so object caching is not used.

/*
 * Muutosloki: kuka muutti mitä ja milloin.
 *
 * kohde:    jasen (jäsenen tiedot), maksu (jäsenen yksittäinen lasku: maksu, lähetys, postitus),
 *           lasku (koko lasku), asetukset, viesti (tiedotteet), vienti (tuonnit ja viennit)
 * kohde_id: jäsenen id (jasen, maksu), laskun id (lasku), muuten 0
 * muutokset: JSON-lista [kenttä, vanha, uusi] tai vapaamuotoiset lisätiedot
 */

function jasrek_log($kohde, $kohde_id, $toiminto, $kuvaus, $muutokset = null) {
    global $wpdb;
    $u = wp_get_current_user();
    $nimi = ($u && $u->ID) ? $u->display_name . ($u->user_login !== $u->display_name ? ' (' . $u->user_login . ')' : '') : 'Järjestelmä (automaattinen)';
    $wpdb->insert(jasrek_t('loki'), array(
        'aika'        => current_time('mysql'),
        'kayttaja_id' => $u ? (int) $u->ID : 0,
        'kayttaja'    => function_exists('mb_substr') ? mb_substr($nimi, 0, 100) : substr($nimi, 0, 100),
        'kohde'       => $kohde,
        'kohde_id'    => (int) $kohde_id,
        'toiminto'    => $toiminto,
        'kuvaus'      => (string) $kuvaus,
        'muutokset'   => ($muutokset === null || $muutokset === array()) ? null : wp_json_encode($muutokset),
    ));
    $id = (int) $wpdb->insert_id;
    jasrek_log_cleanup();
    return $id;
}

/** Poistaa säilytysajan ylittäneet merkinnät (kerran vuorokaudessa). */
function jasrek_log_cleanup($force = false) {
    if (!$force && get_transient('jasrek_log_cleanup')) return;
    set_transient('jasrek_log_cleanup', 1, DAY_IN_SECONDS);
    $kk = (int) jasrek_settings()['loki_kk'];
    if ($kk <= 0) return;
    global $wpdb;
    $raja = gmdate('Y-m-d H:i:s', strtotime(current_time('mysql') . " -$kk months"));
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->jasrek_loki} WHERE aika < %s", $raja));
}

/** Muutokset kahden tilannekuvan välillä: [[kenttä, vanha, uusi], ...]. */
function jasrek_log_diff($old, $new) {
    $out = array();
    foreach (array_unique(array_merge(array_keys($old), array_keys($new))) as $k) {
        $a = isset($old[$k]) ? (string) $old[$k] : '';
        $b = isset($new[$k]) ? (string) $new[$k] : '';
        if ($a !== $b) $out[] = array($k, $a, $b);
    }
    return $out;
}

/** Jäsenen tiedot luettavassa muodossa lokia varten (kenttä => arvo). */
function jasrek_member_snapshot($m) {
    if (!$m) return array();
    $m = (object) $m;
    $s = array(
        'Etunimi'     => (string) $m->etunimi,
        'Sukunimi'    => (string) $m->sukunimi,
    );
    if (jasrek_tunnus_on() || (string) $m->tunnus !== '') $s[jasrek_tunnus_nimi()] = (string) $m->tunnus;
    $s += array(
        'Sähköposti'  => (string) $m->email,
        'Puhelin'     => (string) $m->puhelin,
        'Osoite'      => (string) $m->osoite,
        'Liittynyt'   => jasrek_date($m->liittynyt),
        'Syntynyt'    => isset($m->syntynyt) ? jasrek_syntynyt_teksti($m->syntynyt) : '',
        'Jäsenluokka' => jasrek_laji_nimi((string) $m->jasenlaji) !== '' ? jasrek_laji_nimi((string) $m->jasenlaji) : (string) $m->jasenlaji,
        'Jäsenyys'    => jasrek_tila_nimi($m->tila),
        'Eronnut'     => isset($m->eronnut_pvm) ? jasrek_date($m->eronnut_pvm) : '',
        'Paperilasku' => !empty($m->paperilasku) ? 'kyllä' : '',
        'Lisätiedot'  => (string) $m->lisatiedot,
    );
    $lisa = jasrek_member_lisa($m);
    foreach (jasrek_lisakentat() as $id => $k) {
        $v = isset($lisa[$id]) ? (string) $lisa[$id] : '';
        $s[$k['nimi']] = jasrek_lisa_value($k, $v, 'kyllä');
    }
    return $s;
}

function jasrek_log_member_name($m) {
    $m = (object) $m;
    return trim($m->etunimi . ' ' . $m->sukunimi);
}

function jasrek_log_toiminnot() {
    return array(
        'lisatty'          => 'Jäsen lisätty',
        'muokattu'         => 'Tietoja muutettu',
        'poistettu'        => 'Jäsen poistettu',
        'tuotu'            => 'Lisätty CSV-tuonnilla',
        'tuonti_paivitys'  => 'Päivitetty CSV-tuonnilla',
        'laskulle'         => 'Lisätty laskulle',
        'maksettu'         => 'Merkitty maksetuksi',
        'vahvistus'        => 'Maksuvahvistus lähetetty',
        'vahvistus_virhe'  => 'Maksuvahvistus epäonnistui',
        'maksu_peruttu'    => 'Maksumerkintä peruttu',
        'poistettu_laskulta' => 'Poistettu laskulta',
        'lahetetty'        => 'Lasku lähetetty',
        'muistutettu'      => 'Muistutus lähetetty',
        'postitettu'       => 'Paperilasku postitettu',
        'muistutus_postitettu' => 'Paperimuistutus postitettu',
        'lasku_luotu'      => 'Lasku luotu',
        'lasku_muokattu'   => 'Laskua muokattu',
        'lasku_poistettu'  => 'Lasku poistettu',
        'jasenia_lisatty'  => 'Jäseniä lisätty laskulle',
        'lahetys'          => 'Laskut lähetetty',
        'muistutukset'     => 'Muistutukset lähetetty',
        'auto_muistutukset'=> 'Automaattiset muistutukset',
        'asetukset'        => 'Asetuksia muutettu',
        'tiedote'          => 'Tiedote lähetetty',
        'vienti'           => 'Tiedot viety',
        'tuonti'           => 'Tiedot tuotu',
        'palautus'         => 'Varmuuskopio palautettu',
    );
}

function jasrek_log_kohteet() {
    return array(
        ''          => 'Kaikki',
        'jasen'     => 'Jäsenten tiedot',
        'maksu'     => 'Maksut ja lähetykset',
        'lasku'     => 'Laskut',
        'asetukset' => 'Asetukset',
        'viesti'    => 'Tiedotteet',
        'vienti'    => 'Tuonnit ja viennit',
    );
}

/**
 * Lähetysajo (useita AJAX-eriä) kirjataan yhdeksi merkinnäksi, jota päivitetään erä kerrallaan.
 */
function jasrek_log_run($lasku, $toiminto, $run_key, $sent_names, $failed) {
    global $wpdb;
    $t = jasrek_t('loki');
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->jasrek_loki} WHERE kohde = 'lasku' AND kohde_id = %d AND toiminto = %s AND kayttaja_id = %d ORDER BY id DESC LIMIT 1",
        $lasku->id, $toiminto, get_current_user_id()
    ));
    $data = $row && $row->muutokset ? json_decode($row->muutokset, true) : null;
    if (!is_array($data) || !isset($data['ajo']) || $data['ajo'] !== $run_key) {
        if (!$sent_names && !$failed) return;
        $data = array('ajo' => $run_key, 'nimet' => array(), 'epaonnistui' => 0);
        $row = null;
    }
    $data['nimet'] = array_merge($data['nimet'], $sent_names);
    $data['epaonnistui'] += $failed;
    $n = count($data['nimet']);
    $kuvaus = $lasku->nimi . ': ' . $n . ($toiminto === 'muistutukset' ? ' muistutusta' : ' laskua') . ' sähköpostilla'
        . ($data['epaonnistui'] ? ', ' . $data['epaonnistui'] . ' epäonnistui' : '');
    if ($row) {
        $wpdb->update($t, array('kuvaus' => $kuvaus, 'muutokset' => wp_json_encode($data), 'aika' => current_time('mysql')), array('id' => $row->id));
    } else {
        jasrek_log('lasku', $lasku->id, $toiminto, $kuvaus, $data);
    }
}

/* ------------------------------------------------------------------
 * Näkymät
 * ------------------------------------------------------------------ */

/** Muutokset HTML-listana. */
function jasrek_log_changes_html($json) {
    if (!$json) return '';
    $d = json_decode($json, true);
    if (!is_array($d)) return '';
    if (isset($d['nimet'])) {
        $n = $d['nimet'];
        $txt = implode(', ', array_slice($n, 0, 30)) . (count($n) > 30 ? ' … (+' . (count($n) - 30) . ')' : '');
        return $n ? '<div class="jasrek-sub">' . esc_html($txt) . '</div>' : '';
    }
    $out = '<ul class="jasrek-log-changes">';
    foreach ($d as $c) {
        if (!is_array($c) || count($c) < 3) continue;
        $out .= '<li><span class="jasrek-log-field">' . esc_html($c[0]) . ':</span> ';
        if ((string) $c[1] !== '') $out .= '<del>' . esc_html(wp_html_excerpt((string) $c[1], 120, '…')) . '</del> → ';
        $out .= (string) $c[2] !== '' ? '<ins>' . esc_html(wp_html_excerpt((string) $c[2], 120, '…')) . '</ins>' : '<em class="jasrek-muted">tyhjä</em>';
        $out .= '</li>';
    }
    return $out . '</ul>';
}

function jasrek_log_target_link($e) {
    if (($e->kohde === 'jasen' || $e->kohde === 'maksu') && $e->kohde_id) {
        return admin_url('admin.php?page=jasenrekisteri&view=edit&id=' . (int) $e->kohde_id);
    }
    if ($e->kohde === 'lasku' && $e->kohde_id) {
        return admin_url('admin.php?page=jasrek-laskutus&view=lasku&id=' . (int) $e->kohde_id);
    }
    return '';
}

function jasrek_log_table($entries, $show_target = true) {
    $labels = jasrek_log_toiminnot();
    ?>
    <table class="widefat striped jasrek-table jasrek-log">
        <thead><tr><th style="width:130px">Aika</th><th style="width:170px">Käyttäjä</th><th>Tapahtuma</th></tr></thead>
        <tbody>
        <?php if (!$entries): ?><tr><td colspan="3" class="jasrek-empty">Ei merkintöjä.</td></tr><?php endif; ?>
        <?php foreach ($entries as $e):
            $link = $show_target ? jasrek_log_target_link($e) : ''; ?>
            <tr>
                <td><?php echo esc_html(gmdate('j.n.Y H:i', strtotime($e->aika))); ?></td>
                <td><?php echo esc_html($e->kayttaja); ?></td>
                <td><span class="jasrek-badge jasrek-log-<?php echo esc_attr($e->kohde); ?>"><?php echo esc_html(isset($labels[$e->toiminto]) ? $labels[$e->toiminto] : $e->toiminto); ?></span>
                    <?php echo $link ? '<a href="' . esc_url($link) . '">' . esc_html($e->kuvaus) . '</a>' : esc_html($e->kuvaus); ?>
                    <?php echo jasrek_log_changes_html($e->muutokset); // phpcs:ignore -- escapattu funktiossa ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

/** Jäsenen muutoshistoria jäsenen lomakkeen alle. */
function jasrek_member_log_panel($m) {
    global $wpdb;
    $entries = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->jasrek_loki} WHERE kohde IN ('jasen','maksu') AND kohde_id = %d ORDER BY id DESC LIMIT 15", $m->id
    ));
    ?>
    <div class="jasrek-panel">
        <h2>Muutoshistoria</h2>
        <?php jasrek_log_table($entries, false); ?>
        <p><a href="<?php echo esc_url(admin_url('admin.php?page=jasrek-loki&jasen=' . (int) $m->id)); ?>">Näytä kaikki jäsenen merkinnät muutoslokissa →</a></p>
    </div>
    <?php
}

function jasrek_page_log() {
    global $wpdb;
    $t = jasrek_t('loki');
    $kohteet = jasrek_log_kohteet();
    $kohde = sanitize_key(jasrek_get('kohde'));
    if (!isset($kohteet[$kohde])) $kohde = '';
    $q     = jasrek_get('q');
    $jasen = jasrek_get_int('jasen', 0);
    $sivu  = max(1, jasrek_get_int('sivu', 1));
    $per   = 50;

    // WHERE kootaan SQL-literaaleista; arvot sidotaan prepare()-kutsulla.
    $where = array('1=1'); $args = array();
    if ($kohde !== '') { $where[] = 'kohde = %s'; $args[] = $kohde; }
    if ($jasen) { $where[] = "kohde IN ('jasen','maksu') AND kohde_id = %d"; $args[] = $jasen; }
    if ($q !== '') {
        $like = '%' . $wpdb->esc_like($q) . '%';
        $where[] = '(kuvaus LIKE %s OR kayttaja LIKE %s OR muutokset LIKE %s)';
        array_push($args, $like, $like, $like);
    }
    $w = implode(' AND ', $where);
    $count_sql = "SELECT COUNT(*) FROM {$wpdb->jasrek_loki} WHERE $w";
    $list_sql  = "SELECT * FROM {$wpdb->jasrek_loki} WHERE $w ORDER BY id DESC LIMIT %d OFFSET %d";
    // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- built from the literal fragments above; values bound with prepare().
    $total = (int) $wpdb->get_var($args ? $wpdb->prepare($count_sql, $args) : $count_sql);
    $entries = $wpdb->get_results($wpdb->prepare($list_sql, array_merge($args, array($per, ($sivu - 1) * $per))));
    // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $pages = max(1, (int) ceil($total / $per));
    $kk = (int) jasrek_settings()['loki_kk'];
    $jm = $jasen ? $wpdb->get_row($wpdb->prepare("SELECT etunimi, sukunimi FROM {$wpdb->jasrek_jasenet} WHERE id = %d", $jasen)) : null;
    ?>
    <div class="wrap jasrek-wrap">
        <h1>Muutosloki</h1>
        <p class="description">Kaikki jäsenrekisteriin, laskuihin ja asetuksiin tehdyt muutokset: kuka, mitä ja milloin.
            <?php echo $kk > 0 ? 'Merkinnät säilytetään ' . (int) $kk . ' kuukautta.' : 'Merkinnät säilytetään pysyvästi.'; ?>
            Säilytysajan voi muuttaa <a href="<?php echo esc_url(admin_url('admin.php?page=jasrek-asetukset#jasrek-loki')); ?>">asetuksissa</a>.</p>

        <form method="get" class="jasrek-toolbar">
            <input type="hidden" name="page" value="jasrek-loki">
            <?php if ($jasen): ?><input type="hidden" name="jasen" value="<?php echo (int) $jasen; ?>"><?php endif; ?>
            <div class="jasrek-toolbar-right">
                <select name="kohde">
                    <?php foreach ($kohteet as $k => $label): ?><option value="<?php echo esc_attr($k); ?>" <?php selected($kohde, $k); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?>
                </select>
                <input type="search" name="q" value="<?php echo esc_attr($q); ?>" class="jasrek-search" placeholder="Hae nimellä, käyttäjällä tai muutoksella…">
                <button type="submit" class="button">Suodata</button>
                <?php if ($kohde !== '' || $q !== '' || $jasen): ?><a class="button-link" href="<?php echo esc_url(admin_url('admin.php?page=jasrek-loki')); ?>">Tyhjennä suodatus</a><?php endif; ?>
            </div>
            <span class="jasrek-muted"><?php echo $jm ? esc_html('Jäsen: ' . $jm->etunimi . ' ' . $jm->sukunimi . ' · ') : ($jasen ? 'Jäsen #' . (int) $jasen . ' (poistettu) · ' : ''); ?><?php echo (int) $total; ?> merkintää</span>
        </form>

        <?php jasrek_log_table($entries); ?>

        <?php if ($pages > 1): ?>
        <p class="jasrek-pager">
            <?php for ($i = 1; $i <= $pages; $i++):
                if ($pages > 12 && abs($i - $sivu) > 3 && $i !== 1 && $i !== $pages) { if (abs($i - $sivu) === 4) echo '<span>…</span> '; continue; } ?>
                <?php if ($i === $sivu): ?><span class="button disabled"><?php echo esc_html($i); ?></span>
                <?php else: ?><a class="button" href="<?php echo esc_url(add_query_arg(array('sivu' => $i))); ?>"><?php echo esc_html($i); ?></a><?php endif; ?>
            <?php endfor; ?>
        </p>
        <?php endif; ?>
    </div>
    <?php
}
