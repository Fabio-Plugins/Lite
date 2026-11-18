<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Indicateurs du haut de la page Analytics (dernières 24 heures).
 */
class FabioAIChatbot_Analytics {

    private $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'fabioai_chatbot_conversations';
    }

    /**
     * Nombre de questions et de questions uniques sur les dernières 24 heures.
     *
     * @param string|null $search_keywords Mots-clés séparés par des virgules.
     */
    public function get_analytics($search_keywords = null) {

        global $wpdb;

        // Clé de cache unique. La "version" change à chaque nouvelle conversation
        // enregistrée : le cache est ainsi invalidé tout seul.
        $conversations_version = get_option('fabioai_chatbot_conversations_version', '0');
        $cache_key = 'fabioai_chatbot_analytics_24h_' . md5($search_keywords . '|' . $conversations_version);
        $cached_data = get_transient($cache_key);
        if ($cached_data !== false) {
            return $cached_data;
        }

        // Les dates sont enregistrées à l'heure du site (current_time) :
        // on compare donc avec l'heure du site et non celle du serveur MySQL.
        $since = $wpdb->prepare('AND user_time >= %s', wp_date('Y-m-d H:i:s', time() - DAY_IN_SECONDS));

        // $since et la recherche sont construits avec $wpdb->prepare().
        $search_where = $this->build_search_where($search_keywords);

        $rows = $wpdb->get_col(
            "SELECT user_message
            FROM {$this->table}
            WHERE user_message != ''
            $since
            $search_where"
        );

        $total_questions = 0;
        $unique = [];

        foreach ((array) $rows as $message) {

            $q = $this->normalize_question(trim((string) $message));

            if ($q === '') continue;

            $total_questions++;
            $unique[$q] = true;
        }

        $result_data = [
            'kpis' => [
                'total_questions'  => $total_questions,
                'unique_questions' => count($unique),
            ],
        ];

        // Cache d'1 heure
        set_transient($cache_key, $result_data, 3600);

        return $result_data;
    }

    /**
     * Recherche par mots-clés (séparés par des virgules), requêtes préparées.
     */
    private function build_search_where($search_keywords) {

        global $wpdb;

        if (empty($search_keywords) || !is_string($search_keywords)) {
            return '';
        }

        $conditions = [];

        foreach (array_map('trim', explode(',', $search_keywords)) as $keyword) {
            if ($keyword !== '') {
                $conditions[] = $wpdb->prepare(
                    'LOWER(user_message) LIKE LOWER(%s)',
                    '%' . $wpdb->esc_like($keyword) . '%'
                );
            }
        }

        return empty($conditions) ? '' : 'AND (' . implode(' OR ', $conditions) . ')';
    }

    private function normalize_question($text) {

        $text = strtolower($text);
        $text = trim($text);

        // supprimer ponctuation simple
        $text = preg_replace('/[^\w\s]/u', '', $text);

        // supprimer espaces multiples
        $text = preg_replace('/\s+/', ' ', $text);

        return $text;
    }
}
