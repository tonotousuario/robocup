// export-pdf.mjs — Exporta el deck RoboLeague a PDF sin desvanecidos.
//
// El exportador genérico capturaba los .reveal a media transición (opacity .7s),
// dejando casi todo el contenido invisible. Aquí usamos la navegación REAL del
// deck (deck.show(i), que aplica .visible) y desactivamos transiciones/animaciones
// para capturar el estado final nítido de cada slide.
//
// Uso:  node export-pdf.mjs <serveDir> <htmlFile> <output.pdf> [W] [H]
import { chromium } from 'playwright';
import { createServer } from 'http';
import { readFileSync } from 'fs';
import { join, extname } from 'path';

const [, , SERVE_DIR, HTML_FILE, OUTPUT_PDF, W = '1920', H = '1080'] = process.argv;
const VW = parseInt(W), VH = parseInt(H);

const MIME = { '.html': 'text/html', '.css': 'text/css', '.js': 'application/javascript',
  '.json': 'application/json', '.png': 'image/png', '.jpg': 'image/jpeg', '.jpeg': 'image/jpeg',
  '.gif': 'image/gif', '.svg': 'image/svg+xml', '.webp': 'image/webp', '.woff': 'font/woff',
  '.woff2': 'font/woff2', '.ttf': 'font/ttf' };

const server = createServer((req, res) => {
  const url = decodeURIComponent(req.url);
  const filePath = join(SERVE_DIR, url === '/' ? HTML_FILE : url);
  try {
    const content = readFileSync(filePath);
    res.writeHead(200, { 'Content-Type': MIME[extname(filePath).toLowerCase()] || 'application/octet-stream' });
    res.end(content);
  } catch { res.writeHead(404); res.end('Not found'); }
});
const port = await new Promise(r => server.listen(0, () => r(server.address().port)));

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: VW, height: VH }, deviceScaleFactor: 1 });
await page.goto(`http://localhost:${port}/`, { waitUntil: 'networkidle' });
await page.evaluate(() => document.fonts.ready);

// Mata transiciones y animaciones: los .reveal saltan a su estado final al instante.
await page.addStyleTag({ content: `*,*::before,*::after{transition:none!important;animation:none!important}` });

const count = await page.evaluate(() => document.querySelectorAll('.slide').length);
console.log(`  ${count} slides @ ${VW}x${VH}`);

const shots = [];
for (let i = 0; i < count; i++) {
  await page.evaluate((idx) => {
    // Navegación real del deck (aplica .active + .visible). Fallback defensivo.
    if (window.deck && typeof window.deck.show === 'function') { window.deck.show(idx); }
    document.querySelectorAll('.slide').forEach((s, j) => {
      s.classList.toggle('active', j === idx);
      s.classList.toggle('visible', j === idx);
    });
    // Cinturón y tirantes: fuerza los reveal de la slide activa a su estado final.
    document.querySelectorAll('.slide')[idx].querySelectorAll('.reveal').forEach(el => {
      el.style.opacity = '1'; el.style.transform = 'none'; el.style.visibility = 'visible';
    });
  }, i);
  await page.waitForTimeout(120);
  shots.push(await page.screenshot({ type: 'png' }));
  process.stdout.write(`\r  capturada ${i + 1}/${count}`);
}
console.log('');
await browser.close();

// Ensambla el PDF a partir de las capturas (una por página, tamaño exacto).
const imgs = shots.map(b => `<div class="p"><img src="data:image/png;base64,${b.toString('base64')}"></div>`).join('');
const html = `<!doctype html><html><head><style>
*{margin:0;padding:0}@page{size:${VW}px ${VH}px;margin:0}
.p{width:${VW}px;height:${VH}px;page-break-after:always;overflow:hidden}
.p:last-child{page-break-after:auto}img{width:${VW}px;height:${VH}px;display:block}
</style></head><body>${imgs}</body></html>`;

const b2 = await chromium.launch();
const pp = await b2.newPage();
await pp.setContent(html, { waitUntil: 'load' });
await pp.pdf({ path: OUTPUT_PDF, width: `${VW}px`, height: `${VH}px`, printBackground: true,
  margin: { top: 0, right: 0, bottom: 0, left: 0 } });
await b2.close();
server.close();
console.log(`  ✓ ${OUTPUT_PDF}`);
