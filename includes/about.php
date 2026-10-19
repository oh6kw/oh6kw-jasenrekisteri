<?php
if (!defined('ABSPATH')) exit;

/*
 * Tietoja-sivu: tekijät, lisenssi, linkit, palautelomake ja Ko-fi.
 */

define('JASREK_DEV_EMAIL', 'jarno.jaskari@gmail.com');
define('JASREK_GITHUB', 'https://github.com/oh6kw/oh6kw-jasenrekisteri');
define('JASREK_KOFI', 'https://ko-fi.com/oh6kw');

// Linkit Lisäosat-sivulle lisäosan kuvauksen alle
add_filter('plugin_row_meta', function ($links, $file) {
    if ($file !== plugin_basename(JASREK_FILE)) return $links;
    $links[] = '<a href="' . esc_url(JASREK_GITHUB) . '" target="_blank" rel="noopener">GitHub</a>';
    $links[] = '<a href="' . esc_url(admin_url('admin.php?page=jasrek-tietoja#palaute')) . '">Palaute ja kehitysehdotukset</a>';
    $links[] = '<a href="' . esc_url(JASREK_KOFI) . '" target="_blank" rel="noopener">☕ Tarjoa kahvit (Ko-fi)</a>';
    return $links;
}, 10, 2);

add_action('admin_post_jasrek_feedback', 'jasrek_handle_feedback');
function jasrek_handle_feedback() {
    jasrek_check('jasrek_feedback');
    $uid = get_current_user_id();
    if (get_transient('jasrek_feedback_sent_' . $uid)) {
        jasrek_redirect('jasrek-tietoja', array('jasrek_msg' => 'feedback_wait'));
    }
    $tyypit = array('ehdotus' => 'Kehitysehdotus', 'virhe' => 'Virheilmoitus', 'kysymys' => 'Kysymys', 'muu' => 'Muu palaute');
    $tyyppi = isset($tyypit[jasrek_post('tyyppi')]) ? $tyypit[jasrek_post('tyyppi')] : 'Muu palaute';
    $viesti = trim(sanitize_textarea_field(jasrek_post('viesti')));
    $nimi   = trim(str_replace(array("\r", "\n", '<', '>', '"'), '', sanitize_text_field(jasrek_post('nimi'))));
    $email  = sanitize_email(jasrek_post('email'));
    if ($viesti === '') jasrek_redirect('jasrek-tietoja', array('jasrek_msg' => 'feedback_empty'));

    $body = $tyyppi . "\n\n" . $viesti . "\n\n--\n"
          . 'Lähettäjä: ' . ($nimi !== '' ? $nimi : '(ei nimeä)') . ($email ? ' <' . $email . '>' : '') . "\n";
    if (jasrek_post('tekniset') === '1') {
        global $wp_version;
        $body .= 'OH6KW Jäsenrekisteri ' . JASREK_VERSION . ' · WordPress ' . $wp_version . ' · PHP ' . PHP_VERSION . ' · ' . home_url() . "\n";
    }
    $headers = array('Content-Type: text/plain; charset=UTF-8');
    if ($email) $headers[] = 'Reply-To: ' . ($nimi !== '' ? $nimi . ' ' : '') . '<' . $email . '>';

    $ok = wp_mail(JASREK_DEV_EMAIL, '[OH6KW Jäsenrekisteri] ' . $tyyppi . ': ' . wp_trim_words($viesti, 8, '…'), $body, $headers);
    if ($ok) set_transient('jasrek_feedback_sent_' . $uid, 1, MINUTE_IN_SECONDS);
    jasrek_redirect('jasrek-tietoja', array('jasrek_msg' => $ok ? 'feedback_sent' : 'send_failed'));
}

function jasrek_page_about() {
    global $wp_version;
    $me = wp_get_current_user();
    ?>
    <div class="wrap jasrek-wrap jasrek-about">
        <h1>Tietoja ja palaute</h1>
        <?php jasrek_notice(); ?>

        <div class="jasrek-panel">
            <h2>OH6KW Jäsenrekisteri <?php echo esc_html(JASREK_VERSION); ?></h2>
            <p>Jäsenrekisteri ja jäsenmaksulaskutus yhdistyksille. Täysin ilmainen – kaikki ominaisuudet ovat mukana.</p>
            <p class="description">Jäsenrekisteri toimii vain hallintapaneelissa. Sivustolle ei julkaista mitään, eikä jäsentietoja näy kävijöille.</p>
            <dl>
                <dt>Tekijä</dt>
                <dd>Jarno Jaskari, OH6KW – alkuperäinen lisäosa, ideointi ja ylläpito</dd>
                <dt>Jatkokehitys</dt>
                <dd>Jarno Jaskari yhdessä Claude-tekoälyn (Anthropic) kanssa</dd>
                <dt>Yhteys</dt>
                <dd><a href="mailto:<?php echo esc_attr(JASREK_DEV_EMAIL); ?>"><?php echo esc_html(JASREK_DEV_EMAIL); ?></a></dd>
                <dt>Lähdekoodi</dt>
                <dd><a href="<?php echo esc_url(JASREK_GITHUB); ?>" target="_blank" rel="noopener">GitHub</a> ·
                    <a href="<?php echo esc_url(JASREK_GITHUB . '/issues'); ?>" target="_blank" rel="noopener">vikailmoitukset</a> ·
                    <a href="<?php echo esc_url(JASREK_GITHUB . '/blob/main/CHANGELOG.md'); ?>" target="_blank" rel="noopener">muutosloki</a> ·
                    <a href="<?php echo esc_url(JASREK_GITHUB . '/releases'); ?>" target="_blank" rel="noopener">uusimmat versiot</a></dd>
                <dt>Lisenssi</dt>
                <dd><a href="https://www.gnu.org/licenses/agpl-3.0.html" target="_blank" rel="noopener">GNU Affero General Public License v3.0</a> (AGPL-3.0-or-later). QR-koodikirjasto: Kazuhiko Arase, MIT-lisenssi.</dd>
                <dt>Ympäristö</dt>
                <dd>WordPress <?php echo esc_html($wp_version); ?> · PHP <?php echo esc_html(PHP_VERSION); ?></dd>
            </dl>
        </div>

        <?php jasrek_privacy_panel(); ?>

        <div class="jasrek-panel">
            <h2>Tarjoa kahvit</h2>
            <p>Jos lisäosasta on hyötyä yhdistyksellesi, voit tarjota kehittäjälle kahvit. Kiitos!</p>
            <p><a class="jasrek-kofi" href="<?php echo esc_url(JASREK_KOFI); ?>" target="_blank" rel="noopener">☕ Tarjoa kahvit Ko-fissa</a></p>
        </div>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="jasrek-panel jasrek-form" id="palaute">
            <input type="hidden" name="action" value="jasrek_feedback">
            <?php wp_nonce_field('jasrek_feedback'); ?>
            <h2>Palaute ja kehitysehdotukset</h2>
            <p class="description">Viesti lähetetään kehittäjälle osoitteeseen <?php echo esc_html(JASREK_DEV_EMAIL); ?> sivustosi sähköpostiasetuksilla. Jäsenrekisterin tietoja ei lähetetä. Voit myös avata <a href="<?php echo esc_url(JASREK_GITHUB . '/issues'); ?>" target="_blank" rel="noopener">GitHub-issuen</a>.</p>
            <div class="jasrek-grid">
                <p><label for="jasrek-fb-tyyppi">Aihe</label>
                   <select id="jasrek-fb-tyyppi" name="tyyppi">
                       <option value="ehdotus">Kehitysehdotus</option>
                       <option value="virhe">Virheilmoitus</option>
                       <option value="kysymys">Kysymys</option>
                       <option value="muu">Muu palaute</option>
                   </select></p>
                <p><label for="jasrek-fb-nimi">Nimesi</label>
                   <input type="text" id="jasrek-fb-nimi" name="nimi" value="<?php echo esc_attr($me->display_name); ?>"></p>
                <p><label for="jasrek-fb-email">Sähköpostisi (vastausta varten)</label>
                   <input type="email" id="jasrek-fb-email" name="email" value="<?php echo esc_attr($me->user_email); ?>"></p>
            </div>
            <p><label for="jasrek-fb-viesti">Viesti *</label>
               <textarea id="jasrek-fb-viesti" name="viesti" rows="6" required placeholder="Mitä toivoisit lisäosaan, tai mikä ei toiminut?"></textarea></p>
            <p><label class="jasrek-radio"><input type="checkbox" name="tekniset" value="1"> Liitä mukaan versiotiedot (OH6KW Jäsenrekisteri <?php echo esc_html(JASREK_VERSION); ?>, WordPress, PHP, sivuston osoite) – helpottaa vikojen selvittämistä</label></p>
            <p class="jasrek-actions"><button type="submit" class="button button-primary">Lähetä palaute</button></p>
        </form>
    </div>
    <?php
}
