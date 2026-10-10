// Strips rules from the built CSS that are byte-for-byte identical to a
// baseline build of Filament's theme.css alone (built by the same vite run),
// since every panel already loads Filament's theme. The source stylesheet
// keeps its full theme.css import, so @apply and theme variables still work.
// Usage: node scripts/dedupe-css.mjs <baseline.css> <target.css>...
import fs from 'node:fs';
import postcss from 'postcss';

const [base, ...targets] = process.argv.slice(2);

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
postcss.parse(fs.readFileSync(base, 'utf8')).walk((n) => {
    if (n.type === 'rule' || (n.type === 'atrule' && n.nodes && !n.nodes.some((c) => c.type === 'rule' || c.type === 'atrule'))) known.add(key(n));
    if (n.type === 'decl' && n.parent.type === 'rule') known.add(`${key(n.parent)}#${n.toString()}`);
});

for (const file of targets) {
    const before = fs.statSync(file).size;
    const root = postcss.parse(fs.readFileSync(file, 'utf8'));
    root.walk((n) => {
        if (n.type === 'rule' || (n.type === 'atrule' && n.nodes && !n.nodes.some((c) => c.type === 'rule' || c.type === 'atrule'))) {
            if (known.has(key(n))) n.remove();
        }
    });
    // drop containers left empty
    let changed = true;
    while (changed) {
        changed = false;
        root.walk((n) => { if ((n.type === 'atrule' || n.type === 'rule') && n.nodes && n.nodes.length === 0) { n.remove(); changed = true; } });
    }
    fs.writeFileSync(file, root.toString());
    console.log(`${file}: ${before} -> ${fs.statSync(file).size} bytes`);
}

fs.unlinkSync(base);
