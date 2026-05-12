import { apiRequest } from "./client";
import type {
  RtmfActor,
  RtmfAttachment,
  RtmfDashboardSummary,
  RtmfFrontend,
  RtmfFrontendInput,
  RtmfFrontendItem,
  RtmfFrontendItemInput,
  RtmfFrontendScenarioGroup,
  RtmfFrontendScenarioGroupInput,
  RtmfFrontendScenarioRow,
  RtmfFrontendScenarioRowInput,
  RtmfModule,
  RtmfModulePhoto,
  RtmfSnapshotStatus,
  RtmfSubModule,
  RtmfSubModulePhoto,
} from "@/types";

// ── Dashboard ──
export async function fetchRtmfDashboard() {
  return apiRequest<{ data: RtmfDashboardSummary }>("/api/rtmf/dashboard");
}

// ── Frontends ──
export async function listRtmfFrontends(params = "") {
  return apiRequest<{ data: RtmfFrontend[]; meta: Record<string, unknown> }>(
    `/api/rtmf-frontends${params}`,
  );
}

export async function getRtmfFrontend(id: number) {
  return apiRequest<{ data: RtmfFrontend }>(`/api/rtmf-frontends/${id}`);
}

export async function createRtmfFrontend(input: RtmfFrontendInput) {
  return apiRequest<{ data: RtmfFrontend }>("/api/rtmf-frontends", {
    method: "POST",
    body: JSON.stringify(input),
  });
}

export async function updateRtmfFrontend(id: number, input: Partial<RtmfFrontendInput>) {
  return apiRequest<{ data: RtmfFrontend }>(`/api/rtmf-frontends/${id}`, {
    method: "PUT",
    body: JSON.stringify(input),
  });
}

export async function deleteRtmfFrontend(id: number) {
  return apiRequest<{ data: { success: boolean } }>(`/api/rtmf-frontends/${id}`, {
    method: "DELETE",
  });
}

// ── Snapshot (delegated from Frontend id to its url_path) ──
export type RtmfSnapshot = {
  html: string | null;
  status: RtmfSnapshotStatus;
  capturedAt: string | null;
  vuePath: string | null;
  liveUrl: string | null;
};

export async function getRtmfFrontendSnapshot(id: number) {
  return apiRequest<{ data: RtmfSnapshot }>(`/api/rtmf-frontends/${id}/snapshot`);
}

export async function captureRtmfFrontendSnapshot(id: number) {
  return apiRequest<{ data: RtmfSnapshot }>(`/api/rtmf-frontends/${id}/snapshot`, {
    method: "POST",
  });
}

export type RtmfFrontendSource = {
  exists: boolean;
  path: string | null;
  content: string | null;
  lineCount: number;
  sizeBytes: number;
};

export async function getRtmfFrontendSource(id: number) {
  return apiRequest<{ data: RtmfFrontendSource }>(`/api/rtmf-frontends/${id}/source`);
}

// ── Modules ──
export async function listRtmfModules(params = "") {
  return apiRequest<{ data: RtmfModule[]; meta: Record<string, unknown> }>(
    `/api/rtmf-modules${params}`,
  );
}
export async function getRtmfModule(id: number) {
  return apiRequest<{ data: RtmfModule }>(`/api/rtmf-modules/${id}`);
}
export async function createRtmfModule(input: Partial<RtmfModule>) {
  return apiRequest<{ data: RtmfModule }>("/api/rtmf-modules", { method: "POST", body: JSON.stringify(input) });
}
export async function updateRtmfModule(id: number, input: Partial<RtmfModule>) {
  return apiRequest<{ data: RtmfModule }>(`/api/rtmf-modules/${id}`, { method: "PUT", body: JSON.stringify(input) });
}
export async function deleteRtmfModule(id: number) {
  return apiRequest<{ data: { success: boolean } }>(`/api/rtmf-modules/${id}`, { method: "DELETE" });
}

// ── Sub-modules (nested under a module) ──
export async function listRtmfSubModules(moduleId: number) {
  return apiRequest<{ data: RtmfSubModule[] }>(`/api/rtmf-modules/${moduleId}/sub-modules`);
}
export async function createRtmfSubModule(moduleId: number, input: Partial<RtmfSubModule>) {
  return apiRequest<{ data: RtmfSubModule }>(`/api/rtmf-modules/${moduleId}/sub-modules`, {
    method: "POST",
    body: JSON.stringify(input),
  });
}
export async function updateRtmfSubModule(moduleId: number, id: number, input: Partial<RtmfSubModule>) {
  return apiRequest<{ data: RtmfSubModule }>(`/api/rtmf-modules/${moduleId}/sub-modules/${id}`, {
    method: "PUT",
    body: JSON.stringify(input),
  });
}
export async function deleteRtmfSubModule(moduleId: number, id: number) {
  return apiRequest<{ data: { success: boolean } }>(`/api/rtmf-modules/${moduleId}/sub-modules/${id}`, {
    method: "DELETE",
  });
}
export async function reorderSubModules(moduleId: number, ids: number[]) {
  return apiRequest<{ data: { success: boolean } }>(`/api/rtmf-modules/${moduleId}/sub-modules/reorder`, {
    method: "POST",
    body: JSON.stringify({ ids }),
  });
}

// ── Module Photos ──
export async function listModulePhotos(moduleId: number) {
  return apiRequest<{ data: RtmfModulePhoto[] }>(`/api/rtmf-modules/${moduleId}/photos`);
}
export async function uploadModulePhoto(moduleId: number, file: File) {
  const form = new FormData();
  form.append("file", file);
  return apiRequest<{ data: RtmfModulePhoto }>(`/api/rtmf-modules/${moduleId}/photos`, { method: "POST", body: form });
}
export async function deleteModulePhoto(moduleId: number, photoId: number) {
  return apiRequest<{ data: { success: boolean } }>(`/api/rtmf-modules/${moduleId}/photos/${photoId}`, { method: "DELETE" });
}

// ── Sub-module Photos ──
export async function listSubModulePhotos(moduleId: number, subModuleId: number) {
  return apiRequest<{ data: RtmfSubModulePhoto[] }>(`/api/rtmf-modules/${moduleId}/sub-modules/${subModuleId}/photos`);
}
export async function uploadSubModulePhoto(moduleId: number, subModuleId: number, file: File) {
  const form = new FormData();
  form.append("file", file);
  return apiRequest<{ data: RtmfSubModulePhoto }>(`/api/rtmf-modules/${moduleId}/sub-modules/${subModuleId}/photos`, { method: "POST", body: form });
}
export async function deleteSubModulePhoto(moduleId: number, subModuleId: number, photoId: number) {
  return apiRequest<{ data: { success: boolean } }>(`/api/rtmf-modules/${moduleId}/sub-modules/${subModuleId}/photos/${photoId}`, { method: "DELETE" });
}

// ── Actors ──
export async function listRtmfActors(params = "") {
  return apiRequest<{ data: RtmfActor[]; meta: Record<string, unknown> }>(
    `/api/rtmf-actors${params}`,
  );
}
export async function getRtmfActor(id: number) {
  return apiRequest<{ data: RtmfActor }>(`/api/rtmf-actors/${id}`);
}
export async function createRtmfActor(input: Partial<RtmfActor>) {
  return apiRequest<{ data: RtmfActor }>("/api/rtmf-actors", { method: "POST", body: JSON.stringify(input) });
}
export async function updateRtmfActor(id: number, input: Partial<RtmfActor>) {
  return apiRequest<{ data: RtmfActor }>(`/api/rtmf-actors/${id}`, { method: "PUT", body: JSON.stringify(input) });
}
export async function deleteRtmfActor(id: number) {
  return apiRequest<{ data: { success: boolean } }>(`/api/rtmf-actors/${id}`, { method: "DELETE" });
}



// ── Frontend Items ──
export async function listRtmfFrontendItems(frontendId: number) {
  return apiRequest<{ data: RtmfFrontendItem[] }>(`/api/rtmf-frontends/${frontendId}/items`);
}
export async function createRtmfFrontendItem(frontendId: number, input: RtmfFrontendItemInput) {
  return apiRequest<{ data: RtmfFrontendItem }>(`/api/rtmf-frontends/${frontendId}/items`, {
    method: "POST",
    body: JSON.stringify(input),
  });
}
export async function updateRtmfFrontendItem(frontendId: number, itemId: number, input: RtmfFrontendItemInput) {
  return apiRequest<{ data: RtmfFrontendItem }>(`/api/rtmf-frontends/${frontendId}/items/${itemId}`, {
    method: "PATCH",
    body: JSON.stringify(input),
  });
}
export async function deleteRtmfFrontendItem(frontendId: number, itemId: number) {
  return apiRequest<{ data: { success: boolean } }>(`/api/rtmf-frontends/${frontendId}/items/${itemId}`, {
    method: "DELETE",
  });
}

// ── Frontend Attachments ──
export async function listRtmfAttachments(frontendId: number) {
  return apiRequest<{ data: RtmfAttachment[] }>(`/api/rtmf-frontends/${frontendId}/attachments`);
}
export async function uploadRtmfAttachment(frontendId: number, file: File, label: string) {
  const form = new FormData();
  form.append("file", file);
  if (label) form.append("label", label);
  return apiRequest<{ data: RtmfAttachment }>(`/api/rtmf-frontends/${frontendId}/attachments`, { method: "POST", body: form });
}
export async function updateRtmfAttachmentLabel(frontendId: number, attachmentId: number, label: string) {
  return apiRequest<{ data: RtmfAttachment }>(`/api/rtmf-frontends/${frontendId}/attachments/${attachmentId}`, { method: "PATCH", body: JSON.stringify({ label }) });
}
export async function deleteRtmfAttachment(frontendId: number, attachmentId: number) {
  return apiRequest<{ data: { success: boolean } }>(`/api/rtmf-frontends/${frontendId}/attachments/${attachmentId}`, { method: "DELETE" });
}

// ── Scenario Groups ──
export async function listRtmfScenarioGroups(frontendId: number) {
  return apiRequest<{ data: RtmfFrontendScenarioGroup[] }>(`/api/rtmf-frontends/${frontendId}/scenario-groups`);
}
export async function createRtmfScenarioGroup(frontendId: number, input: RtmfFrontendScenarioGroupInput) {
  return apiRequest<{ data: RtmfFrontendScenarioGroup }>(`/api/rtmf-frontends/${frontendId}/scenario-groups`, { method: "POST", body: JSON.stringify(input) });
}
export async function updateRtmfScenarioGroup(frontendId: number, groupId: number, input: RtmfFrontendScenarioGroupInput) {
  return apiRequest<{ data: RtmfFrontendScenarioGroup }>(`/api/rtmf-frontends/${frontendId}/scenario-groups/${groupId}`, { method: "PATCH", body: JSON.stringify(input) });
}
export async function deleteRtmfScenarioGroup(frontendId: number, groupId: number) {
  return apiRequest<{ data: { success: boolean } }>(`/api/rtmf-frontends/${frontendId}/scenario-groups/${groupId}`, { method: "DELETE" });
}

// ── Scenario Rows ──
export async function createRtmfScenarioRow(frontendId: number, groupId: number, input: RtmfFrontendScenarioRowInput) {
  return apiRequest<{ data: RtmfFrontendScenarioRow }>(`/api/rtmf-frontends/${frontendId}/scenario-groups/${groupId}/rows`, { method: "POST", body: JSON.stringify(input) });
}
export async function updateRtmfScenarioRow(frontendId: number, groupId: number, rowId: number, input: RtmfFrontendScenarioRowInput) {
  return apiRequest<{ data: RtmfFrontendScenarioRow }>(`/api/rtmf-frontends/${frontendId}/scenario-groups/${groupId}/rows/${rowId}`, { method: "PATCH", body: JSON.stringify(input) });
}
export async function deleteRtmfScenarioRow(frontendId: number, groupId: number, rowId: number) {
  return apiRequest<{ data: { success: boolean } }>(`/api/rtmf-frontends/${frontendId}/scenario-groups/${groupId}/rows/${rowId}`, { method: "DELETE" });
}
