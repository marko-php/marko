// @ts-check
import { defineConfig } from 'astro/config';
import starlight from '@astrojs/starlight';
import { readFileSync } from 'node:fs';

const arcadeDark = JSON.parse(
	readFileSync(new URL('./src/themes/arcade-dark.json', import.meta.url), 'utf-8')
);

// https://astro.build/config
export default defineConfig({
	site: 'https://marko.build',
	base: '/docs',
	integrations: [
		starlight({
			title: 'MARKO DOCS',
			components: {
				ThemeSelect: './src/components/ThemeSelect.astro',
			},
			expressiveCode: {
				themes: [arcadeDark],
			},
			social: [
				{ icon: 'github', label: 'GitHub', href: 'https://github.com/markshust/marko' },
			],
			editLink: {
				baseUrl: 'https://github.com/markshust/marko/edit/develop/docs/',
			},
			customCss: [
				'./src/styles/custom.css',
				'@fontsource/press-start-2p/400.css',
				'@fontsource/inter/400.css',
				'@fontsource/inter/600.css',
				'@fontsource/jetbrains-mono/400.css',
				'@fontsource/jetbrains-mono/600.css',
			],
			head: [
				{
					tag: 'meta',
					attrs: {
						property: 'og:image',
						content: 'https://marko.build/docs/og-image.png',
					},
				},
				{
					tag: 'script',
					content: `document.addEventListener('DOMContentLoaded',()=>{document.querySelectorAll('.social-icons a').forEach(a=>{a.setAttribute('target','_blank');a.setAttribute('rel','me noopener')})})`,
				},
			],
			sidebar: [
				{
					label: 'Getting Started',
					items: [
						{ label: 'Introduction', slug: 'getting-started/introduction' },
						{ label: 'Installation', slug: 'getting-started/installation' },
						{ label: 'Your First Application', slug: 'getting-started/first-application' },
						{ label: 'Project Structure', slug: 'getting-started/project-structure' },
						{ label: 'Configuration', slug: 'getting-started/configuration' },
					],
				},
				{
					label: 'Concepts',
					items: [
						{ label: 'Modularity', slug: 'concepts/modularity' },
						{ label: 'Dependency Injection', slug: 'concepts/dependency-injection' },
						{ label: 'Preferences', slug: 'concepts/preferences' },
						{ label: 'Plugins', slug: 'concepts/plugins' },
						{ label: 'Events & Observers', slug: 'concepts/events' },
					],
				},
				{
					label: 'Packages',
					autogenerate: { directory: 'packages' },
				},
				{
					label: 'Guides',
					items: [
						{ label: 'Routing', slug: 'guides/routing' },
						{ label: 'Database', slug: 'guides/database' },
						{ label: 'Authentication', slug: 'guides/authentication' },
						{ label: 'Caching', slug: 'guides/caching' },
						{ label: 'Mail', slug: 'guides/mail' },
						{ label: 'Queues', slug: 'guides/queues' },
						{ label: 'Validation', slug: 'guides/validation' },
						{ label: 'Error Handling', slug: 'guides/error-handling' },
						{ label: 'Testing', slug: 'guides/testing' },
					],
				},
				{
					label: 'Tutorials',
					items: [
						{ label: 'Build a Blog', slug: 'tutorials/build-a-blog' },
						{ label: 'Build a REST API', slug: 'tutorials/build-a-rest-api' },
						{ label: 'Create a Custom Module', slug: 'tutorials/custom-module' },
					],
				},
			],
		}),
	],
});
