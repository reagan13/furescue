export const state = {
  accessToken: "",
  user: null,
  me: null,
  threads: [],
  messages: [],
  currentKey: null,
  pollTimer: null,
  sending: false,
  loadError: "",
  query: "",
};

export function threadKey(t) {
  return `${t.related_type}|${t.related_id}`;
}

export function upsertThread(row) {
  const key = threadKey(row);
  const index = state.threads.findIndex((t) => threadKey(t) === key);
  if (index >= 0) {
    state.threads[index] = { ...state.threads[index], ...row };
    return;
  }
  state.threads.unshift(row);
}

export function findThread(key) {
  return state.threads.find((t) => threadKey(t) === key) || null;
}
