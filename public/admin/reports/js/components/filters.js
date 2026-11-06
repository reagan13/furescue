import { Select } from "/shared/components/select/select.js";
import { Search } from "/shared/components/search/search.js";
import { Toolbar } from "/shared/components/toolbar/toolbar.js";
import { FilterTabs as TabStrip, FilterTab } from "/shared/components/filter-tabs/filter-tabs.js";
import { state } from "../state.js";
import { reportCounts } from "./kpis.js";

export const FILTERS = [
  { key: "all", label: "All" },
  { key: "pending_verification", label: "Pending verification" },
  { key: "verified", label: "Verified" },
  { key: "dismissed", label: "Dismissed" },
];

export function FilterTabs() {
  const c = reportCounts();
  const count = { all: c.all, pending_verification: c.pending, verified: c.verified, dismissed: c.dismissed };
  return TabStrip({
    id: "report-tabs",
    children: FILTERS.map((f) =>
      FilterTab({ key: f.key, label: `${f.label} &middot; ${count[f.key]}`, active: state.filter === f.key })
    ).join(""),
  });
}

export function FilterToolbar() {
  return Toolbar({
    children: `
    ${Search({ id: "report-search", placeholder: "Search case #, barangay, description…", value: state.query })}
    <div class="report-sort">
      <label for="report-sort" class="report-sort-label">Sort</label>
      ${Select({
        id: "report-sort",
        options: [
          { value: "newest", label: "Newest" },
          { value: "assigned", label: "Assigned" },
          { value: "verified", label: "Verified" },
        ],
        value: state.sort,
        placeholder: "Sort",
        className: "report-sort-control",
      })}
    </div>`,
  });
}
