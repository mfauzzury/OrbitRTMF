<script setup lang="ts">
import { onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import { FolderKanban, ArrowRight, Users } from "lucide-vue-next";
import AdminLayout from "@/layouts/AdminLayout.vue";
import { listRtmfProjects } from "@/api/rtmf";
import type { RtmfProject } from "@/types";

const router = useRouter();
const projects = ref<RtmfProject[]>([]);
const loading = ref(true);

const ROLE_LABELS: Record<string, string> = {
  admin:            "Admin",
  business_analyst: "Business Analyst",
  qa:               "QA",
  technical:        "Technical",
  developer:        "Developer",
  viewer:           "Viewer",
};

const ROLE_COLORS: Record<string, string> = {
  admin:            "bg-violet-100 text-violet-700",
  business_analyst: "bg-blue-100 text-blue-700",
  qa:               "bg-amber-100 text-amber-700",
  technical:        "bg-cyan-100 text-cyan-700",
  developer:        "bg-emerald-100 text-emerald-700",
  viewer:           "bg-slate-100 text-slate-600",
};

function enterProject(project: RtmfProject) {
  const role = project.myRole ?? "viewer";
  const section = role === "viewer" ? "defects" : "frontends";
  router.push(`/admin/rtmf/projects/${project.id}/${section}`);
}

onMounted(async () => {
  try {
    const res = await listRtmfProjects();
    projects.value = res.data;
    if (res.data.length === 1) {
      enterProject(res.data[0]);
      return;
    }
  } finally {
    loading.value = false;
  }
});
</script>

<template>
  <AdminLayout>
    <div class="mx-auto max-w-5xl space-y-6">

      <div>
        <h1 class="page-title">My Task</h1>
        <p class="mt-0.5 text-sm text-slate-500">Projects you are assigned to</p>
      </div>

      <div v-if="loading" class="py-16 text-center text-sm text-slate-400">Loading…</div>

      <div v-else-if="projects.length === 0" class="rounded-xl border border-dashed border-slate-300 bg-white py-16 text-center">
        <FolderKanban class="mx-auto mb-3 h-10 w-10 text-slate-300" />
        <p class="text-sm font-medium text-slate-600">No projects assigned</p>
        <p class="mt-1 text-xs text-slate-400">Contact your administrator to be added to a project.</p>
      </div>

      <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <button
          v-for="project in projects"
          :key="project.id"
          class="group flex flex-col items-start gap-4 rounded-xl border border-slate-200 bg-white p-5 text-left shadow-sm transition-all hover:border-[var(--accent-300)] hover:shadow-md"
          @click="enterProject(project)"
        >
          <div class="flex w-full items-start justify-between gap-2">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-[var(--accent-100)]">
              <FolderKanban class="h-5 w-5 text-[var(--accent-600)]" />
            </div>
            <span
              class="rounded-full px-2.5 py-0.5 text-xs font-medium"
              :class="ROLE_COLORS[project.myRole ?? 'viewer'] ?? 'bg-slate-100 text-slate-600'"
            >
              {{ ROLE_LABELS[project.myRole ?? 'viewer'] ?? project.myRole }}
            </span>
          </div>

          <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-semibold text-slate-900 group-hover:text-[var(--accent-700)]">
              {{ project.name }}
            </p>
            <p v-if="project.description" class="mt-0.5 line-clamp-2 text-xs text-slate-400">
              {{ project.description }}
            </p>
          </div>

          <div class="flex w-full items-center justify-between">
            <div class="flex items-center gap-1 text-xs text-slate-400">
              <Users class="h-3.5 w-3.5" />
              <span>Project workspace</span>
            </div>
            <span class="flex items-center gap-1 text-xs font-medium text-[var(--accent-600)] opacity-0 transition-opacity group-hover:opacity-100">
              Enter <ArrowRight class="h-3.5 w-3.5" />
            </span>
          </div>
        </button>
      </div>

    </div>
  </AdminLayout>
</template>
