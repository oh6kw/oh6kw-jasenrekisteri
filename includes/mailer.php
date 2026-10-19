<?php
if (!defined('ABSPATH')) exit;
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The plugin's own custom tables (members, invoices, audit log). The data is admin-only and changes on every write, so object caching is not used.

/*
 * Tiedotteet jäsenille. Viesti tallennetaan väliaikaisesti ja lähetetään
 * selaimesta käsin pienissä erissä, jotta suurikaan lista ei aiheuta aikakatkaisua.
 */

function jasrek_mail_recipients($kohde) {
    global $wpdb;
    $base = "SELECT id FROM {$wpdb->jasrek_jasenet} WHERE email IS NOT NULL AND email <> ''";
    if ($kohde === 'aktiiviset') {
        $ids = $wpdb->get_col("$base AND tila = 'aktiivinen' ORDER BY id"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $base is a literal query without user input.
    } elseif (strpos($kohde, 'luokka_') === 0) {
        $ids = $wpdb->get_col($wpdb->prepare("$base AND tila = 'aktiivinen' AND jasenlaji = %s ORDER BY id", substr($kohde, 7))); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $base is a literal query without user input.
    } elseif ($kohde === 'puuttuvat') {
        // Voimassa olevat, joilta puuttuu jokin perustieto (sähköposti, osoite tai puhelin) – ja joilla on sähköposti
        $puute = array_keys(jasrek_checkup_data(array('osoite', 'puhelin'))['puutteet']);
        $ids = $puute ? $wpdb->get_col($wpdb->prepare("$base AND tila = 'aktiivinen' AND id IN (" . implode(',', array_fill(0, count($puute), '%d')) . ") ORDER BY id", $puute)) : array(); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $base is a literal query; ids are bound.
    } elseif ($kohde === 'maksamattomat') {
        $latest = (int) $wpdb->get_var("SELECT MAX(id) FROM {$wpdb->jasrek_laskut}");
        if (!$latest) return array();
        $ids = $wpdb->get_col($wpdb->prepare("$base AND id IN (SELECT jasen_id FROM {$wpdb->jasrek_rivit} WHERE lasku_id = %d AND maksettu IS NULL) ORDER BY id", $latest)); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $base is a literal query without user input.
    } else {
        $ids = $wpdb->get_col("$base ORDER BY id"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $base is a literal query without user input.
    }
    return array_map('intval', $ids);
}

/** Pohja "Tarkista tietosi" -viestille. */
function jasrek_tarkista_pohja() {
    return array(
        'subject' => 'Tarkistathan jäsentietosi',
        'body'    => "Hei {etunimi},\n\npidämme yhdistyksen jäsenrekisterin ajan tasalla. Alla ovat tiedot, jotka meillä on sinusta:\n\n{omat_tiedot}\n\nJos jokin tieto puuttuu tai on muuttunut, vastaa tähän viestiin ja kerro oikeat tiedot. Jos kaikki on kunnossa, sinun ei tarvitse tehdä mitään.\n\nYstävällisin terveisin\n{yhdistys}",
    );
}

/**
 * Jäsenen omat tiedot riveinä {omat_tiedot}-paikkamerkkiä varten: array(otsikko, arvo, tila),
 * tila '' = arvo, 'puuttuu' = perustieto puuttuu (korostetaan), 'ei' = ei ilmoitettu, 'viiva' = tyhjä lisäkenttä.
 */
function jasrek_omat_tiedot_rivit($m) {
    $g = function ($k) use ($m) { return isset($m->$k) ? trim((string) $m->$k) : ''; };
    $rivit = array(array('Nimi', trim($g('etunimi') . ' ' . $g('sukunimi')), ''));
    if (jasrek_tunnus_on()) $rivit[] = array(jasrek_tunnus_nimi(), $g('tunnus'), $g('tunnus') !== '' ? '' : 'ei');
    $rivit[] = array('Jäsenluokka', jasrek_laji_nimi($g('jasenlaji')) !== '' ? jasrek_laji_nimi($g('jasenlaji')) : 'Jäsen', '');
    foreach (array('osoite' => 'Osoite', 'puhelin' => 'Puhelin', 'email' => 'Sähköposti') as $k => $nimi) {
        $rivit[] = array($nimi, $g($k), $g($k) !== '' ? '' : 'puuttuu');
    }
    $liitt = $g('liittynyt') !== '' && $g('liittynyt') !== '0000-00-00';
    $rivit[] = array('Liittynyt', $liitt ? jasrek_date($g('liittynyt')) : '', $liitt ? '' : 'ei');
    $rivit[] = array('Syntymävuosi tai -päivä', $g('syntynyt') !== '' ? jasrek_syntynyt_teksti($g('syntynyt')) : '', $g('syntynyt') !== '' ? '' : 'ei');
    $lisa = is_object($m) ? jasrek_member_lisa($m) : array();
    foreach (jasrek_lisakentat() as $kid => $k) {
        $v = isset($lisa[$kid]) ? (string) $lisa[$kid] : '';
        $rivit[] = array($k['nimi'], $v !== '' ? jasrek_lisa_value($k, $v, 'Kyllä') : '', $v !== '' ? '' : 'viiva');
    }
    return $rivit;
}

/** {omat_tiedot} sähköpostiin taulukkona. Puuttuvat perustiedot korostetaan. */
function jasrek_omat_tiedot_html($m) {
    $html = '<table role="presentation" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:4px 0 8px;font-size:14px">';
    foreach (jasrek_omat_tiedot_rivit($m) as $r) {
        if ($r[2] === 'puuttuu') $v = '<span style="color:#b45309;font-weight:bold">puuttuu</span>';
        elseif ($r[2] === 'ei') $v = '<span style="color:#6b7785">ei ilmoitettu</span>' . ($r[0] === 'Syntymävuosi tai -päivä' ? ' <span style="color:#6b7785">(vapaaehtoinen)</span>' : '');
        elseif ($r[2] === 'viiva') $v = '–';
        else $v = esc_html($r[1]);
        $html .= '<tr><td style="padding:4px 16px 4px 0;color:#6b7785;vertical-align:top;white-space:nowrap">' . esc_html($r[0]) . '</td><td style="padding:4px 0;vertical-align:top">' . $v . '</td></tr>';
    }
    return $html . '</table>';
}

/** {omat_tiedot} paperilaskuun ja PDF:ään tiiviinä tekstinä (tilaa on vähän). Tyhjät lisäkentät jätetään pois. */
function jasrek_omat_tiedot_teksti($m) {
    $out = array();
    foreach (jasrek_omat_tiedot_rivit($m) as $r) {
        if ($r[2] === 'viiva') continue;
        $out[] = $r[0] . ': ' . ($r[2] === 'puuttuu' ? 'PUUTTUU' : ($r[2] === 'ei' ? 'ei ilmoitettu' : $r[1]));
    }
    return implode("\n", $out);
}

/** Laskurivin jäsen {omat_tiedot}-paikkamerkkiä varten (null, jos rivi ei ole rekisterin jäsenen). */
function jasrek_rivi_jasen($r) {
    global $wpdb;
    if (empty($r->jasen_id)) return null;
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->jasrek_jasenet} WHERE id = %d", (int) $r->jasen_id));
}

/** Laskun saatteen {omat_tiedot}: 'html' = taulukko täytetyn tekstin sisään, 'teksti' = rivit ennen täyttöä. */
function jasrek_saate_omat_tiedot($text, $r, $muoto) {
    if (strpos($text, '{omat_tiedot}') === false) return $text;
    $m = jasrek_rivi_jasen($r);
    if (!$m) return str_replace('{omat_tiedot}', '', $text);
    return str_replace('{omat_tiedot}', $muoto === 'html' ? jasrek_omat_tiedot_html($m) : jasrek_omat_tiedot_teksti($m), $text);
}

function jasrek_mass_mail_html($subject, $body, $m) {
    $s = jasrek_settings();
    $vars = array(
        'etunimi' => $m->etunimi, 'sukunimi' => $m->sukunimi,
        'nimi' => trim($m->etunimi . ' ' . $m->sukunimi), 'tunnus' => $m->tunnus, 'yhdistys' => $s['yhdistys'],
        'jasenluokka' => (isset($m->jasenlaji) && jasrek_laji_nimi((string) $m->jasenlaji) !== '') ? jasrek_laji_nimi((string) $m->jasenlaji) : 'Jäsen',
    );
    $html = jasrek_fill($body, $vars);
    if (strpos($html, '{omat_tiedot}') !== false) $html = str_replace('{omat_tiedot}', jasrek_omat_tiedot_html($m), $html);
    return jasrek_email_layout($subject, '<div>' . $html . '</div>');
}

add_action('admin_post_jasrek_mass_mail', 'jasrek_handle_mass_mail');
function jasrek_handle_mass_mail() {
    jasrek_check('jasrek_mass_mail');
    $sivu    = jasrek_mail_sivu(jasrek_post('sivu'));
    $subject = sanitize_text_field(jasrek_post('subject'));
    $body    = sanitize_textarea_field(jasrek_post('body'));
    $kohde   = jasrek_post('kohde', 'aktiiviset');
    $sallitut = $sivu === 'tarkistus' ? array('aktiiviset', 'puuttuvat') : array('aktiiviset', 'kaikki', 'maksamattomat');
    foreach (array_keys(jasrek_jasenlajit()) as $lid) $sallitut[] = 'luokka_' . $lid;
    if (!in_array($kohde, $sallitut, true)) $kohde = 'aktiiviset';
    $paluu = function ($args) use ($sivu) {
        if ($sivu !== '') $args = array_merge(array('view' => $sivu), $args);
        jasrek_redirect('jasrek-sahkoposti', $args);
    };

    $uid = get_current_user_id();
    set_transient(jasrek_mail_draft_key($sivu, $uid), compact('subject', 'body', 'kohde'), DAY_IN_SECONDS);

    if ($subject === '' || $body === '') $paluu(array('jasrek_msg' => 'mail_invalid'));

    // Esikatselu: viesti yhden arvotun vastaanottajan tiedoilla, mitään ei lähetetä
    if (jasrek_post('esikatselu') !== '') {
        $m = jasrek_mail_sample_member($kohde, (int) jasrek_post('edellinen'));
        if (!$m) $paluu(array('jasrek_msg' => 'no_mail_recipients'));
        $paluu(array('esikatselu' => (int) $m->id));
    }

    // Testiviesti annettuun osoitteeseen, arvotun vastaanottajan tiedoilla
    if (jasrek_post('test') !== '') {
        $to = sanitize_email(jasrek_post('test_to'));
        if (!$to) $paluu(array('jasrek_msg' => 'test_invalid'));
        $m = jasrek_mail_sample_member($kohde);
        if (!$m) {
            $me = wp_get_current_user();
            $m = (object) array('id' => 0, 'etunimi' => $me->first_name ?: $me->display_name, 'sukunimi' => $me->last_name, 'tunnus' => '', 'email' => $to, 'osoite' => '', 'puhelin' => '', 'jasenlaji' => '', 'liittynyt' => '', 'syntynyt' => '', 'lisakentat' => '');
        }
        $ok = jasrek_mail($to, '[TESTI] ' . $subject, jasrek_mass_mail_html($subject, $body, $m));
        $paluu(array('jasrek_msg' => $ok ? 'test_sent' : 'send_failed', 'jasrek_to' => $to, 'jasrek_jasen' => (int) $m->id));
    }

    $ids = jasrek_mail_recipients($kohde);
    if (!$ids) $paluu(array('jasrek_msg' => 'no_mail_recipients'));
    // Pienet kirjaimet: sanitize_key() muuttaa tunnuksen pieneksi luettaessa.
    $key = strtolower(wp_generate_password(12, false));
    set_transient('jasrek_mail_run_' . $key, array('subject' => $subject, 'body' => $body, 'ids' => $ids, 'user' => $uid, 'sivu' => $sivu), DAY_IN_SECONDS);
    $kohteet = array('aktiiviset' => 'kaikki jäsenet', 'kaikki' => 'kaikki, myös eronneet', 'maksamattomat' => 'viimeisimmän laskun maksamattomat', 'puuttuvat' => 'jäsenet, joilta puuttuu tietoja');
    $kuvaus = strpos($kohde, 'luokka_') === 0 ? jasrek_laji_nimi(substr($kohde, 7)) : (isset($kohteet[$kohde]) ? $kohteet[$kohde] : $kohde);
    jasrek_log('viesti', 0, 'tiedote', ($sivu === 'tarkistus' ? 'Tietojentarkistuspyyntö ' : '') . '"' . $subject . '" – ' . count($ids) . ' vastaanottajaa (' . $kuvaus . ')', array(array('Viesti', '', $body)));
    $paluu(array('run' => $key));
}

add_action('wp_ajax_jasrek_mass_mail_chunk', 'jasrek_ajax_mass_mail_chunk');
function jasrek_ajax_mass_mail_chunk() {
    if (!current_user_can('manage_options')) wp_send_json_error('Ei oikeuksia', 403);
    $key = sanitize_key(jasrek_post('key'));
    check_ajax_referer('jasrek_mass_mail_' . $key . '_' . jasrek_data_gen(), 'nonce'); // vanhenee, jos tiedot palautetaan

    $run = get_transient('jasrek_mail_run_' . $key);
    if (!$run) wp_send_json_error('Lähetystä ei löytynyt tai se on vanhentunut.');

    global $wpdb;
    // Edistyminen pidetään palvelimella, jotta sivun päivitys ei lähetä samoja viestejä uudelleen.
    $offset = isset($run['offset']) ? (int) $run['offset'] : 0;
    $batch  = array_slice($run['ids'], $offset, 5);
    $run['offset'] = $offset + count($batch);
    set_transient('jasrek_mail_run_' . $key, $run, DAY_IN_SECONDS);
    $sent = 0; $failed = 0; $errors = array();
    foreach ($batch as $id) {
        $m = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->jasrek_jasenet} WHERE id = %d", $id));
        if ($m && is_email($m->email) && jasrek_mail($m->email, $run['subject'], jasrek_mass_mail_html($run['subject'], $run['body'], $m))) {
            $sent++;
        } else {
            $failed++;
            if ($m) $errors[] = $m->etunimi . ' ' . $m->sukunimi . ': ' . ($GLOBALS['jasrek_mail_error'] ?: 'lähetys epäonnistui');
        }
    }
    $next = $offset + count($batch);
    $remaining = max(0, count($run['ids']) - $next);
    if ($remaining === 0) {
        delete_transient('jasrek_mail_run_' . $key);
        delete_transient(jasrek_mail_draft_key(isset($run['sivu']) ? $run['sivu'] : '', $run['user']));
    }
    wp_send_json_success(array('sent' => $sent, 'failed' => $failed, 'remaining' => $remaining, 'offset' => $next, 'errors' => $errors));
}

/** Sähköpostisivun välilehti: '' = tiedote, 'tarkistus' = tietojentarkistuspyyntö. */
function jasrek_mail_sivu($arvo) {
    return $arvo === 'tarkistus' ? 'tarkistus' : '';
}

/** Luonnos säilyy välilehtikohtaisesti, joten tiedote ja tarkistuspyyntö eivät sekoitu. */
function jasrek_mail_draft_key($sivu, $uid) {
    return 'jasrek_mail_draft_' . ($sivu === 'tarkistus' ? 'tark_' : '') . (int) $uid;
}

function jasrek_mail_draft($sivu) {
    $uid = get_current_user_id();
    $draft = get_transient(jasrek_mail_draft_key($sivu, $uid));
    // Aiemmin tarkistuspyyntö täytettiin tiedotteen luonnokseen. Sellainen siirretään omalle
    // välilehdelleen (jos siellä ei ole omaa luonnosta), ja tiedote jää tyhjäksi.
    if ($sivu === '' && is_array($draft) && strpos((string) $draft['body'], '{omat_tiedot}') !== false) {
        if (!is_array(get_transient(jasrek_mail_draft_key('tarkistus', $uid)))) {
            set_transient(jasrek_mail_draft_key('tarkistus', $uid), $draft, DAY_IN_SECONDS);
        }
        delete_transient(jasrek_mail_draft_key('', $uid));
        $draft = null;
    }
    if ($sivu === 'tarkistus' && jasrek_get('pohja') === 'uusi') {
        delete_transient(jasrek_mail_draft_key($sivu, get_current_user_id()));
        $draft = null;
    }
    if ($sivu === 'tarkistus' && !is_array($draft)) {
        $draft = array_merge(jasrek_tarkista_pohja(), array('kohde' => 'aktiiviset'));
    }
    if (!is_array($draft)) $draft = array('subject' => '', 'body' => '', 'kohde' => 'aktiiviset');
    if ($sivu === 'tarkistus' && !in_array($draft['kohde'], array('aktiiviset', 'puuttuvat'), true) && strpos((string) $draft['kohde'], 'luokka_') !== 0) $draft['kohde'] = 'aktiiviset';
    if ($sivu === '' && $draft['kohde'] === 'puuttuvat') $draft['kohde'] = 'aktiiviset';
    // Tietojen tarkistus -sivun painike valitsee vastaanottajiksi ne, joilta puuttuu tietoja
    if ($sivu === 'tarkistus' && jasrek_get('kohde') === 'puuttuvat') $draft['kohde'] = 'puuttuvat';
    return $draft;
}

/** Arpoo jäsenen valituista vastaanottajista esikatselua ja testiviestiä varten (ei edellistä uudelleen, jos voi). */
function jasrek_mail_sample_member($kohde, $ohita = 0) {
    global $wpdb;
    $ids = jasrek_mail_recipients($kohde);
    if (!$ids) $ids = jasrek_mail_recipients('aktiiviset');
    if (!$ids) return null;
    if (count($ids) > 1) $ids = array_values(array_diff($ids, array((int) $ohita)));
    $id = $ids[wp_rand(0, count($ids) - 1)];
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->jasrek_jasenet} WHERE id = %d", $id));
}

function jasrek_mail_tabs($sivu) {
    $tabs = array('' => 'Tiedote', 'tarkistus' => 'Tietojentarkistuspyyntö');
    echo '<nav class="nav-tab-wrapper jasrek-navtabs">';
    foreach ($tabs as $k => $label) {
        $url = admin_url('admin.php?page=jasrek-sahkoposti' . ($k !== '' ? '&view=' . $k : ''));
        echo '<a class="nav-tab' . ($sivu === $k ? ' nav-tab-active' : '') . '" href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
    }
    echo '</nav>';
}

function jasrek_page_mail() {
    global $wpdb;
    $key = sanitize_key(jasrek_get('run', ''));
    $run = $key ? get_transient('jasrek_mail_run_' . $key) : null;
    // Vanha osoite (…&pohja=tarkista) ohjautuu tarkistuspyynnön välilehdelle
    $sivu = jasrek_mail_sivu(jasrek_get('pohja') === 'tarkista' ? 'tarkistus' : jasrek_get('view'));
    if ($run) $sivu = jasrek_mail_sivu(isset($run['sivu']) ? $run['sivu'] : '');
    $draft = jasrek_mail_draft($sivu);
    $sivu_url = admin_url('admin.php?page=jasrek-sahkoposti' . ($sivu ? '&view=' . $sivu : ''));

    $esik = jasrek_get_int('esikatselu');
    $esik_m = $esik ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->jasrek_jasenet} WHERE id = %d", $esik)) : null;

    $c_puu = count(jasrek_mail_recipients('puuttuvat'));
    $c_akt = count(jasrek_mail_recipients('aktiiviset'));
    $c_all = count(jasrek_mail_recipients('kaikki'));
    $c_unp = count(jasrek_mail_recipients('maksamattomat'));
    ?>
    <div class="wrap jasrek-wrap">
        <h1>Sähköposti jäsenille</h1>
        <?php jasrek_notice(); ?>
        <?php jasrek_mail_tabs($sivu); ?>

        <?php if ($run): ?>
            <div class="jasrek-panel">
                <h2>Lähetetään: <?php echo esc_html($run['subject']); ?></h2>
                <p><?php echo count($run['ids']); ?> vastaanottajaa<?php echo !empty($run['offset']) ? ', joista ' . (int) $run['offset'] . ' jo käsitelty' : ''; ?>. Pidä tämä sivu auki, kunnes lähetys on valmis.</p>
                <button type="button" class="button button-primary jasrek-run" data-autostart="1"
                    data-action="jasrek_mass_mail_chunk"
                    data-params="<?php echo esc_attr(wp_json_encode(array('key' => $key, 'offset' => 0, 'nonce' => wp_create_nonce('jasrek_mass_mail_' . $key . '_' . jasrek_data_gen())))); ?>"
                    data-done-url="<?php echo esc_url($sivu_url); ?>">Jatka lähetystä</button>
                <div class="jasrek-progress" hidden><div class="jasrek-progress-bar"><span></span></div><p class="jasrek-progress-text"></p></div>
            </div>
        <?php else: ?>
        <?php if ($sivu === 'tarkistus'): ?>
            <p class="description jasrek-mail-intro">Jokainen vastaanottaja saa viestissä <strong>vain omat tietonsa</strong> taulukkona (<code>{omat_tiedot}</code>) – puuttuvat korostettuina – ja pyynnön ilmoittaa muutokset vastaamalla viestiin. Kenenkään muun tiedot eivät näy. Viesti lähtee vasta, kun painat <em>Lähetä</em>; sitä ennen voit muokata tekstiä, katsoa esikatselun ja lähettää testin itsellesi.
            <a href="<?php echo esc_url(add_query_arg('pohja', 'uusi', $sivu_url)); ?>" data-confirm="Palautetaanko otsikko ja viesti oletuspohjaksi? Omat muutoksesi katoavat.">Palauta oletusteksti</a></p>
        <?php endif; ?>

        <?php if ($esik_m): ?>
            <div class="jasrek-panel jasrek-esikatselu">
                <h2>Esikatselu</h2>
                <p class="description">Viesti sellaisena kuin <strong><?php echo esc_html(trim($esik_m->etunimi . ' ' . $esik_m->sukunimi)); ?></strong> sen saisi (arvottu valituista vastaanottajista). Mitään ei ole lähetetty. Painamalla <em>Esikatselu</em> uudelleen arvotaan toinen jäsen.</p>
                <iframe class="jasrek-preview-frame" sandbox="allow-same-origin" title="Viestin esikatselu" srcdoc="<?php echo esc_attr(jasrek_mass_mail_html($draft['subject'], $draft['body'], $esik_m)); ?>"></iframe>
            </div>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="jasrek-panel jasrek-form">
            <input type="hidden" name="action" value="jasrek_mass_mail">
            <input type="hidden" name="sivu" value="<?php echo esc_attr($sivu); ?>">
            <input type="hidden" name="edellinen" value="<?php echo esc_attr($esik_m ? (int) $esik_m->id : 0); ?>">
            <?php wp_nonce_field('jasrek_mass_mail'); ?>

            <fieldset class="jasrek-fieldset">
                <legend>Vastaanottajat</legend>
                <label class="jasrek-radio"><input type="radio" name="kohde" value="aktiiviset" <?php checked($draft['kohde'], 'aktiiviset'); ?>> Kaikki jäsenet (<?php echo esc_html($c_akt); ?>)</label>
                <?php foreach (jasrek_jasenlajit() as $lid => $l): $k = 'luokka_' . $lid; ?>
                    <label class="jasrek-radio jasrek-indent"><input type="radio" name="kohde" value="<?php echo esc_attr($k); ?>" <?php checked($draft['kohde'], $k); ?>> Vain: <?php echo esc_html($l['nimi']); ?> (<?php echo count(jasrek_mail_recipients($k)); ?>)</label>
                <?php endforeach; ?>
                <?php if ($sivu === 'tarkistus'): ?>
                    <label class="jasrek-radio"><input type="radio" name="kohde" value="puuttuvat" <?php checked($draft['kohde'], 'puuttuvat'); ?>> Jäsenet, joilta puuttuu osoite tai puhelinnumero (<?php echo esc_html($c_puu); ?>)</label>
                <?php else: ?>
                    <label class="jasrek-radio"><input type="radio" name="kohde" value="kaikki" <?php checked($draft['kohde'], 'kaikki'); ?>> Kaikki, myös eronneet (<?php echo esc_html($c_all); ?>)</label>
                    <label class="jasrek-radio"><input type="radio" name="kohde" value="maksamattomat" <?php checked($draft['kohde'], 'maksamattomat'); ?>> Viimeisimmän laskun maksamattomat (<?php echo esc_html($c_unp); ?>)</label>
                <?php endif; ?>
                <p class="description">Vain jäsenet, joilla on sähköpostiosoite. Jokainen saa oman viestinsä – osoitteet eivät näy muille.</p>
            </fieldset>

            <p><label for="jasrek-subject">Otsikko</label>
               <input type="text" id="jasrek-subject" name="subject" value="<?php echo esc_attr($draft['subject']); ?>" required></p>
            <p><label for="jasrek-body">Viesti</label>
               <textarea id="jasrek-body" name="body" rows="<?php echo $sivu === 'tarkistus' ? 14 : 12; ?>" required placeholder="Hei {etunimi},&#10;&#10;…"><?php echo esc_textarea($draft['body']); ?></textarea>
               <?php echo wp_kses_post(jasrek_placeholders('jasrek-body', $sivu === 'tarkistus' ? 'tarkistus' : 'tiedote', 'Rivinvaihdot säilyvät.')); ?></p>

            <p class="jasrek-actions">
                <button type="submit" name="esikatselu" value="1" class="button button-large">Esikatselu</button>
                <button type="submit" class="button button-primary button-large" data-confirm="Lähetetäänkö viesti valituille jäsenille?">Lähetä</button>
                <span class="description">Esikatselu näyttää viestin yhden arvotun vastaanottajan tiedoilla – mitään ei lähetetä.</span>
            </p>
            <div class="jasrek-testbox">
                <label for="jasrek-test-to">Testiviesti osoitteeseen</label>
                <input type="email" id="jasrek-test-to" name="test_to" value="<?php echo esc_attr(wp_get_current_user()->user_email); ?>">
                <button type="submit" name="test" value="1" class="button">Lähetä testi</button>
                <span class="description">Oletuksena sinun WordPress-tunnuksesi osoite (<?php echo esc_html(wp_get_current_user()->display_name); ?>). Testi lähtee vain tähän osoitteeseen. Viestiin arvotaan yhden valitun vastaanottajan tiedot, jotta näet, miltä viesti jäsenelle näyttää – kenen tiedot, kerrotaan lähetyksen jälkeen.</span>
            </div>
        </form>
        <?php endif; ?>
    </div>
    <?php
}
