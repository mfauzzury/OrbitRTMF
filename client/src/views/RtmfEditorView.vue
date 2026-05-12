<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";
import { RouterLink, useRoute, useRouter } from "vue-router";
import { Paperclip, Trash2 as TrashIcon, LayoutGrid, Save, Trash2, Upload, X, Plus, TableProperties, ClipboardList, ExternalLink, GitMerge, Search, Network, GripVertical, Layout, UserCheck } from "lucide-vue-next";

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
} from "@/api/rtmf";
import { listUsers, listExternalUsers } from "@/api/cms";
import type { RtmfActor, RtmfAttachment, RtmfFrontend, RtmfFrontendAssignee, RtmfFrontendItem, RtmfFrontendScenarioGroup, RtmfFrontendScenarioRow, RtmfModule, RtmfSubModule } from "@/types";
import { useConfirmDialog } from "@/composables/useConfirmDialog";
import { useToast } from "@/composables/useToast";
import { useAuthStore } from "@/stores/auth";

const route = useRoute();
const router = useRouter();
const toast = useToast();
const confirmDialog = useConfirmDialog();
const auth = useAuthStore();

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

// ── Page Links ──
type PageLinkRow = { id: number | null; note: string; search: string };
const fromLinks = ref<PageLinkRow[]>([]);
const toLinks = ref<PageLinkRow[]>([]);
const allFrontends = ref<RtmfFrontend[]>([]);
const linksLoading = ref(true);

const fromIds = computed(() => fromLinks.value.filter((l) => l.id !== null).map((l) => l.id as number));
const toIds = computed(() => toLinks.value.filter((l) => l.id !== null).map((l) => l.id as number));

function frontendById(fid: number) {
  return allFrontends.value.find((f) => f.id === fid);
}

function fromAvailable(rowId: number | null, search: string): RtmfFrontend[] {
  const taken = fromLinks.value.filter((l) => l.id !== null && l.id !== rowId).map((l) => l.id);
  const q = search.trim().toLowerCase();
  return allFrontends.value
    .filter((f) => f.id !== id.value && !taken.includes(f.id))
    .filter((f) => !q || (f.specId ?? "").toLowerCase().includes(q) || (f.title ?? "").toLowerCase().includes(q));
}

function toAvailable(rowId: number | null, search: string): RtmfFrontend[] {
  const taken = toLinks.value.filter((l) => l.id !== null && l.id !== rowId).map((l) => l.id);
  const q = search.trim().toLowerCase();
  return allFrontends.value
    .filter((f) => f.id !== id.value && !taken.includes(f.id))
    .filter((f) => !q || (f.specId ?? "").toLowerCase().includes(q) || (f.title ?? "").toLowerCase().includes(q));
}

const showDiagram = ref(false);

const diagram = computed(() => {
  const nodeW = 165;
  const nodeH = 54;
  const rowGap = 56;
  const fromCount = fromIds.value.length;
  const toCount = toIds.value.length;
  const maxCount = Math.max(fromCount, toCount, 1);
  const svgH = Math.max(maxCount * rowGap + 80, 180);

  function colY(count: number, index: number): number {
    const totalH = count * nodeH + (count - 1) * (rowGap - nodeH);
    const startY = (svgH - totalH) / 2;
    return startY + index * rowGap;
  }

  const leftX = 20;
  const centerX = 205;
  const rightX = 390;
  const curY = svgH / 2 - nodeH / 2;

  const fromNodes = fromIds.value.map((fid, i) => {
    const f = frontendById(fid);
    return { id: fid, specId: f?.specId ?? String(fid), title: f?.title ?? "", x: leftX, y: colY(fromCount, i) };
  });

  const toNodes = toIds.value.map((fid, i) => {
    const f = frontendById(fid);
    return { id: fid, specId: f?.specId ?? String(fid), title: f?.title ?? "", x: rightX, y: colY(toCount, i) };
  });

  return { fromNodes, toNodes, curX: centerX, curY, svgH, nodeW, nodeH };
});

function addFromLink() {
  fromLinks.value.push({ id: null, note: "", search: "" });
}
async function removeFromLink(index: number) {
  const row = fromLinks.value[index];
  if (row.id !== null) {
    const f = frontendById(row.id);
    const ok = await confirmDialog.confirm({
      title: "Remove link?",
      message: `Remove "${f?.specId ?? row.id}" from the From links?`,
      confirmText: "Remove",
      destructive: true,
    });
    if (!ok) return;
  }
  fromLinks.value.splice(index, 1);
}
function addToLink() {
  toLinks.value.push({ id: null, note: "", search: "" });
}
async function removeToLink(index: number) {
  const row = toLinks.value[index];
  if (row.id !== null) {
    const f = frontendById(row.id);
    const ok = await confirmDialog.confirm({
      title: "Remove link?",
      message: `Remove "${f?.specId ?? row.id}" from the Go To links?`,
      confirmText: "Remove",
      destructive: true,
    });
    if (!ok) return;
  }
  toLinks.value.splice(index, 1);
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
  const [m, a, localU, extU] = await Promise.all([listRtmfModules(), listRtmfActors(), listUsers(), listExternalUsers()]);
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
    const res = await listRtmfFrontends("?limit=500&sort_by=spec_id&sort_dir=asc");
    allFrontends.value = res.data ?? [];
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
  fromLinks.value = (r.linksFrom ?? []).map((f) => ({ id: f.id, note: "", search: "" }));
  toLinks.value = (r.linksTo ?? []).map((f) => ({ id: f.id, note: "", search: "" }));
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
    fromIds: fromIds.value,
    toIds: toIds.value,
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

const isAssignedToMe = computed(() =>
  auth.user ? assignees.value.some((a) => String(a.id) === String(auth.user!.id)) : false
);
const canToggleDone = computed(() => auth.isAdmin || isAssignedToMe.value);

async function toggleDone() {
  if (!isEdit.value || !canToggleDone.value) return;
  try {
    await updateRtmfFrontend(id.value, { isDone: isDone.value } as never);
  } catch (e) {
    isDone.value = !isDone.value; // revert on error
    toast.error("Failed to update status", e instanceof Error ? e.message : "");
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

const ACTION_TYPES = ['Button', 'Link', 'Icon', 'Tab'];
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
const activeTab = ref<"frontend" | "mockup" | "scenario">("frontend");

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
  loadAllFrontends();
  await load();
  await loadItems();
  await loadAttachments();
  await loadScenarioGroups();
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
        <h1 class="page-title">{{ isEdit ? 'Edit Page' : 'New Page' }}</h1>
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
          @click="activeTab = 'mockup'"
          class="flex items-center gap-2 border-b-2 px-5 py-2.5 text-sm font-medium transition-colors"
          :class="activeTab === 'mockup' ? 'border-violet-600 bg-white text-violet-700' : 'border-transparent text-slate-500 hover:bg-white hover:text-slate-700'"
        >
          <Layout class="h-4 w-4" />
          Mockup
          <span v-if="items.length" class="ml-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">{{ items.length }}</span>
        </button>
        <button
          @click="activeTab = 'scenario'"
          class="flex items-center gap-2 border-b-2 px-5 py-2.5 text-sm font-medium transition-colors"
          :class="activeTab === 'scenario' ? 'border-violet-600 bg-white text-violet-700' : 'border-transparent text-slate-500 hover:bg-white hover:text-slate-700'"
        >
          <ClipboardList class="h-4 w-4" />
          Scenario
          <span class="ml-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">{{ scenarioGroups.length }}</span>
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

      <!-- Page Links (edit mode only) -->
      <article v-if="isEdit" class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
          <GitMerge class="h-4 w-4 text-violet-600" />
          <h2 class="text-sm font-semibold text-slate-900">Page Links</h2>
          <button
            v-if="fromIds.length || toIds.length"
            type="button"
            @click="showDiagram = true"
            class="ml-auto flex items-center justify-center rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
            title="Show relation diagram"
          >
            <Network class="h-3.5 w-3.5" />
          </button>
        </div>

        <div v-if="linksLoading" class="px-4 py-6 text-center text-xs text-slate-400">Loading…</div>

        <template v-else>
          <!-- FROM section -->
          <div class="border-b border-slate-100">
            <div class="bg-slate-50 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500">← From</div>
            <div v-if="fromLinks.length > 0" class="grid min-w-[480px] grid-cols-[1fr_1fr_32px] gap-2 border-b border-slate-100 bg-slate-50 px-4 py-1.5 text-xs font-medium text-slate-500">
              <span>Page</span><span>Note / Condition</span><span></span>
            </div>
            <div class="divide-y divide-slate-100">
              <div
                v-for="(row, i) in fromLinks"
                :key="`from-${i}`"
                class="grid min-w-[480px] grid-cols-[1fr_1fr_32px] items-center gap-2 px-4 py-2"
              >
                <div class="relative">
                  <div v-if="row.id !== null" class="flex items-center gap-1.5 rounded border border-slate-200 bg-slate-50 px-1.5 py-1">
                    <a :href="`/admin/rtmf/frontends/${row.id}`" target="_blank" class="flex-1 truncate font-mono text-xs font-medium text-violet-700 hover:underline">{{ frontendById(row.id)?.specId ?? row.id }}</a>
                    <button type="button" @click="row.id = null; row.search = ''" class="shrink-0 text-slate-400 hover:text-slate-600"><X class="h-3 w-3" /></button>
                  </div>
                  <template v-else>
                    <div class="flex items-center gap-1 rounded border border-slate-200 bg-slate-50 px-1.5 py-1">
                      <Search class="h-3 w-3 shrink-0 text-slate-400" />
                      <input v-model="row.search" class="w-full bg-transparent text-xs text-slate-700 placeholder-slate-400 focus:outline-none" placeholder="Search page…" autocomplete="off" />
                    </div>
                    <div v-if="row.search" class="absolute left-0 right-0 z-20 mt-1 max-h-48 overflow-y-auto rounded-md border border-slate-200 bg-white shadow-md">
                      <button
                        v-for="f in fromAvailable(null, row.search)"
                        :key="f.id"
                        type="button"
                        @click="row.id = f.id; row.search = ''"
                        class="block w-full px-2.5 py-2 text-left hover:bg-violet-50"
                      >
                        <span class="block truncate font-mono text-xs font-medium text-slate-800">{{ f.specId }}</span>
                        <span class="block truncate text-xs text-slate-400">{{ f.title }}</span>
                      </button>
                      <p v-if="!fromAvailable(null, row.search).length" class="px-2.5 py-2 text-xs text-slate-400">No match.</p>
                    </div>
                  </template>
                </div>
                <input
                  v-model="row.note"
                  class="w-full rounded border border-transparent px-1.5 py-1 text-xs text-slate-700 hover:border-slate-200 focus:border-slate-300 focus:outline-none focus:ring-1 focus:ring-slate-200"
                  placeholder="Note / Condition…"
                />
                <button v-if="auth.isAdmin" @click="removeFromLink(i)" class="flex items-center justify-center rounded p-1 text-slate-400 hover:bg-rose-50 hover:text-rose-600">
                  <TrashIcon class="h-3.5 w-3.5" />
                </button>
              </div>
            </div>
            <div v-if="auth.isAdmin" class="px-4 py-2.5">
              <button @click="addFromLink" class="flex items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-600 shadow-sm transition-colors hover:bg-slate-50">
                <Plus class="h-3.5 w-3.5" />
                Add From
              </button>
            </div>
          </div>

          <!-- GO TO section -->
          <div>
            <div class="bg-slate-50 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Go To →</div>
            <div v-if="toLinks.length > 0" class="grid min-w-[480px] grid-cols-[1fr_1fr_32px] gap-2 border-b border-slate-100 bg-slate-50 px-4 py-1.5 text-xs font-medium text-slate-500">
              <span>Page</span><span>Note / Condition</span><span></span>
            </div>
            <div class="divide-y divide-slate-100">
              <div
                v-for="(row, i) in toLinks"
                :key="`to-${i}`"
                class="grid min-w-[480px] grid-cols-[1fr_1fr_32px] items-center gap-2 px-4 py-2"
              >
                <div class="relative">
                  <div v-if="row.id !== null" class="flex items-center gap-1.5 rounded border border-slate-200 bg-slate-50 px-1.5 py-1">
                    <a :href="`/admin/rtmf/frontends/${row.id}`" target="_blank" class="flex-1 truncate font-mono text-xs font-medium text-teal-700 hover:underline">{{ frontendById(row.id)?.specId ?? row.id }}</a>
                    <button type="button" @click="row.id = null; row.search = ''" class="shrink-0 text-slate-400 hover:text-slate-600"><X class="h-3 w-3" /></button>
                  </div>
                  <template v-else>
                    <div class="flex items-center gap-1 rounded border border-slate-200 bg-slate-50 px-1.5 py-1">
                      <Search class="h-3 w-3 shrink-0 text-slate-400" />
                      <input v-model="row.search" class="w-full bg-transparent text-xs text-slate-700 placeholder-slate-400 focus:outline-none" placeholder="Search page…" autocomplete="off" />
                    </div>
                    <div v-if="row.search" class="absolute left-0 right-0 z-20 mt-1 max-h-48 overflow-y-auto rounded-md border border-slate-200 bg-white shadow-md">
                      <button
                        v-for="f in toAvailable(null, row.search)"
                        :key="f.id"
                        type="button"
                        @click="row.id = f.id; row.search = ''"
                        class="block w-full px-2.5 py-2 text-left hover:bg-teal-50"
                      >
                        <span class="block truncate font-mono text-xs font-medium text-slate-800">{{ f.specId }}</span>
                        <span class="block truncate text-xs text-slate-400">{{ f.title }}</span>
                      </button>
                      <p v-if="!toAvailable(null, row.search).length" class="px-2.5 py-2 text-xs text-slate-400">No match.</p>
                    </div>
                  </template>
                </div>
                <input
                  v-model="row.note"
                  class="w-full rounded border border-transparent px-1.5 py-1 text-xs text-slate-700 hover:border-slate-200 focus:border-slate-300 focus:outline-none focus:ring-1 focus:ring-slate-200"
                  placeholder="Note / Condition…"
                />
                <button v-if="auth.isAdmin" @click="removeToLink(i)" class="flex items-center justify-center rounded p-1 text-slate-400 hover:bg-rose-50 hover:text-rose-600">
                  <TrashIcon class="h-3.5 w-3.5" />
                </button>
              </div>
            </div>
            <div v-if="auth.isAdmin" class="px-4 py-2.5">
              <button @click="addToLink" class="flex items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-600 shadow-sm transition-colors hover:bg-slate-50">
                <Plus class="h-3.5 w-3.5" />
                Add Go To
              </button>
            </div>
          </div>
        </template>
      </article>

        </div><!-- end left column -->

        <!-- Right: sidebar (edit mode only) -->
        <aside v-if="isEdit" class="sticky top-4 space-y-4">

          <!-- Done toggle card -->
          <div class="rounded-lg border bg-white shadow-sm transition-colors" :class="isDone ? 'border-emerald-200' : 'border-slate-200'">
            <div class="flex items-center gap-3 px-4 py-3">
              <div class="flex-1">
                <p class="text-sm font-semibold text-slate-800">Mark as Done</p>
                <p class="mt-0.5 text-xs" :class="isDone ? 'text-emerald-600' : 'text-slate-400'">
                  {{ isDone ? 'This page is completed' : 'Not yet completed' }}
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
                    v-if="auth.isAdmin"
                    type="button"
                    @click="assignees = assignees.filter((x) => String(x.id) !== String(a.id))"
                    class="flex shrink-0 items-center justify-center rounded-full p-0.5 text-violet-400 hover:bg-violet-200 hover:text-violet-700"
                  ><X class="h-3 w-3" /></button>
                </div>
              </div>

              <!-- Search input -->
              <div v-if="auth.isAdmin" class="relative">
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

              <p v-if="!assignees.length && !auth.isAdmin" class="text-xs text-slate-400">No one assigned.</p>
              <p v-if="!auth.isAdmin && assignees.length === 0" class="text-xs text-slate-400">Only admins can assign.</p>
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
                <button v-if="auth.isAdmin" @click="removeAttachment(att.id)" class="shrink-0 rounded p-1 text-slate-400 hover:bg-rose-50 hover:text-rose-600">
                  <TrashIcon class="h-3.5 w-3.5" />
                </button>
              </div>
              <!-- Upload row -->
              <div v-if="auth.isAdmin" class="space-y-2 p-3">
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

      <!-- Mockup tab (edit mode only) -->
      <div v-if="isEdit" v-show="activeTab === 'mockup'" class="space-y-4">

        <!-- Mockup image -->
        <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-3">
            <Layout class="h-4 w-4 text-violet-600" />
            <h2 class="text-sm font-semibold text-slate-900">Mockup Image</h2>
            <div v-if="mockupAttachment && auth.isAdmin" class="ml-auto flex gap-2">
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
            <div v-if="mockupFile && auth.isAdmin" class="mt-3 flex justify-end">
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
          <div v-if="mockupFile && mockupAttachment && auth.isAdmin" class="border-t border-slate-100 px-4 py-3">
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
            <div v-if="items.length > 0" class="grid min-w-[900px] grid-cols-[24px_44px_180px_1fr_1fr_1fr_1fr_56px_40px] gap-3 border-b border-slate-100 bg-slate-50 px-5 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
              <span></span>
              <span>#</span>
              <span>Type</span>
              <span>Label / Field</span>
              <span>Field Name</span>
              <span>Note / Condition / Parameter</span>
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
                class="grid min-w-[900px] grid-cols-[24px_44px_180px_1fr_1fr_1fr_1fr_56px_40px] items-start gap-3 px-5 py-3 transition-colors"
                :class="dragIndex === index ? 'bg-violet-50 opacity-60' : 'hover:bg-slate-50'"
              >
                <!-- Drag handle -->
                <GripVertical class="mt-2 h-4 w-4 cursor-grab text-slate-300 hover:text-slate-500 active:cursor-grabbing" />

                <!-- Running number -->
                <span class="mt-2 select-none font-mono text-sm font-medium text-slate-400">{{ index + 1 }}</span>

                <!-- Type -->
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
                    <option value="Component">Component</option>
                    <option value="Form">Form</option>
                    <option value="Integrasi">Integrasi</option>
                  </optgroup>
                </select>

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
                  v-model="item.condition"
                  @blur="saveItem(item)"
                  rows="2"
                  class="w-full resize-none rounded-lg border border-slate-200 px-2.5 py-1.5 text-sm text-slate-700 shadow-sm focus:border-violet-400 focus:outline-none focus:ring-2 focus:ring-violet-100"
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
                <input
                  v-if="!isActionType(item.type)"
                  v-model="item.validation"
                  @blur="saveItem(item)"
                  class="w-full rounded-lg border border-slate-200 px-2.5 py-1.5 text-sm text-slate-700 shadow-sm focus:border-violet-400 focus:outline-none focus:ring-2 focus:ring-violet-100"
                  placeholder="e.g. Required, Max 255"
                />
                <!-- Action: page picker per pair, aligned to condition rows -->
                <div v-else class="space-y-1">
                  <div v-for="(pair, li) in (conditionLines[item.id] ?? [{ c: '', p: null }])" :key="li" class="relative h-8">
                    <!-- Selected chip -->
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
                    <!-- Search -->
                    <div v-else class="relative h-8">
                      <input
                        v-model="conditionPageSearch[`${item.id}_${li}`]"
                        class="h-8 w-full rounded-md border border-slate-200 px-2 py-0 text-sm text-slate-700 shadow-sm focus:border-violet-400 focus:outline-none focus:ring-2 focus:ring-violet-100"
                        placeholder="Search page…"
                      />
                      <div
                        v-if="(conditionPageSearch[`${item.id}_${li}`] ?? '').trim()"
                        class="absolute left-0 top-full z-20 mt-0.5 max-h-40 w-64 overflow-y-auto rounded-md border border-slate-200 bg-white shadow-lg"
                      >
                        <div
                          v-for="f in pagesForLine(item.id, li)"
                          :key="f.id"
                          :title="f.title ?? ''"
                          @mousedown.prevent="conditionLines[item.id][li].p = f.id; conditionPageSearch[`${item.id}_${li}`] = ''; item.condition = serializeConditionLines(item.id); saveItem(item)"
                          class="flex cursor-pointer items-center gap-1.5 px-2.5 py-1.5 hover:bg-violet-50"
                        >
                          <span class="flex-shrink-0 font-mono text-[10px] text-slate-500">{{ f.specId }}</span>
                          <span class="min-w-0 flex-1 truncate text-xs text-slate-700">{{ f.title }}</span>
                        </div>
                        <div v-if="pagesForLine(item.id, li).length === 0" class="px-2.5 py-2 text-xs text-slate-400">No results</div>
                      </div>
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

                <button v-if="auth.isAdmin" @click="removeItem(item.id)" class="mt-1 flex items-center justify-center rounded-lg p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600">
                  <TrashIcon class="h-4 w-4" />
                </button>
              </div>
            </div>
          </div><!-- end overflow-x-auto -->

          <div v-if="auth.isAdmin" class="border-t border-slate-100 px-5 py-3">
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
                v-if="auth.isAdmin"
                @click="removeScenarioGroup(group.id)"
                class="mt-1 flex items-center justify-center rounded p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600"
                title="Delete group"
              >
                <TrashIcon class="h-4 w-4" />
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
                    v-if="auth.isAdmin"
                    @click="removeScenarioRow(group, row.id)"
                    class="mt-1 flex items-center justify-center rounded p-1 text-slate-400 hover:bg-rose-50 hover:text-rose-600"
                  >
                    <TrashIcon class="h-3.5 w-3.5" />
                  </button>
                </div>
              </div>
            </div>

            <!-- Add row -->
            <button
              v-if="auth.isAdmin"
              @click="addScenarioRow(group)"
              class="flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-500 shadow-sm transition-colors hover:bg-slate-50"
            >
              <Plus class="h-3.5 w-3.5" />
              Add Row
            </button>
          </div>
        </div>

        <!-- Add group -->
        <div v-if="auth.isAdmin" class="border-t border-slate-100 px-4 py-3">
          <button
            @click="addScenarioGroup"
            class="flex items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-600 shadow-sm transition-colors hover:bg-slate-50"
          >
            <Plus class="h-3.5 w-3.5" />
            Add Scenario Group
          </button>
        </div>
      </article>

      <div v-if="auth.isAdmin" class="flex items-center gap-3">
        <button class="flex items-center gap-2 rounded-lg bg-slate-900 px-5 py-2.5 text-sm font-medium text-white shadow-sm transition-colors hover:bg-slate-800" @click="save">
          <Save class="h-4 w-4" />
          {{ isEdit ? 'Update Frontend' : 'Create Frontend' }}
        </button>
        <button class="flex items-center gap-2 rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-600 shadow-sm transition-colors hover:bg-slate-50" @click="router.push('/admin/rtmf/frontends')">
          <X class="h-4 w-4" />
          Cancel
        </button>
        <button v-if="isEdit" class="ml-auto flex items-center gap-2 rounded-lg border border-rose-200 px-5 py-2.5 text-sm font-medium text-rose-600 shadow-sm transition-colors hover:bg-rose-50" @click="remove">
          <Trash2 class="h-4 w-4" />
          Delete
        </button>
      </div>
    </div>
  </AdminLayout>

  <!-- Relation diagram modal -->
  <Teleport to="body">
    <div
      v-if="showDiagram"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
      @click.self="showDiagram = false"
    >
      <div class="w-full max-w-2xl rounded-xl border border-slate-200 bg-white shadow-2xl">
        <div class="flex items-center gap-2 border-b border-slate-100 px-5 py-3">
          <Network class="h-4 w-4 text-violet-600" />
          <h2 class="text-sm font-semibold text-slate-900">Relation Diagram</h2>
          <span class="ml-2 text-xs text-slate-400">{{ specId }}</span>
          <button
            type="button"
            @click="showDiagram = false"
            class="ml-auto flex items-center justify-center rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
          >
            <X class="h-4 w-4" />
          </button>
        </div>
        <div class="overflow-auto p-4">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="560"
            :height="diagram.svgH"
            :viewBox="`0 0 560 ${diagram.svgH}`"
            class="mx-auto block"
          >
            <!-- Edges: from nodes → current -->
            <path
              v-for="n in diagram.fromNodes"
              :key="`edge-from-${n.id}`"
              :d="`M ${n.x + diagram.nodeW} ${n.y + diagram.nodeH / 2}
                   C ${(n.x + diagram.nodeW + diagram.curX) / 2} ${n.y + diagram.nodeH / 2},
                     ${(n.x + diagram.nodeW + diagram.curX) / 2} ${diagram.curY + diagram.nodeH / 2},
                     ${diagram.curX} ${diagram.curY + diagram.nodeH / 2}`"
              fill="none"
              stroke="#a78bfa"
              stroke-width="1.5"
              marker-end="url(#arrow-violet)"
            />
            <!-- Edges: current → to nodes -->
            <path
              v-for="n in diagram.toNodes"
              :key="`edge-to-${n.id}`"
              :d="`M ${diagram.curX + diagram.nodeW} ${diagram.curY + diagram.nodeH / 2}
                   C ${(diagram.curX + diagram.nodeW + n.x) / 2} ${diagram.curY + diagram.nodeH / 2},
                     ${(diagram.curX + diagram.nodeW + n.x) / 2} ${n.y + diagram.nodeH / 2},
                     ${n.x} ${n.y + diagram.nodeH / 2}`"
              fill="none"
              stroke="#2dd4bf"
              stroke-width="1.5"
              marker-end="url(#arrow-teal)"
            />

            <!-- From nodes -->
            <a
              v-for="n in diagram.fromNodes"
              :key="`node-from-${n.id}`"
              :href="`/admin/rtmf/frontends/${n.id}`"
              target="_blank"
            >
              <rect
                :x="n.x" :y="n.y"
                :width="diagram.nodeW" :height="diagram.nodeH"
                rx="6"
                fill="#ede9fe"
                stroke="#a78bfa"
                stroke-width="1.5"
                class="cursor-pointer hover:fill-violet-200"
              />
              <text
                :x="n.x + diagram.nodeW / 2"
                :y="n.y + 20"
                text-anchor="middle"
                font-size="13"
                font-family="monospace"
                fill="#6d28d9"
                font-weight="600"
              >{{ n.specId }}</text>
              <text
                :x="n.x + diagram.nodeW / 2"
                :y="n.y + 39"
                text-anchor="middle"
                font-size="12"
                font-family="sans-serif"
                fill="#7c3aed"
              >
                <tspan>{{ n.title.length > 20 ? n.title.slice(0, 18) + '…' : n.title }}</tspan>
              </text>
            </a>

            <!-- Current node -->
            <rect
              :x="diagram.curX" :y="diagram.curY"
              :width="diagram.nodeW" :height="diagram.nodeH"
              rx="6"
              fill="#1e293b"
              stroke="#334155"
              stroke-width="1.5"
            />
            <text
              :x="diagram.curX + diagram.nodeW / 2"
              :y="diagram.curY + 20"
              text-anchor="middle"
              font-size="13"
              font-family="monospace"
              fill="#e2e8f0"
              font-weight="600"
            >{{ specId }}</text>
            <text
              :x="diagram.curX + diagram.nodeW / 2"
              :y="diagram.curY + 39"
              text-anchor="middle"
              font-size="12"
              font-family="sans-serif"
              fill="#94a3b8"
            >
              <tspan>{{ title.length > 20 ? title.slice(0, 18) + '…' : title }}</tspan>
            </text>

            <!-- To nodes -->
            <a
              v-for="n in diagram.toNodes"
              :key="`node-to-${n.id}`"
              :href="`/admin/rtmf/frontends/${n.id}`"
              target="_blank"
            >
              <rect
                :x="n.x" :y="n.y"
                :width="diagram.nodeW" :height="diagram.nodeH"
                rx="6"
                fill="#ccfbf1"
                stroke="#2dd4bf"
                stroke-width="1.5"
                class="cursor-pointer hover:fill-teal-200"
              />
              <text
                :x="n.x + diagram.nodeW / 2"
                :y="n.y + 20"
                text-anchor="middle"
                font-size="13"
                font-family="monospace"
                fill="#0f766e"
                font-weight="600"
              >{{ n.specId }}</text>
              <text
                :x="n.x + diagram.nodeW / 2"
                :y="n.y + 39"
                text-anchor="middle"
                font-size="12"
                font-family="sans-serif"
                fill="#0d9488"
              >
                <tspan>{{ n.title.length > 20 ? n.title.slice(0, 18) + '…' : n.title }}</tspan>
              </text>
            </a>

            <!-- Arrow marker definitions -->
            <defs>
              <marker id="arrow-violet" markerWidth="8" markerHeight="8" refX="6" refY="3" orient="auto">
                <path d="M0,0 L0,6 L8,3 z" fill="#a78bfa" />
              </marker>
              <marker id="arrow-teal" markerWidth="8" markerHeight="8" refX="6" refY="3" orient="auto">
                <path d="M0,0 L0,6 L8,3 z" fill="#2dd4bf" />
              </marker>
            </defs>
          </svg>

          <div class="mt-3 flex items-center justify-center gap-6 text-xs text-slate-500">
            <span class="flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-sm bg-violet-200 ring-1 ring-violet-400"></span> From (navigates here)</span>
            <span class="flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-sm bg-slate-800 ring-1 ring-slate-600"></span> This page</span>
            <span class="flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-sm bg-teal-100 ring-1 ring-teal-400"></span> Go To (navigated from here)</span>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>
