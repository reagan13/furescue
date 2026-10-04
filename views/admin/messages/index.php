<?php

declare(strict_types=1);

$composeBtn = button_html(
    'Start conversation',
    'default',
    'default',
    '',
    'plus',
    'data-action="compose" id="amsg-compose"'
);

$sendBtn = button_html(
    'Send',
    'default',
    'default',
    'amsg-send',
    'send-horizontal',
    'id="amsg-send"',
    'submit'
);

$backBtn = button_html(
    'Back',
    'ghost',
    'sm',
    'amsg-back',
    'arrow-left',
    'id="amsg-back" aria-label="Back to conversations"'
);

$adminChildren = '
  <div class="page-head">
    <div>
      <span class="stamp stamp--coral">Communication</span>
      <h1 class="page-title">Messages</h1>
      <p class="page-sub">Staff inbox for reports, cases, and adoption applications.</p>
    </div>
    <div class="page-head-actions">
      ' . search_control('amsg-search', 'Search name, message…', '', 'amsg-search', 'aria-label="Search conversations"') . '
      ' . $composeBtn . '
    </div>
  </div>
  <div class="panel amsg-panel">
    <div class="amsg-shell is-inbox-empty" id="amsg-shell">
      <aside class="amsg-list" aria-label="Conversations">
        <div class="amsg-list-head">
          <i data-lucide="message-square"></i>
          <h2 class="amsg-list-title">Conversations</h2>
        </div>
        <div class="amsg-list-items" id="amsg-threads">
          <div class="amsg-empty">
            <i data-lucide="inbox"></i>
            <p class="amsg-empty-title">Loading conversations&hellip;</p>
            <p class="amsg-empty-text">Conversations appear when someone messages this admin, or after you start a conversation.</p>
          </div>
        </div>
      </aside>
      <section class="amsg-thread" aria-live="polite">
        <div class="amsg-empty is-hidden" id="amsg-empty">
          <i data-lucide="messages-square"></i>
          <p class="amsg-empty-title">No conversation selected</p>
          <p class="amsg-empty-text">Pick a conversation, or start one. Threads show up when someone messages this admin, or after Start conversation.</p>
        </div>
        <header class="amsg-thread-head is-hidden" id="amsg-thread-head">
          ' . $backBtn . '
          <span class="amsg-avatar" id="amsg-peer-avatar">&nbsp;</span>
          <div class="amsg-thread-title">
            <strong id="amsg-peer-name">&nbsp;</strong>
            <span class="stamp stamp--sm stamp--accent" id="amsg-context-chip"></span>
          </div>
        </header>
        <div class="amsg-thread-scroll is-hidden" id="amsg-scroll"></div>
        <form class="amsg-composer is-hidden" id="amsg-form">
          <label class="visually-hidden" for="amsg-input">Message</label>
          <input type="text" id="amsg-input" class="input" placeholder="Write a message&hellip;" autocomplete="off" maxlength="4000">
          ' . $sendBtn . '
        </form>
      </section>
    </div>
  </div>';
