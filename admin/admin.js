document.addEventListener('DOMContentLoaded', function () {
    const btn = document.getElementById('fabioai-chatbot-run-scan');
    const progressWrap = document.getElementById('fabioai-chatbot-scan-progress');

    if (!btn || !progressWrap || typeof fabioaiChatbotAdmin === 'undefined') {
        console.error('Admin JS not ready');
        return;
    }

    const iconEl = progressWrap.querySelector('.fabioai-scan-progress-icon');
    const labelEl = progressWrap.querySelector('.fabioai-scan-progress-label');
    const percentEl = progressWrap.querySelector('.fabioai-scan-progress-percent');
    const barEl = progressWrap.querySelector('.fabioai-scan-progress-bar');
    const detailEl = progressWrap.querySelector('.fabioai-scan-progress-detail');

    const manualScanEl = document.getElementById('fabioai-chatbot-last-manual-scan');
    const autoScanEl = document.getElementById('fabioai-chatbot-last-auto-scan');
    const nextAutoScanEl = document.getElementById('fabioai-chatbot-next-auto-scan');
    const totalIndexedEl = document.getElementById('fabioai-chatbot-total-indexed');
    const breakdownListEl = document.getElementById('fabioai-chatbot-scan-breakdown-list');
    const breakdownEmptyEl = document.getElementById('fabioai-chatbot-scan-breakdown-empty');

    function showProgress() {
        progressWrap.style.display = 'block';
    }

    function setDetail(text) {
        if (!text) {
            return;
        }
        detailEl.classList.remove('is-visible');
        window.requestAnimationFrame(function () {
            detailEl.textContent = text;
            detailEl.classList.add('is-visible');
        });
    }

    function renderBreakdown(breakdown) {
        if (!breakdownListEl) {
            return;
        }

        breakdownListEl.innerHTML = '';

        if (!breakdown || !breakdown.length) {
            if (breakdownEmptyEl) {
                breakdownEmptyEl.style.display = '';
            }
            return;
        }

        if (breakdownEmptyEl) {
            breakdownEmptyEl.style.display = 'none';
        }

        breakdown.forEach(function (entry) {
            const li = document.createElement('li');

            const label = document.createElement('span');
            label.className = 'fabioai-scan-breakdown-label';
            label.textContent = entry.label;

            const count = document.createElement('strong');
            count.className = 'fabioai-scan-breakdown-count';
            count.textContent = entry.count;

            li.appendChild(label);
            li.appendChild(count);
            breakdownListEl.appendChild(li);
        });
    }

    function updateSummary(summary) {
        if (!summary) {
            return;
        }
        if (manualScanEl) {
            manualScanEl.textContent = summary.last_manual_scan_text;
        }
        if (autoScanEl) {
            autoScanEl.textContent = summary.last_auto_scan_text;
        }
        if (nextAutoScanEl) {
            nextAutoScanEl.textContent = summary.next_auto_scan_text;
        }
        if (totalIndexedEl) {
            totalIndexedEl.textContent = summary.last_scan_count;
        }
        renderBreakdown(summary.breakdown);
    }

    function renderRunning(data) {
        showProgress();
        progressWrap.classList.remove('is-done');
        btn.disabled = true;

        const percent = Math.max(0, Math.min(100, parseInt(data.percent, 10) || 0));
        percentEl.textContent = percent + '%';
        barEl.style.width = percent + '%';
        labelEl.textContent = 'Scanning your content…';

        setDetail(data.last_title);
    }

    function renderDone(data) {
        showProgress();
        progressWrap.classList.add('is-done');
        btn.disabled = false;

        percentEl.textContent = '100%';
        barEl.style.width = '100%';
        labelEl.textContent = 'Scan completed';

        const count = parseInt(data.items_indexed, 10) || 0;
        setDetail(count + (count === 1 ? ' content indexed' : ' contents indexed'));
        // Le visuel reste affiché en permanence, il n'y a plus de disparition automatique.
    }

    function poll() {
        fetch(fabioaiChatbotAdmin.ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams({
                action: 'fabioai_chatbot_get_scan_progress',
                _ajax_nonce: fabioaiChatbotAdmin.nonce
            })
        })
        .then(res => res.json())
        .then(res => {
            if (!res.success) {
                return;
            }

            const data = res.data;

            if (data.status === 'running') {
                renderRunning(data);
            } else if (data.status === 'done') {
                renderDone(data);
            }

            updateSummary(data.summary);
        })
        .catch(err => {
            console.error(err);
        });
    }

    // Poll immédiatement (couvre le cas d'un scan déjà en cours à l'arrivée sur la page),
    // puis en continu pour détecter un scan auto qui démarrerait pendant la visite.
    poll();
    setInterval(poll, 3000);

    btn.addEventListener('click', function () {
        btn.disabled = true;

        fetch(fabioaiChatbotAdmin.ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams({
                action: 'fabioai_chatbot_run_scan',
                _ajax_nonce: fabioaiChatbotAdmin.nonce
            })
        })
        .then(res => res.json())
        .then(res => {
            if (!res.success) {
                alert(res.data || 'Scan failed');
                btn.disabled = false;
            }
            // En cas de succès, c'est le polling (déjà actif) qui affiche la barre et la confirmation.
        })
        .catch(err => {
            console.error(err);
            alert('Scan failed');
            btn.disabled = false;
        });
    });
});

jQuery(document).ready(function ($) {
    function updateModelOptions() {
        const provider = $('#fabioai-chatbot-provider').val();
        const modelSelect = $('#fabioai-chatbot-model');
        const models = (window.fabioaiChatbotAdmin && fabioaiChatbotAdmin.models && fabioaiChatbotAdmin.models[provider]) ? fabioaiChatbotAdmin.models[provider] : [];
        const currentModel = (window.fabioaiChatbotAdmin && fabioaiChatbotAdmin.currentModel) ? fabioaiChatbotAdmin.currentModel : '';

        modelSelect.empty();

        if (!models.length) {
            modelSelect.append($('<option></option>').val('').text('No model available'));
            return;
        }

        let hasSelected = false;

        models.forEach(function (model) {
            const isSelected = model.value === currentModel;
            const option = $('<option></option>').val(model.value).text(model.label);

            if (isSelected) {
                option.prop('selected', true);
                hasSelected = true;
            }

            modelSelect.append(option);
        });

        if (!hasSelected && models[0]) {
            modelSelect.val(models[0].value);
        }
    }

    updateModelOptions();

    $('#fabioai-chatbot-provider').on('change', function () {
        updateModelOptions();
    });

    function updateDisplaySettings() {
        var mode = $('input[name="fabioai_chatbot_display_mode"]:checked').val();
        var bbpressMode = $('input[name="fabioai_chatbot_bbpress_mode"]:checked').val();
        var isAuto = (mode === 'auto');
        var isBubble = (mode === 'bubble');
        // La position de la bulle reste active si bbPress utilise la bulle.
        var needsBubblePosition = isBubble || (bbpressMode === 'bubble');
        $('#fabioai-chatbot-insertion-row').find('select, input').prop('disabled', !isAuto);
        $('#fabioai-chatbot-bubble-row').find('select, input').prop('disabled', !needsBubblePosition);
    }

    $('input[name="fabioai_chatbot_display_mode"]').change(updateDisplaySettings);
    $('input[name="fabioai_chatbot_bbpress_mode"]').change(updateDisplaySettings);
    updateDisplaySettings();

    function updateGradientSettings() {
        var enabled = $('input[name="fabioai_chatbot_enable_gradients"]').is(':checked');
        $('select[name="fabioai_chatbot_gradient_style"]').prop('disabled', !enabled);
    }

    $('input[name="fabioai_chatbot_enable_gradients"]').change(updateGradientSettings);
    updateGradientSettings();

    $('.fabioai-chatbot-color-field').wpColorPicker();
});


// =========================
// STEP 1 : CUSTOM FIELDS CARD
// =========================
document.addEventListener('DOMContentLoaded', function () {
    var panel = document.getElementById('fabioai-chatbot-extra-panel');
    if (!panel) { return; }

    var tbody = panel.querySelector('tbody');
    var tpl = document.getElementById('fabioai-extra-row-template');
    var addBtn = document.getElementById('fabioai-extra-add');
    var list = document.getElementById('fabioai-extra-keys');
    var emptyRow = panel.querySelector('.fabioai-extra-empty');
    var cache = {};
    var counter = 0;

    function toggleEmpty() {
        emptyRow.style.display = tbody.querySelectorAll('.fabioai-extra-row').length ? 'none' : '';
    }

    function fill(keys) {
        list.innerHTML = '';
        keys.forEach(function (key) {
            var option = document.createElement('option');
            option.value = key;
            list.appendChild(option);
        });
    }

    function load(type) {
        if (cache[type]) { fill(cache[type]); return; }

        fetch(panel.getAttribute('data-ajax'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'fabioai_chatbot_get_meta_keys',
                _ajax_nonce: panel.getAttribute('data-nonce'),
                post_type: type
            })
        })
        .then(function (res) { return res.json(); })
        .then(function (res) {
            if (res && res.success) { cache[type] = res.data; fill(res.data); }
        })
        .catch(function () {});
    }

    tbody.addEventListener('focusin', function (e) {
        if (e.target.classList.contains('fabioai-extra-key')) {
            load(e.target.closest('tr').querySelector('select').value);
        }
    });

    tbody.addEventListener('change', function (e) {
        if (e.target.tagName === 'SELECT') { load(e.target.value); }
    });

    tbody.addEventListener('click', function (e) {
        var remove = e.target.closest('.fabioai-extra-remove');
        if (remove) {
            remove.closest('tr').remove();
            toggleEmpty();
        }
    });

    addBtn.addEventListener('click', function () {
        counter++;
        tbody.insertAdjacentHTML('beforeend', tpl.innerHTML.replace(/__INDEX__/g, 'n' + Date.now() + '_' + counter));
        toggleEmpty();
    });
});
