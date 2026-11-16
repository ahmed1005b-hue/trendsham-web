(function () {
  const toggleBtn = document.getElementById('ts-chat-toggle');
  const closeBtn = document.getElementById('ts-chat-close');
  const win = document.getElementById('ts-chat-window');
  const messagesEl = document.getElementById('ts-chat-messages');
  const form = document.getElementById('ts-chat-form');
  const input = document.getElementById('ts-chat-input');
  const sendBtn = document.getElementById('ts-chat-send');
  const widget = document.getElementById('trendsham-chat-widget');

  if (
    !widget || !widget.dataset.historyUrl || !widget.dataset.sendUrl ||
    !toggleBtn || !closeBtn || !win || !messagesEl || !form || !input || !sendBtn
  ) {
    return;
  }

  let loaded = false;
  let fallbackGuestId = null;
  let conversationStatus = 'open';
  let lastMessageId = 0;
  let pollTimer = null;
  const locale = (widget.dataset.locale || document.documentElement.lang || 'ar').split('-')[0];
  const localizedMessages = {
    ar: {
      typing: '...جاري الكتابة',
      loading: 'جاري تحميل المحادثة...',
      error: 'عذراً، صار خطأ. حاول مرة ثانية.',
      offline: 'تعذر الاتصال بالخادم، تأكد من الإنترنت وحاول مرة ثانية.',
      greeting: 'أهلاً فيك! أنا مساعد تريندشام. دورلك على منتج بالسعر واللون اللي بدك، تابعلك طلباتك، وفرجيك كوبوناتك 🙂',
      retry: 'إعادة المحاولة',
      open: 'افتح مساعد تريندشام',
      close: 'إغلاق المحادثة',
      agent: 'موظف الدعم',
      inStock: 'متوفر',
      outOfStock: 'غير متوفر',
      chips: ['تتبع طلبي', 'كوبوناتي', 'عروض اليوم', 'تحدث مع موظف'],
    },
    en: {
      typing: 'Typing...',
      loading: 'Loading conversation...',
      error: 'Sorry, something went wrong. Please try again.',
      offline: 'Could not connect to the server. Check your internet connection and try again.',
      greeting: 'Welcome! I’m Trendsham’s assistant. Ask me about your orders or any product 🙂',
      retry: 'Try again',
      open: 'Open Trendsham assistant',
      close: 'Close conversation',
      agent: 'Support agent',
      inStock: 'In stock',
      outOfStock: 'Out of stock',
      chips: [],
    },
    tr: {
      typing: 'Yazıyor...',
      loading: 'Sohbet yükleniyor...',
      error: 'Üzgünüz, bir hata oluştu. Lütfen tekrar deneyin.',
      offline: 'Sunucuya bağlanılamadı. İnternet bağlantınızı kontrol edip tekrar deneyin.',
      greeting: 'Hoş geldiniz! Ben Trendsham asistanıyım. Siparişlerinizi veya ürünleri sorabilirsiniz 🙂',
      retry: 'Tekrar dene',
      open: 'Trendsham asistanını aç',
      close: 'Sohbeti kapat',
      agent: 'Destek temsilcisi',
      inStock: 'Stokta',
      outOfStock: 'Stokta yok',
      chips: [],
    },
  };
  const messages = localizedMessages[locale] || localizedMessages.ar;

  toggleBtn.addEventListener('click', async () => {
    if (win.hidden) {
      openChat();
      if (!loaded) {
        loaded = await loadConversation();
      } else {
        setStatus(conversationStatus);
      }
      input.focus();
      return;
    }

    closeChat();
  });

  closeBtn.addEventListener('click', closeChat);

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !win.hidden) {
      closeChat();
    }
  });

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    const text = input.value.trim();
    if (!text || sendBtn.disabled) return;
    input.value = '';
    sendText(text);
  });

  async function sendText(text) {
    if (sendBtn.disabled) return;
    clearChips();
    appendMessage({ role: 'user', content: text });
    const typingEl = appendMessage({ role: 'assistant', content: messages.typing });
    typingEl.classList.add('ts-chat-message--typing');
    setBusy(true);

    try {
      const response = await fetch(widget.dataset.sendUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-CSRF-TOKEN': getCsrfToken(),
          'X-Guest-Id': getGuestId(),
        },
        credentials: 'same-origin',
        body: JSON.stringify({ message: text }),
      });

      if (!response.ok) {
        typingEl.classList.remove('ts-chat-message--typing');
        typingEl.textContent = messages.error;
        return;
      }

      const data = await response.json();
      typingEl.remove();
      // رقم رسالة العميل المحفوظة: حتى لا يعيد التحقق الدوري عرضها مرة ثانية
      if (data.message && data.message.id > lastMessageId) lastMessageId = data.message.id;
      setStatus(data.status);
      // reply = null: المحادثة عند موظف، والرد يصل لاحقاً
      if (data.reply) appendMessage(data.reply);
    } catch (error) {
      typingEl.classList.remove('ts-chat-message--typing');
      typingEl.textContent = messages.offline;
    } finally {
      setBusy(false);
      input.focus();
    }
  }

  function setStatus(status) {
    conversationStatus = status || 'open';
    if (conversationStatus === 'escalated' && !win.hidden) {
      startPolling();
    } else {
      stopPolling();
    }
  }

  // ردود الموظف: نتحقق كل 15 ثانية ما دامت المحادثة محوّلة والنافذة مفتوحة
  function startPolling() {
    if (pollTimer) return;
    pollTimer = window.setInterval(async () => {
      try {
        const data = await fetchHistory();
        (data.messages || []).filter((m) => m.id > lastMessageId).forEach((m) => appendMessage(m));
        setStatus(data.status);
      } catch (error) {
        // نحاول بالدورة التالية
      }
    }, 15000);
  }

  function stopPolling() {
    if (pollTimer) {
      window.clearInterval(pollTimer);
      pollTimer = null;
    }
  }

  function openChat() {
    win.hidden = false;
    win.setAttribute('aria-hidden', 'false');
    toggleBtn.setAttribute('aria-expanded', 'true');
    toggleBtn.setAttribute('aria-label', messages.close);
    toggleBtn.title = messages.close;
    toggleBtn.querySelector('i').className = 'bi bi-x-lg';
  }

  function closeChat() {
    stopPolling();
    win.hidden = true;
    win.setAttribute('aria-hidden', 'true');
    toggleBtn.setAttribute('aria-expanded', 'false');
    toggleBtn.setAttribute('aria-label', messages.open);
    toggleBtn.title = messages.open;
    toggleBtn.querySelector('i').className = 'bi bi-chat-dots-fill';
    toggleBtn.focus();
  }

  async function fetchHistory() {
    const response = await fetch(widget.dataset.historyUrl, {
      headers: { Accept: 'application/json', 'X-Guest-Id': getGuestId() },
      credentials: 'same-origin',
    });

    if (!response.ok) {
      throw new Error('Chat history request failed');
    }

    return response.json();
  }

  async function loadConversation() {
    messagesEl.replaceChildren();
    lastMessageId = 0;
    const loadingEl = appendMessage({ role: 'assistant', content: messages.loading });
    loadingEl.classList.add('ts-chat-message--typing');

    try {
      const data = await fetchHistory();
      loadingEl.remove();

      if (Array.isArray(data.messages) && data.messages.length > 0) {
        data.messages.forEach((message) => appendMessage(message));
      } else {
        // البائع يحصل على مساعد متجره، فاقتراحاته عن متجره لا عن التسوق
        const isSeller = widget.dataset.seller === '1' && locale === 'ar';
        appendMessage({
          role: 'assistant',
          content: isSeller ? 'أهلاً فيك! أنا مساعد متجرك 📊 اسألني عن مبيعاتك وطلباتك ومخزونك، أو قلّي "اكتب وصف لمنتج ...".' : messages.greeting,
          meta: { chips: isSeller ? ['مبيعاتي هالشهر', 'الطلبات اللي بانتظاري', 'المنتجات اللي قربت تخلص', 'نصائح لزيد مبيعاتي'] : messages.chips },
        });
      }
      setStatus(data.status);
      return true;
    } catch (error) {
      loadingEl.remove();
      appendMessage({ role: 'assistant', content: messages.greeting });
      appendLoadError();
      return false;
    }
  }

  function appendLoadError() {
    const errorEl = document.createElement('div');
    errorEl.className = 'ts-chat-load-error';
    errorEl.setAttribute('role', 'alert');

    const message = document.createElement('p');
    message.textContent = messages.offline;

    const retryBtn = document.createElement('button');
    retryBtn.className = 'ts-chat-retry';
    retryBtn.type = 'button';
    retryBtn.textContent = messages.retry;
    retryBtn.addEventListener('click', async () => {
      retryBtn.disabled = true;
      loaded = await loadConversation();
    });

    errorEl.append(message, retryBtn);
    messagesEl.appendChild(errorEl);
    messagesEl.scrollTop = messagesEl.scrollHeight;
  }

  // كل المحتوى يُبنى بـ textContent: لا HTML من الخادم أو المستخدم
  function appendMessage(message) {
    const meta = message.meta || {};
    const isUser = message.role === 'user';
    const bubble = document.createElement('div');
    bubble.className = `ts-chat-message ${isUser ? 'ts-chat-user' : 'ts-chat-assistant'}`;

    if (meta.agent) {
      bubble.classList.add('ts-chat-agent');
      const badge = textEl('span', 'ts-chat-agent-badge', messages.agent + (meta.agent_name ? ` · ${meta.agent_name}` : ''));
      bubble.appendChild(badge);
    }

    bubble.appendChild(document.createTextNode(String(message.content ?? '')));
    messagesEl.appendChild(bubble);

    if (message.id && message.id > lastMessageId) lastMessageId = message.id;

    if (!isUser && Array.isArray(meta.cards) && meta.cards.length) {
      messagesEl.appendChild(renderCards(meta.cards));
    }
    if (!isUser && Array.isArray(meta.chips) && meta.chips.length) {
      clearChips();
      messagesEl.appendChild(renderChips(meta.chips));
    }

    messagesEl.scrollTop = messagesEl.scrollHeight;
    return bubble;
  }

  function renderCards(cards) {
    const row = document.createElement('div');
    row.className = 'ts-chat-cards';

    cards.forEach((card) => {
      const href = safeUrl(card.url);
      const el = document.createElement(href ? 'a' : 'div');
      if (href) el.href = href;
      el.className = `ts-chat-card ts-chat-card--${card.type}`;

      if (card.type === 'product') {
        const image = safeUrl(card.image);
        if (image) {
          const img = document.createElement('img');
          img.src = image;
          img.alt = '';
          img.loading = 'lazy';
          // الصورة تكبّر البطاقة بعد التحميل: نبقى أسفل المحادثة
          img.addEventListener('load', () => { messagesEl.scrollTop = messagesEl.scrollHeight; }, { once: true });
          el.appendChild(img);
        }
        el.appendChild(textEl('span', 'ts-chat-card__name', card.name));
        const price = textEl('span', 'ts-chat-card__price', card.price_label);
        if (card.old_price_label) price.appendChild(textEl('del', '', card.old_price_label));
        el.appendChild(price);
        el.appendChild(textEl('span', card.in_stock ? 'ts-chat-card__stock' : 'ts-chat-card__stock is-out', card.in_stock ? messages.inStock : messages.outOfStock));
      } else if (card.type === 'order') {
        el.appendChild(textEl('span', 'ts-chat-card__name', card.order_number));
        el.appendChild(textEl('span', 'ts-chat-card__status', card.status_label));
        el.appendChild(textEl('span', 'ts-chat-card__price', card.total_label));
      } else {
        el.appendChild(textEl('span', 'ts-chat-card__name', card.title));
      }
      row.appendChild(el);
    });

    return row;
  }

  function renderChips(chips) {
    const row = document.createElement('div');
    row.className = 'ts-chat-chips';
    chips.forEach((chip) => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'ts-chat-chip';
      btn.textContent = chip;
      btn.addEventListener('click', () => sendText(chip));
      row.appendChild(btn);
    });
    return row;
  }

  // الاقتراحات تخص آخر رد فقط
  function clearChips() {
    messagesEl.querySelectorAll('.ts-chat-chips').forEach((el) => el.remove());
  }

  function textEl(tag, className, text) {
    const el = document.createElement(tag);
    if (className) el.className = className;
    el.textContent = String(text ?? '');
    return el;
  }

  // روابط http(s) فقط — لا javascript: ولا غيرها
  function safeUrl(url) {
    if (!url) return null;
    try {
      const parsed = new URL(url, window.location.href);
      return ['http:', 'https:'].includes(parsed.protocol) ? parsed.href : null;
    } catch (error) {
      return null;
    }
  }

  function setBusy(busy) {
    input.disabled = busy;
    sendBtn.disabled = busy;
    form.setAttribute('aria-busy', String(busy));
    messagesEl.querySelectorAll('.ts-chat-chip').forEach((btn) => { btn.disabled = busy; });
  }

  function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.content : '';
  }

  function getGuestId() {
    const key = 'trendsham_chat_guest_id';

    try {
      let id = window.localStorage.getItem(key);
      if (!id) {
        id = createGuestId();
        window.localStorage.setItem(key, id);
      }
      return id;
    } catch (error) {
      if (!fallbackGuestId) {
        fallbackGuestId = createGuestId();
      }
      return fallbackGuestId;
    }
  }

  function createGuestId() {
    if (window.crypto && typeof window.crypto.randomUUID === 'function') {
      return window.crypto.randomUUID();
    }

    // randomUUID غير متاح على HTTP العادي؛ getRandomValues متاح دائماً وقوي
    if (window.crypto && typeof window.crypto.getRandomValues === 'function') {
      const bytes = window.crypto.getRandomValues(new Uint8Array(16));
      return Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('');
    }

    return `${Date.now()}-${Math.random().toString(36).slice(2)}`;
  }
})();
