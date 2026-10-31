import { ref } from "vue";

/**
 * Shared drawer state so the top-bar toggle and the navigation panel stay in
 * sync on compact viewports.
 */
export const navigationOpen = ref(false);

export function toggleNavigation() {
    navigationOpen.value = !navigationOpen.value;
}

export function closeNavigation() {
    navigationOpen.value = false;
}

/**
 * The directory behaves as an accordion: only the group that owns the page in
 * use stays expanded and every other group collapses. Keeping the state here
 * means a switch between admin modules never leaves the panel collapsed.
 */
export const expandedGroup = ref<string | null>(null);

export function openGroup(id: string | null) {
    expandedGroup.value = id;
}

export function toggleGroup(id: string) {
    expandedGroup.value = expandedGroup.value === id ? null : id;
}
