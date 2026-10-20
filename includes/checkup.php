<?php
if (!defined('ABSPATH')) exit;
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The plugin's own custom tables (members, invoices, audit log). The data is admin-only and changes on every write, so object caching is not used.

/*
 * Tietojen tarkistus: voimassa olevien jäsenten puuttuvat tiedot (sähköposti, osoite, puhelin,
 * valinnaisesti liittymispäivä ja syntymävuosi) sekä korjattavat asiat (paperilaskun saaja ilman
 * osoitetta, virheellinen sähköposti, tuplat, outo puhelinnumero). Puutelistan voi tulostaa
 * jäsenillassa: tulosteessa näkyvät vain puuttuvien tietojen nimet, ei jäsenten olemassa olevia tietoja.
 */

/** Tarkistettavat tiedot: avain => array(otsikko, oletuksena mukana). */
function jasrek_checkup_kentat() {
    return array(
        'email'     => array('Sähköposti', true),
        'osoite'    => array('Osoite', true),
        'puhelin'   => array('Puhelin', true),
        'liittynyt' => array('Liittymispäivä', false),
        'syntynyt'  => array('Syntymävuosi tai -päivä', false),
    );
}

/** Valitut tarkistettavat kentät pyynnöstä (k[]), tai oletukset. */
function jasrek_checkup_valitut() {
    $kaikki = jasrek_checkup_kentat();
    $k = isset($_GET['k']) ? array_map('sanitize_key', (array) wp_unslash($_GET['k'])) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- vain näkymän valinta, ei muuta tietoja
    $out = array();
    foreach ($kaikki as $id => $d) {
        if ($k === null ? $d[1] : in_array($id, $k, true)) $out[] = $id;
    }
    return $out;
}

/** Onko puhelinnumero selvästi virheellinen (kirjaimia, liian lyhyt tai pitkä)? */
function jasrek_checkup_puhelin_outo($p) {
    $p = trim((string) $p);
    if ($p === '') return false;
    if (!preg_match('/^\+?[\d\s\-()]+$/', $p)) return true;
    $n = strlen(preg_replace('/\D/', '', $p));
    return $n < 6 || $n > 15;
}

/**
 * Tarkistuksen tulos: array('puutteet' => array(id => array('m' => jäsen, 'puuttuu' => array(kentät))),
 * 'korjattavat' => array(array('m', 'teksti', 'taso' => 'err'|'warn'|'info'))).
 */
function jasrek_checkup_data($kentat) {
    global $wpdb;
    $members = $wpdb->get_results("SELECT * FROM {$wpdb->jasrek_jasenet} WHERE tila = 'aktiivinen' ORDER BY sukunimi, etunimi");
    jasrek_sort_by_name($members);
    $puutteet = array(); $korj = array();
    $nimet = array(); $emails = array();
    foreach ($members as $m) {
        $p = array();
        foreach ($kentat as $k) {
            $v = isset($m->$k) ? trim((string) $m->$k) : '';
            if ($v === '' || $v === '0000-00-00') $p[] = $k;
        }
        if ($p) $puutteet[$m->id] = array('m' => $m, 'puuttuu' => $p);

        $email = trim((string) $m->email);
        if (trim((string) $m->osoite) === '' && jasrek_is_paperi($m)) {
            $korj[] = array('m' => $m, 'taso' => 'err', 'teksti' => $email === ''
                ? 'Ei sähköpostia eikä osoitetta – laskua ei voi toimittaa.'
                : 'Saa laskun paperilla, mutta osoite puuttuu.');
        }
        if ($email !== '' && !is_email($email)) $korj[] = array('m' => $m, 'taso' => 'err', 'teksti' => 'Sähköpostiosoite näyttää virheelliseltä.');
        if (jasrek_checkup_puhelin_outo($m->puhelin)) $korj[] = array('m' => $m, 'taso' => 'warn', 'teksti' => 'Puhelinnumero näyttää virheelliseltä.');
        $nimet[jasrek_lower(trim($m->etunimi) . ' ' . trim($m->sukunimi))][] = $m;
        if ($email !== '') $emails[jasrek_lower($email)][] = $m;
    }
    foreach ($nimet as $lista) {
        if (count($lista) < 2) continue;
        foreach ($lista as $m) $korj[] = array('m' => $m, 'taso' => 'warn', 'teksti' => 'Sama nimi on rekisterissä ' . count($lista) . ' kertaa – mahdollinen tupla.');
    }
    foreach ($emails as $lista) {
        if (count($lista) < 2) continue;
        $muut = function ($self) use ($lista) {
            $n = array();
            foreach ($lista as $o) if ($o->id !== $self->id) $n[] = trim($o->etunimi . ' ' . $o->sukunimi);
            return implode(', ', $n);
        };
        foreach ($lista as $m) $korj[] = array('m' => $m, 'taso' => 'info', 'teksti' => 'Sama sähköposti kuin jäsenellä ' . $muut($m) . ' (voi olla perheen yhteinen osoite).');
    }
    $jarj = array('err' => 0, 'warn' => 1, 'info' => 2);
    usort($korj, function ($a, $b) use ($jarj) {
        if ($jarj[$a['taso']] !== $jarj[$b['taso']]) return $jarj[$a['taso']] - $jarj[$b['taso']];
        return strcmp(jasrek_fi_key($a['m']->sukunimi . ' ' . $a['m']->etunimi), jasrek_fi_key($b['m']->sukunimi . ' ' . $b['m']->etunimi));
    });
    return array('puutteet' => $puutteet, 'korjattavat' => $korj);
}

function jasrek_checkup_print_url($kentat) {
    return add_query_arg(array('action' => 'jasrek_puutelista', 'k' => $kentat, '_wpnonce' => wp_create_nonce('jasrek_puutelista')), admin_url('admin-post.php'));
}

/** Tarkistussivu hallinnassa: Jäsenet → (kortti) Tietojen tarkistus. */
function jasrek_checkup_page() {
    $kaikki = jasrek_checkup_kentat();
    $kentat = jasrek_checkup_valitut();
    $d = jasrek_checkup_data($kentat);
    $tunnus = jasrek_tunnus_on();
    $base = admin_url('admin.php?page=jasenrekisteri');
    $edit = function ($m) use ($base) {
        return '<a href="' . esc_url(add_query_arg(array('view' => 'edit', 'id' => (int) $m->id), $base)) . '"><strong>' . esc_html($m->sukunimi . ' ' . $m->etunimi) . '</strong></a>';
    };
    $tasot = array('err' => 'jasrek-badge-err', 'warn' => 'jasrek-badge-warn', 'info' => 'jasrek-badge-info');
    $tasonimet = array('err' => 'Korjaa', 'warn' => 'Tarkista', 'info' => 'Huom.');
    ?>
    <h1 class="wp-heading-inline">Tietojen tarkistus</h1>
    <?php if ($d['puutteet']): ?><a class="page-title-action" target="_blank" href="<?php echo esc_url(jasrek_checkup_print_url($kentat)); ?>">Tulosta puutelista</a>
    <a class="page-title-action" href="<?php echo esc_url(admin_url('admin.php?page=jasrek-sahkoposti&view=tarkistus&kohde=puuttuvat')); ?>" title="Avaa sähköpostisivun tietojentarkistuspyynnön valmiiksi täytettynä. Mitään ei lähetetä ennen kuin painat siellä Lähetä.">Tietojentarkistuspyyntö sähköpostilla…</a><?php endif; ?>
    <hr class="wp-header-end">
    <p><a href="<?php echo esc_url($base); ?>">← Jäsenet</a></p>
    <p class="description">Tarkistaa voimassa olevien jäsenten tiedot. Jäsenen nimestä pääset muokkaamaan hänen tietojaan. Puutelistan voi tulostaa esimerkiksi jäsenillassa täydennettäväksi: tulosteessa näkyvät vain puuttuvien tietojen nimet, ei jäsenten olemassa olevia tietoja. <em>Tietojentarkistuspyyntö sähköpostilla</em> avaa viestin valmiiksi täytettynä niille, joilta puuttuu osoite tai puhelinnumero – viesti ei lähde heti, vaan voit ensin muokata sitä, vaihtaa vastaanottajat, katsoa esikatselun ja lähettää testin itsellesi.</p>

    <form method="get" class="jasrek-kp-form">
        <input type="hidden" name="page" value="jasenrekisteri">
        <input type="hidden" name="view" value="tarkistus">
        <strong>Tarkista:</strong>
        <?php foreach ($kaikki as $id => $k): ?>
            <label><input type="checkbox" name="k[]" value="<?php echo esc_attr($id); ?>" <?php checked(in_array($id, $kentat, true)); ?>> <?php echo esc_html($k[0]); ?></label>
        <?php endforeach; ?>
        <input type="hidden" name="k[]" value="_">
        <button type="submit" class="button">Päivitä</button>
    </form>

    <h2>Korjattavaa<?php echo $d['korjattavat'] ? ' (' . (int) count($d['korjattavat']) . ')' : ''; ?></h2>
    <?php if (!$d['korjattavat']): ?>
        <p><span class="jasrek-badge jasrek-badge-ok">✓</span> Ei korjattavaa: ei paperilaskun saajia ilman osoitetta, virheellisiä sähköposteja, tuplia eikä outoja puhelinnumeroita.</p>
    <?php else: ?>
    <table class="widefat striped jasrek-table">
        <thead><tr><th style="width:90px"></th><th>Nimi</th><?php if ($tunnus): ?><th><?php echo esc_html(jasrek_tunnus_nimi()); ?></th><?php endif; ?><th>Mitä</th></tr></thead>
        <tbody>
        <?php foreach ($d['korjattavat'] as $r): ?>
            <tr><td><span class="jasrek-badge <?php echo esc_attr($tasot[$r['taso']]); ?>"><?php echo esc_html($tasonimet[$r['taso']]); ?></span></td>
                <td><?php echo $edit($r['m']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in $edit ?></td>
                <?php if ($tunnus): ?><td><?php echo esc_html((string) $r['m']->tunnus); ?></td><?php endif; ?>
                <td><?php echo esc_html($r['teksti']); ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <h2>Puuttuvat tiedot<?php echo $d['puutteet'] ? ' (' . (int) count($d['puutteet']) . ' jäsentä)' : ''; ?></h2>
    <?php if (!$kentat): ?>
        <p>Valitse yllä, mitä tietoja tarkistetaan.</p>
    <?php elseif (!$d['puutteet']): ?>
        <p><span class="jasrek-badge jasrek-badge-ok">✓</span> Kaikilla voimassa olevilla jäsenillä on valitut tiedot.</p>
    <?php else: ?>
    <table class="widefat striped jasrek-table jasrek-checkup">
        <thead><tr><th>Nimi</th><?php if ($tunnus): ?><th><?php echo esc_html(jasrek_tunnus_nimi()); ?></th><?php endif; ?>
            <?php foreach ($kentat as $k): ?><th class="jasrek-c"><?php echo esc_html($kaikki[$k][0]); ?></th><?php endforeach; ?></tr></thead>
        <tbody>
        <?php foreach ($d['puutteet'] as $r): ?>
            <tr><td><?php echo $edit($r['m']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in $edit ?></td>
                <?php if ($tunnus): ?><td><?php echo esc_html((string) $r['m']->tunnus); ?></td><?php endif; ?>
                <?php foreach ($kentat as $k): ?><td class="jasrek-c"><?php echo in_array($k, $r['puuttuu'], true) ? '<span class="jasrek-badge jasrek-badge-warn">puuttuu</span>' : '<span class="jasrek-muted">✓</span>'; ?></td><?php endforeach; ?></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif;
}

/** Tulostettava puutelista: nimi, tunnus ja puuttuvien tietojen nimet viivoineen. */
add_action('admin_post_jasrek_puutelista', 'jasrek_handle_checkup_print');
function jasrek_handle_checkup_print() {
    jasrek_check('jasrek_puutelista');
    $s = jasrek_settings();
    $kaikki = jasrek_checkup_kentat();
    $kentat = jasrek_checkup_valitut();
    $d = jasrek_checkup_data($kentat);
    $tunnus = jasrek_tunnus_on();
    jasrek_log('vienti', 0, 'vienti', 'Puutelista tulostettavana (' . count($d['puutteet']) . ' jäsentä)');
    nocache_headers();
    header('Content-Type: text/html; charset=UTF-8');
    ?>
<!DOCTYPE html>
<html lang="fi"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo esc_html('Puuttuvat jäsentiedot – ' . $s['yhdistys']); ?></title>
<?php wp_print_styles('jasrek-checkup-print'); ?>
</head>
<body>
<div class="tools">
  <button type="button" data-jasrek-print>🖨 Tulosta</button>
  <a href="<?php echo esc_url(add_query_arg(array('page' => 'jasenrekisteri', 'view' => 'tarkistus', 'k' => $kentat), admin_url('admin.php'))); ?>">← Tietojen tarkistus</a>
  <span class="info">Tulosta tämä paperille tai tallenna PDF:nä (tulostusikkunassa tulostimeksi <em>Tallenna PDF-tiedostona</em>). Tulosteessa näkyvät vain puuttuvien tietojen nimet, ei jäsenten olemassa olevia tietoja.</span>
</div>
<div class="paperi">
  <h1>Puuttuvat jäsentiedot</h1>
  <p class="ala"><?php echo esc_html($s['yhdistys'] . ' · ' . count($d['puutteet']) . ' ' . (count($d['puutteet']) === 1 ? 'jäsen' : 'jäsentä') . ' · tilanne ' . jasrek_date(current_time('Y-m-d'))); ?></p>
  <p class="ohje">Puuttuuko sinulta jotain? Kirjoita tieto viivalle tai kerro se hallitukselle. Tietoja käytetään vain yhdistyksen jäsenrekisterissä.</p>
  <table class="puutteet">
    <tbody>
    <?php foreach ($d['puutteet'] as $r): $m = $r['m']; ?>
      <tr>
        <td class="nimi"><strong><?php echo esc_html($m->sukunimi . ' ' . $m->etunimi); ?></strong><?php if ($tunnus && (string) $m->tunnus !== ''): ?><br><span class="tunnus"><?php echo esc_html($m->tunnus); ?></span><?php endif; ?></td>
        <td class="rivit">
          <?php foreach ($r['puuttuu'] as $k): ?><div class="rivi"><span class="kentta"><?php echo esc_html($kaikki[$k][0]); ?>:</span><span class="viiva"></span></div><?php endforeach; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$d['puutteet']): ?><tr><td colspan="2">Ei puuttuvia tietoja.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php wp_print_scripts('jasrek-print-page'); ?>
</body></html>
    <?php
    exit;
}
