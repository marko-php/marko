import {
  bootstrapMarkoInertiaVue,
  discoverMarkoServerLayouts,
} from "../../vendor/marko/inertia-vue/resources/js/bootstrap";

const pages = import.meta.glob([
  "./pages/**/*.vue",
  "../../app/**/resources/js/pages/**/*.vue",
  "../../modules/**/resources/js/pages/**/*.vue",
  "../../vendor/marko/**/resources/js/pages/**/*.vue",
]);

// import AppLayout from "@/layouts/AppLayout.vue";
// import AdminLayout from "@admin-panel/layouts/AdminLayout.vue";

bootstrapMarkoInertiaVue({
  pages,
  // defaultLayout: AppLayout,
  // serverLayouts: {
  //   ...discoverMarkoServerLayouts(import.meta.glob([
  //     "./layouts/**/*.vue",
  //     "../../app/**/resources/js/layouts/**/*.vue",
  //     "../../modules/**/resources/js/layouts/**/*.vue",
  //     "../../vendor/marko/**/resources/js/layouts/**/*.vue",
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
