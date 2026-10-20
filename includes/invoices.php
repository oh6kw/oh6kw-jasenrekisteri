<?php
if (!defined('ABSPATH')) exit;
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The plugin's own custom tables (members, invoices, audit log). The data is admin-only and changes on every write, so object caching is not used.

/* ------------------------------------------------------------------
 * Tietokantaapurit
 * ------------------------------------------------------------------ */

function jasrek_get_lasku($id) {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->jasrek_laskut} WHERE id = %d", $id));
}

/** Laskurivit liitettynä jäsenen nykyisiin tietoihin. to_email = nykyinen osoite, tai laskulle tallennettu. */
function jasrek_rivi_select_sql() {
    // Jäsenluokka laskutushetken mukaan (vanhoilla riveillä nykyinen luokka)
    global $wpdb;
    return "SELECT r.*, m.etunimi, m.sukunimi, m.tunnus, m.tila, m.osoite, COALESCE(m.paperilasku, 0) AS paperilasku,
            COALESCE(NULLIF(r.jasenlaji, ''), m.jasenlaji, '') AS jasenlaji, COALESCE(NULLIF(m.email, ''), r.email) AS to_email
            FROM {$wpdb->jasrek_rivit} r LEFT JOIN {$wpdb->jasrek_jasenet} m ON m.id = r.jasen_id ";
}

/** Paikkamerkit IN-listaan, esim. 3 kokonaislukua -> "%d,%d,%d". */
function jasrek_in_placeholders($values, $type = '%d') {
    return implode(',', array_fill(0, max(1, count($values)), $type));
}

/**
 * Laskurivit jäsenen tietoineen. $where on kutsujan SQL-literaali paikkamerkein (%d, %s),
 * ja kaikki arvot välitetään $args-taulukossa prepare()-kutsulle.
 */
function jasrek_get_rivit($where, $args = array(), $single = false) {
    global $wpdb;
    $sql = jasrek_rivi_select_sql() . $where;
    // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- static SELECT + literal WHERE with placeholders; values are passed through prepare().
    $query = $args ? $wpdb->prepare($sql, $args) : $sql;
    $result = $single ? $wpdb->get_row($query) : $wpdb->get_results($query);
    // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    return $result;
}

/** Laskurivien määrä samalla WHERE-ehdolla kuin jasrek_get_rivit(). */
function jasrek_count_rivit($where, $args = array()) {
    global $wpdb;
    $sql = "SELECT COUNT(*) FROM {$wpdb->jasrek_rivit} r LEFT JOIN {$wpdb->jasrek_jasenet} m ON m.id = r.jasen_id " . $where;
    // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- literal WHERE with placeholders; values are passed through prepare().
    $count = (int) $wpdb->get_var($args ? $wpdb->prepare($sql, $args) : $sql);
    // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    return $count;
}

function jasrek_get_rivi($id) {
    global $wpdb;
    return jasrek_get_rivit('WHERE r.id = %d', array($id), true);
}

/** Hinnat laskulta: array(laji_id => summa). */
function jasrek_lasku_hinnat($lasku) {
    $h = !empty($lasku->hinnat) ? json_decode($lasku->hinnat, true) : array();
    return is_array($h) ? $h : array();
}

/**
 * Luo laskurivit. Summa määräytyy jäsenlajin mukaan ($hinnat), muuten $summa.
 * Jäsenet, joiden summa on 0, ohitetaan ($skipped).
 */
function jasrek_add_rows($lasku_id, $members, $summa, $hinnat = array(), &$skipped = 0, $erapaiva = null, $postimaksu = 0) {
    global $wpdb;
    $n = 0;
    $skipped = 0;
    foreach ($members as $m) {
        $price = jasrek_member_price($m, $hinnat, $summa);
        if ($price <= 0) { $skipped++; continue; }
        // Postimaksu vain paperilaskun saajille
        $pm = ($postimaksu > 0 && jasrek_is_paperi($m)) ? (float) $postimaksu : 0;
        $ok = $wpdb->insert(jasrek_t('rivit'), array(
            'lasku_id' => $lasku_id,
            'jasen_id' => $m->id,
            'nimi'     => trim($m->etunimi . ' ' . $m->sukunimi),
            'email'    => (string) $m->email,
            'viite'    => jasrek_unique_viite($lasku_id, $m->id),
            'summa'    => $price + $pm,
            'postimaksu' => $pm,
            'jasenlaji'=> (string) $m->jasenlaji,
            'erapaiva' => $erapaiva,
        ));
        if ($ok) $n++;
    }
    return $n;
}

/**
 * Lisää yksittäisen jäsenen jo tehdylle laskulle (esim. kauden jälkeen liittynyt).
 * Palauttaa uuden laskurivin id:n, tai 0 jos jäsen on jo laskulla.
 */
function jasrek_add_member_to_lasku($lasku, $m, $summa, $erapaiva = null) {
    global $wpdb;
    $t = jasrek_t('rivit');
    $exists = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->jasrek_rivit} WHERE lasku_id = %d AND jasen_id = %d", $lasku->id, $m->id));
    if ($exists) return 0;
    $pm = (!empty($lasku->postimaksu) && (float) $lasku->postimaksu > 0 && jasrek_is_paperi($m)) ? (float) $lasku->postimaksu : 0;
    $ok = $wpdb->insert($t, array(
        'lasku_id' => $lasku->id,
        'jasen_id' => $m->id,
        'nimi'     => trim($m->etunimi . ' ' . $m->sukunimi),
        'email'    => (string) $m->email,
        'viite'    => jasrek_unique_viite($lasku->id, $m->id),
        'summa'    => $summa + $pm,
        'postimaksu' => $pm,
        'jasenlaji'=> (string) $m->jasenlaji,
        'erapaiva' => ($erapaiva && $erapaiva !== $lasku->erapaiva) ? $erapaiva : null,
    ));
    return $ok ? (int) $wpdb->insert_id : 0;
}

/** Laskut, joille jäsenen voi vielä lisätä (uusimmat ensin). */
function jasrek_laskut_ilman_jasenta($jasen_id, $limit = 3) {
    global $wpdb;
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->jasrek_laskut} WHERE id NOT IN (SELECT lasku_id FROM {$wpdb->jasrek_rivit} WHERE jasen_id = %d) ORDER BY id DESC LIMIT %d",
        (int) $jasen_id, (int) $limit
    ));
}

/**
 * Lisää tai poistaa postimaksun maksamattomilta laskuriveiltä. Lisättäessä käytetään laskun postimaksua,
 * tai asetusten postimaksua, jos laskulla ei ole omaa. Palauttaa muutettujen rivien määrän.
 */
function jasrek_set_postimaksu($lasku, $rows, $lisaa) {
    global $wpdb;
    $pm = (float) $lasku->postimaksu > 0 ? (float) $lasku->postimaksu : jasrek_postimaksu_oletus();
    $n = 0;
    foreach ($rows as $r) {
        if ($r->maksettu) continue;
        $old = jasrek_rivi_postimaksu($r);
        $new = $lisaa ? $pm : 0;
        if (abs($old - $new) < 0.005) continue;
        $wpdb->update(jasrek_t('rivit'), array('postimaksu' => $new, 'summa' => jasrek_rivi_jasenmaksu($r) + $new), array('id' => $r->id));
        jasrek_log_rivi($r, 'muokattu', ($new > 0 ? 'postimaksu lisätty ' . html_entity_decode(jasrek_money($new)) : 'postimaksu poistettu') . ', uusi summa ' . html_entity_decode(jasrek_money(jasrek_rivi_jasenmaksu($r) + $new)), $lasku);
        $n++;
    }
    return $n;
}

/** Kirjaa yksittäisen laskurivin tapahtuman jäsenen muutoshistoriaan. */
function jasrek_log_rivi($r, $toiminto, $lisa = '', $lasku = null) {
    if (!$lasku) $lasku = jasrek_get_lasku($r->lasku_id);
    $p = jasrek_rivi_person($r);
    jasrek_log('maksu', (int) $r->jasen_id, $toiminto, ($lasku ? $lasku->nimi : 'Lasku') . ': ' . $p['nimi'] . ', ' . html_entity_decode(jasrek_money($r->summa)) . ($lisa !== '' ? ' – ' . $lisa : ''));
}

/**
 * Lähettää yhden laskun. $test_to: lähetä testinä tähän osoitteeseen, ei kirjata mitään.
 */
function jasrek_send_rivi($lasku, $r, $reminder = false, $test_to = null) {
    global $wpdb;
    $s  = jasrek_settings();
    $to = $test_to ? $test_to : $r->to_email;
    $now = current_time('mysql');

    if (!$to || !is_email($to)) {
        if (!$test_to) {
            $wpdb->update(jasrek_t('rivit'), array('viim_yritys' => $now, 'virhe' => 'Ei kelvollista sähköpostiosoitetta'), array('id' => $r->id));
        }
        return false;
    }

    $subject = ($reminder ? 'Muistutus: ' : '') . $lasku->nimi . ($s['yhdistys'] !== '' ? ' – ' . $s['yhdistys'] : '');
    if ($test_to) $subject = '[TESTI] ' . $subject;

    $liitteet = array();
    if ($s['sposti_pdf'] === '1') {
        try {
            $liitteet[jasrek_invoice_pdf_name($lasku, $r, $reminder)] = jasrek_invoice_pdf($lasku, array($r), $reminder);
        } catch (Exception $e) {
            $liitteet = array(); // lasku lähtee silti ilman liitettä
        }
    }
    $ok = jasrek_mail($to, $subject, jasrek_invoice_html($lasku, $r, $reminder), jasrek_invoice_email_images($lasku, $r), $liitteet);
    if ($test_to) return $ok;

    $upd = array('viim_yritys' => $now);
    if ($ok) {
        if ($reminder) {
            $upd['muistutettu']  = $now;
            $upd['muistutuksia'] = (int) $r->muistutuksia + 1;
        }
        if ($reminder === false || empty($r->lahetetty)) $upd['lahetetty'] = $now;
        $upd['virhe'] = null;
    } else {
        $err = $GLOBALS['jasrek_mail_error'] ? $GLOBALS['jasrek_mail_error'] : 'Lähetys epäonnistui';
        $upd['virhe'] = function_exists('mb_substr') ? mb_substr($err, 0, 250) : substr($err, 0, 250);
    }
    $wpdb->update(jasrek_t('rivit'), $upd, array('id' => $r->id));
    return $ok;
}

/* ------------------------------------------------------------------
 * Käsittelijät
 * ------------------------------------------------------------------ */

add_action('admin_post_jasrek_create_lasku', 'jasrek_handle_create_lasku');
function jasrek_handle_create_lasku() {
    jasrek_check('jasrek_create_lasku');
    global $wpdb;

    $nimi     = sanitize_text_field(jasrek_post('nimi'));
    $summa    = jasrek_parse_money(jasrek_post('summa'));
    $erapaiva = jasrek_date_or_null(jasrek_post('erapaiva'));
    $laskupvm = jasrek_date_or_null(jasrek_post('laskupvm'));
    if (!$laskupvm) $laskupvm = current_time('Y-m-d');
    $viesti   = sanitize_textarea_field(jasrek_post('viesti'));
    $pm_raw = jasrek_post('postimaksu', null);
    $postimaksu = $pm_raw !== null ? min(999.99, max(0, jasrek_parse_money(sanitize_text_field($pm_raw)))) : jasrek_postimaksu_oletus();

    // Jäsenlajikohtaiset summat
    $hinnat = array();
    $hinta_post = jasrek_post_array('hinta');
    foreach (jasrek_jasenlajit() as $lid => $l) {
        $raw = isset($hinta_post[$lid]) ? $hinta_post[$lid] : '';
        $hinnat[$lid] = min(999999.99, max(0, jasrek_parse_money($raw)));
    }
    $max = $hinnat ? max(array_merge(array($summa), $hinnat)) : $summa;
    if ($nimi === '' || $max <= 0 || $summa < 0 || $summa > 999999.99 || !$erapaiva) {
        jasrek_redirect('jasrek-laskutus', array('view' => 'new', 'jasrek_msg' => 'lasku_invalid'));
    }
    if ($erapaiva < $laskupvm) {
        jasrek_redirect('jasrek-laskutus', array('view' => 'new', 'jasrek_msg' => 'due_before_date'));
    }

    $t = jasrek_t('jasenet');
    if (jasrek_post('vastaanottajat') === 'valitut') {
        $ids = jasrek_post_ids('jasenet');
        $members = $ids ? $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->jasrek_jasenet} WHERE id IN (" . implode(',', array_fill(0, count($ids), '%d')) . ')', $ids)) : array();
    } else {
        $members = $wpdb->get_results("SELECT * FROM {$wpdb->jasrek_jasenet} WHERE tila = 'aktiivinen'");
    }
    if (!$members) {
        jasrek_redirect('jasrek-laskutus', array('view' => 'new', 'jasrek_msg' => 'no_recipients'));
    }

    $wpdb->insert(jasrek_t('laskut'), array(
        'nimi' => $nimi, 'summa' => $summa, 'laskupvm' => $laskupvm,
        'erapaiva' => $erapaiva, 'viesti' => $viesti, 'luotu' => current_time('mysql'),
        'hinnat' => $hinnat ? wp_json_encode($hinnat) : null,
        'postimaksu' => $postimaksu,
        'auto_muistutus' => 1,
    ));
    $lasku_id = (int) $wpdb->insert_id;
    $skipped = 0;
    $n = jasrek_add_rows($lasku_id, $members, $summa, $hinnat, $skipped, null, $postimaksu);
    $failed = count($members) - $n - $skipped;
    if ($n === 0) {
        $wpdb->delete(jasrek_t('laskut'), array('id' => $lasku_id));
        jasrek_redirect('jasrek-laskutus', array('view' => 'new', 'jasrek_msg' => 'no_recipients'));
    }
    $hm = array();
    foreach ($hinnat as $lid => $h) $hm[] = array('Hinta: ' . jasrek_laji_nimi($lid), '', html_entity_decode(jasrek_money($h)));
    if (!$hinnat || $summa > 0) $hm[] = array('Summa' . ($hinnat ? ' (ilman jäsenluokkaa)' : ''), '', html_entity_decode(jasrek_money($summa)));
    if ($postimaksu > 0) $hm[] = array('Postimaksu paperilaskuihin', '', html_entity_decode(jasrek_money($postimaksu)));
    $hm[] = array('Eräpäivä', '', jasrek_date($erapaiva));
    jasrek_log('lasku', $lasku_id, 'lasku_luotu', $nimi . ': ' . $n . ' jäsentä' . ($skipped ? ', ' . $skipped . ' ohitettu (0 €)' : ''), $hm);
    if ($skipped > 0 && $failed <= 0) {
        jasrek_redirect('jasrek-laskutus', array('view' => 'lasku', 'id' => $lasku_id, 'jasrek_msg' => 'lasku_created_skip', 'jasrek_n' => $n, 'jasrek_s' => $skipped));
    }
    if ($failed > 0) {
        jasrek_redirect('jasrek-laskutus', array('view' => 'lasku', 'id' => $lasku_id, 'jasrek_msg' => 'rows_failed', 'jasrek_n' => $failed));
    }
    jasrek_redirect('jasrek-laskutus', array('view' => 'lasku', 'id' => $lasku_id, 'jasrek_msg' => 'lasku_created', 'jasrek_n' => $n));
}

add_action('admin_post_jasrek_update_lasku', 'jasrek_handle_update_lasku');
function jasrek_handle_update_lasku() {
    $id = intval(jasrek_post('id', 0));
    jasrek_check('jasrek_update_lasku_' . $id);
    global $wpdb;
    $nimi     = sanitize_text_field(jasrek_post('nimi'));
    $erapaiva = jasrek_date_or_null(jasrek_post('erapaiva'));
    if ($nimi === '' || !$erapaiva) {
        jasrek_redirect('jasrek-laskutus', array('view' => 'lasku', 'id' => $id, 'jasrek_msg' => 'lasku_invalid'));
    }
    $old = jasrek_get_lasku($id);
    if (!$old) jasrek_redirect('jasrek-laskutus');
    $upd = array(
        'nimi' => $nimi, 'erapaiva' => $erapaiva, 'viesti' => sanitize_textarea_field(jasrek_post('viesti')),
        'auto_muistutus' => jasrek_settings()['auto_muistutus'] === '1' ? (jasrek_post('auto_muistutus') === '1' ? 1 : 0) : (int) $old->auto_muistutus,
    );
    $wpdb->update(jasrek_t('laskut'), $upd, array('id' => $id));
    $snap = function ($l) {
        $l = (object) $l;
        return array('Nimi' => $l->nimi, 'Eräpäivä' => jasrek_date($l->erapaiva), 'Saateteksti' => (string) $l->viesti, 'Automaattiset muistutukset' => $l->auto_muistutus ? 'kyllä' : 'ei');
    };
    $diff = jasrek_log_diff($snap($old), $snap($upd));
    if ($diff) jasrek_log('lasku', $id, 'lasku_muokattu', $nimi, $diff);
    jasrek_redirect('jasrek-laskutus', array('view' => 'lasku', 'id' => $id, 'jasrek_msg' => 'lasku_updated'));
}

add_action('admin_post_jasrek_delete_lasku', 'jasrek_handle_delete_lasku');
function jasrek_handle_delete_lasku() {
    $id = jasrek_request_int('id');
    jasrek_check('jasrek_delete_lasku_' . $id);
    global $wpdb;
    $old = jasrek_get_lasku($id);
    if ($old) {
        $st = $wpdb->get_row($wpdb->prepare("SELECT COUNT(*) AS n, SUM(maksettu IS NOT NULL) AS mak, SUM(summa) AS yht FROM {$wpdb->jasrek_rivit} WHERE lasku_id = %d", $id));
        jasrek_log('lasku', $id, 'lasku_poistettu', sprintf('%s (%d laskua, %d maksettu, yhteensä %s)', $old->nimi, $st->n, $st->mak, html_entity_decode(jasrek_money($st->yht))));
    }
    $wpdb->delete(jasrek_t('rivit'), array('lasku_id' => $id));
    $wpdb->delete(jasrek_t('laskut'), array('id' => $id));
    jasrek_redirect('jasrek-laskutus', array('jasrek_msg' => 'lasku_deleted'));
}

add_action('admin_post_jasrek_add_missing', 'jasrek_handle_add_missing');
function jasrek_handle_add_missing() {
    $id = jasrek_request_int('id');
    jasrek_check('jasrek_add_missing_' . $id);
    global $wpdb;
    $lasku = jasrek_get_lasku($id);
    if (!$lasku) jasrek_redirect('jasrek-laskutus');
    $members = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->jasrek_jasenet} WHERE tila = 'aktiivinen' AND id NOT IN (SELECT jasen_id FROM {$wpdb->jasrek_rivit} WHERE lasku_id = %d)",
        $id
    ));
    $era = jasrek_late_era($lasku);
    $skipped = 0;
    $n = jasrek_add_rows($id, $members, $lasku->summa, jasrek_lasku_hinnat($lasku), $skipped, $era !== $lasku->erapaiva ? $era : null, (float) $lasku->postimaksu);
    if ($n) {
        $nimet = array();
        foreach ($members as $m) if (jasrek_member_price($m, jasrek_lasku_hinnat($lasku), $lasku->summa) > 0) $nimet[] = jasrek_log_member_name($m);
        jasrek_log('lasku', $id, 'jasenia_lisatty', $lasku->nimi . ': ' . $n . ' uutta jäsentä, eräpäivä ' . jasrek_date($era), array('nimet' => $nimet));
    }
    jasrek_redirect('jasrek-laskutus', array('view' => 'lasku', 'id' => $id, 'jasrek_msg' => 'rows_added', 'jasrek_n' => $n));
}

add_action('admin_post_jasrek_add_postage', 'jasrek_handle_add_postage');
function jasrek_handle_add_postage() {
    $id = jasrek_request_int('id');
    jasrek_check('jasrek_add_postage_' . $id);
    global $wpdb;
    $lasku = jasrek_get_lasku($id);
    if (!$lasku) jasrek_redirect('jasrek-laskutus');
    $rows = array_filter(jasrek_get_rivit('WHERE r.lasku_id = %d AND r.maksettu IS NULL AND r.lahetetty IS NULL', array($id)), 'jasrek_is_paperi');
    $n = jasrek_set_postimaksu($lasku, $rows, true);
    jasrek_redirect('jasrek-laskutus', array('view' => 'lasku', 'id' => $id, 'jasrek_msg' => 'postage_updated', 'jasrek_n' => $n));
}

add_action('admin_post_jasrek_toggle_paid', 'jasrek_handle_toggle_paid');
function jasrek_handle_toggle_paid() {
    $rid = jasrek_request_int('rivi');
    jasrek_check('jasrek_paid_' . $rid);
    global $wpdb;
    $r = jasrek_get_rivi($rid);
    if (!$r) jasrek_redirect('jasrek-laskutus');
    $wpdb->update(jasrek_t('rivit'), $r->maksettu
        ? array('maksettu' => null, 'maksettu_summa' => null, 'maksaja' => null, 'arkistotunnus' => null)
        : array('maksettu' => current_time('Y-m-d')), array('id' => $rid));
    jasrek_log_rivi($r, $r->maksettu ? 'maksu_peruttu' : 'maksettu', $r->maksettu ? 'aiemmin merkitty ' . jasrek_date($r->maksettu) : 'käsin');
    jasrek_redirect('jasrek-laskutus', array('view' => 'lasku', 'id' => $r->lasku_id, 'jasrek_msg' => 'paid_updated', 'jasrek_n' => 1));
}

add_action('admin_post_jasrek_bulk_rows', 'jasrek_handle_bulk_rows');
function jasrek_handle_bulk_rows() {
    $lasku_id = intval(jasrek_post('lasku_id', 0));
    jasrek_check('jasrek_bulk_rows_' . $lasku_id);
    global $wpdb;
    $ids = jasrek_post_ids('rivit');
    if (!$ids) {
        jasrek_redirect('jasrek-laskutus', array('view' => 'lasku', 'id' => $lasku_id, 'jasrek_msg' => 'nothing_selected'));
    }
    $op = jasrek_post('bulk_op');
    $kohteet = jasrek_get_rivit('WHERE r.lasku_id = %d AND r.id IN (' . jasrek_in_placeholders($ids) . ')', array_merge(array($lasku_id), $ids));
    $bpvm = jasrek_date_or_null(jasrek_post('bulk_pvm'));
    foreach ($kohteet as $r) {
        if ($op === 'paid' && !$r->maksettu) jasrek_log_rivi($r, 'maksettu', 'joukkotoiminto, maksupäivä ' . jasrek_date($bpvm ? $bpvm : current_time('Y-m-d')));
        if ($op === 'unpaid' && $r->maksettu) jasrek_log_rivi($r, 'maksu_peruttu', 'joukkotoiminto');
        if ($op === 'remove') jasrek_log_rivi($r, 'poistettu_laskulta', 'viite ' . jasrek_viite_format($r->viite));
    }
    if ($op === 'posti_lisaa' || $op === 'posti_poista') {
        $lasku = jasrek_get_lasku($lasku_id);
        $n = jasrek_set_postimaksu($lasku, $kohteet, $op === 'posti_lisaa');
        jasrek_redirect('jasrek-laskutus', array('view' => 'lasku', 'id' => $lasku_id, 'jasrek_msg' => 'postage_updated', 'jasrek_n' => $n));
    }
    if ($op === 'paid') {
        $pvm = jasrek_date_or_null(jasrek_post('bulk_pvm'));
        if (!$pvm) $pvm = current_time('Y-m-d');
        $uudet = array();
        foreach ($kohteet as $r) if (!$r->maksettu) $uudet[] = (int) $r->id;
        $n = $wpdb->query($wpdb->prepare("UPDATE {$wpdb->jasrek_rivit} SET maksettu = %s WHERE lasku_id = %d AND id IN (" . implode(',', array_fill(0, count($ids), '%d')) . ")", array_merge(array($pvm, $lasku_id), $ids)));
        $v = (jasrek_vahvistus_on() && jasrek_post('vahvistus') === '1') ? jasrek_queue_vahvistukset($uudet) : 0;
        jasrek_redirect('jasrek-laskutus', array('view' => 'lasku', 'id' => $lasku_id, 'jasrek_msg' => 'paid_updated', 'jasrek_n' => (int) $n, 'jasrek_v' => $v));
    } elseif ($op === 'unpaid') {
        $n = $wpdb->query($wpdb->prepare("UPDATE {$wpdb->jasrek_rivit} SET maksettu = NULL, maksettu_summa = NULL, maksaja = NULL, arkistotunnus = NULL WHERE lasku_id = %d AND id IN (" . implode(',', array_fill(0, count($ids), '%d')) . ")", array_merge(array($lasku_id), $ids)));
        jasrek_redirect('jasrek-laskutus', array('view' => 'lasku', 'id' => $lasku_id, 'jasrek_msg' => 'paid_updated', 'jasrek_n' => (int) $n));
    } elseif ($op === 'remove') {
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->jasrek_rivit} WHERE lasku_id = %d AND id IN (" . implode(',', array_fill(0, count($ids), '%d')) . ")", array_merge(array($lasku_id), $ids)));
        jasrek_redirect('jasrek-laskutus', array('view' => 'lasku', 'id' => $lasku_id, 'jasrek_msg' => 'row_deleted'));
    }
    jasrek_redirect('jasrek-laskutus', array('view' => 'lasku', 'id' => $lasku_id, 'jasrek_msg' => 'nothing_selected'));
}

add_action('admin_post_jasrek_delete_rivi', 'jasrek_handle_delete_rivi');
function jasrek_handle_delete_rivi() {
    $rid = jasrek_request_int('rivi');
    jasrek_check('jasrek_delete_rivi_' . $rid);
    global $wpdb;
    $r = jasrek_get_rivi($rid);
    if (!$r) jasrek_redirect('jasrek-laskutus');
    $wpdb->delete(jasrek_t('rivit'), array('id' => $rid));
    jasrek_log_rivi($r, 'poistettu_laskulta', 'viite ' . jasrek_viite_format($r->viite));
    jasrek_redirect('jasrek-laskutus', array('view' => 'lasku', 'id' => $r->lasku_id, 'jasrek_msg' => 'row_deleted'));
}

add_action('admin_post_jasrek_send_one', 'jasrek_handle_send_one');
function jasrek_handle_send_one() {
    $rid = jasrek_request_int('rivi');
    jasrek_check('jasrek_send_one_' . $rid);
    $r = jasrek_get_rivi($rid);
    $lasku = $r ? jasrek_get_lasku($r->lasku_id) : null;
    if (!$lasku) jasrek_redirect('jasrek-laskutus');
    $reminder = jasrek_request_flag('muistutus');
    $ok = jasrek_send_rivi($lasku, $r, $reminder);
    if ($ok) jasrek_log_rivi($r, $reminder ? 'muistutettu' : 'lahetetty', $r->to_email, $lasku);
    jasrek_redirect('jasrek-laskutus', array('view' => 'lasku', 'id' => $lasku->id, 'jasrek_msg' => $ok ? 'sent_one' : 'send_failed'));
}

add_action('admin_post_jasrek_send_test', 'jasrek_handle_send_test');
function jasrek_handle_send_test() {
    $id = jasrek_request_int('id');
    jasrek_check('jasrek_send_test_' . $id);
    global $wpdb;
    $lasku = jasrek_get_lasku($id);
    $r = $lasku ? jasrek_get_rivit('WHERE r.lasku_id = %d ORDER BY r.id LIMIT 1', array($id), true) : null;
    if (!$r) jasrek_redirect('jasrek-laskutus');
    $to = sanitize_email(jasrek_post('test_to'));
    if (!$to) jasrek_redirect('jasrek-laskutus', array('view' => 'lasku', 'id' => $id, 'jasrek_msg' => 'test_invalid'));
    $ok = jasrek_send_rivi($lasku, $r, jasrek_post('muistutus') === '1', $to);
    jasrek_redirect('jasrek-laskutus', array('view' => 'lasku', 'id' => $id, 'jasrek_msg' => $ok ? 'test_sent' : 'send_failed', 'jasrek_to' => $to));
}

add_action('admin_post_jasrek_preview', 'jasrek_handle_preview');
function jasrek_handle_preview() {
    $rid = jasrek_request_int('rivi');
    jasrek_check('jasrek_preview_' . $rid);
    $r = jasrek_get_rivi($rid);
    $lasku = $r ? jasrek_get_lasku($r->lasku_id) : null;
    if (!$lasku) wp_die('Laskua ei löytynyt.');
    nocache_headers();
    header('Content-Type: text/html; charset=UTF-8');
    echo jasrek_invoice_html($lasku, $r, jasrek_request_flag('muistutus'), 'data'); // phpcs:ignore -- valmiiksi escapattu HTML
    exit;
}

add_action('admin_post_jasrek_export_lasku', 'jasrek_handle_export_lasku');
function jasrek_handle_export_lasku() {
    $id = jasrek_request_int('id');
    jasrek_check('jasrek_export_lasku_' . $id);
    global $wpdb;
    $lasku = jasrek_get_lasku($id);
    if (!$lasku) jasrek_redirect('jasrek-laskutus');
    $rows = jasrek_get_rivit('WHERE r.lasku_id = %d ORDER BY r.nimi', array($id));
    $out = array();
    foreach ($rows as $r) {
        $p = jasrek_rivi_person($r);
        $out[] = array($p['nimi'], $p['tunnus'], $r->to_email, jasrek_viite_format($r->viite), number_format((float) $r->summa, 2, ',', ''), jasrek_date(jasrek_rivi_era($lasku, $r)),
            $r->lahetetty ? jasrek_date($r->lahetetty) : '', $r->muistutettu ? (int) $r->muistutuksia . ' kertaa, viimeksi ' . jasrek_date($r->muistutettu) : '',
            $r->maksettu ? jasrek_date($r->maksettu) : '', $r->maksettu_summa !== null ? number_format((float) $r->maksettu_summa, 2, ',', '') : '',
            (string) $r->maksaja, (string) $r->arkistotunnus, $r->virhe);
    }
    jasrek_log('vienti', 0, 'vienti', 'Lasku ' . $lasku->nimi . ' CSV-tiedostoon');
    jasrek_csv_output(sanitize_file_name($lasku->nimi) . '_' . jasrek_file_stamp() . '.csv',
        array('Nimi', jasrek_tunnus_nimi(), 'Sähköposti', 'Viite', 'Summa', 'Eräpäivä', 'Lähetetty', 'Muistutettu', 'Maksettu', 'Maksettu summa', 'Maksaja', 'Arkistointitunnus', 'Virhe'), $out);
}

/* ------------------------------------------------------------------
 * AJAX: laskujen lähetys pienissä erissä (ei aikakatkaisuja, edistymispalkki)
 * ------------------------------------------------------------------ */

add_action('wp_ajax_jasrek_send_chunk', 'jasrek_ajax_send_chunk');
function jasrek_ajax_send_chunk() {
    if (!current_user_can('manage_options')) wp_send_json_error('Ei oikeuksia', 403);
    $lasku_id = jasrek_request_int('lasku_id');
    check_ajax_referer('jasrek_send_chunk_' . $lasku_id . '_' . jasrek_data_gen(), 'nonce'); // vanhenee, jos tiedot palautetaan

    global $wpdb;
    $lasku = jasrek_get_lasku($lasku_id);
    if (!$lasku) wp_send_json_error('Laskua ei löytynyt');

    $reminder  = sanitize_key(jasrek_post('mode')) === 'muistutus';
    $run_start = sanitize_text_field(jasrek_post('run_start'));
    if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $run_start)) wp_send_json_error('Virheellinen pyyntö');

    $where = "WHERE r.lasku_id = %d AND (r.viim_yritys IS NULL OR r.viim_yritys < %s) AND COALESCE(NULLIF(m.email, ''), r.email) <> '' AND COALESCE(m.paperilasku, 0) = 0 ";
    $args  = array($lasku_id, $run_start);
    if ($reminder) {
        // Samana päivänä jo muistutetuille ei lähetetä uudelleen (estää vahingossa tehdyn tuplalähetyksen).
        $where .= 'AND r.maksettu IS NULL AND (r.muistutettu IS NULL OR r.muistutettu < %s) ';
        $args[] = current_time('Y-m-d') . ' 00:00:00';
    } else {
        $where .= 'AND r.lahetetty IS NULL ';
    }

    $rows = jasrek_get_rivit($where . 'ORDER BY r.id LIMIT 5', $args);
    $sent = 0; $failed = 0; $errors = array(); $names = array();
    foreach ($rows as $r) {
        if (jasrek_send_rivi($lasku, $r, $reminder)) {
            $sent++;
            $names[] = jasrek_rivi_person($r)['nimi'];
        } else {
            $failed++;
            $errors[] = jasrek_rivi_person($r)['nimi'] . ': ' . ($GLOBALS['jasrek_mail_error'] ?: 'lähetys epäonnistui');
        }
    }
    $remaining = jasrek_count_rivit($where, $args);
    jasrek_log_run($lasku, $reminder ? 'muistutukset' : 'lahetys', $run_start, $names, $failed);

    wp_send_json_success(array('sent' => $sent, 'failed' => $failed, 'remaining' => $remaining, 'errors' => $errors));
}

/* ------------------------------------------------------------------
 * Sivut
 * ------------------------------------------------------------------ */

function jasrek_page_invoices() {
    $view = sanitize_key(jasrek_get('view', ''));
    echo '<div class="wrap jasrek-wrap">';
    if ($view === 'new') {
        jasrek_invoice_new_form();
    } elseif ($view === 'maksutuonti') {
        jasrek_payments_preview();
    } elseif ($view === 'lasku' && !empty(jasrek_get('id'))) {
        jasrek_invoice_detail(jasrek_get_int('id'));
    } else {
        jasrek_invoice_list();
    }
    echo '</div>';
}

function jasrek_settings_warning() {
    $s = jasrek_settings();
    if ($s['iban'] === '' || !jasrek_iban_valid($s['iban'])) {
        echo '<div class="notice notice-warning"><p><strong>Tilinumero puuttuu tai on virheellinen.</strong> Lisää yhdistyksen IBAN '
            . '<a href="' . esc_url(admin_url('admin.php?page=jasrek-asetukset')) . '">asetuksiin</a> ennen laskujen lähettämistä.</p></div>';
        return true;
    }
    return false;
}

function jasrek_invoice_list() {
    global $wpdb;
    delete_transient('jasrek_payimp_' . get_current_user_id()); // keskeneräinen maksujen tuonti peruttu
    $laskut = $wpdb->get_results(
        $wpdb->prepare('SELECT l.*, COUNT(r.id) AS n, SUM(r.lahetetty IS NOT NULL) AS lah, SUM(r.maksettu IS NOT NULL) AS mak, MIN(r.summa - r.postimaksu) AS min_s, MAX(r.summa - r.postimaksu) AS max_s, '
        . 'SUM(r.maksettu IS NULL AND COALESCE(r.erapaiva, l.erapaiva) < %s) AS erant, ', current_time('Y-m-d'))
        . "SUM(CASE WHEN r.maksettu IS NOT NULL THEN r.summa ELSE 0 END) AS mak_summa, SUM(r.summa) AS yht FROM {$wpdb->jasrek_laskut} l LEFT JOIN {$wpdb->jasrek_rivit} r ON r.lasku_id = l.id GROUP BY l.id ORDER BY l.id DESC"
    );
    $today = current_time('Y-m-d');
    ?>
    <h1 class="wp-heading-inline">Jäsenmaksulaskutus</h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=jasrek-laskutus&view=new')); ?>" class="page-title-action">Uusi lasku</a>
    <?php if ($laskut): ?>
    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=jasrek_export_xlsx&id=0'), 'jasrek_export_xlsx_0')); ?>" class="page-title-action" title="Yhteenveto, kaikki avoimet maksut ja jokainen lasku omalla välilehdellään">Vie kaikki laskut Exceliin</a>
    <?php endif; ?>
    <hr class="wp-header-end">
    <?php jasrek_notice(); jasrek_settings_warning(); ?>

    <?php if (!$laskut): ?>
        <div class="jasrek-panel jasrek-empty-state">
            <h2>Ei vielä laskuja</h2>
            <p>Luo esimerkiksi ”Jäsenmaksu 2026” -lasku. Jokainen aktiivinen jäsen saa oman laskun omalla viitenumerollaan sähköpostiin.</p>
            <p><a class="button button-primary button-hero" href="<?php echo esc_url(admin_url('admin.php?page=jasrek-laskutus&view=new')); ?>">Luo ensimmäinen lasku</a></p>
        </div>
    <?php else: ?>
    <table class="widefat striped jasrek-table">
        <thead><tr>
            <th>Lasku</th><th>Summa</th><th>Eräpäivä</th><th>Lähetetty</th><th>Maksettu</th><th>Kertynyt / yhteensä</th>
        </tr></thead>
        <tbody>
        <?php foreach ($laskut as $l):
            $n = (int) $l->n; $pct = $n ? round($l->mak / $n * 100) : 0;
            $url = admin_url('admin.php?page=jasrek-laskutus&view=lasku&id=' . $l->id); ?>
            <tr>
                <td><a class="jasrek-name" href="<?php echo esc_url($url); ?>"><?php echo esc_html($l->nimi); ?></a>
                    <div class="jasrek-sub">Luotu <?php echo esc_html(jasrek_date($l->luotu)); ?></div></td>
                <td><?php echo esc_html(jasrek_summa_range($l->min_s, $l->max_s, $l->summa)); ?></td>
                <td><?php echo esc_html(jasrek_date($l->erapaiva)); ?>
                    <?php if ((int) $l->erant > 0): ?><span class="jasrek-badge jasrek-badge-err"><?php echo (int) $l->erant; ?> erääntynyt</span><?php endif; ?></td>
                <td><?php echo esc_html((int) $l->lah . ' / ' . $n); ?></td>
                <td><div class="jasrek-meter" title="<?php echo esc_attr($pct . ' %'); ?>"><span style="width:<?php echo (int) $pct; ?>%"></span></div>
                    <?php echo esc_html((int) $l->mak . ' / ' . $n); ?></td>
                <td><?php echo esc_html(jasrek_money($l->mak_summa)) . ' / ' . esc_html(jasrek_money($l->yht)); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php jasrek_payments_upload_panel(); ?>
    <?php endif;
}

function jasrek_invoice_new_form() {
    global $wpdb;
    $s = jasrek_settings();
    $members = $wpdb->get_results("SELECT * FROM {$wpdb->jasrek_jasenet} WHERE tila IN ('aktiivinen', 'eronnut') ORDER BY tila ASC, sukunimi, etunimi");
    jasrek_sort_by_name($members, false, false, function ($m) { return $m->tila; });
    $n_akt = 0; $n_noemail = 0; $n_mv = 0; $n_pap = 0;
    $lajit = jasrek_jasenlajit();
    $laji_n = array();
    foreach ($members as $m) {
        if ($m->tila !== 'aktiivinen') continue;
        $k = isset($lajit[$m->jasenlaji]) ? $m->jasenlaji : '';
        $laji_n[$k] = isset($laji_n[$k]) ? $laji_n[$k] + 1 : 1;
        if (jasrek_is_maksuvapaa($m)) { $n_mv++; continue; }
        $n_akt++;
        if (!$m->email) $n_noemail++;
        elseif ($m->paperilasku) $n_pap++;
    }
    $year = (int) current_time('Y');
    $suggest_year = ((int) current_time('n') >= 10) ? $year + 1 : $year;
    ?>
    <h1>Uusi lasku</h1>
    <p><a href="<?php echo esc_url(admin_url('admin.php?page=jasrek-laskutus')); ?>">&larr; Takaisin laskuihin</a></p>
    <?php jasrek_notice(); jasrek_settings_warning(); ?>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="jasrek-panel jasrek-form">
        <input type="hidden" name="action" value="jasrek_create_lasku">
        <?php wp_nonce_field('jasrek_create_lasku'); ?>

        <div class="jasrek-grid">
            <p class="jasrek-span-2"><label for="jasrek-nimi">Laskun nimi *</label>
               <input type="text" id="jasrek-nimi" name="nimi" required value="<?php echo esc_attr('Jäsenmaksu ' . $suggest_year); ?>"></p>
            <?php if (!$lajit): ?>
            <p><label for="jasrek-summa">Summa (€) *</label>
               <input type="text" id="jasrek-summa" name="summa" required inputmode="decimal" placeholder="esim. 25,00"></p>
            <?php endif; ?>
            <p><label for="jasrek-laskupvm">Laskun päiväys</label>
               <input type="date" id="jasrek-laskupvm" name="laskupvm" value="<?php echo esc_attr(current_time('Y-m-d')); ?>"></p>
            <p><label for="jasrek-erapaiva">Eräpäivä *</label>
               <input type="date" id="jasrek-erapaiva" name="erapaiva" required value="<?php echo esc_attr(gmdate('Y-m-d', strtotime(current_time('Y-m-d') . ' +21 days'))); ?>"></p>
            <p><label for="jasrek-postimaksu">Postimaksu paperilaskuihin (€)</label>
               <input type="text" id="jasrek-postimaksu" name="postimaksu" inputmode="decimal" value="<?php echo esc_attr(number_format(jasrek_postimaksu_oletus(), 2, ',', '')); ?>">
               <span class="description">Lisätään vain paperilaskun saajien laskuihin<?php echo ($n_noemail + $n_pap) ? ' (' . (int) ($n_noemail + $n_pap) . ' jäsentä)' : ''; ?>. 0 = ei postimaksua. Oletus <a href="<?php echo esc_url(admin_url('admin.php?page=jasrek-asetukset#jasrek-paperi-asetukset')); ?>">asetuksista</a>.</span></p>
        </div>

        <?php if ($lajit): ?>
        <fieldset class="jasrek-fieldset">
            <legend>Jäsenmaksut jäsenluokittain</legend>
            <table class="jasrek-hinnat">
                <?php foreach ($lajit as $lid => $l): ?>
                <tr><td><label for="jasrek-h-<?php echo esc_attr($lid); ?>"><?php echo esc_html($l['nimi']); ?></label></td>
                    <td><input type="text" id="jasrek-h-<?php echo esc_attr($lid); ?>" name="hinta[<?php echo esc_attr($lid); ?>]" inputmode="decimal" value="<?php echo esc_attr(number_format((float) $l['summa'], 2, ',', '')); ?>"> €</td>
                    <td class="jasrek-muted"><?php echo isset($laji_n[$lid]) ? (int) $laji_n[$lid] : 0; ?> jäsentä<?php echo (float) $l['summa'] <= 0 ? ' · ei laskua' : ''; ?></td></tr>
                <?php endforeach; ?>
                <?php if (!empty($laji_n[''])): ?>
                <tr><td><label for="jasrek-summa">Ilman jäsenluokkaa</label></td>
                    <td><input type="text" id="jasrek-summa" name="summa" inputmode="decimal" value="" placeholder="0,00"> €</td>
                    <td class="jasrek-muted"><?php echo (int) $laji_n['']; ?> jäsentä</td></tr>
                <?php else: ?>
                <input type="hidden" name="summa" value="0">
                <?php endif; ?>
            </table>
            <p class="description">Summat haetaan asetuksista, ja niitä voi muuttaa tälle laskulle. Jos summa on 0 €, sen luokan jäsenille ei tehdä laskua (esim. kunniajäsenet).</p>
        </fieldset>
        <?php endif; ?>

        <p><label for="jasrek-viesti">Saateteksti</label>
           <textarea id="jasrek-viesti" name="viesti" rows="8"><?php echo esc_textarea($s['viesti_oletus']); ?></textarea>
           <?php echo wp_kses_post(jasrek_placeholders('jasrek-viesti', 'lasku', 'Laskun tiedot (tilinumero, viite, summa, eräpäivä, QR- ja viivakoodi) lisätään viestiin automaattisesti.')); ?></p>

        <fieldset class="jasrek-fieldset">
            <legend>Kenelle lasku tehdään?</legend>
            <label class="jasrek-radio"><input type="radio" name="vastaanottajat" value="aktiiviset" checked>
                Kaikille jäsenmaksun maksaville jäsenille (<?php echo (int) $n_akt; ?>)<?php if ($n_mv): ?> <span class="jasrek-muted">– <?php echo (int) $n_mv; ?> jäsentä, joiden jäsenmaksu on 0 €, jätetään pois</span><?php endif; ?></label>
            <label class="jasrek-radio"><input type="radio" name="vastaanottajat" value="valitut" data-jasrek-toggle="#jasrek-member-pick">
                Valituille jäsenille</label>
            <?php if ($n_noemail || $n_pap): ?>
                <p class="jasrek-hint">📄 <?php echo (int) ($n_noemail + $n_pap); ?> laskutettavaa saa laskun paperilla (<?php echo $n_pap ? (int) $n_pap . ' haluaa paperilaskun' : ''; ?><?php echo $n_pap && $n_noemail ? ', ' : ''; ?><?php echo $n_noemail ? (int) $n_noemail . ' ilman sähköpostia' : ''; ?>). Heidän laskunsa tulostetaan laskun sivulta ikkunakuoreen sopivina.</p>
            <?php endif; ?>
            <div id="jasrek-member-pick" class="jasrek-member-pick" hidden>
                <p><input type="search" class="jasrek-search" data-jasrek-filter="#jasrek-pick-list" placeholder="Hae…">
                   <label><input type="checkbox" class="jasrek-check-all" data-target="#jasrek-pick-list"> Valitse kaikki näkyvät</label></p>
                <div id="jasrek-pick-list" class="jasrek-pick-list">
                <?php foreach ($members as $m):
                    $search = preg_replace('/\s+/', '', strtolower(remove_accents($m->etunimi . $m->sukunimi . $m->tunnus))); ?>
                    <label data-search="<?php echo esc_attr($search); ?>">
                        <input type="checkbox" name="jasenet[]" value="<?php echo esc_attr($m->id); ?>">
                        <?php echo esc_html($m->sukunimi . ' ' . $m->etunimi); ?>
                        <?php if ($m->tunnus && jasrek_tunnus_on()): ?><span class="jasrek-call"><?php echo esc_html($m->tunnus); ?></span><?php endif; ?>
                        <?php echo wp_kses_post(jasrek_laji_badge($m->jasenlaji)); ?>
                        <?php echo wp_kses_post(jasrek_tila_badge($m->tila)); ?>
                        <?php if (!$m->email): ?><span class="jasrek-badge jasrek-badge-warn">ei sähköpostia</span><?php elseif ($m->paperilasku): ?><span class="jasrek-badge jasrek-badge-info">paperilasku</span><?php endif; ?>
                    </label>
                <?php endforeach; ?>
                </div>
            </div>
        </fieldset>

        <p class="jasrek-actions">
            <button type="submit" class="button button-primary button-large">Luo laskut</button>
            <span class="description">Laskuja ei vielä lähetetä – voit tarkistaa ne ja lähettää testilaskun ensin.</span>
        </p>
    </form>
    <?php
}

/** Laskurivin maksutilanne merkkinä. */
function jasrek_pay_badge($lasku, $r, $today) {
    if ($r->maksettu) {
        $h = '<span class="jasrek-badge jasrek-badge-ok">Maksettu ' . esc_html(jasrek_date($r->maksettu)) . '</span>';
        if ($r->maksettu_summa !== null && abs((float) $r->maksettu_summa - (float) $r->summa) > 0.004) {
            $h .= '<div><span class="jasrek-badge jasrek-badge-orange">Maksettu summa ' . esc_html(jasrek_money($r->maksettu_summa)) . '</span></div>';
        }
        if ($r->maksaja) $h .= '<div class="jasrek-sub">Maksaja: ' . esc_html($r->maksaja) . '</div>';
        return $h;
    }
    if (jasrek_rivi_era($lasku, $r) < $today) return '<span class="jasrek-badge jasrek-badge-err">Erääntynyt</span>';
    return '<span class="jasrek-badge jasrek-badge-info">Avoin</span>';
}

function jasrek_invoice_detail($id) {
    global $wpdb;
    $lasku = jasrek_get_lasku($id);
    if (!$lasku) { echo '<p>Laskua ei löytynyt.</p>'; return; }

    $rows  = jasrek_get_rivit('WHERE r.lasku_id = %d ORDER BY m.sukunimi, m.etunimi, r.nimi', array($id));
    jasrek_sort_by_name($rows);
    $today = current_time('Y-m-d');
    $no_iban = false;

    $st = array('n' => 0, 'lah' => 0, 'mak' => 0, 'mak_summa' => 0, 'avoin_summa' => 0, 'unsent' => 0, 'unpaid_mail' => 0, 'noemail' => 0, 'muist_tanaan' => 0, 'muist_aiemmin' => 0, 'min' => null, 'max' => null, 'erant' => 0,
                'paperi' => 0, 'p_unsent' => 0, 'p_remind' => 0, 'p_noaddr' => 0, 'p_nopost' => 0);
    $paper_rows = array();
    foreach ($rows as $r) {
        $st['n']++;
        $jm = jasrek_rivi_jasenmaksu($r); // korttiin jäsenmaksut ilman postimaksua
        $st['min'] = $st['min'] === null ? $jm : min($st['min'], $jm);
        $st['max'] = $st['max'] === null ? $jm : max($st['max'], $jm);
        if ($r->lahetetty) $st['lah']++;
        if ($r->maksettu) { $st['mak']++; $st['mak_summa'] += $r->summa; } else { $st['avoin_summa'] += $r->summa; if (jasrek_rivi_era($lasku, $r) < $today) $st['erant'] += $r->summa; }
        if (jasrek_is_paperi($r)) {
            $st['paperi']++;
            if (!$r->to_email) $st['noemail']++;
            if (!$r->lahetetty) $st['p_unsent']++;
            elseif (!$r->maksettu && jasrek_rivi_era($lasku, $r) < $today) $st['p_remind']++;
            if (trim((string) $r->osoite) === '') $st['p_noaddr']++;
            if (!$r->maksettu && !$r->lahetetty && jasrek_rivi_postimaksu($r) <= 0) $st['p_nopost']++;
            $paper_rows[] = $r;
            continue;
        }
        if (!$r->lahetetty) $st['unsent']++;
        if (!$r->maksettu) {
            if ($r->muistutettu && substr($r->muistutettu, 0, 10) === $today) { $st['muist_tanaan']++; }
            else { $st['unpaid_mail']++; if ($r->muistutettu) $st['muist_aiemmin']++; }
        }
    }
    $n_missing = 0;
    $hinnat = jasrek_lasku_hinnat($lasku);
    foreach ($wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->jasrek_jasenet} WHERE tila = 'aktiivinen' AND id NOT IN (SELECT jasen_id FROM {$wpdb->jasrek_rivit} WHERE lasku_id = %d)", $id)) as $mm) {
        if (jasrek_member_price($mm, $hinnat, $lasku->summa) > 0) $n_missing++;
    }

    $post_url   = admin_url('admin-post.php');
    $run_start  = current_time('mysql');
    $send_nonce = wp_create_nonce('jasrek_send_chunk_' . $id . '_' . jasrek_data_gen());
    $is_overdue = $lasku->erapaiva < $today; // laskun yleinen eräpäivä
    ?>
    <h1 class="wp-heading-inline"><?php echo esc_html($lasku->nimi); ?></h1>
    <hr class="wp-header-end">
    <p><a href="<?php echo esc_url(admin_url('admin.php?page=jasrek-laskutus')); ?>">&larr; Kaikki laskut</a></p>
    <?php jasrek_notice(); $no_iban = jasrek_settings_warning(); ?>

    <div class="jasrek-cards">
        <div class="jasrek-card"><span class="jasrek-card-num"><?php echo esc_html(jasrek_summa_range($st['min'], $st['max'], $lasku->summa)); ?></span><span class="jasrek-card-label">Eräpäivä <?php echo esc_html(jasrek_date($lasku->erapaiva)); ?></span></div>
        <div class="jasrek-card"><span class="jasrek-card-num"><?php echo esc_html($st['lah'] . ' / ' . $st['n']); ?></span><span class="jasrek-card-label">Lähetetty</span></div>
        <div class="jasrek-card jasrek-card-ok"><span class="jasrek-card-num"><?php echo esc_html($st['mak'] . ' / ' . $st['n']); ?></span><span class="jasrek-card-label">Maksettu (<?php echo esc_html(jasrek_money($st['mak_summa'])); ?>)</span></div>
        <div class="jasrek-card <?php echo $st['erant'] > 0 ? 'jasrek-card-err' : ''; ?>"><span class="jasrek-card-num"><?php echo esc_html(jasrek_money($st['avoin_summa'])); ?></span><span class="jasrek-card-label">Avoinna<?php
            if ($st['erant'] > 0) echo abs($st['erant'] - $st['avoin_summa']) < 0.005 ? ' – erääntynyt' : esc_html(', josta erääntynyt ' . jasrek_money($st['erant'])); ?></span></div>
    </div>

    <div class="jasrek-panel jasrek-send-panel">
        <h2>Lähetys</h2>
        <div class="jasrek-send-actions">
            <?php if ($st['unsent'] > 0): ?>
                <button type="button" class="button button-primary button-large jasrek-run"
                    <?php disabled($no_iban); ?>
                    data-action="jasrek_send_chunk"
                    data-params="<?php echo esc_attr(wp_json_encode(array('lasku_id' => $id, 'mode' => 'lasku', 'run_start' => $run_start, 'nonce' => $send_nonce))); ?>"
                    data-confirm="<?php echo esc_attr('Lähetetäänkö lasku ' . $st['unsent'] . ' jäsenelle sähköpostilla?'); ?>">
                    Lähetä laskut (<?php echo (int) $st['unsent']; ?> lähettämätöntä)</button>
            <?php else: ?>
                <span class="jasrek-badge jasrek-badge-ok jasrek-badge-lg">✓ Kaikki sähköpostilaskut lähetetty</span>
            <?php endif; ?>

            <?php if ($st['lah'] > 0 && $st['unpaid_mail'] > 0): ?>
                <button type="button" class="button button-large jasrek-run"
                    data-action="jasrek_send_chunk"
                    data-params="<?php echo esc_attr(wp_json_encode(array('lasku_id' => $id, 'mode' => 'muistutus', 'run_start' => $run_start, 'nonce' => $send_nonce))); ?>"
                    data-confirm="<?php echo esc_attr('Lähetetäänkö maksumuistutus ' . $st['unpaid_mail'] . ' maksamattomalle jäsenelle?' . ($st['muist_aiemmin'] ? ' Heistä ' . $st['muist_aiemmin'] . ' on jo muistutettu aiemmin.' : '')); ?>">
                    Lähetä muistutus maksamattomille (<?php echo (int) $st['unpaid_mail']; ?>)</button>
            <?php elseif ($st['muist_tanaan'] > 0): ?>
                <span class="jasrek-badge jasrek-badge-orange jasrek-badge-lg">Maksamattomia muistutettu tänään (<?php echo (int) $st['muist_tanaan']; ?>)</span>
            <?php endif; ?>

            <?php if ($rows): ?>
                <a class="button" target="_blank" href="<?php echo esc_url(wp_nonce_url($post_url . '?action=jasrek_preview&rivi=' . $rows[0]->id, 'jasrek_preview_' . $rows[0]->id)); ?>">Esikatsele laskua</a>
            <?php endif; ?>
        </div>
        <?php if ($rows): $sample = jasrek_rivi_person($rows[0]); ?>
        <form method="post" action="<?php echo esc_url($post_url); ?>" class="jasrek-testbox">
            <input type="hidden" name="action" value="jasrek_send_test">
            <input type="hidden" name="id" value="<?php echo esc_attr($id); ?>">
            <?php wp_nonce_field('jasrek_send_test_' . $id); ?>
            <label for="jasrek-test-to">Testilasku osoitteeseen</label>
            <input type="email" id="jasrek-test-to" name="test_to" required value="<?php echo esc_attr(wp_get_current_user()->user_email); ?>">
            <select name="muistutus"><option value="0">lasku</option><option value="1">muistutus</option></select>
            <button type="submit" class="button">Lähetä testi</button>
            <span class="description">Oletuksena sinun WordPress-tunnuksesi osoite (<?php echo esc_html(wp_get_current_user()->display_name); ?>). Testissä käytetään jäsenen <?php echo esc_html($sample['nimi']); ?> laskun tietoja, mutta jäsenelle ei lähetetä mitään.</span>
        </form>
        <?php endif; ?>
        <div class="jasrek-progress" hidden><div class="jasrek-progress-bar"><span></span></div><p class="jasrek-progress-text"></p></div>
        <?php $auto = jasrek_auto_first_date($lasku); if (jasrek_settings()['auto_muistutus'] === '1'): ?>
            <p class="description jasrek-auto-info">🔔 Automaattinen muistutus:
            <?php if ($auto): ?>
                päällä – <?php echo esc_html(jasrek_settings()['auto_paivia']); ?> päivää eräpäivän jälkeen<?php echo $auto >= $today ? ' (ensimmäinen ' . esc_html(jasrek_date($auto)) . ')' : ''; ?>, enintään <?php echo (int) jasrek_settings()['auto_max']; ?> kpl.
            <?php else: ?>
                pois päältä tältä laskulta.
            <?php endif; ?></p>
        <?php endif; ?>
        <?php if ($st['paperi']): ?>
            <p class="description">📄 <?php echo (int) $st['paperi']; ?> jäsentä saa laskun paperilla, eikä heille lähetetä sähköpostia – ks. <a href="#jasrek-paperi">Paperilaskut</a>.</p>
        <?php endif; ?>
    </div>

    <?php if ($paper_rows): ?>
    <div class="jasrek-panel" id="jasrek-paperi">
        <h2>Paperilaskut (<?php echo (int) $st['paperi']; ?>)</h2>
        <p class="description"><?php
            $pref = $st['paperi'] - $st['noemail'];
            echo esc_html(implode(', ', array_filter(array($pref ? $pref . ' haluaa laskun paperilla' : '', $st['noemail'] ? $st['noemail'] . ' ilman sähköpostia' : '')))) . '.'; ?>
            Tulosta laskut, taita ja laita ikkunakuoreen (C5 tai DL) – nimi ja osoite osuvat ikkunaan. Merkitse sitten postitetuksi, niin näet mitkä on hoidettu.</p>
        <div class="jasrek-send-actions">
            <?php if ($st['p_unsent']): ?>
                <a class="button button-primary button-large" target="_blank" href="<?php echo esc_url(jasrek_print_url($id, array('joukko' => 'postittamattomat'))); ?>" <?php echo $no_iban ? 'aria-disabled="true"' : ''; ?>>🖨 Tulosta postittamattomat (<?php echo (int) $st['p_unsent']; ?>)</a>
            <?php else: ?>
                <span class="jasrek-badge jasrek-badge-ok jasrek-badge-lg">✓ Kaikki paperilaskut postitettu</span>
            <?php endif; ?>
            <?php if ($st['p_remind']): ?>
                <a class="button button-large" target="_blank" href="<?php echo esc_url(jasrek_print_url($id, array('joukko' => 'muistutukset'))); ?>">🖨 Tulosta muistutukset erääntyneille (<?php echo (int) $st['p_remind']; ?>)</a>
            <?php endif; ?>
            <a class="button" target="_blank" href="<?php echo esc_url(jasrek_print_url($id, array('joukko' => 'kaikki'))); ?>">Näytä kaikki paperilaskut</a>
        </div>
        <?php $pm_lasku = (float) $lasku->postimaksu > 0 ? (float) $lasku->postimaksu : jasrek_postimaksu_oletus();
        if ($st['p_nopost'] && $pm_lasku > 0): ?>
            <p class="jasrek-hint">📮 <?php echo (int) $st['p_nopost']; ?> postittamattomalta paperilaskulta puuttuu postimaksu (esim. jäsen valitsi paperilaskun laskun teon jälkeen).
                <a class="button button-small" href="<?php echo esc_url(wp_nonce_url($post_url . '?action=jasrek_add_postage&id=' . $id, 'jasrek_add_postage_' . $id)); ?>"
                   data-confirm="<?php echo esc_attr('Lisätäänkö postimaksu ' . html_entity_decode(jasrek_money($pm_lasku)) . ' ' . $st['p_nopost'] . ' paperilaskuun?'); ?>">Lisää postimaksu <?php echo esc_html(jasrek_money($pm_lasku)); ?></a></p>
        <?php endif; ?>
        <?php if ($st['p_noaddr']): ?>
            <p class="jasrek-hint">⚠ <?php echo (int) $st['p_noaddr']; ?> paperilaskun saajalta puuttuu osoite. Lisää osoite jäsenen tietoihin.</p>
        <?php endif; ?>
        <form method="post" action="<?php echo esc_url($post_url); ?>">
            <input type="hidden" name="action" value="jasrek_paper_mark">
            <input type="hidden" name="lasku_id" value="<?php echo esc_attr($id); ?>">
            <?php wp_nonce_field('jasrek_paper_mark_' . $id); ?>
            <table class="widefat striped jasrek-table" id="jasrek-paper-rows">
                <thead><tr>
                    <td class="check-column"><input type="checkbox" class="jasrek-check-all" data-target="#jasrek-paper-rows"></td>
                    <th>Jäsen</th><th>Postiosoite</th><th>Viite</th><th>Summa</th><th>Postitus</th><th>Maksu</th><th class="jasrek-col-actions">Toiminnot</th>
                </tr></thead>
                <tbody>
                <?php foreach ($paper_rows as $r):
                    $p = jasrek_rivi_person($r); ?>
                    <tr>
                        <th class="check-column"><input type="checkbox" name="rivit[]" value="<?php echo esc_attr($r->id); ?>"></th>
                        <td><strong><?php echo esc_html($p['nimi']); ?></strong>
                            <div class="jasrek-sub"><?php echo $r->paperilasku ? 'haluaa paperilaskun' : 'ei sähköpostia'; ?></div></td>
                        <td><?php echo trim((string) $r->osoite) !== '' ? esc_html($r->osoite) : '<span class="jasrek-badge jasrek-badge-warn">osoite puuttuu</span>'; ?></td>
                        <td><code class="jasrek-ref"><?php echo esc_html(jasrek_viite_format($r->viite)); ?></code></td>
                        <td><?php echo esc_html(jasrek_money($r->summa)); ?>
                            <?php if (jasrek_rivi_postimaksu($r) > 0): ?><div class="jasrek-sub">sis. postimaksun <?php echo esc_html(jasrek_money(jasrek_rivi_postimaksu($r))); ?></div><?php endif; ?></td>
                        <td><?php if ($r->lahetetty): ?><span class="jasrek-badge jasrek-badge-ok">Postitettu <?php echo esc_html(jasrek_date($r->lahetetty)); ?></span><?php else: ?><span class="jasrek-badge jasrek-badge-muted">Ei postitettu</span><?php endif; ?>
                            <?php if ($r->muistutettu): ?><div><span class="jasrek-badge jasrek-badge-orange">Muistutettu <?php echo (int) $r->muistutuksia > 1 ? (int) $r->muistutuksia . '× · ' : ''; ?><?php echo esc_html(jasrek_date($r->muistutettu)); ?></span></div><?php endif; ?></td>
                        <td><?php echo jasrek_pay_badge($lasku, $r, $today); // phpcs:ignore ?></td>
                        <td class="jasrek-col-actions">
                            <a class="button button-small" target="_blank" href="<?php echo esc_url(jasrek_print_url($id, array('rivi' => $r->id))); ?>">Näytä lasku</a>
                            <a class="button button-small" target="_blank" href="<?php echo esc_url(jasrek_print_url($id, array('rivi' => $r->id, 'tulosta' => 1))); ?>">🖨 Tulosta</a>
                            <?php if (!$r->maksettu && $r->lahetetty && jasrek_rivi_era($lasku, $r) < $today): ?>
                                <a class="button button-small" target="_blank" href="<?php echo esc_url(jasrek_print_url($id, array('rivi' => $r->id, 'muistutus' => 1, 'tulosta' => 1))); ?>">🖨 Muistutus</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <div class="jasrek-bulk">
                <label for="jasrek-paper-op"><strong>Valituille:</strong></label>
                <select name="tapa" id="jasrek-paper-op">
                    <option value="lasku">Merkitse lasku postitetuksi tänään</option>
                    <option value="muistutus">Merkitse muistutus postitetuksi tänään</option>
                </select>
                <button type="submit" class="button" data-confirm="Merkitäänkö valitut postitetuiksi?">Suorita</button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url($post_url); ?>" id="jasrek-bulk-form">
        <input type="hidden" name="action" value="jasrek_bulk_rows">
        <input type="hidden" name="lasku_id" value="<?php echo esc_attr($id); ?>">
        <?php wp_nonce_field('jasrek_bulk_rows_' . $id); ?>

        <div class="jasrek-toolbar">
            <ul class="subsubsub jasrek-tabs" data-jasrek-status-for="#jasrek-rows">
                <li><a href="#" data-status="" class="current">Kaikki (<?php echo esc_html($st['n']); ?>)</a></li>
                <li><a href="#" data-status="avoin">Avoimet (<?php echo esc_html($st['n'] - $st['mak']); ?>)</a></li>
                <li><a href="#" data-status="maksettu">Maksetut (<?php echo esc_html($st['mak']); ?>)</a></li>
                <li><a href="#" data-status="lahettamaton">Lähettämättömät (<?php echo esc_html($st['n'] - $st['lah']); ?>)</a></li>
                <?php if ($st['paperi']): ?><li><a href="#" data-status="paperi">Paperilaskut (<?php echo esc_html($st['paperi']); ?>)</a></li><?php endif; ?>
            </ul>
            <div class="jasrek-toolbar-right">
                <input type="search" class="jasrek-search" data-jasrek-filter="#jasrek-rows" placeholder="Hae viitteellä, nimellä tai sähköpostilla…">
            </div>
        </div>

        <table class="widefat striped jasrek-table" id="jasrek-rows">
            <thead><tr>
                <td class="check-column"><input type="checkbox" class="jasrek-check-all" data-target="#jasrek-rows"></td>
                <th>Jäsen</th><th>Viite</th><th>Summa</th><th>Sähköposti</th><th>Maksu</th><th class="jasrek-col-actions">Toiminnot</th>
            </tr></thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="7" class="jasrek-empty">Laskulla ei ole rivejä.</td></tr><?php endif; ?>
            <?php foreach ($rows as $r):
                $p = jasrek_rivi_person($r);
                $status = array($r->maksettu ? 'maksettu' : 'avoin');
                if (!$r->lahetetty) $status[] = 'lahettamaton';
                $paperi = jasrek_is_paperi($r);
                if ($paperi) $status[] = 'paperi';
                $search = preg_replace('/\s+/', '', strtolower(remove_accents($p['nimi'] . $p['tunnus'] . $r->to_email)) . $r->viite);
                $paid_url = wp_nonce_url($post_url . '?action=jasrek_toggle_paid&rivi=' . $r->id, 'jasrek_paid_' . $r->id);
                $send_url = wp_nonce_url($post_url . '?action=jasrek_send_one&rivi=' . $r->id, 'jasrek_send_one_' . $r->id);
                $prev_url = wp_nonce_url($post_url . '?action=jasrek_preview&rivi=' . $r->id, 'jasrek_preview_' . $r->id);
                ?>
                <tr data-search="<?php echo esc_attr($search); ?>" data-status="<?php echo esc_attr(implode(' ', $status)); ?>">
                    <th class="check-column"><input type="checkbox" name="rivit[]" value="<?php echo esc_attr($r->id); ?>"></th>
                    <td><strong><?php echo esc_html($p['nimi']); ?></strong>
                        <?php if ($p['tunnus'] && jasrek_tunnus_on()): ?><span class="jasrek-call"><?php echo esc_html($p['tunnus']); ?></span><?php endif; ?>
                        <?php if ($r->etunimi === null): ?><div class="jasrek-sub">poistettu rekisteristä</div><?php endif; ?></td>
                    <td><code class="jasrek-ref"><?php echo esc_html(jasrek_viite_format($r->viite)); ?></code></td>
                    <td><?php echo esc_html(jasrek_money($r->summa)); ?>
                        <?php if (jasrek_rivi_postimaksu($r) > 0): ?><div class="jasrek-sub">sis. postimaksun <?php echo esc_html(jasrek_money(jasrek_rivi_postimaksu($r))); ?></div><?php endif; ?>
                        <?php if (!empty($r->erapaiva) && $r->erapaiva !== $lasku->erapaiva): ?><div class="jasrek-sub" title="Jäsenkohtainen eräpäivä (lisätty laskulle myöhemmin)">eräp. <?php echo esc_html(jasrek_date($r->erapaiva)); ?></div><?php endif; ?></td>
                    <td>
                        <?php if ($paperi): ?>
                            <span class="jasrek-badge jasrek-badge-info">📄 paperilasku</span>
                            <div><?php echo $r->lahetetty ? '<span class="jasrek-badge jasrek-badge-ok">Postitettu ' . esc_html(jasrek_date($r->lahetetty)) . '</span>' : '<span class="jasrek-badge jasrek-badge-muted">Ei postitettu</span>'; ?></div>
                            <?php if ($r->muistutettu): ?><div><span class="jasrek-badge jasrek-badge-orange">Muistutettu <?php echo esc_html(jasrek_date($r->muistutettu)); ?></span></div><?php endif; ?>
                        <?php elseif ($r->lahetetty): ?>
                            <span class="jasrek-badge jasrek-badge-ok">Lähetetty <?php echo esc_html(jasrek_date($r->lahetetty)); ?></span>
                            <?php if ($r->muistutettu): ?><div><span class="jasrek-badge jasrek-badge-orange" title="<?php echo esc_attr('Muistutuksia lähetetty ' . (int) $r->muistutuksia . ' kpl'); ?>">Muistutettu <?php echo (int) $r->muistutuksia > 1 ? (int) $r->muistutuksia . '× · viimeksi ' : ''; ?><?php echo esc_html(jasrek_date($r->muistutettu)); ?></span></div><?php endif; ?>
                        <?php else: ?>
                            <span class="jasrek-badge jasrek-badge-muted">Ei lähetetty</span>
                        <?php endif; ?>
                        <?php if ($r->virhe): ?><div class="jasrek-sub jasrek-err" title="<?php echo esc_attr($r->virhe); ?>">⚠ <?php echo esc_html(wp_trim_words($r->virhe, 8)); ?></div><?php endif; ?>
                        <?php if ($r->to_email): ?><div class="jasrek-sub"><?php echo esc_html($r->to_email); ?></div><?php endif; ?>
                    </td>
                    <td><?php echo jasrek_pay_badge($lasku, $r, $today); // phpcs:ignore ?></td>
                    <td class="jasrek-col-actions">
                        <a class="button button-small <?php echo $r->maksettu ? '' : 'button-primary'; ?>" href="<?php echo esc_url($paid_url); ?>"><?php echo $r->maksettu ? 'Peru maksu' : 'Merkitse maksetuksi'; ?></a>
                        <?php if ($paperi): ?>
                            <a class="button button-small" target="_blank" href="<?php echo esc_url(jasrek_print_url($id, array('rivi' => $r->id))); ?>">Näytä</a>
                            <a class="button button-small" target="_blank" href="<?php echo esc_url(jasrek_print_url($id, array('rivi' => $r->id, 'tulosta' => 1))); ?>">Tulosta</a>
                        <?php else: ?>
                            <a class="button button-small" target="_blank" href="<?php echo esc_url($prev_url); ?>">Näytä</a>
                            <?php $tul_muist = $r->lahetetty && !$r->maksettu && jasrek_rivi_era($lasku, $r) < $today; ?>
                            <a class="button button-small" target="_blank" href="<?php echo esc_url(jasrek_print_url($id, $tul_muist ? array('rivi' => $r->id, 'muistutus' => 1) : array('rivi' => $r->id))); ?>" title="<?php echo esc_attr(($tul_muist ? 'Maksumuistutus' : 'Lasku') . ' tulostettavana A4-sivuna, samannäköisenä kuin paperilasku. Tulosta paperille tai tallenna PDF:nä (tulostin: Tallenna PDF-tiedostona) ja lähetä jäsenelle omasta sähköpostistasi, jos lasku on hukassa.'); ?>"><?php echo $tul_muist ? 'Tulosta muistutus' : 'Tulosta'; ?></a>
                        <?php endif; ?>
                        <?php if ($r->maksettu && $r->to_email && jasrek_vahvistus_on()): ?>
                            <a class="button button-small" href="<?php echo esc_url(wp_nonce_url($post_url . '?action=jasrek_send_vahvistus&rivi=' . $r->id, 'jasrek_vahvistus_' . $r->id)); ?>" data-confirm="<?php echo esc_attr('Lähetetäänkö maksuvahvistus osoitteeseen ' . $r->to_email . '?'); ?>">Lähetä vahvistus</a>
                        <?php endif; ?>
                        <?php if ($r->to_email && !$paperi): ?>
                            <a class="button button-small" href="<?php echo esc_url($send_url); ?>"
                               data-confirm="<?php echo esc_attr(($r->lahetetty ? 'Lähetetäänkö lasku uudelleen' : 'Lähetetäänkö lasku') . ' osoitteeseen ' . $r->to_email . '?'); ?>"><?php echo $r->lahetetty ? 'Lähetä uudelleen' : 'Lähetä'; ?></a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <div class="jasrek-bulk">
            <label for="jasrek-bulk-op"><strong>Valituille:</strong></label>
            <select name="bulk_op" id="jasrek-bulk-op">
                <option value="paid">Merkitse maksetuksi päivämäärällä</option>
                <option value="unpaid">Peru maksumerkintä</option>
                <option value="remove">Poista laskulta</option>
                <option value="posti_lisaa">Lisää postimaksu (<?php echo esc_html(jasrek_money((float) $lasku->postimaksu > 0 ? $lasku->postimaksu : jasrek_postimaksu_oletus())); ?>, maksamattomat)</option>
                <option value="posti_poista">Poista postimaksu (maksamattomat)</option>
            </select>
            <input type="date" name="bulk_pvm" value="<?php echo esc_attr($today); ?>">
            <button type="submit" class="button" data-confirm="Suoritetaanko toiminto valituille riveille?">Suorita</button>
            <?php if (jasrek_vahvistus_on()): ?><label class="jasrek-radio jasrek-vahvistus-ruksi"><input type="checkbox" name="vahvistus" value="1" checked> Maksetuksi merkittäessä lähetä maksuvahvistus sähköpostilla</label><?php endif; ?>
        </div>
    </form>

    <div class="jasrek-columns">
        <details class="jasrek-panel">
            <summary><strong>Muokkaa laskun tietoja</strong></summary>
            <form method="post" action="<?php echo esc_url($post_url); ?>" class="jasrek-form">
                <input type="hidden" name="action" value="jasrek_update_lasku">
                <input type="hidden" name="id" value="<?php echo esc_attr($id); ?>">
                <?php wp_nonce_field('jasrek_update_lasku_' . $id); ?>
                <p><label for="jasrek-e-nimi">Laskun nimi</label><input type="text" id="jasrek-e-nimi" name="nimi" value="<?php echo esc_attr($lasku->nimi); ?>" required></p>
                <p><label for="jasrek-e-era">Eräpäivä</label><input type="date" id="jasrek-e-era" name="erapaiva" value="<?php echo esc_attr($lasku->erapaiva); ?>" required></p>
                <?php if (jasrek_settings()['auto_muistutus'] === '1'): ?>
                <p><label class="jasrek-radio"><input type="checkbox" name="auto_muistutus" value="1" <?php checked((int) $lasku->auto_muistutus, 1); ?>> Automaattiset maksumuistutukset tälle laskulle</label></p>
                <?php endif; ?>
                <p><label for="jasrek-e-viesti">Saateteksti</label><textarea id="jasrek-e-viesti" name="viesti" rows="6"><?php echo esc_textarea($lasku->viesti); ?></textarea>
                   <?php echo wp_kses_post(jasrek_placeholders('jasrek-e-viesti', 'lasku')); ?></p>
                <p class="description">Summaa ei voi muuttaa jälkikäteen, koska laskut on jo muodostettu. Muutokset näkyvät seuraavissa lähetyksissä.</p>
                <p><button type="submit" class="button button-primary">Tallenna</button></p>
            </form>
        </details>

        <div class="jasrek-panel">
            <p><strong>Muut toiminnot</strong></p>
            <p>
                <?php if ($n_missing > 0): ?>
                    <a class="button" href="<?php echo esc_url(wp_nonce_url($post_url . '?action=jasrek_add_missing&id=' . $id, 'jasrek_add_missing_' . $id)); ?>">Lisää laskulle uudet aktiiviset jäsenet (<?php echo esc_html($n_missing); ?>)</a>
                <?php endif; ?>
                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=jasrek-laskutus#jasrek-maksutuonti')); ?>">Tuo maksut pankista</a>
                <a class="button" href="<?php echo esc_url(wp_nonce_url($post_url . '?action=jasrek_export_xlsx&id=' . $id, 'jasrek_export_xlsx_' . $id)); ?>">Vie Exceliin</a>
                <a class="button" href="<?php echo esc_url(wp_nonce_url($post_url . '?action=jasrek_export_lasku&id=' . $id, 'jasrek_export_lasku_' . $id)); ?>">Vie CSV</a>
                <a class="button jasrek-btn-danger" href="<?php echo esc_url(wp_nonce_url($post_url . '?action=jasrek_delete_lasku&id=' . $id, 'jasrek_delete_lasku_' . $id)); ?>"
                   data-confirm="Poistetaanko koko lasku ja kaikki sen maksutiedot pysyvästi?">Poista lasku</a>
            </p>
            <p class="description">Vinkki: hae viitenumerolla tiliotteelta ylläolevasta hakukentästä – voit kirjoittaa viitteen välilyönneillä tai ilman.</p>
        </div>
    </div>
    <?php
}
