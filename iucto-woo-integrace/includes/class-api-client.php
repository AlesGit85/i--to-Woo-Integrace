<?php
/**
 * API Client třída
 * 
 * Zajišťuje komunikaci s iÚčto API.
 * Wrapper pro HTTP requesty s error handlingem a logováním.
 *
 * @package IUcto_Woo_Integration
 * @since 2.0.0
 */

// Prevence přímého přístupu
if (!defined('ABSPATH')) {
    exit;
}

class IUcto_Woo_API_Client {
    
    /**
     * Instance settings
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
     * Poslední chyba z API
     * 
     * @var WP_Error|null
     */
    private $last_error;
    
    /**
     * Konstruktor
     * 
     * @param IUcto_Woo_Settings $settings Instance nastavení
     * @param IUcto_Woo_Logger   $logger   Instance loggeru
     */
    public function __construct($settings, $logger) {
        $this->settings = $settings;
        $this->logger = $logger;
        $this->last_error = null;
    }
    
    /**
     * GET request
     * 
     * @param string $endpoint API endpoint (bez base URL)
     * @param array  $params   Query parametry
     * @return array|WP_Error Odpověď z API nebo WP_Error při chybě
     */
    public function get($endpoint, $params = []) {
        return $this->request('GET', $endpoint, null, $params);
    }
    
    /**
     * POST request
     * 
     * @param string $endpoint API endpoint
     * @param array  $data     Data k odeslání
     * @return array|WP_Error Odpověď z API nebo WP_Error při chybě
     */
    public function post($endpoint, $data = []) {
        return $this->request('POST', $endpoint, $data);
    }
    
    /**
     * PUT request
     * 
     * @param string $endpoint API endpoint
     * @param array  $data     Data k aktualizaci
     * @return array|WP_Error Odpověď z API nebo WP_Error při chybě
     */
    public function put($endpoint, $data = []) {
        return $this->request('PUT', $endpoint, $data);
    }
    
    /**
     * DELETE request
     * 
     * @param string $endpoint API endpoint
     * @return array|WP_Error Odpověď z API nebo WP_Error při chybě
     */
    public function delete($endpoint) {
        return $this->request('DELETE', $endpoint);
    }
    
    /**
     * Provede HTTP request na iÚčto API
     * 
     * @param string $method   HTTP metoda (GET, POST, PUT, DELETE)
     * @param string $endpoint API endpoint
     * @param array  $data     Data pro body (POST, PUT)
     * @param array  $params   Query parametry (GET)
     * @return array|WP_Error Dekódovaná odpověď nebo WP_Error
     */
    private function request($method, $endpoint, $data = null, $params = []) {
        // Reset poslední chyby
        $this->last_error = null;
        
        // Kontrola API klíče
        $api_key = $this->settings->get_api_key();
        if (empty($api_key)) {
            $error = new WP_Error(
                'no_api_key',
                'API klíč není nastaven v nastavení pluginu.'
            );
            $this->last_error = $error;
            $this->logger->error('API request selhal: Chybí API klíč');
            return $error;
        }
        
        // Sestavení URL
        $url = rtrim(IUCTO_API_URL, '/');
        if (!empty($endpoint)) {
            $url .= '/' . ltrim($endpoint, '/');
        }
        
        // Přidání query parametrů pro GET
        if ($method === 'GET' && !empty($params)) {
            $url = add_query_arg($params, $url);
        }
        
        // Sestavení argumentů requestu
        $args = [
            'method'  => $method,
            'headers' => [
                'X-Auth-Key'  => $api_key,
                'Content-Type'=> 'application/json',
                'Accept'      => 'application/hal+json, application/json',
            ],
            'timeout' => 30,
        ];
        
        // Přidání body pro POST, PUT, PATCH
        if (in_array($method, ['POST', 'PUT', 'PATCH']) && $data !== null) {
            $args['body'] = wp_json_encode($data);
        }
        
        // Logování requestu
        $this->logger->log_api_request($method, $endpoint, $data);
        
        // Provedení requestu
        $response = wp_remote_request($url, $args);
        
        // Kontrola WP_Error
        if (is_wp_error($response)) {
            $this->last_error = $response;
            $this->logger->error('API request selhal (WP_Error)', [
                'error' => $response->get_error_message(),
                'endpoint' => $endpoint,
            ]);
            return $response;
        }
        
        // Získání status kódu a body
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        // Logování odpovědi
        $this->logger->log_api_response($status_code, $body, $endpoint);
        
        // Dekódování JSON
        $decoded = json_decode($body, true);
        
        // Kontrola HTTP status kódu
        if ($status_code < 200 || $status_code >= 300) {
            $error_message = $this->extract_error_message($decoded, $body, $status_code);
            $error = new WP_Error('api_error', $error_message, ['status_code' => $status_code]);
            $this->last_error = $error;
            
            $this->logger->error('API vrátilo chybu', [
                'status_code' => $status_code,
                'message' => $error_message,
                'endpoint' => $endpoint,
            ]);
            
            return $error;
        }
        
        // Úspěšná odpověď
        return is_null($decoded) ? $body : $decoded;
    }
    
    /**
     * Extrahuje chybovou zprávu z API odpovědi
     * 
     * @param mixed  $decoded     Dekódovaná JSON odpověď
     * @param string $raw_body    Surové body
     * @param int    $status_code HTTP status kód
     * @return string Chybová zpráva
     */
    private function extract_error_message($decoded, $raw_body, $status_code) {
        // Pokud je odpověď pole s message
        if (is_array($decoded) && isset($decoded['message'])) {
            return $decoded['message'];
        }
        
        // Pokud je odpověď pole s errors
        if (is_array($decoded) && isset($decoded['errors'])) {
            if (is_array($decoded['errors'])) {
                return wp_json_encode($decoded['errors'], JSON_UNESCAPED_UNICODE);
            }
            return $decoded['errors'];
        }
        
        // Pokud máme dekódovaný response, vrátíme ho jako JSON
        if (is_array($decoded)) {
            return wp_json_encode($decoded, JSON_UNESCAPED_UNICODE);
        }
        
        // Pokud máme surové body (kratší než 500 znaků)
        if (!empty($raw_body) && strlen($raw_body) < 500) {
            return $raw_body;
        }
        
        // Fallback na generic message
        return sprintf('HTTP Error %d', $status_code);
    }
    
    /**
     * Validuje API připojení
     * 
     * Pokusí se získat seznam bankovních účtů jako test.
     * 
     * @return bool True pokud je API dostupné
     */
    public function validate_connection() {
        $response = $this->get('bank_account');
        
        if (is_wp_error($response)) {
            $this->logger->error('Test API připojení selhal', [
                'error' => $response->get_error_message(),
            ]);
            return false;
        }
        
        $this->logger->info('Test API připojení úspěšný');
        return true;
    }
    
    /**
     * Je API dostupné?
     * 
     * Kontroluje zda je API key nastaven a poslední request nebyl chyba.
     * 
     * @return bool
     */
    public function is_connected() {
        return !empty($this->settings->get_api_key()) && null === $this->last_error;
    }
    
    /**
     * Vrátí poslední chybu z API
     * 
     * @return WP_Error|null
     */
    public function get_last_error() {
        return $this->last_error;
    }
    
    /**
     * Vrátí poslední chybovou zprávu jako string
     * 
     * @return string Prázdný string pokud není žádná chyba
     */
    public function get_last_error_message() {
        if (null === $this->last_error) {
            return '';
        }
        
        return $this->last_error->get_error_message();
    }
}