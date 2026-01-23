document.querySelectorAll('[data-add-qty]').forEach((button) => {
  button.addEventListener('click', () => {
    const input = button.closest('[data-qty-wrap]')?.querySelector('input[type="number"]')
    if (!input) return
    const step = button.dataset.addQty === 'minus' ? -1 : 1
    const next = Math.max(1, Number(input.value || 1) + step)
    input.value = String(next)
  })
})
