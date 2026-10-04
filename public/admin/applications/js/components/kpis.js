import { KpiCard } from "/shared/components/kpi-card/kpi-card.js";
import { state } from "../state.js";

export function applicationCounts() {
  const count = (s) => state.items.filter((a) => a.status === s).length;
  return {
    all: state.items.length,
    pending: count("pending"),
    approved: count("approved"),
    rejected: count("rejected"),
    completed: count("completed"),
    cancelled: count("cancelled"),
  };
}

export function buildKpis() {
  const c = applicationCounts();
  return [
    { icon: "file-check", value: c.all, label: "Total applications", tone: "jungle" },
    {
      icon: "clock",
      value: c.pending,
      label: "Pending",
      tone: "coral",
      trend: c.pending ? "Needs You" : "",
      trendTone: "down",
    },
    { icon: "badge-check", value: c.approved, label: "Approved", tone: "sky" },
    { icon: "file-x", value: c.rejected, label: "Rejected", tone: "ink" },
    { icon: "check-circle-2", value: c.completed, label: "Completed", tone: "ink" },
    { icon: "ban", value: c.cancelled, label: "Cancelled", tone: "ink" },
  ];
}

export function KpiTile(k) {
  return KpiCard({ ...k, compact: true });
}
