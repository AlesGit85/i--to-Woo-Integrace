<?php
/**
 * Plugin Name: iÚčto Woo Integrace
 * Description: Automatická integrace iÚčto fakturace pro WooCommerce - vytváření zálohových a konečných faktur
 * Version: 2.1.7
 * Author: Allimedia.cz
 * Author URI: https://allimedia.cz
 * Text Domain: iucto-woo-integration
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 9.0
 * 
 * @package IUcto_Woo_Integration
 */

// Prevence přímého přístupu
if (!defined('ABSPATH')) {
    exit;
}

// Definice konstant pluginu
define('IUCTO_WOO_VERSION', '2.1.3');
define('IUCTO_WOO_MIN_PHP', '7.4');
define('IUCTO_WOO_MIN_WOO', '5.0');
define('IUCTO_WOO_PLUGIN_FILE', __FILE__);
define('IUCTO_WOO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('IUCTO_WOO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('IUCTO_WOO_INCLUDES_DIR', IUCTO_WOO_PLUGIN_DIR . 'includes/');

// API konstanty
define('IUCTO_API_URL', 'https://online.iucto.cz/api/1.3');
define('IUCTO_API_VERSION', '1.3');

/**
 * Kontrola požadavků pluginu
 * 
 * @return bool True pokud jsou splněny všechny požadavky
 */
function iucto_woo_check_requirements() {
    $errors = [];
    
    // Kontrola PHP verze
    if (version_compare(PHP_VERSION, IUCTO_WOO_MIN_PHP, '<')) {
        $errors[] = sprintf(
            'iÚčto Woo Integrace vyžaduje PHP verzi %s nebo vyšší. Vaše verze: %s',
            IUCTO_WOO_MIN_PHP,
            PHP_VERSION
        );
    }
    
    // Kontrola WooCommerce
    if (!class_exists('WooCommerce')) {
        $errors[] = 'iÚčto Woo Integrace vyžaduje aktivní WooCommerce plugin.';
    } elseif (defined('WC_VERSION') && version_compare(WC_VERSION, IUCTO_WOO_MIN_WOO, '<')) {
        $errors[] = sprintf(
            'iÚčto Woo Integrace vyžaduje WooCommerce verzi %s nebo vyšší. Vaše verze: %s',
            IUCTO_WOO_MIN_WOO,
            WC_VERSION
        );
    }
    
    // Zobrazení chyb
    if (!empty($errors)) {
        add_action('admin_notices', function() use ($errors) {
            foreach ($errors as $error) {
                echo '<div class="notice notice-error"><p>' . esc_html($error) . '</p></div>';
            }
        });
        return false;
    }
    
    return true;
}

/**
 * Autoloader pro třídy pluginu
 * 
 * Automaticky načítá třídy z includes/ složky podle jejich názvu.
 * Konvence: IUcto_Woo_Class_Name => class-class-name.php
 * 
 * @param string $class_name Název třídy k načtení
 * @return void
 */
function iucto_woo_autoloader($class_name) {
    // Kontrola, zda třída patří k našemu pluginu
    if (strpos($class_name, 'IUcto_Woo_') !== 0) {
        return;
    }
    
    // Odstranění prefixu a převod na lowercase s pomlčkami
    $class_name = str_replace('IUcto_Woo_', '', $class_name);
    $class_name = strtolower(str_replace('_', '-', $class_name));
    
    // Sestavení cesty k souboru
    $file = IUCTO_WOO_INCLUDES_DIR . 'class-' . $class_name . '.php';
    
    // Načtení souboru pokud existuje
    if (file_exists($file)) {
        require_once $file;
    }
}
spl_autoload_register('iucto_woo_autoloader');

/**
 * Inicializace pluginu
 * 
 * Spustí se po načtení všech pluginů.
 * Kontroluje požadavky a inicializuje hlavní třídu pluginu.
 * 
 * @return void
 */
function iucto_woo_init() {
    // Kontrola požadavků
    if (!iucto_woo_check_requirements()) {
        return;
    }
    
    // Načtení textové domény pro překlady
    load_plugin_textdomain(
        'iucto-woo-integration',
        false,
        dirname(plugin_basename(IUCTO_WOO_PLUGIN_FILE)) . '/languages'
    );
    
    // Kontrola a update pluginu (migrace z předchozích verzí)
    iucto_woo_check_version_and_update();
    
    // Inicializace hlavní třídy pluginu
    IUcto_Woo_Plugin::instance();
}
add_action('plugins_loaded', 'iucto_woo_init');

/**
 * Kontrola verze a automatická migrace nastavení
 * 
 * Tato funkce se spustí při každém načtení pluginu a zkontroluje,
 * jestli nejsou potřeba nějaké změny v databázi kvůli nové verzi.
 * 
 * @return void
 */
function iucto_woo_check_version_and_update() {
    // Získání uložené verze z databáze
    $saved_version = get_option('iucto_woo_plugin_version', '0.0.0');
    $current_version = IUCTO_WOO_VERSION;
    
    // Pokud je verze stejná, nic neděláme
    if (version_compare($saved_version, $current_version, '>=')) {
        return;
    }
    
    // Migrace z verzí starších než 2.1.3 - přidání vat_account_id
    if (version_compare($saved_version, '2.1.3', '<')) {
        // Přidáme vat_account_id pouze pokud ještě neexistuje
        if (get_option('iucto_vat_account_id') === false) {
            add_option('iucto_vat_account_id', 343, '', 'yes');
        }
    }
    
    // Aktualizace verze v databázi
    update_option('iucto_woo_plugin_version', $current_version);
}

/**
 * Aktivace pluginu
 * 
 * @return void
 */
function iucto_woo_activate() {
    // Kontrola požadavků při aktivaci
    if (!iucto_woo_check_requirements()) {
        deactivate_plugins(plugin_basename(IUCTO_WOO_PLUGIN_FILE));
        wp_die(
            'iÚčto Woo Integrace nemůže být aktivován. Zkontrolujte požadavky pluginu.',
            'Chyba aktivace',
            ['back_link' => true]
        );
    }
    
    // Nastavení výchozích hodnot (pokud ještě neexistují)
    $defaults = [
        'iucto_invoice_maturity' => 14,
        'iucto_vat_rate' => 21,
        'iucto_bank_account_id' => 58226,
        'iucto_chart_account_id' => 604,
        'iucto_accountentrytype_id' => 532,
        'iucto_vat_account_id' => 343,
        'iucto_auto_send_email' => 0,
    ];
    
    foreach ($defaults as $key => $value) {
        if (get_option($key) === false) {
            add_option($key, $value, '', 'yes');
        }
    }
    
    // Nastavení aktuální verze
    update_option('iucto_woo_plugin_version', IUCTO_WOO_VERSION);
    
    // Flush rewrite rules
    flush_rewrite_rules();
}
register_activation_hook(IUCTO_WOO_PLUGIN_FILE, 'iucto_woo_activate');

/**
 * Deaktivace pluginu
 * 
 * @return void
 */
function iucto_woo_deactivate() {
    // Flush rewrite rules
    flush_rewrite_rules();
}
register_deactivation_hook(IUCTO_WOO_PLUGIN_FILE, 'iucto_woo_deactivate');