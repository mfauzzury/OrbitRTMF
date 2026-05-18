<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";
import { useRouter } from "vue-router";
import {
  AppWindow, Users, Layers, GitBranch,
  ArrowRight, CheckCircle2, RefreshCw,
  ClipboardCheck, Wrench, FlaskConical, Code2, TableProperties, ChevronDown, ChevronRight,
} from "lucide-vue-next";

import AdminLayout from "@/layouts/AdminLayout.vue";
import { fetchRtmfDashboard, fetchRtmfByAssignee } from "@/api/rtmf";
import { useRtmfProjectStore } from "@/stores/rtmfProject";
import type { RtmfDashboardSummary, RtmfRoleModuleStat, RtmfByAssigneeSummary } from "@/types";

const router = useRouter();
const projectStore = useRtmfProjectStore();

// ── Tabs ──
const activeTab = ref<"main" | "individual">("main");

// ── Main tab ──
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

const selectedRole = ref<string | null>(null);

function toggleRole(key: string) {
  selectedRole.value = selectedRole.value === key ? null : key;
}

const drillModules = computed<RtmfRoleModuleStat[]>(() => {
  if (!selectedRole.value || !summary.value?.byRoleModule) return [];
  const key = selectedRole.value as keyof typeof summary.value.byRoleModule;
  return summary.value.byRoleModule[key] ?? [];
});

const donePercent = computed(() => {
  const t = summary.value?.totals;
  if (!t || t.frontends === 0) return 0;
  return Math.round((t.done / t.frontends) * 100);
});

const colorMap: Record<string, string> = {
  violet:  "bg-violet-600",
  sky:     "bg-sky-500",
  amber:   "bg-amber-500",
  green:   "bg-green-600",
};
const ringMap: Record<string, string> = {
  violet: "ring-violet-600",
  sky:    "ring-sky-500",
  amber:  "ring-amber-500",
  green:  "ring-green-600",
};
const textMap: Record<string, string> = {
  violet: "text-violet-700",
  sky:    "text-sky-700",
  amber:  "text-amber-700",
  green:  "text-green-700",
};
const bgMap: Record<string, string> = {
  violet: "bg-violet-50 border-violet-200",
  sky:    "bg-sky-50 border-sky-200",
  amber:  "bg-amber-50 border-amber-200",
  green:  "bg-green-50 border-green-200",
};

const reviewRoles = computed(() => {
  const r = summary.value?.byReview;
  const total = summary.value?.totals.frontends ?? 0;
  if (!r) return [];
  return [
    { key: "businessAnalyst", label: "Business Analyst", icon: ClipboardCheck, color: "violet", stat: r.businessAnalyst },
    { key: "qa",              label: "QA",               icon: FlaskConical,   color: "sky",    stat: r.qa },
    { key: "technical",       label: "Technical",        icon: Wrench,         color: "amber",  stat: r.technical },
    { key: "developer",       label: "Developer",        icon: Code2,          color: "green",  stat: r.developer },
  ].map(role => ({
    ...role,
    total,
    approvedPct: total ? Math.round((role.stat.approved / total) * 100) : 0,
  }));
});

const selectedRoleObj = computed(() => reviewRoles.value.find(r => r.key === selectedRole.value) ?? null);

// ── Individual tab ──
const indSummary = ref<RtmfByAssigneeSummary | null>(null);
const indLoading = ref(false);
let indSeq = 0;

// Use a plain object instead of Set — Vue 3 ref<Set> doesn't track .add()/.delete() mutations
const expandedCards = ref<Record<string, boolean>>({});

function toggleCard(key: string) {
  if (expandedCards.value[key]) delete expandedCards.value[key];
  else expandedCards.value[key] = true;
}

async function loadIndividual() {
  const seq = ++indSeq;
  indLoading.value = true;
  try {
    const pid = projectStore.activeProjectId;
    const params = pid ? `?project_id=${pid}` : "";
    const res = await fetchRtmfByAssignee(params);
    if (seq === indSeq) indSummary.value = res.data;
  } finally {
    if (seq === indSeq) indLoading.value = false;
  }
}

watch(activeTab, (t) => { if (t === "individual" && !indSummary.value) loadIndividual(); });

function refreshAll() {
  load();
  indSummary.value = null;
  expandedCards.value = {};
  if (activeTab.value === "individual") loadIndividual();
}

// Reset + reload individual on project change
watch(() => projectStore.activeProjectId, () => {
  load();
  indSummary.value = null;
  expandedCards.value = {};
  if (activeTab.value === "individual") loadIndividual();
});

// Chart helpers
const chartMaxVal = computed(() => {
  if (!indSummary.value) return 1;
  return Math.max(1, ...indSummary.value.dailyTrend.map(d => d.open + d.reviewed + d.approved));
});

function barPx(n: number) {
  return Math.round((n / chartMaxVal.value) * 96);
}

function initials(name: string) {
  return name.split(" ").map(w => w[0]).join("").toUpperCase().slice(0, 2);
}

function shortDate(iso: string) {
  const d = new Date(iso);
  return `${d.getDate()}/${d.getMonth() + 1}`;
}

function donePct(done: number, total: number) {
  return total ? Math.round((done / total) * 100) : 0;
}

onMounted(load);
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
          @click="refreshAll"
          :disabled="loading || indLoading"
          class="flex items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-600 shadow-sm transition-colors hover:bg-slate-50 disabled:opacity-50"
        >
          <RefreshCw class="h-3.5 w-3.5" :class="(loading || indLoading) ? 'animate-spin' : ''" />
          Refresh
        </button>
      </div>

      <!-- Tab bar -->
      <div class="flex overflow-hidden rounded-t-lg border border-slate-200 bg-slate-50">
        <button
          @click="activeTab = 'main'"
          class="flex items-center gap-2 border-b-2 px-5 py-2.5 text-sm font-medium transition-colors"
          :class="activeTab === 'main' ? 'border-violet-600 bg-white text-violet-700' : 'border-transparent text-slate-500 hover:bg-white hover:text-slate-700'"
        >
          <Layers class="h-4 w-4" />
          Main
        </button>
        <button
          @click="activeTab = 'individual'"
          class="flex items-center gap-2 border-b-2 px-5 py-2.5 text-sm font-medium transition-colors"
          :class="activeTab === 'individual' ? 'border-violet-600 bg-white text-violet-700' : 'border-transparent text-slate-500 hover:bg-white hover:text-slate-700'"
        >
          <Users class="h-4 w-4" />
          Individual
        </button>
      </div>

      <!-- ══ MAIN TAB ══ -->
      <template v-if="activeTab === 'main'">
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
                  <span class="rounded border border-slate-200 bg-white px-1.5 py-0.5 font-mono text-[10px] font-semibold text-slate-500 shadow-sm">{{ mod.code }}</span>
                  <span class="text-[11px] font-bold" :class="mod.frontendsCount && mod.doneCount === mod.frontendsCount ? 'text-emerald-600' : 'text-slate-400'">
                    {{ donePct(mod.doneCount, mod.frontendsCount) }}%
                  </span>
                </div>
                <p class="mb-2.5 truncate text-xs font-semibold text-slate-700">{{ mod.name }}</p>
                <div class="h-1.5 w-full overflow-hidden rounded-full bg-slate-200">
                  <div
                    class="h-full rounded-full transition-all"
                    :class="mod.frontendsCount && mod.doneCount === mod.frontendsCount ? 'bg-emerald-500' : 'bg-violet-500'"
                    :style="{ width: donePct(mod.doneCount, mod.frontendsCount) + '%' }"
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
              :class="[bgMap[role.color], selectedRole === role.key ? 'ring-2 ring-offset-1 ' + ringMap[role.color] : '']"
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
                  <p class="text-[10px] text-slate-500">Closed</p>
                </div>
                <div>
                  <p class="text-base font-semibold text-slate-600">{{ role.stat.reviewed }}</p>
                  <p class="text-[10px] text-slate-500">In Progress</p>
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
          <div v-if="selectedRoleObj && drillModules.length" class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-100 px-4 py-2.5">
              <div class="flex items-center gap-2">
                <component :is="selectedRoleObj.icon" class="h-4 w-4 text-slate-400" />
                <h2 class="text-sm font-semibold text-slate-800">{{ selectedRoleObj.label }} — By Module</h2>
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
                      :style="{ width: donePct(mod.approved, mod.total) + '%' }"
                    />
                  </div>
                </div>
                <div class="shrink-0 text-right">
                  <p class="text-xs font-semibold text-emerald-600">{{ mod.approved }} closed</p>
                  <p class="text-[10px] text-slate-400">{{ mod.reviewed }} in progress · {{ mod.open }} open</p>
                </div>
              </div>
            </div>
          </div>

        </template>
        <div v-else-if="loading" class="py-16 text-center text-sm text-slate-400">Loading…</div>
      </template>
      <!-- end MAIN TAB -->

      <!-- ══ INDIVIDUAL TAB ══ -->
      <div v-if="activeTab === 'individual'">

        <div v-if="indLoading" class="py-16 text-center text-sm text-slate-400">Loading…</div>

        <template v-else-if="indSummary">

          <!-- BA Feedback Trend -->
          <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-3">
              <ClipboardCheck class="h-4 w-4 text-violet-600" />
              <h2 class="text-sm font-semibold text-slate-900">BA Feedback Trend — Last 14 Days</h2>
              <div class="ml-auto flex items-center gap-3 text-[11px] text-slate-500">
                <span class="flex items-center gap-1"><span class="inline-block h-2.5 w-2.5 rounded-sm bg-slate-300"></span>Open</span>
                <span class="flex items-center gap-1"><span class="inline-block h-2.5 w-2.5 rounded-sm bg-amber-400"></span>In Progress</span>
                <span class="flex items-center gap-1"><span class="inline-block h-2.5 w-2.5 rounded-sm bg-emerald-500"></span>Closed</span>
              </div>
            </div>
            <div class="p-4 pb-8">
              <div class="flex h-24 items-end gap-1">
                <div
                  v-for="day in indSummary.dailyTrend"
                  :key="day.date"
                  class="group relative flex flex-1 flex-col-reverse"
                >
                  <!-- flex-col-reverse: first DOM child sits at the visual bottom -->
                  <div
                    v-if="day.open > 0"
                    class="w-full rounded-b-sm bg-slate-300 transition-all"
                    :style="{ height: barPx(day.open) + 'px' }"
                  />
                  <div
                    v-if="day.reviewed > 0"
                    class="w-full bg-amber-400 transition-all"
                    :style="{ height: barPx(day.reviewed) + 'px' }"
                  />
                  <div
                    v-if="day.approved > 0"
                    class="w-full rounded-t-sm bg-emerald-500 transition-all"
                    :style="{ height: barPx(day.approved) + 'px' }"
                  />
                  <div v-if="day.open === 0 && day.reviewed === 0 && day.approved === 0" class="w-full rounded-sm bg-slate-100" style="height:2px" />
                  <!-- Date label -->
                  <span class="absolute -bottom-5 left-0 right-0 text-center text-[9px] text-slate-400">{{ shortDate(day.date) }}</span>
                  <!-- Tooltip -->
                  <div class="pointer-events-none absolute bottom-full z-10 mb-1 hidden w-24 rounded-lg border border-slate-200 bg-white p-2 text-[10px] shadow-lg group-hover:block">
                    <p class="mb-1 font-medium text-slate-600">{{ day.date }}</p>
                    <p class="text-emerald-600">Closed: {{ day.approved }}</p>
                    <p class="text-amber-600">In Progress: {{ day.reviewed }}</p>
                    <p class="text-slate-400">Open: {{ day.open }}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Assignee Cards -->
          <div v-if="indSummary.assignees.length === 0" class="mt-4 rounded-lg border border-slate-200 bg-white py-16 text-center text-sm text-slate-400 shadow-sm">
            No assignees found.
          </div>
          <div v-else class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <div
              v-for="a in indSummary.assignees"
              :key="a.key"
              class="rounded-lg border border-slate-200 bg-white shadow-sm"
            >
              <!-- Card header -->
              <div class="flex items-center gap-3 border-b border-slate-100 px-4 py-3">
                <img
                  v-if="a.photoUrl"
                  :src="a.photoUrl"
                  class="h-9 w-9 shrink-0 rounded-full object-cover"
                />
                <div v-else class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-violet-100 text-sm font-bold text-violet-700">
                  {{ initials(a.name) }}
                </div>
                <div class="min-w-0 flex-1">
                  <p class="truncate text-sm font-semibold text-slate-800">{{ a.name }}</p>
                  <p v-if="a.email" class="truncate text-[11px] text-slate-400">{{ a.email }}</p>
                </div>
                <div class="shrink-0 text-right">
                  <p class="text-sm font-bold text-slate-700">{{ a.total }}<span class="text-[10px] font-normal text-slate-400 ml-0.5">pages</span></p>
                </div>
              </div>

              <!-- Stacked progress bar: bg=open | amber=in-progress | emerald=closed -->
              <div class="px-4 pt-3">
                <div class="flex h-2 w-full overflow-hidden rounded-full bg-slate-300">
                  <div class="h-full bg-emerald-500 transition-all" :style="{ width: donePct(a.baFeedback.approved, a.total) + '%' }" />
                  <div class="h-full bg-amber-400 transition-all"   :style="{ width: donePct(a.baFeedback.reviewed, a.total) + '%' }" />
                </div>
                <div class="mt-1.5 flex items-center gap-3 text-[10px] text-slate-400">
                  <span class="flex items-center gap-1"><span class="inline-block h-2 w-2 rounded-sm bg-emerald-500"></span>{{ a.baFeedback.approved }} Closed</span>
                  <span class="flex items-center gap-1"><span class="inline-block h-2 w-2 rounded-sm bg-amber-400"></span>{{ a.baFeedback.reviewed }} In Progress</span>
                  <span class="flex items-center gap-1"><span class="inline-block h-2 w-2 rounded-sm bg-slate-300"></span>{{ a.baFeedback.open }} Open</span>
                </div>
              </div>

              <!-- Module breakdown toggle -->
              <div class="border-t border-slate-100">
                <button
                  class="flex w-full items-center gap-1.5 px-4 py-2 text-[11px] font-medium text-slate-500 transition-colors hover:bg-slate-50 hover:text-slate-700"
                  @click="toggleCard(a.key)"
                >
                  <component :is="expandedCards[a.key] ? ChevronDown : ChevronRight" class="h-3.5 w-3.5" />
                  By Module ({{ a.byModule.length }})
                </button>
                <div v-if="expandedCards[a.key]" class="divide-y divide-slate-100 border-t border-slate-100">
                  <div
                    v-for="mod in a.byModule"
                    :key="mod.moduleId"
                    class="flex items-center gap-2 px-4 py-2"
                  >
                    <span class="w-14 shrink-0 rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[10px] text-slate-600">{{ mod.code }}</span>
                    <div class="min-w-0 flex-1">
                      <p class="truncate text-[11px] text-slate-600">{{ mod.name }}</p>
                      <div class="mt-0.5 flex h-1.5 w-full overflow-hidden rounded-full bg-slate-300">
                        <div class="h-full bg-emerald-500 transition-all" :style="{ width: donePct(mod.baFeedback.approved, mod.total) + '%' }" />
                        <div class="h-full bg-amber-400 transition-all"   :style="{ width: donePct(mod.baFeedback.reviewed, mod.total) + '%' }" />
                      </div>
                    </div>
                    <span class="shrink-0 text-[10px] text-slate-400">{{ mod.baFeedback.approved }}/{{ mod.total }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

        </template>
      </div>
      <!-- end INDIVIDUAL TAB -->

    </div>
  </AdminLayout>
</template>
