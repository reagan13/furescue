import { createIcons, icons } from "lucide";
import { state, getAnimal, setSelectedId, visibleAnimals, normalize } from "./state.js";
import { renderAnimalGrid, renderSelection } from "./components/grid.js";
import { renderDetail, renderSideStats } from "./components/side.js";
import { renderAnimalKpis } from "./components/kpis.js";
import { openAddAnimalDialog } from "./components/modal.js";
import { openHealthRecordDialog } from "./components/health.js";
import { openEditAnimalDialog } from "./components/edit.js";
import { confirmDialog } from "/shared/components/dialog/dialog.js";
import { deleteAnimal } from "/assets/js/admin/admin-data.js";
import { toast } from "/shared/components/toast/toast.js";
import { datedCsvName, downloadCsv } from "/assets/js/lib/csv.js";

export function initAnimalsEvents() {
  const app = document.getElementById("app");
  if (!app) return;
  if (app.dataset.animalsEventsBound) return;
  app.dataset.animalsEventsBound = "1";

  app.addEventListener("click", async (e) => {
    const openBtn = e.target.closest('[data-act="open-add"]');
    if (openBtn) {
      await openAddAnimalDialog();
      return;
    }

    const healthBtn = e.target.closest('[data-act="add-health"]');
    if (healthBtn) {
      const animal = getAnimal(state.selectedId);
      if (animal) {
        const saved = await openHealthRecordDialog(animal);
        if (saved) {
          animal.hasMedical = true;
          renderAnimalGrid();
          renderAnimalKpis();
          renderDetail();
        }
      }
      return;
    }

    const editBtn = e.target.closest('[data-act="edit-animal"]');
    if (editBtn) {
      const animal = getAnimal(state.selectedId);
      if (animal) {
        const updated = await openEditAnimalDialog(animal);
        if (updated) {
          const idx = state.animals.findIndex((a) => a.id === animal.id);
          if (idx !== -1) state.animals[idx] = normalize(updated);
          renderAnimalGrid();
          renderAnimalKpis();
          renderSideStats();
          renderSelection();
        }
      }
      return;
    }

    const delBtn = e.target.closest('[data-act="delete-animal"]');
    if (delBtn) {
      const animal = getAnimal(state.selectedId);
      if (animal) {
        const ok = await confirmDialog({
          title: "Delete this animal?",
          message: `Hide "${animal.name}"? The record stays in the database and no longer appears in the shelter list.`,
          confirmText: "Delete",
          cancelText: "Cancel",
          danger: true,
          run: () => deleteAnimal(animal.id),
        });
        if (!ok) return;
        state.animals = state.animals.filter((a) => a.id !== animal.id);
        setSelectedId(null);
        renderAnimalGrid();
        renderAnimalKpis();
        renderSelection();
        renderSideStats();
      }
      return;
    }

    const card = e.target.closest(".animal-card");
    if (card) {
      const id = card.dataset.animal;
      setSelectedId(state.selectedId === id ? null : id);
      renderSelection();
      renderDetail();
      return;
    }

    const tab = e.target.closest("[data-filter]");
    if (tab) {
      state.filter = tab.dataset.filter;
      renderAnimalGrid();
      const tabs = document.getElementById("animal-filter-tabs");
      if (tabs) {
        tabs.querySelectorAll("[data-filter]").forEach((b) =>
          b.classList.toggle("is-active", b.dataset.filter === state.filter)
        );
      }
      return;
    }

    const exportBtn = e.target.closest("[data-export]");
    if (exportBtn) {
      const rows = visibleAnimals();
      if (!rows.length) {
        toast("No animals match the current filters.", { type: "error" });
        return;
      }
      downloadCsv(
        datedCsvName("animals"),
        ["id", "name", "species", "breed", "age", "sex", "status", "barangay", "intake"],
        rows.map((a) => [a.id, a.name, a.species, a.breed, a.age, a.sex, a.status, a.barangay, a.intake])
      );
      toast("CSV downloaded.", { type: "success" });
    }
  });

  app.addEventListener("input", (e) => {
    if (e.target && e.target.id === "animal-search") {
      state.query = e.target.value;
      renderAnimalGrid();
    }
  });

  createIcons({ icons });
}
