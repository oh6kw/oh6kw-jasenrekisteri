<?php
if (!defined('ABSPATH')) exit;
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The plugin's own custom tables (members, invoices, audit log). The data is admin-only and changes on every write, so object caching is not used.

/*
 * Laskujen vienti Exceliin: yhteenveto, kaikki avoimet maksut ja jokainen lasku omalla välilehdellään.
 */

add_action('admin_post_jasrek_export_xlsx', 'jasrek_handle_export_xlsx');
function jasrek_handle_export_xlsx() {
    $id = jasrek_request_int('id'); // 0 = kaikki laskut
    jasrek_check('jasrek_export_xlsx_' . $id);
    global $wpdb;
    require_once JASREK_DIR . 'includes/xlsx.php';

    $lt = jasrek_t('laskut');
    $laskut = $id
        ? $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->jasrek_laskut} WHERE id = %d", $id))
        : $wpdb->get_results("SELECT * FROM {$wpdb->jasrek_laskut} ORDER BY laskupvm DESC, id DESC");
    if (!$laskut) jasrek_redirect('jasrek-laskutus');

    $today  = current_time('Y-m-d');
    $tunnus = jasrek_tunnus_on();
    $x = new JASREK_Xlsx();
    $sum = array(); $avoimet = array(); $sheets = array();

    foreach ($laskut as $l) {
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT r.*, m.etunimi, m.sukunimi, m.tunnus, m.tila, COALESCE(NULLIF(r.jasenlaji, ''), m.jasenlaji, '') AS jasenlaji, m.puhelin, COALESCE(NULLIF(m.email, ''), r.email) AS to_email FROM {$wpdb->jasrek_rivit} r LEFT JOIN {$wpdb->jasrek_jasenet} m ON m.id = r.jasen_id WHERE r.lasku_id = %d ORDER BY m.sukunimi, m.etunimi, r.nimi", $l->id
        ));
        jasrek_sort_by_name($rows);
        $n = 0; $n_mak = 0; $n_era = 0; $e_lask = 0; $e_mak = 0; $e_avoin = 0;
        $out = array();
        foreach ($rows as $r) {
            $p    = jasrek_rivi_person($r);
            $era  = jasrek_rivi_era($l, $r);
            $n++; $e_lask += $r->summa;
            if ($r->maksettu) {
                $n_mak++;
                $paid = $r->maksettu_summa !== null ? (float) $r->maksettu_summa : (float) $r->summa;
                $e_mak += $paid;
                $poikkeaa = abs($paid - (float) $r->summa) > 0.004;
                $tila = JASREK_Xlsx::text($poikkeaa ? 'Maksettu (eri summa)' : 'Maksettu', $poikkeaa ? JASREK_Xlsx::S_WARN : JASREK_Xlsx::S_OK);
            } else {
                $e_avoin += $r->summa;
                if ($era < $today) { $n_era++; $tila = JASREK_Xlsx::text('Erääntynyt', JASREK_Xlsx::S_ERR); }
                else { $tila = JASREK_Xlsx::text('Avoin'); }
                $avoimet[] = array_merge(
                    array(JASREK_Xlsx::text($l->nimi), JASREK_Xlsx::text($p['nimi'])),
                    $tunnus ? array(JASREK_Xlsx::text($p['tunnus'])) : array(),
                    array(JASREK_Xlsx::text(jasrek_laji_nimi((string) $r->jasenlaji)), JASREK_Xlsx::text((string) $r->to_email), JASREK_Xlsx::text((string) $r->puhelin),
                        JASREK_Xlsx::text(jasrek_viite_format($r->viite)), JASREK_Xlsx::money($r->summa), JASREK_Xlsx::date($era), $tila,
                        (int) $r->muistutuksia ?: '', $r->muistutettu ? JASREK_Xlsx::date($r->muistutettu) : '')
                );
            }
            $out[] = array_merge(
                array(JASREK_Xlsx::text($p['nimi'])),
                $tunnus ? array(JASREK_Xlsx::text($p['tunnus'])) : array(),
                array(
                    JASREK_Xlsx::text(jasrek_laji_nimi((string) $r->jasenlaji)),
                    JASREK_Xlsx::text((string) $r->to_email),
                    JASREK_Xlsx::text(jasrek_viite_format($r->viite)),
                    JASREK_Xlsx::money($r->summa),
                    JASREK_Xlsx::date($era),
                    $tila,
                    $r->maksettu ? JASREK_Xlsx::date($r->maksettu) : '',
                    $r->maksettu ? JASREK_Xlsx::money($r->maksettu_summa !== null ? $r->maksettu_summa : $r->summa) : '',
                    JASREK_Xlsx::text((string) $r->maksaja),
                    $r->lahetetty ? JASREK_Xlsx::date($r->lahetetty) : '',
                    (int) $r->muistutuksia ?: '',
                )
            );
        }
        $sum[] = array(JASREK_Xlsx::text($l->nimi), JASREK_Xlsx::date($l->laskupvm), JASREK_Xlsx::date($l->erapaiva), $n, $n_mak, $n - $n_mak, $n_era,
            JASREK_Xlsx::money($e_lask), JASREK_Xlsx::money($e_mak), JASREK_Xlsx::money($e_avoin));
        $sheets[] = array($l, $out);
    }

    $t = $tunnus ? 1 : 0; // sarakesiirtymä, jos lisätunniste on käytössä

    // 1. Yhteenveto
    $x->add_sheet('Yhteenveto',
        array('Lasku', 'Laskun pvm', 'Eräpäivä', 'Laskuja', 'Maksettu', 'Avoinna', 'Erääntynyt', 'Laskutettu €', 'Maksettu €', 'Avoinna €'),
        $sum, array(30, 12, 12, 9, 10, 9, 11, 14, 14, 14),
        count($sum) > 1 ? array(JASREK_Xlsx::text('Yhteensä', JASREK_Xlsx::S_BOLD), '', '',
            JASREK_Xlsx::formula('SUM(D2:D{last})', JASREK_Xlsx::S_BOLD), JASREK_Xlsx::formula('SUM(E2:E{last})', JASREK_Xlsx::S_BOLD),
            JASREK_Xlsx::formula('SUM(F2:F{last})', JASREK_Xlsx::S_BOLD), JASREK_Xlsx::formula('SUM(G2:G{last})', JASREK_Xlsx::S_BOLD),
            JASREK_Xlsx::formula('SUM(H2:H{last})', JASREK_Xlsx::S_BOLD_MONEY), JASREK_Xlsx::formula('SUM(I2:I{last})', JASREK_Xlsx::S_BOLD_MONEY),
            JASREK_Xlsx::formula('SUM(J2:J{last})', JASREK_Xlsx::S_BOLD_MONEY)) : null);

    // 2. Kaikki avoimet maksut (vain kun viedään kaikki laskut)
    if (!$id) {
        $x->add_sheet('Avoimet maksut',
            array_merge(array('Lasku', 'Nimi'), $tunnus ? array(jasrek_tunnus_nimi()) : array(),
                array('Jäsenluokka', 'Sähköposti', 'Puhelin', 'Viite', 'Summa', 'Eräpäivä', 'Tila', 'Muistutuksia', 'Viim. muistutus')),
            $avoimet, array_merge(array(24, 24), $tunnus ? array(12) : array(), array(16, 30, 16, 14, 11, 12, 12, 12, 14)),
            $avoimet ? array_merge(array(JASREK_Xlsx::text('Yhteensä', JASREK_Xlsx::S_BOLD), ''), $tunnus ? array('') : array(), array('', '', '', '',
                JASREK_Xlsx::formula('SUM(' . chr(71 + $t) . '2:' . chr(71 + $t) . '{last})', JASREK_Xlsx::S_BOLD_MONEY))) : null);
    }

    // 3. Jokainen lasku omalle välilehdelle
    foreach ($sheets as $sh) {
        list($l, $out) = $sh;
        $sumcol = chr(69 + $t);  // Summa
        $paidcol = chr(73 + $t); // Maksettu €
        $x->add_sheet($l->nimi,
            array_merge(array('Nimi'), $tunnus ? array(jasrek_tunnus_nimi()) : array(),
                array('Jäsenluokka', 'Sähköposti', 'Viite', 'Summa', 'Eräpäivä', 'Tila', 'Maksupäivä', 'Maksettu €', 'Maksaja', 'Lähetetty', 'Muistutuksia')),
            $out, array_merge(array(26), $tunnus ? array(12) : array(), array(16, 30, 14, 11, 12, 20, 12, 12, 22, 12, 12)),
            array_merge(array(JASREK_Xlsx::text('Yhteensä', JASREK_Xlsx::S_BOLD)), $tunnus ? array('') : array(), array('', '', '',
                JASREK_Xlsx::formula("SUM({$sumcol}2:{$sumcol}{last})", JASREK_Xlsx::S_BOLD_MONEY), '', '', '',
                JASREK_Xlsx::formula("SUM({$paidcol}2:{$paidcol}{last})", JASREK_Xlsx::S_BOLD_MONEY))));
    }

    $nimi = ($id ? sanitize_file_name($laskut[0]->nimi) : 'jasenmaksut') . '_' . jasrek_file_stamp();
    $bin = $x->build();
    jasrek_log('vienti', 0, 'vienti', $id ? 'Lasku ' . $laskut[0]->nimi . ' Exceliin' : 'Kaikki laskut Exceliin (' . count($laskut) . ' laskua)');
    nocache_headers();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $nimi . '.xlsx"');
    header('Content-Length: ' . strlen($bin));
    echo $bin; // phpcs:ignore -- binääritiedosto
    exit;
}

/*
 * Kaikki tiedot yhteen Excel-tiedostoon (Jäsenet-sivun "Vie kaikki tiedot Exceliin").
 * Välilehdet: Yhteenveto, Jäsenet, jokainen lisäkenttä omana välilehtenään, Jäsenluokat,
 * Laskut, Laskurivit, Avoimet maksut ja Muutosloki. Tiedosto on luettavaksi ja arkistoon;
 * palautukseen käytetään varmuuskopiota.
 */
add_action('admin_post_jasrek_export_all', 'jasrek_handle_export_all');
function jasrek_handle_export_all() {
    jasrek_check('jasrek_export_all');
    global $wpdb;
    require_once JASREK_DIR . 'includes/xlsx.php';
    $X = 'JASREK_Xlsx';
    $s = jasrek_settings();
    $today = current_time('Y-m-d');
    $tunnus = jasrek_tunnus_on();
    $tn = $tunnus ? array(jasrek_tunnus_nimi()) : array();
    $kentat = jasrek_lisakentat();
    $lajit = jasrek_jasenlajit();
    $x = new JASREK_Xlsx();
    $d = function ($v) use ($X) { return ($v && $v !== '0000-00-00') ? $X::date($v) : ''; };
    $t = function ($v) use ($X) { return $X::text((string) $v); };
    $aika = function ($v) { return ($v && $v !== '0000-00-00 00:00:00') ? jasrek_date($v) . ' ' . substr($v, 11, 5) : ''; };

    // Jäsenet
    $jasenet = $wpdb->get_results("SELECT * FROM {$wpdb->jasrek_jasenet} ORDER BY sukunimi, etunimi, id");
    jasrek_sort_by_name($jasenet);
    $m_rows = array(); $lisa_rows = array(); $n_tila = array(); $n_laji = array();
    foreach ($jasenet as $m) {
        $lisa = jasrek_member_lisa($m);
        $nimi = trim($m->etunimi . ' ' . $m->sukunimi);
        $ero = $m->tila === 'eronnut';
        $n_tila[$m->tila] = (isset($n_tila[$m->tila]) ? $n_tila[$m->tila] : 0) + 1;
        $lk = $m->jasenlaji . '|' . ($ero ? 'e' : 'v');
        $n_laji[$lk] = (isset($n_laji[$lk]) ? $n_laji[$lk] : 0) + 1;
        $row = array_merge(
            array((int) $m->id, $t($m->etunimi), $t($m->sukunimi)),
            $tunnus ? array($t($m->tunnus)) : array(),
            array($t(jasrek_laji_nimi($m->jasenlaji)), $X::text(jasrek_tila_nimi($m->tila), $ero ? $X::S_WARN : $X::S_TEXT),
                $t($m->osoite), $t($m->puhelin), $t($m->email), $d($m->liittynyt), $t(jasrek_syntynyt_teksti($m->syntynyt)),
                $d($m->eronnut_pvm), $m->paperilasku ? $t('Kyllä') : '')
        );
        foreach ($kentat as $kid => $k) {
            $v = isset($lisa[$kid]) ? (string) $lisa[$kid] : '';
            $row[] = jasrek_lisa_is_date($k) ? $d($v) : $t(jasrek_lisa_value($k, $v, 'Kyllä'));
            if ($v !== '') {
                $lisa_rows[$kid][] = array('v' => $v, 'row' => array_merge(
                    array(jasrek_lisa_is_date($k) ? $d($v) : $t(jasrek_lisa_value($k, $v, 'Kyllä')), $t($nimi)),
                    $tunnus ? array($t($m->tunnus)) : array(),
                    array($X::text(jasrek_tila_nimi($m->tila), $ero ? $X::S_WARN : $X::S_TEXT), $t($m->email), $t($m->puhelin))
                ));
            }
        }
        $row[] = $t($m->lisatiedot);
        $m_rows[] = $row;
    }

    // Laskut ja laskurivit
    $laskut = $wpdb->get_results("SELECT * FROM {$wpdb->jasrek_laskut} ORDER BY laskupvm DESC, id DESC");
    $l_rows = array(); $r_rows = array(); $a_rows = array(); $e_avoin_kaikki = 0; $n_rivit = 0;
    foreach ($laskut as $l) {
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT r.*, m.etunimi, m.sukunimi, m.tunnus, m.puhelin, m.paperilasku, COALESCE(NULLIF(m.email, ''), r.email) AS to_email FROM {$wpdb->jasrek_rivit} r LEFT JOIN {$wpdb->jasrek_jasenet} m ON m.id = r.jasen_id WHERE r.lasku_id = %d ORDER BY m.sukunimi, m.etunimi, r.nimi", $l->id
        ));
        jasrek_sort_by_name($rows);
        $n = 0; $n_mak = 0; $n_era = 0; $e_lask = 0; $e_post = 0; $e_mak = 0; $e_avoin = 0;
        foreach ($rows as $r) {
            $p = jasrek_rivi_person($r);
            $era = jasrek_rivi_era($l, $r);
            $n++; $n_rivit++; $e_lask += $r->summa; $e_post += jasrek_rivi_postimaksu($r);
            $paid = '';
            if ($r->maksettu) {
                $n_mak++;
                $paid = $r->maksettu_summa !== null ? (float) $r->maksettu_summa : (float) $r->summa;
                $e_mak += $paid;
                $poikkeaa = abs($paid - (float) $r->summa) > 0.004;
                $tila = $X::text($poikkeaa ? 'Maksettu (eri summa)' : 'Maksettu', $poikkeaa ? $X::S_WARN : $X::S_OK);
            } else {
                $e_avoin += $r->summa;
                if ($era < $today) { $n_era++; $tila = $X::text('Erääntynyt', $X::S_ERR); }
                else { $tila = $X::text('Avoin'); }
            }
            $tapa = jasrek_is_paperi($r) ? 'Paperi' : 'Sähköposti';
            $r_rows[] = array_merge(
                array($t($l->nimi), $t($p['nimi'])), $tunnus ? array($t($p['tunnus'])) : array(),
                array($t(jasrek_laji_nimi((string) $r->jasenlaji)), $t($tapa), $t($r->to_email), $t(jasrek_viite_format($r->viite)),
                    $X::money($r->summa), jasrek_rivi_postimaksu($r) > 0 ? $X::money(jasrek_rivi_postimaksu($r)) : '', $d($era), $tila,
                    $d($r->maksettu), $paid !== '' ? $X::money($paid) : '', $t($r->maksaja), $t($r->arkistotunnus),
                    $t($aika($r->lahetetty)), (int) $r->muistutuksia ?: '', $t($aika($r->muistutettu)))
            );
            if (!$r->maksettu) {
                $a_rows[] = array_merge(
                    array($t($l->nimi), $t($p['nimi'])), $tunnus ? array($t($p['tunnus'])) : array(),
                    array($t(jasrek_laji_nimi((string) $r->jasenlaji)), $t($tapa), $t($r->to_email), $t($r->puhelin), $t(jasrek_viite_format($r->viite)),
                        $X::money($r->summa), $d($era), $tila, (int) $r->muistutuksia ?: '', $t($aika($r->muistutettu)))
                );
            }
        }
        $e_avoin_kaikki += $e_avoin;
        $l_rows[] = array($t($l->nimi), $d($l->laskupvm), $d($l->erapaiva), $n, $n_mak, $n - $n_mak, $n_era,
            $X::money($e_lask), $X::money($e_post), $X::money($e_mak), $X::money($e_avoin));
    }

    // Muutosloki
    $loki = $wpdb->get_results("SELECT * FROM {$wpdb->jasrek_loki} ORDER BY aika DESC, id DESC");
    $toim = jasrek_log_toiminnot();
    $kohteet = jasrek_log_kohteet();
    $o_rows = array();
    foreach ($loki as $e) {
        $muut = '';
        $j = $e->muutokset ? json_decode($e->muutokset, true) : null;
        if (is_array($j)) {
            if (isset($j['nimet'])) {
                $muut = implode(', ', (array) $j['nimet']);
            } else {
                $osat = array();
                foreach ($j as $c) {
                    if (!is_array($c) || count($c) < 3) continue;
                    $osat[] = $c[0] . ': ' . ((string) $c[1] !== '' ? $c[1] . ' → ' : '') . ((string) $c[2] !== '' ? $c[2] : '(tyhjä)');
                }
                $muut = implode("\n", $osat);
            }
        }
        $o_rows[] = array($t($aika($e->aika)), $t($e->kayttaja), $t(isset($kohteet[$e->kohde]) && $e->kohde !== '' ? $kohteet[$e->kohde] : $e->kohde),
            $t(isset($toim[$e->toiminto]) ? $toim[$e->toiminto] : $e->toiminto), $t($e->kuvaus), $t(function_exists('mb_substr') ? mb_substr($muut, 0, 32000) : substr($muut, 0, 32000)));
    }

    // 1. Yhteenveto
    $voim = isset($n_tila['aktiivinen']) ? $n_tila['aktiivinen'] : 0;
    $yht = array(
        array($t('Yhdistys'), $t($s['yhdistys'])),
        array($t('Viety'), $t(jasrek_date($today) . ' ' . current_time('H:i'))),
        array($t('Jäseniä (voimassa)'), $voim),
        array($t('Eronneita'), count($jasenet) - $voim),
    );
    foreach ($lajit as $lid => $la) {
        $yht[] = array($t('– ' . $la['nimi']), isset($n_laji[$lid . '|v']) ? $n_laji[$lid . '|v'] : 0);
    }
    $yht[] = array($t('Laskuja'), count($laskut));
    $yht[] = array($t('Laskurivejä (jäsenille lähetetyt laskut)'), $n_rivit);
    $yht[] = array($t('Avoimia maksuja'), count($a_rows));
    $yht[] = array($t('Avoinna yhteensä €'), $X::money($e_avoin_kaikki));
    $yht[] = array($t('Muutoslokin tapahtumia'), count($loki));
    $yht[] = array('', '');
    $yht[] = array($t('Huom.'), $t('Tämä tiedosto on luettavaksi ja arkistoon. Tietojen palauttamiseen käytetään varmuuskopiota (Asetukset → Varmuuskopio).'));
    $x->add_sheet('Yhteenveto', array('Tieto', 'Arvo'), $yht, array(38, 60));

    // 2. Jäsenet
    $x->add_sheet('Jäsenet',
        array_merge(array('ID', 'Etunimi', 'Sukunimi'), $tn,
            array('Jäsenluokka', 'Jäsenyys', 'Osoite', 'Puhelin', 'Sähköposti', 'Liittynyt', 'Syntynyt', 'Eronnut', 'Paperilasku'),
            array_values(array_map(function ($k) { return $k['nimi']; }, $kentat)), array('Lisätiedot')),
        $m_rows,
        array_merge(array(6, 16, 18), $tunnus ? array(12) : array(), array(16, 11, 40, 16, 32, 12, 11, 12, 11),
            array_fill(0, count($kentat), 16), array(50)));

    // 3. Lisäkentät omille välilehdilleen (esim. avaimet), vain täytetyt
    foreach ($kentat as $kid => $k) {
        if (empty($lisa_rows[$kid])) continue;
        $rows = $lisa_rows[$kid];
        usort($rows, function ($a, $b) { return strnatcasecmp($a['v'], $b['v']); });
        $x->add_sheet($k['nimi'],
            array_merge(array($k['nimi'], 'Nimi'), $tn, array('Jäsenyys', 'Sähköposti', 'Puhelin')),
            array_map(function ($r) { return $r['row']; }, $rows),
            array_merge(array(18, 26), $tunnus ? array(12) : array(), array(11, 32, 16)));
    }

    // 4. Jäsenluokat
    $j_rows = array();
    foreach ($lajit as $lid => $la) {
        $j_rows[] = array($t($la['nimi']), $X::money($la['summa']),
            isset($n_laji[$lid . '|v']) ? $n_laji[$lid . '|v'] : 0, isset($n_laji[$lid . '|e']) ? $n_laji[$lid . '|e'] : 0);
    }
    $x->add_sheet('Jäsenluokat', array('Jäsenluokka', 'Jäsenmaksu €', 'Jäseniä (voimassa)', 'Eronneita'), $j_rows, array(24, 14, 18, 12));

    // 5. Laskut, 6. Laskurivit, 7. Avoimet maksut
    $x->add_sheet('Laskut',
        array('Lasku', 'Laskun pvm', 'Eräpäivä', 'Laskuja', 'Maksettu', 'Avoinna', 'Erääntynyt', 'Laskutettu €', 'Josta postimaksuja €', 'Maksettu €', 'Avoinna €'),
        $l_rows, array(30, 12, 12, 9, 10, 9, 11, 14, 18, 14, 14),
        count($l_rows) > 1 ? array($X::text('Yhteensä', $X::S_BOLD), '', '',
            $X::formula('SUM(D2:D{last})', $X::S_BOLD), $X::formula('SUM(E2:E{last})', $X::S_BOLD),
            $X::formula('SUM(F2:F{last})', $X::S_BOLD), $X::formula('SUM(G2:G{last})', $X::S_BOLD),
            $X::formula('SUM(H2:H{last})', $X::S_BOLD_MONEY), $X::formula('SUM(I2:I{last})', $X::S_BOLD_MONEY),
            $X::formula('SUM(J2:J{last})', $X::S_BOLD_MONEY), $X::formula('SUM(K2:K{last})', $X::S_BOLD_MONEY)) : null);

    $o = $tunnus ? 1 : 0;
    $c = function ($i) use ($o) { return chr(65 + $i + $o); };
    $x->add_sheet('Laskurivit',
        array_merge(array('Lasku', 'Nimi'), $tn, array('Jäsenluokka', 'Laskutapa', 'Sähköposti', 'Viite', 'Summa', 'Postimaksu', 'Eräpäivä', 'Tila',
            'Maksupäivä', 'Maksettu €', 'Maksaja', 'Arkistotunnus', 'Lähetetty', 'Muistutuksia', 'Viim. muistutus')),
        $r_rows, array_merge(array(24, 24), $tunnus ? array(12) : array(), array(16, 11, 30, 14, 11, 11, 12, 20, 12, 12, 22, 20, 16, 12, 16)),
        $r_rows ? array_merge(array($X::text('Yhteensä', $X::S_BOLD), ''), $tunnus ? array('') : array(), array('', '', '', '',
            $X::formula('SUM(' . $c(6) . '2:' . $c(6) . '{last})', $X::S_BOLD_MONEY), $X::formula('SUM(' . $c(7) . '2:' . $c(7) . '{last})', $X::S_BOLD_MONEY), '', '', '',
            $X::formula('SUM(' . $c(11) . '2:' . $c(11) . '{last})', $X::S_BOLD_MONEY))) : null);

    $x->add_sheet('Avoimet maksut',
        array_merge(array('Lasku', 'Nimi'), $tn, array('Jäsenluokka', 'Laskutapa', 'Sähköposti', 'Puhelin', 'Viite', 'Summa', 'Eräpäivä', 'Tila', 'Muistutuksia', 'Viim. muistutus')),
        $a_rows, array_merge(array(24, 24), $tunnus ? array(12) : array(), array(16, 11, 30, 16, 14, 11, 12, 12, 12, 16)),
        $a_rows ? array_merge(array($X::text('Yhteensä', $X::S_BOLD), ''), $tunnus ? array('') : array(), array('', '', '', '', '',
            $X::formula('SUM(' . $c(7) . '2:' . $c(7) . '{last})', $X::S_BOLD_MONEY))) : null);

    // 8. Muutosloki
    $x->add_sheet('Muutosloki', array('Aika', 'Käyttäjä', 'Kohde', 'Toiminto', 'Kuvaus', 'Muutokset'), $o_rows, array(16, 18, 20, 26, 50, 60));

    $bin = $x->build();
    jasrek_log('vienti', 0, 'vienti', 'Kaikki tiedot Exceliin (' . count($jasenet) . ' jäsentä, ' . count($laskut) . ' laskua)');
    nocache_headers();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="jasenrekisteri-kaikki_' . jasrek_file_stamp() . '.xlsx"');
    header('Content-Length: ' . strlen($bin));
    echo $bin; // phpcs:ignore -- binääritiedosto
    exit;
}
