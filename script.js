document.addEventListener("DOMContentLoaded", () => {
  // Auto pemisah ribuan pada input .js-rupiah (ketik 600000 -> tampil 600.000).
  // Saat form dikirim, nilainya dikembalikan ke angka polos (600000).
  const kelompokRibuan = (digits) => digits.replace(/\B(?=(\d{3})+(?!\d))/g, ".")

  function pasangFormatRupiah(inp) {
    const format = () => {
      const caret = inp.selectionStart ?? inp.value.length
      const digitSebelumCaret = inp.value.slice(0, caret).replace(/\D/g, "").length
      const digits = inp.value.replace(/\D/g, "").replace(/^0+(?=\d)/, "")
      inp.value = digits ? kelompokRibuan(digits) : ""

      // Kembalikan posisi kursor mengikuti jumlah digit sebelumnya
      let terlihat = 0
      let pos = inp.value.length
      if (digitSebelumCaret === 0) {
        pos = 0
      } else {
        for (let i = 0; i < inp.value.length; i++) {
          if (/\d/.test(inp.value[i])) {
            terlihat++
            if (terlihat === digitSebelumCaret) {
              pos = i + 1
              break
            }
          }
        }
      }
      try {
        inp.setSelectionRange(pos, pos)
      } catch (e) {
        /* input belum fokus */
      }
    }

    inp.addEventListener("input", format)
    if (inp.value) format()

    const form = inp.closest("form")
    if (form && !form.dataset.rupiahBound) {
      form.dataset.rupiahBound = "1"
      form.addEventListener("submit", () => {
        form.querySelectorAll("input.js-rupiah").forEach((el) => {
          el.value = el.value.replace(/\D/g, "")
        })
      })
    }
  }

  document.querySelectorAll("input.js-rupiah").forEach(pasangFormatRupiah)

  // Form transaksi: tombol jenis + chip kategori/tanggal mengisi <select>/<input> asli.
  // Listener saring kategori (inline di halaman) terpasang lebih dulu, jadi opsi sudah diperbarui saat sync().
  document.querySelectorAll("[data-transaction-fields]").forEach((fs) => {
    const { tipe, kategori_id: kat, tanggal: tgl } = fs.form.elements
    const toggle = fs.querySelector("[data-tipe-toggle]")
    const katChips = fs.querySelector("[data-kategori-chips]")
    const tglChips = fs.querySelector("[data-tanggal-chips]")
    const pilih = (el, value) => {
      el.value = value
      el.dispatchEvent(new Event("change", { bubbles: true }))
    }

    const sync = () => {
      fs.dataset.tipe = tipe.value
      toggle.querySelectorAll("button").forEach((b) => b.setAttribute("aria-pressed", String(b.dataset.value === tipe.value)))
      let ada = false
      katChips.querySelectorAll(".chip").forEach((c) => {
        c.hidden = c.dataset.tipe !== tipe.value
        ada ||= !c.hidden
        c.setAttribute("aria-pressed", String(c.dataset.value === kat.value))
      })
      katChips.querySelector("[data-kategori-empty]").hidden = ada
      tglChips.querySelectorAll(".chip").forEach((c) => c.setAttribute("aria-pressed", String(c.dataset.value === tgl.value)))
    }

    toggle.addEventListener("click", (e) => {
      const b = e.target.closest("button")
      if (b && b.dataset.value !== tipe.value) pilih(tipe, b.dataset.value)
    })
    katChips.addEventListener("click", (e) => {
      const c = e.target.closest(".chip")
      if (c) pilih(kat, kat.value === c.dataset.value ? "" : c.dataset.value)
    })
    tglChips.addEventListener("click", (e) => {
      const c = e.target.closest(".chip")
      if (c) pilih(tgl, c.dataset.value)
    })
    tipe.addEventListener("change", sync)
    kat.addEventListener("change", sync)
    tgl.addEventListener("input", sync)
    tgl.addEventListener("change", sync)

    fs.classList.add("is-enhanced")
    toggle.hidden = katChips.hidden = tglChips.hidden = false
    sync()
  })

  // Notifikasi flash: hilang otomatis setelah 4 detik
  document.querySelectorAll(".app-flash").forEach((el) => {
    setTimeout(() => {
      el.style.opacity = "0"
      el.style.transform = "translateY(-6px)"
      setTimeout(() => el.remove(), 400)
    }, 4000)
  })

  // Mobile sidebar toggle
  const sidebarToggle = document.createElement("button")
  sidebarToggle.classList.add("sidebar-toggle")
  sidebarToggle.innerHTML = '<i class="fas fa-bars"></i>'
  document.querySelector(".content-header").prepend(sidebarToggle)

  sidebarToggle.addEventListener("click", () => {
    const sidebar = document.querySelector(".sidebar")
    const content = document.querySelector(".content")

    if (sidebar.style.width === "var(--sidebar-width)" || sidebar.style.width === "250px") {
      sidebar.style.width = "0"
      content.style.marginLeft = "0"
    } else {
      sidebar.style.width = "var(--sidebar-width)"
      content.style.marginLeft = "var(--sidebar-width)"
    }
  })

  // Tema terang / gelap
  const rootEl = document.documentElement
  const temaTersimpan = () => {
    try {
      return localStorage.getItem("appTheme")
    } catch (e) {
      return null
    }
  }
  const setTema = (nama, simpan) => {
    rootEl.setAttribute("data-theme", nama)
    if (simpan) {
      try {
        localStorage.setItem("appTheme", nama)
      } catch (e) {
        /* localStorage tidak tersedia */
      }
    }
    const btn = document.getElementById("themeToggle")
    if (btn) {
      const ic = btn.querySelector("i")
      if (ic) ic.className = nama === "dark" ? "fas fa-sun" : "fas fa-moon"
      btn.setAttribute("aria-label", nama === "dark" ? "Mode terang" : "Mode gelap")
    }
  }

  // Sinkronkan ikon dengan tema yang sudah dipasang oleh skrip <head>
  setTema(
    rootEl.getAttribute("data-theme") ||
      temaTersimpan() ||
      (window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light"),
    false
  )

  const themeToggle = document.getElementById("themeToggle")
  if (themeToggle) {
    themeToggle.addEventListener("click", () => {
      const gelap = rootEl.getAttribute("data-theme") === "dark"
      setTema(gelap ? "light" : "dark", true)
    })
  }

  // Ikuti tema OS selama pengguna belum memilih manual
  window.matchMedia("(prefers-color-scheme: dark)").addEventListener("change", (e) => {
    if (!temaTersimpan()) setTema(e.matches ? "dark" : "light", false)
  })

  // Hapus massal: checkbox per baris + tombol "Hapus Terpilih"
  document.querySelectorAll(".form-hapus-massal").forEach((form) => {
    const all = form.querySelector(".cb-all")
    const btn = form.querySelector('button[type="submit"]')
    const count = form.querySelector(".cb-count")
    const items = () => Array.from(form.querySelectorAll(".cb-item"))

    const refresh = () => {
      const dipilih = items().filter((c) => c.checked)
      if (count) count.textContent = dipilih.length
      if (btn) btn.disabled = dipilih.length === 0
      if (all) {
        all.checked = dipilih.length > 0 && dipilih.length === items().length
        all.indeterminate = dipilih.length > 0 && dipilih.length < items().length
      }
    }

    if (all) {
      all.addEventListener("change", () => {
        items().forEach((c) => {
          c.checked = all.checked
        })
        refresh()
      })
    }

    form.addEventListener("change", (e) => {
      if (e.target.classList.contains("cb-item")) refresh()
    })

    form.addEventListener("submit", (e) => {
      const n = items().filter((c) => c.checked).length
      if (n === 0) {
        e.preventDefault()
        return
      }
      if (!confirm(`Hapus ${n} transaksi terpilih?`)) e.preventDefault()
    })

    refresh()
  })

  // Angka count-up pada kartu ringkasan
  const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches
  const fmtRp = (n) => "Rp " + Math.round(n).toLocaleString("id-ID")
  document.querySelectorAll(".count-up").forEach((el) => {
    const target = Number.parseFloat(el.getAttribute("data-value") || "0")
    if (!Number.isFinite(target)) return
    if (reduceMotion) {
      el.textContent = fmtRp(target)
      return
    }
    const durasi = 900
    const mulai = performance.now()
    const ease = (t) => 1 - Math.pow(1 - t, 3)
    const langkah = (now) => {
      const p = Math.min(1, (now - mulai) / durasi)
      el.textContent = fmtRp(target * ease(p))
      if (p < 1) requestAnimationFrame(langkah)
      else el.textContent = fmtRp(target)
    }
    requestAnimationFrame(langkah)
  })
})
