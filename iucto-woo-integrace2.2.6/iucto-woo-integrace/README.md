# iÚčto Woo Integrace - Verze 2.0

> **Kompletně přepsaná verze** s čistou architekturou, HPOS kompatibilitou a moderními best practices.

[![WordPress](https://img.shields.io/badge/WordPress-5.8+-blue.svg)](https://wordpress.org/)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-5.0+-purple.svg)](https://woocommerce.com/)
[![PHP](https://img.shields.io/badge/PHP-7.4+-777BB4.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-Custom-green.svg)]()

Automatická integrace iÚčto fakturace pro WooCommerce. Plugin automaticky vytváří a odesílá faktury při změnách statusů objednávek.

---

## 📋 Obsah

- [Co je nové ve verzi 2.0](#-co-je-nové-ve-verzi-20)
- [Požadavky](#-požadavky)
- [Instalace](#-instalace)
- [Konfigurace](#-konfigurace)
- [Jak plugin funguje](#-jak-plugin-funguje)
- [Architektura](#-architektura)
- [Pro vývojáře](#-pro-vývojáře)
- [Řešení problémů](#-řešení-problémů)
- [Changelog](#-changelog)

---

## ✨ Co je nové ve verzi 2.0

### 🏗️ Nová architektura
- ✅ **Rozděleno do tříd** podle Single Responsibility Principle
- ✅ **Dependency Injection** - jasné závislosti mezi komponentami
- ✅ **Autoloader** - automatické načítání tříd
- ✅ **HPOS kompatibilita** - plná podpora High-Performance Order Storage

### 🔒 Bezpečnost
- ✅ **Sanitizace všech vstupů** pomocí WordPress funkcí
- ✅ **Escape všech výstupů** - ochrana proti XSS
- ✅ **Validace dat** - centralizovaná validační třída
- ✅ **WP Nonce** pro formuláře

### 📊 Logování
- ✅ **WooCommerce Logger** místo error_log
- ✅ **Úrovně logování** (debug, info, warning, error, critical)
- ✅ **Strukturované logy** s kontextem
- ✅ **Ochrana citlivých dat** v lozích

### 🎯 Lepší error handling
- ✅ **WP_Error** konzistentně používán
- ✅ **Validace na více úrovních**
- ✅ **User-friendly error messages** v admin poznámkách

---

## 💻 Požadavky

- **WordPress:** 5.8 nebo vyšší
- **WooCommerce:** 5.0 nebo vyšší
- **PHP:** 7.4 nebo vyšší
- **iÚčto účet:** Aktivní účet s API přístupem

---

## 🚀 Instalace

### Krok 1: Nahrajte plugin

1. Stáhněte složku `iucto-woo-integrace`
2. Nahrajte ji do `/wp-content/plugins/`
3. Nebo nahrajte jako ZIP přes **Pluginy → Přidat nový → Nahrát plugin**

### Krok 2: Aktivujte plugin

1. Jděte do **Pluginy** v admin sekci
2. Najděte **iÚčto Woo Integrace**
3. Klikněte na **Aktivovat**

### Krok 3: Získejte API klíč z iÚčto

1. Přihlaste se na [app.iucto.cz](https://app.iucto.cz)
2. Jděte do **Nastavení → Integrace → API**
3. Vygenerujte nový API klíč
4. Zkopírujte API klíč (uložte si ho někam bezpečně)

---

## ⚙️ Konfigurace

### Základní nastavení (povinné)

Jděte na **WooCommerce → iÚčto Fakturace** a vyplňte:

1. **🔑 API Nastavení**
   - API Klíč (z iÚčto)

2. **🏢 Firemní údaje**
   - Název firmy
   - Adresa firmy
   - IČO (8 číslic)
   - DIČ (pokud jste plátce DPH)

3. **💰 DPH Nastavení**
   - ☑️ Jsem plátce DPH (zaškrtnout pokud platí)
   - Sazba DPH v % (obvykle 21%)

4. **📄 Fakturační nastavení**
   - Splatnost faktury ve dnech (výchozí: 14)
   - ☐ Automaticky odesílat faktury emailem (doporučeno: VYPNUTO)
     - **Výchozí:** Vypnuto - majitel si faktury odesílá ručně z iÚčto
     - Pokud zapnuto: Plugin automaticky odešle email přes iÚčto API

### Pokročilá nastavení (volitelné)

5. **🔧 Pokročilá nastavení iÚčto**
   - Fallback Customer ID (volitelné)
   - Bankovní účet ID (výchozí: 58226)
   - Chart Account ID (výchozí: 604)
   - Account Entry Type ID (výchozí: 532)

6. **📦 Kategorie předobjednávek**
   - Vyberte kategorie, které jsou předobjednávky
   - Pro tyto se vytvoří proforma + konečná faktura

### Test připojení

Po vyplnění nastavení:

1. Klikněte na **"Otestovat připojení k iÚčto API"**
2. Měli byste vidět **✅ Připojení úspěšné**

---

## 🎯 Jak plugin funguje

### Flow pro PŘEDOBJEDNÁVKY (odražedla)
```
┌─────────────────────────────────────────────────────┐
│  Objednávka zaplacena (status "Zaplaceno")          │
└──────────────────┬──────────────────────────────────┘
                   │
                   ↓
         ┌─────────────────────┐
         │ Vytvoří PROFORMA    │ 📄 Zálohová faktura
         │ fakturu v iÚčto     │
         └─────────┬───────────┘
                   │
                   ↓
         ┌─────────────────────┐
         │ Faktura vytvořena   │ ✅ Připravena v iÚčto
         │ (bez odeslání)      │    (majitel odešle ručně)
         └─────────────────────┘
                   
                   │
                   ↓
┌─────────────────────────────────────────────────────┐
│  Objednávka dokončena (status "Completed")          │
└──────────────────┬──────────────────────────────────┘
                   │
                   ↓
         ┌─────────────────────┐
         │ Vytvoří KONEČNOU    │ ✅ Daňový doklad
         │ fakturu v iÚčto     │    (navázaná na proforma)
         └─────────┬───────────┘
                   │
                   ↓
         ┌─────────────────────┐
         │ Faktura vytvořena   │ ✅ Připravena v iÚčto
         │ (bez odeslání)      │    (majitel odešle ručně)
         └─────────────────────┘
```

### Flow pro BĚŽNÉ PRODUKTY
```
┌─────────────────────────────────────────────────────┐
│  Objednávka zaplacena / dokončena                   │
└──────────────────┬──────────────────────────────────┘
                   │
                   ↓
         ┌─────────────────────┐
         │ Vytvoří KONEČNOU    │ ✅ Rovnou daňový doklad
         │ fakturu v iÚčto     │
         └─────────┬───────────┘
                   │
                   ↓
         ┌─────────────────────┐
         │ Odešle email        │ 📧 Automaticky
         │ zákazníkovi         │
         └─────────────────────┘
```

### Automatické procesy

Plugin automaticky:

- ✅ **Vytváří faktury** v iÚčto (proforma i konečné)
- ✅ **Vytváří zákazníky** v iÚčto (nebo najde existující podle emailu)
- ✅ **Generuje variabilní symbol** z čísla objednávky
- ✅ **Počítá DPH** podle nastavení
- ✅ **Mapuje platební metody** (dobírka, převod, karta)
- ✅ **Loguje vše** do WooCommerce logů
- ✅ **Přidává poznámky** k objednávkám
- ⚙️ **Odesílání emailů** - volitelné (výchozí: vypnuto)
  - Faktury se vytvoří v iÚčto
  - Majitel si je pak ručně odešle z iÚčto aplikace
  - Lze zapnout v nastavení pro automatické odesílání

---

## 🏗️ Architektura

### Struktura projektu
```
iucto-woo-integrace/
│
├── iucto-woo-integrace-plugin.php    # Hlavní soubor + autoloader
│
├── includes/                          # Všechny třídy
│   ├── class-plugin.php               # Hlavní singleton (řídí vše)
│   ├── class-logger.php               # WC Logger wrapper
│   ├── class-settings.php             # Správa nastavení
│   ├── class-api-client.php           # iÚčto API komunikace
│   ├── class-customer-manager.php     # Správa zákazníků
│   ├── class-invoice-manager.php      # Správa faktur (hlavní logika)
│   ├── class-admin-ui.php             # Admin UI (sloupce, meta boxy)
│   └── class-validator.php            # Validace dat
│
├── templates/                         # Admin templates
│   └── settings-page.php              # Settings stránka
│
├── assets/                            # CSS a JS
│   ├── css/
│   │   └── admin.css
│   └── js/
│       └── admin.js
│
└── languages/                         # Překlady (připraveno)
```

### Třídy a jejich zodpovědnosti

| Třída | Zodpovědnost |
|-------|--------------|
| `IUcto_Woo_Plugin` | Hlavní třída - inicializace, propojení komponent |
| `IUcto_Woo_Logger` | Logování (debug, info, warning, error, critical) |
| `IUcto_Woo_Settings` | Správa nastavení, gettery, validace |
| `IUcto_Woo_API_Client` | HTTP komunikace s iÚčto API |
| `IUcto_Woo_Customer_Manager` | Vytváření/hledání zákazníků v iÚčto |
| `IUcto_Woo_Invoice_Manager` | Vytváření faktur, zpracování objednávek |
| `IUcto_Woo_Admin_UI` | Sloupce v seznamu, meta boxy v detailu |
| `IUcto_Woo_Validator` | Validační metody (IČO, DIČ, email...) |

### Naming Conventions

**Třídy:**
- `IUcto_Woo_*` prefix pro všechny třídy
- PascalCase s podtržítky

**Metody:**
- `create_*` - vytváření (create_proforma_invoice)
- `get_*` - získání dat (get_customer_id)
- `is_*` / `has_*` - boolean (is_preorder, has_invoice)
- `validate_*` - validace (validate_settings)
- `process_*` - zpracování (process_paid_order)

**Komentáře:**
- PHPDoc + inline = **česky** (business logika)
- Názvy metod/proměnných = **anglicky** (standard)

---

## 👨‍💻 Pro vývojáře

### WordPress Hooks

Plugin poskytuje vlastní akce pro rozšíření:
```php
// Po vytvoření proforma faktury
add_action('iucto_woo_proforma_invoice_created', function($invoice_id, $order) {
    // Váš kód
}, 10, 2);

// Po vytvoření konečné faktury
add_action('iucto_woo_tax_invoice_created', function($invoice_id, $order, $proforma_id) {
    // Váš kód
}, 10, 3);
```

### Filtry
```php
// Rozšíření mapování platebních metod
add_filter('iucto_woo_payment_method_map', function($map) {
    $map['my_custom_gateway'] = 'creditcard';
    return $map;
});
```

### Přístup ke komponentám
```php
// Získání instance pluginu
$plugin = IUcto_Woo_Plugin::instance();

// Přístup ke komponentám
$settings = $plugin->get_settings();
$api = $plugin->get_api_client();
$invoices = $plugin->get_invoice_manager();
$logger = $plugin->get_logger();

// Příklad použití
if ($settings->is_configured()) {
    $api_key = $settings->get_api_key();
    // ...
}
```

### Logování
```php
$logger = IUcto_Woo_Plugin::instance()->get_logger();

$logger->debug('Debug zpráva', ['data' => 'hodnota']);
$logger->info('Info zpráva');
$logger->warning('Varování');
$logger->error('Chyba', ['error' => $error]);
$logger->critical('Kritická chyba');
```

Logy najdeš v **WooCommerce → Status → Logy** (source: `iucto-woo-integration`)

### Validace
```php
// Validace IČO
if (IUcto_Woo_Validator::validate_ico($ico)) {
    // IČO je validní
}

// Sanitizace IČO
$clean_ico = IUcto_Woo_Validator::sanitize_ico($ico);

// Další validace
IUcto_Woo_Validator::validate_dic($dic);
IUcto_Woo_Validator::validate_email($email);
IUcto_Woo_Validator::validate_api_key($key);
```

---

## 🐛 Řešení problémů

### Faktury se nevytváří

**Kontrola:**
1. ✅ Je plugin aktivní?
2. ✅ Je vyplněný API klíč?
3. ✅ Jsou vyplněny všechny povinné údaje firmy?
4. ✅ Zkontroluj poznámky u objednávky (tam jsou chyby)
5. ✅ Zkontroluj logy: **WooCommerce → Status → Logy**

**Řešení:**
```
WooCommerce → iÚčto Fakturace
→ Vyplň všechna povinná pole
→ Klikni "Otestovat připojení"
```

### Emaily se neodesílají

**Kontrola:**
1. ✅ Je zapnuto "Automatické odesílání emailů"?
2. ✅ WordPress umí odesílat emaily? (test: poslat test email)
3. ✅ Zkontroluj spam složku zákazníka

**Řešení:**
- Nainstaluj plugin **WP Mail SMTP** pro spolehlivé odesílání
- Zkontroluj poznámky objednávky - tam vidíš zda email byl odeslán

### Chyba "API klíč není platný"

**Řešení:**
1. Vygeneruj nový API klíč v iÚčto
2. Zkopíruj ho **bez mezer** na začátku/konci
3. Ulož nastavení
4. Klikni "Otestovat připojení"

### Faktury mají špatné DPH

**Kontrola:**
1. ✅ Je správně zaškrtnuto "Jsem plátce DPH"?
2. ✅ Je správná sazba DPH (21%)?
3. ✅ Produkty mají správně nastavené "Zdanitelné"?

**Řešení:**
```
WooCommerce → iÚčto Fakturace
→ Zkontroluj DPH nastavení
→ Ulož změny
```

### Duplicitní faktury

Plugin má **ochranu proti duplicitám**:
- Kontroluje `_iucto_proforma_invoice_id` před vytvořením proforma
- Kontroluje `_iucto_tax_invoice_id` před vytvořením konečné

Pokud vidíš duplicity:
1. Zkontroluj logy
2. Zkontroluj meta data objednávky
3. Kontaktuj podporu

### Debug mode

Zapni debug pro detailní logy:
```php
// V wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Pak najdeš detailní logy v:
- `/wp-content/debug.log`
- **WooCommerce → Status → Logy** (zdroj: iucto-woo-integration)

---

## 📊 Kde najdu faktury?

### 1. V seznamu objednávek
- **Sloupec "Faktura iÚčto"**
- 📄 = Proforma faktura
- ✅ = Konečná faktura

### 2. V detailu objednávky
- **Pravý panel → Meta box "iÚčto Faktury"**
- Zobrazuje ID faktur
- Tlačítko "Otevřít iÚčto"

### 3. V poznámkách objednávky
- ✅ Proforma faktura vytvořena (ID: 123)
- 📧 Proforma odeslána na email
- ✅ Konečná faktura vytvořena (ID: 456)
- 📧 Faktura odeslána na email

### 4. Přímo v iÚčto
- [app.iucto.cz](https://app.iucto.cz)
- **Faktury → Vydané**

---

## 🔄 Changelog

### Verze 2.2.6 (30. ledna 2026) - PRODUCTION READY

**🎉 Produkční verze - Proforma faktury fungují!**

**Hotovo:**
- ✅ **Proforma faktury** - plně funkční
- ✅ Správné API parametry (chart_account_id, accountentrytype_id, vat_chart_id)
- ✅ Automatické vytváření zákazníků
- ✅ Generování variabilních symbolů
- ✅ Počítání DPH
- 🧹 Odstraněny debug logy
- 📦 Čistý kód pro produkci

**Známé problémy:**
- ⚠️ **Konečné faktury (TAX)** - vyžadují datum zdanitelného plnění
  - Chyba: "Datum zdanitelného plnění je nutné vyplnit..."
  - Řešení: Bude doplněno v příští verzi (v2.3.0)
  - Workaround: Vytvářejte konečné faktury ručně v iÚčto

**Technické změny:**
- Oprava názvu parametru: `vat_chart_id` (místo `vat_account_id`)
- Typ faktury: `'advance'` pro proforma (místo `'proforma'`)
- Všechny faktury mají stejné parametry (proforma i tax)

---

### Verze 2.0.0 (Leden 2025)

**🎉 Kompletní přepsání pluginu**

**Přidáno:**
- ✨ Nová modulární architektura (8 specializovaných tříd)
- ✨ Dependency Injection pattern
- ✨ Autoloader pro automatické načítání tříd
- ✨ Plná HPOS (High-Performance Order Storage) kompatibilita
- ✨ WooCommerce Logger integrace
- ✨ Strukturované logování s úrovněmi (debug, info, warning, error)
- ✨ Centralizovaná validace (Validator třída)
- ✨ Admin UI s meta boxy a sloupci
- ✨ Template systém pro admin stránky
- ✨ CSS a JS pro admin rozhraní
- ✨ Lepší naming conventions (jasné názvy metod)
- ✨ PHPDoc dokumentace všech tříd a metod
- ✨ WordPress Hooks pro rozšíření (actions, filters)
- ✨ Ochrana proti duplicitním fakturám
- ✨ Sanitizace všech vstupů, escape všech výstupů

**Změněno:**
- 🔄 `create_advance_invoice()` → `create_proforma_invoice()`
- 🔄 `create_final_invoice()` → `create_tax_invoice()`
- 🔄 `handle_order_paid()` → `process_paid_order()`
- 🔄 `is_preorder_order()` → `is_preorder()`
- 🔄 Používání `$order->get_meta()` místo `get_post_meta()`
- 🔄 Používání `$order->update_meta_data()` místo `update_post_meta()`

**Odstraněno:**
- ❌ Velký monolitický soubor (700+ řádků)
- ❌ Hard-coded závislosti
- ❌ Nekonzistentní error handling
- ❌ Používání `error_log()` přímo
- ❌ Post meta funkce pro HPOS nekompatibilitu

**Opraveno:**
- 🐛 Chybějící `handle_payment_complete()` metoda
- 🐛 Duplicitní vytváření faktur
- 🐛 Nekonzistentní detekce předobjednávek
- 🐛 Chybějící validace customer_id
- 🐛 API klíč v plaintextu (stále platí, ale lepší handling)

**Bezpečnost:**
- 🔒 Všechny vstupy sanitizovány
- 🔒 Všechny výstupy escapovány
- 🔒 WP Nonce připraveno pro Ajax
- 🔒 Citlivá data skryta v lozích

---

### Verze 1.x (Starší verze)

_Pro referenci - tato verze již není podporována._

---

## 📞 Podpora

### Kontakt
- **Email:** [váš-email@example.com]
- **Web:** [https://allimedia.cz](https://allimedia.cz)

### Dokumentace
- **iÚčto API:** [https://iucto.docs.apiary.io/](https://iucto.docs.apiary.io/)
- **iÚčto podpora:** [https://podpora.iucto.cz](https://podpora.iucto.cz)

### Hlášení chyb
Našli jste chybu? Kontaktujte nás s:
1. Popisem problému
2. Kroky k reprodukci
3. Snímky obrazovky (pokud jsou relevantní)
4. Export WooCommerce logů

---

## 📄 Licence

Tento plugin je poskytován "jak je" bez jakýchkoliv záruk.

© 2025 Allimedia.cz - Všechna práva vyhrazena.

---

## 🙏 Poděkování

Děkujeme všem, kteří se podíleli na vývoji a testování pluginu!

**Speciální poděkování:**
- Týmu iÚčto za skvělé API
- Komunitě WooCommerce
- Všem beta testerům

---

**Verze:** 2.0.0  
**Poslední aktualizace:** Leden 2026  
**Autor:** Allimedia.cz