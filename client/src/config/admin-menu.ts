import type { Component } from "vue";
import {
  AppWindow,
  BookOpen,
  Bug,
  Cable,
  Database,
  FileSpreadsheet,
  FileInput,
  FileClock,
  FileText,
  FolderKanban,
  Gauge,
  GitBranch,
  GitPullRequest,
  HardDrive,
  Image,
  BarChart2,
  LayoutDashboard,
  LayoutGrid,
  Layers,
  Link2,
  Menu,
  PieChart,
  ScrollText,
  Settings,
  Shield,
  Users,
} from "lucide-vue-next";

export type MenuNode = {
  id: string;
  label: string;
  to: string;
  children?: MenuNode[];
};

export type MenuItemDef = MenuNode & {
  icon: Component;
  adminOnly?: boolean;
};

export type MenuGroupDef = {
  id: string;
  label: string;
  items: MenuItemDef[];
  requiredPermissions?: string[];
};

export type AdminMenuPrefs = {
  groupOrder: string[];
  itemOrder: Record<string, string[]>;
  childOrder: Record<string, string[]>;
  grandchildOrder: Record<string, string[]>;
  hidden: string[];
  hiddenChildren: string[];
  hiddenGrandchildren: string[];
  hiddenGroups: string[];
};

export const DEFAULT_MENU: MenuGroupDef[] = [
  {
    id: "dashboard",
    label: "",
    items: [
      { id: "main-dashboard", label: "My Task", to: "/admin", icon: Gauge },
    ],
  },
  {
    id: "portal",
    label: "Webfront",
    requiredPermissions: ["posts.view"],
    items: [
      { id: "dashboard", label: "Dashboard", to: "/admin/portal/dashboard", icon: Gauge },
      {
        id: "posts",
        label: "Posts",
        to: "/admin/posts",
        icon: FileText,
        children: [
          { id: "posts-all", label: "All Posts", to: "/admin/posts" },
          { id: "posts-new", label: "Add New", to: "/admin/posts/new" },
          { id: "posts-categories", label: "Categories", to: "/admin/categories" },
        ],
      },
      {
        id: "pages",
        label: "Pages",
        to: "/admin/pages",
        icon: FileText,
        children: [
          { id: "pages-all", label: "All Pages", to: "/admin/pages" },
          { id: "pages-new", label: "Add New", to: "/admin/pages/new" },
        ],
      },
      {
        id: "media",
        label: "Media",
        to: "/admin/media",
        icon: Image,
        children: [{ id: "media-library", label: "Library", to: "/admin/media" }],
      },
      { id: "storefront-menu", label: "Menus", to: "/admin/webfront-menu", icon: Link2 },
      { id: "webfront-settings", label: "Settings", to: "/admin/webfront-settings", icon: Settings },
    ],
  },
  {
    id: "rtmf-setup",
    label: "Setup",
    requiredPermissions: ["rtmf.manage"],
    items: [
      { id: "rtmf-projects", label: "Projects", to: "/admin/rtmf/projects", icon: FolderKanban },
    ],
  },
  {
    id: "administration",
    label: "Administration",
    requiredPermissions: ["users.view"],
    items: [
      {
        id: "platform-auth",
        label: "Authentication",
        to: "/admin/platform/identity/users",
        icon: Shield,
        children: [
          { id: "platform-users-all", label: "All Users", to: "/admin/platform/identity/users" },
          { id: "platform-users-new", label: "Add User", to: "/admin/platform/identity/users/new" },
        ],
      },
      { id: "platform-rbac", label: "RBAC", to: "/admin/platform/identity/roles", icon: PieChart },
      { id: "admin-media-library", label: "Media Library", to: "/admin/administration/media-library", icon: HardDrive, adminOnly: true },
      { id: "menus", label: "Menus", to: "/admin/menus", icon: Menu },
      {
        id: "settings",
        label: "Settings",
        to: "/admin/settings",
        icon: Settings,
        children: [
          { id: "settings-general", label: "General", to: "/admin/settings" },
          { id: "settings-system", label: "System", to: "/admin/settings/system" },
        ],
      },
    ],
  },
  {
    id: "rtmf",
    label: "Page Catalog",
    requiredPermissions: ["rtmf.catalog"],
    items: [
      { id: "rtmf-dashboard",        label: "Dashboard",      to: "/admin/rtmf/dashboard",    icon: LayoutDashboard },
      { id: "rtmf-frontends", label: "Pages", to: "/admin/rtmf/frontends", icon: AppWindow },
      { id: "rtmf-modules", label: "Module", to: "/admin/rtmf/modules", icon: Layers },
      { id: "rtmf-actors", label: "Actor", to: "/admin/rtmf/actors", icon: Users },
      {
        id: "rtmf-flow-scenarios",
        label: "Flow Scenarios",
        to: "/admin/rtmf/scenarios",
        icon: GitBranch,
        children: [
          { id: "rtmf-scenarios", label: "Custom Flow",    to: "/admin/rtmf/scenarios" },
          { id: "rtmf-relations", label: "Page Relations", to: "/admin/rtmf/relations" },
        ],
      },
    ],
  },
  {
    id: "tools",
    label: "Tools",
    requiredPermissions: ["rtmf.tools"],
    items: [
      { id: "rtmf-import",      label: "Import",    to: "/admin/rtmf/import",      icon: FileInput },
      { id: "rtmf-export",      label: "Export",    to: "/admin/rtmf/export",      icon: FileSpreadsheet },
      { id: "tools-changelog",  label: "Changelog", to: "/admin/tools/changelog",  icon: ScrollText, adminOnly: true },
    ],
  },
  {
    id: "defect-tracking",
    label: "Project Tracker",
    requiredPermissions: ["rtmf.tracker"],
    items: [
      { id: "defect-reporting", label: "Defects",              to: "/admin/defects",          icon: Bug },
      { id: "cr-tracking",      label: "Change Request (CR)",  to: "/admin/cr",               icon: GitPullRequest },
      { id: "catalog-tracking", label: "Page Catalog",         to: "/admin/catalog-tracking", icon: BarChart2 },
    ],
  },
  {
    id: "development",
    label: "Development",
    items: [
      { id: "changelog", label: "Changelog", to: "/admin/development/changelog", icon: FileClock },
      { id: "developers-guide", label: "Developers Guide", to: "/admin/development/developers-guide", icon: BookOpen },
      { id: "database-schema", label: "Database Schema", to: "/admin/development/database-schema", icon: Database },
      { id: "api-explorer", label: "API Explorer", to: "/admin/development/api-explorer", icon: Cable },
      {
        id: "kitchen-sink",
        label: "Kitchen Sink",
        to: "/admin/kitchen-sink",
        icon: LayoutGrid,
        children: [
          { id: "kitchen-components", label: "Components", to: "/admin/kitchen-sink" },
          { id: "kitchen-forms", label: "Forms", to: "/admin/kitchen-sink/forms" },
          { id: "kitchen-charts", label: "Charts", to: "/admin/kitchen-sink/charts" },
        ],
      },
    ],
  },
];
