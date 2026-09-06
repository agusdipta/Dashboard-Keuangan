const test = require("node:test")
const assert = require("node:assert/strict")
const fs = require("node:fs")
const path = require("node:path")
const { parseAmount, findTotal, suggestTotals } = require("../receipt-parser.js")

test("membaca nominal rupiah tanpa mengalikan pecahan sen", () => {
  for (const input of ["75.000", "75,000", "75000", "75.000,00", "75,000.00", "75000.00", "Rp 75.000", "IDR 75 000"]) {
    assert.equal(parseAmount(input), 75000, input)
  }
  assert.equal(parseAmount("1.275.000,00"), 1275000)
  assert.equal(parseAmount("500,00"), 500)
  assert.equal(parseAmount("9999999999999"), 9999999999999)
  for (const input of ["", "0", "-75000", "75.000,50", "12.34.500", "10000000000000", "NaN", "Rp ABC", "1 75.000", "75 00"]) {
    assert.equal(parseAmount(input), null, input)
  }
})

test("spasi OCR di sekitar pemisah nominal diterima tanpa menyatukan dua harga", () => {
  for (const value of ["42900. 00", "42900 .00", "42900 . 00", "42 . 900, 00", "42, 900. 00", "Rp 42. 900", "42 900. 00"]) {
    assert.equal(parseAmount(value), 42900, value)
    assert.equal(findTotal(`TOTAL ${value}`).amount, 42900, value)
  }
  for (const value of ["42900. 50", "38648. 65", "42 . 900 3 . 000", "2 42900. 00", "42900. 00 3000. 00", "42 90 0. 00"]) {
    assert.equal(parseAmount(value), null, value)
    assert.equal(findTotal(`TOTAL ${value}`).amount, null, value)
  }
})

test("output OCR MINISO yang dilaporkan pengguna menghasilkan Rp42.900", () => {
  const text = fs.readFileSync(path.join(__dirname, "fixtures/miniso-ocr.txt"), "utf8")
  const result = findTotal(text)
  assert.equal(result.amount, 42900)
  assert.equal(result.ambiguous, false)
  assert.equal(result.candidates.length, 1)
  assert.equal(findTotal(text + "\nCash Rp 50, 000\nChange 7, 100").amount, 42900)
})

test("mengambil total akhir, bukan subtotal, tunai, atau kembalian", () => {
  const receipt = `TOKO CONTOH
    SUSU 2 x 25.000 50.000
    ROTI 1 x 20.000 20.000
    SUB TOTAL 70.000
    PPN 5.000
    TOTAL 75.000
    TUNAI 100.000
    KEMBALI 25.000`
  assert.equal(findTotal(receipt).amount, 75000)
  assert.equal(findTotal("TOTAL 75.000\nPEMBULATAN -50\nTOTAL BAYAR 74.950").amount, 74950)
  assert.equal(findTotal("TOTAL HARGA 80.000\nDISKON 5.000\nGRAND TOTAL Rp 75.000,00").amount, 75000)
})

test("mendukung label total, pemisah, dan nominal di baris berikutnya", () => {
  for (const label of ["TOTAL", "TOTAL BAYAR", "TOTAL BELANJA", "TOTAL PEMBAYARAN", "JUMLAH BAYAR", "GRAND TOTAL", "AMOUNT DUE", "T0TAL", "TOTA1"]) {
    assert.equal(findTotal(`${label} : Rp 75.000,00`).amount, 75000, label)
    assert.equal(findTotal(`${label}\n75.000`).amount, 75000, label)
  }
  assert.equal(findTotal("TOTAL Rp75.000,-").amount, 75000)
  assert.equal(findTotal("TOTAL 75,000.-").amount, 75000)
})

test("tidak menebak nominal bila label total tidak ditemukan", () => {
  for (const receipt of ["", "SUSU 50.000\nROTI 25.000", "SUBTOTAL 75.000", "SUB TOTAL 75.000", "TOTAL ITEM 3", "TOTAL QTY 3", "TOTAL DISKON 10.000", "TOTAL PPN 7.500", "TOTAL HEMAT 10.000", "TOTAL\nTUNAI 100.000\nKEMBALI 25.000", "TOTAL 75.OOO", "TOTAL -75.000"]) {
    assert.equal(findTotal(receipt).amount, null, receipt)
  }
})

test("label Grand T 1 ta | dikenali bila nominal utuh, tanpa menebak digit rusak", () => {
  for (const label of ["Grand T 1 ta |", "GRAND TITAL", "T1TAL", "Grand T I T A !"]) {
    assert.equal(findTotal(`SUBTOTAL 215.000\n${label} : 248.325\nCASH 300.000`).amount, 248325, label)
  }
  for (const text of ["Grand T 1 ta | . i 08.3", "Grand T1tal 248.32S", "SUB T1TAL 215.000", "SUB\nT1TAL 215.000", "T1TAL ITEM 5", "T1TAL PPN 22.575"]) {
    assert.equal(findTotal(text).amount, null, text)
  }
})

test("total berbeda meminta pilihan; pengulangan total sama tetap satu kandidat", () => {
  const result = findTotal("TOTAL 75.000\nTOTAL 80.000")
  assert.equal(result.amount, null)
  assert.equal(result.ambiguous, true)
  assert.deepEqual(result.candidates.map((candidate) => candidate.amount), [75000, 80000])
  assert.equal(findTotal("TOTAL 75.000\nTOTAL 75.000,00").amount, 75000)
})

test("mengenali variasi total akhir Indonesia, Inggris, dan singkatan struk", () => {
  const labels = [
    "GRAN TOTAL", "GRANDTOTAL", "GRAND TOTAL BAYAR", "GRAND TOTAL PEMBAYARAN",
    "TOTAL AKHIR", "TOTAL TAGIHAN", "TOTAL HARUS DIBAYAR", "TOTAL YANG HARUS DIBAYAR",
    "JUMLAH PEMBAYARAN", "JUMLAH TAGIHAN", "JUMLAH HARUS DIBAYAR", "JUMLAH YANG HARUS DIBAYAR",
    "JML BAYAR", "JML. PEMBAYARAN", "JML HARUS DIBAYAR", "TTL BAYAR",
    "HARUS DIBAYAR", "YANG HARUS DIBAYAR", "FINAL TOTAL", "TOTAL DUE",
    "TOTAL AMOUNT DUE", "AMOUNT PAYABLE", "TOTAL PAYABLE", "TOTAL AMOUNT PAYABLE", "PAYABLE AMOUNT",
    "TOTAL PEMBELIAN", "TOTAL PENJUALAN", "JUMLAH BELANJA", "JUMLAH PEMBELIAN", "JML BELANJA", "TTL BELANJA",
    "TOTAL BERSIH", "JUMLAH BERSIH", "NET TOTAL", "NETT TOTAL", "TOTAL NET", "TOTAL NETT", "TOTAL NETO", "TOTAL NETTO",
    "TOTAL AMOUNT", "BILL TOTAL", "TOTAL BILL", "INVOICE TOTAL", "TOTAL INVOICE", "JUMLAH HARGA",
  ]
  for (const label of labels) {
    assert.equal(findTotal(`${label}: Rp 75.000,00`).amount, 75000, label)
    assert.equal(findTotal(`${label}\n75.000`).amount, 75000, label)
  }
})

test("menoleransi salah baca huruf, spasi, dan tanda pemisah hanya pada label", () => {
  for (const label of ["GR4ND T0TA1", "GRAND T0TAI", "GRANO TOTAL", "T0T4L", "TOTA|", "TOTA!", "T O T A L",
    "G R A N D T O T A L", "grand-total", "*** Grand Total ***", "| TOTAL |", "TOTAL |", "TOTAL (Rp)",
    "GRAND TOTAL (IDR)", "T0TAL 8AYAR", "JUM1AH HARU5 D1BAYAR", "TOTAL PEM8AYARAN"]) {
    assert.equal(findTotal(`${label} : 75.000`).amount, 75000, label)
  }
  assert.equal(findTotal("GRANDTOTAL:75000").amount, 75000)
  assert.equal(findTotal("TOTA1:Rp75000").amount, 75000)
  assert.equal(findTotal("GR4ND T0TA1 75.OOO").amount, null)
  assert.equal(findTotal("TOTAL 7S.000").amount, null)
})

test("mendukung label total terpotong baris tanpa mengambil subtotal terpotong", () => {
  for (const receipt of ["GRAND\nTOTAL\nRp75.000", "TOTAL\nBAYAR 75.000", "JUMLAH\nHARUS DIBAYAR\n75.000", "TOTAL YANG\nHARUS DIBAYAR 75.000"]) {
    assert.equal(findTotal(receipt).amount, 75000, receipt)
  }
  for (const receipt of ["SUB\nTOTAL 70.000", "SUB-\nTOTAL\n70.000", "SU8\nT0TAL 70.000",
    "TOTAL\nTUNAI\n100.000", "TOTAL DISKON\n10.000", "GRAND TOTAL\nKEMBALI 25.000"]) {
    assert.equal(findTotal(receipt).amount, null, receipt)
  }
})

test("mengutamakan tagihan akhir setelah pajak, diskon, dan pembulatan", () => {
  assert.equal(findTotal("TOTAL BELANJA 80.000\nDISKON 5.000\nGRAND TOTAL 75.000").amount, 75000)
  assert.equal(findTotal("NET TOTAL 70.000\nPPN 5.000\nTOTAL AMOUNT DUE 75.000").amount, 75000)
  assert.equal(findTotal("NET TOTAL 70.000\nPPN 5.000\nTOTAL 75.000").amount, 75000)
  assert.equal(findTotal("TOTAL 75.000\nPEMBULATAN -50\nJML. BAYAR 74.950").amount, 74950)
  const conflicting = findTotal("GRAND TOTAL 75.000\nTOTAL BAYAR 74.950")
  assert.equal(conflicting.amount, null)
  assert.equal(conflicting.ambiguous, true)
  assert.equal(findTotal("TOTAL BELANJA 80.000\nTOTAL 75.000").ambiguous, true)
})

test("label mirip total untuk jumlah barang, diskon, pajak, dan pembayaran tetap ditolak", () => {
  for (const label of ["TOTAL ITEMS", "TOTAL BARANG", "TOTAL PCS", "TOTAL QTY", "TOTAL DISCOUNT",
    "GRAND TOTAL DISKON", "TOTAL SAVINGS", "TOTAL PAJAK", "TOTAL TAX", "TOTAL PPN", "TOTAL SERVICE",
    "TOTAL TUNAI", "TOTAL CASH", "TOTAL DIBAYAR", "TOTAL PAID", "AMOUNT PAID", "CASH", "TUNAI", "KEMBALIAN",
    "JUMLAH", "JML", "AMOUNT", "NETTO", "SUB TOTAL", "SUB-TOTAL", "SUBT0TA1", "NOTOTAL", "TOTALITAS"]) {
    assert.equal(findTotal(`${label} 100.000`).amount, null, label)
  }
  for (const amount of ["-75.000", "Rp -75.000", "- Rp75.000", "+75.000", "75.000 10.000", "3 75.000"]) {
    assert.equal(findTotal(`GRAND TOTAL ${amount}`).amount, null, amount)
  }
})

test("OCR Ichiban yang rusak memberi saran Rp248.325 dari tunai dan kembalian saja", () => {
  const text = fs.readFileSync(path.join(__dirname, "fixtures/ichiban-ocr.txt"), "utf8")
  assert.equal(findTotal(text).amount, null)
  const suggestions = suggestTotals(text)
  assert.equal(suggestions.length, 1)
  assert.equal(suggestions[0].amount, 248325)
  assert.equal(suggestions[0].method, "cash-change")
  assert.match(suggestions[0].evidence, /Rp300\.000.*Rp51\.675.*Rp248\.325/)
  assert.match(suggestions[0].evidence, /Periksa/)
  // Saran aritmetika tidak mengubah hasil pembacaan total atau menebak digit PBJT.
  assert.equal(findTotal(text).amount, null)
  assert.equal(suggestions.some((suggestion) => suggestion.amount === 228325), false)
})

test("saran tunai mendukung label Indonesia, nol, dan nominal pada baris berikutnya", () => {
  for (const cash of ["CASH", "CASH Rp", "TUNAI", "BAYAR CASH", "BAYAR TUNAI"]) {
    for (const change of ["CHANGE", "KEMBALI", "KEMBALIAN"]) {
      const result = suggestTotals(`${cash}:\n300.000\n----------\n${change}\n51.675`)
      assert.equal(result.length, 1, `${cash}, ${change}`)
      assert.equal(result[0].amount, 248325)
    }
  }
  for (const zero of ["0", "0.00", "0,00"]) {
    assert.equal(suggestTotals(`CASH 42.900\nCHANGE ${zero}`)[0].amount, 42900)
  }
  assert.equal(parseAmount("0"), null)
})

test("HANG dan HANGE hanya dianggap kembalian tepat setelah baris tunai", () => {
  for (const label of ["hang", "HANGE"]) {
    assert.equal(suggestTotals(`CASH 300.000\n-----\n${label} 51.675`)[0].amount, 248325)
    assert.deepEqual(suggestTotals(`CASH 300.000\nBarang lain\n${label} 51.675`), [])
    assert.deepEqual(suggestTotals(`${label} 51.675\nCASH 300.000`), [])
    assert.deepEqual(suggestTotals(`${label} 51.675`), [])
  }
  assert.deepEqual(suggestTotals("CASH 300.000\nHANGING 51.675"), [])
})

test("saran tunai menolak angka rusak, transaksi ganda, refund, dan pembayaran gabungan", () => {
  const receipts = [
    "CASH 300.000", "CHANGE 51.675", "CASH 3OO.OOO\nCHANGE 51.675", "CASH 300.000\nCHANGE 5I.675",
    "CASH -300.000\nCHANGE 51.675", "CASH 300.000\nCHANGE -51.675", "CASH 300.000\nCHANGE 300.000",
    "CASH 300.000\nCHANGE 350.000", "CASH 300.000\nCHANGE 51.675,50",
    "CASH 300.000\nCASH 300.000\nCHANGE 51.675", "CASH 300.000\nCASH ERROR\nCHANGE 51.675",
    "CASH 300.000\nCHANGE 51.675\nKEMBALI 51.675", "CASH 300.000\nCHANGE 51.675\nCHANGE ?",
    "CASH 300.000\nPAYMENT 20.000\nCHANGE 51.675", "CASH 300.000\nQRIS 20.000\nCHANGE 51.675",
    "CASH 300.000\nCHANGE 51.675\nDEBIT 20.000", "CASH 300.000\nCHANGE 51.675\nVOUCHER 20.000",
    "REFUND\nCASH 300.000\nCHANGE 51.675", "RETUR\nCASH 300.000\nCHANGE 51.675",
    "TOTAL -248.325\nCASH 300.000\nCHANGE 51.675", "SUBTOTAL -248.325\nCASH 300.000\nCHANGE 51.675",
    "CASH 300.000\nbaris satu\nbaris dua\nbaris tiga\nCHANGE 51.675",
    "CASH 300.000\nCHANGE 51.675\nCASH 100.000\nCHANGE 10.000",
  ]
  for (const receipt of receipts) assert.deepEqual(suggestTotals(receipt), [], receipt)
})

test("subtotal ditambah biaya dan pajak memberi hitungan terpisah yang dapat diperiksa", () => {
  const receipt = "SUBTOTAL 215.000\nService Charge 10.750\nPBJT 22.575\nGrand Total 248.325\nCASH 300.000\nCHANGE 51.675"
  const suggestions = suggestTotals(receipt)
  assert.deepEqual(suggestions.map(({ method, amount }) => ({ method, amount })), [
    { method: "cash-change", amount: 248325 }, { method: "components", amount: 248325 },
  ])
  assert.match(suggestions[1].evidence, /Rp215\.000.*Rp10\.750.*Rp22\.575.*Rp248\.325/)
  assert.equal(findTotal(receipt).amount, 248325)
  assert.equal(suggestTotals("Subtota! 215 000\nService Charge (5%) 10.750\nPBJT (10%) 22.575\nGrand T 1 ta | . i 08.3")[0].amount, 248325)
  assert.equal(suggestTotals("SUB TOTAL\n215.000\nBIAYA LAYANAN\n10.750\nPAJAK\n22.575\nTOTAL ?")[0].amount, 248325)
})

test("diskon dan pembulatan memakai angka tertulis dengan tanda yang tepat", () => {
  for (const discount of ["5.000", "-5.000"]) {
    assert.equal(suggestTotals(`SUBTOTAL 100.000\nDISKON ${discount}\nPPN 10.000\nPEMBULATAN -50\nTOTAL ?`)[0].amount, 104950)
  }
  assert.equal(suggestTotals("SUBTOTAL 100.000\nDISCOUNT 5% 5.000\nROUNDING +50\nTOTAL ?")[0].amount, 95050)
  assert.equal(suggestTotals("SUBTOTAL 100.000\nDISCOUNT PRICE 0\nVAT 0.00\nTOTAL ?")[0].amount, 100000)
})

test("hitungan komponen menolak biaya tidak terbaca, pajak inklusif, dan rincian yang ambigu", () => {
  const receipts = [
    "SUBTOTAL 215.000", "SUBTOTAL 215.000\nService Charge 10.750\nPRT 2.575\nGRAND TOTAL ?",
    "SUBTOTAL 215.000\nService Charge ?\nPBJT 22.575\nGRAND TOTAL ?",
    "SUBTOTAL 215.000\nPBJT 10%\nGRAND TOTAL ?",
    "SUBTOTAL 215.000\nPBJT 110% 22.575\nGRAND TOTAL ?",
    "SUBTOTAL 215.000\nPPN 22.575\nPBJT 22.575\nGRAND TOTAL ?",
    "SUBTOTAL 215.000\nSERVICE 10.750\nSERVICE CHARGE 10.750\nGRAND TOTAL ?",
    "SUBTOTAL 215.000\nPPN -22.575\nGRAND TOTAL ?",
    "SUBTOTAL 215.000\nPPN 22.575,50\nGRAND TOTAL ?",
    "SUBTOTAL 215.000\nDISCOUNT 300.000\nGRAND TOTAL ?",
    "SUBTOTAL 215.000\nPBJT 22.575\nTOTAL ?\nSERVICE 10.750\nGRAND TOTAL ?",
    "PPN 22.575\nSUBTOTAL 215.000\nPBJT 22.575\nGRAND TOTAL ?",
    "SUBTOTAL 215.000\nPBJT 22.575\nSUBTOTAL 100.000\nPPN 10.000\nGRAND TOTAL ?",
    "SUBTOTAL 215.000\nPBJT 22.575\nTOTAL ?\nCASH 300.000\nCASH 100.000",
    "SUBTOTAL 215.000\nPBJT 22.575\nTOTAL ?\nCASH -300.000",
    "REFUND\nSUBTOTAL 215.000\nPBJT 22.575\nTOTAL ?",
  ]
  for (const included of ["TAX INCLUDED", "SUDAH TERMASUK PAJAK", "TAX INCLUSIVE", "PPN INC", "INCL. PPN"]) {
    receipts.push(`${included}\nSUBTOTAL 215.000\nPBJT 22.575\nTOTAL ?`)
  }
  for (const receipt of receipts) assert.deepEqual(suggestTotals(receipt), [], receipt)
})
