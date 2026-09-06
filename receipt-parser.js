/* Pembacaan total struk, terpisah dari OCR agar aturan nominal bisa diuji. */
;(function (root, factory) {
  if (typeof module === "object" && module.exports) module.exports = factory()
  else root.ReceiptParser = factory()
})(typeof globalThis !== "undefined" ? globalThis : this, function () {
  // Tambahkan variasi label di sini. Prioritas lebih tinggi = total lebih akhir.
  // Label harus cocok utuh; jangan tambahkan TUNAI, DIBAYAR, JUMLAH, atau SUBTOTAL
  // sendiri karena bisa berarti uang yang diserahkan, jumlah barang, atau subtotal.
  const TOTAL_LABEL_GROUPS = [
    {
      priority: 3,
      labels: [
        "GRAND TOTAL", "GRAN TOTAL", "GRANO TOTAL", "GRAND TOTAL BAYAR", "GRAND TOTAL PEMBAYARAN",
        "TOTAL AKHIR", "TOTAL BAYAR", "TOTAL PEMBAYARAN", "TOTAL TAGIHAN",
        "TOTAL HARUS DIBAYAR", "TOTAL YANG HARUS DIBAYAR",
        "JUMLAH BAYAR", "JUMLAH PEMBAYARAN", "JUMLAH TAGIHAN",
        "JUMLAH HARUS DIBAYAR", "JUMLAH YANG HARUS DIBAYAR",
        "JML BAYAR", "JML PEMBAYARAN", "JML HARUS DIBAYAR", "TTL BAYAR",
        "HARUS DIBAYAR", "YANG HARUS DIBAYAR",
        "FINAL TOTAL", "TOTAL DUE", "AMOUNT DUE", "TOTAL AMOUNT DUE",
        "AMOUNT PAYABLE", "TOTAL PAYABLE", "TOTAL AMOUNT PAYABLE", "PAYABLE AMOUNT",
      ],
    },
    {
      priority: 2,
      labels: [
        "TOTAL", "TOTAL BELANJA", "TOTAL PEMBELIAN", "TOTAL PENJUALAN",
        "JUMLAH BELANJA", "JUMLAH PEMBELIAN", "JML BELANJA", "TTL BELANJA",
        "TOTAL AMOUNT", "BILL TOTAL", "TOTAL BILL", "INVOICE TOTAL", "TOTAL INVOICE",
      ],
    },
    {
      // Harga/neto dapat belum mencakup pajak atau pembulatan.
      priority: 1,
      labels: [
        "TOTAL HARGA", "JUMLAH HARGA", "TOTAL BERSIH", "JUMLAH BERSIH",
        "NET TOTAL", "NETT TOTAL", "TOTAL NET", "TOTAL NETT", "TOTAL NETO", "TOTAL NETTO",
      ],
    },
  ]

  function normalizeLabel(value) {
    // Koreksi hanya label, tidak pernah mengubah huruf menjadi angka pada nominal.
    return value.toUpperCase()
      .replace(/\(\s*(?:RP\.?|IDR)\s*\)/g, "")
      .replace(/0/g, "O").replace(/4/g, "A").replace(/5/g, "S").replace(/8/g, "B")
      .replace(/[1IL|!]/g, "I")
      .replace(/[\s.:;=_*()[\]-]/g, "")
      // OCR kadang membaca O pada TOTAL sebagai 1/I: "Grand T 1 ta |".
      // Tetap cocokkan label utuh agar SUBTOTAL / TOTAL ITEM tidak ikut diterima.
      .replace(/TITAI/g, "TOTAI")
  }

  const labelPriorities = new Map()
  TOTAL_LABEL_GROUPS.forEach(({ priority, labels }) => {
    labels.forEach((label) => labelPriorities.set(normalizeLabel(label), priority))
  })

  function labelPriority(label) {
    // Coba bentuk asli dulu: tanda | bisa merupakan salah baca huruf L pada TOTA|.
    return labelPriorities.get(normalizeLabel(label))
      || labelPriorities.get(normalizeLabel(label.trim().replace(/^[|!]+|[|!]+$/g, "")))
      || 0
  }

  function parseAmount(value) {
    let raw = String(value).trim().replace(/^(?:RP\.?|IDR)\s*/i, "")
    // OCR dapat menyisipkan spasi di sekitar tanda nominal: 42900. 00 / 42 . 900, 00.
    // Spasi di antara dua angka terpisah tetap diperiksa di bawah, tidak digabung bebas.
    raw = raw.replace(/\s*([.,])\s*/g, "$1")
    // Spasi hanya boleh berupa pengelompokan ribuan, bukan dua angka terpisah.
    if (/\s/.test(raw) && !/^\d{1,3}(?:\s+\d{3})+(?:[.,]00)?$/.test(raw)) return null
    raw = raw.replace(/\s+/g, "")
    // Pecahan sen hanya diterima bila nol: form transaksi memakai rupiah bulat.
    const formats = [
      /^\d{1,3}(?:\.\d{3})+(?:,00)?$/,
      /^\d{1,3}(?:,\d{3})+(?:\.00)?$/,
      /^\d+(?:[.,]00)?$/,
    ]
    if (!formats.some((pattern) => pattern.test(raw))) return null
    const integer = raw.replace(/[.,]00$/, "").replace(/[.,]/g, "")
    const amount = Number(integer)
    return Number.isSafeInteger(amount) && amount > 0 && amount <= 9999999999999 ? amount : null
  }

  function findTotal(text) {
    const lines = String(text).split(/\r?\n/).map((line) => line.trim()).filter(Boolean)
    const found = []
    // Pemisah wajib agar angka 1 di TOTA1 tidak dianggap bagian dari nominal.
    const amountAtEnd = /(^|[\s:;=])([+-]?\s*(?:RP\.?|IDR)?\s*[+-]?\s*\d[\d.,\s]*(?:,-|\.-)?)$/i

    lines.forEach((_, index) => {
      // SUB di baris sebelumnya adalah bagian dari SUB TOTAL, bukan total akhir.
      if (index > 0 && normalizeLabel(lines[index - 1]) === "SUB") return
      // Maksimal tiga baris: GRAND / TOTAL / Rp75.000 atau TOTAL / BAYAR / 75.000.
      for (let span = 1; span <= 3 && index + span <= lines.length; span++) {
        const combined = lines.slice(index, index + span).join(" ")
        const match = combined.match(amountAtEnd)
        if (!match) continue
        const priority = labelPriority(combined.slice(0, match.index))
        if (!priority) continue
        const amount = parseAmount(match[2].trim().replace(/(?:,-|\.-)$/, ""))
        if (amount === null) continue
        found.push({ amount, label: lines.slice(index, index + span).join("\n"), priority })
        break
      }
    })

    const highest = Math.max(0, ...found.map((candidate) => candidate.priority))
    const candidates = []
    found.filter((candidate) => candidate.priority === highest).forEach(({ amount, label }) => {
      if (!candidates.some((candidate) => candidate.amount === amount)) candidates.push({ amount, label })
    })
    return {
      amount: candidates.length === 1 ? candidates[0].amount : null,
      ambiguous: candidates.length > 1,
      candidates,
    }
  }

  const SUMMARY_LABELS = {
    cash: ["CASH", "TUNAI", "BAYAR CASH", "BAYAR TUNAI"],
    change: ["CHANGE", "KEMBALI", "KEMBALIAN"],
    // Hanya diterima tepat setelah pembayaran tunai; bukan pencocokan fuzzy bebas.
    damagedChange: ["HANGE", "HANG"],
    subtotal: ["SUBTOTAL", "SUB TOTAL"],
    service: ["SERVICE", "SERVICE CHARGE", "BIAYA LAYANAN", "BIAYA SERVIS"],
    tax: ["TAX", "PAJAK", "PPN", "PBJT", "VAT"],
    discount: ["DISCOUNT", "DISCOUNT PRICE", "DISKON", "POTONGAN"],
    rounding: ["ROUNDING", "PEMBULATAN"],
  }
  const summaryKinds = new Map()
  Object.entries(SUMMARY_LABELS).forEach(([kind, labels]) => {
    labels.forEach((label) => summaryKinds.set(normalizeLabel(label), kind))
  })

  function summaryKind(value) {
    const label = value.replace(/\s*(?:RP\.?|IDR)\s*$/i, "")
    const normalized = normalizeLabel(label)
    if (summaryKinds.has(normalized)) return summaryKinds.get(normalized)
    // Persentase hanya keterangan label. Nominalnya tetap harus tertulis lengkap.
    if (/%/.test(label)) {
      const percentages = label.match(/\d+(?:[.,]\d+)?\s*%/g) || []
      if (percentages.length !== 1 || Number(percentages[0].replace("%", "").replace(",", ".")) > 100) return null
      const kind = summaryKinds.get(normalizeLabel(label.replace(/\d+(?:[.,]\d+)?\s*%/, "")))
      return ["tax", "service", "discount"].includes(kind) ? kind : null
    }
    return labelPriority(label) ? "total" : null
  }

  function summaryPrefix(line) {
    let found = null
    // Cari label utuh terpanjang: DISCOUNT PRICE harus mengalahkan DISCOUNT.
    for (let end = 1; end <= line.length; end++) {
      if (end < line.length && !/[\s:;=]/.test(line[end])) continue
      const label = line.slice(0, end).trim().replace(/[:;=]+$/, "").trim()
      const kind = summaryKind(label)
      if (kind) found = { kind, label, payload: line.slice(end).replace(/^[\s:;=]+/, "") }
    }
    return found
  }

  function summaryAmount(value) {
    let raw = String(value).trim().replace(/^(?:RP\.?|IDR)\s*/i, "")
    const sign = raw.startsWith("-") ? -1 : 1
    raw = raw.replace(/^[+-]\s*/, "").replace(/^(?:RP\.?|IDR)\s*/i, "")
      .replace(/(?:,-|\.-)$/, "").trim()
    const amount = parseAmount(raw)
    // Nol sah untuk kembalian, pajak, atau diskon; parseAmount tetap menolak nol.
    if (amount === null && !/^0+(?:[.,]\s*00)?$/.test(raw)) return null
    return sign * (amount || 0)
  }

  function summaryRows(lines) {
    const rows = []
    for (let index = 0; index < lines.length; index++) {
      const prefix = summaryPrefix(lines[index])
      if (!prefix) continue
      let end = index
      let payload = prefix.payload
      if (!payload && index + 1 < lines.length && summaryAmount(lines[index + 1]) !== null) {
        payload = lines[++end]
      }
      rows.push({ ...prefix, index, end, amount: payload ? summaryAmount(payload) : null })
      index = end
    }
    return rows
  }

  function rupiah(amount) {
    return "Rp" + String(amount).replace(/\B(?=(\d{3})+(?!\d))/g, ".")
  }

  function suggestTotals(text) {
    // Semua hasil fungsi ini adalah saran hitungan untuk dipilih pengguna.
    // Jangan meneruskannya ke findTotal atau menyimpan transaksi secara otomatis.
    const original = String(text)
    if (/\b(?:REFUND(?:ED)?|RETUR|RETURN|VOID|BATAL|PEMBATALAN)\b/i.test(original)) return []
    const lines = original.split(/\r?\n/).map((line) => line.trim()).filter(Boolean)
    const rows = summaryRows(lines)
    const suggestions = []
    const cashRows = rows.filter((row) => row.kind === "cash")
    const changeRows = rows.filter((row) => ["change", "damagedChange"].includes(row.kind))
    // Nominal pembayaran/total negatif menandakan arah transaksi yang berbeda.
    if (rows.some((row) => ["cash", "change", "damagedChange", "subtotal", "total"].includes(row.kind) && row.amount < 0)) return []
    const separator = (line) => /^[-=_*.|\s]+$/.test(line)
    const otherPayment = /(?:^|\n)\s*(?:DEBIT|CREDIT|KREDIT|KARTU|CARD|QRIS|TRANSFER|VOUCHER|GIFT\s*CARD|E[- ]?WALLET|SPLIT\s*PAYMENT|PEMBAYARAN\s+GABUNGAN)\b/i.test(original)

    if (cashRows.length === 1 && changeRows.length === 1 && !otherPayment) {
      const cash = cashRows[0]
      const change = changeRows[0]
      const between = lines.slice(cash.end + 1, change.index).filter((line) => !separator(line))
      // Jarak dibatasi; HANG/HANGE wajib berada tepat setelah baris tunai.
      const close = change.index > cash.end && between.length <= (change.kind === "damagedChange" ? 0 : 2)
      const noOtherAmounts = between.every((line) => !/\d/.test(line))
      const amount = cash.amount - change.amount
      if (close && noOtherAmounts && cash.amount > 0 && change.amount !== null && change.amount >= 0
        && Number.isSafeInteger(amount) && amount > 0 && amount <= 9999999999999) {
        suggestions.push({ amount, method: "cash-change", label: "Tunai dikurangi kembalian",
          evidence: `${cash.label}: ${rupiah(cash.amount)} - ${change.label}: ${rupiah(change.amount)} = ${rupiah(amount)}. Periksa kedua angka pada struk.` })
      }
    }

    const subtotals = rows.filter((row) => row.kind === "subtotal")
    const includedTax = /\b(?:INCLU(?:DED|SIVE)|INCL\.?|TERMASUK|SUDAH\s+TERMASUK)\b|\b(?:TAX|PPN|PAJAK|PBJT|VAT)\s*[-:]?\s*INC\b/i.test(original)
    if (subtotals.length === 1 && subtotals[0].amount > 0 && !includedTax && cashRows.length <= 1 && changeRows.length <= 1) {
      const subtotal = subtotals[0]
      const componentKinds = ["service", "tax", "discount", "rounding"]
      const components = []
      let valid = true
      for (let index = subtotal.end + 1; index < lines.length; index++) {
        if (separator(lines[index])) continue
        const row = rows.find((candidate) => candidate.index === index)
        // Akhir ringkasan, termasuk label GRAND yang nominalnya rusak oleh OCR.
        if ((row && ["total", "cash", "change", "damagedChange"].includes(row.kind)) || /^GR[A4]N[D0O]?\b/i.test(lines[index])) break
        if (!row || !componentKinds.includes(row.kind) || row.amount === null
          || (["tax", "service"].includes(row.kind) && row.amount < 0)
          || components.some((component) => component.kind === row.kind)) {
          valid = false
          break
        }
        components.push(row)
        index = row.end
      }
      // Pajak/biaya yang muncul sebelum subtotal dapat sudah termasuk di dalamnya.
      if (rows.some((row) => ["tax", "service"].includes(row.kind) && row.index < subtotal.index)) valid = false
      // Jangan menawarkan hitungan parsial bila masih ada penyesuaian setelah TOTAL.
      if (rows.some((row) => componentKinds.includes(row.kind) && row.index > subtotal.end && !components.includes(row))) valid = false
      if (valid && components.length) {
        const amount = components.reduce((sum, row) => sum + (row.kind === "discount" ? -Math.abs(row.amount) : row.amount), subtotal.amount)
        if (Number.isSafeInteger(amount) && amount > 0 && amount <= 9999999999999) {
          const equation = components.map((row) => {
            const negative = row.kind === "discount" || row.amount < 0
            return `${negative ? "-" : "+"} ${row.label}: ${rupiah(Math.abs(row.amount))}`
          }).join(" ")
          suggestions.push({ amount, method: "components", label: "Hitung dari rincian tagihan",
            evidence: `${subtotal.label}: ${rupiah(subtotal.amount)} ${equation} = ${rupiah(amount)}. Periksa apakah seluruh biaya sudah tercakup.` })
        }
      }
    }
    return suggestions
  }

  return { parseAmount, findTotal, suggestTotals }
})
