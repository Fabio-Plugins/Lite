<?php

/*
Plugin Name: Fabio Chatbot Lite for Shop, Blog & Forums
Description: AI ChatBot With Analytic Features
Version: 1.4.0
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.0
Author: Fabio-Plugins.com
Author URI: https://fabio-plugins.com
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: fabio-ai-chatbot-lite
*/
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
// ==========================
// CONSTANTES
// ==========================
if ( !defined( 'FABIOAI_CHATBOT_PLUGIN_PATH' ) ) {
    define( 'FABIOAI_CHATBOT_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
}
if ( !defined( 'FABIOAI_CHATBOT_PLUGIN_URL' ) ) {
    define( 'FABIOAI_CHATBOT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}
// Version du schéma de base de données : à incrémenter à chaque changement de
// fabioai_chatbot_create_tables() pour déclencher la migration automatique.
// 1.1 : colonnes session_id / page_url / intent + table des clics.
if ( !defined( 'FABIOAI_CHATBOT_DB_VERSION' ) ) {
    define( 'FABIOAI_CHATBOT_DB_VERSION', '1.1' );
}
// ==========================
// CHARGEMENT DEPENDANCES
// ==========================
add_action( 'plugins_loaded', function () {
    require_once FABIOAI_CHATBOT_PLUGIN_PATH . 'includes/stopwords.php';
    require_once FABIOAI_CHATBOT_PLUGIN_PATH . 'includes/class-fabioai-chatbot-shortcodes.php';
    require_once FABIOAI_CHATBOT_PLUGIN_PATH . 'includes/class-fabioai-chatbot-scan.php';
    require_once FABIOAI_CHATBOT_PLUGIN_PATH . 'includes/fabioai-extra-content.php';
    require_once FABIOAI_CHATBOT_PLUGIN_PATH . 'includes/class-fabioai-chatbot-analytics.php';
    require_once FABIOAI_CHATBOT_PLUGIN_PATH . 'includes/class-fabioai-chatbot-conversations.php';
} );
// Charger la page admin uniquement en back-office
if ( is_admin() ) {
    require_once FABIOAI_CHATBOT_PLUGIN_PATH . 'admin/admin-page.php';
    require_once FABIOAI_CHATBOT_PLUGIN_PATH . 'admin/analytics-page.php';
    // ← AJOUT
}
// ==========================
// DB TABLES (ACTIVATION)
// ==========================
register_activation_hook( __FILE__, 'fabioai_chatbot_activate' );
function fabioai_chatbot_activate() {
    // Création des tables
    fabioai_chatbot_create_tables();

    // Statut "running" affiché immédiatement, avant même que le scan ne démarre réellement
    if ( !class_exists( 'FabioAIChatbot_Scan' ) ) {
        require_once FABIOAI_CHATBOT_PLUGIN_PATH . 'includes/class-fabioai-chatbot-scan.php';
    }
    if ( class_exists( 'FabioAIChatbot_Scan' ) ) {
        FabioAIChatbot_Scan::start( 'auto' );
    }

    // Déclenchement immédiat du tout premier scan (se lance au prochain cycle WP-Cron, quasi instantané)
    if ( !wp_next_scheduled( 'fabioai_chatbot_daily_scan' ) ) {
        wp_schedule_single_event( time(), 'fabioai_chatbot_daily_scan' );
    }

    // Programmation des scans quotidiens suivants (24h après l'activation, pour ne pas doubler le scan immédiat)
    if ( !wp_next_scheduled( 'fabioai_chatbot_daily_scan_recurring' ) ) {
        wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 'fabioai_chatbot_daily_scan_recurring' );
    }
}

function fabioai_chatbot_create_tables() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $table_name = $wpdb->prefix . 'fabioai_chatbot_scan_segments';
    $charset_collate = $wpdb->get_charset_collate();
    // Table conforme aux champs utilisés dans fabioai_chatbot_store_segments() (class-fabioai-chatbot-scan.php)
    $sql = "CREATE TABLE {$table_name} (\n        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,\n        post_id BIGINT(20) UNSIGNED NOT NULL,\n        post_type VARCHAR(50) NOT NULL,\n        title TEXT NOT NULL,\n        url TEXT NOT NULL,\n        segment_index INT(11) NOT NULL,\n        segment_text LONGTEXT NOT NULL,\n        content_hash CHAR(32) NOT NULL,\n        scanned_at DATETIME NOT NULL,\n        PRIMARY KEY  (id),\n        UNIQUE KEY content_hash (content_hash),\n        KEY post_id (post_id),\n        KEY post_type (post_type),\n        KEY scanned_at (scanned_at)\n    ) {$charset_collate};";
    dbDelta( $sql );
    // ==========================
    // TABLE CONVERSATIONS
    // ==========================
    $table_conversations = $wpdb->prefix . 'fabioai_chatbot_conversations';
    // DB 1.1 : session_id (identifiant de session du navigateur), page_url (chemin de la page
    // où la question a été posée) et intent (étiquette d'intention). Les conversations déjà
    // enregistrées gardent des valeurs vides. Note dbDelta : deux espaces après PRIMARY KEY.
    $sql_conversations = "CREATE TABLE {$table_conversations} (\n        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,\n        user_message TEXT NOT NULL,\n        user_time DATETIME NOT NULL,\n        bot_response LONGTEXT NOT NULL,\n        bot_time DATETIME NOT NULL,\n        sources LONGTEXT NOT NULL DEFAULT '',\n        session_id VARCHAR(36) NOT NULL DEFAULT '',\n        page_url VARCHAR(255) NOT NULL DEFAULT '',\n        intent VARCHAR(30) NOT NULL DEFAULT '',\n        PRIMARY KEY  (id),\n        KEY user_time (user_time),\n        KEY session_id (session_id),\n        KEY sources (sources(191))\n    ) {$charset_collate};";
    dbDelta( $sql_conversations );
    // ==========================
    // TABLE CLICS (DB 1.1)
    // ==========================
    // Un enregistrement par clic sur un lien affiché par le chatbot.
    $table_clicks = $wpdb->prefix . 'fabioai_chatbot_clicks';
    $sql_clicks = "CREATE TABLE {$table_clicks} (\n        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,\n        conversation_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,\n        session_id VARCHAR(36) NOT NULL DEFAULT '',\n        link_url VARCHAR(500) NOT NULL DEFAULT '',\n        link_type VARCHAR(10) NOT NULL DEFAULT '',\n        click_time DATETIME NOT NULL,\n        PRIMARY KEY  (id),\n        KEY conversation_id (conversation_id),\n        KEY session_id (session_id),\n        KEY click_time (click_time)\n    ) {$charset_collate};";
    dbDelta( $sql_clicks );
}
// ==========================
// MIGRATION AUTOMATIQUE DE LA BASE (indépendante de l'activation)
// ==========================
// register_activation_hook() ne se déclenche pas lors d'une mise à jour de
// fichiers en place : on vérifie donc à chaque chargement que le schéma est
// à jour (nouvelles colonnes et table de clics).
add_action( 'plugins_loaded', 'fabioai_chatbot_maybe_upgrade_db', 5 );
function fabioai_chatbot_maybe_upgrade_db() {
    $installed_version = get_option( 'fabioai_chatbot_db_version', '0' );
    if ( version_compare( $installed_version, FABIOAI_CHATBOT_DB_VERSION, '<' ) ) {
        fabioai_chatbot_create_tables();
        // Date de début du suivi des sessions : les conversations plus anciennes
        // n'ont ni session ni clics (avertissement sur la page Analytics).
        if ( get_option( 'fabioai_chatbot_sessions_since', '' ) === '' ) {
            update_option( 'fabioai_chatbot_sessions_since', current_time( 'mysql' ), false );
        }
        update_option( 'fabioai_chatbot_db_version', FABIOAI_CHATBOT_DB_VERSION, false );
    }
}

// ==========================
// CRON : DAILY AUTO SCAN
// ==========================
register_deactivation_hook( __FILE__, 'fabioai_chatbot_deactivate' );
function fabioai_chatbot_deactivate() {
    $timestamp = wp_next_scheduled( 'fabioai_chatbot_daily_scan' );
    if ( $timestamp ) {
        wp_unschedule_event( $timestamp, 'fabioai_chatbot_daily_scan' );
    }
    $recurring_timestamp = wp_next_scheduled( 'fabioai_chatbot_daily_scan_recurring' );
    if ( $recurring_timestamp ) {
        wp_unschedule_event( $recurring_timestamp, 'fabioai_chatbot_daily_scan_recurring' );
    }
    delete_transient( 'fabioai_chatbot_auto_scan_lock' );
    delete_transient( 'fabioai_chatbot_manual_scan_lock' );
    delete_transient( 'fabioai_chatbot_scan_progress' );
}

add_action( 'fabioai_chatbot_daily_scan', 'fabioai_chatbot_run_daily_scan' );
add_action( 'fabioai_chatbot_daily_scan_recurring', 'fabioai_chatbot_run_daily_scan' );
function fabioai_chatbot_run_daily_scan() {
    // Charger la classe même en contexte cron
    if ( !class_exists( 'FabioAIChatbot_Scan' ) ) {
        require_once FABIOAI_CHATBOT_PLUGIN_PATH . 'includes/class-fabioai-chatbot-scan.php';
    }
    // Lock spécifique au scan automatique
    if ( get_transient( 'fabioai_chatbot_auto_scan_lock' ) ) {
        return;
    }
    set_transient( 'fabioai_chatbot_auto_scan_lock', 1, 10 * MINUTE_IN_SECONDS );
    if ( !class_exists( 'FabioAIChatbot_Scan' ) ) {
        delete_transient( 'fabioai_chatbot_auto_scan_lock' );
        return;
    }
    $scanner = new FabioAIChatbot_Scan();
    $items = $scanner->run( 'auto' );
    fabioai_chatbot_set_scan_meta( [
        'site_fingerprint' => fabioai_chatbot_get_site_fingerprint(),
        'last_scan_type'   => 'auto',
        'last_scan_ts'     => time(),
        'items_count'      => ( is_array( $items ) ? count( $items ) : 0 ),
    ] );
    update_option( 'fabioai_chatbot_last_auto_scan', time() );
    update_option( 'fabioai_chatbot_last_scan_count', ( is_array( $items ) ? count( $items ) : 0 ) );
    delete_transient( 'fabioai_chatbot_auto_scan_lock' );
}

// ==========================
// ENQUEUE FRONT-END
// ==========================
add_action( 'wp_enqueue_scripts', 'fabioai_chatbot_enqueue_assets' );
function fabioai_chatbot_enqueue_assets() {
    // CSS principal du chatbot (nouvelle UI)
    wp_enqueue_style(
        'fabioai-chatbot-style',
        FABIOAI_CHATBOT_PLUGIN_URL . 'assets/css/chatbot.css',
        [],
        filemtime( FABIOAI_CHATBOT_PLUGIN_PATH . 'assets/css/chatbot.css' )
    );
    // Dynamic CSS variables
    $bubble_color = get_option( 'fabioai_chatbot_bubble_color', '#ff6a00' );
    $border_color = get_option( 'fabioai_chatbot_border_color', '#ff6a00' );
    $chatbot_bg_color = get_option( 'fabioai_chatbot_background_color', '#ffffff' );
    $user_bg = get_option( 'fabioai_chatbot_user_bg', '#6c5ce7' );
    $bot_bg = get_option( 'fabioai_chatbot_bot_bg', '#f4f6ff' );
    $button_bg = get_option( 'fabioai_chatbot_button_bg', '#38bdf8' );
    $enable_gradients = get_option( 'fabioai_chatbot_enable_gradients', 'no' );
    $gradient_style = get_option( 'fabioai_chatbot_gradient_style', 'soft' );
    $allowed_gradient_styles = ['soft', 'vivid', 'premium'];
    if ( !in_array( $gradient_style, $allowed_gradient_styles, true ) ) {
        $gradient_style = 'soft';
    }
    $gradient_enabled_value = ( $enable_gradients === 'yes' ? '1' : '0' );
    $shadow_style = get_option( 'fabioai_chatbot_shadow_style', 'soft' );
    $allowed_shadow_styles = ['none', 'soft', 'elevated', 'glow'];
    if ( !in_array( $shadow_style, $allowed_shadow_styles, true ) ) {
        $shadow_style = 'soft';
    }
    $dynamic_css = ':root {
      --fabioai-chatbot-bubble-color: ' . esc_attr( $bubble_color ) . ';
      --fabioai-chatbot-border-color: ' . esc_attr( $border_color ) . ';
      --fabioai-chatbot-bg-color: ' . esc_attr( $chatbot_bg_color ) . ';
      --fabioai-chatbot-user-bg: ' . esc_attr( $user_bg ) . ';
      --fabioai-chatbot-bot-bg: ' . esc_attr( $bot_bg ) . ';
      --fabioai-chatbot-button-bg: ' . esc_attr( $button_bg ) . ';
      --fabioai-chatbot-gradient-enabled: ' . esc_attr( $gradient_enabled_value ) . ';
      --fabioai-chatbot-gradient-style: ' . esc_attr( $gradient_style ) . ';
      --fabioai-chatbot-shadow-style: ' . esc_attr( $shadow_style ) . ';
  }';
  wp_add_inline_style( 'fabioai-chatbot-style', $dynamic_css );
    // JS du chatbot
  wp_enqueue_script(
    'fabioai-chatbot-script',
    FABIOAI_CHATBOT_PLUGIN_URL . 'assets/chatbot.js',
    ['jquery'],
    filemtime( FABIOAI_CHATBOT_PLUGIN_PATH . 'assets/chatbot.js' ),
    true
);
    // Données transmises au JS
  wp_localize_script( 'fabioai-chatbot-script', 'fabioaiChatbotData', [
    'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
    'nonce'       => wp_create_nonce( 'fabioai_chatbot_chat_request_nonce' ),
    'welcomeText' => get_option( 'fabioai_chatbot_welcome_text', '' ),
] );
}

// ==========================
// FRONT-END BODY CLASSES
// ==========================
add_filter( 'body_class', 'fabioai_chatbot_body_classes' );
function fabioai_chatbot_body_classes(  $classes  ) {
    $enable_gradients = get_option( 'fabioai_chatbot_enable_gradients', 'no' );
    $gradient_style = get_option( 'fabioai_chatbot_gradient_style', 'soft' );
    $allowed_gradient_styles = ['soft', 'vivid', 'premium'];
    if ( !in_array( $gradient_style, $allowed_gradient_styles, true ) ) {
        $gradient_style = 'soft';
    }
    if ( $enable_gradients === 'yes' ) {
        $classes[] = 'fabioai-chatbot-gradients-enabled';
        $classes[] = 'fabioai-chatbot-gradient-' . $gradient_style;
    }

    $shadow_style = get_option( 'fabioai_chatbot_shadow_style', 'soft' );
    $allowed_shadow_styles = ['none', 'soft', 'elevated', 'glow'];
    if ( !in_array( $shadow_style, $allowed_shadow_styles, true ) ) {
        $shadow_style = 'soft';
    }
    $classes[] = 'fabioai-chatbot-shadow-' . $shadow_style;

    return $classes;
}

// ==========================
// ENQUEUE ADMIN
// ==========================
add_action( 'admin_enqueue_scripts', 'fabioai_chatbot_enqueue_admin_scripts' );
function fabioai_chatbot_enqueue_admin_scripts() {
    wp_enqueue_style( 'wp-color-picker' );
    wp_enqueue_script( 'wp-color-picker' );
    wp_enqueue_media();
    wp_enqueue_style(
        'fabioai-chatbot-admin-css',
        FABIOAI_CHATBOT_PLUGIN_URL . 'admin/admin.css',
        [],
        filemtime( FABIOAI_CHATBOT_PLUGIN_PATH . 'admin/admin.css' )
    );
    wp_enqueue_script(
        'fabioai-chatbot-admin',
        FABIOAI_CHATBOT_PLUGIN_URL . 'admin/admin.js',
        ['jquery', 'wp-color-picker'],
        filemtime( FABIOAI_CHATBOT_PLUGIN_PATH . 'admin/admin.js' ),
        true
    );
    wp_localize_script( 'fabioai-chatbot-admin', 'fabioaiChatbotAdmin', [
        'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
        'nonce'           => wp_create_nonce( 'fabioai_chatbot_scan' ),
        'models'          => [
            'openai'    => [
                [
                    'value' => 'gpt-5.6-luna',
                    'label' => 'GPT-5.6 Luna',
                ],
                [
                    'value' => 'gpt-6-luna',
                    'label' => 'GPT-6 Luna',
                ]
            ],
            'gemini'    => [[
                'value' => 'gemini-3.1-flash-lite',
                'label' => 'Gemini 3.1 Flash-Lite',
            ], [
                'value' => 'gemini-3.8-flash',
                'label' => 'Gemini 3.8 Flash',
            ]],
            'mistral'   => [[
                'value' => 'mistral-large-3',
                'label' => 'Mistral Large 3',
            ]],
            'anthropic' => [[
                'value' => 'claude-haiku-5-5',
                'label' => 'Claude Haiku 5.5',
            ], [
                'value' => 'claude-sonnet-5-5',
                'label' => 'Claude Sonnet 5.5',
            ]],
        ],
        'currentProvider' => get_option( 'fabioai_chatbot_provider', 'openai' ),
        'currentModel'    => get_option( 'fabioai_chatbot_model', 'gpt-5.6-luna' ),
    ] );
        // 🔥 ANALYTICS PAGE ONLY
    if ( isset( $_GET['page'] ) && $_GET['page'] === 'fabioai-chatbot-analytics' ) {
        wp_enqueue_style(
            'fabioai-chatbot-analytics-css',
            FABIOAI_CHATBOT_PLUGIN_URL . 'admin/analytics.css',
            [],
            filemtime( FABIOAI_CHATBOT_PLUGIN_PATH . 'admin/analytics.css' )
        );
        wp_enqueue_script(
            'fabioai-chatbot-analytics-js',
            FABIOAI_CHATBOT_PLUGIN_URL . 'admin/analytics.js',
            ['jquery', 'fabioai-chatbot-admin'],
            filemtime( FABIOAI_CHATBOT_PLUGIN_PATH . 'admin/analytics.js' ),
            true
        );
        wp_localize_script( 'fabioai-chatbot-analytics-js', 'fabioaiChatbotAnalytics', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'fabioai_chatbot_analytics_nonce' ),
        ] );
        // Conversations, intentions, nuage de mots
        wp_enqueue_style(
            'fabioai-chatbot-analytics-conversations-css',
            FABIOAI_CHATBOT_PLUGIN_URL . 'admin/analytics-conversations.css',
            ['fabioai-chatbot-analytics-css'],
            filemtime( FABIOAI_CHATBOT_PLUGIN_PATH . 'admin/analytics-conversations.css' )
        );
        wp_enqueue_script(
            'fabioai-chatbot-analytics-conversations-js',
            FABIOAI_CHATBOT_PLUGIN_URL . 'admin/analytics-conversations.js',
            ['fabioai-chatbot-analytics-js'],
            filemtime( FABIOAI_CHATBOT_PLUGIN_PATH . 'admin/analytics-conversations.js' ),
            true
        );
        wp_localize_script( 'fabioai-chatbot-analytics-conversations-js', 'fabioaiChatbotConversations', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'fabioai_chatbot_analytics_nonce' ),
        ] );
    }
}

// ==========================
// INITIALISATION SHORTCODES
// ==========================
add_action( 'init', function () {
    if ( class_exists( 'FabioAIChatbot_Shortcodes' ) ) {
        // Instance gardée en mémoire : l'analyse des anciennes conversations (admin) l'utilise
        // pour appeler l'IA sans enregistrer une deuxième fois les hooks du chatbot.
        $GLOBALS['fabioai_chatbot_shortcodes'] = new FabioAIChatbot_Shortcodes();
    }
} );
// ==========================
// PAGE ADMIN
// ==========================
add_action( 'admin_menu', function () {
    add_menu_page(
        'Fabio AI ChatBot Lite',
        'Fabio AI ChatBot Lite',
        'manage_options',
        'fabioai-chatbot-admin',
        'fabioai_chatbot_render_admin_page',
        'dashicons-format-chat'
    );
    // 🔥 AJOUT ANALYTICS ICI
    add_submenu_page(
        'fabioai-chatbot-admin',
        'Analytics',
        'Analytics',
        'manage_options',
        'fabioai-chatbot-analytics',
        'fabioai_chatbot_render_analytics_page'
    );
} );
// ==========================
// SETTINGS
// ==========================
add_action( 'admin_init', function () {
    register_setting( 'fabioai_chatbot_settings', 'fabioai_chatbot_welcome_text', [
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_textarea_field',
        'default'           => '',
    ] );
    register_setting( 'fabioai_chatbot_settings', 'fabioai_chatbot_openai_api_key', [
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => '',
    ] );
    register_setting( 'fabioai_chatbot_settings', 'fabioai_chatbot_provider', [
        'type'              => 'string',
        'sanitize_callback' => function ( $value ) {
            $allowed = ['openai', 'gemini', 'mistral', 'anthropic'];
            return ( in_array( $value, $allowed, true ) ? $value : 'openai' );
        },
        'default'           => 'openai',
    ] );
    register_setting( 'fabioai_chatbot_settings', 'fabioai_chatbot_gemini_api_key', [
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => '',
    ] );
    register_setting( 'fabioai_chatbot_settings', 'fabioai_chatbot_mistral_api_key', [
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => '',
    ] );
    register_setting( 'fabioai_chatbot_settings', 'fabioai_chatbot_anthropic_api_key', [
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => '',
    ] );
    register_setting( 'fabioai_chatbot_settings', 'fabioai_chatbot_model', [
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => 'gpt-5.6-luna',
    ] );
    // Display settings
    register_setting( 'fabioai_chatbot_settings', 'fabioai_chatbot_display_mode', [
        'type'              => 'string',
        'sanitize_callback' => function ( $value ) {
            $allowed = ['manual', 'auto', 'bubble'];
            if ( in_array( $value, $allowed, true ) ) {
                return $value;
            }
            return 'manual';
            // sécurité par défaut
        },
        'default'           => 'manual',
    ] );
    register_setting( 'fabioai_chatbot_settings', 'fabioai_chatbot_insert_position', [
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => 'top',
    ] );
    register_setting( 'fabioai_chatbot_settings', 'fabioai_chatbot_insert_index', [
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => '1',
    ] );
    register_setting( 'fabioai_chatbot_settings', 'fabioai_chatbot_bubble_position', [
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => 'right',
    ] );
    register_setting( 'fabioai_chatbot_settings', 'fabioai_chatbot_bubble_color', [
        'type'              => 'string',
        'sanitize_callback' => function ( $value ) {
            $value = trim( (string) $value );
            // Si le champ n'est pas envoyé (mode auto), on garde l'ancienne valeur
            if ( $value === '' ) {
                return get_option( 'fabioai_chatbot_bubble_color', '#ff6a00' );
            }
            // Validation hex stricte
            if ( preg_match( '/^#[0-9a-fA-F]{6}$/', $value ) ) {
                return $value;
            }
            // Sinon on garde l'ancienne valeur
            return get_option( 'fabioai_chatbot_bubble_color', '#ff6a00' );
        },
        'default'           => '#ff6a00',
    ] );
    // Border color
    register_setting( 'fabioai_chatbot_settings', 'fabioai_chatbot_border_color', [
        'type'              => 'string',
        'sanitize_callback' => function ( $value ) {
            $value = trim( (string) $value );
            return ( preg_match( '/^#[0-9a-fA-F]{6}$/', $value ) ? $value : '#ff6a00' );
        },
        'default'           => '#ff6a00',
    ] );
    // Chatbot background color
    register_setting( 'fabioai_chatbot_settings', 'fabioai_chatbot_background_color', [
        'type'              => 'string',
        'sanitize_callback' => function ( $value ) {
            $value = trim( (string) $value );
            return ( preg_match( '/^#[0-9a-fA-F]{6}$/', $value ) ? $value : '#ffffff' );
        },
        'default'           => '#ffffff',
    ] );
    // User bubble background
    register_setting( 'fabioai_chatbot_settings', 'fabioai_chatbot_user_bg', [
        'type'              => 'string',
        'sanitize_callback' => function ( $value ) {
            $value = trim( (string) $value );
            return ( preg_match( '/^#[0-9a-fA-F]{6}$/', $value ) ? $value : '#6c5ce7' );
        },
        'default'           => '#6c5ce7',
    ] );
    // Bot bubble background
    register_setting( 'fabioai_chatbot_settings', 'fabioai_chatbot_bot_bg', [
        'type'              => 'string',
        'sanitize_callback' => function ( $value ) {
            $value = trim( (string) $value );
            return ( preg_match( '/^#[0-9a-fA-F]{6}$/', $value ) ? $value : '#f4f6ff' );
        },
        'default'           => '#f4f6ff',
    ] );
    // Button background
    register_setting( 'fabioai_chatbot_settings', 'fabioai_chatbot_button_bg', [
        'type'              => 'string',
        'sanitize_callback' => function ( $value ) {
            $value = trim( (string) $value );
            return ( preg_match( '/^#[0-9a-fA-F]{6}$/', $value ) ? $value : '#38bdf8' );
        },
        'default'           => '#38bdf8',
    ] );
    // Enable gradients
    register_setting( 'fabioai_chatbot_settings', 'fabioai_chatbot_enable_gradients', [
        'type'              => 'string',
        'sanitize_callback' => function ( $value ) {
            return ( $value === 'yes' ? 'yes' : 'no' );
        },
        'default'           => 'no',
    ] );
    // Gradient style
    register_setting( 'fabioai_chatbot_settings', 'fabioai_chatbot_gradient_style', [
        'type'              => 'string',
        'sanitize_callback' => function ( $value ) {
            $allowed = ['soft', 'vivid', 'premium'];
            return ( in_array( $value, $allowed, true ) ? $value : 'soft' );
        },
        'default'           => 'soft',
    ] );
    // Shadow / elevation style
    register_setting( 'fabioai_chatbot_settings', 'fabioai_chatbot_shadow_style', [
        'type'              => 'string',
        'sanitize_callback' => function ( $value ) {
            $allowed = ['none', 'soft', 'elevated', 'glow'];
            return ( in_array( $value, $allowed, true ) ? $value : 'soft' );
        },
        'default'           => 'soft',
    ] );
} );

// ==========================
// EXPORT CONVERSATIONS (CSV)
// ==========================
// ==========================
// AJAX CHECK CONVERSATIONS (ADMIN)
// ==========================
add_action( 'wp_ajax_fabioai_chatbot_check_conversations', 'fabioai_chatbot_check_conversations' );
function fabioai_chatbot_check_conversations() {
    if ( !current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }
    global $wpdb;
    $table = $wpdb->prefix . 'fabioai_chatbot_conversations';
    $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
    if ( $count === 0 ) {
        wp_send_json_error( 'empty' );
    }
    wp_send_json_success( 'ok' );
}

add_action( 'admin_post_fabioai_chatbot_export_conversations', 'fabioai_chatbot_export_conversations' );
function fabioai_chatbot_export_conversations() {
    if ( !current_user_can( 'manage_options' ) ) {
        wp_die( 'Unauthorized' );
    }
    check_admin_referer( 'fabioai_chatbot_export_nonce' );

    $should_clear = ( isset( $_POST['clear_after_export'] ) && $_POST['clear_after_export'] === '1' );

    if ( $should_clear && empty( $_POST['confirm_clear'] ) ) {
        wp_die( 'You must confirm database clearing before exporting with deletion.' );
    }

    global $wpdb;
    $table = $wpdb->prefix . 'fabioai_chatbot_conversations';
    $results = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY user_time DESC", ARRAY_A );
    if ( empty( $results ) ) {
        wp_die( 'No conversations found.' );
    }
    header( 'Content-Type: text/csv' );
    header( 'Content-Disposition: attachment; filename=chatbot-conversations-' . date( 'Y-m-d' ) . '.csv' );
    header( 'Pragma: no-cache' );
    header( 'Expires: 0' );
    $output = fopen( 'php://output', 'w' );
    fputcsv( $output, [
        'User Message',
        'User Time',
        'Bot Response',
        'Bot Time'
    ] );
    foreach ( $results as $row ) {
        fputcsv( $output, [
            $row['user_message'],
            $row['user_time'],
            $row['bot_response'],
            $row['bot_time']
        ] );
    }
    fclose( $output );

    if ( $should_clear ) {
        $wpdb->query( "TRUNCATE TABLE {$table}" );
        $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}fabioai_chatbot_clicks" );

        $conversations_version = (int) get_option( 'fabioai_chatbot_conversations_version', '0' );
        update_option( 'fabioai_chatbot_conversations_version', (string) ( $conversations_version + 1 ) );
    }

    exit;
}

// ==========================
// AJAX : MANUAL SCAN (admin button)
// ==========================
add_action( 'wp_ajax_fabioai_chatbot_run_scan', 'fabioai_chatbot_run_manual_scan' );
function fabioai_chatbot_run_manual_scan() {
    check_ajax_referer( 'fabioai_chatbot_scan', '_ajax_nonce' );
    if ( !current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }
    if ( get_transient( 'fabioai_chatbot_manual_scan_lock' ) ) {
        wp_send_json_error( 'Scan already running.' );
    }
    set_transient( 'fabioai_chatbot_manual_scan_lock', 1, 10 * MINUTE_IN_SECONDS );
    if ( !class_exists( 'FabioAIChatbot_Scan' ) ) {
        require_once FABIOAI_CHATBOT_PLUGIN_PATH . 'includes/class-fabioai-chatbot-scan.php';
    }
    if ( !class_exists( 'FabioAIChatbot_Scan' ) ) {
        delete_transient( 'fabioai_chatbot_manual_scan_lock' );
        wp_send_json_error( 'Scanner not available.' );
    }
    // Statut "running" affiché instantanément côté admin, avant le comptage réel
    FabioAIChatbot_Scan::start( 'manual' );
    $current_fingerprint = fabioai_chatbot_get_site_fingerprint();
    $scanner = new FabioAIChatbot_Scan();
    $items = $scanner->run( 'manual' );
    fabioai_chatbot_set_scan_meta( [
        'site_fingerprint' => $current_fingerprint,
        'last_scan_type'   => 'manual_ajax',
        'last_scan_ts'     => time(),
        'items_count'      => ( is_array( $items ) ? count( $items ) : 0 ),
    ] );
    update_option( 'fabioai_chatbot_last_manual_scan', time() );
    update_option( 'fabioai_chatbot_last_scan_count', ( is_array( $items ) ? count( $items ) : 0 ) );
    delete_transient( 'fabioai_chatbot_manual_scan_lock' );
    wp_send_json_success( 'Manual scan completed successfully.' );
}

// ==========================
// AJAX : SCAN PROGRESS (polling, auto + manual)
// ==========================

/**
 * Résumé "dernier scan" formaté (partagé entre le rendu initial de la page
 * et le polling AJAX, pour éviter toute désynchronisation).
 */
function fabioai_chatbot_get_scan_summary(): array {
    $last_scan        = (int) get_option( 'fabioai_chatbot_last_manual_scan', 0 );
    $last_auto_scan   = (int) get_option( 'fabioai_chatbot_last_auto_scan', 0 );
    $last_scan_count  = (int) get_option( 'fabioai_chatbot_last_scan_count', 0 );
    $date_time_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

    $next_auto_scan = fabioai_chatbot_get_next_scan_timestamp();

    return [
        'last_manual_scan_text' => ( $last_scan ? date_i18n( $date_time_format, $last_scan ) : 'Never' ),
        'last_auto_scan_text'   => ( $last_auto_scan ? date_i18n( $date_time_format, $last_auto_scan ) : 'Never' ),
        'next_auto_scan_text'   => ( $next_auto_scan ? date_i18n( $date_time_format, $next_auto_scan ) : 'Not scheduled' ),
        'last_scan_count'       => $last_scan_count,
        'breakdown'             => fabioai_chatbot_get_scan_breakdown(),
    ];
}

/**
 * Prochain horodatage de scan automatique programmé (le plus proche entre le
 * déclenchement immédiat à l'activation et la récurrence quotidienne).
 */
function fabioai_chatbot_get_next_scan_timestamp(): int {
    $next_single    = wp_next_scheduled( 'fabioai_chatbot_daily_scan' );
    $next_recurring = wp_next_scheduled( 'fabioai_chatbot_daily_scan_recurring' );

    $candidates = array_filter(
        [ $next_single, $next_recurring ],
        function ( $timestamp ) {
            return $timestamp !== false;
        }
    );

    if ( empty( $candidates ) ) {
        return 0;
    }

    return (int) min( $candidates );
}

/**
 * Répartition du dernier scan par type de contenu, avec libellés lisibles.
 * Ne renvoie que les types réellement présents (count > 0).
 */
function fabioai_chatbot_get_scan_breakdown(): array {
    $raw = json_decode( (string) get_option( 'fabioai_chatbot_last_scan_breakdown', '' ), true );
    if ( !is_array( $raw ) ) {
        $raw = [];
    }

    $labels = [
        'post'        => 'Blog posts',
        'page'        => 'Pages',
        'product'     => 'Products',
        'category'    => 'Categories',
        'post_tag'    => 'Tags',
        'product_cat' => 'Product categories',
        'product_tag' => 'Product tags',
        'forum'       => 'Forums',
        'topic'       => 'Forum topics',
        'reply'       => 'Forum replies',
    ];

    $breakdown = [];
    foreach ( $labels as $type => $label ) {
        $count = isset( $raw[ $type ] ) ? (int) $raw[ $type ] : 0;
        if ( $count > 0 ) {
            $breakdown[] = [
                'type'  => $type,
                'label' => $label,
                'count' => $count,
            ];
        }
    }

    // Types de contenu personnalisés choisis dans la carte des champs personnalisés.
    foreach ( $raw as $type => $count ) {
        $count = (int) $count;
        if ( isset( $labels[ $type ] ) || $count <= 0 ) {
            continue;
        }
        $object = post_type_exists( $type ) ? get_post_type_object( $type ) : null;
        $breakdown[] = [
            'type'  => (string) $type,
            'label' => ( $object && ! empty( $object->labels->name ) ) ? (string) $object->labels->name : ucfirst( str_replace( '_', ' ', (string) $type ) ),
            'count' => $count,
        ];
    }

    return $breakdown;
}

add_action( 'wp_ajax_fabioai_chatbot_get_scan_progress', 'fabioai_chatbot_get_scan_progress' );
function fabioai_chatbot_get_scan_progress() {
    check_ajax_referer( 'fabioai_chatbot_scan', '_ajax_nonce' );
    if ( !current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }
    if ( !class_exists( 'FabioAIChatbot_Scan' ) ) {
        require_once FABIOAI_CHATBOT_PLUGIN_PATH . 'includes/class-fabioai-chatbot-scan.php';
    }
    if ( !class_exists( 'FabioAIChatbot_Scan' ) ) {
        wp_send_json_error( 'Scanner not available.' );
    }
    $progress = FabioAIChatbot_Scan::get_progress();
    $progress['summary'] = fabioai_chatbot_get_scan_summary();
    wp_send_json_success( $progress );
}

// ==========================
// AJAX : ANALYTICS DATA
// ==========================
add_action( 'wp_ajax_fabioai_chatbot_get_analytics', function () {
    check_ajax_referer( 'fabioai_chatbot_analytics_nonce', '_ajax_nonce' );
    if ( !current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }
    $search_keywords = ( isset( $_POST['search_keywords'] ) ? sanitize_text_field( wp_unslash( $_POST['search_keywords'] ) ) : null );
    $analytics = new FabioAIChatbot_Analytics();
    wp_send_json_success( $analytics->get_analytics( $search_keywords ) );
} );

// ==========================
// AJAX : CONVERSATIONS, INTENTIONS, NUAGE DE MOTS (page Analytics)
// ==========================
add_action( 'wp_ajax_fabioai_chatbot_get_conversations', function () {
    check_ajax_referer( 'fabioai_chatbot_analytics_nonce', '_ajax_nonce' );
    if ( !current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }
    $range = '24h'; // Lite : période fixe (dernières 24 heures)
    $sort = ( isset( $_POST['sort'] ) ? sanitize_text_field( wp_unslash( $_POST['sort'] ) ) : 'date_desc' );
    $click_filter = ( isset( $_POST['click_filter'] ) ? sanitize_text_field( wp_unslash( $_POST['click_filter'] ) ) : 'all' );
    $date_from = null;
    $date_to = null;
    $page = ( isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1 );
    $search_keywords = ( isset( $_POST['search_keywords'] ) ? sanitize_text_field( wp_unslash( $_POST['search_keywords'] ) ) : null );
    $intent = ( isset( $_POST['intent'] ) ? sanitize_key( wp_unslash( $_POST['intent'] ) ) : '' );
    $conversations = new FabioAIChatbot_Conversations();
    wp_send_json_success( $conversations->get_conversations(
        $range,
        $date_from,
        $date_to,
        $sort,
        $click_filter,
        $page,
        $search_keywords,
        $intent
    ) );
} );
add_action( 'wp_ajax_fabioai_chatbot_get_conversation_detail', function () {
    check_ajax_referer( 'fabioai_chatbot_analytics_nonce', '_ajax_nonce' );
    if ( !current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }
    $conversation_id = ( isset( $_POST['conversation_id'] ) ? absint( $_POST['conversation_id'] ) : 0 );
    $conversations = new FabioAIChatbot_Conversations();
    $detail = $conversations->get_conversation_detail( $conversation_id );
    if ( empty( $detail ) ) {
        wp_send_json_error( 'Conversation not found' );
    }
    wp_send_json_success( $detail );
} );
add_action( 'wp_ajax_fabioai_chatbot_get_intents', function () {
    check_ajax_referer( 'fabioai_chatbot_analytics_nonce', '_ajax_nonce' );
    if ( !current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }
    $range = '24h'; // Lite : période fixe (dernières 24 heures)
    $date_from = null;
    $date_to = null;
    $search_keywords = ( isset( $_POST['search_keywords'] ) ? sanitize_text_field( wp_unslash( $_POST['search_keywords'] ) ) : null );
    $conversations = new FabioAIChatbot_Conversations();
    wp_send_json_success( $conversations->get_intent_breakdown(
        $range,
        $date_from,
        $date_to,
        $search_keywords
    ) );
} );
add_action( 'wp_ajax_fabioai_chatbot_get_word_cloud', function () {
    check_ajax_referer( 'fabioai_chatbot_analytics_nonce', '_ajax_nonce' );
    if ( !current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }
    $range = '24h'; // Lite : période fixe (dernières 24 heures)
    $date_from = null;
    $date_to = null;
    $search_keywords = ( isset( $_POST['search_keywords'] ) ? sanitize_text_field( wp_unslash( $_POST['search_keywords'] ) ) : null );
    $conversations = new FabioAIChatbot_Conversations();
    wp_send_json_success( $conversations->get_word_cloud(
        $range,
        $date_from,
        $date_to,
        $search_keywords
    ) );
} );
add_action( 'wp_ajax_fabioai_chatbot_backfill_intents', function () {
    check_ajax_referer( 'fabioai_chatbot_analytics_nonce', '_ajax_nonce' );
    if ( !current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }
    set_time_limit( 120 );
    $cursor = ( isset( $_POST['cursor'] ) ? absint( $_POST['cursor'] ) : 0 );
    $shortcodes = ( isset( $GLOBALS['fabioai_chatbot_shortcodes'] ) ? $GLOBALS['fabioai_chatbot_shortcodes'] : null );
    $conversations = new FabioAIChatbot_Conversations();
    $result = $conversations->backfill_intents_batch( $shortcodes, $cursor );
    if ( empty( $result['ok'] ) ) {
        wp_send_json_error( ( isset( $result['error'] ) ? $result['error'] : 'Analysis failed.' ) );
    }
    wp_send_json_success( $result );
} );
// ==========================
// META SCAN (fingerprint)
// ==========================
function fabioai_chatbot_get_existing_scan_meta() : array {
    $meta = get_option( 'fabioai_chatbot_scan_meta', [] );
    return ( is_array( $meta ) ? $meta : [] );
}

function fabioai_chatbot_set_scan_meta(  array $meta  ) : void {
    $existing = fabioai_chatbot_get_existing_scan_meta();
    $merged = array_merge( $existing, $meta );
    update_option( 'fabioai_chatbot_scan_meta', $merged, false );
}

/**
 * Fingerprint simple et robuste :
 * - basé sur (count posts/pages publiés) + (dernier post_modified_gmt)
 * - suffisant pour détecter des changements sans scanner tout le site
 */
function fabioai_chatbot_get_site_fingerprint() : string {
    global $wpdb;
    $post_types = ['post', 'page'];
    if ( post_type_exists( 'product' ) ) {
        $post_types[] = 'product';
    }
    $escaped_types = array_map( 'esc_sql', $post_types );
    $in_types = "'" . implode( "','", $escaped_types ) . "'";
    $row = $wpdb->get_row( "SELECT\n            COUNT(ID) AS c,\n            MAX(post_modified_gmt) AS m\n        FROM {$wpdb->posts}\n        WHERE post_status = 'publish'\n          AND post_type IN ({$in_types})", ARRAY_A );
    $count = ( isset( $row['c'] ) ? (string) $row['c'] : '0' );
    $maxm = ( isset( $row['m'] ) ? (string) $row['m'] : '' );
    return md5( $count . '|' . $maxm );
}
