<?php
/**
 * Admin UI třída
 * 
 * Spravuje admin rozhraní - sloupce v seznamu objednávek, meta boxy.
 *
 * @package IUcto_Woo_Integration
 * @since 2.0.0
 */

// Prevence přímého přístupu
if (!defined('ABSPATH')) {
    exit;
}

class IUcto_Woo_Admin_UI {
    
    /**
     * Instance invoice managera
     * 
     * @var IUcto_Woo_Invoice_Manager
     */
    private $invoice_manager;
    
    /**
     * Instance nastavení
     * 
     * @var IUcto_Woo_Settings
     */
    private $settings;
    
    /**
     * Instance loggeru
     * 
     * @var IUcto_Woo_Logger
     */
    private $logger;
    
    /**
     * Konstruktor
     * 
     * @param IUcto_Woo_Invoice_Manager $invoice_manager Instance invoice managera
     * @param IUcto_Woo_Settings        $settings        Instance nastavení
     * @param IUcto_Woo_Logger          $logger          Instance loggeru
     */
    public function __construct($invoice_manager, $settings, $logger) {
        $this->invoice_manager = $invoice_manager;
        $this->settings = $settings;
        $this->logger = $logger;
    }
    
    /**
     * Přidá sloupec "Faktura iÚčto" do seznamu objednávek
     * 
     * @param array $columns Existující sloupce
     * @return array Upravené sloupce
     */
    public function add_invoice_column($columns) {
        $new_columns = [];
        
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            
            // Přidáme náš sloupec za status sloupec
            if ($key === 'order_status') {
                $new_columns['iucto_invoice'] = 'Faktura iÚčto';
            }
        }
        
        return $new_columns;
    }
    
    /**
     * Vykreslí obsah sloupce "Faktura iÚčto"
     * 
     * @param string $column  Název sloupce
     * @param int    $post_id ID objednávky (post)
     * @return void
     */
    public function render_invoice_column($column, $post_id) {
        if ($column !== 'iucto_invoice') {
            return;
        }
        
        $order = wc_get_order($post_id);
        if (!$order) {
            echo '—';
            return;
        }
        
        $proforma_id = $this->invoice_manager->get_proforma_invoice_id($order);
        $tax_id = $this->invoice_manager->get_tax_invoice_id($order);
        
        if ($proforma_id) {
            echo '<span title="Proforma faktura">📄 ' . esc_html($proforma_id) . '</span><br>';
        }
        
        if ($tax_id) {
            echo '<span title="Daňový doklad">✅ ' . esc_html($tax_id) . '</span>';
        }
        
        if (!$proforma_id && !$tax_id) {
            echo '—';
        }
    }
    
    /**
     * Registruje meta boxy pro detail objednávky
     * 
     * @return void
     */
    public function register_meta_boxes() {
        // Meta box pro běžné objednávky (post type)
        add_meta_box(
            'iucto_invoice_details',
            'iÚčto Faktury',
            [$this, 'render_invoice_meta_box'],
            'shop_order',
            'side',
            'high'
        );
        
        // Meta box pro HPOS objednávky
        $screen = wc_get_container()->get(\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled()
            ? wc_get_page_screen_id('shop-order')
            : 'shop_order';
            
        add_meta_box(
            'iucto_invoice_details_hpos',
            'iÚčto Faktury',
            [$this, 'render_invoice_meta_box'],
            $screen,
            'side',
            'high'
        );
    }
    
    /**
     * Vykreslí obsah meta boxu s fakturami
     * 
     * @param WP_Post|WC_Order $post_or_order Post nebo Order objekt
     * @return void
     */
    public function render_invoice_meta_box($post_or_order) {
        // Získání order objektu
        if ($post_or_order instanceof WP_Post) {
            $order = wc_get_order($post_or_order->ID);
        } else {
            $order = $post_or_order;
        }
        
        if (!$order) {
            echo '<p><em>Nelze načíst data objednávky.</em></p>';
            return;
        }
        
        $proforma_id = $this->invoice_manager->get_proforma_invoice_id($order);
        $tax_id = $this->invoice_manager->get_tax_invoice_id($order);
        
        ?>
        <div class="iucto-invoice-meta-box">
            <style>
                .iucto-invoice-meta-box { padding: 10px 0; }
                .iucto-invoice-item { margin-bottom: 15px; padding: 10px; background: #f9f9f9; border-left: 3px solid #2271b1; }
                .iucto-invoice-item strong { display: block; margin-bottom: 5px; }
                .iucto-invoice-item .invoice-id { font-size: 16px; color: #2271b1; }
                .iucto-no-invoice { color: #666; font-style: italic; }
            </style>
            
            <?php if ($proforma_id): ?>
                <div class="iucto-invoice-item">
                    <strong>📄 Proforma faktura (záloha):</strong>
                    <span class="invoice-id">ID: <?php echo esc_html($proforma_id); ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($tax_id): ?>
                <div class="iucto-invoice-item">
                    <strong>✅ Daňový doklad:</strong>
                    <span class="invoice-id">ID: <?php echo esc_html($tax_id); ?></span>
                </div>
            <?php endif; ?>
            
            <?php if (!$proforma_id && !$tax_id): ?>
                <p class="iucto-no-invoice">Zatím nebyly vytvořeny žádné faktury.</p>
            <?php endif; ?>
            
            <p style="margin-top: 15px;">
                <a href="https://app.iucto.cz" target="_blank" class="button button-secondary">
                    Otevřít iÚčto
                </a>
            </p>
            
            <?php if (!$this->settings->is_configured()): ?>
                <div style="margin-top: 15px; padding: 10px; background: #fff3cd; border-left: 3px solid #ffc107;">
                    <strong>⚠️ Plugin není nakonfigurován</strong>
                    <p style="margin: 5px 0 0 0; font-size: 12px;">
                        <a href="<?php echo admin_url('admin.php?page=iucto-settings'); ?>">
                            Přejít na nastavení
                        </a>
                    </p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}