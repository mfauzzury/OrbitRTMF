<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import {
  Users,
  Plus,
  Pencil,
  Trash2,
  CheckCircle2,
  XCircle,
  Globe,
  ChevronLeft,
  ChevronRight,
} from "lucide-vue-next";

import AdminLayout from "@/layouts/AdminLayout.vue";
import { listUsers, deleteUser, listExternalUsers } from "@/api/cms";
import { useConfirmDialog } from "@/composables/useConfirmDialog";
import { useToast } from "@/composables/useToast";
import type { UserDetail, ExternalUser } from "@/types";

const PAGE_SIZE = 10;

const users = ref<UserDetail[]>([]);
const externalUsers = ref<ExternalUser[]>([]);
const usersPage = ref(1);
const extPage = ref(1);
const { confirm } = useConfirmDialog();
const toast = useToast();

const usersPageCount = computed(() => Math.max(1, Math.ceil(users.value.length / PAGE_SIZE)));
const extPageCount = computed(() => Math.max(1, Math.ceil(externalUsers.value.length / PAGE_SIZE)));

const pagedUsers = computed(() => {
  const start = (usersPage.value - 1) * PAGE_SIZE;
  return users.value.slice(start, start + PAGE_SIZE);
});

const pagedExtUsers = computed(() => {
  const start = (extPage.value - 1) * PAGE_SIZE;
  return externalUsers.value.slice(start, start + PAGE_SIZE);
});

async function load() {
  // Load independently: /api/external/users uses MySQL testagent — if it fails, local PostgreSQL
  // users must still appear (Promise.all would reject and leave both tables empty).
  const [localOutcome, extOutcome] = await Promise.allSettled([listUsers(), listExternalUsers()]);

  if (localOutcome.status === "fulfilled") {
    users.value = localOutcome.value.data;
  } else {
    users.value = [];
    toast.error(
      "Could not load users",
      localOutcome.reason instanceof Error ? localOutcome.reason.message : "Request failed",
    );
  }

  if (extOutcome.status === "fulfilled") {
    externalUsers.value = extOutcome.value.data;
  } else {
    externalUsers.value = [];
    toast.info(
      "External users not loaded",
      extOutcome.reason instanceof Error
        ? extOutcome.reason.message
        : "Optional MySQL directory unreachable — check EXTERNAL_DB_* if you need this list.",
    );
  }
}

async function remove(id: number) {
  const allowed = await confirm({
    title: "Delete user?",
    message: "This action cannot be undone.",
    confirmText: "Delete",
    destructive: true,
  });
  if (!allowed) return;
  try {
    await deleteUser(id);
    await load();
    toast.success("User deleted");
  } catch (e) {
    toast.error("Delete failed", e instanceof Error ? e.message : "Unable to delete user.");
  }
}

onMounted(load);
</script>

<template>
  <AdminLayout>
    <div class="mx-auto max-w-7xl space-y-4">
      <!-- ───── Hero Header ───── -->
      <div class="flex items-center justify-between">
        <h1 class="page-title">Users</h1>
        <router-link
          to="/admin/settings/users/new"
          class="flex items-center gap-2 rounded-lg bg-slate-900 px-4 py-1.5 text-sm font-medium text-white shadow-sm transition-colors hover:bg-slate-800"
        >
          <Plus class="h-4 w-4" />
          Add User
        </router-link>
      </div>

      <!-- ───── Users Table ───── -->
      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
          <Users class="h-4 w-4 text-blue-600" />
          <h2 class="text-sm font-semibold text-slate-900">All Users</h2>
          <span class="ml-1 text-xs text-slate-400">{{ users.length }}</span>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-slate-100 text-left">
                <th class="px-4 py-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Name</th>
                <th class="px-4 py-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Email</th>
                <th class="px-4 py-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Role</th>
                <th class="px-4 py-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                <th class="px-4 py-2 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="user in pagedUsers" :key="user.id" class="transition-colors hover:bg-slate-50">
                <td class="px-4 py-2">
                  <router-link :to="'/admin/settings/users/' + user.id" class="flex items-center gap-2.5 hover:text-violet-600">
                    <img v-if="user.photoUrl" :src="user.photoUrl" class="h-7 w-7 shrink-0 rounded-full object-cover" />
                    <div v-else class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600">
                      {{ user.name.charAt(0).toUpperCase() }}
                    </div>
                    <span class="font-medium text-slate-900">{{ user.name }}</span>
                  </router-link>
                </td>
                <td class="px-4 py-2 text-slate-500">{{ user.email }}</td>
                <td class="px-4 py-2">
                  <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">{{ user.role }}</span>
                </td>
                <td class="px-4 py-2">
                  <span v-if="user.isActive" class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-700">
                    <CheckCircle2 class="h-3 w-3" /> Active
                  </span>
                  <span v-else class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-500">
                    <XCircle class="h-3 w-3" /> Inactive
                  </span>
                </td>
                <td class="px-4 py-2 text-right">
                  <div class="flex items-center justify-end gap-1.5">
                    <router-link :to="'/admin/settings/users/' + user.id" class="group relative flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700">
                      <Pencil class="h-3.5 w-3.5" />
                      <span class="pointer-events-none absolute -top-8 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-md bg-slate-900 px-2 py-1 text-xs text-white opacity-0 shadow-lg transition-opacity group-hover:opacity-100">Edit</span>
                    </router-link>
                    <button class="group relative flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition-colors hover:bg-rose-50 hover:text-rose-600" @click="remove(user.id)">
                      <Trash2 class="h-3.5 w-3.5" />
                      <span class="pointer-events-none absolute -top-8 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-md bg-slate-900 px-2 py-1 text-xs text-white opacity-0 shadow-lg transition-opacity group-hover:opacity-100">Delete</span>
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="users.length === 0">
                <td colspan="5" class="px-4 py-6 text-center text-sm text-slate-400">No users found.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="usersPageCount > 1" class="flex items-center justify-between border-t border-slate-100 px-4 py-2.5">
          <button
            class="flex items-center gap-1 rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50 disabled:opacity-40"
            :disabled="usersPage <= 1"
            @click="usersPage--"
          >
            <ChevronLeft class="h-3.5 w-3.5" />
            Previous
          </button>
          <span class="text-sm text-slate-500">Page {{ usersPage }} of {{ usersPageCount }}</span>
          <button
            class="flex items-center gap-1 rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50 disabled:opacity-40"
            :disabled="usersPage >= usersPageCount"
            @click="usersPage++"
          >
            Next
            <ChevronRight class="h-3.5 w-3.5" />
          </button>
        </div>
      </article>

      <!-- ───── External Users Table ───── -->
      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
          <Globe class="h-4 w-4 text-amber-500" />
          <h2 class="text-sm font-semibold text-slate-900">External Users</h2>
          <span class="ml-1 text-xs text-slate-400">{{ externalUsers.length }}</span>
          <span class="ml-auto rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-700">Read-only</span>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-slate-100 text-left">
                <th class="px-4 py-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Name</th>
                <th class="px-4 py-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Email</th>
                <th class="px-4 py-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Role</th>
                <th class="px-4 py-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Source</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="user in pagedExtUsers" :key="user.id" class="transition-colors hover:bg-slate-50">
                <td class="px-4 py-2">
                  <div class="flex items-center gap-2.5">
                    <img v-if="user.photoUrl" :src="user.photoUrl" class="h-7 w-7 shrink-0 rounded-full object-cover" />
                    <div v-else class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-amber-100 text-xs font-semibold text-amber-700">
                      {{ user.name.charAt(0).toUpperCase() }}
                    </div>
                    <span class="font-medium text-slate-900">{{ user.name }}</span>
                  </div>
                </td>
                <td class="px-4 py-2 text-slate-500">{{ user.email }}</td>
                <td class="px-4 py-2">
                  <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">{{ user.role }}</span>
                </td>
                <td class="px-4 py-2">
                  <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-700">
                    <Globe class="h-3 w-3" /> External
                  </span>
                </td>
              </tr>
              <tr v-if="externalUsers.length === 0">
                <td colspan="4" class="px-4 py-6 text-center text-sm text-slate-400">No external users found.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="extPageCount > 1" class="flex items-center justify-between border-t border-slate-100 px-4 py-2.5">
          <button
            class="flex items-center gap-1 rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50 disabled:opacity-40"
            :disabled="extPage <= 1"
            @click="extPage--"
          >
            <ChevronLeft class="h-3.5 w-3.5" />
            Previous
          </button>
          <span class="text-sm text-slate-500">Page {{ extPage }} of {{ extPageCount }}</span>
          <button
            class="flex items-center gap-1 rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50 disabled:opacity-40"
            :disabled="extPage >= extPageCount"
            @click="extPage++"
          >
            Next
            <ChevronRight class="h-3.5 w-3.5" />
          </button>
        </div>
      </article>
    </div>
  </AdminLayout>
</template>
