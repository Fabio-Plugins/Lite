<?php
if (!defined('ABSPATH')) {
    exit;
}

function fabioai_chatbot_render_admin_page() {

    $welcome_text = get_option('fabioai_chatbot_welcome_text', '');

    $current_provider = get_option('fabioai_chatbot_provider', 'openai');

    $openai_api_key = get_option('fabioai_chatbot_openai_api_key', '');
    $gemini_api_key = get_option('fabioai_chatbot_gemini_api_key', '');
    $mistral_api_key = get_option('fabioai_chatbot_mistral_api_key', '');
    $anthropic_api_key = get_option('fabioai_chatbot_anthropic_api_key', '');

    $display_mode = get_option('fabioai_chatbot_display_mode', 'manual');
    $insert_position = get_option('fabioai_chatbot_insert_position', 'top');
    $bubble_position = get_option('fabioai_chatbot_bubble_position', 'right');

    $bbpress_mode = get_option('fabioai_chatbot_bbpress_mode', 'same');
    if (!in_array($bbpress_mode, ['same', 'auto', 'bubble'], true)) {
        $bbpress_mode = 'same';
    }

    $enable_gradients = get_option('fabioai_chatbot_enable_gradients', 'no');
    $gradient_style = get_option('fabioai_chatbot_gradient_style', 'soft');
    $shadow_style = get_option('fabioai_chatbot_shadow_style', 'soft');

    $scan_summary = fabioai_chatbot_get_scan_summary();
    $last_scan_text = $scan_summary['last_manual_scan_text'];
    $last_auto_scan_text = $scan_summary['last_auto_scan_text'];
    $next_auto_scan_text = $scan_summary['next_auto_scan_text'];
    $last_scan_count = $scan_summary['last_scan_count'];

    echo '<div class="wrap">';
    echo '<h1>Fabio AI ChatBot Lite</h1>';

    if (isset($_GET['scan']) && $_GET['scan'] === 'done') {
        echo '<div class="notice notice-success"><p>Scan successful</p></div>';
    }

    echo '<form method="post" action="options.php">';

    // Security fields for WordPress Settings API
    settings_fields('fabioai_chatbot_settings');
    do_settings_sections('fabioai_chatbot_settings');

    echo '<table class="form-table">';

    // ==========================================================
    // STEP 1: WEBSITE SCAN
    // ==========================================================
    echo '<tr><th colspan="2"><h2>Step 1: Website Scan</h2><p class="description"><a href="https://fabio-plugins.com/installation-guide/#step-03" target="_blank">Check our installation guide</a></p></th></tr>';

    echo '<tr><td colspan="2">';

    echo '<div class="fabioai-scan-cards">';

    // --- Automatic scan card ---
    echo '  <div class="fabioai-scan-card">';
    echo '      <h4 class="fabioai-scan-card-title">Automatic scan</h4>';
    echo '      <p class="description">The automatic scan runs every 24 hours, starting from the moment you activated the plugin.</p>';
    echo '      <p class="fabioai-scan-meta">Last automatic scan: <strong><span id="fabioai-chatbot-last-auto-scan">' . esc_html($last_auto_scan_text) . '</span></strong></p>';
    echo '      <p class="fabioai-scan-meta">Next automatic scan: <strong><span id="fabioai-chatbot-next-auto-scan">' . esc_html($next_auto_scan_text) . '</span></strong></p>';
    echo '  </div>';

    // --- Manual scan card ---
    echo '  <div class="fabioai-scan-card">';
    echo '      <h4 class="fabioai-scan-card-title">Manual scan</h4>';
    echo '      <p class="description">Use the manual scan when you have updated content.</p>';
    echo '      <button type="button" id="fabioai-chatbot-run-scan" class="button button-primary">Run Manual Scan</button>';
    echo '      <p class="fabioai-scan-meta">Last manual scan: <strong><span id="fabioai-chatbot-last-manual-scan">' . esc_html($last_scan_text) . '</span></strong></p>';
    echo '  </div>';

    echo '</div>';

    // --- Shared breakdown (reflects the latest scan, whether automatic or manual) ---
    echo '<div class="fabioai-scan-breakdown-panel" id="fabioai-chatbot-scan-breakdown-panel">';
    echo '  <h4 class="fabioai-scan-card-title">Indexed content</h4>';
    echo '  <p class="description">Minimum content length to be scanned: 50 characters for products, 200 characters for everything else.</p>';

    if (empty($scan_summary['breakdown'])) {
        echo '  <p class="fabioai-scan-meta" id="fabioai-chatbot-scan-breakdown-empty">No content indexed yet.</p>';
    } else {
        echo '  <p class="fabioai-scan-meta" id="fabioai-chatbot-scan-breakdown-empty" style="display:none;">No content indexed yet.</p>';
    }

    echo '  <p class="fabioai-scan-meta">Total: <strong><span id="fabioai-chatbot-total-indexed">' . intval($last_scan_count) . '</span></strong> contents indexed</p>';
    echo '  <ul class="fabioai-scan-breakdown-list" id="fabioai-chatbot-scan-breakdown-list">';
    foreach ($scan_summary['breakdown'] as $entry) {
        echo '<li><span class="fabioai-scan-breakdown-label">' . esc_html($entry['label']) . '</span><strong class="fabioai-scan-breakdown-count">' . intval($entry['count']) . '</strong></li>';
    }
    echo '  </ul>';
    echo '</div>';

    // --- Shared progress status (applies to both automatic and manual scans) ---
    echo '<div id="fabioai-chatbot-scan-progress" class="fabioai-scan-progress" style="display:none;" aria-live="polite">';
    echo '  <div class="fabioai-scan-progress-header">';
    echo '      <span class="fabioai-scan-progress-icon" aria-hidden="true"></span>';
    echo '      <span class="fabioai-scan-progress-label">Scanning your content&hellip;</span>';
    echo '      <span class="fabioai-scan-progress-percent">0%</span>';
    echo '  </div>';
    echo '  <div class="fabioai-scan-progress-track">';
    echo '      <div class="fabioai-scan-progress-bar" style="width:0%;"></div>';
    echo '  </div>';
    echo '  <div class="fabioai-scan-progress-detail"></div>';
    echo '</div>';

    // --- Custom fields to index (includes/fabioai-extra-content.php) ---
    if (function_exists('fabioai_chatbot_render_extra_content_card')) {
        fabioai_chatbot_render_extra_content_card();
    }

    echo '</td></tr>';

    // ==========================================================
    // STEP 2: API KEYS
    // ==========================================================
    echo '<tr><th colspan="2"><h2>Step 2: API Keys</h2></th></tr>';

    // AI Provider
    echo '<tr>';
    echo '<th scope="row">AI Provider <a href="https://fabio-plugins.com/installation-guide/#step-04" target="_blank"> Check our installation guide</a></th>';
    echo '<td>';
    echo '<select name="fabioai_chatbot_provider" id="fabioai-chatbot-provider">';
    echo '<option value="openai"' . selected($current_provider, 'openai', false) . '>OpenAI</option>';
    echo '<option value="gemini"' . selected($current_provider, 'gemini', false) . '>Gemini</option>';
    echo '<option value="mistral"' . selected($current_provider, 'mistral', false) . '>Mistral</option>';
    echo '<option value="anthropic"' . selected($current_provider, 'anthropic', false) . '>Anthropic (Claude)</option>';
    echo '</select>';
    echo '<p class="description">Choose the AI provider used by the chatbot.</p>';
    echo '</td>';
    echo '</tr>';

    // Model
    echo '<tr>';
    echo '<th scope="row">AI Model</th>';
    echo '<td>';
    echo '<select name="fabioai_chatbot_model" id="fabioai-chatbot-model"></select>';
    echo '<p class="description">The available models change automatically depending on the provider selected above.</p>';
    echo '</td>';
    echo '</tr>';

    // OpenAI API Key
    echo '<tr>';
    echo '<th scope="row">OpenAI API Key</th>';
    echo '<td>';
    echo '<input
    type="password"
    name="fabioai_chatbot_openai_api_key"
    class="regular-text"
    value="' . esc_attr($openai_api_key) . '"
    autocomplete="off"
    />';
    echo '<p class="description">Used only if OpenAI is selected.</p>';
    echo '</td>';
    echo '</tr>';

    // Gemini API Key
    echo '<tr>';
    echo '<th scope="row">Gemini API Key</th>';
    echo '<td>';
    echo '<input
    type="password"
    name="fabioai_chatbot_gemini_api_key"
    class="regular-text"
    value="' . esc_attr($gemini_api_key) . '"
    autocomplete="off"
    />';
    echo '<p class="description">Used only if Gemini is selected.</p>';
    echo '</td>';
    echo '</tr>';

    // Mistral API Key
    echo '<tr>';
    echo '<th scope="row">Mistral API Key</th>';
    echo '<td>';
    echo '<input
    type="password"
    name="fabioai_chatbot_mistral_api_key"
    class="regular-text"
    value="' . esc_attr($mistral_api_key) . '"
    autocomplete="off"
    />';
    echo '<p class="description">Used only if Mistral is selected.</p>';
    echo '</td>';
    echo '</tr>';

    // Anthropic API Key
    echo '<tr>';
    echo '<th scope="row">Anthropic API Key</th>';
    echo '<td>';
    echo '<input
    type="password"
    name="fabioai_chatbot_anthropic_api_key"
    class="regular-text"
    value="' . esc_attr($anthropic_api_key) . '"
    autocomplete="off"
    />';
    echo '<p class="description">Used only if Anthropic (Claude) is selected.</p>';
    echo '</td>';
    echo '</tr>';

    // ==========================================================
    // STEP 3: COLORS, SHADOW & GRADIENT
    // ==========================================================
    echo '<tr><th colspan="2"><h2>Step 3: Colors, Shadow &amp; Gradient</h2></th></tr>';

    // Bubble Color
    echo '<tr id="fabioai-chatbot-bubble-color-row">';
    echo '<th scope="row">Bubble color <a href="https://fabio-plugins.com/installation-guide/#step-05" target="_blank"> Check our installation guide</a></th>';
    echo '<td>';

    $bubble_color = get_option('fabioai_chatbot_bubble_color', '#ff6a00');

    echo '<input
    type="text"
    name="fabioai_chatbot_bubble_color"
    value="' . esc_attr($bubble_color) . '"
    class="fabioai-chatbot-color-field"
    data-default-color="#ff6a00"
    />';

    echo '<p class="description">
    Choose the color of the floating bubble.
    </p>';

    echo '</td>';
    echo '</tr>';

    // Border Color
    echo '<tr>';
    echo '<th scope="row">Chatbot border color</th>';
    echo '<td>';

    $border_color = get_option('fabioai_chatbot_border_color', '#ff6a00');

    echo '<input
    type="text"
    name="fabioai_chatbot_border_color"
    value="' . esc_attr($border_color) . '"
    class="fabioai-chatbot-color-field"
    data-default-color="#ff6a00"
    />';

    echo '<p class="description">
    Color of the chatbot container border.
    </p>';

    echo '</td>';
    echo '</tr>';

    // Chatbot Background Color
    echo '<tr>';
    echo '<th scope="row">Chatbot background color</th>';
    echo '<td>';

    $chatbot_bg_color = get_option('fabioai_chatbot_background_color', '#ffffff');

    echo '<input
    type="text"
    name="fabioai_chatbot_background_color"
    value="' . esc_attr($chatbot_bg_color) . '"
    class="fabioai-chatbot-color-field"
    data-default-color="#ffffff"
    />';

    echo '<p class="description">
    Background color of the chatbot container.
    </p>';

    echo '</td>';
    echo '</tr>';

    // User Bubble Background
    echo '<tr>';
    echo '<th scope="row">User message background</th>';
    echo '<td>';

    $user_bg = get_option('fabioai_chatbot_user_bg', '#6c5ce7');

    echo '<input
    type="text"
    name="fabioai_chatbot_user_bg"
    value="' . esc_attr($user_bg) . '"
    class="fabioai-chatbot-color-field"
    data-default-color="#6c5ce7"
    />';

    echo '<p class="description">
    Background color of user messages.
    </p>';

    echo '</td>';
    echo '</tr>';

    // Bot Bubble Background
    echo '<tr>';
    echo '<th scope="row">Bot message background</th>';
    echo '<td>';

    $bot_bg = get_option('fabioai_chatbot_bot_bg', '#f4f6ff');

    echo '<input
    type="text"
    name="fabioai_chatbot_bot_bg"
    value="' . esc_attr($bot_bg) . '"
    class="fabioai-chatbot-color-field"
    data-default-color="#f4f6ff"
    />';

    echo '<p class="description">
    Background color of bot messages.
    </p>';

    echo '</td>';
    echo '</tr>';

    // Button Background
    echo '<tr>';
    echo '<th scope="row">Send button color</th>';
    echo '<td>';

    $button_bg = get_option('fabioai_chatbot_button_bg', '#38bdf8');

    echo '<input
    type="text"
    name="fabioai_chatbot_button_bg"
    value="' . esc_attr($button_bg) . '"
    class="fabioai-chatbot-color-field"
    data-default-color="#38bdf8"
    />';

    echo '<p class="description">
    Background color of the send button.
    </p>';

    echo '</td>';
    echo '</tr>';

    // Enable gradients
    echo '<tr>';
    echo '<th scope="row">Enable gradients</th>';
    echo '<td>';

    echo '<label>';
    echo '<input type="checkbox" name="fabioai_chatbot_enable_gradients" value="yes" ' . checked($enable_gradients, 'yes', false) . '> ';
    echo 'Apply gradient finish to customizable chatbot colors';
    echo '</label>';

    echo '<p class="description">
    When enabled, gradients are applied automatically to the chatbot background, border, message bubbles, send button, floating bubble and input background.
    </p>';

    echo '</td>';
    echo '</tr>';

    // Gradient style
    echo '<tr>';
    echo '<th scope="row">Gradient style</th>';
    echo '<td>';

    echo '<select name="fabioai_chatbot_gradient_style">';
    echo '<option value="soft" ' . selected($gradient_style, 'soft', false) . '>Soft</option>';
    echo '<option value="vivid" ' . selected($gradient_style, 'vivid', false) . '>Vivid</option>';
    echo '<option value="premium" ' . selected($gradient_style, 'premium', false) . '>Premium</option>';
    echo '</select>';

    echo '<p class="description">
    Choose how strong the gradient finish should be. Direction is diagonal by default.
    </p>';

    echo '</td>';
    echo '</tr>';

    // Shadow & Elevation
    echo '<tr>';
    echo '<th scope="row">Shadow &amp; Elevation</th>';
    echo '<td>';

    echo '<select name="fabioai_chatbot_shadow_style">';
    echo '<option value="none" ' . selected($shadow_style, 'none', false) . '>None</option>';
    echo '<option value="soft" ' . selected($shadow_style, 'soft', false) . '>Soft</option>';
    echo '<option value="elevated" ' . selected($shadow_style, 'elevated', false) . '>Elevated</option>';
    echo '<option value="glow" ' . selected($shadow_style, 'glow', false) . '>Glow</option>';
    echo '</select>';

    echo '<p class="description">
    Choose the shadow depth applied to the chatbot window and the floating bubble.
    </p>';

    echo '</td>';
    echo '</tr>';

    // ==========================================================
    // STEP 4: DISPLAY MODE
    // ==========================================================
    echo '<tr><th colspan="2"><h2>Step 4: Display Mode</h2></th></tr>';

    echo '<tr>';
    echo '<th scope="row">Display mode <a href="https://fabio-plugins.com/installation-guide/#step-06" target="_blank">Check our installation guide</a></th>';
    echo '<td>';

    echo '<label><input type="radio" name="fabioai_chatbot_display_mode" value="manual" ' . checked($display_mode, 'manual', false) . '> Shortcode manual : add [fabioai_chatbot] anywhere</label><br>';

    echo '<label><input type="radio" name="fabioai_chatbot_display_mode" value="auto" ' . checked($display_mode, 'auto', false) . '> Automatic insertion</label><br>';

    echo '<label><input type="radio" name="fabioai_chatbot_display_mode" value="bubble" ' . checked($display_mode, 'bubble', false) . '> Floating bubble</label>';

    echo '</td>';
    echo '</tr>';

    echo '<tr id="fabioai-chatbot-insertion-row">';
    echo '<th scope="row">Insertion settings</th>';
    echo '<td>';

    echo '<select name="fabioai_chatbot_insert_position">';

    echo '<option value="top" ' . selected($insert_position, 'top', false) . '>Top of content</option>';
    echo '<option value="bottom" ' . selected($insert_position, 'bottom', false) . '>Bottom of content</option>';

    /* BEFORE H2 */
    echo '<option value="before_h2_1" ' . selected($insert_position, 'before_h2_1', false) . '>Before first H2</option>';
    echo '<option value="before_h2_2" ' . selected($insert_position, 'before_h2_2', false) . '>Before second H2</option>';
    echo '<option value="before_h2_3" ' . selected($insert_position, 'before_h2_3', false) . '>Before third H2</option>';
    echo '<option value="before_h2_4" ' . selected($insert_position, 'before_h2_4', false) . '>Before fourth H2</option>';

    /* AFTER H2 */
    echo '<option value="after_h2_1" ' . selected($insert_position, 'after_h2_1', false) . '>After first H2</option>';
    echo '<option value="after_h2_2" ' . selected($insert_position, 'after_h2_2', false) . '>After second H2</option>';
    echo '<option value="after_h2_3" ' . selected($insert_position, 'after_h2_3', false) . '>After third H2</option>';
    echo '<option value="after_h2_4" ' . selected($insert_position, 'after_h2_4', false) . '>After fourth H2</option>';

    /* BEFORE H3 */
    echo '<option value="before_h3_1" ' . selected($insert_position, 'before_h3_1', false) . '>Before first H3</option>';
    echo '<option value="before_h3_2" ' . selected($insert_position, 'before_h3_2', false) . '>Before second H3</option>';
    echo '<option value="before_h3_3" ' . selected($insert_position, 'before_h3_3', false) . '>Before third H3</option>';
    echo '<option value="before_h3_4" ' . selected($insert_position, 'before_h3_4', false) . '>Before fourth H3</option>';

    /* AFTER H3 */
    echo '<option value="after_h3_1" ' . selected($insert_position, 'after_h3_1', false) . '>After first H3</option>';
    echo '<option value="after_h3_2" ' . selected($insert_position, 'after_h3_2', false) . '>After second H3</option>';
    echo '<option value="after_h3_3" ' . selected($insert_position, 'after_h3_3', false) . '>After third H3</option>';
    echo '<option value="after_h3_4" ' . selected($insert_position, 'after_h3_4', false) . '>After fourth H3</option>';

    echo '</select>';

    echo '</td>';
    echo '</tr>';

    echo '<tr id="fabioai-chatbot-bubble-row">';
    echo '<th scope="row">Bubble position</th>';
    echo '<td>';

    echo '<label><input type="radio" name="fabioai_chatbot_bubble_position" value="right" ' . checked($bubble_position, 'right', false) . '> Bottom right</label><br>';

    echo '<label><input type="radio" name="fabioai_chatbot_bubble_position" value="left" ' . checked($bubble_position, 'left', false) . '> Bottom left</label>';

    echo '</td>';
    echo '</tr>';

    // bbPress forum pages (shown only when bbPress is active)
    if (function_exists('bbpress')) {
        echo '<tr id="fabioai-chatbot-bbpress-row">';
        echo '<th scope="row">bbPress forum pages</th>';
        echo '<td>';

        echo '<label><input type="radio" name="fabioai_chatbot_bbpress_mode" value="same" ' . checked($bbpress_mode, 'same', false) . '> Same as above</label><br>';

        echo '<label><input type="radio" name="fabioai_chatbot_bbpress_mode" value="auto" ' . checked($bbpress_mode, 'auto', false) . '> Automatic insertion (at the top of the forum home, each forum, topic and reply page)</label><br>';

        echo '<label><input type="radio" name="fabioai_chatbot_bbpress_mode" value="bubble" ' . checked($bbpress_mode, 'bubble', false) . '> Floating bubble (uses the bubble position above)</label>';

        echo '<p class="description">Applies only to bbPress pages and overrides the display mode above there. If you add a heading to the forum with a bbPress hook, the chatbot is shown right after it.</p>';

        echo '</td>';
        echo '</tr>';
    }

    // ==========================================================
    // STEP 5: WELCOME MESSAGE
    // ==========================================================
    echo '<tr><th colspan="2"><h2>Step 5: Welcome Message</h2></th></tr>';

    echo '<tr>';
    echo '<th scope="row">Welcome text <a href="https://fabio-plugins.com/installation-guide/#step-07" target="_blank">Check our installation guide</a></th>';
    echo '<td>';
    echo '<textarea name="fabioai_chatbot_welcome_text" rows="3" cols="50">'
    . esc_textarea($welcome_text) .
    '</textarea>';
    echo '</td>';
    echo '</tr>';

    echo '</table>';

    echo '<p><button type="submit" class="button button-primary">Save settings</button></p>';

    echo '</form>';

    echo '</div>';

}
