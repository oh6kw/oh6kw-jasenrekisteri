<?php
if (!defined('ABSPATH')) exit;
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The plugin's own custom tables (members, invoices, audit log). The data is admin-only and changes on every write, so object caching is not used.

/*
 * Jäsenluettelo tulostettavana A4-sivuna (vaaka): nimi, tunnus, omat lisäkentät, sähköposti,
 * puhelin, osoite, liittymispäivä ja syntymävuosi/-päivä. Paperille tai PDF:ksi.
 */

function jasrek_member_print_url($args = array()) {
    return wp_nonce_url(add_query_arg(array_merge(array('action' => 'jasrek_jasenluettelo'), $args), admin_url('admin-post.php')), 'jasrek_jasenluettelo');
}

add_action('admin_post_jasrek_jasenluettelo', 'jasrek_handle_member_print');
function jasrek_handle_member_print() {
    jasrek_check('jasrek_jasenluettelo');
    global $wpdb;
    $s = jasrek_settings();
    $tunnus = jasrek_tunnus_on();
    $kentat = jasrek_lisakentat();
    $lajit = jasrek_jasenlajit();

    // Valinnat (työkalurivi)
    $tila = jasrek_get('tila', 'aktiivinen');
    if (!in_array($tila, array('aktiivinen', 'eronnut', 'kaikki'), true)) $tila = 'aktiivinen';
    $laji = (string) jasrek_get('laji', '');
    if ($laji !== '' && !isset($lajit[$laji])) $laji = '';
    $jarj = jasrek_get('jarj') === 'tunnus' && $tunnus ? 'tunnus' : 'nimi';
    $valittu = isset($_GET['k']) ? array_map('sanitize_key', (array) wp_unslash($_GET['k'])) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce tarkistettu jasrek_check():ssä
    $nayta = array();
    foreach ($kentat as $kid => $k) {
        $nayta[$kid] = $valittu === null ? !empty($k['lista']) && $k['lista'] === '1' : in_array($kid, $valittu, true);
    }
    $nayta_laji = $valittu === null ? count($lajit) > 1 : in_array('_laji', $valittu, true);

    $members = $jarj === 'tunnus'
        ? $wpdb->get_results("SELECT * FROM {$wpdb->jasrek_jasenet} ORDER BY tunnus = '', tunnus, sukunimi, etunimi")
        : $wpdb->get_results("SELECT * FROM {$wpdb->jasrek_jasenet} ORDER BY sukunimi, etunimi");
    if ($jarj === 'nimi') jasrek_sort_by_name($members);
    $members = array_values(array_filter($members, function ($m) use ($tila, $laji) {
        if ($tila !== 'kaikki' && $m->tila !== $tila) return false;
        return $laji === '' || (string) $m->jasenlaji === $laji;
    }));

    $tilat = array('aktiivinen' => 'Voimassa olevat', 'eronnut' => 'Eronneet', 'kaikki' => 'Kaikki');
    $otsikko = 'Jäsenluettelo' . ($tila === 'eronnut' ? ' – eronneet' : '') . ($laji !== '' ? ' – ' . $lajit[$laji]['nimi'] : '');
    jasrek_log('vienti', 0, 'vienti', $otsikko . ' tulostettavana (' . count($members) . ' jäsentä)');

    nocache_headers();
    header('Content-Type: text/html; charset=UTF-8');
    ?>
<!DOCTYPE html>
<html lang="fi"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo esc_html($otsikko . ' – ' . $s['yhdistys']); ?></title>
<?php wp_print_styles('jasrek-members-print'); ?>
</head>
<body>
<div class="tools">
  <form method="get" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <input type="hidden" name="action" value="jasrek_jasenluettelo">
    <?php wp_nonce_field('jasrek_jasenluettelo', '_wpnonce', false); ?>
    <label>Jäsenet
      <select name="tila"><?php foreach ($tilat as $k => $v): ?><option value="<?php echo esc_attr($k); ?>" <?php selected($tila, $k); ?>><?php echo esc_html($v); ?></option><?php endforeach; ?></select></label>
    <?php if (count($lajit) > 1): ?>
    <label>Jäsenluokka
      <select name="laji"><option value="">Kaikki</option><?php foreach ($lajit as $lid => $l): ?><option value="<?php echo esc_attr($lid); ?>" <?php selected($laji, (string) $lid); ?>><?php echo esc_html($l['nimi']); ?></option><?php endforeach; ?></select></label>
    <?php endif; ?>
    <?php if ($tunnus): ?>
    <label>Järjestys
      <select name="jarj"><option value="nimi" <?php selected($jarj, 'nimi'); ?>>Sukunimi</option><option value="tunnus" <?php selected($jarj, 'tunnus'); ?>><?php echo esc_html(jasrek_tunnus_nimi()); ?></option></select></label>
    <?php endif; ?>
    <span class="sarakkeet">Sarakkeet:
      <?php if (count($lajit) > 1): ?><label><input type="checkbox" name="k[]" value="_laji" <?php checked($nayta_laji); ?>> Jäsenluokka</label><?php endif; ?>
      <?php foreach ($kentat as $kid => $k): ?><label><input type="checkbox" name="k[]" value="<?php echo esc_attr($kid); ?>" <?php checked($nayta[$kid]); ?>> <?php echo esc_html($k['nimi']); ?></label><?php endforeach; ?>
      <input type="hidden" name="k[]" value="_">
    </span>
    <button type="submit">Päivitä</button>
  </form>
  <button type="button" data-jasrek-print>🖨 Tulosta</button>
  <a href="<?php echo esc_url(admin_url('admin.php?page=jasenrekisteri')); ?>">← Jäsenet</a>
  <span class="info">Tulosta tämä paperille tai tallenna PDF:nä (tulostusikkunassa tulostimeksi <em>Tallenna PDF-tiedostona</em>). Sivu tulostuu vaakasuuntaisena.</span>
</div>
<div class="paperi">
  <h1><?php echo esc_html($otsikko); ?></h1>
  <p class="ala"><?php echo esc_html($s['yhdistys'] . ' · ' . count($members) . ' ' . (count($members) === 1 ? 'jäsen' : 'jäsentä') . ' · tilanne ' . jasrek_date(current_time('Y-m-d'))); ?></p>
  <table>
    <thead><tr>
      <th class="nro">#</th><th>Nimi</th>
      <?php if ($tunnus): ?><th><?php echo esc_html(jasrek_tunnus_nimi()); ?></th><?php endif; ?>
      <?php if ($nayta_laji): ?><th>Jäsenluokka</th><?php endif; ?>
      <?php foreach ($kentat as $kid => $k): if (!$nayta[$kid]) continue; ?><th><?php echo esc_html($k['nimi']); ?></th><?php endforeach; ?>
      <th>Sähköposti</th><th>Puhelin</th><th>Osoite</th><th class="pvm">Liittynyt</th><th class="pvm">Syntynyt</th>
      <?php if ($tila !== 'aktiivinen'): ?><th class="pvm">Eronnut</th><?php endif; ?>
    </tr></thead>
    <tbody>
    <?php foreach ($members as $i => $m): $lisa = jasrek_member_lisa($m); ?>
      <tr>
        <td class="nro"><?php echo (int) ($i + 1); ?></td>
        <td class="nimi"><?php echo esc_html($m->sukunimi . ' ' . $m->etunimi); ?></td>
        <?php if ($tunnus): ?><td><?php echo esc_html((string) $m->tunnus); ?></td><?php endif; ?>
        <?php if ($nayta_laji): ?><td><?php echo esc_html(jasrek_laji_nimi((string) $m->jasenlaji)); ?></td><?php endif; ?>
        <?php foreach ($kentat as $kid => $k): if (!$nayta[$kid]) continue; ?><td><?php echo esc_html(jasrek_lisa_value($k, isset($lisa[$kid]) ? $lisa[$kid] : '')); ?></td><?php endforeach; ?>
        <td class="email"><?php echo esc_html((string) $m->email); ?></td>
        <td class="puh"><?php echo esc_html((string) $m->puhelin); ?></td>
        <td><?php echo esc_html((string) $m->osoite); ?></td>
        <td class="pvm"><?php echo esc_html(jasrek_date($m->liittynyt)); ?></td>
        <td class="pvm"><?php echo esc_html(jasrek_syntynyt_teksti($m->syntynyt)); ?></td>
        <?php if ($tila !== 'aktiivinen'): ?><td class="pvm"><?php echo esc_html($m->tila === 'eronnut' ? (jasrek_date($m->eronnut_pvm) ?: 'eronnut') : ''); ?></td><?php endif; ?>
      </tr>
    <?php endforeach; ?>
    <?php if (!$members): ?><tr><td colspan="12">Ei jäseniä.</td></tr><?php endif; ?>
    </tbody>
  </table>
  <p class="huom">Luettelo sisältää henkilötietoja. Säilytä se huolellisesti ja hävitä tarpeettomaksi käynyt tuloste tai PDF.</p>
</div>
<?php wp_print_scripts('jasrek-print-page'); ?>
</body></html>
    <?php
    exit;
}
