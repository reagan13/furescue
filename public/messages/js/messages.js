import { createIcons, icons } from "lucide";
import { apiFetch, getSessionUser, PORTAL_ROLES, requireAuth } from "/assets/js/lib/api.js";
import { bootstrapPageAuth } from "/assets/js/lib/page-auth.js";
import { esc, timeAgo } from "/assets/js/lib/format.js";
import { initResidentShell } from "/assets/js/components/resident-shell.js";
import { toast } from "/shared/components/toast/toast.js";
import { confirmDialog } from "/shared/components/dialog/dialog.js";
import { openCompose } from "./compose.js";

const CONTEXT_LABEL = { report: "Report", case: "Case", adoption: "Adoption" };

const state = {
  me: getSessionUser(),
  threads: [],
  currentKey: null,
  pollTimer: null,
  sending: false,
  loadError: "",
};

function threadKey(t) {
  return `${t.related_type}|${t.related_id}`;
}

function setHidden(id, hidden) {
  document.getElementById(id)?.classList.toggle("is-hidden", hidden);
}

function syncInboxLayout() {
  const shell = document.getElementById("msg-shell");
  const emptyInbox = !!state.loadError || !state.threads.length;
  shell?.classList.toggle("is-inbox-empty", emptyInbox);

  if (emptyInbox || !state.currentKey) {
    shell?.classList.remove("is-thread-open");
    setHidden("msg-empty", emptyInbox);
    setHidden("msg-thread-head", true);
    setHidden("msg-scroll", true);
    setHidden("msg-form", true);
    return;
  }

  shell?.classList.add("is-thread-open");
  setHidden("msg-empty", true);
  setHidden("msg-thread-head", false);
  setHidden("msg-scroll", false);
  setHidden("msg-form", false);
}

function contextLabel(type) {
  return CONTEXT_LABEL[type] || String(type || "Context");
}

function initialOf(name) {
  return String(name || "?").trim().charAt(0);
}

function renderThreads() {
  const wrap = document.getElementById("msg-threads");
  if (!wrap) return;

  if (state.loadError) {
    wrap.innerHTML = `
      <div class="rempty">
        <i data-lucide="wifi-off"></i>
        <p class="rempty-title">Could not load conversations</p>
        <p class="rempty-text">${esc(state.loadError)}</p>
      </div>`;
    createIcons({ icons });
    syncInboxLayout();
    return;
  }

  if (!state.threads.length) {
    wrap.innerHTML = `
      <div class="rempty">
        <i data-lucide="inbox"></i>
        <p class="rempty-title">No conversations yet</p>
        <p class="rempty-text">Start a conversation with City Vet about a report or adoption — tap New message.</p>
      </div>`;
    createIcons({ icons });
    syncInboxLayout();
    return;
  }

  wrap.innerHTML = state.threads
    .map(
      (t) => `
    <button type="button" class="msg-thread-item${threadKey(t) === state.currentKey ? " is-active" : ""}" data-key="${esc(threadKey(t))}">
      <span class="msg-avatar">${esc(initialOf(t.other_user_name))}</span>
      <span class="msg-item-main">
        <span class="msg-item-name">${esc(t.other_user_name || "Unknown user")}</span>
        <span class="msg-item-preview">${esc(String(t.last_message || "").replace(/\s+/g, " "))}</span>
      </span>
      <span class="msg-item-side">
        <span class="msg-item-time">${esc(timeAgo(t.last_sent_at))}</span>
        ${Number(t.unread_count) > 0 ? `<span class="msg-item-unread">${Number(t.unread_count)}</span>` : ""}
      </span>
    </button>`
    )
    .join("");
  createIcons({ icons });
  syncInboxLayout();
}

function findThread(key) {
  return state.threads.find((t) => threadKey(t) === key) || null;
}

function bubbleRow(msg) {
  const mine = state.me && msg.sender_id === state.me.id;
  const readTick = mine && msg.read_at ? '<i data-lucide="check-check"></i>' : "";
  return `
  <div class="msg-bubble-row ${mine ? "msg-bubble-row--mine" : "msg-bubble-row--theirs"}">
    <div class="msg-bubble">
      <p class="msg-bubble-text">${esc(msg.message_text)}</p>
      <span class="msg-bubble-meta"><span>${esc(timeAgo(msg.sent_at))}</span>${readTick}</span>
    </div>
  </div>`;
}

function daySeparator(prev, next) {
  const day = (v) => String(v || "").slice(0, 10);
  return !prev || day(prev.sent_at) !== day(next.sent_at);
}

function dayLabel(input) {
  const d = new Date(String(input || "").replace(" ", "T"));
  if (Number.isNaN(d.getTime())) return "";
  return d.toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" });
}

async function loadMessages(key) {
  const [type, id] = key.split("|");
  const data = await apiFetch(
    `/messages?related_type=${encodeURIComponent(type)}&related_id=${encodeURIComponent(id)}`
  );
  return (data && Array.isArray(data.messages)) ? data.messages : [];
}

function renderThread(messages, { forceScroll = true } = {}) {
  const scroll = document.getElementById("msg-scroll");
  if (!scroll) return;
  const stickToBottom =
    forceScroll || scroll.scrollHeight - scroll.scrollTop - scroll.clientHeight < 120;
  let html = "";
  let prev = null;
  for (const msg of messages) {
    if (daySeparator(prev, msg)) {
      html += `<span class="msg-day-sep">${esc(dayLabel(msg.sent_at))}</span>`;
    }
    html += bubbleRow(msg);
    prev = msg;
  }
  if (!messages.length) {
    html = `<div class="rempty"><i data-lucide="message-circle"></i><p class="rempty-text">No messages yet — say hello!</p></div>`;
  }
  scroll.innerHTML = html;
  createIcons({ icons });
  if (stickToBottom) scroll.scrollTop = scroll.scrollHeight;
}

async function markThreadRead(key, knownMessages = null) {
  const t = findThread(key);
  try {
    const messages = knownMessages || (await loadMessages(key));
    const unread = messages.filter(
      (m) => m.receiver_id === (state.me && state.me.id) && !m.read_at
    );
    if (unread.length) {
      await Promise.all(
        unread.map((m) =>
          apiFetch(`/messages/${encodeURIComponent(m.id)}/read`, { method: "PATCH" }).catch(() => {})
        )
      );
      if (t) {
        t.unread_count = 0;
        renderThreads();
        void refreshThreads({ silent: true });
      }
    }
  } catch {
    /* non-fatal */
  }
}

async function openThread(key) {
  state.currentKey = key;
  syncInboxLayout();

  const t = findThread(key);
  const nameEl = document.getElementById("msg-peer-name");
  const avatarEl = document.getElementById("msg-peer-avatar");
  const chipEl = document.getElementById("msg-context-chip");
  if (nameEl) nameEl.textContent = t?.other_user_name || "Conversation";
  if (avatarEl) avatarEl.textContent = initialOf(t?.other_user_name);
  if (chipEl) chipEl.textContent = contextLabel(t?.related_type);

  renderThreads();
  try {
    renderThread(await loadMessages(key));
  } catch (err) {
    toast(err.message || "Could not load this conversation.", { type: "error" });
    return;
  }
  void markThreadRead(key);
}

async function refreshThreads({ silent = false } = {}) {
  try {
    const data = await apiFetch("/messages/threads");
    state.threads = (data && Array.isArray(data.threads)) ? data.threads : [];
    state.loadError = "";
    renderThreads();
  } catch (err) {
    if (silent) return;
    state.loadError = err.message || "Could not load conversations.";
    toast(state.loadError, { type: "error" });
    renderThreads();
  }
}

async function sendMessage(text) {
  const t = findThread(state.currentKey || "");
  if (!t || state.sending) return;
  const input = document.getElementById("msg-input");
  const sendBtn = document.getElementById("msg-send");
  state.sending = true;
  if (sendBtn) sendBtn.disabled = true;
  try {
    await apiFetch("/messages", {
      method: "POST",
      body: {
        receiver_id: t.other_user_id,
        related_type: t.related_type,
        related_id: t.related_id,
        message_text: text,
      },
    });
    if (input) input.value = "";
    renderThread(await loadMessages(threadKey(t)));
    void refreshThreads({ silent: true });
  } catch (err) {
    toast(err.message || "Could not send your message.", { type: "error" });
  } finally {
    state.sending = false;
    if (sendBtn) sendBtn.disabled = false;
    input?.focus();
  }
}

function startPolling() {
  if (state.pollTimer) return;
  state.pollTimer = setInterval(async () => {
    await refreshThreads({ silent: true });
    if (state.currentKey) {
      try {
        const messages = await loadMessages(state.currentKey);
        renderThread(messages, { forceScroll: false });
        await markThreadRead(state.currentKey, messages);
      } catch {
        /* ignore polling errors */
      }
    }
  }, 15000);
}

function boot() {
  bootstrapPageAuth();
  const user = requireAuth(PORTAL_ROLES);
  if (!user) return;
  state.me = user;
  initResidentShell();

  const list = document.getElementById("msg-threads");
  list?.addEventListener("click", (e) => {
    const item = e.target.closest("[data-key]");
    if (item) void openThread(String(item.dataset.key));
  });

  document.getElementById("msg-back")?.addEventListener("click", () => {
    state.currentKey = null;
    renderThreads();
  });

  document.getElementById("msg-compose")?.addEventListener("click", () => {
    const params = new URLSearchParams(window.location.search);
    void openCompose({
      preselect: params.get("type") && params.get("id") ? { type: params.get("type"), id: params.get("id") } : null,
      onSent: async (started) => {
        await refreshThreads();
        if (started) void openThread(`${started.related_type}|${started.related_id}`);
      },
    });
  });

  document.getElementById("msg-form")?.addEventListener("submit", async (e) => {
    e.preventDefault();
    const input = document.getElementById("msg-input");
    const text = String(input && input.value ? input.value : "").trim();
    if (!text) return;
    const t = findThread(state.currentKey || "");
    const peer = (t && t.other_user_name) || "the team";
    const ok = await confirmDialog({
      title: "Send this message?",
      message: `This will send a message to ${peer}.`,
      confirmText: "Send message",
    });
    if (!ok) return;
    void sendMessage(text);
  });

  void refreshThreads().then(() => {
    const params = new URLSearchParams(window.location.search);
    if (params.get("compose") === "1") {
      document.getElementById("msg-compose")?.click();
    }
  });
  startPolling();
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", boot);
} else {
  boot();
}
