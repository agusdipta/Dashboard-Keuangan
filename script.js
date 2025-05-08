document.addEventListener("DOMContentLoaded", () => {
  // Format currency inputs
  const jumlahInput = document.getElementById("jumlah")
  if (jumlahInput) {
    jumlahInput.addEventListener("input", (e) => {
      // Remove non-numeric characters
      const value = e.target.value.replace(/[^\d]/g, "")

      // Format with thousand separators for display only
      if (value.length > 0) {
        const formattedValue = new Intl.NumberFormat("id-ID").format(value)
        // We don't update the input value directly to avoid cursor position issues
        // This is just for visual feedback
        document.getElementById("formatted-amount").textContent = `Rp ${formattedValue}`
      } else {
        document.getElementById("formatted-amount").textContent = ""
      }
    })
  }

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

  // Set current date to the date input by default
  const dateInput = document.getElementById("tanggal")
  if (dateInput) {
    const today = new Date()
    const formattedDate = today.toISOString().substr(0, 10)
    dateInput.value = formattedDate
  }

  // Tema aplikasi
  const applyTheme = () => {
    const selectedTheme = localStorage.getItem("appTheme") || "default"
    document.documentElement.setAttribute("data-theme", selectedTheme)

    // Tambahkan kelas tema ke body
    document.body.className = ""
    document.body.classList.add(`theme-${selectedTheme}`)
  }

  // Terapkan tema saat halaman dimuat
  applyTheme()
})
