import { PaginationBar } from "/shared/components/pagination/pagination.js";
import { Button } from "/shared/components/button/button.js";
import { shortId, timeAgo } from "/admin/js/helpers.js";
import { state } from "../state.js";
import { esc, statusLabel, stampCls, emptyMessage, speciesLabel } from "./util.js";

export function filteredListings() {
  const q = state.query.trim().toLowerCase();
  let list = state.listings;
  if (state.filter !== "all") list = list.filter((row) => row.status === state.filter);
  if (q) {
    list = list.filter((row) =>
      [row.animal_name, row.species, speciesLabel(row.species), row.poster_name, row.status, shortId(row.id), row.review_notes]
        .join(" ")
        .toLowerCase()
        .includes(q)
    );
  }
  return [...list].sort((a, b) => {
    const rankA = a.status === "pending_review" ? 0 : 1;
    const rankB = b.status === "pending_review" ? 0 : 1;
    if (rankA !== rankB) return rankA - rankB;
    return new Date(b.created_at || 0) - new Date(a.created_at || 0);
  });
}

function actionLinks(row) {
  if (row.status !== "pending_review") return "";
  return [
    Button({
      text: "Approve",
      variant: "default",
      size: "sm",
      icon: "badge-check",
      attrs: `data-action="approve" data-id="${esc(row.id)}"`,
    }),
    Button({
      text: "Reject",
      variant: "destructive",
      size: "sm",
      icon: "file-x",
      attrs: `data-action="reject" data-id="${esc(row.id)}"`,
    }),
  ].join("");
}

function listingRow(row) {
  const name = (row.animal_name && String(row.animal_name).trim()) || "Unnamed";
  const poster = (row.poster_name && String(row.poster_name).trim()) || "Unknown poster";
  return `
    <tr data-id="${esc(row.id)}">
      <td class="table-cell table-cell--strong">${esc(name)}</td>
      <td class="table-cell">${esc(speciesLabel(row.species))}</td>
      <td class="table-cell">${esc(poster)}</td>
      <td class="table-cell"><span class="stamp stamp--sm ${stampCls(row.status)}">${esc(statusLabel(row.status))}</span></td>
      <td class="table-cell table-cell--mono table-cell--muted">${esc(timeAgo(row.created_at))}</td>
      <td class="table-cell table-cell--right table-cell--nowrap">
        <span class="table-actions">${actionLinks(row)}</span>
      </td>
    </tr>`;
}

export function ListingTable() {
  if (state.error && !state.listings.length) {
    return `<div class="queue-empty"><div class="empty-state"><i data-lucide="triangle-alert"></i><span>${esc(state.error)}</span></div></div>`;
  }
  const list = filteredListings();
  if (list.length === 0) {
    return `<div class="queue-empty"><div class="empty-state"><i data-lucide="home"></i><span>${esc(emptyMessage(state.filter, state.query))}</span></div></div>`;
  }
  const start = (state.page - 1) * state.pageSize;
  const rows = list.slice(start, start + state.pageSize).map(listingRow).join("");
  const pagination = `<div class="queue-pagination">${PaginationBar({ total: list.length, perPage: state.pageSize, page: state.page })}</div>`;
  const banner = state.error
    ? `<div class="queue-empty"><div class="empty-state"><i data-lucide="triangle-alert"></i><span>${esc(state.error)}</span></div></div>`
    : "";
  return `
    ${banner}
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr class="table-head">
            <th>Animal</th><th>Species</th><th>Poster</th><th>Status</th><th>Posted</th><th class="table-cell--right">Action</th>
          </tr>
        </thead>
        <tbody>${rows}</tbody>
      </table>
    </div>
    ${pagination}`;
}
