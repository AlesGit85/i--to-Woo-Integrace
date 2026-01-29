<?php

/**
 * Settings třída
 * 
 * Spravuje všechna nastavení pluginu, admin stránku a validaci.
 * Poskytuje gettery pro jednotlivá nastavení s výchozími hodnotami.
 *
 * @package IUcto_Woo_Integration
 * @since 2.0.0
 */

// Prevence přímého přístupu
if (!defined('ABSPATH')) {
    exit;
}

class IUcto_Woo_Settings
{

    /**
     * Instance loggeru
     * 
     * @var IUcto_Woo_Logger
     */
    private $logger;

    /**
     * Prefix pro option keys
     * 
     * @var string
     */
    private $option_prefix = 'iucto_';

    /**
     * Konstruktor
     * 
     * @param IUcto_Woo_Logger $logger Instance loggeru
     */
    public function __construct($logger)
    {
        $this->logger = $logger;
    }

    /**
     * Registruje admin menu
     * 
     * @return void
     */
    public function register_menu()
    {
        add_submenu_page(
            'woocommerce',
            'iÚčto Woo Integrace',
            'iÚčto Woo Integrace',
            'manage_woocommerce',
            'iucto-settings',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Registruje WordPress settings
     * 
     * @return void
     */
    public function register_settings()
    {
        // API nastavení
        register_setting('iucto_settings_group', 'iucto_api_key', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);

        // Firemní údaje
        register_setting('iucto_settings_group', 'iucto_company_name', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('iucto_settings_group', 'iucto_company_address', [
            'sanitize_callback' => 'sanitize_textarea_field',
        ]);
        register_setting('iucto_settings_group', 'iucto_company_ico', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('iucto_settings_group', 'iucto_company_dic', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);

        // DPH nastavení
        register_setting('iucto_settings_group', 'iucto_vat_payer', [
            'sanitize_callback' => 'absint',
            'default' => 0,
        ]);
        register_setting('iucto_settings_group', 'iucto_vat_rate', [
            'sanitize_callback' => 'absint',
            'default' => 21,
        ]);

        // Fakturační nastavení
        register_setting('iucto_settings_group', 'iucto_invoice_maturity', [
            'sanitize_callback' => 'absint',
            'default' => 14,
        ]);
        register_setting('iucto_settings_group', 'iucto_auto_send_email', [
            'sanitize_callback' => 'absint',
            'default' => 0,
        ]);

        // iÚčto specifická nastavení
        register_setting('iucto_settings_group', 'iucto_customer_id', [
            'sanitize_callback' => 'absint',
        ]);
        register_setting('iucto_settings_group', 'iucto_bank_account_id', [
            'sanitize_callback' => 'absint',
            'default' => 58226,
        ]);
        register_setting('iucto_settings_group', 'iucto_chart_account_id', [
            'sanitize_callback' => 'absint',
            'default' => 604,
        ]);
        register_setting('iucto_settings_group', 'iucto_accountentrytype_id', [
            'sanitize_callback' => 'absint',
            'default' => 532,
        ]);
        register_setting('iucto_settings_group', 'iucto_vat_account_id', [
            'sanitize_callback' => 'absint',
            'default' => 343,
        ]);

        // Kategorie předobjednávek
        register_setting('iucto_settings_group', 'iucto_preorder_categories', [
            'sanitize_callback' => [$this, 'sanitize_category_array'],
            'default' => [],
        ]);
    }

    /**
     * Sanitizuje pole kategorií
     * 
     * @param mixed $input Vstupní data
     * @return array Sanitizované pole ID kategorií
     */
    public function sanitize_category_array($input)
    {
        if (!is_array($input)) {
            return [];
        }

        return array_map('absint', $input);
    }

    /**
     * Vykreslí admin stránku s nastavením
     * 
     * @return void
     */
    public function render_settings_page()
    {
        // Kontrola oprávnění
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Nemáte oprávnění k přístupu na tuto stránku.');
        }

        // Test připojení pokud je požadován
        $test_result = null;
        if (isset($_GET['test_connection']) && check_admin_referer('iucto_test_connection', 'nonce')) {
            $test_result = $this->test_api_connection();
        }

        // Include template pro admin stránku
        include IUCTO_WOO_PLUGIN_DIR . 'templates/settings-page.php';
    }

    /**
     * Testuje API připojení
     * 
     * @return array Výsledek testu [success => bool, message => string]
     */
    private function test_api_connection()
    {
        // Kontrola zda je vyplněný API klíč
        $api_key = $this->get_api_key();

        if (empty($api_key)) {
            return [
                'success' => false,
                'message' => '❌ API klíč není vyplněný. Vyplňte API klíč a uložte nastavení.',
            ];
        }

        // Kontrola minimální délky API klíče
        if (strlen($api_key) < 20) {
            return [
                'success' => false,
                'message' => '❌ API klíč má neplatný formát (je příliš krátký).',
            ];
        }

        // Získání API klienta z pluginu
        $plugin = IUcto_Woo_Plugin::instance();
        $api_client = $plugin->get_api_client();

        // Test připojení
        $this->logger->info('Spouštím test API připojení');

        if ($api_client->validate_connection()) {
            $this->logger->info('Test API připojení úspěšný');

            return [
                'success' => true,
                'message' => '✅ Připojení k iÚčto API funguje! API klíč je validní a připojení bylo úspěšně navázáno.',
            ];
        } else {
            $error = $api_client->get_last_error_message();
            $this->logger->error('Test API připojení selhal', ['error' => $error]);

            // User-friendly error zprávy
            if (strpos($error, '401') !== false || strpos($error, 'Unauthorized') !== false) {
                $message = '❌ API klíč není validní. Zkontrolujte, že jste zkopírovali správný klíč z iÚčto.';
            } elseif (strpos($error, '403') !== false || strpos($error, 'Forbidden') !== false) {
                $message = '❌ Přístup odepřen. API klíč nemá dostatečná oprávnění.';
            } elseif (strpos($error, 'timeout') !== false) {
                $message = '❌ Časový limit vypršel. Zkuste to prosím znovu.';
            } elseif (strpos($error, 'Could not resolve host') !== false) {
                $message = '❌ Nelze se připojit k iÚčto API. Zkontrolujte připojení k internetu.';
            } else {
                $message = '❌ Připojení selhalo: ' . $error;
            }

            return [
                'success' => false,
                'message' => $message,
            ];
        }
    }

    /**
     * Gettery pro nastavení
     */

    /**
     * Vrátí API klíč
     * 
     * @return string
     */
    public function get_api_key()
    {
        return get_option('iucto_api_key', '');
    }

    /**
     * Vrátí název firmy
     * 
     * @return string
     */
    public function get_company_name()
    {
        return get_option('iucto_company_name', '');
    }

    /**
     * Vrátí adresu firmy
     * 
     * @return string
     */
    public function get_company_address()
    {
        return get_option('iucto_company_address', '');
    }

    /**
     * Vrátí IČO
     * 
     * @return string
     */
    public function get_company_ico()
    {
        return get_option('iucto_company_ico', '');
    }

    /**
     * Vrátí DIČ
     * 
     * @return string
     */
    public function get_company_dic()
    {
        return get_option('iucto_company_dic', '');
    }

    /**
     * Je firma plátce DPH?
     * 
     * @return bool
     */
    public function is_vat_payer()
    {
        return (bool) get_option('iucto_vat_payer', 0);
    }

    /**
     * Vrátí sazbu DPH v procentech
     * 
     * @return int
     */
    public function get_vat_rate()
    {
        return (int) get_option('iucto_vat_rate', 21);
    }

    /**
     * Vrátí splatnost faktury ve dnech
     * 
     * @return int
     */
    public function get_invoice_maturity()
    {
        return (int) get_option('iucto_invoice_maturity', 14);
    }

    /**
     * Je zapnuto automatické odesílání emailů?
     * 
     * @return bool
     */
    public function is_auto_send_enabled()
    {
        return (bool) get_option('iucto_auto_send_email', 1);
    }

    /**
     * Vrátí fallback customer ID (pokud je nastaveno)
     * 
     * @return int
     */
    public function get_customer_id()
    {
        return (int) get_option('iucto_customer_id', 0);
    }

    /**
     * Vrátí ID bankovního účtu v iÚčto
     * 
     * @return int
     */
    public function get_bank_account_id()
    {
        return (int) get_option('iucto_bank_account_id', 58226);
    }

    /**
     * Vrátí ID účtové osnovy
     * 
     * @return int
     */
    public function get_chart_account_id()
    {
        return (int) get_option('iucto_chart_account_id', 604);
    }

    /**
     * Vrátí ID typu účetního zápisu
     * 
     * @return int
     */
    public function get_accountentrytype_id()
    {
        return (int) get_option('iucto_accountentrytype_id', 532);
    }

    /**
     * Vrátí ID účtu DPH (obvykle 343 pro české plátce DPH)
     * 
     * @return int
     */
    public function get_vat_account_id()
    {
        return (int) get_option('iucto_vat_account_id', 343);
    }

    /**
     * Vrátí pole ID kategorií označených jako předobjednávky
     * 
     * @return array
     */
    public function get_preorder_categories()
    {
        $categories = get_option('iucto_preorder_categories', []);
        return is_array($categories) ? $categories : [];
    }

    /**
     * Validační metody
     */

    /**
     * Je plugin kompletně nakonfigurován?
     * 
     * Kontroluje povinná nastavení.
     * 
     * @return bool
     */
    public function is_configured()
    {
        $required = [
            $this->get_api_key(),
            $this->get_company_name(),
            $this->get_company_address(),
            $this->get_company_ico(),
        ];

        foreach ($required as $value) {
            if (empty($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Vrátí seznam chybějících povinných nastavení
     * 
     * @return array Pole názvů chybějících nastavení
     */
    public function get_missing_settings()
    {
        $missing = [];

        if (empty($this->get_api_key())) {
            $missing[] = 'API klíč';
        }
        if (empty($this->get_company_name())) {
            $missing[] = 'Název firmy';
        }
        if (empty($this->get_company_address())) {
            $missing[] = 'Adresa firmy';
        }
        if (empty($this->get_company_ico())) {
            $missing[] = 'IČO';
        }

        return $missing;
    }

    /**
     * Zvaliduje všechna nastavení
     * 
     * @return array [valid => bool, errors => array]
     */
    public function validate_settings()
    {
        $errors = [];

        // Validace API klíče
        $api_key = $this->get_api_key();
        if (empty($api_key)) {
            $errors[] = 'API klíč je povinný.';
        } elseif (strlen($api_key) < 20) {
            $errors[] = 'API klíč má neplatný formát.';
        }

        // Validace IČO
        $ico = $this->get_company_ico();
        if (!empty($ico) && !preg_match('/^\d{8}$/', $ico)) {
            $errors[] = 'IČO musí obsahovat přesně 8 číslic.';
        }

        // Validace DPH sazby
        $vat_rate = $this->get_vat_rate();
        if ($vat_rate < 0 || $vat_rate > 100) {
            $errors[] = 'Sazba DPH musí být mezi 0-100%.';
        }

        // Validace splatnosti
        $maturity = $this->get_invoice_maturity();
        if ($maturity < 1 || $maturity > 365) {
            $errors[] = 'Splatnost musí být mezi 1-365 dny.';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}