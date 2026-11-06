import { toast } from "/shared/components/toast/toast.js";
import { fetchThreads, fetchThread, postMessage, markMessageRead } from "../api.js";
import { state, threadKey, findThread, upsertThread } from "../state.js";
import { renderList } from "../components/list.js";
import { renderPane, renderPaneNotice, showThreadChrome, syncInboxLayout } from "../components/pane.js";

async function loadMessages(key) {
  const [type, id] = String(key || "").split("|");
  if (!type || !id) return [];
  return fetchThread(type, id);
}

export async function refreshThreads({ silent = false } = {}) {
  try {
    state.threads = await fetchThreads();
    state.loadError = "";
    renderList();
    syncInboxLayout();
  } catch (err) {
    if (silent) return;
    state.loadError = err.message || "Could not load conversations.";
    toast(state.loadError, { type: "error" });
    renderList();
    syncInboxLayout();
  }
}

export async function markThreadRead(key, knownMessages = null) {
  const thread = findThread(key);
  try {
    const messages = knownMessages || (await loadMessages(key));
    const myId = state.me && state.me.id;
    const unread = messages.filter((m) => m.receiver_id === myId && !m.read_at);
    if (!unread.length) return;
    await Promise.all(unread.map((m) => markMessageRead(m.id).catch(() => {})));
    if (thread) {
      thread.unread_count = 0;
      renderList();
      void refreshThreads({ silent: true });
    }
  } catch {
    /* non-fatal */
  }
}

export async function openThread(key) {
  if (!key) return;
  state.currentKey = key;
  renderList();
  syncInboxLayout();
  showThreadChrome(findThread(key) || { related_type: String(key).split("|")[0] });
  try {
    const messages = await loadMessages(key);
    state.messages = messages;
    renderPane(messages);
  } catch (err) {
    const message = err.message || "Only the resident who filed this report, the assigned rescuer, or an admin can open this thread.";
    renderPaneNotice(message);
    toast(message, { type: "error" });
    return;
  }
  void markThreadRead(key);
}

export function closeThread() {
  state.currentKey = null;
  state.messages = [];
  renderList();
  syncInboxLayout();
}

export async function sendCurrent() {
  const thread = findThread(state.currentKey || "");
  if (!thread || state.sending) return;
  const input = document.getElementById("amsg-input");
  const sendBtn = document.getElementById("amsg-send");
  const text = String(input && input.value ? input.value : "").trim();
  if (!text) return;

  state.sending = true;
  if (sendBtn) sendBtn.disabled = true;
  try {
    await postMessage({
      receiver_id: thread.other_user_id,
      related_type: thread.related_type,
      related_id: thread.related_id,
      message_text: text,
    });
    if (input) input.value = "";
    const messages = await loadMessages(threadKey(thread));
    state.messages = messages;
    renderPane(messages);
    void refreshThreads({ silent: true });
  } catch (err) {
    toast(err.message || "Could not send your message.", { type: "error" });
  } finally {
    state.sending = false;
    if (sendBtn) sendBtn.disabled = false;
    input?.focus();
  }
}

export async function startConversation({
  related_type,
  related_id,
  receiver_id,
  other_user_name,
  message_text,
}) {
  await postMessage({
    receiver_id,
    related_type,
    related_id,
    message_text,
  });
  upsertThread({
    related_type,
    related_id,
    other_user_id: receiver_id,
    other_user_name: other_user_name || "Conversation",
    last_message: message_text,
    last_sent_at: new Date().toISOString(),
    unread_count: 0,
  });
  await openThread(threadKey({ related_type, related_id }));
  void refreshThreads({ silent: true });
}

export function startPolling() {
  if (state.pollTimer) return;
  state.pollTimer = setInterval(async () => {
    await refreshThreads({ silent: true });
    if (!state.currentKey) return;
    try {
      const messages = await loadMessages(state.currentKey);
      state.messages = messages;
      renderPane(messages, { forceScroll: false });
      await markThreadRead(state.currentKey, messages);
    } catch {
      /* ignore polling errors */
    }
  }, 15000);
}
