import { createIcons, icons } from "lucide";
import { timeAgo } from "/assets/js/lib/format.js";
import { Button } from "/shared/components/button/button.js";
import { findThread, state } from "../state.js";
import { contextLabel, contextStamp, esc, initialOf, dayKey, dayLabel } from "../util.js";

function bubbleRow(msg) {
  const mine = state.me && msg.sender_id === state.me.id;
  const readTick = mine && msg.read_at ? '<i data-lucide="check-check"></i>' : "";
  return `
  <div class="amsg-bubble-row ${mine ? "amsg-bubble-row--mine" : "amsg-bubble-row--theirs"}">
    <div class="amsg-bubble">
      <p class="amsg-bubble-text">${esc(msg.message_text)}</p>
      <span class="amsg-bubble-meta"><span>${esc(timeAgo(msg.sent_at))}</span>${readTick}</span>
    </div>
  </div>`;
}

export function showThreadChrome(thread) {
  document.getElementById("amsg-empty")?.classList.add("is-hidden");
  document.getElementById("amsg-thread-head")?.classList.remove("is-hidden");
  document.getElementById("amsg-scroll")?.classList.remove("is-hidden");
  document.getElementById("amsg-form")?.classList.remove("is-hidden");

  const nameEl = document.getElementById("amsg-peer-name");
  const avatarEl = document.getElementById("amsg-peer-avatar");
  const chipEl = document.getElementById("amsg-context-chip");
  if (nameEl) nameEl.textContent = thread?.other_user_name || "Conversation";
  if (avatarEl) avatarEl.textContent = initialOf(thread?.other_user_name);
  if (chipEl) {
    chipEl.textContent = contextLabel(thread?.related_type);
    chipEl.className = `stamp stamp--sm ${contextStamp(thread?.related_type)}`;
  }
}

export function hideThreadChrome() {
  document.getElementById("amsg-shell")?.classList.remove("is-thread-open");
  document.getElementById("amsg-empty")?.classList.remove("is-hidden");
  document.getElementById("amsg-thread-head")?.classList.add("is-hidden");
  document.getElementById("amsg-scroll")?.classList.add("is-hidden");
  document.getElementById("amsg-form")?.classList.add("is-hidden");
}

export function syncInboxLayout() {
  const shell = document.getElementById("amsg-shell");
  const emptyInbox = !!state.loadError || !state.threads.length;
  shell?.classList.toggle("is-inbox-empty", emptyInbox);

  if (emptyInbox) {
    hideThreadChrome();
    document.getElementById("amsg-empty")?.classList.add("is-hidden");
    return;
  }

  if (!state.currentKey) {
    hideThreadChrome();
    return;
  }

  shell?.classList.add("is-thread-open");
  showThreadChrome(findThread(state.currentKey));
}

export function renderPaneNotice(text) {
  const scroll = document.getElementById("amsg-scroll");
  if (!scroll) return;
  scroll.innerHTML = `
      <div class="amsg-empty">
        <i data-lucide="message-circle"></i>
        <p class="amsg-empty-text">${esc(text)}</p>
      </div>`;
  createIcons({ icons });
}

export function renderPane(messages, { forceScroll = true } = {}) {
  const scroll = document.getElementById("amsg-scroll");
  if (!scroll) return;
  const stickToBottom =
    forceScroll || scroll.scrollHeight - scroll.scrollTop - scroll.clientHeight < 120;

  let html = "";
  let prev = null;
  for (const msg of messages) {
    if (!prev || dayKey(prev.sent_at) !== dayKey(msg.sent_at)) {
      html += `<span class="amsg-day-sep">${esc(dayLabel(msg.sent_at))}</span>`;
    }
    html += bubbleRow(msg);
    prev = msg;
  }
  if (!messages.length) {
    html = `
      <div class="amsg-empty">
        <i data-lucide="message-circle"></i>
        <p class="amsg-empty-text">No messages yet — say hello.</p>
      </div>`;
  }
  scroll.innerHTML = html;
  createIcons({ icons });
  if (stickToBottom) scroll.scrollTop = scroll.scrollHeight;
}

export function ThreadHead() {
  return `
        <header class="amsg-thread-head is-hidden" id="amsg-thread-head">
          ${Button({ text: "Back", variant: "ghost", size: "sm", icon: "arrow-left", className: "amsg-back", attrs: 'id="amsg-back" aria-label="Back to conversations"' })}
          <span class="amsg-avatar" id="amsg-peer-avatar">&nbsp;</span>
          <div class="amsg-thread-title">
            <strong id="amsg-peer-name">&nbsp;</strong>
            <span class="stamp stamp--sm stamp--accent" id="amsg-context-chip"></span>
          </div>
        </header>`;
}

export function ThreadEmpty() {
  return `
        <div class="amsg-empty is-hidden" id="amsg-empty">
          <i data-lucide="messages-square"></i>
          <p class="amsg-empty-title">No conversation selected</p>
          <p class="amsg-empty-text">Pick a conversation, or start one. Threads show up when someone messages this admin, or after Start conversation.</p>
        </div>`;
}
