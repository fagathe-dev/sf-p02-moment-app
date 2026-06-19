const esbuild = require('esbuild');
const fs = require('fs');
const path = require('path');

const args = process.argv.slice(2);
const isBuild =
  args.includes('--mode') && args[args.indexOf('--mode') + 1] === 'build';
const isWatch = args.includes('--watch');

const srcDir = 'assets/ts';
const outdir = 'public/js';

/**
 * Seuls les fichiers "entry-point" sont compilés :
 *   - assets/ts/main.ts
 *   - assets/ts/pages/**\/*.ts
 *
 * Les fichiers components/** et core/** sont des modules internes ;
 * ils sont automatiquement bundlés dans les entry-points qui les importent.
 */
function collectEntryPoints(dir, baseDir = dir) {
  const entries = [];
  for (const item of fs.readdirSync(dir, { withFileTypes: true })) {
    const fullPath = path.join(dir, item.name);
    if (item.isDirectory()) {
      entries.push(...collectEntryPoints(fullPath, baseDir));
    } else if (item.isFile() && item.name.endsWith('.ts')) {
      const rel = path.relative(baseDir, fullPath); // e.g. "main.ts" or "pages/auth/profile.ts"
      // Exclut les dossiers qui ne sont que des bibliothèques internes
      if (!rel.startsWith('components') && !rel.startsWith('core')) {
        entries.push(fullPath);
      }
    }
  }
  return entries;
}

const entryPoints = collectEntryPoints(srcDir);

/**
 * Supprime récursivement le contenu d'un répertoire (pas le répertoire lui-même).
 * Utilisé pour nettoyer public/js/ avant chaque build.
 */
function cleanDir(dir) {
  if (!fs.existsSync(dir)) return;
  for (const item of fs.readdirSync(dir, { withFileTypes: true })) {
    const fullPath = path.join(dir, item.name);
    if (item.isDirectory()) {
      fs.rmSync(fullPath, { recursive: true, force: true });
    } else {
      fs.unlinkSync(fullPath);
    }
  }
}

cleanDir(outdir);
if (!fs.existsSync(outdir)) fs.mkdirSync(outdir, { recursive: true });

const buildOptions = {
  entryPoints,
  bundle: true,
  outdir,
  sourcemap: false,
  minify: isBuild,
  target: 'es2020',
  format: 'iife',
  tsconfig: 'tsconfig.json',
};

async function build() {
  try {
    if (isWatch) {
      const ctx = await esbuild.context(buildOptions);
      await ctx.watch();
      const time = new Date().toLocaleTimeString();
      console.log(`[${time}] ✅ TS compiled to ${outdir}/`);
      console.log('👀 Watching TS files for changes...');
    } else {
      await esbuild.build(buildOptions);
      const time = new Date().toLocaleTimeString();
      console.log(`[${time}] ✅ TS compiled to ${outdir}/`);
    }
  } catch (error) {
    console.error('❌ TS compilation failed:', error.message);
    process.exit(1);
  }
}

build();
