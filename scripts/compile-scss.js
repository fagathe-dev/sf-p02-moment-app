const sass = require('sass');
const fs = require('fs');
const path = require('path');

const args = process.argv.slice(2);
const isBuild =
  args.includes('--mode') && args[args.indexOf('--mode') + 1] === 'build';
const isWatch = args.includes('--watch'); // Détection du flag watch

const entryPoint = 'assets/scss/custom.scss';
const outdir = 'public/css';
const outfile = 'custom.css';

if (!fs.existsSync(outdir)) fs.mkdirSync(outdir, { recursive: true });

// Fonction de compilation isolée
function compileSass() {
  try {
    const result = sass.compile(entryPoint, {
      style: isBuild ? 'compressed' : 'expanded',
    });
    fs.writeFileSync(path.join(outdir, outfile), result.css);
    const time = new Date().toLocaleTimeString();
    console.log(`[${time}] ✅ SCSS compiled to ${outdir}/${outfile}`);
  } catch (error) {
    console.error('❌ SCSS compilation failed:', error.message);
  }
}

// 1. On lance une première compilation au démarrage
compileSass();

// 2. Si mode watch activé, on écoute le dossier
if (isWatch) {
  console.log('👀 Watching SCSS files for changes...');
  let timeout;

  // Écoute récursive de tout le dossier scss
  fs.watch('assets/scss', { recursive: true }, (eventType, filename) => {
    if (filename && filename.endsWith('.scss')) {
      // Debounce : on annule la précédente compilation si elle date de moins de 100ms
      clearTimeout(timeout);
      timeout = setTimeout(() => {
        compileSass();
      }, 100);
    }
  });
}
