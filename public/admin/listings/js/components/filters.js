import { Search } from "/shared/components/search/search.js";
import { Toolbar } from "/shared/components/toolbar/toolbar.js";
import { FilterTabs as TabStrip, FilterTab } from "/shared/components/filter-tabs/filter-tabs.js";
import { state } from "../state.js";
import { listingCounts } from "./kpis.js";

export const FILTERS = [
  { key: "all", label: "All" },
  { key: "pending_review", label: "In review" },
  { key: "approved", label: "Live" },
  { key: "rejected", label: "Rejected" },
];

export function FilterTabs() {
  const c = listingCounts();
  const count = {
    all: c.all,
    pending_review: c.pending,
    approved: c.live,
    rejected: c.rejected,
  };
  return TabStrip({
    id: "listing-tabs",
    children: FILTERS.map((f) =>
      FilterTab({ key: f.key, label: `${f.label} &middot; ${count[f.key]}`, active: state.filter === f.key })
    ).join(""),
  });
}

export function FilterToolbar() {
  return Toolbar({
    children: Search({ id: "listing-search", placeholder: "Search animal, species, poster…", value: state.query }),
  });
}
