/* Editor lokal; gambar hanya menjadi File baru setelah pengguna menerapkan potongan. */
;(function () {
  const MAX_PIXELS = 6000000
  const MAX_SIDE = 4096
  const MAX_FILE_SIZE = 10 * 1024 * 1024
  const clamp = (value, min, max) => Math.min(max, Math.max(min, value))

  function attach(panel, options = {}) {
    const editor = panel.querySelector("[data-receipt-crop]")
    const opener = panel.querySelector("[data-scan-crop]")
    if (!editor || !opener) return { setFile() {}, setBusy() {}, close() {}, dispose() {} }
    const canvas = editor.querySelector("[data-receipt-crop-canvas]")
    const message = editor.querySelector("[data-receipt-crop-status]")
    const inputs = Array.from(editor.querySelectorAll("[data-receipt-crop-edge]"))
    const controls = Array.from(editor.querySelectorAll("button, input"))
    const closeButton = editor.querySelector("[data-receipt-crop-close]")
    const listeners = []
    let file = null
    let source = null
    let imageUrl = null
    let pendingImage = null
    let busy = false
    let working = false
    let disposed = false
    let generation = 0
    let angle = 0
    let selection = { left: 0.03, right: 0.97, top: 0.02, bottom: 0.98 }
    let pointer = null

    function listen(target, event, handler) {
      target.addEventListener(event, handler)
      listeners.push(() => target.removeEventListener(event, handler))
    }

    function enableControls() {
      opener.disabled = busy || working || disposed
      for (const control of controls) control.disabled = busy || working || !source
      closeButton.disabled = busy
      canvas.style.pointerEvents = busy || working || !source ? "none" : "auto"
    }

    function release() {
      if (pointer) {
        try { canvas.releasePointerCapture(pointer.id) } catch (_) {}
        pointer = null
      }
      if (pendingImage) {
        pendingImage.src = ""
        pendingImage = null
      }
      if (imageUrl) {
        URL.revokeObjectURL(imageUrl)
        imageUrl = null
      }
      if (source) source.width = source.height = 1
      source = null
      canvas.width = canvas.height = 1
    }

    function close(restoreFocus = false) {
      generation++
      working = false
      editor.hidden = true
      release()
      enableControls()
      if (restoreFocus && !opener.hidden && !opener.disabled) opener.focus()
    }

    function orientedSize() {
      return angle % 2 ? { width: source.height, height: source.width } : { width: source.width, height: source.height }
    }

    function drawImage(context, width, height) {
      context.save()
      context.translate(width / 2, height / 2)
      context.rotate(angle * Math.PI / 2)
      const size = orientedSize()
      const scale = width / size.width
      context.drawImage(source, -source.width * scale / 2, -source.height * scale / 2,
        source.width * scale, source.height * scale)
      context.restore()
    }

    function draw() {
      if (!source || editor.hidden) return
      const size = orientedSize()
      const scale = Math.min(1, 1000 / size.width, 1400 / size.height)
      canvas.width = Math.max(1, Math.round(size.width * scale))
      canvas.height = Math.max(1, Math.round(size.height * scale))
      const context = canvas.getContext("2d")
      drawImage(context, canvas.width, canvas.height)
      const x = selection.left * canvas.width
      const y = selection.top * canvas.height
      const width = (selection.right - selection.left) * canvas.width
      const height = (selection.bottom - selection.top) * canvas.height
      context.fillStyle = "rgba(0, 0, 0, 0.6)"
      context.fillRect(0, 0, canvas.width, y)
      context.fillRect(0, y + height, canvas.width, canvas.height - y - height)
      context.fillRect(0, y, x, height)
      context.fillRect(x + width, y, canvas.width - x - width, height)
      context.lineWidth = Math.max(2, canvas.width / 250)
      context.strokeStyle = "#fff"
      context.strokeRect(x, y, width, height)
      context.setLineDash([8, 5])
      context.strokeStyle = "#315bff"
      context.strokeRect(x, y, width, height)
      for (const input of inputs) input.value = String(Math.round(selection[input.dataset.receiptCropEdge] * 1000) / 10)
    }

    async function open() {
      if (!file || busy || working || disposed) return
      close()
      const currentGeneration = generation
      const inputFile = file
      working = true
      angle = 0
      selection = { left: 0.03, right: 0.97, top: 0.02, bottom: 0.98 }
      editor.hidden = false
      message.textContent = "Menyiapkan foto…"
      enableControls()
      closeButton.focus()
      const url = URL.createObjectURL(inputFile)
      imageUrl = url
      const img = new Image()
      pendingImage = img
      try {
        img.src = url
        await img.decode()
        if (currentGeneration !== generation || disposed) return
        if (!img.naturalWidth || !img.naturalHeight) throw new Error("Gambar tidak memiliki ukuran yang valid.")
        const scale = Math.min(1, MAX_SIDE / img.naturalWidth, MAX_SIDE / img.naturalHeight,
          Math.sqrt(MAX_PIXELS / (img.naturalWidth * img.naturalHeight)))
        source = document.createElement("canvas")
        source.width = Math.max(1, Math.floor(img.naturalWidth * scale))
        source.height = Math.max(1, Math.floor(img.naturalHeight * scale))
        const context = source.getContext("2d")
        if (!context) throw new Error("Perangkat tidak dapat membuka editor gambar.")
        context.fillStyle = "#fff"
        context.fillRect(0, 0, source.width, source.height)
        context.imageSmoothingQuality = "high"
        context.drawImage(img, 0, 0, source.width, source.height)
        message.textContent = "Area terang akan dibaca. Pastikan kata total dan nominalnya tidak terpotong."
        draw()
      } catch (_) {
        if (currentGeneration === generation) {
          release()
          message.textContent = "Foto tidak dapat dibuka. Pilih ulang foto JPG, PNG, atau WebP."
        }
      } finally {
        URL.revokeObjectURL(url)
        if (imageUrl === url) imageUrl = null
        img.src = ""
        if (pendingImage === img) pendingImage = null
        if (currentGeneration === generation) {
          working = false
          enableControls()
        }
      }
    }

    function point(event) {
      const bounds = canvas.getBoundingClientRect()
      return { x: clamp((event.clientX - bounds.left) / bounds.width, 0, 1),
        y: clamp((event.clientY - bounds.top) / bounds.height, 0, 1) }
    }

    listen(opener, "click", open)
    listen(closeButton, "click", () => close(true))
    listen(editor, "keydown", (event) => {
      if (event.key === "Escape" && !busy) {
        event.preventDefault()
        event.stopPropagation()
        close(true)
      }
    })
    listen(editor.querySelector("[data-receipt-crop-rotate]"), "click", () => {
      if (!source || busy || working) return
      angle = (angle + 1) % 4
      selection = { left: 1 - selection.bottom, right: 1 - selection.top,
        top: selection.left, bottom: selection.right }
      draw()
    })
    listen(editor.querySelector("[data-receipt-crop-reset]"), "click", () => {
      if (!source || busy || working) return
      selection = { left: 0, right: 1, top: 0, bottom: 1 }
      draw()
    })
    for (const input of inputs) listen(input, "change", () => {
      if (!source || busy || working) return
      const edge = input.dataset.receiptCropEdge
      const value = Number(input.value) / 100
      if (!Number.isFinite(value) || input.value === "") { draw(); return }
      const minimum = edge === "right" ? selection.left + 0.01 : edge === "bottom" ? selection.top + 0.01 : 0
      const maximum = edge === "left" ? selection.right - 0.01 : edge === "top" ? selection.bottom - 0.01 : 1
      selection[edge] = clamp(value, minimum, maximum)
      draw()
    })
    listen(canvas, "pointerdown", (event) => {
      if (!source || busy || working || pointer || (event.pointerType === "mouse" && event.button !== 0)) return
      event.preventDefault()
      pointer = { id: event.pointerId, start: point(event), original: { ...selection } }
      canvas.setPointerCapture(event.pointerId)
    })
    listen(canvas, "pointermove", (event) => {
      if (!pointer || pointer.id !== event.pointerId) return
      const end = point(event)
      if (Math.abs(pointer.start.x - end.x) < 0.01 || Math.abs(pointer.start.y - end.y) < 0.01) return
      selection = { left: Math.min(pointer.start.x, end.x), right: Math.max(pointer.start.x, end.x),
        top: Math.min(pointer.start.y, end.y), bottom: Math.max(pointer.start.y, end.y) }
      draw()
    })
    const finishPointer = (event) => {
      if (!pointer || pointer.id !== event.pointerId) return
      if (event.type === "pointercancel") selection = pointer.original
      try { canvas.releasePointerCapture(pointer.id) } catch (_) {}
      pointer = null
      draw()
    }
    listen(canvas, "pointerup", finishPointer)
    listen(canvas, "pointercancel", finishPointer)

    listen(editor.querySelector("[data-receipt-crop-apply]"), "click", async () => {
      if (!source || busy || working) return
      const currentGeneration = generation
      const size = orientedSize()
      const left = Math.round(selection.left * size.width)
      const top = Math.round(selection.top * size.height)
      const width = Math.round(selection.right * size.width) - left
      const height = Math.round(selection.bottom * size.height) - top
      if (width < 40 || height < 24) {
        message.textContent = "Area terlalu kecil. Perlebar kotak agar tulisan TOTAL dan angkanya ikut terbaca."
        return
      }
      working = true
      enableControls()
      message.textContent = "Menyiapkan potongan…"
      const output = document.createElement("canvas")
      output.width = width
      output.height = height
      try {
        const context = output.getContext("2d")
        context.translate(-left, -top)
        drawImage(context, size.width, size.height)
        const blob = await new Promise((resolve) => output.toBlob(resolve, "image/png"))
        if (currentGeneration !== generation || disposed) return
        if (!blob || blob.size > MAX_FILE_SIZE) throw new Error("Hasil potongan terlalu besar. Pilih area struk atau total yang lebih kecil.")
        const result = new File([blob], "struk-potongan.png", { type: "image/png", lastModified: Date.now() })
        close(true)
        if (typeof options.onApply === "function") options.onApply(result)
      } catch (error) {
        if (currentGeneration === generation) message.textContent = error.message || "Potongan gagal dibuat. Coba pilih area lebih kecil."
      } finally {
        output.width = output.height = 1
        if (currentGeneration === generation) {
          working = false
          enableControls()
        }
      }
    })

    return {
      setFile(nextFile) {
        close()
        const valid = nextFile && /^image\/(jpeg|png|webp)$/.test(nextFile.type)
          && nextFile.size > 0 && nextFile.size <= MAX_FILE_SIZE
        file = valid ? nextFile : null
        opener.hidden = !file
        enableControls()
        return Boolean(valid)
      },
      setBusy(value) {
        busy = Boolean(value)
        if (busy && !editor.hidden) close()
        enableControls()
      },
      close,
      dispose() {
        disposed = true
        close()
        file = null
        opener.hidden = true
        for (const remove of listeners) remove()
      },
    }
  }

  window.ReceiptCrop = { attach }
})()
