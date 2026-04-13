import {
  bootstrapMarkoInertiaSvelte,
  discoverMarkoServerLayouts,
} from "../../vendor/marko/inertia-svelte/resources/js/bootstrap";

const pages = import.meta.glob([
  "./pages/**/*.svelte",
  "../../app/**/resources/js/pages/**/*.svelte",
  "../../modules/**/resources/js/pages/**/*.svelte",
  "../../vendor/marko/**/resources/js/pages/**/*.svelte",
]);

// import AppLayout from "@/layouts/AppLayout.svelte";
// import AdminLayout from "@admin-panel/layouts/AdminLayout.svelte";

bootstrapMarkoInertiaSvelte({
  pages,
  // defaultLayout: AppLayout,
  // serverLayouts: {
  //   ...discoverMarkoServerLayouts(import.meta.glob([
  //     "./layouts/**/*.svelte",
  //     "../../app/**/resources/js/layouts/**/*.svelte",
  //     "../../modules/**/resources/js/layouts/**/*.svelte",
  //     "../../vendor/marko/**/resources/js/layouts/**/*.svelte",
  //   ], { eager: true })),
  //   "admin-panel::AdminLayout": AdminLayout,
  // },
  // resolveLayout: ({ moduleName, componentPath }) => {
  //   if (moduleName === "admin-panel" || componentPath.startsWith("Admin/")) {
  //     return AdminLayout;
  //   }
  //
  //   return AppLayout;
  // },
});
