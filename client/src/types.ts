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
  projectId?: number;
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

export type RtmfReviewRoleStat = {
  approved: number;
  reviewed: number;
  open: number;
};

export type RtmfRoleModuleStat = {
  id: number;
  code: string;
  name: string;
  total: number;
  approved: number;
  reviewed: number;
  open: number;
};

export type RtmfDashboardSummary = {
  totals: {
    frontends: number;
    done: number;
    modules: number;
    actors: number;
    items: number;
    scenarios: number;
    approvedAll: number;
  };
  itemsByStatus: {
    implemented: number;
    partial: number;
    missing: number;
    unset: number;
  };
  byModule: RtmfDashboardModuleStat[];
  byActor: RtmfDashboardActorStat[];
  byReview: {
    businessAnalyst: RtmfReviewRoleStat;
    qa: RtmfReviewRoleStat;
    technical: RtmfReviewRoleStat;
    developer: RtmfReviewRoleStat;
  };
  byRoleModule: {
    businessAnalyst: RtmfRoleModuleStat[];
    qa: RtmfRoleModuleStat[];
    technical: RtmfRoleModuleStat[];
    developer: RtmfRoleModuleStat[];
  };
};

export type RtmfAssigneeStat = {
  key: string;
  name: string;
  email?: string | null;
  photoUrl?: string | null;
  total: number;
  done: number;
  byModule: { moduleId: number; code: string; name: string; total: number; done: number }[];
  baFeedback: { open: number; reviewed: number; approved: number };
};

export type RtmfDailyTrend = {
  date: string;
  open: number;
  reviewed: number;
  approved: number;
};

export type RtmfByAssigneeSummary = {
  assignees: RtmfAssigneeStat[];
  dailyTrend: RtmfDailyTrend[];
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
  assignees?: RtmfFrontendAssignee[];
  isDone?: boolean;
  vuePath?: string | null;
  liveUrl?: string | null;
  urlDev?: string | null;
  urlStg?: string | null;
  urlPrd?: string | null;
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
  feedbacks?: RtmfFrontendFeedback[];
};

export type RtmfScenarioStepLinkInput = {
  toStepId?: number | null;
  condition?: string | null;
  sortOrder?: number;
};

export type RtmfScenarioStepLink = RtmfScenarioStepLinkInput & {
  id: number;
  fromStepId: number;
  toStep?: { id: number; rtmfFrontendId?: number | null; page?: { id: number; specId: string; title: string } | null } | null;
  createdAt: string;
  updatedAt: string;
};

export type RtmfScenarioStepInput = {
  rtmfFrontendId?: number | null;
  actorIds?: number[];
  note?: string | null;
  sortOrder?: number;
};

export type RtmfScenarioStep = RtmfScenarioStepInput & {
  id: number;
  rtmfScenarioId: number;
  page?: { id: number; specId: string; title: string } | null;
  actors?: { id: number; name: string }[];
  links?: RtmfScenarioStepLink[];
  createdAt: string;
  updatedAt: string;
};

export type RtmfScenarioAttachment = {
  id: number;
  rtmfScenarioId: number;
  label: string | null;
  filename: string;
  originalName: string;
  mimeType: string;
  size: number;
  url: string;
  createdAt: string;
};

export type RtmfScenarioInput = {
  title: string;
  description?: string | null;
  isDone?: boolean;
  assignees?: RtmfFrontendAssignee[];
};

export type RtmfScenario = RtmfScenarioInput & {
  id: number;
  sortOrder: number;
  stepsCount?: number;
  steps?: RtmfScenarioStep[];
  createdAt: string;
  updatedAt: string;
};

export type RtmfProjectInput = {
  code: string;
  name: string;
  description?: string | null;
  sortOrder?: number;
};

export type RtmfProject = RtmfProjectInput & {
  id: number;
  myRole?: string;
  createdAt: string;
  updatedAt: string;
};

export type RtmfProjectMember = {
  id: number;
  name: string;
  email: string;
  role: string;
  projectRole?: string;
  photoUrl?: string | null;
};

export type RtmfFrontendFeedbackRole = 'business_analyst' | 'qa' | 'technical' | 'developer';
export type RtmfFrontendFeedbackStatus = 'open' | 'reviewed' | 'approved';

export type RtmfFrontendFeedback = {
  id: number;
  rtmfFrontendId: number;
  role: RtmfFrontendFeedbackRole;
  status: RtmfFrontendFeedbackStatus;
  comment: string | null;
  createdAt: string;
  updatedAt: string;
};

export type RtmfUrlPathInput = {
  vuePath?: string | null;
  liveUrl?: string | null;
  description?: string | null;
};

export type RtmfUrlPath = {
  id: number;
  vuePath: string | null;
  liveUrl: string | null;
  description: string | null;
  lineCount: number | null;
  fileSizeKb: number | null;
  sharedComponents?: unknown[] | null;
  snapshotHtml?: string | null;
  snapshotStatus: RtmfSnapshotStatus;
  snapshotCapturedAt: string | null;
  frontendsCount?: number;
  createdAt: string;
  updatedAt: string;
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
  permissions?: string[];
};

export type MemberCandidate = {
  id: number;
  name: string;
  email: string;
  role: string;
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

export type AllAttachment = {
  id: string;
  source: 'media' | 'frontend_attachment' | 'module_photo' | 'submodule_photo' | 'scenario_attachment';
  sourceLabel: string;
  context?: string | null;
  filename: string;
  originalName: string;
  label?: string | null;
  mimeType: string;
  size: number;
  url: string;
  createdAt: string;
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
  photoUrl: string | null;
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


export type RtmfImportFrontendResult = {
  specId: string;
  action: 'created' | 'updated';
  items: number;
  endpoints: number;
};

export type RtmfImportResult = {
  module: string;
  subModule: string;
  frontends: RtmfImportFrontendResult[];
};

export type RtmfApiEndpointMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';

export type RtmfFrontendApiEndpoint = {
  id: number;
  rtmfFrontendId: number;
  method: RtmfApiEndpointMethod;
  endpoint: string;
  description: string | null;
  sortOrder: number;
  createdAt: string;
  updatedAt: string;
};

export type RtmfFrontendApiEndpointInput = {
  method?: RtmfApiEndpointMethod;
  endpoint: string;
  description?: string | null;
  sortOrder?: number;
};

export type RtmfRelationEdge = {
  itemId: number;
  itemType: string | null;
  itemLabel: string | null;
  condition: string | null;
  fromId: number;
  fromSpecId: string;
  fromTitle: string;
  toId: number;
  toSpecId: string;
  toTitle: string;
};
