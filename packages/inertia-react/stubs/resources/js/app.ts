import {
  bootstrapMarkoInertiaReact,
  discoverMarkoServerLayouts,
} from "../../vendor/marko/inertia-react/resources/js/bootstrap";

const pages = import.meta.glob([
  "./pages/**/*.jsx",
  "./pages/**/*.tsx",
  "../../app/**/resources/js/pages/**/*.jsx",
  "../../app/**/resources/js/pages/**/*.tsx",
  "../../modules/**/resources/js/pages/**/*.jsx",
  "../../modules/**/resources/js/pages/**/*.tsx",
  "../../vendor/marko/**/resources/js/pages/**/*.jsx",
  "../../vendor/marko/**/resources/js/pages/**/*.tsx",
]);

// import AppLayout from "@/layouts/AppLayout";
// import AdminLayout from "@admin-panel/layouts/AdminLayout";

bootstrapMarkoInertiaReact({
  pages,
  // defaultLayout: AppLayout,
  // serverLayouts: {
  //   ...discoverMarkoServerLayouts(import.meta.glob([
  //     "./layouts/**/*.jsx",
  //     "./layouts/**/*.tsx",
  //     "../../app/**/resources/js/layouts/**/*.jsx",
  //     "../../app/**/resources/js/layouts/**/*.tsx",
  //     "../../modules/**/resources/js/layouts/**/*.jsx",
  //     "../../modules/**/resources/js/layouts/**/*.tsx",
  //     "../../vendor/marko/**/resources/js/layouts/**/*.jsx",
  //     "../../vendor/marko/**/resources/js/layouts/**/*.tsx",
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
