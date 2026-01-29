<?php

/**
 * Invoice Manager třída
 * 
 * Hlavní třída pro správu faktur v iÚčto.
 * Vytváří zálohové i konečné faktury, zpracovává objednávky, odesílá emaily.
 *
 * @package IUcto_Woo_Integration
 * @since 2.0.0
 */

// Prevence přímého přístupu
if (!defined('ABSPATH')) {
    exit;
}

class IUcto_Woo_Invoice_Manager
{

    /**
     * Instance API klienta
     * 
     * @var IUcto_Woo_API_Client
     */
    private $api_client;

    /**
     * Instance customer managera
     * 
     * @var IUcto_Woo_Customer_Manager
     */
    private $customer_manager;

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
     * @param IUcto_Woo_API_Client       $api_client       Instance API klienta
     * @param IUcto_Woo_Customer_Manager $customer_manager Instance customer managera
     * @param IUcto_Woo_Settings         $settings         Instance nastavení
     * @param IUcto_Woo_Logger           $logger           Instance loggeru
     */
    public function __construct($api_client, $customer_manager, $settings, $logger)
    {
        $this->api_client = $api_client;
        $this->customer_manager = $customer_manager;
        $this->settings = $settings;
        $this->logger = $logger;
    }

    /**
     * Zpracuje zaplacenou objednávku
     * 
     * Pro předobjednávky vytvoří proforma fakturu.
     * Pro běžné produkty vytvoří rovnou daňový doklad.
     * 
     * @hooked woocommerce_order_status_zaplaceno
     * 
     * @param int $order_id ID objednávky
     * @return void
     */
    public function process_paid_order($order_id)
    {
        $this->logger->info('Zpracovávám zaplacenou objednávku', ['order_id' => $order_id]);

        $order = wc_get_order($order_id);

        if (!$order) {
            $this->logger->error('Objednávka nenalezena', ['order_id' => $order_id]);
            return;
        }

        // Kontrola zda už má fakturu
        if ($this->has_invoice($order)) {
            $this->logger->info('Objednávka již má fakturu, přeskakuji', ['order_id' => $order_id]);
            return;
        }

        // Kontrola zda je to předobjednávka
        if ($this->is_preorder($order)) {
            $this->logger->info('Detekována předobjednávka - vytvářím proforma', ['order_id' => $order_id]);
            $this->create_proforma_invoice($order);
        } else {
            $this->logger->info('Běžná objednávka - vytvářím daňový doklad', ['order_id' => $order_id]);
            $this->create_tax_invoice($order);
        }
    }

    /**
     * Zpracuje payment_complete hook
     * 
     * Fallback pro standardní WooCommerce payment complete.
     * 
     * @hooked woocommerce_payment_complete
     * 
     * @param int $order_id ID objednávky
     * @return void
     */
    public function process_payment_complete($order_id)
    {
        $this->logger->debug('Payment complete hook', ['order_id' => $order_id]);

        // Zavoláme stejnou logiku jako pro paid order
        $this->process_paid_order($order_id);
    }

    /**
     * Zpracuje dokončenou objednávku
     * 
     * Pro předobjednávky vytvoří konečný daňový doklad navázaný na proforma.
     * 
     * @hooked woocommerce_order_status_completed
     * 
     * @param int $order_id ID objednávky
     * @return void
     */
    public function process_completed_order($order_id)
    {
        $this->logger->info('Zpracovávám dokončenou objednávku', ['order_id' => $order_id]);

        $order = wc_get_order($order_id);

        if (!$order) {
            $this->logger->error('Objednávka nenalezena', ['order_id' => $order_id]);
            return;
        }

        // Pokud má proforma fakturu, vytvoříme konečnou
        if ($this->has_proforma($order)) {
            $proforma_id = $this->get_proforma_invoice_id($order);
            $this->logger->info('Vytvářím konečnou fakturu navázanou na proforma', [
                'order_id' => $order_id,
                'proforma_id' => $proforma_id,
            ]);
            $this->create_tax_invoice($order, $proforma_id);
        }
        // Pokud nemá žádnou fakturu, vytvoříme konečnou
        elseif (!$this->has_tax_invoice($order)) {
            $this->logger->info('Objednávka nemá fakturu - vytvářím daňový doklad', ['order_id' => $order_id]);
            $this->create_tax_invoice($order);
        }
    }

    /**
     * Vytvoří proforma fakturu (zálohovou)
     * 
     * @param WC_Order $order Instance objednávky
     * @return int|false Invoice ID nebo false při chybě
     */
    public function create_proforma_invoice($order)
    {
        $this->logger->info('Vytvářím proforma fakturu', ['order_id' => $order->get_id()]);

        // Kontrola duplicity
        if ($this->has_proforma($order)) {
            $this->logger->warning('Proforma faktura již existuje', ['order_id' => $order->get_id()]);
            return false;
        }

        // Sestavení dat faktury
        $invoice_data = $this->build_invoice_payload($order, 'proforma');

        if (is_wp_error($invoice_data)) {
            $this->logger->error('Chyba při sestavování dat proforma faktury', [
                'error' => $invoice_data->get_error_message(),
            ]);
            $order->add_order_note('❌ Chyba při vytváření proforma faktury: ' . $invoice_data->get_error_message());
            return false;
        }

        // 🔍 DEBUG: Vypsat přesný payload PŘED odesláním
        error_log('🔍 DEBUG PAYLOAD PRO PROFORMA FAKTURU:');
        error_log('════════════════════════════════════════');
        error_log('Objednávka: #' . $order->get_id());
        error_log('Celý payload: ' . print_r($invoice_data, true));
        error_log('');
        error_log('📋 POLOŽKY FAKTURY:');
        if (isset($invoice_data['items']) && is_array($invoice_data['items'])) {
            foreach ($invoice_data['items'] as $index => $item) {
                error_log("  Položka #{$index}:");
                error_log("    - Text: " . ($item['text'] ?? 'N/A'));
                error_log("    - VAT: " . ($item['vat'] ?? 'N/A') . '%');
                error_log("    - Chart Account ID: " . (isset($item['chart_account_id']) ? $item['chart_account_id'] : '❌ NENÍ (správně pro proforma)'));
                error_log("    - VAT Account ID: " . ($item['vat_account_id'] ?? '❌ CHYBÍ!'));
                error_log("    - Price: " . ($item['price'] ?? 'N/A'));
            }
        }
        error_log('════════════════════════════════════════');
        error_log('');

        // API request
        $response = $this->api_client->post('proforma_invoice_issued', $invoice_data);

        if (is_wp_error($response)) {
            $this->logger->error('API chyba při vytváření proforma faktury', [
                'error' => $response->get_error_message(),
            ]);
            $order->add_order_note('❌ Chyba při vytváření proforma faktury: ' . $response->get_error_message());
            return false;
        }

        // Kontrola odpovědi
        if (!isset($response['id'])) {
            $this->logger->error('API nevrátilo invoice ID', ['response' => $response]);
            $order->add_order_note('❌ Chyba: iÚčto API nevrátilo ID faktury');
            return false;
        }

        $invoice_id = (int) $response['id'];

        // Uložení do meta dat
        $order->update_meta_data('_iucto_proforma_invoice_id', $invoice_id);
        $order->update_meta_data('_iucto_invoice_type', 'proforma');
        $order->save();

        $this->logger->info('Proforma faktura vytvořena', [
            'order_id' => $order->get_id(),
            'invoice_id' => $invoice_id,
        ]);

        $note = sprintf('✅ Proforma faktura vytvořena v iÚčto (ID: %d)', $invoice_id);
        if (!$this->settings->is_auto_send_enabled()) {
            $note .= ' - Email nebyl odeslán (odesílání vypnuto). Odešlete ručně z iÚčto.';
        }
        $order->add_order_note($note);

        // Odeslání emailu
        if ($this->settings->is_auto_send_enabled()) {
            $this->send_invoice_by_email($invoice_id, 'proforma_invoice_issued', $order);
        }

        // Action hook pro custom kód
        do_action('iucto_woo_proforma_invoice_created', $invoice_id, $order);

        return $invoice_id;
    }

    /**
     * Vytvoří daňový doklad (konečnou fakturu)
     * 
     * @param WC_Order $order       Instance objednávky
     * @param int      $proforma_id ID proforma faktury (volitelné)
     * @return int|false Invoice ID nebo false při chybě
     */
    public function create_tax_invoice($order, $proforma_id = null)
    {
        $this->logger->info('Vytvářím daňový doklad', [
            'order_id' => $order->get_id(),
            'proforma_id' => $proforma_id,
        ]);

        // Kontrola duplicity
        if ($this->has_tax_invoice($order)) {
            $this->logger->warning('Daňový doklad již existuje', ['order_id' => $order->get_id()]);
            return false;
        }

        // Sestavení dat faktury
        $invoice_data = $this->build_invoice_payload($order, 'tax', $proforma_id);

        if (is_wp_error($invoice_data)) {
            $this->logger->error('Chyba při sestavování dat daňového dokladu', [
                'error' => $invoice_data->get_error_message(),
            ]);
            $order->add_order_note('❌ Chyba při vytváření faktury: ' . $invoice_data->get_error_message());
            return false;
        }

        // 🔍 DEBUG: Vypsat přesný payload PŘED odesláním
        error_log('🔍 DEBUG PAYLOAD PRO DAŇOVÝ DOKLAD:');
        error_log('════════════════════════════════════════');
        error_log('Objednávka: #' . $order->get_id());
        error_log('Celý payload: ' . print_r($invoice_data, true));
        error_log('');
        error_log('📋 POLOŽKY FAKTURY:');
        if (isset($invoice_data['items']) && is_array($invoice_data['items'])) {
            foreach ($invoice_data['items'] as $index => $item) {
                error_log("  Položka #{$index}:");
                error_log("    - Text: " . ($item['text'] ?? 'N/A'));
                error_log("    - VAT: " . ($item['vat'] ?? 'N/A') . '%');
                error_log("    - Chart Account ID: " . ($item['chart_account_id'] ?? '❌ CHYBÍ!'));
                error_log("    - VAT Account ID: " . ($item['vat_account_id'] ?? '❌ CHYBÍ!'));
                error_log("    - Price: " . ($item['price'] ?? 'N/A'));
            }
        }
        error_log('════════════════════════════════════════');
        error_log('');

        // API request
        $response = $this->api_client->post('invoice_issued', $invoice_data);

        if (is_wp_error($response)) {
            $this->logger->error('API chyba při vytváření daňového dokladu', [
                'error' => $response->get_error_message(),
            ]);
            $order->add_order_note('❌ Chyba při vytváření faktury: ' . $response->get_error_message());
            return false;
        }

        // Kontrola odpovědi
        if (!isset($response['id'])) {
            $this->logger->error('API nevrátilo invoice ID', ['response' => $response]);
            $order->add_order_note('❌ Chyba: iÚčto API nevrátilo ID faktury');
            return false;
        }

        $invoice_id = (int) $response['id'];

        // Uložení do meta dat
        $order->update_meta_data('_iucto_tax_invoice_id', $invoice_id);
        $order->update_meta_data('_iucto_invoice_id', $invoice_id); // Hlavní invoice ID
        $order->update_meta_data('_iucto_invoice_type', 'tax');
        $order->save();

        $this->logger->info('Daňový doklad vytvořen', [
            'order_id' => $order->get_id(),
            'invoice_id' => $invoice_id,
        ]);

        // Order note
        $note = $proforma_id
            ? sprintf('✅ Konečná faktura vytvořena v iÚčto (ID: %d) - navázána na proforma %d', $invoice_id, $proforma_id)
            : sprintf('✅ Faktura vytvořena v iÚčto (ID: %d)', $invoice_id);

        if (!$this->settings->is_auto_send_enabled()) {
            $note .= ' - Email nebyl odeslán (odesílání vypnuto). Odešlete ručně z iÚčto.';
        }

        $order->add_order_note($note);

        // Odeslání emailu
        if ($this->settings->is_auto_send_enabled()) {
            $this->send_invoice_by_email($invoice_id, 'invoice_issued', $order);
        }

        // Action hook pro custom kód
        do_action('iucto_woo_tax_invoice_created', $invoice_id, $order, $proforma_id);

        return $invoice_id;
    }

    /**
     * Sestaví payload pro iÚčto API
     * 
     * @param WC_Order $order       Instance objednávky
     * @param string   $type        Typ faktury ('proforma' nebo 'tax')
     * @param int      $proforma_id ID proforma faktury (pro navázání)
     * @return array|WP_Error Data faktury nebo WP_Error
     */
    private function build_invoice_payload($order, $type = 'tax', $proforma_id = null)
    {
        // Získání nebo vytvoření zákazníka
        $customer_id = $this->customer_manager->get_or_create_customer($order);

        if (is_wp_error($customer_id)) {
            return $customer_id;
        }

        // Základní data faktury
        $date = current_time('Y-m-d');
        $maturity_days = $this->settings->get_invoice_maturity();
        $due_date = date('Y-m-d', strtotime("+{$maturity_days} days"));

        $data = [
            'variable_symbol' => (string) $order->get_order_number(),
            'date' => $date,
            'maturity_date' => $due_date,
            'currency' => $order->get_currency(),
            'description' => sprintf('Objednávka #%s', $order->get_order_number()),
            'rounding_type' => 'none',
            'bank_account' => $this->settings->get_bank_account_id(),
            'customer_id' => $customer_id,
            'payment_type' => $this->map_payment_method($order->get_payment_method()),
        ];

        // Položky faktury - PŘEDÁME TYP!
        $items = $this->build_invoice_items($order, $type);
        if (empty($items)) {
            return new WP_Error('no_items', 'Faktura nemá žádné položky.');
        }
        $data['items'] = $items;

        // Navázání na proforma fakturu
        if ($type === 'tax' && $proforma_id) {
            $data['advance_invoice_id'] = (int) $proforma_id;
        }

        return $data;
    }

    /**
     * Sestaví položky faktury z objednávky
     * 
     * @param WC_Order $order Instance objednávky
     * @param string   $type  Typ faktury ('proforma' nebo 'tax')
     * @return array Pole položek faktury
     */
    private function build_invoice_items($order, $type = 'tax')
    {
        $items = [];
        $vat_rate = $this->settings->get_vat_rate();
        $vat_account_id = $this->settings->get_vat_account_id();

        // Pro PROFORMA: pouze chart_account_id (bez accountentrytype_id)
        // Pro TAX: obojí
        $chart_account_id = $this->settings->get_chart_account_id();
        $accountentrytype_id = null;

        if ($type === 'tax') {
            $accountentrytype_id = $this->settings->get_accountentrytype_id();
        }

        // 🔍 DEBUG: Vypsat načtená nastavení
        error_log('🔍 DEBUG NASTAVENÍ Z DATABÁZE:');
        error_log('  Typ faktury: ' . $type);
        error_log('  chart_account_id: ' . var_export($chart_account_id, true));
        error_log('  accountentrytype_id: ' . var_export($accountentrytype_id, true));
        error_log('  vat_rate: ' . var_export($vat_rate, true));
        error_log('  vat_account_id: ' . var_export($vat_account_id, true));
        error_log('');

        // Produkty
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $qty = max(1, (float) $item->get_quantity());

            // Výpočet jednotkové ceny bez DPH
            $unit_price = $product && $product->is_taxable()
                ? round($item->get_subtotal() / $qty, 2)
                : round($item->get_total() / $qty, 2);

            // VAT sazba pro produkt
            $item_vat = ($product && $product->is_taxable()) ? $vat_rate : 0;

            $item_data = [
                'text' => $item->get_name(),
                'amount' => $qty,
                'unit' => 'ks',
                'price' => $unit_price,
                'unit_price_inc_vat' => false,
                'vat' => $item_vat,
                'chart_account_id' => $chart_account_id, // Pro oba typy faktur
            ];

            // Accountentrytype POUZE pro konečné faktury
            if ($type === 'tax' && $accountentrytype_id !== null) {
                $item_data['accountentrytype_id'] = $accountentrytype_id;
            }

            // Přidat vat_account_id POUZE pokud má položka nenulové DPH
            if ($item_vat > 0) {
                $item_data['vat_account_id'] = $vat_account_id;
            }

            $items[] = $item_data;
        }

        // Doprava
        foreach ($order->get_items('shipping') as $ship_item) {
            $shipping_vat = $this->settings->is_vat_payer() ? $vat_rate : 0;

            $ship_data = [
                'text' => 'Doprava: ' . $ship_item->get_method_title(),
                'amount' => 1,
                'unit' => 'ks',
                'price' => round($ship_item->get_total(), 2),
                'unit_price_inc_vat' => false,
                'vat' => $shipping_vat,
                'chart_account_id' => $chart_account_id, // Pro oba typy faktur
            ];

            // Accountentrytype POUZE pro konečné faktury
            if ($type === 'tax' && $accountentrytype_id !== null) {
                $ship_data['accountentrytype_id'] = $accountentrytype_id;
            }

            // Přidat vat_account_id POUZE pokud má doprava nenulové DPH
            if ($shipping_vat > 0) {
                $ship_data['vat_account_id'] = $vat_account_id;
            }

            $items[] = $ship_data;
        }

        return $items;
    }

    /**
     * Mapuje WooCommerce payment method na iÚčto payment type
     * 
     * @param string $payment_method WC payment method
     * @return string iÚčto payment type
     */
    private function map_payment_method($payment_method)
    {
        $map = [
            'cod' => 'cashondelivery',
            'bacs' => 'transfer',
            'bank_transfer' => 'transfer',
            'stripe' => 'creditcard',
            'stripe_cc' => 'creditcard',
            'stripe_sepa' => 'transfer',
            'gopay' => 'creditcard',
            'paypal' => 'creditcard',
            'cheque' => 'cheque',
        ];

        // Filter pro možnost rozšíření
        $map = apply_filters('iucto_woo_payment_method_map', $map);

        return isset($map[$payment_method]) ? $map[$payment_method] : 'transfer';
    }

    /**
     * Odešle fakturu emailem zákazníkovi přes iÚčto API
     * 
     * @param int      $invoice_id   ID faktury v iÚčto
     * @param string   $invoice_type Typ faktury (proforma_invoice_issued, invoice_issued)
     * @param WC_Order $order        Instance objednávky
     * @return bool True při úspěchu
     */
    public function send_invoice_by_email($invoice_id, $invoice_type, $order)
    {
        $endpoint = $invoice_type . '/' . $invoice_id . '/email';
        $email = $order->get_billing_email();

        if (empty($email)) {
            $this->logger->warning('Nelze odeslat fakturu - chybí email', [
                'order_id' => $order->get_id(),
                'invoice_id' => $invoice_id,
            ]);
            $order->add_order_note('⚠️ Nelze odeslat fakturu - email zákazníka není vyplněn');
            return false;
        }

        // Příprava zprávy
        $invoice_label = ($invoice_type === 'proforma_invoice_issued') ? 'zálohovou fakturu' : 'fakturu';
        $company_name = $this->settings->get_company_name();

        $message = sprintf(
            "Dobrý den,\n\nv příloze zasíláme %s k objednávce č. %s.\n\nDěkujeme za Vaši objednávku.\n\nS pozdravem,\n%s",
            $invoice_label,
            $order->get_order_number(),
            $company_name
        );

        $email_data = [
            'message' => $message,
            'recipient' => [$email],
            'attach_pdf' => true,
        ];

        $this->logger->info('Odesílám fakturu emailem', [
            'invoice_id' => $invoice_id,
            'email' => $email,
        ]);

        $response = $this->api_client->post($endpoint, $email_data);

        if (is_wp_error($response)) {
            $this->logger->error('Chyba při odesílání emailu', [
                'error' => $response->get_error_message(),
            ]);
            $order->add_order_note('⚠️ Chyba při odesílání faktury emailem: ' . $response->get_error_message());
            return false;
        }

        $type_label = ($invoice_type === 'proforma_invoice_issued') ? 'Proforma' : 'Faktura';
        $order->add_order_note(sprintf('📧 %s odeslána na email %s přes iÚčto', $type_label, $email));

        $this->logger->info('Faktura odeslána emailem', [
            'invoice_id' => $invoice_id,
            'email' => $email,
        ]);

        return true;
    }

    /**
     * Kontrolní metody
     */

    /**
     * Má objednávka nějakou fakturu?
     * 
     * @param WC_Order $order Instance objednávky
     * @return bool
     */
    public function has_invoice($order)
    {
        return $this->has_proforma($order) || $this->has_tax_invoice($order);
    }

    /**
     * Má objednávka proforma fakturu?
     * 
     * @param WC_Order $order Instance objednávky
     * @return bool
     */
    public function has_proforma($order)
    {
        return (int) $order->get_meta('_iucto_proforma_invoice_id', true) > 0;
    }

    /**
     * Má objednávka daňový doklad?
     * 
     * @param WC_Order $order Instance objednávky
     * @return bool
     */
    public function has_tax_invoice($order)
    {
        return (int) $order->get_meta('_iucto_tax_invoice_id', true) > 0;
    }

    /**
     * Vrátí ID proforma faktury
     * 
     * @param WC_Order $order Instance objednávky
     * @return int
     */
    public function get_proforma_invoice_id($order)
    {
        return (int) $order->get_meta('_iucto_proforma_invoice_id', true);
    }

    /**
     * Vrátí ID daňového dokladu
     * 
     * @param WC_Order $order Instance objednávky
     * @return int
     */
    public function get_tax_invoice_id($order)
    {
        return (int) $order->get_meta('_iucto_tax_invoice_id', true);
    }

    /**
     * Je objednávka předobjednávka?
     * 
     * Kontroluje:
     * 1. WC Pre-Orders plugin
     * 2. Kategorie označené jako předobjednávky v nastavení
     * 
     * @param WC_Order $order Instance objednávky
     * @return bool
     */
    private function is_preorder($order)
    {
        // Primárně kontrola přes WC Pre-Orders plugin
        if (class_exists('WC_Pre_Orders_Order')) {
            if (WC_Pre_Orders_Order::order_contains_pre_order($order)) {
                $this->logger->debug('Předobjednávka detekována přes WC Pre-Orders plugin');
                return true;
            }
        }

        // Fallback: kontrola kategorií produktů
        $preorder_categories = $this->settings->get_preorder_categories();

        if (empty($preorder_categories)) {
            return false;
        }

        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if (!$product) {
                continue;
            }

            $product_categories = $product->get_category_ids();

            // Pokud produkt patří do kategorie předobjednávek
            if (array_intersect($product_categories, $preorder_categories)) {
                $this->logger->debug('Předobjednávka detekována přes kategorii produktu', [
                    'product_id' => $product->get_id(),
                    'categories' => $product_categories,
                ]);
                return true;
            }
        }

        return false;
    }
}
