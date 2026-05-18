<script setup lang="ts">
import { onMounted, ref, watch } from "vue";

const props = defineProps<{
  modelValue: string;
  placeholder?: string;
}>();

const emit = defineEmits<{
  (event: "update:modelValue", value: string): void;
}>();

const editorRef = ref<HTMLDivElement | null>(null);
let isInternalUpdate = false;

function exec(command: string, value?: string) {
  document.execCommand(command, false, value ?? undefined);
  editorRef.value?.focus();
  emitCurrent();
}

function emitCurrent() {
  if (!editorRef.value) return;
  isInternalUpdate = true;
  emit("update:modelValue", editorRef.value.innerHTML);
  isInternalUpdate = false;
}

function onInput() {
  emitCurrent();
}

function onPaste(e: ClipboardEvent) {
  e.preventDefault();
  const text = e.clipboardData?.getData("text/plain") ?? "";
  document.execCommand("insertText", false, text);
}

onMounted(() => {
  if (editorRef.value && props.modelValue) {
    editorRef.value.innerHTML = props.modelValue;
  }
});

watch(() => props.modelValue, (val) => {
  if (isInternalUpdate) return;
  if (!editorRef.value) return;
  if (editorRef.value.innerHTML !== val) {
    editorRef.value.innerHTML = val ?? "";
  }
});

function isActive(command: string, value?: string): boolean {
  return document.queryCommandState(command);
}
</script>

<template>
  <div class="rounded-lg border border-slate-200 bg-white">
    <!-- Toolbar -->
    <div class="flex flex-wrap items-center gap-1 border-b border-slate-100 px-3 py-2">
      <button
        type="button"
        title="Bold"
        class="rounded px-2 py-1 text-xs font-bold transition-colors hover:bg-slate-100"
        @mousedown.prevent="exec('bold')"
      >B</button>
      <button
        type="button"
        title="Italic"
        class="rounded px-2 py-1 text-xs italic transition-colors hover:bg-slate-100"
        @mousedown.prevent="exec('italic')"
      >I</button>
      <button
        type="button"
        title="Underline"
        class="rounded px-2 py-1 text-xs underline transition-colors hover:bg-slate-100"
        @mousedown.prevent="exec('underline')"
      >U</button>
      <div class="mx-1 h-4 w-px bg-slate-200" />
      <button
        type="button"
        title="Heading 2"
        class="rounded px-2 py-1 text-xs font-semibold transition-colors hover:bg-slate-100"
        @mousedown.prevent="exec('formatBlock', 'h2')"
      >H2</button>
      <button
        type="button"
        title="Heading 3"
        class="rounded px-2 py-1 text-xs font-semibold transition-colors hover:bg-slate-100"
        @mousedown.prevent="exec('formatBlock', 'h3')"
      >H3</button>
      <button
        type="button"
        title="Normal text"
        class="rounded px-2 py-1 text-xs transition-colors hover:bg-slate-100"
        @mousedown.prevent="exec('formatBlock', 'p')"
      >¶</button>
      <div class="mx-1 h-4 w-px bg-slate-200" />
      <button
        type="button"
        title="Bullet list"
        class="rounded px-2 py-1 text-xs transition-colors hover:bg-slate-100"
        @mousedown.prevent="exec('insertUnorderedList')"
      >• List</button>
      <button
        type="button"
        title="Numbered list"
        class="rounded px-2 py-1 text-xs transition-colors hover:bg-slate-100"
        @mousedown.prevent="exec('insertOrderedList')"
      >1. List</button>
      <div class="mx-1 h-4 w-px bg-slate-200" />
      <button
        type="button"
        title="Remove formatting"
        class="rounded px-2 py-1 text-xs text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700"
        @mousedown.prevent="exec('removeFormat')"
      >Clear</button>
    </div>

    <!-- Editable area -->
    <div
      ref="editorRef"
      contenteditable="true"
      class="rte-content min-h-[14rem] px-4 py-3 text-sm text-slate-800 focus:outline-none"
      :data-placeholder="placeholder || 'Write here…'"
      @input="onInput"
      @paste="onPaste"
    />
  </div>
</template>

<style scoped>
.rte-content:empty::before {
  content: attr(data-placeholder);
  color: rgb(148 163 184);
  pointer-events: none;
}

.rte-content :deep(h2) {
  margin: 0.75rem 0 0.25rem;
  font-size: 1rem;
  font-weight: 700;
  color: rgb(15 23 42);
}

.rte-content :deep(h3) {
  margin: 0.5rem 0 0.25rem;
  font-size: 0.9rem;
  font-weight: 600;
  color: rgb(30 41 59);
}

.rte-content :deep(p) {
  margin: 0.25rem 0;
  line-height: 1.6;
}

.rte-content :deep(ul),
.rte-content :deep(ol) {
  margin: 0.25rem 0;
  padding-left: 1.5rem;
}

.rte-content :deep(ul) {
  list-style-type: disc;
}

.rte-content :deep(ol) {
  list-style-type: decimal;
}

.rte-content :deep(li) {
  display: list-item;
  margin: 0.1rem 0;
  line-height: 1.6;
}

.rte-content :deep(strong) {
  font-weight: 600;
}
</style>
