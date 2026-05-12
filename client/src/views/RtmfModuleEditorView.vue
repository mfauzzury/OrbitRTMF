<script setup lang="ts">
import { computed, onMounted, onUnmounted, reactive, ref } from "vue";
import { RouterLink, useRoute, useRouter } from "vue-router";
import { ChevronDown, ChevronRight, Eye, File, Folder, GripVertical, ImagePlus, LayoutGrid, Pencil, Plus, Save, Trash2, X } from "lucide-vue-next";
import AdminLayout from "@/layouts/AdminLayout.vue";
import {
  createRtmfModule,
  createRtmfSubModule,
  deleteModulePhoto,
  deleteRtmfModule,
  deleteRtmfSubModule,
  deleteSubModulePhoto,
  getRtmfModule,
  listModulePhotos,
  listRtmfSubModules,
  listSubModulePhotos,
  reorderSubModules,
  updateRtmfModule,
  updateRtmfSubModule,
  uploadModulePhoto,
  uploadSubModulePhoto,
} from "@/api/rtmf";
import type { RtmfModulePhoto, RtmfSubModule, RtmfSubModulePhoto } from "@/types";
import { useConfirmDialog } from "@/composables/useConfirmDialog";
import { useToast } from "@/composables/useToast";

const route = useRoute();
const router = useRouter();
const toast = useToast();
const confirmDialog = useConfirmDialog();

const id = computed(() => Number(route.params.id || 0));
const isEdit = computed(() => id.value > 0);

const code = ref("");
const name = ref("");
const description = ref("");
const sortOrder = ref(0);
const frontendsCount = ref(0);

// ── Sub-modules (flat list; tree built via computed) ──
const subModules = ref<RtmfSubModule[]>([]);
const editingId = ref<number | null>(null); // null=none, 0=new row, >0=editing
const expandedIds = ref<number[]>([]); // plain array — reliable Vue 3 reactivity
// module-level photos
const modulePhotos = ref<RtmfModulePhoto[]>([]);
const modulePhotoUploading = ref(false);
const modulePhotoInputRef = ref<HTMLInputElement | null>(null);

// sub-module photos
const subPhotos = ref<RtmfSubModulePhoto[]>([]);
const photoUploading = ref(false);
const photoInputRef = ref<HTMLInputElement | null>(null);

type ModalPhoto = { url: string; originalName: string };
const modalPhoto = ref<ModalPhoto | null>(null);

const dragSourceId = ref<number | null>(null);
const dragOverId = ref<number | null>(null);

const draft = reactive({
  code: "",
  name: "",
  description: "",
  parentId: null as number | null,
});

type SubModuleTreeNode = RtmfSubModule & { children: SubModuleTreeNode[] };
interface DisplayItem {
  node: SubModuleTreeNode;
  depth: number;
  guides: boolean[];  // guides[i] = true → draw vertical line at ancestor depth i
  isLast: boolean;    // last among its siblings (→ elbow, not tee)
}

const treeRoots = computed((): SubModuleTreeNode[] => {
  const map = new Map<number, SubModuleTreeNode>();
  for (const item of subModules.value) {
    map.set(item.id, { ...item, children: [] });
  }
  const roots: SubModuleTreeNode[] = [];
  for (const item of subModules.value) {
    const node = map.get(item.id)!;
    if (!item.parentId) {
      roots.push(node);
    } else {
      const parent = map.get(item.parentId);
      if (parent) parent.children.push(node);
      else roots.push(node); // orphan → treat as root
    }
  }
  return roots;
});

const treeDisplayItems = computed((): DisplayItem[] => {
  function walk(nodes: SubModuleTreeNode[], depth: number, guides: boolean[]): DisplayItem[] {
    const result: DisplayItem[] = [];
    const sorted = [...nodes].sort((a, b) => (a.sort_order ?? a.sortOrder ?? 0) - (b.sort_order ?? b.sortOrder ?? 0));
    for (let i = 0; i < sorted.length; i++) {
      const node = sorted[i];
      const isLast = i === sorted.length - 1;
      result.push({ node, depth, guides: [...guides], isLast });
      if (node.children.length > 0 && expandedIds.value.includes(node.id)) {
        result.push(...walk(node.children, depth + 1, [...guides, !isLast]));
      }
    }
    return result;
  }
  return walk(treeRoots.value, 0, []);
});

function isExpanded(nodeId: number) {
  return expandedIds.value.includes(nodeId);
}

function toggleExpand(nodeId: number) {
  if (expandedIds.value.includes(nodeId)) {
    expandedIds.value = expandedIds.value.filter(x => x !== nodeId);
  } else {
    expandedIds.value = [...expandedIds.value, nodeId];
  }
}

function getNodeById(nodeId: number): RtmfSubModule | undefined {
  return subModules.value.find(s => s.id === nodeId);
}

function resetDraft(seed?: Partial<RtmfSubModule> & { parentId?: number | null }) {
  draft.code = seed?.code ?? "";
  draft.name = seed?.name ?? "";
  draft.description = seed?.description ?? "";
  draft.parentId = seed?.parentId ?? null;
}

async function loadSubModules() {
  if (!isEdit.value) return;
  const r = await listRtmfSubModules(id.value);
  subModules.value = r.data;
  const withKids = r.data
    .filter(s => r.data.some(c => c.parentId === s.id))
    .map(s => s.id);
  expandedIds.value = [...new Set([...expandedIds.value, ...withKids])];
}

function startAdd(parentId: number | null = null) {
  editingId.value = 0;
  resetDraft({ parentId });
  subPhotos.value = [];
  if (parentId !== null && !expandedIds.value.includes(parentId)) {
    expandedIds.value = [...expandedIds.value, parentId];
  }
}

function startEdit(sub: RtmfSubModule) {
  editingId.value = sub.id;
  resetDraft({ ...sub, parentId: sub.parentId ?? null });
  loadSubPhotos(sub.id);
}

function cancelEdit() {
  editingId.value = null;
  resetDraft();
  subPhotos.value = [];
}

async function loadSubPhotos(subId: number) {
  const r = await listSubModulePhotos(id.value, subId);
  subPhotos.value = r.data;
}

async function uploadPhotos(event: Event) {
  if (!editingId.value) return;
  const input = event.target as HTMLInputElement;
  const files = Array.from(input.files ?? []);
  if (!files.length) return;
  photoUploading.value = true;
  try {
    for (const file of files) {
      await uploadSubModulePhoto(id.value, editingId.value, file);
    }
    await loadSubPhotos(editingId.value);
  } catch (e) {
    toast.error("Upload failed", e instanceof Error ? e.message : "");
  } finally {
    photoUploading.value = false;
    input.value = "";
  }
}

async function removePhoto(photoId: number) {
  if (!editingId.value) return;
  try {
    await deleteSubModulePhoto(id.value, editingId.value, photoId);
    await loadSubPhotos(editingId.value);
  } catch (e) {
    toast.error("Delete failed", e instanceof Error ? e.message : "");
  }
}

function openPhotoModal(photo: RtmfSubModulePhoto) {
  modalPhoto.value = photo;
}

function closeModal() {
  modalPhoto.value = null;
}

function onKeydown(e: KeyboardEvent) {
  if (e.key === "Escape") closeModal();
}

// ── Drag-to-reorder ──
function onDragStart(_event: DragEvent, nodeId: number) {
  dragSourceId.value = nodeId;
}

function onDragOver(event: DragEvent, nodeId: number) {
  event.preventDefault();
  if (!dragSourceId.value || dragSourceId.value === nodeId) return;
  const source = subModules.value.find(s => s.id === dragSourceId.value);
  const target = subModules.value.find(s => s.id === nodeId);
  if (source && target && (source.parentId ?? null) === (target.parentId ?? null)) {
    dragOverId.value = nodeId;
  } else {
    dragOverId.value = null;
  }
}

function onDragLeave() {
  dragOverId.value = null;
}

function onDragEnd() {
  dragSourceId.value = null;
  dragOverId.value = null;
}

async function onDrop(event: DragEvent, targetNode: RtmfSubModule) {
  event.preventDefault();
  const sourceId = dragSourceId.value;
  dragSourceId.value = null;
  dragOverId.value = null;

  if (!sourceId || sourceId === targetNode.id) return;

  const source = subModules.value.find(s => s.id === sourceId);
  if (!source || (source.parentId ?? null) !== (targetNode.parentId ?? null)) return;

  const siblings = subModules.value
    .filter(s => (s.parentId ?? null) === (source.parentId ?? null))
    .sort((a, b) => (a.sortOrder ?? 0) - (b.sortOrder ?? 0));

  const withoutSource = siblings.filter(s => s.id !== sourceId);
  const targetIdx = withoutSource.findIndex(s => s.id === targetNode.id);
  const newOrder = [
    ...withoutSource.slice(0, targetIdx),
    source,
    ...withoutSource.slice(targetIdx),
  ];

  try {
    await reorderSubModules(id.value, newOrder.map(s => s.id));
    await loadSubModules();
  } catch (e) {
    toast.error("Reorder failed", e instanceof Error ? e.message : "");
  }
}

async function saveSub() {
  if (!isEdit.value) {
    toast.error("Save module first", "Create the module before adding sub-modules.");
    return;
  }
  const payload = {
    code: draft.code.trim(),
    name: draft.name.trim(),
    description: draft.description.trim() || null,
    parent_id: draft.parentId,
  };
  if (!payload.code || !payload.name) {
    toast.error("Missing fields", "Code and Name are required.");
    return;
  }
  try {
    if (editingId.value === 0) {
      const r = await createRtmfSubModule(id.value, payload);
      toast.success("Sub-module added — you can now attach photos below.");
      await loadSubModules();
      editingId.value = r.data.id;
      resetDraft({ ...r.data, parentId: r.data.parentId ?? null });
      await loadSubPhotos(r.data.id);
    } else if (editingId.value) {
      await updateRtmfSubModule(id.value, editingId.value, payload);
      toast.success("Sub-module updated");
      cancelEdit();
      await loadSubModules();
    }
  } catch (e) {
    toast.error("Save failed", e instanceof Error ? e.message : "");
  }
}

async function removeSub(sub: RtmfSubModule) {
  const hasChildren = subModules.value.some(s => s.parentId === sub.id);
  const allowed = await confirmDialog.confirm({
    title: "Delete sub-module?",
    message: hasChildren
      ? `Remove "${sub.code}"? Its children will become root-level items.`
      : `Remove "${sub.code}"?`,
    confirmText: "Delete",
    destructive: true,
  });
  if (!allowed) return;
  try {
    await deleteRtmfSubModule(id.value, sub.id);
    toast.success("Sub-module deleted");
    await loadSubModules();
  } catch (e) {
    toast.error("Delete failed", e instanceof Error ? e.message : "");
  }
}

// ── Module ──
async function load() {
  if (!isEdit.value) return;
  const r = await getRtmfModule(id.value);
  const d = r.data as unknown as Record<string, unknown>;
  code.value = r.data.code;
  name.value = r.data.name;
  description.value = r.data.description || "";
  sortOrder.value = (d.sort_order as number) ?? 0;
  frontendsCount.value = (d.frontends_count as number) ?? 0;
  await Promise.all([loadSubModules(), loadModulePhotos()]);
}

async function loadModulePhotos() {
  if (!isEdit.value) return;
  const r = await listModulePhotos(id.value);
  modulePhotos.value = r.data;
}

async function uploadModulePhotos(event: Event) {
  const input = event.target as HTMLInputElement;
  const files = Array.from(input.files ?? []);
  if (!files.length) return;
  modulePhotoUploading.value = true;
  try {
    for (const file of files) {
      await uploadModulePhoto(id.value, file);
    }
    await loadModulePhotos();
  } catch (e) {
    toast.error("Upload failed", e instanceof Error ? e.message : "");
  } finally {
    modulePhotoUploading.value = false;
    input.value = "";
  }
}

async function removeModulePhoto(photoId: number) {
  try {
    await deleteModulePhoto(id.value, photoId);
    await loadModulePhotos();
  } catch (e) {
    toast.error("Delete failed", e instanceof Error ? e.message : "");
  }
}

async function save() {
  const payload = {
    code: code.value.trim(),
    name: name.value.trim(),
    description: description.value.trim() || null,
    sortOrder: sortOrder.value,
  };
  try {
    if (isEdit.value) {
      await updateRtmfModule(id.value, payload);
      toast.success("Module updated");
    } else {
      const r = await createRtmfModule(payload);
      toast.success("Module created");
      router.push(`/admin/rtmf/modules/${r.data.id}`);
      return;
    }
    router.push("/admin/rtmf/modules");
  } catch (e) {
    toast.error("Save failed", e instanceof Error ? e.message : "");
  }
}

async function remove() {
  const allowed = await confirmDialog.confirm({
    title: "Delete module?",
    message: `Remove "${code.value}"?`,
    confirmText: "Delete",
    destructive: true,
  });
  if (!allowed) return;
  try {
    await deleteRtmfModule(id.value);
    toast.success("Module deleted");
    router.push("/admin/rtmf/modules");
  } catch (e) {
    toast.error("Delete failed", e instanceof Error ? e.message : "");
  }
}

onMounted(() => {
  load();
  window.addEventListener("keydown", onKeydown);
});

onUnmounted(() => {
  window.removeEventListener("keydown", onKeydown);
});
</script>

<template>
  <AdminLayout>
    <div class="mx-auto max-w-7xl space-y-4">
      <div>
        <nav class="mb-1 flex items-center gap-1.5 text-xs font-medium text-slate-500">
          <RouterLink to="/admin/rtmf/frontends" class="hover:text-violet-600 transition-colors">Page Catalog</RouterLink>
          <span class="text-slate-300">/</span>
          <RouterLink to="/admin/rtmf/modules" class="hover:text-violet-600 transition-colors">Modules</RouterLink>
          <span class="text-slate-300">/</span>
          <span class="text-slate-700">{{ isEdit ? 'Edit' : 'New' }}</span>
        </nav>
        <h1 class="page-title">{{ isEdit ? 'Edit Module' : 'New Module' }}</h1>
      </div>

      <!-- Module details -->
      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
          <LayoutGrid class="h-4 w-4 text-violet-600" />
          <h2 class="text-sm font-semibold text-slate-900">Module Details</h2>
          <span v-if="isEdit" class="ml-auto text-xs text-slate-500">{{ frontendsCount }} frontend entries</span>
        </div>
        <div class="grid gap-3 p-4 md:grid-cols-2">
          <div class="space-y-1.5">
            <label class="text-sm font-medium text-slate-700">Name</label>
            <input v-model="name" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200" placeholder="Profiling" />
          </div>
          <div class="space-y-1.5">
            <label class="text-sm font-medium text-slate-700">Code</label>
            <input v-model="code" class="w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm shadow-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200" placeholder="PRF" />
          </div>
          <div class="space-y-1.5 md:col-span-2">
            <label class="text-sm font-medium text-slate-700">Description</label>
            <textarea v-model="description" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200" />
          </div>
        </div>

        <!-- Module reference photos (edit mode only) -->
        <div v-if="isEdit" class="border-t border-slate-100 px-4 py-3">
          <p class="mb-2 text-xs font-medium text-slate-600">Reference Photos</p>
          <div class="flex flex-wrap gap-2">
            <div
              v-for="photo in modulePhotos"
              :key="photo.id"
              class="group relative h-16 w-16 flex-shrink-0 overflow-hidden rounded-md border border-slate-200 bg-slate-100"
            >
              <img
                :src="photo.url"
                :alt="photo.originalName"
                class="h-full w-full cursor-pointer object-cover"
                @click="openPhotoModal(photo)"
              />
              <div class="pointer-events-none absolute inset-0 flex items-center justify-center bg-black/30 opacity-0 transition-opacity group-hover:opacity-100">
                <Eye class="h-5 w-5 text-white drop-shadow" />
              </div>
              <button
                type="button"
                class="absolute right-0.5 top-0.5 flex h-5 w-5 items-center justify-center rounded bg-black/60 opacity-0 transition-opacity group-hover:opacity-100 hover:bg-rose-600"
                title="Delete photo"
                @click.stop="removeModulePhoto(photo.id)"
              >
                <Trash2 class="h-3 w-3 text-white" />
              </button>
            </div>

            <!-- Add button -->
            <button
              type="button"
              class="flex h-16 w-16 flex-shrink-0 items-center justify-center rounded-md border border-dashed border-slate-300 text-slate-400 hover:border-violet-400 hover:text-violet-500 disabled:opacity-50"
              :disabled="modulePhotoUploading"
              @click="modulePhotoInputRef?.click()"
            >
              <ImagePlus v-if="!modulePhotoUploading" class="h-5 w-5" />
              <span v-else class="text-xs">…</span>
            </button>
            <input
              ref="modulePhotoInputRef"
              type="file"
              accept="image/*"
              multiple
              class="hidden"
              @change="uploadModulePhotos"
            />
          </div>
        </div>
      </article>

      <!-- Sub-modules tree -->
      <article v-if="isEdit" class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
          <LayoutGrid class="h-4 w-4 text-violet-600" />
          <h2 class="text-sm font-semibold text-slate-900">Sub-modules</h2>
          <span class="ml-1 rounded-full bg-slate-100 px-1.5 py-0.5 text-xs font-medium text-slate-500">{{ subModules.length }}</span>
          <button
            v-if="editingId === null"
            type="button"
            class="ml-auto flex items-center gap-1.5 rounded-md bg-slate-900 px-2.5 py-1 text-xs font-medium text-white transition-colors hover:bg-slate-800"
            @click="startAdd(null)"
          >
            <Plus class="h-3.5 w-3.5" />
            Add Sub-module
          </button>
        </div>

        <!-- Tree -->
        <div class="divide-y divide-slate-50">
          <div
            v-for="item in treeDisplayItems"
            :key="item.node.id"
            draggable="true"
            class="group flex items-stretch transition-colors hover:bg-slate-50"
            :class="{
              'bg-violet-50/60 hover:bg-violet-50/60': editingId === item.node.id,
              'opacity-40': dragSourceId === item.node.id,
              'border-t-2 border-violet-400': dragOverId === item.node.id,
            }"
            @dragstart="onDragStart($event, item.node.id)"
            @dragover="onDragOver($event, item.node.id)"
            @dragleave="onDragLeave"
            @dragend="onDragEnd"
            @drop="onDrop($event, item.node)"
          >
            <!-- Base left spacer -->
            <div class="w-3 flex-shrink-0" />

            <!-- Ancestor guide cells: vertical continuation lines -->
            <div
              v-for="(hasLine, gi) in item.guides"
              :key="gi"
              class="relative w-5 flex-shrink-0"
            >
              <div
                v-if="hasLine"
                class="absolute bg-slate-200"
                style="width: 1px; left: 10px; top: 0; bottom: 0"
              />
            </div>

            <!-- Connector: elbow (└) or tee (├) -->
            <div v-if="item.depth > 0" class="relative w-5 flex-shrink-0">
              <!-- vertical: top to midpoint -->
              <div class="absolute bg-slate-200" style="width: 1px; left: 10px; top: 0; bottom: 50%" />
              <!-- vertical: midpoint to bottom (tee only — not last child) -->
              <div v-if="!item.isLast" class="absolute bg-slate-200" style="width: 1px; left: 10px; top: 50%; bottom: 0" />
              <!-- horizontal: midpoint across to content -->
              <div class="absolute bg-slate-200" style="height: 1px; left: 10px; right: 0; top: 50%; transform: translateY(-0.5px)" />
            </div>

            <!-- Row content -->
            <div class="flex min-w-0 flex-1 items-center gap-1.5 py-1.5 pr-3">
              <!-- Drag handle -->
              <GripVertical class="h-3.5 w-3.5 flex-shrink-0 cursor-grab text-slate-300 opacity-0 group-hover:opacity-100 active:cursor-grabbing" />
              <!-- Expand/collapse toggle -->
              <button
                v-if="item.node.children.length > 0"
                type="button"
                class="flex h-5 w-5 flex-shrink-0 items-center justify-center rounded text-slate-400 hover:text-slate-700"
                @click="toggleExpand(item.node.id)"
              >
                <ChevronDown v-if="isExpanded(item.node.id)" class="h-3.5 w-3.5" />
                <ChevronRight v-else class="h-3.5 w-3.5" />
              </button>
              <span v-else class="h-5 w-5 flex-shrink-0" />

              <!-- Node icon -->
              <Folder v-if="item.node.children.length > 0" class="h-4 w-4 flex-shrink-0 text-amber-400" />
              <File v-else class="h-4 w-4 flex-shrink-0 text-slate-300" />

              <!-- Code -->
              <span class="flex-shrink-0 font-mono text-xs text-slate-500">{{ item.node.code }}</span>

              <!-- Name -->
              <span class="min-w-0 flex-1 truncate text-sm text-slate-800">{{ item.node.name }}</span>

              <!-- Description -->
              <span class="hidden min-w-0 max-w-[200px] flex-shrink truncate text-xs text-slate-400 md:block">{{ item.node.description || '' }}</span>

              <!-- Child count badge -->
              <span v-if="item.node.children.length > 0" class="flex-shrink-0 rounded-full bg-amber-50 px-1.5 py-0.5 text-xs text-amber-600">
                {{ item.node.children.length }}
              </span>

              <!-- Actions (reveal on hover) -->
              <div
                class="flex flex-shrink-0 items-center gap-0.5 transition-opacity"
                :class="editingId === item.node.id ? 'opacity-100' : 'opacity-0 group-hover:opacity-100'"
              >
                <button
                  type="button"
                  class="flex h-6 w-6 items-center justify-center rounded text-slate-400 hover:bg-violet-100 hover:text-violet-600"
                  title="Add child"
                  @click="startAdd(item.node.id)"
                >
                  <Plus class="h-3 w-3" />
                </button>
                <button
                  type="button"
                  class="flex h-6 w-6 items-center justify-center rounded text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                  title="Edit"
                  @click="startEdit(item.node)"
                >
                  <Pencil class="h-3 w-3" />
                </button>
                <button
                  type="button"
                  class="flex h-6 w-6 items-center justify-center rounded text-slate-400 hover:bg-rose-50 hover:text-rose-600"
                  title="Delete"
                  @click="removeSub(item.node)"
                >
                  <Trash2 class="h-3 w-3" />
                </button>
              </div>
            </div>
          </div>

          <!-- Empty state -->
          <div v-if="subModules.length === 0 && editingId === null" class="px-4 py-8 text-center text-sm text-slate-400">
            No sub-modules yet. Click <span class="font-semibold text-slate-600">Add Sub-module</span> to create the first one.
          </div>
        </div>

        <!-- Edit / New form panel -->
        <div v-if="editingId !== null" class="border-t border-violet-100 bg-violet-50/30 px-4 py-3">
          <p class="mb-3 text-xs font-semibold text-slate-700">
            {{ editingId === 0 ? 'New Sub-module' : 'Edit Sub-module' }}
            <span v-if="editingId === 0 && draft.parentId !== null" class="font-normal text-slate-400">
              — child of
              <span class="font-medium text-slate-600">{{ getNodeById(draft.parentId)?.name }}</span>
            </span>
          </p>
          <div class="grid gap-3 md:grid-cols-2">
            <div class="space-y-1">
              <label class="text-xs font-medium text-slate-600">Code <span class="text-rose-400">*</span></label>
              <input
                v-model="draft.code"
                class="w-full rounded-md border border-slate-300 px-2.5 py-1.5 font-mono text-sm focus:border-violet-400 focus:outline-none focus:ring-2 focus:ring-violet-100"
                placeholder="PRF-AS-01"
              />
            </div>
            <div class="space-y-1">
              <label class="text-xs font-medium text-slate-600">Name <span class="text-rose-400">*</span></label>
              <input
                v-model="draft.name"
                class="w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-sm focus:border-violet-400 focus:outline-none focus:ring-2 focus:ring-violet-100"
                placeholder="Nama sub-modul"
              />
            </div>
            <div class="space-y-1 md:col-span-2">
              <label class="text-xs font-medium text-slate-600">Description</label>
              <input
                v-model="draft.description"
                class="w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-sm focus:border-violet-400 focus:outline-none focus:ring-2 focus:ring-violet-100"
              />
            </div>
          </div>
          <!-- Reference Photos -->
          <div class="mt-3 border-t border-violet-100 pt-3">
            <p class="mb-2 text-xs font-medium text-slate-600">Reference Photos</p>
            <p v-if="editingId === 0" class="text-xs italic text-slate-400">Save the sub-module first to attach photos.</p>
            <div v-else>
              <div v-if="subPhotos.length > 0" class="mb-2 flex flex-wrap gap-2">
                <div
                  v-for="photo in subPhotos"
                  :key="photo.id"
                  class="group relative h-16 w-16 flex-shrink-0 overflow-hidden rounded-md border border-slate-200 bg-slate-100"
                >
                  <img
                    :src="photo.url"
                    :alt="photo.originalName"
                    class="h-full w-full cursor-pointer object-cover"
                    @click="openPhotoModal(photo)"
                  />
                  <!-- View overlay (centre, shows on hover) -->
                  <div
                    class="pointer-events-none absolute inset-0 flex items-center justify-center bg-black/30 opacity-0 transition-opacity group-hover:opacity-100"
                  >
                    <Eye class="h-5 w-5 text-white drop-shadow" />
                  </div>
                  <!-- Delete button (top-right corner) -->
                  <button
                    type="button"
                    class="absolute right-0.5 top-0.5 flex h-5 w-5 items-center justify-center rounded bg-black/60 opacity-0 transition-opacity group-hover:opacity-100 hover:bg-rose-600"
                    title="Delete photo"
                    @click.stop="removePhoto(photo.id)"
                  >
                    <Trash2 class="h-3 w-3 text-white" />
                  </button>
                </div>
              </div>
              <button
                type="button"
                class="flex items-center gap-1.5 rounded-md border border-dashed border-slate-300 px-2.5 py-1.5 text-xs font-medium text-slate-500 hover:border-violet-400 hover:text-violet-600 disabled:opacity-50"
                :disabled="photoUploading"
                @click="photoInputRef?.click()"
              >
                <ImagePlus class="h-3.5 w-3.5" />
                {{ photoUploading ? 'Uploading…' : 'Add Photo' }}
              </button>
              <input
                ref="photoInputRef"
                type="file"
                accept="image/*"
                multiple
                class="hidden"
                @change="uploadPhotos"
              />
            </div>
          </div>

          <div class="mt-3 flex items-center gap-2">
            <button
              class="rounded-md bg-slate-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-slate-800"
              @click="saveSub"
            >
              Save
            </button>
            <button
              class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50"
              @click="cancelEdit"
            >
              Cancel
            </button>
          </div>
        </div>
      </article>

      <!-- Footer actions -->
      <div class="flex items-center gap-3">
        <button class="flex items-center gap-2 rounded-lg bg-slate-900 px-5 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-slate-800" @click="save">
          <Save class="h-4 w-4" />{{ isEdit ? 'Update' : 'Create' }}
        </button>
        <button class="flex items-center gap-2 rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-600 shadow-sm hover:bg-slate-50" @click="router.push('/admin/rtmf/modules')">
          <X class="h-4 w-4" />Cancel
        </button>
        <button v-if="isEdit" class="ml-auto flex items-center gap-2 rounded-lg border border-rose-200 px-5 py-2.5 text-sm font-medium text-rose-600 shadow-sm hover:bg-rose-50" @click="remove">
          <Trash2 class="h-4 w-4" />Delete
        </button>
      </div>
    </div>

    <!-- Photo modal -->
    <Teleport to="body">
      <div
        v-if="modalPhoto"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4"
        @click.self="closeModal"
      >
        <div class="relative flex max-h-full max-w-full flex-col items-center">
          <img
            :src="modalPhoto.url"
            :alt="modalPhoto.originalName"
            class="max-h-[80vh] max-w-[90vw] rounded-lg object-contain shadow-2xl"
          />
          <p class="mt-3 text-sm text-slate-300">{{ modalPhoto.originalName }}</p>
          <button
            type="button"
            class="absolute -right-3 -top-3 flex h-8 w-8 items-center justify-center rounded-full bg-white/10 text-white backdrop-blur hover:bg-white/20"
            @click="closeModal"
          >
            <X class="h-4 w-4" />
          </button>
        </div>
      </div>
    </Teleport>
  </AdminLayout>
</template>
