<?php
/**
 * Fabio AI ChatBot Lite — Custom fields for the scan
 *
 * Custom fields chosen by the webmaster (admin card in Step 1) are added to the
 * text indexed by the scan. A custom content type is scanned in full (title,
 * content and chosen fields) as soon as a field is chosen for it.
 *
 * @copyright 2026 Fabio-Plugins.com
 * @license GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ==========================
// OPTIONS / HELPERS
// ==========================

/** Content types always scanned by the plugin (shared with the scanner constant). */
function fabioai_chatbot_native_post_types() : array {
    if ( class_exists( 'FabioAIChatbot_Scan' ) ) {
        return FabioAIChatbot_Scan::SCANNABLE_POST_TYPES;
    }
    return ['post', 'page', 'product', 'forum', 'topic', 'reply'];
}

/**
 * Public custom content types of the site (created by a theme, by Custom Post Type UI,
 * ACF, Pods, Toolset, JetEngine...). They are scanned only once a custom field is
 * chosen for them.
 */
function fabioai_chatbot_custom_post_types() : array {
    $found = get_post_types( ['public' => true, '_builtin' => false], 'names' );
    $found = is_array( $found ) ? array_values( $found ) : [];

    // Technical types that are not content.
    $skip = ['elementor_library', 'e-floating-buttons', 'e-landing-page'];

    $types = array_values( array_diff( $found, fabioai_chatbot_native_post_types(), $skip ) );
    $types = apply_filters( 'fabioai_chatbot_custom_post_types', $types );

    return is_array( $types ) ? array_values( array_unique( array_map( 'sanitize_key', $types ) ) ) : [];
}

/** Content types a custom field can be attached to: custom types first, then the standard ones. */
function fabioai_chatbot_extra_post_types() : array {
    return array_values( array_unique( array_merge(
        fabioai_chatbot_custom_post_types(),
        fabioai_chatbot_native_post_types()
    ) ) );
}

/** Rows saved in the admin: [ ['post_type' => , 'key' => , 'label' => ], ... ] */
function fabioai_chatbot_get_custom_field_rows() : array {
    $rows = get_option( 'fabioai_chatbot_custom_fields', [] );

    if ( ! is_array( $rows ) ) {
        return [];
    }

    $clean = [];

    foreach ( $rows as $row ) {
        if ( ! is_array( $row ) || empty( $row['key'] ) || empty( $row['post_type'] ) ) {
            continue;
        }

        $clean[] = [
            'post_type' => (string) $row['post_type'],
            'key'       => (string) $row['key'],
            'label'     => isset( $row['label'] ) ? (string) $row['label'] : '',
        ];
    }

    return $clean;
}

/** HTML (or text) to plain text, keeping paragraph and line breaks. */
function fabioai_chatbot_html_to_text( string $html ) : string {
    $html = preg_replace( '~</(?:p|li|h[1-6]|blockquote|div|tr|ul|ol)>~i', "\n\n", $html );
    $html = preg_replace( '~<br\s*/?>~i', "\n", (string) $html );

    $text = fabioai_chatbot_clean_content( (string) $html );
    $text = preg_replace( "/[ \t]*\n[ \t]*/", "\n", $text );
    $text = preg_replace( "/\n{3,}/", "\n\n", (string) $text );

    return trim( (string) $text );
}

/** Value of a custom field (string, number or array of scalars) to plain text. */
function fabioai_chatbot_meta_value_to_text( $value ) : string {
    if ( is_array( $value ) ) {
        $parts = [];

        array_walk_recursive( $value, function ( $item ) use ( &$parts ) {
            if ( is_scalar( $item ) && trim( (string) $item ) !== '' ) {
                $parts[] = trim( (string) $item );
            }
        } );

        $value = implode( ', ', $parts );
    } elseif ( ! is_scalar( $value ) ) {
        return '';
    }

    return fabioai_chatbot_html_to_text( (string) $value );
}

/** Cuts a long text into pieces of at most $max characters, preferably at a space. */
function fabioai_chatbot_hard_wrap( string $text, int $max ) : array {
    if ( mb_strlen( $text ) <= $max ) {
        return [$text];
    }

    $pieces = [];

    while ( mb_strlen( $text ) > $max ) {
        $cut = mb_substr( $text, 0, $max );
        $pos = mb_strrpos( $cut, ' ' );

        if ( $pos === false || $pos < ( $max / 2 ) ) {
            $pos = $max;
        }

        $pieces[] = trim( mb_substr( $text, 0, $pos ) );
        $text     = trim( mb_substr( $text, $pos ) );
    }

    if ( $text !== '' ) {
        $pieces[] = $text;
    }

    return $pieces;
}

/**
 * Packs lines into passages of at most $max characters. Every passage starts
 * with $header so that it stays understandable on its own.
 */
function fabioai_chatbot_pack_lines( string $header, array $lines, int $max = 1100 ) : array {
    $segments = [];
    $current  = $header;
    $room     = max( 200, $max - mb_strlen( $header ) - 1 );

    foreach ( $lines as $line ) {
        $line = trim( (string) $line );

        if ( $line === '' ) {
            continue;
        }

        foreach ( fabioai_chatbot_hard_wrap( $line, $room ) as $piece ) {
            if ( $current !== $header && mb_strlen( $current . "\n" . $piece ) > $max ) {
                $segments[] = $current;
                $current    = $header;
            }

            $current .= "\n" . $piece;
        }
    }

    if ( $current !== $header ) {
        $segments[] = $current;
    }

    return $segments;
}

// ==========================
// CUSTOM FIELDS → PASSAGES
// ==========================

/** Passages built from the custom fields chosen for the content type of $post. */
function fabioai_chatbot_build_field_segments( WP_Post $post, string $title ) : array {
    $blocks = [];

    foreach ( fabioai_chatbot_get_custom_field_rows() as $row ) {
        if ( $row['post_type'] !== $post->post_type ) {
            continue;
        }

        $text = fabioai_chatbot_meta_value_to_text( get_post_meta( $post->ID, $row['key'], true ) );

        if ( $text === '' ) {
            continue;
        }

        $blocks[] = [
            'label' => ( trim( $row['label'] ) !== '' ) ? trim( $row['label'] ) : $row['key'],
            'text'  => $text,
        ];
    }

    $segments = [];

    foreach ( $blocks as $block ) {
        $label      = $block['label'];
        $label_line = preg_match( '/[?!.:]$/u', $label ) ? $label : $label . ':';
        $header     = 'Title: ' . $title . "\n" . $label_line;
        $lines      = preg_split( '/\n+/', $block['text'] );

        $segments = array_merge( $segments, fabioai_chatbot_pack_lines( $header, $lines ) );
    }

    return $segments;
}

/**
 * All the extra passages of one post.
 * Called by FabioAIChatbot_Scan::run().
 */
function fabioai_chatbot_build_extra_segments( WP_Post $post, string $title ) : array {
    $clean_title = fabioai_chatbot_clean_content( $title );

    return array_values( fabioai_chatbot_build_field_segments( $post, $clean_title ) );
}

// ==========================
// SETTINGS
// ==========================

function fabioai_chatbot_sanitize_custom_fields( $value ) : array {
    if ( ! is_array( $value ) ) {
        return [];
    }

    // Types already saved stay valid even if their plugin is switched off for a while.
    $allowed_types = array_values( array_unique( array_merge(
        fabioai_chatbot_extra_post_types(),
        array_column( fabioai_chatbot_get_custom_field_rows(), 'post_type' )
    ) ) );
    $clean = [];
    $seen  = [];

    foreach ( $value as $row ) {
        if ( ! is_array( $row ) ) {
            continue;
        }

        $type = isset( $row['post_type'] ) ? sanitize_key( (string) $row['post_type'] ) : '';
        $key  = isset( $row['key'] ) ? preg_replace( '/[^A-Za-z0-9_\-\.:]/', '', (string) $row['key'] ) : '';

        if ( $key === '' || ! in_array( $type, $allowed_types, true ) || isset( $seen[ $type . '|' . $key ] ) ) {
            continue;
        }

        $label = isset( $row['label'] ) ? sanitize_text_field( (string) $row['label'] ) : '';
        $label = mb_substr( $label, 0, 120 );

        if ( $label === '' ) {
            $label = ucfirst( trim( str_replace( ['_', '-'], ' ', ltrim( $key, '_' ) ) ) );
        }

        $seen[ $type . '|' . $key ] = true;
        $clean[]                    = [
            'post_type' => $type,
            'key'       => $key,
            'label'     => $label,
        ];

        if ( count( $clean ) >= 30 ) {
            break;
        }
    }

    return $clean;
}

add_action( 'admin_init', function () {
    register_setting( 'fabioai_chatbot_settings', 'fabioai_chatbot_custom_fields', [
        'type'              => 'array',
        'sanitize_callback' => 'fabioai_chatbot_sanitize_custom_fields',
        'default'           => [],
    ] );
} );

/** Changing the custom fields changes what is indexed: a full scan is scheduled. */
function fabioai_chatbot_schedule_full_rescan() : void {
    if ( wp_next_scheduled( 'fabioai_chatbot_daily_scan' ) ) {
        return;
    }

    // Shows the progress bar right away, before the scan really starts.
    if ( class_exists( 'FabioAIChatbot_Scan' ) ) {
        FabioAIChatbot_Scan::start( 'auto' );
    }

    wp_schedule_single_event( time(), 'fabioai_chatbot_daily_scan' );
}

add_action( 'update_option_fabioai_chatbot_custom_fields', 'fabioai_chatbot_schedule_full_rescan' );

add_action( 'add_option_fabioai_chatbot_custom_fields', function ( $option, $value ) {
    // First save with the default value (nothing to index): no scan needed.
    if ( $value === [] ) {
        return;
    }

    fabioai_chatbot_schedule_full_rescan();
}, 10, 2 );

// ==========================
// AJAX : FIELD KEYS AVAILABLE FOR A CONTENT TYPE (admin only, on demand)
// ==========================

function fabioai_chatbot_filter_meta_keys( array $keys ) : array {
    $hidden = '/^(_edit_lock|_edit_last|_thumbnail_id|_pingme|_encloseme|_wp_|_oembed_|_menu_item_|_transient|_elementor_)/';

    return array_values( array_filter( $keys, function ( $key ) use ( $hidden ) {
        return is_string( $key ) && $key !== '' && strlen( $key ) <= 64 && ! preg_match( $hidden, $key );
    } ) );
}

add_action( 'wp_ajax_fabioai_chatbot_get_meta_keys', function () {
    check_ajax_referer( 'fabioai_chatbot_meta_keys', '_ajax_nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }

    $type = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : '';

    if ( ! in_array( $type, fabioai_chatbot_extra_post_types(), true ) ) {
        wp_send_json_error( 'Invalid content type' );
    }

    $cache_key = 'fabioai_chatbot_meta_keys_' . $type;
    $keys      = get_transient( $cache_key );

    if ( ! is_array( $keys ) ) {
        global $wpdb;

        // Admin-only lookup, cached for 5 minutes just below.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $found = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT pm.meta_key
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE p.post_type = %s
               AND p.post_status = 'publish'
               AND pm.meta_value <> ''
             ORDER BY pm.meta_key ASC
             LIMIT 1000",
            $type
        ) );

        $keys = fabioai_chatbot_filter_meta_keys( is_array( $found ) ? $found : [] );
        set_transient( $cache_key, $keys, 5 * MINUTE_IN_SECONDS );
    }

    wp_send_json_success( $keys );
} );

// ==========================
// ADMIN CARD (printed in Step 1 of the settings page, inside the settings form)
// The script and the styles of the card are in admin/admin.js and admin/admin.css.
// ==========================

/** Content types offered in the menu: custom types first, then the standard ones. */
function fabioai_chatbot_extra_type_options( array $rows ) : array {
    $native   = fabioai_chatbot_native_post_types();
    $label_of = function ( $type ) use ( $native ) {
        if ( ! in_array( $type, $native, true ) ) {
            $object = get_post_type_object( $type );

            if ( $object && ! empty( $object->labels->singular_name ) ) {
                return (string) $object->labels->singular_name;
            }
        }

        return ucfirst( str_replace( '_', ' ', $type ) );
    };

    $options = [];

    foreach ( fabioai_chatbot_extra_post_types() as $type ) {
        if ( post_type_exists( $type ) ) {
            $options[ $type ] = $label_of( $type );
        }
    }

    foreach ( $rows as $row ) {
        if ( ! isset( $options[ $row['post_type'] ] ) ) {
            $options[ $row['post_type'] ] = $label_of( $row['post_type'] ) . ' (inactive)';
        }
    }

    return $options;
}

function fabioai_chatbot_extra_row_html( $index, array $row, array $types ) : string {
    $name = 'fabioai_chatbot_custom_fields[' . $index . ']';

    $html  = '<tr class="fabioai-extra-row"><td><select name="' . esc_attr( $name ) . '[post_type]">';

    foreach ( $types as $slug => $label ) {
        $html .= '<option value="' . esc_attr( $slug ) . '"' . selected( $row['post_type'], $slug, false ) . '>' . esc_html( $label ) . '</option>';
    }

    $html .= '</select></td>';
    $html .= '<td><input type="text" class="fabioai-extra-key" list="fabioai-extra-keys" name="' . esc_attr( $name ) . '[key]" value="' . esc_attr( $row['key'] ) . '" placeholder="e.g. _my_field" autocomplete="off"></td>';
    $html .= '<td><input type="text" name="' . esc_attr( $name ) . '[label]" value="' . esc_attr( $row['label'] ) . '" placeholder="e.g. What does the AI do?" maxlength="120"></td>';
    $html .= '<td><button type="button" class="button-link button-link-delete fabioai-extra-remove">Remove</button></td></tr>';

    return $html;
}

function fabioai_chatbot_render_extra_content_card() : void {
    $rows  = fabioai_chatbot_get_custom_field_rows();
    $types = fabioai_chatbot_extra_type_options( $rows );
    $blank = [
        'post_type' => (string) key( $types ),
        'key'       => '',
        'label'     => '',
    ];

    echo '<div class="fabioai-scan-breakdown-panel fabioai-extra-panel" id="fabioai-chatbot-extra-panel" data-ajax="' . esc_url( admin_url( 'admin-ajax.php' ) ) . '" data-nonce="' . esc_attr( wp_create_nonce( 'fabioai_chatbot_meta_keys' ) ) . '">';
    echo '<h4 class="fabioai-scan-card-title">Custom fields to index</h4>';
    echo '<p class="description">Posts, pages and products are already scanned: this adds the text of their <strong>custom fields</strong> to the chatbot&rsquo;s answers. It works with the fields saved by <strong>ACF</strong>, <strong>Meta Box</strong> (default storage), <strong>Pods</strong> (default storage), <strong>Toolset</strong> and <strong>JetEngine</strong> (default storage). The content types created by <strong>Custom Post Type UI</strong> and these plugins are listed first in the menu below.</p>';
    echo '<p class="description">Choose where the field belongs, pick or type its key, and name it: the name is what the AI reads above the field&rsquo;s text. A custom content type is scanned in full (title, content and chosen fields) as soon as you add a field to it. Fields kept in a separate database table are not supported. Indexed text can appear in chatbot answers, so never map private fields.</p>';

    echo '<table class="widefat striped fabioai-extra-table"><thead><tr>';
    echo '<th>Field belongs to</th><th>Field key</th><th>Name shown to the AI</th><th></th>';
    echo '</tr></thead><tbody>';

    foreach ( $rows as $i => $row ) {
        echo fabioai_chatbot_extra_row_html( $i, $row, $types ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside.
    }

    echo '<tr class="fabioai-extra-empty"' . ( empty( $rows ) ? '' : ' style="display:none;"' ) . '><td colspan="4">No custom field yet.</td></tr>';
    echo '</tbody></table>';

    echo '<template id="fabioai-extra-row-template">' . fabioai_chatbot_extra_row_html( '__INDEX__', $blank, $types ) . '</template>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside.
    echo '<datalist id="fabioai-extra-keys"></datalist>';

    echo '<p><button type="button" class="button" id="fabioai-extra-add">Add a field</button></p>';
    echo '<p class="description">Saving these settings schedules a full scan automatically. After that, changes made to a field are indexed by the next automatic or manual scan.</p>';
    echo '</div>';
}
