// Copies the approved brand assets (brand/ is the source of truth) into public/ for the web app.
// Runs automatically before `npm run dev` and `npm run build`.
import { cpSync, mkdirSync, readdirSync, copyFileSync, rmSync } from 'node:fs';
import { join } from 'node:path';

const root = new URL('..', import.meta.url).pathname.replace(/^\/([A-Za-z]:)/, '$1');
const src = join(root, 'brand');
const dest = join(root, 'public', 'brand');

rmSync(dest, { recursive: true, force: true });
mkdirSync(dest, { recursive: true });

for (const dir of ['logo', 'icons']) {
    cpSync(join(src, dir), join(dest, dir), { recursive: true });
}
mkdirSync(join(dest, 'animation'), { recursive: true });
for (const file of readdirSync(join(src, 'animation')).filter((f) => f.endsWith('.svg'))) {
    copyFileSync(join(src, 'animation', file), join(dest, 'animation', file));
}
copyFileSync(join(src, 'icons', 'favicon.ico'), join(root, 'public', 'favicon.ico'));

console.log('brand assets synced to public/brand');
