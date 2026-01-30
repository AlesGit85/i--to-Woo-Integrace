<?php
/**
 * Hlavní třída pluginu
 * 
 * Singleton třída která řídí inicializaci a propojení všech komponent pluginu.
 * Odpovídá za registraci WordPress hooků a koordinaci mezi jednotlivými moduly.
 *
 * @package IUcto_Woo_Integration
 * @since 2.0.0
 */

// Prevence přímého přístupu
if (!defined('ABSPATH')) {
    exit;
}

class IUcto_Woo_Plugin {
    
    /**
     * Singleton instance pluginu
     * 
     * @var IUcto_Woo_Plugin|null
     */
    private static $instance = null;
    
    /**
     * Instance nastavení pluginu
     * 
     * @var IUcto_Woo_Settings
     */
    private $settings;
    
    /**
     * Instance API klienta
     * 
     * @var IUcto_Woo_API_Client
     */
    private $api_client;
    
    /**
     * Instance managera zákazníků
     * 
     * @var IUcto_Woo_Customer_Manager
     */
    private $customer_manager;
    
    /**
     * Instance managera faktur
     * 
     * @var IUcto_Woo_Invoice_Manager
     */
    private $invoice_manager;
    
    /**
     * Instance admin UI
     * 
     * @var IUcto_Woo_Admin_UI
     */
    private $admin_ui;
    
    /**
     * Instance loggeru
     * 
     * @var IUcto_Woo_Logger
     */
    private $logger;
    
    /**
     * Vrátí singleton instanci pluginu
     * 
     * @return IUcto_Woo_Plugin
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Privátní konstruktor - prevence přímé inicializace
     */
    private function __construct() {
        $this->init_components();
        $this->register_hooks();
    }
    
    /**
     * Prevence klonování instance
     */
    private function __clone() {}
    
    /**
     * Prevence unserialize instance
     */
    public function __wakeup() {
        throw new Exception('Nelze unserializovat singleton.');
    }
    
    /**
     * Inicializuje všechny komponenty pluginu
     * 
     * Vytváří instance všech tříd a propojuje jejich závislosti.
     * Pořadí inicializace je důležité kvůli závislostem mezi třídami.
     * 
     * @return void
     */
    private function init_components() {
        // Logger - inicializujeme jako první, používají ho ostatní komponenty
        $this->logger = new IUcto_Woo_Logger();
        
        // Nastavení pluginu
        $this->settings = new IUcto_Woo_Settings($this->logger);
        
        // API klient - vyžaduje nastavení a logger
        $this->api_client = new IUcto_Woo_API_Client(
            $this->settings,
            $this->logger
        );
        
        // Manager zákazníků - vyžaduje API klienta
        $this->customer_manager = new IUcto_Woo_Customer_Manager(
            $this->api_client,
            $this->logger
        );
        
        // Manager faktur - vyžaduje API klienta, customer managera a nastavení
        $this->invoice_manager = new IUcto_Woo_Invoice_Manager(
            $this->api_client,
            $this->customer_manager,
            $this->settings,
            $this->logger
        );
        
        // Admin UI - vyžaduje invoice managera a nastavení
        $this->admin_ui = new IUcto_Woo_Admin_UI(
            $this->invoice_manager,
            $this->settings,
            $this->logger
        );
    }
    
    /**
     * Registruje všechny WordPress a WooCommerce hooky
     * 
     * @return void
     */
    private function register_hooks() {
        // Deklarace HPOS kompatibility
        add_action('before_woocommerce_init', [$this, 'declare_hpos_compatibility']);
        
        // Admin hooky
        if (is_admin()) {
            add_action('admin_menu', [$this->settings, 'register_menu']);
            add_action('admin_init', [$this->settings, 'register_settings']);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
            
            // Admin UI hooky (sloupce, meta boxy)
            add_filter('manage_edit-shop_order_columns', [$this->admin_ui, 'add_invoice_column']);
            add_action('manage_shop_order_posts_custom_column', [$this->admin_ui, 'render_invoice_column'], 10, 2);
            add_action('add_meta_boxes', [$this->admin_ui, 'register_meta_boxes']);
        }
        
        // WooCommerce order hooky
        add_action('woocommerce_order_status_zaplaceno', [$this->invoice_manager, 'process_paid_order'], 10, 1);
        add_action('woocommerce_payment_complete', [$this->invoice_manager, 'process_payment_complete'], 10, 1);
        add_action('woocommerce_order_status_completed', [$this->invoice_manager, 'process_completed_order'], 10, 1);
        
        // Diagnostické hooky (pouze pokud je zapnutý WP_DEBUG)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            add_action('woocommerce_order_status_changed', [$this, 'log_status_change'], 10, 4);
        }
    }
    
    /**
     * Deklaruje kompatibilitu s WooCommerce HPOS (High-Performance Order Storage)
     * 
     * @return void
     */
    public function declare_hpos_compatibility() {
        if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                'custom_order_tables',
                IUCTO_WOO_PLUGIN_FILE,
                true
            );
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                'orders_cache',
                IUCTO_WOO_PLUGIN_FILE,
                true
            );
        }
    }
    
    /**
     * Načte CSS a JS pro admin
     * 
     * @param string $hook Aktuální admin stránka
     * @return void
     */
    public function enqueue_admin_assets($hook) {
        // Debug: vypíše aktuální hook (odkomentuj pro debugging)
        // error_log('iÚčto Admin Assets Hook: ' . $hook);
        
        // Načteme na našich stránkách
        $load_assets = false;
        
        // Naše settings stránka
        if (strpos($hook, 'iucto-settings') !== false) {
            $load_assets = true;
        }
        
        // Edit stránka objednávek (legacy a HPOS)
        if (strpos($hook, 'shop_order') !== false || strpos($hook, 'woocommerce_page_wc-orders') !== false) {
            $load_assets = true;
        }
        
        // Seznam objednávek (legacy)
        if ($hook === 'edit.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'shop_order') {
            $load_assets = true;
        }
        
        if (!$load_assets) {
            return;
        }
        
        // CSS
        wp_enqueue_style(
            'iucto-woo-admin',
            IUCTO_WOO_PLUGIN_URL . 'assets/css/admin.css',
            [],
            IUCTO_WOO_VERSION
        );
        
        // JS - ujistíme se, že jQuery je načteno
        wp_enqueue_script('jquery');
        
        wp_enqueue_script(
            'iucto-woo-admin',
            IUCTO_WOO_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            IUCTO_WOO_VERSION,
            true
        );
        
        // Lokalizace JS
        wp_localize_script('iucto-woo-admin', 'iuctoWooAdmin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('iucto_woo_admin'),
        ]);
    }
    
    /**
     * Diagnostický hook - loguje všechny změny statusu objednávky
     * 
     * Aktivní pouze pokud je zapnutý WP_DEBUG.
     * Pomáhá při debugování flow objednávek.
     * 
     * @param int      $order_id   ID objednávky
     * @param string   $old_status Starý status
     * @param string   $new_status Nový status
     * @param WC_Order $order      Instance objednávky
     * @return void
     */
    public function log_status_change($order_id, $old_status, $new_status, $order) {
        $this->logger->debug(sprintf(
            'Objednávka #%d změnila status z "%s" na "%s"',
            $order_id,
            $old_status,
            $new_status
        ), [
            'order_id' => $order_id,
            'old_status' => $old_status,
            'new_status' => $new_status,
        ]);
    }
    
    /**
     * Gettery pro přístup ke komponentám z vnějšku (pokud je potřeba)
     */
    
    public function get_settings() {
        return $this->settings;
    }
    
    public function get_api_client() {
        return $this->api_client;
    }
    
    public function get_customer_manager() {
        return $this->customer_manager;
    }
    
    public function get_invoice_manager() {
        return $this->invoice_manager;
    }
    
    public function get_logger() {
        return $this->logger;
    }
}