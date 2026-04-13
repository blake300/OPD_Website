// ── Accordion Dropdown Component ──
window.AccordionDropdown = (function () {
  function create(container, nodes, opts) {
    opts = opts || {}
    var wrap = document.createElement('div')
    wrap.className = 'accdd-wrap'
    var input = document.createElement('input')
    input.type = 'text'
    input.className = 'accdd-input'
    input.readOnly = true
    input.placeholder = opts.placeholder || 'Select...'
    var chev = document.createElement('span')
    chev.className = 'accdd-chev'
    chev.textContent = '\u25BC'
    var panel = document.createElement('div')
    panel.className = 'accdd-panel'
    wrap.appendChild(input)
    wrap.appendChild(chev)
    wrap.appendChild(panel)
    container.appendChild(wrap)

    var committed = opts.value || ''
    if (committed) input.value = committed.split(' > ').pop()

    input.addEventListener('click', function () { panel.classList.toggle('open') })
    document.addEventListener('click', function (e) { if (!wrap.contains(e.target)) panel.classList.remove('open') })

    var allBodies = [], allArrows = []

    function buildPanel() {
      panel.innerHTML = ''
      allBodies = []; allArrows = []
      if (!nodes || !nodes.length) {
        var empty = document.createElement('div')
        empty.style.cssText = 'padding:12px;color:#999;font-size:13px;text-align:center;'
        empty.textContent = 'No options available'
        panel.appendChild(empty)
        return
      }

      var leafOnly = !!opts.leafOnly

      nodes.forEach(function (top) {
        var sec = document.createElement('div'); sec.className = 'accdd-sec'
        var hdr = document.createElement('div'); hdr.className = 'accdd-top'
        hdr.innerHTML = '<span class="accdd-bar"></span><span class="accdd-lbl">' + esc(top.label) + '</span><span class="accdd-arr">\u25B6</span>'
        var body = document.createElement('div'); body.className = 'accdd-mid'
        var arr = hdr.querySelector('.accdd-arr')
        allBodies.push(body); allArrows.push(arr)

        hdr.addEventListener('click', function () {
          var was = body.classList.contains('open')
          allBodies.forEach(function (b) { b.classList.remove('open') })
          allArrows.forEach(function (a) { a.classList.remove('open') })
          if (!was) { body.classList.add('open'); arr.classList.add('open') }
        })

        if (!top.children || !top.children.length) {
          hdr.addEventListener('click', function () { doSelect(top.label) })
          sec.appendChild(hdr)
          panel.appendChild(sec)
          return
        }

        // If not leaf-only, parent labels are also selectable
        if (!leafOnly) {
          hdr.querySelector('.accdd-lbl').style.cursor = 'pointer'
          hdr.querySelector('.accdd-lbl').addEventListener('click', function (e) {
            e.stopPropagation()
            doSelect(top.label)
          })
        }

        var secChildren = [], secGcWraps = []
        top.children.forEach(function (child) {
          var cDiv = document.createElement('div'); cDiv.className = 'accdd-child'
          var cBar = document.createElement('span'); cBar.className = 'accdd-child-bar'
          var cText = document.createElement('span'); cText.className = 'accdd-child-text'
          cText.textContent = child.label
          cDiv.appendChild(cBar); cDiv.appendChild(cText)
          secChildren.push(cDiv)

          if (!child.children || !child.children.length) {
            cDiv.addEventListener('click', function () { doSelect(top.label + ' > ' + child.label) })
            cDiv.addEventListener('mouseenter', function () { input.value = child.label; input.style.color = '#aaa' })
            cDiv.addEventListener('mouseleave', function () { input.value = committed ? committed.split(' > ').pop() : ''; input.style.color = '' })
            body.appendChild(cDiv)
            return
          }

          // If not leaf-only, child text is selectable directly
          if (!leafOnly) {
            cText.style.cursor = 'pointer'
            cText.addEventListener('click', function (e) {
              e.stopPropagation()
              doSelect(top.label + ' > ' + child.label)
            })
            cText.addEventListener('mouseenter', function () { input.value = child.label; input.style.color = '#aaa' })
            cText.addEventListener('mouseleave', function () { input.value = committed ? committed.split(' > ').pop() : ''; input.style.color = '' })
          }

          var gcW = document.createElement('div'); gcW.className = 'accdd-gcw'
          secGcWraps.push(gcW)

          cDiv.addEventListener('click', function () {
            var was = gcW.classList.contains('open')
            secGcWraps.forEach(function (w) { w.classList.remove('open') })
            secChildren.forEach(function (c) { c.classList.remove('accdd-child-open') })
            if (!was) { gcW.classList.add('open'); cDiv.classList.add('accdd-child-open') }
          })

          child.children.forEach(function (gc) {
            var g = document.createElement('div'); g.className = 'accdd-gc'
            var gDot = document.createElement('span'); gDot.className = 'accdd-dot'
            var gText = document.createElement('span'); gText.style.flex = '1'; gText.textContent = gc.label
            g.appendChild(gDot); g.appendChild(gText)
            g.addEventListener('mouseenter', function () { input.value = gc.label; input.style.color = '#aaa' })
            g.addEventListener('mouseleave', function () { input.value = committed ? committed.split(' > ').pop() : ''; input.style.color = '' })
            g.addEventListener('click', function (e) {
              e.stopPropagation()
              doSelect(top.label + ' > ' + child.label + ' > ' + gc.label)
            })
            gcW.appendChild(g)
          })
          body.appendChild(cDiv); body.appendChild(gcW)
        })
        sec.appendChild(hdr); sec.appendChild(body); panel.appendChild(sec)
      })
    }

    function doSelect(path) {
      committed = path
      input.value = path.split(' > ').pop()
      input.style.color = ''
      panel.classList.remove('open')
      if (typeof opts.onChange === 'function') opts.onChange(path)
    }

    function esc(s) {
      var d = document.createElement('div'); d.textContent = s; return d.innerHTML
    }

    buildPanel()

    return {
      getValue: function () { return committed },
      setValue: function (path) {
        committed = path || ''
        input.value = committed ? committed.split(' > ').pop() : ''
      },
      setNodes: function (newNodes) { nodes = newNodes; buildPanel() },
      destroy: function () { if (wrap.parentNode) wrap.parentNode.removeChild(wrap) },
      element: wrap
    }
  }
  return { create: create }
})()

function closestWithAttr(node, attrName) {
  let current = node
  while (current && current !== document) {
    if (current.hasAttribute && current.hasAttribute(attrName)) {
      return current
    }
    current = current.parentNode
  }
  return null
}

const qtyButtons = document.querySelectorAll('[data-add-qty]')
for (let i = 0; i < qtyButtons.length; i += 1) {
  (function (button) {
    button.addEventListener('click', function () {
      const wrap = button.closest ? button.closest('[data-qty-wrap]') : closestWithAttr(button, 'data-qty-wrap')
      const input = wrap ? wrap.querySelector('input[type="number"]') : null
      if (!input) return
      const step = button.getAttribute('data-add-qty') === 'minus' ? -1 : 1
      let current = parseInt(input.value, 10)
      if (!current || current < 1) current = 1
      let next = current + step
      if (next < 1) next = 1
      input.value = String(next)
      // Fix 3: showPicker() cross-browser fallback
      if (typeof input.showPicker === 'function') {
        try {
          input.showPicker()
        } catch (e) {
          // showPicker() not supported in this browser — focus the element instead
          input.focus()
        }
      }
    })
})(qtyButtons[i])
}

/* Predictive search suggestions */
(function () {
  var form = document.querySelector('.search-bar')
  if (!form) return
  var input = form.querySelector('.search-input')
  var list = form.querySelector('.search-suggestions')
  if (!input || !list) return

  var minChars = 2
  var debounceTimer = null
  var activeIndex = -1
  var items = []
  var lastQuery = ''
  var activeController = null

  function setExpanded(expanded) {
    input.setAttribute('aria-expanded', expanded ? 'true' : 'false')
  }

  function clearSuggestions() {
    list.innerHTML = ''
    list.hidden = true
    items = []
    activeIndex = -1
    input.removeAttribute('aria-activedescendant')
    setExpanded(false)
  }

  function setActive(index) {
    if (!items.length) return
    items.forEach(function (item, idx) {
      if (idx === index) {
        item.classList.add('is-active')
        item.setAttribute('aria-selected', 'true')
        input.setAttribute('aria-activedescendant', item.id)
      } else {
        item.classList.remove('is-active')
        item.setAttribute('aria-selected', 'false')
      }
    })
    activeIndex = index
  }

  function renderSuggestions(results) {
    list.innerHTML = ''
    items = []
    activeIndex = -1
    if (!Array.isArray(results) || !results.length) {
      clearSuggestions()
      return
    }

    var fragment = document.createDocumentFragment()
    results.forEach(function (row, index) {
      var link = document.createElement('a')
      link.className = 'search-suggestion'
      link.href = '/product.php?id=' + encodeURIComponent(row.id || '')
      link.setAttribute('role', 'option')
      link.setAttribute('aria-selected', 'false')
      link.id = 'search-suggestion-' + String(index)

      if (row.imageUrl) {
        var img = document.createElement('img')
        img.className = 'suggestion-thumb'
        img.src = row.imageUrl
        img.alt = ''
        img.loading = 'lazy'
        link.appendChild(img)
      }

      var textWrap = document.createElement('div')
      textWrap.className = 'suggestion-text'

      var title = document.createElement('span')
      title.className = 'suggestion-title'
      title.textContent = row.name || 'Product'
      textWrap.appendChild(title)

      var details = []
      if (row.sku) details.push(row.sku)
      if (row.category) details.push(row.category)
      if (details.length) {
        var subtitle = document.createElement('span')
        subtitle.className = 'suggestion-subtitle'
        subtitle.textContent = details.join(' • ')
        textWrap.appendChild(subtitle)
      }

      link.appendChild(textWrap)

      link.addEventListener('mouseenter', function () {
        setActive(index)
      })

      fragment.appendChild(link)
      items.push(link)
    })

    list.appendChild(fragment)
    list.hidden = false
    setExpanded(true)
  }

  function fetchSuggestions(query) {
    if (activeController && typeof activeController.abort === 'function') {
      activeController.abort()
    }
    activeController = typeof AbortController !== 'undefined' ? new AbortController() : null
    var url = '/api/search_suggestions.php?q=' + encodeURIComponent(query)
    var options = { credentials: 'same-origin' }
    if (activeController) options.signal = activeController.signal

    fetch(url, options)
      .then(function (resp) { return resp.json().catch(function () { return {} }) })
      .then(function (data) {
        if (!data || !Array.isArray(data.results)) {
          clearSuggestions()
          return
        }
        renderSuggestions(data.results)
      })
      .catch(function (err) {
        if (err && err.name !== 'AbortError') {
          // Silently close suggestions on error — don't alert, just hide
          clearSuggestions()
        }
      })
  }

  function handleInput() {
    var query = String(input.value || '').trim()
    if (query.length < minChars) {
      clearSuggestions()
      lastQuery = query
      return
    }
    if (query === lastQuery) return
    lastQuery = query
    if (debounceTimer) clearTimeout(debounceTimer)
    debounceTimer = setTimeout(function () {
      fetchSuggestions(query)
    }, 180)
  }

  input.addEventListener('input', handleInput)

  input.addEventListener('keydown', function (event) {
    if (list.hidden || !items.length) return
    if (event.key === 'ArrowDown') {
      event.preventDefault()
      var next = activeIndex + 1
      if (next >= items.length) next = 0
      setActive(next)
      return
    }
    if (event.key === 'ArrowUp') {
      event.preventDefault()
      var prev = activeIndex - 1
      if (prev < 0) prev = items.length - 1
      setActive(prev)
      return
    }
    if (event.key === 'Enter') {
      if (activeIndex >= 0 && items[activeIndex]) {
        event.preventDefault()
        items[activeIndex].click()
      }
      return
    }
    if (event.key === 'Escape') {
      clearSuggestions()
    }
  })

  document.addEventListener('click', function (event) {
    if (!form.contains(event.target)) {
      clearSuggestions()
    }
  })
})();

/* Cart badge: update the header cart count */
window.updateCartBadge = function (delta) {
  var badge = document.querySelector('.cart-badge')
  var link = document.querySelector('.header-cart-link')
  if (!link) return
  if (!badge) {
    badge = document.createElement('span')
    badge.className = 'cart-badge'
    badge.setAttribute('data-cart-count', '0')
    badge.textContent = '0'
    link.appendChild(badge)
  }
  var current = parseInt(badge.getAttribute('data-cart-count') || '0', 10)
  var next = Math.max(0, current + (delta || 1))
  badge.setAttribute('data-cart-count', String(next))
  badge.textContent = String(next)
  badge.style.display = next > 0 ? '' : 'none'
};

/* Favorites module - centralized handling for favorite buttons across the site */
(function () {
  // Fix 1: Show a brief positioned error message near the favorite button
  function showFavoriteError(btn) {
    var msg = document.createElement('span')
    msg.textContent = 'Could not update favorites. Please try again.'
    msg.style.cssText = 'position:absolute;background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;border-radius:6px;padding:6px 10px;font-size:12px;z-index:200;white-space:nowrap;top:100%;left:50%;transform:translateX(-50%);margin-top:4px;'
    var parent = btn.closest('[style*="position"]') || btn.parentElement
    if (parent) {
      parent.style.position = parent.style.position || 'relative'
      parent.appendChild(msg)
      setTimeout(function () { msg.remove() }, 4000)
    }
  }

  function createSpinnerEl() {
    const span = document.createElement('span')
    span.className = 'favorite-spinner'
    return span
  }

  function FavoritesInit(options) {
    options = options || {}
    const csrfToken = options.csrfToken || ''
    let isSignedIn = !!options.isSignedIn
    const messageEl = document.getElementById('favorite-message')
    const cache = {}
    let openMenu = null
    let messageTimer = null
    const signInHtml = 'Please Sign-In to Select Favorites. <a href="/login.php">Sign in</a> or <a href="/register.php">Register</a>'

    function getWrap(button) {
      if (!button) return null
      if (button.closest) return button.closest('.favorite-wrap')
      let node = button
      while (node && node !== document) {
        if (node.classList && node.classList.contains('favorite-wrap')) return node
        node = node.parentNode
      }
      return null
    }

    function showInlineMessage(wrap, text, html) {
      if (!wrap) return false
      const inline = wrap.querySelector('[data-favorite-message]')
      if (!inline) return false
      if (html) {
        inline.innerHTML = html
      } else {
        inline.textContent = text
      }
      inline.hidden = false
      if (inline._favTimer) clearTimeout(inline._favTimer)
      inline._favTimer = setTimeout(function () { inline.hidden = true }, 4000)
      return true
    }

    function parseJsonResponse(resp) {
      return resp.json().catch(function () { return {} }).then(function (data) {
        if (!resp.ok) {
          const err = new Error(data.error || 'Request failed')
          err.status = resp.status
          throw err
        }
        return data
      })
    }

    function showMessage(text, options) {
      const opts = options || {}
      if (showInlineMessage(opts.wrap || null, text, opts.html || '')) {
        return
      }
      if (messageEl) {
        messageEl.textContent = text
        messageEl.style.display = 'block'
        if (messageTimer) clearTimeout(messageTimer)
        messageTimer = setTimeout(function () { messageEl.style.display = 'none' }, 3000)
        return
      }
      alert(text)
    }

    function setButtonState(button, selectedIds) {
      const active = Array.isArray(selectedIds) && selectedIds.length > 0
      button.classList.toggle('is-active', active)
      button.setAttribute('aria-pressed', active ? 'true' : 'false')
    }

    function closeMenu() {
      if (openMenu) {
        openMenu.setAttribute('hidden', '')
        openMenu = null
      }
    }

    function fetchFavorites(productId, variantId, callback) {
      const key = productId + '::' + (variantId || '')
      if (cache[key]) { callback(null, cache[key]); return }
      let url = '/api/favorites.php?productId=' + encodeURIComponent(productId)
      if (variantId) url += '&variantId=' + encodeURIComponent(variantId)
      fetch(url, { credentials: 'same-origin' })
        .then(parseJsonResponse)
        .then(function (data) { cache[key] = data; callback(null, data) })
        .catch(function (err) { callback(err || new Error('Failed')) })
    }

    function updateFavoriteSelection(button, productId, variantId, selectedIds) {
      const spinner = button.querySelector('.favorite-spinner') || createSpinnerEl()
      if (!button.querySelector('.favorite-spinner')) button.appendChild(spinner)
      button.classList.add('loading')
      return fetch('/api/favorites.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
        body: JSON.stringify({ action: 'set_product_categories', productId: productId, variantId: variantId || null, categoryIds: selectedIds })
      })
        .then(parseJsonResponse)
        .then(function (data) {
          const finalIds = Array.isArray(data.selectedCategoryIds) ? data.selectedCategoryIds : selectedIds
          setButtonState(button, finalIds)
          return finalIds
        })
        .finally(function () { button.classList.remove('loading'); const sp = button.querySelector('.favorite-spinner'); if (sp) sp.remove() })
    }

    function isUncategorized(category) {
      const name = String(category && category.name ? category.name : '').toLowerCase()
      return name === 'uncategorised' || name === 'uncategorized'
    }

    function normalizeCategories(data) {
      const categories = Array.isArray(data.categories) ? data.categories.slice() : []
      categories.forEach(function (category) {
        if (category && isUncategorized(category)) category.isDefault = true
      })
      categories.sort(function (a, b) {
        const aUncat = isUncategorized(a)
        const bUncat = isUncategorized(b)
        if (aUncat && !bUncat) return -1
        if (bUncat && !aUncat) return 1
        const aName = String(a && a.name ? a.name : '')
        const bName = String(b && b.name ? b.name : '')
        return aName.localeCompare(bName)
      })
      return categories
    }

    function sanitizeIdPart(value) {
      return String(value || '').replace(/[^a-zA-Z0-9_-]/g, '')
    }

    function renderMenu(button, menu, data) {
      menu.innerHTML = ''
      const categories = normalizeCategories(data)
      let selected = Array.isArray(data.selectedCategoryIds) ? data.selectedCategoryIds.slice() : []
      setButtonState(button, selected)
      const productId = button.getAttribute('data-product-id') || ''
      const variantId = button.getAttribute('data-variant-id') || ''
      if (!categories.length) {
        const empty = document.createElement('div'); empty.className = 'favorite-empty'; empty.textContent = 'No categories yet.'; menu.appendChild(empty); return
      }
      categories.forEach(function (category, index) {
        const row = document.createElement('label')
        row.className = 'favorite-option'
        const checkbox = document.createElement('input')
        checkbox.type = 'checkbox'
        checkbox.value = category.id || ''
        const catId = sanitizeIdPart(category && category.id ? category.id : '') || String(index)
        const baseId = 'favcat-' + sanitizeIdPart(productId || 'product') + '-' + sanitizeIdPart(variantId || 'base') + '-' + catId
        checkbox.id = baseId
        checkbox.name = 'favorite_categories[]'
        checkbox.checked = selected.indexOf(checkbox.value) !== -1
        const text = document.createElement('span')
        text.textContent = category.name || 'Category'
        row.appendChild(checkbox); row.appendChild(text); menu.appendChild(row)
      })

      const cacheKey = productId + '::' + (variantId || '')
      menu.querySelectorAll('input[type="checkbox"]').forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
          const selectedIds = []
          menu.querySelectorAll('input[type="checkbox"]').forEach(function (input) { if (input.checked) selectedIds.push(input.value) })
          updateFavoriteSelection(button, productId, variantId, selectedIds)
            .then(function (finalIds) { selected = finalIds.slice(); if (cache[cacheKey]) cache[cacheKey].selectedCategoryIds = finalIds })
            .catch(function (err) {
              const wrap = getWrap(button)
              if (err && err.status === 401) { isSignedIn = false; showMessage('Please Sign-In to Select Favorites', { wrap: wrap, html: signInHtml }) } else { showMessage('Unable to save favorites.', { wrap: wrap }) }
              menu.querySelectorAll('input[type="checkbox"]').forEach(function (input) { input.checked = selected.indexOf(input.value) !== -1 })
              setButtonState(button, selected)
              showFavoriteError(button)
            })
        })
      })

      if (!menu.dataset.bound) { menu.addEventListener('click', function (event) { event.stopPropagation() }); menu.dataset.bound = 'true' }
    }

    function bindButtons() {
      const favoriteButtons = document.querySelectorAll('[data-favorite]')
      if (!favoriteButtons.length) return
      favoriteButtons.forEach(function (button) {
        button.addEventListener('click', function (event) {
          event.preventDefault(); event.stopPropagation()
          const wrap = getWrap(button)
          if (!isSignedIn) { showMessage('Please Sign-In to Select Favorites', { wrap: wrap, html: signInHtml }); button.classList.remove('is-active'); button.setAttribute('aria-pressed', 'false'); closeMenu(); return }
          if (!wrap) { console.error('No favorite-wrap found'); showMessage('Error: Menu container not found'); return }
          const menu = wrap.querySelector('[data-favorite-menu]')
          if (!menu) { console.error('No favorite menu found'); showMessage('Error: Menu not found'); return }
          if (!menu.hasAttribute('hidden')) { menu.setAttribute('hidden', ''); openMenu = null; return }
          const productId = button.getAttribute('data-product-id') || ''
          const variantId = button.getAttribute('data-variant-id') || ''
          closeMenu()
          fetchFavorites(productId, variantId, function (err, data) {
            if (err || !data) { console.error('Failed to fetch favorites:', err); if (err && err.status === 401) { isSignedIn = false; showMessage('Please Sign-In to Select Favorites', { wrap: wrap, html: signInHtml }); setButtonState(button, []) } else { showMessage('Unable to load favorites.', { wrap: wrap }) }; return }
            renderMenu(button, menu, data)
            menu.removeAttribute('hidden')
            openMenu = menu
          })
        })
      })
      document.addEventListener('click', function () { closeMenu() })
      if (isSignedIn) {
        const initButtons = document.querySelectorAll('[data-favorite]')
        initButtons.forEach(function (button) {
          const productId = button.getAttribute('data-product-id') || ''
          const variantId = button.getAttribute('data-variant-id') || ''
          fetchFavorites(productId, variantId, function (err, data) {
            if (err || !data) { if (err && err.status === 401) { isSignedIn = false; setButtonState(button, []) }; return }
            setButtonState(button, data.selectedCategoryIds || [])
          })
        })
      } else {
        const initButtons = document.querySelectorAll('[data-favorite]')
        initButtons.forEach(function (button) { setButtonState(button, []) })
      }
    }

    bindButtons()
    return { bind: bindButtons }
  }

  window.Favorites = { init: FavoritesInit }
})();

