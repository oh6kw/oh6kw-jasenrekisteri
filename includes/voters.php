<?php
if (!defined('ABSPATH')) exit;
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The plugin's own custom tables (members, invoices, audit log). The data is admin-only and changes on every write, so object caching is not used.

/*
 * Äänioikeusluettelo vuosikokoukseen: voimassa olevat jäsenet, jäsenmaksun tila valitulta laskulta
 * ja allekirjoitussarake. Tulostettava A4-sivu.
 */

function jasrek_voters_url($args = array()) {
    return wp_nonce_url(add_query_arg(array_merge(array('action' => 'jasrek_aanioikeus'), $args), admin_url('admin-post.php')), 'jasrek_aanioikeus');
}

/**
 * Äänioikeutetut. Jäsen on äänioikeutettu, jos hän on voimassa oleva jäsen ja
 * a) hänen laskunsa valitulta laskulta on maksettu tai
 * b) hänen jäsenluokkansa jäsenmaksu on 0 € (esim. kunniajäsen).
 */
function jasrek_voters_data($lasku_id) {
    global $wpdb;
    $members = $wpdb->get_results("SELECT * FROM {$wpdb->jasrek_jasenet} WHERE tila = 'aktiivinen' ORDER BY sukunimi, etunimi");
    jasrek_sort_by_name($members);
    $rows = $lasku_id ? $wpdb->get_results($wpdb->prepare("SELECT jasen_id, maksettu, summa FROM {$wpdb->jasrek_rivit} WHERE lasku_id = %d", $lasku_id), OBJECT_K) : array();
    $out = array();
    foreach ($members as $m) {
        $r = isset($rows[$m->id]) ? $rows[$m->id] : null;
        if ($r && $r->maksettu) {
            $tila = 'Maksettu ' . jasrek_date($r->maksettu); $oikeus = true;
        } elseif (jasrek_is_maksuvapaa($m)) {
            $tila = 'Ei jäsenmaksua'; $oikeus = true;
        } elseif ($r) {
            $tila = 'Maksamatta'; $oikeus = false;
        } else {
            $tila = 'Ei laskulla'; $oikeus = false;
        }
        $out[] = array('m' => $m, 'tila' => $tila, 'oikeus' => $oikeus);
    }
    return $out;
}

add_action('admin_post_jasrek_aanioikeus', 'jasrek_handle_voters');
function jasrek_handle_voters() {
    jasrek_check('jasrek_aanioikeus');
    global $wpdb;
    $laskut = $wpdb->get_results("SELECT id, nimi, laskupvm FROM {$wpdb->jasrek_laskut} ORDER BY laskupvm DESC, id DESC");
    $lasku_id = jasrek_get_int('lasku', $laskut ? (int) $laskut[0]->id : 0);
    $vain = jasrek_get('vain') === '1';
    $otsikko = jasrek_get('otsikko', 'Vuosikokous ' . current_time('Y'));
    $pvm = jasrek_date_or_null(jasrek_get('pvm'));
    $data = jasrek_voters_data($lasku_id);
    $n_oikeus = count(array_filter($data, function ($d) { return $d['oikeus']; }));
    if ($vain) $data = array_values(array_filter($data, function ($d) { return $d['oikeus']; }));
    $lasku = $lasku_id ? jasrek_get_lasku($lasku_id) : null;
    $s = jasrek_settings();
    $tunnus = jasrek_tunnus_on();
    $back = admin_url('admin.php?page=jasrek-raportti');
    jasrek_log('vienti', 0, 'vienti', 'Äänioikeusluettelo: ' . $otsikko . ($lasku ? ' (' . $lasku->nimi . ')' : ''));

    nocache_headers();
    header('Content-Type: text/html; charset=UTF-8');
    ?>
<!DOCTYPE html>
<html lang="fi"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo esc_html('Äänioikeusluettelo – ' . $otsikko); ?></title>
<?php wp_print_styles('jasrek-voters'); ?>
</head>
<body>
<div class="tools">
  <form method="get" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <input type="hidden" name="action" value="jasrek_aanioikeus">
    <?php wp_nonce_field('jasrek_aanioikeus', '_wpnonce', false); ?>
    <label>Otsikko <input type="text" name="otsikko" value="<?php echo esc_attr($otsikko); ?>" size="22"></label>
    <label>Päivä <input type="date" name="pvm" value="<?php echo esc_attr((string) $pvm); ?>"></label>
    <label>Jäsenmaksu laskulta
      <select name="lasku">
        <?php foreach ($laskut as $l): ?><option value="<?php echo (int) $l->id; ?>" <?php selected($lasku_id, (int) $l->id); ?>><?php echo esc_html($l->nimi); ?></option><?php endforeach; ?>
      </select></label>
    <label><input type="checkbox" name="vain" value="1" <?php checked($vain); ?>> Vain äänioikeutetut</label>
    <button type="submit">Päivitä</button>
  </form>
  <button type="button" data-jasrek-print>🖨 Tulosta</button>
  <a href="<?php echo esc_url($back); ?>">← Vuosiraportti</a>
  <span class="info">Tulosta tämä paperille tai tallenna PDF:nä (tulostusikkunassa tulostimeksi <em>Tallenna PDF-tiedostona</em>).</span>
</div>
<div class="paperi">
  <h1><?php echo esc_html('Äänioikeusluettelo – ' . $otsikko); ?></h1>
  <p class="ala"><?php echo esc_html($s['yhdistys'] . ($pvm ? ' · ' . jasrek_date($pvm) : '') . ($lasku ? ' · jäsenmaksu: ' . $lasku->nimi : '') . ' · tilanne ' . jasrek_date(current_time('Y-m-d'))); ?></p>
  <table>
    <thead><tr><th class="nro">#</th><th>Nimi</th><?php if ($tunnus): ?><th><?php echo esc_html(jasrek_tunnus_nimi()); ?></th><?php endif; ?><th>Jäsenluokka</th><th>Jäsenmaksu</th><th class="ok">Ääni&shy;oikeus</th><th class="allek">Allekirjoitus</th></tr></thead>
    <tbody>
    <?php foreach ($data as $i => $d): $m = $d['m']; ?>
      <tr>
        <td class="nro"><?php echo (int) ($i + 1); ?></td>
        <td><?php echo esc_html($m->sukunimi . ' ' . $m->etunimi); ?></td>
        <?php if ($tunnus): ?><td><?php echo esc_html((string) $m->tunnus); ?></td><?php endif; ?>
        <td><?php echo esc_html(jasrek_laji_nimi((string) $m->jasenlaji)); ?></td>
        <td><?php echo esc_html($d['tila']); ?></td>
        <td class="<?php echo $d['oikeus'] ? 'ok' : 'ei'; ?>"><?php echo $d['oikeus'] ? '✓' : '–'; ?></td>
        <td class="allek"></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$data): ?><tr><td colspan="7">Ei jäseniä.</td></tr><?php endif; ?>
    </tbody>
  </table>
  <p class="yht"><strong><?php echo (int) $n_oikeus; ?> äänioikeutettua</strong><?php echo $vain ? '' : esc_html(' · ' . count($data) . ' voimassa olevaa jäsentä'); ?>.</p>
  <p class="huom">Äänioikeutettu: voimassa oleva jäsen, jonka jäsenmaksu valitulta laskulta on maksettu tai jonka jäsenluokan jäsenmaksu on 0 €. Tarkista äänioikeuden ehdot yhdistyksen säännöistä.</p>
</div>
<?php wp_print_scripts('jasrek-print-page'); ?>
</body></html>
    <?php
    exit;
}
