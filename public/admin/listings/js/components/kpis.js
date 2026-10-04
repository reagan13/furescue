import { KpiCard } from "/shared/components/kpi-card/kpi-card.js";
import { state } from "../state.js";

export function listingCounts() {
  const count = (status) => state.listings.filter((row) => row.status === status).length;
  return {
    all: state.listings.length,
    pending: count("pending_review"),
    live: count("approved"),
    rejected: count("rejected"),
  };
}

export function buildKpis() {
  const c = listingCounts();
  return [
    { icon: "home", value: c.all, label: "Total listings", tone: "jungle" },
    {
      icon: "clock",
      value: c.pending,
      label: "In review",
      tone: "coral",
      trend: c.pending ? "Needs You" : "",
      trendTone: "down",
    },
    { icon: "badge-check", value: c.live, label: "Live", tone: "sky" },
    { icon: "file-x", value: c.rejected, label: "Rejected", tone: "ink" },
  ];
}

export function KpiTile(k) {
  return KpiCard({ ...k, compact: true });
}
