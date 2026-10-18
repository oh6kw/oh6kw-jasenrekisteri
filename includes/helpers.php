<?php
if (!defined('ABSPATH')) exit;
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The plugin's own custom tables (members, invoices, audit log). The data is admin-only and changes on every write, so object caching is not used.

/* ------------------------------------------------------------------
 * Taulut, asetukset, oikeudet
 * ------------------------------------------------------------------ */

function jasrek_table_names() {
    return array(
        'jasenet' => 'jasenrekisteri',            // sama taulu kuin aiemmissa versioissa
        'laskut'  => 'jasenrekisteri_laskut',
        'rivit'   => 'jasenrekisteri_laskurivit',
        'loki'    => 'jasenrekisteri_loki',
    );
}

/**
 * Taulujen nimet $wpdb-olion ominaisuuksina ($wpdb->jasrek_jasenet jne.), kuten WordPressin omat
 * taulut ($wpdb->posts). SQL-kyselyissä taulun nimi luetaan aina tästä.
 */
function jasrek_register_tables() {
    global $wpdb;
    foreach (jasrek_table_names() as $key => $table) {
        $wpdb->{'jasrek_' . $key} = $wpdb->prefix . $table;
    }
}
jasrek_register_tables();
add_action('switch_blog', 'jasrek_register_tables');

function jasrek_t($name) {
    global $wpdb;
    $names = jasrek_table_names();
    return $wpdb->prefix . $names[$name];
}

function jasrek_settings() {
    $defaults = array(
        'yhdistys'        => get_bloginfo('name'),
        'iban'            => '',
        'bic'             => '',
        'ytunnus'         => '',
        'lahettaja_nimi'  => get_bloginfo('name'),
        'lahettaja_email' => get_option('admin_email'),
        'vastaus_email'   => '',
        'yhteystiedot'    => '',
        'tunnus_kaytossa' => '1',
        'tunnus_nimi'     => 'Jäsennumero',
        'tunnus_isot'     => '0',
        'maksukoodi'      => 'viivakoodi',
        'jasenlajit'      => array(),
        'lisakentat'      => array(),
        'loki_kk'         => '24',
        'postimaksu'      => '0',
        'osoite'          => '',
        'sposti_pdf'      => '0',
        'tarrat'          => array(), // osoitetarrojen arkki ja hienosäätö (muistetaan tarrasivulta)
        'kp_pankki'       => '',
        'kp_tuotot'       => '',
        'kp_posti'        => '',
        'postiosoite'     => '',
        'paperi_malli'    => 'tilisiirto',
        'sposti_malli'    => 'maksukortti',
        'merkki_kaytossa' => '1',
        'merkki_ika'      => '50, 60, 70, 75, 80, 85, 90, 95, 100',
        'merkki_jasen'    => '10, 20, 25, 30, 40, 50, 60',
        'auto_muistutus'  => '0',
        'auto_paivia'     => '14',
        'auto_max'        => '1',
        'auto_raportti'   => '1',
        'poista_tiedot'   => '0',
        'viesti_oletus'   => "Hei {etunimi},\n\nohessa {laskun_nimi}. Muistathan käyttää maksaessasi viitenumeroa, jotta maksusi kohdistuu oikein.\n\nKiitos, että olet mukana toiminnassa!\n\nYstävällisin terveisin\n{yhdistys}",
        'muistutus_teksti'=> "Hei {etunimi},\n\nemme ole vielä havainneet maksuasi alla olevaan laskuun. Jos olet jo maksanut, tämä viesti on aiheeton – kiitos!\n\nYstävällisin terveisin\n{yhdistys}",
    );
    $saved = get_option('jasrek_settings', array());
    $s = wp_parse_args(is_array($saved) ? $saved : array(), $defaults);
    // Tyhjäksi jätetyn tekstin tilalla käytetään oletusta, jottei lasku lähde ilman saatetta.
    foreach (array('viesti_oletus', 'muistutus_teksti', 'maksukoodi', 'yhdistys', 'lahettaja_nimi') as $k) {
        if (trim((string) $s[$k]) === '') $s[$k] = $defaults[$k];
    }
    // 4.8.1: sähköpostin tilisiirtolomakemalli korvattiin maksukortilla
    if ($s['sposti_malli'] === 'tilisiirto' || !in_array($s['sposti_malli'], array('perus', 'maksukortti'), true)) $s['sposti_malli'] = 'maksukortti';
    return $s;
}

/** Onko lisätunniste (esim. jäsennumero) käytössä, ja millä nimellä. */
function jasrek_tunnus_on() {
    return jasrek_settings()['tunnus_kaytossa'] === '1';
}
function jasrek_tunnus_nimi() {
    $n = trim(jasrek_settings()['tunnus_nimi']);
    return $n !== '' ? $n : 'Tunnus';
}

/** Jäsenluokat: array(id => array('nimi' => ..., 'summa' => ...)). Summa 0 = ei laskuteta. */
function jasrek_jasenlajit() {
    $l = jasrek_settings()['jasenlajit'];
    return is_array($l) ? $l : array();
}
function jasrek_laji_nimi($id) {
    $l = jasrek_jasenlajit();
    return ($id !== '' && isset($l[$id])) ? $l[$id]['nimi'] : '';
}
/**
 * Pienet ja isot kirjaimet myös ilman PHP:n mbstring-laajennusta (å, ä, ö muunnetaan silloin taulukolla).
 */
function jasrek_lower($s) {
    $s = (string) $s;
    if (function_exists('mb_strtolower')) return mb_strtolower($s, 'UTF-8');
    return strtr(strtolower($s), array('Å' => 'å', 'Ä' => 'ä', 'Ö' => 'ö', 'Ü' => 'ü', 'É' => 'é', 'Š' => 'š', 'Ž' => 'ž'));
}
function jasrek_upper($s) {
    $s = (string) $s;
    if (function_exists('mb_strtoupper')) return mb_strtoupper($s, 'UTF-8');
    return strtr(strtoupper($s), array('å' => 'Å', 'ä' => 'Ä', 'ö' => 'Ö', 'ü' => 'Ü', 'é' => 'É', 'š' => 'Š', 'ž' => 'Ž'));
}

/** Etsii jäsenluokan nimellä (kirjainkoolla ei väliä). */
function jasrek_laji_by_name($nimi) {
    $nimi = jasrek_lower(trim($nimi));
    if ($nimi === '') return '';
    foreach (jasrek_jasenlajit() as $id => $l) {
        $n = jasrek_lower($l['nimi']);
        if ($n === $nimi) return $id;
    }
    return null;
}
/** Jäsenen laskutettava summa: laskun hinta luokalle, muuten luokan nykyinen hinta, muuten oletussumma. */
function jasrek_member_price($m, $hinnat, $oletus) {
    $laji = isset($m->jasenlaji) ? $m->jasenlaji : '';
    if ($laji !== '' && is_array($hinnat) && isset($hinnat[$laji])) return (float) $hinnat[$laji];
    $lajit = jasrek_jasenlajit();
    if ($laji !== '' && isset($lajit[$laji])) return (float) $lajit[$laji]['summa'];
    return (float) $oletus;
}
/** Onko jäsenen luokan jäsenmaksu 0 € (esim. kunniajäsen)? */
function jasrek_is_maksuvapaa($m) {
    $lajit = jasrek_jasenlajit();
    $laji  = isset($m->jasenlaji) ? $m->jasenlaji : '';
    return $laji !== '' && isset($lajit[$laji]) && (float) $lajit[$laji]['summa'] <= 0;
}
function jasrek_laji_badge($laji_id) {
    $lajit = jasrek_jasenlajit();
    if ($laji_id === '' || !isset($lajit[$laji_id])) return '';
    $free = (float) $lajit[$laji_id]['summa'] <= 0;
    return '<span class="jasrek-badge ' . ($free ? 'jasrek-badge-purple' : 'jasrek-badge-laji') . '"' . ($free ? ' title="Ei jäsenmaksua"' : '') . '>'
        . esc_html($lajit[$laji_id]['nimi']) . '</span>';
}

/** Lisäkenttien tyypit. */
function jasrek_lisa_tyypit() {
    return array('teksti' => 'Teksti', 'valinta' => 'Valintaruutu', 'pvm' => 'Päivämäärä');
}
function jasrek_lisa_is_date($kentta) {
    return $kentta['tyyppi'] === 'pvm';
}

/**
 * Jäsenen syntymävuosi tai -päivä (vapaaehtoinen). Tallennetaan muodossa VVVV tai VVVV-KK-PP.
 * Hyväksyy esim. 1976, 12.3.1976 ja 1976-03-12. Virheellinen tai tuleva arvo palauttaa ''.
 */
function jasrek_syntynyt_parse($s) {
    $s = trim(sanitize_text_field((string) $s));
    if ($s === '') return '';
    if (preg_match('/^\d{4}$/', $s)) {
        return ((int) $s >= 1900 && (int) $s <= (int) current_time('Y')) ? $s : '';
    }
    $d = jasrek_parse_date_any($s);
    return ($d && (int) substr($d, 0, 4) >= 1900 && $d <= current_time('Y-m-d')) ? $d : '';
}
/** Syntymävuosi sellaisenaan tai syntymäpäivä muodossa p.k.vvvv. */
function jasrek_syntynyt_teksti($v) {
    $v = (string) $v;
    return preg_match('/^\d{4}$/', $v) ? $v : jasrek_date($v);
}
/** Asetuksen vuosilista (esim. "50, 60, 70") kokonaislukuina. */
function jasrek_vuosilista($s) {
    $out = array();
    foreach (preg_split('/[^\d]+/', (string) $s) as $v) if ($v !== '' && (int) $v > 0 && (int) $v < 200) $out[] = (int) $v;
    $out = array_values(array_unique($out));
    sort($out);
    return $out;
}

/**
 * Merkkipäivät annettuna vuonna: täytettävät vuodet (syntymävuosi tai -päivä) ja jäsenyysvuodet (liittymispäivä)
 * asetuksissa valituilla tasavuosilla. Palauttaa listan array(pvm, tyyppi 'ika'|'jasenyys', vuodet, jäsen), päivän mukaan.
 * Jos jäsenestä tiedetään vain syntymävuosi, pvm on '' ja merkkipäivä on listan lopussa.
 */
function jasrek_merkki_on() {
    return jasrek_settings()['merkki_kaytossa'] === '1';
}
function jasrek_merkkipaivat($year) {
    global $wpdb;
    if (!jasrek_merkki_on()) return array();
    $year = (int) $year;
    $s = jasrek_settings();
    $iat = jasrek_vuosilista($s['merkki_ika']);
    $jat = jasrek_vuosilista($s['merkki_jasen']);
    $out = array();
    $paiva = function ($ymd) use ($year) {
        $md = substr($ymd, 5, 5);
        if ($md === '02-29' && !checkdate(2, 29, $year)) $md = '02-28';
        return $year . '-' . $md;
    };
    foreach ($wpdb->get_results("SELECT * FROM {$wpdb->jasrek_jasenet} WHERE tila = 'aktiivinen'") as $m) {
        $syn = isset($m->syntynyt) ? (string) $m->syntynyt : '';
        if ($iat && preg_match('/^\d{4}(-\d{2}-\d{2})?$/', $syn)) {
            $ika = $year - (int) substr($syn, 0, 4);
            if (in_array($ika, $iat, true)) $out[] = array('pvm' => strlen($syn) === 10 ? $paiva($syn) : '', 'tyyppi' => 'ika', 'vuodet' => $ika, 'm' => $m);
        }
        if ($jat && $m->liittynyt && $m->liittynyt !== '0000-00-00') {
            $v = $year - (int) substr($m->liittynyt, 0, 4);
            if (in_array($v, $jat, true)) $out[] = array('pvm' => $paiva($m->liittynyt), 'tyyppi' => 'jasenyys', 'vuodet' => $v, 'm' => $m);
        }
    }
    $avain = function ($mp) use ($year) { return ($mp['pvm'] !== '' ? $mp['pvm'] : $year . '-99') . $mp['m']->sukunimi . ' ' . $mp['m']->etunimi; };
    usort($out, function ($a, $b) use ($avain) { return strcmp($avain($a), $avain($b)); });
    return $out;
}
/** Merkkipäivän päivä näytettäväksi; pelkästä syntymävuodesta "vuoden aikana". */
function jasrek_merkkipaiva_pvm($mp) {
    return $mp['pvm'] !== '' ? jasrek_date($mp['pvm']) : 'vuoden aikana';
}
function jasrek_merkkipaiva_teksti($mp) {
    return $mp['tyyppi'] === 'ika' ? $mp['vuodet'] . ' vuotta' : $mp['vuodet'] . ' vuotta jäsenenä';
}

/**
 * Omat lisäkentät: array(id => array('nimi' => ..., 'tyyppi' => 'teksti'|'valinta'|'pvm', 'lista' => '1'|'0')).
 * Päivämäärät tallennetaan muodossa VVVV-KK-PP.
 * Arvot tallennetaan jäsenelle JSON-muodossa (jasenet.lisakentat).
 */
function jasrek_lisakentat() {
    $l = jasrek_settings()['lisakentat'];
    return is_array($l) ? $l : array();
}
function jasrek_member_lisa($m) {
    $raw = is_object($m) && isset($m->lisakentat) ? $m->lisakentat : '';
    $d = $raw ? json_decode($raw, true) : array();
    return is_array($d) ? $d : array();
}
/** Lisäkentän arvo näytettäväksi: valintaruutu "✓"/"Kyllä", teksti sellaisenaan. */
function jasrek_lisa_value($kentta, $v, $check = '✓') {
    $v = (string) $v;
    if ($kentta['tyyppi'] === 'valinta') return $v !== '' ? $check : '';
    if (jasrek_lisa_is_date($kentta)) return jasrek_date($v);
    return $v;
}
/** Tulkitsee tuodun tai lomakkeen arvon lisäkentälle. */
function jasrek_lisa_parse($kentta, $v) {
    $v = trim((string) $v);
    if ($kentta['tyyppi'] === 'valinta') {
        $l = strtolower(remove_accents($v));
        return in_array($l, array('1', 'x', 'k', 'kylla', 'on', 'yes', 'true', 'y', '✓'), true) ? '1' : '';
    }
    if (jasrek_lisa_is_date($kentta)) {
        $d = function_exists('jasrek_parse_date_any') ? jasrek_parse_date_any(sanitize_text_field($v)) : jasrek_date_or_null($v);
        return $d ? $d : '';
    }
    $v = sanitize_text_field($v);
    return function_exists('mb_substr') ? mb_substr($v, 0, 200) : substr($v, 0, 200);
}

/** Saako jäsen laskun paperilla? Kyllä, jos hän on sitä pyytänyt tai häneltä puuttuu sähköposti. */
function jasrek_is_paperi($r) {
    $email = isset($r->to_email) ? $r->to_email : (isset($r->email) ? $r->email : '');
    return !empty($r->paperilasku) || (string) $email === '';
}

/** Asetuksissa määritelty postimaksu paperilaskuille (0 = ei postimaksua). */
function jasrek_postimaksu_oletus() {
    return max(0, round((float) jasrek_settings()['postimaksu'], 2));
}
/** Laskurivin postimaksu ja jäsenmaksu ilman postimaksua. */
function jasrek_rivi_postimaksu($r) {
    return isset($r->postimaksu) ? (float) $r->postimaksu : 0.0;
}
function jasrek_rivi_jasenmaksu($r) {
    return (float) $r->summa - jasrek_rivi_postimaksu($r);
}

/** Laskurivin eräpäivä: jäsenkohtainen (myöhemmin lisätty jäsen), muuten laskun eräpäivä. */
function jasrek_rivi_era($lasku, $r) {
    return (!empty($r->erapaiva) && $r->erapaiva !== '0000-00-00') ? $r->erapaiva : $lasku->erapaiva;
}
/** Eräpäivä laskulle myöhemmin lisättävälle: laskun eräpäivä, tai 3 viikkoa tästä päivästä, jos se on jo lähellä tai mennyt. */
function jasrek_late_era($lasku) {
    $today = current_time('Y-m-d');
    $min = gmdate('Y-m-d', strtotime($today . ' +14 days'));
    return $lasku->erapaiva >= $min ? $lasku->erapaiva : gmdate('Y-m-d', strtotime($today . ' +21 days'));
}

/** Jäsenyyden tila. Maksuvelvollisuus määräytyy jäsenluokasta (0 € = ei laskuteta). */
function jasrek_tilat() {
    return array(
        'aktiivinen' => 'Voimassa',
        'eronnut'    => 'Eronnut',
    );
}
/** Tilan nimi näytettäväksi. 'hakemus' voi olla jäänyt 4.7.0-testiversiosta; se näkyy Eronneet-välilehdellä. */
function jasrek_tila_nimi($tila) {
    $t = jasrek_tilat();
    if (isset($t[$tila])) return $t[$tila];
    return $tila === 'hakemus' ? 'Liittymishakemus' : (string) $tila;
}

function jasrek_tila_badge($tila) {
    if ($tila === 'eronnut') return '<span class="jasrek-badge jasrek-badge-muted">eronnut</span>';
    if ($tila === 'hakemus') return '<span class="jasrek-badge jasrek-badge-orange">liittymishakemus</span>';
    return '';
}
/** SQL-ehto: kaikki voimassa olevat jäsenet. */
function jasrek_sql_jasenet() {
    return "tila = 'aktiivinen'";
}

/** Tarkistaa oikeudet ja nonce-tunnisteen (CSRF-suojaus). */
function jasrek_check($nonce_action) {
    if (!current_user_can('manage_options')) {
        wp_die('Sinulla ei ole oikeuksia tähän toimintoon.', 403);
    }
    check_admin_referer($nonce_action);
}

/**
 * Tietojen versio: vaihtuu, kun tiedot palautetaan varmuuskopiosta. Keskeneräiset toiminnot
 * (maksujen ja jäsenten tuonnin esikatselu, laskujen ja tiedotteiden lähetys) sidotaan tähän,
 * jotta ne eivät jatku palautuksen jälkeen vanhoilla rivitunnisteilla.
 */
function jasrek_data_gen() {
    return (string) get_option('jasrek_data_gen', '0');
}

function jasrek_redirect($page, $args = array()) {
    $args = array_filter($args, function ($v) { return $v !== null && $v !== ''; });
    wp_safe_redirect(add_query_arg($args, admin_url('admin.php?page=' . $page)));
    exit;
}

/*
 * Pyyntöparametrien luku. Lomakkeiden käsittelijät kutsuvat aina ensin jasrek_check() (oikeudet + nonce),
 * joten POST-arvot luetaan vasta tarkistuksen jälkeen. GET-parametrit ovat näkymän valintoja (sivu, välilehti,
 * suodatus) eivätkä muuta tietoja, joten niihin ei tarvita noncea.
 */

/** POST-arvo (merkkijono). Kutsuja sanitoi arvon käyttötarkoituksen mukaan (esim. sanitize_email, sanitize_textarea_field). */
function jasrek_post($key, $default = '') {
    // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce checked by jasrek_check() in every handler; sanitized by the caller.
    return isset($_POST[$key]) ? wp_unslash($_POST[$key]) : $default;
}

/** POST-taulukko, jonka jokainen arvo on siistitty tekstiksi (avaimet säilyvät). */
function jasrek_post_array($key) {
    $raw = isset($_POST[$key]) ? wp_unslash($_POST[$key]) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce checked by jasrek_check() in every handler; values sanitized below.
    if (!is_array($raw)) return array();
    $out = array();
    foreach ($raw as $k => $v) {
        if (is_array($v)) continue;
        $out[is_int($k) ? $k : sanitize_key($k)] = sanitize_text_field($v);
    }
    return $out;
}

/** POST-taulukon positiiviset kokonaisluvut (esim. valitut rivit). */
function jasrek_post_ids($key) {
    return array_values(array_filter(array_map('absint', jasrek_post_array($key))));
}

/** GET-parametri tekstinä (näkymän valinta, ei muuta tietoja). */
function jasrek_get($key, $default = '') {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view parameter.
    return isset($_GET[$key]) ? sanitize_text_field(wp_unslash($_GET[$key])) : $default;
}
function jasrek_get_int($key, $default = 0) {
    $v = jasrek_get($key, '');
    return $v === '' ? $default : intval($v);
}

/** Kokonaisluku GET- tai POST-pyynnöstä. Käytetään kohteen tunnisteeseen, josta nonce-tarkistuksen nimi muodostetaan. */
function jasrek_request_int($key) {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the id is part of the nonce action and is verified immediately by jasrek_check().
    return isset($_REQUEST[$key]) ? intval($_REQUEST[$key]) : 0;
}
function jasrek_request_flag($key) {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified by jasrek_check() in the handler.
    return !empty($_REQUEST[$key]);
}

/** Ladattu tiedosto (vain tarkistettu, palvelimen väliaikainen tiedosto). */
function jasrek_uploaded_file($key) {
    if (empty($_FILES[$key]) || !is_array($_FILES[$key])) return null; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce checked by jasrek_check() before upload handling.
    $f = $_FILES[$key]; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated below; only tmp_name, size, error and a sanitized name are used.
    if (!isset($f['error'], $f['tmp_name'], $f['size']) || (int) $f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) return null;
    return array('tmp_name' => $f['tmp_name'], 'size' => (int) $f['size'], 'name' => sanitize_file_name(isset($f['name']) ? $f['name'] : 'tiedosto'));
}

/* ------------------------------------------------------------------
 * Ilmoitukset (Post/Redirect/Get -mallilla, ei uudelleenlähetystä F5:llä)
 * ------------------------------------------------------------------ */

function jasrek_notice() {
    if (empty(jasrek_get('jasrek_msg'))) return;
    $n = jasrek_get_int('jasrek_n', 0);
    $msgs = array(
        'member_added'    => array('success', 'Jäsen lisätty.'),
        'member_updated'  => array('success', 'Jäsenen tiedot päivitetty.'),
        'member_deleted'  => array('success', 'Jäsen poistettu.'),
        'name_required'   => array('error',   'Etunimi ja sukunimi ovat pakollisia.'),
        'email_invalid'   => array('warning', 'Tiedot tallennettiin, mutta sähköpostiosoite oli virheellinen eikä sitä tallennettu.'),
        'lasku_invalid'   => array('error',   'Tarkista laskun nimi, summa ja eräpäivä.'),
        'no_recipients'   => array('error',   'Laskulle ei valittu yhtään jäsentä.'),
        'lasku_created_skip' => array('success', sprintf('Lasku luotu %d jäsenelle. %d jäsentä ohitettiin, koska heidän jäsenmaksunsa on 0 € (esim. kunniajäsenet).', $n, jasrek_get_int('jasrek_s', 0))),
        'lasku_created'   => array('success', sprintf('Lasku luotu %d jäsenelle. Tarkista tiedot, lähetä halutessasi testilasku itsellesi ja lähetä sitten laskut.', $n)),
        'lasku_updated'   => array('success', 'Laskun tiedot päivitetty.'),
        'lasku_deleted'   => array('success', 'Lasku poistettu.'),
        'rows_added'      => array('success', sprintf('Laskulle lisättiin %d jäsentä.', $n)),
        'paid_updated'    => array('success', sprintf('Maksutiedot päivitetty (%d kpl).', $n)),
        'row_deleted'     => array('success', 'Jäsen poistettu tältä laskulta.'),
        'sent_one'        => array('success', 'Lasku lähetetty.'),
        'send_failed'     => array('error',   'Lähetys epäonnistui. Tarkista sähköpostiosoite ja sivuston sähköpostiasetukset.'),
        'test_sent'       => array('success', 'Testiviesti lähetetty osoitteeseen ' . sanitize_email(jasrek_get('jasrek_to')) . '.'),
        'test_invalid'    => array('error',   'Anna kelvollinen sähköpostiosoite testiviestille.'),
        'settings_saved'  => array('success', 'Asetukset tallennettu.'),
        'iban_invalid'    => array('warning', 'Asetukset tallennettu, mutta IBAN-tilinumero ei näytä oikealta. Tarkista se.'),
        'mail_invalid'    => array('error',   'Otsikko ja viesti ovat pakollisia.'),
        'nothing_selected'=> array('warning', 'Et valinnut yhtään riviä.'),
        'member_invoiced' => array(empty(jasrek_get('jasrek_e')) ? 'success' : 'warning', sprintf('Jäsen tallennettu ja lisätty %d laskulle.%s%s', $n,
            !empty(jasrek_get('jasrek_s')) ? ' Lasku lähetetty sähköpostilla.' : '',
            !empty(jasrek_get('jasrek_e')) ? ' Laskun lähetys epäonnistui – lähetä se laskun sivulta uudelleen.' : '')),
        'feedback_sent'   => array('success', 'Kiitos palautteesta! Viesti lähetettiin kehittäjälle.'),
        'feedback_empty'  => array('error',   'Kirjoita viesti ennen lähettämistä.'),
        'feedback_wait'   => array('warning', 'Odota hetki ennen seuraavan palautteen lähettämistä.'),
        'members_bulk'    => array('success', sprintf('Muutettu %d jäsenen tiedot.', $n)),
        'csv_empty'       => array('error',   'Tiedostosta ei löytynyt rivejä. Tarkista, että ensimmäisellä rivillä on otsikot ja sen alla jäsenten tiedot.'),
        'members_imported'=> array('success', sprintf('Tuonti valmis: %d lisätty, %d päivitetty, %d ohitettu (jo rekisterissä), %d ohitettu puuttuvan nimen vuoksi.%s',
            $n, jasrek_get_int('jasrek_u', 0), jasrek_get_int('jasrek_s', 0), jasrek_get_int('jasrek_e', 0),
            !empty(jasrek_get('jasrek_l')) ? ' ' . jasrek_get_int('jasrek_l') . ' riviltä jäsenluokkaa ei tunnistettu – lisää luokka asetuksiin ja valitse se jäsenelle.' : '')),
        'upload_failed'   => array('error',   'Tiedoston lataus epäonnistui. Tarkista, että valitsit tiedoston (enintään 5 Mt).'),
        'no_payments'     => array('error',   'Tiedostosta ei löytynyt maksuja. Tarkista, että se on viitesiirtoaineisto (KTL), camt.054/053-XML tai tiliote CSV-muodossa, jossa on viite- ja summasarake.'),
        'payments_imported' => array('success', sprintf('%d laskua merkitty maksetuksi pankin tiedostosta.', $n)),
        'no_mail_recipients' => array('warning', 'Valitulla vastaanottajajoukolla ei ole yhtään sähköpostiosoitetta.'),
        'due_before_date' => array('error',   'Eräpäivä ei voi olla ennen laskun päiväystä.'),
        'member_duplicate'=> array('warning', 'Jäsen tallennettiin, mutta rekisterissä on jo toinen jäsen samalla nimellä tai sähköpostiosoitteella. Tarkista, ettei kyseessä ole tuplakirjaus.'),
        'postage_updated' => array('success', sprintf('Postimaksu päivitetty %d laskuun.', $n)),
        'paper_marked'    => array('success', sprintf('%d paperilaskua merkitty postitetuksi.', $n)),
        'paper_reminded'  => array('success', sprintf('%d paperimuistutusta merkitty postitetuksi.', $n)),
        'backup_invalid'  => array('error',   'Varmuuskopiota ei voitu käyttää: ' . (string) get_transient('jasrek_backup_err_' . get_current_user_id())),
        'backup_upload_failed' => array('error', 'Tiedoston lataus epäonnistui. Tarkista, että valitsit tiedoston ja että se on enintään ' . size_format(wp_max_upload_size()) . '. Suuren rekisterin varmuuskopiosta voi jättää muutoslokin pois.'),
        'data_restored'   => array('warning', 'Tiedot on palautettu varmuuskopiosta tämän toiminnon aloittamisen jälkeen. Aloita se uudelleen.'),
        'backup_expired'  => array('warning', 'Varmuuskopio on vanhentunut tai sitä ei löytynyt. Lue tiedosto uudelleen.'),
        'backup_confirm'  => array('warning', 'Vahvista ensin, että nykyiset tiedot korvataan.'),
        'backup_restored' => array('success', sprintf('Varmuuskopio palautettu: %d jäsentä. Palautusta edeltänyt tilanne on tallessa, ja palautuksen voi perua alla.', $n)),
        'backup_undone'   => array('success', 'Palautus peruttu: palautusta edeltänyt tilanne on palautettu.'),
        'backup_undo_deleted' => array('success', 'Tallessa ollut palautusta edeltänyt tilanne poistettu.'),
        'rows_failed'     => array('warning', sprintf('Lasku luotiin, mutta %d jäsenelle laskua ei voitu muodostaa (viitenumero oli jo käytössä). Ota yhteyttä ylläpitoon.', $n)),
    );
    $key = sanitize_key(jasrek_get('jasrek_msg'));
    if (!isset($msgs[$key])) return;
    list($type, $text) = $msgs[$key];
    printf('<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr($type), esc_html($text));
}

/* ------------------------------------------------------------------
 * Muotoilut
 * ------------------------------------------------------------------ */

function jasrek_money($amount) {
    return number_format((float) $amount, 2, ',', "\xC2\xA0") . "\xC2\xA0€";
}

/** Summa tai summaväli (jos jäsenlajeilla on eri hinnat). */
function jasrek_summa_range($min, $max, $fallback) {
    if ($min === null || $max === null) return jasrek_money($fallback);
    if (abs((float) $min - (float) $max) < 0.005) return jasrek_money($min);
    return number_format((float) $min, 2, ',', "\xC2\xA0") . '–' . jasrek_money($max);
}

/**
 * Korjaa taulukkolaskennassa rikkoutuneen puhelinnumeron: 401234567 → 0401234567,
 * 358401234567 → +358401234567. Muihin muotoihin ei kosketa.
 */
function jasrek_normalize_phone($p) {
    $p = trim((string) $p);
    if ($p === '' || !preg_match('/^[\d\s\-]+$/', $p)) return $p;
    $digits = preg_replace('/\D/', '', $p);
    if (strpos($digits, '358') === 0 && strlen($digits) >= 11) return '+' . $digits;
    if ($digits[0] !== '0' && strlen($digits) >= 6 && strlen($digits) <= 10) return '0' . $p;
    return $p;
}

function jasrek_parse_money($s) {
    $s = str_replace(array(' ', "\xC2\xA0", '€'), '', (string) $s);
    $s = str_replace(',', '.', $s);
    return is_numeric($s) ? round((float) $s, 2) : 0.0;
}

function jasrek_date($ymd) {
    if (!$ymd || $ymd === '0000-00-00') return '';
    $ts = strtotime($ymd);
    return $ts ? gmdate('j.n.Y', $ts) : '';
}

function jasrek_date_or_null($s) {
    $s = trim(sanitize_text_field(wp_unslash((string) $s)));
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
        return $s;
    }
    return null;
}

/* ------------------------------------------------------------------
 * Pankkitiedot: IBAN, kotimainen viitenumero, virtuaaliviivakoodi
 * ------------------------------------------------------------------ */

function jasrek_iban_clean($iban) {
    return strtoupper(preg_replace('/\s+/', '', (string) $iban));
}

function jasrek_iban_valid($iban) {
    $iban = jasrek_iban_clean($iban);
    if (!preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{10,30}$/', $iban)) return false;
    $r = substr($iban, 4) . substr($iban, 0, 4);
    $mod = 0;
    foreach (str_split($r) as $c) {
        $part = ctype_alpha($c) ? (string) (ord($c) - 55) : $c;
        foreach (str_split($part) as $d) {
            $mod = ($mod * 10 + (int) $d) % 97;
        }
    }
    return $mod === 1;
}

function jasrek_iban_format($iban) {
    return trim(chunk_split(jasrek_iban_clean($iban), 4, ' '));
}

/** Suomalaisen viitenumeron tarkiste (painot 7-3-1 oikealta vasemmalle). */
function jasrek_viite_tarkiste($base) {
    $weights = array(7, 3, 1);
    $sum = 0;
    $rev = strrev($base);
    $len = strlen($rev);
    for ($i = 0; $i < $len; $i++) {
        $sum += (int) $rev[$i] * $weights[$i % 3];
    }
    return (10 - $sum % 10) % 10;
}

/**
 * Yksilöllinen viite: laskun numero + jäsenen ID (4 numeroa) + tarkiste.
 * Esim. lasku 3, jäsen 17 -> 3 0017 + tarkiste. Viitteestä näkee suoraan kenen maksu on kyseessä.
 */
function jasrek_viite($lasku_id, $jasen_id) {
    $base = sprintf('%d%04d', (int) $lasku_id, (int) $jasen_id);
    return $base . jasrek_viite_tarkiste($base);
}

/**
 * Yksilöllinen viite laskuriville. Jos perusmuotoinen viite on jo käytössä (mahdollista, kun
 * jäsenten ID:t ylittävät 9999), lisätään perään juokseva numero ennen tarkistetta.
 */
function jasrek_unique_viite($lasku_id, $jasen_id) {
    global $wpdb;
    $t = jasrek_t('rivit');
    $base = sprintf('%d%04d', (int) $lasku_id, (int) $jasen_id);
    for ($k = 0; $k < 1000; $k++) {
        $b = $k === 0 ? $base : $base . $k;
        $v = $b . jasrek_viite_tarkiste($b);
        if (!$wpdb->get_var($wpdb->prepare("SELECT 1 FROM {$wpdb->jasrek_rivit} WHERE viite = %s", $v))) return $v;
    }
    return $base . jasrek_viite_tarkiste($base);
}

/** Viite viiden numeron ryhmiin oikealta, kuten pankit sen esittävät. */
function jasrek_viite_format($viite) {
    $v = preg_replace('/\D/', '', (string) $viite);
    return trim(strrev(chunk_split(strrev($v), 5, ' ')));
}

/**
 * Pankkiviivakoodin versio 4 (kotimainen viite) virtuaaliviivakoodina.
 * Jäsen voi liittää tämän pankkisovellukseen, jolloin tilinumero, summa, viite ja eräpäivä täyttyvät itsestään.
 * Toimii vain suomalaisella IBANilla.
 */
function jasrek_virtuaaliviivakoodi($iban, $summa, $viite, $erapaiva) {
    $iban = jasrek_iban_clean($iban);
    if (!preg_match('/^FI(\d{16})$/', $iban, $m)) return '';
    $cents = (int) round((float) $summa * 100);
    if ($cents <= 0 || $cents > 99999999) return '';
    $ref = preg_replace('/\D/', '', (string) $viite);
    if ($ref === '' || strlen($ref) > 20) return '';
    $ts  = $erapaiva ? strtotime($erapaiva) : false;
    $due = $ts ? gmdate('ymd', $ts) : '000000';
    return '4' . $m[1]
        . sprintf('%06d', intdiv($cents, 100))
        . sprintf('%02d', $cents % 100)
        . '000'
        . str_pad($ref, 20, '0', STR_PAD_LEFT)
        . $due;
}

/* ------------------------------------------------------------------
 * Sähköposti
 * ------------------------------------------------------------------ */

$GLOBALS['jasrek_mail_error'] = '';
add_action('wp_mail_failed', function ($error) {
    $GLOBALS['jasrek_mail_error'] = $error->get_error_message();
});

function jasrek_mail($to, $subject, $html, $images = array(), $attachments = array()) {
    $s = jasrek_settings();
    $headers = array('Content-Type: text/html; charset=UTF-8');
    if ($s['lahettaja_email'] && is_email($s['lahettaja_email'])) {
        $name = str_replace(array("\r", "\n", '"', '<', '>'), '', $s['lahettaja_nimi']);
        $headers[] = 'From: ' . ($name !== '' ? $name . ' ' : '') . '<' . $s['lahettaja_email'] . '>';
    }
    if ($s['vastaus_email'] && is_email($s['vastaus_email'])) {
        $headers[] = 'Reply-To: ' . $s['vastaus_email'];
    }
    $GLOBALS['jasrek_mail_error'] = '';
    // Lisätään viestiin myös pelkkä tekstiversio (multipart/alternative). Moni roskapostisuodatin
    // suhtautuu epäluuloisesti pelkkään HTML-viestiin.
    $alt = function ($phpmailer) use ($html, $images, $attachments) {
        $text = preg_replace('#<(br|/p|/tr|/div|/h\d)[^>]*>#i', "\n", $html);
        $text = preg_replace('#</t[dh]>\s*<t[dh][^>]*>#i', ': ', $text);
        $text = html_entity_decode(wp_strip_all_tags(preg_replace('#<head.*?</head>#is', '', $text)), ENT_QUOTES, 'UTF-8');
        $text = preg_replace("/[ \t]+/", ' ', $text);
        $phpmailer->AltBody = trim(preg_replace("/\s*\n\s*(\n\s*)+/", "\n\n", $text));
        // Kuvat upotetaan viestiin (cid:), jolloin ne näkyvät ilman ulkoisia latauksia.
        foreach ($images as $cid => $png) {
            $phpmailer->addStringEmbeddedImage($png, $cid, $cid . '.png', 'base64', 'image/png');
        }
        // Liitteet muistista (esim. PDF-lasku) – ei väliaikaistiedostoja
        foreach ($attachments as $name => $data) {
            $phpmailer->addStringAttachment($data, $name, 'base64', 'application/pdf');
        }
    };
    add_action('phpmailer_init', $alt);
    $ok = (bool) wp_mail($to, $subject, $html, $headers);
    remove_action('phpmailer_init', $alt);
    return $ok;
}

/**
 * Klikattavat paikkamerkit tekstikentän alle: painike lisää merkin kohdistimen kohdalle.
 * $tyyppi: 'lasku' (laskut ja muistutukset) tai 'tiedote'.
 */
function jasrek_placeholders($target_id, $tyyppi = 'lasku', $lisa = '') {
    $selitteet = array(
        'etunimi' => 'Etunimi', 'sukunimi' => 'Sukunimi', 'nimi' => 'Etu- ja sukunimi',
        'tunnus' => jasrek_tunnus_nimi(), 'jasenluokka' => 'Jäsenluokka, esim. Nuorisojäsen',
        'laskun_nimi' => 'Laskun nimi, esim. Jäsenmaksu 2027', 'summa' => 'Jäsenmaksu, esim. 25,00 € (ilman paperilaskun postimaksua)',
        'maksettava' => 'Maksettava yhteensä (paperilaskussa jäsenmaksu + postimaksu)', 'postimaksu' => 'Postimaksu (0,00 €, jos lasku tulee sähköpostilla)',
        'viite' => 'Viitenumero', 'erapaiva' => 'Eräpäivä', 'yhdistys' => 'Yhdistyksen nimi',
    );
    $merkit = $tyyppi === 'tiedote'
        ? array('etunimi', 'sukunimi', 'nimi', 'tunnus', 'jasenluokka', 'yhdistys')
        : array('etunimi', 'sukunimi', 'tunnus', 'jasenluokka', 'laskun_nimi', 'summa', 'maksettava', 'viite', 'erapaiva', 'yhdistys');
    if (!jasrek_tunnus_on()) $merkit = array_values(array_diff($merkit, array('tunnus')));
    $out = '<span class="description jasrek-phs">Paikkamerkit – lisää klikkaamalla: ';
    foreach ($merkit as $k) {
        $out .= '<button type="button" class="jasrek-ph" data-target="#' . esc_attr($target_id) . '" data-ph="{' . esc_attr($k) . '}" title="' . esc_attr($selitteet[$k]) . '">{' . esc_html($k) . '}</button> ';
    }
    return $out . ($lisa !== '' ? esc_html($lisa) : '') . '</span>';
}

/** Korvaa {paikkamerkit} ja muuttaa tekstin turvalliseksi HTML:ksi. */
function jasrek_fill($text, $vars) {
    $out = esc_html($text);
    foreach ($vars as $k => $v) {
        $out = str_replace('{' . $k . '}', esc_html((string) $v), $out);
    }
    return nl2br($out);
}

/* ------------------------------------------------------------------
 * CSV-vienti (Excel-yhteensopiva: UTF-8 BOM ja puolipiste)
 * ------------------------------------------------------------------ */

function jasrek_csv_output($filename, $header, $rows) {
    nocache_headers();
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    // Kirjoitetaan suoraan selaimelle lähtevään virtaan (php://output), ei tiedostojärjestelmään.
    $out = fopen('php://output', 'w'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- php://output stream for a CSV download, not a file.
    fwrite($out, "\xEF\xBB\xBF"); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- UTF-8 BOM to the output stream.
    fputcsv($out, $header, ';');
    foreach ($rows as $row) {
        $row = array_map(function ($v) {
            $v = (string) $v;
            // Puhelinnumerot ja muut etunollalliset numerot tekstinä, jottei Excel pudota nollaa
            // (0401234567 → 401234567) tai muuta +358-numeroa luvuksi. Tuonti tunnistaa muodon.
            if (preg_match('/^(0[\d ]*\d|\+\d[\d ]{6,})$/', $v)) return '="' . $v . '"';
            // Esim. avaimen numero 8.12 – Excel tulkitsisi sen päivämääräksi 8.12.
            if (preg_match('/^\d{1,2}\.\d{1,2}$/', $v)) return '="' . $v . '"';
            // Estetään kaavojen suoritus taulukkolaskennassa
            return ($v !== '' && in_array($v[0], array('=', '@', '+', '-', "\t", "\r"), true)) ? "'" . $v : $v;
        }, $row);
        fputcsv($out, $row, ';');
    }
    fclose($out); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- output stream.
    exit;
}

/** Tilisiirtolomakkeen Saaja-rivit: yhdistyksen nimi ja virallinen osoite (enintään 3 riviä, ylimenevät yhdistetään viimeiselle). */
function jasrek_saaja_rivit($s = null) {
    if ($s === null) $s = jasrek_settings();
    $osa = array_values(array_filter(array_map('trim', preg_split('/\s*\n\s*/', (string) $s['osoite'])), 'strlen'));
    $rivit = array_merge(array((string) $s['yhdistys']), $osa);
    if (count($rivit) > 3) $rivit = array_merge(array_slice($rivit, 0, 2), array(implode(', ', array_slice($rivit, 2))));
    return $rivit;
}

