<?php
if (!defined('ABSPATH')) exit;

/*
 * Automaattinen varmuuskopio sähköpostilla (valinnainen, asetuksista): viikoittain, kuukausittain tai
 * neljännesvuosittain, karkeasti valittuna kellonaikana. WP-Cron tarkistaa kerran päivässä valittuna
 * aikana, onko lähetys erääntynyt. Tila (edellinen lähetys, virhe) pidetään omassa optiossaan, jotta
 * asetusten tallennus ei nollaa sitä.
 */

add_action('jasrek_autobackup', 'jasrek_run_autobackup');
add_action('init', 'jasrek_schedule_autobackup');
register_deactivation_hook(JASREK_FILE, function () {
    wp_clear_scheduled_hook('jasrek_autobackup');
    wp_clear_scheduled_hook('jasrek_vahvistus_run');
});

/** Lähetysvälit: avain => array(nimi, päiviä). */
function jasrek_autobackup_valit() {
    return array(
        'viikko' => array('Viikoittain', 7),
        'kk'     => array('Kuukausittain', 30),
        'q'      => array('Neljännesvuosittain', 91),
    );
}
/** Karkeat kellonajat: tunti => nimi. */
function jasrek_autobackup_ajat() {
    return array('3' => 'Yöllä (n. klo 3)', '7' => 'Aamulla (n. klo 7)', '12' => 'Päivällä (n. klo 12)', '18' => 'Illalla (n. klo 18)');
}

function jasrek_autobackup_state() {
    $st = get_option('jasrek_autobackup_state', array());
    return wp_parse_args(is_array($st) ? $st : array(), array('last' => 0, 'last_ok' => 0, 'error' => '', 'hour' => -1));
}

/** Vastaanottajat asetuksista (pilkulla erotettu), vain kelvolliset osoitteet. Oletus: ylläpitäjän osoite. */
function jasrek_autobackup_recipients() {
    $raw = (string) jasrek_settings()['autobackup_to'];
    if (trim($raw) === '') $raw = (string) get_option('admin_email');
    $out = array();
    foreach (preg_split('/[\s,;]+/', $raw) as $e) {
        $e = sanitize_email($e);
        if ($e !== '' && is_email($e)) $out[] = $e;
    }
    return array_values(array_unique($out));
}

/** Seuraava ajankohta valittuna tuntina sivuston aikavyöhykkeellä (Unix-aika). */
function jasrek_autobackup_next_time($hour) {
    $tz = wp_timezone();
    $now = new DateTime('now', $tz);
    $t = new DateTime('today', $tz);
    $t->setTime((int) $hour, 0);
    if ($t <= $now) $t->modify('+1 day');
    return $t->getTimestamp();
}

/** Ajastus päälle/pois ja uudelleen, jos kellonaika on vaihtunut. */
function jasrek_schedule_autobackup() {
    $s = jasrek_settings();
    $on = $s['autobackup'] === '1';
    $next = wp_next_scheduled('jasrek_autobackup');
    $st = jasrek_autobackup_state();
    $hour = (int) $s['autobackup_aika'];
    if (!$on) {
        if ($next) wp_clear_scheduled_hook('jasrek_autobackup');
        return;
    }
    if ($next && (int) $st['hour'] === $hour) return;
    wp_clear_scheduled_hook('jasrek_autobackup');
    wp_schedule_event(jasrek_autobackup_next_time($hour), 'daily', 'jasrek_autobackup');
    $st['hour'] = $hour;
    update_option('jasrek_autobackup_state', $st, false);
}

/** Onko lähetys erääntynyt (edellisestä onnistuneesta yli valittu väli, pieni jousto ajastuksen heittoon)? */
function jasrek_autobackup_due() {
    $s = jasrek_settings();
    $valit = jasrek_autobackup_valit();
    $days = isset($valit[$s['autobackup_vali']]) ? $valit[$s['autobackup_vali']][1] : 30;
    $st = jasrek_autobackup_state();
    return !$st['last_ok'] || (time() - (int) $st['last_ok']) >= ($days * DAY_IN_SECONDS - 6 * HOUR_IN_SECONDS);
}

function jasrek_run_autobackup() {
    $s = jasrek_settings();
    if ($s['autobackup'] !== '1' || !jasrek_autobackup_due()) return;
    jasrek_send_autobackup(false);
}

/**
 * Lähettää varmuuskopion sähköpostin liitteenä. $kasin: true = "Lähetä nyt" -painike.
 * Palauttaa true tai virheilmoituksen.
 */
function jasrek_send_autobackup($kasin) {
    $s = jasrek_settings();
    $to = jasrek_autobackup_recipients();
    $st = jasrek_autobackup_state();
    $st['last'] = time();
    if (!$to) {
        $st['error'] = 'Vastaanottajan sähköpostiosoite puuttuu tai on virheellinen.';
        update_option('jasrek_autobackup_state', $st, false);
        return $st['error'];
    }
    $data = jasrek_backup_build($s['autobackup_loki'] === '1');
    $json = jasrek_backup_json($data);
    $nimi = jasrek_backup_filename();
    if (jasrek_backup_can_gzip()) { $liite = gzencode($json, 9); $nimi .= '.gz'; } else { $liite = $json; }
    $sum = jasrek_backup_summary($data);
    $html = jasrek_email_layout('Jäsenrekisterin varmuuskopio',
        '<p>Liitteenä jäsenrekisterin ' . ($kasin ? '' : 'automaattinen ') . 'varmuuskopio ' . esc_html(wp_date('j.n.Y H:i')) . '.</p>'
        . '<p>Jäseniä ' . (int) $sum['jasenet'] . ' (voimassa ' . (int) $sum['aktiiviset'] . '), laskuja ' . (int) $sum['laskut'] . ', laskurivejä ' . (int) $sum['rivit'] . ' (maksettu ' . (int) $sum['maksetut'] . ')'
        . ($sum['loki'] !== null ? ', muutoslokissa ' . (int) $sum['loki'] . ' merkintää' : '') . '.</p>'
        . '<p>Tiedot palautetaan kohdassa <em>Jäsenrekisteri → Asetukset → Varmuuskopio ja siirto</em>. Liite sisältää jäsenten henkilötietoja: säilytä se huolellisesti äläkä välitä sitä eteenpäin.</p>'
        . '<p style="color:#6b7785;font-size:13px">Automaattisen varmuuskopion asetuksia voi muuttaa tai sen voi kytkeä pois kohdassa <a href="' . esc_url(admin_url('admin.php?page=jasrek-asetukset#jasrek-autobackup')) . '">Asetukset</a>.</p>');
    $ok = jasrek_mail(implode(', ', $to), 'Jäsenrekisterin varmuuskopio – ' . $s['yhdistys'] . ' – ' . wp_date('j.n.Y'), $html, array(), array($nimi => $liite));
    if ($ok) {
        $st['last_ok'] = time();
        $st['error'] = '';
    } else {
        $st['error'] = $GLOBALS['jasrek_mail_error'] ?: 'Sähköpostin lähetys epäonnistui.';
    }
    update_option('jasrek_autobackup_state', $st, false);
    jasrek_log('vienti', 0, 'vienti', ($kasin ? 'Varmuuskopio lähetetty sähköpostilla' : 'Automaattinen varmuuskopio') . ' → ' . implode(', ', $to) . ($ok ? '' : ' – EPÄONNISTUI: ' . $st['error']));
    return $ok ? true : $st['error'];
}

add_action('admin_post_jasrek_autobackup_now', 'jasrek_handle_autobackup_now');
function jasrek_handle_autobackup_now() {
    jasrek_check('jasrek_autobackup_now');
    $r = jasrek_send_autobackup(true);
    if ($r !== true) set_transient('jasrek_backup_err_' . get_current_user_id(), $r, 10 * MINUTE_IN_SECONDS);
    wp_safe_redirect(admin_url('admin.php?page=jasrek-asetukset&jasrek_msg=' . ($r === true ? 'autobackup_sent' : 'autobackup_failed') . '#jasrek-autobackup'));
    exit;
}

/** Asetuspaneeli (asetuslomakkeen sisällä). */
function jasrek_autobackup_panel($s) {
    $st = jasrek_autobackup_state();
    $next = wp_next_scheduled('jasrek_autobackup');
    $to = jasrek_autobackup_recipients();
    ?>
    <div class="jasrek-panel" id="jasrek-autobackup">
        <h2>Automaattinen varmuuskopio sähköpostilla</h2>
        <p><label class="jasrek-radio"><input type="checkbox" name="autobackup" value="1" <?php checked($s['autobackup'], '1'); ?>> Lähetä varmuuskopio automaattisesti sähköpostin liitteenä</label></p>
        <div class="jasrek-grid">
            <p><label for="jasrek-abvali">Kuinka usein</label>
               <select id="jasrek-abvali" name="autobackup_vali"><?php foreach (jasrek_autobackup_valit() as $k => $v): ?><option value="<?php echo esc_attr($k); ?>" <?php selected($s['autobackup_vali'], $k); ?>><?php echo esc_html($v[0]); ?></option><?php endforeach; ?></select></p>
            <p><label for="jasrek-abaika">Kellonaika</label>
               <select id="jasrek-abaika" name="autobackup_aika"><?php foreach (jasrek_autobackup_ajat() as $k => $v): ?><option value="<?php echo esc_attr($k); ?>" <?php selected((string) $s['autobackup_aika'], (string) $k); ?>><?php echo esc_html($v); ?></option><?php endforeach; ?></select></p>
            <p><label for="jasrek-abto">Kenelle</label>
               <input type="text" id="jasrek-abto" name="autobackup_to" value="<?php echo esc_attr($s['autobackup_to']); ?>" placeholder="<?php echo esc_attr((string) get_option('admin_email')); ?>">
               <span class="description">Yksi tai useampi osoite pilkulla erotettuna. Tyhjänä sivuston ylläpitäjän osoite.</span></p>
        </div>
        <p><label class="jasrek-radio"><input type="checkbox" name="autobackup_loki" value="1" <?php checked($s['autobackup_loki'], '1'); ?>> Muutosloki mukaan varmuuskopioon</label></p>
        <p class="jasrek-hint">🔒 Liite sisältää jäsenten henkilötietoja. Valitse vastaanottajaksi oma, henkilökohtainen osoite – ei yhteistä tai monen lukemaa postilaatikkoa.</p>
        <p class="description">WordPress käynnistää ajastetut tehtävät sivuston käyntien yhteydessä, joten hiljaisella sivustolla lähetys voi tulla vähän valittua aikaa myöhemmin.
            <?php if ($st['last_ok']): ?><br>Edellinen onnistunut lähetys: <?php echo esc_html(wp_date('j.n.Y H:i', (int) $st['last_ok'])); ?><?php endif; ?>
            <?php if ($s['autobackup'] === '1' && $next): ?><br>Seuraava tarkistus: <?php echo esc_html(wp_date('j.n.Y H:i', $next)); ?><?php endif; ?></p>
        <?php if ($st['error'] !== ''): ?><p class="jasrek-hint">⚠ Edellinen lähetys epäonnistui (<?php echo esc_html(wp_date('j.n.Y H:i', (int) $st['last'])); ?>): <?php echo esc_html($st['error']); ?></p><?php endif; ?>
        <?php if ($to): ?><p><a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=jasrek_autobackup_now'), 'jasrek_autobackup_now')); ?>" data-confirm="<?php echo esc_attr('Lähetetäänkö varmuuskopio nyt osoitteeseen ' . implode(', ', $to) . '? (Tallenna ensin, jos muutit osoitetta.)'); ?>">Lähetä varmuuskopio nyt</a>
            <span class="description">Kokeile, että viesti ja liite tulevat perille. Lähettää tallennettuun osoitteeseen.</span></p><?php endif; ?>
    </div>
    <?php
}
