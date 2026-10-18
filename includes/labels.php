<?php
if (!defined('ABSPATH')) exit;
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The plugin's own custom tables (members, invoices, audit log). The data is admin-only and changes on every write, so object caching is not used.

/*
 * Osoitetarrat ja lähettäjätarrat A4-tarra-arkille. Arkin mitat valitaan valmiista
 * vaihtoehdoista tai annetaan itse; valinta ja tulostimen hienosäätö muistetaan asetuksissa.
 */

/** Valmiit tarra-arkit: sarakkeet, rivit, tarran leveys ja korkeus, ylä- ja vasen reuna, välit (mm). */
function jasrek_tarra_arkit() {
    return array(
        '24-70x36' => array('nimi' => '24 tarraa, 70 × 36 mm (3 × 8)', 'cols' => 3, 'rows' => 8, 'w' => 70, 'h' => 36, 'top' => 4.5, 'left' => 0, 'gx' => 0, 'gy' => 0),
        '24-70x37' => array('nimi' => '24 tarraa, 70 × 37 mm (3 × 8)', 'cols' => 3, 'rows' => 8, 'w' => 70, 'h' => 37, 'top' => 0.5, 'left' => 0, 'gx' => 0, 'gy' => 0),
        '21-70x42' => array('nimi' => '21 tarraa, 70 × 42,3 mm (3 × 7)', 'cols' => 3, 'rows' => 7, 'w' => 70, 'h' => 42.3, 'top' => 0.4, 'left' => 0, 'gx' => 0, 'gy' => 0),
        '27-70x32' => array('nimi' => '27 tarraa, 70 × 32 mm (3 × 9)', 'cols' => 3, 'rows' => 9, 'w' => 70, 'h' => 32, 'top' => 4.5, 'left' => 0, 'gx' => 0, 'gy' => 0),
        '14-105x42' => array('nimi' => '14 tarraa, 105 × 42,3 mm (2 × 7)', 'cols' => 2, 'rows' => 7, 'w' => 105, 'h' => 42.3, 'top' => 0.4, 'left' => 0, 'gx' => 0, 'gy' => 0),
    );
}

function jasrek_labels_url($args = array()) {
    return add_query_arg(array_merge(array('action' => 'jasrek_tarrat', '_wpnonce' => wp_create_nonce('jasrek_tarrat')), $args), admin_url('admin-post.php'));
}

/** Osoite tarran riveiksi: pilkku tai rivinvaihto erottaa rivit, postinumerorivi isoilla kirjaimilla (Postin suositus). */
function jasrek_tarra_rivit($osoite) {
    $osat = array_values(array_filter(array_map('trim', preg_split('/\s*[,\n]\s*/', (string) $osoite)), 'strlen'));
    foreach ($osat as $i => $o) {
        if (preg_match('/^(FI-)?\d{5}\s/u', $o) || ($i === count($osat) - 1 && $i > 0)) $osat[$i] = jasrek_upper($o);
    }
    return $osat;
}

/** Luku pyöristettynä 0,1 mm:iin ja rajattuna. */
function jasrek_tarra_mm($v, $min, $max, $oletus) {
    $v = str_replace(',', '.', trim((string) $v));
    if ($v === '' || !is_numeric($v)) return $oletus;
    return max($min, min($max, round((float) $v, 1)));
}

add_action('admin_post_jasrek_tarrat', 'jasrek_handle_labels');
function jasrek_handle_labels() {
    jasrek_check('jasrek_tarrat');
    global $wpdb;
    $s = jasrek_settings();
    $muisti = is_array($s['tarrat']) ? $s['tarrat'] : array();
    $arkit = jasrek_tarra_arkit();
    $lajit = jasrek_jasenlajit();
    $lahetetty = jasrek_get('arkki') !== '';

    // Arkki ja hienosäätö: lomakkeelta tai muistista
    $src = $lahetetty ? array(
        'arkki' => jasrek_get('arkki'), 'sx' => jasrek_get('sx'), 'sy' => jasrek_get('sy'),
        'cols' => jasrek_get('cols'), 'rows' => jasrek_get('rows'), 'w' => jasrek_get('w'), 'h' => jasrek_get('h'),
        'top' => jasrek_get('top'), 'left' => jasrek_get('left'), 'gx' => jasrek_get('gx'), 'gy' => jasrek_get('gy'),
    ) : $muisti;
    $arkki_id = isset($src['arkki']) && ($src['arkki'] === 'oma' || isset($arkit[$src['arkki']])) ? $src['arkki'] : '24-70x36';
    $g = function ($k, $d) use ($src) { return isset($src[$k]) ? $src[$k] : $d; };
    $oma = array(
        'cols' => (int) jasrek_tarra_mm($g('cols', 3), 1, 6, 3), 'rows' => (int) jasrek_tarra_mm($g('rows', 8), 1, 20, 8),
        'w' => jasrek_tarra_mm($g('w', 70), 10, 210, 70), 'h' => jasrek_tarra_mm($g('h', 36), 10, 297, 36),
        'top' => jasrek_tarra_mm($g('top', 4.5), 0, 100, 4.5), 'left' => jasrek_tarra_mm($g('left', 0), 0, 100, 0),
        'gx' => jasrek_tarra_mm($g('gx', 0), 0, 50, 0), 'gy' => jasrek_tarra_mm($g('gy', 0), 0, 50, 0),
    );
    $sx = jasrek_tarra_mm($g('sx', 0), -15, 15, 0);
    $sy = jasrek_tarra_mm($g('sy', 0), -15, 15, 0);
    $a = $arkki_id === 'oma' ? $oma : $arkit[$arkki_id];
    $per = $a['cols'] * $a['rows'];
    if ($lahetetty) {
        $uusi = array_merge(array('arkki' => $arkki_id, 'sx' => $sx, 'sy' => $sy), $oma);
        if ($uusi != $muisti) {
            $raw = get_option('jasrek_settings', array());
            if (is_array($raw)) { $raw['tarrat'] = $uusi; update_option('jasrek_settings', $raw); }
        }
    }

    // Kenelle
    $kohde = jasrek_get('kohde', 'paperi');
    $ids = array_values(array_filter(array_map('intval', explode(',', (string) jasrek_get('ids', '')))));
    $kohteet = array('paperi' => 'Paperilaskun saajat', 'aktiivinen' => 'Kaikki voimassa olevat');
    foreach ($lajit as $lid => $l) $kohteet['laji:' . $lid] = 'Jäsenluokka: ' . $l['nimi'];
    $kohteet['eronnut'] = 'Eronneet';
    $kohteet['kaikki'] = 'Kaikki, myös eronneet';
    if ($ids) $kohteet['valitut'] = 'Valitut (' . count($ids) . ')';
    $kohteet['lahettaja'] = 'Lähettäjätarrat (yhdistyksen osoite)';
    if (!isset($kohteet[$kohde])) $kohde = $ids ? 'valitut' : 'paperi';
    $alku = max(1, min($per, (int) jasrek_get('alku', 1)));
    $maara = max(1, min(500, (int) jasrek_get('maara', $per - $alku + 1)));

    $tarrat = array(); $puuttuu = array(); $lah_virhe = '';
    if ($kohde === 'lahettaja') {
        $rivit = trim((string) $s['postiosoite']) !== '' ? $s['postiosoite'] : $s['osoite'];
        $rivit = jasrek_tarra_rivit($rivit);
        if (!$rivit) $lah_virhe = 'Yhdistyksen palautusosoite ja osoite puuttuvat. Lisää ne kohdassa Asetukset (Yhdistys / Paperilaskut).';
        else for ($i = 0; $i < $maara; $i++) $tarrat[] = array_merge(array((string) $s['yhdistys']), $rivit);
    } else {
        $members = $wpdb->get_results("SELECT * FROM {$wpdb->jasrek_jasenet} ORDER BY sukunimi, etunimi");
        foreach ($members as $m) {
            if ($kohde === 'valitut') { if (!in_array((int) $m->id, $ids, true)) continue; }
            elseif ($kohde === 'paperi') { if ($m->tila !== 'aktiivinen' || !jasrek_is_paperi($m)) continue; }
            elseif ($kohde === 'aktiivinen') { if ($m->tila !== 'aktiivinen') continue; }
            elseif ($kohde === 'eronnut') { if ($m->tila !== 'eronnut') continue; }
            elseif (strpos($kohde, 'laji:') === 0) { if ($m->tila !== 'aktiivinen' || (string) $m->jasenlaji !== substr($kohde, 5)) continue; }
            $nimi = trim($m->etunimi . ' ' . $m->sukunimi);
            $rivit = jasrek_tarra_rivit($m->osoite);
            if (!$rivit) { $puuttuu[] = $nimi; continue; }
            $tarrat[] = array_merge(array($nimi), $rivit);
        }
    }
    $sivuja = $tarrat ? (int) ceil(($alku - 1 + count($tarrat)) / $per) : 0;
    jasrek_log('vienti', 0, 'vienti', ($kohde === 'lahettaja' ? 'Lähettäjätarrat' : 'Osoitetarrat: ' . $kohteet[$kohde]) . ' (' . count($tarrat) . ' tarraa, ' . $a['cols'] . ' × ' . $a['rows'] . ')');

    nocache_headers();
    header('Content-Type: text/html; charset=UTF-8');
    $num = function ($v) { return rtrim(rtrim(number_format((float) $v, 1, '.', ''), '0'), '.'); };
    ?>
<!DOCTYPE html>
<html lang="fi"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo esc_html(($kohde === 'lahettaja' ? 'Lähettäjätarrat' : 'Osoitetarrat') . ' – ' . $s['yhdistys']); ?></title>
<?php wp_print_styles('jasrek-labels'); ?>
</head>
<body>
<div class="tools">
  <form method="get" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <input type="hidden" name="action" value="jasrek_tarrat">
    <?php wp_nonce_field('jasrek_tarrat', '_wpnonce', false); ?>
    <?php if ($ids): ?><input type="hidden" name="ids" value="<?php echo esc_attr(implode(',', $ids)); ?>"><?php endif; ?>
    <label>Tarrat
      <select name="kohde"><?php foreach ($kohteet as $k => $v): ?><option value="<?php echo esc_attr($k); ?>" <?php selected($kohde, $k); ?>><?php echo esc_html($v); ?></option><?php endforeach; ?></select></label>
    <label>Arkki
      <select name="arkki"><?php foreach ($arkit as $k => $v): ?><option value="<?php echo esc_attr($k); ?>" <?php selected($arkki_id, $k); ?>><?php echo esc_html($v['nimi']); ?></option><?php endforeach; ?>
        <option value="oma" <?php selected($arkki_id, 'oma'); ?>>Oma arkki (mitat alla)</option></select></label>
    <label>Aloita tarrasta nro <input type="number" name="alku" value="<?php echo (int) $alku; ?>" min="1" max="<?php echo (int) $per; ?>" style="width:4.5em"></label>
    <?php if ($kohde === 'lahettaja'): ?><label>Määrä <input type="number" name="maara" value="<?php echo (int) $maara; ?>" min="1" max="500" style="width:4.5em"></label><?php endif; ?>
    <button type="submit">Päivitä</button>
    <details<?php echo $arkki_id === 'oma' ? ' open' : ''; ?>>
      <summary>Oma arkki ja tulostimen hienosäätö</summary>
      <div class="oma">
        <span>Oma arkki:</span>
        <label>sarakkeita <input type="number" name="cols" value="<?php echo (int) $oma['cols']; ?>" min="1" max="6"></label>
        <label>rivejä <input type="number" name="rows" value="<?php echo (int) $oma['rows']; ?>" min="1" max="20"></label>
        <label>tarra <input type="text" name="w" value="<?php echo esc_attr($num($oma['w'])); ?>"> × <input type="text" name="h" value="<?php echo esc_attr($num($oma['h'])); ?>"> mm</label>
        <label>yläreuna <input type="text" name="top" value="<?php echo esc_attr($num($oma['top'])); ?>"> mm</label>
        <label>vasen reuna <input type="text" name="left" value="<?php echo esc_attr($num($oma['left'])); ?>"> mm</label>
        <label>väli vaaka <input type="text" name="gx" value="<?php echo esc_attr($num($oma['gx'])); ?>"> pysty <input type="text" name="gy" value="<?php echo esc_attr($num($oma['gy'])); ?>"> mm</label>
      </div>
      <div class="oma">
        <span>Hienosäätö (kaikki arkit):</span>
        <label>oikealle <input type="text" name="sx" value="<?php echo esc_attr($num($sx)); ?>"> mm</label>
        <label>alas <input type="text" name="sy" value="<?php echo esc_attr($num($sy)); ?>"> mm</label>
        <span class="vihje">Negatiivinen luku siirtää vasemmalle / ylös. Valinnat muistetaan seuraavalla kerralla.</span>
      </div>
    </details>
  </form>
  <?php if ($tarrat): ?><button type="button" data-jasrek-print>🖨 Tulosta</button><?php endif; ?>
  <a href="<?php echo esc_url(admin_url('admin.php?page=jasenrekisteri')); ?>">← Jäsenet</a>
  <span class="info">Tulosta tarra-arkille: tulostusikkunassa <em>Marginaalit: ei mitään</em> ja <em>Skaalaus 100 %</em> (ei ”Sovita sivulle”). Kokeile ensin tavalliselle paperille ja vertaa tarra-arkkiin valoa vasten. Tarvittaessa siirrä kohdassa <em>Hienosäätö</em>.</span>
  <span class="info"><strong><?php echo (int) count($tarrat); ?> tarraa</strong>, <?php echo (int) $sivuja; ?> <?php echo $sivuja === 1 ? 'arkki' : 'arkkia'; ?> (<?php echo (int) $per; ?> tarraa / arkki).</span>
  <?php if ($puuttuu): ?><span class="warn">⚠ Osoite puuttuu, ei tulosteta (<?php echo (int) count($puuttuu); ?>): <?php echo esc_html(implode(', ', $puuttuu)); ?>.</span><?php endif; ?>
  <?php if ($lah_virhe): ?><span class="warn">⚠ <?php echo esc_html($lah_virhe); ?></span><?php endif; ?>
  <?php if (!$tarrat && !$lah_virhe): ?><span class="warn">Valinnalla ei löytynyt yhtään jäsentä, jolla on osoite.</span><?php endif; ?>
</div>
<?php
    for ($p = 0; $p < $sivuja; $p++):
        echo '<div class="arkki">';
        for ($i = 0; $i < $per; $i++) {
            $n = $p * $per + $i - ($alku - 1);
            $c = $i % $a['cols']; $r = intdiv($i, $a['cols']);
            $x = $a['left'] + $c * ($a['w'] + $a['gx']) + $sx;
            $y = $a['top'] + $r * ($a['h'] + $a['gy']) + $sy;
            $style = 'left:' . $num($x) . 'mm;top:' . $num($y) . 'mm;width:' . $num($a['w']) . 'mm;height:' . $num($a['h']) . 'mm';
            $lines = ($n >= 0 && isset($tarrat[$n])) ? $tarrat[$n] : null;
            echo '<div class="tarra' . ($lines ? '' : ' tyhja') . '" style="' . esc_attr($style) . '">';
            if ($lines) {
                echo '<div class="rivit">';
                foreach ($lines as $k => $l) echo '<div' . ($k === 0 ? ' class="nimi"' : '') . '>' . esc_html($l) . '</div>';
                echo '</div>';
            }
            echo '</div>';
        }
        echo '</div>';
    endfor;
    wp_print_scripts('jasrek-print-page');
    ?>
</body></html>
    <?php
    exit;
}
