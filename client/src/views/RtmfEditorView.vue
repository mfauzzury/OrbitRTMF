<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";
import { RouterLink, useRoute, useRouter } from "vue-router";
import { Cable, Paperclip, Trash2, LayoutGrid, Save, Upload, X, Plus, TableProperties, ExternalLink, Search, GripVertical, Layout, UserCheck, MessageSquare, CheckCircle2, Share2 } from "lucide-vue-next";

import AdminLayout from "@/layouts/AdminLayout.vue";
import MarkdownEditor from "@/components/MarkdownEditor.vue";
import {
  createRtmfFrontend,
  deleteRtmfFrontend,
  listRtmfFrontends,
  deleteRtmfAttachment,
  getRtmfFrontend,
  listRtmfActors,
  listRtmfAttachments,
  listRtmfModules,
  listRtmfSubModules,
  updateRtmfAttachmentLabel,
  updateRtmfFrontend,
  uploadRtmfAttachment,
  listRtmfFrontendItems,
  createRtmfFrontendItem,
  updateRtmfFrontendItem,
  deleteRtmfFrontendItem,
  listRtmfScenarioGroups,
  createRtmfScenarioGroup,
  updateRtmfScenarioGroup,
  deleteRtmfScenarioGroup,
  createRtmfScenarioRow,
  updateRtmfScenarioRow,
  deleteRtmfScenarioRow,
  listRtmfFrontendFeedbacks,
  upsertRtmfFrontendFeedback,
  listRtmfApiEndpoints,
  createRtmfApiEndpoint,
  updateRtmfApiEndpoint,
  deleteRtmfApiEndpoint,
  getRtmfIncomingLinks,
} from "@/api/rtmf";
import { listUsers, listExternalUsers } from "@/api/cms";
import type { RtmfActor, RtmfApiEndpointMethod, RtmfAttachment, RtmfFrontend, RtmfFrontendApiEndpoint, RtmfFrontendAssignee, RtmfFrontendFeedback, RtmfFrontendFeedbackStatus, RtmfFrontendItem, RtmfFrontendScenarioGroup, RtmfFrontendScenarioRow, RtmfModule, RtmfSubModule } from "@/types";
import { useConfirmDialog } from "@/composables/useConfirmDialog";
import { useToast } from "@/composables/useToast";
import { useAuthStore } from "@/stores/auth";
import { useRtmfProjectStore } from "@/stores/rtmfProject";

const vAutoResize = {
  mounted(el: HTMLTextAreaElement) {
    const MAX = 160;
    const resize = () => {
      el.style.height = 'auto';
      const next = Math.min(el.scrollHeight, MAX);
      el.style.height = next + 'px';
      el.style.overflowY = el.scrollHeight > MAX ? 'auto' : 'hidden';
    };
    resize();
    el.addEventListener('input', resize);
  },
  updated(el: HTMLTextAreaElement) {
    const MAX = 160;
    el.style.height = 'auto';
    const next = Math.min(el.scrollHeight, MAX);
    el.style.height = next + 'px';
    el.style.overflowY = el.scrollHeight > MAX ? 'auto' : 'hidden';
  },
};

const route = useRoute();
const router = useRouter();
const toast = useToast();
const confirmDialog = useConfirmDialog();
const auth = useAuthStore();
const projectStore = useRtmfProjectStore();

const id = computed(() => Number(route.params.id || 0));
const isEdit = computed(() => id.value > 0);

const specId = ref("");
const moduleId = ref<number | null>(null);
const subModuleId = ref<number | null>(null);
const actorIds = ref<number[]>([]);
const vuePath = ref("");
const urlDev = ref("");
const urlStg = ref("");
const urlPrd = ref("");
const tabCode = ref("");
const title = ref("");
const businessRequirement = ref("");
const stakeholderRequirement = ref("");
const description = ref("");
const createdAt = ref<string | null>(null);
const modules = ref<RtmfModule[]>([]);
const subModules = ref<RtmfSubModule[]>([]);
const actors = ref<RtmfActor[]>([]);
type AssigneeOption = RtmfFrontendAssignee & { email?: string | null };
const allUsers = ref<AssigneeOption[]>([]);
const assignees = ref<RtmfFrontendAssignee[]>([]);
const assigneeSearch = ref("");
const assigneeDropdownOpen = ref(false);
const isDone = ref(false);

const filteredAssigneeUsers = computed(() => {
  const q = assigneeSearch.value.trim().toLowerCase();
  const selectedIds = new Set(assignees.value.map((a) => String(a.id)));
  return allUsers.value.filter(
    (u) => !selectedIds.has(String(u.id)) &&
      (!q || u.name.toLowerCase().includes(q) || (u.email ?? "").toLowerCase().includes(q))
  );
});

function addAssignee(user: AssigneeOption) {
  const already = assignees.value.some((a) => String(a.id) === String(user.id));
  if (!already) {
    assignees.value.push({ id: user.id, name: user.name, photoUrl: user.photoUrl ?? null, source: user.source });
  }
  assigneeSearch.value = "";
  assigneeDropdownOpen.value = false;
}

// ── Page Links (item-level) ──
const allFrontends = ref<RtmfFrontend[]>([]);
const linksLoading = ref(true);

type CondDropdown = { itemId: number; li: number; x: number; y: number; width: number; el: HTMLElement } | null;
const condDropdown = ref<CondDropdown>(null);

function updateCondDropdownPos() {
  if (!condDropdown.value) return;
  const rect = condDropdown.value.el.getBoundingClientRect();
  condDropdown.value.x = rect.left;
  condDropdown.value.y = rect.bottom;
  condDropdown.value.width = rect.width;
}

function openCondDropdown(e: Event, itemId: number, li: number) {
  const el = e.target as HTMLElement;
  const rect = el.getBoundingClientRect();
  condDropdown.value = { itemId, li, x: rect.left, y: rect.bottom, width: rect.width, el };
  window.addEventListener('scroll', updateCondDropdownPos, true);
}

function closeCondDropdown() {
  window.removeEventListener('scroll', updateCondDropdownPos, true);
  condDropdown.value = null;
}

function selectCondPage(itemId: number, li: number, pageId: number) {
  conditionLines.value[itemId][li].p = pageId;
  conditionPageSearch.value[`${itemId}_${li}`] = '';
  closeCondDropdown();
  const item = items.value.find((i) => i.id === itemId);
  if (item) {
    item.condition = serializeConditionLines(itemId);
    saveItem(item);
  }
}


async function refreshSubModules() {
  if (!moduleId.value) {
    subModules.value = [];
    return;
  }
  const r = await listRtmfSubModules(moduleId.value);
  subModules.value = r.data;
}

// Build a flat option list from the submodule tree, with depth-based labels
type SubModuleOption = { id: number; label: string; depth: number };

const subModuleOptions = computed((): SubModuleOption[] => {
  type TreeNode = RtmfSubModule & { children: TreeNode[] };
  const map = new Map<number, TreeNode>();
  for (const s of subModules.value) map.set(s.id, { ...s, children: [] });
  const roots: TreeNode[] = [];
  for (const s of subModules.value) {
    const node = map.get(s.id)!;
    if (!s.parentId) roots.push(node);
    else map.get(s.parentId)?.children.push(node);
  }
  const result: SubModuleOption[] = [];
  function walk(nodes: TreeNode[], depth: number) {
    const sorted = [...nodes].sort((a, b) => a.sortOrder - b.sortOrder);
    for (const node of sorted) {
      const prefix = depth === 0 ? '' : ('  '.repeat(depth) + '└ ');
      result.push({ id: node.id, label: `${prefix}${node.code} — ${node.name}`, depth });
      if (node.children.length) walk(node.children, depth + 1);
    }
  }
  walk(roots, 0);
  return result;
});

watch(moduleId, async (next, prev) => {
  if (next !== prev) {
    await refreshSubModules();
    if (subModuleId.value && !subModules.value.some((s) => s.id === subModuleId.value)) {
      subModuleId.value = null;
    }
  }
});

async function loadRefData() {
  const pid = projectStore.activeProjectId;
  const pidParam = pid ? `?project_id=${pid}` : "";
  const [m, a, localU, extU] = await Promise.all([listRtmfModules(pidParam), listRtmfActors(pidParam), listUsers(), listExternalUsers()]);
  modules.value = m.data;
  actors.value = a.data;
  const local: AssigneeOption[] = localU.data.map((u) => ({
    id: u.id, name: u.name, email: u.email, photoUrl: u.photoUrl ?? null, source: 'local' as const,
  }));
  const external: AssigneeOption[] = extU.data.map((u) => ({
    id: u.id, name: u.name, email: u.email, photoUrl: u.avatarUrl ?? null, source: 'external' as const,
  }));
  allUsers.value = [...local, ...external].sort((a, b) => a.name.localeCompare(b.name));
}

async function loadAllFrontends() {
  linksLoading.value = true;
  try {
    const pid = projectStore.activeProjectId;
    const pidParam = pid ? `&project_id=${pid}` : "";
    const PAGE_SIZE = 200;
    const MAX_PAGES = 50; // safety cap: 50 × 200 = 10,000 items
    let page = 1;
    let collected: RtmfFrontend[] = [];
    while (page <= MAX_PAGES) {
      const res = await listRtmfFrontends(`?limit=${PAGE_SIZE}&page=${page}&sort_by=spec_id&sort_dir=asc${pidParam}`);
      const rows = res.data ?? [];
      collected = collected.concat(rows);
      const total = (res.meta?.total as number) ?? 0;
      const totalPages = (res.meta?.totalPages as number) ?? 1;
      // stop if we've fetched all pages, got an empty page, or meta is missing
      if (page >= totalPages || rows.length === 0 || collected.length >= total) break;
      page++;
    }
    allFrontends.value = collected;
  } catch {
    allFrontends.value = [];
  } finally {
    linksLoading.value = false;
  }
}

async function load() {
  if (!isEdit.value) return;
  const response = await getRtmfFrontend(id.value);
  const r = response.data;
  specId.value = r.specId;
  moduleId.value = r.moduleId ?? r.module?.id ?? null;
  subModuleId.value = r.subModuleId ?? r.subModule?.id ?? null;
  await refreshSubModules();
  actorIds.value = (r.actors ?? []).map((a) => a.id);
  assignees.value = (r.assignees ?? []) as RtmfFrontendAssignee[];
  isDone.value = r.isDone ?? false;
  vuePath.value = r.vuePath || "";
  urlDev.value = r.urlDev || "";
  urlStg.value = r.urlStg || "";
  urlPrd.value = r.urlPrd || "";
  tabCode.value = r.tabCode || "";
  title.value = r.title;
  businessRequirement.value = r.businessRequirement || "";
  stakeholderRequirement.value = r.stakeholderRequirement || "";
  description.value = r.description || "";
  createdAt.value = r.createdAt ?? null;
}

async function save() {
  if (!moduleId.value) {
    toast.error("Module required", "Please select a module.");
    return;
  }
  const payload = {
    specId: specId.value.trim(),
    moduleId: moduleId.value,
    subModuleId: subModuleId.value,
    actorIds: actorIds.value,
    assignees: assignees.value,
    isDone: isDone.value,
    vuePath: vuePath.value.trim() || null,
    urlDev: urlDev.value.trim() || null,
    urlStg: urlStg.value.trim() || null,
    urlPrd: urlPrd.value.trim() || null,
    tabCode: tabCode.value.trim() || null,
    title: title.value.trim(),
    businessRequirement: businessRequirement.value.trim() || null,
    stakeholderRequirement: stakeholderRequirement.value.trim() || null,
    description: description.value.trim() || null,
  };

  try {
    if (isEdit.value) {
      await updateRtmfFrontend(id.value, payload);
      toast.success("Page saved");
    } else {
      await createRtmfFrontend(payload);
      toast.success("Page created");
      router.push("/admin/rtmf/frontends");
    }
  } catch (e) {
    toast.error("Save failed", e instanceof Error ? e.message : "Unable to save entry.");
  }
}

async function syncDoneFromFeedbacks() {
  if (!isEdit.value) return;
  const allApproved = FEEDBACK_ROLES.every(r =>
    feedbacks.value.find(f => f.role === r.key)?.status === 'approved'
  );
  if (allApproved === isDone.value) return;
  isDone.value = allApproved;
  try {
    await updateRtmfFrontend(id.value, { isDone: isDone.value } as never);
  } catch {
    isDone.value = !isDone.value;
  }
}

async function remove() {
  if (!isEdit.value) return;
  const allowed = await confirmDialog.confirm({
    title: "Delete page?",
    message: `Remove "${specId.value}" from the catalog?`,
    confirmText: "Delete",
    destructive: true,
  });
  if (!allowed) return;
  try {
    await deleteRtmfFrontend(id.value);
    toast.success("Page deleted");
    router.push("/admin/rtmf/frontends");
  } catch (e) {
    toast.error("Delete failed", e instanceof Error ? e.message : "Unable to delete entry.");
  }
}

// ── FR Items ──
const items = ref<RtmfFrontendItem[]>([]);

const ACTION_TYPES = ['Button', 'Link', 'Icon', 'Tab', 'Component', 'Form'];
const isActionType = (type: string | null) => ACTION_TYPES.includes(type ?? '');

// Each Action row stores paired condition+page entries: [{c: string, p: number|null}]
type ConditionPair = { c: string; p: number | null };
const conditionLines = ref<Record<number, ConditionPair[]>>({});
// Search text per (itemId, lineIndex): key = `${itemId}_${li}`
const conditionPageSearch = ref<Record<string, string>>({});

function emptyPair(): ConditionPair { return { c: '', p: null }; }

function initConditionLines(item: RtmfFrontendItem) {
  if (item.id in conditionLines.value) return;
  if (!item.condition) { conditionLines.value[item.id] = [emptyPair()]; return; }
  try {
    const parsed = JSON.parse(item.condition);
    if (Array.isArray(parsed) && parsed.length) {
      // New format: [{c, p}]
      if (typeof parsed[0] === 'object' && parsed[0] !== null && 'c' in parsed[0]) {
        conditionLines.value[item.id] = parsed as ConditionPair[];
        return;
      }
      // Old format: string[] — migrate to [{c, p:null}]
      conditionLines.value[item.id] = parsed.map((s: string) => ({ c: s, p: null }));
      return;
    }
  } catch {}
  // Plain string fallback
  conditionLines.value[item.id] = [{ c: item.condition, p: null }];
}

function serializeConditionLines(itemId: number): string | null {
  const pairs = conditionLines.value[itemId] ?? [emptyPair()];
  const filled = pairs.filter((r) => r.c.trim() || r.p !== null);
  if (!filled.length) return null;
  return JSON.stringify(filled);
}

function addConditionLine(item: RtmfFrontendItem) {
  conditionLines.value[item.id] = [...(conditionLines.value[item.id] ?? [emptyPair()]), emptyPair()];
}

function removeConditionLine(item: RtmfFrontendItem, index: number) {
  const pairs = [...(conditionLines.value[item.id] ?? [emptyPair()])];
  pairs.splice(index, 1);
  conditionLines.value[item.id] = pairs.length ? pairs : [emptyPair()];
  item.condition = serializeConditionLines(item.id);
  saveItem(item);
}

function pageForLine(itemId: number, li: number) {
  const p = conditionLines.value[itemId]?.[li]?.p;
  return p != null ? (allFrontends.value.find((f) => f.id === p) ?? null) : null;
}

function pagesForLine(itemId: number, li: number): RtmfFrontend[] {
  const q = (conditionPageSearch.value[`${itemId}_${li}`] ?? '').trim().toLowerCase();
  return allFrontends.value.filter(
    (f) => !q || (f.specId ?? '').toLowerCase().includes(q) || (f.title ?? '').toLowerCase().includes(q)
  );
}

async function loadItems() {
  if (!isEdit.value) return;
  const res = await listRtmfFrontendItems(id.value);
  items.value = res.data;
  res.data.forEach(initConditionLines);
}

async function addItem() {
  const res = await createRtmfFrontendItem(id.value, { sortOrder: items.value.length });
  items.value.push(res.data);
  initConditionLines(res.data);
}

async function saveItem(item: RtmfFrontendItem) {
  const condition = isActionType(item.type)
    ? serializeConditionLines(item.id)
    : item.condition;
  await updateRtmfFrontendItem(id.value, item.id, {
    type: item.type,
    label: item.label,
    condition,
    validation: item.validation,
    mandatory: item.mandatory,
    tableFieldname: item.tableFieldname,
    sortOrder: item.sortOrder,
  });
}

// ── Drag-to-reorder ──
const dragIndex = ref<number | null>(null);

function onItemDragStart(index: number) {
  dragIndex.value = index;
}

function onItemDragOver(e: DragEvent) {
  e.preventDefault();
}

async function onItemDrop(targetIndex: number) {
  if (dragIndex.value === null || dragIndex.value === targetIndex) {
    dragIndex.value = null;
    return;
  }
  const arr = [...items.value];
  const [moved] = arr.splice(dragIndex.value, 1);
  arr.splice(targetIndex, 0, moved);
  arr.forEach((item, i) => { item.sortOrder = i; });
  items.value = arr;
  dragIndex.value = null;
  await Promise.all(arr.map((item) => saveItem(item)));
}

async function removeItem(itemId: number) {
  const item = items.value.find((i) => i.id === itemId);
  const label = item?.label ? `"${item.label}"` : `item #${items.value.indexOf(item!) + 1}`;
  const ok = await confirmDialog.confirm({
    title: 'Delete Item',
    message: `Remove ${label} from this page? This cannot be undone.`,
    confirmText: 'Delete',
    destructive: true,
  });
  if (!ok) return;
  await deleteRtmfFrontendItem(id.value, itemId);
  items.value = items.value.filter((i) => i.id !== itemId);
}

// ── Attachments ──
const attachments = ref<RtmfAttachment[]>([]);
const uploadFile = ref<File | null>(null);
const uploadLabel = ref("");
const uploading = ref(false);

async function loadAttachments() {
  if (!isEdit.value) return;
  const res = await listRtmfAttachments(id.value);
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
    const res = await uploadRtmfAttachment(id.value, uploadFile.value, uploadLabel.value);
    attachments.value.push(res.data);
    uploadFile.value = null;
    uploadLabel.value = "";
    (document.getElementById("attachment-file-input") as HTMLInputElement).value = "";
    toast.success("File uploaded");
  } catch (e) {
    toast.error("Upload failed", e instanceof Error ? e.message : "Unable to upload file.");
  } finally {
    uploading.value = false;
  }
}

async function saveAttachmentLabel(attachment: RtmfAttachment) {
  await updateRtmfAttachmentLabel(id.value, attachment.id, attachment.label ?? "");
}

async function removeAttachment(attachmentId: number) {
  await deleteRtmfAttachment(id.value, attachmentId);
  attachments.value = attachments.value.filter((a) => a.id !== attachmentId);
}

function formatBytes(bytes: number) {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

// ── Tab state ──
const activeTab = ref<"frontend" | "api" | "mockup" | "relation" | "scenario" | "feedback">("frontend");

// ── API Endpoints ──
const apiEndpoints = ref<RtmfFrontendApiEndpoint[]>([]);

const HTTP_METHODS: RtmfApiEndpointMethod[] = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

const METHOD_COLORS: Record<RtmfApiEndpointMethod, string> = {
  GET:    'bg-emerald-100 text-emerald-700',
  POST:   'bg-blue-100 text-blue-700',
  PUT:    'bg-amber-100 text-amber-700',
  PATCH:  'bg-orange-100 text-orange-700',
  DELETE: 'bg-rose-100 text-rose-700',
};

async function addApiEndpoint() {
  if (!isEdit.value) return;
  try {
    const res = await createRtmfApiEndpoint(id.value, { method: 'GET', endpoint: '' });
    apiEndpoints.value.push(res.data);
  } catch {
    toast.error("Failed to add endpoint");
  }
}

async function saveApiEndpoint(ep: RtmfFrontendApiEndpoint) {
  try {
    await updateRtmfApiEndpoint(id.value, ep.id, {
      method: ep.method,
      endpoint: ep.endpoint,
      description: ep.description,
    });
  } catch {
    toast.error("Failed to save endpoint");
  }
}

async function removeApiEndpoint(epId: number) {
  try {
    await deleteRtmfApiEndpoint(id.value, epId);
    apiEndpoints.value = apiEndpoints.value.filter(e => e.id !== epId);
  } catch {
    toast.error("Failed to delete endpoint");
  }
}

// ── Feedback state ──
const feedbacks = ref<RtmfFrontendFeedback[]>([]);

const FEEDBACK_ROLES = [
  { key: 'business_analyst' as const, label: 'Business Analyst' },
  { key: 'qa' as const,               label: 'QA' },
  { key: 'technical' as const,        label: 'Technical' },
  { key: 'developer' as const,        label: 'Developer' },
];

function formatFeedbackDate(iso: string | undefined): string {
  if (!iso) return '';
  return new Date(iso).toLocaleString('en-GB', { dateStyle: 'short', timeStyle: 'short' });
}

function canEditFeedbackRow(roleKey: string): boolean {
  const pr = projectStore.activeProjectRole;
  return pr === 'admin' || pr === roleKey;
}

function feedbackFor(role: string) {
  return feedbacks.value.find(f => f.role === role)
    ?? { id: 0, role, status: 'open', comment: null } as unknown as RtmfFrontendFeedback;
}

async function saveFeedback(role: string, patch: { status?: string; comment?: string | null }) {
  const fb = feedbackFor(role);
  const payload = { status: fb.status, comment: fb.comment, ...patch };
  try {
    const res = await upsertRtmfFrontendFeedback(id.value, role, payload);
    const idx = feedbacks.value.findIndex(f => f.role === role);
    if (idx >= 0) feedbacks.value[idx] = res.data;
    else feedbacks.value.push(res.data);
    await syncDoneFromFeedbacks();
  } catch {
    toast.error("Failed to save feedback");
  }
}

// ── Page relation diagram (built from conditionLines + incoming links API) ──
type RelationNode = {
  id: number;
  specId: string;
  title: string;
  links: { itemLabel: string; condition: string }[];
};
type IncomingNode = { id: number; specId: string; title: string; links: { itemId: number; type: string | null }[] };

const incomingNodes = ref<IncomingNode[]>([]);

async function loadIncomingLinks() {
  if (!isEdit.value) return;
  try {
    const res = await getRtmfIncomingLinks(id.value);
    incomingNodes.value = res.data;
  } catch { incomingNodes.value = []; }
}

const relationNodes = computed<RelationNode[]>(() => {
  const map = new Map<number, RelationNode>();
  for (const [itemIdStr, pairs] of Object.entries(conditionLines.value)) {
    const itemId = Number(itemIdStr);
    const item = items.value.find((i) => i.id === itemId);
    if (!item) continue;
    for (const pair of pairs) {
      if (pair.p === null) continue;
      const page = allFrontends.value.find((f) => f.id === pair.p);
      if (!page) continue;
      if (!map.has(pair.p)) {
        map.set(pair.p, { id: pair.p, specId: page.specId, title: page.title, links: [] });
      }
      const cTrunc = pair.c.length > 18 ? pair.c.slice(0, 16) + '…' : pair.c;
      map.get(pair.p)!.links.push({ itemLabel: `${item.type ?? 'Item'} #${itemId}`, condition: cTrunc });
    }
  }
  return [...map.values()];
});

const relationDiagram = computed(() => {
  const nodeW = 190;
  const nodeH = 52;
  const gap = 20;
  const outNodes = relationNodes.value;
  const inNodes = incomingNodes.value;
  const n = Math.max(outNodes.length, inNodes.length, 1);
  const svgH = Math.max(nodeH + 32, n * (nodeH + gap) + 32);
  const curX = 220;
  const curY = svgH / 2 - nodeH / 2;
  const leftX = 16;
  const rightX = 440;
  const posIncoming = inNodes.map((node, i) => ({ ...node, x: leftX, y: i * (nodeH + gap) + 16 }));
  const posOutgoing = outNodes.map((node, i) => ({ ...node, x: rightX, y: i * (nodeH + gap) + 16 }));
  return { nodeW, nodeH, svgH, curX, curY, leftX, rightX, posIncoming, posOutgoing, svgW: rightX + nodeW + 16 };
});

// ── Mockup image (stored as attachment with label __mockup__) ──
const mockupAttachment = computed(() => attachments.value.find((a) => a.label === "__mockup__") ?? null);
const mockupFile = ref<File | null>(null);
const mockupUploading = ref(false);

function onMockupFileChange(e: Event) {
  const input = e.target as HTMLInputElement;
  mockupFile.value = input.files?.[0] ?? null;
}

async function handleMockupUpload() {
  if (!mockupFile.value || !isEdit.value) return;
  mockupUploading.value = true;
  try {
    if (mockupAttachment.value) {
      await deleteRtmfAttachment(id.value, mockupAttachment.value.id);
      attachments.value = attachments.value.filter((a) => a.id !== mockupAttachment.value!.id);
    }
    const res = await uploadRtmfAttachment(id.value, mockupFile.value, "__mockup__");
    attachments.value.push(res.data);
    mockupFile.value = null;
    const el = document.getElementById("mockup-file-input") as HTMLInputElement | null;
    if (el) el.value = "";
    toast.success("Mockup uploaded");
  } catch (e) {
    toast.error("Upload failed", e instanceof Error ? e.message : "Unable to upload mockup.");
  } finally {
    mockupUploading.value = false;
  }
}

async function removeMockup() {
  if (!mockupAttachment.value) return;
  await deleteRtmfAttachment(id.value, mockupAttachment.value.id);
  attachments.value = attachments.value.filter((a) => a.id !== mockupAttachment.value!.id);
}

// ── Scenario Groups & Rows ──
const scenarioGroups = ref<RtmfFrontendScenarioGroup[]>([]);

async function loadScenarioGroups() {
  if (!isEdit.value) return;
  const res = await listRtmfScenarioGroups(id.value);
  scenarioGroups.value = res.data;
}

async function addScenarioGroup() {
  const res = await createRtmfScenarioGroup(id.value, { sortOrder: scenarioGroups.value.length });
  scenarioGroups.value.push(res.data);
}

async function saveScenarioGroup(group: RtmfFrontendScenarioGroup) {
  await updateRtmfScenarioGroup(id.value, group.id, {
    title: group.title,
    description: group.description,
    sortOrder: group.sortOrder,
  });
}

async function removeScenarioGroup(groupId: number) {
  await deleteRtmfScenarioGroup(id.value, groupId);
  scenarioGroups.value = scenarioGroups.value.filter((g) => g.id !== groupId);
}

async function addScenarioRow(group: RtmfFrontendScenarioGroup) {
  const res = await createRtmfScenarioRow(id.value, group.id, { sortOrder: group.rows.length });
  group.rows.push(res.data);
}

async function saveScenarioRow(group: RtmfFrontendScenarioGroup, row: RtmfFrontendScenarioRow) {
  await updateRtmfScenarioRow(id.value, group.id, row.id, {
    step: row.step,
    fasa: row.fasa,
    role: row.role,
    aktiviti: row.aktiviti,
    sortOrder: row.sortOrder,
  });
}

async function removeScenarioRow(group: RtmfFrontendScenarioGroup, rowId: number) {
  await deleteRtmfScenarioRow(id.value, group.id, rowId);
  group.rows = group.rows.filter((r) => r.id !== rowId);
}

onMounted(async () => {
  await loadRefData();
  await Promise.all([loadAllFrontends(), load()]);
  await loadItems();
  await loadAttachments();
  await loadScenarioGroups();
  if (isEdit.value) {
    const fbRes = await listRtmfFrontendFeedbacks(id.value);
    feedbacks.value = fbRes.data;
    const epRes = await listRtmfApiEndpoints(id.value);
    apiEndpoints.value = epRes.data;
    loadIncomingLinks();
  }
});
</script>

<template>
  <AdminLayout>
    <div class="mx-auto max-w-7xl space-y-4">
      <div>
        <nav class="mb-1 flex items-center gap-1.5 text-xs font-medium text-slate-500">
          <RouterLink to="/admin/rtmf/frontends" class="hover:text-violet-600 transition-colors">Page Catalog</RouterLink>
          <span class="text-slate-300">/</span>
          <RouterLink to="/admin/rtmf/frontends" class="hover:text-violet-600 transition-colors">Frontend Catalog</RouterLink>
          <span class="text-slate-300">/</span>
          <span class="text-slate-700">{{ isEdit ? 'Edit' : 'New' }}</span>
        </nav>
        <h1 class="page-title flex items-center gap-2">
          {{ isEdit ? 'Edit Page' : 'New Page' }}
          <span v-if="isDone" class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">
            <CheckCircle2 class="h-3 w-3" /> Completed
          </span>
        </h1>
        <p v-if="isEdit && createdAt" class="mt-1 text-sm text-slate-500">
          Created {{ new Date(createdAt).toLocaleDateString("en-GB", { day: "2-digit", month: "short", year: "numeric" }) }}
        </p>
      </div>

      <!-- Tab bar (edit mode only) -->
      <div v-if="isEdit" class="flex overflow-hidden rounded-t-lg border border-slate-200 bg-slate-50">
        <button
          @click="activeTab = 'frontend'"
          class="flex items-center gap-2 border-b-2 px-5 py-2.5 text-sm font-medium transition-colors"
          :class="activeTab === 'frontend' ? 'border-violet-600 bg-white text-violet-700' : 'border-transparent text-slate-500 hover:bg-white hover:text-slate-700'"
        >
          <TableProperties class="h-4 w-4" />
          Frontend
        </button>
        <button
          @click="activeTab = 'api'"
          class="flex items-center gap-2 border-b-2 px-5 py-2.5 text-sm font-medium transition-colors"
          :class="activeTab === 'api' ? 'border-violet-600 bg-white text-violet-700' : 'border-transparent text-slate-500 hover:bg-white hover:text-slate-700'"
        >
          <Cable class="h-4 w-4" />
          API
          <span v-if="apiEndpoints.length" class="ml-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">{{ apiEndpoints.length }}</span>
        </button>
        <button
          @click="activeTab = 'mockup'"
          class="flex items-center gap-2 border-b-2 px-5 py-2.5 text-sm font-medium transition-colors"
          :class="activeTab === 'mockup' ? 'border-violet-600 bg-white text-violet-700' : 'border-transparent text-slate-500 hover:bg-white hover:text-slate-700'"
        >
          <Layout class="h-4 w-4" />
          Mockup
          <span v-if="items.length" class="ml-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">{{ items.length }}</span>
        </button>
        <button
          @click="activeTab = 'relation'"
          class="flex items-center gap-2 border-b-2 px-5 py-2.5 text-sm font-medium transition-colors"
          :class="activeTab === 'relation' ? 'border-violet-600 bg-white text-violet-700' : 'border-transparent text-slate-500 hover:bg-white hover:text-slate-700'"
        >
          <Share2 class="h-4 w-4" />
          Relations
          <span v-if="relationNodes.length || incomingNodes.length" class="ml-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">{{ relationNodes.length + incomingNodes.length }}</span>
        </button>
        <!-- Scenario tab hidden temporarily -->

        <button
          @click="activeTab = 'feedback'"
          class="flex items-center gap-2 border-b-2 px-5 py-2.5 text-sm font-medium transition-colors"
          :class="activeTab === 'feedback' ? 'border-violet-600 bg-white text-violet-700' : 'border-transparent text-slate-500 hover:bg-white hover:text-slate-700'"
        >
          <MessageSquare class="h-4 w-4" />
          Feedback
          <span class="ml-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">{{ feedbacks.filter(f => f.status !== 'open').length }}/4</span>
        </button>
      </div>

      <!-- Frontend tab: main content + sidebar -->
      <div v-show="!isEdit || activeTab === 'frontend'" :class="isEdit ? 'grid grid-cols-1 items-start gap-4 lg:grid-cols-[1fr_260px]' : 'space-y-4'">

        <!-- Left: main sections -->
        <div class="space-y-4">

      <!-- Spec & Linkage -->
      <article class="rounded-lg border border-slate-200 bg-white shadow-sm" :class="isEdit ? 'rounded-tl-none' : ''">
        <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
          <LayoutGrid class="h-4 w-4 text-violet-600" />
          <h2 class="text-sm font-semibold text-slate-900">Spec &amp; Linkage</h2>
        </div>
        <div class="grid gap-3 p-4 md:grid-cols-2">
          <div class="space-y-1.5 md:col-span-2">
            <label class="text-sm font-medium text-slate-700">Title <span class="text-rose-500">*</span></label>
            <input v-model="title" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200" placeholder="Maklumat Peribadi" />
          </div>
          <div class="space-y-1.5">
            <label class="text-sm font-medium text-slate-700">Page ID</label>
            <input v-model="specId" class="w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm shadow-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200" placeholder="PRF-AS-QS-02_01_01" />
          </div>
          <div class="space-y-1.5">
            <label class="text-sm font-medium text-slate-700">Module <span class="text-rose-500">*</span></label>
            <select v-model.number="moduleId" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200">
              <option :value="null">— select —</option>
              <option v-for="m in modules" :key="m.id" :value="m.id">{{ m.code }} — {{ m.name }}</option>
            </select>
          </div>
          <div class="space-y-1.5">
            <label class="text-sm font-medium text-slate-700">Sub-module</label>
            <select v-model.number="subModuleId" :disabled="!moduleId" class="w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm shadow-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200 disabled:bg-slate-50 disabled:text-slate-400">
              <option :value="null">—</option>
              <option v-for="opt in subModuleOptions" :key="opt.id" :value="opt.id" :style="{ paddingLeft: `${opt.depth * 16}px` }">{{ opt.label }}</option>
            </select>
            <p v-if="!moduleId" class="text-xs text-slate-400">Select a module first.</p>
            <p v-else-if="subModules.length === 0" class="text-xs text-slate-400">This module has no sub-modules yet. <RouterLink :to="`/admin/rtmf/modules/${moduleId}`" class="text-violet-600 hover:underline">Add one →</RouterLink></p>
          </div>
          <div class="space-y-1.5 md:col-span-2">
            <label class="text-sm font-medium text-slate-700">Actors</label>
            <div class="flex flex-wrap gap-1.5 rounded-lg border border-slate-300 bg-white p-2 shadow-sm">
              <label
                v-for="a in actors"
                :key="a.id"
                class="flex cursor-pointer items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium transition-colors"
                :class="actorIds.includes(a.id)
                  ? 'border-violet-600 bg-violet-600 text-white'
                  : 'border-slate-200 bg-slate-50 text-slate-600 hover:border-slate-300 hover:bg-slate-100'"
              >
                <input type="checkbox" :value="a.id" v-model="actorIds" class="hidden" />
                {{ a.name }}
              </label>
              <span v-if="actors.length === 0" class="px-1 text-xs text-slate-400">No actors defined yet.</span>
            </div>
            <p class="text-xs text-slate-400">Click to toggle. Multiple actors can be assigned.</p>
          </div>
          <div class="space-y-1.5 md:col-span-2">
            <label class="text-sm font-medium text-slate-700">Business Requirement</label>
            <textarea v-model="businessRequirement" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200" />
          </div>
          <div class="space-y-1.5 md:col-span-2">
            <label class="text-sm font-medium text-slate-700">Stakeholder Requirement <span class="text-xs font-normal text-slate-400">(URS)</span></label>
            <textarea v-model="stakeholderRequirement" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200" />
          </div>
        </div>
      </article>

      <!-- Description -->
      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
          <LayoutGrid class="h-4 w-4 text-violet-600" />
          <h2 class="text-sm font-semibold text-slate-900">Description</h2>
        </div>
        <div class="p-4">
          <MarkdownEditor v-model="description" :rows="10" placeholder="Purpose, key fields, shared components used…" />
        </div>
      </article>

        </div><!-- end left column -->

        <!-- Right: sidebar (edit mode only) -->
        <aside v-if="isEdit" class="sticky top-4 space-y-4">

          <!-- Assign to card -->
          <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
              <UserCheck class="h-4 w-4 text-violet-600" />
              <h2 class="text-sm font-semibold text-slate-900">Assign To</h2>
            </div>
            <div class="p-3 space-y-2">
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
              <p v-if="!projectStore.canEdit && assignees.length === 0" class="text-xs text-slate-400">Only admins can assign.</p>
            </div>
          </div>

          <!-- Implementation card -->
          <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
              <ExternalLink class="h-4 w-4 text-violet-600" />
              <h2 class="text-sm font-semibold text-slate-900">Implementation</h2>
            </div>
            <div class="space-y-3 p-3">
              <div class="space-y-1.5">
                <label class="text-xs font-medium text-slate-600">Mockup Link</label>
                <div class="flex gap-1.5">
                  <input v-model="vuePath" class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 font-mono text-xs shadow-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200" placeholder="src/views/SomeView.vue" />
                  <a :href="vuePath || undefined" target="_blank" :tabindex="vuePath ? 0 : -1" class="flex items-center justify-center rounded-lg border border-slate-300 px-2 shadow-sm transition-colors" :class="vuePath ? 'text-slate-500 hover:border-violet-400 hover:text-violet-600' : 'pointer-events-none text-slate-300'">
                    <ExternalLink class="h-3.5 w-3.5" />
                  </a>
                </div>
              </div>
              <div class="space-y-1.5">
                <label class="text-xs font-medium text-slate-600">URL <span class="font-normal text-slate-400">(DEV)</span></label>
                <div class="flex gap-1.5">
                  <input v-model="urlDev" class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 font-mono text-xs shadow-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200" placeholder="http://localhost:5173/…" />
                  <a :href="urlDev || undefined" target="_blank" :tabindex="urlDev ? 0 : -1" class="flex items-center justify-center rounded-lg border border-slate-300 px-2 shadow-sm transition-colors" :class="urlDev ? 'text-slate-500 hover:border-violet-400 hover:text-violet-600' : 'pointer-events-none text-slate-300'">
                    <ExternalLink class="h-3.5 w-3.5" />
                  </a>
                </div>
              </div>
              <div class="space-y-1.5">
                <label class="text-xs font-medium text-slate-600">URL <span class="font-normal text-slate-400">(STG)</span></label>
                <div class="flex gap-1.5">
                  <input v-model="urlStg" class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 font-mono text-xs shadow-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200" placeholder="https://stg.example.com/…" />
                  <a :href="urlStg || undefined" target="_blank" :tabindex="urlStg ? 0 : -1" class="flex items-center justify-center rounded-lg border border-slate-300 px-2 shadow-sm transition-colors" :class="urlStg ? 'text-slate-500 hover:border-violet-400 hover:text-violet-600' : 'pointer-events-none text-slate-300'">
                    <ExternalLink class="h-3.5 w-3.5" />
                  </a>
                </div>
              </div>
              <div class="space-y-1.5">
                <label class="text-xs font-medium text-slate-600">URL <span class="font-normal text-slate-400">(PRD)</span></label>
                <div class="flex gap-1.5">
                  <input v-model="urlPrd" class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 font-mono text-xs shadow-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200" placeholder="https://app.example.com/…" />
                  <a :href="urlPrd || undefined" target="_blank" :tabindex="urlPrd ? 0 : -1" class="flex items-center justify-center rounded-lg border border-slate-300 px-2 shadow-sm transition-colors" :class="urlPrd ? 'text-slate-500 hover:border-violet-400 hover:text-violet-600' : 'pointer-events-none text-slate-300'">
                    <ExternalLink class="h-3.5 w-3.5" />
                  </a>
                </div>
              </div>
            </div>
          </div>

          <!-- Attachments card -->
          <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
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
              <!-- Upload row -->
              <div v-if="projectStore.canEdit" class="space-y-2 p-3">
                <input id="attachment-file-input" type="file" @change="onFileChange" class="block w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs text-slate-700 shadow-sm file:mr-2 file:rounded file:border-0 file:bg-slate-100 file:px-2 file:py-0.5 file:text-xs file:font-medium hover:file:bg-slate-200" />
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
        </aside>

      </div><!-- end frontend tab grid -->

      <!-- API tab (edit mode only) -->
      <div v-if="isEdit" v-show="activeTab === 'api'" class="space-y-4">
        <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-3">
            <Cable class="h-4 w-4 text-violet-600" />
            <h2 class="text-sm font-semibold text-slate-900">API Endpoints</h2>
            <span class="ml-auto text-sm text-slate-400">{{ apiEndpoints.length }} endpoint{{ apiEndpoints.length !== 1 ? 's' : '' }}</span>
          </div>

          <div class="overflow-x-auto">
            <!-- Header -->
            <div v-if="apiEndpoints.length > 0" class="grid min-w-[640px] grid-cols-[100px_1fr_1fr_40px] gap-3 border-b border-slate-100 bg-slate-50 px-5 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
              <span>Method</span>
              <span>Endpoint</span>
              <span>Description</span>
              <span></span>
            </div>

            <div class="divide-y divide-slate-100">
              <div
                v-for="ep in apiEndpoints"
                :key="ep.id"
                class="grid min-w-[640px] grid-cols-[100px_1fr_1fr_40px] items-center gap-3 px-5 py-2.5 hover:bg-slate-50"
              >
                <!-- Method -->
                <select
                  v-model="ep.method"
                  @change="saveApiEndpoint(ep)"
                  class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs font-semibold shadow-sm focus:border-violet-400 focus:outline-none focus:ring-2 focus:ring-violet-100"
                  :class="METHOD_COLORS[ep.method]"
                >
                  <option v-for="m in HTTP_METHODS" :key="m" :value="m">{{ m }}</option>
                </select>

                <!-- Endpoint URL -->
                <input
                  v-model="ep.endpoint"
                  @blur="saveApiEndpoint(ep)"
                  class="w-full rounded-lg border border-slate-200 px-2.5 py-1.5 font-mono text-xs text-slate-700 shadow-sm focus:border-violet-400 focus:outline-none focus:ring-2 focus:ring-violet-100"
                  placeholder="/api/resource"
                />

                <!-- Description -->
                <input
                  v-model="ep.description"
                  @blur="saveApiEndpoint(ep)"
                  class="w-full rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs text-slate-700 shadow-sm focus:border-violet-400 focus:outline-none focus:ring-2 focus:ring-violet-100"
                  placeholder="What this call does…"
                />

                <!-- Delete -->
                <button
                  @click="removeApiEndpoint(ep.id)"
                  class="flex items-center justify-center rounded p-1.5 text-slate-300 hover:bg-rose-50 hover:text-rose-500"
                >
                  <Trash2 class="h-3.5 w-3.5" />
                </button>
              </div>
            </div>

            <div v-if="apiEndpoints.length === 0" class="px-5 py-8 text-center text-sm text-slate-400">
              No API endpoints documented yet.
            </div>
          </div>

          <div class="border-t border-slate-100 px-5 py-3">
            <button
              @click="addApiEndpoint"
              class="flex items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-600 shadow-sm transition-colors hover:bg-slate-50"
            >
              <Plus class="h-3.5 w-3.5" />
              Add Endpoint
            </button>
          </div>
        </article>
      </div>

      <!-- Mockup tab (edit mode only) -->
      <div v-if="isEdit" v-show="activeTab === 'mockup'" class="space-y-4">

        <!-- Mockup image -->
        <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-3">
            <Layout class="h-4 w-4 text-violet-600" />
            <h2 class="text-sm font-semibold text-slate-900">Mockup Image</h2>
            <div v-if="mockupAttachment && projectStore.canEdit" class="ml-auto flex gap-2">
              <label class="flex cursor-pointer items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-600 shadow-sm transition-colors hover:bg-slate-50">
                <Upload class="h-4 w-4" />
                Replace
                <input type="file" accept="image/*" class="hidden" @change="onMockupFileChange" />
              </label>
              <button @click="removeMockup" class="flex items-center gap-1.5 rounded-lg border border-rose-200 px-3 py-1.5 text-sm font-medium text-rose-600 shadow-sm transition-colors hover:bg-rose-50">
                <Trash2 class="h-4 w-4" />
                Remove
              </button>
            </div>
          </div>

          <!-- Image display -->
          <div v-if="mockupAttachment" class="p-4">
            <img :src="mockupAttachment.url" alt="Mockup" class="w-full rounded-lg border border-slate-100 object-contain shadow-sm" />
          </div>

          <!-- Upload area (no image yet or after remove) -->
          <div v-else class="p-6">
            <label
              class="flex w-full cursor-pointer flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed border-slate-200 bg-slate-50 py-12 transition-colors hover:border-violet-300 hover:bg-violet-50"
              :class="{ 'border-violet-400 bg-violet-50': mockupFile }"
            >
              <Upload class="h-8 w-8 text-slate-300" />
              <div class="text-center">
                <p class="text-sm font-medium text-slate-600">{{ mockupFile ? mockupFile.name : 'Click to upload mockup image' }}</p>
                <p class="mt-1 text-xs text-slate-400">PNG, JPG, GIF, WebP</p>
              </div>
              <input id="mockup-file-input" type="file" accept="image/*" class="hidden" @change="onMockupFileChange" />
            </label>
            <div v-if="mockupFile && projectStore.canEdit" class="mt-3 flex justify-end">
              <button
                @click="handleMockupUpload"
                :disabled="mockupUploading"
                class="flex items-center gap-2 rounded-lg bg-violet-600 px-5 py-2 text-sm font-medium text-white shadow-sm transition-colors hover:bg-violet-700 disabled:opacity-50"
              >
                <Upload class="h-4 w-4" />
                {{ mockupUploading ? 'Uploading…' : 'Upload' }}
              </button>
            </div>
          </div>

          <!-- Replace trigger upload handler (hidden file input result) -->
          <div v-if="mockupFile && mockupAttachment && projectStore.canEdit" class="border-t border-slate-100 px-4 py-3">
            <div class="flex items-center justify-between gap-3">
              <span class="truncate text-sm text-slate-600">{{ mockupFile.name }}</span>
              <button
                @click="handleMockupUpload"
                :disabled="mockupUploading"
                class="shrink-0 flex items-center gap-2 rounded-lg bg-violet-600 px-5 py-2 text-sm font-medium text-white shadow-sm transition-colors hover:bg-violet-700 disabled:opacity-50"
              >
                <Upload class="h-4 w-4" />
                {{ mockupUploading ? 'Uploading…' : 'Upload' }}
              </button>
            </div>
          </div>
        </article>

        <!-- Form Items -->
        <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-3">
            <TableProperties class="h-4 w-4 text-violet-600" />
            <h2 class="text-sm font-semibold text-slate-900">Form Items</h2>
            <span class="ml-auto text-sm text-slate-400">{{ items.length }} item{{ items.length !== 1 ? 's' : '' }}</span>
          </div>

          <div class="overflow-x-auto">
            <!-- Header row -->
            <div v-if="items.length > 0" class="grid min-w-[900px] grid-cols-[24px_28px_180px_1fr_1fr_1fr_1fr_56px_40px] gap-3 border-b border-slate-100 bg-slate-50 px-5 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
              <span></span>
              <span>#</span>
              <span>Type</span>
              <span>Label / Field</span>
              <span>Field Name</span>
              <span>Condition / Page Link</span>
              <span>Validation / Page</span>
              <span class="text-center">Mandatory</span>
              <span></span>
            </div>

            <div class="divide-y divide-slate-100">
              <div
                v-for="(item, index) in items"
                :key="item.id"
                draggable="true"
                @dragstart="onItemDragStart(index)"
                @dragover="onItemDragOver"
                @drop="onItemDrop(index)"
                class="grid min-w-[900px] grid-cols-[24px_28px_180px_1fr_1fr_1fr_1fr_56px_40px] items-start gap-3 px-5 py-3 transition-colors"
                :class="dragIndex === index ? 'bg-violet-50 opacity-60' : 'hover:bg-slate-50'"
              >
                <!-- Drag handle -->
                <GripVertical class="mt-2 h-4 w-4 cursor-grab text-slate-300 hover:text-slate-500 active:cursor-grabbing" />

                <!-- Running number -->
                <span class="mt-2 select-none font-mono text-sm font-medium text-slate-400">{{ index + 1 }}</span>

                <!-- Type -->
                <div class="space-y-0.5">
                <select
                  v-model="item.type"
                  @change="saveItem(item)"
                  class="w-full rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-sm text-slate-700 shadow-sm focus:border-violet-400 focus:outline-none focus:ring-2 focus:ring-violet-100"
                >
                  <option :value="null">— select —</option>
                  <optgroup label="Basic">
                    <option value="Text">Text</option>
                    <option value="Label">Label</option>
                    <option value="Header">Header</option>
                    <option value="Divider">Divider</option>
                  </optgroup>
                  <optgroup label="Input">
                    <option value="Input">Input</option>
                    <option value="Textarea">Textarea</option>
                    <option value="Select">Select / Dropdown</option>
                    <option value="Checkbox">Checkbox</option>
                    <option value="Radio">Radio</option>
                    <option value="DatePicker">Date Picker</option>
                    <option value="FileUpload">File Upload</option>
                  </optgroup>
                  <optgroup label="Action">
                    <option value="Button">Button</option>
                    <option value="Link">Link</option>
                    <option value="Icon">Icon</option>
                    <option value="Tab">Tab</option>
                    <option value="Component">Component</option>
                    <option value="Form">Form</option>
                  </optgroup>
                  <optgroup label="Display">
                    <option value="Badge">Badge</option>
                    <option value="Image">Image</option>
                    <option value="Table">Table</option>
                    <option value="List">List</option>
                    <option value="Card">Card</option>
                    <option value="Chart">Chart</option>
                  </optgroup>
                  <optgroup label="Overlay">
                    <option value="Modal">Modal</option>
                    <option value="Alert">Alert / Toast</option>
                  </optgroup>
                  <optgroup label="Notification">
                    <option value="Notification-Email">Email</option>
                    <option value="Notification-SMS">SMS</option>
                    <option value="Notification-MobileApps">Mobile Apps</option>
                    <option value="Notification-Web">Web</option>
                  </optgroup>
                  <optgroup label="Other">
                    <option value="Integrasi">Integrasi</option>
                  </optgroup>
                </select>
                <p class="font-mono text-[10px] text-slate-600">#{{ item.id }}</p>
                </div>

                <!-- Label / Field -->
                <input
                  v-model="item.label"
                  @blur="saveItem(item)"
                  class="w-full rounded-lg border border-slate-200 px-2.5 py-1.5 text-sm text-slate-700 shadow-sm focus:border-violet-400 focus:outline-none focus:ring-2 focus:ring-violet-100"
                  placeholder="e.g. Jenis Pengenalan"
                />

                <!-- Field Name -->
                <input
                  v-model="item.tableFieldname"
                  @blur="saveItem(item)"
                  class="w-full rounded-lg border border-slate-200 px-2.5 py-1.5 text-sm text-slate-700 shadow-sm focus:border-violet-400 focus:outline-none focus:ring-2 focus:ring-violet-100"
                  placeholder="e.g. nama_penuh"
                />

                <!-- Condition -->
                <textarea
                  v-if="!isActionType(item.type)"
                  v-auto-resize
                  v-model="item.condition"
                  @blur="saveItem(item)"
                  rows="2"
                  class="w-full resize-none rounded-lg border border-slate-200 px-2.5 py-1.5 text-sm text-slate-700 shadow-sm focus:border-violet-400 focus:outline-none focus:ring-2 focus:ring-violet-100"
                  style="min-height: 3.5rem"
                  placeholder="e.g. status ≠ DRAF"
                />
                <!-- Action: condition inputs, one per pair -->
                <div v-else class="space-y-1">
                  <div v-for="(pair, li) in (conditionLines[item.id] ?? [{ c: '', p: null }])" :key="li" class="flex h-8 items-center gap-1">
                    <input
                      :value="pair.c"
                      @input="conditionLines[item.id][li].c = ($event.target as HTMLInputElement).value"
                      @blur="item.condition = serializeConditionLines(item.id); saveItem(item)"
                      class="h-8 w-full rounded-md border border-slate-200 px-2 py-0 text-sm text-slate-700 shadow-sm focus:border-violet-400 focus:outline-none focus:ring-2 focus:ring-violet-100"
                      placeholder="e.g. status = AKTIF"
                    />
                    <button type="button" @click="removeConditionLine(item, li)" class="flex-shrink-0 text-slate-300 hover:text-rose-500">
                      <X class="h-3.5 w-3.5" />
                    </button>
                  </div>
                  <button type="button" @click="addConditionLine(item)" class="flex h-5 items-center gap-0.5 text-xs text-violet-500 hover:underline">
                    <Plus class="h-3 w-3" />Add
                  </button>
                </div>

                <!-- Validation / Page -->
                <textarea
                  v-if="!isActionType(item.type)"
                  v-auto-resize
                  v-model="item.validation"
                  @blur="saveItem(item)"
                  rows="2"
                  class="w-full resize-none rounded-lg border border-slate-200 px-2.5 py-1.5 text-sm text-slate-700 shadow-sm focus:border-violet-400 focus:outline-none focus:ring-2 focus:ring-violet-100"
                  style="min-height: 3.5rem"
                  placeholder="e.g. Max 255, Email"
                />
                <!-- Action: page picker per pair, aligned to condition rows -->
                <div v-else class="space-y-1">
                  <div v-for="(pair, li) in (conditionLines[item.id] ?? [{ c: '', p: null }])" :key="li" class="relative h-8">
                    <!-- Selected chip (page found) -->
                    <div
                      v-if="pageForLine(item.id, li)"
                      :title="pageForLine(item.id, li)!.title ?? ''"
                      class="flex h-8 items-center gap-1.5 overflow-hidden rounded-md border border-violet-300 bg-violet-50 px-2"
                    >
                      <span class="flex-shrink-0 font-mono text-[10px] text-violet-700">{{ pageForLine(item.id, li)!.specId }}</span>
                      <span class="min-w-0 flex-1 truncate text-xs text-slate-600">{{ pageForLine(item.id, li)!.title }}</span>
                      <button
                        type="button"
                        @click="conditionLines[item.id][li].p = null; item.condition = serializeConditionLines(item.id); saveItem(item)"
                        class="flex-shrink-0 text-slate-400 hover:text-rose-500"
                      ><X class="h-3.5 w-3.5" /></button>
                    </div>
                    <!-- Page ID set but allFrontends still loading or page deleted -->
                    <div
                      v-else-if="pair.p !== null"
                      class="flex h-8 items-center gap-1.5 overflow-hidden rounded-md border border-slate-200 bg-slate-50 px-2"
                    >
                      <span class="flex-shrink-0 font-mono text-[10px] text-slate-400">{{ linksLoading ? 'Loading…' : `#${pair.p} (not found)` }}</span>
                      <button
                        v-if="!linksLoading"
                        type="button"
                        @click="conditionLines[item.id][li].p = null; item.condition = serializeConditionLines(item.id); saveItem(item)"
                        class="flex-shrink-0 text-slate-300 hover:text-rose-500"
                      ><X class="h-3.5 w-3.5" /></button>
                    </div>
                    <!-- Search -->
                    <div v-else class="h-8">
                      <input
                        v-model="conditionPageSearch[`${item.id}_${li}`]"
                        class="h-8 w-full rounded-md border border-slate-200 px-2 py-0 text-sm text-slate-700 shadow-sm focus:border-violet-400 focus:outline-none focus:ring-2 focus:ring-violet-100"
                        placeholder="Search page…"
                        @input="openCondDropdown($event, item.id, li)"
                        @focus="openCondDropdown($event, item.id, li)"
                        @blur="setTimeout(closeCondDropdown, 150)"
                      />
                    </div>
                  </div>
                  <!-- spacer matches "+ Add" button height -->
                  <div class="h-5"></div>
                </div>

                <!-- Mandatory -->
                <div class="flex items-center justify-center pt-1.5">
                  <input
                    type="checkbox"
                    v-model="item.mandatory"
                    @change="saveItem(item)"
                    class="h-4 w-4 cursor-pointer rounded border-slate-300 accent-violet-600"
                  />
                </div>

                <button v-if="projectStore.canEdit" @click="removeItem(item.id)" class="mt-1 flex items-center justify-center rounded-lg p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600">
                  <Trash2 class="h-4 w-4" />
                </button>
              </div>
            </div>
          </div><!-- end overflow-x-auto -->

          <div v-if="projectStore.canEdit" class="border-t border-slate-100 px-5 py-3">
            <button
              @click="addItem"
              class="flex items-center gap-2 rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 shadow-sm transition-colors hover:bg-slate-50"
            >
              <Plus class="h-4 w-4" />
              Add Item
            </button>
          </div>
        </article>

      </div><!-- end mockup tab -->

      <!-- Relations tab (edit mode only) -->
      <div v-if="isEdit" v-show="activeTab === 'relation'" class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
          <Share2 class="h-4 w-4 text-violet-600" />
          <h2 class="text-sm font-semibold text-slate-900">Page Relations</h2>
          <span class="ml-2 text-xs text-slate-400">Outgoing links defined via action item conditions</span>
        </div>

        <div v-if="relationNodes.length === 0 && incomingNodes.length === 0" class="py-14 text-center text-sm text-slate-400">
          No page links yet. Add links by picking a page in the <strong class="font-medium text-slate-600">Condition / Page Link</strong> column for any action item (Button, Link, Icon, Tab, Component, Form).
        </div>

        <div v-else class="overflow-auto p-4">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            :width="relationDiagram.svgW"
            :height="relationDiagram.svgH"
            :viewBox="`0 0 ${relationDiagram.svgW} ${relationDiagram.svgH}`"
            class="block"
          >
            <defs>
              <marker id="rel-arrow-out" markerWidth="8" markerHeight="8" refX="6" refY="3" orient="auto">
                <path d="M0,0 L0,6 L8,3 z" fill="#a78bfa" />
              </marker>
              <marker id="rel-arrow-in" markerWidth="8" markerHeight="8" refX="6" refY="3" orient="auto">
                <path d="M0,0 L0,6 L8,3 z" fill="#0ea5e9" />
              </marker>
            </defs>

            <!-- Arrows from each incoming page → current page -->
            <g v-for="node in relationDiagram.posIncoming" :key="`in-edge-${node.id}`">
              <path
                :d="`M ${node.x + relationDiagram.nodeW} ${node.y + relationDiagram.nodeH / 2}
                     C ${(node.x + relationDiagram.nodeW + relationDiagram.curX) / 2} ${node.y + relationDiagram.nodeH / 2},
                       ${(node.x + relationDiagram.nodeW + relationDiagram.curX) / 2} ${relationDiagram.curY + relationDiagram.nodeH / 2},
                       ${relationDiagram.curX} ${relationDiagram.curY + relationDiagram.nodeH / 2}`"
                fill="none"
                stroke="#0ea5e9"
                stroke-width="1.5"
                marker-end="url(#rel-arrow-in)"
              />
              <text
                v-for="(link, li) in node.links"
                :key="li"
                :x="(node.x + relationDiagram.nodeW + relationDiagram.curX) / 2"
                :y="((node.y + relationDiagram.nodeH / 2 + relationDiagram.curY + relationDiagram.nodeH / 2) / 2) - (node.links.length - 1) * 8 + li * 16"
                text-anchor="middle"
                font-size="10"
                font-family="sans-serif"
                fill="#0369a1"
              >{{ link.type ?? 'Item' }} #{{ link.itemId }}</text>
            </g>

            <!-- Arrows from current page → each outgoing page -->
            <g v-for="node in relationDiagram.posOutgoing" :key="`out-edge-${node.id}`">
              <path
                :d="`M ${relationDiagram.curX + relationDiagram.nodeW} ${relationDiagram.curY + relationDiagram.nodeH / 2}
                     C ${(relationDiagram.curX + relationDiagram.nodeW + node.x) / 2} ${relationDiagram.curY + relationDiagram.nodeH / 2},
                       ${(relationDiagram.curX + relationDiagram.nodeW + node.x) / 2} ${node.y + relationDiagram.nodeH / 2},
                       ${node.x} ${node.y + relationDiagram.nodeH / 2}`"
                fill="none"
                stroke="#a78bfa"
                stroke-width="1.5"
                marker-end="url(#rel-arrow-out)"
              />
              <text
                v-for="(link, li) in node.links"
                :key="li"
                :x="(relationDiagram.curX + relationDiagram.nodeW + node.x) / 2"
                :y="((relationDiagram.curY + relationDiagram.nodeH / 2 + node.y + relationDiagram.nodeH / 2) / 2) - (node.links.length - 1) * 8 + li * 16"
                text-anchor="middle"
                font-size="10"
                font-family="sans-serif"
                fill="#7c3aed"
              >{{ link.itemLabel }}{{ link.condition ? ` · ${link.condition}` : '' }}</text>
            </g>

            <!-- Incoming page nodes (left, sky/teal) -->
            <a
              v-for="node in relationDiagram.posIncoming"
              :key="`in-node-${node.id}`"
              :href="`/admin/rtmf/frontends/${node.id}`"
              target="_blank"
            >
              <rect
                :x="node.x"
                :y="node.y"
                :width="relationDiagram.nodeW"
                :height="relationDiagram.nodeH"
                rx="6"
                fill="#e0f2fe"
                stroke="#38bdf8"
                stroke-width="1.5"
                class="cursor-pointer hover:fill-sky-200"
              />
              <text
                :x="node.x + relationDiagram.nodeW / 2"
                :y="node.y + 20"
                text-anchor="middle"
                font-size="12"
                font-family="monospace"
                fill="#0369a1"
                font-weight="600"
              >{{ node.specId }}</text>
              <text
                :x="node.x + relationDiagram.nodeW / 2"
                :y="node.y + 37"
                text-anchor="middle"
                font-size="11"
                font-family="sans-serif"
                fill="#0284c7"
              ><tspan>{{ node.title.length > 22 ? node.title.slice(0, 20) + '…' : node.title }}</tspan></text>
            </a>

            <!-- Current page node (center, dark) -->
            <rect
              :x="relationDiagram.curX"
              :y="relationDiagram.curY"
              :width="relationDiagram.nodeW"
              :height="relationDiagram.nodeH"
              rx="6"
              fill="#1e293b"
              stroke="#334155"
              stroke-width="1.5"
            />
            <text
              :x="relationDiagram.curX + relationDiagram.nodeW / 2"
              :y="relationDiagram.curY + 20"
              text-anchor="middle"
              font-size="12"
              font-family="monospace"
              fill="#e2e8f0"
              font-weight="600"
            >{{ specId }}</text>
            <text
              :x="relationDiagram.curX + relationDiagram.nodeW / 2"
              :y="relationDiagram.curY + 37"
              text-anchor="middle"
              font-size="11"
              font-family="sans-serif"
              fill="#94a3b8"
            ><tspan>{{ title.length > 22 ? title.slice(0, 20) + '…' : title }}</tspan></text>

            <!-- Outgoing page nodes (right, violet) -->
            <a
              v-for="node in relationDiagram.posOutgoing"
              :key="`out-node-${node.id}`"
              :href="`/admin/rtmf/frontends/${node.id}`"
              target="_blank"
            >
              <rect
                :x="node.x"
                :y="node.y"
                :width="relationDiagram.nodeW"
                :height="relationDiagram.nodeH"
                rx="6"
                fill="#ede9fe"
                stroke="#a78bfa"
                stroke-width="1.5"
                class="cursor-pointer hover:fill-violet-200"
              />
              <text
                :x="node.x + relationDiagram.nodeW / 2"
                :y="node.y + 20"
                text-anchor="middle"
                font-size="12"
                font-family="monospace"
                fill="#6d28d9"
                font-weight="600"
              >{{ node.specId }}</text>
              <text
                :x="node.x + relationDiagram.nodeW / 2"
                :y="node.y + 37"
                text-anchor="middle"
                font-size="11"
                font-family="sans-serif"
                fill="#7c3aed"
              ><tspan>{{ node.title.length > 22 ? node.title.slice(0, 20) + '…' : node.title }}</tspan></text>
            </a>
          </svg>
        </div>
      </div>

      <!-- Scenario (edit mode only) -->
      <article v-if="isEdit" v-show="activeTab === 'scenario'" class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <!-- Empty state -->
        <div v-if="scenarioGroups.length === 0" class="px-4 py-8 text-center text-sm text-slate-400">
          No scenario groups yet. Add one below.
        </div>

        <!-- Groups -->
        <div class="divide-y divide-slate-100">
          <div v-for="group in scenarioGroups" :key="group.id" class="space-y-3 p-4">
            <!-- Group header -->
            <div class="flex items-start gap-3">
              <div class="flex-1 space-y-2">
                <input
                  v-model="group.title"
                  @blur="saveScenarioGroup(group)"
                  class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-800 placeholder-slate-400 focus:border-violet-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-violet-100"
                  placeholder="Group title e.g. Senario 1: Pendaftaran"
                />
                <textarea
                  v-model="group.description"
                  @blur="saveScenarioGroup(group)"
                  rows="2"
                  class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600 placeholder-slate-400 focus:border-violet-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-violet-100"
                  placeholder="Optional group description…"
                />
              </div>
              <button
                v-if="projectStore.canEdit"
                @click="removeScenarioGroup(group.id)"
                class="mt-1 flex items-center justify-center rounded p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600"
                title="Delete group"
              >
                <Trash2 class="h-4 w-4" />
              </button>
            </div>

            <!-- Rows table -->
            <div class="overflow-x-auto rounded-lg border border-slate-100">
              <div v-if="group.rows.length > 0" class="grid min-w-[560px] grid-cols-[64px_160px_160px_1fr_32px] gap-2 border-b border-slate-100 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-500">
                <span>Step</span>
                <span>Fasa</span>
                <span>Role</span>
                <span>Aktiviti / Scenario</span>
                <span></span>
              </div>
              <div class="divide-y divide-slate-50">
                <div
                  v-for="row in group.rows"
                  :key="row.id"
                  class="grid min-w-[560px] grid-cols-[64px_160px_160px_1fr_32px] items-start gap-2 px-3 py-2"
                >
                  <input
                    v-model="row.step"
                    @blur="saveScenarioRow(group, row)"
                    class="w-full rounded border border-transparent px-1.5 py-1 font-mono text-xs text-slate-700 hover:border-slate-200 focus:border-slate-300 focus:outline-none focus:ring-1 focus:ring-slate-200"
                    placeholder="1"
                  />
                  <input
                    v-model="row.fasa"
                    @blur="saveScenarioRow(group, row)"
                    class="w-full rounded border border-transparent px-1.5 py-1 text-xs text-slate-700 hover:border-slate-200 focus:border-slate-300 focus:outline-none focus:ring-1 focus:ring-slate-200"
                    placeholder="e.g. Pra-Proses"
                  />
                  <select
                    v-model="row.role"
                    @change="saveScenarioRow(group, row)"
                    class="w-full rounded border border-transparent bg-transparent px-1.5 py-1 text-xs text-slate-700 hover:border-slate-200 focus:border-slate-300 focus:outline-none focus:ring-1 focus:ring-slate-200"
                  >
                    <option :value="null">—</option>
                    <option v-for="a in actors" :key="a.id" :value="a.name">{{ a.name }}</option>
                  </select>
                  <textarea
                    v-model="row.aktiviti"
                    @blur="saveScenarioRow(group, row)"
                    rows="2"
                    class="w-full resize-none rounded border border-transparent px-1.5 py-1 text-xs text-slate-700 hover:border-slate-200 focus:border-slate-300 focus:outline-none focus:ring-1 focus:ring-slate-200"
                    placeholder="Describe the activity or scenario step…"
                  />
                  <button
                    v-if="projectStore.canEdit"
                    @click="removeScenarioRow(group, row.id)"
                    class="mt-1 flex items-center justify-center rounded p-1 text-slate-400 hover:bg-rose-50 hover:text-rose-600"
                  >
                    <Trash2 class="h-3.5 w-3.5" />
                  </button>
                </div>
              </div>
            </div>

            <!-- Add row -->
            <button
              v-if="projectStore.canEdit"
              @click="addScenarioRow(group)"
              class="flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-500 shadow-sm transition-colors hover:bg-slate-50"
            >
              <Plus class="h-3.5 w-3.5" />
              Add Row
            </button>
          </div>
        </div>

        <!-- Add group -->
        <div v-if="projectStore.canEdit" class="border-t border-slate-100 px-4 py-3">
          <button
            @click="addScenarioGroup"
            class="flex items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-600 shadow-sm transition-colors hover:bg-slate-50"
          >
            <Plus class="h-3.5 w-3.5" />
            Add Scenario Group
          </button>
        </div>
      </article>

      <!-- Feedback (edit mode only) -->
      <article v-if="isEdit" v-show="activeTab === 'feedback'" class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
          <MessageSquare class="h-4 w-4 text-violet-600" />
          <h2 class="text-sm font-semibold text-slate-900">Feedback</h2>
        </div>
        <div class="divide-y divide-slate-100">
          <div
            v-for="roleDef in FEEDBACK_ROLES"
            :key="roleDef.key"
            class="p-4"
            :class="!canEditFeedbackRow(roleDef.key) ? 'opacity-60' : ''"
          >
            <div class="flex items-start gap-4">
              <div class="w-44 flex-shrink-0 pt-0.5">
                <span class="text-sm font-semibold text-slate-700">{{ roleDef.label }}</span>
                <span v-if="!canEditFeedbackRow(roleDef.key)" class="ml-1.5 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-500">Read only</span>
              </div>
              <div class="flex-1 space-y-2">
                <select
                  :value="feedbackFor(roleDef.key).status"
                  :disabled="!canEditFeedbackRow(roleDef.key)"
                  @change="saveFeedback(roleDef.key, { status: ($event.target as HTMLSelectElement).value as RtmfFrontendFeedbackStatus })"
                  class="rounded-lg border px-3 py-1.5 text-sm font-medium shadow-sm transition-colors"
                  :class="{
                    'focus:outline-none focus:ring-2 focus:ring-violet-100 cursor-pointer': canEditFeedbackRow(roleDef.key),
                    'cursor-not-allowed': !canEditFeedbackRow(roleDef.key),
                    'border-slate-200 bg-slate-50 text-slate-500': feedbackFor(roleDef.key).status === 'open',
                    'border-amber-200 bg-amber-50 text-amber-700': feedbackFor(roleDef.key).status === 'reviewed',
                    'border-emerald-200 bg-emerald-50 text-emerald-700': feedbackFor(roleDef.key).status === 'approved',
                  }"
                >
                  <option value="open">Open</option>
                  <option value="reviewed">In Progress</option>
                  <option value="approved">Closed</option>
                </select>
                <textarea
                  :value="feedbackFor(roleDef.key).comment ?? ''"
                  :disabled="!canEditFeedbackRow(roleDef.key)"
                  @blur="canEditFeedbackRow(roleDef.key) && saveFeedback(roleDef.key, { comment: ($event.target as HTMLTextAreaElement).value || null })"
                  rows="2"
                  class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 placeholder-slate-400 shadow-sm"
                  :class="canEditFeedbackRow(roleDef.key) ? 'bg-slate-50 focus:border-violet-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-violet-100' : 'bg-slate-50 cursor-not-allowed resize-none'"
                  :placeholder="canEditFeedbackRow(roleDef.key) ? 'Leave a comment…' : ''"
                />
                <p v-if="feedbackFor(roleDef.key).id" class="text-[10px] text-slate-400">
                  Updated {{ formatFeedbackDate(feedbackFor(roleDef.key).updatedAt) }}
                </p>
              </div>
            </div>
          </div>
        </div>
      </article>

      <div class="flex items-center gap-3">
        <template v-if="projectStore.canEdit">
          <button class="flex items-center gap-2 rounded-lg bg-slate-900 px-5 py-2.5 text-sm font-medium text-white shadow-sm transition-colors hover:bg-slate-800" @click="save">
            <Save class="h-4 w-4" />
            {{ isEdit ? 'Update Frontend' : 'Create Frontend' }}
          </button>
        </template>
        <button class="flex items-center gap-2 rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-600 shadow-sm transition-colors hover:bg-slate-50" @click="router.push('/admin/rtmf/frontends')">
          <X class="h-4 w-4" />
          {{ projectStore.canEdit ? 'Cancel' : 'Back to List' }}
        </button>
        <button v-if="isEdit && projectStore.canEdit" class="ml-auto flex items-center gap-2 rounded-lg border border-rose-200 px-5 py-2.5 text-sm font-medium text-rose-600 shadow-sm transition-colors hover:bg-rose-50" @click="remove">
          <Trash2 class="h-4 w-4" />
          Delete
        </button>
      </div>
    </div>
  </AdminLayout>

  <!-- Condition page search dropdown — teleported to body to escape stacking contexts -->
  <Teleport to="body">
    <div
      v-if="condDropdown && (conditionPageSearch[`${condDropdown.itemId}_${condDropdown.li}`] ?? '').trim()"
      class="fixed z-[9999] max-h-40 overflow-y-auto rounded-md border border-slate-200 bg-white shadow-lg"
      :style="{ top: `${condDropdown.y + 2}px`, left: `${condDropdown.x}px`, width: `${Math.max(condDropdown.width, 256)}px` }"
      @mousedown.prevent
    >
      <div
        v-for="f in pagesForLine(condDropdown.itemId, condDropdown.li)"
        :key="f.id"
        :title="f.title ?? ''"
        @mousedown.prevent="selectCondPage(condDropdown.itemId, condDropdown.li, f.id)"
        class="flex cursor-pointer items-center gap-1.5 px-2.5 py-1.5 hover:bg-violet-50"
      >
        <span class="flex-shrink-0 font-mono text-[10px] text-slate-500">{{ f.specId }}</span>
        <span class="min-w-0 flex-1 truncate text-xs text-slate-700">{{ f.title }}</span>
      </div>
      <div v-if="pagesForLine(condDropdown.itemId, condDropdown.li).length === 0" class="px-2.5 py-2 text-xs text-slate-400">No results</div>
    </div>
  </Teleport>

</template>
