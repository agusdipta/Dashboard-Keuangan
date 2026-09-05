/* OCR dimuat saat diperlukan; gambar tidak diunggah ke server. */
;(function () {
  const CDN = "https://cdn.jsdelivr.net/npm/tesseract.js@6.0.1/dist/"
  let libraryPromise
  let activeScan = null

  function loadOcr() {
    if (window.Tesseract) return Promise.resolve(window.Tesseract)
    if (!libraryPromise) {
      libraryPromise = new Promise((resolve, reject) => {
        const script = document.createElement("script")
        const fail = () => {
          clearTimeout(timer)
          script.remove()
          reject(new Error("Mesin scan gagal dimuat. Periksa internet, lalu pilih gambar lagi."))
        }
        const timer = setTimeout(fail, 30000)
        script.src = CDN + "tesseract.min.js"
        script.onload = () => {
          clearTimeout(timer)
          if (window.Tesseract) resolve(window.Tesseract)
          else fail()
        }
        script.onerror = fail
        document.head.appendChild(script)
      }).catch((error) => {
        libraryPromise = null
        throw error
      })
    }
    return libraryPromise
  }

  async function prepareImage(file) {
    const url = URL.createObjectURL(file)
    try {
      const img = new Image()
      img.src = url
      await img.decode()
      // Pertahankan lebih banyak detail daripada batas lama 1800 px / 6 MP.
      // Pembesaran dibatasi 2x; batas piksel menjaga penggunaan memori di HP.
      const scale = Math.min(2, 2600 / img.naturalWidth, 6000 / img.naturalHeight,
        Math.sqrt(8000000 / (img.naturalWidth * img.naturalHeight)))
      const width = Math.max(1, Math.round(img.naturalWidth * scale))
      const height = Math.max(1, Math.round(img.naturalHeight * scale))
      const border = 16
      const canvas = document.createElement("canvas")
      canvas.width = width + border * 2
      canvas.height = height + border * 2
      const ctx = canvas.getContext("2d")
      ctx.fillStyle = "#fff"
      ctx.fillRect(0, 0, canvas.width, canvas.height)
      ctx.imageSmoothingQuality = "high"
      ctx.drawImage(img, border, border, width, height)
      return canvas
    } finally {
      URL.revokeObjectURL(url)
    }
  }

  function improveContrast(source) {
    const canvas = document.createElement("canvas")
    canvas.width = source.width
    canvas.height = source.height
    const ctx = canvas.getContext("2d")
    ctx.drawImage(source, 0, 0)
    const pixels = ctx.getImageData(0, 0, canvas.width, canvas.height)
    const histogram = new Uint32Array(256)
    const data = pixels.data
    for (let i = 0; i < data.length; i += 4) {
      const grey = Math.round(data[i] * 0.299 + data[i + 1] * 0.587 + data[i + 2] * 0.114)
      data[i] = grey
      histogram[grey]++
    }
    const percentile = (fraction) => {
      const target = canvas.width * canvas.height * fraction
      let count = 0
      for (let value = 0; value < 256; value++) {
        count += histogram[value]
        if (count >= target) return value
      }
      return 255
    }
    const low = percentile(0.01)
    const high = percentile(0.99)
    for (let i = 0; i < data.length; i += 4) {
      const grey = high - low >= 20 ? (data[i] - low) * 255 / (high - low) : data[i]
      data[i] = data[i + 1] = data[i + 2] = grey
    }
    // Tetap abu-abu, bukan ambang hitam-putih tetap yang bisa menghapus titik nominal.
    ctx.putImageData(pixels, 0, 0)
    return canvas
  }

  function totalConfidence(data, amount) {
    const scores = []
    // Nilai kualitas baris total; kode barang/nama toko tidak menentukan kualitasnya.
    for (const block of data.blocks || []) {
      for (const paragraph of block.paragraphs || []) {
        for (const line of paragraph.lines || []) {
          if (!window.ReceiptParser.findTotal(line.text || "").candidates.some((candidate) => candidate.amount === amount)) continue
          const numericScores = (line.words || [])
            .filter((word) => /\d/.test(word.text || "") && Number.isFinite(word.confidence))
            .map((word) => word.confidence)
          // Angka yang ragu tetap ditahan meskipun kata TOTAL terbaca sangat baik.
          if (Number.isFinite(line.confidence)) numericScores.push(line.confidence)
          if (numericScores.length) scores.push(Math.min(...numericScores))
        }
      }
    }
    return scores.length ? Math.max(...scores) : (Number.isFinite(data.confidence) ? data.confidence : 0)
  }

  function focusSummary(source) {
    // Area ringkasan biasanya di bawah. Potong kanvas sebelum OCR/rotasi agar
    // koordinat tidak tertukar dengan bounding box hasil yang sudah diputar.
    const top = Math.floor(source.height * 0.45)
    const canvas = document.createElement("canvas")
    canvas.width = source.width
    canvas.height = source.height - top + 32
    const ctx = canvas.getContext("2d")
    ctx.fillStyle = "#fff"
    ctx.fillRect(0, 0, canvas.width, canvas.height)
    ctx.drawImage(source, 0, top, source.width, source.height - top, 0, 16, source.width, source.height - top)
    return canvas
  }

  function combineReadings(readings, incomplete = false) {
    const totals = new Map()
    const checks = new Map()
    readings.forEach(({ result, suggestions }) => {
      result.candidates.forEach((candidate) => {
        const previous = totals.get(candidate.amount)
        if (!previous || candidate.confidence > previous.confidence) totals.set(candidate.amount, { ...candidate, derived: false })
      })
      suggestions.forEach((suggestion) => checks.set(`${suggestion.method}:${suggestion.amount}`, suggestion))
    })
    for (const suggestion of checks.values()) {
      if (!totals.has(suggestion.amount)) totals.set(suggestion.amount, { ...suggestion, confidence: 0, derived: true })
    }
    const candidates = Array.from(totals.values())
    // Dahulukan nominal yang didukung hitungan independen, lalu kualitas OCR.
    // Semua pilihan hanya mengisi draf; pengguna tetap menekan Simpan.
    const support = (candidate) => Array.from(checks.values()).filter((check) => check.amount === candidate.amount).length
    candidates.sort((a, b) => support(b) - support(a) || b.confidence - a.confidence)
    const preferred = candidates[0]
    const uncertain = incomplete || candidates.length > 1 || !!preferred && (preferred.derived || preferred.confidence < 70)
    return {
      text: readings.map(({ text, label }, index) => readings.length === 1 ? text
        : `Pembacaan ${index + 1} (${label}):\n${text || "Tidak ada teks yang terbaca."}`).join("\n\n"),
      result: {
        amount: preferred ? preferred.amount : null,
        candidates, uncertain, checks: Array.from(checks.values()),
        ambiguous: candidates.length > 1,
      },
    }
  }

  function attachScanner(panel) {
    const form = panel.closest("form")
    const field = (name) => form.elements.namedItem(name)
    const status = panel.querySelector("[data-scan-status]")
    const progress = panel.querySelector("[data-scan-progress]")
    const preview = panel.querySelector("[data-scan-preview]")
    const details = panel.querySelector("[data-scan-details]")
    const output = panel.querySelector("[data-scan-text]")
    const choice = panel.querySelector("[data-scan-choice]")
    const candidates = panel.querySelector("[data-scan-candidates]")
    const cancel = panel.querySelector("[data-scan-cancel]")
    const checks = panel.querySelector("[data-scan-checks]")
    const checkList = panel.querySelector("[data-scan-check-list]")
    const fields = form.querySelector("[data-transaction-fields]")
    const manualButton = form.querySelector("[data-transaction-manual]")
    const photoButton = form.querySelector("[data-transaction-photo]")
    const reviewNote = form.querySelector("[data-transaction-summary]")
    // Alternatif nominal dapat dikoreksi langsung dari form hasil scan.
    reviewNote.after(choice)
    let previewUrl
    let currentRun = null
    let lastAmount = null
    let submitted = false
    let displayedCandidates = []
    const rupiah = (amount) => "Rp " + amount.toLocaleString("id-ID")
    const say = (message, error = false) => {
      status.textContent = message
      status.classList.toggle("scan-struk-error", error)
    }

    function setMode(mode, focus = false) {
      const photo = mode === "photo"
      panel.hidden = !photo
      fields.hidden = photo
      fields.disabled = photo
      manualButton.hidden = !photo
      photoButton.hidden = photo
      form.dataset.transactionMode = mode
      form.querySelector("[data-transaction-title]").textContent = mode === "review" ? "Periksa transaksi" : "Input Manual"
      reviewNote.hidden = mode !== "review"
      if (focus) field("keterangan").focus()
    }

    function applyTotal(amount, suggested = false, evidence = "") {
      field("tipe").value = "pengeluaran"
      field("tipe").dispatchEvent(new Event("change", { bubbles: true }))
      field("jumlah").value = amount.toLocaleString("id-ID")
      field("jumlah").dispatchEvent(new Event("input", { bubbles: true }))
      lastAmount = amount
      if (!field("keterangan").value.trim()) field("keterangan").value = "Belanja dari struk"
      say(`${suggested ? "Saran total" : "Total terbaca"} ${rupiah(amount)} sudah diisi. ${evidence ? evidence + ". " : ""}Bisa dikoreksi sebelum Simpan.`)
      reviewNote.textContent = status.textContent
      setMode("review")
    }

    candidates.addEventListener("change", () => {
      if (candidates.value) {
        const selected = displayedCandidates.find((candidate) => candidate.amount === Number(candidates.value))
        if (selected) applyTotal(selected.amount, selected.derived, selected.evidence)
      }
    })

    function renderReading(reading) {
      output.textContent = reading.text || "Tidak ada teks yang terbaca."
      details.hidden = false
      const result = reading.result
      displayedCandidates = result.candidates
      checkList.replaceChildren()
      for (const check of result.checks) {
        const item = document.createElement("li")
        item.textContent = check.evidence
        checkList.appendChild(item)
      }
      checks.hidden = result.checks.length === 0
      if (result.candidates.length > 1) {
        candidates.add(new Option("— Pilih total belanja —", ""))
        result.candidates.forEach((candidate) => candidates.add(new Option(
          rupiah(candidate.amount) + (candidate.derived ? " — saran hitungan" : " — hasil scan"), candidate.amount)))
        choice.hidden = false
      }
      if (result.amount !== null) {
        const selected = result.candidates[0]
        candidates.value = String(selected.amount)
        applyTotal(selected.amount, result.uncertain, selected.evidence || (result.ambiguous ? "Ada alternatif jumlah di bawah" : ""))
      } else {
        say("Total belum terbaca. Ambil foto baru atau pilih Input Manual.", true)
      }
    }

    manualButton.addEventListener("click", () => {
      if (!currentRun) setMode("manual", true)
    })
    photoButton.addEventListener("click", () => {
      if (!currentRun) setMode("photo")
    })
    form.addEventListener("transaction:open", () => {
      if (!currentRun) {
        setMode("photo")
        form.scrollTop = 0
      }
    })
    setMode("photo")

    async function scan(file) {
      if (!file) return
      if (activeScan) {
        say("Scan lain masih berjalan. Tunggu selesai atau batalkan scan tersebut.")
        return
      }
      if (!/^image\/(jpeg|png|webp)$/.test(file.type)) {
        say("Pilih gambar JPG, PNG, atau WebP. Ubah foto HEIC ke JPG terlebih dahulu.", true)
        return
      }
      if (!file.size || file.size > 10 * 1024 * 1024) {
        say("Ukuran gambar harus lebih dari 0 dan maksimal 10 MB.", true)
        return
      }
      setMode("photo")

      // Mode struk tetap pengeluaran bila OCR gagal dan jumlah diisi manual.
      field("tipe").value = "pengeluaran"
      field("tipe").dispatchEvent(new Event("change", { bubbles: true }))
      if (lastAmount !== null && Number(field("jumlah").value.replace(/\D/g, "")) === lastAmount) {
        field("jumlah").value = ""
      }
      lastAmount = null
      if (previewUrl) URL.revokeObjectURL(previewUrl)
      previewUrl = URL.createObjectURL(file)
      preview.src = previewUrl
      preview.hidden = false
      output.textContent = ""
      details.hidden = true
      details.open = false
      choice.hidden = true
      candidates.replaceChildren()
      displayedCandidates = []
      checks.hidden = true
      checkList.replaceChildren()
      progress.hidden = false
      progress.removeAttribute("value")
      cancel.hidden = false
      say("Menyiapkan scan. Pemuatan pertama bisa memerlukan waktu lebih lama…")

      const run = { cancelled: false, worker: null, readings: [] }
      currentRun = activeScan = run
      // Jaga isian selama OCR agar hasilnya tidak menimpa edit yang sedang diketik.
      const controls = Array.from(form.querySelectorAll("input, select, textarea, button"))
        .filter((control) => control !== cancel && !control.disabled)
      controls.forEach((control) => { control.disabled = true })
      panel.setAttribute("aria-busy", "true")
      let timeout
      const interrupted = new Promise((_, reject) => {
        run.stop = (message, kind = "failure") => {
          run.cancelled = true
          const error = new Error(message)
          error.kind = kind
          reject(error)
        }
        timeout = setTimeout(() => run.stop("Scan terlalu lama. Ambil foto baru atau isi jumlah manual.", "timeout"), 120000)
      })

      async function recognize() {
        const canvas = await prepareImage(file)
        run.canvas = canvas
        if (run.cancelled) { canvas.width = canvas.height = 1; return null }
        const ocr = await loadOcr()
        if (run.cancelled) return null
        let passLabel = "Membaca struk"
        const worker = await ocr.createWorker("ind+eng", 1, {
          workerPath: CDN + "worker.min.js",
          corePath: "https://cdn.jsdelivr.net/npm/tesseract.js-core@6.0.0",
          logger: (message) => {
            if (run.cancelled) return
            if (message.status === "recognizing text") {
              const percent = Math.round((message.progress || 0) * 100)
              progress.value = percent
              say(`${passLabel}… ${percent}%`)
            }
          },
          errorHandler: () => run.stop("Struk gagal dibaca. Periksa internet atau coba foto yang lebih jelas."),
        }, {
          // Struk banyak berisi kode barang dan angka, bukan kalimat biasa.
          load_system_dawg: "0",
          load_freq_dawg: "0",
        })
        if (run.cancelled) {
          await worker.terminate()
          return null
        }
        run.worker = worker
        await worker.setParameters({ preserve_interword_spaces: "1", user_defined_dpi: "300" })
        if (run.cancelled) return null
        const readings = run.readings
        async function read(image, options, label) {
          passLabel = label
          say(`${passLabel}…`)
          progress.value = 0
          const { data } = await worker.recognize(image, { rotateAuto: true, ...options }, { text: true, blocks: true })
          if (run.cancelled) return
          const result = window.ReceiptParser.findTotal(data.text || "")
          result.candidates.forEach((candidate) => { candidate.confidence = totalConfidence(data, candidate.amount) })
          readings.push({
            label,
            text: data.text || "",
            confidence: result.amount !== null ? result.candidates[0].confidence
              : (Number.isFinite(data.confidence) ? data.confidence : 0),
            result,
            suggestions: window.ReceiptParser.suggestTotals(data.text || ""),
          })
        }
        // Potongan satu baris memakai mode baris; foto penuh memakai mode kolom.
        const singleLine = canvas.width / canvas.height >= 4
        await read(canvas, { tessedit_pageseg_mode: singleLine ? "7" : "4", thresholding_method: "0" }, "Membaca gambar asli")
        if (run.cancelled) return null
        const first = readings[0]
        // Skor ini indikator untuk mencoba lagi, bukan jaminan nominal benar.
        if (first.confidence < 85 || first.result.amount === null) {
          // Coba tata letak lain pada gambar asli terlebih dahulu. Kontras/Sauvola
          // dapat menghilangkan tulisan pudar, sehingga bukan satu-satunya cadangan.
          await read(canvas, { tessedit_pageseg_mode: "6", thresholding_method: "0" }, "Membaca ulang tata letak")
          if (run.cancelled) return null
        }
        const readable = () => readings.some((reading) => reading.result.amount !== null && reading.confidence >= 70)
        if (!readable()) {
          // Pertahankan kesempatan membaca seluruh struk sebelum fokus ke bawah.
          run.enhanced = improveContrast(canvas)
          await read(run.enhanced, { tessedit_pageseg_mode: singleLine ? "7" : "6", thresholding_method: "2" }, "Memeriksa kontras tulisan")
          if (run.cancelled) return null
        }
        if (!readable() && !singleLine) {
          run.focused = focusSummary(run.enhanced || canvas)
          await read(run.focused, { tessedit_pageseg_mode: "6", thresholding_method: "2" }, "Membaca area ringkasan bawah")
          if (run.cancelled) return null
        }
        return combineReadings(readings)
      }

      try {
        const reading = await Promise.race([recognize(), interrupted])
        if (run.cancelled) return
        renderReading(reading)
      } catch (error) {
        if (error.kind !== "cancel" && run.readings.length) {
          renderReading(combineReadings(run.readings, true))
          say("Pembacaan lanjutan terhenti. Hasil awal sudah diisi; periksa jumlah sebelum menyimpan.", true)
          reviewNote.textContent = status.textContent
        } else {
          say(error.message || "Scan gagal. Coba lagi atau isi transaksi secara manual.", true)
        }
      } finally {
        clearTimeout(timeout)
        run.cancelled = true
        if (run.worker) run.worker.terminate().catch(() => {})
        for (const canvas of [run.canvas, run.enhanced, run.focused]) {
          if (canvas) canvas.width = canvas.height = 1
        }
        controls.forEach((control) => { control.disabled = false })
        progress.hidden = true
        cancel.hidden = true
        panel.removeAttribute("aria-busy")
        currentRun = activeScan = null
      }
    }

    cancel.addEventListener("click", () => currentRun?.stop("Scan dibatalkan. Kamu bisa memilih gambar lagi atau mengisi transaksi manual.", "cancel"))
    for (const source of ["camera", "upload"]) {
      const input = panel.querySelector(`[data-scan-${source}-input]`)
      panel.querySelector(`[data-scan-${source}]`).addEventListener("click", () => input.click())
      input.addEventListener("change", () => {
        const file = input.files[0]
        input.value = ""
        scan(file)
      })
    }

    form.addEventListener("submit", (event) => {
      if (currentRun || submitted || fields.hidden) {
        event.preventDefault()
        event.stopImmediatePropagation()
        return
      }
      submitted = true
      form.querySelectorAll('button[type="submit"]').forEach((button) => { button.disabled = true })
    }, true)
    window.addEventListener("pageshow", () => {
      if (!submitted) return
      submitted = false
      form.querySelectorAll('button[type="submit"]').forEach((button) => { button.disabled = false })
    })
    window.addEventListener("pagehide", () => {
      if (currentRun) currentRun.stop("Scan dibatalkan karena halaman ditutup.", "cancel")
    })
  }

  document.querySelectorAll("[data-scan-struk]").forEach(attachScanner)
})()
