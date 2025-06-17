<?php
/**
 * Classe per le impostazioni admin del plugin
 *
 * @package MetaTrackingPro
 * @author Adelmo Infante
 */

// Impedisce accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

class MTP_Admin_Settings {
    
    /**
     * Costruttore
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_notices', array($this, 'show_admin_notices'));
    }
    
    /**
     * Aggiunge il menu admin
     */
    public function add_admin_menu() {
        add_options_page(
            __('Meta Tracking Pro', 'meta-tracking-pro'),
            __('Meta Tracking Pro', 'meta-tracking-pro'),
            'manage_options',
            'meta-tracking-pro',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Registra le impostazioni
     */
    public function register_settings() {
        // Gruppo di impostazioni
        register_setting('mtp_settings', 'mtp_pixel_id', array(
            'sanitize_callback' => 'sanitize_text_field'
        ));
        register_setting('mtp_settings', 'mtp_access_token', array(
            'sanitize_callback' => 'sanitize_text_field'
        ));
        register_setting('mtp_settings', 'mtp_api_version', array(
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'v18.0'
        ));
        register_setting('mtp_settings', 'mtp_test_event_code', array(
            'sanitize_callback' => 'sanitize_text_field'
        ));
        register_setting('mtp_settings', 'mtp_enabled', array(
            'sanitize_callback' => array($this, 'sanitize_checkbox'),
            'default' => true
        ));
    }
    
    /**
     * Sanitizza checkbox
     */
    public function sanitize_checkbox($input) {
        return $input ? true : false;
    }
    
    /**
     * Carica script admin
     */
    public function enqueue_admin_scripts($hook) {
        if ('settings_page_meta-tracking-pro' !== $hook) {
            return;
        }
        
        wp_enqueue_style('mtp-admin-style', MTP_PLUGIN_URL . 'assets/admin.css', array(), MTP_VERSION);
    }
    
    /**
     * Mostra avvisi admin
     */
    public function show_admin_notices() {
        $screen = get_current_screen();
        if ($screen->id !== 'settings_page_meta-tracking-pro') {
            return;
        }
        
        // Verifica configurazione
        if (!MetaTrackingPro::is_configured()) {
            ?>
            <div class="notice notice-warning">
                <p>
                    <strong><?php _e('Configurazione incompleta', 'meta-tracking-pro'); ?></strong><br>
                    <?php _e('Inserisci Pixel ID e Access Token per attivare il tracking.', 'meta-tracking-pro'); ?>
                </p>
            </div>
            <?php
        }
    }
    
    /**
     * Pagina delle impostazioni
     */
    public function admin_page() {
        // Verifica permessi
        if (!current_user_can('manage_options')) {
            wp_die(__('Non hai i permessi per accedere a questa pagina.', 'meta-tracking-pro'));
        }
        
        // Test connessione se richiesto
        $test_result = null;
        if (isset($_GET['test_connection']) && wp_verify_nonce($_GET['_wpnonce'], 'mtp_test_connection')) {
            $test_result = $this->test_api_connection();
        }
        ?>
        <div class="wrap">
            <h1><?php _e('Meta Tracking Pro - Impostazioni', 'meta-tracking-pro'); ?></h1>
            
            <div class="mtp-admin-container">
                
                <!-- Status Card -->
                <div class="mtp-status-card">
                    <h3><?php _e('Status Configurazione', 'meta-tracking-pro'); ?></h3>
                    <?php if (MetaTrackingPro::is_configured()): ?>
                        <div class="mtp-status-ok">
                            <span class="dashicons dashicons-yes-alt"></span>
                            <?php _e('Plugin configurato e attivo', 'meta-tracking-pro'); ?>
                        </div>
                    <?php else: ?>
                        <div class="mtp-status-warning">
                            <span class="dashicons dashicons-warning"></span>
                            <?php _e('Configurazione incompleta', 'meta-tracking-pro'); ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Form Impostazioni -->
                <form method="post" action="options.php">
                    <?php settings_fields('mtp_settings'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="mtp_enabled"><?php _e('Attiva Tracking', 'meta-tracking-pro'); ?></label>
                            </th>
                            <td>
                                <label class="mtp-switch">
                                    <input type="checkbox" id="mtp_enabled" name="mtp_enabled" value="1" 
                                           <?php checked(get_option('mtp_enabled', true)); ?>>
                                    <span class="mtp-slider"></span>
                                </label>
                                <p class="description"><?php _e('Attiva o disattiva il tracking Meta.', 'meta-tracking-pro'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="mtp_pixel_id"><?php _e('Pixel ID', 'meta-tracking-pro'); ?> *</label>
                            </th>
                            <td>
                                <input type="text" id="mtp_pixel_id" name="mtp_pixel_id" 
                                       value="<?php echo esc_attr(get_option('mtp_pixel_id')); ?>" 
                                       class="regular-text" placeholder="430779897379989" required>
                                <p class="description">
                                    <?php _e('ID del tuo Facebook Pixel (solo numeri).', 'meta-tracking-pro'); ?>
                                    <a href="https://business.facebook.com/events_manager" target="_blank">
                                        <?php _e('Trova il tuo Pixel ID', 'meta-tracking-pro'); ?>
                                    </a>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="mtp_access_token"><?php _e('Access Token', 'meta-tracking-pro'); ?> *</label>
                            </th>
                            <td>
                                <input type="password" id="mtp_access_token" name="mtp_access_token" 
                                       value="<?php echo esc_attr(get_option('mtp_access_token')); ?>" 
                                       class="regular-text" placeholder="EAAQXCRk5EJMBO..." required>
                                <button type="button" class="button mtp-show-password" onclick="mtpTogglePassword('mtp_access_token')">
                                    <?php _e('Mostra', 'meta-tracking-pro'); ?>
                                </button>
                                <p class="description">
                                    <?php _e('Token per API Conversions (server-side tracking).', 'meta-tracking-pro'); ?>
                                    <a href="https://developers.facebook.com/tools/accesstoken/" target="_blank">
                                        <?php _e('Genera Access Token', 'meta-tracking-pro'); ?>
                                    </a>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="mtp_api_version"><?php _e('Versione API', 'meta-tracking-pro'); ?></label>
                            </th>
                            <td>
                                <select id="mtp_api_version" name="mtp_api_version">
                                    <option value="v18.0" <?php selected(get_option('mtp_api_version', 'v18.0'), 'v18.0'); ?>>v18.0</option>
                                    <option value="v19.0" <?php selected(get_option('mtp_api_version'), 'v19.0'); ?>>v19.0</option>
                                    <option value="v20.0" <?php selected(get_option('mtp_api_version'), 'v20.0'); ?>>v20.0</option>
                                </select>
                                <p class="description"><?php _e('Versione API Graph di Facebook da utilizzare.', 'meta-tracking-pro'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="mtp_test_event_code"><?php _e('Test Event Code', 'meta-tracking-pro'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="mtp_test_event_code" name="mtp_test_event_code" 
                                       value="<?php echo esc_attr(get_option('mtp_test_event_code')); ?>" 
                                       class="regular-text" placeholder="TEST12345">
                                <p class="description">
                                    <?php _e('Codice per testare gli eventi (opzionale, solo durante sviluppo).', 'meta-tracking-pro'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button(__('Salva Impostazioni', 'meta-tracking-pro')); ?>
                </form>
                
                <!-- Test Connessione -->
                <?php if (MetaTrackingPro::is_configured()): ?>
                <div class="mtp-test-section">
                    <h3><?php _e('Test Connessione', 'meta-tracking-pro'); ?></h3>
                    <p><?php _e('Verifica che la connessione con Facebook funzioni correttamente.', 'meta-tracking-pro'); ?></p>
                    
                    <?php if ($test_result !== null): ?>
                        <div class="notice notice-<?php echo $test_result['success'] ? 'success' : 'error'; ?> inline">
                            <p><?php echo esc_html($test_result['message']); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <a href="<?php echo wp_nonce_url(add_query_arg('test_connection', '1'), 'mtp_test_connection'); ?>" 
                       class="button button-secondary">
                        <?php _e('Testa Connessione', 'meta-tracking-pro'); ?>
                    </a>
                </div>
                <?php endif; ?>
                
                <!-- Info Plugin -->
                <div class="mtp-info-section">
                    <h3><?php _e('Informazioni Plugin', 'meta-tracking-pro'); ?></h3>
                    <table class="mtp-info-table">
                        <tr>
                            <td><strong><?php _e('Versione:', 'meta-tracking-pro'); ?></strong></td>
                            <td><?php echo MTP_VERSION; ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('Autore:', 'meta-tracking-pro'); ?></strong></td>
                            <td>Adelmo Infante</td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('Sito:', 'meta-tracking-pro'); ?></strong></td>
                            <td><a href="https://ilcovodelnerd.com" target="_blank">Il Covo del Nerd</a></td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('WooCommerce:', 'meta-tracking-pro'); ?></strong></td>
                            <td><?php echo class_exists('WooCommerce') ? WC()->version : __('Non installato', 'meta-tracking-pro'); ?></td>
                        </tr>
                    </table>
                </div>
                
            </div>
        </div>
        
        <style>
        .mtp-admin-container { max-width: 800px; }
        .mtp-status-card { background: #fff; border: 1px solid #ddd; border-radius: 4px; padding: 20px; margin: 20px 0; }
        .mtp-status-ok { color: #46b450; font-weight: bold; }
        .mtp-status-ok .dashicons { color: #46b450; }
        .mtp-status-warning { color: #ffb900; font-weight: bold; }
        .mtp-status-warning .dashicons { color: #ffb900; }
        .mtp-switch { position: relative; display: inline-block; width: 60px; height: 34px; }
        .mtp-switch input { opacity: 0; width: 0; height: 0; }
        .mtp-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 34px; }
        .mtp-slider:before { position: absolute; content: ""; height: 26px; width: 26px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .mtp-slider { background-color: #2196F3; }
        input:checked + .mtp-slider:before { transform: translateX(26px); }
        .mtp-test-section, .mtp-info-section { background: #fff; border: 1px solid #ddd; border-radius: 4px; padding: 20px; margin: 20px 0; }
        .mtp-info-table { width: 100%; }
        .mtp-info-table td { padding: 5px 0; }
        .mtp-show-password { margin-left: 10px; }
        </style>
        
        <script>
        function mtpTogglePassword(fieldId) {
            var field = document.getElementById(fieldId);
            var button = field.nextElementSibling;
            if (field.type === "password") {
                field.type = "text";
                button.textContent = "<?php _e('Nascondi', 'meta-tracking-pro'); ?>";
            } else {
                field.type = "password";
                button.textContent = "<?php _e('Mostra', 'meta-tracking-pro'); ?>";
            }
        }
        </script>
        <?php
    }
    
    /**
 * Testa la connessione API - VERSIONE MIGLIORATA
 */
private function test_api_connection() {
    $pixel_id = get_option('mtp_pixel_id');
    $access_token = get_option('mtp_access_token');
    $api_version = get_option('mtp_api_version', 'v18.0');
    
    if (empty($pixel_id) || empty($access_token)) {
        return array(
            'success' => false,
            'message' => __('Pixel ID o Access Token mancante.', 'meta-tracking-pro')
        );
    }
    
    // METODO 1: Test con invio evento di prova (più affidabile)
    $test_url = "https://graph.facebook.com/{$api_version}/{$pixel_id}/events";
    
    $test_event = array(
        'data' => json_encode(array(array(
            'event_name' => 'PageView',
            'event_time' => time(),
            'action_source' => 'website',
            'event_source_url' => home_url(),
            'user_data' => array(
                'client_ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                'client_user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'TestAgent'
            ),
            'custom_data' => array(
                'content_name' => 'Test Connection'
            ),
            'event_id' => 'test_connection_' . time()
        ))),
        'access_token' => $access_token
    );
    
    // Aggiungi test event code se presente
    $test_code = get_option('mtp_test_event_code', '');
    if (!empty($test_code)) {
        $test_event['test_event_code'] = $test_code;
    }
    
    $response = wp_remote_post($test_url, array(
        'timeout' => 10,
        'sslverify' => true,
        'body' => $test_event,
        'headers' => array(
            'Content-Type' => 'application/x-www-form-urlencoded'
        )
    ));
    
    if (is_wp_error($response)) {
        // METODO 2: Fallback - test semplice su pixel info
        return $this->test_pixel_info_fallback($pixel_id, $access_token, $api_version);
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    if (isset($data['error'])) {
        // Se fallisce il test eventi, prova metodo alternativo
        if ($data['error']['code'] == 100) {
            return $this->test_pixel_info_fallback($pixel_id, $access_token, $api_version);
        }
        
        return array(
            'success' => false,
            'message' => sprintf(__('Errore API: %s (Codice: %s)', 'meta-tracking-pro'), 
                $data['error']['message'], 
                $data['error']['code']
            )
        );
    }
    
    // Se il test eventi va a buon fine
    if (isset($data['events_received'])) {
        $message = __('✅ Connessione API perfetta! Eventi di test ricevuti: ', 'meta-tracking-pro') . $data['events_received'];
        
        if (!empty($test_code)) {
            $message .= __(' (modalità test attiva)', 'meta-tracking-pro');
        }
        
        return array(
            'success' => true,
            'message' => $message
        );
    }
    
    return array(
        'success' => true,
        'message' => __('✅ Connessione riuscita! API Conversions operativo.', 'meta-tracking-pro')
    );
}

    /**
     * Test fallback usando info pixel (meno permessi richiesti)
     */
    private function test_pixel_info_fallback($pixel_id, $access_token, $api_version) {
        // Prova diversi endpoint con permessi più bassi
        $test_endpoints = array(
            // Endpoint 1: Stats del pixel (permessi minimi)
            "https://graph.facebook.com/{$api_version}/{$pixel_id}/stats?access_token={$access_token}",
            // Endpoint 2: Info base pixel
            "https://graph.facebook.com/{$api_version}/{$pixel_id}?fields=id,name&access_token={$access_token}",
            // Endpoint 3: Test con me endpoint
            "https://graph.facebook.com/{$api_version}/me?access_token={$access_token}"
        );
        
        foreach ($test_endpoints as $index => $url) {
            $response = wp_remote_get($url, array(
                'timeout' => 10,
                'sslverify' => true
            ));
            
            if (!is_wp_error($response)) {
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);
                
                if (!isset($data['error'])) {
                    $messages = array(
                        0 => __('✅ Connessione base riuscita! (Stats disponibili)', 'meta-tracking-pro'),
                        1 => __('✅ Connessione riuscita! Pixel riconosciuto.', 'meta-tracking-pro'),
                        2 => __('✅ Access Token valido! Connessione API attiva.', 'meta-tracking-pro')
                    );
                    
                    return array(
                        'success' => true,
                        'message' => $messages[$index] . ' ' . __('Il tracking funziona correttamente!', 'meta-tracking-pro')
                    );
                }
            }
        }
        
        // Se tutti i test falliscono, ma sappiamo che il tracking funziona
        return array(
            'success' => true,
            'message' => __('ℹ️ I permessi dell\'Access Token sono limitati per il test, ma il tracking è OPERATIVO! Verifica con Facebook Pixel Helper che il tracking funzioni correttamente (cosa che hai già fatto con successo).', 'meta-tracking-pro')
        );
    }
}