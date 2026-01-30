/**
 * iÚčto Woo Integrace - Admin JavaScript s taby
 * 
 * @package IUcto_Woo_Integration
 * @since 2.0.0
 */

(function($) {
    'use strict';
    
    console.log('iÚčto: Admin JS načten');
    
    /**
     * Inicializace po načtení DOM
     */
    $(document).ready(function() {
        console.log('iÚčto: DOM ready');
        
        // Inicializace settings page s taby
        if ($('.iucto-settings-wrap').length) {
            console.log('iÚčto: Settings wrap nalezen, inicializuji...');
            initSettingsPage();
            initTabs();
        } else {
            console.log('iÚčto: Settings wrap nenalezen');
        }
        
        // Inicializace order detail page
        if ($('.iucto-invoice-meta-box').length) {
            console.log('iÚčto: Invoice meta box nalezen');
            initOrderDetailPage();
        }
    });
    
    /**
     * Inicializace tabů
     */
    function initTabs() {
        console.log('iÚčto: Inicializuji taby');
        
        // Kontrola existence tabů
        var tabsCount = $('.iucto-nav-tabs .nav-tab').length;
        var contentsCount = $('.iucto-tab-content').length;
        console.log('iÚčto: Počet tabů: ' + tabsCount + ', Počet contentu: ' + contentsCount);
        
        if (tabsCount === 0) {
            console.error('iÚčto: Nenalezeny žádné taby!');
            return;
        }
        
        // Získání aktivního tabu z URL hash nebo localStorage
        var activeTab = getActiveTab();
        console.log('iÚčto: Aktivní tab: ' + activeTab);
        
        // Aktivace tabu
        switchTab(activeTab);
        
        // Event listener pro klikání na taby
        $('.iucto-nav-tabs .nav-tab').on('click', function(e) {
            e.preventDefault();
            
            var tabId = $(this).data('tab');
            console.log('iÚčto: Kliknuto na tab: ' + tabId);
            
            switchTab(tabId);
            
            // Uložení do localStorage
            try {
                localStorage.setItem('iucto_active_tab', tabId);
                console.log('iÚčto: Tab uložen do localStorage');
            } catch(err) {
                console.warn('iÚčto: Nelze uložit do localStorage', err);
            }
            
            // Aktualizace URL hash
            window.location.hash = tabId;
        });
        
        // Event listener pro změnu URL hash (back/forward browser buttons)
        $(window).on('hashchange', function() {
            var hash = window.location.hash.substring(1);
            console.log('iÚčto: Hash změněn na: ' + hash);
            if (hash) {
                switchTab(hash);
            }
        });
    }
    
    /**
     * Získá aktivní tab z URL nebo localStorage
     */
    function getActiveTab() {
        // Zkusit z URL hash
        var hash = window.location.hash.substring(1);
        if (hash && $('#tab-' + hash).length) {
            console.log('iÚčto: Použit tab z URL: ' + hash);
            return hash;
        }
        
        // Zkusit z localStorage
        try {
            var saved = localStorage.getItem('iucto_active_tab');
            if (saved && $('#tab-' + saved).length) {
                console.log('iÚčto: Použit tab z localStorage: ' + saved);
                return saved;
            }
        } catch(err) {
            console.warn('iÚčto: Nelze číst z localStorage', err);
        }
        
        // Výchozí první tab
        console.log('iÚčto: Použit výchozí tab: api');
        return 'api';
    }
    
    /**
     * Přepne na zadaný tab
     */
    function switchTab(tabId) {
        console.log('iÚčto: Přepínám na tab: ' + tabId);
        
        // Kontrola existence tabu
        if (!$('#tab-' + tabId).length) {
            console.error('iÚčto: Tab content #tab-' + tabId + ' neexistuje!');
            return;
        }
        
        // Odstranit active třídu ze všech tabů
        $('.iucto-nav-tabs .nav-tab').removeClass('nav-tab-active');
        
        // Přidat active třídu na kliknutý tab
        var $activeTab = $('.iucto-nav-tabs .nav-tab[data-tab="' + tabId + '"]');
        if ($activeTab.length) {
            $activeTab.addClass('nav-tab-active');
            console.log('iÚčto: Tab button aktivován');
        } else {
            console.warn('iÚčto: Tab button pro ' + tabId + ' nenalezen');
        }
        
        // Skrýt všechny tab contents
        $('.iucto-tab-content').hide();
        console.log('iÚčto: Všechny obsahy skryty');
        
        // Zobrazit aktivní tab content
        $('#tab-' + tabId).fadeIn(200);
        console.log('iÚčto: Obsah tabu ' + tabId + ' zobrazen');
        
        // Scroll na začátek stránky
        $('html, body').animate({
            scrollTop: $('.iucto-settings-wrap').offset().top - 32
        }, 300);
    }
    
    /**
     * Inicializace settings stránky
     */
    function initSettingsPage() {
        console.log('iÚčto: Settings page initialized');
        
        // Validace IČO při blur
        $('#iucto_company_ico').on('blur', function() {
            validateICO($(this));
        });
        
        // Validace IČO při input (real-time)
        $('#iucto_company_ico').on('input', function() {
            var ico = $(this).val();
            // Povolit pouze čísla
            $(this).val(ico.replace(/[^0-9]/g, ''));
        });
        
        // Validace sazby DPH
        $('#iucto_vat_rate').on('input', function() {
            validatePercentage($(this), 0, 100);
        });
        
        // Validace splatnosti
        $('#iucto_invoice_maturity').on('input', function() {
            validateDays($(this), 1, 365);
        });
        
        // Zvýraznění povinných polí
        highlightRequiredFields();
        
        // Auto-save notification
        setupAutoSaveNotification();
    }
    
    /**
     * Validace IČO
     */
    function validateICO($input) {
        var ico = $input.val();
        
        // Odstranit předchozí error
        $input.removeClass('error');
        $input.next('.ico-error').remove();
        
        if (ico && !ico.match(/^\d{8}$/)) {
            $input.addClass('error');
            $input.css('border-color', '#dc3232');
            
            if (!$input.next('.ico-error').length) {
                $input.after('<p class="ico-error" style="color: #dc3232; margin: 5px 0 0 0;">⚠️ IČO musí obsahovat přesně 8 číslic</p>');
            }
            return false;
        } else {
            $input.css('border-color', '');
            return true;
        }
    }
    
    /**
     * Validace procentuální hodnoty
     */
    function validatePercentage($input, min, max) {
        var value = parseInt($input.val());
        
        if (value < min || value > max) {
            $input.css('border-color', '#dc3232');
            return false;
        } else {
            $input.css('border-color', '');
            return true;
        }
    }
    
    /**
     * Validace počtu dní
     */
    function validateDays($input, min, max) {
        var value = parseInt($input.val());
        
        if (value < min || value > max) {
            $input.css('border-color', '#dc3232');
            return false;
        } else {
            $input.css('border-color', '');
            return true;
        }
    }
    
    /**
     * Zvýraznění povinných polí
     */
    function highlightRequiredFields() {
        $('input[required], textarea[required]').each(function() {
            var $label = $(this).closest('tr').find('th label');
            if ($label.length && !$label.find('.required-indicator').length) {
                $label.append(' <span class="required-indicator" style="color: #dc3232;">*</span>');
            }
        });
    }
    
    /**
     * Setup auto-save notification
     */
    function setupAutoSaveNotification() {
        var $form = $('.iucto-settings-form');
        var originalData = $form.serialize();
        
        // Detekce změn ve formuláři
        $form.on('change', 'input, select, textarea', function() {
            var currentData = $form.serialize();
            
            if (currentData !== originalData) {
                $('.iucto-save-info').html(
                    '<span class="dashicons dashicons-warning"></span>' +
                    '<strong>Máte neuložené změny!</strong> Nezapomeňte kliknout na "Uložit změny".'
                ).css('color', '#d63638');
            }
        });
        
        // Reset po uložení
        $form.on('submit', function() {
            $('.iucto-save-info').html(
                '<span class="dashicons dashicons-yes-alt"></span>' +
                'Ukládám změny...'
            ).css('color', '#00a32a');
        });
    }
    
    /**
     * Inicializace order detail stránky
     */
    function initOrderDetailPage() {
        console.log('iÚčto: Order detail page initialized');
        
        // TODO: Tlačítko pro manuální vytvoření faktury
        // TODO: Tlačítko pro storno faktury
        // TODO: Preview faktury
    }
    
    /**
     * Ajax test připojení k API (pro budoucí použití)
     */
    function testApiConnection() {
        var $button = $('.iucto-test-connection');
        
        // Disable tlačítko a přidej loader
        $button.prop('disabled', true);
        $button.find('.button-text').text('Testuji připojení...');
        $button.append('<span class="iucto-loading"></span>');
        
        $.ajax({
            url: iuctoWooAdmin.ajax_url,
            type: 'POST',
            data: {
                action: 'iucto_test_connection',
                nonce: iuctoWooAdmin.nonce
            },
            success: function(response) {
                console.log('Test result:', response);
                
                // Re-enable tlačítko
                $button.prop('disabled', false);
                $button.find('.button-text').text('Otestovat připojení');
                $button.find('.iucto-loading').remove();
                
                // Zobrazit notice
                if (response.success) {
                    showNotice('Test připojení úspěšný', 'success');
                } else {
                    showNotice('Test připojení selhal: ' + response.data.message, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Ajax error:', error);
                
                // Re-enable tlačítko
                $button.prop('disabled', false);
                $button.find('.button-text').text('Otestovat připojení');
                $button.find('.iucto-loading').remove();
                
                showNotice('Chyba při testování: ' + error, 'error');
            }
        });
    }
    
    /**
     * Zobrazí admin notice
     */
    function showNotice(message, type) {
        type = type || 'info';
        
        var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        
        $('.iucto-settings-wrap h1').after($notice);
        
        // Auto dismiss po 5 sekundách
        setTimeout(function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
        
        // Scroll na notice
        $('html, body').animate({
            scrollTop: $notice.offset().top - 50
        }, 300);
    }
    
    /**
     * Smooth scroll pro kotvy
     */
    $('a[href^="#"]').on('click', function(e) {
        if ($(this).hasClass('nav-tab')) {
            return; // Taby se řeší jinde
        }
        
        var target = $(this.getAttribute('href'));
        
        if (target.length) {
            e.preventDefault();
            $('html, body').stop().animate({
                scrollTop: target.offset().top - 50
            }, 500);
        }
    });
    
    /**
     * Potvrzení před opuštěním stránky s neuloženými změnami
     */
    var formChanged = false;
    
    $('.iucto-settings-form').on('change', 'input, select, textarea', function() {
        formChanged = true;
    });
    
    $('.iucto-settings-form').on('submit', function() {
        formChanged = false;
    });
    
    $(window).on('beforeunload', function() {
        if (formChanged) {
            return 'Máte neuložené změny. Opravdu chcete opustit stránku?';
        }
    });
    
})(jQuery);