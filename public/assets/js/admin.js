const productsResource = {
  id: 'products',
  title: 'Products',
  endpoint: '/api/products.php',
  fields: [
    { name: 'name', label: 'Name', required: true },
    { name: 'sku', label: 'SKU', required: true },
    {
      name: 'category',
      label: 'Category',
      type: 'select',
      options: [
        'AutoBailer Artifical Lift',
        'Parts',
        'Tools',
        'Services',
        'Supplies',
        'Used Equipment'
      ]
    },
    { name: 'imageUrl', label: 'Image URL' },
    { name: 'imageFile', label: 'Image File', type: 'file', transient: true },
    { name: 'status', label: 'Status' },
    { name: 'price', label: 'Price' },
    { name: 'inventory', label: 'Inventory' }
  ],
  columns: ['name', 'sku', 'category', 'status', 'price', 'inventory', 'updatedAt']
}

const resources = [
  {
    id: 'orders',
    title: 'Orders',
    endpoint: '/api/orders.php',
    fields: [
      { name: 'number', label: 'Order #', required: true },
      { name: 'customerName', label: 'Customer', required: true },
      { name: 'status', label: 'Status' },
      { name: 'paymentStatus', label: 'Payment' },
      { name: 'fulfillmentStatus', label: 'Fulfillment' },
      { name: 'total', label: 'Total' },
      { name: 'currency', label: 'Currency' }
    ],
    columns: ['number', 'customerName', 'status', 'paymentStatus', 'fulfillmentStatus', 'total', 'updatedAt']
  },
  {
    id: 'customers',
    title: 'Customers',
    endpoint: '/api/customers.php',
    fields: [
      { name: 'name', label: 'Name', required: true },
      { name: 'email', label: 'Email', required: true },
      { name: 'phone', label: 'Phone' },
      { name: 'status', label: 'Status' },
      { name: 'ltv', label: 'LTV' },
      { name: 'tags', label: 'Tags' }
    ],
    columns: ['name', 'email', 'status', 'ltv', 'tags', 'updatedAt']
  },
  {
    id: 'inventory',
    title: 'Inventory',
    endpoint: '/api/inventory.php',
    fields: [
      { name: 'sku', label: 'SKU', required: true },
      { name: 'location', label: 'Location', required: true },
      { name: 'onHand', label: 'On Hand' },
      { name: 'reserved', label: 'Reserved' },
      { name: 'available', label: 'Available' },
      { name: 'reorderPoint', label: 'Reorder Point' }
    ],
    columns: ['sku', 'location', 'onHand', 'reserved', 'available', 'updatedAt']
  },
  {
    id: 'promotions',
    title: 'Promotions',
    endpoint: '/api/promotions.php',
    fields: [
      { name: 'code', label: 'Code', required: true },
      { name: 'type', label: 'Type' },
      { name: 'value', label: 'Value' },
      { name: 'status', label: 'Status' },
      { name: 'startsAt', label: 'Starts' },
      { name: 'endsAt', label: 'Ends' },
      { name: 'usageLimit', label: 'Usage Limit' },
      { name: 'used', label: 'Used' }
    ],
    columns: ['code', 'type', 'status', 'value', 'used', 'updatedAt']
  },
  {
    id: 'payments',
    title: 'Payments',
    endpoint: '/api/payments.php',
    fields: [
      { name: 'orderId', label: 'Order ID', required: true },
      { name: 'method', label: 'Method', required: true },
      { name: 'amount', label: 'Amount' },
      { name: 'status', label: 'Status' },
      { name: 'capturedAt', label: 'Captured At' }
    ],
    columns: ['orderId', 'method', 'status', 'amount', 'capturedAt']
  },
  {
    id: 'shipments',
    title: 'Shipments',
    endpoint: '/api/shipments.php',
    fields: [
      { name: 'orderId', label: 'Order ID', required: true },
      { name: 'carrier', label: 'Carrier', required: true },
      { name: 'tracking', label: 'Tracking', required: true },
      { name: 'status', label: 'Status' },
      { name: 'shippedAt', label: 'Shipped At' },
      { name: 'eta', label: 'ETA' }
    ],
    columns: ['orderId', 'carrier', 'status', 'tracking', 'eta']
  },
  {
    id: 'analytics',
    title: 'Analytics Reports',
    endpoint: '/api/analytics.php',
    fields: [
      { name: 'name', label: 'Report Name', required: true },
      { name: 'period', label: 'Period', required: true },
      { name: 'metric', label: 'Metric', required: true },
      { name: 'value', label: 'Value' }
    ],
    columns: ['name', 'period', 'metric', 'value', 'updatedAt']
  },
  {
    id: 'content',
    title: 'Content and Pages',
    endpoint: '/api/content.php',
    fields: [
      { name: 'title', label: 'Title', required: true },
      { name: 'slug', label: 'Slug', required: true },
      { name: 'status', label: 'Status' }
    ],
    columns: ['title', 'slug', 'status', 'updatedAt']
  },
  {
    id: 'users',
    title: 'Users and Roles',
    endpoint: '/api/users.php',
    fields: [
      { name: 'name', label: 'Name', required: true },
      { name: 'email', label: 'Email', required: true },
      { name: 'role', label: 'Role' },
      { name: 'status', label: 'Status' },
      { name: 'lastLogin', label: 'Last Login' }
    ],
    columns: ['name', 'email', 'role', 'status', 'lastLogin']
  },
  {
    id: 'integrations',
    title: 'Integrations',
    endpoint: '/api/integrations.php',
    fields: [
      { name: 'name', label: 'Name', required: true },
      { name: 'type', label: 'Type', required: true },
      { name: 'status', label: 'Status' },
      { name: 'lastSync', label: 'Last Sync' }
    ],
    columns: ['name', 'type', 'status', 'lastSync']
  },
  {
    id: 'settings',
    title: 'System Settings',
    endpoint: '/api/settings.php',
    fields: [
      { name: 'key', label: 'Key', required: true },
      { name: 'value', label: 'Value', required: true }
    ],
    columns: ['key', 'value', 'updatedAt']
  },
  {
    id: 'reliability',
    title: 'Reliability Logs',
    endpoint: '/api/reliability.php',
    fields: [
      { name: 'type', label: 'Type', required: true },
      { name: 'status', label: 'Status' },
      { name: 'message', label: 'Message' }
    ],
    columns: ['type', 'status', 'message', 'createdAt']
  }
]

const variantFields = [
  { name: 'productId', label: 'Product', type: 'hidden', required: true },
  { name: 'name', label: 'Variant Name', required: true },
  { name: 'sku', label: 'SKU', required: true },
  { name: 'status', label: 'Status' },
  { name: 'price', label: 'Price' },
  { name: 'inventory', label: 'Inventory' }
]

const variantColumns = ['name', 'sku', 'status', 'price', 'inventory', 'updatedAt']

const nav = document.getElementById('nav')
const stack = document.getElementById('resource-stack')
const metrics = document.getElementById('metrics')
const productsPanel = document.getElementById('products-panel')
const productsSearch = document.getElementById('products-search')
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''

const numericFields = new Set([
  'price',
  'inventory',
  'total',
  'ltv',
  'onHand',
  'reserved',
  'available',
  'reorderPoint',
  'usageLimit',
  'used',
  'amount',
  'value'
])
const statusColumns = new Set([
  'status',
  'paymentStatus',
  'fulfillmentStatus'
])
const dateFields = new Set([
  'startsAt',
  'endsAt',
  'capturedAt',
  'shippedAt',
  'eta',
  'lastLogin',
  'lastSync',
  'createdAt',
  'updatedAt'
])

const state = {}

function normalizeStatus(value) {
  return String(value || '')
    .toLowerCase()
    .trim()
    .replace(/\s+/g, '_')
}

function formatValue(value) {
  if (value === null || value === undefined || value === '') {
    return '—'
  }
  return String(value)
}

function createField(resourceId, field) {
  const fieldWrap = document.createElement('div')
  fieldWrap.className = 'field'
  let input
  if (field.type === 'select') {
    input = document.createElement('select')
  } else if (field.type === 'textarea') {
    input = document.createElement('textarea')
  } else {
    input = document.createElement('input')
  }
  input.name = field.name
  input.id = `${resourceId}-${field.name}`
  if (field.type === 'file') {
    input.type = 'file'
    input.accept = 'image/*'
  } else if (field.type && field.type !== 'select' && field.type !== 'textarea') {
    input.type = field.type
  }
  if (field.type === 'select' && Array.isArray(field.options)) {
    const placeholder = document.createElement('option')
    placeholder.value = ''
    placeholder.textContent = 'Select...'
    input.appendChild(placeholder)
    field.options.forEach((option) => {
      const opt = document.createElement('option')
      opt.value = option
      opt.textContent = option
      input.appendChild(opt)
    })
  }
  if (field.required) {
    input.required = true
  }
  if (numericFields.has(field.name)) {
    input.type = 'number'
    input.step = '0.01'
  }
  if (dateFields.has(field.name)) {
    input.type = 'text'
  }
  if (field.type !== 'hidden') {
    const label = document.createElement('label')
    label.htmlFor = input.id
    label.textContent = field.label
    fieldWrap.appendChild(label)
  } else {
    fieldWrap.style.display = 'none'
  }
  fieldWrap.appendChild(input)
  return fieldWrap
}

function createForm(resource) {
  const form = document.createElement('form')
  form.className = 'form'
  form.dataset.resourceId = resource.id
  form.dataset.form = resource.id

  const message = document.createElement('div')
  message.className = 'form-message'
  form.appendChild(message)

  resource.fields.forEach((field) => {
    form.appendChild(createField(resource.id, field))
  })

  const actions = document.createElement('div')
  actions.className = 'form-actions'
  const saveBtn = document.createElement('button')
  saveBtn.type = 'submit'
  saveBtn.className = 'primary-btn'
  saveBtn.textContent = 'Save'
  const clearBtn = document.createElement('button')
  clearBtn.type = 'button'
  clearBtn.className = 'ghost-btn'
  clearBtn.textContent = 'Clear'
  clearBtn.addEventListener('click', () => {
    clearForm(form)
  })
  actions.appendChild(saveBtn)
  actions.appendChild(clearBtn)
  form.appendChild(actions)

  form.addEventListener('submit', async (event) => {
    event.preventDefault()
    message.textContent = ''
    const payload = readForm(form, resource.fields)
    const editingId = form.dataset.editingId
    try {
      let saved
      if (editingId) {
        saved = await updateItem(resource, editingId, payload)
        form.dataset.editingId = ''
      } else {
        saved = await createItem(resource, payload)
      }
      if (resource.id === 'products') {
        if (saved) {
          fillForm(form, resource.fields, saved)
          setSelectedProduct(saved)
        }
      } else {
        clearForm(form)
      }
      await refreshResource(resource)
    } catch (error) {
      message.textContent = error instanceof Error ? error.message : 'Save failed'
    }
  })

  return form
}

function clearForm(form) {
  form.reset()
  form.dataset.editingId = ''
  if (form.dataset.resourceId === 'products') {
    clearSelectedProduct()
  }
  const message = form.querySelector('.form-message')
  if (message) {
    message.textContent = ''
  }
}

function readForm(form, fields) {
  const payload = {}
  fields.forEach((field) => {
    if (field.transient || field.type === 'file') {
      return
    }
    const input = form.querySelector(`[name="${field.name}"]`)
    if (!input) {
      return
    }
    const value = input.value.trim()
    if (value === '') {
      payload[field.name] = null
      return
    }
    if (numericFields.has(field.name)) {
      payload[field.name] = Number(value)
      return
    }
    payload[field.name] = value
  })
  return payload
}

function fillForm(form, fields, item) {
  fields.forEach((field) => {
    if (field.transient || field.type === 'file') {
      return
    }
    const input = form.querySelector(`[name="${field.name}"]`)
    if (!input) {
      return
    }
    const value = item[field.name]
    input.value = value === null || value === undefined ? '' : String(value)
  })
  form.dataset.editingId = item.id
  const message = form.querySelector('.form-message')
  if (message) {
    message.textContent = 'Editing existing record'
  }
}

function createTable(resource) {
  const table = document.createElement('div')
  table.className = 'table'
  table.dataset.table = resource.id
  return table
}

function renderTable(resource, table, items, form) {
  table.innerHTML = ''
  const head = document.createElement('div')
  head.className = 'table-head'
  const gridTemplate = `repeat(${resource.columns.length}, minmax(0, 1fr)) 140px`
  head.style.gridTemplateColumns = gridTemplate
  resource.columns.forEach((column) => {
    const cell = document.createElement('div')
    cell.textContent = column
    head.appendChild(cell)
  })
  const actionsHead = document.createElement('div')
  actionsHead.textContent = 'Actions'
  head.appendChild(actionsHead)
  table.appendChild(head)

  if (!items.length) {
    const empty = document.createElement('div')
    empty.className = 'table-empty'
    empty.textContent = 'No records yet.'
    table.appendChild(empty)
    return
  }

  items.forEach((item) => {
    const row = document.createElement('div')
    row.className = 'table-row'
    row.style.gridTemplateColumns = gridTemplate
    resource.columns.forEach((column) => {
      const cell = document.createElement('div')
      const value = item[column]
      if (statusColumns.has(column) && value) {
        const pill = document.createElement('span')
        pill.className = `status ${normalizeStatus(value)}`
        pill.textContent = value
        cell.appendChild(pill)
      } else {
        cell.textContent = formatValue(value)
      }
      row.appendChild(cell)
    })

    const actions = document.createElement('div')
    actions.className = 'row-actions'
    const editBtn = document.createElement('button')
    editBtn.type = 'button'
    editBtn.className = 'ghost-btn'
    editBtn.textContent = 'Edit'
    editBtn.addEventListener('click', () => {
      fillForm(form, resource.fields, item)
      if (resource.id === 'products') {
        setSelectedProduct(item)
      }
    })
    const deleteBtn = document.createElement('button')
    deleteBtn.type = 'button'
    deleteBtn.className = 'ghost-btn'
    deleteBtn.textContent = 'Delete'
    deleteBtn.addEventListener('click', async () => {
      await deleteItem(resource, item.id)
      await refreshResource(resource)
      if (resource.id === 'products' && state.products?.selectedProduct?.id === item.id) {
        clearSelectedProduct()
      }
    })
    actions.appendChild(editBtn)
    actions.appendChild(deleteBtn)
    row.appendChild(actions)
    table.appendChild(row)
  })
}

async function fetchItems(resource) {
  const response = await fetch(resource.endpoint, { method: 'GET' })
  return handleJsonResponse(response, 'Failed to load data', true)
}

async function createItem(resource, payload) {
  const response = await fetch(resource.endpoint, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': csrfToken
    },
    body: JSON.stringify(payload)
  })
  return handleJsonResponse(response, 'Create failed')
}

async function updateItem(resource, id, payload) {
  const response = await fetch(`${resource.endpoint}?id=${encodeURIComponent(id)}`, {
    method: 'PUT',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': csrfToken
    },
    body: JSON.stringify(payload)
  })
  return handleJsonResponse(response, 'Update failed')
}

async function deleteItem(resource, id) {
  const response = await fetch(`${resource.endpoint}?id=${encodeURIComponent(id)}`, {
    method: 'DELETE',
    headers: {
      'X-CSRF-Token': csrfToken
    }
  })
  return handleJsonResponse(response, 'Delete failed')
}

async function handleJsonResponse(response, fallbackMessage, returnItemsOnly = false) {
  if (response.status === 401) {
    window.location.href = '/admin-login.php'
    throw new Error('Unauthorized')
  }
  if (response.status === 403) {
    throw new Error('Forbidden')
  }
  if (!response.ok) {
    throw new Error(fallbackMessage)
  }
  const data = await response.json()
  if (returnItemsOnly) {
    return data.items || []
  }
  return data
}

function setFormEnabled(form, enabled) {
  form.querySelectorAll('input, select, textarea, button').forEach((element) => {
    if (element.type === 'hidden') {
      return
    }
    element.disabled = !enabled
  })
}

function updateImagePreview(form, url) {
  const preview = form.querySelector('[data-image-preview]')
  if (!preview) {
    return
  }
  if (url) {
    preview.src = url
    preview.style.display = 'block'
  } else {
    preview.removeAttribute('src')
    preview.style.display = 'none'
  }
}

function wireProductImageUpload(form) {
  const urlInput = form.querySelector('[name="imageUrl"]')
  const fileInput = form.querySelector('[name="imageFile"]')
  if (!urlInput || !fileInput) {
    return
  }

  const preview = document.createElement('img')
  preview.className = 'image-preview'
  preview.setAttribute('data-image-preview', 'true')
  preview.style.display = 'none'
  urlInput.parentElement?.appendChild(preview)

  const uploadRow = document.createElement('div')
  uploadRow.className = 'form-actions'
  const uploadBtn = document.createElement('button')
  uploadBtn.type = 'button'
  uploadBtn.className = 'ghost-btn'
  uploadBtn.textContent = 'Upload Image'
  const uploadMessage = document.createElement('div')
  uploadMessage.className = 'form-message'
  uploadRow.appendChild(uploadBtn)
  uploadRow.appendChild(uploadMessage)
  fileInput.parentElement?.appendChild(uploadRow)

  uploadBtn.addEventListener('click', async () => {
    uploadMessage.textContent = ''
    const file = fileInput.files?.[0]
    if (!file) {
      uploadMessage.textContent = 'Select an image first.'
      return
    }
    const formData = new FormData()
    formData.append('image', file)
    try {
      const response = await fetch('/api/upload.php', {
        method: 'POST',
        headers: {
          'X-CSRF-Token': csrfToken
        },
        body: formData
      })
      if (!response.ok) {
        throw new Error('Upload failed')
      }
      const data = await response.json()
      if (!data.path) {
        throw new Error('Upload failed')
      }
      urlInput.value = data.path
      updateImagePreview(form, data.path)
      uploadMessage.textContent = 'Image uploaded.'
    } catch (error) {
      uploadMessage.textContent = error instanceof Error ? error.message : 'Upload failed'
    }
  })

  urlInput.addEventListener('input', () => {
    updateImagePreview(form, urlInput.value.trim())
  })
}

function createVariantForm() {
  const form = document.createElement('form')
  form.className = 'form'
  form.dataset.editingId = ''

  const message = document.createElement('div')
  message.className = 'form-message'
  form.appendChild(message)

  variantFields.forEach((field) => {
    form.appendChild(createField('variant', field))
  })

  const actions = document.createElement('div')
  actions.className = 'form-actions'
  const saveBtn = document.createElement('button')
  saveBtn.type = 'submit'
  saveBtn.className = 'primary-btn'
  saveBtn.textContent = 'Save'
  const clearBtn = document.createElement('button')
  clearBtn.type = 'button'
  clearBtn.className = 'ghost-btn'
  clearBtn.textContent = 'Clear'
  clearBtn.addEventListener('click', () => {
    resetVariantForm(form)
  })
  actions.appendChild(saveBtn)
  actions.appendChild(clearBtn)
  form.appendChild(actions)

  form.addEventListener('submit', async (event) => {
    event.preventDefault()
    message.textContent = ''
    const payload = readForm(form, variantFields)
    if (!payload.productId) {
      message.textContent = 'Select a product first.'
      return
    }
    try {
      if (form.dataset.editingId) {
        await updateVariant(form.dataset.editingId, payload)
      } else {
        await createVariant(payload)
      }
      resetVariantForm(form, payload.productId)
      await refreshVariants(payload.productId)
    } catch (error) {
      message.textContent = error instanceof Error ? error.message : 'Save failed'
    }
  })

  return form
}

function resetVariantForm(form, productId = null) {
  form.reset()
  form.dataset.editingId = ''
  const productInput = form.querySelector('[name="productId"]')
  if (productInput) {
    productInput.value = productId || ''
  }
  const message = form.querySelector('.form-message')
  if (message) {
    message.textContent = ''
  }
}

async function fetchVariants(productId) {
  const response = await fetch(`/api/product_variants.php?productId=${encodeURIComponent(productId)}`)
  return handleJsonResponse(response, 'Failed to load variants', true)
}

async function createVariant(payload) {
  const response = await fetch('/api/product_variants.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': csrfToken
    },
    body: JSON.stringify(payload)
  })
  return handleJsonResponse(response, 'Create failed')
}

async function updateVariant(id, payload) {
  const response = await fetch(`/api/product_variants.php?id=${encodeURIComponent(id)}`, {
    method: 'PUT',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': csrfToken
    },
    body: JSON.stringify(payload)
  })
  return handleJsonResponse(response, 'Update failed')
}

async function deleteVariant(id) {
  const response = await fetch(`/api/product_variants.php?id=${encodeURIComponent(id)}`, {
    method: 'DELETE',
    headers: {
      'X-CSRF-Token': csrfToken
    }
  })
  return handleJsonResponse(response, 'Delete failed')
}

function renderVariantsTable(items, table, form) {
  table.innerHTML = ''
  const head = document.createElement('div')
  head.className = 'table-head'
  const gridTemplate = `repeat(${variantColumns.length}, minmax(0, 1fr)) 140px`
  head.style.gridTemplateColumns = gridTemplate
  variantColumns.forEach((column) => {
    const cell = document.createElement('div')
    cell.textContent = column
    head.appendChild(cell)
  })
  const actionsHead = document.createElement('div')
  actionsHead.textContent = 'Actions'
  head.appendChild(actionsHead)
  table.appendChild(head)

  if (!items.length) {
    const empty = document.createElement('div')
    empty.className = 'table-empty'
    empty.textContent = 'No variants yet.'
    table.appendChild(empty)
    return
  }

  items.forEach((item) => {
    const row = document.createElement('div')
    row.className = 'table-row'
    row.style.gridTemplateColumns = gridTemplate
    variantColumns.forEach((column) => {
      const cell = document.createElement('div')
      const value = item[column]
      if (statusColumns.has(column) && value) {
        const pill = document.createElement('span')
        pill.className = `status ${normalizeStatus(value)}`
        pill.textContent = value
        cell.appendChild(pill)
      } else {
        cell.textContent = formatValue(value)
      }
      row.appendChild(cell)
    })

    const actions = document.createElement('div')
    actions.className = 'row-actions'
    const editBtn = document.createElement('button')
    editBtn.type = 'button'
    editBtn.className = 'ghost-btn'
    editBtn.textContent = 'Edit'
    editBtn.addEventListener('click', () => fillForm(form, variantFields, item))
    const deleteBtn = document.createElement('button')
    deleteBtn.type = 'button'
    deleteBtn.className = 'ghost-btn'
    deleteBtn.textContent = 'Delete'
    deleteBtn.addEventListener('click', async () => {
      await deleteVariant(item.id)
      await refreshVariants(item.productId)
    })
    actions.appendChild(editBtn)
    actions.appendChild(deleteBtn)
    row.appendChild(actions)
    table.appendChild(row)
  })
}

async function refreshVariants(productId) {
  const entry = state.products?.extras?.variants
  if (!entry) {
    return
  }
  if (!productId) {
    entry.table.innerHTML = '<div class="table-empty">Select a product to manage variants.</div>'
    resetVariantForm(entry.form)
    setFormEnabled(entry.form, false)
    return
  }
  setFormEnabled(entry.form, true)
  const productInput = entry.form.querySelector('[name="productId"]')
  if (productInput) {
    productInput.value = productId
  }
  const items = await fetchVariants(productId)
  entry.items = items
  renderVariantsTable(items, entry.table, entry.form)
}

async function fetchAssociations(productId) {
  const response = await fetch(`/api/product_associations.php?productId=${encodeURIComponent(productId)}`)
  return handleJsonResponse(response, 'Failed to load associations')
}

async function saveAssociations(productId, relatedProductIds) {
  const response = await fetch('/api/product_associations.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': csrfToken
    },
    body: JSON.stringify({ productId, relatedProductIds })
  })
  return handleJsonResponse(response, 'Save failed')
}

function renderAssociationsList(products, selectedIds, list) {
  list.innerHTML = ''
  if (!products.length) {
    const empty = document.createElement('div')
    empty.className = 'table-empty'
    empty.textContent = 'No other products available.'
    list.appendChild(empty)
    return
  }
  products.forEach((product) => {
    const label = document.createElement('label')
    label.className = 'association-item'
    const checkbox = document.createElement('input')
    checkbox.type = 'checkbox'
    checkbox.value = product.id
    checkbox.checked = selectedIds.includes(product.id)
    const text = document.createElement('span')
    text.textContent = `${product.name || 'Product'} (${product.sku || product.id})`
    label.appendChild(checkbox)
    label.appendChild(text)
    list.appendChild(label)
  })
}

async function refreshAssociations(productId) {
  const entry = state.products?.extras?.associations
  if (!entry) {
    return
  }
  if (!productId) {
    entry.message.textContent = 'Select a product to manage associations.'
    entry.list.innerHTML = ''
    entry.saveBtn.disabled = true
    return
  }
  entry.saveBtn.disabled = false
  entry.message.textContent = ''
  const response = await fetchAssociations(productId)
  const selectedIds = response.relatedProductIds || []
  const products = (state.products?.items || []).filter((item) => item.id !== productId)
  renderAssociationsList(products, selectedIds, entry.list)
}

function createProductExtras() {
  const container = document.createElement('div')
  container.className = 'product-extras'

  const selection = document.createElement('div')
  selection.className = 'product-selection'
  selection.textContent = 'Select a product to manage variants and associations.'
  container.appendChild(selection)

  const variantsPanel = document.createElement('div')
  variantsPanel.className = 'product-subpanel'
  variantsPanel.innerHTML = `
    <div class="subpanel-header">
      <h3>Product Variations</h3>
      <p>Create variations tied to the selected product.</p>
    </div>
  `
  const variantsBody = document.createElement('div')
  variantsBody.className = 'panel-body'
  const variantForm = createVariantForm()
  const variantTable = document.createElement('div')
  variantTable.className = 'table'
  variantsBody.appendChild(variantForm)
  variantsBody.appendChild(variantTable)
  variantsPanel.appendChild(variantsBody)

  const assocPanel = document.createElement('div')
  assocPanel.className = 'product-subpanel'
  assocPanel.innerHTML = `
    <div class="subpanel-header">
      <h3>Associated Products</h3>
      <p>Pick products that should be shown as related.</p>
    </div>
  `
  const assocBody = document.createElement('div')
  assocBody.className = 'panel-body'
  const assocMessage = document.createElement('div')
  assocMessage.className = 'form-message'
  const assocList = document.createElement('div')
  assocList.className = 'association-list'
  const assocActions = document.createElement('div')
  assocActions.className = 'form-actions'
  const assocSaveBtn = document.createElement('button')
  assocSaveBtn.type = 'button'
  assocSaveBtn.className = 'primary-btn'
  assocSaveBtn.textContent = 'Save Associations'
  assocActions.appendChild(assocSaveBtn)
  assocBody.appendChild(assocMessage)
  assocBody.appendChild(assocList)
  assocBody.appendChild(assocActions)
  assocPanel.appendChild(assocBody)

  assocSaveBtn.addEventListener('click', async () => {
    const selectedProduct = state.products?.selectedProduct
    if (!selectedProduct) {
      assocMessage.textContent = 'Select a product first.'
      return
    }
    const selectedIds = Array.from(assocList.querySelectorAll('input[type="checkbox"]:checked')).map(
      (input) => input.value
    )
    try {
      await saveAssociations(selectedProduct.id, selectedIds)
      assocMessage.textContent = 'Associations saved.'
    } catch (error) {
      assocMessage.textContent = error instanceof Error ? error.message : 'Save failed'
    }
  })

  container.appendChild(variantsPanel)
  container.appendChild(assocPanel)

  return {
    container,
    selection,
    variants: { form: variantForm, table: variantTable, items: [] },
    associations: { list: assocList, message: assocMessage, saveBtn: assocSaveBtn }
  }
}

function setSelectedProduct(product) {
  const entry = state.products
  if (!entry || !entry.extras) {
    return
  }
  entry.selectedProduct = product
  const productId = product?.id || ''
  entry.extras.selection.textContent = productId
    ? `Managing: ${product.name || productId}`
    : 'Select a product to manage variants and associations.'
  const urlInput = entry.form?.querySelector('[name="imageUrl"]')
  if (urlInput) {
    updateImagePreview(entry.form, urlInput.value.trim())
  }
  refreshVariants(productId)
  refreshAssociations(productId)
}

function clearSelectedProduct() {
  const entry = state.products
  if (!entry || !entry.extras) {
    return
  }
  entry.selectedProduct = null
  entry.extras.selection.textContent = 'Select a product to manage variants and associations.'
  updateImagePreview(entry.form, '')
  refreshVariants('')
  refreshAssociations('')
}

function buildPanel(resource, container) {
  const body = document.createElement('div')
  body.className = 'panel-body'
  container.appendChild(body)

  const form = createForm(resource)
  const table = createTable(resource)
  body.appendChild(form)
  body.appendChild(table)

  state[resource.id] = { items: [], form, table }
  return { form, table }
}

async function refreshResource(resource) {
  const entry = state[resource.id]
  if (!entry) {
    return
  }
  const items = await fetchItems(resource)
  entry.items = items
  renderTable(resource, entry.table, items, entry.form)
  if (resource.id === 'products') {
    const selectedId = state.products?.selectedProduct?.id
    if (selectedId) {
      const updated = items.find((item) => item.id === selectedId)
      if (updated) {
        setSelectedProduct(updated)
      } else {
        clearSelectedProduct()
      }
    }
  }
  updateMetrics()
}

function updateMetrics() {
  if (!metrics) {
    return
  }
  const productCount = (state.products?.items || []).length
  const orderCount = (state.orders?.items || []).length
  const customersCount = (state.customers?.items || []).length
  const revenue = (state.orders?.items || []).reduce((sum, item) => {
    const value = Number(item.total || 0)
    return sum + (Number.isNaN(value) ? 0 : value)
  }, 0)

  metrics.innerHTML = ''
  const cards = [
    { label: 'Products', value: productCount },
    { label: 'Orders', value: orderCount },
    { label: 'Revenue', value: `$${revenue.toFixed(2)}` },
    { label: 'Customers', value: customersCount }
  ]
  cards.forEach((card) => {
    const panel = document.createElement('div')
    panel.className = 'metric-card'
    panel.innerHTML = `
      <div class="metric-label">${card.label}</div>
      <div class="metric-value">${card.value}</div>
    `
    metrics.appendChild(panel)
  })
}

function buildNav() {
  const dashboard = document.createElement('a')
  dashboard.href = '#dashboard'
  dashboard.innerHTML = '<span class="nav-dot"></span>Dashboard'
  nav.appendChild(dashboard)

  const productsLink = document.createElement('a')
  productsLink.href = '#products'
  productsLink.innerHTML = '<span class="nav-dot"></span>Products'
  nav.appendChild(productsLink)

  resources.forEach((resource) => {
    const link = document.createElement('a')
    link.href = `#${resource.id}`
    link.innerHTML = `<span class="nav-dot"></span>${resource.title}`
    nav.appendChild(link)
  })
}

function buildResourceSections() {
  resources.forEach((resource) => {
    const section = document.createElement('section')
    section.id = resource.id
    section.className = 'panel'
    section.innerHTML = `
      <div class="panel-header">
        <div>
          <div class="eyebrow">${resource.title}</div>
          <h2>${resource.title}</h2>
        </div>
        <div class="panel-actions">
          <button class="ghost-btn" data-refresh="${resource.id}">Refresh</button>
        </div>
      </div>
    `
    stack.appendChild(section)
    buildPanel(resource, section)
  })
}

function wireRefreshButtons() {
  document.querySelectorAll('[data-refresh]').forEach((button) => {
    button.addEventListener('click', async () => {
      const id = button.getAttribute('data-refresh')
      if (!id) {
        return
      }
      const resource = id === 'products' ? productsResource : resources.find((entry) => entry.id === id)
      if (!resource) {
        return
      }
      await refreshResource(resource)
    })
  })
}

function initProducts() {
  if (!productsPanel) {
    return
  }
  const body = document.createElement('div')
  body.className = 'panel-body'
  productsPanel.appendChild(body)
  const form = createForm(productsResource)
  const table = createTable(productsResource)
  body.appendChild(form)
  body.appendChild(table)
  const extras = createProductExtras()
  body.appendChild(extras.container)
  state.products = { items: [], form, table, extras, selectedProduct: null }
  wireProductImageUpload(form)

  productsSearch?.addEventListener('input', () => {
    const query = productsSearch.value.trim().toLowerCase()
    const items = state.products.items || []
    const filtered = items.filter((item) => {
      const haystack = `${item.name || ''} ${item.sku || ''} ${item.category || ''}`.toLowerCase()
      return haystack.includes(query)
    })
    renderTable(productsResource, table, filtered, form)
  })
}

async function init() {
  buildNav()
  buildResourceSections()
  initProducts()
  wireRefreshButtons()

  const loadTargets = [productsResource, ...resources]
  for (const resource of loadTargets) {
    try {
      await refreshResource(resource)
    } catch (error) {
      const entry = state[resource.id]
      if (entry?.table) {
        entry.table.textContent = 'Unable to load data.'
      }
    }
  }
}

init()
