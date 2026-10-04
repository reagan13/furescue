import { apiFetchFull, apiUpload } from "/assets/js/lib/api.js";

export { subscribeToNotifications } from "/assets/js/lib/notification-stream.js";

async function list(path, perPage = 100) {
  const sep = path.includes("?") ? "&" : "?";
  const payload = await apiFetchFull(`${path}${sep}per_page=${perPage}`);
  const items = Array.isArray(payload.data) ? payload.data : [];
  return { items, total: (payload.meta && payload.meta.total) ?? items.length };
}

async function raw(path) {
  const payload = await apiFetchFull(path);
  return payload.data;
}

export const fetchOverview = () =>
  raw("/analytics/overview").then((d) => (d && d.stats) || null);

export const fetchReports = (status = "pending_verification") =>
  list(`/reports?status=${status}`);

export const fetchAllReports = () => list("/reports");

export const fetchAdoptions = (status = "pending") =>
  list(`/adoptions?status=${status}`);

export const fetchCases = () => list("/cases");

export const fetchAnimals = () => list("/animals");

export const fetchAllAnimals = (perPage = 1000) =>
  apiFetchFull(`/animals?per_page=${perPage}`).then((d) => (d && Array.isArray(d.data) ? d.data : []));

export const createAnimal = (body) =>
  post("/animals", body).then((p) => (p && p.data ? p.data.animal : null));

export const fetchRescuerCases = (rescuerId) =>
  list(`/cases?assigned_rescuer_id=${encodeURIComponent(rescuerId)}`);

export const fetchCase = (id) =>
  raw(`/cases/${id}`).then((d) => (d && d.case) || null);

export const fetchReport = (id) =>
  raw(`/reports/${id}`).then((d) => (d && d.report) || null);

export const fetchNotifications = () =>
  list("/notifications?is_read=false");

export const broadcastAnnouncement = (body) =>
  apiFetchFull("/admin/notifications", { method: "POST", body });

export const fetchRecentBroadcasts = () =>
  raw("/admin/notifications/recent").then((d) => (d && d.broadcasts) || []);

export const fetchUnreadCount = () =>
  raw("/notifications/unread-count").then((d) => (d && d.count) || 0);

export const markNotificationRead = (id) =>
  apiFetchFull(`/notifications/${encodeURIComponent(id)}/read`, { method: "PATCH" });

export const fetchElearning = async () => {
  const published = await list("/elearning/modules?published_status=published");
  const drafts = await list("/elearning/modules?published_status=draft");
  return { published: published.total, drafts: drafts.total, items: published.items };
};

export const fetchAdoptionTrends = () =>
  raw("/analytics/adoption-trends").then((d) => (d && d.trends) || []);

export const fetchHealthUpdates = () =>
  raw("/health/updates").then((d) => (d && d.updates) || []);

export const fetchHealthRecords = () =>
  raw("/health/records").then((d) => (d && d.records) || []);

export const fetchAnimalHealthRecord = (id) =>
  raw(`/animals/${encodeURIComponent(id)}/health-record`).then((d) => (d && d.record) || null);

export const fetchMedicalAnimalIds = () =>
  fetchHealthRecords()
    .then((records) => {
      const list = Array.isArray(records) ? records : [];
      return new Set(list.filter((r) => r.hasMedicalRecord).map((r) => r.animalId));
    })
    .catch(() => new Set());

export const fetchHealthActivity = () =>
  raw("/health/activity").then((d) => (d && d.daily) || []);

export const fetchHeatmap = (status = "all") =>
  raw(`/reports/map/heatmap?status=${encodeURIComponent(status)}`).then((d) => (d && d.points) || []);

export const reverseGeocode = (lat, lng) =>
  raw(`/geo/reverse?lat=${encodeURIComponent(lat)}&lng=${encodeURIComponent(lng)}`);

async function post(path, body = {}) {
  return apiFetchFull(path, { method: "POST", body });
}

async function patch(path, body = {}) {
  return apiFetchFull(path, { method: "PATCH", body });
}

async function del(path) {
  return apiFetchFull(path, { method: "DELETE" });
}

export const updateAnimal = (id, body) =>
  patch(`/animals/${id}`, body).then((p) => (p && p.data ? p.data.animal : null));

export const deleteAnimal = (id) => del(`/animals/${id}`);

async function put(path, body = {}) {
  return apiFetchFull(path, { method: "PUT", body });
}

export const upsertAnimalMedical = (id, body) =>
  put(`/animals/${id}/medical`, body).then((p) => (p && p.data ? p.data.medical : null));

export const upsertAnimalVaccinations = (id, vaccinationRecords, vaccinationDetails) =>
  put(`/animals/${id}/medical`, {
    vaccination_records: vaccinationRecords,
    ...(vaccinationDetails !== undefined ? { vaccination_details: vaccinationDetails } : {}),
  }).then((p) => (p && p.data ? p.data.medical : null));

export const addAnimalVital = (id, body) =>
  post(`/animals/${id}/vitals`, body).then((p) => (p && p.data ? p.data.vital : null));

export const uploadAnimalDocument = (id, formData) =>
  apiUpload(`/animals/${encodeURIComponent(id)}/documents`, formData).then((p) => (p && p.data ? p.data.document : null));

export const updateAnimalDocument = (id, body) =>
  patch(`/documents/${id}`, body).then((p) => (p && p.data ? p.data.document : null));

export const deleteAnimalDocument = (id) => del(`/documents/${id}`);

export const fetchRescuers = () =>
  list("/users?role=rescuer&account_status=active");

export const fetchRescuerApplicants = () =>
  list("/users?role=rescuer&account_status=pending");

export const fetchPendingRescuers = () =>
  list("/users?role=rescuer&account_status=pending");

export const fetchSuspendedRescuers = () =>
  list("/users?role=rescuer&account_status=suspended");

export const fetchUser = (id) => raw(`/users/${id}`);

export async function fetchUsers() {
  const items = [];
  let page = 1;
  let total = Infinity;
  while (items.length < total) {
    const payload = await apiFetchFull(`/users?per_page=100&page=${page}`);
    const batch = Array.isArray(payload.data) ? payload.data : [];
    total = (payload.meta && payload.meta.total) ?? items.length + batch.length;
    items.push(...batch);
    if (batch.length === 0) break;
    page += 1;
    if (page > 50) break;
  }
  return { items, total: items.length };
}

export const createUser = (body) =>
  post("/users", body).then((p) => (p && p.data ? p.data.user : null));

export const updateUser = (id, body) =>
  patch(`/users/${id}`, body).then((p) => (p && p.data ? p.data.user : null));

export const deleteUser = (id) => del(`/users/${id}`);

export const setUserStatus = (id, status) =>
  patch(`/users/${id}`, { account_status: status });

export const toggleRescuerDuty = (id, status) =>
  patch(`/rescuers/${encodeURIComponent(id)}/duty`, { status });

export const assignRescuer = (caseId, rescuerId) =>
  post(`/cases/${caseId}/assign`, { rescuer_id: rescuerId });

export const updateCaseStatus = (caseId, status) =>
  patch(`/cases/${caseId}/status`, { status });

export const resolveCase = (caseId) =>
  post(`/cases/${caseId}/resolve`);

export const uploadReportMedia = (id, formData) =>
  apiUpload(`/reports/${encodeURIComponent(id)}/media`, formData);

export const uploadCaseProof = (id, formData) =>
  apiUpload(`/cases/${encodeURIComponent(id)}/proof`, formData);

export const fetchCaseActivity = (caseId) =>
  raw(`/cases/${caseId}/activity`).then((d) => (d && d.activity) || []);

export const verifyReport = (id) => post(`/reports/${id}/verify`);
export const dismissReport = (id, reason) =>
  post(`/reports/${id}/dismiss`, { dismiss_reason: reason });
export const approveRescuer = (id, remarks = null) =>
  post(`/admin/rescuers/${id}/approve`, remarks ? { remarks } : {});
export const rejectRescuer = (id, remarks = null) =>
  post(`/admin/rescuers/${id}/reject`, remarks ? { remarks } : {});
export const approveAdoption = (id) => post(`/adoptions/${id}/approve`);
export const rejectAdoption = (id, reason) =>
  post(`/adoptions/${id}/reject`, { rejection_reason: reason });

export const createAdoptionListing = (animalId) =>
  post(`/adoption-listings`, { animal_id: animalId });
