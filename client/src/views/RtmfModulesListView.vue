<script setup lang="ts">
import { onMounted, ref } from "vue";
import { RouterLink, useRouter } from "vue-router";
import { LayoutGrid, Plus } from "lucide-vue-next";
import AdminLayout from "@/layouts/AdminLayout.vue";
import { listRtmfModules, listRtmfSubModules } from "@/api/rtmf";
import { useAuthStore } from "@/stores/auth";
import { useRtmfProjectStore } from "@/stores/rtmfProject";
import type { RtmfModule, RtmfSubModule } from "@/types";

const auth = useAuthStore();
const router = useRouter();
const projectStore = useRtmfProjectStore();
const rows = ref<RtmfModule[]>([]);
const subModulesMap = ref<Record<number, RtmfSubModule[]>>({});

async function load() {
  const pid = projectStore.activeProjectId;
  const params = pid ? `?project_id=${pid}` : "";
  const r = await listRtmfModules(params);
  rows.value = r.data;
  await Promise.all(
    r.data.map(async (m) => {
      const sr = await listRtmfSubModules(m.id);
      subModulesMap.value[m.id] = sr.data;
    }),
  );
}

onMounted(load);
</script>

<template>
  <AdminLayout>
    <div class="mx-auto max-w-7xl space-y-4">
      <div class="flex items-center justify-between">
        <div>
          <nav class="mb-1 flex items-center gap-1.5 text-xs font-medium text-slate-500">
            <RouterLink to="/admin/rtmf/frontends" class="hover:text-violet-600 transition-colors">Page Catalog</RouterLink>
            <span class="text-slate-300">/</span>
            <span class="text-slate-700">Modules</span>
          </nav>
          <h1 class="page-title">Modules</h1>
          <p class="mt-1 text-sm text-slate-500">Top-level registration modules and their sub-modules.</p>
        </div>
        <button
          v-if="projectStore.canEdit"
          class="flex items-center gap-2 rounded-lg bg-slate-900 px-4 py-1.5 text-sm font-medium text-white shadow-sm hover:bg-slate-800"
          @click="router.push('/admin/rtmf/modules/new')"
        >
          <Plus class="h-4 w-4" />Add Module
        </button>
      </div>

      <div v-if="rows.length === 0" class="rounded-lg border border-slate-200 bg-white px-4 py-10 text-center text-sm text-slate-400 shadow-sm">
        No modules yet.
      </div>

      <div class="grid auto-rows-min gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <article
          v-for="m in rows"
          :key="m.id"
          class="group flex cursor-pointer flex-col rounded-xl border border-slate-200 bg-white shadow-sm transition-shadow hover:shadow-md"
          @click="router.push(`/admin/rtmf/modules/${m.id}`)"
        >
          <!-- Card header -->
          <div class="flex items-start gap-3 border-b border-slate-100 px-5 py-4">
            <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg bg-violet-100 text-violet-600">
              <LayoutGrid class="h-4.5 w-4.5 h-[18px] w-[18px]" />
            </div>
            <div class="min-w-0 flex-1">
              <p class="font-mono text-xs font-semibold uppercase tracking-widest text-slate-400">{{ m.code }}</p>
              <h2 class="truncate text-base font-semibold text-slate-900">{{ m.name }}</h2>
              <p v-if="m.description" class="mt-0.5 truncate text-xs text-slate-400">{{ m.description }}</p>
            </div>
          </div>

          <!-- Sub-modules list -->
          <div class="px-5 py-3">
            <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">
              Sub-modules <span class="ml-1 font-normal normal-case">{{ (subModulesMap[m.id] ?? []).length }}</span>
            </p>
            <ul class="max-h-40 space-y-1.5 overflow-y-auto">
              <li
                v-for="(sub, i) in (subModulesMap[m.id] ?? [])"
                :key="sub.id"
                class="flex items-center gap-2.5"
              >
                <span class="flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-500">{{ i + 1 }}</span>
                <span class="text-sm text-slate-700">{{ sub.name }}</span>
              </li>
              <li v-if="(subModulesMap[m.id] ?? []).length === 0" class="text-xs text-slate-400">
                No sub-modules yet.
              </li>
            </ul>
          </div>

          <!-- Card footer -->
          <div class="flex items-center justify-between border-t border-slate-100 px-5 py-3">
            <span class="text-xs text-slate-400">{{ m.frontendsCount ?? 0 }} frontend entries</span>
            <span class="text-xs font-medium text-violet-600 opacity-0 transition-opacity group-hover:opacity-100">Edit →</span>
          </div>
        </article>
      </div>
    </div>
  </AdminLayout>
</template>
