const esbuild = require('esbuild');
const fs = require('fs');
const path = require('path');

const args = process.argv.slice(2);
const isBuild =
  args.includes('--mode') && args[args.indexOf('--mode') + 1] === 'build';
const isWatch = args.includes('--watch');

const srcDir = 'assets/ts';
const entryPoints = fs
  .readdirSync(srcDir, { recursive: true })
  .filter((f) => f.endsWith('.ts'))
  .map((f) => path.join(srcDir, f));
const outdir = 'public/js';

if (!fs.existsSync(outdir)) fs.mkdirSync(outdir, { recursive: true });

const buildOptions = {
  entryPoints,
  bundle: true,
  outdir,
  sourcemap: false,
  minify: isBuild,
  target: 'es2020',
  format: 'esm',
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
