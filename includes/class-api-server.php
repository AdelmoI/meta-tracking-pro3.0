<?php
/**
 * Classe per API Conversions server-side
 *
 * @package MetaTrackingPro
 * @author Adelmo Infante
 */

// Impedisce accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

class MTP_API_Server {
    
    /**
     * Costruttore
     */
    public function __construct() {
        // La classe è principalmente statica per facilità d'uso
    }
    
    /**
     * Invia evento tramite API Conversions
     *
     * @param string $event_name Nome dell'evento
     * @param array $custom_data Dati personalizzati dell'evento
     * @param array $user_data Dati utente (opzionale)
     * @return bool Success status
     */
    public static function send_event($event_name, $custom_data = array(), $user_data = array()) {
        // Verifica configurazione
        if (!defined('MTP_PIXEL_ID') || !defined('MTP_ACCESS_TOKEN') || !defined('MTP_API_VERSION')) {
            error_log('MTP: Costanti API non definite per evento ' . $event_name);
            return false;
        }
        
        // Verifica che il tracking sia abilitato
        if (!get_option('mtp_enabled', true)) {
            return false;
        }
        
        try {
            $url = 'https://graph.facebook.com/' . MTP_API_VERSION . '/' . MTP_PIXEL_ID . '/events';
            
            // Prepara dati utente formattati
            $user_data_formatted = self::format_user_data($user_data);
            
            // Prepara l'evento
            $event = array(
                'event_name' => $event_name,
                'event_time' => time(),
                'event_source_url' => self::get_current_url(),
                'action_source' => 'website',
                'user_data' => $user_data_formatted,
                'custom_data' => $custom_data
            );
            
            // Event ID per deduplicazione
            $event['event_id'] = self::generate_event_id($event_name);
            
            // Prepara la richiesta
            $data = array(
                'data' => json_encode(array($event)),
                'access_token' => MTP_ACCESS_TOKEN
            );
            
            // Aggiunge test event code se configurato
            if (defined('MTP_TEST_EVENT_CODE') && !empty(MTP_TEST_EVENT_CODE)) {
                $data['test_event_code'] = MTP_TEST_EVENT_CODE;
            }
            
            // Invia la richiesta (non bloccante per performance)
            $response = wp_remote_post($url, array(
                'body' => $data,
                'timeout' => 3, // Ottimizzato per produzione
                'blocking' => false, // Non blocca la pagina
                'sslverify' => true,
                'headers' => array(
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'User-Agent' => 'MetaTrackingPro/' . MTP_VERSION . ' (WordPress)'
                )
            ));
            
            // Log errori solo se necessario
            if (is_wp_error($response)) {
                error_log('MTP API Error: ' . $response->get_error_message());
                return false;
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log('MTP Exception: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Formatta i dati utente per l'API
     *
     * @param array $user_data Dati utente grezzi
     * @return array Dati utente formattati e hashati
     */
    private static function format_user_data($user_data = array()) {
        $formatted = array();
        
        // Email (hashata)
        if (!empty($user_data['email']) && is_email($user_data['email'])) {
            $formatted['em'] = hash('sha256', strtolower(trim($user_data['email'])));
        }
        
        // Telefono (hashato)
        if (!empty($user_data['phone'])) {
            $phone = preg_replace('/[^0-9]/', '', $user_data['phone']);
            if (strlen($phone) >= 7) {
                $formatted['ph'] = hash('sha256', $phone);
            }
        }
        
        // Nome (hashato)
        if (!empty($user_data['first_name'])) {
            $formatted['fn'] = hash('sha256', strtolower(trim($user_data['first_name'])));
        }
        
        // Cognome (hashato)
        if (!empty($user_data['last_name'])) {
            $formatted['ln'] = hash('sha256', strtolower(trim($user_data['last_name'])));
        }
        
        // Città (hashata)
        if (!empty($user_data['city'])) {
            $formatted['ct'] = hash('sha256', strtolower(trim($user_data['city'])));
        }
        
        // Stato/Provincia (hashato)
        if (!empty($user_data['state'])) {
            $formatted['st'] = hash('sha256', strtolower(trim($user_data['state'])));
        }
        
        // CAP (hashato)
        if (!empty($user_data['postal_code'])) {
            $formatted['zp'] = hash('sha256', trim($user_data['postal_code']));
        }
        
        // Paese (hashato)
        if (!empty($user_data['country'])) {
            $formatted['country'] = hash('sha256', strtolower(trim($user_data['country'])));
        }
        
        // IP Address (non hashato)
        if (isset($_SERVER['REMOTE_ADDR'])) {
            $formatted['client_ip_address'] = $_SERVER['REMOTE_ADDR'];
        }
        
        // User Agent (non hashato)
        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            $formatted['client_user_agent'] = $_SERVER['HTTP_USER_AGENT'];
        }
        
        // External ID (per utenti loggati)
        if (is_user_logged_in()) {
            $formatted['external_id'] = hash('sha256', get_current_user_id());
        }
        
        // Cookie Facebook (se disponibili)
        if (isset($_COOKIE['_fbp'])) {
            $formatted['fbp'] = $_COOKIE['_fbp'];
        }
        
        if (isset($_COOKIE['_fbc'])) {
            $formatted['fbc'] = $_COOKIE['_fbc'];
        }
        
        return $formatted;
    }
    
    /**
     * Genera un event ID unico per deduplicazione
     *
     * @param string $event_name Nome dell'evento
     * @return string Event ID univoco
     */
    private static function generate_event_id($event_name) {
        return strtolower($event_name) . '_server_' . time() . '_' . uniqid();
    }
    
    /**
     * Ottiene l'URL corrente
     *
     * @return string URL corrente
     */
    private static function get_current_url() {
        if (isset($_SERVER['REQUEST_URI'])) {
            return home_url($_SERVER['REQUEST_URI']);
        }
        return home_url();
    }
    
    /**
     * Testa la connessione API
     *
     * @return array Risultato del test
     */
    public static function test_connection() {
        if (!defined('MTP_PIXEL_ID') || !defined('MTP_ACCESS_TOKEN') || !defined('MTP_API_VERSION')) {
            return array(
                'success' => false,
                'message' => 'Configurazione incompleta'
            );
        }
        
        $url = 'https://graph.facebook.com/' . MTP_API_VERSION . '/' . MTP_PIXEL_ID . '?access_token=' . MTP_ACCESS_TOKEN;
        
        $response = wp_remote_get($url, array(
            'timeout' => 10,
            'sslverify' => true,
            'headers' => array(
                'User-Agent' => 'MetaTrackingPro/' . MTP_VERSION . ' (WordPress)'
            )
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => 'Errore connessione: ' . $response->get_error_message()
            );
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['error'])) {
            return array(
                'success' => false,
                'message' => 'Errore API: ' . $data['error']['message']
            );
        }
        
        if (isset($data['id'])) {
            return array(
                'success' => true,
                'message' => 'Connessione riuscita! Pixel ID: ' . $data['id']
            );
        }
        
        return array(
            'success' => false,
            'message' => 'Risposta API non valida'
        );
    }
    
    /**
     * Invia evento di test per verificare il funzionamento
     *
     * @return array Risultato del test
     */
    public static function send_test_event() {
        $test_data = array(
            'content_type' => 'product',
            'content_ids' => array('test_product_123'),
            'value' => 9.99,
            'currency' => 'EUR'
        );
        
        $test_user_data = array(
            'email' => 'test@example.com',
            'first_name' => 'Test',
            'last_name' => 'User'
        );
        
        $success = self::send_event('PageView', $test_data, $test_user_data);
        
        return array(
            'success' => $success,
            'message' => $success ? 'Evento di test inviato con successo' : 'Errore nell\'invio dell\'evento di test'
        );
    }
    
    /**
     * Ottiene le statistiche di utilizzo API (se disponibili)
     *
     * @return array Statistiche
     */
    public static function get_api_stats() {
        // Implementazione base - può essere estesa in futuro
        return array(
            'events_sent_today' => get_transient('mtp_events_sent_today') ?: 0,
            'last_event_time' => get_option('mtp_last_event_time', 0),
            'api_errors_today' => get_transient('mtp_api_errors_today') ?: 0
        );
    }
    
    /**
     * Incrementa il contatore eventi
     */
    public static function increment_event_counter() {
        $today = date('Y-m-d');
        $key = 'mtp_events_sent_' . $today;
        $count = get_transient($key) ?: 0;
        set_transient($key, $count + 1, DAY_IN_SECONDS);
        
        // Aggiorna anche il timestamp ultimo evento
        update_option('mtp_last_event_time', time());
    }
    
    /**
     * Incrementa il contatore errori
     */
    public static function increment_error_counter() {
        $today = date('Y-m-d');
        $key = 'mtp_api_errors_' . $today;
        $count = get_transient($key) ?: 0;
        set_transient($key, $count + 1, DAY_IN_SECONDS);
    }
    
    /**
     * Valida i dati dell'evento prima dell'invio
     *
     * @param string $event_name Nome evento
     * @param array $custom_data Dati custom
     * @return bool|string True se valido, stringa errore altrimenti
     */
    public static function validate_event_data($event_name, $custom_data) {
        // Verifica nome evento
        $valid_events = array(
            'ViewContent', 'AddToCart', 'AddToWishlist', 'InitiateCheckout',
            'AddPaymentInfo', 'Purchase', 'Lead', 'CompleteRegistration',
            'Search', 'Contact', 'CustomizeProduct', 'Donate', 'FindLocation',
            'Schedule', 'StartTrial', 'SubmitApplication', 'Subscribe'
        );
        
        if (!in_array($event_name, $valid_events) && substr($event_name, 0, 6) !== 'Custom') {
            return 'Nome evento non valido: ' . $event_name;
        }
        
        // Verifica currency se presente value
        if (isset($custom_data['value']) && !isset($custom_data['currency'])) {
            return 'Currency richiesto quando è presente value';
        }
        
        // Verifica content_ids formato
        if (isset($custom_data['content_ids'])) {
            if (!is_array($custom_data['content_ids'])) {
                return 'content_ids deve essere un array';
            }
            
            if (count($custom_data['content_ids']) > 100) {
                return 'Troppi content_ids (massimo 100)';
            }
        }
        
        return true;
    }
    
    /**
     * Pulisce i dati dell'evento rimuovendo valori non validi
     *
     * @param array $data Dati da pulire
     * @return array Dati puliti
     */
    public static function sanitize_event_data($data) {
        $sanitized = array();
        
        foreach ($data as $key => $value) {
            // Rimuovi valori null o stringhe vuote
            if ($value === null || $value === '') {
                continue;
            }
            
            // Sanifica stringhe
            if (is_string($value)) {
                $sanitized[$key] = sanitize_text_field($value);
            }
            // Mantieni numeri e array così come sono
            else if (is_numeric($value) || is_array($value) || is_bool($value)) {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }
}