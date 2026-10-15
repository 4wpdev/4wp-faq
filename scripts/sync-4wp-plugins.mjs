#!/usr/bin/env node
/**
 * Sync the "More 4WP plugins" block in README.md from the 4WP family list.
 * Source of truth: https://github.com/4wpdev/4wpdev/blob/main/plugins.json
 * Versions are live shields.io badges (WordPress.org Stable tag / GitHub release),
 * so the table only changes when a plugin is added, removed or renamed.
 */
import { readFileSync, writeFileSync } from 'node:fs';

const SELF = '4wp-faq';
const SOURCE = 'https://raw.githubusercontent.com/4wpdev/4wpdev/main/plugins.json';
const README = new URL('../README.md', import.meta.url);
const START = '<!-- 4WP-PLUGINS:START -->';
const END = '<!-- 4WP-PLUGINS:END -->';

const res = await fetch(SOURCE);
if (!res.ok) {
	console.error(`Cannot fetch ${SOURCE}: ${res.status}`);
	process.exit(1);
}
const { plugins = [] } = await res.json();

const rows = plugins
	.filter((p) => p.slug !== SELF && p.status === 'active')
	.map((p) => {
		const version = p.wordpress_org
			? `[![WordPress.org](https://img.shields.io/wordpress/plugin/v/${p.slug}?style=flat-square&label=)](${p.wordpress_org_url})`
			: `[![GitHub](https://img.shields.io/github/v/tag/4wpdev/${p.slug}?style=flat-square&label=)](${p.repository})`;
		const wporg = p.wordpress_org ? `[WordPress.org](${p.wordpress_org_url})` : '—';
		const desc = (p.description || '').replace(/\|/g, '\\|').split(/(?<=\.)\s/)[0];
		return `| **[${p.name}](${p.repository})** | ${desc} | ${version} | ${wporg} |`;
	});

const block = [
	START,
	'| Plugin | What it does | Version | Directory |',
	'|---|---|---|---|',
	...rows,
	END,
].join('\n');

const readme = readFileSync(README, 'utf8');
const re = new RegExp(`${START}[\\s\\S]*?${END}`);
if (!re.test(readme)) {
	console.error('Markers not found in README.md');
	process.exit(1);
}
const next = readme.replace(re, block);
if (next !== readme) {
	writeFileSync(README, next);
	console.log(`README updated: ${rows.length} plugins`);
} else {
	console.log('README already up to date');
}
