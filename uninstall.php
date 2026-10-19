<?php
/**
 * Ajetaan, kun lisäosa poistetaan WordPressistä (Lisäosat → Poista).
 * Tiedot poistetaan vain, jos ylläpitäjä on valinnut niin (Asetukset → Lisäosan poistaminen,
 * tai kysely, joka näytetään lisäosaa poistettaessa käytöstä). Oletuksena tiedot säilyvät.
 */
if (!defined('WP_UNINSTALL_PLUGIN')) exit;
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The plugin's own custom tables (members, invoices, audit log). The data is admin-only and changes on every write, so object caching is not used.

function jasrek_uninstall_site() {
    global $wpdb;
    wp_clear_scheduled_hook('jasrek_auto_muistutus');
    wp_clear_scheduled_hook('jr_auto_muistutus'); // ennen versiota 4.6.2
    wp_clear_scheduled_hook('jasrek_autobackup');
    wp_clear_scheduled_hook('jasrek_vahvistus_run');
    delete_option('jasrek_vahvistus_jono'); // lähettämättömät maksuvahvistukset eivät saa jäädä odottamaan

    $s = get_option('jasrek_settings', get_option('jr_settings'));
    if (!is_array($s) || !isset($s['poista_tiedot']) || $s['poista_tiedot'] !== '1') {
        return; // Säilytetään jäsenet, laskut ja asetukset – lisäosan voi asentaa uudelleen ja jatkaa siitä.
    }

    foreach (array('jasenrekisteri_laskurivit', 'jasenrekisteri_laskut', 'jasenrekisteri_loki', 'jasenrekisteri') as $t) {
        $wpdb->query('DROP TABLE IF EXISTS `' . esc_sql($wpdb->prefix . $t) . '`'); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared -- removing the plugin's own tables on uninstall, only when the admin has chosen to delete the data.
    }
    foreach (array('jasrek_settings', 'jasrek_db_version', 'jasrek_backup_edellinen', 'jasrek_data_gen', 'jasrek_autobackup_state', 'jr_settings', 'jr_db_version') as $o) delete_option($o);
    // Vahvistusta odottavat varmuuskopiot (asetukset jasrek_backup_tulossa_<käyttäjä>)
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like('jasrek_backup_tulossa_') . '%'));
    // Väliaikaiset tiedot (keskeneräiset tuonnit ja lähetykset)
    foreach (array('jasrek_', 'jr_') as $prefix) {
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            $wpdb->esc_like('_transient_' . $prefix) . '%',
            $wpdb->esc_like('_transient_timeout_' . $prefix) . '%'
        ));
    }
}

if (is_multisite()) {
    foreach (get_sites(array('fields' => 'ids', 'number' => 0)) as $blog_id) {
        switch_to_blog($blog_id);
        jasrek_uninstall_site();
        restore_current_blog();
    }
} else {
    jasrek_uninstall_site();
}
