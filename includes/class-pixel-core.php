<?php
/**
 * Classe core per l'inizializzazione del Facebook Pixel
 *
 * @package MetaTrackingPro
 * @author Adelmo Infante
 */

// Impedisce accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

class MTP_Pixel_Core {
    
    /**
     * Costruttore
     */
    public function __construct() {
        // Solo se il tracking è abilitato
        if (!get_option('mtp_enabled', true)) {
            return;
        }
        
        // Verifica che abbiamo le costanti necessarie
        if (!defined('MTP_PIXEL_ID') || !defined('MTP_ACCESS_TOKEN')) {
            return;
        }
        
        // Hook per l'inizializzazione
        add_action('wp_head', array($this, 'define_callback_system'), 1);
        add_action('wp_head', array($this, 'pixel_base_code'), 2);
        add_action('wp_head', array($this, 'block_conflicting_pixels'), 999);
    }
    
    /**
     * Definisce il sistema di callback JavaScript - PRODUCTION CLEAN
     */
    public function define_callback_system() {
        ?>
        <script>
        // Sistema callback Meta Tracking Pro - PRODUCTION VERSION
        window.fbPixelCallbacks = window.fbPixelCallbacks || [];
        window.fbPixelReady = false;
        window.fbTrackedEvents = window.fbTrackedEvents || new Set();
        
        // Helper per ritardare operazioni non critiche
        function deferNonCritical(callback, delay = 50) {
            if (document.readyState === 'loading') {
                window.addEventListener('load', function() {
                    setTimeout(callback, delay);
                });
            } else {
                setTimeout(callback, delay);
            }
        }
        
        // Blocca pixel preesistenti - RITARDATO
        deferNonCritical(function() {
            if (typeof fbq !== 'undefined') {
                window.originalFbq = fbq;
            }
            window.wc_facebook_pixel = null;
            window.wcFacebookPixel = null;
        });
        
        // jQuery operations - RITARDATE
        if (typeof jQuery !== 'undefined') {
            deferNonCritical(function() {
                jQuery(function($) {
                    $(document).off('click.wc-facebook-pixel');
                    $(document).off('added_to_cart.wc-facebook-pixel');
                    
                    if (window.wc_facebook_pixel_options) {
                        window.wc_facebook_pixel_options = null;
                    }
                });
            }, 300);
        }
        
        // Funzione principale per callback
        window.onFbPixelReady = function(callback) {
            if (window.fbPixelReady) {
                try {
                    callback();
                } catch(e) {
                    console.error('MTP Callback Error:', e);
                }
            } else {
                window.fbPixelCallbacks.push(callback);
            }
        };
        
        // Funzione tracking - CLEAN VERSION
        window.fbTrackEvent = function(eventName, params, source) {
            var timestamp = Date.now();
            var eventId = eventName.toLowerCase() + '_' + timestamp + '_' + Math.random().toString(36).substr(2, 9);
            
            var eventKey = eventName + '_' + JSON.stringify(params) + '_' + (source || '');
            var eventHash = 'evt_';
            
            for (var i = 0; i < eventKey.length; i++) {
                eventHash += eventKey.charCodeAt(i).toString(16);
            }
            eventHash = eventHash.substring(0, 20);
            
            if (window.fbTrackedEvents.has(eventHash)) {
                return false;
            }
            
            window.fbTrackedEvents.add(eventHash);
            
            setTimeout(function() {
                window.fbTrackedEvents.delete(eventHash);
            }, 30000);
            
            // TRACKING ASINCRONO
            requestAnimationFrame(function() {
                fbq('track', eventName, params, {eventID: eventId});
            });
            
            return true;
        };
        
        // Monitor eventi - RITARDATO
        window.fbEventMonitor = function() {
            deferNonCritical(function() {
                if (typeof fbq !== 'undefined' && !window.fbqOverridden) {
                    window.fbqOverridden = true;
                    var originalFbq = fbq;
                    
                    window.fbq = function() {
                        var args = Array.prototype.slice.call(arguments);
                        var eventType = args[0];
                        var eventName = args[1];
                        var params = args[2] || {};
                        var options = args[3] || {};
                        
                        if (eventType === 'track' && eventName === 'AddToCart') {
                            if (params.cs_est === true || !options.eventID) {
                                return;
                            }
                        }
                        
                        if (eventType === 'track' && ['AddToCart', 'Purchase', 'InitiateCheckout'].includes(eventName)) {
                            if (!options.eventID) {
                                return;
                            }
                        }
                        
                        return originalFbq.apply(this, arguments);
                    };
                    
                    for (var prop in originalFbq) {
                        if (originalFbq.hasOwnProperty(prop)) {
                            window.fbq[prop] = originalFbq[prop];
                        }
                    }
                }
            }, 500);
        };
        </script>
        <?php
    }
    
    /**
     * Codice base del Facebook Pixel - PRODUCTION CLEAN
     */
    public function pixel_base_code() {
        ?>
        <script>
        // Facebook Pixel Base Code - PRODUCTION VERSION
        !function(f,b,e,v,n,t,s)
        {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
        n.callMethod.apply(n,arguments):n.queue.push(arguments)};
        if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
        n.queue=[];t=b.createElement(e);t.async=!0;
        t.src=v;s=b.getElementsByTagName(e)[0];
        s.parentNode.insertBefore(t,s)}(window, document,'script',
        'https://connect.facebook.net/en_US/fbevents.js');
        
        function initializeFacebookPixel() {
            try {
                if (typeof fbq !== 'undefined' && fbq.callMethod) {
                    fbq('init', '<?php echo MTP_PIXEL_ID; ?>');
                    fbq('track', 'PageView', {}, {eventID: 'pageview_' + Date.now()});
                    
                    window.fbPixelReady = true;
                    
                    // Esegui callback in coda
                    if (window.fbPixelCallbacks && window.fbPixelCallbacks.length > 0) {
                        var callbacksToExecute = window.fbPixelCallbacks.slice();
                        window.fbPixelCallbacks = [];
                        
                        callbacksToExecute.forEach(function(callback) {
                            try {
                                callback();
                            } catch(e) {
                                console.error('MTP Callback Error:', e);
                            }
                        });
                    }
                    
                    // Attiva monitor eventi
                    setTimeout(function() {
                        if (typeof window.fbEventMonitor === 'function') {
                            window.fbEventMonitor();
                        }
                    }, 200);
                    
                } else {
                    setTimeout(initializeFacebookPixel, 500);
                }
            } catch(e) {
                console.error('MTP Pixel Init Error:', e);
                setTimeout(initializeFacebookPixel, 1000);
            }
        }
        
        initializeFacebookPixel();
        </script>
        <noscript>
        <img height="1" width="1" style="display:none"
        src="https://www.facebook.com/tr?id=<?php echo MTP_PIXEL_ID; ?>&ev=PageView&noscript=1"/>
        </noscript>
        <?php
    }
    
    /**
     * Blocca pixel conflittuali - PRODUCTION CLEAN
     */
    public function block_conflicting_pixels() {
        ?>
        <script>
        // Blocca pixel WooCommerce nativi - PRODUCTION VERSION
        if (typeof wc_facebook_pixel !== 'undefined') {
            wc_facebook_pixel = null;
        }
        
        jQuery(document).ready(function($) {
            $('script[src*="facebook-for-woocommerce"]').remove();
            $('script').filter(function() {
                return $(this).text().includes('wc_facebook_pixel') || 
                       $(this).text().includes('cs_est') ||
                       ($(this).text().includes('fbq') && $(this).text().includes('AddToCart') && !$(this).text().includes('MTP'));
            }).remove();
        });
        </script>
        <?php
    }
    
    /**
     * Ottieni il Pixel ID configurato
     */
    public static function get_pixel_id() {
        return defined('MTP_PIXEL_ID') ? MTP_PIXEL_ID : '';
    }
    
    /**
     * Verifica se il pixel è inizializzato
     */
    public static function is_pixel_ready() {
        return defined('MTP_PIXEL_ID') && defined('MTP_ACCESS_TOKEN') && get_option('mtp_enabled', true);
    }
    
    /**
     * Genera un event ID unico
     */
    public static function generate_event_id($event_name, $additional_data = '') {
        return strtolower($event_name) . '_' . time() . '_' . substr(md5($additional_data . uniqid()), 0, 8);
    }
}