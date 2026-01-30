<?php
/**
 * Customer Manager třída
 * 
 * Spravuje zákazníky v iÚčto - vytváření, vyhledávání, aktualizace.
 * Cachuje customer ID v meta datech objednávky.
 *
 * @package IUcto_Woo_Integration
 * @since 2.0.0
 */

// Prevence přímého přístupu
if (!defined('ABSPATH')) {
    exit;
}

class IUcto_Woo_Customer_Manager {
    
    /**
     * Instance API klienta
     * 
     * @var IUcto_Woo_API_Client
     */
    private $api_client;
    
    /**
     * Instance loggeru
     * 
     * @var IUcto_Woo_Logger
     */
    private $logger;
    
    /**
     * Konstruktor
     * 
     * @param IUcto_Woo_API_Client $api_client Instance API klienta
     * @param IUcto_Woo_Logger     $logger     Instance loggeru
     */
    public function __construct($api_client, $logger) {
        $this->api_client = $api_client;
        $this->logger = $logger;
    }
    
    /**
     * Získá nebo vytvoří zákazníka v iÚčto pro danou objednávku
     * 
     * 1. Zkusí najít uložené customer_id v meta datech objednávky
     * 2. Zkusí najít zákazníka podle emailu v iÚčto
     * 3. Vytvoří nového zákazníka
     * 
     * @param WC_Order $order Instance objednávky
     * @return int|WP_Error Customer ID nebo WP_Error při chybě
     */
    public function get_or_create_customer($order) {
        // Pokus získat customer_id z meta dat objednávky
        $customer_id = $this->get_cached_customer_id($order);
        
        if ($customer_id > 0) {
            $this->logger->debug('Použit cachovaný customer_id', [
                'customer_id' => $customer_id,
                'order_id' => $order->get_id(),
            ]);
            return $customer_id;
        }
        
        // Pokus najít zákazníka podle emailu
        $email = $order->get_billing_email();
        $customer_id = $this->find_customer_by_email($email);
        
        if (is_wp_error($customer_id)) {
            $this->logger->error('Chyba při hledání zákazníka', [
                'email' => $email,
                'error' => $customer_id->get_error_message(),
            ]);
            return $customer_id;
        }
        
        if ($customer_id > 0) {
            // Zákazník nalezen - uložíme do cache
            $this->cache_customer_id($order, $customer_id);
            
            $this->logger->info('Nalezen existující zákazník', [
                'customer_id' => $customer_id,
                'email' => $email,
            ]);
            
            return $customer_id;
        }
        
        // Zákazník nenalezen - vytvoříme nového
        $customer_id = $this->create_customer($order);
        
        if (is_wp_error($customer_id)) {
            $this->logger->error('Chyba při vytváření zákazníka', [
                'email' => $email,
                'error' => $customer_id->get_error_message(),
            ]);
            return $customer_id;
        }
        
        // Uložíme do cache
        $this->cache_customer_id($order, $customer_id);
        
        $this->logger->info('Vytvořen nový zákazník', [
            'customer_id' => $customer_id,
            'email' => $email,
        ]);
        
        return $customer_id;
    }
    
    /**
     * Najde zákazníka v iÚčto podle emailu
     * 
     * @param string $email Email zákazníka
     * @return int|WP_Error Customer ID nebo 0 pokud nenalezen, WP_Error při chybě
     */
    public function find_customer_by_email($email) {
        if (empty($email)) {
            return new WP_Error('empty_email', 'Email zákazníka je prázdný.');
        }
        
        $this->logger->debug('Hledám zákazníka podle emailu', ['email' => $email]);
        
        // API request
        $response = $this->api_client->get('customer', ['email' => $email]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Kontrola zda byl zákazník nalezen
        if (isset($response['_embedded']['customer'][0]['id'])) {
            return (int) $response['_embedded']['customer'][0]['id'];
        }
        
        // Zákazník nenalezen
        return 0;
    }
    
    /**
     * Vytvoří nového zákazníka v iÚčto
     * 
     * @param WC_Order $order Instance objednávky
     * @return int|WP_Error Customer ID nebo WP_Error při chybě
     */
    public function create_customer($order) {
        // Sestavení dat zákazníka
        $customer_data = $this->build_customer_data($order);
        
        // Validace dat
        $validation = $this->validate_customer_data($customer_data);
        if (is_wp_error($validation)) {
            return $validation;
        }
        
        $this->logger->debug('Vytvářím nového zákazníka', [
            'email' => $customer_data['email'],
            'name' => $customer_data['name'],
        ]);
        
        // API request
        $response = $this->api_client->post('customer', $customer_data);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Kontrola odpovědi
        if (!isset($response['id'])) {
            return new WP_Error(
                'invalid_response',
                'iÚčto API nevrátilo customer ID.',
                $response
            );
        }
        
        return (int) $response['id'];
    }
    
    /**
     * Aktualizuje data zákazníka v iÚčto
     * 
     * @param int      $customer_id ID zákazníka v iÚčto
     * @param WC_Order $order       Instance objednávky
     * @return bool|WP_Error True při úspěchu nebo WP_Error
     */
    public function update_customer($customer_id, $order) {
        $customer_data = $this->build_customer_data($order);
        
        $this->logger->debug('Aktualizuji zákazníka', [
            'customer_id' => $customer_id,
            'email' => $customer_data['email'],
        ]);
        
        $response = $this->api_client->put('customer/' . $customer_id, $customer_data);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return true;
    }
    
    /**
     * Sestaví data zákazníka z WooCommerce objednávky
     * 
     * @param WC_Order $order Instance objednávky
     * @return array Data zákazníka pro iÚčto API
     */
    private function build_customer_data($order) {
        // Jméno zákazníka
        $customer_name = trim(
            $order->get_billing_first_name() . ' ' . $order->get_billing_last_name()
        );
        
        // Fallback na email pokud není jméno
        if (empty($customer_name)) {
            $customer_name = $order->get_billing_email();
        }
        
        // Sestavení adresy
        $address = [
            'street' => $order->get_billing_address_1() ?: '',
            'city' => $order->get_billing_city() ?: '',
            'postal_code' => $order->get_billing_postcode() ?: '',
            'country' => $order->get_billing_country() ?: 'CZ',
        ];
        
        // Data zákazníka
        $data = [
            'name' => $customer_name,
            'email' => $order->get_billing_email(),
            'phone' => $order->get_billing_phone() ?: '',
            'usual_maturity' => 14, // TODO: Použít z nastavení
            'preferred_payment_method' => 'transfer',
            'invoice_language' => 'cs',
            'vat_payer' => false, // Zákazník obvykle není plátce DPH (naše firma ano)
            'address' => $address,
        ];
        
        // Přidání firemních údajů pokud jsou vyplněny
        if (!empty($order->get_billing_company())) {
            $data['company_name'] = $order->get_billing_company();
        }
        
        return $data;
    }
    
    /**
     * Validuje data zákazníka před odesláním do API
     * 
     * @param array $data Data zákazníka
     * @return bool|WP_Error True pokud jsou data validní, WP_Error pokud ne
     */
    public function validate_customer_data($data) {
        $errors = [];
        
        // Kontrola povinných polí
        if (empty($data['name'])) {
            $errors[] = 'Jméno zákazníka je povinné.';
        }
        
        if (empty($data['email'])) {
            $errors[] = 'Email zákazníka je povinný.';
        } elseif (!is_email($data['email'])) {
            $errors[] = 'Email zákazníka má neplatný formát.';
        }
        
        // Pokud jsou chyby, vrátíme WP_Error
        if (!empty($errors)) {
            return new WP_Error('invalid_customer_data', implode(' ', $errors));
        }
        
        return true;
    }
    
    /**
     * Získá cachované customer_id z meta dat objednávky
     * 
     * Podporuje HPOS i legacy post meta.
     * 
     * @param WC_Order $order Instance objednávky
     * @return int Customer ID nebo 0
     */
    private function get_cached_customer_id($order) {
        $customer_id = $order->get_meta('_iucto_customer_id', true);
        return (int) $customer_id;
    }
    
    /**
     * Uloží customer_id do meta dat objednávky
     * 
     * Podporuje HPOS i legacy post meta.
     * 
     * @param WC_Order $order       Instance objednávky
     * @param int      $customer_id Customer ID
     * @return void
     */
    private function cache_customer_id($order, $customer_id) {
        $order->update_meta_data('_iucto_customer_id', (int) $customer_id);
        $order->save();
        
        $this->logger->debug('Customer ID uloženo do cache', [
            'order_id' => $order->get_id(),
            'customer_id' => $customer_id,
        ]);
    }
}