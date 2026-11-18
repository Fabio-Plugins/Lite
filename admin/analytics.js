/*
 * Page Analytics (Lite) : indicateurs du haut de page et filtre par mots-clés.
 * La période est fixe : les dernières 24 heures.
 *
 * Le filtre est partagé avec analytics-conversations.js (conversations, intentions,
 * nuage de mots) via window.fabioaiAnalyticsFilters et l'événement "fabioai-analytics-filters".
 */
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('fabioai-chatbot-search-keywords');

    if (!searchInput || typeof fabioaiChatbotAnalytics === 'undefined') {
        return;
    }

    let currentSearchKeywords = '';
    let searchTimeout;

    function getFilters() {
        return {
            range: '24h',
            dateFrom: '',
            dateTo: '',
            search: currentSearchKeywords
        };
    }

    function notifyFiltersChanged() {
        document.dispatchEvent(new CustomEvent('fabioai-analytics-filters', { detail: getFilters() }));
    }

    window.fabioaiAnalyticsFilters = {
        get: getFilters,
        // Utilisé par le nuage de mots : un clic sur un mot filtre toute la page.
        setSearch: function (text) {
            searchInput.value = text || '';
            currentSearchKeywords = searchInput.value.trim();
            loadKpis();
            notifyFiltersChanged();
        }
    };

    function renderKpis(kpis) {
        kpis = kpis || {};

        const values = {
            'fabioai-chatbot-kpi-total': kpis.total_questions || 0,
            'fabioai-chatbot-kpi-unique': kpis.unique_questions || 0
        };

        Object.keys(values).forEach(function (id) {
            const el = document.getElementById(id);
            if (el) {
                el.textContent = values[id];
            }
        });
    }

    function loadKpis() {
        const params = new URLSearchParams();
        params.append('action', 'fabioai_chatbot_get_analytics');
        params.append('_ajax_nonce', fabioaiChatbotAnalytics.nonce);

        if (currentSearchKeywords) {
            params.append('search_keywords', currentSearchKeywords);
        }

        fetch(fabioaiChatbotAnalytics.ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: params.toString(),
            credentials: 'same-origin'
        })
        .then(function (response) {
            return response.json();
        })
        .then(function (result) {
            if (!result || !result.success || !result.data) {
                throw new Error('Invalid analytics response.');
            }

            renderKpis(result.data.kpis);
        })
        .catch(function () {
            renderKpis(null);
        });
    }

    // ===== RECHERCHE PAR MOTS-CLÉS =====
    searchInput.addEventListener('input', function () {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function () {
            currentSearchKeywords = searchInput.value.trim();
            loadKpis();
            notifyFiltersChanged();
        }, 500);
    });

    loadKpis();
});
