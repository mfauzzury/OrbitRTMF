export type PublishStatus = "draft" | "published" | "archived";

export type RtmfSnapshotStatus = "ok" | "not_found" | "error" | null;

export type RtmfSubModule = {
  id: number;
  moduleId: number;
  parentId?: number | null;
  code: string;
  name: string;
  description?: string | null;
  sortOrder: number;
  createdAt?: string;
  updatedAt?: string;
};

export type RtmfSubModulePhoto = {
  id: number;
  rtmfSubModuleId: number;
  filename: string;
  originalName: string;
  mimeType: string;
  size: number;
  url: string;
  createdAt: string;
};

export type RtmfModulePhoto = {
  id: number;
  rtmfModuleId: number;
  filename: string;
  originalName: string;
  mimeType: string;
  size: number;
  url: string;
  createdAt: string;
};

export type RtmfModule = {
  id: number;
  code: string;
  name: string;
  description?: string | null;
  sortOrder: number;
  frontendsCount?: number;
  subModulesCount?: number;
  subModules?: RtmfSubModule[];
  createdAt?: string;
  updatedAt?: string;
};

export type RtmfActor = {
  id: number;
  name: string;
  description?: string | null;
  sortOrder: number;
  frontendsCount?: number;
  createdAt?: string;
  updatedAt?: string;
};


export type RtmfFrontendItemStatus = "implemented" | "partial" | "missing";

export type RtmfFrontendItem = {
  id: number;
  rtmfFrontendId: number;
  idFr: string | null;
  type: string | null;
  label: string | null;
  condition: string | null;
  validation: string | null;
  mandatory: boolean;
  screenName: string | null;
  tableFieldname: string | null;
  status: RtmfFrontendItemStatus | null;
  sortOrder: number;
  createdAt: string;
  updatedAt: string;
};

export type RtmfFrontendItemInput = Partial<Omit<RtmfFrontendItem, "id" | "rtmfFrontendId" | "createdAt" | "updatedAt">>;

export type RtmfFrontendScenarioRow = {
  id: number;
  rtmfFrontendScenarioGroupId: number;
  step: string | null;
  fasa: string | null;
  role: string | null;
  aktiviti: string | null;
  sortOrder: number;
  createdAt: string;
  updatedAt: string;
};

export type RtmfFrontendScenarioRowInput = Partial<
  Omit<RtmfFrontendScenarioRow, "id" | "rtmfFrontendScenarioGroupId" | "createdAt" | "updatedAt">
>;

export type RtmfFrontendScenarioGroup = {
  id: number;
  rtmfFrontendId: number;
  title: string | null;
  description: string | null;
  sortOrder: number;
  rows: RtmfFrontendScenarioRow[];
  createdAt: string;
  updatedAt: string;
};

export type RtmfFrontendScenarioGroupInput = Partial<
  Omit<RtmfFrontendScenarioGroup, "id" | "rtmfFrontendId" | "rows" | "createdAt" | "updatedAt">
>;

export type RtmfAttachment = {
  id: number;
  rtmfFrontendId: number;
  label: string | null;
  filename: string;
  originalName: string;
  mimeType: string;
  size: number;
  url: string;
  createdAt: string;
};

export type RtmfDashboardModuleStat = {
  id: number;
  code: string;
  name: string;
  frontendsCount: number;
  doneCount: number;
  itemsCount: number;
  implementedCount: number;
};

export type RtmfDashboardActorStat = {
  id: number;
  name: string;
  frontendsCount: number;
};

export type RtmfDashboardSummary = {
  totals: {
    frontends: number;
    done: number;
    modules: number;
    actors: number;
    items: number;
    scenarios: number;
  };
  itemsByStatus: {
    implemented: number;
    partial: number;
    missing: number;
    unset: number;
  };
  byModule: RtmfDashboardModuleStat[];
  byActor: RtmfDashboardActorStat[];
};

export type RtmfFrontendLink = {
  id: number;
  specId: string;
  title: string;
};

export type RtmfFrontendInput = {
  specId: string;
  moduleId: number;
  subModuleId?: number | null;
  actorIds?: number[];
  fromIds?: number[];
  toIds?: number[];
  assignees?: RtmfFrontendAssignee[];
  isDone?: boolean;
  vuePath?: string | null;
  liveUrl?: string | null;
  tabCode?: string | null;
  title: string;
  businessRequirement?: string | null;
  stakeholderRequirement?: string | null;
  description?: string | null;
};

export type RtmfFrontendAssignee = {
  id: number | string;
  name: string;
  email?: string | null;
  photoUrl?: string | null;
  source?: 'local' | 'external';
};

export type RtmfFrontend = RtmfFrontendInput & {
  id: number;
  createdAt: string;
  updatedAt: string;
  deletedAt?: string | null;
  module?: RtmfModule;
  subModule?: RtmfSubModule | null;
  actors?: RtmfActor[];
  assignees?: RtmfFrontendAssignee[];
  linksFrom?: RtmfFrontendLink[];
  linksTo?: RtmfFrontendLink[];
  snapshotStatus?: RtmfSnapshotStatus;
  snapshotCapturedAt?: string | null;
};

export type ThemeColor = "violet" | "blue" | "green" | "red" | "black-white" | "grey";

export type ApiError = { error: { code: string; message: string; details?: unknown } };

export type ApiResponse<T> = { data: T; meta?: Record<string, unknown> };

export type User = {
  id: number;
  email: string;
  name: string;
  photoUrl?: string;
  role?: string;
};

export type PostInput = {
  title: string;
  slug?: string;
  excerpt?: string;
  content: string;
  status: PublishStatus;
  featuredImageId?: number | null;
  categoryIds?: number[];
};

export type Post = PostInput & {
  id: number;
  slug: string;
  publishedAt: string | null;
  createdAt: string;
  updatedAt: string;
  featuredImage?: Media | null;
  categories?: Category[];
};

export type CategoryInput = {
  name: string;
  slug?: string;
  description?: string;
};

export type Category = {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  createdAt: string;
  updatedAt: string;
  _count?: { posts: number };
};

export type PageInput = {
  title: string;
  slug?: string;
  content: string;
  status: PublishStatus;
  featuredImageId?: number | null;
};

export type Page = PageInput & {
  id: number;
  slug: string;
  publishedAt: string | null;
  createdAt: string;
  updatedAt: string;
  featuredImage?: Media | null;
};

export type Media = {
  id: number;
  filename: string;
  originalName: string;
  title: string | null;
  caption: string | null;
  description: string | null;
  mimeType: string;
  size: number;
  width: number | null;
  height: number | null;
  altText: string | null;
  path: string;
  url: string;
  createdAt: string;
};

export type MediaMetadataInput = {
  title: string;
  altText: string;
  caption: string;
  description: string;
};

export type SettingsPayload = {
  siteTitle: string;
  tagline: string;
  webfrontTitle: string;
  webfrontTagline: string;
  titleFormat: string;
  metaDescription: string;
  siteIconUrl: string;
  webfrontLogoUrl: string;
  sidebarLogoUrl: string;
  faviconUrl: string;
  language: string;
  timezone: string;
  footerText: string;
  frontPageId: number | null;
};

export type PublicSiteSettings = Pick<
  SettingsPayload,
  "siteTitle" | "tagline" | "webfrontTitle" | "webfrontTagline" | "metaDescription" | "footerText" | "siteIconUrl" | "webfrontLogoUrl" | "sidebarLogoUrl" | "faviconUrl"
> & {
  storefrontMenu: StorefrontMenuItem[];
};

export type StorefrontMenuItem = {
  id: string;
  label: string;
  href: string;
  parentId: string | null;
  openInNewTab: boolean;
};

export type Role = {
  id: number;
  name: string;
  description: string;
  permissions: string[];
  createdAt: string;
  updatedAt: string;
};

export type RoleInput = {
  name: string;
  description: string;
  permissions: string[];
};

export type UserDetail = {
  id: number;
  name: string;
  email: string;
  role: string;
  photoUrl?: string | null;
  isActive: boolean;
  createdAt: string;
  updatedAt: string;
};

export type UserInput = {
  name: string;
  email: string;
  password?: string;
  role: string;
  isActive: boolean;
};

export type ExternalUser = {
  id: string;
  name: string;
  email: string;
  role: string;
  avatarUrl: string | null;
  createdAt: string;
  updatedAt: string;
};

export type AuditLog = {
  id: number;
  userId: number | null;
  action: string;
  auditableType: string | null;
  auditableId: number | null;
  oldValues: Record<string, unknown> | null;
  newValues: Record<string, unknown> | null;
  ipAddress: string | null;
  userAgent: string | null;
  createdAt: string;
  user?: { id: number; name: string; email: string } | null;
};
