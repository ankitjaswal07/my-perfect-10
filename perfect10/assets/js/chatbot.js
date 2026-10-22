/**
 * Perfect 10 — 24/7 Website-Scanning Concierge & Conversational Lead Capture.
 *
 * Works 100% without any API key by scanning the live WordPress website,
 * answering visitor questions with clickable source cards, and conversationally
 * collecting customer Name, Mobile Number, Email & Preferences so the concierge
 * team can get in touch soon. Also displays status when enhanced with an
 * optional Agent API key.
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-p10-chat]');
  if (!root || typeof p10Chat === 'undefined') return;

  var cfg = p10Chat;
  var i18n = cfg.i18n || {};
  var dialog = root.querySelector('dialog');
  var find = function (name) { return root.querySelector('[data-chat-' + name + ']'); };
  var input = root.querySelector('#p10-chat-input');
  var log = find('log');
  var status = find('status');
  var suggestions = find('suggestions');
  var minimize = find('minimize');
  var followBtn = find('follow');

  var busy = false;
  var controller = null;
  var muted = true;
  var last = '';
  var recognition = null;
  var flow = null;
  var generation = 0;
  var typing = null;
  var leadCompleted = false;
  var turnCount = 0;
  var synth = window.speechSynthesis;

  function setStatus(message, isError) {
    if (!status) return;
    status.textContent = message || '';
    status.classList.toggle('is-error', !!isError);
  }

  function updateProviderBadge() {
    var el = find('provider');
    if (!el) return;
    if (cfg.agentReady) {
      el.textContent = '● Live Website Scanner + Agent AI Active';
    } else {
      el.textContent = '● Live Website Scanner · Instant Concierge';
    }
  }
  updateProviderBadge();

  function scrollLog() {
    if (log) log.scrollTop = log.scrollHeight;
  }

  function bubble(text, speaker) {
    var el = document.createElement('div');
    el.className = 'p10-chat-message p10-chat-message--' + speaker;

    var label = document.createElement('strong');
    label.className = 'p10-chat-message__author';
    label.textContent = speaker === 'user' ? (i18n.you || 'You') : (i18n.assistant || 'Perfect 10 Concierge');
    el.appendChild(label);

    var p = document.createElement('p');
    p.className = 'p10-chat-message__text';
    p.textContent = text;
    el.appendChild(p);

    log.appendChild(el);
    scrollLog();
    return el;
  }

  function actionChip(parent, label, onClick, isPrimary) {
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = isPrimary ? 'p10-chat-chip p10-chat-chip--gold' : 'p10-chat-chip';
    btn.textContent = label;
    btn.addEventListener('click', onClick);
    parent.appendChild(btn);
    return btn;
  }

  function showTyping() {
    if (typing) return;
    typing = document.createElement('div');
    typing.className = 'p10-chat__typing';
    typing.setAttribute('role', 'status');
    typing.setAttribute('aria-label', i18n.typing || 'Concierge is scanning website…');
    for (var i = 0; i < 3; i++) {
      typing.appendChild(document.createElement('span'));
    }
    log.appendChild(typing);
    scrollLog();
  }

  function hideTyping() {
    if (typing && typing.parentNode) typing.parentNode.removeChild(typing);
    typing = null;
  }

  function speak(text) {
    if (!synth || muted || !text) return;
    synth.cancel();
    var u = new SpeechSynthesisUtterance(text);
    u.lang = document.documentElement.lang || 'en';
    synth.speak(u);
  }

  function stopVoice() {
    if (synth) synth.cancel();
    if (recognition) {
      try { recognition.abort(); } catch (e) { /* noop */ }
    }
    var mic = find('mic');
    if (mic) mic.textContent = 'Voice Input';
  }

  function renderSuggestions() {
    if (!suggestions) return;
    suggestions.replaceChildren();
    (cfg.suggestions || []).forEach(function (text) {
      var chip = document.createElement('button');
      chip.type = 'button';
      chip.className = 'p10-chat__suggestion';
      chip.textContent = text;
      chip.addEventListener('click', function () {
        if (busy) return;
        if (/callback|book a reservation/i.test(text) && cfg.followup) {
          startLeadFlow('');
          return;
        }
        input.value = text;
        submitMessage();
      });
      suggestions.appendChild(chip);
    });
  }

  function welcome() {
    var text = cfg.welcome || 'Welcome to Perfect 10. Ask me about companions, cities, rates, or leave your mobile & email for a private concierge callback.';
    var el = bubble(text, 'assistant');
    if (cfg.followup && !leadCompleted) {
      var actions = document.createElement('div');
      actions.className = 'p10-chat-inline-actions';
      actionChip(actions, 'Leave Mobile & Email for Callback', function () {
        startLeadFlow('');
      }, true);
      if (cfg.childSiteUrl && cfg.childSiteName) {
        var ext = document.createElement('a');
        ext.className = 'p10-chat-chip';
        ext.href = cfg.childSiteUrl;
        ext.target = '_blank';
        ext.rel = 'noopener';
        ext.textContent = cfg.childSiteName + ' ↗';
        actions.appendChild(ext);
      }
      el.appendChild(actions);
    }
    renderSuggestions();
  }

  function reset() {
    generation++;
    if (controller) controller.abort();
    stopVoice();
    flow = null;
    busy = false;
    last = '';
    turnCount = 0;
    if (input) {
      input.value = '';
      input.placeholder = 'Ask about companions, cities, rates, or request a callback…';
    }
    log.replaceChildren();
    hideTyping();
    setStatus('');
    if (followBtn) followBtn.hidden = !cfg.followup;
    welcome();
  }

  function openDialog() {
    if (!dialog) return;
    dialog.classList.remove('is-minimized');
    if (minimize) minimize.setAttribute('aria-expanded', 'true');
    if (typeof dialog.showModal === 'function') {
      if (!dialog.open) dialog.showModal();
    } else {
      dialog.setAttribute('open', '');
    }
    find('open').setAttribute('aria-expanded', 'true');
    if (input) input.focus();
    scrollLog();
  }

  function closeDialog() {
    if (!dialog) return;
    if (typeof dialog.close === 'function' && dialog.open) {
      dialog.close();
    } else {
      dialog.removeAttribute('open');
    }
  }

  find('open').addEventListener('click', function () {
    if (dialog && dialog.open) {
      closeDialog();
    } else {
      openDialog();
    }
  });

  if (find('close')) {
    find('close').addEventListener('click', closeDialog);
  }

  if (minimize) {
    minimize.addEventListener('click', function () {
      var min = !dialog.classList.contains('is-minimized');
      dialog.classList.toggle('is-minimized', min);
      minimize.setAttribute('aria-expanded', String(!min));
      if (!min && input) input.focus();
    });
  }

  dialog.addEventListener('close', function () {
    generation++;
    if (controller) controller.abort();
    busy = false;
    hideTyping();
    stopVoice();
    find('open').setAttribute('aria-expanded', 'false');
    dialog.classList.remove('is-minimized');
  });

  dialog.addEventListener('click', function (event) {
    if (event.target === dialog) closeDialog();
  });

  if (find('reset')) {
    find('reset').addEventListener('click', function () {
      leadCompleted = false;
      reset();
      if (input) input.focus();
    });
  }

  if (find('mute')) {
    find('mute').addEventListener('click', function () {
      muted = !muted;
      if (muted) stopVoice();
      find('mute').textContent = muted ? 'Audio Off' : 'Audio On';
      find('mute').setAttribute('aria-pressed', String(muted));
    });
    if (!synth) find('mute').disabled = true;
  }

  var Recognition = window.SpeechRecognition || window.webkitSpeechRecognition;
  if (Recognition && find('mic')) {
    find('mic').hidden = false;
    recognition = new Recognition();
    recognition.interimResults = false;
    recognition.lang = document.documentElement.lang || 'en';
    recognition.onresult = function (event) {
      input.value = event.results[0][0].transcript.slice(0, 600);
      setStatus('Voice captured — press Send.');
      input.focus();
    };
    recognition.onerror = function () {
      setStatus('Microphone unavailable. Please type your message instead.', true);
    };
    recognition.onend = function () { find('mic').textContent = 'Voice Input'; };
    find('mic').addEventListener('click', function () {
      if (busy) return;
      try {
        recognition.start();
        find('mic').textContent = 'Listening…';
      } catch (e) {
        setStatus('Microphone busy. Please type instead.', true);
      }
    });
  }

  async function request(path, payload) {
    controller = new AbortController();
    var timer = setTimeout(function () { controller.abort(); }, 22000);
    try {
      var res = await fetch(cfg.root + path, {
        method: 'POST',
        credentials: 'same-origin',
        signal: controller.signal,
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': cfg.nonce
        },
        body: JSON.stringify(payload)
      });
      var data = await res.json();
      if (!res.ok) throw new Error(data.message || (i18n.error || 'Request failed.'));
      return data;
    } finally {
      clearTimeout(timer);
    }
  }

  /**
   * Conversational Lead Capture Flow:
   * Collects Name -> Mobile Number -> Email -> Companion/City/Notes -> Saves to WP Admin Enquiries!
   */
  function startLeadFlow(initialContext) {
    if (!cfg.followup || busy) return;
    flow = {
      step: 'name',
      data: {
        name: '',
        phone: '',
        email: '',
        location: initialContext || '',
        message: ''
      }
    };
    if (suggestions) suggestions.replaceChildren();
    var el = bubble(
      'Wonderful — our private concierge desk would love to assist you personally! First, what is your name?',
      'assistant'
    );
    var actions = document.createElement('div');
    actions.className = 'p10-chat-inline-actions';
    actionChip(actions, 'Cancel callback', function () {
      flow = null;
      input.placeholder = 'Ask about companions, cities, rates, or request a callback…';
      bubble('No problem at all — ask me anything else about our companions, cities, or packages!', 'assistant');
    }, false);
    el.appendChild(actions);
    input.placeholder = 'Enter your name…';
    input.focus();
  }

  async function saveLead(leadData) {
    var gen = generation;
    busy = true;
    showTyping();
    setStatus('Sending your details to our private concierge desk…');
    try {
      var result = await request('/followup', {
        name: leadData.name,
        phone: leadData.phone,
        email: leadData.email,
        location: leadData.location || '',
        message: leadData.message || 'Callback requested via Perfect 10 Concierge Chatbot.',
        consent: true,
        notice: cfg.notice
      });
      if (gen !== generation) return;
      hideTyping();
      flow = null;
      leadCompleted = true;
      input.placeholder = 'Ask anything else about companions, locations, or blogs…';
      var confirmEl = bubble(
        result.message || ('Thank you, ' + leadData.name + '! Your mobile number and email have been received — we will get in touch with you very soon.'),
        'assistant'
      );
      if (cfg.phone && cfg.phoneHref) {
        var row = document.createElement('div');
        row.className = 'p10-chat-inline-actions';
        var callLink = document.createElement('a');
        callLink.className = 'p10-chat-chip p10-chat-chip--gold';
        callLink.href = cfg.phoneHref;
        callLink.textContent = 'Call Direct: ' + cfg.phone;
        row.appendChild(callLink);
        confirmEl.appendChild(row);
      }
      setStatus('Callback saved · Our concierge will get in touch soon.');
      speak(result.message);
    } catch (err) {
      hideTyping();
      setStatus(err.message || 'Could not save right now.', true);
      bubble(err.message || 'Please check your mobile number or email and try again.', 'assistant');
    } finally {
      if (gen === generation) busy = false;
    }
  }

  function handleLeadStep(text) {
    if (!flow) return;

    if (/^(cancel|stop|nevermind)$/i.test(text)) {
      flow = null;
      input.placeholder = 'Ask about companions, cities, rates, or request a callback…';
      bubble('Callback request cancelled. How else may I help you explore Perfect 10?', 'assistant');
      return;
    }

    if (flow.step === 'name') {
      if (text.length < 2 || text.length > 80) {
        bubble('Please share your first name or preferred name (2–80 characters).', 'assistant');
        return;
      }
      flow.data.name = text;
      flow.step = 'phone';
      bubble('Nice to meet you, ' + text + '! What is the best mobile number to reach you on?', 'assistant');
      input.placeholder = 'Enter your mobile number (e.g. 310-555-0199)…';
      input.focus();
      return;
    }

    if (flow.step === 'phone') {
      var cleanedPhone = text.trim();
      if (!/^\+?[0-9 ().\-]{7,40}$/.test(cleanedPhone)) {
        bubble('Please enter a valid mobile number with area code (for example, 310-555-0199).', 'assistant');
        return;
      }
      flow.data.phone = cleanedPhone;
      flow.step = 'email';
      bubble('Thank you! And what is your email address so we can send discreet confirmation details?', 'assistant');
      input.placeholder = 'Enter your email address (e.g. name@email.com)…';
      input.focus();
      return;
    }

    if (flow.step === 'email') {
      var cleanedEmail = text.trim();
      if (cleanedEmail.toLowerCase() !== 'skip') {
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(cleanedEmail) || cleanedEmail.length > 150) {
          bubble('Please enter a valid email address (for example, alex@domain.com), or type “skip” if you prefer phone only.', 'assistant');
          return;
        }
        flow.data.email = cleanedEmail;
      }
      flow.step = 'notes';
      var noteEl = bubble(
        'Almost done! Which companion, city (e.g. Beverly Hills, Newport Beach), or preferred evening time should our coordinator note?',
        'assistant'
      );
      var skipRow = document.createElement('div');
      skipRow.className = 'p10-chat-inline-actions';
      actionChip(skipRow, 'Submit Now — Get in Touch Soon', function () {
        if (!flow || busy) return;
        flow.data.message = flow.data.location || 'Private concierge callback requested.';
        saveLead(flow.data);
      }, true);
      noteEl.appendChild(skipRow);
      input.placeholder = 'Companion, city, date/time, or press Submit Now…';
      input.focus();
      return;
    }

    if (flow.step === 'notes') {
      flow.data.message = text;
      saveLead(flow.data);
    }
  }

  if (followBtn) {
    followBtn.addEventListener('click', function () {
      if (busy) return;
      startLeadFlow('');
    });
  }

  function renderSourceCard(parent, src) {
    if (!src || !src.url || !src.title) return;
    try {
      var parsed = new URL(src.url, window.location.href);
      if (!/^https?:$/.test(parsed.protocol)) return;

      var card = document.createElement('a');
      card.className = 'p10-chat-source';
      card.href = parsed.href;
      if (src.external || parsed.origin !== window.location.origin) {
        card.target = '_blank';
        card.rel = 'noopener';
      }

      if (src.image) {
        var img = document.createElement('img');
        img.className = 'p10-chat-source__img';
        img.src = src.image;
        img.alt = src.title;
        img.loading = 'lazy';
        card.appendChild(img);
      }

      var body = document.createElement('div');
      body.className = 'p10-chat-source__body';

      if (src.badge) {
        var badge = document.createElement('span');
        badge.className = 'p10-chat-source__badge';
        badge.textContent = src.badge;
        body.appendChild(badge);
      }

      var title = document.createElement('strong');
      title.className = 'p10-chat-source__title';
      title.textContent = src.title + (src.external ? ' ↗' : ' →');
      body.appendChild(title);

      if (src.text) {
        var desc = document.createElement('span');
        desc.className = 'p10-chat-source__desc';
        desc.textContent = src.text;
        body.appendChild(desc);
      }

      card.appendChild(body);
      parent.appendChild(card);
    } catch (e) { /* ignore malformed URL */ }
  }

  async function submitMessage(retryText) {
    var text = typeof retryText === 'string' ? retryText : input.value.trim();
    if (!text || busy) return;

    if (!navigator.onLine) {
      setStatus(i18n.offline || 'You appear to be offline.', true);
      return;
    }

    if (!retryText) input.value = '';
    bubble(text, 'user');

    if (flow) {
      handleLeadStep(text);
      return;
    }

    // Check if visitor explicitly asked to leave their details / get a callback right away.
    if (cfg.followup && /^(call me|callback|leave my number|contact me|book now|request callback)$/i.test(text)) {
      startLeadFlow('');
      return;
    }

    if (suggestions) suggestions.replaceChildren();
    busy = true;
    turnCount++;
    setStatus('Scanning website profiles, cities, packages & guides…');
    showTyping();

    var started = generation;
    try {
      var data = await request('', { message: text });
      if (started !== generation) return;
      hideTyping();

      var el = bubble(data.answer, 'assistant');
      last = data.answer;

      if (data.sources && data.sources.length) {
        var list = document.createElement('div');
        list.className = 'p10-chat-sources';
        data.sources.forEach(function (src) {
          renderSourceCard(list, src);
        });
        el.appendChild(list);
      }

      // Conversational Lead Prompt: invite visitor to share Name, Mobile & Email so we can get in touch soon!
      if (cfg.followup && !leadCompleted && (data.prompt_lead || turnCount >= 1)) {
        var promptWrap = document.createElement('div');
        promptWrap.className = 'p10-chat-lead-cta';

        var promptNote = document.createElement('p');
        promptNote.className = 'p10-chat-lead-cta__text';
        promptNote.textContent = 'Would you like our concierge specialist to contact you privately? Leave your mobile number & email and we will get in touch soon:';
        promptWrap.appendChild(promptNote);

        var btnRow = document.createElement('div');
        btnRow.className = 'p10-chat-inline-actions';
        actionChip(btnRow, 'Share Mobile & Email', function () {
          startLeadFlow(text);
        }, true);
        if (cfg.directoryUrl) {
          var dirLink = document.createElement('a');
          dirLink.className = 'p10-chat-chip';
          dirLink.href = cfg.directoryUrl;
          dirLink.textContent = 'Browse Full Directory';
          btnRow.appendChild(dirLink);
        }
        promptWrap.appendChild(btnRow);
        el.appendChild(promptWrap);
      }

      scrollLog();
      speak(last);
      setStatus('');
    } catch (err) {
      if (started !== generation) return;
      hideTyping();
      var msg = err.name === 'AbortError'
        ? 'Request timed out. Please try again.'
        : (err.message || i18n.error);
      setStatus(msg, true);
      var errEl = bubble(msg, 'assistant');
      var retryWrap = document.createElement('div');
      retryWrap.className = 'p10-chat-inline-actions';
      actionChip(retryWrap, 'Try Again', function () {
        errEl.remove();
        submitMessage(text);
      }, true);
      errEl.appendChild(retryWrap);
    } finally {
      if (started === generation) {
        hideTyping();
        busy = false;
      }
    }
  }

  find('form').addEventListener('submit', function (event) {
    event.preventDefault();
    submitMessage();
  });

  reset();
})();
