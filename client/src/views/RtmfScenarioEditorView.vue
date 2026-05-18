<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { RouterLink, useRoute, useRouter } from "vue-router";
import { GitBranch, GripVertical, Paperclip, Plus, Save, Trash2, Upload, UserCheck, X } from "lucide-vue-next";
import AdminLayout from "@/layouts/AdminLayout.vue";
import {
  createRtmfScenario,
  createRtmfScenarioStep,
  createRtmfScenarioStepLink,
  deleteRtmfScenario,
  deleteRtmfScenarioAttachment,
  deleteRtmfScenarioStep,
  deleteRtmfScenarioStepLink,
  getRtmfScenario,
  listRtmfActors,
  listRtmfFrontends,
  listRtmfScenarioAttachments,
  reorderRtmfScenarioSteps,
  updateRtmfScenario,
  updateRtmfScenarioAttachmentLabel,
  updateRtmfScenarioStep,
  updateRtmfScenarioStepLink,
  uploadRtmfScenarioAttachment,
} from "@/api/rtmf";
import type { RtmfActor, RtmfFrontend, RtmfFrontendAssignee, RtmfScenario, RtmfScenarioAttachment, RtmfScenarioStep, RtmfScenarioStepLink } from "@/types";
import { listUsers, listExternalUsers } from "@/api/cms";
import { useToast } from "@/composables/useToast";
import { useConfirmDialog } from "@/composables/useConfirmDialog";
import { useAuthStore } from "@/stores/auth";
import { useRtmfProjectStore } from "@/stores/rtmfProject";

const route = useRoute();
const router = useRouter();
const toast = useToast();
const confirmDialog = useConfirmDialog();
const auth = useAuthStore();
const projectStore = useRtmfProjectStore();

const id = computed(() => Number(route.params.id || 0));
const isEdit = computed(() => id.value > 0);

// ── Scenario fields ──
const title = ref("");
const description = ref("");
const isDone = ref(false);
const saving = ref(false);

// ── Assignees ──
type AssigneeOption = RtmfFrontendAssignee & { email?: string | null };
const assignees = ref<RtmfFrontendAssignee[]>([]);
const allUsers = ref<AssigneeOption[]>([]);
const assigneeSearch = ref("");
const assigneeDropdownOpen = ref(false);

const filteredAssigneeUsers = computed(() => {
  const q = assigneeSearch.value.trim().toLowerCase();
  const selectedIds = new Set(assignees.value.map((a) => String(a.id)));
  return allUsers.value.filter(
    (u) => !selectedIds.has(String(u.id)) &&
      (!q || u.name.toLowerCase().includes(q) || (u.email ?? "").toLowerCase().includes(q))
  );
});

function addAssignee(user: AssigneeOption) {
  if (!assignees.value.some((a) => String(a.id) === String(user.id))) {
    assignees.value.push({ id: user.id, name: user.name, photoUrl: user.photoUrl ?? null, source: user.source });
  }
  assigneeSearch.value = "";
  assigneeDropdownOpen.value = false;
}

const isAssignedToMe = computed(() =>
  auth.user ? assignees.value.some((a) => String(a.id) === String(auth.user!.id)) : false
);
const canToggleDone = computed(() => projectStore.canEdit || isAssignedToMe.value);

// ── Steps ──
const steps = ref<RtmfScenarioStep[]>([]);

// ── All frontends for page picker ──
const allFrontends = ref<RtmfFrontend[]>([]);
const pageSearches = ref<Record<number, string>>({}); // stepId → search query
type PageDropdown = { stepId: number; x: number; y: number; width: number; el: HTMLElement } | null;
const pageDropdown = ref<PageDropdown>(null);

function openPageDropdown(e: Event, stepId: number) {
  const el = e.target as HTMLElement;
  const rect = el.getBoundingClientRect();
  pageDropdown.value = { stepId, x: rect.left, y: rect.bottom, width: rect.width, el };
  window.addEventListener('scroll', updatePageDropdownPos, true);
}

function updatePageDropdownPos() {
  if (!pageDropdown.value) return;
  const rect = pageDropdown.value.el.getBoundingClientRect();
  pageDropdown.value.x = rect.left;
  pageDropdown.value.y = rect.bottom;
  pageDropdown.value.width = rect.width;
}

function closePageDropdown() {
  window.removeEventListener('scroll', updatePageDropdownPos, true);
  pageDropdown.value = null;
}

// ── Actors ──
const allActors = ref<RtmfActor[]>([]);
const actorPickerOpen = ref<number | null>(null); // stepId

function actorsForStep(step: RtmfScenarioStep): { id: number; name: string }[] {
  return step.actors ?? [];
}

function actorPickerOptions(step: RtmfScenarioStep) {
  const selectedIds = new Set((step.actors ?? []).map((a) => a.id));
  return allActors.value.filter((a) => !selectedIds.has(a.id));
}

function addActor(step: RtmfScenarioStep, actor: RtmfActor) {
  if (!(step.actors ?? []).some((a) => a.id === actor.id)) {
    step.actors = [...(step.actors ?? []), { id: actor.id, name: actor.name }];
  }
  actorPickerOpen.value = null;
  saveStep(step);
}

function removeActor(step: RtmfScenarioStep, actorId: number) {
  step.actors = (step.actors ?? []).filter((a) => a.id !== actorId);
  saveStep(step);
}

// ── Link target picker open state — key: `${stepId}_${linkId}` ──
const linkPickerOpen = ref<string | null>(null);

// ── Drag reorder ──
const dragIndex = ref<number | null>(null);

// ── Load ──
async function loadScenario() {
  if (!isEdit.value) return;
  const res = await getRtmfScenario(id.value);
  const s: RtmfScenario = res.data;
  title.value = s.title ?? "";
  description.value = s.description ?? "";
  isDone.value = s.isDone ?? false;
  assignees.value = (s.assignees ?? []) as RtmfFrontendAssignee[];
  steps.value = (s.steps ?? []).map((step) => ({ ...step, links: step.links ?? [] }));
}

async function loadFrontends() {
  try {
    const pid = projectStore.activeProjectId;
    const pidParam = pid ? `&project_id=${pid}` : "";
    const PAGE_SIZE = 200;
    const MAX_PAGES = 50;
    let page = 1;
    let collected: RtmfFrontend[] = [];
    while (page <= MAX_PAGES) {
      const res = await listRtmfFrontends(`?limit=${PAGE_SIZE}&page=${page}&sort_by=spec_id&sort_dir=asc${pidParam}`);
      const rows = res.data ?? [];
      collected = collected.concat(rows);
      if (rows.length < PAGE_SIZE) break;
      page++;
    }
    allFrontends.value = collected;
  } catch {
    allFrontends.value = [];
  }
}

async function loadActors() {
  try {
    const pid = projectStore.activeProjectId;
    const params = pid ? `?project_id=${pid}` : "";
    const res = await listRtmfActors(params);
    allActors.value = res.data ?? [];
  } catch {
    allActors.value = [];
  }
}

async function loadUsers() {
  try {
    const [local, ext] = await Promise.all([listUsers(), listExternalUsers()]);
    const byEmail = new Map<string, { id: number | string; name: string; email: string; photoUrl: string | null; source: "local" | "external" }>();
    for (const u of local.data ?? []) {
      byEmail.set(u.email, { id: u.id, name: u.name, email: u.email, photoUrl: u.photoUrl ?? null, source: "local" });
    }
    for (const u of ext.data ?? []) {
      const existing = byEmail.get(u.email);
      if (existing) {
        if (u.photoUrl) existing.photoUrl = u.photoUrl;
      } else {
        byEmail.set(u.email, { id: u.id, name: u.name, email: u.email, photoUrl: u.photoUrl ?? null, source: "external" });
      }
    }
    allUsers.value = Array.from(byEmail.values()).sort((a, b) => a.name.localeCompare(b.name));
  } catch {
    allUsers.value = [];
  }
}

// ── Attachments ──
const attachments = ref<RtmfScenarioAttachment[]>([]);
const uploadFile = ref<File | null>(null);
const uploadLabel = ref("");
const uploading = ref(false);

async function loadAttachments() {
  if (!isEdit.value) return;
  const res = await listRtmfScenarioAttachments(id.value);
  attachments.value = res.data;
}

function onFileChange(e: Event) {
  const input = e.target as HTMLInputElement;
  uploadFile.value = input.files?.[0] ?? null;
}

async function handleUpload() {
  if (!uploadFile.value || !isEdit.value) return;
  uploading.value = true;
  try {
    const res = await uploadRtmfScenarioAttachment(id.value, uploadFile.value, uploadLabel.value);
    attachments.value.push(res.data);
    uploadFile.value = null;
    uploadLabel.value = "";
    (document.getElementById("scenario-attachment-file-input") as HTMLInputElement).value = "";
    toast.success("File uploaded");
  } catch (e) {
    toast.error("Upload failed", e instanceof Error ? e.message : "");
  } finally {
    uploading.value = false;
  }
}

async function saveAttachmentLabel(att: RtmfScenarioAttachment) {
  await updateRtmfScenarioAttachmentLabel(id.value, att.id, att.label ?? "");
}

async function removeAttachment(attachmentId: number) {
  await deleteRtmfScenarioAttachment(id.value, attachmentId);
  attachments.value = attachments.value.filter((a) => a.id !== attachmentId);
}

function formatBytes(bytes: number) {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

// ── Save scenario header ──
async function saveScenario() {
  if (!title.value.trim()) return;
  saving.value = true;
  try {
    const base = { title: title.value, description: description.value || null, isDone: isDone.value, assignees: assignees.value };
    const payload = isEdit.value ? base : { ...base, projectId: projectStore.activeProjectId ?? undefined };
    if (isEdit.value) {
      await updateRtmfScenario(id.value, base);
    } else {
      const res = await createRtmfScenario(payload);
      router.replace(`/admin/rtmf/scenarios/${res.data.id}`);
    }
  } catch (e) {
    toast.error("Save failed", e instanceof Error ? e.message : "");
  } finally {
    saving.value = false;
  }
}

async function toggleDone() {
  if (!isEdit.value || !canToggleDone.value) return;
  try {
    await updateRtmfScenario(id.value, { title: title.value, isDone: isDone.value });
  } catch {
    isDone.value = !isDone.value;
    toast.error("Failed to update status");
  }
}

// ── Delete scenario ──
async function remove() {
  if (!isEdit.value) return;
  const allowed = await confirmDialog.confirm({
    title: "Delete scenario?",
    message: `Remove "${title.value}"? This will delete all steps.`,
    confirmText: "Delete",
    destructive: true,
  });
  if (!allowed) return;
  try {
    await deleteRtmfScenario(id.value);
    toast.success("Scenario deleted");
    router.push("/admin/rtmf/scenarios");
  } catch (e) {
    toast.error("Delete failed", e instanceof Error ? e.message : "");
  }
}

// ── Steps CRUD ──
async function addStep() {
  if (!isEdit.value) return;
  const res = await createRtmfScenarioStep(id.value, { sortOrder: steps.value.length });
  steps.value.push({ ...res.data, links: res.data.links ?? [] });
}

async function saveStep(step: RtmfScenarioStep) {
  await updateRtmfScenarioStep(id.value, step.id, {
    rtmfFrontendId: step.rtmfFrontendId ?? null,
    actorIds: (step.actors ?? []).map((a) => a.id),
    note: step.note ?? null,
    sortOrder: step.sortOrder,
  });
}

async function removeStep(stepId: number) {
  await deleteRtmfScenarioStep(id.value, stepId);
  steps.value = steps.value.filter((s) => s.id !== stepId);
  // Clear any links pointing at the deleted step
  steps.value.forEach((s) => {
    s.links = (s.links ?? []).filter((l) => l.toStepId !== stepId);
  });
}

// ── Page picker helpers ──
function pageForStep(step: RtmfScenarioStep | null | undefined) {
  if (!step) return null;
  return step.rtmfFrontendId != null
    ? (allFrontends.value.find((f) => f.id === step.rtmfFrontendId) ?? step.page ?? null)
    : null;
}

function pagesForSearch(stepId: number): RtmfFrontend[] {
  const q = (pageSearches.value[stepId] ?? "").trim().toLowerCase();
  return allFrontends.value.filter(
    (f) => !q || (f.specId ?? "").toLowerCase().includes(q) || (f.title ?? "").toLowerCase().includes(q)
  );
}

function selectPage(step: RtmfScenarioStep, frontend: RtmfFrontend) {
  step.rtmfFrontendId = frontend.id;
  step.page = { id: frontend.id, specId: frontend.specId, title: frontend.title };
  pageSearches.value[step.id] = "";
  saveStep(step);
}

function clearPage(step: RtmfScenarioStep) {
  step.rtmfFrontendId = null;
  step.page = null;
  saveStep(step);
}

// ── Links CRUD ──
async function addLink(step: RtmfScenarioStep) {
  const res = await createRtmfScenarioStepLink(id.value, step.id, {
    toStepId: null,
    condition: null,
    sortOrder: (step.links ?? []).length,
  });
  step.links = [...(step.links ?? []), res.data];
}

async function saveLink(step: RtmfScenarioStep, link: RtmfScenarioStepLink) {
  await updateRtmfScenarioStepLink(id.value, step.id, link.id, {
    toStepId: link.toStepId ?? null,
    condition: link.condition ?? null,
    sortOrder: link.sortOrder,
  });
}

async function removeLink(step: RtmfScenarioStep, linkId: number) {
  await deleteRtmfScenarioStepLink(id.value, step.id, linkId);
  step.links = (step.links ?? []).filter((l) => l.id !== linkId);
}

function selectLinkTarget(step: RtmfScenarioStep, link: RtmfScenarioStepLink, target: RtmfScenarioStep) {
  link.toStepId = target.id;
  link.toStep = {
    id: target.id,
    rtmfFrontendId: target.rtmfFrontendId,
    page: target.page ?? null,
  };
  linkPickerOpen.value = null;
  saveLink(step, link);
}

function clearLinkTarget(step: RtmfScenarioStep, link: RtmfScenarioStepLink) {
  link.toStepId = null;
  link.toStep = null;
  saveLink(step, link);
}

function linkTargetPage(link: RtmfScenarioStepLink) {
  if (!link.toStepId) return null;
  const target = steps.value.find((s) => s.id === link.toStepId);
  if (target) return pageForStep(target);
  return link.toStep?.page ?? null;
}

function linkTargetStepIndex(link: RtmfScenarioStepLink) {
  return steps.value.findIndex((s) => s.id === link.toStepId);
}

// ── Drag reorder ──
function onDragStart(index: number) { dragIndex.value = index; }
function onDragOver(e: DragEvent) { e.preventDefault(); }
async function onDrop(targetIndex: number) {
  if (dragIndex.value === null || dragIndex.value === targetIndex) { dragIndex.value = null; return; }
  const arr = [...steps.value];
  const [moved] = arr.splice(dragIndex.value, 1);
  arr.splice(targetIndex, 0, moved);
  arr.forEach((s, i) => { s.sortOrder = i; });
  steps.value = arr;
  dragIndex.value = null;
  await reorderRtmfScenarioSteps(id.value, arr.map((s) => s.id));
}

// ── SVG flow diagram — horizontal layout, curved connectors ──
// Forward edges dip below into lanes; back edges arch above. Corners are smooth bezier curves.
const NODE_W  = 165;
const NODE_H  = 62;
const GAP_X   = 60;
const PAD_X   = 24;
const LANE_H  = 20;   // vertical height per lane row
const LANE_PAD = 14;  // gap from node edge to first lane
const CORNER  = 10;   // bezier corner radius

const diagram = computed(() => {
  if (steps.value.length === 0) return null;

  type RawEdge = { srcIdx: number; tgtIdx: number; span: number; isBack: boolean; link: RtmfScenarioStepLink };
  const rawEdges: RawEdge[] = [];
  const idxMap = new Map(steps.value.map((s, i) => [s.id, i]));

  steps.value.forEach((step) => {
    const srcIdx = idxMap.get(step.id)!;
    (step.links ?? []).forEach((link) => {
      if (link.toStepId == null || !idxMap.has(link.toStepId)) return;
      const tgtIdx = idxMap.get(link.toStepId)!;
      const span   = Math.abs(tgtIdx - srcIdx);
      rawEdges.push({ srcIdx, tgtIdx, span, isBack: tgtIdx < srcIdx, link });
    });
  });

  const maxBck = rawEdges.filter(e =>  e.isBack).reduce((m, e) => Math.max(m, e.span), 0);
  const maxFwd = rawEdges.filter(e => !e.isBack).reduce((m, e) => Math.max(m, e.span), 0);
  const topPad = maxBck > 0 ? LANE_PAD + maxBck * LANE_H + 12 : 24;
  const botPad = maxFwd > 0 ? LANE_PAD + maxFwd * LANE_H + 16 : 24;

  const nodeY = topPad;
  const nodes = steps.value.map((step, i) => ({
    step,
    index: i,
    x: PAD_X + i * (NODE_W + GAP_X),
    y: nodeY,
  }));

  const fwdOut = new Map<number,number>(); const fwdOutC = new Map<number,number>();
  const bckOut = new Map<number,number>(); const bckOutC = new Map<number,number>();
  const fwdIn  = new Map<number,number>(); const fwdInC  = new Map<number,number>();
  const bckIn  = new Map<number,number>(); const bckInC  = new Map<number,number>();
  rawEdges.forEach(({ srcIdx, tgtIdx, isBack }) => {
    if (!isBack) { fwdOut.set(srcIdx, (fwdOut.get(srcIdx) ?? 0) + 1); fwdIn.set(tgtIdx,  (fwdIn.get(tgtIdx)  ?? 0) + 1); }
    else         { bckOut.set(srcIdx, (bckOut.get(srcIdx) ?? 0) + 1); bckIn.set(tgtIdx,  (bckIn.get(tgtIdx)  ?? 0) + 1); }
  });

  const edges: { path: string; isBack: boolean; label: string | null; lx: number; ly: number; anchor: string }[] = [];
  const R = CORNER;

  rawEdges.forEach(({ srcIdx, tgtIdx, span, isBack, link }) => {
    const src = nodes[srcIdx];
    const tgt = nodes[tgtIdx];

    if (!isBack) {
      // Forward: exit bottom, curve down into lane, cross right, curve up into target bottom
      const laneY = nodeY + NODE_H + LANE_PAD + (span - 1) * LANE_H;
      const outN = fwdOut.get(srcIdx) ?? 1; const outI = fwdOutC.get(srcIdx) ?? 0; fwdOutC.set(srcIdx, outI + 1);
      const inN  = fwdIn.get(tgtIdx)  ?? 1; const inI  = fwdInC.get(tgtIdx)  ?? 0; fwdInC.set(tgtIdx,  inI  + 1);
      const ex = src.x + NODE_W * (outI + 1) / (outN + 1);
      const nx = tgt.x + NODE_W * (inI  + 1) / (inN  + 1);
      const ey = nodeY + NODE_H;
      const path = [
        `M ${ex} ${ey}`,
        `L ${ex} ${laneY - R}`,
        `Q ${ex} ${laneY} ${ex + R} ${laneY}`,
        `L ${nx - R} ${laneY}`,
        `Q ${nx} ${laneY} ${nx} ${laneY - R}`,
        `L ${nx} ${ey}`,
      ].join(' ');
      edges.push({ path, isBack: false, label: link.condition ?? null, lx: (ex + nx) / 2, ly: laneY + 5, anchor: 'middle' });

    } else {
      // Back: exit top, curve up into arch lane, cross left, curve down into target top
      const archY = nodeY - LANE_PAD - (span - 1) * LANE_H;
      const outN = bckOut.get(srcIdx) ?? 1; const outI = bckOutC.get(srcIdx) ?? 0; bckOutC.set(srcIdx, outI + 1);
      const inN  = bckIn.get(tgtIdx)  ?? 1; const inI  = bckInC.get(tgtIdx)  ?? 0; bckInC.set(tgtIdx,  inI  + 1);
      const ex = src.x + NODE_W * (outI + 1) / (outN + 1);
      const nx = tgt.x + NODE_W * (inI  + 1) / (inN  + 1);
      const ey = nodeY;
      const path = [
        `M ${ex} ${ey}`,
        `L ${ex} ${archY + R}`,
        `Q ${ex} ${archY} ${ex - R} ${archY}`,
        `L ${nx + R} ${archY}`,
        `Q ${nx} ${archY} ${nx} ${archY + R}`,
        `L ${nx} ${ey}`,
      ].join(' ');
      edges.push({ path, isBack: true, label: link.condition ?? null, lx: (ex + nx) / 2, ly: archY - 8, anchor: 'middle' });
    }
  });

  const svgW = PAD_X * 2 + steps.value.length * (NODE_W + GAP_X) - GAP_X;
  const svgH = topPad + NODE_H + botPad;

  return { nodes, edges, svgW, svgH };
});

onMounted(async () => {
  await Promise.all([loadScenario(), loadFrontends(), loadActors(), loadUsers(), loadAttachments()]);
});
</script>

<template>
  <AdminLayout>
    <div class="mx-auto max-w-7xl space-y-4">
      <!-- Breadcrumb -->
      <div>
        <nav class="mb-1 flex items-center gap-1.5 text-xs font-medium text-slate-500">
          <RouterLink to="/admin/rtmf/scenarios" class="hover:text-violet-600">Flow Scenarios</RouterLink>
          <span class="text-slate-300">/</span>
          <span class="text-slate-700">{{ isEdit ? 'Edit' : 'New' }}</span>
        </nav>
        <h1 class="page-title">{{ isEdit ? (title || 'Scenario') : 'New Scenario' }}</h1>
      </div>

      <!-- ── Flow diagram — full width ── -->
      <article v-if="isEdit" class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-4 py-3">
              <h2 class="text-sm font-semibold text-slate-900">Flow Diagram</h2>
            </div>

            <div v-if="!diagram" class="px-4 py-8 text-center text-sm text-slate-400">
              Add steps to see the flow.
            </div>

            <div v-else class="overflow-x-auto p-4">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                :width="diagram.svgW"
                :height="diagram.svgH"
                class="block"
              >
                <!-- Edges (drawn behind nodes) -->
                <path
                  v-for="(edge, ei) in diagram.edges"
                  :key="ei"
                  :d="edge.path"
                  fill="none"
                  stroke="#a78bfa"
                  stroke-width="1.5"
                  stroke-linejoin="round"
                  stroke-linecap="round"
                  :stroke-dasharray="edge.isBack ? '5 3' : 'none'"
                  :marker-end="edge.isBack ? 'url(#scenario-arrow-back)' : 'url(#scenario-arrow)'"
                />
                <!-- Condition labels -->
                <text
                  v-for="(edge, ei) in diagram.edges.filter(e => e.label)"
                  :key="`lbl-${ei}`"
                  :x="edge.lx"
                  :y="edge.ly"
                  :text-anchor="edge.anchor"
                  font-size="10"
                  font-family="sans-serif"
                  font-style="italic"
                  :fill="edge.isBack ? '#8b5cf6' : '#6d28d9'"
                >{{ edge.label!.length > 20 ? edge.label!.slice(0, 20) + '…' : edge.label }}</text>

                <!-- Nodes with page -->
                <a
                  v-for="n in diagram.nodes.filter(n => n.step.page)"
                  :key="`node-${n.step.id}`"
                  :href="`/admin/rtmf/frontends/${n.step.page!.id}`"
                  target="_blank"
                >
                  <rect
                    :x="n.x" :y="n.y"
                    :width="NODE_W" :height="NODE_H"
                    rx="6"
                    :fill="n.index === 0 ? '#1e293b' : '#ede9fe'"
                    :stroke="n.index === 0 ? '#334155' : '#a78bfa'"
                    stroke-width="1.5"
                    :class="n.index === 0 ? '' : 'cursor-pointer hover:fill-violet-200'"
                  />
                  <text
                    :x="n.x + NODE_W / 2" :y="n.y + 26"
                    text-anchor="middle"
                    font-size="11"
                    font-family="monospace"
                    font-weight="600"
                    :fill="n.index === 0 ? '#e2e8f0' : '#6d28d9'"
                  >{{ `${n.index + 1}. ${n.step.page!.specId}` }}</text>
                  <text
                    :x="n.x + NODE_W / 2" :y="n.y + 46"
                    text-anchor="middle"
                    font-size="11"
                    font-family="sans-serif"
                    :fill="n.index === 0 ? '#94a3b8' : '#7c3aed'"
                  >{{ n.step.page!.title.length > 18 ? n.step.page!.title.slice(0, 17) + '…' : n.step.page!.title }}</text>
                </a>

                <!-- Nodes without page (placeholder) -->
                <g v-for="n in diagram.nodes.filter(n => !n.step.page)" :key="`node-empty-${n.step.id}`">
                  <rect
                    :x="n.x" :y="n.y"
                    :width="NODE_W" :height="NODE_H"
                    rx="6"
                    fill="#f8fafc"
                    stroke="#e2e8f0"
                    stroke-width="1"
                    stroke-dasharray="4 3"
                  />
                  <text
                    :x="n.x + NODE_W / 2" :y="n.y + 26"
                    text-anchor="middle"
                    font-size="11"
                    font-family="monospace"
                    font-weight="600"
                    fill="#94a3b8"
                  >Step {{ n.index + 1 }}</text>
                  <text
                    :x="n.x + NODE_W / 2" :y="n.y + 46"
                    text-anchor="middle"
                    font-size="11"
                    font-family="sans-serif"
                    fill="#cbd5e1"
                  >No page</text>
                </g>

                <!-- Arrow marker definitions (at end, same as relation diagram) -->
                <defs>
                  <marker id="scenario-arrow" markerWidth="8" markerHeight="8" refX="8" refY="3" orient="auto">
                    <path d="M0,0 L0,6 L8,3 z" fill="#a78bfa" />
                  </marker>
                  <marker id="scenario-arrow-back" markerWidth="8" markerHeight="8" refX="8" refY="3" orient="auto">
                    <path d="M0,0 L0,6 L8,3 z" fill="#a78bfa" />
                  </marker>
                </defs>
              </svg>
            </div>
      </article>

      <!-- ── Two-column: steps + scenario details ── -->
      <div class="grid grid-cols-1 gap-4 lg:grid-cols-[1fr_320px]">

        <!-- Steps list -->
          <article v-if="isEdit" class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-3">
              <h2 class="text-sm font-semibold text-slate-900">Steps</h2>
              <span class="ml-auto text-sm text-slate-400">{{ steps.length }} step{{ steps.length !== 1 ? 's' : '' }}</span>
            </div>

            <div v-if="steps.length === 0" class="px-4 py-8 text-center text-sm text-slate-400">
              No steps yet. Add one below.
            </div>

            <div class="divide-y divide-slate-100">
              <div
                v-for="(step, index) in steps"
                :key="step.id"
                draggable="true"
                @dragstart="onDragStart(index)"
                @dragover="onDragOver"
                @drop="onDrop(index)"
                class="flex items-start gap-3 px-4 py-3 transition-colors"
                :class="dragIndex === index ? 'bg-violet-50 opacity-60' : 'hover:bg-slate-50'"
              >
                <!-- Drag handle -->
                <GripVertical class="mt-2.5 h-4 w-4 flex-shrink-0 cursor-grab text-slate-300 hover:text-slate-500 active:cursor-grabbing" />

                <!-- Step number -->
                <span class="mt-2 flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-violet-100 text-[10px] font-semibold text-violet-700">{{ index + 1 }}</span>

                <!-- Fields -->
                <div class="min-w-0 flex-1 space-y-2">

                  <!-- Page picker -->
                  <div class="relative">
                    <div
                      v-if="pageForStep(step)"
                      :title="pageForStep(step)!.title"
                      class="flex items-center gap-2 rounded-lg border border-violet-300 bg-violet-50 px-3 py-1.5 text-sm"
                    >
                      <span class="font-mono text-[11px] text-violet-600 flex-shrink-0">{{ pageForStep(step)!.specId }}</span>
                      <span class="min-w-0 flex-1 truncate text-slate-700">{{ pageForStep(step)!.title }}</span>
                      <button type="button" @click="clearPage(step)" class="flex-shrink-0 text-slate-400 hover:text-rose-500">
                        <X class="h-3.5 w-3.5" />
                      </button>
                    </div>
                    <div v-else>
                      <input
                        v-model="pageSearches[step.id]"
                        class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-700 shadow-sm focus:border-violet-400 focus:outline-none focus:ring-2 focus:ring-violet-100"
                        placeholder="Search page…"
                        @input="openPageDropdown($event, step.id)"
                        @focus="openPageDropdown($event, step.id)"
                        @blur="setTimeout(closePageDropdown, 150)"
                      />
                    </div>
                  </div>

                  <!-- Actor multi-picker -->
                  <div class="space-y-1">
                    <!-- Selected actor chips -->
                    <div v-if="actorsForStep(step).length" class="flex flex-wrap gap-1.5">
                      <div
                        v-for="a in actorsForStep(step)"
                        :key="a.id"
                        class="flex items-center gap-1 rounded-full border border-indigo-200 bg-indigo-50 pl-2.5 pr-1.5 py-0.5 text-xs font-medium text-indigo-700"
                      >
                        {{ a.name }}
                        <button type="button" @click="removeActor(step, a.id)" class="text-indigo-400 hover:text-rose-500">
                          <X class="h-3 w-3" />
                        </button>
                      </div>
                    </div>
                    <!-- Add actor dropdown -->
                    <div v-if="actorPickerOptions(step).length || !actorsForStep(step).length" class="relative">
                      <div v-if="actorPickerOpen === step.id" class="fixed inset-0 z-10" @click="actorPickerOpen = null" />
                      <button
                        type="button"
                        @click="actorPickerOpen = actorPickerOpen === step.id ? null : step.id"
                        class="relative z-20 flex items-center gap-1 rounded-lg border border-dashed border-slate-200 px-2.5 py-1 text-xs text-slate-400 hover:border-indigo-300 hover:text-indigo-500"
                      >
                        <Plus class="h-3 w-3" /> Add actor
                      </button>
                      <div
                        v-if="actorPickerOpen === step.id"
                        class="absolute left-0 top-full z-20 mt-0.5 min-w-[180px] overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg"
                      >
                        <div
                          v-for="a in actorPickerOptions(step)"
                          :key="a.id"
                          @mousedown.prevent="addActor(step, a)"
                          class="cursor-pointer px-3 py-2 text-sm text-slate-700 hover:bg-indigo-50"
                        >{{ a.name }}</div>
                        <div v-if="actorPickerOptions(step).length === 0" class="px-3 py-2 text-xs text-slate-400">All actors added</div>
                      </div>
                    </div>
                  </div>

                  <!-- Note -->
                  <input
                    v-model="step.note"
                    @blur="saveStep(step)"
                    class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-700 shadow-sm focus:border-violet-400 focus:outline-none focus:ring-2 focus:ring-violet-100"
                    placeholder="Note (optional)"
                  />

                  <!-- Links (goes-to rows) -->
                  <div class="space-y-1.5">
                    <div
                      v-for="link in (step.links ?? [])"
                      :key="link.id"
                      class="flex items-center gap-2 rounded-lg border border-slate-100 bg-slate-50 px-2.5 py-1.5"
                    >
                      <!-- Arrow label -->
                      <span class="flex-shrink-0 text-[11px] font-medium text-slate-400">→</span>

                      <!-- Condition input -->
                      <input
                        v-model="link.condition"
                        @blur="saveLink(step, link)"
                        class="w-28 flex-shrink-0 rounded border border-slate-200 bg-white px-2 py-1 text-xs text-slate-700 focus:border-violet-400 focus:outline-none focus:ring-1 focus:ring-violet-100"
                        placeholder="Condition…"
                      />

                      <!-- Target step picker -->
                      <div class="relative min-w-0 flex-1">
                        <!-- Backdrop -->
                        <div v-if="linkPickerOpen === `${step.id}_${link.id}`" class="fixed inset-0 z-10" @click="linkPickerOpen = null" />

                        <!-- Selected chip -->
                        <div
                          v-if="link.toStepId"
                          class="relative z-20 flex items-center gap-1.5 rounded border border-slate-200 bg-white px-2 py-1 text-xs"
                        >
                          <span class="flex h-3.5 w-3.5 flex-shrink-0 items-center justify-center rounded-full bg-violet-100 text-[8px] font-bold text-violet-700">
                            {{ linkTargetStepIndex(link) + 1 }}
                          </span>
                          <span class="flex-shrink-0 font-mono text-[10px] text-slate-400">{{ linkTargetPage(link)?.specId ?? '—' }}</span>
                          <span class="min-w-0 flex-1 truncate text-slate-600" :title="linkTargetPage(link)?.title">{{ linkTargetPage(link)?.title ?? 'No page' }}</span>
                          <button type="button" @click="clearLinkTarget(step, link)" class="flex-shrink-0 text-slate-300 hover:text-rose-500">
                            <X class="h-3 w-3" />
                          </button>
                        </div>

                        <!-- Trigger -->
                        <button
                          v-else
                          type="button"
                          @click="linkPickerOpen = linkPickerOpen === `${step.id}_${link.id}` ? null : `${step.id}_${link.id}`"
                          class="relative z-20 w-full rounded border border-dashed border-slate-300 bg-white px-2 py-1 text-left text-xs text-slate-400 hover:border-violet-400 hover:text-violet-500"
                        >
                          Select target step…
                        </button>

                        <!-- Dropdown -->
                        <div
                          v-if="linkPickerOpen === `${step.id}_${link.id}`"
                          class="absolute left-0 top-full z-20 mt-0.5 w-full min-w-[220px] overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg"
                        >
                          <div
                            v-for="other in steps.filter((s) => s.id !== step.id)"
                            :key="other.id"
                            @mousedown.prevent="selectLinkTarget(step, link, other)"
                            class="flex cursor-pointer items-center gap-2 px-3 py-2 text-sm hover:bg-violet-50"
                          >
                            <span class="flex h-4 w-4 flex-shrink-0 items-center justify-center rounded-full bg-violet-100 text-[9px] font-semibold text-violet-700">{{ steps.indexOf(other) + 1 }}</span>
                            <span class="flex-shrink-0 font-mono text-[11px] text-slate-400">{{ pageForStep(other)?.specId ?? '—' }}</span>
                            <span class="min-w-0 flex-1 truncate text-slate-700" :title="pageForStep(other)?.title">{{ pageForStep(other)?.title ?? 'No page' }}</span>
                          </div>
                          <div v-if="steps.filter((s) => s.id !== step.id).length === 0" class="px-3 py-2.5 text-xs text-slate-400">No other steps yet</div>
                        </div>
                      </div>

                      <!-- Delete link -->
                      <button
                        type="button"
                        @click="removeLink(step, link.id)"
                        class="flex-shrink-0 text-slate-300 hover:text-rose-500"
                      >
                        <Trash2 class="h-3.5 w-3.5" />
                      </button>
                    </div>

                    <!-- Add link -->
                    <button
                      v-if="projectStore.canEdit"
                      type="button"
                      @click="addLink(step)"
                      class="flex items-center gap-1 rounded px-1.5 py-1 text-xs text-slate-400 hover:bg-slate-100 hover:text-violet-600"
                    >
                      <Plus class="h-3 w-3" />
                      Add link
                    </button>
                  </div>
                </div>

                <!-- Delete step -->
                <button
                  v-if="projectStore.canEdit"
                  @click="removeStep(step.id)"
                  class="mt-1 flex-shrink-0 rounded-lg p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600"
                >
                  <Trash2 class="h-4 w-4" />
                </button>
              </div>
            </div>

            <div v-if="projectStore.canEdit" class="border-t border-slate-100 px-4 py-3">
              <button
                @click="addStep"
                class="flex items-center gap-2 rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 shadow-sm hover:bg-slate-50"
              >
                <Plus class="h-4 w-4" />
                Add Step
              </button>
            </div>
          </article>

        <!-- ── Right column: sidebar cards ── -->
        <div class="space-y-4">

          <!-- Scenario Details -->
          <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-3">
              <GitBranch class="h-4 w-4 text-violet-600" />
              <h2 class="text-sm font-semibold text-slate-900">Scenario Details</h2>
            </div>
            <div class="space-y-3 p-4">
              <div>
                <label class="mb-1 block text-xs font-medium text-slate-600">Title <span class="text-rose-500">*</span></label>
                <input
                  v-model="title"
                  @blur="saveScenario"
                  class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-800 shadow-sm focus:border-violet-400 focus:outline-none focus:ring-2 focus:ring-violet-100"
                  placeholder="e.g. User Registration Flow"
                />
              </div>
              <div>
                <label class="mb-1 block text-xs font-medium text-slate-600">Description</label>
                <textarea
                  v-model="description"
                  rows="3"
                  class="w-full resize-none rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-violet-400 focus:outline-none focus:ring-2 focus:ring-violet-100"
                  placeholder="Optional description…"
                />
              </div>
            </div>
          </article>

          <!-- Done toggle card -->
          <div class="rounded-lg border bg-white shadow-sm transition-colors" :class="isDone ? 'border-emerald-200' : 'border-slate-200'">
            <div class="flex items-center gap-3 px-4 py-3">
              <div class="flex-1">
                <p class="text-sm font-semibold text-slate-800">Mark as Done</p>
                <p class="mt-0.5 text-xs" :class="isDone ? 'text-emerald-600' : 'text-slate-400'">
                  {{ isDone ? 'This scenario is completed' : 'Not yet completed' }}
                </p>
              </div>
              <button
                type="button"
                :disabled="!canToggleDone"
                @click="isDone = !isDone; toggleDone()"
                class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 focus:outline-none disabled:cursor-not-allowed disabled:opacity-40"
                :class="isDone ? 'bg-emerald-500' : 'bg-slate-200'"
                :title="!canToggleDone ? 'Only assigned users or admins can toggle this' : ''"
              >
                <span
                  class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200"
                  :class="isDone ? 'translate-x-5' : 'translate-x-0'"
                />
              </button>
            </div>
          </div>

          <!-- Assign To card -->
          <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
              <UserCheck class="h-4 w-4 text-violet-600" />
              <h2 class="text-sm font-semibold text-slate-900">Assign To</h2>
            </div>
            <div class="space-y-2 p-3">
              <!-- Selected assignees -->
              <div v-if="assignees.length" class="space-y-1">
                <div
                  v-for="a in assignees"
                  :key="String(a.id)"
                  class="flex items-center gap-2 rounded-lg bg-violet-50 px-2 py-1.5"
                >
                  <img v-if="a.photoUrl" :src="a.photoUrl" class="h-6 w-6 shrink-0 rounded-full object-cover" />
                  <div v-else class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-violet-200 text-xs font-semibold text-violet-700">
                    {{ a.name.charAt(0).toUpperCase() }}
                  </div>
                  <span class="min-w-0 flex-1 truncate text-xs font-medium text-violet-900">{{ a.name }}</span>
                  <button
                    v-if="projectStore.canEdit"
                    type="button"
                    @click="assignees = assignees.filter((x) => String(x.id) !== String(a.id))"
                    class="flex shrink-0 items-center justify-center rounded-full p-0.5 text-violet-400 hover:bg-violet-200 hover:text-violet-700"
                  ><X class="h-3 w-3" /></button>
                </div>
              </div>
              <!-- Search input -->
              <div v-if="projectStore.canEdit" class="relative">
                <div v-if="assigneeDropdownOpen" class="fixed inset-0 z-10" @click="assigneeDropdownOpen = false" />
                <input
                  v-model="assigneeSearch"
                  placeholder="Search user…"
                  class="w-full rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs text-slate-700 shadow-sm focus:border-violet-400 focus:outline-none focus:ring-1 focus:ring-violet-200"
                  @focus="assigneeDropdownOpen = true"
                />
                <div
                  v-if="assigneeDropdownOpen && filteredAssigneeUsers.length"
                  class="absolute left-0 right-0 z-20 mt-1 max-h-44 overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg"
                >
                  <button
                    v-for="u in filteredAssigneeUsers"
                    :key="u.id"
                    type="button"
                    @mousedown.prevent="addAssignee(u)"
                    class="flex w-full items-center gap-2 px-3 py-2 text-left hover:bg-violet-50"
                  >
                    <img v-if="u.photoUrl" :src="u.photoUrl" class="h-6 w-6 shrink-0 rounded-full object-cover" />
                    <div v-else class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600">
                      {{ u.name.charAt(0).toUpperCase() }}
                    </div>
                    <div class="min-w-0">
                      <p class="truncate text-xs font-medium text-slate-800">{{ u.name }}</p>
                      <p class="truncate text-xs text-slate-400">{{ u.email }}</p>
                    </div>
                  </button>
                </div>
                <p v-if="assigneeDropdownOpen && !filteredAssigneeUsers.length && assigneeSearch" class="absolute left-0 right-0 z-20 mt-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-400 shadow-lg">No match.</p>
              </div>
              <p v-if="!assignees.length && !projectStore.canEdit" class="text-xs text-slate-400">No one assigned.</p>
            </div>
          </div>

          <!-- Attachments card -->
          <div v-if="isEdit" class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
              <Paperclip class="h-4 w-4 text-violet-600" />
              <h2 class="text-sm font-semibold text-slate-900">Attachments</h2>
              <span class="ml-auto text-xs text-slate-400">{{ attachments.length }} file{{ attachments.length !== 1 ? 's' : '' }}</span>
            </div>
            <div class="divide-y divide-slate-100">
              <div v-for="att in attachments" :key="att.id" class="flex items-center gap-2 px-3 py-2">
                <Paperclip class="h-3.5 w-3.5 shrink-0 text-slate-400" />
                <div class="min-w-0 flex-1 space-y-0.5">
                  <input
                    v-model="att.label"
                    @blur="saveAttachmentLabel(att)"
                    class="w-full rounded border border-transparent px-1 py-0.5 text-xs text-slate-700 hover:border-slate-200 focus:border-slate-300 focus:outline-none focus:ring-1 focus:ring-slate-200"
                    placeholder="Add label…"
                  />
                  <div class="flex items-center gap-1.5 text-xs text-slate-400">
                    <a :href="att.url" target="_blank" class="truncate font-mono hover:text-violet-600 hover:underline">{{ att.originalName }}</a>
                    <span>·</span>
                    <span class="shrink-0">{{ formatBytes(att.size) }}</span>
                  </div>
                </div>
                <button v-if="projectStore.canEdit" @click="removeAttachment(att.id)" class="shrink-0 rounded p-1 text-slate-400 hover:bg-rose-50 hover:text-rose-600">
                  <Trash2 class="h-3.5 w-3.5" />
                </button>
              </div>
              <div v-if="projectStore.canEdit" class="space-y-2 p-3">
                <input id="scenario-attachment-file-input" type="file" @change="onFileChange" class="block w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs text-slate-700 shadow-sm file:mr-2 file:rounded file:border-0 file:bg-slate-100 file:px-2 file:py-0.5 file:text-xs file:font-medium hover:file:bg-slate-200" />
                <input v-model="uploadLabel" class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs shadow-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200" placeholder="Label (optional)" />
                <button
                  :disabled="!uploadFile || uploading"
                  @click="handleUpload"
                  class="flex w-full items-center justify-center gap-1.5 rounded-lg bg-violet-600 px-3 py-1.5 text-xs font-medium text-white shadow-sm transition-colors hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-50"
                >
                  <Upload class="h-3.5 w-3.5" />
                  {{ uploading ? 'Uploading…' : 'Upload' }}
                </button>
              </div>
            </div>
          </div>

        </div>

      </div>

      <!-- ── Bottom action bar ── -->
      <div v-if="projectStore.canEdit" class="flex items-center gap-3">
        <button
          @click="saveScenario"
          :disabled="!title.trim() || saving"
          class="flex items-center gap-2 rounded-lg bg-slate-900 px-5 py-2.5 text-sm font-medium text-white shadow-sm transition-colors hover:bg-slate-800 disabled:opacity-40"
        >
          <Save class="h-4 w-4" />
          {{ saving ? 'Saving…' : (isEdit ? 'Update Scenario' : 'Create Scenario') }}
        </button>
        <button
          type="button"
          @click="router.push('/admin/rtmf/scenarios')"
          class="flex items-center gap-2 rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-600 shadow-sm transition-colors hover:bg-slate-50"
        >
          <X class="h-4 w-4" />
          Cancel
        </button>
        <button
          v-if="isEdit"
          type="button"
          @click="remove"
          class="ml-auto flex items-center gap-2 rounded-lg border border-rose-200 px-5 py-2.5 text-sm font-medium text-rose-600 shadow-sm transition-colors hover:bg-rose-50"
        >
          <Trash2 class="h-4 w-4" />
          Delete
        </button>
      </div>
    </div>
  </AdminLayout>

  <!-- Page search dropdown — teleported to body to escape stacking contexts -->
  <Teleport to="body">
    <div
      v-if="pageDropdown && (pageSearches[pageDropdown.stepId] ?? '').trim()"
      class="fixed z-[9999] max-h-48 overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg"
      :style="{ top: `${pageDropdown.y + 2}px`, left: `${pageDropdown.x}px`, width: `${Math.max(pageDropdown.width, 260)}px` }"
      @mousedown.prevent
    >
      <div
        v-for="f in pagesForSearch(pageDropdown.stepId)"
        :key="f.id"
        :title="f.title"
        @mousedown.prevent="selectPage(steps.find(s => s.id === pageDropdown!.stepId)!, f); closePageDropdown()"
        class="flex cursor-pointer items-center gap-2 px-3 py-2 text-sm hover:bg-violet-50"
      >
        <span class="flex-shrink-0 font-mono text-[11px] text-slate-500">{{ f.specId }}</span>
        <span class="min-w-0 flex-1 truncate text-slate-700">{{ f.title }}</span>
      </div>
      <div v-if="pagesForSearch(pageDropdown.stepId).length === 0" class="px-3 py-2.5 text-sm text-slate-400">No results</div>
    </div>
  </Teleport>
</template>
