import { fetchListings } from "./api.js";
import { uniqueListingsByAnimal } from "./unique.js";

export const state = {
  listings: [],
  filter: "all",
  query: "",
  page: 1,
  pageSize: 20,
  error: null,
};

function mergeNames(items) {
  const prev = Object.fromEntries((state.listings || []).map((row) => [row.id, row]));
  return (items || []).map((row) => {
    const prior = prev[row.id] || {};
    return {
      ...row,
      animal_name: row.animal_name || prior.animal_name || "",
      species: row.species || prior.species || "",
      poster_name: row.poster_name || prior.poster_name || "",
    };
  });
}

export async function loadListings() {
  try {
    const result = await fetchListings();
    state.listings = uniqueListingsByAnimal(mergeNames(result.items));
    state.error = null;
  } catch (err) {
    state.error = (err && err.message) || "Could not load listings.";
  }
}
