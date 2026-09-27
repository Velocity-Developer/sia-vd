"""Bangun docs/system-flowchart.html dari docs/system-flowchart.md (+ bagian 16 docs/system-flow.md).

Diagram dirender lebih dulu menjadi SVG statis dengan label teks SVG murni (htmlLabels: false) agar teks
tidak terpotong dan halaman bisa dibuka tanpa internet.

Kebutuhan: Python 3.9+, Node.js (npx), dan Chromium/Chrome.
    python3 docs/build-flowchart-html.py
    CHROME_PATH=/path/ke/chrome python3 docs/build-flowchart-html.py   # bila Chrome tidak ditemukan otomatis
Teks diukur dengan font pertama yang tersedia dari MERMAID_CONFIG.fontFamily; pakai font yang selebar atau
lebih lebar dari Arial (mis. DejaVu Sans) agar teks tetap muat di perangkat lain.
"""
import html
import json
import os
import re
import subprocess
import tempfile
from pathlib import Path

DOCS = Path(__file__).resolve().parent
MERMAID_CLI = '@mermaid-js/mermaid-cli@11'
MERMAID_CONFIG = {
    "theme": "base",
    "htmlLabels": False,
    "fontFamily": "\"DejaVu Sans\", Arial, Helvetica, sans-serif",
    "themeVariables": {
        "fontFamily": "\"DejaVu Sans\", Arial, Helvetica, sans-serif",
        "fontSize": "14px",
        "primaryColor": "#eef5fc",
        "primaryBorderColor": "#0075de",
        "primaryTextColor": "#1f1e1d",
        "lineColor": "#615d59",
        "secondaryColor": "#fff6e0",
        "tertiaryColor": "#f6f5f4",
        "clusterBkg": "#faf9f8",
        "clusterBorder": "#d8d5d2",
        "edgeLabelBackground": "#ffffff",
        "noteBkgColor": "#fff6e0",
        "noteBorderColor": "#e0b64f",
        "noteTextColor": "#1f1e1d",
        "actorBkg": "#eef5fc",
        "actorBorder": "#0075de",
        "labelBoxBkgColor": "#eef5fc",
        "signalColor": "#31302e",
        "signalTextColor": "#1f1e1d"
    },
    "flowchart": {
        "htmlLabels": False,
        "useMaxWidth": False,
        "curve": "basis",
        "padding": 12,
        "nodeSpacing": 40,
        "rankSpacing": 50
    },
    "state": {
        "useMaxWidth": False
    },
    "sequence": {
        "useMaxWidth": False,
        "wrap": False,
        "messageAlign": "center"
    },
    "er": {
        "useMaxWidth": False
    }
}


def render_svg(sumber: list[str]) -> list[str]:
    """Render tiap blok Mermaid menjadi SVG (id d01, d02, ...) lewat mermaid-cli."""
    with tempfile.TemporaryDirectory() as tmp:
        kerja = Path(tmp)
        (kerja / 'mermaid.json').write_text(json.dumps(MERMAID_CONFIG))
        puppeteer = {'args': ['--no-sandbox']}
        if os.environ.get('CHROME_PATH'):
            puppeteer['executablePath'] = os.environ['CHROME_PATH']
        (kerja / 'puppeteer.json').write_text(json.dumps(puppeteer))
        hasil = []
        for i, blok in enumerate(sumber, 1):
            nama = f'd{i:02d}'
            (kerja / f'{nama}.mmd').write_text(blok)
            subprocess.run(
                ['npx', '-y', MERMAID_CLI, '-p', 'puppeteer.json', '-c', 'mermaid.json', '-I', nama, '-i', f'{nama}.mmd', '-o', f'{nama}.svg'],
                cwd=kerja, check=True, capture_output=True, env={**os.environ, 'PUPPETEER_SKIP_DOWNLOAD': '1'},
            )
            hasil.append((kerja / f'{nama}.svg').read_text())
            print(f'  {nama} dirender')
        return hasil


md = (DOCS / 'system-flowchart.md').read_text()
flow = (DOCS / 'system-flow.md').read_text()


def inline(teks: str) -> str:
    """Markdown inline sederhana: `kode`, **tebal**, [teks](url) -> teks saja (tautan antar-dokumen md tidak berlaku di HTML)."""
    teks = html.escape(teks, quote=False)
    teks = re.sub(r'\[([^\]]+)\]\(([^)]+)\)', r'\1', teks)
    teks = re.sub(r'\*\*(.+?)\*\*', r'<strong>\1</strong>', teks)
    teks = re.sub(r'`([^`]+)`', r'<code>\1</code>', teks)
    teks = re.sub(r'\*([^*]+)\*', r'<em>\1</em>', teks)
    return teks


def rapikan_svg(svg: str, nomor: int) -> tuple[str, float, float]:
    svg = re.sub(r'<\?xml[^>]*>\s*', '', svg)
    # Koordinat 1 desimal cukup; memangkas ukuran berkas tanpa mengubah tampilan.
    svg = re.sub(r'(\d+\.\d)\d+', r'\1', svg)
    lebar = float(re.search(r'<svg[^>]*\swidth="([\d.]+)"', svg).group(1))
    tinggi = float(re.search(r'<svg[^>]*\sheight="([\d.]+)"', svg).group(1))
    svg = re.sub(r'(<svg[^>]*?)\swidth="[\d.]+"', r'\1', svg, count=1)
    svg = re.sub(r'(<svg[^>]*?)\sheight="[\d.]+"', r'\1', svg, count=1)
    svg = re.sub(r'(<svg[^>]*?)\sstyle="[^"]*"', r'\1', svg, count=1)
    # Tidak diperkecil di bawah 70% ukuran asli (teks minimal +-10 px); selebihnya digeser di dalam kartu.
    svg = svg.replace('<svg ', f'<svg style="max-width:{lebar:.0f}px;min-width:{lebar * 0.7:.0f}px" data-lebar="{lebar:.0f}" data-tinggi="{tinggi:.0f}" ', 1)
    return svg, lebar, tinggi


# --- Bagian diagram dari system-flowchart.md ---
bagian = re.split(r'\n## (?=\d+\. )', md)
pembuka = bagian[0]
commit_kode = re.search(r'commit `(\w+)`', pembuka).group(1)

svg_semua = render_svg([re.search(r'```mermaid\n(.*?)```', b, re.S).group(1) for b in bagian[1:]])
diagram = []
for i, blok in enumerate(bagian[1:], 1):
    judul = re.match(r'(\d+)\. (.+)', blok).group(2).strip()
    sebelum = blok.split('```mermaid')[0]
    deskripsi = '\n'.join(sebelum.splitlines()[1:]).strip()
    sumber = re.search(r'```mermaid\n(.*?)```', blok, re.S).group(1)
    pd = sorted({int(x) for x in re.findall(r'PD-(\d+)', sumber)})
    jenis = sumber.split()[0]
    svg, lebar, tinggi = rapikan_svg(svg_semua[i - 1], i)
    diagram.append(dict(no=i, judul=judul, deskripsi=deskripsi, pd=pd, jenis=jenis, svg=svg, lebar=lebar, tinggi=tinggi))

# --- Butir "Perlu dikonfirmasi" dari system-flow.md bagian 16 ---
b16 = flow.split('## 16. Perlu dikonfirmasi', 1)[1]
pd_html = []
for kelompok in re.split(r'\n### ', b16)[1:]:
    judul_k, *isi = kelompok.split('\n')
    butir = re.findall(r'^(\d+)\. (.+)$', '\n'.join(isi), re.M)
    pd_html.append(f'<h3>{inline(judul_k.strip())}</h3><ol class="pd-list">' + ''.join(
        f'<li id="pd-{n}" value="{n}"><span class="pd-no">PD-{n}</span><span>{inline(t)}</span></li>' for n, t in butir) + '</ol>')

LABEL_JENIS = {'flowchart': 'Flowchart', 'stateDiagram-v2': 'Diagram status', 'sequenceDiagram': 'Diagram urutan', 'erDiagram': 'Diagram relasi data'}

toc = ''.join(f'<li><a href="#diagram-{d["no"]}"><span class="toc-no">{d["no"]}</span>{inline(d["judul"])}</a></li>' for d in diagram)
kartu = []
for d in diagram:
    chips = ''
    if d['pd']:
        chips = '<p class="pd-chips"><span>Perlu dikonfirmasi:</span> ' + ' '.join(f'<a class="chip" href="#pd-{n}">PD-{n}</a>' for n in d['pd']) + '</p>'
    desk = f'<p class="desc">{inline(d["deskripsi"])}</p>' if d['deskripsi'] else ''
    kartu.append(f'''
<section class="card" id="diagram-{d["no"]}" aria-labelledby="judul-{d["no"]}">
  <header class="card-head">
    <div>
      <p class="eyebrow">Diagram {d["no"]} · {LABEL_JENIS.get(d["jenis"], d["jenis"])}</p>
      <h2 id="judul-{d["no"]}">{inline(d["judul"])}</h2>
    </div>
    <div class="actions">
      <button type="button" class="btn" data-perbesar="{d["no"]}" aria-label="Perbesar diagram {d["no"]}">Perbesar</button>
      <button type="button" class="btn ghost" data-unduh="{d["no"]}" aria-label="Unduh SVG diagram {d["no"]}">Unduh SVG</button>
    </div>
  </header>
  {desk}{chips}
  <figure class="diagram" data-no="{d["no"]}">{d["svg"]}</figure>
  <p class="geser" hidden>Diagram ini lebih lebar dari layar: geser ke samping di dalam kotak, atau tekan Perbesar.</p>
</section>''')

halaman = f'''<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Flowchart Sistem SIA VD</title>
<meta name="description" content="Diagram alur proses bisnis SIA VD berdasarkan kode aktual (commit {commit_kode}).">
<style>
:root {{
  --bg: #f6f5f4; --surface: #ffffff; --text: #1f1e1d; --muted: #615d59; --faint: #a39e98;
  --border: #e6e6e6; --accent: #0075de; --accent-soft: #eef5fc; --warn: #8a5a00; --warn-soft: #fff6e0;
  --paper: #ffffff; --shadow: 0 1px 3px rgba(0,0,0,.06);
  color-scheme: light;
}}
@media (prefers-color-scheme: dark) {{
  :root:not([data-theme="light"]) {{
    --bg: #161514; --surface: #1f1e1d; --text: #f1efed; --muted: #b9b4ae; --faint: #8a857f;
    --border: #34322f; --accent: #5aa9f0; --accent-soft: #1c2a38; --warn: #f0c56a; --warn-soft: #3a2f16;
    --paper: #ffffff; --shadow: none; color-scheme: dark;
  }}
}}
:root[data-theme="dark"] {{
  --bg: #161514; --surface: #1f1e1d; --text: #f1efed; --muted: #b9b4ae; --faint: #8a857f;
  --border: #34322f; --accent: #5aa9f0; --accent-soft: #1c2a38; --warn: #f0c56a; --warn-soft: #3a2f16;
  --paper: #ffffff; --shadow: none; color-scheme: dark;
}}
* {{ box-sizing: border-box; }}
html {{ scroll-behavior: smooth; }}
body {{ margin: 0; background: var(--bg); color: var(--text); font: 16px/1.6 system-ui, -apple-system, "Segoe UI", Roboto, "DejaVu Sans", sans-serif; }}
a {{ color: var(--accent); }}
code {{ font: .88em ui-monospace, "SFMono-Regular", Menlo, Consolas, monospace; background: var(--accent-soft); padding: .1em .35em; border-radius: 4px; overflow-wrap: anywhere; }}
.wrap {{ max-width: 1400px; margin: 0 auto; padding: 32px 16px 64px; }}
.top h1 {{ font-size: clamp(1.6rem, 3vw, 2.2rem); line-height: 1.2; margin: 0 0 .4rem; }}
.top p {{ margin: .2rem 0; color: var(--muted); max-width: 75ch; }}
.meta {{ font-size: .9rem; color: var(--faint) !important; }}
.layout {{ display: grid; gap: 24px; margin-top: 24px; }}
@media (min-width: 1100px) {{ .layout {{ grid-template-columns: 260px minmax(0, 1fr); }} }}
nav.toc {{ background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 12px; box-shadow: var(--shadow); }}
@media (min-width: 1100px) {{ nav.toc {{ position: sticky; top: 16px; max-height: calc(100vh - 32px); overflow: auto; }} }}
nav.toc summary {{ cursor: pointer; font-weight: 600; padding: 4px 6px; }}
nav.toc ol {{ list-style: none; margin: 8px 0 0; padding: 0; }}
nav.toc li a {{ display: flex; gap: 8px; padding: 6px 8px; border-radius: 8px; text-decoration: none; color: var(--text); font-size: .92rem; line-height: 1.35; }}
nav.toc li a:hover, nav.toc li a:focus-visible {{ background: var(--accent-soft); }}
.toc-no {{ flex: none; width: 1.8em; color: var(--faint); font-variant-numeric: tabular-nums; }}
main {{ display: grid; gap: 24px; min-width: 0; }}
.card {{ background: var(--surface); border: 1px solid var(--border); border-radius: 14px; padding: 20px; box-shadow: var(--shadow); min-width: 0; scroll-margin-top: 16px; }}
.card-head {{ display: flex; flex-wrap: wrap; gap: 12px; justify-content: space-between; align-items: flex-start; }}
.eyebrow {{ margin: 0; font-size: .78rem; letter-spacing: .06em; text-transform: uppercase; color: var(--faint); }}
.card h2 {{ margin: .15rem 0 0; font-size: 1.3rem; line-height: 1.3; }}
.desc {{ color: var(--muted); margin: .75rem 0 0; max-width: 80ch; }}
.pd-chips {{ margin: .6rem 0 0; font-size: .88rem; color: var(--muted); display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }}
.chip {{ background: var(--warn-soft); color: var(--warn); border-radius: 999px; padding: 1px 10px; text-decoration: none; font-weight: 600; }}
.actions {{ display: flex; gap: 8px; flex-wrap: wrap; }}
.btn {{ font: inherit; font-size: .88rem; border: 1px solid var(--accent); background: var(--accent); color: #fff; border-radius: 999px; padding: 6px 14px; cursor: pointer; }}
.btn.ghost {{ background: transparent; color: var(--accent); }}
.btn:focus-visible, nav.toc a:focus-visible, .chip:focus-visible {{ outline: 2px solid var(--accent); outline-offset: 2px; }}
/* Diagram selalu di atas kertas terang agar warna Mermaid tetap kontras di mode gelap. */
figure.diagram {{ margin: 16px 0 0; background: var(--paper); border: 1px solid var(--border); border-radius: 10px; padding: 16px; overflow-x: auto; text-align: center; }}
figure.diagram svg {{ width: 100%; height: auto; display: inline-block; }}
.legend {{ display: grid; gap: 10px; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-top: 12px; }}
.legend div {{ border: 1px solid var(--border); border-radius: 10px; padding: 10px 12px; font-size: .92rem; color: var(--muted); }}
.legend strong {{ color: var(--text); display: block; }}
.pd h3 {{ margin: 1.4rem 0 .4rem; font-size: 1.05rem; }}
.pd-list {{ list-style: none; margin: 0; padding: 0; display: grid; gap: 6px; }}
.pd-list li {{ display: grid; grid-template-columns: 4.2em minmax(0, 1fr); gap: 8px; padding: 8px 10px; border-radius: 8px; scroll-margin-top: 16px; }}
.pd-list li:target {{ background: var(--warn-soft); }}
.pd-no {{ font-weight: 700; color: var(--warn); font-variant-numeric: tabular-nums; }}
dialog {{ width: min(96vw, 1600px); height: min(92vh, 1200px); padding: 0; border: 1px solid var(--border); border-radius: 14px; background: var(--surface); color: var(--text); }}
dialog::backdrop {{ background: rgba(0,0,0,.55); }}
.dlg-head {{ display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 12px 16px; border-bottom: 1px solid var(--border); }}
.dlg-head h2 {{ margin: 0; font-size: 1.05rem; }}
.dlg-tools {{ display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }}
.dlg-body {{ height: calc(100% - 58px); overflow: auto; background: var(--paper); padding: 16px; }}
.dlg-body svg {{ display: block; margin: 0 auto; max-width: none !important; height: auto; }}
.geser {{ margin: 6px 0 0; font-size: .85rem; color: var(--faint); }}
footer {{ margin-top: 32px; color: var(--faint); font-size: .88rem; }}
@media print {{
  nav.toc, .actions, .btn {{ display: none !important; }}
  .layout {{ display: block; }}
  .card {{ break-inside: avoid; box-shadow: none; }}
  body {{ background: #fff; }}
}}
</style>
</head>
<body>
<div class="wrap">
  <header class="top">
    <h1>Flowchart Sistem SIA VD</h1>
    <p>Gambaran alur proses bisnis SIA VD, disusun dari kode aktual di cabang <code>main</code> pada commit <code>{commit_kode}</code>. Penjelasan teks tiap validasi, status, dan keterkaitan antarfitur ada di <code>docs/system-flow.md</code>.</p>
    <p>Butir bertanda PD-nomor merujuk ke daftar <a href="#perlu-dikonfirmasi">Perlu dikonfirmasi</a> di akhir halaman.</p>
    <p class="meta">{len(diagram)} diagram · dirender statis sehingga teks label tidak terpotong dan halaman bisa dibuka tanpa internet.</p>
  </header>
  <div class="layout">
    <nav class="toc" aria-label="Daftar diagram">
      <details open>
        <summary>Daftar diagram</summary>
        <ol>{toc}<li><a href="#cara-membaca"><span class="toc-no">i</span>Cara membaca</a></li><li><a href="#perlu-dikonfirmasi"><span class="toc-no">!</span>Perlu dikonfirmasi</a></li></ol>
      </details>
    </nav>
    <main>
      <section class="card" id="cara-membaca">
        <h2>Cara membaca</h2>
        <div class="legend">
          <div><strong>Kotak</strong>Langkah atau proses.</div>
          <div><strong>Belah ketupat</strong>Keputusan atau pemeriksaan; cabang diberi label Ya/Tidak atau kondisinya.</div>
          <div><strong>Kotak bersudut bulat</strong>Awal atau hasil akhir alur.</div>
          <div><strong>Garis putus-putus</strong>Catatan atau hubungan pendukung, bukan urutan langkah.</div>
          <div><strong>Diagram status</strong>Kotak adalah status yang tersimpan; panah adalah kejadian yang mengubahnya.</div>
          <div><strong>Tombol Perbesar</strong>Membuka diagram di ukuran asli agar teks kecil tetap mudah dibaca.</div>
        </div>
      </section>
      {''.join(kartu)}
      <section class="card pd" id="perlu-dikonfirmasi">
        <h2>Perlu dikonfirmasi</h2>
        <p class="desc">Perilaku di kode yang ambigu, tampak tidak konsisten, atau belum bisa dipastikan maksudnya (dari <code>docs/system-flow.md</code> bagian 16).</p>
        {''.join(pd_html)}
      </section>
      <footer>Disusun dari kode SIA VD commit <code>{commit_kode}</code>. Perbarui halaman ini bila alur di kode berubah.</footer>
    </main>
  </div>
</div>

<dialog id="dlg" aria-labelledby="dlg-judul">
  <div class="dlg-head">
    <h2 id="dlg-judul"></h2>
    <div class="dlg-tools">
      <button type="button" class="btn ghost" data-zoom="-1" aria-label="Perkecil">−</button>
      <button type="button" class="btn ghost" data-zoom="0" aria-label="Ukuran asli">100%</button>
      <button type="button" class="btn ghost" data-zoom="1" aria-label="Perbesar">+</button>
      <button type="button" class="btn" id="dlg-tutup">Tutup</button>
    </div>
  </div>
  <div class="dlg-body" id="dlg-body"></div>
</dialog>

<script>
(() => {{
  const dlg = document.getElementById('dlg');
  const body = document.getElementById('dlg-body');
  const judul = document.getElementById('dlg-judul');
  let skala = 1, svgAktif = null;
  const terapkan = () => {{
    if (!svgAktif) return;
    svgAktif.style.width = (Number(svgAktif.dataset.lebar) * skala) + 'px';
    dlg.querySelector('[data-zoom="0"]').textContent = Math.round(skala * 100) + '%';
  }};
  document.querySelectorAll('[data-perbesar]').forEach((btn) => btn.addEventListener('click', () => {{
    const no = btn.dataset.perbesar;
    const asli = document.querySelector(`figure[data-no="${{no}}"] svg`);
    body.replaceChildren();
    svgAktif = asli.cloneNode(true);
    // ID sengaja dipertahankan: gaya internal SVG Mermaid memakai selektor #id.
    body.appendChild(svgAktif);
    skala = 1; terapkan();
    judul.textContent = document.getElementById(`judul-${{no}}`).textContent;
    dlg.showModal();
  }}));
  dlg.querySelectorAll('[data-zoom]').forEach((b) => b.addEventListener('click', () => {{
    const z = Number(b.dataset.zoom);
    skala = z === 0 ? 1 : Math.min(3, Math.max(0.25, skala * (z > 0 ? 1.25 : 0.8)));
    terapkan();
  }}));
  // Petunjuk geser hanya untuk diagram yang benar-benar lebih lebar dari kotaknya.
  const cekGeser = () => document.querySelectorAll('figure.diagram').forEach((f) => {{
    f.nextElementSibling.hidden = f.scrollWidth <= f.clientWidth + 1;
  }});
  cekGeser();
  window.addEventListener('resize', cekGeser);
  // Alur Mermaid dimulai di tengah atas, jadi diagram yang lebih lebar dari kotak dibuka dari tengah.
  document.querySelectorAll('figure.diagram').forEach((f) => {{ f.scrollLeft = (f.scrollWidth - f.clientWidth) / 2; }});
  if (window.matchMedia('(max-width: 1099px)').matches) document.querySelector('nav.toc details').removeAttribute('open');
  document.getElementById('dlg-tutup').addEventListener('click', () => dlg.close());
  dlg.addEventListener('click', (e) => {{ if (e.target === dlg) dlg.close(); }});
  document.querySelectorAll('[data-unduh]').forEach((btn) => btn.addEventListener('click', () => {{
    const no = btn.dataset.unduh;
    const svg = document.querySelector(`figure[data-no="${{no}}"] svg`).cloneNode(true);
    svg.setAttribute('width', svg.dataset.lebar);
    svg.setAttribute('height', svg.dataset.tinggi);
    svg.removeAttribute('style');
    const blob = new Blob([new XMLSerializer().serializeToString(svg)], {{ type: 'image/svg+xml' }});
    const a = Object.assign(document.createElement('a'), {{ href: URL.createObjectURL(blob), download: `sia-vd-diagram-${{no}}.svg` }});
    a.click();
    setTimeout(() => URL.revokeObjectURL(a.href), 1000);
  }}));
}})();
</script>
</body>
</html>
'''
(DOCS / 'system-flowchart.html').write_text(halaman)
print('ok', len(halaman), 'byte,', len(diagram), 'diagram,', sum(len(re.findall(r'id="pd-', x)) for x in pd_html), 'PD')
