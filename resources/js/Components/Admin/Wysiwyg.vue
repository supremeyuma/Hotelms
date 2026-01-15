<script setup>
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import { watch, onBeforeUnmount } from 'vue'

const props = defineProps({
  modelValue: String
})

const emit = defineEmits(['update:modelValue'])

const editor = useEditor({
  extensions: [StarterKit],
  content: props.modelValue,
  // This updates the parent form when you type
  onUpdate: ({ editor }) => {
    emit('update:modelValue', editor.getHTML())
  },
  editorProps: {
    attributes: {
      class: 'prose prose-sm sm:prose lg:prose-lg xl:prose-2xl focus:outline-none min-h-[300px]',
    },
  },
})

// ✅ CRITICAL: Watch for changes from the parent (switching items)
watch(() => props.modelValue, (value) => {
  // Only update if the content is actually different to avoid cursor jumping
  const isSame = editor.value.getHTML() === value
  if (isSame) return

  editor.value.commands.setContent(value, false)
})

onBeforeUnmount(() => {
  editor.value?.destroy()
})
</script>

<template>
  <div class="wysiwyg-container border-2 border-slate-100 rounded-2xl overflow-hidden focus-within:border-indigo-500 transition-all">
    <div v-if="editor" class="bg-slate-50 border-b border-slate-100 p-2 flex gap-2">
       <button @click="editor.chain().focus().toggleBold().run()" :class="{ 'bg-slate-200': editor.isActive('bold') }" class="p-1 rounded text-xs font-bold px-2">B</button>
       <button @click="editor.chain().focus().toggleItalic().run()" :class="{ 'bg-slate-200': editor.isActive('italic') }" class="p-1 rounded text-xs italic px-2">I</button>
       <button @click="editor.chain().focus().toggleBulletList().run()" :class="{ 'bg-slate-200': editor.isActive('bulletList') }" class="p-1 rounded text-xs px-2">List</button>
    </div>
    
    <EditorContent :editor="editor" class="p-4 bg-white" />
  </div>
</template>

<style>
/* Tiptap needs basic styling to show lists/bold correctly in the editor */
.tiptap ul { list-style-type: disc; padding-left: 1.5rem; }
.tiptap ol { list-style-type: decimal; padding-left: 1.5rem; }
.tiptap p { margin-bottom: 1rem; }
</style>