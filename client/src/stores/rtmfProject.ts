import { defineStore } from "pinia";
import { listRtmfProjects } from "@/api/rtmf";
import type { RtmfProject } from "@/types";

export const useRtmfProjectStore = defineStore("rtmfProject", {
  state: () => ({
    projects: [] as RtmfProject[],
    activeProjectId: Number(localStorage.getItem("rtmf_project_id")) || (null as number | null),
    loaded: false,
  }),

  getters: {
    activeProject: (s): RtmfProject | null =>
      s.projects.find((p) => p.id === s.activeProjectId) ?? null,
    activeProjectRole: (s): string | null => {
      const p = s.projects.find((p) => p.id === s.activeProjectId);
      return p?.myRole ?? null;
    },
    canEdit: (s): boolean => {
      const p = s.projects.find((p) => p.id === s.activeProjectId);
      return p?.myRole === "admin" || p?.myRole === "business_analyst";
    },
    effectivePermissions: (s): string[] => {
      const p = s.projects.find((p) => p.id === s.activeProjectId);
      const role = p?.myRole ?? null;
      if (!role) return [];
      if (role === "admin" || role === "business_analyst")
        return ["rtmf.catalog", "rtmf.tools", "rtmf.tracker", "rtmf.feedback"];
      if (role === "qa" || role === "technical" || role === "developer")
        return ["rtmf.catalog", "rtmf.tracker", "rtmf.feedback"];
      if (role === "viewer")
        return ["rtmf.tracker"];
      return [];
    },
  },

  actions: {
    async loadProjects() {
      if (this.loaded) return;
      try {
        const res = await listRtmfProjects();
        this.projects = res.data;
        this.loaded = true;
        // Auto-select first project if nothing is stored or stored id is invalid
        if (!this.activeProjectId || !this.projects.find((p) => p.id === this.activeProjectId)) {
          const first = this.projects[0];
          if (first) this.setActive(first.id);
        }
      } catch {
        // silently ignore — will retry next navigation
      }
    },

    setActive(id: number) {
      this.activeProjectId = id;
      localStorage.setItem("rtmf_project_id", String(id));
    },

    invalidate() {
      this.loaded = false;
      this.projects = [];
    },
  },
});
