import { apiRequest } from "./client";
import type {
  RtmfImportResult,
  RtmfActor,
  RtmfAttachment,
  RtmfScenarioAttachment,
  RtmfDashboardSummary,
  RtmfByAssigneeSummary,
  RtmfFrontend,
  RtmfFrontendInput,
  RtmfFrontendItem,
  RtmfFrontendItemInput,
  RtmfFrontendApiEndpoint,
  RtmfFrontendApiEndpointInput,
  RtmfRelationEdge,
  RtmfFrontendFeedback,
  RtmfFrontendFeedbackRole,
  RtmfFrontendFeedbackStatus,
  RtmfFrontendScenarioGroup,
  RtmfFrontendScenarioGroupInput,
  RtmfFrontendScenarioRow,
  RtmfFrontendScenarioRowInput,
  RtmfModule,
  RtmfProject,
  RtmfProjectInput,
  RtmfProjectMember,
  RtmfScenario,
  RtmfScenarioInput,
  RtmfScenarioStep,
  RtmfScenarioStepInput,
  RtmfScenarioStepLink,
  RtmfScenarioStepLinkInput,
  RtmfModulePhoto,
  RtmfSnapshotStatus,
  RtmfSubModule,
  RtmfSubModulePhoto,
  RtmfUrlPath,
  RtmfUrlPathInput,
} from "@/types";

// ── Projects ──

export async function listRtmfProjects(params = "") {
  return apiRequest<{ data: RtmfProject[]; meta: Record<string, unknown> }>(`/api/rtmf-projects${params}`);
}

// ── Dashboard ──
export async function fetchRtmfDashboard(params = "") {
  return apiRequest<{ data: RtmfDashboardSummary }>(`/api/rtmf/dashboard${params}`);
}

export async function fetchRtmfByAssignee(params = "") {
  return apiRequest<{ data: RtmfByAssigneeSummary }>(`/api/rtmf/dashboard/by-assignee${params}`);
}

// ── Frontends ──
export async function listRtmfFrontends(params = "", init: RequestInit = {}) {
  return apiRequest<{ data: RtmfFrontend[]; meta: Record<string, unknown> }>(
    `/api/rtmf-frontends${params}`,
    init,
  );
}

export async function getRtmfFrontend(id: number) {
  return apiRequest<{ data: RtmfFrontend }>(`/api/rtmf-frontends/${id}`);
}

export async function getRtmfIncomingLinks(id: number) {
  return apiRequest<{ data: { id: number; specId: string; title: string; links: { itemId: number; type: string | null }[] }[] }>(`/api/rtmf-frontends/${id}/incoming-links`);
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

export async function duplicateRtmfFrontend(id: number) {
  return apiRequest<{ data: RtmfFrontend }>(`/api/rtmf-frontends/${id}/duplicate`, {
    method: "POST",
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
export async function linkRtmfAttachment(frontendId: number, payload: { url: string; originalName: string; mimeType: string; size: number; label: string }) {
  return apiRequest<{ data: RtmfAttachment }>(`/api/rtmf-frontends/${frontendId}/attachments/link`, { method: "POST", body: JSON.stringify(payload) });
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

// ── URL Paths ──
export async function listRtmfUrlPaths(params = "") {
  return apiRequest<{ data: RtmfUrlPath[]; meta: Record<string, unknown> }>(
    `/api/rtmf-url-paths${params}`,
  );
}

export async function getRtmfUrlPath(id: number) {
  return apiRequest<{ data: RtmfUrlPath }>(`/api/rtmf-url-paths/${id}`);
}

export async function createRtmfUrlPath(input: RtmfUrlPathInput) {
  return apiRequest<{ data: RtmfUrlPath }>("/api/rtmf-url-paths", {
    method: "POST",
    body: JSON.stringify(input),
  });
}

export async function updateRtmfUrlPath(id: number, input: Partial<RtmfUrlPathInput>) {
  return apiRequest<{ data: RtmfUrlPath }>(`/api/rtmf-url-paths/${id}`, {
    method: "PUT",
    body: JSON.stringify(input),
  });
}

export async function deleteRtmfUrlPath(id: number) {
  return apiRequest<{ data: { success: boolean } }>(`/api/rtmf-url-paths/${id}`, {
    method: "DELETE",
  });
}

export async function captureRtmfUrlPathSnapshot(id: number) {
  return apiRequest<{ data: RtmfUrlPath }>(`/api/rtmf-url-paths/${id}/snapshot`, {
    method: "POST",
  });
}

// ── API Endpoints ──

export async function listRtmfApiEndpoints(frontendId: number) {
  return apiRequest<{ data: RtmfFrontendApiEndpoint[] }>(`/api/rtmf-frontends/${frontendId}/api-endpoints`);
}

export async function createRtmfApiEndpoint(frontendId: number, input: RtmfFrontendApiEndpointInput) {
  return apiRequest<{ data: RtmfFrontendApiEndpoint }>(`/api/rtmf-frontends/${frontendId}/api-endpoints`, {
    method: "POST",
    body: JSON.stringify(input),
  });
}

export async function updateRtmfApiEndpoint(frontendId: number, id: number, input: Partial<RtmfFrontendApiEndpointInput>) {
  return apiRequest<{ data: RtmfFrontendApiEndpoint }>(`/api/rtmf-frontends/${frontendId}/api-endpoints/${id}`, {
    method: "PUT",
    body: JSON.stringify(input),
  });
}

export async function deleteRtmfApiEndpoint(frontendId: number, id: number) {
  return apiRequest<{ data: { success: boolean } }>(`/api/rtmf-frontends/${frontendId}/api-endpoints/${id}`, {
    method: "DELETE",
  });
}

// ── Feedback ──

export async function listRtmfFrontendFeedbacks(frontendId: number) {
  return apiRequest<{ data: RtmfFrontendFeedback[] }>(`/api/rtmf-frontends/${frontendId}/feedbacks`);
}

export async function upsertRtmfFrontendFeedback(
  frontendId: number,
  role: RtmfFrontendFeedbackRole,
  input: { status?: RtmfFrontendFeedbackStatus; comment?: string | null },
) {
  return apiRequest<{ data: RtmfFrontendFeedback }>(`/api/rtmf-frontends/${frontendId}/feedbacks/${role}`, {
    method: "PUT",
    body: JSON.stringify(input),
  });
}

// ── Projects ──

export async function createRtmfProject(input: RtmfProjectInput) {
  return apiRequest<{ data: RtmfProject }>("/api/rtmf-projects", {
    method: "POST",
    body: JSON.stringify(input),
  });
}

export async function getRtmfProject(id: number) {
  return apiRequest<{ data: RtmfProject }>(`/api/rtmf-projects/${id}`);
}

export async function updateRtmfProject(id: number, input: Partial<RtmfProjectInput>) {
  return apiRequest<{ data: RtmfProject }>(`/api/rtmf-projects/${id}`, {
    method: "PUT",
    body: JSON.stringify(input),
  });
}

export async function deleteRtmfProject(id: number) {
  return apiRequest<{ data: { success: boolean } }>(`/api/rtmf-projects/${id}`, { method: "DELETE" });
}

export async function listRtmfProjectMembers(projectId: number) {
  return apiRequest<{ data: RtmfProjectMember[] }>(`/api/rtmf-projects/${projectId}/members`);
}

export async function addRtmfProjectMember(projectId: number, input: { externalUserId: string; projectRole?: string }) {
  return apiRequest<{ data: RtmfProjectMember }>(`/api/rtmf-projects/${projectId}/members`, {
    method: "POST",
    body: JSON.stringify(input),
  });
}

export async function updateRtmfProjectMember(projectId: number, userId: number, input: { projectRole?: string }) {
  return apiRequest<{ data: RtmfProjectMember }>(`/api/rtmf-projects/${projectId}/members/${userId}`, {
    method: "PUT",
    body: JSON.stringify(input),
  });
}

export async function removeRtmfProjectMember(projectId: number, userId: number) {
  return apiRequest<{ data: { success: boolean } }>(`/api/rtmf-projects/${projectId}/members/${userId}`, {
    method: "DELETE",
  });
}

// ── Scenarios ──

export async function getRtmfScenario(id: number) {
  return apiRequest<{ data: RtmfScenario }>(`/api/rtmf-scenarios/${id}`);
}

export async function createRtmfScenario(input: RtmfScenarioInput) {
  return apiRequest<{ data: RtmfScenario }>("/api/rtmf-scenarios", {
    method: "POST",
    body: JSON.stringify(input),
  });
}

export async function updateRtmfScenario(id: number, input: Partial<RtmfScenarioInput>) {
  return apiRequest<{ data: RtmfScenario }>(`/api/rtmf-scenarios/${id}`, {
    method: "PUT",
    body: JSON.stringify(input),
  });
}

export async function deleteRtmfScenario(id: number) {
  return apiRequest<{ data: { success: boolean } }>(`/api/rtmf-scenarios/${id}`, { method: "DELETE" });
}

export async function listRtmfScenarioAttachments(scenarioId: number) {
  return apiRequest<{ data: RtmfScenarioAttachment[] }>(`/api/rtmf-scenarios/${scenarioId}/attachments`);
}

export async function uploadRtmfScenarioAttachment(scenarioId: number, file: File, label: string) {
  const form = new FormData();
  form.append("file", file);
  form.append("label", label);
  return apiRequest<{ data: RtmfScenarioAttachment }>(`/api/rtmf-scenarios/${scenarioId}/attachments`, {
    method: "POST",
    body: form,
  });
}

export async function updateRtmfScenarioAttachmentLabel(scenarioId: number, attachmentId: number, label: string) {
  return apiRequest<{ data: RtmfScenarioAttachment }>(`/api/rtmf-scenarios/${scenarioId}/attachments/${attachmentId}`, {
    method: "PUT",
    body: JSON.stringify({ label }),
  });
}

export async function deleteRtmfScenarioAttachment(scenarioId: number, attachmentId: number) {
  return apiRequest<{ data: { success: boolean } }>(`/api/rtmf-scenarios/${scenarioId}/attachments/${attachmentId}`, {
    method: "DELETE",
  });
}

// ── Scenario Steps ──

export async function createRtmfScenarioStep(scenarioId: number, input: RtmfScenarioStepInput) {
  return apiRequest<{ data: RtmfScenarioStep }>(`/api/rtmf-scenarios/${scenarioId}/steps`, {
    method: "POST",
    body: JSON.stringify(input),
  });
}

export async function updateRtmfScenarioStep(scenarioId: number, stepId: number, input: Partial<RtmfScenarioStepInput>) {
  return apiRequest<{ data: RtmfScenarioStep }>(`/api/rtmf-scenarios/${scenarioId}/steps/${stepId}`, {
    method: "PUT",
    body: JSON.stringify(input),
  });
}

export async function deleteRtmfScenarioStep(scenarioId: number, stepId: number) {
  return apiRequest<{ data: { success: boolean } }>(`/api/rtmf-scenarios/${scenarioId}/steps/${stepId}`, {
    method: "DELETE",
  });
}

export async function reorderRtmfScenarioSteps(scenarioId: number, ids: number[]) {
  return apiRequest<{ data: { success: boolean } }>(`/api/rtmf-scenarios/${scenarioId}/steps/reorder`, {
    method: "POST",
    body: JSON.stringify({ ids }),
  });
}

// ── Scenario Step Links ──

export async function createRtmfScenarioStepLink(scenarioId: number, stepId: number, input: RtmfScenarioStepLinkInput) {
  return apiRequest<{ data: RtmfScenarioStepLink }>(`/api/rtmf-scenarios/${scenarioId}/steps/${stepId}/links`, {
    method: "POST",
    body: JSON.stringify(input),
  });
}

export async function updateRtmfScenarioStepLink(
  scenarioId: number,
  stepId: number,
  linkId: number,
  input: Partial<RtmfScenarioStepLinkInput>,
) {
  return apiRequest<{ data: RtmfScenarioStepLink }>(
    `/api/rtmf-scenarios/${scenarioId}/steps/${stepId}/links/${linkId}`,
    { method: "PUT", body: JSON.stringify(input) },
  );
}

export async function deleteRtmfScenarioStepLink(scenarioId: number, stepId: number, linkId: number) {
  return apiRequest<{ data: { success: boolean } }>(
    `/api/rtmf-scenarios/${scenarioId}/steps/${stepId}/links/${linkId}`,
    { method: "DELETE" },
  );
}

// ── Scenarios List ──

export async function listRtmfScenarios(params = "") {
  return apiRequest<{ data: RtmfScenario[]; meta: Record<string, unknown> }>(`/api/rtmf-scenarios${params}`);
}

// ── Import / Export ──

export async function importRtmfCatalog(payload: unknown) {
  return apiRequest<{ data: RtmfImportResult[] }>("/api/rtmf-import", {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function fetchRtmfRelations(params = "") {
  return apiRequest<{ data: RtmfRelationEdge[] }>(`/api/rtmf-frontends/relations${params}`);
}
