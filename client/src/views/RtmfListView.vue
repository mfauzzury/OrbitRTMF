<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";
import { RouterLink, useRouter } from "vue-router";
import { ChevronLeft, ChevronRight, LayoutGrid, Plus, Search } from "lucide-vue-next";

import AdminLayout from "@/layouts/AdminLayout.vue";
import { listRtmfFrontends, listRtmfModules } from "@/api/rtmf";
import { useRtmfProjectStore } from "@/stores/rtmfProject";
import { useToast } from "@/composables/useToast";
import type { RtmfFrontend, RtmfModule } from "@/types";

const router = useRouter();
const toast = useToast();
const projectStore = useRtmfProjectStore();

const rows = ref<RtmfFrontend[]>([]);
const modules = ref<RtmfModule[]>([]);
const total = ref(0);
const totalPages = ref(1);
const page = ref(1);
const limit = ref(25);
const loading = ref(false);
const PAGE_SIZES = [10, 25, 50, 100];
const q = ref("");
const moduleFilter = ref<number | "">("");
const doneFilter = ref<"" | "1" | "0">("");

const rangeStart = computed(() => (total.value === 0 ? 0 : (page.value - 1) * limit.value + 1));
const rangeEnd = computed(() => Math.min(page.value * limit.value, total.value));

async function load() {
  loading.value = true;
  const params = new URLSearchParams({ page: String(page.value), limit: String(limit.value) });
  if (q.value) params.set("q", q.value);
  if (moduleFilter.value) params.set("module_id", String(moduleFilter.value));
  if (doneFilter.value !== "") params.set("is_done", doneFilter.value);
  const pid = projectStore.activeProjectId;
  if (pid) params.set("project_id", String(pid));
  try {
    const response = await listRtmfFrontends(`?${params.toString()}`);
    rows.value = response.data;
    total.value = (response.meta?.total as number) ?? response.data.length;
    totalPages.value = (response.meta?.totalPages as number) ?? (Math.ceil(total.value / limit.value) || 1);
  } catch (e) {
    toast.error("Failed to load", e instanceof Error ? e.message : "API error");
  } finally {
    loading.value = false;
  }
}

function resetAndLoad() {
  page.value = 1;
  load();
}

function prevPage() {
  if (page.value > 1 && !loading.value) {
    page.value--;
    load();
  }
}

function nextPage() {
  if (page.value < totalPages.value && !loading.value) {
    page.value++;
    load();
  }
}

let searchTimer: ReturnType<typeof setTimeout> | null = null;
watch(q, () => {
  if (searchTimer) clearTimeout(searchTimer);
  searchTimer = setTimeout(resetAndLoad, 350);
});

watch(() => projectStore.activeProjectId, resetAndLoad);

onMounted(async () => {
  await projectStore.loadProjects();
  try {
    const pid = projectStore.activeProjectId;
    const modParams = pid ? `?project_id=${pid}` : "";
    const modResp = await listRtmfModules(modParams);
    modules.value = modResp.data;
  } catch (e) {
    toast.error("Failed to load modules", e instanceof Error ? e.message : "API error — check console");
    console.error("[RtmfListView]", e);
  }
  await load();
});

</script>

<template>
  <AdminLayout>
    <div class="mx-auto max-w-7xl space-y-4">
      <div class="flex items-center justify-between">
        <div>
          <nav class="mb-1 flex items-center gap-1.5 text-xs font-medium text-slate-500">
            <RouterLink to="/admin/rtmf/frontends" class="hover:text-violet-600 transition-colors">Page Catalog</RouterLink>
            <span class="text-slate-300">/</span>
            <span class="text-slate-700">Page Catalog</span>
          </nav>
          <h1 class="page-title">Page Catalog</h1>
          <p class="mt-1 text-sm text-slate-500">Requirements traceability matrix — all frontend spec entries.</p>
        </div>
        <button
          v-if="projectStore.canEdit"
          class="flex items-center gap-2 rounded-lg bg-slate-900 px-4 py-1.5 text-sm font-medium text-white shadow-sm transition-colors hover:bg-slate-800"
          @click="router.push('/admin/rtmf/frontends/new')"
        >
          <Plus class="h-4 w-4" />
          Add Page
        </button>
      </div>

      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <!-- Toolbar -->
        <div class="border-b border-slate-100 px-4 py-2.5">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
              <LayoutGrid class="h-4 w-4 text-violet-600" />
              <h2 class="text-sm font-semibold text-slate-900">Pages</h2>
              <span class="ml-1 text-xs text-slate-500">{{ total }} entries</span>
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <select
                v-model="moduleFilter"
                class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm shadow-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200"
                @change="resetAndLoad"
              >
                <option :value="''">All modules</option>
                <option v-for="m in modules" :key="m.id" :value="m.id">{{ m.code }} — {{ m.name }}</option>
              </select>
              <select
                v-model="doneFilter"
                class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm shadow-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200"
                @change="resetAndLoad"
              >
                <option value="">All status</option>
                <option value="1">Done</option>
                <option value="0">Pending</option>
              </select>
              <div class="relative">
                <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input
                  v-model="q"
                  placeholder="Search spec id, title…"
                  class="w-64 rounded-lg border border-slate-300 py-1.5 pl-9 pr-3 text-sm shadow-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200"
                  @keyup.enter="resetAndLoad"
                />
              </div>
              <button class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm font-medium text-slate-600 shadow-sm transition-colors hover:bg-slate-50" @click="resetAndLoad">Filter</button>
            </div>
          </div>
        </div>

        <!-- Table -->
        <div class="table-container">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-slate-100 text-left">
                <th class="whitespace-nowrap px-3 py-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Page ID</th>
                <th class="whitespace-nowrap px-3 py-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Title</th>
                <th class="whitespace-nowrap px-3 py-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Module / Sub-module</th>
                <th class="whitespace-nowrap px-2 py-2 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">Done</th>
                <th class="whitespace-nowrap px-2 py-2 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">
                  <span class="flex items-center justify-center gap-1">
                    <span class="text-violet-500">BA</span>
                    <span class="text-slate-300">·</span>
                    <span class="text-sky-500">QA</span>
                    <span class="text-slate-300">·</span>
                    <span class="text-amber-500">Tech</span>
                    <span class="text-slate-300">·</span>
                    <span class="text-green-600">Dev</span>
                  </span>
                </th>
                <th class="whitespace-nowrap px-2 py-2 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">Assigned</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr
                v-for="item in rows"
                :key="item.id"
                class="cursor-pointer align-middle transition-colors hover:bg-slate-50"
                @click="router.push(`/admin/rtmf/frontends/${item.id}`)"
              >
                <!-- ID_FR -->
                <td class="whitespace-nowrap px-3 py-2 font-mono text-xs text-slate-700">{{ item.specId }}</td>

                <!-- Title -->
                <td class="max-w-[260px] px-3 py-2 text-sm font-medium text-slate-900 truncate">{{ item.title }}</td>

                <!-- Module / Sub-module -->
                <td class="whitespace-nowrap px-3 py-2 font-mono text-xs text-slate-700">
                  {{ item.module?.code }}{{ item.subModule ? ' > ' + item.subModule.code : '' }}
                </td>

                <!-- Done -->
                <td class="whitespace-nowrap px-3 py-2 text-center" @click.stop>
                  <span
                    :title="item.isDone ? 'Done' : 'Not done'"
                    class="inline-flex h-4 w-4 items-center justify-center rounded-full"
                    :class="item.isDone ? 'bg-emerald-100 text-emerald-600' : 'bg-slate-100 text-slate-300'"
                  >
                    <svg viewBox="0 0 12 12" class="h-2.5 w-2.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <polyline points="2,6 5,9 10,3" />
                    </svg>
                  </span>
                </td>

                <!-- Reviews: BA · QA · Tech · Dev -->
                <td class="px-2 py-2 text-center" @click.stop>
                  <div class="flex items-center justify-center gap-1">
                    <span
                      v-for="role in (['business_analyst', 'qa', 'technical', 'developer'] as const)"
                      :key="role"
                      :title="`${role === 'business_analyst' ? 'BA' : role === 'qa' ? 'QA' : role === 'technical' ? 'Tech' : 'Dev'}: ${{ open: 'Open', reviewed: 'In Progress', approved: 'Closed' }[item.feedbacks?.find(f => f.role === role)?.status ?? 'open'] ?? 'Open'}`"
                      class="inline-flex h-4 w-4 items-center justify-center rounded-full"
                      :class="{
                        'bg-emerald-100 text-emerald-600': item.feedbacks?.find(f => f.role === role)?.status === 'approved',
                        'bg-amber-100 text-amber-600':     item.feedbacks?.find(f => f.role === role)?.status === 'reviewed',
                        'bg-slate-100 text-slate-300':     !item.feedbacks?.find(f => f.role === role) || item.feedbacks?.find(f => f.role === role)?.status === 'open',
                      }"
                    >
                      <svg v-if="item.feedbacks?.find(f => f.role === role)?.status === 'approved'" viewBox="0 0 12 12" class="h-2 w-2" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="2,6 5,9 10,3" /></svg>
                      <svg v-else-if="item.feedbacks?.find(f => f.role === role)?.status === 'reviewed'" viewBox="0 0 12 12" class="h-2 w-2" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="6" cy="6" r="3" /></svg>
                      <svg v-else viewBox="0 0 12 12" class="h-2 w-2" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="6" y1="3" x2="6" y2="9" /><line x1="3" y1="6" x2="9" y2="6" /></svg>
                    </span>
                  </div>
                </td>

                <!-- Assignees -->
                <td class="whitespace-nowrap px-3 py-2">
                  <div v-if="item.assignees?.length" class="flex items-center justify-center -space-x-1.5">
                    <template v-for="(a, i) in (item.assignees ?? []).slice(0, 4)" :key="String(a.id)">
                      <div class="group relative" :style="{ zIndex: 10 - i }">
                        <img
                          v-if="a.photoUrl"
                          :src="a.photoUrl"
                          class="h-6 w-6 rounded-full object-cover ring-2 ring-white"
                        />
                        <div
                          v-else
                          class="flex h-6 w-6 items-center justify-center rounded-full bg-violet-100 text-[10px] font-semibold text-violet-700 ring-2 ring-white"
                        >{{ a.name.charAt(0).toUpperCase() }}</div>
                        <div class="pointer-events-none absolute bottom-full left-1/2 mb-1.5 -translate-x-1/2 whitespace-nowrap rounded bg-slate-800 px-2 py-1 text-[11px] text-white opacity-0 transition-opacity group-hover:opacity-100">
                          {{ a.name }}
                          <div class="absolute left-1/2 top-full -translate-x-1/2 border-4 border-transparent border-t-slate-800"></div>
                        </div>
                      </div>
                    </template>
                    <div
                      v-if="(item.assignees?.length ?? 0) > 4"
                      class="group relative flex h-6 w-6 items-center justify-center rounded-full bg-slate-100 text-[10px] font-semibold text-slate-500 ring-2 ring-white"
                      style="z-index: 6"
                    >
                      +{{ (item.assignees?.length ?? 0) - 4 }}
                      <div class="pointer-events-none absolute bottom-full left-1/2 mb-1.5 -translate-x-1/2 whitespace-nowrap rounded bg-slate-800 px-2 py-1 text-[11px] text-white opacity-0 transition-opacity group-hover:opacity-100">
                        {{ (item.assignees ?? []).slice(4).map(a => a.name).join(', ') }}
                        <div class="absolute left-1/2 top-full -translate-x-1/2 border-4 border-transparent border-t-slate-800"></div>
                      </div>
                    </div>
                  </div>
                </td>

              </tr>
              <tr v-if="rows.length === 0">
                <td colspan="7" class="px-4 py-6 text-center text-sm text-slate-400">No frontend entries found.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-4 py-2.5">
          <div class="flex items-center gap-2 text-sm text-slate-500">
            <span>
              Showing <span class="font-medium text-slate-700">{{ rangeStart }}–{{ rangeEnd }}</span>
              of <span class="font-medium text-slate-700">{{ total }}</span>
            </span>
            <span class="text-slate-300">·</span>
            <label class="flex items-center gap-1.5">
              <span class="text-xs">Per page</span>
              <select
                v-model.number="limit"
                class="rounded-md border border-slate-300 px-2 py-1 text-xs shadow-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200"
                @change="resetAndLoad"
              >
                <option v-for="size in PAGE_SIZES" :key="size" :value="size">{{ size }}</option>
              </select>
            </label>
          </div>
          <div class="flex items-center gap-2">
            <button class="flex items-center gap-1 rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50 disabled:opacity-40" :disabled="page <= 1 || loading" @click="prevPage">
              <ChevronLeft class="h-3.5 w-3.5" />Previous
            </button>
            <span class="flex items-center gap-1.5 text-sm text-slate-500">
              <svg v-if="loading" class="h-3.5 w-3.5 animate-spin text-violet-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
              Page {{ page }} of {{ totalPages }}
            </span>
            <button class="flex items-center gap-1 rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50 disabled:opacity-40" :disabled="page >= totalPages || loading" @click="nextPage">
              Next<ChevronRight class="h-3.5 w-3.5" />
            </button>
          </div>
        </div>
      </article>
    </div>
  </AdminLayout>

</template>
