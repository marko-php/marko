type MarkoViteReadyDetail = {
  entrypoint: string;
};

declare global {
  interface WindowEventMap {
    'marko:app:ready': CustomEvent<MarkoViteReadyDetail>;
    'marko:vite:ready': CustomEvent<MarkoViteReadyDetail>;
  }
}

export function bootstrapMarkoVite(entrypoint: string): void {
  document.documentElement.dataset.markoApp = 'ready';
  document.documentElement.dataset.markoAppEntrypoint = entrypoint;
  document.documentElement.dataset.markoVite = 'ready';
  document.documentElement.dataset.markoViteEntrypoint = entrypoint;

  const status = document.querySelector<HTMLElement>('[data-marko-app-status], [data-marko-vite-status]');

  if (status !== null) {
    status.textContent = 'Frontend bootstrap ready';
    status.dataset.markoAppEntrypoint = entrypoint;
    status.dataset.markoViteEntrypoint = entrypoint;
  }

  window.dispatchEvent(new CustomEvent<MarkoViteReadyDetail>('marko:app:ready', {
    detail: {
      entrypoint,
    },
  }));

  window.dispatchEvent(new CustomEvent<MarkoViteReadyDetail>('marko:vite:ready', {
    detail: {
      entrypoint,
    },
  }));
}
