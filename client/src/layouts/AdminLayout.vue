<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { Bell, Check, ChevronDown, FolderKanban, LogOut, MessageCircle, Send, Settings, Shield } from "lucide-vue-next";

import type { ThemeColor } from "@/types";
import type { MenuItemDef, MenuNode } from "@/config/admin-menu";
import { useSidebarCollapse } from "@/composables/useSidebarCollapse";
import { useToast } from "@/composables/useToast";
import AppToastRegion from "@/components/AppToastRegion.vue";

import { useAuthStore } from "@/stores/auth";
import { useMenuStore } from "@/stores/menu";
import { useSiteStore } from "@/stores/site";
import { useUiThemeStore } from "@/stores/uiTheme";
import { useRtmfProjectStore } from "@/stores/rtmfProject";
import { API_BASE_URL } from "@/env";

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const menuStore = useMenuStore();
const site = useSiteStore();
const uiTheme = useUiThemeStore();
const rtmfProjectStore = useRtmfProjectStore();
const toast = useToast();
const { isCollapsed, isCompact, toggle: toggleSidebar, toggleCompact } = useSidebarCollapse();

const settingsOpen = ref(false);
const settingsDropdownRef = ref<HTMLElement | null>(null);

const chatOpen = ref(false);
const chatDropdownRef = ref<HTMLElement | null>(null);
const chatMessage = ref("");
const chatMessages = ref<Array<{ id: number; from: "user" | "admin"; text: string; time: string }>>([]);
let chatIdCounter = 0;

function sendChatMessage() {
  const text = chatMessage.value.trim();
  if (!text) return;
  chatMessages.value.push({
    id: ++chatIdCounter,
    from: "user",
    text,
    time: new Date().toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" }),
  });
  chatMessage.value = "";
}

const themeChoices: Array<{ label: string; value: ThemeColor }> = [
  { label: "Violet", value: "violet" },
  { label: "Blue", value: "blue" },
  { label: "Green", value: "green" },
  { label: "Red", value: "red" },
  { label: "B&W", value: "black-white" },
  { label: "Grey", value: "grey" },
];

const handleDocumentClick = (event: MouseEvent) => {
  if (settingsOpen.value && settingsDropdownRef.value && !settingsDropdownRef.value.contains(event.target as Node)) {
    settingsOpen.value = false;
  }
  if (chatOpen.value && chatDropdownRef.value && !chatDropdownRef.value.contains(event.target as Node)) {
    chatOpen.value = false;
  }
};

const handleEscape = (event: KeyboardEvent) => {
  if (event.key === "Escape") {
    settingsOpen.value = false;
    chatOpen.value = false;
  }
};

function resolveUrl(url: string) {
  if (!url) return "";
  if (url.startsWith("http")) return url;
  return `${API_BASE_URL}${url}`;
}

onMounted(() => {
  site.load();
  menuStore.load();
  rtmfProjectStore.loadProjects();
  document.addEventListener("click", handleDocumentClick);
  document.addEventListener("keydown", handleEscape);
});

onBeforeUnmount(() => {
  document.removeEventListener("click", handleDocumentClick);
  document.removeEventListener("keydown", handleEscape);
});

const openMenus = reactive<Record<string, boolean>>({});

const userInitials = computed(() => {
  if (!auth.user?.name) return "A";
  return auth.user.name
    .split(" ")
    .map((n) => n[0])
    .join("")
    .toUpperCase()
    .slice(0, 2);
});

const userRoleLabel = computed(() => auth.user?.role || "Administrator");

const effectivePerms = computed((): string[] => {
  if (auth.isAdmin) return auth.permissions;
  return rtmfProjectStore.effectivePermissions;
});

const visibleMenu = computed(() => {
  const userPerms = effectivePerms.value;

  return menuStore.resolvedMenu
    .filter((g) => {
      if (!g.requiredPermissions || g.requiredPermissions.length === 0) return true;
      if (auth.isAdmin) return true;
      return g.requiredPermissions.some((p) => userPerms.includes(p));
    })
    .map((g) => ({
      ...g,
      items: auth.isAdmin ? g.items : g.items.filter((item) => !item.adminOnly),
    }))
    .filter((g) => g.items.length > 0);
});

const PROJECT_ROUTE_MAP: Record<string, string> = {
  "/admin/rtmf/dashboard":   "dashboard",
  "/admin/rtmf/frontends":   "frontends",
  "/admin/rtmf/modules":     "modules",
  "/admin/rtmf/actors":      "actors",
  "/admin/rtmf/scenarios":   "scenarios",
  "/admin/rtmf/relations":   "relations",
  "/admin/rtmf/import":      "import",
  "/admin/rtmf/export":      "export",
  "/admin/defects":          "defects",
  "/admin/cr":               "cr",
  "/admin/catalog-tracking": "tracking",
};

function injectProjectId(menu: typeof visibleMenu.value, projectId: number | null) {
  if (!projectId) return menu;
  return menu.map((group) => ({
    ...group,
    items: group.items.map((item) => {
      const segment = PROJECT_ROUTE_MAP[item.to];
      if (!segment) return item;
      const base = `/admin/rtmf/projects/${projectId}/${segment}`;
      return {
        ...item,
        to: base,
        children: item.children?.map((child) => ({
          ...child,
          to: PROJECT_ROUTE_MAP[child.to]
            ? `/admin/rtmf/projects/${projectId}/${PROJECT_ROUTE_MAP[child.to]}`
            : child.to,
        })),
      };
    }),
  }));
}

const finalMenu = computed(() =>
  injectProjectId(visibleMenu.value, rtmfProjectStore.activeProjectId),
);
const HEADER_TEXT_MAX = 20;

function truncateHeaderText(value: string, max = HEADER_TEXT_MAX) {
  if (!value) return "";
  return value.length > max ? `${value.slice(0, max)}...` : value;
}

const headerSiteTitle = computed(() => truncateHeaderText(site.siteTitle || ""));
const headerUserName = computed(() => truncateHeaderText(auth.user?.name || "Admin"));
const headerUserRole = computed(() => truncateHeaderText(userRoleLabel.value));

const rowBaseClass = computed(() =>
  isCompact.value
    ? "gap-2.5 px-3 py-1 text-[13px] leading-tight"
    : "gap-2.5 px-3 py-1.5 text-sm",
);

const collapsedRowBaseClass = computed(() =>
  isCompact.value
    ? "md:justify-center md:px-0 md:py-1.5 md:rounded-none gap-2.5 px-3 py-1"
    : "md:justify-center md:px-0 md:py-2.5 md:rounded-none gap-2.5 px-3 py-1.5",
);

const childRowClass = computed(() =>
  isCompact.value
    ? "block rounded-md px-3 py-0.5 text-[13px] leading-tight transition-all hover:bg-[var(--accent-50)]"
    : "block rounded-md px-3 py-1 text-sm transition-all hover:bg-[var(--accent-50)]",
);

function switchProject(id: number) {
  rtmfProjectStore.setActive(id);
  // If currently on a project-scoped URL, navigate to the same section in the new project
  const currentProjectId = route.params.projectId;
  if (currentProjectId) {
    const newPath = route.path.replace(
      `/admin/rtmf/projects/${String(currentProjectId)}/`,
      `/admin/rtmf/projects/${id}/`,
    );
    if (newPath !== route.path) router.push(newPath);
  }
}

async function signOut() {
  try {
    await auth.signOut();
    toast.success("Signed out", "You have been logged out.");
    router.push("/admin/login");
  } catch (e) {
    toast.error("Sign out failed", e instanceof Error ? e.message : "Please try again.");
  }
}

function isActive(path: string): boolean {
  if (path === "/" || path === "/admin") return route.path === path;
  return route.path.startsWith(path);
}

function itemClass(path: string) {
  if (isActive(path)) {
    return "border border-[var(--accent-200)] bg-[var(--accent-50)] font-medium text-[var(--accent-700)]";
  }
  return "border border-transparent text-slate-900";
}

function childClass(path: string) {
  if (route.path === path) {
    return "border border-[var(--accent-200)] bg-[var(--accent-50)] font-medium text-[var(--accent-700)]";
  }
  return "border border-transparent text-slate-600";
}

function toggleMenu(id: string) {
  openMenus[id] = !openMenus[id];
}

function isNodeActive(node: { to: string; children?: MenuNode[] }): boolean {
  if (isActive(node.to)) return true;
  if (!node.children || node.children.length === 0) return false;
  return node.children.some((child) => isNodeActive(child));
}

function syncOpenMenus() {
  const syncNode = (node: MenuNode | MenuItemDef) => {
    if (node.children && node.children.length > 0 && isNodeActive(node)) {
      openMenus[node.id] = true;
      for (const child of node.children) syncNode(child);
    }
  };

  for (const group of finalMenu.value) {
    for (const item of group.items) {
      syncNode(item);
    }
  }
}

watch(() => route.path, syncOpenMenus, { immediate: true });
watch(() => finalMenu.value, syncOpenMenus, { deep: true });
</script>

<template>
  <div class="min-h-screen bg-[#f8f9fb]">
    <header class="sticky top-0 z-40 flex h-10 items-center justify-between border-b border-slate-200 bg-white px-5">
      <div class="flex items-center gap-1">
        <div v-if="site.siteIconUrl" class="flex h-[20px] shrink-0 items-center justify-center overflow-hidden">
          <img :src="resolveUrl(site.siteIconUrl)" alt="Site icon" class="h-full w-auto object-contain" />
        </div>
        <div
          v-else
          class="flex h-[20px] w-[20px] shrink-0 items-center justify-center rounded-md bg-gradient-to-br from-[var(--accent-600)] to-[var(--accent-500)]"
        >
          <Shield class="h-[17px] w-[17px] text-white" />
        </div>
      </div>

      <div class="flex items-center self-stretch">
        <div
          v-if="site.siteTitle"
          class="flex h-full items-center overflow-hidden whitespace-nowrap"
        >
          <span class="px-4 text-sm font-light text-slate-900">{{ headerSiteTitle }}</span>
          <span class="h-full w-px bg-slate-200" />
        </div>

        <AppToastRegion />

        <router-link
          :to="'/admin/settings/users/' + auth.user?.id"
          class="group relative flex h-full items-center gap-2 px-4 transition-colors hover:bg-[var(--accent-600)]"
        >
          <img
            v-if="auth.user?.photoUrl"
            :src="auth.user.photoUrl"
            class="h-6 w-6 shrink-0 rounded-full object-cover ring-1 ring-white"
            :alt="auth.user.name"
          />
          <div
            v-else
            class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-[var(--accent-600)] to-[var(--accent-500)] text-[10px] font-semibold text-white"
          >
            {{ userInitials }}
          </div>
          <div class="leading-tight">
            <p class="text-sm font-medium text-slate-700 group-hover:text-white">{{ headerUserName }}</p>
            <p class="text-[11px] text-slate-500 group-hover:text-white/80">{{ headerUserRole }}</p>
          </div>
          <span class="pointer-events-none absolute -bottom-8 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-md bg-slate-900 px-2 py-1 text-xs text-white opacity-0 shadow-lg transition-opacity group-hover:opacity-100">Profile</span>
        </router-link>

        <span class="h-full w-px bg-slate-200" />

        <div ref="chatDropdownRef" class="relative flex h-full items-stretch">
          <button
            class="group relative flex h-full items-center px-4 text-slate-500 transition-colors hover:bg-[var(--accent-600)] hover:text-white"
            @click.stop="chatOpen = !chatOpen"
          >
            <MessageCircle class="h-4 w-4" />
            <span class="pointer-events-none absolute -bottom-8 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-md bg-slate-900 px-2 py-1 text-xs text-white opacity-0 shadow-lg transition-opacity group-hover:opacity-100">Chat</span>
          </button>

          <div
            v-if="chatOpen"
            class="absolute right-0 top-full z-50 mt-2 flex h-96 w-80 flex-col rounded-lg border border-slate-200 bg-white shadow-lg"
          >
            <div class="flex items-center justify-between border-b border-slate-200 px-4 py-2.5">
              <p class="text-sm font-semibold text-slate-700">Chat</p>
              <button class="text-slate-400 transition-colors hover:text-slate-600" @click="chatOpen = false">&times;</button>
            </div>

            <div class="flex-1 space-y-3 overflow-y-auto px-4 py-3">
              <div v-if="chatMessages.length === 0" class="flex h-full items-center justify-center">
                <p class="text-xs text-slate-400">No messages yet</p>
              </div>
              <div
                v-for="msg in chatMessages"
                :key="msg.id"
                class="flex"
                :class="msg.from === 'user' ? 'justify-end' : 'justify-start'"
              >
                <div
                  class="max-w-[75%] rounded-lg px-3 py-2 text-xs"
                  :class="msg.from === 'user' ? 'bg-[var(--accent-600)] text-white' : 'bg-slate-100 text-slate-700'"
                >
                  <p>{{ msg.text }}</p>
                  <p class="mt-1 text-[10px]" :class="msg.from === 'user' ? 'text-white/70' : 'text-slate-400'">{{ msg.time }}</p>
                </div>
              </div>
            </div>

            <div class="border-t border-slate-200 px-3 py-2.5">
              <div class="flex items-center gap-2">
                <input
                  v-model="chatMessage"
                  type="text"
                  placeholder="Type a message..."
                  class="flex-1 rounded-md border border-slate-200 px-3 py-1.5 text-xs text-slate-700 outline-none transition-colors focus:border-[var(--accent-400)] focus:ring-1 focus:ring-[var(--accent-400)]"
                  @keydown.enter="sendChatMessage"
                />
                <button
                  class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-[var(--accent-600)] text-white transition-colors hover:bg-[var(--accent-700)]"
                  @click="sendChatMessage"
                >
                  <Send class="h-3.5 w-3.5" />
                </button>
              </div>
            </div>
          </div>
        </div>

        <span class="h-full w-px bg-slate-200" />

        <div ref="settingsDropdownRef" class="relative flex h-full items-stretch">
          <button
            class="group relative flex h-full items-center px-4 text-slate-500 transition-colors hover:bg-[var(--accent-600)] hover:text-white"
            @click.stop="settingsOpen = !settingsOpen"
          >
            <Settings class="h-4 w-4" />
            <span class="pointer-events-none absolute -bottom-8 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-md bg-slate-900 px-2 py-1 text-xs text-white opacity-0 shadow-lg transition-opacity group-hover:opacity-100">Theme settings</span>
          </button>

          <div
            v-if="settingsOpen"
            class="absolute right-0 top-full z-50 mt-2 w-56 rounded-lg border border-slate-200 bg-white p-3 shadow-lg"
          >
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Theme color</p>
            <div class="grid grid-cols-2 gap-2">
              <button
                v-for="theme in themeChoices"
                :key="theme.value"
                class="flex items-center justify-between rounded-md border px-2.5 py-2 text-xs font-medium transition-colors"
                :class="uiTheme.themeColor === theme.value
                  ? 'border-[var(--accent-500)] bg-[var(--accent-50)] text-[var(--accent-700)]'
                  : 'border-slate-200 text-slate-600 hover:border-[var(--accent-ring)] hover:text-slate-900'"
                @click="uiTheme.setThemeColor(theme.value)"
              >
                <span class="flex items-center gap-2">
                  <span
                    class="h-2.5 w-2.5 rounded-full"
                    :class="theme.value === 'violet'
                      ? 'bg-violet-500'
                      : theme.value === 'blue'
                        ? 'bg-blue-500'
                        : theme.value === 'green'
                          ? 'bg-emerald-500'
                          : theme.value === 'red'
                            ? 'bg-rose-500'
                            : theme.value === 'black-white'
                              ? 'bg-slate-900'
                              : 'bg-neutral-500'"
                  />
                  {{ theme.label }}
                </span>
                <Check v-if="uiTheme.themeColor === theme.value" class="h-3.5 w-3.5" />
              </button>
            </div>

            <div class="mt-3 border-t border-slate-200 pt-3">
              <button
                class="flex w-full items-center justify-between rounded-md border border-slate-200 px-2.5 py-2 text-xs font-medium text-slate-700 transition-colors hover:border-[var(--accent-ring)]"
                @click="toggleCompact"
              >
                <span>Compact sidebar</span>
                <span
                  class="relative inline-flex h-4 w-7 items-center rounded-full transition-colors"
                  :class="isCompact ? 'bg-[var(--accent-600)]' : 'bg-slate-300'"
                >
                  <span
                    class="inline-block h-3 w-3 transform rounded-full bg-white transition"
                    :class="isCompact ? 'translate-x-3.5' : 'translate-x-0.5'"
                  />
                </span>
              </button>
            </div>
          </div>
        </div>

        <span class="h-full w-px bg-slate-200" />

        <button
          class="group relative flex h-full items-center px-4 text-slate-500 transition-colors hover:bg-[var(--accent-600)] hover:text-white"
        >
          <Bell class="h-4 w-4" />
          <span class="absolute right-1.5 top-1.5 h-2 w-2 rounded-full bg-rose-500 ring-2 ring-white" />
          <span class="pointer-events-none absolute -bottom-8 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-md bg-slate-900 px-2 py-1 text-xs text-white opacity-0 shadow-lg transition-opacity group-hover:opacity-100">Notifications</span>
        </button>

        <span class="h-full w-px bg-slate-200" />

        <button
          class="group relative flex h-full items-center px-4 text-slate-500 transition-colors hover:bg-[var(--accent-600)] hover:text-white"
          @click="signOut"
        >
          <LogOut class="h-4 w-4" />
          <span class="pointer-events-none absolute -bottom-8 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-md bg-slate-900 px-2 py-1 text-xs text-white opacity-0 shadow-lg transition-opacity group-hover:opacity-100">Logout</span>
        </button>
      </div>
    </header>

    <div class="flex flex-col md:flex-row">
      <aside
        class="relative flex flex-col border-r border-slate-200 bg-slate-50/50 transition-[width] duration-300 ease-in-out md:min-h-[calc(100vh-40px)]"
        :class="isCollapsed ? 'w-full md:w-14' : 'w-full md:w-64'"
      >
        <button
          class="absolute -right-3.5 top-10 z-40 hidden h-7 w-7 items-center justify-center rounded-full border border-slate-200 bg-[var(--accent-600)] text-white shadow-md transition-all hover:bg-[var(--accent-700)] hover:shadow-lg md:flex"
          :title="isCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
          @click="toggleSidebar"
        >
          <ChevronDown
            class="h-4 w-4 transition-transform duration-200"
            :class="isCollapsed ? '-rotate-90' : 'rotate-90'"
          />
        </button>

        <div
          v-if="site.sidebarLogoUrl"
          class="border-b border-slate-200 bg-white px-3 py-3"
          :class="isCollapsed ? 'md:hidden' : ''"
        >
          <div class="flex h-12 items-center justify-center overflow-hidden">
            <img :src="resolveUrl(site.sidebarLogoUrl)" alt="Sidebar logo" class="h-full w-full object-contain" />
          </div>
        </div>

        <!-- Project picker — top of sidebar -->
        <div class="border-b border-slate-200 bg-white" :class="isCollapsed ? 'md:flex md:justify-center md:px-0 md:py-2.5 px-3 py-2.5' : 'px-3 py-3'">
          <!-- Collapsed: icon only -->
          <div v-if="isCollapsed" class="flex justify-center">
            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-[var(--accent-100)]">
              <FolderKanban class="h-4 w-4 text-[var(--accent-600)]" />
            </div>
          </div>
          <!-- Expanded: full picker -->
          <div v-else>
            <p class="mb-1.5 text-[10px] font-semibold uppercase tracking-wider text-slate-400">Active Project</p>
            <div class="relative">
              <FolderKanban class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--accent-500)]" />
              <select
                :value="rtmfProjectStore.activeProjectId ?? ''"
                class="w-full appearance-none rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-9 pr-8 text-sm font-medium text-slate-800 shadow-sm focus:border-[var(--accent-400)] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[var(--accent-300)]"
                @change="switchProject(Number(($event.target as HTMLSelectElement).value))"
              >
                <option value="" disabled>Select project…</option>
                <option v-for="p in rtmfProjectStore.projects" :key="p.id" :value="p.id">{{ p.name }}</option>
              </select>
              <ChevronDown class="pointer-events-none absolute right-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            </div>
          </div>
        </div>

        <nav class="flex-1 p-3" :class="isCollapsed ? 'md:overflow-visible md:px-0 md:py-2' : ''">
          <div v-for="(group, gi) in finalMenu" :key="group.id">
            <p
              v-if="group.label"
              class="px-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400"
              :class="[gi === 0 ? 'mb-1' : 'mb-1 mt-4', isCollapsed ? 'md:hidden' : '']"
            >
              {{ group.label }}
            </p>

            <div v-for="item in group.items" :key="item.id" class="mb-0.5">
              <button
                v-if="item.children && item.children.length > 0"
                type="button"
                class="group relative flex w-full items-center rounded-lg text-left font-medium transition-all hover:bg-[var(--accent-50)]"
                :class="[
                  isCollapsed ? collapsedRowBaseClass : rowBaseClass,
                  isCollapsed && isNodeActive(item) ? 'md:border md:border-[var(--accent-200)] md:bg-[var(--accent-50)] md:text-[var(--accent-700)] md:font-medium text-slate-900' : 'text-slate-900',
                  isCollapsed ? '' : itemClass(isNodeActive(item) ? route.path : item.to)
                ]"
                @click="isCollapsed ? toggleSidebar() : toggleMenu(item.id)"
              >
                <component
                  :is="item.icon"
                  class="shrink-0 transition-colors"
                  :class="[
                    isCollapsed ? 'md:h-5 md:w-5 h-4 w-4' : 'h-4 w-4',
                    isCollapsed && isNodeActive(item) ? 'md:text-[var(--accent-700)] text-slate-700' : isNodeActive(item) ? 'text-slate-900' : 'text-slate-400 group-hover:text-[var(--accent-600)]'
                  ]"
                />
                <span class="flex-1 transition-colors" :class="[isCollapsed ? 'md:hidden' : '', isNodeActive(item) ? '' : 'group-hover:text-[var(--accent-700)]']">{{ item.label }}</span>
                <ChevronDown
                  class="h-4 w-4 text-slate-400 transition-transform duration-200"
                  :class="[{ '-rotate-90': !openMenus[item.id] }, isCollapsed ? 'md:hidden' : '']"
                />
                <span
                  v-if="isCollapsed"
                  class="pointer-events-none absolute left-full z-50 ml-2 hidden whitespace-nowrap rounded-md bg-slate-900 px-2 py-1 text-xs font-medium text-white opacity-0 shadow-lg transition-opacity group-hover:opacity-100 md:block"
                >
                  {{ item.label }}
                </span>
              </button>

              <router-link
                v-else
                :to="item.to"
                class="group relative flex items-center rounded-lg font-medium transition-all hover:bg-[var(--accent-50)]"
                :class="[
                  isCollapsed ? collapsedRowBaseClass : rowBaseClass,
                  isCollapsed && isActive(item.to) ? 'md:border md:border-[var(--accent-200)] md:bg-[var(--accent-50)] md:text-[var(--accent-700)] md:font-medium text-slate-900' : 'text-slate-900',
                  isCollapsed ? '' : itemClass(item.to)
                ]"
              >
                <component
                  :is="item.icon"
                  class="shrink-0 transition-colors"
                  :class="[
                    isCollapsed ? 'md:h-5 md:w-5 h-4 w-4' : 'h-4 w-4',
                    isCollapsed && isActive(item.to) ? 'md:text-[var(--accent-700)] text-slate-700' : isActive(item.to) ? 'text-slate-900' : 'text-slate-400 group-hover:text-[var(--accent-600)]'
                  ]"
                />
                <span class="flex-1 transition-colors" :class="[isCollapsed ? 'md:hidden' : '', isActive(item.to) ? '' : 'group-hover:text-[var(--accent-700)]']">{{ item.label }}</span>
                <span
                  v-if="isCollapsed"
                  class="pointer-events-none absolute left-full z-50 ml-2 hidden whitespace-nowrap rounded-md bg-slate-900 px-2 py-1 text-xs font-medium text-white opacity-0 shadow-lg transition-opacity group-hover:opacity-100 md:block"
                >
                  {{ item.label }}
                </span>
              </router-link>

              <div
                v-if="item.children && item.children.length > 0 && openMenus[item.id] && !isCollapsed"
                class="ml-5 mt-1 space-y-0.5 border-l-2 border-slate-200 pl-4"
              >
                <template v-for="child in item.children" :key="child.id">
                  <button
                    v-if="child.children && child.children.length > 0"
                    type="button"
                    class="flex w-full items-center rounded-md text-left transition-all hover:bg-[var(--accent-50)]"
                    :class="[childRowClass, childClass(isNodeActive(child) ? route.path : child.to)]"
                    @click="toggleMenu(child.id)"
                  >
                    <span class="flex-1">{{ child.label }}</span>
                    <ChevronDown
                      class="h-3.5 w-3.5 text-slate-400 transition-transform duration-200"
                      :class="{ '-rotate-90': !openMenus[child.id] }"
                    />
                  </button>

                  <router-link
                    v-else
                    :to="child.to"
                    :class="[childRowClass, childClass(child.to)]"
                  >
                    {{ child.label }}
                  </router-link>

                  <div
                    v-if="child.children && child.children.length > 0 && openMenus[child.id]"
                    class="ml-4 mt-1 space-y-0.5 border-l border-slate-200 pl-3"
                  >
                    <router-link
                      v-for="grandchild in child.children"
                      :key="grandchild.id"
                      :to="grandchild.to"
                      :class="[childRowClass, childClass(grandchild.to)]"
                    >
                      {{ grandchild.label }}
                    </router-link>
                  </div>
                </template>
              </div>
            </div>
          </div>
        </nav>

        <div
          v-if="site.footerText"
          class="border-t border-slate-200 px-3 py-2.5 transition-opacity duration-300"
          :class="isCollapsed ? 'md:hidden' : ''"
        >
          <p class="text-[11px] leading-relaxed text-slate-400">{{ site.footerText }}</p>
        </div>
      </aside>

      <main class="w-full min-w-0 flex-1 bg-white p-3 transition-all duration-300 ease-in-out md:p-4">
        <slot />
      </main>
    </div>

  </div>
</template>
