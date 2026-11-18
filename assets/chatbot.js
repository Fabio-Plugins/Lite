document.addEventListener('DOMContentLoaded', function () {

  // =========================
  // DOM ELEMENTS
  // =========================
  const form = document.getElementById('fabioai-chatbot-chat-form');
  const inputMessage = document.getElementById('fabioai-chatbot-chat-input');
  const messagesBox = document.getElementById('fabioai-chatbot-chat-messages');
  let fabioaiChatbotStartTime = 0;
  let fabioaiChatbotTimerInterval = null;

  // =========================
  // CONVERSATION HISTORY SETTINGS
  // =========================
  // Nombre maximum de messages renvoyés au serveur avec chaque question
  // (6 = 3 questions + 3 réponses). Le serveur applique les mêmes limites.
  const HISTORY_MAX_MESSAGES = 6;
  // Longueur maximale de chaque message de l'historique (en caractères).
  const HISTORY_MAX_CHARS = 500;
  // Messages d'erreur du chatbot, jamais renvoyés comme historique.
  const HISTORY_SKIP_TEXTS = [
    'Sorry, I could not generate an answer at this time.',
    '??????????'
  ];

  // =========================
  // ANALYTICS : IDENTIFIANT DE SESSION ET SUIVI DES CLICS
  // =========================
  // Identifiant aléatoire (UUID) généré dans le navigateur et gardé dans sessionStorage :
  // il disparaît à la fermeture de l'onglet. Aucun cookie, aucune donnée personnelle.
  const ANALYTICS_SESSION_KEY = 'fabioai_chatbot_sid';
  let analyticsSessionIdFallback = '';

  function generateUuid() {
    if (window.crypto && typeof window.crypto.randomUUID === 'function') {
      return window.crypto.randomUUID();
    }

    const bytes = new Uint8Array(16);
    if (window.crypto && typeof window.crypto.getRandomValues === 'function') {
      window.crypto.getRandomValues(bytes);
    } else {
      for (let i = 0; i < 16; i++) bytes[i] = Math.floor(Math.random() * 256);
    }
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;

    const hex = Array.prototype.map.call(bytes, function (b) {
      return ('0' + b.toString(16)).slice(-2);
    }).join('');

    return hex.slice(0, 8) + '-' + hex.slice(8, 12) + '-' + hex.slice(12, 16) + '-' + hex.slice(16, 20) + '-' + hex.slice(20);
  }

  function getAnalyticsSessionId() {
    try {
      const stored = sessionStorage.getItem(ANALYTICS_SESSION_KEY);
      if (stored && /^[a-f0-9-]{36}$/i.test(stored)) return stored;

      const created = generateUuid();
      sessionStorage.setItem(ANALYTICS_SESSION_KEY, created);
      return created;
    } catch (e) {
      // Stockage bloqué : identifiant valable seulement pour cette page
      if (!analyticsSessionIdFallback) analyticsSessionIdFallback = generateUuid();
      return analyticsSessionIdFallback;
    }
  }

  // Enregistre un clic sur un lien affiché par le chatbot (envoi discret, sans bloquer la navigation).
  function trackLinkClick(conversationId, url) {
    if (!conversationId || !url || typeof fabioaiChatbotData === 'undefined') return;

    const payload = new FormData();
    payload.append('action', 'fabioai_chatbot_track_click');
    payload.append('nonce', fabioaiChatbotData.nonce || '');
    payload.append('session_id', getAnalyticsSessionId());
    payload.append('conversation_id', String(conversationId));
    payload.append('url', url);

    try {
      if (navigator.sendBeacon && navigator.sendBeacon(fabioaiChatbotData.ajaxUrl, payload)) {
        return;
      }
    } catch (e) {
      // on essaie fetch ci-dessous
    }

    try {
      fetch(fabioaiChatbotData.ajaxUrl, {
        method: 'POST',
        body: payload,
        credentials: 'same-origin',
        keepalive: true
      }).catch(function () {});
    } catch (e) {
      // le suivi ne doit jamais gêner le visiteur
    }
  }

  function handleBotLinkClick(event) {
    const link = event.target && event.target.closest ? event.target.closest('a') : null;
    if (!link || !link.href) return;

    const botMessage = link.closest('.fabioai-chatbot-message.bot');
    if (!botMessage || !botMessage.dataset.conversationId) return;

    // Clic gauche ou clic molette uniquement (le clic droit n'ouvre pas le lien)
    if (event.type === 'auxclick' && event.button !== 1) return;

    trackLinkClick(botMessage.dataset.conversationId, link.href);
  }

  // =========================
  // SESSION STORAGE MANAGEMENT
  // =========================
  // Format de session : on ne sauvegarde que les messages (texte brut d'origine).
  // L'état ouvert/fermé n'est jamais sauvegardé : après un changement de page,
  // le chatbot s'affiche fermé et un clic le rouvre avec l'historique.
  // Incrémenter SESSION_VERSION si le format change : les anciennes sessions sont ignorées.
  const SESSION_VERSION = 2;

  // Clé unique par site. Elle ne dépend PAS du mode d'affichage (intégré dans
  // le contenu ou bulle flottante) : un visiteur qui passe d'une page de
  // contenu (chatbot intégré) à une page de forum (bulle) retrouve ainsi la
  // même conversation, et inversement.
  const getChatbotSessionKey = () => {
    const siteKey = window.location.hostname.replace(/\./g, '_');
    return `fabioai_chatbot_session_${siteKey}`;
  };

  // Sauvegarde la session (messages uniquement, texte brut)
  function saveChatbotSession() {
    try {
      const messages = [];

      document.querySelectorAll('#fabioai-chatbot-chat-messages .fabioai-chatbot-message').forEach((msgElement) => {
        // Ignore le message de chargement (timer en cours)
        if (msgElement.querySelector('.fabioai-chatbot-timer')) return;

        const rawText = msgElement.dataset.rawText;
        if (typeof rawText !== 'string' || rawText === '') return;

        if (msgElement.classList.contains('user')) {
          messages.push({ type: 'user', text: rawText });
          return;
        }

        let sources = [];
        if (msgElement.dataset.rawSources) {
          try {
            const parsed = JSON.parse(msgElement.dataset.rawSources);
            if (Array.isArray(parsed)) sources = parsed;
          } catch (e) {
            sources = [];
          }
        }
        messages.push({
          type: 'bot',
          text: rawText,
          sources,
          conversationId: parseInt(msgElement.dataset.conversationId, 10) || 0
        });
      });

      sessionStorage.setItem(
        getChatbotSessionKey(),
        JSON.stringify({
          version: SESSION_VERSION,
          messages,
        })
      );
    } catch (e) {
      // Stockage bloqué par le navigateur : le chatbot fonctionne sans historique entre les pages
    }
  }

  function loadChatbotSession() {
    try {
      const savedSession = sessionStorage.getItem(getChatbotSessionKey());
      if (!savedSession) return null;

      const parsed = JSON.parse(savedSession);
      if (!parsed || parsed.version !== SESSION_VERSION || !Array.isArray(parsed.messages)) {
        return null; // ancien format ou données invalides
      }
      return parsed;
    } catch (e) {
      return null;
    }
  }

  function restoreChatbotSession() {
    if (!messagesBox) return;

    const savedSession = loadChatbotSession();
    if (!savedSession || savedSession.messages.length === 0) return;

    // Restaurer les messages à partir du texte brut
    savedSession.messages.forEach((msg) => {
      if (msg.type === 'bot') {
        addMessage(msg.text, 'bot', msg.sources || [], msg.conversationId || 0);
      } else if (msg.type === 'user') {
        addMessage(msg.text, 'user');
      }
    });

    // Mode intégré : le chatbot s'affiche réduit (un clic le rouvre).
    // La bulle flottante reste fermée par défaut (classe fabioai-chatbot-hidden du template).
    const inlineChatBox = document.getElementById('fabioai-chatbot');
    if (inlineChatBox && !inlineChatBox.closest('#fabioai-chatbot-floating-chat')) {
      inlineChatBox.classList.add('fabioai-chatbot-collapsed');
    }
  }

  // =========================
  // INLINE MODE : OPEN / CLOSE BUTTON ICON
  // =========================
  // Le bouton sert à ouvrir ET fermer (icône et libellé synchronisés)
  function updateInlineToggleButton() {
    const inlineChatBox = document.getElementById('fabioai-chatbot');
    if (!inlineChatBox || inlineChatBox.closest('#fabioai-chatbot-floating-chat')) return;

    const toggleButton = inlineChatBox.querySelector('#fabioai-chatbot-close-chat, .fabioai-chatbot-close-chat');
    if (!toggleButton) return;

    const isCollapsed = inlineChatBox.classList.contains('fabioai-chatbot-collapsed');

    toggleButton.textContent = isCollapsed ? '+' : '✕';
    toggleButton.setAttribute('aria-label', isCollapsed ? 'Open chat' : 'Close chat');
    toggleButton.setAttribute('aria-expanded', isCollapsed ? 'false' : 'true');
  }

  // =========================
  // SCROLL JUSQU'À LA DERNIÈRE RÉPONSE DU CHATBOT
  // =========================
  // Après un changement de page, la conversation est restaurée pendant que le
  // chatbot est masqué (aucun scroll possible). À chaque ouverture, on fait donc
  // défiler la zone de messages jusqu'au début de la dernière réponse du bot.
  // Seule la zone de messages défile, jamais la page.
  function scrollToLastBotMessage() {
    if (!messagesBox) return;

    const botMessages = messagesBox.querySelectorAll('.fabioai-chatbot-message.bot');
    let lastBotMessage = null;

    for (let i = botMessages.length - 1; i >= 0; i--) {
      // Ignore le message de chargement (timer en cours)
      if (!botMessages[i].querySelector('.fabioai-chatbot-timer')) {
        lastBotMessage = botMessages[i];
        break;
      }
    }

    if (!lastBotMessage) return;

    const scrollOffset = window.innerWidth > 768 ? 120 : 12;
    messagesBox.scrollTop = Math.max(lastBotMessage.offsetTop - scrollOffset, 0);
  }

  // Appelé juste après l'ouverture : une fois tout de suite, puis après le
  // prochain rendu, au cas où l'affichage ne serait pas encore calculé.
  function scrollToLastBotMessageWhenOpened() {
    scrollToLastBotMessage();

    if (typeof window.requestAnimationFrame === 'function') {
      window.requestAnimationFrame(scrollToLastBotMessage);
    }
  }

  // =========================
  // RESTORE SESSION ON LOAD
  // =========================
  restoreChatbotSession();
  updateInlineToggleButton();

  function linkify(text) {
    const urlRegex = /(https?:\/\/[^\s<]+)/g;
    return text.replace(urlRegex, function (url) {
      return `<a href="${url}">${url}</a>`;
    });
  }

  // Supprime les marqueurs de citation parasites ajoutés par certains modèles
  // (caractères de la zone privée Unicode, affichés comme des carrés vides).
  // Écrits ici avec des séquences d'échappement pour rester lisibles et sûrs.
  function stripModelCitationMarkers(text) {
    if (typeof text !== 'string') return '';

    return text
      .replace(/\s*[\[【]\s*S\d+\s*[\]】]/g, '')
      .replace(/[^]*/g, '')
      .replace(/[-]/g, '');
  }

  function formatBotReadableAnswer(text) {
    if (typeof text !== 'string') return '';

    let formatted = text.trim();

    // Sépare le timer du reste
    formatted = formatted.replace(/^(⏱\s*\d+(\.\d+)?s)\s*/i, '$1\n\n');

    // Sépare les produits après une virgule
    formatted = formatted.replace(
      /,\s+(and\s+)?(?=[A-Z][A-Za-z0-9]+(?:\s+[A-Z][A-Za-z0-9]+)+\s+Series\s+\d+\s+[A-Z][A-Za-z0-9]+)/g,
      '\n\n• '
      );

    // Sépare les produits même sans virgule
    formatted = formatted.replace(
      /(Series\s+\d+\s+[A-Z][A-Za-z0-9]+)\s+(and\s+)?(?=[A-Z][A-Za-z0-9]+(?:\s+[A-Z][A-Za-z0-9]+)+\s+Series\s+\d+\s+[A-Z][A-Za-z0-9]+)/g,
      '$1\n\n• '
      );

    // Ajoute une première puce après deux-points
    formatted = formatted.replace(
      /:\s+(?=[A-Z][A-Za-z0-9]+(?:\s+[A-Z][A-Za-z0-9]+)+\s+Series\s+\d+\s+[A-Z][A-Za-z0-9]+)/g,
      ':\n\n• '
      );

    // Aère les informations produit sans toucher au stock
    formatted = formatted
    .replace(/\s*\(SKU:/gi, '\nSKU:')
    .replace(/,\s*price:/gi, '\nPrice:')
      .replace(/\)\s*/g, '');

    // Sépare les introductions courantes
    formatted = formatted.replace(
      /(The available|Available|Here are|Voici|Les produits|Les options)/g,
      '\n\n$1'
      );

    // Nettoyage
    formatted = formatted.replace(/•\s+•\s+/g, '• ');

    return formatted.trim();
  }

  // =========================
  // UTIL : ADD MESSAGE
  // =========================
  function addMessage(text, type = 'user', sources = [], conversationId = 0) {
    if (!messagesBox) return null;

    const msg = document.createElement('div');
    msg.className = 'fabioai-chatbot-message ' + type;

    // Texte brut d'origine : c'est lui (et jamais le HTML rendu) qui est sauvegardé en session
    msg.dataset.rawText = typeof text === 'string' ? text : '';

    // Identifiant de la conversation enregistrée côté serveur (sert à rattacher les clics)
    if (type === 'bot' && conversationId > 0) {
      msg.dataset.conversationId = String(conversationId);
    }

    const bubble = document.createElement('div');
    bubble.className = 'fabioai-chatbot-bubble';

    if (type === 'bot') {
      if (typeof text === 'string' && text.includes('fabioai-chatbot-loader')) {
        bubble.innerHTML = text;
      } else {
        const safeText = stripModelCitationMarkers(typeof text === 'string' ? text : '');

        const readableText = formatBotReadableAnswer(safeText);

        bubble.innerHTML = linkify(readableText)
          .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/\n+/g, '<br><br>');

        const validSources = Array.isArray(sources)
        ? sources
        .filter(source => source && source.url)
        .slice(0, 5)
        : [];

        msg.dataset.rawSources = JSON.stringify(validSources);

        if (validSources.length > 0) {
          const sourcesBox = document.createElement('div');
          sourcesBox.className = 'fabioai-chatbot-sources';

          const title = document.createElement('div');
          title.className = 'fabioai-chatbot-sources-title';
          title.textContent = 'Sources:';
          sourcesBox.appendChild(title);

          const list = document.createElement('ul');

          validSources.forEach((source, index) => {
            const item = document.createElement('li');
            const link = document.createElement('a');

            link.href = source.url;

            link.textContent =
            source.title && source.title.trim() !== ''
            ? source.title
            : 'Source ' + (index + 1);

            item.appendChild(link);
            list.appendChild(item);
          });

          sourcesBox.appendChild(list);
          bubble.appendChild(sourcesBox);
        }
      }
    } else {
      bubble.textContent = text;
    }

    msg.appendChild(bubble);
    messagesBox.appendChild(msg);

    // Scroll interne à la zone de messages uniquement (jamais de scroll de la page)
    if (type === 'user') {
      messagesBox.scrollTop = messagesBox.scrollHeight;
    } else {
      const scrollOffset = window.innerWidth > 768 ? 120 : 12;

      messagesBox.scrollTo({
        top: Math.max(msg.offsetTop - scrollOffset, 0),
        behavior: 'smooth'
      });
    }

    return msg;
  }

  // =========================
  // CONVERSATION HISTORY (envoyé au serveur avec chaque question)
  // =========================
  // Coupe un message trop long. Pour une réponse du bot, on garde la FIN :
  // c'est là que se trouve en général sa question à l'internaute.
  // Pour un message de l'internaute, on garde le début.
  function truncateForHistory(text, role) {
    if (text.length <= HISTORY_MAX_CHARS) return text;

    return role === 'assistant'
      ? '…' + text.slice(-HISTORY_MAX_CHARS)
      : text.slice(0, HISTORY_MAX_CHARS) + '…';
  }

  // Construit l'historique à partir des messages affichés.
  // À appeler AVANT d'ajouter la nouvelle question à l'écran.
  function getConversationHistory() {
    const welcome = (typeof fabioaiChatbotData !== 'undefined' && fabioaiChatbotData.welcomeText)
      ? String(fabioaiChatbotData.welcomeText).replace(/\s+/g, ' ').trim()
      : '';

    const history = [];

    document.querySelectorAll('#fabioai-chatbot-chat-messages .fabioai-chatbot-message').forEach(function (msgElement) {
      // Ignore le message de chargement (timer en cours)
      if (msgElement.querySelector('.fabioai-chatbot-timer')) return;

      const role = msgElement.classList.contains('user') ? 'user' : 'assistant';
      const bubble = msgElement.querySelector('.fabioai-chatbot-bubble');

      let text = (typeof msgElement.dataset.rawText === 'string')
        ? msgElement.dataset.rawText
        : (bubble ? bubble.textContent : '');

      // Retire le chronomètre ajouté devant les réponses du bot (ex. "⏱ 6.7s")
      if (role === 'assistant') {
        text = text.replace(/^\s*⏱\s*\d+(\.\d+)?s\s*/, '');
      }

      text = text.replace(/\s+/g, ' ').trim();
      if (!text) return;

      // Ignore le message de bienvenue et les messages d'erreur
      if (role === 'assistant' && (text === welcome || HISTORY_SKIP_TEXTS.indexOf(text) !== -1)) {
        return;
      }

      history.push({ role: role, content: truncateForHistory(text, role) });
    });

    return history.slice(-HISTORY_MAX_MESSAGES);
  }

  // =========================
  // FUNCTION : SEND MESSAGE
  // =========================
  function sendMessage(message) {
    if (!message) return;

    // Historique de la conversation, capturé AVANT l'ajout de la nouvelle question
    const history = getConversationHistory();

    const inlineChatBox = document.getElementById('fabioai-chatbot');

    if (inlineChatBox) {
      inlineChatBox.classList.remove('fabioai-chatbot-collapsed');
      updateInlineToggleButton();
    }

    // UI
    addMessage(message, 'user');

    addMessage('<span class="fabioai-chatbot-timer">0.0s</span>', 'bot');

    fabioaiChatbotStartTime = Date.now();

    fabioaiChatbotTimerInterval = setInterval(function () {
      const elapsed = ((Date.now() - fabioaiChatbotStartTime) / 1000).toFixed(1);
      const loader = messagesBox.querySelector('.fabioai-chatbot-timer');

      if (loader) {
        loader.textContent = '⏱ ' + elapsed + 's';
      }
    }, 100);

    const payload = new FormData();
    payload.append('action', 'fabioai_chatbot_chat_request');
    payload.append('message', message);
    payload.append('nonce', fabioaiChatbotData.nonce || '');
    payload.append('session_id', getAnalyticsSessionId());
    payload.append('page_url', window.location.pathname || '');

    if (history.length > 0) {
      payload.append('history', JSON.stringify(history));
    }

    fetch(fabioaiChatbotData.ajaxUrl, {
      method: 'POST',
      body: payload,
      credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
      clearInterval(fabioaiChatbotTimerInterval);
      const responseTime = ((Date.now() - fabioaiChatbotStartTime) / 1000).toFixed(1);
        // Remove the "..."
      const loaders = messagesBox.querySelectorAll('.fabioai-chatbot-message.bot');
      if (loaders.length) {
        messagesBox.removeChild(loaders[loaders.length - 1]);
      }

      if (data && data.success && data.data && data.data.response) {
        addMessage(
          "⏱ " + responseTime + "s\n\n" + data.data.response,
          'bot',
          data.data.sources || [],
          parseInt(data.data.conversation_id, 10) || 0
          );

        saveChatbotSession();
      } else {
        addMessage('Sorry, I could not generate an answer at this time.', 'bot');

        saveChatbotSession();
      }
    })
    .catch(() => {
      clearInterval(fabioaiChatbotTimerInterval);
      const loaders = messagesBox.querySelectorAll('.fabioai-chatbot-message.bot');
      if (loaders.length) {
        messagesBox.removeChild(loaders[loaders.length - 1]);
      }
      addMessage('??????????', 'bot');
      saveChatbotSession();
    });
  }

  // =========================
  // SEND MESSAGE
  // =========================
  if (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();

      const message = inputMessage.value.trim();
      if (!message) return;

      inputMessage.value = '';
      sendMessage(message);
    });
  }

  // =========================
  // SUIVI DES CLICS SUR LES LIENS DU CHATBOT
  // =========================
  if (messagesBox) {
    messagesBox.addEventListener('click', handleBotLinkClick);
    messagesBox.addEventListener('auxclick', handleBotLinkClick);
  }

  // =========================
  // BOT WELCOME TEXT (display only if no history)
  // =========================
  const savedSession = loadChatbotSession();
  if (
    typeof fabioaiChatbotData !== 'undefined' &&
    fabioaiChatbotData.welcomeText &&
    fabioaiChatbotData.welcomeText.trim() !== '' &&
    (!savedSession || !savedSession.messages || savedSession.messages.length === 0)
    ) {
    addMessage(fabioaiChatbotData.welcomeText.trim(), 'bot');
}

  // =========================
  // FLOATING BUBBLE TOGGLE
  // =========================
const floatingBubble = document.getElementById('fabioai-chatbot-floating-bubble');
const floatingChat = document.getElementById('fabioai-chatbot-floating-chat');

if (floatingBubble && floatingChat) {
  floatingBubble.addEventListener('click', function () {
    floatingChat.classList.toggle('fabioai-chatbot-hidden');

    // À l'ouverture, la conversation défile jusqu'à la dernière réponse du chatbot
    if (!floatingChat.classList.contains('fabioai-chatbot-hidden')) {
      scrollToLastBotMessageWhenOpened();
    }
  });
}

  // =========================
  // CLOSE / OPEN BUTTON
  // =========================
const closeButtons = document.querySelectorAll('#fabioai-chatbot-close-chat, .fabioai-chatbot-close-chat');

closeButtons.forEach(function (closeButton) {
  closeButton.addEventListener('click', function () {
    const floatingChatBox = closeButton.closest('#fabioai-chatbot-floating-chat');
    const inlineChatBox = closeButton.closest('#fabioai-chatbot');

      // Mode bulle flottante : le bouton masque la fenêtre
    if (floatingChatBox) {
      floatingChatBox.classList.add('fabioai-chatbot-hidden');
      return;
    }

      // Mode intégré dans le contenu : ouvre / réduit sans effacer la conversation
    if (inlineChatBox) {
      inlineChatBox.classList.toggle('fabioai-chatbot-collapsed');
      updateInlineToggleButton();

      // À l'ouverture, la conversation défile jusqu'à la dernière réponse du chatbot
      if (!inlineChatBox.classList.contains('fabioai-chatbot-collapsed')) {
        scrollToLastBotMessageWhenOpened();
      }
    }
  });
});

});
