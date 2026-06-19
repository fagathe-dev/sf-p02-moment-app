const sass = require('sass');
const fs = require('fs');
const path = require('path');

const args = process.argv.slice(2);
const isBuild =
  args.includes('--mode') && args[args.indexOf('--mode') + 1] === 'build';
const isWatch = args.includes('--watch');

// Points d'entrée : { src, dest }
const entries = [
  { src: 'assets/scss/custom.scss', dest: 'public/css/custom.css' },
  { src: 'assets/scss/icons.scss', dest: 'public/css/icons.css' },
];

// Crée les dossiers de sortie si nécessaire
const outdirs = [...new Set(entries.map((e) => path.dirname(e.dest)))];
outdirs.forEach((dir) => {
  if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
});

// Compile un seul point d'entrée
function compileEntry({ src, dest }) {
  try {
    const result = sass.compile(src, {
      style: isBuild ? 'compressed' : 'expanded',
    });
    fs.writeFileSync(dest, result.css);
    const time = new Date().toLocaleTimeString();
    console.log(`[${time}] ✅ SCSS compiled to ${dest}`);
  } catch (error) {
    console.error(`❌ SCSS compilation failed (${src}):`, error.message);
  }
}

// Compile tous les points d'entrée
function compileAll() {
  entries.forEach(compileEntry);
}

// 1. Compilation initiale
compileAll();

// 2. Mode watch : recompile tout à chaque modification dans assets/scss/
if (isWatch) {
  console.log('👀 Watching SCSS files for changes...');
  let timeout;

  fs.watch('assets/scss', { recursive: true }, (eventType, filename) => {
    if (filename && filename.endsWith('.scss')) {
      clearTimeout(timeout);
      timeout = setTimeout(compileAll, 100);
    }
  });
}
