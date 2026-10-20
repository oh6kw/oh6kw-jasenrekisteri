<?php
if (!defined('ABSPATH')) exit;

/*
 * Lisäosan käynnistys: osien lataus, valikot, tyylit ja skriptit.
 * Ladataan pääsivulta (oh6kw-jasenrekisteri.php) vasta, kun on varmistettu, ettei vanhaa versiota ole jo ladattu.
 */

require_once JASREK_DIR . 'includes/helpers.php';
require_once JASREK_DIR . 'includes/log.php';
require_once JASREK_DIR . 'includes/install.php';
require_once JASREK_DIR . 'includes/email-templates.php';
require_once JASREK_DIR . 'includes/paycodes.php';
require_once JASREK_DIR . 'includes/members.php';
require_once JASREK_DIR . 'includes/import-members.php';
require_once JASREK_DIR . 'includes/invoices.php';
require_once JASREK_DIR . 'includes/import-payments.php';
require_once JASREK_DIR . 'includes/export-xlsx.php';
require_once JASREK_DIR . 'includes/print.php';
require_once JASREK_DIR . 'includes/pdf.php';
require_once JASREK_DIR . 'includes/report.php';
require_once JASREK_DIR . 'includes/accounting.php';
require_once JASREK_DIR . 'includes/voters.php';
require_once JASREK_DIR . 'includes/member-print.php';
require_once JASREK_DIR . 'includes/labels.php';
require_once JASREK_DIR . 'includes/checkup.php';
require_once JASREK_DIR . 'includes/confirmations.php';
require_once JASREK_DIR . 'includes/autobackup.php';
require_once JASREK_DIR . 'includes/backup.php';
require_once JASREK_DIR . 'includes/privacy.php';
require_once JASREK_DIR . 'includes/mailer.php';
require_once JASREK_DIR . 'includes/settings.php';
require_once JASREK_DIR . 'includes/cron.php';
require_once JASREK_DIR . 'includes/about.php';

// Tietokanta luodaan aktivoinnissa ja päivitetään automaattisesti, kun plugin päivittyy.
register_activation_hook(JASREK_FILE, 'jasrek_install');
add_action('plugins_loaded', 'jasrek_maybe_upgrade');

// Hallintavalikko
add_action('admin_menu', 'jasrek_admin_menu');
function jasrek_admin_menu() {
    jasrek_page_hooks(add_menu_page('Jäsenrekisteri', 'Jäsenrekisteri', 'manage_options', 'jasenrekisteri', 'jasrek_page_members', 'dashicons-groups', 26));
    jasrek_page_hooks(add_submenu_page('jasenrekisteri', 'Jäsenet', 'Jäsenet', 'manage_options', 'jasenrekisteri', 'jasrek_page_members'));
    jasrek_page_hooks(add_submenu_page('jasenrekisteri', 'Jäsenmaksulaskutus', 'Laskutus', 'manage_options', 'jasrek-laskutus', 'jasrek_page_invoices'));
    jasrek_page_hooks(add_submenu_page('jasenrekisteri', 'Sähköposti jäsenille', 'Sähköposti', 'manage_options', 'jasrek-sahkoposti', 'jasrek_page_mail'));
    jasrek_page_hooks(add_submenu_page('jasenrekisteri', 'Vuosiraportti', 'Vuosiraportti', 'manage_options', 'jasrek-raportti', 'jasrek_page_report'));
    jasrek_page_hooks(add_submenu_page('jasenrekisteri', 'Muutosloki', 'Muutosloki', 'manage_options', 'jasrek-loki', 'jasrek_page_log'));
    jasrek_page_hooks(add_submenu_page('jasenrekisteri', 'Jäsenrekisterin asetukset', 'Asetukset', 'manage_options', 'jasrek-asetukset', 'jasrek_page_settings'));
    jasrek_page_hooks(add_submenu_page('jasenrekisteri', 'Tietoja ja palaute', 'Tietoja ja palaute', 'manage_options', 'jasrek-tietoja', 'jasrek_page_about'));
}

/** Pluginin omien hallintasivujen tunnisteet (hook suffix). */
function jasrek_page_hooks($add = null) {
    static $hooks = array();
    if ($add) $hooks[] = $add;
    return $hooks;
}
function jasrek_is_own_screen() {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    return $screen && in_array($screen->id, jasrek_page_hooks(), true);
}

// Tulostettavien sivujen (admin-post.php) tyylit ja skripti rekisteröidään; ne tulostetaan sivun omaan HTML:ään.
add_action('admin_init', function () {
    wp_register_style('jasrek-print', JASREK_URL . 'assets/print.css', array(), JASREK_VERSION);
    wp_register_style('jasrek-voters', JASREK_URL . 'assets/voters.css', array(), JASREK_VERSION);
    wp_register_style('jasrek-checkup-print', JASREK_URL . 'assets/checkup-print.css', array('jasrek-voters'), JASREK_VERSION);
    wp_register_style('jasrek-labels', JASREK_URL . 'assets/labels.css', array(), JASREK_VERSION);
    wp_register_style('jasrek-members-print', JASREK_URL . 'assets/members-print.css', array('jasrek-voters'), JASREK_VERSION);
    wp_register_style('jasrek-document', JASREK_URL . 'assets/document.css', array(), JASREK_VERSION);
    wp_register_script('jasrek-print-page', JASREK_URL . 'assets/print-page.js', array(), JASREK_VERSION, true);
});

// Tyylit ja skriptit vain pluginin omille sivuille
add_action('admin_enqueue_scripts', function ($hook) {
    if (!in_array($hook, jasrek_page_hooks(), true)) return;
    wp_enqueue_style('jasrek-admin', JASREK_URL . 'assets/admin.css', array(), JASREK_VERSION);
    wp_enqueue_script('jasrek-admin', JASREK_URL . 'assets/admin.js', array('jquery'), JASREK_VERSION, true);
    wp_localize_script('jasrek-admin', 'JASREK', array('ajax' => admin_url('admin-ajax.php')));
});

// Omille sivuille body-luokka, johon tulostustyylit rajataan.
add_filter('admin_body_class', function ($classes) {
    return jasrek_is_own_screen() ? $classes . ' jasrek-screen' : $classes;
});

// Lisäosat-sivu: kysytään käytöstä poistettaessa, poistetaanko tiedot myöhemmin lisäosan poiston yhteydessä.
add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'plugins.php' || !current_user_can('manage_options')) return;
    wp_enqueue_script('jasrek-deactivate', JASREK_URL . 'assets/deactivate.js', array('jquery'), JASREK_VERSION, true);
    wp_localize_script('jasrek-deactivate', 'JASREKD', array(
        'plugin'  => plugin_basename(JASREK_FILE),
        'nonce'   => wp_create_nonce('jasrek_set_uninstall'),
        'current' => jasrek_settings()['poista_tiedot'],
        'backup'  => wp_nonce_url(admin_url('admin-post.php?action=jasrek_backup_export&loki=1'), 'jasrek_backup_export'),
        'csv'     => wp_nonce_url(admin_url('admin-post.php?action=jasrek_export_members'), 'jasrek_export_members'),
    ));
    wp_enqueue_style('jasrek-deactivate', JASREK_URL . 'assets/deactivate.css', array(), JASREK_VERSION);
});
add_action('wp_ajax_jasrek_set_uninstall', function () {
    if (!current_user_can('manage_options')) wp_send_json_error('Ei oikeuksia', 403);
    check_ajax_referer('jasrek_set_uninstall', 'nonce');
    $s = get_option('jasrek_settings', array());
    if (!is_array($s)) $s = array();
    $s['poista_tiedot'] = jasrek_post('poista') === '1' ? '1' : '0';
    update_option('jasrek_settings', $s);
    wp_send_json_success();
});

// Asetukset-linkki lisäosalistaan
add_filter('plugin_action_links_' . plugin_basename(JASREK_FILE), function ($links) {
    array_unshift($links, '<a href="' . esc_url(admin_url('admin.php?page=jasrek-asetukset')) . '">Asetukset</a>');
    return $links;
});
