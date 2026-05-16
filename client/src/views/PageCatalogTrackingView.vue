<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";
import { useRouter } from "vue-router";
import {
  AppWindow, Users, Layers, GitBranch,
  ArrowRight, CheckCircle2, RefreshCw,
  ClipboardCheck, ShieldCheck, Wrench, FlaskConical, Code2,
} from "lucide-vue-next";

import AdminLayout from "@/layouts/AdminLayout.vue";
import { fetchRtmfDashboard } from "@/api/rtmf";
import { useRtmfProjectStore } from "@/stores/rtmfProject";
import type { RtmfDashboardSummary, RtmfRoleModuleStat } from "@/types";

const router = useRouter();
const projectStore = useRtmfProjectStore();

const selectedRole = ref<string | null>(null);

function toggleRole(key: string) {
  selectedRole.value = selectedRole.value === key ? null : key;
}

const drillModules = computed<RtmfRoleModuleStat[]>(() => {
  if (!selectedRole.value || !summary.value?.byRoleModule) return [];
  const key = selectedRole.value as keyof typeof summary.value.byRoleModule;
  return summary.value.byRoleModule[key] ?? [];
});

const summary = ref<RtmfDashboardSummary | null>(null);
const loading = ref(false);
let loadSeq = 0;

async function load() {
  const seq = ++loadSeq;
  loading.value = true;
  try {
    const pid = projectStore.activeProjectId;
    const params = pid ? `?project_id=${pid}` : "";
    const res = await fetchRtmfDashboard(params);
    if (seq === loadSeq) summary.value = res.data;
  } finally {
    if (seq === loadSeq) loading.value = false;
  }
}

onMounted(load);
watch(() => projectStore.activeProjectId, load);

const donePercent = computed(() => {
  const t = summary.value?.totals;
  if (!t || t.frontends === 0) return 0;
  return Math.round((t.done / t.frontends) * 100);
});


const reviewRoles = computed(() => {
  const r = summary.value?.byReview;
  const total = summary.value?.totals.frontends ?? 0;
  if (!r) return [];
  return [
    { key: 'businessAnalyst', label: 'Business Analyst', icon: ClipboardCheck, color: 'violet', stat: r.businessAnalyst },
    { key: 'qa',               label: 'QA',               icon: FlaskConical,   color: 'sky',    stat: r.qa },
    { key: 'technical',        label: 'Technical',        icon: Wrench,         color: 'amber',  stat: r.technical },
    { key: 'developer',        label: 'Developer',        icon: Code2,          color: 'green',  stat: r.developer },
  ].map(role => ({
    ...role,
    total,
    approvedPct: total ? Math.round((role.stat.approved / total) * 100) : 0,
  }));
});

const colorMap: Record<string, string> = {
  violet: 'bg-violet-600',
  sky:    'bg-sky-500',
  amber:  'bg-amber-500',
  emerald:'bg-emerald-500',
  green:  'bg-green-600',
};
const textMap: Record<string, string> = {
  violet: 'text-violet-700',
  sky:    'text-sky-700',
  amber:  'text-amber-700',
  green:  'text-green-700',
};
const bgMap: Record<string, string> = {
  violet: 'bg-violet-50 border-violet-200',
  sky:    'bg-sky-50 border-sky-200',
  amber:  'bg-amber-50 border-amber-200',
  green:  'bg-green-50 border-green-200',
};
</script>

<template>
  <AdminLayout>
    <div class="mx-auto max-w-7xl space-y-4">

      <!-- Header -->
      <div class="flex items-start justify-between">
        <div>
          <h1 class="page-title">Page Catalog Dashboard</h1>
          <p class="mt-0.5 text-sm text-slate-500">Progress overview — pages, review status, and implementation</p>
        </div>
        <button
          @click="load"
          :disabled="loading"
          class="flex items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-600 shadow-sm transition-colors hover:bg-slate-50 disabled:opacity-50"
        >
          <RefreshCw class="h-3.5 w-3.5" :class="loading ? 'animate-spin' : ''" />
          Refresh
        </button>
      </div>

      <template v-if="summary">

        <!-- Top KPI row -->
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
          <div class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
            <div class="flex items-center justify-between">
              <p class="text-[11px] text-slate-500">Total Pages</p>
              <AppWindow class="h-3.5 w-3.5 text-slate-400" />
            </div>
            <p class="mt-1 text-xl font-semibold text-slate-800">{{ summary.totals.frontends }}</p>
          </div>
          <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 shadow-sm">
            <div class="flex items-center justify-between">
              <p class="text-[11px] text-emerald-600">Done</p>
              <CheckCircle2 class="h-3.5 w-3.5 text-emerald-500" />
            </div>
            <p class="mt-1 text-xl font-semibold text-emerald-700">{{ summary.totals.done }}</p>
            <p class="text-[10px] text-emerald-500">{{ donePercent }}%</p>
          </div>
<div class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
            <div class="flex items-center justify-between">
              <p class="text-[11px] text-slate-500">Modules</p>
              <Layers class="h-3.5 w-3.5 text-slate-400" />
            </div>
            <p class="mt-1 text-xl font-semibold text-slate-800">{{ summary.totals.modules }}</p>
          </div>
          <div class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
            <div class="flex items-center justify-between">
              <p class="text-[11px] text-slate-500">Actors</p>
              <Users class="h-3.5 w-3.5 text-slate-400" />
            </div>
            <p class="mt-1 text-xl font-semibold text-slate-800">{{ summary.totals.actors }}</p>
          </div>
          <div class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
            <div class="flex items-center justify-between">
              <p class="text-[11px] text-slate-500">Form Items</p>
              <TableProperties class="h-3.5 w-3.5 text-slate-400" />
            </div>
            <p class="mt-1 text-xl font-semibold text-slate-800">{{ summary.totals.items }}</p>
          </div>
          <div class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
            <div class="flex items-center justify-between">
              <p class="text-[11px] text-slate-500">Scenarios</p>
              <GitBranch class="h-3.5 w-3.5 text-slate-400" />
            </div>
            <p class="mt-1 text-xl font-semibold text-slate-800">{{ summary.totals.scenarios }}</p>
          </div>
        </div>

        <!-- Module breakdown -->
        <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="flex items-center justify-between border-b border-slate-100 px-4 py-2.5">
            <div class="flex items-center gap-2">
              <Layers class="h-4 w-4 text-slate-400" />
              <h2 class="text-sm font-semibold text-slate-800">By Module</h2>
            </div>
            <button class="flex items-center gap-1 text-xs text-slate-400 transition-colors hover:text-slate-600" @click="router.push('/admin/rtmf/modules')">
              View all <ArrowRight class="h-3 w-3" />
            </button>
          </div>
          <div class="grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            <div
              v-for="mod in summary.byModule"
              :key="mod.id"
              class="rounded-lg border border-slate-200 bg-slate-50 p-3"
            >
              <div class="mb-2 flex items-center justify-between">
                <span class="rounded bg-white px-1.5 py-0.5 font-mono text-[10px] font-semibold text-slate-500 shadow-sm border border-slate-200">{{ mod.code }}</span>
                <span class="text-[11px] font-bold" :class="mod.frontendsCount && mod.doneCount === mod.frontendsCount ? 'text-emerald-600' : 'text-slate-400'">
                  {{ mod.frontendsCount ? Math.round((mod.doneCount / mod.frontendsCount) * 100) : 0 }}%
                </span>
              </div>
              <p class="mb-2.5 truncate text-xs font-semibold text-slate-700">{{ mod.name }}</p>
              <div class="h-1.5 w-full overflow-hidden rounded-full bg-slate-200">
                <div
                  class="h-full rounded-full transition-all"
                  :class="mod.frontendsCount && mod.doneCount === mod.frontendsCount ? 'bg-emerald-500' : 'bg-violet-500'"
                  :style="{ width: mod.frontendsCount ? Math.round((mod.doneCount / mod.frontendsCount) * 100) + '%' : '0%' }"
                />
              </div>
              <div class="mt-2 flex items-center justify-between text-[10px] text-slate-400">
                <span>{{ mod.doneCount }}/{{ mod.frontendsCount }} pages</span>
                <span>{{ mod.itemsCount }} items</span>
              </div>
            </div>
            <div v-if="summary.byModule.length === 0" class="col-span-full py-6 text-center text-xs text-slate-400">No modules.</div>
          </div>
        </div>

        <!-- Review status cards -->
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <div
            v-for="role in reviewRoles"
            :key="role.key"
            class="cursor-pointer rounded-lg border p-4 shadow-sm transition-all"
            :class="[bgMap[role.color], selectedRole === role.key ? 'ring-2 ring-offset-1 ' + colorMap[role.color].replace('bg-', 'ring-') : '']"
            @click="toggleRole(role.key)"
          >
            <div class="flex items-center gap-2">
              <component :is="role.icon" class="h-4 w-4 shrink-0" :class="textMap[role.color]" />
              <p class="min-w-0 flex-1 truncate text-sm font-semibold" :class="textMap[role.color]">{{ role.label }}</p>
              <span class="shrink-0 text-xs font-semibold" :class="textMap[role.color]">{{ role.approvedPct }}%</span>
            </div>
            <div class="mt-3 flex items-center gap-1">
              <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-white/60">
                <div
                  class="h-full rounded-full transition-all"
                  :class="colorMap[role.color]"
                  :style="{ width: role.approvedPct + '%' }"
                />
              </div>
            </div>
            <div class="mt-3 grid grid-cols-3 gap-2 text-center">
              <div>
                <p class="text-base font-semibold" :class="textMap[role.color]">{{ role.stat.approved }}</p>
                <p class="text-[10px] text-slate-500">Approved</p>
              </div>
              <div>
                <p class="text-base font-semibold text-slate-600">{{ role.stat.reviewed }}</p>
                <p class="text-[10px] text-slate-500">Reviewed</p>
              </div>
              <div>
                <p class="text-base font-semibold text-slate-400">{{ role.stat.open }}</p>
                <p class="text-[10px] text-slate-500">Open</p>
              </div>
            </div>
            <p class="mt-3 text-center text-[10px]" :class="textMap[role.color]">
              {{ selectedRole === role.key ? '▲ Hide breakdown' : '▼ View by module' }}
            </p>
          </div>
        </div>

        <!-- Drill-down: module breakdown for selected role -->
        <div v-if="selectedRole && drillModules.length" class="rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="flex items-center justify-between border-b border-slate-100 px-4 py-2.5">
            <div class="flex items-center gap-2">
              <component :is="reviewRoles.find(r => r.key === selectedRole)?.icon" class="h-4 w-4 text-slate-400" />
              <h2 class="text-sm font-semibold text-slate-800">
                {{ reviewRoles.find(r => r.key === selectedRole)?.label }} — By Module
              </h2>
            </div>
            <button class="text-xs text-slate-400 hover:text-slate-600" @click="selectedRole = null">✕ Close</button>
          </div>
          <div class="divide-y divide-slate-100">
            <div
              v-for="mod in drillModules"
              :key="mod.id"
              class="flex items-center gap-3 px-4 py-2.5"
            >
              <div class="w-16 shrink-0">
                <span class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[10px] text-slate-600">{{ mod.code }}</span>
              </div>
              <div class="min-w-0 flex-1">
                <p class="truncate text-xs font-medium text-slate-800">{{ mod.name }}</p>
                <div class="mt-1 h-1 w-full overflow-hidden rounded-full bg-slate-100">
                  <div
                    class="h-full rounded-full bg-emerald-500 transition-all"
                    :style="{ width: mod.total ? Math.round((mod.approved / mod.total) * 100) + '%' : '0%' }"
                  />
                </div>
              </div>
              <div class="shrink-0 text-right">
                <p class="text-xs font-semibold text-emerald-600">{{ mod.approved }} approved</p>
                <p class="text-[10px] text-slate-400">{{ mod.reviewed }} reviewed · {{ mod.open }} open</p>
              </div>
            </div>
          </div>
        </div>


      </template>

      <div v-else-if="loading" class="py-16 text-center text-sm text-slate-400">Loading…</div>

    </div>
  </AdminLayout>
</template>
