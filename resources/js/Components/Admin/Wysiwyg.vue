<!-- resources/js/Components/Admin/Wysiwyg.vue -->
<script setup>
import { Editor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import { onMounted, onBeforeUnmount } from 'vue'

const props = defineProps({
  modelValue: String
})

const emit = defineEmits(['update:modelValue'])

let editor

onMounted(() => {
  editor = new Editor({
    extensions: [StarterKit],
    content: props.modelValue,
    onUpdate: () => {
      emit('update:modelValue', editor.getHTML())
    }
  })
})

onBeforeUnmount(() => editor?.destroy())
</script>

<template>
  <EditorContent :editor="editor" class="border rounded-xl p-4 min-h-[200px]" />
</template>
