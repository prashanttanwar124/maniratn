<script setup lang="ts">
import { home } from '@/routes';
import { Link } from '@inertiajs/vue3';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="bg-background flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
        <div class="w-full max-w-sm">
            <div class="flex flex-col gap-6">
                <div class="flex flex-col items-center gap-3">
                    <Link :href="home()" class="flex items-center gap-3">
                        <img src="/logo-mark.png" alt="KaratSetu" class="h-10 w-auto object-contain flex-shrink-0" />
                        <span class="text-2xl font-bold tracking-tight text-[#1c3633]">
                            Karat<span class="text-[#c08f34]">Setu</span>
                        </span>
                    </Link>
                    <div class="space-y-1 text-center">
                        <h1 class="text-xl font-semibold tracking-tight text-surface-900">{{ title }}</h1>
                        <p class="text-xs text-surface-500 text-center">
                            {{ description }}
                        </p>
                    </div>
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>
