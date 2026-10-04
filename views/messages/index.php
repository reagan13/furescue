<div class="rpage-head">
  <div>
    <h2 class="rpage-title">Messages</h2>
    <p class="rpage-sub">Talk with the rescue team about your reports, cases, and adoption applications.</p>
  </div>
  <button type="button" class="rbtn rbtn--solid" id="msg-compose">
    <i data-lucide="message-square-plus"></i><span>New message</span>
  </button>
</div>

<div class="rcard msg-shell is-inbox-empty" id="msg-shell">
  <aside class="msg-list" aria-label="Conversations">
    <div class="msg-list-head">
      <h3 class="rcard-title"><i data-lucide="message-square"></i> Conversations</h3>
    </div>
    <div class="msg-list-items" id="msg-threads">
      <div class="rempty"><p class="rempty-text">Loading conversations&hellip;</p></div>
    </div>
  </aside>

  <section class="msg-thread" aria-live="polite">
    <div class="rempty msg-thread-empty is-hidden" id="msg-empty">
      <i data-lucide="messages-square"></i>
      <p class="rempty-title">No conversation selected</p>
      <p class="rempty-text">Pick a conversation on the left, or start one from a report, case, or adoption.</p>
    </div>

    <header class="msg-thread-head is-hidden" id="msg-thread-head">
      <button type="button" class="rbtn rbtn--ghost rbtn--sm msg-back" id="msg-back" aria-label="Back to conversations">
        <i data-lucide="arrow-left"></i>
      </button>
      <span class="msg-avatar" id="msg-peer-avatar">&nbsp;</span>
      <div class="msg-thread-title">
        <strong id="msg-peer-name">&nbsp;</strong>
        <span class="rchip rchip--sky" id="msg-context-chip"></span>
      </div>
    </header>

    <div class="msg-thread-scroll is-hidden" id="msg-scroll"></div>

    <form class="msg-composer is-hidden" id="msg-form">
      <label class="visually-hidden" for="msg-input">Message</label>
      <input type="text" id="msg-input" class="input" placeholder="Write a message&hellip;" autocomplete="off" maxlength="4000">
      <button type="submit" class="rbtn rbtn--solid msg-send" id="msg-send">
        <i data-lucide="send-horizontal"></i><span class="msg-send-label">Send</span>
      </button>
    </form>
  </section>
</div>
