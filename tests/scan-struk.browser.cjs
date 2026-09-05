// Pengujian browser tanpa dependensi npm dan tanpa menyentuh database aplikasi.
// Node 22+, PHP, dan Chrome/Edge diperlukan. Tambahkan --ocr untuk menguji CDN/OCR asli.
const assert = require("node:assert/strict")
const fs = require("node:fs")
const os = require("node:os")
const path = require("node:path")
const http = require("node:http")
const { spawn, spawnSync } = require("node:child_process")

const root = path.resolve(__dirname, "..")
const browserPath = process.env.BROWSER_PATH || [
  "C:/Program Files/Google/Chrome/Application/chrome.exe",
  "C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe",
].find((candidate) => fs.existsSync(candidate))
assert.ok(browserPath, "Set BROWSER_PATH ke lokasi Chrome/Edge")
const source = fs.readFileSync(path.join(root, "index.php"), "utf8")
const desktopForm = source.match(/<form action="tambah.php" method="post" class="transaction-form">[\s\S]*?<\/form>/)[0]
const categoryScript = source.match(/\/\/ Saring pilihan kategori sesuai tipe transaksi[\s\S]*?\/\/ Ekspor PDF bulan ini/)[0]
const fixturePhp = `<?php
function csrf_field() { return '<input type="hidden" name="csrf_token" value="test-only">'; }
function e($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function ambil_kategori($db, $uid) { return [
  ['id'=>1, 'tipe'=>'pemasukan', 'nama'=>'Gaji'],
  ['id'=>2, 'tipe'=>'pengeluaran', 'nama'=>'Belanja']
]; }
$koneksi = null; $UID = 1; $daftar_kategori = ambil_kategori(null, 1);
?><!doctype html><html lang="id"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="styles.css"></head><body>
<div class="sidebar"></div><main class="content"><header class="content-header"></header>
<div class="card add-transaction-card"><div class="card-body">${desktopForm}</div></div></main>
<script src="script.js"></script><script>${categoryScript}\n</script>
<?php require 'partial_mobilenav.php'; ?></body></html>`
const rendered = spawnSync(process.env.PHP_PATH || "php", [], { cwd: root, input: fixturePhp, encoding: "utf8", windowsHide: true })
assert.equal(rendered.status, 0, rendered.stderr)

const posts = []
const server = http.createServer((req, res) => {
  if (req.method === "POST") {
    let body = ""
    req.on("data", (chunk) => { body += chunk })
    req.on("end", () => { posts.push(Object.fromEntries(new URLSearchParams(body))); res.end("saved-test-only") })
    return
  }
  if (req.url === "/") { res.setHeader("Content-Type", "text/html; charset=utf-8"); res.end(rendered.stdout); return }
  const file = new URL(req.url, "http://localhost").pathname.slice(1)
  if (!["styles.css", "script.js", "receipt-parser.js", "receipt-crop.js", "scan-struk.js"].includes(file)) { res.writeHead(404); res.end(); return }
  res.setHeader("Content-Type", file.endsWith(".css") ? "text/css" : "text/javascript")
  res.end(fs.readFileSync(path.join(root, file)))
})
const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms))
let browser, socket, profile
const pending = new Map()
let sequence = 0
const errors = []

async function main() {
  await new Promise((resolve) => server.listen(0, "127.0.0.1", resolve))
  profile = fs.mkdtempSync(path.join(os.tmpdir(), "fintrack-scan-test-"))
  browser = spawn(browserPath, ["--headless=new", "--disable-gpu", "--no-first-run", "--no-default-browser-check",
    "--remote-debugging-port=0", `--user-data-dir=${profile}`, "about:blank"], { windowsHide: true, stdio: "ignore" })
  const portFile = path.join(profile, "DevToolsActivePort")
  for (let i = 0; i < 100 && !fs.existsSync(portFile); i++) await sleep(100)
  assert.ok(fs.existsSync(portFile), "Browser tidak menyediakan port debugging")
  const port = fs.readFileSync(portFile, "utf8").split("\n")[0]
  const targets = await fetch(`http://127.0.0.1:${port}/json/list`).then((response) => response.json())
  socket = new WebSocket(targets.find((target) => target.type === "page").webSocketDebuggerUrl)
  await new Promise((resolve, reject) => { socket.onopen = resolve; socket.onerror = reject })
  socket.onmessage = ({ data }) => {
    const message = JSON.parse(data)
    if (message.method === "Runtime.exceptionThrown") errors.push(message.params.exceptionDetails)
    if (!message.id) return
    const callback = pending.get(message.id)
    if (!callback) return
    pending.delete(message.id)
    message.error ? callback.reject(message.error) : callback.resolve(message.result)
  }
  socket.onclose = () => {
    pending.forEach(({ reject }) => reject(new Error("Koneksi browser ditutup")))
    pending.clear()
  }
  const cdp = (method, params = {}) => new Promise((resolve, reject) => {
    const id = ++sequence
    const timer = setTimeout(() => { pending.delete(id); reject(new Error(`CDP timeout: ${method}`)) }, 15000)
    pending.set(id, {
      resolve: (result) => { clearTimeout(timer); resolve(result) },
      reject: (error) => { clearTimeout(timer); reject(error) },
    })
    socket.send(JSON.stringify({ id, method, params }))
  })
  const evaluate = async (fn, ...args) => {
    const result = await cdp("Runtime.evaluate", {
      expression: `(${fn.toString()})(...${JSON.stringify(args)})`, returnByValue: true, awaitPromise: true,
    })
    if (result.exceptionDetails) throw new Error(JSON.stringify(result.exceptionDetails))
    return result.result.value
  }
  const until = async (fn, message, timeout = 15000) => {
    const started = Date.now()
    while (Date.now() - started < timeout) { if (await evaluate(fn)) return; await sleep(100) }
    throw new Error(message)
  }
  await cdp("Runtime.enable")
  await cdp("Page.enable")
  await cdp("Emulation.setDeviceMetricsOverride", { width: 1280, height: 1000, deviceScaleFactor: 1, mobile: false })
  await cdp("Page.navigate", { url: `http://127.0.0.1:${server.address().port}/` })
  await until(() => !!window.ReceiptParser && document.readyState === "complete", "Halaman belum siap")
  assert.equal(await evaluate(() => Array.from(document.querySelectorAll('form')).filter((form) => form.querySelector('[data-scan-struk]')).every((form) =>
    form.dataset.transactionMode === 'photo' && form.querySelector('[data-transaction-fields]').hidden && form.querySelector('[data-transaction-fields]').disabled)), true)
  assert.equal(await evaluate(() => document.querySelector('.transaction-form').dispatchEvent(new Event('submit', { cancelable: true }))), false)
  await evaluate(() => document.querySelector('[data-transaction-manual]').click())
  assert.equal(await evaluate(() => document.activeElement.id), 'keterangan')
  assert.equal(await evaluate(() => document.querySelector('.transaction-form').jumlah.matches(':disabled')), false)
  await evaluate(() => {
    document.querySelector('.transaction-form').jumlah.value = '12.000'
    document.querySelector('[data-transaction-photo]').click()
    document.querySelector('[data-transaction-manual]').click()
  })
  assert.equal(await evaluate(() => document.querySelector('.transaction-form').jumlah.value), '12.000')
  await evaluate(() => {
    document.querySelector('.transaction-form').jumlah.value = ''
    document.querySelector('[data-transaction-photo]').click()
  })
  console.log('PASS: awal mode foto, input manual bisa dipakai, draf terjaga, dan mode foto tidak bisa dikirim')
  await evaluate(() => {
    window.mockText = "SUBTOTAL 70.000\nPPN 5.000\nGR4ND T0TA1 75.000,00\nTUNAI 100.000\nKEMBALI 25.000"
    window.Tesseract = {
      createWorker: async () => ({
        setParameters: async () => {},
        recognize: async (canvas) => {
          window.mockReadCount = (window.mockReadCount || 0) + 1
          window.lastScanSize = [canvas.width, canvas.height]
          if (window.mockHold) await new Promise((resolve) => { window.releaseMock = resolve })
          if (window.mockHoldAt === window.mockReadCount) await new Promise((resolve) => { window.releaseMock = resolve })
          if (window.mockError) throw new Error("Koneksi OCR putus")
          const next = window.mockReadings?.shift()
          if (next) window.lastMockReading = next
          const data = window.mockReadings ? window.lastMockReading : { text: window.mockText, confidence: 96 }
          if (data?.error) throw new Error("Koneksi OCR putus saat pembacaan lanjutan")
          return { data }
        },
        terminate: async () => {},
      }),
    }
    window.pickReceipt = async (panelIndex = 0, invalid = false) => {
      const canvas = document.createElement("canvas")
      canvas.width = 1000; canvas.height = 900
      const ctx = canvas.getContext("2d")
      ctx.fillStyle = "white"; ctx.fillRect(0, 0, 1000, 900)
      ctx.fillStyle = "black"; ctx.font = "32px monospace"
      if (window.receiptScenario === "shadow-skew") {
        const shade = ctx.createLinearGradient(0, 0, 1000, 900)
        shade.addColorStop(0, "#bcb6ae"); shade.addColorStop(1, "#fbf7f1")
        ctx.fillStyle = shade; ctx.fillRect(0, 0, 1000, 900)
        ctx.translate(500, 450); ctx.rotate(4 * Math.PI / 180); ctx.translate(-500, -450)
        ctx.fillStyle = "#6c6660"; ctx.font = "24px monospace"
      }
      const lines = ["TOKO CONTOH", "STRUK BELANJA", "---------------------------", "SUSU                 50.000", "ROTI                 20.000", "SUBTOTAL             70.000", "PPN                   5.000", "TOTAL                75.000", "TUNAI               100.000", "KEMBALI              25.000"]
      lines.forEach((line, index) => ctx.fillText(line, 45, 80 + index * 65))
      const blob = await new Promise((resolve) => canvas.toBlob(resolve, "image/png"))
      const file = new File([blob], invalid ? "struk.pdf" : "struk.png", { type: invalid ? "application/pdf" : "image/png" })
      const transfer = new DataTransfer(); transfer.items.add(file)
      const input = document.querySelectorAll("[data-scan-upload-input]")[panelIndex]
      input.files = transfer.files; input.dispatchEvent(new Event("change", { bubbles: true }))
    }
  })
  const done = () => !document.querySelector("[data-scan-struk][aria-busy]")
  const state = () => {
    const form = document.querySelector(".transaction-form")
    return { amount: form.jumlah.value, type: form.tipe.value, description: form.keterangan.value,
      categoryOptions: Array.from(form.kategori_id.options, (option) => option.value),
      status: form.querySelector("[data-scan-status]").textContent,
      choice: !form.querySelector("[data-scan-choice]").hidden }
  }
  await evaluate(() => window.pickReceipt())
  await until(done, "Scan belum selesai")
  let current = await evaluate(state)
  assert.equal(current.amount, "75.000")
  assert.equal(current.type, "pengeluaran")
  assert.equal(current.description, "Belanja dari struk")
  assert.equal(await evaluate(() => document.querySelector('.transaction-form').dataset.transactionMode), 'review')
  assert.equal(await evaluate(() => document.querySelector('.transaction-form').jumlah.matches(':disabled')), false)
  assert.equal(await evaluate(() => document.querySelector('[data-scan-struk]').hidden), true)
  assert.deepEqual(current.categoryOptions, ["", "2"])
  assert.equal(posts.length, 0, "Scan tidak boleh otomatis menyimpan transaksi")
  console.log("PASS: scan mengisi total, mengubah tipe, menyaring kategori, dan menunggu Simpan")

  await evaluate(async () => { window.mockText = "TOTAL 50.000\nTOTAL 75.000"; await window.pickReceipt() })
  await until(done, "Pilihan total belum selesai")
  current = await evaluate(state)
  assert.equal(current.amount, "50.000")
  assert.equal(current.choice, true)
  await evaluate(() => {
    const select = document.querySelector("[data-scan-candidates]")
    select.value = "50000"; select.dispatchEvent(new Event("change", { bubbles: true }))
  })
  assert.equal((await evaluate(state)).amount, "50.000")
  await evaluate(async () => { window.mockText = "SUBTOTAL 50.000\nTUNAI 100.000"; await window.pickReceipt() })
  await until(done, "Scan tanpa total belum selesai")
  assert.equal((await evaluate(state)).amount, "")
  assert.match((await evaluate(state)).status, /belum terbaca/)
  assert.equal((await evaluate(state)).type, "pengeluaran")
  console.log("PASS: saran total diisi otomatis dan alternatif bisa dipilih; scan baru tidak memakai total scan lama")

  await evaluate(async () => {
    document.querySelector(".transaction-form").jumlah.value = "12.000"
    await window.pickReceipt(0, true)
  })
  assert.equal((await evaluate(state)).amount, "12.000")
  assert.match((await evaluate(state)).status, /Pilih gambar/)
  await evaluate(async () => { window.mockHold = true; await window.pickReceipt() })
  await until(() => typeof window.releaseMock === "function", "Mock scan tidak berjalan")
  assert.equal(await evaluate(() => document.querySelector(".transaction-form").dispatchEvent(new Event("submit", { cancelable: true }))), false)
  await evaluate(() => document.querySelector("[data-scan-cancel]").click())
  await until(done, "Pembatalan belum selesai")
  await evaluate(() => { window.mockHold = false; window.releaseMock() })
  assert.equal((await evaluate(state)).amount, "12.000")
  await evaluate(async () => { window.mockError = true; await window.pickReceipt() })
  await until(done, "Kegagalan scan tidak dipulihkan")
  assert.equal(await evaluate(() => document.querySelector(".transaction-form").jumlah.disabled), false)
  console.log("PASS: gambar invalid, batal scan, dan OCR gagal tetap menyediakan input manual")

  await evaluate(async () => {
    window.mockError = false
    window.mockText = '<img src=x onerror="window.ocrInjected=true">\nTOTAL 75.000'
    await window.pickReceipt()
  })
  await until(done, "Teks OCR belum ditampilkan")
  assert.equal(await evaluate(() => !!window.ocrInjected || !!document.querySelector("[data-scan-text] img")), false)
  await evaluate(() => { document.querySelector(".transaction-form").jumlah.value = "12.000" })
  console.log("PASS: teks OCR ditampilkan sebagai teks, tidak dijalankan sebagai HTML")

  await evaluate(async () => {
    document.querySelector(".transaction-form").jumlah.value = ""
    window.mockReadCount = 0
    window.mockReadings = [
      { text: "TOTA? 75.OOO", confidence: 45 },
      { text: "TOTAL BAYAR 75.000", confidence: 92 },
    ]
    await window.pickReceipt()
  })
  await until(done, "Pembacaan ulang belum selesai")
  assert.equal((await evaluate(state)).amount, "75.000")
  assert.equal(await evaluate(() => window.mockReadCount), 2)
  await evaluate(async () => {
    window.mockReadings = [
      { text: "TOTAL 75.000", confidence: 50 },
      { text: "TOTAL 79.000", confidence: 93 },
    ]
    await window.pickReceipt()
  })
  await until(done, "Perbandingan pembacaan belum selesai")
  assert.equal((await evaluate(state)).amount, "79.000")
  assert.equal((await evaluate(state)).choice, true)
  assert.deepEqual(await evaluate(() => Array.from(document.querySelector("[data-scan-candidates]").options, (option) => option.value)), ["", "79000", "75000"])
  await evaluate(async () => {
    window.mockReadings = [
      { text: "TOTAL 75.000", confidence: 40 },
      { text: "TOTAL 75.000", confidence: 55 },
    ]
    await window.pickReceipt()
  })
  await until(done, "Hasil kurang yakin belum selesai")
  assert.equal((await evaluate(state)).amount, "75.000")
  assert.match((await evaluate(state)).status, /Saran total/)
  console.log("PASS: pembacaan otomatis memilih nominal terbaik; hasil ragu diisi sebagai saran yang bisa diedit")

  await evaluate(async (text) => {
    document.querySelector(".transaction-form").jumlah.value = ""
    window.mockReadCount = 0
    window.totalReading = (pageScore, numberScore) => ({
      text, confidence: pageScore,
      blocks: [{ paragraphs: [{ lines: [{
        text: "TOTAL                   42900. 00", confidence: 95,
        words: [
          { text: "TOTAL", confidence: 99 },
          { text: "42900.", confidence: numberScore },
          { text: "00", confidence: numberScore },
        ],
      }] }] }],
    })
    window.mockReadings = [window.totalReading(40, 94)]
    await window.pickReceipt()
  }, fs.readFileSync(path.join(__dirname, "fixtures/miniso-ocr.txt"), "utf8"))
  await until(done, "Regresi MINISO belum selesai")
  assert.equal((await evaluate(state)).amount, "42.900")
  assert.equal((await evaluate(state)).type, "pengeluaran")
  assert.equal(await evaluate(() => window.mockReadCount), 1)
  await evaluate(async () => {
    window.mockReadings = [window.totalReading(98, 35), window.totalReading(98, 45)]
    await window.pickReceipt()
  })
  await until(done, "Pemeriksaan angka ragu belum selesai")
  assert.equal((await evaluate(state)).amount, "42.900")
  assert.match((await evaluate(state)).status, /Saran total/)
  assert.equal((await evaluate(state)).choice, false)
  await evaluate(() => { document.querySelector(".transaction-form").jumlah.value = "12.000" })
  console.log("PASS: output MINISO mengisi Rp42.900; teks toko buruk tidak menurunkan skor total, angka ragu ditandai sebagai saran")

  await evaluate(async (text) => {
    document.querySelector(".transaction-form").jumlah.value = ""
    window.mockReadCount = 0
    window.mockReadings = [{ text, confidence: 40 }, { text: "ICHIBAN SUSHI\nMall Bali Galeria", confidence: 94 }]
    await window.pickReceipt()
  }, fs.readFileSync(path.join(__dirname, "fixtures/ichiban-ocr.txt"), "utf8"))
  await until(done, "Cadangan Ichiban belum selesai")
  assert.equal((await evaluate(state)).amount, "248.325")
  assert.equal((await evaluate(state)).choice, false)
  assert.match(await evaluate(() => document.querySelector("[data-scan-check-list]").textContent), /300\.000.*51\.675.*248\.325/)
  assert.equal(await evaluate(() => window.mockReadCount), 4)
  assert.equal(posts.length, 0)
  assert.equal((await evaluate(state)).amount, "248.325")
  console.log("PASS: OCR Ichiban rusak menghasilkan saran Rp248.325 dari tunai/kembalian, otomatis diisi tanpa menyimpan transaksi")

  await evaluate(async () => {
    window.mockReadings = [{ text: 'TOTAL 250.000\nCASH 300.000\nCHANGE 51.675', confidence: 96 }]
    await window.pickReceipt()
  })
  await until(done, 'Saran hitungan belum diprioritaskan')
  assert.equal((await evaluate(state)).amount, '248.325')
  assert.equal((await evaluate(state)).choice, true)
  assert.equal(await evaluate(() => document.querySelector('[data-scan-candidates]').value), '248325')
  assert.match(await evaluate(() => document.querySelector('[data-transaction-summary]').textContent), /300\.000.*51\.675/)
  assert.equal(posts.length, 0)
  console.log('PASS: saran didukung hitungan mengisi draf, alternatif OCR tersedia tanpa otomatis menyimpan')

  await evaluate(async () => {
    window.mockReadings = [{ text: "TOTAL 75.000", confidence: 60 }, { error: true }]
    await window.pickReceipt()
  })
  await until(done, "Pemulihan kegagalan lanjutan belum selesai")
  assert.equal((await evaluate(state)).amount, "75.000")
  assert.match((await evaluate(state)).status, /lanjutan terhenti/)
  assert.match(await evaluate(() => document.querySelector("[data-scan-text]").textContent), /TOTAL 75\.000/)
  assert.equal((await evaluate(state)).choice, false)
  console.log("PASS: kegagalan pembacaan lanjutan mempertahankan hasil awal untuk diperiksa")

  await evaluate(async () => {
    const realTimeout = window.setTimeout
    window.setTimeout = (fn, delay, ...args) => realTimeout(fn, delay === 120000 ? 1000 : delay, ...args)
    window.mockReadCount = 0
    window.mockHoldAt = 2
    window.mockReadings = [{ text: "TOTAL 75.000", confidence: 60 }]
    try { await window.pickReceipt() } finally { window.setTimeout = realTimeout }
  })
  await until(done, "Batas waktu tidak memulihkan form")
  assert.match((await evaluate(state)).status, /lanjutan terhenti/)
  assert.equal((await evaluate(state)).amount, "75.000")
  assert.equal(await evaluate(() => document.querySelector(".transaction-form").jumlah.disabled), false)
  assert.equal((await evaluate(state)).choice, false)
  await evaluate(() => { window.mockHoldAt = null; window.releaseMock() })
  console.log("PASS: timeout melepaskan form dan mempertahankan hasil awal dengan saran otomatis yang bisa diedit")

  await evaluate(() => { document.querySelector(".transaction-form").jumlah.value = "12.000" })

  await cdp("Emulation.setDeviceMetricsOverride", { width: 390, height: 844, deviceScaleFactor: 1, mobile: true })
  await evaluate(() => {
    document.documentElement.setAttribute('data-theme', 'dark')
    document.getElementById('mnavFab').click()
  })
  await until(() => document.activeElement.matches('[data-scan-camera]'), 'Mode foto HP harus fokus tombol kamera')
  assert.equal(await evaluate(() => document.querySelector('.sheet-form').dataset.transactionMode), 'photo')
  if (process.argv.includes('--screenshot')) {
    const screenshot = await cdp('Page.captureScreenshot', { format: 'png' })
    const screenshotPath = path.join(os.tmpdir(), 'fintrack-photo-mobile.png')
    fs.writeFileSync(screenshotPath, Buffer.from(screenshot.data, 'base64'))
    console.log(`Screenshot: ${screenshotPath}`)
  }
  await evaluate(() => {
    document.querySelectorAll('[data-transaction-manual]')[1].click()
    document.getElementById('s_keterangan').value = 'Draf manual'
    document.getElementById('sheetClose').click()
    document.getElementById('mnavFab').click()
  })
  assert.equal(await evaluate(() => document.querySelector('.sheet-form').dataset.transactionMode), 'photo')
  assert.equal(await evaluate(() => document.getElementById('s_keterangan').value), 'Draf manual')
  console.log('PASS: Tambah Transaksi HP membuka foto, tanpa keyboard, dan menyimpan draf ketika dibuka ulang')
  await evaluate(async () => {
    document.documentElement.setAttribute("data-theme", "dark")
    window.mockReadings = null
    window.mockError = false; window.mockText = "JUM1AH HARU5 D1BAYAR 42.500"
    await window.pickReceipt(1)
  })
  await until(done, "Scan mobile belum selesai")
  assert.equal(await evaluate(() => document.getElementById("s_jumlah").value), "42.500")
  assert.equal((await evaluate(state)).amount, "12.000", "Scan mobile tidak boleh mengubah form desktop")
  assert.equal(await evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true)
  console.log("PASS: form HP, isolasi form, dan lebar layar 390px dalam mode gelap")

  assert.equal(await evaluate(() => document.querySelector('[data-scan-retry], [data-scan-crop], [data-receipt-crop]')), null)
  await evaluate(() => {
    const form = document.querySelector('.sheet-form')
    form.querySelector('[data-transaction-photo]').click()
    form.querySelector('[data-transaction-manual]').click()
    form.jumlah.value = '125.000'
  })
  assert.equal(await evaluate(() => document.querySelector('.sheet-form').checkValidity()), true)
  await evaluate(() => {
    const form = document.querySelector('.sheet-form')
    form.tipe.value = 'pemasukan'
    form.tipe.dispatchEvent(new Event('change', { bubbles: true }))
  })
  assert.deepEqual(await evaluate(() => Array.from(document.querySelector('.sheet-form').kategori_id.options, option => option.value)), ['', '1'])
  await evaluate(() => {
    const form = document.querySelector('.sheet-form')
    form.tipe.value = 'pengeluaran'
    form.tipe.dispatchEvent(new Event('change', { bubbles: true }))
  })
  if (process.argv.includes('--screenshot')) {
    await evaluate(async () => {
      await Promise.all(document.getElementById('sheetTambah').getAnimations().map(animation => animation.finished))
      document.querySelector('.sheet-form').scrollTop = 0
    })
    const screenshot = await cdp('Page.captureScreenshot', { format: 'png' })
    const screenshotPath = path.join(os.tmpdir(), 'fintrack-manual-mobile.png')
    fs.writeFileSync(screenshotPath, Buffer.from(screenshot.data, 'base64'))
    console.log('Screenshot: ' + screenshotPath)
  }
  console.log('PASS: form manual valid, kategori mengikuti tipe, tombol crop/baca ulang tidak ada')

  if (process.argv.includes("--ocr")) {
    console.log("Menguji Tesseract dan CDN asli dengan gambar struk sintetis...")
    await evaluate(async () => { delete window.Tesseract; await window.pickReceipt(1) })
    await until(done, "OCR asli tidak selesai", 130000)
    const actual = await evaluate(() => ({
      amount: document.getElementById("s_jumlah").value,
      status: document.querySelectorAll("[data-scan-status]")[1].textContent,
      text: document.querySelectorAll("[data-scan-text]")[1].textContent,
    }))
    assert.equal(actual.amount, "75.000", JSON.stringify(actual))
    console.log("PASS: OCR asli membaca total Rp75.000 dari PNG, mengabaikan tunai Rp100.000")

    await evaluate(async () => {
      window.receiptScenario = "shadow-skew"
      await window.pickReceipt(1)
    })
    await until(done, "OCR foto miring/berbayang tidak selesai", 130000)
    const shadowed = await evaluate(() => ({
      amount: document.getElementById("s_jumlah").value,
      text: document.querySelectorAll("[data-scan-text]")[1].textContent,
      status: document.querySelectorAll("[data-scan-status]")[1].textContent,
    }))
    assert.equal(shadowed.amount, "75.000", JSON.stringify(shadowed))
    assert.match(shadowed.text, /TOKO\s+CONTOH/i)
    assert.match(shadowed.text, /SUSU/i)
    assert.match(shadowed.text, /ROTI/i)
    console.log("PASS: OCR asli membaca nama toko, item, dan total pada contoh teks kecil miring 4 derajat dengan bayangan")

  }

  await evaluate(() => {
    const form = document.querySelector(".sheet-form")
    if (form.dataset.transactionMode === 'photo') form.querySelector('[data-transaction-manual]').click()
    form.jumlah.value = "74.000"
    form.keterangan.value = "Belanja diperiksa"
    form.requestSubmit()
  })
  for (let i = 0; i < 100 && posts.length === 0; i++) await sleep(100)
  assert.equal(posts.length, 1)
  assert.equal(posts[0].jumlah, "74000")
  assert.equal(posts[0].tipe, "pengeluaran")
  assert.equal(posts[0].csrf_token, "test-only")
  assert.equal(posts[0].keterangan, "Belanja diperiksa")
  assert.ok(!Object.keys(posts[0]).some((key) => /file|image|scan/i.test(key)))
  assert.equal(errors.length, 0, JSON.stringify(errors))
  console.log("PASS: nominal yang dikoreksi dikirim sekali lewat POST transaksi beserta CSRF, tanpa foto")
  await cdp("Browser.close")
}

main().catch((error) => { console.error(error); process.exitCode = 1 }).finally(async () => {
  if (socket) socket.close()
  if (browser && browser.exitCode === null) { browser.kill(); await sleep(1000) }
  server.closeAllConnections(); server.close()
  // Hapus hanya profil browser sementara yang dibuat pengujian ini.
  if (profile && path.dirname(profile) === path.resolve(os.tmpdir()) && path.basename(profile).startsWith("fintrack-scan-test-")) {
    try { fs.rmSync(profile, { recursive: true, force: true, maxRetries: 3, retryDelay: 250 }) } catch {}
  }
})
