<!-- resources/js/Components/Pagination.vue -->
<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
  links: {
    type: Array,
    required: true
  },
  only: {
    type: Array
  }
})
</script>
<template>
<div class="join">
  <template v-for="(link, index) in links" :key="index">
    <Link v-if="index === 0"
      :href="link.url ?? ''"
      :only="only"
      method="get"
      class="btn btn-sm join-item" 
      :class="{ 'text-base-content/50': !link.url, 'btn-active': link.active }"
    >&laquo;
    </Link>
    
    <Link v-else-if="index === links.length - 1"
      :href="link.url ?? ''"
      :only="only"
      method="get"
      class="btn btn-sm join-item" 
      :class="{ 'text-base-content/50': !link.url, 'btn-active': link.active }"
    >&raquo;
    </Link>
    
    <Link v-else
      v-if="link.url"
      :href="link.url"
      :only="only"
      method="get"
      class="btn btn-sm join-item" 
      :class="{ 'text-base-content/50': !link.url, 'btn-active btn-outline': link.active}"
      v-html="link.label"
    />
  </template>
</div>
</template>
