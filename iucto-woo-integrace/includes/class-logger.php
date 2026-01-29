<?php
/**
 * Logger třída
 * 
 * Wrapper pro WooCommerce logger s podporou různých úrovní logování.
 * Automaticky přidává kontext a formátuje zprávy.
 *
 * @package IUcto_Woo_Integration
 * @since 2.0.0
 */

// Prevence přímého přístupu
if (!defined('ABSPATH')) {
    exit;
}

class IUcto_Woo_Logger {
    
    /**
     * Instance WooCommerce loggeru
     * 
     * @var WC_Logger|null
     */
    private $logger;
    
    /**
     * Název zdroje pro logy
     * 
     * @var string
     */
    private $source = 'iucto-woo-integration';
    
    /**
     * Konstruktor
     */
    public function __construct() {
        // WooCommerce logger se inicializuje až když je potřeba
        $this->logger = null;
    }
    
    /**
     * Získá nebo vytvoří instanci WC loggeru
     * 
     * @return WC_Logger
     */
    private function get_logger() {
        if (null === $this->logger && function_exists('wc_get_logger')) {
            $this->logger = wc_get_logger();
        }
        return $this->logger;
    }
    
    /**
     * Loguje debug zprávu
     * 
     * Používá se pro detailní diagnostiku během vývoje.
     * Loguje pouze pokud je zapnutý WP_DEBUG.
     * 
     * @param string $message Zpráva k zalogování
     * @param array  $context Dodatečný kontext (pole klíč-hodnota)
     * @return void
     */
    public function debug($message, $context = []) {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        
        $this->log('debug', $message, $context);
    }
    
    /**
     * Loguje informační zprávu
     * 
     * Používá se pro běžné informace o průběhu operací.
     * 
     * @param string $message Zpráva k zalogování
     * @param array  $context Dodatečný kontext
     * @return void
     */
    public function info($message, $context = []) {
        $this->log('info', $message, $context);
    }
    
    /**
     * Loguje varování
     * 
     * Používá se pro situace, které nejsou chyby, ale vyžadují pozornost.
     * 
     * @param string $message Zpráva k zalogování
     * @param array  $context Dodatečný kontext
     * @return void
     */
    public function warning($message, $context = []) {
        $this->log('warning', $message, $context);
    }
    
    /**
     * Loguje chybu
     * 
     * Používá se pro chyby, které neumožní dokončit operaci.
     * 
     * @param string $message Zpráva k zalogování
     * @param array  $context Dodatečný kontext
     * @return void
     */
    public function error($message, $context = []) {
        $this->log('error', $message, $context);
    }
    
    /**
     * Loguje kritickou chybu
     * 
     * Používá se pro vážné problémy vyžadující okamžitou pozornost.
     * 
     * @param string $message Zpráva k zalogování
     * @param array  $context Dodatečný kontext
     * @return void
     */
    public function critical($message, $context = []) {
        $this->log('critical', $message, $context);
    }
    
    /**
     * Interní metoda pro logování
     * 
     * @param string $level   Úroveň logu (debug, info, warning, error, critical)
     * @param string $message Zpráva
     * @param array  $context Kontext
     * @return void
     */
    private function log($level, $message, $context = []) {
        $logger = $this->get_logger();
        
        if (!$logger) {
            // Fallback na error_log pokud WC logger není dostupný
            error_log(sprintf('[iÚčto Woo] [%s] %s', strtoupper($level), $message));
            if (!empty($context)) {
                error_log('[iÚčto Woo] Context: ' . wp_json_encode($context));
            }
            return;
        }
        
        // Přidání kontextu do zprávy pokud existuje
        $formatted_message = $message;
        if (!empty($context)) {
            $formatted_message .= ' | Kontext: ' . wp_json_encode($context, JSON_UNESCAPED_UNICODE);
        }
        
        // Logování přes WC Logger
        $logger->log($level, $formatted_message, [
            'source' => $this->source,
        ]);
    }
    
    /**
     * Loguje API request
     * 
     * Speciální metoda pro logování API požadavků do iÚčto.
     * 
     * @param string $method   HTTP metoda (GET, POST, PUT, DELETE)
     * @param string $endpoint API endpoint
     * @param array  $data     Data requestu (budou skryty citlivé informace)
     * @return void
     */
    public function log_api_request($method, $endpoint, $data = []) {
        // Odstranění citlivých dat z logu
        $safe_data = $this->sanitize_log_data($data);
        
        $this->debug(sprintf(
            'API Request: %s %s',
            $method,
            $endpoint
        ), [
            'method' => $method,
            'endpoint' => $endpoint,
            'data' => $safe_data,
        ]);
    }
    
    /**
     * Loguje API odpověď
     * 
     * @param int    $status_code HTTP status kód
     * @param mixed  $response    Odpověď z API
     * @param string $endpoint    API endpoint
     * @return void
     */
    public function log_api_response($status_code, $response, $endpoint = '') {
        $level = $status_code >= 200 && $status_code < 300 ? 'debug' : 'error';
        
        $this->log($level, sprintf(
            'API Response [%d]: %s',
            $status_code,
            $endpoint
        ), [
            'status_code' => $status_code,
            'endpoint' => $endpoint,
            'response' => is_array($response) ? $response : substr($response, 0, 500),
        ]);
    }
    
    /**
     * Odstraní citlivá data z pole pro logování
     * 
     * Skryje API klíče, hesla, emaily atd.
     * 
     * @param array $data Data k sanitizaci
     * @return array Sanitizovaná data
     */
    private function sanitize_log_data($data) {
        if (!is_array($data)) {
            return $data;
        }
        
        $sensitive_keys = [
            'api_key',
            'password',
            'token',
            'secret',
        ];
        
        $sanitized = $data;
        
        foreach ($sanitized as $key => $value) {
            // Skrytí citlivých klíčů
            if (in_array(strtolower($key), $sensitive_keys)) {
                $sanitized[$key] = '***HIDDEN***';
            }
            
            // Rekurzivně pro vnořená pole
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitize_log_data($value);
            }
        }
        
        return $sanitized;
    }
}