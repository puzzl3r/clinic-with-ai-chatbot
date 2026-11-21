/* CarePoint AI chatbot widget */
(function () {
  'use strict';

  var API_URL = window.CHATBOT_API_URL || '/clinic-public/api/chat.php';
  var panel = null;
  var log = null;
  var input = null;
  var sendBtn = null;
  var menuPop = null;
  var historyLoaded = false;
  var busy = false;

  var GREETING = 'Hi! I am the CarePoint Assistant. I can help with booking an adjustment appointment, our branches, and clinic schedule. How can I help you today?';

  var SUGGESTIONS = [
    'What are your opening hours?',
    'Where are your branches?',
    'How do I book an adjustment?'
  ];

  function el(tag, className, text) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
  }

  function formatTime(createdAt) {
    if (!createdAt) return '';
    var d = new Date(String(createdAt).replace(' ', 'T'));
    if (isNaN(d.getTime())) return '';
    return d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
  }

  function saveTranscript() {
    var lines = ['CarePoint Assistant - chat transcript', 'Saved: ' + new Date().toLocaleString(), ''];
    var bubbles = log.querySelectorAll('.chat-msg');
    if (!bubbles.length) return;
    bubbles.forEach(function (b) {
      if (b.classList.contains('error')) return;
      var who = b.classList.contains('user') ? 'You' : 'CarePoint Assistant';
      var time = b.querySelector('time');
      var text = b.querySelector('span') ? b.querySelector('span').textContent : b.textContent;
      lines.push('[' + (time ? time.textContent : '') + '] ' + who + ': ' + text);
      lines.push('');
    });
    var blob = new Blob([lines.join('\n')], { type: 'text/plain' });
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url;
    a.download = 'carepoint-chat-transcript.txt';
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
  }

  function newChat() {
    if (busy) return;
    fetch(API_URL, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'new_chat' })
    }).catch(function () {}).finally(function () {
      log.innerHTML = '';
      historyLoaded = true;
      addMessage('bot', GREETING);
      addSuggestions();
    });
  }

  function addSuggestions() {
    var wrap = el('div', 'chat-suggestions');
    SUGGESTIONS.forEach(function (text) {
      var chip = el('button', 'chat-suggest');
      chip.type = 'button';
      chip.textContent = text;
      chip.addEventListener('click', function () {
        sendMessage(text);
      });
      wrap.appendChild(chip);
    });
    log.appendChild(wrap);
    log.scrollTop = log.scrollHeight;
  }

  function clearSuggestions() {
    var wrap = log.querySelector('.chat-suggestions');
    if (wrap) wrap.remove();
  }

  function addMessage(sender, text, createdAt) {
    var bubble = el('div', 'chat-msg ' + (sender === 'user' ? 'user' : 'bot'));
    bubble.appendChild(el('span', null, text));
    var t = formatTime(createdAt);
    if (t) bubble.appendChild(el('time', null, t));
    log.appendChild(bubble);
    log.scrollTop = log.scrollHeight;
    return bubble;
  }

  function addError(text) {
    var bubble = el('div', 'chat-msg error', text);
    log.appendChild(bubble);
    log.scrollTop = log.scrollHeight;
  }

  function showTyping(on) {
    var existing = log.querySelector('.chat-typing');
    if (on && !existing) {
      var typing = el('div', 'chat-typing');
      typing.appendChild(el('span'));
      typing.appendChild(el('span'));
      typing.appendChild(el('span'));
      log.appendChild(typing);
      log.scrollTop = log.scrollHeight;
    } else if (!on && existing) {
      existing.remove();
    }
  }

  function loadHistory() {
    if (historyLoaded) return;
    historyLoaded = true;
    fetch(API_URL, { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        var messages = (data && data.messages) || [];
        if (messages.length === 0) {
          addMessage('bot', GREETING);
          addSuggestions();
        } else {
          messages.forEach(function (m) { addMessage(m.sender, m.message, m.created_at); });
        }
      })
      .catch(function () {
        addMessage('bot', GREETING);
        addSuggestions();
      });
  }

  function sendMessage(fromSuggestion) {
    var text = (fromSuggestion || input.value).trim();
    if (!text || busy) return;
    busy = true;
    sendBtn.disabled = true;
    if (!fromSuggestion) input.value = '';
    clearSuggestions();
    addMessage('user', text);
    showTyping(true);

    fetch(API_URL, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ message: text })
    })
      .then(function (r) {
        return r.json().then(function (data) { return { ok: r.ok, data: data }; });
      })
      .then(function (res) {
        showTyping(false);
        if (res.ok && res.data && res.data.reply) {
          addMessage('bot', res.data.reply.message, res.data.reply.created_at);
        } else {
          addError((res.data && res.data.error) || 'Something went wrong. Please try again.');
        }
      })
      .catch(function () {
        showTyping(false);
        addError('Could not reach the chat server. Please check your connection and try again.');
      })
      .finally(function () {
        busy = false;
        sendBtn.disabled = false;
        input.focus();
      });
  }

  function buildWidget() {
    var launcher = el('button', 'chatbot-launcher');
    launcher.type = 'button';
    launcher.setAttribute('aria-label', 'Open CarePoint AI chatbot');
    launcher.innerHTML =
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
      '<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>' +
      '</svg><span class="chatbot-badge"></span>';

    panel = el('div', 'chatbot-panel');
    panel.setAttribute('role', 'dialog');
    panel.setAttribute('aria-label', 'CarePoint AI chatbot');

    var head = el('div', 'chatbot-head');
    head.innerHTML =
      '<span class="chatbot-avatar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="7" width="16" height="12" rx="3"/><path d="M12 7V4M9 12h.01M15 12h.01M9.5 16h5"/></svg></span>' +
      '<span><b>CarePoint Assistant</b><small><span class="chatbot-status"></span>Online &middot; AI chatbot</small></span>';

    var expandBtn = el('button', 'chatbot-expand');
    expandBtn.type = 'button';
    expandBtn.title = 'Expand';
    expandBtn.setAttribute('aria-label', 'Expand chat');
    var ICON_EXPAND = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 3H5a2 2 0 0 0-2 2v3M21 8V5a2 2 0 0 0-2-2h-3M3 16v3a2 2 0 0 0 2 2h3M16 21h3a2 2 0 0 0 2-2v-3"/></svg>';
    var ICON_COLLAPSE = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 3v3a2 2 0 0 1-2 2H3M21 8h-3a2 2 0 0 1-2-2V3M3 16h3a2 2 0 0 1 2 2v3M16 21v-3a2 2 0 0 1 2-2h3"/></svg>';
    expandBtn.innerHTML = ICON_EXPAND;
    expandBtn.addEventListener('click', function () {
      var expanded = panel.classList.toggle('expanded');
      expandBtn.innerHTML = expanded ? ICON_COLLAPSE : ICON_EXPAND;
      expandBtn.title = expanded ? 'Collapse' : 'Expand';
      expandBtn.setAttribute('aria-label', expanded ? 'Collapse chat' : 'Expand chat');
    });

    var menuWrap = el('div', 'chatbot-menu');
    var menuBtn = el('button', 'chatbot-menu-btn');
    menuBtn.type = 'button';
    menuBtn.setAttribute('aria-label', 'Chat options');
    menuBtn.setAttribute('aria-haspopup', 'true');
    menuBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="19" cy="12" r="1.8"/></svg>';
    menuPop = el('div', 'chatbot-menu-pop');
    menuPop.setAttribute('role', 'menu');

    var newChatItem = el('button', 'chatbot-menu-item');
    newChatItem.type = 'button';
    newChatItem.setAttribute('role', 'menuitem');
    newChatItem.textContent = 'New Chat';
    var saveItem = el('button', 'chatbot-menu-item');
    saveItem.type = 'button';
    saveItem.setAttribute('role', 'menuitem');
    saveItem.textContent = 'Save Transcript';
    menuPop.appendChild(newChatItem);
    menuPop.appendChild(saveItem);
    menuWrap.appendChild(menuBtn);
    menuWrap.appendChild(menuPop);

    var closeBtn = el('button', 'chatbot-close');
    closeBtn.type = 'button';
    closeBtn.setAttribute('aria-label', 'Close chat');
    closeBtn.textContent = '\u00d7';
    head.appendChild(expandBtn);
    head.appendChild(menuWrap);
    head.appendChild(closeBtn);

    var privacy = el('div', 'chatbot-privacy');
    privacy.textContent = 'Privacy notice: please do not share sensitive medical information, passwords, or payment details in this chat. Conversations are recorded so clinic staff can assist you better.';

    log = el('div', 'chatlog');

    var inputRow = el('form', 'chat-input-row');
    input = el('input');
    input.type = 'text';
    input.placeholder = 'Type your message...';
    input.maxLength = 2000;
    input.setAttribute('aria-label', 'Chat message');
    input.autocomplete = 'off';
    sendBtn = el('button');
    sendBtn.type = 'submit';
    sendBtn.setAttribute('aria-label', 'Send message');
    sendBtn.innerHTML =
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>';
    inputRow.appendChild(input);
    inputRow.appendChild(sendBtn);

    panel.appendChild(head);
    panel.appendChild(privacy);
    panel.appendChild(log);
    panel.appendChild(inputRow);

    document.body.appendChild(launcher);
    document.body.appendChild(panel);

    launcher.addEventListener('click', function () {
      if (panel.classList.toggle('open')) {
        loadHistory();
        input.focus();
      }
    });
    closeBtn.addEventListener('click', function () {
      panel.classList.remove('open');
      launcher.focus();
    });
    menuBtn.addEventListener('click', function (ev) {
      ev.stopPropagation();
      menuPop.classList.toggle('open');
    });
    document.addEventListener('click', function () {
      menuPop.classList.remove('open');
    });
    menuPop.addEventListener('click', function (ev) {
      ev.stopPropagation();
    });
    newChatItem.addEventListener('click', function () {
      menuPop.classList.remove('open');
      newChat();
    });
    saveItem.addEventListener('click', function () {
      menuPop.classList.remove('open');
      saveTranscript();
    });
    inputRow.addEventListener('submit', function (ev) {
      ev.preventDefault();
      sendMessage();
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', buildWidget);
  } else {
    buildWidget();
  }
})();
