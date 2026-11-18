<?php
/**
 * Fabio AI ChatBot Lite
 *
 * Analytics : liste des conversations, intentions des visiteurs et nuage de mots.
 *
 * Une "conversation" = une session de navigation (identifiant session_id généré par
 * le navigateur). Les anciennes lignes enregistrées avant la mise à jour n'ont pas de
 * session : chacune est affichée seule, sans suivi des clics.
 *
 * @copyright 2026 Fabio-Plugins.com
 * @license GPL-2.0-or-later
 */

if (!defined('ABSPATH')) {
    exit;
}

class FabioAIChatbot_Conversations {

    // Nombre de conversations par page dans la liste.
    const PER_PAGE = 20;

    // Nombre maximum d'échanges affichés dans le panneau d'une conversation.
    const MAX_MESSAGES = 100;

    // Nombre maximum de questions analysées pour le nuage de mots (les plus récentes de la période).
    const WORD_CLOUD_MAX_QUESTIONS = 5000;

    // Nombre maximum de mots affichés dans le nuage.
    const WORD_CLOUD_MAX_WORDS = 60;

    // Nombre de questions classées par appel IA lors de l'analyse des anciennes conversations.
    const INTENT_BATCH_SIZE = 25;

    private $table;
    private $clicks_table;

    public function __construct() {
        global $wpdb;
        $this->table        = $wpdb->prefix . 'fabioai_chatbot_conversations';
        $this->clicks_table = $wpdb->prefix . 'fabioai_chatbot_clicks';
    }

    // =========================================================
    // LISTE DES CONVERSATIONS
    // =========================================================

    /**
     * Liste paginée des conversations + indicateurs de la période.
     *
     * @param string      $range           24h | 30d | custom | all
     * @param string|null $date_from       AAAA-MM-JJ (custom)
     * @param string|null $date_to         AAAA-MM-JJ (custom)
     * @param string      $sort            date_desc | date_asc | clicks_desc | clicks_asc
     * @param string      $click_filter    all | clicked | none
     * @param int         $page            Numéro de page (1 = première)
     * @param string|null $search_keywords Mots-clés séparés par des virgules (cherchés dans les questions)
     * @param string      $intent          Code d'intention ou chaîne vide
     * @return array
     */
    public function get_conversations($range, $date_from, $date_to, $sort, $click_filter, $page, $search_keywords = null, $intent = '') {

        global $wpdb;

        $where = $this->build_date_where($range, $date_from, $date_to);

        if (!in_array($sort, ['date_desc', 'date_asc', 'clicks_desc', 'clicks_asc'], true)) {
            $sort = 'date_desc';
        }

        if (!in_array($click_filter, ['all', 'clicked', 'none'], true)) {
            $click_filter = 'all';
        }

        // Filtre d'intention : liste blanche uniquement.
        $intent = in_array($intent, FabioAIChatbot_Shortcodes::INTENT_CODES, true) ? $intent : '';

        $page     = max(1, (int) $page);
        $per_page = self::PER_PAGE;
        $offset   = ($page - 1) * $per_page;

        // Clé de regroupement : la session, ou la ligne elle-même pour les anciennes conversations.
        $group_key = "IF(c.session_id = '', CONCAT('legacy-', c.id), c.session_id)";

        // Nombre de liens distincts cliqués par session.
        $clicks_by_session = "SELECT session_id, COUNT(DISTINCT CONCAT(conversation_id, '|', link_url)) AS clicked
            FROM {$this->clicks_table}
            GROUP BY session_id";

        // Mots-clés : une conversation est gardée si l'une de ses questions en contient un.
        $search = $this->build_search_condition($search_keywords);

        $having_parts = [];
        if ($click_filter === 'clicked') {
            $having_parts[] = 'clicked > 0';
        } elseif ($click_filter === 'none') {
            $having_parts[] = 'clicked = 0';
        }
        if ($search !== '') {
            $having_parts[] = "SUM(CASE WHEN {$search} THEN 1 ELSE 0 END) > 0";
        }
        $having_no_intent = empty($having_parts) ? '' : 'HAVING ' . implode(' AND ', $having_parts);

        // Intention choisie : la conversation doit contenir au moins une question de cette intention.
        $intent_hits = '0';
        if ($intent !== '') {
            $intent_hits    = "SUM(CASE WHEN c.intent = '{$intent}' THEN 1 ELSE 0 END)";
            $having_parts[] = "{$intent_hits} > 0";
        }
        $having = empty($having_parts) ? '' : 'HAVING ' . implode(' AND ', $having_parts);

        $from_base = "FROM {$this->table} c
            LEFT JOIN ({$clicks_by_session}) k ON k.session_id = c.session_id AND c.session_id <> ''
            WHERE c.user_message <> ''
            $where
            GROUP BY gk";

        $from_sql = $from_base . "\n            " . $having;

        if ($sort === 'date_asc') {
            $secondary = 'started ASC, first_id ASC';
        } elseif ($sort === 'clicks_desc') {
            $secondary = 'clicked DESC, started DESC';
        } elseif ($sort === 'clicks_asc') {
            $secondary = 'clicked ASC, started DESC';
        } else {
            $secondary = 'started DESC, first_id DESC';
        }

        // Avec une intention choisie : d'abord les conversations qui en contiennent le plus.
        $order_sql = 'ORDER BY ' . ($intent !== '' ? 'intent_hits DESC, ' : '') . $secondary;

        // Nombre de conversations par intention (mêmes filtres, sauf l'intention elle-même).
        $intent_cols = '';
        $intent_sums = '';
        foreach (FabioAIChatbot_Shortcodes::INTENT_CODES as $code) {
            $intent_cols .= ", SUM(CASE WHEN c.intent = '{$code}' THEN 1 ELSE 0 END) AS i_{$code}";
            $intent_sums .= ", COALESCE(SUM(i_{$code} > 0), 0) AS n_{$code}";
        }

        $count_row = $wpdb->get_row(
            "SELECT COUNT(*) AS total{$intent_sums} FROM (
                SELECT {$group_key} AS gk, COALESCE(MAX(k.clicked), 0) AS clicked{$intent_cols}
                {$from_base}
                {$having_no_intent}
            ) t"
        );

        $intent_counts = [];
        foreach (FabioAIChatbot_Shortcodes::INTENT_CODES as $code) {
            $prop = 'n_' . $code;
            $intent_counts[$code] = $count_row && isset($count_row->$prop) ? (int) $count_row->$prop : 0;
        }
        $all_count = $count_row ? (int) $count_row->total : 0;

        // Total (pour la pagination).
        $total_items = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM (
                SELECT {$group_key} AS gk, COALESCE(MAX(k.clicked), 0) AS clicked
                {$from_sql}
            ) t"
        );

        $total_pages = max(1, (int) ceil($total_items / $per_page));

        $groups = $wpdb->get_results(
            "SELECT {$group_key} AS gk,
                    MIN(c.id) AS first_id,
                    MIN(c.user_time) AS started,
                    MAX(c.user_time) AS last_time,
                    COUNT(*) AS messages,
                    COALESCE(MAX(k.clicked), 0) AS clicked,
                    {$intent_hits} AS intent_hits
            {$from_sql}
            {$order_sql}
            LIMIT " . (int) $per_page . " OFFSET " . (int) $offset
        );

        $rows = $this->build_rows(is_array($groups) ? $groups : []);

        return [
            'rows'          => $rows,
            'pagination'    => [
                'current_page' => $page,
                'per_page'     => $per_page,
                'total_items'  => $total_items,
                'total_pages'  => $total_pages,
            ],
            'intent_counts' => $intent_counts,
            'all_count'     => $all_count,
            'intent'        => $intent,
            'kpis'          => $this->get_kpis($where . ($search !== '' ? " AND {$search}" : '')),
            'notice'        => $this->get_notice(),
        ];
    }

    /**
     * Condition SQL des mots-clés (séparés par des virgules, OU entre eux), ou chaîne vide.
     */
    private function build_search_condition($search_keywords) {

        global $wpdb;

        if (!is_string($search_keywords) || trim($search_keywords) === '') {
            return '';
        }

        $conditions = [];

        foreach (array_slice(explode(',', $search_keywords), 0, 10) as $keyword) {

            $keyword = trim(mb_substr($keyword, 0, 100));

            if ($keyword === '') {
                continue;
            }

            $conditions[] = $wpdb->prepare(
                'LOWER(c.user_message) LIKE LOWER(%s)',
                '%' . $wpdb->esc_like($keyword) . '%'
            );
        }

        return empty($conditions) ? '' : '(' . implode(' OR ', $conditions) . ')';
    }

    /**
     * Complète les groupes de la page avec la première question, les intentions,
     * le nombre de liens proposés et cliqués.
     */
    private function build_rows(array $groups) {

        global $wpdb;

        if (empty($groups)) {
            return [];
        }

        $sessions   = [];
        $legacy_ids = [];

        foreach ($groups as $group) {
            if (strpos((string) $group->gk, 'legacy-') === 0) {
                $legacy_ids[] = (int) substr((string) $group->gk, 7);
            } else {
                $sessions[] = (string) $group->gk;
            }
        }

        $conditions = [];
        $params     = [];

        if (!empty($sessions)) {
            $conditions[] = 'session_id IN (' . implode(',', array_fill(0, count($sessions), '%s')) . ')';
            $params       = array_merge($params, $sessions);
        }

        if (!empty($legacy_ids)) {
            $conditions[] = 'id IN (' . implode(',', array_fill(0, count($legacy_ids), '%d')) . ')';
            $params       = array_merge($params, $legacy_ids);
        }

        $messages = $wpdb->get_results($wpdb->prepare(
            "SELECT id, session_id, user_message, user_time, bot_response, sources, page_url, intent
            FROM {$this->table}
            WHERE (" . implode(' OR ', $conditions) . ")
            AND user_message <> ''
            ORDER BY id ASC",
            $params
        ));

        $clicks_by_conversation = $this->get_clicks_map(is_array($messages) ? wp_list_pluck($messages, 'id') : []);

        $by_group = [];

        foreach ((array) $messages as $message) {
            $key = ((string) $message->session_id !== '') ? (string) $message->session_id : 'legacy-' . (int) $message->id;
            $by_group[$key][] = $message;
        }

        $out = [];

        foreach ($groups as $group) {

            $key      = (string) $group->gk;
            $messages = isset($by_group[$key]) ? $by_group[$key] : [];

            $offered = 0;
            $clicked = 0;
            $intents = [];

            foreach ($messages as $message) {

                $links     = $this->get_offered_links($message);
                $click_map = isset($clicks_by_conversation[(int) $message->id]) ? $clicks_by_conversation[(int) $message->id] : [];

                foreach ($links as $link) {
                    $offered++;

                    if (isset($click_map[$link['key']])) {
                        $clicked++;
                    }
                }

                $intent = (string) $message->intent;

                if ($intent !== '') {
                    $intents[$intent] = isset($intents[$intent]) ? $intents[$intent] + 1 : 1;
                }
            }

            arsort($intents);

            $first = !empty($messages) ? $messages[0] : null;

            $out[] = [
                'id'             => (int) $group->first_id,
                'started'        => (string) $group->started,
                'last_time'      => (string) $group->last_time,
                'messages'       => (int) $group->messages,
                'first_question' => $first ? mb_substr(trim(wp_strip_all_tags((string) $first->user_message)), 0, 160) : '',
                'page_url'       => $first ? (string) $first->page_url : '',
                'intents'        => array_slice(array_keys($intents), 0, 3),
                'offered'        => $offered,
                'clicked'        => $clicked,
                'is_legacy'      => (strpos($key, 'legacy-') === 0),
            ];
        }

        return $out;
    }

    /**
     * Indicateurs de la période sélectionnée.
     */
    private function get_kpis($where) {

        global $wpdb;

        $totals = $wpdb->get_row(
            "SELECT COUNT(*) AS messages,
                    COUNT(DISTINCT NULLIF(c.session_id, '')) AS sessions,
                    COALESCE(SUM(c.session_id = ''), 0) AS legacy
            FROM {$this->table} c
            WHERE c.user_message <> ''
            $where"
        );

        $click_totals = $wpdb->get_row(
            "SELECT COUNT(*) AS total_clicks,
                    COUNT(DISTINCT cl.session_id) AS sessions_clicked
            FROM {$this->clicks_table} cl
            INNER JOIN {$this->table} c ON c.id = cl.conversation_id
            WHERE c.user_message <> ''
            $where"
        );

        $unique = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT LOWER(TRIM(c.user_message)))
            FROM {$this->table} c
            WHERE c.user_message <> ''
            $where"
        );

        $messages         = $totals ? (int) $totals->messages : 0;
        $sessions         = $totals ? (int) $totals->sessions : 0;
        $legacy           = $totals ? (int) $totals->legacy : 0;
        $total_clicks     = $click_totals ? (int) $click_totals->total_clicks : 0;
        $sessions_clicked = $click_totals ? (int) $click_totals->sessions_clicked : 0;

        return [
            'conversations'    => $sessions + $legacy,
            'tracked'          => $sessions,
            'messages'         => $messages,
            'unique_questions' => $unique,
            'total_clicks'     => $total_clicks,
            'sessions_clicked' => $sessions_clicked,
            'click_rate'       => $sessions > 0 ? (int) round(($sessions_clicked / $sessions) * 100) : null,
        ];
    }

    /**
     * Avertissement sur les conversations enregistrées avant le suivi des sessions.
     */
    private function get_notice() {

        global $wpdb;

        $legacy_total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table} WHERE session_id = '' AND user_message <> ''");
        $since        = (string) get_option('fabioai_chatbot_sessions_since', '');

        return [
            'legacy_total' => $legacy_total,
            'since'        => $since,
        ];
    }

    // =========================================================
    // DÉTAIL D'UNE CONVERSATION (panneau latéral)
    // =========================================================

    /**
     * Échanges d'une conversation, avec pour chaque lien proposé : cliqué ou non.
     *
     * @param int $conversation_id Identifiant de n'importe quel échange de la conversation.
     * @return array|null
     */
    public function get_conversation_detail($conversation_id) {

        global $wpdb;

        $conversation_id = (int) $conversation_id;

        if ($conversation_id <= 0) {
            return null;
        }

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT id, session_id FROM {$this->table} WHERE id = %d",
            $conversation_id
        ));

        if (!$row) {
            return null;
        }

        if ((string) $row->session_id === '') {
            $messages = $wpdb->get_results($wpdb->prepare(
                "SELECT id, session_id, user_message, user_time, bot_response, sources, page_url, intent
                FROM {$this->table} WHERE id = %d",
                $conversation_id
            ));
        } else {
            $messages = $wpdb->get_results($wpdb->prepare(
                "SELECT id, session_id, user_message, user_time, bot_response, sources, page_url, intent
                FROM {$this->table}
                WHERE session_id = %s
                ORDER BY id ASC
                LIMIT %d",
                $row->session_id,
                self::MAX_MESSAGES
            ));
        }

        if (empty($messages)) {
            return null;
        }

        $clicks_map = $this->get_clicks_map(wp_list_pluck($messages, 'id'));

        $out_messages = [];
        $offered      = 0;
        $clicked      = 0;

        foreach ($messages as $message) {

            $click_map = isset($clicks_map[(int) $message->id]) ? $clicks_map[(int) $message->id] : [];
            $links_out = [];

            foreach ($this->get_offered_links($message) as $link) {

                $offered++;
                $is_clicked = isset($click_map[$link['key']]);

                if ($is_clicked) {
                    $clicked++;
                }

                $links_out[] = [
                    'title'      => $link['title'],
                    'url'        => $link['url'],
                    'type'       => $link['type'],
                    'clicked'    => $is_clicked,
                    'clicked_at' => $is_clicked ? $click_map[$link['key']]['first'] : '',
                    'clicks'     => $is_clicked ? $click_map[$link['key']]['count'] : 0,
                ];
            }

            $out_messages[] = [
                'id'       => (int) $message->id,
                'time'     => (string) $message->user_time,
                'question' => trim(wp_strip_all_tags((string) $message->user_message)),
                'answer'   => trim(wp_strip_all_tags((string) $message->bot_response)),
                'intent'   => (string) $message->intent,
                'page_url' => (string) $message->page_url,
                'links'    => $links_out,
            ];
        }

        $first = $messages[0];
        $last  = $messages[count($messages) - 1];

        return [
            'is_legacy' => ((string) $row->session_id === ''),
            'started'   => (string) $first->user_time,
            'last_time' => (string) $last->user_time,
            'page_url'  => (string) $first->page_url,
            'offered'   => $offered,
            'clicked'   => $clicked,
            'messages'  => $out_messages,
        ];
    }

    // =========================================================
    // NUAGE DE MOTS
    // =========================================================

    /**
     * Mots les plus utilisés par les visiteurs dans leurs questions.
     *
     * Chaque mot est compté une fois par question. Les mots vides (le, the, de, and...)
     * sont ignorés dans les principales langues. Les mots du filtre de recherche sont
     * exclus, pour faire apparaître les mots qui les accompagnent.
     */
    public function get_word_cloud($range, $date_from, $date_to, $search_keywords = null) {

        global $wpdb;

        $where  = $this->build_date_where($range, $date_from, $date_to);
        $search = $this->build_search_condition($search_keywords);

        if ($search !== '') {
            $where .= " AND {$search}";
        }

        $messages = $wpdb->get_col(
            "SELECT c.user_message
            FROM {$this->table} c
            WHERE c.user_message <> ''
            $where
            ORDER BY c.id DESC
            LIMIT " . (int) self::WORD_CLOUD_MAX_QUESTIONS
        );

        $stopwords = array_flip($this->get_stopwords());

        // Mots du filtre : exclus du nuage.
        $excluded = [];
        if (is_string($search_keywords) && trim($search_keywords) !== '') {
            foreach (explode(',', $search_keywords) as $keyword) {
                foreach ($this->tokenize($keyword) as $token) {
                    $excluded[$this->word_key($token)] = true;
                }
            }
        }

        $counts   = [];
        $variants = [];

        foreach ((array) $messages as $message) {

            $seen = [];

            foreach ($this->tokenize((string) $message) as $word) {

                $key = $this->word_key($word);

                if ($key === '' || isset($seen[$key]) || isset($stopwords[$word]) || isset($excluded[$key])) {
                    continue;
                }

                $seen[$key]            = true;
                $counts[$key]          = isset($counts[$key]) ? $counts[$key] + 1 : 1;
                $variants[$key][$word] = isset($variants[$key][$word]) ? $variants[$key][$word] + 1 : 1;
            }
        }

        arsort($counts);

        $items = [];

        foreach (array_slice($counts, 0, self::WORD_CLOUD_MAX_WORDS, true) as $key => $count) {
            // Forme la plus utilisée du mot (avec ses accents).
            arsort($variants[$key]);

            $items[] = [
                'word'  => (string) array_key_first($variants[$key]),
                'count' => (int) $count,
            ];
        }

        return [
            'items'     => $items,
            'questions' => count((array) $messages),
        ];
    }

    /**
     * Découpe une question en mots (minuscules, sans élisions ni contractions).
     */
    private function tokenize($text) {

        $text  = mb_strtolower(wp_strip_all_tags((string) $text));
        $parts = preg_split('/[^\p{L}\p{N}\'’-]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);

        if (!is_array($parts)) {
            return [];
        }

        $words = [];

        foreach ($parts as $part) {

            $part = trim($part, "'’-");

            // Élisions françaises/italiennes (l'avion, d'accord, qu'est) et contractions anglaises (don't, it's).
            $part = preg_replace('/^(?:l|d|j|m|n|s|t|c|qu|jusqu|lorsqu|puisqu)[\'’]/u', '', $part);
            $part = preg_replace('/n[\'’]t$/u', '', (string) $part);
            $part = preg_replace('/[\'’](?:s|t|re|ve|ll|d|m)$/u', '', (string) $part);
            $part = trim((string) $part, "'’-");

            if ($part === '' || mb_strlen($part) < 3 || mb_strlen($part) > 30 || ctype_digit($part)) {
                continue;
            }

            if (preg_match('/^\p{N}+$/u', $part)) {
                continue;
            }

            $words[] = $part;
        }

        return $words;
    }

    /**
     * Clé de regroupement d'un mot : sans accents (cafe = café).
     */
    private function word_key($word) {
        return mb_strtolower(remove_accents((string) $word));
    }

    /**
     * Mots vides (sans intérêt pour un nuage) dans les principales langues.
     */
    private function get_stopwords() {

        $words = '
            the and for with this that are was were from have has had what which who where when how why can could would should will
            you your yours our ours their them they his her its there here any some not but all than then too very just about into onto
            does did done get got want need like please thanks thank hello have been being also more most other such only own same
            les des une aux est sont pas pour avec dans sur par qui que quoi dont quel quelle quels quelles votre vos notre nos
            leur leurs vous nous ils elles mais donc car ces cette cet ses mon ton son mes tes comme aussi plus moins tout tous toute
            toutes très bien avoir etre fait faire peut peux puis veux voudrais voulez bonjour merci svp stp salut
            los las del una unos unas por con para que como pero más muy esta este esto son hay tiene tengo quiero hola gracias
            der die das den dem des ein eine einer eines und oder mit für von auf ist sind nicht ich sie wir ihr aber auch wie was wer wo
            hallo danke bitte
            gli uno una dei delle del della dello con per che come non sono ho hai ciao grazie
            uma uns umas dos das com para por que como não são tenho quero ola obrigado obrigada
            het een van voor met niet zijn maar ook wat wie hoe dank bedankt hallo
        ';

        return array_values(array_unique(preg_split('/\s+/', trim($words), -1, PREG_SPLIT_NO_EMPTY)));
    }

    // =========================================================
    // INTENTIONS
    // =========================================================

    /**
     * Répartition des intentions sur la période (toutes les questions, anciennes comprises).
     */
    public function get_intent_breakdown($range, $date_from, $date_to, $search_keywords = null) {

        global $wpdb;

        $where  = $this->build_date_where($range, $date_from, $date_to);
        $search = $this->build_search_condition($search_keywords);

        if ($search !== '') {
            $where .= " AND {$search}";
        }

        $rows = $wpdb->get_results(
            "SELECT c.intent AS intent, COUNT(*) AS total
            FROM {$this->table} c
            WHERE c.user_message <> ''
            $where
            GROUP BY c.intent"
        );

        $items        = [];
        $total        = 0;
        $unclassified = 0;

        foreach ((array) $rows as $row) {

            $count  = (int) $row->total;
            $total += $count;

            if (in_array((string) $row->intent, FabioAIChatbot_Shortcodes::INTENT_CODES, true)) {
                $items[] = ['code' => (string) $row->intent, 'count' => $count];
            } else {
                $unclassified += $count;
            }
        }

        usort($items, function ($a, $b) {
            return $b['count'] <=> $a['count'];
        });

        return [
            'items'              => $items,
            'total'              => $total,
            'unclassified'       => $unclassified,
            'unclassified_total' => $this->count_unclassified(),
            'batch_size'         => self::INTENT_BATCH_SIZE,
        ];
    }

    /**
     * Nombre de questions (toutes dates) dont l'intention n'est pas encore connue.
     */
    public function count_unclassified() {

        global $wpdb;

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table} WHERE intent = '' AND user_message <> ''");
    }

    /**
     * Classe un lot de questions sans intention (des plus récentes aux plus anciennes).
     *
     * @param object $shortcodes Instance de FabioAIChatbot_Shortcodes (appel IA).
     * @param int    $cursor     0 au départ, puis 'next_cursor' renvoyé par l'appel précédent.
     * @return array ['ok', 'error', 'done', 'updated', 'next_cursor', 'remaining']
     */
    public function backfill_intents_batch($shortcodes, $cursor) {

        global $wpdb;

        if (!is_object($shortcodes) || !method_exists($shortcodes, 'classify_questions')) {
            return ['ok' => false, 'error' => 'The chatbot is not ready. Reload the page and try again.'];
        }

        $cursor = max(0, (int) $cursor);

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, user_message FROM {$this->table}
            WHERE intent = '' AND user_message <> '' AND (%d = 0 OR id < %d)
            ORDER BY id DESC
            LIMIT %d",
            $cursor,
            $cursor,
            self::INTENT_BATCH_SIZE
        ));

        if (empty($rows)) {
            return ['ok' => true, 'done' => true, 'updated' => 0, 'next_cursor' => 0, 'remaining' => $this->count_unclassified()];
        }

        $questions   = [];
        $next_cursor = (int) $rows[0]->id;

        foreach ($rows as $row) {
            $questions[(int) $row->id] = (string) $row->user_message;
            $next_cursor               = min($next_cursor, (int) $row->id);
        }

        $result = $shortcodes->classify_questions($questions);

        if ($result === null) {
            return ['ok' => false, 'error' => 'No AI API key is configured. Add your key in the chatbot settings first.'];
        }

        if (empty($result)) {
            return ['ok' => false, 'error' => 'The AI did not return a usable answer. Please try again in a moment.'];
        }

        $updated = 0;

        foreach ($result as $id => $code) {
            $done = $wpdb->update(
                $this->table,
                ['intent' => $code],
                ['id' => (int) $id, 'intent' => ''],
                ['%s'],
                ['%d', '%s']
            );

            if ($done) {
                $updated += (int) $done;
            }
        }

        return [
            'ok'          => true,
            'done'        => false,
            'updated'     => $updated,
            'next_cursor' => $next_cursor,
            'remaining'   => $this->count_unclassified(),
        ];
    }

    // =========================================================
    // OUTILS
    // =========================================================

    /**
     * Clics enregistrés, regroupés par conversation puis par lien (clé normalisée).
     *
     * @param array $conversation_ids Identifiants d'échanges.
     * @return array [conversation_id => [clé du lien => ['first' => date, 'count' => n]]]
     */
    private function get_clicks_map($conversation_ids) {

        global $wpdb;

        $conversation_ids = array_values(array_unique(array_map('intval', (array) $conversation_ids)));

        if (empty($conversation_ids)) {
            return [];
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT conversation_id, link_url, click_time
            FROM {$this->clicks_table}
            WHERE conversation_id IN (" . implode(',', array_fill(0, count($conversation_ids), '%d')) . ")
            ORDER BY click_time ASC, id ASC",
            $conversation_ids
        ));

        $map = [];

        foreach ((array) $rows as $row) {

            $conversation = (int) $row->conversation_id;
            $key          = $this->link_key((string) $row->link_url);

            if ($key === '') {
                continue;
            }

            if (!isset($map[$conversation][$key])) {
                $map[$conversation][$key] = [
                    'first' => (string) $row->click_time,
                    'count' => 0,
                ];
            }

            $map[$conversation][$key]['count']++;
        }

        return $map;
    }

    /**
     * Liens proposés dans une réponse du chatbot : sources + adresses écrites dans le texte.
     *
     * @param object $row Ligne de la table des conversations.
     * @return array Liste de ['url', 'title', 'type' (source|inline), 'key'].
     */
    private function get_offered_links($row) {

        $links = [];
        $seen  = [];

        // 1. Sources (liste "Sources:" sous la réponse).
        $sources = json_decode((string) $row->sources, true);

        if (is_array($sources)) {
            foreach ($sources as $source) {

                $url = isset($source['url']) ? trim((string) $source['url']) : '';

                if ($url === '' || !preg_match('#^https?://#i', $url)) {
                    continue;
                }

                $key = $this->link_key($url);

                if ($key === '' || isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;

                $title = isset($source['title']) ? trim(wp_strip_all_tags((string) $source['title'])) : '';

                $links[] = [
                    'url'   => $url,
                    'title' => ($title !== '') ? $title : $url,
                    'type'  => 'source',
                    'key'   => $key,
                ];
            }
        }

        // 2. Adresses écrites en clair dans le texte de la réponse (le chatbot les rend cliquables).
        $response = html_entity_decode((string) $row->bot_response, ENT_QUOTES, 'UTF-8');

        if ($response === '') {
            return $links;
        }

        if (preg_match_all('#https?://[^\s<>"\']+#i', $response, $bare)) {
            foreach ($bare[0] as $url) {

                $url = rtrim($url, ".,;:!?)]}'\"");
                $key = $this->link_key($url);

                if ($key === '' || isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;

                $links[] = [
                    'url'   => $url,
                    'title' => $url,
                    'type'  => 'inline',
                    'key'   => $key,
                ];
            }
        }

        return $links;
    }

    /**
     * Clé de comparaison d'un lien : ignore le slash final, la ponctuation collée
     * à la fin d'une adresse écrite en clair et l'encodage des caractères.
     */
    private function link_key($url) {
        $url = trim((string) $url);

        if ($url === '') {
            return '';
        }

        return rtrim(rawurldecode($url), "/.,;:!?)]}'\"");
    }

    /**
     * Filtre de dates (heure du site, comme l'heure enregistrée avec chaque conversation).
     * Les dates sont validées puis passées par $wpdb->prepare().
     */
    private function build_date_where($range, $date_from, $date_to) {

        global $wpdb;

        switch ($range) {

            case '24h':
                return $wpdb->prepare('AND c.user_time >= %s', wp_date('Y-m-d H:i:s', time() - DAY_IN_SECONDS));

            case '30d':
                return $wpdb->prepare('AND c.user_time >= %s', wp_date('Y-m-d H:i:s', time() - (30 * DAY_IN_SECONDS)));

            case 'custom':
                if (
                    is_string($date_from) && is_string($date_to) &&
                    preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from) &&
                    preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)
                ) {
                    return $wpdb->prepare(
                        'AND c.user_time BETWEEN %s AND %s',
                        $date_from . ' 00:00:00',
                        $date_to . ' 23:59:59'
                    );
                }
                return '';

            default:
                return '';
        }
    }
}
