// Removes rules from the built CSS that Filament already serves in every panel.
// The baseline is Filament's prebuilt panel stylesheet, the same file
// `php artisan filament:assets` publishes as public/css/filament/filament/app.css,
// resolved from vendor/filament/filament after `composer install` (so it tracks
// the Filament version pinned in composer.lock). The source stylesheet keeps its
// full theme.css import, so @apply and theme variables still compile.
// Both files go through the same esbuild minifier before comparing so that
// formatting differences between Filament's build and ours don't hide matches.
// Usage: node scripts/dedupe-css.mjs <target.css>...
import fs from 'node:fs';
import postcss from 'postcss';
import { transformSync } from 'esbuild';

const base = 'vendor/filament/filament/dist/theme.css';
if (!fs.existsSync(base)) {
    console.error(`dedupe-css: ${base} not found, run composer install first`);
    process.exit(1);
}
const targets = process.argv.slice(2);
const norm = (css) => transformSync(css, { loader: 'css', minify: true }).code;

const isLeaf = (n) => n.type === 'rule' || (n.type === 'atrule' && n.nodes && !n.nodes.some((c) => c.type === 'rule' || c.type === 'atrule'));
const ctx = (node) => {
    const parts = [];
    for (let p = node.parent; p && p.type !== 'root'; p = p.parent) {
        parts.unshift(p.type === 'atrule' ? `@${p.name} ${p.params}` : p.selector);
    }
    return parts.join(' > ');
};
const key = (node) => {
    const body = node.nodes ? node.nodes.map((n) => n.toString()).join(';') : '';
    const head = node.type === 'rule' ? node.selector : `@${node.name} ${node.params}`;
    return `${ctx(node)} | ${head} { ${body} }`;
};

const known = new Set();
postcss.parse(norm(fs.readFileSync(base, 'utf8'))).walk((n) => { if (isLeaf(n)) known.add(key(n)); });

for (const file of targets) {
    const before = fs.statSync(file).size;
    const root = postcss.parse(norm(fs.readFileSync(file, 'utf8')));
    root.walk((n) => { if (isLeaf(n) && known.has(key(n))) n.remove(); });
    let changed = true;
    while (changed) {
        changed = false;
        root.walk((n) => { if ((n.type === 'atrule' || n.type === 'rule') && n.nodes && n.nodes.length === 0) { n.remove(); changed = true; } });
    }
    fs.writeFileSync(file, norm(root.toString()));
    console.log(`${file}: ${before} -> ${fs.statSync(file).size} bytes`);
}
