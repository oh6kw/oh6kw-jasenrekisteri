<?php
if (!defined('ABSPATH')) exit;
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The plugin's own custom tables (members, invoices, audit log). The data is admin-only and changes on every write, so object caching is not used.

/*
 * Paperilaskut: tulostettava A4-lasku ikkunakuoreen (C5 tai DL, ikkuna vasemmalla ylhäällä)
 * ja postitetuksi merkitseminen.
 */

/** Tulostusnäkymän osoite. $args: rivi, joukko (postittamattomat|muistutukset|kaikki), muistutus, tulosta. */
function jasrek_print_url($lasku_id, $args = array()) {
    return wp_nonce_url(add_query_arg(array_merge(array('action' => 'jasrek_print', 'id' => (int) $lasku_id), $args), admin_url('admin-post.php')), 'jasrek_print_' . (int) $lasku_id);
}

/** Laskun paperilaskurivit valitun joukon mukaan. */
function jasrek_paper_rows($lasku, $joukko) {
    global $wpdb;
    $rows = jasrek_get_rivit('WHERE r.lasku_id = %d ORDER BY m.sukunimi, m.etunimi, r.nimi', array($lasku->id));
    jasrek_sort_by_name($rows);
    $today = current_time('Y-m-d');
    return array_values(array_filter($rows, function ($r) use ($lasku, $joukko, $today) {
        if (!jasrek_is_paperi($r)) return false;
        if ($joukko === 'postittamattomat') return !$r->lahetetty;
        if ($joukko === 'muistutukset') return $r->lahetetty && !$r->maksettu && jasrek_rivi_era($lasku, $r) < $today;
        return true;
    }));
}

add_action('admin_post_jasrek_print', 'jasrek_handle_print');
function jasrek_handle_print() {
    $id = jasrek_get_int('id');
    jasrek_check('jasrek_print_' . $id);
    global $wpdb;
    $lasku = jasrek_get_lasku($id);
    if (!$lasku) wp_die('Laskua ei löytynyt.');

    $rivi   = jasrek_get_int('rivi', 0);
    $joukko = sanitize_key(jasrek_get('joukko', ''));
    $muist  = !empty(jasrek_get('muistutus')) || $joukko === 'muistutukset';
    if ($rivi) {
        $r = jasrek_get_rivit('WHERE r.id = %d AND r.lasku_id = %d', array($rivi, $id), true);
        $rows = $r ? array($r) : array();
    } else {
        $rows = jasrek_paper_rows($lasku, in_array($joukko, array('postittamattomat', 'muistutukset'), true) ? $joukko : 'kaikki');
    }
    nocache_headers();
    header('Content-Type: text/html; charset=UTF-8');
    echo jasrek_print_document($lasku, $rows, $muist, !empty(jasrek_get('tulosta'))); // phpcs:ignore -- valmiiksi escapattu HTML
    exit;
}

add_action('admin_post_jasrek_paper_mark', 'jasrek_handle_paper_mark');
function jasrek_handle_paper_mark() {
    $lasku_id = intval(jasrek_post('lasku_id', 0));
    jasrek_check('jasrek_paper_mark_' . $lasku_id);
    global $wpdb;
    $lasku = jasrek_get_lasku($lasku_id);
    if (!$lasku) jasrek_redirect('jasrek-laskutus');
    $ids = jasrek_post_ids('rivit');
    if (!$ids) jasrek_redirect('jasrek-laskutus', array('view' => 'lasku', 'id' => $lasku_id, 'jasrek_msg' => 'nothing_selected'));
    $muist = jasrek_post('tapa') === 'muistutus';
    $now = current_time('mysql');
    $n = 0;
    $rows = jasrek_get_rivit('WHERE r.lasku_id = %d AND r.id IN (' . jasrek_in_placeholders($ids) . ')', array_merge(array($lasku_id), $ids));
    foreach ($rows as $r) {
        if ($muist) {
            if ($r->maksettu) continue;
            $upd = array('muistutettu' => $now, 'muistutuksia' => (int) $r->muistutuksia + 1, 'viim_yritys' => $now, 'virhe' => null);
            if (!$r->lahetetty) $upd['lahetetty'] = $now;
        } else {
            $upd = array('lahetetty' => $now, 'viim_yritys' => $now, 'virhe' => null);
        }
        $wpdb->update(jasrek_t('rivit'), $upd, array('id' => $r->id));
        jasrek_log_rivi($r, $muist ? 'muistutus_postitettu' : 'postitettu', 'paperilla', $lasku);
        $n++;
    }
    jasrek_redirect('jasrek-laskutus', array('view' => 'lasku', 'id' => $lasku_id, 'jasrek_msg' => $muist ? 'paper_reminded' : 'paper_marked', 'jasrek_n' => $n));
}

/** Postiosoitteen rivit: nimi ja osoite pilkkujen kohdalta riveiksi. */
function jasrek_address_lines($r) {
    $p = jasrek_rivi_person($r);
    $lines = array($p['nimi']);
    foreach (preg_split('/\s*[,\n]\s*/', (string) (isset($r->osoite) ? $r->osoite : '')) as $l) {
        if (trim($l) !== '') $lines[] = trim($l);
    }
    return $lines;
}

function jasrek_print_document($lasku, $rows, $reminder, $autoprint) {
    $s = jasrek_settings();
    // Sähköpostilaskun saajan lasku (laskun rivilistan Tulosta-painike): ei postitusmerkintää
    $paperi = count(array_filter($rows, 'jasrek_is_paperi')) === count($rows);
    $title = $paperi ? ($reminder ? 'Maksumuistutukset: ' : 'Paperilaskut: ') . $lasku->nimi
        : ($reminder ? 'Maksumuistutus: ' : 'Lasku: ') . $lasku->nimi . (count($rows) === 1 ? ' – ' . jasrek_rivi_person($rows[0])['nimi'] : '');
    $ids = array_map(function ($r) { return (int) $r->id; }, $rows);
    $no_addr = array();
    foreach ($rows as $r) if (count(jasrek_address_lines($r)) < 2) $no_addr[] = jasrek_rivi_person($r)['nimi'];
    $back = admin_url('admin.php?page=jasrek-laskutus&view=lasku&id=' . (int) $lasku->id);

    ob_start(); ?>
<!DOCTYPE html>
<html lang="fi"><head><meta charset="UTF-8"><title><?php echo esc_html($title); ?></title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<?php wp_print_styles('jasrek-print'); ?>
</head>
<body<?php echo $autoprint && $rows ? ' data-jasrek-autoprint="1"' : ''; ?>>
<div class="jasrek-tools">
    <strong><?php echo esc_html($title); ?></strong>
    <span><?php echo count($rows); ?> kpl</span>
    <?php if ($rows): ?>
    <button type="button" data-jasrek-print>🖨 Tulosta</button>
    <?php endif; ?>
    <?php if ($rows && $paperi): ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="jasrek_paper_mark">
        <input type="hidden" name="lasku_id" value="<?php echo (int) $lasku->id; ?>">
        <input type="hidden" name="tapa" value="<?php echo $reminder ? 'muistutus' : 'lasku'; ?>">
        <?php wp_nonce_field('jasrek_paper_mark_' . $lasku->id); ?>
        <?php foreach ($ids as $rid): ?><input type="hidden" name="rivit[]" value="<?php echo (int) $rid; ?>"><?php endforeach; ?>
        <button type="submit" class="jasrek-sec" data-jasrek-confirm="Merkitäänkö <?php echo count($rows); ?> <?php echo $reminder ? 'muistutusta' : 'laskua'; ?> postitetuksi tänään?">✓ Merkitse <?php echo count($rows) === 1 ? 'postitetuksi' : 'kaikki postitetuiksi'; ?></button>
    </form>
    <?php endif; ?>
    <a href="<?php echo esc_url($back); ?>">← Takaisin laskuun</a>
    <?php if ($rows && !$paperi): ?><span class="jasrek-info">Jäsen saa laskunsa sähköpostilla. Tulosta tämä paperille tai tallenna PDF:nä (tulostusikkunassa tulostimeksi <em>Tallenna PDF-tiedostona</em>) ja lähetä jäsenelle omasta sähköpostistasi. Tulostaminen ei muuta laskun tilaa.</span><?php endif; ?>
    <?php if ($rows && $paperi): ?><span class="jasrek-info">Tulosta A4-paperille ilman sivun reunuksia (selaimen tulostusasetus ”Marginaalit: ei mitään” tai ”oletus”, skaalaus 100 %). Taita paperi reunan merkkien kohdalta ja laita ikkunakuoreen (C5 tai DL, ikkuna vasemmalla).<?php echo $s['paperi_malli'] === 'tilisiirto' ? ' Tilisiirtolomake tulostuu sivun alareunaan.' : ''; ?></span><?php endif; ?>
    <span class="jasrek-warn" id="jasrek-ylivuoto" hidden>⚠ Saateteksti ei mahdu kokonaan sivulle tilisiirtolomakkeen yläpuolelle: <span></span>. Lyhennä laskun saatetekstiä tai käytä perusmallia (Asetukset → Paperilaskut).</span>
    <span class="jasrek-warn" id="jasrek-ikkuna-yli" hidden>⚠ Palautusosoite tai vastaanottajan osoite ei mahdu kokonaan kuoren ikkunaan: <span></span>. Lyhennä palautusosoitetta (Asetukset → Paperilaskut) tai jäsenen osoitetta.</span>
    <?php if ($no_addr): ?><span class="jasrek-warn">⚠ Osoite puuttuu: <?php echo esc_html(implode(', ', $no_addr)); ?>. Lisää osoite jäsenen tietoihin ennen tulostusta.</span><?php endif; ?>
</div>
<?php if (!$rows): ?>
    <div class="tyhja"><p>Ei tulostettavia laskuja tässä joukossa.</p><p><a href="<?php echo esc_url($back); ?>">← Takaisin laskuun</a></p></div>
<?php endif; ?>
<?php foreach ($rows as $r) echo jasrek_print_page($lasku, $r, $reminder, $s); // phpcs:ignore ?>
<?php wp_print_scripts('jasrek-print-page'); ?>
</body></html>
    <?php
    return ob_get_clean();
}

/** Yksi A4-sivu valitulla mallilla (Asetukset → Paperilaskut → Laskun malli). */
function jasrek_print_page($lasku, $r, $reminder, $s) {
    if ($s['paperi_malli'] === 'tilisiirto') return jasrek_print_page_tilisiirto($lasku, $r, $reminder, $s);
    return jasrek_print_page_perus($lasku, $r, $reminder, $s);
}

/** Taittomerkit: puolitaitto C5-kuoreen (148,5 mm) ja kolmitaitto DL-kuoreen (99 ja 198 mm). */
function jasrek_print_marks() {
    return '<span class="taitto" style="top:99mm"></span><span class="taitto" style="top:148.5mm"></span><span class="taitto" style="top:198mm"></span>';
}

/**
 * Sivun yläosa (lähettäjä, ikkunakuoren osoite ja laskun tiedot) sekä saate, erittely ja yhteensä.
 * Sama kummassakin mallissa, joten osoite osuu aina samaan kohtaan kuoren ikkunaa.
 */
function jasrek_print_head($lasku, $r, $reminder, $s, $vars, $erittely = true) {
    $text = $reminder ? $s['muistutus_teksti'] : ($lasku->viesti !== '' ? $lasku->viesti : $s['viesti_oletus']);
    $addr = jasrek_address_lines($r);
    $luokka = isset($r->jasenlaji) ? jasrek_laji_nimi((string) $r->jasenlaji) : '';
    // Palautusosoite ikkunaan vain, jos se on annettu (muuten nimi toistuisi turhaan). Enintään kaksi riviä, ks. print.css ja print-page.js.
    $palautus = trim((string) $s['postiosoite']) !== '' ? $s['yhdistys'] . ', ' . preg_replace('/\s*\n\s*/', ', ', trim($s['postiosoite'])) : '';

    $h  = '<div class="ylaosa">';
    $h .= '<div class="lahettaja">' . esc_html($s['yhdistys']) . '</div>';
    $h .= '<div class="ikkuna">' . ($palautus !== '' ? '<div class="palautus">' . esc_html($palautus) . '</div>' : '') . '<div class="vastaanottaja">';
    foreach ($addr as $i => $l) $h .= ($i ? '<br>' : '') . ($i === 0 ? '<strong>' . esc_html($l) . '</strong>' : esc_html($l));
    if (count($addr) < 2) $h .= '<br><span class="puuttuu">(osoite puuttuu)</span>';
    $h .= '</div></div>';
    $h .= '<div class="tiedot"><div class="laji">' . ($reminder ? 'MAKSUMUISTUTUS' : 'LASKU') . '</div><table>';
    $h .= '<tr><td>Päiväys</td><td>' . esc_html($reminder ? jasrek_date(current_time('Y-m-d')) : $vars['laskupvm']) . '</td></tr>';
    if ($reminder) $h .= '<tr><td>Alkuperäinen lasku</td><td>' . esc_html($vars['laskupvm']) . '</td></tr>';
    if (jasrek_tunnus_on() && $vars['tunnus'] !== '') $h .= '<tr><td>' . esc_html(jasrek_tunnus_nimi()) . '</td><td>' . esc_html($vars['tunnus']) . '</td></tr>';
    $h .= '<tr><td>Viitenumero</td><td class="vahva mono">' . esc_html($vars['viite']) . '</td></tr>';
    $h .= '<tr><td>Eräpäivä</td><td class="vahva">' . esc_html($vars['erapaiva']) . '</td></tr>';
    if (jasrek_rivi_postimaksu($r) > 0) {
        $h .= '<tr><td>Jäsenmaksu</td><td>' . esc_html($vars['jasenmaksu']) . '</td></tr>';
        $h .= '<tr><td>Postimaksu</td><td>' . esc_html($vars['postimaksu']) . '</td></tr>';
        $h .= '<tr class="yht"><td>Maksettava</td><td class="vahva">' . esc_html($vars['maksettava']) . '</td></tr>';
    } else {
        $h .= '<tr><td>Summa</td><td class="vahva">' . esc_html($vars['maksettava']) . '</td></tr>';
    }
    $h .= '</table></div></div>';

    $h .= '<div class="saate">' . jasrek_fill($text, $vars) . '</div>';
    $note = jasrek_postimaksu_note($text, $r, $vars);
    if ($note !== '') $h .= '<p class="postihuom">' . esc_html($note) . '</p>';
    if (!$erittely) return $h; // tilisiirtomalli: summat ovat tietolaatikossa ja lomakkeessa
    $h .= '<table class="erittely"><thead><tr><th>Erittely</th><th class="summa">Summa</th></tr></thead><tbody>';
    $h .= '<tr><td>' . esc_html($lasku->nimi) . ' – ' . esc_html($vars['nimi']) . ($luokka !== '' ? ' (' . esc_html($luokka) . ')' : '') . '</td><td class="summa">' . esc_html($vars['jasenmaksu']) . '</td></tr>';
    if (jasrek_rivi_postimaksu($r) > 0) $h .= '<tr><td>Postimaksu (paperilasku)</td><td class="summa">' . esc_html($vars['postimaksu']) . '</td></tr>';
    $h .= '</tbody></table>';
    $h .= '<div class="yhteensa"><span>Maksettava viimeistään ' . esc_html($vars['erapaiva']) . '</span><strong>' . esc_html($vars['maksettava']) . '</strong></div>';
    return $h;
}

/** Perusmalli: maksutiedot, QR-koodi ja pankkiviivakoodi saatteen alla. */
function jasrek_print_page_perus($lasku, $r, $reminder, $s) {
    $vars = jasrek_invoice_vars($lasku, $r);
    $images = jasrek_invoice_images($lasku, $r);
    $vvk = jasrek_virtuaaliviivakoodi($s['iban'], $r->summa, $r->viite, jasrek_rivi_era($lasku, $r));
    $yt = trim(preg_replace('/\s*\n\s*/', ' · ', (string) $s['yhteystiedot']));
    $h  = '<div class="sivu">' . jasrek_print_marks();
    $h .= jasrek_print_head($lasku, $r, $reminder, $s, $vars);
    $h .= '<div class="alaosa"><div class="maksurivi">';
    if (isset($images['jrqr'])) {
        $h .= '<img class="qr" alt="QR-koodi" src="data:image/png;base64,' . base64_encode($images['jrqr']) . '">';
    }
    $h .= '<div class="maksutiedot"><table>'
        . '<tr><td>Saaja</td><td><strong>' . esc_html($s['yhdistys']) . '</strong></td></tr>'
        . '<tr><td>Tilinumero (IBAN)</td><td class="mono"><strong>' . esc_html($vars['iban']) . '</strong></td></tr>'
        . ($s['bic'] !== '' ? '<tr><td>BIC</td><td>' . esc_html($s['bic']) . '</td></tr>' : '')
        . '<tr><td>Viitenumero</td><td class="mono"><strong>' . esc_html($vars['viite']) . '</strong></td></tr>'
        . '<tr><td>Eräpäivä</td><td>' . esc_html($vars['erapaiva']) . '</td></tr>'
        . '<tr><td>Euro</td><td><strong>' . esc_html($vars['maksettava']) . '</strong></td></tr>'
        . '</table>'
        . (isset($images['jrqr']) ? '<div class="huom">Skannaa QR-koodi pankkisovelluksella, niin maksun tiedot täyttyvät valmiiksi.</div>' : '')
        . '</div></div>';
    if (isset($images['jrbar'])) {
        // Kuva on piirretty 2 px/moduuli; tulosteessa 0,3 mm/moduuli
        $px = unpack('N', substr($images['jrbar'], 16, 4))[1];
        $mm = round($px / 2 * 0.3, 1);
        $h .= '<div class="viivakoodi"><img alt="Pankkiviivakoodi" style="width:' . esc_attr($mm) . 'mm" src="data:image/png;base64,' . base64_encode($images['jrbar']) . '"></div>';
    }
    if ($vvk !== '') $h .= '<div class="vvk">Virtuaaliviivakoodi: <span class="mono">' . esc_html($vvk) . '</span></div>';
    $h .= '<div class="huom">Käytäthän maksaessasi viitenumeroa, jotta maksu kohdistuu sinulle.</div>';
    $foot = ($yt !== '' ? $yt : $s['yhdistys']) . ($s['ytunnus'] !== '' ? ' · Y-tunnus ' . $s['ytunnus'] : '');
    $h .= '<div class="alatunniste">' . esc_html($foot) . '</div>';
    $h .= '</div></div>';
    return $h;
}

/** Pankkiviivakoodin kuva tulostusmittoihin (piirretty 2 px/moduuli, tulosteessa 0,3 mm/moduuli). */
function jasrek_print_barcode_img($png) {
    $px = unpack('N', substr($png, 16, 4))[1];
    $mm = round($px / 2 * 0.3, 1);
    return '<img alt="Pankkiviivakoodi" style="width:' . esc_attr($mm) . 'mm" src="data:image/png;base64,' . base64_encode($png) . '">';
}

/** Summa tilisiirtolomakkeelle: 1 234,50 EUR. */
function jasrek_print_eur($amount) {
    return number_format((float) $amount, 2, ',', ' ') . ' EUR';
}

/**
 * Tilisiirtolomakemalli: perinteinen suomalainen pankkisiirtolomake (tilisiirto / girering) sivun alareunassa.
 * Yläosa ja ikkunakuoren osoite ovat samat kuin perusmallissa. QR-koodi (jos valittu) on lomakkeen yläpuolella.
 */
function jasrek_print_page_tilisiirto($lasku, $r, $reminder, $s) {
    $vars = jasrek_invoice_vars($lasku, $r);
    $images = jasrek_invoice_images($lasku, $r);
    $vvk = jasrek_virtuaaliviivakoodi($s['iban'], $r->summa, $r->viite, jasrek_rivi_era($lasku, $r));
    $yt = trim(preg_replace('/\s*\n\s*/', ' · ', (string) $s['yhteystiedot']));
    $saaja = jasrek_saaja_rivit($s);
    $maksaja = jasrek_address_lines($r);
    $br = function ($lines) { return implode('<br>', array_map('esc_html', $lines)); };

    $h  = '<div class="sivu ts-sivu">' . jasrek_print_marks();
    $h .= '<div class="ts-keski">' . jasrek_print_head($lasku, $r, $reminder, $s, $vars, false);
    $foot = ($yt !== '' ? $yt : $s['yhdistys']) . ($s['ytunnus'] !== '' ? ' · Y-tunnus ' . $s['ytunnus'] : '');
    $h .= '</div>';

    // Lomakkeen yläpuolella: QR-koodi ja virtuaaliviivakoodi verkkopankkia varten
    $h .= '<div class="ts-ennen">';
    $h .= '<div class="ts-ohje"><div class="ts-ohje-otsikko">Maksaminen</div>'
        . 'Maksa verkkopankissa tai mobiilisovelluksessa tilisiirtolomakkeen tiedoilla. Käytä viitenumeroa <strong class="mono">' . esc_html($vars['viite']) . '</strong>, jotta maksu kohdistuu sinulle.'
        . ($vvk !== '' ? '<div class="ts-vvk">Virtuaaliviivakoodi: <span class="mono">' . esc_html($vvk) . '</span></div>' : '')
        . '<div class="ts-yht">' . esc_html($foot) . '</div></div>';
    if (isset($images['jrqr'])) {
        $h .= '<div class="ts-qr"><img class="qr" alt="QR-koodi" src="data:image/png;base64,' . base64_encode($images['jrqr']) . '"><span>Skannaa pankki&shy;sovelluksella</span></div>';
    }
    $h .= '</div>';

    // Tilisiirtolomake
    $h .= '<div class="ts-lomake"><div class="ts-leikkaa">✂</div><table class="ts">';
    $h .= '<colgroup><col class="ts-c1"><col class="ts-c2"><col class="ts-c3"><col class="ts-c4"><col class="ts-c5"><col class="ts-c6"></colgroup>';
    $h .= '<tr class="ts-r1"><th>Saajan<br>tilinumero<br><span>Mottagarens<br>kontonummer</span></th>'
        . '<td><small>IBAN</small><div class="ts-arvo">' . esc_html($vars['iban']) . '</div></td>'
        . '<td colspan="4" class="ts-oik"><small>BIC</small><div class="ts-arvo">' . esc_html($s['bic']) . '</div></td></tr>';
    $h .= '<tr class="ts-r2"><th>Saaja<br><span>Mottagare</span></th><td><div class="ts-osoite ts-saaja' . (count($saaja) > 2 ? ' ts-pieni' : '') . '">' . $br($saaja) . '</div></td>'
        . '<td colspan="4" rowspan="2" class="ts-oik ts-viesti"><small>Viesti / Meddelande</small></td></tr>';
    $h .= '<tr class="ts-r3"><th rowspan="2" class="ts-maksaja-th"><span class="ts-pysty">TILISIIRTO GIRERING</span>Maksaja<br><span>Betalare</span>'
        . '<div class="ts-allek-l">Allekirjoitus<br><span>Underskrift</span></div></th>'
        . '<td rowspan="2" class="ts-maksaja"><div class="ts-osoite ts-maksaja-os' . (count($maksaja) > 4 ? ' ts-pieni' : '') . '">' . $br($maksaja) . '</div><div class="ts-allek"></div></td></tr>';
    $h .= '<tr class="ts-r4"><th class="ts-oik">Viitenro<br><span>Ref.nr</span></th><td colspan="3" class="ts-arvo">' . esc_html($vars['viite']) . '</td></tr>';
    $h .= '<tr class="ts-r5"><th>Tililtä nro<br><span>Från konto nr</span></th><td></td>'
        . '<th class="ts-oik">Eräpäivä<br><span>Förf.dag</span></th><td class="ts-arvo">' . esc_html($vars['erapaiva']) . '</td>'
        . '<th>Summa</th><td class="ts-arvo ts-summa">' . esc_html(jasrek_print_eur($r->summa)) . '</td></tr>';
    $h .= '</table>';
    $h .= '<div class="ts-ala"><div class="ts-viivakoodi">' . (isset($images['jrbar']) ? jasrek_print_barcode_img($images['jrbar']) : '') . '</div>'
        . '<div class="ts-ehdot">Maksu välitetään saajalle maksujen välityksen ehtojen mukaisesti ja vain maksajan ilmoittaman tilinumeron perusteella.<br>'
        . 'Betalningen förmedlas till mottagaren enligt villkoren för betalningsförmedling och endast till det kontonummer som betalaren angivit.'
        . '<div class="ts-pankki">PANKKI BANKEN</div></div></div>';
    $h .= '</div></div>';
    return $h;
}
