<?php
if (!defined('ABSPATH')) exit;

/*
 * Maksuvahvistus jäsenelle (valinnainen, asetuksista). Kun maksu kirjataan pankkitiedostosta tai
 * joukkotoiminnolla, vahvistukset laitetaan jonoon ja lähetetään taustalla pienissä erissä, jotta
 * suurikaan tuonti ei aiheuta aikakatkaisua. Yksittäisen vahvistuksen voi lähettää laskun sivulta.
 */

add_action('jasrek_vahvistus_run', 'jasrek_run_vahvistukset');

function jasrek_vahvistus_on() {
    return jasrek_settings()['vahvistus_kaytossa'] === '1';
}

/** Viestin paikkamerkit laskuriviltä. */
function jasrek_vahvistus_vars($r, $lasku) {
    $p = jasrek_rivi_person($r);
    $s = jasrek_settings();
    $maksettu = $r->maksettu_summa !== null ? $r->maksettu_summa : $r->summa;
    return array(
        'etunimi' => $p['etunimi'], 'sukunimi' => $p['sukunimi'], 'nimi' => $p['nimi'], 'tunnus' => $p['tunnus'],
        'jasenluokka' => $p['jasenluokka'], 'laskun_nimi' => $lasku->nimi, 'summa' => html_entity_decode(jasrek_money($maksettu)),
        'maksupaiva' => jasrek_date($r->maksettu), 'viite' => jasrek_viite_format($r->viite), 'yhdistys' => $s['yhdistys'],
    );
}

/** Lähettää maksuvahvistuksen yhdelle maksetulle laskuriville. Palauttaa true/false. */
function jasrek_send_vahvistus($rivi_id) {
    $r = jasrek_get_rivi($rivi_id);
    if (!$r || !$r->maksettu) return false;
    $to = (string) $r->to_email;
    if (!is_email($to)) { $GLOBALS['jasrek_mail_error'] = 'ei sähköpostiosoitetta'; return false; }
    $lasku = jasrek_get_lasku($r->lasku_id);
    if (!$lasku) return false;
    $s = jasrek_settings();
    $vars = jasrek_vahvistus_vars($r, $lasku);
    $otsikko = wp_strip_all_tags(str_replace(array_map(function ($k) { return '{' . $k . '}'; }, array_keys($vars)), array_values($vars), $s['vahvistus_otsikko']));
    $html = jasrek_email_layout($otsikko, '<div>' . jasrek_fill($s['vahvistus_teksti'], $vars) . '</div>');
    $ok = jasrek_mail($to, $otsikko, $html);
    jasrek_log_rivi($r, $ok ? 'vahvistus' : 'vahvistus_virhe', $ok ? $to : $to . ': ' . ($GLOBALS['jasrek_mail_error'] ?: 'lähetys epäonnistui'), $lasku);
    return $ok;
}

/** Lisää laskurivit vahvistusjonoon ja käynnistää taustalähetyksen. */
function jasrek_queue_vahvistukset($ids) {
    $ids = array_values(array_unique(array_filter(array_map('intval', (array) $ids))));
    // Vain rivit, joilla on sähköpostiosoite (paperilaskun saaneille ei lähetetä mitään)
    $ids = array_values(array_filter($ids, function ($rid) {
        $r = jasrek_get_rivi($rid);
        return $r && is_email((string) $r->to_email);
    }));
    if (!$ids) return 0;
    $jono = get_option('jasrek_vahvistus_jono', array());
    $jono = array_values(array_unique(array_merge(is_array($jono) ? $jono : array(), $ids)));
    update_option('jasrek_vahvistus_jono', $jono, false);
    if (!wp_next_scheduled('jasrek_vahvistus_run')) wp_schedule_single_event(time(), 'jasrek_vahvistus_run');
    return count($ids);
}

/** Taustalähetys: enintään 20 vahvistusta kerrallaan, loput seuraavalla kierroksella. */
function jasrek_run_vahvistukset() {
    $jono = get_option('jasrek_vahvistus_jono', array());
    if (!is_array($jono) || !$jono) return;
    if (!jasrek_vahvistus_on()) { delete_option('jasrek_vahvistus_jono'); return; } // kytketty pois välillä
    $batch = array_slice($jono, 0, 20);
    update_option('jasrek_vahvistus_jono', array_values(array_slice($jono, 20)), false); // ensin pois jonosta: ei tuplalähetyksiä
    foreach ($batch as $rid) jasrek_send_vahvistus($rid);
    if (count($jono) > 20 && !wp_next_scheduled('jasrek_vahvistus_run')) wp_schedule_single_event(time() + 60, 'jasrek_vahvistus_run');
}

/** Yksittäinen vahvistus laskun sivulta. */
add_action('admin_post_jasrek_send_vahvistus', 'jasrek_handle_send_vahvistus');
function jasrek_handle_send_vahvistus() {
    $rid = jasrek_request_int('rivi');
    jasrek_check('jasrek_vahvistus_' . $rid);
    $r = jasrek_get_rivi($rid);
    if (!$r) jasrek_redirect('jasrek-laskutus');
    $ok = jasrek_send_vahvistus($rid);
    jasrek_redirect('jasrek-laskutus', array('view' => 'lasku', 'id' => $r->lasku_id, 'jasrek_msg' => $ok ? 'vahvistus_sent' : 'send_failed'));
}

/** Ruksi maksun kirjauslomakkeisiin (vain, jos ominaisuus on käytössä). */
function jasrek_vahvistus_checkbox() {
    if (!jasrek_vahvistus_on()) return;
    echo '<label class="jasrek-radio jasrek-vahvistus-ruksi"><input type="checkbox" name="vahvistus" value="1" checked> Lähetä maksuvahvistus sähköpostilla jäsenille, joiden maksu nyt kirjataan</label>';
}
