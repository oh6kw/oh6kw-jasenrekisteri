<?php
if (!defined('ABSPATH')) exit;
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The plugin's own custom tables (members, invoices, audit log). The data is admin-only and changes on every write, so object caching is not used.

/* ------------------------------------------------------------------
 * Käsittelijät (admin-post.php)
 * ------------------------------------------------------------------ */

add_action('admin_post_jasrek_save_member', 'jasrek_handle_save_member');
function jasrek_handle_save_member() {
    jasrek_check('jasrek_save_member');
    global $wpdb;

    $id        = intval(jasrek_post('member_id', 0));
    $raw_email = trim(jasrek_post('email'));
    $email     = sanitize_email($raw_email);
    $tila      = jasrek_post('tila', 'aktiivinen');
    $tunnus    = sanitize_text_field(jasrek_post('tunnus'));
    if (jasrek_settings()['tunnus_isot'] === '1') $tunnus = jasrek_upper($tunnus);

    $data = array(
        'etunimi'    => sanitize_text_field(jasrek_post('etunimi')),
        'sukunimi'   => sanitize_text_field(jasrek_post('sukunimi')),
        'tunnus'     => $tunnus,
        'osoite'     => sanitize_text_field(jasrek_post('osoite')),
        'puhelin'    => sanitize_text_field(jasrek_post('puhelin')),
        'email'      => $email,
        'liittynyt'  => jasrek_date_or_null(jasrek_post('liittynyt')),
        'tila'       => array_key_exists($tila, jasrek_tilat()) ? $tila : 'aktiivinen',
        'lisatiedot' => sanitize_textarea_field(jasrek_post('lisatiedot')),
        'jasenlaji'  => array_key_exists(jasrek_post('jasenlaji'), jasrek_jasenlajit()) ? jasrek_post('jasenlaji') : '',
        'paperilasku'=> jasrek_post('paperilasku') === '1' ? 1 : 0,
    );

    // Omat lisäkentät
    $lisa = array();
    $posted = jasrek_post_array('lisa');
    foreach (jasrek_lisakentat() as $kid => $k) {
        $val = jasrek_lisa_parse($k, isset($posted[$kid]) ? $posted[$kid] : '');
        if ($val !== '') $lisa[$kid] = $val;
    }
    $data['lisakentat'] = $lisa ? wp_json_encode($lisa) : null;
    // Syntymävuosi tai -päivä kysytään vain, kun merkkipäivät ovat käytössä; muuten tallennettu arvo säilyy.
    if (jasrek_merkki_on()) {
        $syn = jasrek_syntynyt_parse(jasrek_post('syntynyt'));
        $data['syntynyt'] = $syn !== '' ? $syn : null;
    }

    $old = $id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->jasrek_jasenet} WHERE id = %d", $id)) : null;
    if ($id && !$old) jasrek_redirect('jasenrekisteri');

    // Eroamispäivä: annettu päivä, aiempi päivä tai tämä päivä, kun jäsen merkitään eronneeksi
    if ($data['tila'] === 'eronnut') {
        $ero = jasrek_date_or_null(jasrek_post('eronnut_pvm'));
        $data['eronnut_pvm'] = $ero ? $ero : (($old && $old->eronnut_pvm) ? $old->eronnut_pvm : current_time('Y-m-d'));
    } else {
        $data['eronnut_pvm'] = null;
    }

    if ($data['etunimi'] === '' || $data['sukunimi'] === '') {
        jasrek_redirect('jasenrekisteri', array('view' => $id ? 'edit' : 'new', 'id' => $id ?: null, 'jasrek_msg' => 'name_required'));
    }

    // Onko rekisterissä jo toinen jäsen samalla nimellä tai sähköpostilla?
    $dup = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->jasrek_jasenet} WHERE id <> %d AND ((etunimi = %s AND sukunimi = %s) OR (email <> '' AND email = %s))",
        $id, $data['etunimi'], $data['sukunimi'], $email
    ));

    if ($id) {
        $wpdb->update(jasrek_t('jasenet'), $data, array('id' => $id));
        $msg = 'member_updated';
        $diff = jasrek_log_diff(jasrek_member_snapshot($old), jasrek_member_snapshot(array_merge((array) $old, $data)));
        if ($diff) jasrek_log('jasen', $id, 'muokattu', jasrek_log_member_name($data), $diff);
    } else {
        $wpdb->insert(jasrek_t('jasenet'), $data);
        $id  = (int) $wpdb->insert_id;
        $msg = 'member_added';
        jasrek_log('jasen', $id, 'lisatty', jasrek_log_member_name($data), jasrek_log_diff(array(), jasrek_member_snapshot($data)));
    }
    if ($dup) $msg = 'member_duplicate';
    if ($raw_email !== '' && $email === '') $msg = 'email_invalid';

    // Lisäys jo tehdylle laskulle (jäsen liittyi laskutuksen jälkeen)
    $valitut = jasrek_post_ids('lisaa_lasku');
    if ($valitut && $id) {
        $m = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->jasrek_jasenet} WHERE id = %d", $id));
        $summat = jasrek_post_array('lasku_summa');
        $erat   = jasrek_post_array('lasku_era');
        $laheta = jasrek_post('laheta_heti') === '1';
        $lisatty = 0; $lahetetty = 0; $virhe = 0;
        foreach ($valitut as $lid) {
            $lasku = jasrek_get_lasku($lid);
            if (!$lasku) continue;
            $summa = isset($summat[$lid]) ? min(999999.99, jasrek_parse_money($summat[$lid])) : 0;
            if ($summa <= 0) continue;
            $era = isset($erat[$lid]) ? jasrek_date_or_null($erat[$lid]) : null;
            $rid = jasrek_add_member_to_lasku($lasku, $m, $summa, $era ? $era : jasrek_late_era($lasku));
            if (!$rid) continue;
            $lisatty++;
            jasrek_log('maksu', $id, 'laskulle', $lasku->nimi . ': ' . jasrek_log_member_name($m) . ', ' . html_entity_decode(jasrek_money($summa)) . ', eräpäivä ' . jasrek_date($era ? $era : jasrek_late_era($lasku)));
            if ($laheta && $m->email && empty($m->paperilasku)) {
                if (jasrek_send_rivi($lasku, jasrek_get_rivi($rid))) $lahetetty++; else $virhe++;
            }
        }
        if ($lisatty) {
            jasrek_redirect('jasenrekisteri', array('view' => 'edit', 'id' => $id, 'jasrek_msg' => 'member_invoiced',
                'jasrek_n' => $lisatty, 'jasrek_s' => $lahetetty, 'jasrek_e' => $virhe));
        }
    }

    jasrek_redirect('jasenrekisteri', array('jasrek_msg' => $msg));
}

add_action('admin_post_jasrek_delete_member', 'jasrek_handle_delete_member');
function jasrek_handle_delete_member() {
    $id = jasrek_request_int('id');
    jasrek_check('jasrek_delete_member_' . $id);
    global $wpdb;
    // Laskurivit säilyvät (nimi ja sähköposti on tallennettu riville), jotta kirjanpito pysyy ehjänä.
    $old = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->jasrek_jasenet} WHERE id = %d", $id));
    $wpdb->delete(jasrek_t('jasenet'), array('id' => $id));
    if ($old) jasrek_log('jasen', $id, 'poistettu', jasrek_log_member_name($old));
    jasrek_redirect('jasenrekisteri', array('jasrek_msg' => 'member_deleted'));
}

add_action('admin_post_jasrek_bulk_members', 'jasrek_handle_bulk_members');
function jasrek_handle_bulk_members() {
    jasrek_check('jasrek_bulk_members');
    global $wpdb;
    $nayta = sanitize_key(jasrek_post('nayta', 'jasenet'));
    $ids = jasrek_post_ids('jasenet');
    if (!$ids) jasrek_redirect('jasenrekisteri', array('nayta' => $nayta, 'jasrek_msg' => 'nothing_selected'));
    $op = jasrek_post('bulk_op');
    if ($op === 'tarrat') {
        wp_safe_redirect(jasrek_labels_url(array('kohde' => 'valitut', 'ids' => implode(',', $ids))));
        exit;
    }
    $n  = 0;
    $before = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->jasrek_jasenet} WHERE id IN (" . implode(',', array_fill(0, count($ids), '%d')) . ")", $ids), OBJECT_K);
    if (strpos($op, 'laji:') === 0) {
        $laji = substr($op, 5);
        if (array_key_exists($laji, jasrek_jasenlajit())) {
            $n = (int) $wpdb->query($wpdb->prepare("UPDATE {$wpdb->jasrek_jasenet} SET jasenlaji = %s WHERE id IN (" . implode(',', array_fill(0, count($ids), '%d')) . ")", array_merge(array($laji), $ids)));
        }
    } elseif ($op === 'eronnut') {
        $n = (int) $wpdb->query($wpdb->prepare("UPDATE {$wpdb->jasrek_jasenet} SET tila = 'eronnut', eronnut_pvm = %s WHERE tila = 'aktiivinen' AND id IN (" . implode(',', array_fill(0, count($ids), '%d')) . ")", array_merge(array(current_time('Y-m-d')), $ids)));
    } elseif ($op === 'aktiivinen') {
        $n = (int) $wpdb->query($wpdb->prepare("UPDATE {$wpdb->jasrek_jasenet} SET tila = 'aktiivinen', eronnut_pvm = NULL WHERE tila <> 'aktiivinen' AND id IN (" . implode(',', array_fill(0, count($ids), '%d')) . ")", $ids));
    } elseif ($op === 'paperi_on' || $op === 'paperi_off') {
        $n = (int) $wpdb->query($wpdb->prepare("UPDATE {$wpdb->jasrek_jasenet} SET paperilasku = %d WHERE id IN (" . implode(',', array_fill(0, count($ids), '%d')) . ")", array_merge(array($op === 'paperi_on' ? 1 : 0), $ids)));
    }
    if ($n) {
        $after = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->jasrek_jasenet} WHERE id IN (" . implode(',', array_fill(0, count($ids), '%d')) . ")", $ids), OBJECT_K);
        foreach ($after as $mid => $m) {
            if (!isset($before[$mid])) continue;
            $diff = jasrek_log_diff(jasrek_member_snapshot($before[$mid]), jasrek_member_snapshot($m));
            if ($diff) jasrek_log('jasen', $mid, 'muokattu', jasrek_log_member_name($m) . ' (joukkomuutos)', $diff);
        }
    }
    jasrek_redirect('jasenrekisteri', array('nayta' => $nayta, 'jasrek_msg' => 'members_bulk', 'jasrek_n' => $n));
}

add_action('admin_post_jasrek_export_members', 'jasrek_handle_export_members');
function jasrek_handle_export_members() {
    jasrek_check('jasrek_export_members');
    global $wpdb;
    $rows = $wpdb->get_results("SELECT * FROM {$wpdb->jasrek_jasenet} ORDER BY sukunimi, etunimi");
    $kentat = jasrek_lisakentat();
    $out = array();
    foreach ($rows as $m) {
        $row = array($m->id, $m->etunimi, $m->sukunimi, $m->tunnus, $m->osoite, $m->puhelin, $m->email, jasrek_date($m->liittynyt), jasrek_syntynyt_teksti($m->syntynyt),
            jasrek_laji_nimi($m->jasenlaji), jasrek_tila_nimi($m->tila), jasrek_date($m->eronnut_pvm),
            $m->paperilasku ? 'Kyllä' : '', $m->lisatiedot);
        $lisa = jasrek_member_lisa($m);
        foreach ($kentat as $kid => $k) $row[] = jasrek_lisa_value($k, isset($lisa[$kid]) ? $lisa[$kid] : '', 'Kyllä');
        $out[] = $row;
    }
    jasrek_log('vienti', 0, 'vienti', 'Jäsenrekisteri CSV-tiedostoon (' . count($rows) . ' jäsentä)');
    jasrek_csv_output('jasenrekisteri-' . current_time('Y-m-d') . '.csv',
        array_merge(array('ID', 'Etunimi', 'Sukunimi', jasrek_tunnus_nimi(), 'Osoite', 'Puhelin', 'Sähköposti', 'Liittynyt', 'Syntynyt', 'Jäsenluokka', 'Jäsenyys', 'Eronnut', 'Paperilasku', 'Lisätiedot'),
            array_values(array_map(function ($k) { return $k['nimi']; }, $kentat))), $out);
}

/* ------------------------------------------------------------------
 * Sivu
 * ------------------------------------------------------------------ */

function jasrek_page_members() {
    $view = sanitize_key(jasrek_get('view', ''));
    echo '<div class="wrap jasrek-wrap">';
    if ($view === 'tuonti') {
        jasrek_members_import_page();
    } elseif ($view === 'tarkistus') {
        jasrek_checkup_page();
    } elseif ($view === 'edit' || $view === 'new') {
        jasrek_member_form($view === 'edit' ? jasrek_get_int('id') : 0);
    } else {
        jasrek_member_list();
    }
    echo '</div>';
}

function jasrek_member_form($id) {
    global $wpdb;
    $m = $id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->jasrek_jasenet} WHERE id = %d", $id)) : null;
    $v = function ($field, $default = '') use ($m) {
        return $m && isset($m->$field) && $m->$field !== null ? $m->$field : $default;
    };
    ?>
    <h1><?php echo $m ? 'Muokkaa jäsentä' : 'Lisää uusi jäsen'; ?></h1>
    <p><a href="<?php echo esc_url(admin_url('admin.php?page=jasenrekisteri')); ?>">&larr; Takaisin jäsenlistaan</a></p>
    <?php jasrek_notice(); ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="jasrek-panel jasrek-form">
        <input type="hidden" name="action" value="jasrek_save_member">
        <?php wp_nonce_field('jasrek_save_member'); ?>
        <?php if ($m): ?><input type="hidden" name="member_id" value="<?php echo esc_attr($m->id); ?>"><?php endif; ?>

        <div class="jasrek-grid">
            <p><label for="jasrek-etunimi">Etunimi *</label>
               <input type="text" id="jasrek-etunimi" name="etunimi" value="<?php echo esc_attr($v('etunimi')); ?>" required></p>
            <p><label for="jasrek-sukunimi">Sukunimi *</label>
               <input type="text" id="jasrek-sukunimi" name="sukunimi" value="<?php echo esc_attr($v('sukunimi')); ?>" required></p>
            <?php if (jasrek_tunnus_on()): ?>
            <p><label for="jasrek-tunnus"><?php echo esc_html(jasrek_tunnus_nimi()); ?>
               <a class="jasrek-label-link" href="<?php echo esc_url(admin_url('admin.php?page=jasrek-asetukset#jasrek-lisatunniste')); ?>">muuta nimeä</a></label>
               <input type="text" id="jasrek-tunnus" name="tunnus" value="<?php echo esc_attr($v('tunnus')); ?>" maxlength="20" class="<?php echo jasrek_settings()['tunnus_isot'] === '1' ? 'jasrek-upper' : ''; ?>"></p>
            <?php else: ?>
               <input type="hidden" name="tunnus" value="<?php echo esc_attr($v('tunnus')); ?>">
            <?php endif; ?>
            <p><label for="jasrek-email">Sähköposti</label>
               <input type="email" id="jasrek-email" name="email" value="<?php echo esc_attr($v('email')); ?>"></p>
            <p><label for="jasrek-puhelin">Puhelin</label>
               <input type="tel" id="jasrek-puhelin" name="puhelin" value="<?php echo esc_attr($v('puhelin')); ?>"></p>
            <p><label for="jasrek-osoite">Osoite</label>
               <input type="text" id="jasrek-osoite" name="osoite" value="<?php echo esc_attr($v('osoite')); ?>"></p>
            <p><label for="jasrek-liittynyt">Liittynyt</label>
               <input type="date" id="jasrek-liittynyt" name="liittynyt" value="<?php echo esc_attr($v('liittynyt')); ?>"></p>
            <?php if (jasrek_merkki_on()): ?>
            <p><label for="jasrek-syntynyt">Syntymävuosi tai -päivä <span class="jasrek-muted">(vapaaehtoinen)</span></label>
               <input type="text" id="jasrek-syntynyt" name="syntynyt" value="<?php echo esc_attr(jasrek_syntynyt_teksti($v('syntynyt'))); ?>" placeholder="esim. 1976 tai 12.3.1976" inputmode="numeric"
                      pattern="\s*(\d{4}|\d{1,2}\.\d{1,2}\.\d{4}|\d{4}-\d{2}-\d{2})\s*" title="Vuosi (esim. 1976) tai päivämäärä (esim. 12.3.1976)">
               <span class="description">Merkkipäiviä varten. Pelkkä vuosi riittää, jos jäsen ei halua kertoa tarkkaa päivää – merkkipäivä näkyy silloin ilman päivää.</span></p>
            <?php endif; ?>
            <?php if (jasrek_jasenlajit()):
                $cur_laji = $v('jasenlaji');
                // Uudelle jäsenelle esivalitaan välilehdellä valittu luokka tai ensimmäinen maksullinen luokka
                if (!$m) {
                    $pre = sanitize_key(jasrek_get('laji', ''));
                    if ($pre === '' || !isset(jasrek_jasenlajit()[$pre])) {
                        foreach (jasrek_jasenlajit() as $lid => $l) { if ((float) $l['summa'] > 0) { $pre = $lid; break; } }
                    }
                    $cur_laji = $pre;
                } ?>
            <p><label for="jasrek-laji">Jäsenluokka</label>
               <select id="jasrek-laji" name="jasenlaji">
                   <option value="">— ei jäsenluokkaa —</option>
                   <?php foreach (jasrek_jasenlajit() as $lid => $l): ?>
                       <option value="<?php echo esc_attr($lid); ?>" <?php selected($cur_laji, $lid); ?>><?php echo esc_html($l['nimi'] . ' – ' . ((float) $l['summa'] > 0 ? jasrek_money($l['summa']) : 'ei jäsenmaksua')); ?></option>
                   <?php endforeach; ?>
               </select>
               <span class="description">Määrää jäsenmaksun. Luokat ja hinnat muutetaan <a href="<?php echo esc_url(admin_url('admin.php?page=jasrek-asetukset#jasrek-luokat')); ?>">asetuksissa</a>.</span></p>
            <?php else: ?>
               <input type="hidden" name="jasenlaji" value="<?php echo esc_attr($v('jasenlaji')); ?>">
            <?php endif; ?>
            <p><label for="jasrek-tila">Jäsenyys</label>
               <select id="jasrek-tila" name="tila">
                   <?php foreach (jasrek_tilat() as $k => $label): ?>
                       <option value="<?php echo esc_attr($k); ?>" <?php selected($v('tila', 'aktiivinen'), $k); ?>><?php echo esc_html($label); ?></option>
                   <?php endforeach; ?>
               </select>
               <span class="description">Eronneet eivät saa laskuja eivätkä tiedotteita, mutta heidän tietonsa ja maksuhistoriansa säilyvät.</span></p>
            <p class="jasrek-ero" <?php echo $v('tila', 'aktiivinen') === 'eronnut' ? '' : 'hidden'; ?>><label for="jasrek-ero">Eroamispäivä</label>
               <input type="date" id="jasrek-ero" name="eronnut_pvm" value="<?php echo esc_attr($v('eronnut_pvm')); ?>">
               <span class="description">Tyhjänä tallennetaan tämä päivä. Käytetään vuosiraportin jäsenmäärissä.</span></p>
        </div>
        <p><label class="jasrek-radio"><input type="checkbox" name="paperilasku" value="1" <?php checked((int) $v('paperilasku', 0), 1); ?>>
            <strong>Haluaa laskun paperilla</strong> – laskua ei lähetetä sähköpostilla, vaan se tulostetaan laskun sivulta postitettavaksi.
            Jäsenet, joilla ei ole sähköpostia, saavat laskun paperilla aina.</label></p>
        <?php $kentat = jasrek_lisakentat(); if ($kentat): $lisa = jasrek_member_lisa($m); ?>
        <fieldset class="jasrek-fieldset">
            <legend>Lisäkentät</legend>
            <div class="jasrek-grid">
            <?php foreach ($kentat as $kid => $k): $val = isset($lisa[$kid]) ? (string) $lisa[$kid] : ''; ?>
                <?php if (jasrek_lisa_is_date($k)): ?>
                    <p><label for="jasrek-lisa-<?php echo esc_attr($kid); ?>"><?php echo esc_html($k['nimi']); ?></label>
                       <input type="date" id="jasrek-lisa-<?php echo esc_attr($kid); ?>" name="lisa[<?php echo esc_attr($kid); ?>]" value="<?php echo esc_attr($val); ?>"></p>
                <?php elseif ($k['tyyppi'] === 'valinta'): ?>
                    <p><label class="jasrek-radio" style="margin-top:26px"><input type="checkbox" name="lisa[<?php echo esc_attr($kid); ?>]" value="1" <?php checked($val !== ''); ?>> <strong><?php echo esc_html($k['nimi']); ?></strong></label></p>
                <?php else: ?>
                    <p><label for="jasrek-lisa-<?php echo esc_attr($kid); ?>"><?php echo esc_html($k['nimi']); ?></label>
                       <input type="text" id="jasrek-lisa-<?php echo esc_attr($kid); ?>" name="lisa[<?php echo esc_attr($kid); ?>]" value="<?php echo esc_attr($val); ?>" maxlength="200"></p>
                <?php endif; ?>
            <?php endforeach; ?>
            </div>
            <p class="description">Lisäkentät määritellään <a href="<?php echo esc_url(admin_url('admin.php?page=jasrek-asetukset#jasrek-lisakentat')); ?>">asetuksissa</a>.</p>
        </fieldset>
        <?php endif; ?>
        <p><label for="jasrek-lisatiedot">Lisätiedot</label>
           <textarea id="jasrek-lisatiedot" name="lisatiedot" rows="3"><?php echo esc_textarea($v('lisatiedot')); ?></textarea></p>

        <?php
        // Jo tehdyt laskut, joilla jäsen ei ole: esim. jäsen liittyy jäsenmaksulaskujen lähettämisen jälkeen.
        $ehdokkaat = jasrek_laskut_ilman_jasenta($m ? $m->id : 0, 3);
        if ($ehdokkaat):
            $lajit = jasrek_jasenlajit(); ?>
        <fieldset class="jasrek-fieldset" id="jasrek-laskulle">
            <legend>Jäsenmaksu jo tehdyltä laskulta</legend>
            <p class="description"><?php echo $m ? 'Jäsen ei ole alla olevilla laskuilla.' : 'Jos jäsen liittyy jäsenmaksulaskujen lähettämisen jälkeen, hänet voi lisätä jo tehdylle laskulle.'; ?> Hän saa oman viitenumeron, ja eräpäivä on vähintään kolmen viikon päästä. Summaa voi muuttaa, esim. jos kauden aikana liittyvältä peritään vain osa jäsenmaksusta. Paperilaskun saajalle summaan lisätään laskun postimaksu.</p>
            <?php foreach ($ehdokkaat as $l):
                $hinnat = jasrek_lasku_hinnat($l);
                $map = array('' => (float) $l->summa);
                foreach ($lajit as $lid => $ll) $map[$lid] = isset($hinnat[$lid]) ? (float) $hinnat[$lid] : (float) $ll['summa'];
                $mm = (object) array('jasenlaji' => (string) $v('jasenlaji', isset($cur_laji) ? $cur_laji : ''));
                $hinta = jasrek_member_price($mm, $hinnat, $l->summa); ?>
                <div class="jasrek-laskulle-rivi" data-hinnat="<?php echo esc_attr(wp_json_encode($map)); ?>">
                    <label class="jasrek-radio"><input type="checkbox" name="lisaa_lasku[]" value="<?php echo esc_attr($l->id); ?>">
                        Lisää laskulle <strong><?php echo esc_html($l->nimi); ?></strong>
                        <span class="jasrek-muted">(laskutettu <?php echo esc_html(jasrek_date($l->laskupvm)); ?>, eräpäivä <?php echo esc_html(jasrek_date($l->erapaiva)); ?>)</span></label>
                    <span class="jasrek-laskulle-kentat">
                        <label for="jasrek-ls-<?php echo esc_attr($l->id); ?>">Summa</label>
                        <input type="text" inputmode="decimal" id="jasrek-ls-<?php echo esc_attr($l->id); ?>" name="lasku_summa[<?php echo esc_attr($l->id); ?>]" value="<?php echo esc_attr(number_format($hinta, 2, ',', '')); ?>"> €
                        <label for="jasrek-le-<?php echo esc_attr($l->id); ?>">Eräpäivä</label>
                        <input type="date" id="jasrek-le-<?php echo esc_attr($l->id); ?>" name="lasku_era[<?php echo esc_attr($l->id); ?>]" value="<?php echo esc_attr(jasrek_late_era($l)); ?>">
                    </span>
                </div>
            <?php endforeach; ?>
            <label class="jasrek-radio"><input type="checkbox" name="laheta_heti" value="1" checked> Lähetä lasku heti sähköpostilla (jos jäsenellä on sähköpostiosoite eikä hän ole valinnut paperilaskua)</label>
        </fieldset>
        <?php endif; ?>

        <p class="jasrek-actions">
            <button type="submit" class="button button-primary button-large"><?php echo $m ? 'Tallenna muutokset' : 'Lisää jäsen'; ?></button>
            <a class="button button-large" href="<?php echo esc_url(admin_url('admin.php?page=jasenrekisteri')); ?>">Peruuta</a>
        </p>
    </form>
    <?php if ($m) { jasrek_member_history($m); jasrek_member_log_panel($m); } ?>
    <?php
}

/** Jäsenen kaikki laskut ja maksut. */
function jasrek_member_history($m) {
    global $wpdb;
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT r.*, l.nimi AS lasku_nimi, COALESCE(r.erapaiva, l.erapaiva) AS era FROM {$wpdb->jasrek_rivit} r JOIN {$wpdb->jasrek_laskut} l ON l.id = r.lasku_id WHERE r.jasen_id = %d ORDER BY l.id DESC",
        $m->id
    ));
    $today = current_time('Y-m-d');
    ?>
    <div class="jasrek-panel">
        <h2>Laskut ja maksut</h2>
        <?php if (!$rows): ?>
            <p class="jasrek-muted">Jäsenelle ei ole vielä tehty laskuja.</p>
        <?php else: ?>
        <table class="widefat striped jasrek-table">
            <thead><tr><th>Lasku</th><th>Viite</th><th>Summa</th><th>Eräpäivä</th><th>Tila</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><a href="<?php echo esc_url(admin_url('admin.php?page=jasrek-laskutus&view=lasku&id=' . $r->lasku_id)); ?>"><?php echo esc_html($r->lasku_nimi); ?></a></td>
                    <td><code class="jasrek-ref"><?php echo esc_html(jasrek_viite_format($r->viite)); ?></code></td>
                    <td><?php echo esc_html(jasrek_money($r->summa)); ?></td>
                    <td><?php echo esc_html(jasrek_date($r->era)); ?></td>
                    <td><?php
                        if ($r->maksettu) echo '<span class="jasrek-badge jasrek-badge-ok">Maksettu ' . esc_html(jasrek_date($r->maksettu)) . '</span>';
                        elseif ($r->era < $today) echo '<span class="jasrek-badge jasrek-badge-err">Erääntynyt</span>';
                        else echo '<span class="jasrek-badge jasrek-badge-info">Avoin</span>';
                        if ($r->muistutettu) echo ' <span class="jasrek-badge jasrek-badge-orange">Muistutettu ' . ((int) $r->muistutuksia > 1 ? (int) $r->muistutuksia . '× ' : '') . esc_html(jasrek_date($r->muistutettu)) . '</span>';
                    ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <?php
}

function jasrek_member_list() {
    global $wpdb;
    $t = jasrek_t('jasenet');
    $tunnus_on = jasrek_tunnus_on();
    $lajit = jasrek_jasenlajit();

    // Järjestäminen: vain sallitut sarakkeet (korjaa SQL-injektion)
    $allowed  = array('id', 'etunimi', 'sukunimi', 'tunnus', 'liittynyt');
    $order_by = in_array(jasrek_get('order_by'), $allowed, true) ? jasrek_get('order_by') : 'sukunimi';
    $order    = jasrek_get('order') === 'desc' ? 'DESC' : 'ASC';

    // Lukumäärät jäsenluokittain (vain voimassa olevat) ja eronneet
    $by_laji = $wpdb->get_results("SELECT jasenlaji, COUNT(*) AS n FROM {$wpdb->jasrek_jasenet} WHERE tila = 'aktiivinen' GROUP BY jasenlaji", OBJECT_K);
    $n_jas = 0; $n_ei = 0;
    foreach ($by_laji as $k => $row) {
        $n_jas += (int) $row->n;
        if (!isset($lajit[$k])) $n_ei += (int) $row->n;
    }
    $n_ero = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->jasrek_jasenet} WHERE tila <> 'aktiivinen'");
    $n_free = 0;
    foreach ($lajit as $lid => $l) if ((float) $l['summa'] <= 0 && isset($by_laji[$lid])) $n_free += (int) $by_laji[$lid]->n;

    // Välilehdet: kaikki jäsenet, jokainen jäsenluokka, ilman luokkaa (jos on), eronneet
    $tabs = array('jasenet' => array('Kaikki jäsenet', $n_jas));
    foreach ($lajit as $lid => $l) {
        $tabs['l_' . $lid] = array($l['nimi'], isset($by_laji[$lid]) ? (int) $by_laji[$lid]->n : 0);
    }
    if ($lajit && $n_ei > 0) $tabs['ei'] = array('Ilman jäsenluokkaa', $n_ei);
    $tabs['eronnut'] = array('Eronneet', $n_ero);

    $nayta = sanitize_key(jasrek_get('nayta', 'jasenet'));
    if (!isset($tabs[$nayta])) $nayta = 'jasenet';
    // WHERE kootaan SQL-literaaleista; kaikki arvot sidotaan prepare()-kutsulla.
    $where = "tila = 'aktiivinen'";
    $args  = array();
    if ($nayta === 'eronnut') {
        $where = "tila <> 'aktiivinen'";
    } elseif ($nayta === 'ei') {
        $known = array_keys($lajit);
        if ($known) {
            $where .= ' AND jasenlaji NOT IN (' . implode(',', array_fill(0, count($known), '%s')) . ')';
            $args = $known;
        }
    } elseif (strpos($nayta, 'l_') === 0) {
        $where .= ' AND jasenlaji = %s';
        $args[] = substr($nayta, 2);
    }
    $args[] = $order_by; // sarake sallittujen listalta, sidotaan tunnisteena (%i)
    $sql = "SELECT * FROM {$wpdb->jasrek_jasenet} WHERE $where ORDER BY %i " . ($order === 'DESC' ? 'DESC' : 'ASC') . ', sukunimi ASC, etunimi ASC';
    $members = $wpdb->get_results($wpdb->prepare($sql, $args)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $sql is built from literal fragments above; all values are bound here.
    $n_noemail = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->jasrek_jasenet} WHERE tila = 'aktiivinen' AND (email IS NULL OR email = '')");
    $n_paperi  = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->jasrek_jasenet} WHERE tila = 'aktiivinen' AND (paperilasku = 1 OR email IS NULL OR email = '')");
    $kentat = jasrek_lisakentat();
    $sarakkeet = array_filter($kentat, function ($k) { return !empty($k['lista']) && $k['lista'] === '1'; });

    // Viimeisimmän laskun maksutilanne jäsenittäin
    $latest = $wpdb->get_row("SELECT * FROM {$wpdb->jasrek_laskut} ORDER BY id DESC LIMIT 1");
    $paystatus = $latest ? $wpdb->get_results($wpdb->prepare("SELECT jasen_id, maksettu, erapaiva FROM {$wpdb->jasrek_rivit} WHERE lasku_id = %d", $latest->id), OBJECT_K) : array();
    $today = current_time('Y-m-d');

    $base = admin_url('admin.php?page=jasenrekisteri');
    $sort_link = function ($col, $label) use ($base, $order_by, $order, $nayta) {
        $next = ($order_by === $col && $order === 'ASC') ? 'desc' : 'asc';
        $arrow = $order_by === $col ? ($order === 'ASC' ? ' ▲' : ' ▼') : '';
        return '<a href="' . esc_url(add_query_arg(array('order_by' => $col, 'order' => $next, 'nayta' => $nayta), $base)) . '">' . esc_html($label) . $arrow . '</a>';
    };
    $cols = ($tunnus_on ? 8 : 7) + count($sarakkeet);
    ?>
    <h1 class="wp-heading-inline">Jäsenrekisteri</h1>
    <a href="<?php echo esc_url(add_query_arg(array('view' => 'new', 'laji' => strpos($nayta, 'l_') === 0 ? substr($nayta, 2) : null), $base)); ?>" class="page-title-action">Lisää uusi jäsen</a>
    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=jasrek_export_all'), 'jasrek_export_all')); ?>" class="page-title-action" title="Jäsenet, lisäkentät, jäsenluokat, laskut, maksut ja muutosloki omille välilehdilleen">Vie kaikki tiedot Exceliin</a>
    <a href="<?php echo esc_url(jasrek_member_print_url($nayta === 'eronnut' ? array('tila' => 'eronnut') : (strpos($nayta, 'l_') === 0 ? array('laji' => substr($nayta, 2)) : array()))); ?>" class="page-title-action" target="_blank" title="Jäsenluettelo tulostettavana A4-sivuna: nimi, <?php echo esc_attr(jasrek_tunnus_on() ? jasrek_lower(jasrek_tunnus_nimi()) . ', ' : ''); ?>sähköposti, puhelin, osoite, liittymis- ja syntymäpäivä. Paperille tai PDF:ksi.">Tulosta jäsenluettelo</a>
    <a href="<?php echo esc_url(jasrek_labels_url($nayta === 'eronnut' ? array('kohde' => 'eronnut') : (strpos($nayta, 'l_') === 0 ? array('kohde' => 'laji:' . substr($nayta, 2)) : array()))); ?>" class="page-title-action" target="_blank" title="Osoitetarrat A4-tarra-arkille (esim. 24 tarraa, 70 × 36 mm): paperilaskun saajat, kaikki, jäsenluokka, eronneet tai valitut. Myös lähettäjätarrat yhdistyksen osoitteella.">Tulosta osoitetarrat</a>
    <hr class="wp-header-end">
    <?php jasrek_notice(); ?>

    <div class="jasrek-cards">
        <div class="jasrek-card"><span class="jasrek-card-num"><?php echo esc_html($n_jas); ?></span><span class="jasrek-card-label">Jäsentä<?php echo esc_html($n_free ? ', joista ' . $n_free . ' ilman jäsenmaksua' : ''); ?></span></div>
        <div class="jasrek-card"><span class="jasrek-card-num"><?php echo esc_html($n_ero); ?></span><span class="jasrek-card-label">Eronnutta</span></div>
        <?php
        // Merkkipäivät seuraavan 60 päivän aikana (myös vuodenvaihteen yli)
        $tanaan = current_time('Y-m-d'); $raja = gmdate('Y-m-d', strtotime($tanaan . ' +60 days'));
        $tulossa = array_values(array_filter(array_merge(jasrek_merkkipaivat((int) substr($tanaan, 0, 4)), jasrek_merkkipaivat((int) substr($tanaan, 0, 4) + 1)),
            function ($mp) use ($tanaan, $raja) { return $mp['pvm'] >= $tanaan && $mp['pvm'] <= $raja; }));
        if ($tulossa): $eka = $tulossa[0]; ?>
        <a class="jasrek-card jasrek-card-link" href="<?php echo esc_url(admin_url('admin.php?page=jasrek-raportti&vuosi=' . substr($eka['pvm'], 0, 4) . '#merkkipaivat')); ?>"><span class="jasrek-card-num">🎉 <?php echo (int) count($tulossa); ?></span>
            <span class="jasrek-card-label">Merkkipäivää 60 päivän sisällä, ensimmäinen <?php echo esc_html(jasrek_date($eka['pvm']) . ': ' . $eka['m']->etunimi . ' ' . $eka['m']->sukunimi . ' ' . jasrek_merkkipaiva_teksti($eka)); ?></span></a>
        <?php endif; ?>
        <?php $n_puute = count(jasrek_checkup_data(array_keys(array_filter(jasrek_checkup_kentat(), function ($k) { return $k[1]; })))['puutteet']); ?>
        <a class="jasrek-card jasrek-card-link <?php echo $n_puute ? 'jasrek-card-warn' : 'jasrek-card-ok'; ?>" href="<?php echo esc_url(add_query_arg('view', 'tarkistus', $base)); ?>" title="Tietojen tarkistus: puuttuvat sähköpostit, osoitteet ja puhelinnumerot, tuplat ja virheelliset tiedot. Puutelistan voi tulostaa.">
            <?php if ($n_puute): ?><span class="jasrek-card-num"><?php echo (int) $n_puute; ?></span><span class="jasrek-card-label">Jäsentä, joilta puuttuu tietoja<?php echo esc_html($n_noemail ? ' (' . $n_noemail . ' ilman sähköpostia)' : ''); ?></span>
            <?php else: ?><span class="jasrek-card-num">✓</span><span class="jasrek-card-label">Kaikki tiedot kunnossa</span><?php endif; ?></a>
        <?php if ($n_paperi): ?>
        <div class="jasrek-card"><span class="jasrek-card-num"><?php echo esc_html($n_paperi); ?></span><span class="jasrek-card-label">Saa laskun paperilla</span></div>
        <?php endif; ?>
        <?php if ($latest):
            $paid = 0; foreach ($paystatus as $p) { if ($p->maksettu) $paid++; } ?>
        <div class="jasrek-card"><span class="jasrek-card-num"><?php echo esc_html($paid . ' / ' . count($paystatus)); ?></span>
            <span class="jasrek-card-label">Maksanut: <?php echo esc_html($latest->nimi); ?></span></div>
        <?php endif; ?>
    </div>

    <?php if (!$lajit): ?>
        <p class="jasrek-hint">Vinkki: määrittele <a href="<?php echo esc_url(admin_url('admin.php?page=jasrek-asetukset#jasrek-luokat')); ?>">asetuksissa</a> jäsenluokat (esim. Jäsen, Nuorisojäsen, Kunniajäsen) ja niiden jäsenmaksut. Luokat näkyvät tässä välilehtinä.</p>
    <?php elseif ($n_ei > 0): ?>
        <p class="jasrek-hint"><?php echo (int) $n_ei; ?> jäsenellä ei ole jäsenluokkaa. Avaa välilehti ”Ilman jäsenluokkaa”, valitse jäsenet ja aseta luokka taulukon alta.</p>
    <?php endif; ?>

    <div class="jasrek-toolbar">
        <ul class="subsubsub jasrek-tabs">
            <?php foreach ($tabs as $k => $tab): ?>
                <li><a href="<?php echo esc_url(add_query_arg('nayta', $k, $base)); ?>" class="<?php echo $nayta === $k ? 'current' : ''; ?>"><?php echo esc_html($tab[0]); ?> <span class="count">(<?php echo (int) $tab[1]; ?>)</span></a></li>
            <?php endforeach; ?>
        </ul>
        <div class="jasrek-toolbar-right">
            <input type="search" class="jasrek-search" data-jasrek-filter="#jasrek-members" placeholder="<?php echo esc_attr('Hae: nimi, sähköposti, puhelin' . ($tunnus_on ? ', ' . jasrek_lower(jasrek_tunnus_nimi()) : '') . ($kentat ? ', lisäkenttä' : '') . '…'); ?>">
            <a class="button" href="<?php echo esc_url(add_query_arg('view', 'tuonti', $base)); ?>">Tuo CSV</a>
            <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=jasrek_export_members'), 'jasrek_export_members')); ?>">Vie CSV</a>
        </div>
    </div>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <input type="hidden" name="action" value="jasrek_bulk_members">
    <input type="hidden" name="nayta" value="<?php echo esc_attr($nayta); ?>">
    <?php wp_nonce_field('jasrek_bulk_members'); ?>
    <table class="widefat striped jasrek-table" id="jasrek-members">
        <thead>
            <tr>
                <td class="check-column"><input type="checkbox" class="jasrek-check-all" data-target="#jasrek-members"></td>
                <th class="jasrek-col-id"><?php echo wp_kses_post($sort_link('id', 'ID')); ?></th>
                <th><?php echo wp_kses_post($sort_link('sukunimi', 'Nimi')); ?></th>
                <?php if ($tunnus_on): ?><th><?php echo wp_kses_post($sort_link('tunnus', jasrek_tunnus_nimi())); ?></th><?php endif; ?>
                <th>Yhteystiedot</th>
                <th><?php echo wp_kses_post($sort_link('liittynyt', 'Liittynyt')); ?></th>
                <?php foreach ($sarakkeet as $k): ?><th><?php echo esc_html($k['nimi']); ?></th><?php endforeach; ?>
                <th><?php echo $latest ? esc_html($latest->nimi) : 'Jäsenmaksu'; ?></th>
                <th class="jasrek-col-actions">Toiminnot</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$members): ?>
            <tr><td colspan="<?php echo esc_html($cols); ?>" class="jasrek-empty">Ei jäseniä tässä näkymässä.</td></tr>
        <?php endif; ?>
        <?php foreach ($members as $m):
            $lisa = jasrek_member_lisa($m);
            // Hakuun myös lisäkentät: kentän nimi (esim. "avain") löytää kaikki, joilla kenttä on täytetty
            $lisa_haku = '';
            foreach ($kentat as $kid => $k) if (isset($lisa[$kid]) && $lisa[$kid] !== '') $lisa_haku .= $k['nimi'] . ($k['tyyppi'] === 'valinta' ? '' : jasrek_lisa_value($k, $lisa[$kid]));
            $search = preg_replace('/\s+/', '', strtolower(remove_accents($m->etunimi . $m->sukunimi . $m->etunimi . $m->tunnus . $m->email . $m->puhelin . jasrek_laji_nimi($m->jasenlaji) . $lisa_haku . ($m->paperilasku ? 'paperilasku' : ''))));
            $edit_url = add_query_arg(array('view' => 'edit', 'id' => $m->id), $base);
            $del_url  = wp_nonce_url(admin_url('admin-post.php?action=jasrek_delete_member&id=' . $m->id), 'jasrek_delete_member_' . $m->id);
            ?>
            <tr data-search="<?php echo esc_attr($search); ?>">
                <th class="check-column"><input type="checkbox" name="jasenet[]" value="<?php echo esc_attr($m->id); ?>"></th>
                <td class="jasrek-col-id"><?php echo esc_html($m->id); ?></td>
                <td><a class="jasrek-name" href="<?php echo esc_url($edit_url); ?>"><?php echo esc_html($m->sukunimi . ' ' . $m->etunimi); ?></a>
                    <?php echo wp_kses_post(jasrek_laji_badge($m->jasenlaji)); ?>
                    <?php echo wp_kses_post(jasrek_tila_badge($m->tila)); ?>
                    <?php if ($m->lisatiedot): ?><div class="jasrek-sub"><?php echo esc_html(wp_trim_words($m->lisatiedot, 10)); ?></div><?php endif; ?>
                </td>
                <?php if ($tunnus_on): ?><td><?php echo $m->tunnus ? '<span class="jasrek-call">' . esc_html($m->tunnus) . '</span>' : ''; ?></td><?php endif; ?>
                <td>
                    <?php if ($m->email): ?><a href="mailto:<?php echo esc_attr($m->email); ?>"><?php echo esc_html($m->email); ?></a><?php else: ?><span class="jasrek-badge jasrek-badge-warn">ei sähköpostia</span><?php endif; ?>
                    <?php if ($m->paperilasku): ?><span class="jasrek-badge jasrek-badge-info" title="Lasku tulostetaan ja postitetaan">paperilasku</span><?php endif; ?>
                    <?php if ($m->puhelin): ?><div class="jasrek-sub"><?php echo esc_html($m->puhelin); ?></div><?php endif; ?>
                    <?php if ($m->osoite): ?><div class="jasrek-sub"><?php echo esc_html($m->osoite); ?></div><?php endif; ?>
                </td>
                <td><?php echo esc_html(jasrek_date($m->liittynyt)); ?></td>
                <?php foreach ($sarakkeet as $kid => $k): ?><td><?php echo esc_html(jasrek_lisa_value($k, isset($lisa[$kid]) ? $lisa[$kid] : '')); ?></td><?php endforeach; ?>
                <td><?php
                    if ($latest && isset($paystatus[$m->id])) {
                        if ($paystatus[$m->id]->maksettu) {
                            echo '<span class="jasrek-badge jasrek-badge-ok">Maksettu ' . esc_html(jasrek_date($paystatus[$m->id]->maksettu)) . '</span>';
                        } elseif (jasrek_rivi_era($latest, $paystatus[$m->id]) < $today) {
                            echo '<span class="jasrek-badge jasrek-badge-err">Erääntynyt</span>';
                        } else {
                            echo '<span class="jasrek-badge jasrek-badge-info">Avoin</span>';
                        }
                    } elseif ($m->tila === 'aktiivinen' && jasrek_is_maksuvapaa($m)) {
                        echo '<span class="jasrek-muted">ei jäsenmaksua</span>';
                    } else {
                        echo '<span class="jasrek-muted">–</span>';
                    }
                ?></td>
                <td class="jasrek-col-actions">
                    <a class="button button-small" href="<?php echo esc_url($edit_url); ?>">Muokkaa</a>
                    <a class="button button-small jasrek-btn-danger" href="<?php echo esc_url($del_url); ?>"
                       data-confirm="<?php echo esc_attr('Poistetaanko ' . $m->etunimi . ' ' . $m->sukunimi . ' pysyvästi? Jos jäsen eroaa, kannattaa mieluummin merkitä hänet eronneeksi, jolloin tiedot ja maksuhistoria säilyvät.'); ?>">Poista</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php if ($members): ?>
    <div class="jasrek-bulk">
        <label for="jasrek-mbulk"><strong>Valituille:</strong></label>
        <select name="bulk_op" id="jasrek-mbulk">
            <?php if ($lajit): ?>
            <optgroup label="Aseta jäsenluokka">
                <?php foreach ($lajit as $lid => $l): ?>
                    <option value="laji:<?php echo esc_attr($lid); ?>"><?php echo esc_html($l['nimi'] . ' (' . jasrek_money($l['summa']) . ')'); ?></option>
                <?php endforeach; ?>
            </optgroup>
            <?php endif; ?>
            <optgroup label="Jäsenyys">
                <option value="eronnut">Merkitse eronneeksi</option>
                <option value="aktiivinen">Palauta jäseneksi</option>
            </optgroup>
            <optgroup label="Laskun toimitus">
                <option value="paperi_on">Lasku paperilla</option>
                <option value="paperi_off">Lasku sähköpostilla</option>
            </optgroup>
            <optgroup label="Tulosta">
                <option value="tarrat">Osoitetarrat valituille</option>
            </optgroup>
        </select>
        <button type="submit" class="button" data-confirm="Muutetaanko valittujen jäsenten tiedot?">Suorita</button>
    </div>
    <?php endif; ?>
    </form>
    <p class="jasrek-muted jasrek-count"><?php echo count($members); ?> henkilöä näkymässä.</p>
    <?php
}
