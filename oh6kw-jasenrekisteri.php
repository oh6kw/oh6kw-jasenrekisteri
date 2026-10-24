<?php
/*
Plugin Name: OH6KW Jäsenrekisteri
Plugin URI: https://github.com/oh6kw/oh6kw-jasenrekisteri
Description: Yhdistyksen jäsenrekisteri: jäsenet, jäsenluokat ja omat lisäkentät, jäsenmaksulaskut omilla viitenumeroilla sähköpostilla tai paperilla, QR-koodi ja pankkiviivakoodi, maksujen tuonti pankista, muistutukset, tiedotteet, vuosiraportti ja muutosloki.
Version: 4.9.0
Author: Jarno Jaskari (oh6kw)
Author URI: https://github.com/oh6kw
License: AGPL-3.0-or-later
License URI: https://www.gnu.org/licenses/agpl-3.0.html
Text Domain: oh6kw-jasenrekisteri
Requires at least: 6.2
Requires PHP: 7.4

Copyright (C) Jarno Jaskari OH6KW
SPDX-License-Identifier: AGPL-3.0-or-later
*/

if (!defined('ABSPATH')) exit; // Estetään suora pääsy tiedostoon

// Jos lisäosan vanha versio (kansio "jasenrekisteri") on vielä käytössä, ei ladata toista kertaa.
if (defined('JASREK_FILE')) {
    add_action('admin_notices', function () {
        if (!current_user_can('activate_plugins')) return;
        echo '<div class="notice notice-error"><p>' . esc_html('OH6KW Jäsenrekisteri on asennettu kahdesti. Poista vanha versio käytöstä (valitse ”Säilytä tiedot”), niin uusi versio jatkaa samoilla tiedoilla.') . '</p></div>';
    });
    return;
}

define('JASREK_VERSION', '4.9.0');
define('JASREK_DB_VERSION', '10');
define('JASREK_FILE', __FILE__);
define('JASREK_DIR', plugin_dir_path(__FILE__));
define('JASREK_URL', plugin_dir_url(__FILE__));

require_once JASREK_DIR . 'includes/main.php';
