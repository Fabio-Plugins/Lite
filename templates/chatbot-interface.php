<?php
if (!defined('ABSPATH')) {
  exit;
}

$enable_gradients = get_option('fabioai_chatbot_enable_gradients', 'no');
$gradient_style = get_option('fabioai_chatbot_gradient_style', 'soft');

$allowed_gradient_styles = ['soft', 'vivid', 'premium'];

if (!in_array($gradient_style, $allowed_gradient_styles, true)) {
  $gradient_style = 'soft';
}

$chatbot_classes = [];

if ($enable_gradients === 'yes') {
  $chatbot_classes[] = 'fabioai-chatbot-gradients-enabled';
  $chatbot_classes[] = 'fabioai-chatbot-gradient-' . $gradient_style;
}

$shadow_style = get_option('fabioai_chatbot_shadow_style', 'soft');
$allowed_shadow_styles = ['none', 'soft', 'elevated', 'glow'];

if (!in_array($shadow_style, $allowed_shadow_styles, true)) {
  $shadow_style = 'soft';
}

$chatbot_classes[] = 'fabioai-chatbot-shadow-' . $shadow_style;
?>

<div id="fabioai-chatbot" class="<?php echo esc_attr(implode(' ', $chatbot_classes)); ?>">
  <div class="fabioai-chatbot-inner">

    <div id="fabioai-chatbot-chat-messages"></div>

    <!-- Loader + timer -->
    <div id="fabioai-chatbot-thinking" style="display:none;">
      <div class="fabioai-chatbot-thinking-icon"></div>
      <div class="fabioai-chatbot-timer">0.0s</div>
    </div>

    <form id="fabioai-chatbot-chat-form">
      <input
      type="text"
      id="fabioai-chatbot-chat-input"
      placeholder="→"
      autocomplete="off"
      required
      />
      <button type="submit" aria-label="Send message"></button>
    </form>

    <button id="fabioai-chatbot-close-chat" class="fabioai-chatbot-close-chat" type="button" aria-label="Close chat">
      ✕
    </button>

  </div>
</div>