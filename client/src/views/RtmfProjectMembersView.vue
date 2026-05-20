<script setup lang="ts">
import { computed, onMounted, ref, onBeforeUnmount } from "vue";
import { RouterLink, useRoute } from "vue-router";
import { UserPlus, Trash2, Users, Search, X, Pencil, Check } from "lucide-vue-next";
import AdminLayout from "@/layouts/AdminLayout.vue";
import {
  getRtmfProject,
  listRtmfProjectMembers,
  listRtmfProjectCandidates,
  addRtmfProjectMember,
  updateRtmfProjectMember,
  removeRtmfProjectMember,
} from "@/api/rtmf";
import { useToast } from "@/composables/useToast";
import { useConfirmDialog } from "@/composables/useConfirmDialog";
import type { MemberCandidate, RtmfProject, RtmfProjectMember } from "@/types";

const SYSTEM_TO_PROJECT_ROLE: Record<string, string> = {
  admin: "admin",
  ba: "business_analyst",
  qa: "qa",
  technical: "technical",
  developer: "developer",
  viewer: "viewer",
};

function suggestProjectRole(systemRole: string): string {
  return SYSTEM_TO_PROJECT_ROLE[systemRole?.toLowerCase()] ?? "viewer";
}

const ROLES = [
  { value: "admin",            label: "Admin" },
  { value: "business_analyst", label: "BA" },
  { value: "qa",               label: "QA" },
  { value: "technical",        label: "Technical" },
  { value: "developer",        label: "Developer" },
  { value: "viewer",           label: "Viewer" },
];

const ROLE_COLORS: Record<string, string> = {
  admin:            "bg-violet-100 text-violet-700",
  business_analyst: "bg-blue-100 text-blue-700",
  qa:               "bg-amber-100 text-amber-700",
  technical:        "bg-teal-100 text-teal-700",
  developer:        "bg-green-100 text-green-700",
  viewer:           "bg-slate-100 text-slate-600",
};

const route = useRoute();
const toast = useToast();
const { confirm } = useConfirmDialog();

const projectId = Number(route.params.id);
const project = ref<RtmfProject | null>(null);
const members = ref<RtmfProjectMember[]>([]);
const allCandidates = ref<MemberCandidate[]>([]);
const loadError = ref<string | null>(null);
const adding = ref(false);
const editingRoleId = ref<number | null>(null);
const editingRole = ref("");

// Add member form state
const searchQ = ref("");
const showDropdown = ref(false);
const selectedUser = ref<MemberCandidate | null>(null);
const addRole = ref("viewer");
const searchInputRef = ref<HTMLInputElement | null>(null);

const dropdownCandidates = computed(() => {
  const q = searchQ.value.trim().toLowerCase();
  if (!q) return [];
  return allCandidates.value
    .filter((u) => u.name.toLowerCase().includes(q) || u.email.toLowerCase().includes(q))
    .slice(0, 8);
});

function selectUser(u: MemberCandidate) {
  selectedUser.value = u;
  searchQ.value = u.name;
  addRole.value = suggestProjectRole(u.role);
  showDropdown.value = false;
}

function clearSelection() {
  selectedUser.value = null;
  searchQ.value = "";
  addRole.value = "viewer";
  showDropdown.value = false;
  searchInputRef.value?.focus();
}

function onSearchInput() {
  selectedUser.value = null;
  showDropdown.value = searchQ.value.trim().length > 0;
}

function onClickOutside(e: MouseEvent) {
  const el = (e.target as HTMLElement).closest("[data-add-member]");
  if (!el) showDropdown.value = false;
}

onMounted(() => document.addEventListener("mousedown", onClickOutside));
onBeforeUnmount(() => document.removeEventListener("mousedown", onClickOutside));

async function loadMembers() {
  try {
    const res = await listRtmfProjectMembers(projectId);
    members.value = res.data;
  } catch {
    toast.error("Failed to load members");
  }
}

async function loadCandidates() {
  try {
    const res = await listRtmfProjectCandidates(projectId);
    allCandidates.value = res.data;
  } catch {
    toast.error("Failed to fetch users");
  }
}

onMounted(async () => {
  try {
    const res = await getRtmfProject(projectId);
    project.value = res.data;
  } catch {
    loadError.value = "Project not found.";
    return;
  }
  await Promise.all([loadMembers(), loadCandidates()]);
});

async function confirmAdd() {
  if (!selectedUser.value) return;
  const user = selectedUser.value;
  adding.value = true;
  try {
    await addRtmfProjectMember(projectId, { userId: user.id, projectRole: addRole.value });
    await Promise.all([loadMembers(), loadCandidates()]);

    toast.success(`${user.name} added as ${roleLabelFor(addRole.value)}`);
    clearSelection();
  } catch {
    toast.error("Failed to add member");
  } finally {
    adding.value = false;
  }
}

function startEditRole(member: RtmfProjectMember) {
  editingRoleId.value = member.id;
  editingRole.value = member.projectRole ?? "viewer";
}

async function saveRole(member: RtmfProjectMember) {
  try {
    await updateRtmfProjectMember(projectId, member.id, { projectRole: editingRole.value });
    const m = members.value.find((m) => m.id === member.id);
    if (m) m.projectRole = editingRole.value;
    toast.success(`${member.name}'s role updated`);
  } catch {
    toast.error("Failed to update role");
  } finally {
    editingRoleId.value = null;
  }
}

async function remove(member: RtmfProjectMember) {
  const accepted = await confirm({
    title: "Remove member?",
    message: `Remove ${member.name} from this project? They will no longer see it in the project picker.`,
    destructive: true,
  });
  if (!accepted) return;
  try {
    await removeRtmfProjectMember(projectId, member.id);
    members.value = members.value.filter((m) => m.id !== member.id);
    toast.success(`${member.name} removed`);
  } catch {
    toast.error("Failed to remove member");
  }
}

function roleLabelFor(value: string) {
  return ROLES.find((r) => r.value === value)?.label ?? value;
}

function roleColorFor(value: string) {
  return ROLE_COLORS[value] ?? "bg-slate-100 text-slate-600";
}
</script>

<template>
  <AdminLayout>
    <div class="mx-auto max-w-7xl space-y-4">
      <div v-if="loadError" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        {{ loadError }}
      </div>

      <!-- Header -->
      <div>
        <nav class="mb-1 flex items-center gap-1.5 text-xs font-medium text-slate-500">
          <RouterLink to="/admin/rtmf/projects" class="transition-colors hover:text-violet-600">Projects</RouterLink>
          <span class="text-slate-300">/</span>
          <span class="text-slate-700">{{ project?.name ?? '…' }}</span>
          <span class="text-slate-300">/</span>
          <span class="text-slate-700">Members</span>
        </nav>
        <h1 class="page-title">{{ project?.name ?? '…' }} — Members</h1>
        <p class="mt-1 text-sm text-slate-500">Manage who can access this project and what role they have.</p>
      </div>

      <!-- Add member bar -->
      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
          <UserPlus class="h-4 w-4 text-violet-600" />
          <h2 class="text-sm font-semibold text-slate-900">Add Member</h2>
        </div>
        <div class="flex items-center gap-2 p-3" data-add-member>
          <!-- Search with dropdown -->
          <div class="relative flex-1">
            <Search class="absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400 pointer-events-none" />
            <input
              ref="searchInputRef"
              v-model="searchQ"
              type="text"
              placeholder="Search by name or email…"
              class="w-full rounded-lg border border-slate-200 py-1.5 pl-8 pr-8 text-sm focus:outline-none focus:ring-2 focus:ring-violet-400"
              :class="{ 'border-violet-300 ring-2 ring-violet-100': selectedUser }"
              @input="onSearchInput"
              @focus="showDropdown = searchQ.trim().length > 0 && !selectedUser"
            />
            <button
              v-if="searchQ"
              class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-300 hover:text-slate-500"
              @click="clearSelection"
            >
              <X class="h-3.5 w-3.5" />
            </button>

            <!-- Dropdown results -->
            <ul
              v-if="showDropdown"
              class="absolute left-0 right-0 top-full z-20 mt-1 max-h-56 overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg"
            >
              <li
                v-for="u in dropdownCandidates"
                :key="u.id"
                class="flex cursor-pointer items-center gap-2.5 px-3 py-2 hover:bg-violet-50"
                @mousedown.prevent="selectUser(u)"
              >
                <div class="h-7 w-7 flex-shrink-0">
                  <img v-if="u.photoUrl" :src="u.photoUrl" class="h-7 w-7 rounded-full object-cover" />
                  <div v-else class="flex h-7 w-7 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600">
                    {{ u.name.charAt(0).toUpperCase() }}
                  </div>
                </div>
                <div class="min-w-0 flex-1">
                  <p class="truncate text-sm font-medium text-slate-800">{{ u.name }}</p>
                  <p class="truncate text-xs text-slate-400">{{ u.email }}</p>
                </div>
              </li>
              <li v-if="dropdownCandidates.length === 0" class="px-3 py-3 text-center text-xs text-slate-400">
                No matching users found.
              </li>
            </ul>
          </div>

          <!-- Role dropdown -->
          <select
            v-model="addRole"
            class="rounded-lg border border-slate-200 py-1.5 pl-2.5 pr-6 text-sm focus:outline-none focus:ring-2 focus:ring-violet-400"
          >
            <option v-for="r in ROLES" :key="r.value" :value="r.value">{{ r.label }}</option>
          </select>

          <!-- Add button -->
          <button
            :disabled="!selectedUser || adding"
            class="flex items-center gap-1.5 rounded-lg bg-violet-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-40"
            @click="confirmAdd"
          >
            <UserPlus class="h-3.5 w-3.5" />
            Add
          </button>
        </div>

        <!-- Selected user preview -->
        <div v-if="selectedUser" class="flex items-center gap-2.5 border-t border-slate-100 bg-violet-50 px-4 py-2">
          <div class="h-6 w-6 flex-shrink-0">
            <img v-if="selectedUser.photoUrl" :src="selectedUser.photoUrl" class="h-6 w-6 rounded-full object-cover" />
            <div v-else class="flex h-6 w-6 items-center justify-center rounded-full bg-violet-200 text-[10px] font-semibold text-violet-700">
              {{ selectedUser.name.charAt(0).toUpperCase() }}
            </div>
          </div>
          <span class="text-xs text-slate-700">
            <span class="font-medium">{{ selectedUser.name }}</span>
            <span class="text-slate-400"> · {{ selectedUser.email }}</span>
          </span>
          <span :class="['ml-auto rounded-full px-2 py-0.5 text-[11px] font-medium', roleColorFor(addRole)]">
            {{ roleLabelFor(addRole) }}
          </span>
        </div>
      </article>

      <!-- Current members -->
      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
          <Users class="h-4 w-4 text-violet-600" />
          <h2 class="text-sm font-semibold text-slate-900">Current Members</h2>
          <span class="ml-1 text-xs text-slate-500">{{ members.length }}</span>
        </div>
        <ul class="divide-y divide-slate-100">
          <li v-for="m in members" :key="m.id" class="flex items-center gap-3 px-4 py-2.5">
            <div class="h-8 w-8 flex-shrink-0">
              <img v-if="m.photoUrl" :src="m.photoUrl" class="h-8 w-8 rounded-full object-cover" />
              <div v-else class="flex h-8 w-8 items-center justify-center rounded-full bg-violet-100 text-xs font-semibold text-violet-700">
                {{ m.name.charAt(0).toUpperCase() }}
              </div>
            </div>
            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-medium text-slate-800">{{ m.name }}</p>
              <p class="truncate text-xs text-slate-500">{{ m.email }}</p>
            </div>

            <!-- Role editing -->
            <template v-if="editingRoleId === m.id">
              <select
                v-model="editingRole"
                class="rounded border border-violet-300 bg-white py-0.5 pl-2 pr-6 text-xs text-slate-700 focus:outline-none focus:ring-2 focus:ring-violet-400"
              >
                <option v-for="r in ROLES" :key="r.value" :value="r.value">{{ r.label }}</option>
              </select>
              <button class="rounded p-1 text-violet-600 hover:bg-violet-50" title="Save" @click="saveRole(m)">
                <Check class="h-4 w-4" />
              </button>
              <button class="rounded p-1 text-slate-300 hover:bg-slate-100 hover:text-slate-500" title="Cancel" @click="editingRoleId = null">
                <X class="h-4 w-4" />
              </button>
            </template>

            <!-- Role badge + actions -->
            <template v-else>
              <span :class="['rounded-full px-2 py-0.5 text-[11px] font-medium', roleColorFor(m.projectRole ?? 'viewer')]">
                {{ roleLabelFor(m.projectRole ?? 'viewer') }}
              </span>
              <button class="rounded p-1 text-slate-300 hover:bg-slate-100 hover:text-slate-600" title="Change role" @click="startEditRole(m)">
                <Pencil class="h-3.5 w-3.5" />
              </button>
              <button class="rounded p-1 text-slate-300 hover:bg-red-50 hover:text-red-500" title="Remove" @click="remove(m)">
                <Trash2 class="h-4 w-4" />
              </button>
            </template>
          </li>
          <li v-if="members.length === 0" class="px-4 py-6 text-center text-sm text-slate-400">No members yet.</li>
        </ul>
      </article>
    </div>
  </AdminLayout>
</template>
