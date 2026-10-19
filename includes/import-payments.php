<?php
if (!defined('ABSPATH')) exit;
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The plugin's own custom tables (members, invoices, audit log). The data is admin-only and changes on every write, so object caching is not used.

/*
 * Maksujen tuonti pankin tiedostosta ja kohdistus laskuihin viitteen perusteella.
 * Tuetut muodot:
 *  - KTL-viitesiirtoaineisto (.txt, kiinteä tietuepituus 90 merkkiä)
 *  - ISO 20022 camt.054 (viiteaineisto) ja camt.053 (tiliote), XML
 *  - Pankkien CSV-tiliotteet (sarakkeet tunnistetaan otsikoista)
 */

/* ------------------------------------------------------------------
 * Jäsentimet
 * ------------------------------------------------------------------ */

function jasrek_to_utf8($s) {
    $s = preg_replace('/^\xEF\xBB\xBF/', '', $s);
    $utf8 = function_exists('mb_check_encoding') ? mb_check_encoding($s, 'UTF-8') : (preg_match('//u', $s) === 1);
    if (!$utf8) {
        if (function_exists('mb_convert_encoding')) {
            $s = mb_convert_encoding($s, 'UTF-8', 'Windows-1252');
        } elseif (function_exists('iconv') && ($conv = iconv('Windows-1252', 'UTF-8//IGNORE', $s)) !== false) {
            $s = $conv;
        } else {
            // Ilman mbstringiä ja iconvia: Latin-1 / Windows-1252 tavu kerrallaan (ä, ö, å ja €).
            $out = '';
            for ($i = 0, $n = strlen($s); $i < $n; $i++) {
                $c = ord($s[$i]);
                if ($c < 0x80) $out .= $s[$i];
                elseif ($c === 0x80) $out .= "\xE2\x82\xAC";
                elseif ($c >= 0xA0) $out .= chr(0xC0 | ($c >> 6)) . chr(0x80 | ($c & 0x3F));
                else $out .= '?';
            }
            $s = $out;
        }
    }
    return $s;
}

function jasrek_parse_date_any($s) {
    $s = trim($s);
    if (preg_match('/^(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})/', $s, $m)) $d = array($m[1], $m[2], $m[3]);
    elseif (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})/', $s, $m)) $d = array($m[3], $m[2], $m[1]);
    elseif (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{2})$/', $s, $m)) $d = array('20' . $m[3], $m[2], $m[1]);
    elseif (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $s, $m)) $d = array($m[1], $m[2], $m[3]);
    elseif (preg_match('/^(\d{2})(\d{2})(\d{2})$/', $s, $m)) $d = array('20' . $m[1], $m[2], $m[3]);
    else return null;
    if (!checkdate((int) $d[1], (int) $d[2], (int) $d[0])) return null;
    return sprintf('%04d-%02d-%02d', $d[0], $d[1], $d[2]);
}

/** Palauttaa array('muoto' => ..., 'maksut' => array(...)). Jokainen maksu: pvm, summa, viite, maksaja, arkisto. */
function jasrek_parse_payment_file($content) {
    $content = jasrek_to_utf8((string) $content);
    $trim = ltrim($content);

    if ($trim !== '' && $trim[0] === '<') {
        return array('muoto' => 'ISO 20022 XML (camt)', 'maksut' => jasrek_parse_camt($trim));
    }

    $lines = preg_split('/\r\n|\r|\n/', $content);
    $ktl = 0;
    foreach ($lines as $l) {
        if (strlen($l) >= 87 && ($l[0] === '3' || $l[0] === '5') && ctype_digit(substr($l, 77, 10))) $ktl++;
    }
    if ($ktl > 0) {
        return array('muoto' => 'KTL-viitesiirtoaineisto', 'maksut' => jasrek_parse_ktl($lines));
    }
    return array('muoto' => 'CSV-tiliote', 'maksut' => jasrek_parse_bank_csv($lines));
}

function jasrek_parse_ktl($lines) {
    $out = array();
    foreach ($lines as $l) {
        if (strlen($l) < 87 || ($l[0] !== '3' && $l[0] !== '5')) continue;
        $oikaisu = $l[0] === '5' || substr($l, 87, 1) === '1';
        $kirjaus = jasrek_parse_date_any(substr($l, 15, 6));
        $out[] = array(
            'pvm'     => $kirjaus ? $kirjaus : jasrek_parse_date_any(substr($l, 21, 6)),
            'arkisto' => trim(substr($l, 27, 16)),
            'viite'   => trim(substr($l, 43, 20)),
            'maksaja' => trim(substr($l, 63, 12)),
            'summa'   => ((int) substr($l, 77, 10)) / 100 * ($oikaisu ? -1 : 1),
        );
    }
    return $out;
}

function jasrek_parse_camt($xml) {
    // Nimiavaruudet pois, jotta polut toimivat kaikissa camt-versioissa.
    $xml = preg_replace('/\sxmlns(:\w+)?="[^"]*"/', '', $xml);
    $xml = preg_replace('/<(\/?)\w+:/', '<$1', $xml);
    $prev = libxml_use_internal_errors(true);
    $doc = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET);
    libxml_use_internal_errors($prev);
    if (!$doc) return array();

    $out = array();
    foreach ($doc->xpath('//Ntry') as $ntry) {
        if ((string) $ntry->CdtDbtInd !== 'CRDT') continue; // vain saapuvat maksut
        $pvm = jasrek_parse_date_any((string) ($ntry->BookgDt->Dt ?: $ntry->BookgDt->DtTm ?: $ntry->ValDt->Dt));
        $txs = $ntry->xpath('NtryDtls/TxDtls');
        if (!$txs) $txs = array($ntry);
        foreach ($txs as $tx) {
            $amt = (string) ($tx->AmtDtls->TxAmt->Amt ?: $tx->Amt ?: $ntry->Amt);
            $ref = (string) ($tx->RmtInf->Strd->CdtrRefInf->Ref ?: '');
            if ($ref === '' && isset($tx->RmtInf->Ustrd)) {
                // Joskus viite on vapaassa viestikentässä
                if (preg_match('/\b(RF\d{2}[\dA-Z]{1,21}|\d{4,20})\b/', preg_replace('/\s+/', '', (string) $tx->RmtInf->Ustrd), $m)) $ref = $m[1];
            }
            $nimi = (string) ($tx->RltdPties->Dbtr->Nm ?: $tx->RltdPties->Dbtr->Pty->Nm ?: '');
            $out[] = array(
                'pvm'     => $pvm,
                'arkisto' => (string) ($tx->Refs->AcctSvcrRef ?: $ntry->AcctSvcrRef ?: ''),
                'viite'   => $ref,
                'maksaja' => $nimi,
                'summa'   => (float) $amt,
            );
        }
    }
    return $out;
}

function jasrek_parse_bank_csv($lines) {
    $lines = array_values(array_filter($lines, function ($l) { return trim($l) !== ''; }));
    if (count($lines) < 2) return array();

    // Erotin: yleisin otsikkorivillä
    $head = $lines[0];
    $delim = ';';
    $best = 0;
    foreach (array(';', ',', "\t") as $d) {
        $c = substr_count($head, $d);
        if ($c > $best) { $best = $c; $delim = $d; }
    }
    $cols = array_map(function ($h) {
        return strtolower(remove_accents(trim($h, " \"'")));
    }, str_getcsv($head, $delim));

    $find = function ($patterns) use ($cols) {
        foreach ($patterns as $p) {
            foreach ($cols as $i => $c) if (preg_match($p, $c)) return $i;
        }
        return null;
    };
    $i_ref  = $find(array('/^viite/', '/viitenumero/', '/reference/', '/^ref/'));
    $i_amt  = $find(array('/^maara/', '/maara/', '/summa/', '/amount/', '/euro/'));
    $i_date = $find(array('/kirjauspaiva/', '/maksupaiva/', '/arvopaiva/', '/paivamaara/', '/^pvm/', '/date/'));
    $i_name = $find(array('/maksaja/', '/saaja\/maksaja/', '/^nimi/', '/payer/', '/name/'));
    $i_arch = $find(array('/arkist/', '/archive/'));
    if ($i_ref === null || $i_amt === null) return array();

    $out = array();
    for ($k = 1; $k < count($lines); $k++) {
        $row = str_getcsv($lines[$k], $delim);
        if (!isset($row[$i_amt])) continue;
        $amt = str_replace(array(' ', "\xC2\xA0", '+', '€'), '', $row[$i_amt]);
        $pc = strrpos($amt, ','); $pd = strrpos($amt, '.');
        if ($pc !== false && ($pd === false || $pc > $pd)) {
            $amt = str_replace(array('.', ','), array('', '.'), $amt);   // 1.234,50 → 1234.50
        } else {
            $amt = str_replace(',', '', $amt);                         // 1,234.50 → 1234.50
        }
        if (!is_numeric($amt)) continue;
        $out[] = array(
            'pvm'     => $i_date !== null && isset($row[$i_date]) ? jasrek_parse_date_any($row[$i_date]) : null,
            'arkisto' => $i_arch !== null && isset($row[$i_arch]) ? trim($row[$i_arch]) : '',
            'viite'   => isset($row[$i_ref]) ? trim($row[$i_ref]) : '',
            'maksaja' => $i_name !== null && isset($row[$i_name]) ? trim($row[$i_name]) : '',
            'summa'   => (float) $amt,
        );
    }
    return $out;
}

/* ------------------------------------------------------------------
 * Kohdistus
 * ------------------------------------------------------------------ */

/**
 * Lisää jokaiseen maksuun: tila (ok|ero|maksettu|kirjattu|tuntematon|ohita), rivi (laskurivi), viesti.
 */
function jasrek_match_payments($maksut) {
    global $wpdb;
    $refs = array(); $archs = array();
    foreach ($maksut as $p) {
        $n = jasrek_normalize_viite($p['viite']);
        if ($n !== '') $refs[$n] = true;
        if ($p['arkisto'] !== '') $archs[$p['arkisto']] = true;
    }
    $rows = array();
    if ($refs) {
        $found = $wpdb->get_results($wpdb->prepare(
            "SELECT r.*, m.etunimi, m.sukunimi, m.tunnus, l.nimi AS lasku_nimi FROM {$wpdb->jasrek_rivit} r LEFT JOIN {$wpdb->jasrek_jasenet} m ON m.id = r.jasen_id JOIN {$wpdb->jasrek_laskut} l ON l.id = r.lasku_id WHERE r.viite IN (" . implode(',', array_fill(0, count($refs), '%s')) . ')',
            array_keys($refs)
        ));
        foreach ($found as $r) $rows[$r->viite] = $r;
    }
    $used_arch = array();
    if ($archs) {
        $used_arch = array_flip($wpdb->get_col($wpdb->prepare(
            "SELECT arkistotunnus FROM {$wpdb->jasrek_rivit} WHERE arkistotunnus IN (" . implode(',', array_fill(0, count($archs), '%s')) . ')',
            array_keys($archs)
        )));
    }

    $seen = array();
    foreach ($maksut as &$p) {
        $n = jasrek_normalize_viite($p['viite']);
        $p['rivi'] = isset($rows[$n]) ? $rows[$n] : null;
        if ($p['summa'] <= 0) {
            $p['tila'] = 'ohita'; $p['viesti'] = 'Oikaisu tai veloitus – ei käsitellä';
        } elseif ($p['arkisto'] !== '' && isset($used_arch[$p['arkisto']])) {
            $p['tila'] = 'kirjattu'; $p['viesti'] = 'Tämä maksu on jo tuotu aiemmin';
        } elseif (!$p['rivi']) {
            $p['tila'] = 'tuntematon';
            $p['viesti'] = $n === '' ? 'Ei viitettä' : (jasrek_viite_tarkiste(substr($n, 0, -1)) !== (int) substr($n, -1) ? 'Viitteen tarkiste ei täsmää' : 'Viitettä ei löydy laskuilta');
        } elseif ($p['rivi']->maksettu || isset($seen[$n])) {
            $p['tila'] = 'maksettu';
            $p['viesti'] = $p['rivi']->maksettu ? 'Merkitty jo maksetuksi ' . jasrek_date($p['rivi']->maksettu) : 'Sama viite toistamiseen tiedostossa';
        } elseif (abs((float) $p['rivi']->summa - $p['summa']) > 0.004) {
            $p['tila'] = 'ero';
            $p['viesti'] = 'Summa poikkeaa: laskulla ' . jasrek_money($p['rivi']->summa);
        } else {
            $p['tila'] = 'ok'; $p['viesti'] = 'Täsmää';
        }
        if ($n !== '' && $p['rivi']) $seen[$n] = true;
    }
    unset($p);
    return $maksut;
}

/* ------------------------------------------------------------------
 * Käsittelijät
 * ------------------------------------------------------------------ */

add_action('admin_post_jasrek_payments_upload', 'jasrek_handle_payments_upload');
function jasrek_handle_payments_upload() {
    jasrek_check('jasrek_payments_upload');
    $f = jasrek_uploaded_file('tiedosto');
    if (!$f || $f['size'] > 5 * MB_IN_BYTES) {
        jasrek_redirect('jasrek-laskutus', array('jasrek_msg' => 'upload_failed'));
    }
    $parsed = jasrek_parse_payment_file(file_get_contents($f['tmp_name']));
    if (!$parsed['maksut']) {
        jasrek_redirect('jasrek-laskutus', array('jasrek_msg' => 'no_payments'));
    }
    $parsed['tiedosto'] = $f['name'];
    $parsed['gen'] = jasrek_data_gen();
    set_transient('jasrek_payimp_' . get_current_user_id(), $parsed, HOUR_IN_SECONDS);
    jasrek_redirect('jasrek-laskutus', array('view' => 'maksutuonti'));
}

add_action('admin_post_jasrek_payments_confirm', 'jasrek_handle_payments_confirm');
function jasrek_handle_payments_confirm() {
    jasrek_check('jasrek_payments_confirm');
    global $wpdb;
    $key = 'jasrek_payimp_' . get_current_user_id();
    $parsed = get_transient($key);
    if (!$parsed) jasrek_redirect('jasrek-laskutus', array('jasrek_msg' => 'no_payments'));
    if (!isset($parsed['gen']) || $parsed['gen'] !== jasrek_data_gen()) {
        delete_transient($key);
        jasrek_redirect('jasrek-laskutus', array('jasrek_msg' => 'data_restored'));
    }

    $valitut = array_flip(array_map('intval', jasrek_post_array('valitut')));
    $maksut = jasrek_match_payments($parsed['maksut']); // tarkistetaan uudelleen
    $n = 0; $maksetut = array();
    foreach ($maksut as $i => $p) {
        if (!isset($valitut[$i]) || !$p['rivi'] || !in_array($p['tila'], array('ok', 'ero'), true)) continue;
        $ok = $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->jasrek_rivit} SET maksettu = %s, maksettu_summa = %f, maksaja = %s, arkistotunnus = %s WHERE id = %d AND maksettu IS NULL",
            $p['pvm'] ? $p['pvm'] : current_time('Y-m-d'), $p['summa'],
            function_exists('mb_substr') ? mb_substr($p['maksaja'], 0, 100) : substr($p['maksaja'], 0, 100),
            substr($p['arkisto'], 0, 40), $p['rivi']->id
        ));
        if ($ok) {
            $n++; $maksetut[] = (int) $p['rivi']->id;
            jasrek_log_rivi($p['rivi'], 'maksettu', 'pankkitiedosto ' . $parsed['tiedosto'] . ', maksupäivä ' . jasrek_date($p['pvm'])
                . ($p['tila'] === 'ero' ? ', maksettu ' . html_entity_decode(jasrek_money($p['summa'])) : '') . ($p['maksaja'] !== '' ? ', maksaja ' . $p['maksaja'] : ''));
        }
    }
    if ($n) jasrek_log('vienti', 0, 'tuonti', 'Maksut tuotu pankin tiedostosta ' . $parsed['tiedosto'] . ' (' . $parsed['muoto'] . '): ' . $n . ' laskua merkitty maksetuksi');
    delete_transient($key);
    $v = (jasrek_vahvistus_on() && jasrek_post('vahvistus') === '1') ? jasrek_queue_vahvistukset($maksetut) : 0;
    jasrek_redirect('jasrek-laskutus', array('jasrek_msg' => 'payments_imported', 'jasrek_n' => $n, 'jasrek_v' => $v));
}

/* ------------------------------------------------------------------
 * Näkymät
 * ------------------------------------------------------------------ */

function jasrek_payments_upload_panel() {
    ?>
    <div class="jasrek-panel" id="jasrek-maksutuonti">
        <h2>Tuo maksut pankista</h2>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" class="jasrek-upload">
            <input type="hidden" name="action" value="jasrek_payments_upload">
            <?php wp_nonce_field('jasrek_payments_upload'); ?>
            <input type="file" name="tiedosto" accept=".txt,.xml,.csv,.dat,.nda" required>
            <button type="submit" class="button button-primary">Lue tiedosto</button>
        </form>
        <p class="description">Lataa verkkopankista <strong>viitesiirtoaineisto</strong> (KTL, .txt), <strong>camt.054</strong>- tai <strong>camt.053</strong>-tiedosto (XML) tai <strong>tiliote CSV-muodossa</strong>. Maksut kohdistetaan laskuihin viitteen perusteella – maksajan nimellä ei ole väliä. Näet ennen tallennusta, mitä merkitään maksetuksi. Samaa maksua ei kirjata kahdesti.</p>
    </div>
    <?php
}

function jasrek_payments_preview() {
    $parsed = get_transient('jasrek_payimp_' . get_current_user_id());
    echo '<h1>Maksujen tuonti</h1>';
    echo '<p><a href="' . esc_url(admin_url('admin.php?page=jasrek-laskutus')) . '">&larr; Takaisin laskuihin</a></p>';
    if (!$parsed) {
        echo '<p>Tuontia ei löytynyt tai se on vanhentunut. Lataa tiedosto uudelleen.</p>';
        return;
    }
    $maksut = jasrek_match_payments($parsed['maksut']);
    $c = array('ok' => 0, 'ero' => 0, 'maksettu' => 0, 'kirjattu' => 0, 'tuntematon' => 0, 'ohita' => 0);
    $sum_ok = 0;
    foreach ($maksut as $p) { $c[$p['tila']]++; if ($p['tila'] === 'ok') $sum_ok += $p['summa']; }
    $badge = array('ok' => 'ok', 'ero' => 'orange', 'maksettu' => 'muted', 'kirjattu' => 'muted', 'tuntematon' => 'warn', 'ohita' => 'muted');
    ?>
    <p><?php echo esc_html($parsed['tiedosto'] . ' · ' . $parsed['muoto'] . ' · ' . count($maksut) . ' tapahtumaa'); ?></p>
    <div class="jasrek-cards">
        <div class="jasrek-card jasrek-card-ok"><span class="jasrek-card-num"><?php echo esc_html($c['ok']); ?></span><span class="jasrek-card-label">Täsmää (<?php echo esc_html(jasrek_money($sum_ok)); ?>)</span></div>
        <div class="jasrek-card jasrek-card-warn"><span class="jasrek-card-num"><?php echo esc_html($c['ero']); ?></span><span class="jasrek-card-label">Summa poikkeaa – tarkista</span></div>
        <div class="jasrek-card"><span class="jasrek-card-num"><?php echo esc_html($c['maksettu'] + $c['kirjattu']); ?></span><span class="jasrek-card-label">Jo kirjattu</span></div>
        <div class="jasrek-card"><span class="jasrek-card-num"><?php echo esc_html($c['tuntematon'] + $c['ohita']); ?></span><span class="jasrek-card-label">Ei kohdistu jäsenmaksuihin</span></div>
    </div>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="jasrek_payments_confirm">
        <?php wp_nonce_field('jasrek_payments_confirm'); ?>
        <table class="widefat striped jasrek-table jasrek-import-table" id="jasrek-payrows">
            <thead><tr>
                <td class="check-column"><input type="checkbox" class="jasrek-check-all" data-target="#jasrek-payrows"></td>
                <th>Päivä</th><th>Maksaja</th><th>Viite</th><th>Summa</th><th>Jäsen ja lasku</th><th>Tila</th>
            </tr></thead>
            <tbody>
            <?php foreach ($maksut as $i => $p):
                $can = in_array($p['tila'], array('ok', 'ero'), true);
                $person = $p['rivi'] ? jasrek_rivi_person($p['rivi']) : null; ?>
                <tr class="<?php echo $p['tila'] === 'ok' ? 'jasrek-row-ok' : ''; ?>" data-search="x">
                    <th class="check-column"><?php if ($can): ?><input type="checkbox" name="valitut[]" value="<?php echo (int) $i; ?>" <?php checked($p['tila'], 'ok'); ?>><?php endif; ?></th>
                    <td><?php echo esc_html(jasrek_date($p['pvm'])); ?></td>
                    <td><?php echo esc_html($p['maksaja']); ?></td>
                    <td><code class="jasrek-ref"><?php echo esc_html($p['viite'] !== '' ? (stripos($p['viite'], 'RF') === 0 ? $p['viite'] : jasrek_viite_format(ltrim($p['viite'], '0'))) : '–'); ?></code></td>
                    <td><?php echo esc_html(jasrek_money($p['summa'])); ?></td>
                    <td><?php if ($person): ?><strong><?php echo esc_html($person['nimi']); ?></strong>
                            <?php if ($person['tunnus'] && jasrek_tunnus_on()): ?><span class="jasrek-call"><?php echo esc_html($person['tunnus']); ?></span><?php endif; ?>
                            <div class="jasrek-sub"><?php echo esc_html($p['rivi']->lasku_nimi); ?></div>
                        <?php else: ?><span class="jasrek-muted">–</span><?php endif; ?></td>
                    <td><span class="jasrek-badge jasrek-badge-<?php echo esc_attr($badge[$p['tila']]); ?>"><?php echo esc_html($p['viesti']); ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php jasrek_vahvistus_checkbox(); ?>
        <p class="jasrek-actions">
            <button type="submit" class="button button-primary button-large">Merkitse valitut maksetuiksi</button>
            <a class="button button-large" href="<?php echo esc_url(admin_url('admin.php?page=jasrek-laskutus')); ?>">Peruuta</a>
            <span class="description">Täsmäävät on valittu valmiiksi. Jos summa poikkeaa (esim. osamaksu), valitse rivi itse, jos haluat kuitata laskun maksetuksi. Maksupäivä, maksaja ja maksettu summa tallennetaan laskulle.</span>
        </p>
    </form>
    <?php
}
