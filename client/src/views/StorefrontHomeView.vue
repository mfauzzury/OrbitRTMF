<script setup lang="ts">
import { onMounted, ref } from "vue";

import { getPublicFrontPage, getPublicSiteSettings } from "@/api/cms";
import StorefrontLayout from "@/layouts/StorefrontLayout.vue";
import type { Page, PublicSiteSettings } from "@/types";

const loading = ref(true);
const error = ref("");
const site = ref<PublicSiteSettings | null>(null);
const page = ref<Page | null>(null);

onMounted(async () => {
  loading.value = true;
  error.value = "";
  try {
    const [siteResponse, pageResponse] = await Promise.all([getPublicSiteSettings(), getPublicFrontPage()]);
    site.value = siteResponse.data;
    page.value = pageResponse.data;
    const resolvedTitle = [site.value?.webfrontTitle, site.value?.siteTitle]
      .find(t => t && t !== "null");
    if (resolvedTitle) document.title = resolvedTitle;
  } catch (e: unknown) {
    const msg = e instanceof Error ? e.message : String(e);
    const isNetworkError = msg.includes("NetworkError") || msg.includes("Failed to fetch") || msg.includes("fetch");
    error.value = isNetworkError ? "NETWORK_ERROR" : msg || "Failed to load Webfront";
  } finally {
    loading.value = false;
  }
});
</script>

<template>
  <StorefrontLayout :site="site" :page="page" :loading="loading" :error="error" />
</template>
