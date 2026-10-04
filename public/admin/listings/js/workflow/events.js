import { createIcons, icons } from "lucide";
import { readPageClick, readPageSizeChange } from "/shared/components/pagination/pagination.js";
import { state } from "../state.js";
import { ListingTable, rerenderAll } from "../components.js";
import { filteredListings } from "../components/table.js";
import { runApprove, runReject } from "./actions.js";
import { toast } from "/shared/components/toast/toast.js";
import { datedCsvName, downloadCsv } from "/assets/js/lib/csv.js";

export function initListingsEvents() {
  const main = document.getElementById("app");
  if (!main || main.dataset.listingEvents) return;
  main.dataset.listingEvents = "1";

  main.addEventListener("click", async (e) => {
    const exportBtn = e.target.closest("[data-export]");
    if (exportBtn) {
      const list = filteredListings();
      if (!list.length) {
        toast("No listings match the current filters.", { type: "error" });
        return;
      }
      downloadCsv(
        datedCsvName("listings"),
        ["id", "animal", "species", "poster", "status", "posted"],
        list.map((row) => [row.id, row.animal_name || "", row.species || "", row.poster_name || "", row.status, row.created_at || ""])
      );
      toast("CSV downloaded.", { type: "success" });
      return;
    }

    const tab = e.target.closest("button[data-filter]");
    if (tab) {
      state.filter = tab.dataset.filter;
      state.page = 1;
      rerenderAll();
      return;
    }

    const page = readPageClick(e.target, state.page);
    if (page) {
      state.page = page;
      const table = document.getElementById("listing-table");
      if (table) {
        table.innerHTML = ListingTable();
        createIcons({ icons });
      }
      return;
    }

    const actionEl = e.target.closest("[data-action]");
    if (actionEl) {
      e.preventDefault();
      const action = actionEl.dataset.action;
      const id = actionEl.dataset.id;
      if (action === "approve") return runApprove(id);
      if (action === "reject") return runReject(id);
    }
  });

  main.addEventListener("change", (e) => {
    const next = readPageSizeChange(e.target, state.pageSize);
    if (!next) return;
    state.pageSize = next;
    state.page = 1;
    const table = document.getElementById("listing-table");
    if (table) {
      table.innerHTML = ListingTable();
      createIcons({ icons });
    }
  });

  main.addEventListener("input", (e) => {
    const search = e.target.closest("#listing-search");
    if (!search) return;
    state.query = search.value;
    state.page = 1;
    const table = document.getElementById("listing-table");
    if (table) {
      table.innerHTML = ListingTable();
      createIcons({ icons });
    }
  });
}
