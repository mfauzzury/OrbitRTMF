import { createRouter, createWebHistory } from "vue-router";
import type { RouteLocationGeneric, RouteRecordRaw } from "vue-router";

import DashboardView from "@/views/DashboardView.vue";
import MainDashboardView from "@/views/MainDashboardView.vue";
import KitchenChartsView from "@/views/KitchenChartsView.vue";
import KitchenFormsView from "@/views/KitchenFormsView.vue";
import LoginView from "@/views/LoginView.vue";
import AdminMediaLibraryView from "@/views/AdminMediaLibraryView.vue";
import MediaLibraryView from "@/views/MediaLibraryView.vue";
import KitchenSinkView from "@/views/KitchenSinkView.vue";
import PageEditorView from "@/views/PageEditorView.vue";
import PagesListView from "@/views/PagesListView.vue";
import PostEditorView from "@/views/PostEditorView.vue";
import PostsListView from "@/views/PostsListView.vue";
import RtmfDashboardView from "@/views/RtmfDashboardView.vue";
import RtmfEditorView from "@/views/RtmfEditorView.vue";
import RtmfListView from "@/views/RtmfListView.vue";
import RtmfModulesListView from "@/views/RtmfModulesListView.vue";
import RtmfModuleEditorView from "@/views/RtmfModuleEditorView.vue";
import RtmfActorsListView from "@/views/RtmfActorsListView.vue";
import RtmfActorEditorView from "@/views/RtmfActorEditorView.vue";
import RtmfExportView from "@/views/RtmfExportView.vue";
import RtmfImportView from "@/views/RtmfImportView.vue";
import RtmfPageRelationsView from "@/views/RtmfPageRelationsView.vue";
import RtmfScenariosListView from "@/views/RtmfScenariosListView.vue";
import RtmfScenarioEditorView from "@/views/RtmfScenarioEditorView.vue";
import RtmfProjectsView from "@/views/RtmfProjectsView.vue";
import RtmfProjectMembersView from "@/views/RtmfProjectMembersView.vue";
import DefectReportingView from "@/views/DefectReportingView.vue";
import CrTrackingView from "@/views/CrTrackingView.vue";
import PageCatalogTrackingView from "@/views/PageCatalogTrackingView.vue";

import CategoriesListView from "@/views/CategoriesListView.vue";
import CategoryEditorView from "@/views/CategoryEditorView.vue";
import DatabaseSchemaView from "@/views/DatabaseSchemaView.vue";
import ChangelogView from "@/views/ChangelogView.vue";
import ToolsChangelogView from "@/views/ToolsChangelogView.vue";
import DevelopersGuideView from "@/views/DevelopersGuideView.vue";
import ApiManagementView from "@/views/ApiManagementView.vue";
import MenusView from "@/views/MenusView.vue";
import StorefrontMenuView from "@/views/StorefrontMenuView.vue";
import WebfrontSettingsView from "@/views/WebfrontSettingsView.vue";
import AuditLogsView from "@/views/AuditLogsView.vue";
import QueueMonitorView from "@/views/QueueMonitorView.vue";
import ComingSoonView from "@/views/ComingSoonView.vue";
import RolesView from "@/views/RolesView.vue";
import SettingsView from "@/views/SettingsView.vue";
import SystemInfoView from "@/views/SystemInfoView.vue";
import UsersView from "@/views/UsersView.vue";
import UserEditView from "@/views/UserEditView.vue";
import StorefrontHomeView from "@/views/StorefrontHomeView.vue";
import StorefrontPageView from "@/views/StorefrontPageView.vue";
import { useAuthStore } from "@/stores/auth";
import { useSiteStore } from "@/stores/site";
import { useRtmfProjectStore } from "@/stores/rtmfProject";

const legacyAdminPaths = [
  "/login",
  "/portal/dashboard",
  "/posts",
  "/posts/new",
  "/posts/:id",
  "/categories",
  "/categories/new",
  "/categories/:id",
  "/pages",
  "/pages/new",
  "/pages/:id",
  "/media",
  "/menus",
  "/webfront-menu",
  "/webfront-settings",
  "/storefront-menu",
  "/kitchen-sink",
  "/kitchen-sink/forms",
  "/kitchen-sink/charts",
  "/development/database-schema",
  "/development/api-management",
  "/profile",
  "/settings",
  "/settings/users",
  "/settings/users/new",
  "/settings/users/:id",
  "/settings/roles",
  "/settings/audit-logs",
  "/settings/queue-monitor",
  "/settings/system",
];

// Backward-compat redirects: old /admin/settings/* → new /admin/platform/* paths
const settingsRedirects: RouteRecordRaw[] = [
  { path: "/admin/settings/users", redirect: "/admin/platform/identity/users" },
  { path: "/admin/settings/users/new", redirect: "/admin/platform/identity/users/new" },
  { path: "/admin/settings/users/:id", redirect: (to: RouteLocationGeneric) => `/admin/platform/identity/users/${String(to.params.id ?? "")}` },
  { path: "/admin/settings/roles", redirect: "/admin/platform/identity/roles" },
  { path: "/admin/settings/audit-logs", redirect: "/admin/platform/observability/audit-trail" },
  { path: "/admin/settings/queue-monitor", redirect: "/admin/platform/queue" },
];

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: "/admin/login", name: "login", component: LoginView, meta: { guestOnly: true, title: "Login" } },
    { path: "/admin", name: "main-dashboard", component: MainDashboardView, meta: { requiresAuth: true, title: "My Task" } },
    { path: "/admin/portal/dashboard", name: "dashboard", component: DashboardView, meta: { requiresAuth: true, title: "Dashboard" } },
    { path: "/admin/posts", name: "posts", component: PostsListView, meta: { requiresAuth: true, title: "Posts" } },
    { path: "/admin/posts/new", name: "post-create", component: PostEditorView, meta: { requiresAuth: true, title: "New Post" } },
    { path: "/admin/posts/:id", name: "post-edit", component: PostEditorView, meta: { requiresAuth: true, title: "Edit Post" } },
    { path: "/admin/categories", name: "categories", component: CategoriesListView, meta: { requiresAuth: true, title: "Categories" } },
    { path: "/admin/categories/new", name: "category-create", component: CategoryEditorView, meta: { requiresAuth: true, title: "New Category" } },
    { path: "/admin/categories/:id", name: "category-edit", component: CategoryEditorView, meta: { requiresAuth: true, title: "Edit Category" } },
    { path: "/admin/pages", name: "pages", component: PagesListView, meta: { requiresAuth: true, title: "Pages" } },
    { path: "/admin/pages/new", name: "page-create", component: PageEditorView, meta: { requiresAuth: true, title: "New Page" } },
    { path: "/admin/pages/:id", name: "page-edit", component: PageEditorView, meta: { requiresAuth: true, title: "Edit Page" } },
    { path: "/admin/media", name: "media", component: MediaLibraryView, meta: { requiresAuth: true, title: "Media" } },
    { path: "/admin/webfront-menu", name: "storefront-menu", component: StorefrontMenuView, meta: { requiresAuth: true, title: "Menus" } },
    { path: "/admin/storefront-menu", redirect: "/admin/webfront-menu" },
    { path: "/admin/webfront-settings", name: "webfront-settings", component: WebfrontSettingsView, meta: { requiresAuth: true, title: "Settings" } },
    { path: "/admin/menus", name: "menus", component: MenusView, meta: { requiresAuth: true, title: "Menus" } },
    { path: "/admin/rtmf", redirect: "/admin/rtmf/dashboard" },
    { path: "/admin/rtmf/projects", name: "rtmf-projects", component: RtmfProjectsView, meta: { requiresAuth: true, title: "Projects — Page Catalog" } },
    { path: "/admin/rtmf/projects/:id/members", name: "rtmf-project-members", component: RtmfProjectMembersView, meta: { requiresAuth: true, title: "Project Members" } },
    { path: "/admin/rtmf/dashboard", name: "rtmf-dashboard", component: RtmfDashboardView, meta: { requiresAuth: true, title: "Page Catalog Dashboard" } },
    { path: "/admin/rtmf/frontends", name: "rtmf-frontends", component: RtmfListView, meta: { requiresAuth: true, title: "Pages" } },
    { path: "/admin/rtmf/frontends/new", name: "rtmf-frontend-create", component: RtmfEditorView, meta: { requiresAuth: true, title: "New Page" } },
    { path: "/admin/rtmf/frontends/:id", name: "rtmf-frontend-edit", component: RtmfEditorView, meta: { requiresAuth: true, title: "Edit Page" } },
    { path: "/admin/rtmf/modules", name: "rtmf-modules", component: RtmfModulesListView, meta: { requiresAuth: true, title: "Modules" } },
    { path: "/admin/rtmf/modules/new", name: "rtmf-module-create", component: RtmfModuleEditorView, meta: { requiresAuth: true, title: "New Module" } },
    { path: "/admin/rtmf/modules/:id", name: "rtmf-module-edit", component: RtmfModuleEditorView, meta: { requiresAuth: true, title: "Edit Module" } },
    { path: "/admin/rtmf/actors", name: "rtmf-actors", component: RtmfActorsListView, meta: { requiresAuth: true, title: "Actors" } },
    { path: "/admin/rtmf/actors/new", name: "rtmf-actor-create", component: RtmfActorEditorView, meta: { requiresAuth: true, title: "New Actor" } },
    { path: "/admin/rtmf/actors/:id", name: "rtmf-actor-edit", component: RtmfActorEditorView, meta: { requiresAuth: true, title: "Edit Actor" } },
    { path: "/admin/rtmf/export", name: "rtmf-export", component: RtmfExportView, meta: { requiresAuth: true, title: "Export" } },
    { path: "/admin/rtmf/import", name: "rtmf-import", component: RtmfImportView, meta: { requiresAuth: true, title: "Import" } },
    { path: "/admin/rtmf/relations", name: "rtmf-page-relations", component: RtmfPageRelationsView, meta: { requiresAuth: true, title: "Page Relations — RTMF" } },
    { path: "/admin/rtmf/scenarios", name: "rtmf-scenarios", component: RtmfScenariosListView, meta: { requiresAuth: true, title: "Flow Scenarios — RTMF" } },
    { path: "/admin/rtmf/scenarios/new", name: "rtmf-scenario-create", component: RtmfScenarioEditorView, meta: { requiresAuth: true, title: "New Scenario — RTMF" } },
    { path: "/admin/rtmf/scenarios/:id", name: "rtmf-scenario-edit", component: RtmfScenarioEditorView, meta: { requiresAuth: true, title: "Edit Scenario — RTMF" } },

    { path: "/admin/defects", name: "defect-reporting", component: DefectReportingView, meta: { requiresAuth: true, title: "Defect Reporting" } },
    { path: "/admin/cr",      name: "cr-tracking",      component: CrTrackingView,      meta: { requiresAuth: true, title: "CR Tracking" } },
    { path: "/admin/catalog-tracking", name: "catalog-tracking", component: PageCatalogTrackingView, meta: { requiresAuth: true, title: "Page Catalog Tracking" } },

    // ── Project-scoped routes (Option B URLs) ──────────────────────────────
    { path: "/admin/rtmf/projects/:projectId/dashboard",       name: "project-dashboard",       component: RtmfDashboardView,       meta: { requiresAuth: true, title: "Dashboard" } },
    { path: "/admin/rtmf/projects/:projectId/frontends",       name: "project-frontends",       component: RtmfListView,            meta: { requiresAuth: true, title: "Pages" } },
    { path: "/admin/rtmf/projects/:projectId/frontends/new",   name: "project-frontend-create", component: RtmfEditorView,          meta: { requiresAuth: true, title: "New Page" } },
    { path: "/admin/rtmf/projects/:projectId/frontends/:id",   name: "project-frontend-edit",   component: RtmfEditorView,          meta: { requiresAuth: true, title: "Edit Page" } },
    { path: "/admin/rtmf/projects/:projectId/modules",         name: "project-modules",         component: RtmfModulesListView,     meta: { requiresAuth: true, title: "Modules" } },
    { path: "/admin/rtmf/projects/:projectId/modules/new",     name: "project-module-create",   component: RtmfModuleEditorView,    meta: { requiresAuth: true, title: "New Module" } },
    { path: "/admin/rtmf/projects/:projectId/modules/:id",     name: "project-module-edit",     component: RtmfModuleEditorView,    meta: { requiresAuth: true, title: "Edit Module" } },
    { path: "/admin/rtmf/projects/:projectId/actors",          name: "project-actors",          component: RtmfActorsListView,      meta: { requiresAuth: true, title: "Actors" } },
    { path: "/admin/rtmf/projects/:projectId/actors/new",      name: "project-actor-create",    component: RtmfActorEditorView,     meta: { requiresAuth: true, title: "New Actor" } },
    { path: "/admin/rtmf/projects/:projectId/actors/:id",      name: "project-actor-edit",      component: RtmfActorEditorView,     meta: { requiresAuth: true, title: "Edit Actor" } },
    { path: "/admin/rtmf/projects/:projectId/scenarios",       name: "project-scenarios",       component: RtmfScenariosListView,   meta: { requiresAuth: true, title: "Flow Scenarios" } },
    { path: "/admin/rtmf/projects/:projectId/scenarios/new",   name: "project-scenario-create", component: RtmfScenarioEditorView,  meta: { requiresAuth: true, title: "New Scenario" } },
    { path: "/admin/rtmf/projects/:projectId/scenarios/:id",   name: "project-scenario-edit",   component: RtmfScenarioEditorView,  meta: { requiresAuth: true, title: "Edit Scenario" } },
    { path: "/admin/rtmf/projects/:projectId/relations",       name: "project-relations",       component: RtmfPageRelationsView,   meta: { requiresAuth: true, title: "Page Relations" } },
    { path: "/admin/rtmf/projects/:projectId/import",          name: "project-import",          component: RtmfImportView,          meta: { requiresAuth: true, title: "Import" } },
    { path: "/admin/rtmf/projects/:projectId/export",          name: "project-export",          component: RtmfExportView,          meta: { requiresAuth: true, title: "Export" } },
    { path: "/admin/rtmf/projects/:projectId/defects",         name: "project-defects",         component: DefectReportingView,     meta: { requiresAuth: true, title: "Defects" } },
    { path: "/admin/rtmf/projects/:projectId/cr",              name: "project-cr",              component: CrTrackingView,          meta: { requiresAuth: true, title: "Change Requests" } },
    { path: "/admin/rtmf/projects/:projectId/tracking",        name: "project-tracking",        component: PageCatalogTrackingView, meta: { requiresAuth: true, title: "Catalog Tracking" } },

    // ── Legacy flat-route redirects → active project ────────────────────
    { path: "/admin/rtmf-frontends",     redirect: "/admin/rtmf/frontends" },
    { path: "/admin/rtmf-frontends/new", redirect: "/admin/rtmf/frontends/new" },
    { path: "/admin/rtmf-frontends/:id", redirect: (to: RouteLocationGeneric) => `/admin/rtmf/frontends/${String(to.params.id ?? "")}` },
    { path: "/admin/kitchen-sink", name: "kitchen-sink", component: KitchenSinkView, meta: { requiresAuth: true, title: "Kitchen Sink" } },
    { path: "/admin/kitchen-sink/forms", name: "kitchen-forms", component: KitchenFormsView, meta: { requiresAuth: true, title: "Forms" } },
    { path: "/admin/kitchen-sink/charts", name: "kitchen-charts", component: KitchenChartsView, meta: { requiresAuth: true, title: "Charts" } },
    { path: "/admin/development/changelog", name: "changelog", component: ChangelogView, meta: { requiresAuth: true, title: "Changelog" } },
    { path: "/admin/tools/changelog", name: "tools-changelog", component: ToolsChangelogView, meta: { requiresAuth: true, requiresAdmin: true, title: "Changelog" } },
    { path: "/admin/development/developers-guide", name: "developers-guide", component: DevelopersGuideView, meta: { requiresAuth: true, title: "Developers Guide" } },
    { path: "/admin/development/database-schema", name: "database-schema", component: DatabaseSchemaView, meta: { requiresAuth: true, title: "Database Schema" } },
    { path: "/admin/development/api-explorer", name: "api-explorer", component: ApiManagementView, meta: { requiresAuth: true, title: "API Explorer" } },
    { path: "/admin/development/api-management", redirect: "/admin/development/api-explorer" },
    {
      path: "/admin/profile",
      name: "profile",
      meta: { requiresAuth: true },
      beforeEnter: async () => {
        const auth = useAuthStore();
        await auth.initialize();
        if (auth.user?.id) return `/admin/platform/identity/users/${auth.user.id}`;
        return { name: "login" };
      },
      component: { template: "" },
    },

    // ── Administration ──
    { path: "/admin/administration/media-library", name: "admin-media-library", component: AdminMediaLibraryView, meta: { requiresAuth: true, requiresAdmin: true, title: "Media Library" } },
    { path: "/admin/settings", name: "settings", component: SettingsView, meta: { requiresAuth: true, title: "Settings" } },
    { path: "/admin/settings/system", name: "settings-system", component: SystemInfoView, meta: { requiresAuth: true, title: "System Info" } },

    // ── Core Platform: Identity & Access ──
    { path: "/admin/platform/identity", redirect: "/admin/platform/identity/users" },
    { path: "/admin/platform/identity/users", name: "platform-users", component: UsersView, meta: { requiresAuth: true, title: "Users" } },
    { path: "/admin/platform/identity/users/new", name: "platform-user-create", component: UserEditView, meta: { requiresAuth: true, title: "New User" } },
    { path: "/admin/platform/identity/users/:id", name: "platform-user-edit", component: UserEditView, meta: { requiresAuth: true, title: "Edit User" } },
    { path: "/admin/platform/identity/roles", name: "platform-rbac", component: RolesView, meta: { requiresAuth: true, title: "RBAC" } },
    { path: "/admin/platform/identity/tokens", name: "platform-tokens", component: ComingSoonView, meta: { requiresAuth: true, title: "Token Management" } },

    // ── Core Platform: Observability (Grafana) ──
    { path: "/admin/platform/observability", redirect: "/admin/platform/observability/audit-trail" },
    { path: "/admin/platform/observability/audit-trail", name: "platform-audit-trail", component: AuditLogsView, meta: { requiresAuth: true, title: "Audit Trail" } },
    { path: "/admin/platform/observability/activity-log", name: "platform-activity-log", component: ComingSoonView, meta: { requiresAuth: true, title: "Activity Log" } },
    { path: "/admin/platform/observability/logging", name: "platform-logging", component: ComingSoonView, meta: { requiresAuth: true, title: "Logging" } },
    { path: "/admin/platform/observability/errors", name: "platform-error-tracking", component: ComingSoonView, meta: { requiresAuth: true, title: "Error Tracking" } },
    { path: "/admin/platform/observability/monitoring", name: "platform-monitoring", component: ComingSoonView, meta: { requiresAuth: true, title: "Monitoring" } },

    // ── Core Platform: Queue (Laravel Queue) ──
    { path: "/admin/platform/queue", name: "platform-queue", component: QueueMonitorView, meta: { requiresAuth: true, title: "Queue" } },
    { path: "/admin/platform/queue/failed", name: "platform-queue-failed", component: ComingSoonView, meta: { requiresAuth: true, title: "Failed Jobs" } },
    { path: "/admin/platform/queue/scheduled", name: "platform-queue-scheduled", component: ComingSoonView, meta: { requiresAuth: true, title: "Scheduled Jobs" } },

    // ── Core Platform: Messaging ──
    { path: "/admin/platform/messaging", redirect: "/admin/platform/messaging/event-bus" },
    { path: "/admin/platform/messaging/event-bus", name: "platform-event-bus", component: ComingSoonView, meta: { requiresAuth: true, title: "Event Bus" } },
    { path: "/admin/platform/messaging/notifications", name: "platform-notifications", component: ComingSoonView, meta: { requiresAuth: true, title: "Notifications" } },

    // ── Backward-compat redirects from old governance/communication paths ──
    { path: "/admin/platform/governance", redirect: "/admin/platform/observability/audit-trail" },
    { path: "/admin/platform/governance/audit-trail", redirect: "/admin/platform/observability/audit-trail" },
    { path: "/admin/platform/governance/activity-log", redirect: "/admin/platform/observability/activity-log" },
    { path: "/admin/platform/communication", redirect: "/admin/platform/messaging/notifications" },
    { path: "/admin/platform/communication/notifications", redirect: "/admin/platform/messaging/notifications" },
    { path: "/admin/platform/messaging/queue", redirect: "/admin/platform/queue" },
    { path: "/admin/platform/messaging/queue/failed", redirect: "/admin/platform/queue/failed" },
    { path: "/admin/platform/messaging/queue/scheduled", redirect: "/admin/platform/queue/scheduled" },

    // ── Core Platform: System Management ──
    { path: "/admin/platform/system", redirect: "/admin/platform/system/configuration" },
    { path: "/admin/platform/system/configuration", name: "platform-config", component: ComingSoonView, meta: { requiresAuth: true, title: "Configuration" } },
    { path: "/admin/platform/system/feature-flags", name: "platform-feature-flags", component: ComingSoonView, meta: { requiresAuth: true, title: "Feature Flags" } },
    { path: "/admin/platform/system/scheduler", name: "platform-scheduler", component: ComingSoonView, meta: { requiresAuth: true, title: "Scheduler" } },

    // ── Core Platform: Storage ──
    { path: "/admin/platform/storage", redirect: "/admin/platform/storage/media" },
    { path: "/admin/platform/storage/media", name: "platform-file-media", component: ComingSoonView, meta: { requiresAuth: true, title: "File / Media Management" } },

    // ── Core Platform: API Gateway (APISIX) ──
    { path: "/admin/platform/gateway", redirect: "/admin/platform/gateway/routes" },
    { path: "/admin/platform/gateway/routes", name: "platform-gateway-routes", component: ComingSoonView, meta: { requiresAuth: true, title: "Routes" } },
    { path: "/admin/platform/gateway/upstreams", name: "platform-gateway-upstreams", component: ComingSoonView, meta: { requiresAuth: true, title: "Upstreams" } },
    { path: "/admin/platform/gateway/consumers", name: "platform-gateway-consumers", component: ComingSoonView, meta: { requiresAuth: true, title: "Consumers" } },
    { path: "/admin/platform/gateway/plugins", name: "platform-gateway-plugins", component: ComingSoonView, meta: { requiresAuth: true, title: "Plugins" } },
    { path: "/admin/platform/gateway/ssl", name: "platform-gateway-ssl", component: ComingSoonView, meta: { requiresAuth: true, title: "SSL Certificates" } },
    { path: "/admin/platform/gateway/webhooks", name: "platform-webhooks", component: ComingSoonView, meta: { requiresAuth: true, title: "Webhooks" } },

    // ── Backward-compat redirects from old integration paths ──
    { path: "/admin/platform/integration", redirect: "/admin/platform/gateway/routes" },
    { path: "/admin/platform/integration/api", redirect: "/admin/platform/gateway/routes" },
    { path: "/admin/platform/integration/webhooks", redirect: "/admin/platform/gateway/webhooks" },

    // ── Core Platform: AI Integration ──
    { path: "/admin/platform/ai", redirect: "/admin/platform/ai/providers" },
    { path: "/admin/platform/ai/providers", name: "platform-ai-providers", component: ComingSoonView, meta: { requiresAuth: true, title: "AI Providers" } },
    { path: "/admin/platform/ai/models", name: "platform-ai-models", component: ComingSoonView, meta: { requiresAuth: true, title: "AI Models" } },
    { path: "/admin/platform/ai/prompts", name: "platform-ai-prompts", component: ComingSoonView, meta: { requiresAuth: true, title: "Prompt Templates" } },
    { path: "/admin/platform/ai/usage", name: "platform-ai-usage", component: ComingSoonView, meta: { requiresAuth: true, title: "AI Usage & Billing" } },

    // ── Backward-compat redirects from old settings paths ──
    ...settingsRedirects,

    ...legacyAdminPaths.map<RouteRecordRaw>((path) => ({
      path,
      redirect: (to: RouteLocationGeneric) => `/admin${to.fullPath}`,
    })),

    { path: "/", name: "storefront-home", component: StorefrontHomeView, meta: { title: "Webfront" } },
    { path: "/:slug", name: "storefront-page", component: StorefrontPageView, meta: { title: "Webfront" } },
  ],
});

router.beforeEach(async (to) => {
  const auth = useAuthStore();
  const rtmfProjectStore = useRtmfProjectStore();
  await auth.initialize();

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: "login" };
  }

  if (to.meta.guestOnly && auth.isAuthenticated) {
    return { name: "main-dashboard" };
  }

  if (to.meta.requiresAdmin && !auth.isAdmin) {
    return { name: "main-dashboard" };
  }

  if (auth.isAuthenticated && !auth.isAdmin) {
    // Block admin-only sections for non-admin users
    const adminOnlyPrefixes = [
      "/admin/platform/",
      "/admin/rtmf/projects",   // Setup — projects list/management
      "/admin/tools/",
      "/admin/administration/",
      "/admin/settings",
      "/admin/posts",
      "/admin/pages",
      "/admin/media",
      "/admin/categories",
      "/admin/menus",
      "/admin/webfront",
    ];
    if (adminOnlyPrefixes.some((p) => to.path.startsWith(p))) {
      return { name: "main-dashboard" };
    }

    // Project-scoped route: verify user is a member of the requested project
    const projectId = to.params.projectId ? Number(to.params.projectId) : null;
    if (projectId) {
      await rtmfProjectStore.loadProjects();
      const isMember = rtmfProjectStore.projects.some((p) => p.id === projectId);
      if (!isMember) return { name: "main-dashboard" };

      // Set active project from URL
      rtmfProjectStore.setActive(projectId);
    }
  }

  return true;
});

router.afterEach((to) => {
  const site = useSiteStore();
  const pageTitle = (to.meta.title as string) || "Admin";
  site.load().then(() => {
    site.setDocumentTitle(pageTitle);
  });
});

export default router;
