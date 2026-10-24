<?php
if (!defined('ABSPATH')) exit;
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The plugin's own custom tables (members, invoices, audit log). The data is admin-only and changes on every write, so object caching is not used.

add_action('admin_post_jasrek_save_settings', 'jasrek_handle_save_settings');
function jasrek_handle_save_settings() {
    jasrek_check('jasrek_save_settings');
    $iban = jasrek_iban_clean(sanitize_text_field(jasrek_post('iban')));
    $maksukoodi = jasrek_post('maksukoodi');

    // Jäsenlajit: rinnakkaiset taulukot id[], nimi[], summa[]
    $lajit = array();
    $ids   = (array) jasrek_post('laji_id', array());
    $nimet = (array) jasrek_post('laji_nimi', array());
    $summat= (array) jasrek_post('laji_summa', array());
    foreach ($nimet as $i => $nimi) {
        $nimi = sanitize_text_field($nimi);
        if ($nimi === '') continue;
        $id = isset($ids[$i]) ? sanitize_key($ids[$i]) : '';
        if ($id === '' || isset($lajit[$id])) $id = 'l' . strtolower(wp_generate_password(6, false));
        $lajit[$id] = array('nimi' => $nimi, 'summa' => jasrek_parse_money(isset($summat[$i]) ? $summat[$i] : ''));
    }

    // Omat lisäkentät: kentta_id[], kentta_nimi[], kentta_tyyppi[], kentta_lista[]
    $kentat = array();
    $kids   = (array) jasrek_post('kentta_id', array());
    $knimet = (array) jasrek_post('kentta_nimi', array());
    $ktyyp  = (array) jasrek_post('kentta_tyyppi', array());
    $klista = (array) jasrek_post('kentta_lista', array());
    foreach ($knimet as $i => $nimi) {
        $nimi = sanitize_text_field($nimi);
        if ($nimi === '') continue;
        $kid = isset($kids[$i]) ? sanitize_key($kids[$i]) : '';
        if ($kid === '' || isset($kentat[$kid])) $kid = 'k' . strtolower(wp_generate_password(6, false));
        $kentat[$kid] = array(
            'nimi'   => $nimi,
            'tyyppi' => (isset($ktyyp[$i]) && isset(jasrek_lisa_tyypit()[$ktyyp[$i]])) ? $ktyyp[$i] : 'teksti',
            'lista'  => (isset($klista[$i]) && $klista[$i] === '1') ? '1' : '0',
        );
    }

    $vanha = jasrek_settings();
    $s = array(
        'yhdistys'         => sanitize_text_field(jasrek_post('yhdistys')),
        'iban'             => $iban,
        'bic'              => strtoupper(sanitize_text_field(jasrek_post('bic'))),
        'ytunnus'          => sanitize_text_field(jasrek_post('ytunnus')),
        'maksukoodi'       => in_array($maksukoodi, array('qr', 'viivakoodi', 'molemmat', 'ei'), true) ? $maksukoodi : 'viivakoodi',
        'sposti_malli'     => jasrek_post('sposti_malli') === 'perus' ? 'perus' : 'maksukortti',
        'sposti_pdf'       => jasrek_post('sposti_pdf') === '1' ? '1' : '0',
        'kp_pankki'        => sanitize_text_field(jasrek_post('kp_pankki')),
        'kp_tuotot'        => sanitize_text_field(jasrek_post('kp_tuotot')),
        'kp_posti'         => sanitize_text_field(jasrek_post('kp_posti')),
        'lahettaja_nimi'   => sanitize_text_field(jasrek_post('lahettaja_nimi')),
        'lahettaja_email'  => sanitize_email(jasrek_post('lahettaja_email')),
        'vastaus_email'    => sanitize_email(jasrek_post('vastaus_email')),
        'yhteystiedot'     => sanitize_textarea_field(jasrek_post('yhteystiedot')),
        'tunnus_kaytossa'  => jasrek_post('tunnus_kaytossa') === '1' ? '1' : '0',
        'tunnus_nimi'      => sanitize_text_field(jasrek_post('tunnus_nimi')),
        'tunnus_isot'      => jasrek_post('tunnus_isot') === '1' ? '1' : '0',
        'jasenlajit'       => $lajit,
        'lisakentat'       => $kentat,
        'osoite'           => sanitize_textarea_field(jasrek_post('osoite')),
        'postiosoite'      => sanitize_textarea_field(jasrek_post('postiosoite')),
        'paperi_malli'     => jasrek_post('paperi_malli') === 'tilisiirto' ? 'tilisiirto' : 'perus',
        'merkki_kaytossa'  => jasrek_post('merkki_kaytossa') === '1' ? '1' : '0',
        'merkki_ika'       => implode(', ', jasrek_vuosilista(jasrek_post('merkki_ika'))),
        'merkki_jasen'     => implode(', ', jasrek_vuosilista(jasrek_post('merkki_jasen'))),
        'postimaksu'       => (string) min(999.99, max(0, jasrek_parse_money(jasrek_post('postimaksu')))),
        'loki_kk'          => in_array(jasrek_post('loki_kk'), array('0', '12', '24', '36', '60', '120'), true) ? jasrek_post('loki_kk') : '24',
        'auto_muistutus'   => jasrek_post('auto_muistutus') === '1' ? '1' : '0',
        'auto_paivia'      => (string) max(1, min(365, intval(jasrek_post('auto_paivia', 14)))),
        'auto_max'         => (string) max(1, min(10, intval(jasrek_post('auto_max', 1)))),
        'auto_raportti'    => jasrek_post('auto_raportti') === '1' ? '1' : '0',
        'poista_tiedot'    => jasrek_post('poista_tiedot') === '1' ? '1' : '0',
        'viesti_oletus'    => sanitize_textarea_field(jasrek_post('viesti_oletus')),
        'muistutus_teksti' => sanitize_textarea_field(jasrek_post('muistutus_teksti')),
        'tarrat'           => $vanha['tarrat'], // tarrasivun muistamat valinnat säilyvät
        'vahvistus_kaytossa' => jasrek_post('vahvistus_kaytossa') === '1' ? '1' : '0',
        'omat_tiedot_kentat' => (function () {
            $kaikki = array_keys(jasrek_omat_tiedot_valinnat());
            $val = isset($_POST['ot_kentat']) ? array_map('sanitize_key', (array) wp_unslash($_POST['ot_kentat'])) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce checked at the top of the handler.
            $val = array_values(array_intersect($kaikki, $val));
            if (!$val) $val = array('nimi');
            return count($val) === count($kaikki) ? 'kaikki' : implode(',', $val);
        })(),
        'vahvistus_otsikko'  => sanitize_text_field(jasrek_post('vahvistus_otsikko')),
        'vahvistus_teksti'   => sanitize_textarea_field(jasrek_post('vahvistus_teksti')),
        'autobackup'       => jasrek_post('autobackup') === '1' ? '1' : '0',
        'autobackup_vali'  => array_key_exists(jasrek_post('autobackup_vali'), jasrek_autobackup_valit()) ? jasrek_post('autobackup_vali') : 'kk',
        'autobackup_aika'  => array_key_exists(jasrek_post('autobackup_aika'), jasrek_autobackup_ajat()) ? (string) jasrek_post('autobackup_aika') : '3',
        'autobackup_to'    => implode(', ', array_filter(array_map('sanitize_email', preg_split('/[\s,;]+/', (string) jasrek_post('autobackup_to'))), 'is_email')),
        'autobackup_loki'  => jasrek_post('autobackup_loki') === '1' ? '1' : '0',
    );
    update_option('jasrek_settings', $s);
    jasrek_log_settings_change($vanha, jasrek_settings());
    jasrek_schedule_cron();
    jasrek_schedule_autobackup();
    jasrek_redirect('jasrek-asetukset', array('jasrek_msg' => ($iban !== '' && !jasrek_iban_valid($iban)) ? 'iban_invalid' : 'settings_saved'));
}

/** Kirjaa muuttuneet asetukset muutoslokiin. */
function jasrek_log_settings_change($a, $b) {
    $labels = array(
        'yhdistys' => 'Yhdistyksen nimi', 'iban' => 'IBAN', 'bic' => 'BIC', 'ytunnus' => 'Y-tunnus', 'maksukoodi' => 'Maksukoodi', 'sposti_malli' => 'Sähköpostilaskun malli', 'sposti_pdf' => 'Sähköpostilasku PDF-liitteenä', 'vahvistus_kaytossa' => 'Maksuvahvistus jäsenelle', 'omat_tiedot_kentat' => '{omat_tiedot}-kentät', 'vahvistus_otsikko' => 'Maksuvahvistuksen otsikko', 'vahvistus_teksti' => 'Maksuvahvistuksen teksti', 'autobackup' => 'Automaattinen varmuuskopio', 'autobackup_vali' => 'Automaattinen varmuuskopio: väli', 'autobackup_aika' => 'Automaattinen varmuuskopio: kellonaika', 'autobackup_to' => 'Automaattinen varmuuskopio: vastaanottajat', 'autobackup_loki' => 'Automaattinen varmuuskopio: muutosloki mukaan', 'kp_pankki' => 'Kirjanpito: pankkitili', 'kp_tuotot' => 'Kirjanpito: jäsenmaksutuotot', 'kp_posti' => 'Kirjanpito: postimaksut',
        'yhteystiedot' => 'Yhteystiedot', 'lahettaja_nimi' => 'Lähettäjän nimi', 'lahettaja_email' => 'Lähettäjän osoite',
        'vastaus_email' => 'Vastausosoite', 'tunnus_kaytossa' => 'Lisätunniste käytössä', 'tunnus_nimi' => 'Lisätunnisteen nimi',
        'tunnus_isot' => 'Lisätunniste isoilla', 'auto_muistutus' => 'Automaattinen muistutus', 'auto_paivia' => 'Muistutus päivää eräpäivän jälkeen',
        'auto_max' => 'Muistutuksia enintään', 'auto_raportti' => 'Muistutusten yhteenveto', 'poista_tiedot' => 'Poista tiedot lisäosan poistossa',
        'loki_kk' => 'Muutoslokin säilytys (kk)', 'postimaksu' => 'Postimaksu paperilaskuihin', 'osoite' => 'Yhdistyksen osoite', 'postiosoite' => 'Palautusosoite (kuoren ikkuna)', 'paperi_malli' => 'Paperilaskun malli', 'merkki_ika' => 'Merkkipäivät: täytettävät vuodet', 'merkki_jasen' => 'Merkkipäivät: jäsenyysvuodet',
        'merkki_kaytossa' => 'Merkkipäivät käytössä', 'viesti_oletus' => 'Laskun saateteksti', 'muistutus_teksti' => 'Muistutuksen teksti',
    );
    $fmt = function ($k, $v) {
        if (in_array($k, array('sposti_pdf', 'vahvistus_kaytossa', 'autobackup', 'autobackup_loki', 'tunnus_kaytossa', 'tunnus_isot', 'auto_muistutus', 'auto_raportti', 'poista_tiedot', 'merkki_kaytossa'), true)) return $v === '1' ? 'kyllä' : 'ei';
        return (string) $v;
    };
    $old = array(); $new = array();
    foreach ($labels as $k => $label) {
        $old[$label] = $fmt($k, isset($a[$k]) ? $a[$k] : '');
        $new[$label] = $fmt($k, isset($b[$k]) ? $b[$k] : '');
    }
    $extra = function ($src) {
        $out = array();
        foreach ((array) $src['jasenlajit'] as $l) $out['Jäsenluokka ' . $l['nimi']] = html_entity_decode(jasrek_money($l['summa']));
        foreach ((array) $src['lisakentat'] as $k) $out['Lisäkenttä ' . $k['nimi']] = (isset(jasrek_lisa_tyypit()[$k['tyyppi']]) ? jasrek_lisa_tyypit()[$k['tyyppi']] : $k['tyyppi']) . ($k['lista'] === '1' ? ', listassa' : '');
        return $out;
    };
    $diff = jasrek_log_diff($old + $extra($a), $new + $extra($b));
    if ($diff) jasrek_log('asetukset', 0, 'asetukset', count($diff) . ' asetusta muutettu', $diff);
}

function jasrek_page_settings() {
    global $wpdb;
    if (jasrek_get('view') === 'palautus') {
        jasrek_backup_confirm_page();
        return;
    }
    $s = jasrek_settings();
    $site_host = wp_parse_url(home_url(), PHP_URL_HOST);
    $from_host = $s['lahettaja_email'] ? substr(strrchr($s['lahettaja_email'], '@'), 1) : '';
    $laji_counts = $wpdb->get_results("SELECT jasenlaji, COUNT(*) AS n FROM {$wpdb->jasrek_jasenet} WHERE tila = 'aktiivinen' GROUP BY jasenlaji", OBJECT_K);
    $raportti_to = $s['vastaus_email'] ? $s['vastaus_email'] : ($s['lahettaja_email'] ? $s['lahettaja_email'] : get_option('admin_email'));
    $next = wp_next_scheduled('jasrek_auto_muistutus');
    ?>
    <div class="wrap jasrek-wrap">
        <h1>Jäsenrekisterin asetukset</h1>
        <?php jasrek_notice(); ?>
        <nav class="nav-tab-wrapper jasrek-navtabs jasrek-settabs">
            <a class="nav-tab" href="#yhdistys" data-tab="yhdistys">Yhdistys</a>
            <a class="nav-tab" href="#jasenet" data-tab="jasenet">Jäsenet</a>
            <a class="nav-tab" href="#laskut" data-tab="laskut">Laskut</a>
            <a class="nav-tab" href="#sposti" data-tab="sposti">Sähköposti</a>
            <a class="nav-tab" href="#yllapito" data-tab="yllapito">Ylläpito</a>
        </nav>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="jasrek-form">
            <input type="hidden" name="action" value="jasrek_save_settings">
            <?php wp_nonce_field('jasrek_save_settings'); ?>


            <div class="jasrek-tab" data-tab="yhdistys">
            <div class="jasrek-panel">
                <h2>Yhdistys ja tilitiedot</h2>
                <p class="description">Näkyvät laskuissa ja tilisiirtolomakkeessa.</p>
                <div class="jasrek-grid">
                    <p class="jasrek-span-2"><label for="jasrek-yhdistys">Yhdistyksen nimi (laskun saaja)</label>
                       <input type="text" id="jasrek-yhdistys" name="yhdistys" value="<?php echo esc_attr($s['yhdistys']); ?>"></p>
                    <p><label for="jasrek-iban">Tilinumero (IBAN)</label>
                       <input type="text" id="jasrek-iban" name="iban" value="<?php echo esc_attr(jasrek_iban_format($s['iban'])); ?>" placeholder="FI00 1234 5600 0007 85" class="jasrek-upper">
                       <?php if ($s['iban'] !== ''): ?>
                           <?php if (jasrek_iban_valid($s['iban'])): ?><span class="jasrek-badge jasrek-badge-ok">✓ kelvollinen</span>
                           <?php else: ?><span class="jasrek-badge jasrek-badge-err">tarkista numero</span><?php endif; ?>
                       <?php endif; ?></p>
                    <p><label for="jasrek-bic">BIC (valinnainen)</label>
                       <input type="text" id="jasrek-bic" name="bic" value="<?php echo esc_attr($s['bic']); ?>" class="jasrek-upper"></p>
                    <p><label for="jasrek-ytunnus">Y-tunnus (valinnainen)</label>
                       <input type="text" id="jasrek-ytunnus" name="ytunnus" value="<?php echo esc_attr($s['ytunnus']); ?>"></p>
                </div>
                <p><label for="jasrek-osoite">Yhdistyksen osoite</label>
                   <textarea id="jasrek-osoite" name="osoite" rows="2" style="max-width:420px" placeholder="esim. Kerhotie 1&#10;60100 Seinäjoki"><?php echo esc_textarea($s['osoite']); ?></textarea>
                   <span class="description">Yhdistyksen virallinen osoite. Näkyy tilisiirtolomakkeen Saaja-kentässä ja tietosuojaselosteen rekisterinpitäjän tiedoissa. Kuoren palautusosoitteen voi antaa erikseen kohdassa <em>Paperilaskut</em>.</span></p>
            </div>
            </div>
            <div class="jasrek-tab" data-tab="jasenet">
            <div class="jasrek-panel" id="jasrek-lisatunniste">
                <h2>Jäsenten lisätunniste</h2>
                <span class="description">Vapaaehtoinen lisäkenttä jäsenille, esim. jäsennumero, tunnus tai kutsutunnus. Näkyy jäsenlistassa, jäsenen lomakkeella ja laskussa. Nimen voi vaihtaa vapaasti.</span>
                    <label class="jasrek-radio"><input type="checkbox" name="tunnus_kaytossa" value="1" <?php checked($s['tunnus_kaytossa'], '1'); ?>> Lisätunniste käytössä</label>
                    <div class="jasrek-grid">
                        <p><label for="jasrek-tnimi">Kentän nimi</label>
                           <input type="text" id="jasrek-tnimi" name="tunnus_nimi" value="<?php echo esc_attr($s['tunnus_nimi']); ?>" placeholder="esim. Jäsennumero, Tunnus, Kutsutunnus"></p>
                        <p><label class="jasrek-radio" style="margin-top:28px"><input type="checkbox" name="tunnus_isot" value="1" <?php checked($s['tunnus_isot'], '1'); ?>> Muunna isoiksi kirjaimiksi (esim. kutsutunnukset)</label></p>
                    </div>
            </div>

            <div class="jasrek-panel" id="jasrek-luokat">
                <h2>Jäsenluokat</h2>
                <p class="description">Esim. Jäsen, Nuorisojäsen, Perhejäsen, Kunniajäsen. Jokaiselle luokalle oma jäsenmaksu. <strong>Jäsenmaksu 0 € = luokan jäsenille ei tehdä laskua</strong> (esim. kunniajäsenet), mutta he saavat tiedotteet. Luokat näkyvät jäsenlistassa välilehtinä. Summia voi vielä muuttaa laskua tehdessä.</p>
                <table class="widefat jasrek-lajit" id="jasrek-lajit">
                    <thead><tr><th>Jäsenluokka</th><th style="width:140px">Jäsenmaksu (€)</th><th style="width:150px">Jäseniä</th><th style="width:60px"></th></tr></thead>
                    <tbody>
                    <?php foreach ($s['jasenlajit'] as $id => $l): ?>
                        <tr>
                            <td><input type="hidden" name="laji_id[]" value="<?php echo esc_attr($id); ?>">
                                <input type="text" name="laji_nimi[]" value="<?php echo esc_attr($l['nimi']); ?>"></td>
                            <td><input type="text" name="laji_summa[]" inputmode="decimal" value="<?php echo esc_attr(number_format((float) $l['summa'], 2, ',', '')); ?>"></td>
                            <td><?php echo isset($laji_counts[$id]) ? (int) $laji_counts[$id]->n : 0; ?><?php echo (float) $l['summa'] <= 0 ? ' <span class="jasrek-badge jasrek-badge-purple">ei maksua</span>' : ''; ?></td>
                            <td><button type="button" class="button-link jasrek-laji-del" aria-label="Poista">✕</button></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <template id="jasrek-laji-tpl">
                    <tr>
                        <td><input type="hidden" name="laji_id[]" value=""><input type="text" name="laji_nimi[]" placeholder="esim. Nuorisojäsen"></td>
                        <td><input type="text" name="laji_summa[]" inputmode="decimal" placeholder="0,00"></td>
                        <td>–</td>
                        <td><button type="button" class="button-link jasrek-laji-del" aria-label="Poista">✕</button></td>
                    </tr>
                </template>
                <p><button type="button" class="button" id="jasrek-laji-add">+ Lisää jäsenluokka</button></p>
                <p class="description">Jos poistat luokan, sen jäsenet siirtyvät välilehdelle ”Ilman jäsenluokkaa”, josta heille voi valita uuden luokan joukkona. Luokan voi nimetä uudelleen – jäsenet pysyvät siinä.</p>
            </div>

            <div class="jasrek-panel" id="jasrek-lisakentat">
                <h2>Omat lisäkentät</h2>
                <p class="description">Yhdistyksen omat tiedot jäsenille, esim. <em>Avain</em> (kerhotalon avaimen numero), <em>Lehti paperilla</em> tai <em>Talkoovastaava</em>.
                    <strong>Teksti</strong>-kenttään voi kirjoittaa arvon (esim. avaimen numero 8.59 – tyhjä tarkoittaa, ettei avainta ole).
                    <strong>Valintaruutu</strong> on pelkkä kyllä/ei-ruksi. <strong>Päivämäärä</strong> tallentaa päivän (esim. avaimen luovutus).
                    Kentät näkyvät jäsenen lomakkeella, CSV-viennissä ja -tuonnissa, ja halutessa omana sarakkeenaan jäsenlistassa.
                    Jäsenlistan haku löytää kentän nimellä (esim. ”avain”) kaikki, joilla kenttä on täytetty.</p>
                <table class="widefat jasrek-lajit" id="jasrek-kentat">
                    <thead><tr><th>Kentän nimi</th><th style="width:220px">Tyyppi</th><th style="width:140px">Jäsenlistassa</th><th style="width:50px"></th></tr></thead>
                    <tbody>
                    <?php foreach ($s['lisakentat'] as $kid => $k): ?>
                        <tr>
                            <td><input type="hidden" name="kentta_id[]" value="<?php echo esc_attr($kid); ?>">
                                <input type="text" name="kentta_nimi[]" value="<?php echo esc_attr($k['nimi']); ?>"></td>
                            <td><select name="kentta_tyyppi[]"><?php foreach (jasrek_lisa_tyypit() as $tk => $tn): ?><option value="<?php echo esc_attr($tk); ?>" <?php selected($k['tyyppi'], $tk); ?>><?php echo esc_html($tn); ?></option><?php endforeach; ?></select></td>
                            <td><select name="kentta_lista[]"><option value="1" <?php selected($k['lista'], '1'); ?>>Näytä sarake</option><option value="0" <?php selected($k['lista'], '0'); ?>>Ei</option></select></td>
                            <td><button type="button" class="button-link jasrek-row-del" data-confirm-del="Poistetaanko lisäkenttä? Kenttään tallennetut tiedot poistuvat jäseniltä, kun jäsenen tiedot seuraavan kerran tallennetaan.">✕</button></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <template id="jasrek-kentta-tpl">
                    <tr>
                        <td><input type="hidden" name="kentta_id[]" value=""><input type="text" name="kentta_nimi[]" placeholder="esim. Avain"></td>
                        <td><select name="kentta_tyyppi[]"><?php foreach (jasrek_lisa_tyypit() as $tk => $tn): ?><option value="<?php echo esc_attr($tk); ?>"><?php echo esc_html($tn); ?></option><?php endforeach; ?></select></td>
                        <td><select name="kentta_lista[]"><option value="1">Näytä sarake</option><option value="0">Ei</option></select></td>
                        <td><button type="button" class="button-link jasrek-row-del">✕</button></td>
                    </tr>
                </template>
                <p><button type="button" class="button jasrek-row-add" data-tpl="#jasrek-kentta-tpl" data-table="#jasrek-kentat">+ Lisää kenttä</button></p>
            </div>

            <div class="jasrek-panel" id="jasrek-merkkipaivat">
                <h2>Merkkipäivät</h2>
                <p class="description">Vuosiraportti ja jäsenlista kertovat, ketkä täyttävät valittuja tasavuosia tai ovat olleet jäsenenä valitut vuodet. Täytettävät vuodet lasketaan jäsenen tiedoissa olevasta <em>syntymävuodesta tai -päivästä</em>. Kenttä on vapaaehtoinen, ja se näkyy jäsenen lomakkeella vain, kun merkkipäivät ovat käytössä. Jos jäsenestä on merkitty vain syntymävuosi, merkkipäivä näkyy vuosiraportissa ilman päivää. Jäsenyysvuodet lasketaan liittymispäivästä. Tyhjä kenttä = sitä merkkipäivää ei näytetä.</p>
                <p><label class="jasrek-radio"><input type="checkbox" name="merkki_kaytossa" value="1" <?php checked($s['merkki_kaytossa'], '1'); ?>> Merkkipäivät käytössä</label>
                   <span class="description">Pois päältä merkkipäiviä ei näytetä jäsenlistassa, vuosiraportissa eikä Excel-viennissä, eikä jäsenen lomakkeella kysytä syntymävuotta. Asetetut vuodet ja tallennetut syntymävuodet säilyvät.</span></p>
                <div class="jasrek-grid">
                    <p><label for="jasrek-merkki-ika">Täytettävät vuodet</label>
                       <input type="text" id="jasrek-merkki-ika" name="merkki_ika" value="<?php echo esc_attr($s['merkki_ika']); ?>" placeholder="50, 60, 70, 80, 90"></p>
                    <p><label for="jasrek-merkki-jasen">Jäsenyysvuodet</label>
                       <input type="text" id="jasrek-merkki-jasen" name="merkki_jasen" value="<?php echo esc_attr($s['merkki_jasen']); ?>" placeholder="10, 20, 25, 30, 40, 50"></p>
                </div>
            </div>
            </div>
            <div class="jasrek-tab" data-tab="laskut">
            <div class="jasrek-panel" id="jasrek-laskut-yleiset">
                <h2>Kaikki laskut</h2>
                <p class="description">Koskevat sekä sähköposti- että paperilaskuja.</p>
                <p><label for="jasrek-vo">Laskun saateteksti (oletus uusille laskuille)</label>
                   <textarea id="jasrek-vo" name="viesti_oletus" rows="7"><?php echo esc_textarea($s['viesti_oletus']); ?></textarea>
                   <?php echo wp_kses_post(jasrek_placeholders('jasrek-vo', 'lasku')); ?></p>
                <p><label for="jasrek-maksukoodi">Maksukoodi laskussa</label>
                   <select id="jasrek-maksukoodi" name="maksukoodi">
                       <option value="molemmat" <?php selected($s['maksukoodi'], 'molemmat'); ?>>QR-koodi ja pankkiviivakoodi</option>
                       <option value="qr" <?php selected($s['maksukoodi'], 'qr'); ?>>Vain QR-koodi</option>
                       <option value="viivakoodi" <?php selected($s['maksukoodi'], 'viivakoodi'); ?>>Vain pankkiviivakoodi</option>
                       <option value="ei" <?php selected($s['maksukoodi'], 'ei'); ?>>Ei kuvaa (vain virtuaaliviivakoodi tekstinä)</option>
                   </select>
                   <span class="description">QR-koodi on EPC-standardin mukainen (viite RF-muodossa, jonka pankit kohdistavat samaan viitteeseen). Pankkiviivakoodi on perinteinen suomalainen laskun viivakoodi. Virtuaaliviivakoodi tekstinä on laskussa aina.</span></p>
            </div>

            <div class="jasrek-panel" id="jasrek-sposti-laskut">
                <h2>Sähköpostilaskut</h2>
                <fieldset class="jasrek-fieldset"><legend>Laskun malli</legend>
                    <label class="jasrek-radio"><input type="radio" name="sposti_malli" value="perus" <?php checked($s['sposti_malli'], 'perus'); ?>> <strong>Perusmalli</strong> – maksutiedot taulukkona sekä QR-koodi ja pankkiviivakoodi kuvina (kohdan <em>Maksukoodi laskussa</em> mukaan).</label>
                    <label class="jasrek-radio"><input type="radio" name="sposti_malli" value="maksukortti" <?php checked($s['sposti_malli'], 'maksukortti'); ?>> <strong>Maksukortti</strong> (suositus) – summa ja eräpäivä isolla, maksun tiedot allekkain helposti kopioitavina, virtuaaliviivakoodi ja QR-koodi (jos valittu kohdassa <em>Maksukoodi laskussa</em>). Toimii hyvin puhelimessa ja tietokoneella. Pankkiviivakoodia ei näytetä, koska sitä ei voi lukea näytöltä – tulostettava lasku viivakoodeineen tulee PDF-liitteenä, jos se on valittu alla.</label>
                    <label class="jasrek-radio"><input type="checkbox" name="sposti_pdf" value="1" <?php checked($s['sposti_pdf'], '1'); ?>> <strong>Liitä lasku PDF:nä</strong> – viestin liitteenä sama lasku kuin paperilaskussa: A4-sivu tilisiirtolomakkeineen, QR-koodi ja pankkiviivakoodi kohdan <em>Maksukoodi laskussa</em> mukaan. Jäsen voi tulostaa ja arkistoida laskun. Liite kasvattaa viestiä noin 5–10 kt. Koskee myös muistutuksia ja testilaskuja.</label>
                </fieldset>
            </div>

            <div class="jasrek-panel" id="jasrek-paperi-asetukset">
                <h2>Paperilaskut</h2>
                <p class="description">Paperilaskun saavat jäsenet, joille on valittu ”Haluaa laskun paperilla”, sekä jäsenet ilman sähköpostia.</p>
                <p><label for="jasrek-postimaksu">Postimaksu (€)</label>
                   <input type="text" id="jasrek-postimaksu" name="postimaksu" inputmode="decimal" style="max-width:140px" value="<?php echo esc_attr(number_format((float) $s['postimaksu'], 2, ',', '')); ?>" placeholder="0,00">
                   <span class="description">Lisätään jäsenmaksuun vain paperilaskun saajille, esim. postimerkin ja kirjekuoren hinta. 0 = ei postimaksua. Summaa voi muuttaa laskua tehdessä.
                   Postimaksu näkyy laskussa omana rivinään, ja se on mukana QR- ja viivakoodin summassa. Sähköpostilaskuihin sitä ei lisätä.
                   Saatetekstissä <code>{summa}</code> on jäsenmaksu ilman postimaksua ja <code>{maksettava}</code> maksettava yhteensä.</span></p>
                <fieldset class="jasrek-fieldset"><legend>Laskun malli</legend>
                    <label class="jasrek-radio"><input type="radio" name="paperi_malli" value="perus" <?php checked($s['paperi_malli'], 'perus'); ?>> <strong>Perusmalli</strong> – maksutiedot, QR-koodi ja pankkiviivakoodi saatteen alla.</label>
                    <label class="jasrek-radio"><input type="radio" name="paperi_malli" value="tilisiirto" <?php checked($s['paperi_malli'], 'tilisiirto'); ?>> <strong>Tilisiirtolomake</strong> – perinteinen suomalainen pankkisiirtolomake sivun alareunassa, QR-koodi sen yläpuolella. Saateteksti mahtuu noin puolelle sivulle.</label>
                    <span class="description">Kummassakin mallissa jäsenen nimi ja osoite osuvat ikkunakuoren (C5 tai DL) ikkunaan. QR-koodi ja pankkiviivakoodi tulostuvat kohdan <em>Maksukoodi</em> mukaan.</span>
                </fieldset>
                <p><label for="jasrek-postiosoite">Palautusosoite (kuoren ikkuna)</label>
                   <textarea id="jasrek-postiosoite" name="postiosoite" rows="2" style="max-width:420px" placeholder="esim. Rahastonhoitaja Matti Meikäläinen&#10;Kotikatu 5, 60100 Seinäjoki"><?php echo esc_textarea($s['postiosoite']); ?></textarea>
                   <span class="description">Tulostetaan pienellä ikkunakuoren ikkunaan vastaanottajan yläpuolelle, jotta kirje palautuu, jos sitä ei voida toimittaa. Voi olla eri kuin yhdistyksen osoite, esim. rahastonhoitajan osoite, jos yhdistyksellä ei ole postilaatikkoa. Yhdistyksen nimi lisätään eteen automaattisesti.
                   Mahtuu enintään kaksi riviä (noin 110 merkkiä yhdistyksen nimi mukaan lukien); pidempi teksti pienennetään tai siitä varoitetaan tulostettaessa. Tyhjänä palautusosoitetta ei tulosteta.</span></p>
            </div>

            <div class="jasrek-panel" id="jasrek-muistutukset">
                <h2>Maksumuistutukset</h2>
                <p><label for="jasrek-mt">Maksumuistutuksen teksti</label>
                   <textarea id="jasrek-mt" name="muistutus_teksti" rows="6"><?php echo esc_textarea($s['muistutus_teksti']); ?></textarea>
                   <?php echo wp_kses_post(jasrek_placeholders('jasrek-mt', 'lasku')); ?></p>
                <h3>Automaattinen maksumuistutus</h3>
                <p><label class="jasrek-radio"><input type="checkbox" name="auto_muistutus" value="1" <?php checked($s['auto_muistutus'], '1'); ?>> Lähetä maksamattomille muistutus automaattisesti</label></p>
                <div class="jasrek-grid">
                    <p><label for="jasrek-apaiv">Päivää eräpäivän jälkeen</label>
                       <input type="number" id="jasrek-apaiv" name="auto_paivia" min="1" max="365" value="<?php echo esc_attr($s['auto_paivia']); ?>"></p>
                    <p><label for="jasrek-amax">Muistutuksia enintään</label>
                       <input type="number" id="jasrek-amax" name="auto_max" min="1" max="10" value="<?php echo esc_attr($s['auto_max']); ?>">
                       <span class="description">Seuraava muistutus lähtee aina saman päivämäärän kuluttua edellisestä.</span></p>
                </div>
                <p><label class="jasrek-radio"><input type="checkbox" name="auto_raportti" value="1" <?php checked($s['auto_raportti'], '1'); ?>> Lähetä yhteenveto lähetetyistä muistutuksista osoitteeseen <?php echo esc_html($raportti_to); ?></label></p>
                <p class="description">Muistutuksen saavat vain jäsenet, joille lasku on jo lähetetty ja joilla on sähköpostiosoite. Automaattisen muistutuksen voi kytkeä pois myös yksittäiseltä laskulta. WordPress tarkistaa tilanteen kerran tunnissa, kun sivustolla on kävijöitä.
                <?php if ($s['auto_muistutus'] === '1' && $next): ?><br>Seuraava tarkistus: <?php echo esc_html(wp_date('j.n.Y H:i', $next)); ?><?php endif; ?></p>
            </div>

            <div class="jasrek-panel" id="jasrek-vahvistus">
                <h2>Maksuvahvistus jäsenelle</h2>
                <p><label class="jasrek-radio"><input type="checkbox" name="vahvistus_kaytossa" value="1" <?php checked($s['vahvistus_kaytossa'], '1'); ?>> Lähetä jäsenelle sähköpostilla vahvistus, kun hänen maksunsa kirjataan</label></p>
                <p class="description">Kun maksuja kirjataan pankkitiedostosta tai joukkotoiminnolla, kirjauksen yhteydessä on ruksi <em>Lähetä maksuvahvistus</em> (valmiiksi valittuna), jonka voi poistaa esim. vanhoja maksuja kirjattaessa. Laskun sivulla maksetun rivin kohdalla on lisäksi painike <em>Lähetä vahvistus</em>. Vahvistukset lähtevät taustalla pienissä erissä. Vain jäsenille, joilla on sähköpostiosoite.</p>
                <p><label for="jasrek-vot">Otsikko</label>
                   <input type="text" id="jasrek-vot" name="vahvistus_otsikko" value="<?php echo esc_attr($s['vahvistus_otsikko']); ?>"></p>
                <p><label for="jasrek-vte">Viesti</label>
                   <textarea id="jasrek-vte" name="vahvistus_teksti" rows="6"><?php echo esc_textarea($s['vahvistus_teksti']); ?></textarea>
                   <?php echo wp_kses_post(jasrek_placeholders('jasrek-vte', 'vahvistus', '{summa} on maksettu summa.')); ?></p>
            </div>

            <div class="jasrek-panel" id="jasrek-kirjanpito">
                <h2>Jäsenmaksut kirjanpitoon</h2>
                <p class="description">Tilit raportissa <a href="<?php echo esc_url(admin_url('admin.php?page=jasrek-raportti&view=kirjanpito')); ?>">Vuosiraportti → Jäsenmaksut kirjanpitoon</a>. Kirjoita kirjanpitäjän käyttämät tilit, esim. <em>1910 Pankkitili</em> ja <em>3000 Jäsenmaksutuotot</em>. Tyhjänä käytetään tilin nimeä ilman numeroa.</p>
                <div class="jasrek-grid">
                    <p><label for="jasrek-kp-pankki">Pankkitili (debet)</label>
                       <input type="text" id="jasrek-kp-pankki" name="kp_pankki" value="<?php echo esc_attr($s['kp_pankki']); ?>" placeholder="Pankkitili"></p>
                    <p><label for="jasrek-kp-tuotot">Jäsenmaksutuotot (kredit)</label>
                       <input type="text" id="jasrek-kp-tuotot" name="kp_tuotot" value="<?php echo esc_attr($s['kp_tuotot']); ?>" placeholder="Jäsenmaksutuotot"></p>
                    <p><label for="jasrek-kp-posti">Paperilaskujen postimaksut (kredit)</label>
                       <input type="text" id="jasrek-kp-posti" name="kp_posti" value="<?php echo esc_attr($s['kp_posti']); ?>" placeholder="Postimaksut">
                       <span class="description">Voi olla sama kuin jäsenmaksutuotot, tai esim. postikulujen tili.</span></p>
                </div>
            </div>
            </div>
            <div class="jasrek-tab" data-tab="sposti">
            <div class="jasrek-panel">
                <h2>Sähköpostin lähettäjä</h2>
                <div class="jasrek-grid">
                    <p><label for="jasrek-lnimi">Lähettäjän nimi</label>
                       <input type="text" id="jasrek-lnimi" name="lahettaja_nimi" value="<?php echo esc_attr($s['lahettaja_nimi']); ?>"></p>
                    <p><label for="jasrek-lemail">Lähettäjän osoite</label>
                       <input type="email" id="jasrek-lemail" name="lahettaja_email" value="<?php echo esc_attr($s['lahettaja_email']); ?>"></p>
                    <p><label for="jasrek-vemail">Vastausosoite (esim. rahastonhoitaja)</label>
                       <input type="email" id="jasrek-vemail" name="vastaus_email" value="<?php echo esc_attr($s['vastaus_email']); ?>"></p>
                </div>
                <?php if ($from_host && $site_host && stripos($site_host, $from_host) === false && stripos($from_host, $site_host) === false): ?>
                    <p class="jasrek-hint">⚠ Lähettäjän osoite (@<?php echo esc_html($from_host); ?>) ei ole sivuston verkkotunnuksessa. Laskut voivat päätyä roskapostiin, ellei sivustolla ole SMTP-lisäosaa, joka lähettää kyseisen osoitteen kautta.</p>
                <?php endif; ?>
                <p class="description">Suositus: asenna SMTP-lisäosa (esim. WP Mail SMTP tai FluentSMTP), jolloin viestit lähtevät yhdistyksen oikean sähköpostitilin kautta eivätkä päädy roskapostiin. Kokeile asetukset lähettämällä testilasku itsellesi.</p>
                <p><label for="jasrek-yhteystiedot">Yhteystiedot viestien alalaitaan</label>
                   <textarea id="jasrek-yhteystiedot" name="yhteystiedot" rows="3" placeholder="Esim. Yhdistys ry · rahastonhoitaja@example.fi · www.example.fi"><?php echo esc_textarea($s['yhteystiedot']); ?></textarea></p>
            </div>

            <div class="jasrek-panel" id="jasrek-omat-tiedot">
                <h2>Jäsenen omat tiedot viesteissä <code>{omat_tiedot}</code></h2>
                <p class="description">Valitse, mitkä tiedot paikkamerkki <code>{omat_tiedot}</code> näyttää jäsenelle tietojentarkistuspyynnössä ja laskun saatteessa. Puuttuva osoite, puhelin tai sähköposti korostetaan. Jos kaikki on valittu, myöhemmin lisättävät omat kentät tulevat mukaan automaattisesti.</p>
                <p class="jasrek-checks"><?php $ot_val = jasrek_omat_tiedot_valitut(); foreach (jasrek_omat_tiedot_valinnat() as $k => $nimi): ?>
                    <label class="jasrek-radio"><input type="checkbox" name="ot_kentat[]" value="<?php echo esc_attr($k); ?>" <?php checked(in_array($k, $ot_val, true)); ?>> <?php echo esc_html($nimi); ?></label>
                <?php endforeach; ?></p>
            </div>
            </div>
            <div class="jasrek-tab" data-tab="yllapito">
            <div class="jasrek-panel" id="jasrek-loki">
                <h2>Muutosloki</h2>
                <p>Kaikki muutokset jäsenten tietoihin, laskuihin, maksuihin ja asetuksiin kirjataan <a href="<?php echo esc_url(admin_url('admin.php?page=jasrek-loki')); ?>">muutoslokiin</a> (kuka, mitä ja milloin).</p>
                <p><label for="jasrek-lokikk">Säilytysaika</label>
                   <select id="jasrek-lokikk" name="loki_kk" style="width:auto">
                       <?php foreach (array('12' => '1 vuosi', '24' => '2 vuotta', '36' => '3 vuotta', '60' => '5 vuotta', '120' => '10 vuotta', '0' => 'Pysyvästi') as $k => $label): ?>
                           <option value="<?php echo esc_attr($k); ?>" <?php selected($s['loki_kk'], (string) $k); ?>><?php echo esc_html($label); ?></option>
                       <?php endforeach; ?>
                   </select>
                   <span class="description">Loki sisältää henkilötietoja (esim. vanhoja osoitteita), joten sitä ei kannata säilyttää tarpeettoman pitkään. Vanhemmat merkinnät poistetaan automaattisesti.</span></p>
            </div>

            <?php jasrek_autobackup_panel($s); ?>

            <div class="jasrek-panel" id="jasrek-poisto">
                <h2>Lisäosan poistaminen</h2>
                <p>Kun lisäosa poistetaan WordPressistä (Lisäosat → Poista käytöstä → Poista):</p>
                <label class="jasrek-radio"><input type="radio" name="poista_tiedot" value="0" <?php checked($s['poista_tiedot'], '0'); ?>> <strong>Säilytä tiedot</strong> – jäsenet, laskut, maksut ja asetukset jäävät tietokantaan, ja lisäosan voi asentaa myöhemmin uudelleen.</label>
                <label class="jasrek-radio"><input type="radio" name="poista_tiedot" value="1" <?php checked($s['poista_tiedot'], '1'); ?> data-confirm="Haluatko varmasti, että kaikki jäsenrekisterin tiedot poistetaan pysyvästi, kun lisäosa poistetaan? Tätä ei voi perua."> <strong>Poista kaikki tiedot</strong> – kaikki lisäosan luomat taulut ja asetukset poistetaan pysyvästi.</label>
                <p class="description">Ota ennen poistamista <a href="#jasrek-varmuuskopio">varmuuskopio</a> (alla), jolla kaikki tiedot voi palauttaa myöhemmin. Valinta kysytään myös, kun lisäosa poistetaan käytöstä.</p>
            </div>
            </div>

            <p class="jasrek-save"><button type="submit" class="button button-primary button-large">Tallenna asetukset</button>
               <span class="description">Tallentaa kaikkien välilehtien asetukset.</span></p>
        </form>
        <div class="jasrek-tab" data-tab="yllapito">
            <?php jasrek_backup_panel(); ?>
        </div>
    </div>
    <?php
}
