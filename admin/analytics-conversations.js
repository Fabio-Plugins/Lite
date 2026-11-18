/**
 * Fabio AI ChatBot Lite - Conversations, intentions et nuage de mots (page Analytics)
 *
 * Une ligne = une conversation (session). Un clic sur la ligne ouvre un panneau
 * latéral avec les échanges et, pour chaque lien proposé par le chatbot,
 * s'il a été cliqué ou non.
 *
 * La période et les mots-clés sont gérés par analytics.js (window.fabioaiAnalyticsFilters) :
 * ce fichier se recharge quand l'événement "fabioai-analytics-filters" est émis.
 */
document.addEventListener('DOMContentLoaded', function () {

    const body = document.getElementById('fabioai-chatbot-conv-body');
    const paginationBox = document.getElementById('fabioai-chatbot-conv-pagination');
    const sortSelect = document.getElementById('fabioai-chatbot-conv-sort');
    const filterSelect = document.getElementById('fabioai-chatbot-conv-filter');
    const noticeBox = document.getElementById('fabioai-chatbot-conv-notice');
    const drawer = document.getElementById('fabioai-chatbot-conv-drawer');
    const drawerBody = document.getElementById('fabioai-chatbot-conv-drawer-body');
    const drawerClose = document.getElementById('fabioai-chatbot-conv-drawer-close');
    const overlay = document.getElementById('fabioai-chatbot-conv-overlay');
    const intentsBody = document.getElementById('fabioai-chatbot-intents-body');
    const analyzeWrap = document.getElementById('fabioai-chatbot-intents-analyze');
    const analyzeText = document.getElementById('fabioai-chatbot-intents-analyze-text');
    const analyzeBtn = document.getElementById('fabioai-chatbot-intents-analyze-btn');
    const analyzeProgress = document.getElementById('fabioai-chatbot-intents-progress');
    const chipsBox = document.getElementById('fabioai-chatbot-intent-chips');
    const cloudBox = document.getElementById('fabioai-chatbot-word-cloud');

    if (!body || typeof fabioaiChatbotConversations === 'undefined') {
        return;
    }

    let currentSort = 'date_desc';
    let currentFilter = 'all';
    let currentPage = 1;
    let currentIntent = '';
    let lastFocusedRow = null;
    let analyzing = false;

    const INTENT_LABELS = {
        purchase: 'Purchase',
        price: 'Price',
        delivery: 'Delivery',
        support: 'Support',
        comparison: 'Comparison',
        contact: 'Contact',
        complaint: 'Complaint',
        information: 'Information',
        off_topic: 'Off-topic'
    };

    // Période et mots-clés courants (partagés avec analytics.js).
    function getFilters() {
        if (window.fabioaiAnalyticsFilters && typeof window.fabioaiAnalyticsFilters.get === 'function') {
            return window.fabioaiAnalyticsFilters.get();
        }

        return { range: '24h', dateFrom: '', dateTo: '', search: '' };
    }

    function escapeHtml(str) {
        if (typeof str !== 'string') {
            return '';
        }

        return str
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function safeUrl(url) {
        return (typeof url === 'string' && /^https?:\/\//i.test(url)) ? url : '';
    }

    // "2026-10-26 08:12:59" -> ["2026-10-26", "08:12"]
    function splitDateTime(value) {
        const text = (typeof value === 'string') ? value : '';
        const parts = text.split(' ');
        return [parts[0] || '', (parts[1] || '').slice(0, 5)];
    }

    function intentBadge(code) {
        const label = INTENT_LABELS[code] || '';
        if (!label) {
            return '';
        }
        return '<span class="fabioai-chatbot-intent fabioai-chatbot-intent-' + escapeHtml(code) + '">' + escapeHtml(label) + '</span>';
    }

    function clickIndicator(clicked, offered) {
        if (!offered) {
            return '<span class="fabioai-chatbot-click-pill is-none">—</span>';
        }

        const cls = clicked > 0 ? 'is-yes' : 'is-no';
        return '<span class="fabioai-chatbot-click-pill ' + cls + '">' + clicked + ' / ' + offered + '</span>';
    }

    function post(action, extra) {
        const params = new URLSearchParams();
        params.append('action', action);
        params.append('_ajax_nonce', fabioaiChatbotConversations.nonce);

        Object.keys(extra || {}).forEach(function (key) {
            params.append(key, extra[key]);
        });

        return fetch(fabioaiChatbotConversations.ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: params.toString(),
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json();
        });
    }

    function setText(id, value) {
        const el = document.getElementById(id);
        if (el) {
            el.textContent = value;
        }
    }

    // Paramètres de période et de recherche communs à toutes les requêtes.
    function dateParams(extra) {
        const filters = getFilters();

        extra = extra || {};
        extra.range = filters.range;

        if (filters.search) {
            extra.search_keywords = filters.search;
        }

        if (filters.range === 'custom') {
            extra.date_from = filters.dateFrom || '';
            extra.date_to = filters.dateTo || '';
        }

        return extra;
    }

    // =========================
    // KPIs (carte Conversations)
    // =========================
    // Les cartes "Total Questions" et "Unique Questions" du haut de page
    // sont alimentées par analytics.js.
    function renderKpis(kpis) {
        kpis = kpis || {};
        setText('fabioai-chatbot-conv-kpi-conversations', kpis.conversations || 0);
        setText('fabioai-chatbot-conv-kpi-messages', kpis.messages || 0);
        setText('fabioai-chatbot-conv-kpi-clicks', kpis.total_clicks || 0);
        setText('fabioai-chatbot-conv-kpi-rate', (kpis.click_rate === null || typeof kpis.click_rate === 'undefined') ? '—' : kpis.click_rate + '%');
    }

    // =========================
    // INTENTIONS (répartition + analyse des anciennes conversations)
    // =========================
    function renderIntents(data) {
        const items = Array.isArray(data.items) ? data.items : [];
        const total = parseInt(data.total, 10) || 0;
        const unclassifiedTotal = parseInt(data.unclassified_total, 10) || 0;
        const batchSize = parseInt(data.batch_size, 10) || 25;
        const unclassifiedPeriod = parseInt(data.unclassified, 10) || 0;

        // Cartes du haut de page
        if (items.length) {
            const pct = total ? Math.round((items[0].count / total) * 100) : 0;
            setText('fabioai-chatbot-kpi-top-intent', (INTENT_LABELS[items[0].code] || items[0].code) + ' · ' + pct + '%');
        } else {
            setText('fabioai-chatbot-kpi-top-intent', '—');
        }
        setText('fabioai-chatbot-kpi-unclassified', unclassifiedTotal);

        // Barres
        if (!items.length && !unclassifiedPeriod) {
            intentsBody.innerHTML = '<p class="fabioai-chatbot-conv-muted">No questions found for this period.</p>';
        } else {
            const max = items.length ? items[0].count : 1;

            let html = items.map(function (item) {
                const pct = total ? Math.round((item.count / total) * 100) : 0;
                const width = Math.max(2, Math.round((item.count / max) * 100));
                return '<div class="fabioai-chatbot-intent-row is-clickable' + (currentIntent === item.code ? ' is-selected' : '') + '" data-intent="' + escapeHtml(item.code) + '" title="Show these conversations">' +
                    '<div class="fabioai-chatbot-intent-name">' + intentBadge(item.code) + '</div>' +
                    '<div class="fabioai-chatbot-intent-bar"><span class="fabioai-chatbot-intent-bar-fill fabioai-chatbot-intent-fill-' + escapeHtml(item.code) + '" style="width:' + width + '%"></span></div>' +
                    '<div class="fabioai-chatbot-intent-count">' + item.count + ' <small>(' + pct + '%)</small></div>' +
                '</div>';
            }).join('');

            if (unclassifiedPeriod > 0) {
                html += '<div class="fabioai-chatbot-intent-row is-unclassified">' +
                    '<div class="fabioai-chatbot-intent-name"><span class="fabioai-chatbot-intent fabioai-chatbot-intent-off_topic">Not analyzed yet</span></div>' +
                    '<div class="fabioai-chatbot-intent-bar"></div>' +
                    '<div class="fabioai-chatbot-intent-count">' + unclassifiedPeriod + '</div>' +
                '</div>';
            }

            intentsBody.innerHTML = html;
        }

        // Bouton d'analyse des questions sans intention (anciennes conversations)
        if (analyzeWrap) {
            if (unclassifiedTotal > 0 || analyzing) {
                const calls = Math.ceil(unclassifiedTotal / batchSize);
                analyzeText.textContent = unclassifiedTotal + ' question' + (unclassifiedTotal > 1 ? 's have' : ' has') +
                    ' no intent yet (conversations recorded before this feature). Analyze them once with your AI provider: ' +
                    'about ' + calls + ' short request' + (calls > 1 ? 's' : '') + ', ' + batchSize + ' questions each. ' +
                    'These requests use your own API key and may incur a small cost.';
                analyzeWrap.style.display = 'block';
            } else {
                analyzeWrap.style.display = 'none';
            }
        }
    }

    function loadIntents() {
        if (!intentsBody) {
            return;
        }

        post('fabioai_chatbot_get_intents', dateParams())
            .then(function (result) {
                if (!result || !result.success || !result.data) {
                    throw new Error('Invalid response');
                }
                renderIntents(result.data);
            })
            .catch(function () {
                intentsBody.innerHTML = '<p class="fabioai-chatbot-conv-muted">Unable to load intents.</p>';
            });
    }

    function runAnalysis(cursor, done) {
        post('fabioai_chatbot_backfill_intents', { cursor: cursor })
            .then(function (result) {
                if (!result || !result.success || !result.data) {
                    const message = (result && typeof result.data === 'string') ? result.data : 'Analysis failed.';
                    throw new Error(message);
                }

                const data = result.data;
                done += parseInt(data.updated, 10) || 0;
                analyzeProgress.textContent = done + ' analyzed, ' + (data.remaining || 0) + ' left...';

                if (data.done || !data.next_cursor) {
                    return finishAnalysis('Done: ' + done + ' question' + (done === 1 ? '' : 's') + ' analyzed.');
                }

                runAnalysis(data.next_cursor, done);
            })
            .catch(function (error) {
                finishAnalysis('Stopped: ' + (error && error.message ? error.message : 'analysis failed.') + ' (' + done + ' analyzed so far; you can resume at any time.)');
            });
    }

    function finishAnalysis(message) {
        analyzing = false;
        analyzeBtn.disabled = false;
        analyzeProgress.textContent = message;
        loadIntents();
        loadConversations();
    }

    if (analyzeBtn) {
        analyzeBtn.addEventListener('click', function () {
            if (analyzing) {
                return;
            }

            analyzing = true;
            analyzeBtn.disabled = true;
            analyzeProgress.textContent = 'Analyzing...';
            runAnalysis(0, 0);
        });
    }

    // =========================
    // AVERTISSEMENT (anciennes conversations)
    // =========================
    function renderNotice(notice) {
        if (!noticeBox) {
            return;
        }

        const legacy = notice ? parseInt(notice.legacy_total, 10) || 0 : 0;

        if (legacy <= 0) {
            noticeBox.style.display = 'none';
            noticeBox.innerHTML = '';
            return;
        }

        const since = notice && notice.since ? splitDateTime(notice.since)[0] : '';
        const sinceText = since ? ' Session tracking has been active since <strong>' + escapeHtml(since) + '</strong>.' : '';

        noticeBox.innerHTML =
            '<strong>' + legacy + ' older conversation' + (legacy > 1 ? 's were' : ' was') + ' recorded before session tracking.</strong> ' +
            'They have no session and no click data: each one appears on its own line with a dash in the clicks column.' + sinceText;
        noticeBox.style.display = 'block';
    }

    // =========================
    // LISTE
    // =========================
    function renderRows(rows) {
        if (!Array.isArray(rows) || !rows.length) {
            body.innerHTML = '<tr><td colspan="6">No conversations found for this period.</td></tr>';
            return;
        }

        body.innerHTML = rows.map(function (row) {
            const dt = splitDateTime(row.started);
            const question = escapeHtml(row.first_question || '');
            const extra = (row.messages > 1) ? ' <span class="fabioai-chatbot-conv-more">+' + (row.messages - 1) + ' more</span>' : '';
            const intents = (Array.isArray(row.intents) ? row.intents : []).map(intentBadge).join(' ');
            const page = escapeHtml(row.page_url || '');
            const legacyTag = row.is_legacy ? ' <span class="fabioai-chatbot-conv-legacy" title="Recorded before session tracking">old</span>' : '';

            return '' +
                '<tr class="fabioai-chatbot-conv-row" tabindex="0" data-id="' + parseInt(row.id, 10) + '">' +
                    '<td class="fabioai-chatbot-conv-date"><strong>' + escapeHtml(dt[0]) + '</strong><span>' + escapeHtml(dt[1]) + '</span></td>' +
                    '<td class="fabioai-chatbot-conv-question">' + question + extra + legacyTag + '</td>' +
                    '<td>' + (row.messages || 0) + '</td>' +
                    '<td>' + (intents || '<span class="fabioai-chatbot-conv-muted">—</span>') + '</td>' +
                    '<td>' + clickIndicator(row.clicked || 0, row.offered || 0) + '</td>' +
                    '<td class="fabioai-chatbot-conv-page">' + (page || '<span class="fabioai-chatbot-conv-muted">—</span>') + '</td>' +
                '</tr>';
        }).join('');
    }

    function renderPagination(pagination) {
        if (!paginationBox) {
            return;
        }

        if (!pagination || !pagination.total_pages || pagination.total_pages <= 1) {
            paginationBox.innerHTML = '';
            return;
        }

        const current = parseInt(pagination.current_page, 10) || 1;
        const totalPages = parseInt(pagination.total_pages, 10) || 1;
        const totalItems = parseInt(pagination.total_items, 10) || 0;

        paginationBox.innerHTML =
            '<div class="fabioai-chatbot-analytics-pagination-inner">' +
                '<button type="button" class="button fabioai-chatbot-conv-page-btn" data-page="' + (current - 1) + '"' + (current <= 1 ? ' disabled' : '') + '>← Previous</button>' +
                '<span class="fabioai-chatbot-pagination-info">Page ' + current + ' of ' + totalPages + ' (' + totalItems + ' conversations)</span>' +
                '<button type="button" class="button fabioai-chatbot-conv-page-btn" data-page="' + (current + 1) + '"' + (current >= totalPages ? ' disabled' : '') + '>Next →</button>' +
            '</div>';
    }

    // =========================
    // PASTILLES D'INTENTION (filtre)
    // =========================
    function renderIntentChips(data) {
        if (!chipsBox) {
            return;
        }

        const counts = data.intent_counts || {};
        const all = parseInt(data.all_count, 10) || 0;

        let html = '<button type="button" class="fabioai-chatbot-intent-chip' + (currentIntent === '' ? ' is-active' : '') + '" data-intent="">All <span>' + all + '</span></button>';

        Object.keys(INTENT_LABELS).forEach(function (code) {
            const n = parseInt(counts[code], 10) || 0;
            const active = currentIntent === code;
            const empty = n === 0 && !active;

            html += '<button type="button" class="fabioai-chatbot-intent-chip fabioai-chatbot-intent-chip-' + code +
                (active ? ' is-active' : '') + (empty ? ' is-empty' : '') + '" data-intent="' + code + '"' +
                (empty ? ' disabled' : '') + ' aria-pressed="' + (active ? 'true' : 'false') + '">' +
                escapeHtml(INTENT_LABELS[code]) + ' <span>' + n + '</span></button>';
        });

        if (currentIntent) {
            html += '<span class="fabioai-chatbot-intent-chip-hint">Most ' + escapeHtml(INTENT_LABELS[currentIntent] || currentIntent).toLowerCase() + ' questions first</span>';
        }

        chipsBox.innerHTML = html;
    }

    function setIntentFilter(code) {
        // Un second clic sur la pastille active retire le filtre.
        currentIntent = (code && code !== currentIntent) ? code : '';
        currentPage = 1;
        loadIntents();
        loadConversations();
    }

    if (chipsBox) {
        chipsBox.addEventListener('click', function (event) {
            const chip = event.target.closest('.fabioai-chatbot-intent-chip');

            if (chip && !chip.disabled) {
                setIntentFilter(chip.dataset.intent || '');
            }
        });
    }

    // Clic sur une barre de "What Visitors Want" = même filtre.
    if (intentsBody) {
        intentsBody.addEventListener('click', function (event) {
            const row = event.target.closest('.fabioai-chatbot-intent-row[data-intent]');

            if (!row) {
                return;
            }

            setIntentFilter(row.dataset.intent);

            const card = document.getElementById('fabioai-chatbot-conversations-card');
            if (card) {
                card.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    }

    // =========================
    // NUAGE DE MOTS
    // =========================
    function renderWordCloud(data) {
        if (!cloudBox) {
            return;
        }

        const items = data && Array.isArray(data.items) ? data.items : [];

        if (!items.length) {
            cloudBox.innerHTML = '<p class="fabioai-chatbot-conv-muted">No words to display for this period.</p>';
            return;
        }

        const counts = items.map(function (item) { return item.count; });
        const max = Math.max.apply(null, counts);
        const min = Math.min.apply(null, counts);

        // Ordre alphabétique : plus lisible qu'un classement, la taille indique la fréquence.
        const sorted = items.slice().sort(function (a, b) {
            return a.word.localeCompare(b.word);
        });

        cloudBox.innerHTML = sorted.map(function (item) {
            const ratio = max > min ? (item.count - min) / (max - min) : 0.5;
            const size = (0.85 + ratio * 1.65).toFixed(2);
            const level = Math.min(4, Math.floor(ratio * 5));

            return '<button type="button" class="fabioai-chatbot-cloud-word level-' + level + '" style="font-size:' + size + 'em" ' +
                'data-word="' + escapeHtml(String(item.word)) + '" title="' + item.count + ' questions">' +
                escapeHtml(String(item.word)) + '</button>';
        }).join(' ');
    }

    function loadWordCloud() {
        if (!cloudBox) {
            return;
        }

        cloudBox.innerHTML = 'Loading data...';

        post('fabioai_chatbot_get_word_cloud', dateParams())
            .then(function (result) {
                if (!result || !result.success || !result.data) {
                    throw new Error('Invalid response');
                }
                renderWordCloud(result.data);
            })
            .catch(function () {
                cloudBox.innerHTML = '<p class="fabioai-chatbot-conv-muted">Unable to load data.</p>';
            });
    }

    if (cloudBox) {
        cloudBox.addEventListener('click', function (event) {
            const btn = event.target.closest('.fabioai-chatbot-cloud-word');

            if (!btn) {
                return;
            }

            // analytics.js met à jour le champ de recherche, recharge ses tableaux,
            // puis émet l'événement qui recharge ce fichier.
            if (window.fabioaiAnalyticsFilters && typeof window.fabioaiAnalyticsFilters.setSearch === 'function') {
                window.fabioaiAnalyticsFilters.setSearch(btn.dataset.word || '');
            }

            const card = document.getElementById('fabioai-chatbot-conversations-card');
            if (card) {
                card.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    }

    // =========================
    // CHARGEMENT
    // =========================
    // Liste des conversations seule (tri, filtre, page, intention).
    function loadConversations() {
        body.innerHTML = '<tr><td colspan="6">Loading data...</td></tr>';

        const extra = dateParams({
            sort: currentSort,
            click_filter: currentFilter,
            page: currentPage
        });

        if (currentIntent) {
            extra.intent = currentIntent;
        }

        post('fabioai_chatbot_get_conversations', extra)
            .then(function (result) {
                if (!result || !result.success || !result.data) {
                    throw new Error('Invalid response');
                }

                renderKpis(result.data.kpis);
                renderIntentChips(result.data);
                renderNotice(result.data.notice);
                renderRows(result.data.rows);
                renderPagination(result.data.pagination);
            })
            .catch(function () {
                body.innerHTML = '<tr><td colspan="6">Unable to load conversations.</td></tr>';
                if (paginationBox) {
                    paginationBox.innerHTML = '';
                }
            });
    }

    // Tout recharger (période ou mots-clés modifiés).
    function loadAll() {
        loadIntents();
        loadWordCloud();
        loadConversations();
    }

    // =========================
    // PANNEAU LATÉRAL
    // =========================
    function renderLink(link) {
        const url = safeUrl(link.url);
        const title = escapeHtml(link.title || link.url || 'Link');
        const typeLabel = link.type === 'source' ? 'Source' : 'Link in answer';
        const titleHtml = url
            ? '<a href="' + escapeHtml(url) + '" target="_blank" rel="noopener noreferrer">' + title + '</a>'
            : title;

        let state;
        if (link.clicked) {
            const when = link.clicked_at ? ' · ' + escapeHtml(splitDateTime(link.clicked_at)[1]) : '';
            const times = (link.clicks > 1) ? ' · ' + link.clicks + '×' : '';
            state = '<span class="fabioai-chatbot-link-state is-clicked">✅ Clicked' + when + times + '</span>';
        } else {
            state = '<span class="fabioai-chatbot-link-state is-not-clicked">⚪ Not clicked</span>';
        }

        return '<li class="fabioai-chatbot-drawer-link">' +
            '<div class="fabioai-chatbot-drawer-link-main">' + titleHtml + '<small>' + typeLabel + '</small></div>' +
            state +
        '</li>';
    }

    function renderDetail(data) {
        const dt = splitDateTime(data.started);
        const page = escapeHtml(data.page_url || '');
        const count = (data.messages || []).length;

        const summary =
            '<div class="fabioai-chatbot-drawer-summary">' +
                '<div><span>Started</span><strong>' + escapeHtml(dt[0]) + ' ' + escapeHtml(dt[1]) + '</strong></div>' +
                '<div><span>Exchanges</span><strong>' + count + '</strong></div>' +
                '<div><span>Links clicked</span><strong>' + clickIndicator(data.clicked || 0, data.offered || 0) + '</strong></div>' +
                (page ? '<div><span>First page</span><strong>' + page + '</strong></div>' : '') +
            '</div>' +
            (data.is_legacy ? '<p class="fabioai-chatbot-drawer-legacy">Recorded before session tracking: no click data is available for this exchange.</p>' : '');

        const messagesHtml = (data.messages || []).map(function (message) {
            const mdt = splitDateTime(message.time);
            const links = Array.isArray(message.links) ? message.links : [];
            const linksHtml = links.length
                ? '<ul class="fabioai-chatbot-drawer-links">' + links.map(renderLink).join('') + '</ul>'
                : '<p class="fabioai-chatbot-conv-muted">No link offered in this answer.</p>';

            return '' +
                '<div class="fabioai-chatbot-drawer-exchange">' +
                    '<div class="fabioai-chatbot-drawer-meta">' + escapeHtml(mdt[1]) + ' ' + intentBadge(message.intent) + '</div>' +
                    '<div class="fabioai-chatbot-drawer-q">' + escapeHtml(message.question || '') + '</div>' +
                    '<div class="fabioai-chatbot-drawer-a">' + escapeHtml(message.answer || '') + '</div>' +
                    linksHtml +
                '</div>';
        }).join('');

        drawerBody.innerHTML = summary + messagesHtml;
    }

    function openDrawer(id, row) {
        if (!drawer) {
            return;
        }

        lastFocusedRow = row || null;
        drawerBody.innerHTML = '<div class="fabioai-chatbot-analytics-empty-state">Loading conversation...</div>';
        drawer.classList.add('is-open');
        drawer.setAttribute('aria-hidden', 'false');
        if (overlay) {
            overlay.classList.add('is-open');
        }
        if (drawerClose) {
            drawerClose.focus();
        }

        post('fabioai_chatbot_get_conversation_detail', { conversation_id: id })
            .then(function (result) {
                if (!result || !result.success || !result.data) {
                    throw new Error('Invalid response');
                }
                renderDetail(result.data);
            })
            .catch(function () {
                drawerBody.innerHTML = '<div class="fabioai-chatbot-analytics-empty-state">Unable to load this conversation.</div>';
            });
    }

    function closeDrawer() {
        if (!drawer) {
            return;
        }

        drawer.classList.remove('is-open');
        drawer.setAttribute('aria-hidden', 'true');
        if (overlay) {
            overlay.classList.remove('is-open');
        }
        if (lastFocusedRow && typeof lastFocusedRow.focus === 'function') {
            lastFocusedRow.focus();
        }
    }

    // =========================
    // ÉVÉNEMENTS
    // =========================
    body.addEventListener('click', function (event) {
        const row = event.target.closest('.fabioai-chatbot-conv-row');
        if (row) {
            openDrawer(row.dataset.id, row);
        }
    });

    body.addEventListener('keydown', function (event) {
        if (event.key !== 'Enter' && event.key !== ' ') {
            return;
        }

        const row = event.target.closest('.fabioai-chatbot-conv-row');
        if (row) {
            event.preventDefault();
            openDrawer(row.dataset.id, row);
        }
    });

    if (paginationBox) {
        paginationBox.addEventListener('click', function (event) {
            const btn = event.target.closest('.fabioai-chatbot-conv-page-btn');
            if (!btn || btn.disabled) {
                return;
            }

            const target = parseInt(btn.dataset.page, 10) || 1;
            if (target < 1 || target === currentPage) {
                return;
            }

            currentPage = target;
            loadConversations();
        });
    }

    if (sortSelect) {
        sortSelect.addEventListener('change', function () {
            currentSort = sortSelect.value || 'date_desc';
            currentPage = 1;
            loadConversations();
        });
    }

    if (filterSelect) {
        filterSelect.addEventListener('change', function () {
            currentFilter = filterSelect.value || 'all';
            currentPage = 1;
            loadConversations();
        });
    }

    // Période ou mots-clés modifiés dans analytics.js.
    document.addEventListener('fabioai-analytics-filters', function () {
        currentPage = 1;
        loadAll();
    });

    if (drawerClose) {
        drawerClose.addEventListener('click', closeDrawer);
    }

    if (overlay) {
        overlay.addEventListener('click', closeDrawer);
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && drawer && drawer.classList.contains('is-open')) {
            closeDrawer();
        }
    });

    loadAll();
});
