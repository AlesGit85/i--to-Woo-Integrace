<?php
/**
 * Validator třída
 * 
 * Poskytuje validační metody pro různé typy dat.
 * Centralizovaná validace pro celý plugin.
 *
 * @package IUcto_Woo_Integration
 * @since 2.0.0
 */

// Prevence přímého přístupu
if (!defined('ABSPATH')) {
    exit;
}

class IUcto_Woo_Validator {
    
    /**
     * Zvaliduje IČO (české identifikační číslo)
     * 
     * IČO musí mít přesně 8 číslic.
     * 
     * @param string $ico IČO k validaci
     * @return bool True pokud je validní
     */
    public static function validate_ico($ico) {
        if (empty($ico)) {
            return false;
        }
        
        // Musí obsahovat přesně 8 číslic
        return (bool) preg_match('/^\d{8}$/', $ico);
    }
    
    /**
     * Zvaliduje DIČ (daňové identifikační číslo)
     * 
     * DIČ může být ve formátu:
     * - CZ12345678 (s předponou)
     * - 12345678 (bez předpony)
     * 
     * @param string $dic DIČ k validaci
     * @return bool True pokud je validní
     */
    public static function validate_dic($dic) {
        if (empty($dic)) {
            return true; // DIČ není povinné
        }
        
        // S předponou CZ nebo SK
        if (preg_match('/^(CZ|SK)\d{8,10}$/', $dic)) {
            return true;
        }
        
        // Bez předpony - jen čísla
        if (preg_match('/^\d{8,10}$/', $dic)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Zvaliduje email
     * 
     * @param string $email Email k validaci
     * @return bool True pokud je validní
     */
    public static function validate_email($email) {
        if (empty($email)) {
            return false;
        }
        
        return is_email($email);
    }
    
    /**
     * Zvaliduje API klíč
     * 
     * API klíč by měl být hexadecimální string min. 20 znaků.
     * 
     * @param string $api_key API klíč k validaci
     * @return bool True pokud je validní
     */
    public static function validate_api_key($api_key) {
        if (empty($api_key)) {
            return false;
        }
        
        // Minimálně 20 znaků
        if (strlen($api_key) < 20) {
            return false;
        }
        
        // Pouze alfanumerické znaky (hex)
        return (bool) preg_match('/^[a-zA-Z0-9]+$/', $api_key);
    }
    
    /**
     * Zvaliduje PSČ
     * 
     * @param string $postal_code PSČ k validaci
     * @return bool True pokud je validní
     */
    public static function validate_postal_code($postal_code) {
        if (empty($postal_code)) {
            return false;
        }
        
        // České PSČ: 5 číslic nebo formát XXX XX
        return (bool) preg_match('/^(\d{5}|\d{3}\s?\d{2})$/', $postal_code);
    }
    
    /**
     * Zvaliduje telefonní číslo
     * 
     * @param string $phone Telefon k validaci
     * @return bool True pokud je validní
     */
    public static function validate_phone($phone) {
        if (empty($phone)) {
            return true; // Telefon není povinný
        }
        
        // Základní validace - min 9 číslic (může obsahovat mezery, +, -)
        $cleaned = preg_replace('/[^0-9]/', '', $phone);
        return strlen($cleaned) >= 9;
    }
    
    /**
     * Zvaliduje procentuální sazbu (0-100)
     * 
     * @param mixed $rate Sazba k validaci
     * @return bool True pokud je validní
     */
    public static function validate_percentage($rate) {
        $rate = (float) $rate;
        return $rate >= 0 && $rate <= 100;
    }
    
    /**
     * Zvaliduje počet dní (1-365)
     * 
     * @param mixed $days Počet dní k validaci
     * @return bool True pokud je validní
     */
    public static function validate_days($days) {
        $days = (int) $days;
        return $days >= 1 && $days <= 365;
    }
    
    /**
     * Sanitizuje IČO
     * 
     * Odstraní mezery a vše kromě číslic.
     * 
     * @param string $ico IČO k sanitizaci
     * @return string Sanitizované IČO
     */
    public static function sanitize_ico($ico) {
        return preg_replace('/[^0-9]/', '', $ico);
    }
    
    /**
     * Sanitizuje DIČ
     * 
     * Převede na uppercase a odstraní mezery.
     * 
     * @param string $dic DIČ k sanitizaci
     * @return string Sanitizované DIČ
     */
    public static function sanitize_dic($dic) {
        $dic = strtoupper($dic);
        $dic = str_replace(' ', '', $dic);
        return $dic;
    }
    
    /**
     * Sanitizuje pole ID kategorií
     * 
     * @param mixed $categories Vstup
     * @return array Pole validních int ID
     */
    public static function sanitize_category_ids($categories) {
        if (!is_array($categories)) {
            return [];
        }
        
        return array_map('absint', array_filter($categories));
    }
}