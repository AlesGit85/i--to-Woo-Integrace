<?php
/**
 * Template pro admin stránku s nastavením - s taby
 * 
 * @package IUcto_Woo_Integration
 * @since 2.0.0
 */

// Prevence přímého přístupu
if (!defined('ABSPATH')) {
    exit;
}

// Proměnné dostupné v tomto template:
// - $this = instance IUcto_Woo_Settings
// - $test_result = výsledek testu připojení (pokud byl proveden)
?>

<div class="wrap iucto-settings-wrap">
    <h1>
        <span class="dashicons dashicons-admin-plugins" style="font-size: 32px; width: 32px; height: 32px;"></span>
        iÚčto Woo Integrace
    </h1>
    
    <p class="description">Automatická integrace iÚčto fakturace pro WooCommerce</p>
    
    <?php
    // Zobrazení chyb/úspěchů
    settings_errors();
    
    // Výsledek testu připojení
    if (isset($test_result)):
        $class = $test_result['success'] ? 'notice-success' : 'notice-error';
        ?>
        <div class="notice <?php echo esc_attr($class); ?> is-dismissible">
            <p><?php echo esc_html($test_result['message']); ?></p>
        </div>
    <?php endif; ?>
    
    <?php
    // Varování pokud plugin není nakonfigurován
    if (!$this->is_configured()):
        $missing = $this->get_missing_settings();
        ?>
        <div class="notice notice-warning">
            <p><strong>⚠️ Plugin není kompletně nakonfigurován</strong></p>
            <p>Chybějící nastavení: <?php echo esc_html(implode(', ', $missing)); ?></p>
        </div>
    <?php endif; ?>
    
    <!-- Tab navigace -->
    <nav class="nav-tab-wrapper iucto-nav-tabs">
        <a href="#api" class="nav-tab nav-tab-active" data-tab="api">
            <span class="dashicons dashicons-admin-network"></span> API Nastavení
        </a>
        <a href="#company" class="nav-tab" data-tab="company">
            <span class="dashicons dashicons-building"></span> Firemní údaje
        </a>
        <a href="#vat" class="nav-tab" data-tab="vat">
            <span class="dashicons dashicons-money-alt"></span> DPH
        </a>
        <a href="#invoicing" class="nav-tab" data-tab="invoicing">
            <span class="dashicons dashicons-media-document"></span> Fakturace
        </a>
        <a href="#advanced" class="nav-tab" data-tab="advanced">
            <span class="dashicons dashicons-admin-settings"></span> Pokročilé
        </a>
        <a href="#help" class="nav-tab" data-tab="help">
            <span class="dashicons dashicons-info"></span> Nápověda
        </a>
    </nav>
    
    <form method="post" action="options.php" class="iucto-settings-form">
        <?php settings_fields('iucto_settings_group'); ?>
        
        <!-- Tab 1: API Nastavení -->
        <div class="iucto-tab-content" id="tab-api">
            <div class="iucto-card">
                <h2>🔑 API Nastavení</h2>
                <p class="description">Připojení k iÚčto API pomocí vašeho API klíče.</p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="iucto_api_key">API Klíč *</label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="iucto_api_key" 
                                   name="iucto_api_key"
                                   value="<?php echo esc_attr($this->get_api_key()); ?>"
                                   class="large-text" 
                                   placeholder="Vložte váš API klíč z iÚčto"
                                   required>
                            <p class="description">
                                <a href="https://app.iucto.cz" target="_blank" class="button button-small">
                                    <span class="dashicons dashicons-external"></span> Získat API klíč z iÚčto
                                </a>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <div class="iucto-test-section">
                    <h3>Test připojení</h3>
                    <p>Ověřte, že API klíč funguje a plugin se může připojit k iÚčto API.</p>
                    <p>
                        <a href="<?php echo wp_nonce_url(
                            admin_url('admin.php?page=iucto-settings&test_connection=1'),
                            'iucto_test_connection',
                            'nonce'
                        ); ?>" class="button button-secondary button-large">
                            <span class="dashicons dashicons-yes-alt"></span> Otestovat připojení k iÚčto API
                        </a>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Tab 2: Firemní údaje -->
        <div class="iucto-tab-content" id="tab-company" style="display: none;">
            <div class="iucto-card">
                <h2>🏢 Firemní údaje</h2>
                <p class="description">Základní informace o vaší firmě pro faktury.</p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="iucto_company_name">Název firmy *</label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="iucto_company_name" 
                                   name="iucto_company_name"
                                   value="<?php echo esc_attr($this->get_company_name()); ?>"
                                   class="regular-text" 
                                   required>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="iucto_company_address">Adresa firmy *</label>
                        </th>
                        <td>
                            <textarea id="iucto_company_address" 
                                      name="iucto_company_address"
                                      rows="3" 
                                      class="large-text"
                                      required><?php echo esc_textarea($this->get_company_address()); ?></textarea>
                            <p class="description">Ulice, PSČ, Město</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="iucto_company_ico">IČO *</label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="iucto_company_ico" 
                                   name="iucto_company_ico"
                                   value="<?php echo esc_attr($this->get_company_ico()); ?>"
                                   class="regular-text"
                                   pattern="\d{8}"
                                   maxlength="8"
                                   required>
                            <p class="description">Přesně 8 číslic</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="iucto_company_dic">DIČ</label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="iucto_company_dic" 
                                   name="iucto_company_dic"
                                   value="<?php echo esc_attr($this->get_company_dic()); ?>"
                                   class="regular-text">
                            <p class="description">Volitelné - vyplňte pokud jste plátce DPH</p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <!-- Tab 3: DPH -->
        <div class="iucto-tab-content" id="tab-vat" style="display: none;">
            <div class="iucto-card">
                <h2>💰 Nastavení DPH</h2>
                <p class="description">Konfigurace daně z přidané hodnoty pro faktury.</p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="iucto_vat_payer">Plátce DPH</label>
                        </th>
                        <td>
                            <label class="iucto-toggle">
                                <input type="checkbox" 
                                       id="iucto_vat_payer" 
                                       name="iucto_vat_payer" 
                                       value="1"
                                       <?php checked($this->is_vat_payer(), 1); ?>>
                                <span class="iucto-toggle-slider"></span>
                                <span class="iucto-toggle-label">Ano, jsem plátce DPH</span>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="iucto_vat_rate">Sazba DPH (%)</label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="iucto_vat_rate" 
                                   name="iucto_vat_rate"
                                   value="<?php echo esc_attr($this->get_vat_rate()); ?>"
                                   min="0" 
                                   max="100"
                                   class="small-text">
                            <span class="description">%</span>
                            <p class="description">Standardní sazba DPH (obvykle 21%)</p>
                        </td>
                    </tr>
                </table>
                
                <div class="iucto-info-box">
                    <span class="dashicons dashicons-info"></span>
                    <div>
                        <strong>Informace o DPH:</strong>
                        <p>Pokud jste plátce DPH, vyplňte DIČ na záložce "Firemní údaje" a nastavte správnou sazbu DPH.</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tab 4: Fakturace -->
        <div class="iucto-tab-content" id="tab-invoicing" style="display: none;">
            <div class="iucto-card">
                <h2>📄 Fakturační nastavení</h2>
                <p class="description">Nastavení týkající se vytváření a odesílání faktur.</p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="iucto_invoice_maturity">Splatnost faktury (dny)</label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="iucto_invoice_maturity" 
                                   name="iucto_invoice_maturity"
                                   value="<?php echo esc_attr($this->get_invoice_maturity()); ?>"
                                   min="1" 
                                   max="365"
                                   class="small-text">
                            <span class="description">dní</span>
                            <p class="description">Počet dní do splatnosti faktury</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="iucto_auto_send_email">Automatické odesílání</label>
                        </th>
                        <td>
                            <label class="iucto-toggle">
                                <input type="checkbox" 
                                       id="iucto_auto_send_email" 
                                       name="iucto_auto_send_email" 
                                       value="1"
                                       <?php checked($this->is_auto_send_enabled(), 1); ?>>
                                <span class="iucto-toggle-slider"></span>
                                <span class="iucto-toggle-label">Automaticky odesílat faktury emailem zákazníkům</span>
                            </label>
                            <p class="description" style="color: #d63638; margin-top: 10px;">
                                <span class="dashicons dashicons-warning"></span>
                                <strong>Doporučeno nechat VYPNUTO.</strong> Faktury se vytvoří v iÚčto a majitel je pak ručně odešle přes iÚčto aplikaci.
                            </p>
                        </td>
                    </tr>
                </table>
                
                <div class="iucto-card-section">
                    <h3>📦 Kategorie předobjednávek</h3>
                    <p class="description">
                        Vyberte kategorie produktů, které jsou <strong>předobjednávky</strong>. 
                        Pro tyto produkty se vytvoří nejdřív proforma faktura (při zaplacení) 
                        a pak konečná faktura (při dokončení).
                    </p>
                    
                    <div class="iucto-placeholder-box">
                        <span class="dashicons dashicons-category"></span>
                        <p><em>Výběr kategorií bude implementován v další verzi.</em></p>
                        <p class="description">
                            Pokud používáte WooCommerce Pre-Orders plugin, 
                            předobjednávky budou detekovány automaticky.
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tab 5: Pokročilé -->
        <div class="iucto-tab-content" id="tab-advanced" style="display: none;">
            <div class="iucto-card">
                <h2>🔧 Pokročilá nastavení iÚčto</h2>
                <p class="description">Pokročilé parametry pro komunikaci s iÚčto API. Měňte pouze pokud víte, co děláte.</p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="iucto_customer_id">Fallback Customer ID</label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="iucto_customer_id" 
                                   name="iucto_customer_id"
                                   value="<?php echo esc_attr($this->get_customer_id()); ?>"
                                   class="regular-text">
                            <p class="description">
                                Volitelné - ID existujícího zákazníka v iÚčto jako fallback.
                                Plugin obvykle vytvoří zákazníka automaticky.
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="iucto_bank_account_id">Bankovní účet (ID)</label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="iucto_bank_account_id" 
                                   name="iucto_bank_account_id"
                                   value="<?php echo esc_attr($this->get_bank_account_id()); ?>"
                                   class="regular-text">
                            <p class="description">ID bankovního účtu v iÚčto (výchozí: 58226)</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="iucto_chart_account_id">Chart Account ID</label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="iucto_chart_account_id" 
                                   name="iucto_chart_account_id"
                                   value="<?php echo esc_attr($this->get_chart_account_id()); ?>"
                                   class="regular-text">
                            <p class="description">ID účtové osnovy (výchozí: 604)</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="iucto_accountentrytype_id">Account Entry Type ID</label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="iucto_accountentrytype_id" 
                                   name="iucto_accountentrytype_id"
                                   value="<?php echo esc_attr($this->get_accountentrytype_id()); ?>"
                                   class="regular-text">
                            <p class="description">ID typu účetního zápisu (výchozí: 532 pro tržby)</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="iucto_vat_account_id">VAT Account ID</label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="iucto_vat_account_id" 
                                   name="iucto_vat_account_id"
                                   value="<?php echo esc_attr($this->get_vat_account_id()); ?>"
                                   class="regular-text">
                            <p class="description">ID účtu DPH (výchozí: 343 pro DPH na výstupu)</p>
                        </td>
                    </tr>
                </table>
                
                <div class="iucto-warning-box">
                    <span class="dashicons dashicons-warning"></span>
                    <div>
                        <strong>Upozornění:</strong>
                        <p>Změna těchto hodnot může způsobit nefunkčnost pluginu. Měňte pouze pokud víte, co děláte, nebo máte pokyny od podpory iÚčto.</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tab 6: Nápověda -->
        <div class="iucto-tab-content" id="tab-help" style="display: none;">
            <div class="iucto-card">
                <h2>ℹ️ Nápověda</h2>
                
                <div class="iucto-help-section">
                    <h3>🚀 Jak plugin funguje</h3>
                    <ul class="iucto-help-list">
                        <li>
                            <span class="dashicons dashicons-yes-alt"></span>
                            <div>
                                <strong>Předobjednávky:</strong> 
                                Po zaplacení → proforma faktura. Po dokončení → konečná faktura.
                            </div>
                        </li>
                        <li>
                            <span class="dashicons dashicons-yes-alt"></span>
                            <div>
                                <strong>Běžné produkty:</strong> 
                                Po zaplacení nebo dokončení → rovnou konečná faktura.
                            </div>
                        </li>
                        <li>
                            <span class="dashicons dashicons-yes-alt"></span>
                            <div>
                                <strong>Odesílání emailů:</strong> 
                                Plugin faktury vytváří v iÚčto. Majitel si je pak ručně odešle z iÚčto aplikace zákazníkovi.
                            </div>
                        </li>
                        <li>
                            <span class="dashicons dashicons-yes-alt"></span>
                            <div>
                                <strong>HPOS kompatibilita:</strong> 
                                Plugin je plně kompatibilní s WooCommerce HPOS (High-Performance Order Storage).
                            </div>
                        </li>
                    </ul>
                </div>
                
                <div class="iucto-help-section">
                    <h3>📍 Kde najdu faktury?</h3>
                    <ul class="iucto-help-list">
                        <li>
                            <span class="dashicons dashicons-list-view"></span>
                            <div>V seznamu objednávek ve sloupci "Faktura iÚčto"</div>
                        </li>
                        <li>
                            <span class="dashicons dashicons-admin-page"></span>
                            <div>V detailu objednávky v meta boxu "iÚčto Faktury"</div>
                        </li>
                        <li>
                            <span class="dashicons dashicons-admin-comments"></span>
                            <div>V poznámkách objednávky</div>
                        </li>
                        <li>
                            <span class="dashicons dashicons-external"></span>
                            <div>V <a href="https://app.iucto.cz" target="_blank">iÚčto aplikaci</a></div>
                        </li>
                    </ul>
                </div>
                
                <div class="iucto-help-section">
                    <h3>🔗 Užitečné odkazy</h3>
                    <p>
                        <a href="https://app.iucto.cz" target="_blank" class="button">
                            <span class="dashicons dashicons-external"></span> Otevřít iÚčto
                        </a>
                        <a href="https://podpora.iucto.cz" target="_blank" class="button">
                            <span class="dashicons dashicons-sos"></span> Podpora iÚčto
                        </a>
                        <a href="https://iucto.docs.apiary.io/" target="_blank" class="button">
                            <span class="dashicons dashicons-book"></span> API Dokumentace
                        </a>
                    </p>
                </div>
                
                <div class="iucto-version-box">
                    <strong>Verze pluginu:</strong> 2.2.6<br>
                    <strong>Autor:</strong> Allimedia.cz<br>
                    <strong>Licence:</strong> Custom
                </div>
            </div>
        </div>
        
        <!-- Sticky footer s tlačítky -->
        <div class="iucto-sticky-footer">
            <?php submit_button('Uložit změny', 'primary large', 'submit', false); ?>
            <span class="iucto-save-info">
                <span class="dashicons dashicons-info"></span>
                Po uložení změn nezapomeňte otestovat připojení k API.
            </span>
        </div>
    </form>
</div>