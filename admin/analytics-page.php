<?php
if (!defined('ABSPATH')) {
    exit;
}

function fabioai_chatbot_render_analytics_page() {
    ?>
    <div class="wrap fabioai-chatbot-analytics-page">

        <div class="fabioai-chatbot-analytics-header">
            <h1 class="fabioai-chatbot-analytics-title">📊 Fabio AI ChatBot Lite – Analytics</h1>
            <p class="fabioai-chatbot-analytics-subtitle">
                Analyze the questions asked by visitors to Fabio AI ChatBot Lite over the last 24 hours.
            </p>
        </div>

        <div class="fabioai-chatbot-analytics-toolbar">
            <div class="fabioai-chatbot-analytics-search">
                <label for="fabioai-chatbot-search-keywords"><strong>Filter by keywords</strong></label>
                <input type="text" id="fabioai-chatbot-search-keywords" placeholder="Ex: price, cost, rate" />
            </div>         

        </div>

    <!-- KPI GRID -->
    <div class="fabioai-chatbot-kpi-grid">
        <div class="fabioai-chatbot-kpi-card">
            <div class="fabioai-chatbot-kpi-icon">💬</div>
            <div class="fabioai-chatbot-kpi-content">
                <div class="fabioai-chatbot-kpi-label">Total Questions</div>
                <div class="fabioai-chatbot-kpi-value" id="fabioai-chatbot-kpi-total">-</div>
            </div>
        </div>

        <div class="fabioai-chatbot-kpi-card">
            <div class="fabioai-chatbot-kpi-icon">🧠</div>
            <div class="fabioai-chatbot-kpi-content">
                <div class="fabioai-chatbot-kpi-label">Unique Questions</div>
                <div class="fabioai-chatbot-kpi-value" id="fabioai-chatbot-kpi-unique">-</div>
            </div>
        </div>

        <div class="fabioai-chatbot-kpi-card">
            <div class="fabioai-chatbot-kpi-icon">🎯</div>
            <div class="fabioai-chatbot-kpi-content">
                <div class="fabioai-chatbot-kpi-label">Top Intent</div>
                <div class="fabioai-chatbot-kpi-value fabioai-chatbot-kpi-value-text" id="fabioai-chatbot-kpi-top-intent">-</div>
            </div>
        </div>

        <div class="fabioai-chatbot-kpi-card">
            <div class="fabioai-chatbot-kpi-icon">🧩</div>
            <div class="fabioai-chatbot-kpi-content">
                <div class="fabioai-chatbot-kpi-label">Questions To Analyze</div>
                <div class="fabioai-chatbot-kpi-value" id="fabioai-chatbot-kpi-unclassified">-</div>
            </div>
        </div>
    </div>

    <div class="fabioai-chatbot-analytics-grid">

        <!-- CONVERSATIONS (sessions, clics) -->
        <div class="fabioai-chatbot-analytics-card fabioai-chatbot-analytics-card-full" id="fabioai-chatbot-conversations-card">
            <div class="fabioai-chatbot-analytics-card-header">
                <h2>🗨️ Conversations</h2>
                <p>One line per visitor conversation. Click a line to read it and see which links were clicked.</p>
            </div>

            <div class="fabioai-chatbot-analytics-card-body">

                <div class="fabioai-chatbot-conv-notice" id="fabioai-chatbot-conv-notice" role="status"></div>

                <div class="fabioai-chatbot-conv-stats">
                    <div class="fabioai-chatbot-conv-stat">
                        <span>Conversations</span>
                        <strong id="fabioai-chatbot-conv-kpi-conversations">-</strong>
                    </div>
                    <div class="fabioai-chatbot-conv-stat">
                        <span>Questions</span>
                        <strong id="fabioai-chatbot-conv-kpi-messages">-</strong>
                    </div>
                    <div class="fabioai-chatbot-conv-stat">
                        <span>Link clicks</span>
                        <strong id="fabioai-chatbot-conv-kpi-clicks">-</strong>
                    </div>
                    <div class="fabioai-chatbot-conv-stat">
                        <span>Conversations with a click</span>
                        <strong id="fabioai-chatbot-conv-kpi-rate">-</strong>
                    </div>
                </div>

                <div class="fabioai-chatbot-conv-controls">
                    <label for="fabioai-chatbot-conv-sort">
                        <strong>Sort by</strong>
                        <select id="fabioai-chatbot-conv-sort">
                            <option value="date_desc" selected>Date &amp; time — newest first</option>
                            <option value="date_asc">Date &amp; time — oldest first</option>
                            <option value="clicks_desc">Most links clicked</option>
                            <option value="clicks_asc">Least links clicked</option>
                        </select>
                    </label>
                    <label for="fabioai-chatbot-conv-filter">
                        <strong>Show</strong>
                        <select id="fabioai-chatbot-conv-filter">
                            <option value="all" selected>All conversations</option>
                            <option value="clicked">With at least one click</option>
                            <option value="none">Without any click</option>
                        </select>
                    </label>
                </div>

                <div class="fabioai-chatbot-intent-chips" id="fabioai-chatbot-intent-chips" role="group" aria-label="Filter by intent"></div>

                <table class="widefat striped fabioai-chatbot-analytics-table fabioai-chatbot-conv-table">
                    <thead>
                        <tr>
                            <th style="width: 110px;">Date &amp; time</th>
                            <th>First question</th>
                            <th style="width: 80px;">Exchanges</th>
                            <th style="width: 150px;">Intent</th>
                            <th style="width: 100px;">Links clicked</th>
                            <th style="width: 200px;">First page</th>
                        </tr>
                    </thead>
                    <tbody id="fabioai-chatbot-conv-body">
                        <tr>
                            <td colspan="6">Loading data...</td>
                        </tr>
                    </tbody>
                </table>

                <div id="fabioai-chatbot-conv-pagination"></div>
            </div>
        </div>

        <!-- INTENTIONS -->
        <div class="fabioai-chatbot-analytics-card fabioai-chatbot-analytics-card-full" id="fabioai-chatbot-intents-card">
            <div class="fabioai-chatbot-analytics-card-header">
                <h2>🎯 What Visitors Want</h2>
                <p>The intent detected behind each question, over the last 24 hours.</p>
            </div>
            <div class="fabioai-chatbot-analytics-card-body">
                <div id="fabioai-chatbot-intents-body" class="fabioai-chatbot-intents-body">Loading data...</div>
                <div id="fabioai-chatbot-intents-analyze" class="fabioai-chatbot-intents-analyze" style="display:none;">
                    <p id="fabioai-chatbot-intents-analyze-text"></p>
                    <button type="button" class="button button-primary" id="fabioai-chatbot-intents-analyze-btn">Analyze with AI</button>
                    <span id="fabioai-chatbot-intents-progress" class="fabioai-chatbot-intents-progress" aria-live="polite"></span>
                </div>
            </div>
        </div>

        <!-- NUAGE DE MOTS -->
        <div class="fabioai-chatbot-analytics-card fabioai-chatbot-analytics-card-full" id="fabioai-chatbot-cloud-card">
            <div class="fabioai-chatbot-analytics-card-header">
                <h2>☁️ Words Visitors Use</h2>
                <p>The words most often typed by visitors, over the last 24 hours. Click a word to filter the conversations.</p>
            </div>
            <div class="fabioai-chatbot-analytics-card-body">
                <div id="fabioai-chatbot-word-cloud" class="fabioai-chatbot-word-cloud" aria-live="polite">Loading data...</div>
            </div>
        </div>

        <!-- EXPORT CSV — TÉLÉCHARGEMENT SIMPLE -->
        <div class="fabioai-chatbot-analytics-card fabioai-chatbot-analytics-card-full">
            <div class="fabioai-chatbot-analytics-card-header">
                <h2>💾 Download Conversations (CSV)</h2>
                <p>Download your full conversation history (all dates) as a CSV file. Your data stays in the database.</p>
            </div>
            <div class="fabioai-chatbot-analytics-card-body">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('fabioai_chatbot_export_nonce'); ?>
                    <input type="hidden" name="action" value="fabioai_chatbot_export_conversations">
                    <input type="hidden" name="clear_after_export" value="0">
                    <input type="submit" class="button button-primary" value="Download CSV">
                </form>
            </div>
        </div>

        <!-- EXPORT CSV — TÉLÉCHARGEMENT + SUPPRESSION -->
        <div class="fabioai-chatbot-analytics-card fabioai-chatbot-analytics-card-full">
            <div class="fabioai-chatbot-analytics-card-header">
                <h2>🗑️ Download & Clear Conversations</h2>
                <p>Download your full conversation history, then permanently delete it from the database. Useful to keep your database lightweight.</p>
            </div>
            <div class="fabioai-chatbot-analytics-card-body">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('fabioai_chatbot_export_nonce'); ?>
                    <input type="hidden" name="action" value="fabioai_chatbot_export_conversations">
                    <input type="hidden" name="clear_after_export" value="1">
                    <label>
                        <input type="checkbox" name="confirm_clear" value="1" required>
                        <strong>⚠️ I understand this will export and permanently delete all conversations.</strong>
                        <a href="https://fabio-plugins.com/support/topic/how-do-i-export-and-analyse-users-conversations/" target="_blank">Read details</a>.
                    </label>
                    <br><br>
                    <input type="submit" class="button" value="Download & Clear Conversations">
                </form>
            </div>
        </div>

    </div>

    <!-- PANNEAU LATÉRAL : détail d'une conversation -->
    <div class="fabioai-chatbot-conv-overlay" id="fabioai-chatbot-conv-overlay"></div>
    <aside class="fabioai-chatbot-conv-drawer" id="fabioai-chatbot-conv-drawer" aria-hidden="true" aria-label="Conversation details">
        <div class="fabioai-chatbot-conv-drawer-header">
            <h2>Conversation</h2>
            <button type="button" id="fabioai-chatbot-conv-drawer-close" aria-label="Close">✕</button>
        </div>
        <div class="fabioai-chatbot-conv-drawer-body" id="fabioai-chatbot-conv-drawer-body"></div>
    </aside>

</div>
<?php
}