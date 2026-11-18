<?php
/**
 * Fabio AI ChatBot Lite
 *
 * @copyright 2026 Fabio-Plugins.com
 * @license GPL-2.0-or-later
 */

if (!defined('ABSPATH')) {
    exit;
}

class FabioAIChatbot_Scan {

    private const LAST_SUCCESSFUL_SCAN_OPTION = 'fabioai_chatbot_last_successful_scan_at';
    private const SCAN_PROGRESS_TRANSIENT      = 'fabioai_chatbot_scan_progress';
    private const PROGRESS_UPDATE_EVERY        = 5;

    /** Types de post toujours scannés par le plugin */
    public const SCANNABLE_POST_TYPES = ['post', 'page', 'product', 'forum', 'topic', 'reply'];

    /**
     * Types de post scannés : types natifs + types personnalisés pour lesquels
     * le webmaster a choisi au moins un champ personnalisé.
     * Voir includes/fabioai-extra-content.php.
     */
    public static function get_scannable_post_types(): array {
        $types = self::SCANNABLE_POST_TYPES;

        if (function_exists('fabioai_chatbot_get_custom_field_rows')) {
            foreach (fabioai_chatbot_get_custom_field_rows() as $row) {
                if (post_type_exists($row['post_type'])) {
                    $types[] = $row['post_type'];
                }
            }
        }

        return array_values(array_unique($types));
    }

    /**
     * Écrit l'état d'avancement du scan (throttlé côté appelant).
     * Utilise un transient (pas une option) : donnée temporaire, pas d'autoload.
     */
    /**
     * Marque immédiatement le scan comme "en cours", avant même le comptage des totaux.
     * Utile pour que la barre s'affiche instantanément (activation ou clic manuel).
     */
    public static function start(string $origin = ''): void {
        self::set_scan_progress(0, 0, '', 'running', $origin);
    }

    /**
     * Lecture de la progression actuelle du scan (pour l'AJAX de polling).
     * Ajoute 'recently_done' pour permettre d'afficher la confirmation même si
     * le navigateur n'a jamais observé l'état 'running' (scan très rapide).
     */
    public static function get_progress(): array {
        $progress = get_transient(self::SCAN_PROGRESS_TRANSIENT);

        if (!is_array($progress)) {
            return [
                'status'        => 'idle',
                'origin'        => '',
                'processed'     => 0,
                'total'         => 0,
                'percent'       => 0,
                'last_title'    => '',
                'items_indexed' => 0,
                'updated_at'    => 0,
                'recently_done' => false,
            ];
        }

        $progress['recently_done'] = (
            ($progress['status'] ?? '') === 'done'
            && (time() - (int) ($progress['updated_at'] ?? 0)) < 20
        );

        return $progress;
    }

    private static function set_scan_progress(
        int $processed,
        int $total,
        string $last_title,
        string $status,
        string $origin = '',
        int $items_indexed = 0
    ): void {
        $percent = ($total > 0) ? (int) round(($processed / $total) * 100) : 0;
        $percent = max(0, min(100, $percent));

        set_transient(self::SCAN_PROGRESS_TRANSIENT, [
            'status'        => $status,
            'origin'        => $origin,
            'processed'     => $processed,
            'total'         => $total,
            'percent'       => $percent,
            'last_title'    => $last_title,
            'items_indexed' => $items_indexed,
            'updated_at'    => time(),
        ], 5 * MINUTE_IN_SECONDS);
    }

    /**
     * Scan complet du contenu
     */
    public function run(string $origin = 'manual'): array {
        $items = [];

        /*
         * 1) POSTS / PAGES / PRODUITS / BBPRESS
         */
        $bbp_post_types = ['forum', 'topic', 'reply'];
        $post_types     = array_values(array_diff(self::get_scannable_post_types(), $bbp_post_types));

      // Requête normale (bbPress exclu volontairement, voir requête séparée ci-dessous)
      $posts = get_posts([
        'post_type'      => $post_types,
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'modified',
        'order'          => 'DESC',
    ]);

      // Requête isolée pour bbPress : son filtre pre_get_posts exclut son propre
      // contenu dès que le post_type est mélangé avec d'autres types (protection
      // interne de bbPress), d'où la nécessité de la requête séparée.
      $bbp_posts = get_posts([
        'post_type'      => $bbp_post_types,
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'modified',
        'order'          => 'DESC',
    ]);

      $posts = array_merge($posts, $bbp_posts);

      // Comptages légers pour le total de progression (pas de fetch complet des objets)
      $total_categories     = (int) wp_count_terms(['taxonomy' => 'category', 'hide_empty' => false]);
      $total_tags           = (int) wp_count_terms(['taxonomy' => 'post_tag', 'hide_empty' => false]);
      $total_product_cats   = taxonomy_exists('product_cat') ? (int) wp_count_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]) : 0;
      $total_product_tags   = taxonomy_exists('product_tag') ? (int) wp_count_terms(['taxonomy' => 'product_tag', 'hide_empty' => false]) : 0;
      $total_items      = count($posts) + $total_categories + $total_tags + $total_product_cats + $total_product_tags;
      $processed        = 0;

      self::set_scan_progress($processed, $total_items, '', 'running', $origin);

      foreach ($posts as $post) {
        $processed++;

        if ($processed % self::PROGRESS_UPDATE_EVERY === 0 || $processed === $total_items) {
            self::set_scan_progress($processed, $total_items, (string) $post->post_title, 'running', $origin);
        }

        if (!empty($post->post_password)) {
            continue;
        }

        $title = get_the_title($post);
        $url   = get_permalink($post);

        $content_raw = (string) $post->post_content;
        $content_raw = ($post->post_type === 'product' ? 'Product name: ' : 'Title: ') . $title . "\n\n" . $content_raw;

        if (!empty($post->post_excerpt)) {
            $content_raw .= "\n\n" . (string) $post->post_excerpt;
        }

        if ($post->post_type === 'product') {
            if (function_exists('wc_get_product')) {
                $product = wc_get_product($post->ID);

                if ($product instanceof WC_Product) {
                    $price          = $product->get_price();
                    $regular_price  = $product->get_regular_price();
                    $sale_price     = $product->get_sale_price();
                    $sku            = $product->get_sku();                        
                    $review_count   = $product->get_review_count();
                    $average_rating = $product->get_average_rating();
                    $price_value      = ($price !== '' && is_numeric($price)) ? (float) $price : null;
                    $regular_value    = ($regular_price !== '' && is_numeric($regular_price)) ? (float) $regular_price : null;
                    $sale_value       = ($sale_price !== '' && is_numeric($sale_price)) ? (float) $sale_price : null;                      
                    $product_type     = $product->get_type();

                    if ($price !== '') {
                        $content_raw .= "\n\nCurrent price: " . $price;
                    }

                    if ($regular_price !== '') {
                        $content_raw .= "\n\nRegular price: " . $regular_price;
                    }

                    if ($sale_price !== '') {
                        $content_raw .= "\n\nSale price: " . $sale_price;
                    }

                    if (
                        $regular_price !== ''
                        && $price !== ''
                        && is_numeric($regular_price)
                        && is_numeric($price)
                        && (float) $regular_price > (float) $price
                    ) {
                        $discount_amount  = (float) $regular_price - (float) $price;
                        $discount_percent = round((($discount_amount / (float) $regular_price) * 100), 2);

                        $content_raw .= "\n\nDiscount amount: " . wc_format_decimal($discount_amount, 2);
                        $content_raw .= "\n\nDiscount percent: " . $discount_percent . '%';
                    }

                    if ($sku !== '') {
                        $content_raw .= "\n\nSKU: " . $sku;
                    }

                    $content_raw .= "\n\nProduct type: " . $product_type;

                    if ($price_value !== null) {
                        $content_raw .= "\n\nPrice value: " . wc_format_decimal($price_value, 2);
                    }

                    if ($regular_value !== null) {
                        $content_raw .= "\n\nRegular price value: " . wc_format_decimal($regular_value, 2);
                    }

                    if ($sale_value !== null) {
                        $content_raw .= "\n\nSale price value: " . wc_format_decimal($sale_value, 2);
                    }

                    $search_tokens = [$title];

                    if ($sku !== '') {
                        $search_tokens[] = $sku;
                    }

                    if ($price_value !== null) {
                        $search_tokens[] = 'price ' . wc_format_decimal($price_value, 2);
                    }

                    if ($regular_value !== null) {
                        $search_tokens[] = 'regular price ' . wc_format_decimal($regular_value, 2);
                    }

                    if ($sale_value !== null) {
                        $search_tokens[] = 'sale price ' . wc_format_decimal($sale_value, 2);
                    }

                    $product_categories = get_the_terms($post->ID, 'product_cat');
                    if ($product_categories && !is_wp_error($product_categories)) {
                        $content_raw .= "\n\nProduct categories: " . implode(', ', wp_list_pluck($product_categories, 'name'));
                    }

                    $product_tags = get_the_terms($post->ID, 'product_tag');
                    if ($product_tags && !is_wp_error($product_tags)) {
                        $content_raw .= "\n\nProduct tags: " . implode(', ', wp_list_pluck($product_tags, 'name'));
                    }

                    $attributes_output = [];
                    foreach ($product->get_attributes() as $attribute) {
                        if ($attribute->is_taxonomy()) {
                            $terms = wc_get_product_terms($post->ID, $attribute->get_name(), ['fields' => 'names']);
                            if (!empty($terms)) {
                                $label = wc_attribute_label($attribute->get_name());
                                $attributes_output[] = $label . ': ' . implode(', ', $terms);
                            }
                        } else {
                            $options = $attribute->get_options();
                            if (!empty($options)) {
                                $label = wc_attribute_label($attribute->get_name());
                                $attributes_output[] = $label . ': ' . implode(', ', $options);
                            }
                        }
                    }

                    if (!empty($attributes_output)) {
                        $content_raw .= "\n\nProduct attributes:\n" . implode("\n", $attributes_output);
                    }

                    if ($product_categories && !is_wp_error($product_categories)) {
                        $search_tokens = array_merge($search_tokens, wp_list_pluck($product_categories, 'name'));
                    }

                    if ($product_tags && !is_wp_error($product_tags)) {
                        $search_tokens = array_merge($search_tokens, wp_list_pluck($product_tags, 'name'));
                    }

                    if (!empty($attributes_output)) {
                        $search_tokens = array_merge($search_tokens, $attributes_output);
                    }

                    $content_raw .= "\n\nSearch text: " . implode(' | ', array_filter(array_unique($search_tokens)));

                    if ($product->is_type('variable')) {
                        $variation_lines = [];

                        foreach ($product->get_children() as $variation_id) {
                            $variation = wc_get_product($variation_id);

                            if (!$variation instanceof WC_Product_Variation) {
                                continue;
                            }

                            $variation_price         = $variation->get_price();
                            $variation_regular_price = $variation->get_regular_price();
                            $variation_sale_price    = $variation->get_sale_price();
                            $variation_sku           = $variation->get_sku();

                            $variation_line = "Variation ID: " . $variation_id;

                            $formatted_attributes = [];
                            $attribute_values     = [];

                            foreach ($variation->get_attributes() as $attr_name => $attr_value) {
                                $taxonomy = str_replace('attribute_', '', $attr_name);
                                $label    = wc_attribute_label($taxonomy);

                                if (taxonomy_exists($taxonomy)) {
                                    $term = get_term_by('slug', $attr_value, $taxonomy);
                                    $attr_value = $term && !is_wp_error($term) ? $term->name : $attr_value;
                                }

                                $formatted_attributes[] = $label . ': ' . $attr_value;
                                $attribute_values[]     = $attr_value;
                            }

                            $variation_name = $title;
                            if (!empty($attribute_values)) {
                                $variation_name .= ' - ' . implode(' - ', $attribute_values);
                            }

                            $variation_line .= ' | Variation name: ' . $variation_name;

                            if (!empty($formatted_attributes)) {
                                $variation_line .= ' | ' . implode(' | ', $formatted_attributes);
                            }

                            if ($variation_price !== '') {
                                $variation_line .= ' | Current price: ' . $variation_price;
                            }

                            if ($variation_regular_price !== '') {
                                $variation_line .= ' | Regular price: ' . $variation_regular_price;
                            }

                            if ($variation_sale_price !== '') {
                                $variation_line .= ' | Sale price: ' . $variation_sale_price;
                            }

                            if (
                                $variation_regular_price !== ''
                                && $variation_price !== ''
                                && is_numeric($variation_regular_price)
                                && is_numeric($variation_price)
                                && (float) $variation_regular_price > (float) $variation_price
                            ) {
                                $variation_discount_amount  = (float) $variation_regular_price - (float) $variation_price;
                                $variation_discount_percent = round((($variation_discount_amount / (float) $variation_regular_price) * 100), 2);

                                $variation_line .= ' | Discount amount: ' . wc_format_decimal($variation_discount_amount, 2);
                                $variation_line .= ' | Discount percent: ' . $variation_discount_percent . '%';
                            }

                            if ($variation_sku !== '') {
                                $variation_line .= ' | SKU: ' . $variation_sku;
                            }

                            if ($variation_price !== '' && is_numeric($variation_price)) {
                                $variation_line .= ' | Price value: ' . wc_format_decimal((float) $variation_price, 2);
                            }

                            if ($variation_regular_price !== '' && is_numeric($variation_regular_price)) {
                                $variation_line .= ' | Regular price value: ' . wc_format_decimal((float) $variation_regular_price, 2);
                            }

                            if ($variation_sale_price !== '' && is_numeric($variation_sale_price)) {
                                $variation_line .= ' | Sale price value: ' . wc_format_decimal((float) $variation_sale_price, 2);
                            }

                            $variation_search_tokens = [$title, $variation_name];

                            if ($variation_sku !== '') {
                                $variation_search_tokens[] = $variation_sku;
                            }

                            if (!empty($attribute_values)) {
                                $variation_search_tokens = array_merge($variation_search_tokens, $attribute_values);
                            }

                            $variation_line .= ' | Search text: ' . implode(' | ', array_filter(array_unique($variation_search_tokens)));

                            $variation_lines[] = $variation_line;
                        }

                        if (!empty($variation_lines)) {
                            $content_raw .= "\n\nVariations:\n" . implode("\n", $variation_lines);
                        }
                    }

                    if (wc_review_ratings_enabled()) {
                        if ($average_rating !== '') {
                            $content_raw .= "\n\nAverage rating: " . $average_rating;
                        }

                        $content_raw .= "\n\nReview count: " . (int) $review_count;
                    }

                    $reviews = get_comments([
                        'post_id' => $post->ID,
                        'status'  => 'approve',
                        'number'  => 10,
                        'orderby' => 'comment_date_gmt',
                        'order'   => 'DESC',
                    ]);

                    if (!empty($reviews)) {
                        $review_lines = [];

                        foreach ($reviews as $review) {
                            $review_text = trim(wp_strip_all_tags((string) $review->comment_content));
                            if ($review_text === '') {
                                continue;
                            }

                            $rating = get_comment_meta($review->comment_ID, 'rating', true);

                            $line = 'Review by ' . $review->comment_author;

                            if ($rating !== '') {
                                $line .= ' | Rating: ' . $rating . '/5';
                            }

                            $line .= ' | ' . $review_text;

                            $review_lines[] = $line;
                        }

                        if (!empty($review_lines)) {
                            $content_raw .= "\n\nProduct reviews:\n" . implode("\n", $review_lines);
                        }
                    }
                }
            }
        }

        if (in_array($post->post_type, ['post', 'page'], true)) {
            $categories = get_the_terms($post->ID, 'category');
            if ($categories && !is_wp_error($categories)) {
                $content_raw .= "\n\nCategories: " . implode(', ', wp_list_pluck($categories, 'name'));
            }

            $tags = get_the_terms($post->ID, 'post_tag');
            if ($tags && !is_wp_error($tags)) {
                $content_raw .= "\n\nTags: " . implode(', ', wp_list_pluck($tags, 'name'));
            }
        }

        if ($post->post_type === 'topic') {
            $forum_id = (int) get_post_meta($post->ID, '_bbp_forum_id', true);
            if ($forum_id > 0) {
                $forum_title = get_the_title($forum_id);
                if ($forum_title) {
                    $content_raw .= "\n\nForum: " . $forum_title;
                }
            }
        }

        if ($post->post_type === 'reply') {
            $forum_id = (int) get_post_meta($post->ID, '_bbp_forum_id', true);
            $topic_id = (int) get_post_meta($post->ID, '_bbp_topic_id', true);

            if ($forum_id > 0) {
                $forum_title = get_the_title($forum_id);
                if ($forum_title) {
                    $content_raw .= "\n\nForum: " . $forum_title;
                }
            }

            if ($topic_id > 0) {
                $topic_title = get_the_title($topic_id);
                if ($topic_title) {
                    $content_raw .= "\n\nTopic: " . $topic_title;
                }
            }
        }

        $content_clean = fabioai_chatbot_clean_content($content_raw);

        // Passages des champs personnalisés choisis (sans longueur minimale).
        $extra_segments = function_exists('fabioai_chatbot_build_extra_segments')
            ? fabioai_chatbot_build_extra_segments($post, (string) $title)
            : [];

        $segments = [];

        if ($content_clean !== '') {
            $min_length = ($post->post_type === 'product') ? 50 : 200;
            $segments   = fabioai_chatbot_segment_content($content_clean, $min_length, 1200);
        }

        if (!empty($extra_segments)) {
            $segments = array_merge($segments, $extra_segments);
        }

        if (empty($segments)) {
            continue;
        }

        $items[] = [
            'id'            => (int) $post->ID,
            'type'          => (string) $post->post_type,
            'title'         => (string) $title,
            'url'           => (string) $url,
            'content_raw'   => $content_raw,
            'content_clean' => $content_clean,
            'segments'      => $segments,
            'modified_gmt'  => (string) get_post_field('post_modified_gmt', $post->ID),
        ];
    }

        /*
         * 2) CATÉGORIES WORDPRESS
         */
        $categories = get_terms([
            'taxonomy'   => 'category',
            'hide_empty' => false,
        ]);

        if (!is_wp_error($categories)) {
            foreach ($categories as $term) {
                $processed++;

                if ($processed % self::PROGRESS_UPDATE_EVERY === 0 || $processed === $total_items) {
                    self::set_scan_progress($processed, $total_items, (string) $term->name, 'running', $origin);
                }

                $content_raw = (string) $term->name;

                if (!empty($term->description)) {
                    $content_raw .= "\n\n" . (string) $term->description;
                }

                $content_raw .= "\n\nTaxonomy: category";
                $content_raw .= "\n\nSlug: " . (string) $term->slug;

                $content_clean = fabioai_chatbot_clean_content($content_raw);

                if ($content_clean === '') {
                    continue;
                }

                $segments = fabioai_chatbot_segment_content($content_clean, 20, 800);

                if (empty($segments)) {
                    continue;
                }

                $items[] = [
                    'id'            => (int) $term->term_id,
                    'type'          => 'category',
                    'title'         => (string) $term->name,
                    'url'           => (string) get_term_link($term),
                    'content_raw'   => $content_raw,
                    'content_clean' => $content_clean,
                    'segments'      => $segments,
                    'modified_gmt'  => '',
                ];
            }
        }

        /*
         * 3) TAGS WORDPRESS
         */
        $tags = get_terms([
            'taxonomy'   => 'post_tag',
            'hide_empty' => false,
        ]);

        if (!is_wp_error($tags)) {
            foreach ($tags as $term) {
                $processed++;

                if ($processed % self::PROGRESS_UPDATE_EVERY === 0 || $processed === $total_items) {
                    self::set_scan_progress($processed, $total_items, (string) $term->name, 'running', $origin);
                }

                $content_raw = (string) $term->name;

                if (!empty($term->description)) {
                    $content_raw .= "\n\n" . (string) $term->description;
                }

                $content_raw .= "\n\nTaxonomy: post_tag";
                $content_raw .= "\n\nSlug: " . (string) $term->slug;

                $content_clean = fabioai_chatbot_clean_content($content_raw);

                if ($content_clean === '') {
                    continue;
                }

                $segments = fabioai_chatbot_segment_content($content_clean, 20, 800);

                if (empty($segments)) {
                    continue;
                }

                $items[] = [
                    'id'            => (int) $term->term_id,
                    'type'          => 'post_tag',
                    'title'         => (string) $term->name,
                    'url'           => (string) get_term_link($term),
                    'content_raw'   => $content_raw,
                    'content_clean' => $content_clean,
                    'segments'      => $segments,
                    'modified_gmt'  => '',
                ];
            }
        }

        /*
         * 4) CATÉGORIES PRODUITS (WooCommerce)
         */
        if (taxonomy_exists('product_cat')) {
            $product_cats = get_terms([
                'taxonomy'   => 'product_cat',
                'hide_empty' => false,
            ]);

            if (!is_wp_error($product_cats)) {
                foreach ($product_cats as $term) {
                    $processed++;

                    if ($processed % self::PROGRESS_UPDATE_EVERY === 0 || $processed === $total_items) {
                        self::set_scan_progress($processed, $total_items, (string) $term->name, 'running', $origin);
                    }

                    $content_raw = (string) $term->name;

                    if (!empty($term->description)) {
                        $content_raw .= "\n\n" . (string) $term->description;
                    }

                    $content_raw .= "\n\nTaxonomy: product_cat";
                    $content_raw .= "\n\nSlug: " . (string) $term->slug;

                    $content_clean = fabioai_chatbot_clean_content($content_raw);

                    if ($content_clean === '') {
                        continue;
                    }

                    $segments = fabioai_chatbot_segment_content($content_clean, 20, 800);

                    if (empty($segments)) {
                        continue;
                    }

                    $items[] = [
                        'id'            => (int) $term->term_id,
                        'type'          => 'product_cat',
                        'title'         => (string) $term->name,
                        'url'           => (string) get_term_link($term),
                        'content_raw'   => $content_raw,
                        'content_clean' => $content_clean,
                        'segments'      => $segments,
                        'modified_gmt'  => '',
                    ];
                }
            }
        }

        /*
         * 5) TAGS PRODUITS (WooCommerce)
         */
        if (taxonomy_exists('product_tag')) {
            $product_tags_terms = get_terms([
                'taxonomy'   => 'product_tag',
                'hide_empty' => false,
            ]);

            if (!is_wp_error($product_tags_terms)) {
                foreach ($product_tags_terms as $term) {
                    $processed++;

                    if ($processed % self::PROGRESS_UPDATE_EVERY === 0 || $processed === $total_items) {
                        self::set_scan_progress($processed, $total_items, (string) $term->name, 'running', $origin);
                    }

                    $content_raw = (string) $term->name;

                    if (!empty($term->description)) {
                        $content_raw .= "\n\n" . (string) $term->description;
                    }

                    $content_raw .= "\n\nTaxonomy: product_tag";
                    $content_raw .= "\n\nSlug: " . (string) $term->slug;

                    $content_clean = fabioai_chatbot_clean_content($content_raw);

                    if ($content_clean === '') {
                        continue;
                    }

                    $segments = fabioai_chatbot_segment_content($content_clean, 20, 800);

                    if (empty($segments)) {
                        continue;
                    }

                    $items[] = [
                        'id'            => (int) $term->term_id,
                        'type'          => 'product_tag',
                        'title'         => (string) $term->name,
                        'url'           => (string) get_term_link($term),
                        'content_raw'   => $content_raw,
                        'content_clean' => $content_clean,
                        'segments'      => $segments,
                        'modified_gmt'  => '',
                    ];
                }
            }
        }

        if ($origin === 'manual') {
            update_option('fabioai_chatbot_last_manual_scan', time());
        } else {
            update_option('fabioai_chatbot_last_auto_scan', time());
        }

        update_option('fabioai_chatbot_last_scan_count', count($items));

        // Répartition par type de contenu (posts, pages, produits, catégories, tags, etc.)
        $breakdown = [];
        foreach ($items as $item) {
            $type = (string) $item['type'];
            $breakdown[$type] = ($breakdown[$type] ?? 0) + 1;
        }
        update_option('fabioai_chatbot_last_scan_breakdown', wp_json_encode($breakdown), false);

        $store_result = fabioai_chatbot_store_segments($items);

        update_option('fabioai_chatbot_last_store_result', wp_json_encode($store_result), false);

        if (empty($store_result['errors'])) {
            update_option(self::LAST_SUCCESSFUL_SCAN_OPTION, current_time('mysql', true));
        }

        self::set_scan_progress($total_items, $total_items, '', 'done', $origin, count($items));

        return $items;
    }
}

/**
 * Nettoyage du contenu brut
 */
function fabioai_chatbot_clean_content(string $content): string {
    $content = strip_shortcodes($content);
    $content = wp_strip_all_tags($content);
    $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $content = preg_replace("/[ \t]+/", ' ', $content);

    return trim($content);
}

/**
 * Segmentation du contenu nettoyé
 */
function fabioai_chatbot_segment_content(
    string $content,
    int $min_length = 200,
    int $max_length = 800
): array {
    $paragraphs = preg_split("/\n\s*\n/", $content);

    $segments = [];
    $current  = '';

    foreach ($paragraphs as $paragraph) {
        $paragraph = trim($paragraph);

        if ($paragraph === '') {
            continue;
        }

        if (mb_strlen($current . ' ' . $paragraph) > $max_length) {
            if (mb_strlen($current) >= $min_length) {
                $segments[] = trim($current);
                $current = $paragraph;
            } else {
                $current .= ' ' . $paragraph;
            }
        } else {
            $current .= ' ' . $paragraph;
        }
    }

    if (mb_strlen(trim($current)) >= $min_length) {
        $segments[] = trim($current);
    }

    return $segments;
}

/**
 * Enregistrement des segments en base
 */
function fabioai_chatbot_store_segments(array $items): array {
    global $wpdb;

    $table = $wpdb->prefix . 'fabioai_chatbot_scan_segments';
    $now   = current_time('mysql');

    $deleted_rows  = 0;
    $inserted_rows = 0;
    $errors        = [];

    $existing_rows = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");

    $truncate_result = $wpdb->query("TRUNCATE TABLE {$table}");

    if ($truncate_result === false) {
        $truncate_error = $wpdb->last_error;

        $delete_result = $wpdb->query("DELETE FROM {$table}");

        if ($delete_result === false) {
            $errors[] = [
                'type'           => 'clear_table_error',
                'truncate_error' => $truncate_error,
                'delete_error'   => $wpdb->last_error,
            ];
        } else {
            $deleted_rows = $existing_rows;
        }
    } else {
        $deleted_rows = $existing_rows;
    }

    if (!empty($errors)) {
        return [
            'items_count'   => count($items),
            'deleted_rows'  => $deleted_rows,
            'inserted_rows' => $inserted_rows,
            'errors'        => $errors,
            'scanned_at'    => $now,
        ];
    }

    foreach ($items as $item) {
        foreach ($item['segments'] as $index => $text) {
            $hash = md5($item['type'] . '|' . $item['id'] . '|' . $index . '|' . $text);

            $inserted = $wpdb->insert(
                $table,
                [
                    'post_id'       => (int) $item['id'],
                    'post_type'     => (string) $item['type'],
                    'title'         => (string) $item['title'],
                    'url'           => (string) $item['url'],
                    'segment_index' => (int) $index,
                    'segment_text'  => (string) $text,
                    'content_hash'  => $hash,
                    'scanned_at'    => $now,
                ]
            );

            if ($inserted === false) {
                $errors[] = [
                    'type'          => 'insert_error',
                    'post_id'       => (int) $item['id'],
                    'post_type'     => (string) $item['type'],
                    'segment_index' => (int) $index,
                    'error'         => $wpdb->last_error,
                    'hash'          => $hash,
                ];
            } else {
                $inserted_rows += 1;
            }
        }
    }

    return [
        'items_count'   => count($items),
        'deleted_rows'  => $deleted_rows,
        'inserted_rows' => $inserted_rows,
        'errors'        => $errors,
        'scanned_at'    => $now,
    ];
}