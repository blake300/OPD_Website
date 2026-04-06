function escapeHtml(str) {
  if (str == null) return ''
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;')
}

const productStatusOptions = [
  { value: 'active', label: 'Active' },
  { value: 'inactive', label: 'Inactive' }
]

const orderStatusOptions = [
  { value: 'New', label: 'New' },
  { value: 'Processing', label: 'Processing' },
  { value: 'Cancelled', label: 'Cancelled' }
]

function readProductCategoryOptions() {
  const fallback = [
    'AutoBailer Artifical Lift',
    'Parts',
    'Tools',
    'Services',
    'Supplies',
    'Used Equipment',
    'Hidden',
    'Hidden A',
    'Hidden B',
    'Hidden C'
  ]
  const meta = document.querySelector('meta[name="product-categories"]')
  if (!meta) {
    return fallback
  }
  try {
    const parsed = JSON.parse(meta.getAttribute('content') || '[]')
    if (!Array.isArray(parsed) || !parsed.length) {
      return fallback
    }
    return parsed
      .map((value) => String(value || '').trim())
      .filter((value, index, arr) => value !== '' && arr.indexOf(value) === index)
  } catch (_error) {
    return fallback
  }
}

const productCategoryOptions = readProductCategoryOptions()

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
      options: productCategoryOptions
    },
    { name: 'status', label: 'Status', type: 'select', options: productStatusOptions },
    { name: 'price', label: 'Price' },
    { name: 'inventory', label: 'Inventory' },
    { name: 'invStockTo', label: 'Inv Stk To' },
    { name: 'invMin', label: 'Inv Min' }
  ],
  columns: [
    'name',
    'sku',
    'category',
    'status',
    'price',
    'inventory',
    'invStockTo',
    'invMin'
  ]
}

const productTableColumns = [
  { key: 'name', label: 'Name', editable: true },
  { key: 'sku', label: 'SKU', editable: true },
  { key: 'category', label: 'Category', editable: true, type: 'select' },
  { key: 'status', label: 'Status', editable: true, type: 'select', options: productStatusOptions },
  { key: 'posNum', label: 'Pos', editable: true, type: 'number', step: '1', readonly: true },
  { key: 'price', label: 'Price', editable: true, type: 'number', step: '0.01' },
  { key: 'inventory', label: 'Inv', editable: true, type: 'number', step: '1' },
  { key: 'invStockTo', label: 'Inv Stk To', editable: true, type: 'number', step: '1' },
  { key: 'invMin', label: 'Inv Min', editable: true, type: 'number', step: '1' },
  { key: 'order', label: 'Order', editable: false }
]

const productDetailFields = [
  { key: 'service', label: 'Service', type: 'checkbox' },
  { key: 'featured', label: 'Featured', type: 'checkbox' },
  { key: 'largeDelivery', label: 'Large Delivery', type: 'checkbox' },
  { key: 'daysOut', label: 'Days Out', type: 'number', step: '1' },
  { key: 'shortDescription', label: 'Short Description', type: 'textarea' },
  { key: 'longDescription', label: 'Long Description', type: 'textarea' },
  { key: 'wgt', label: 'Wgt', type: 'number', step: '0.01' },
  { key: 'lng', label: 'Lng', type: 'number', step: '0.01' },
  { key: 'wdth', label: 'Wdth', type: 'number', step: '0.01' },
  { key: 'hght', label: 'Hght', type: 'number', step: '0.01' },
  { key: 'tags', label: 'Tags' },
  { key: 'vnName', label: 'vn_Name' },
  { key: 'vnContact', label: 'Vn Contact' },
  { key: 'vnPrice', label: 'Vn Price', type: 'number', step: '0.01' },
  { key: 'compName', label: 'Comp_Name' },
  { key: 'compPrice', label: 'Comp_Price', type: 'number', step: '0.01' },
  { key: 'shelfNum', label: 'Shelf_Num' },
  { key: 'estFreight', label: 'Est Freight', type: 'number', step: '0.01' }
]

const productEditableKeys = [
  ...productTableColumns.filter((column) => column.editable).map((column) => column.key),
  ...productDetailFields.map((field) => field.key)
]

const resources = [
  {
    id: 'orders',
    title: 'Orders',
    endpoint: '/api/orders.php',
    exportHref: '/api/orders.php?export=excel',
    fields: [
      { name: 'number', label: 'Order #', required: true },
      { name: 'customerName', label: 'Customer', required: true },
      { name: 'status', label: 'Status', type: 'select', options: orderStatusOptions },
      { name: 'paymentStatus', label: 'Payment' },
      { name: 'fulfillmentStatus', label: 'Fulfillment' },
      { name: 'approvalStatus', label: 'Approval Status' },
      { name: 'orderAmount', label: 'Order Amount' },
      { name: 'tax', label: 'Order Tax Amount' },
      { name: 'shipping', label: 'Order Shipping Amount' },
      { name: 'refundAmount', label: 'Order Refund Amount' },
      { name: 'total', label: 'Order Total Amount' },
      {
        name: 'totalAfterRefund',
        label: 'Order Total Amount (Minus Refund)',
        readonly: true
      },
      { name: 'currency', label: 'Currency' },
      { name: 'shippingMethod', label: 'Shipping Method' },
      { name: 'deliveryZone', label: 'Delivery Zone', readonly: true },
      { name: 'deliveryClass', label: 'Delivery Class', readonly: true },
      { name: 'billingFirstName', label: 'First Name (Billing)' },
      { name: 'billingLastName', label: 'Last Name (Billing)' },
      { name: 'billingCompany', label: 'Company (Billing)' },
      { name: 'billingAddress1', label: 'Address 1 (Billing)' },
      { name: 'billingAddress2', label: 'Address 2 (Billing)' },
      { name: 'billingCity', label: 'City (Billing)' },
      { name: 'billingStateCode', label: 'State Code (Billing)' },
      { name: 'billingEmail', label: 'Email (Billing)' },
      { name: 'billingPhone', label: 'Phone (Billing)' },
      { name: 'billingPostcode', label: 'Postcode (Billing)' },
      { name: 'shippingFirstName', label: 'First Name (Shipping)' },
      { name: 'shippingLastName', label: 'Last Name (Shipping)' },
      { name: 'shippingCompany', label: 'Company (Shipping)' },
      { name: 'shippingAddress1', label: 'Address 1 (Shipping)' },
      { name: 'shippingAddress2', label: 'Address 2 (Shipping)' },
      { name: 'shippingCity', label: 'City (Shipping)' },
      { name: 'shippingStateCode', label: 'State Code (Shipping)' },
      { name: 'shippingPhone', label: 'Phone (Shipping)' },
      { name: 'shippingPostcode', label: 'Postcode (Shipping)' },
      { name: 'notes', label: 'Customer Notes', type: 'textarea' },
      { name: 'paymentMethod', label: 'Payment Method', readonly: true },
      { name: 'capturedAt', label: 'Captured At', readonly: true },
      { name: 'carrier', label: 'Carrier', readonly: true },
      { name: 'tracking', label: 'Tracking', readonly: true },
      { name: 'shipStatus', label: 'Ship Status', readonly: true },
      { name: 'shippedAt', label: 'Shipped At', readonly: true },
      { name: 'eta', label: 'ETA', readonly: true },
      { name: 'arrivalDate', label: 'Arrival Date', type: 'textarea', readonly: true },
      { name: 'serviceArrivalDate', label: 'Service Arrival Date', type: 'textarea', readonly: true }
    ],
    columns: [
      { key: 'number', label: 'Order #' },
      { key: 'customerName', label: 'Customer' },
      { key: 'status', label: 'Status' },
      { key: 'paymentStatus', label: 'Payment' },
      { key: 'fulfillmentStatus', label: 'Fulfillment' },
      { key: 'approvalStatus', label: 'Approval' },
      { key: 'orderAmount', label: 'Order Amount' },
      { key: 'tax', label: 'Tax' },
      { key: 'shipping', label: 'Shipping' },
      { key: 'refundAmount', label: 'Refund' },
      { key: 'total', label: 'Total' },
      { key: 'totalAfterRefund', label: 'Total (Minus Refund)' },
      { key: 'serviceArrivalDate', label: 'Service Arrival Date' },
      { key: 'updatedAt', label: 'Updated' }
    ]
  },
  {
    id: 'users',
    title: 'Users and Roles',
    endpoint: '/api/users.php',
    fields: [
      { name: 'firstName', label: 'First Name (User)', required: true },
      { name: 'lastName', label: 'Last Name (User)' },
      { name: 'email', label: 'Email', required: true },
      { name: 'role', label: 'Role' },
      { name: 'status', label: 'Status' },
      { name: 'companyName', label: 'Company (User)' },
      { name: 'cellPhone', label: 'Phone (User)' },
      { name: 'address', label: 'Address Line 1 (User)' },
      { name: 'address2', label: 'Address Line 2 (User)' },
      { name: 'city', label: 'City (User)' },
      { name: 'state', label: 'State (User)' },
      { name: 'zip', label: 'Postcode (User)' },
      { name: 'shippingFirstName', label: 'First Name (Shipping)' },
      { name: 'shippingLastName', label: 'Last Name (Shipping)' },
      { name: 'shippingPhone', label: 'Phone (Shipping)' },
      { name: 'shippingCompany', label: 'Company (Shipping)' },
      { name: 'shippingAddress1', label: 'Address Line 1 (Shipping)' },
      { name: 'shippingAddress2', label: 'Address Line 2 (Shipping)' },
      { name: 'shippingCity', label: 'City (Shipping)' },
      { name: 'shippingState', label: 'State (Shipping)' },
      { name: 'shippingPostcode', label: 'Postcode (Shipping)' },
      { name: 'allowInvoice', label: 'Allow Invoice', type: 'checkbox' },
      { name: 'bioNotes', label: 'Biographical Notes', type: 'textarea' },
      { name: 'password', label: 'Password', type: 'password' },
      { name: 'lastLogin', label: 'Last Login', readonly: true }
    ],
    columns: ['firstName', 'lastName', 'email', 'role', 'status', 'allowInvoice', 'lastLogin']
  },
  {
    id: 'settings',
    title: 'System Settings',
    endpoint: '/api/system_settings.php',
    disableDelete: true,
    fields: [
      { name: 'clientInviteSms', label: 'Client Invite SMS', type: 'textarea', required: true },
      { name: 'vendorInviteSms', label: 'Vendor Invite SMS', type: 'textarea', required: true },
      { name: 'autoApproveHelpText', label: 'Auto Approve Help Text', type: 'textarea' },
      { name: 'autoApproveTime', label: 'Auto Approve Time (minutes)' },
      { name: 'vendorLimitText', label: 'Vendor Limit Text', type: 'textarea' },
      { name: 'deliverySmallZone1', label: 'Small Delivery Zone 1 Cost' },
      { name: 'deliverySmallZone2', label: 'Small Delivery Zone 2 Cost' },
      { name: 'deliverySmallZone3', label: 'Small Delivery Zone 3 Cost' },
      { name: 'deliveryLargeZone1', label: 'Large Delivery Zone 1 Cost' },
      { name: 'deliveryLargeZone2', label: 'Large Delivery Zone 2 Cost' },
      { name: 'deliveryLargeZone3', label: 'Large Delivery Zone 3 Cost' },
      { name: 'myEquipmentText', label: 'My Equipment Text', type: 'textarea' }
    ],
    columns: [
      { key: 'clientInviteSms', label: 'Client Invite SMS' },
      { key: 'vendorInviteSms', label: 'Vendor Invite SMS' },
      { key: 'autoApproveHelpText', label: 'Auto Approve Help Text' },
      { key: 'autoApproveTime', label: 'Auto Approve Time' },
      { key: 'vendorLimitText', label: 'Vendor Limit Text' },
      { key: 'deliverySmallZone1', label: 'Small Zone 1' },
      { key: 'deliverySmallZone2', label: 'Small Zone 2' },
      { key: 'deliverySmallZone3', label: 'Small Zone 3' },
      { key: 'deliveryLargeZone1', label: 'Large Zone 1' },
      { key: 'deliveryLargeZone2', label: 'Large Zone 2' },
      { key: 'deliveryLargeZone3', label: 'Large Zone 3' },
      { key: 'myEquipmentText', label: 'My Equipment Text' },
      { key: 'updatedAt', label: 'Updated' }
    ]
  },
  {
    id: 'shipping',
    title: 'Shipping',
    endpoint: '/api/shipping_settings.php',
    disableDelete: true,
    fields: [
      { name: 'shippingZone1States', label: 'Zone 1 States (comma separated)', type: 'textarea' },
      { name: 'shippingZone1Flat', label: 'Zone 1 Flat Rate' },
      { name: 'shippingZone1PerLb', label: 'Zone 1 Per Lb Rate' },
      { name: 'shippingZone2States', label: 'Zone 2 States (comma separated)', type: 'textarea' },
      { name: 'shippingZone2Flat', label: 'Zone 2 Flat Rate' },
      { name: 'shippingZone2PerLb', label: 'Zone 2 Per Lb Rate' },
      { name: 'shippingZone3States', label: 'Zone 3 States (comma separated)', type: 'textarea' },
      { name: 'shippingZone3Flat', label: 'Zone 3 Flat Rate' },
      { name: 'shippingZone3PerLb', label: 'Zone 3 Per Lb Rate' }
    ],
    columns: [
      { key: 'shippingZone1States', label: 'Zone 1 States' },
      { key: 'shippingZone1Flat', label: 'Zone 1 Flat' },
      { key: 'shippingZone1PerLb', label: 'Zone 1 Per Lb' },
      { key: 'shippingZone2States', label: 'Zone 2 States' },
      { key: 'shippingZone2Flat', label: 'Zone 2 Flat' },
      { key: 'shippingZone2PerLb', label: 'Zone 2 Per Lb' },
      { key: 'shippingZone3States', label: 'Zone 3 States' },
      { key: 'shippingZone3Flat', label: 'Zone 3 Flat' },
      { key: 'shippingZone3PerLb', label: 'Zone 3 Per Lb' },
      { key: 'updatedAt', label: 'Updated' }
    ]
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

const resourceGroups = {
  operations: {
    id: 'operations',
    title: 'Operations',
    description: 'Orders, payments, and shipments in one workspace.'
  }
}

const variantTableColumns = [
  { key: 'name', label: 'Vrt Name', editable: true, required: true },
  { key: 'sku', label: 'Vrt Sku', editable: true, required: true },
  { key: 'status', label: 'Vrt Status', editable: true },
  { key: 'posNum', label: 'Pos', editable: true, type: 'number', step: '1', readonly: true },
  { key: 'price', label: 'Vrt Price', editable: true, type: 'number', step: '0.01' },
  { key: 'inventory', label: 'Vrt Inv', editable: true, type: 'number', step: '1' },
  { key: 'invStockTo', label: 'Inv Stk To', editable: true, type: 'number', step: '1' },
  { key: 'invMin', label: 'Inv Min', editable: true, type: 'number', step: '1' },
  { key: 'order', label: 'Order', editable: false }
]

const variantDetailFields = [
  { key: 'largeDelivery', label: 'Large Delivery', type: 'checkbox' },
  { key: 'shortDescription', label: 'Short Description', type: 'textarea' },
  { key: 'longDescription', label: 'Long Description', type: 'textarea' },
  { key: 'wgt', label: 'Wgt', type: 'number', step: '0.01' },
  { key: 'lng', label: 'Lng', type: 'number', step: '0.01' },
  { key: 'wdth', label: 'Wdth', type: 'number', step: '0.01' },
  { key: 'hght', label: 'Hght', type: 'number', step: '0.01' },
  { key: 'tags', label: 'Tags' },
  { key: 'vnName', label: 'vn_Name' },
  { key: 'vnContact', label: 'Vn Contact' },
  { key: 'vnPrice', label: 'Vn Price', type: 'number', step: '0.01' },
  { key: 'compName', label: 'Comp_Name' },
  { key: 'compPrice', label: 'Comp_Price', type: 'number', step: '0.01' },
  { key: 'shelfNum', label: 'Shelf_Num' },
  { key: 'estFreight', label: 'Est Freight', type: 'number', step: '0.01' },
  { key: 'parentName', label: 'Parent Name' }
]

const variantEditableKeys = [
  ...variantTableColumns.filter((column) => column.editable).map((column) => column.key),
  ...variantDetailFields.map((field) => field.key)
]

const nav = document.getElementById('nav')
const stack = document.getElementById('resource-stack')
const metrics = document.getElementById('metrics')
const productsPanel = document.getElementById('products-panel')
const productsSearch = document.getElementById('products-search')
const productsCategoryFilter = document.getElementById('products-category-filter')
const productsAddBtn = document.getElementById('products-add')
const productsSaveBtn = document.getElementById('products-save')
const productsMoveBtn = document.getElementById('products-move')
const dbHealthPanel = document.getElementById('db-health-panel')
const dbHealthRunBtn = document.getElementById('db-health-run')
const taxPanel = document.getElementById('tax-panel')
const taxAddGroupBtn = document.getElementById('tax-add-group')
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''

function showAdminNotification(message, isError) {
  var existing = document.getElementById('admin-notification');
  var el = existing || document.createElement('div');
  el.id = 'admin-notification';
  el.role = 'alert';
  el.textContent = message;
  el.style.cssText = 'position:fixed;bottom:24px;right:24px;padding:14px 20px;border-radius:10px;font-size:14px;font-weight:600;z-index:9999;max-width:400px;box-shadow:0 4px 20px rgba(0,0,0,0.15);transition:opacity 0.3s;' + (isError ? 'background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;' : 'background:#dcfce7;color:#166534;border:1px solid #86efac;');
  if (!existing) document.body.appendChild(el);
  el.style.opacity = '1';
  clearTimeout(el._timeout);
  el._timeout = setTimeout(function() { el.style.opacity = '0'; setTimeout(function() { el.remove(); }, 300); }, 5000);
}

const numericFields = new Set([
  'price',
  'inventory',
  'invStockTo',
  'invMin',
  'posNum',
  'daysOut',
  'orderAmount',
  'total',
  'tax',
  'shipping',
  'refundAmount',
  'totalAfterRefund',
  'ltv',
  'onHand',
  'reserved',
  'available',
  'reorderPoint',
  'usageLimit',
  'used',
  'amount',
  'value',
  'wgt',
  'lng',
  'wdth',
  'hght',
  'vnPrice',
  'compPrice',
  'estFreight'
])
const booleanFields = new Set([
  'service'
])
const statusColumns = new Set([
  'status',
  'paymentStatus',
  'fulfillmentStatus',
  'shipStatus'
])
const dateFields = new Set([
  'startsAt',
  'endsAt',
  'capturedAt',
  'shippedAt',
  'eta',
  'arrivalDate',
  'serviceArrivalDate',
  'lastLogin',
  'lastSync',
  'createdAt',
  'updatedAt'
])
const multilineColumns = new Set([
  'arrivalDate',
  'serviceArrivalDate'
])

// Inline editor support for specific resources (orders, users)
const inlineEditResources = new Set(['orders', 'users'])
function isInlineEditResource(resource) {
  return Boolean(resource && inlineEditResources.has(resource.id))
}
function getInlinePanel(row) {
  const sibling = row.nextSibling
  if (sibling instanceof HTMLElement && sibling.classList.contains('inline-edit-panel')) {
    return sibling
  }
  return null
}
function closeAllInlinePanels(table) {
  if (!table) return
  table.querySelectorAll('.inline-edit-panel').forEach((panel) => panel.remove())
}

function syncSelectValue(input, value) {
  if (!(input instanceof HTMLSelectElement)) {
    return
  }
  input.querySelectorAll('option[data-dynamic-option="true"]').forEach((option) => option.remove())
  const normalizedValue = value === null || value === undefined ? '' : String(value)
  if (normalizedValue === '') {
    return
  }
  const hasOption = Array.from(input.options).some((option) => option.value === normalizedValue)
  if (hasOption) {
    return
  }
  const dynamicOption = document.createElement('option')
  dynamicOption.value = normalizedValue
  dynamicOption.textContent = normalizedValue
  dynamicOption.dataset.dynamicOption = 'true'
  input.appendChild(dynamicOption)
}

function setFieldInputValue(input, field, value) {
  if (!input) {
    return
  }
  if (field.type === 'checkbox') {
    input.checked = value === true || value === 1 || value === '1' || value === 'true'
    return
  }
  syncSelectValue(input, value)
  input.value = value === null || value === undefined ? '' : String(value)
}

function createOrderItemsSection(orderId) {
  const section = document.createElement('div')
  section.className = 'order-items-section'
  section.innerHTML = '<div class="order-items-loading">Loading order items...</div>'

  fetch(`/api/orders.php?items=${encodeURIComponent(orderId)}`)
    .then((r) => r.json())
    .then((data) => {
      const items = data.items || []
      if (!items.length) {
        section.innerHTML = '<div class="order-items-empty">No line items for this order.</div>'
        return
      }
      let totalQty = 0
      let totalAmount = 0
      items.forEach((item) => {
        totalQty += item.quantity || 0
        totalAmount += item.total || 0
      })
      let html = '<div class="order-items-header"><span>Order Items</span><span>' + items.length + ' item' + (items.length === 1 ? '' : 's') + ' &middot; ' + totalQty + ' units &middot; $' + totalAmount.toFixed(2) + '</span></div>'
      html += '<table class="order-items-table"><thead><tr><th>Product</th><th>SKU</th><th>Qty</th><th>Price</th><th>Total</th><th>Arrival</th></tr></thead><tbody>'
      items.forEach((item) => {
        const productName = escapeHtml(item.productName || '')
        const variantName = escapeHtml(item.variantName || '')
        const displayName = variantName && productName ? productName + ' — ' + variantName : escapeHtml(item.name || 'Unknown')
        const sku = escapeHtml(item.sku || '')
        const qty = item.quantity || 0
        const price = (item.price || 0).toFixed(2)
        const total = (item.total || 0).toFixed(2)
        const arrival = item.arrivalDate ? escapeHtml(String(item.arrivalDate)) : '—'
        html += `<tr><td title="${displayName}">${displayName}</td><td title="${sku}">${sku}</td><td title="${qty}">${qty}</td><td title="$${price}">$${price}</td><td title="$${total}">$${total}</td><td title="${arrival}">${arrival}</td></tr>`
      })
      html += '</tbody></table>'
      section.innerHTML = html
    })
    .catch(() => {
      section.innerHTML = '<div class="order-items-empty">Failed to load order items.</div>'
    })

  return section
}

const vendorRelationFields = [
  { key: 'id', label: 'ID' },
  { key: 'userId', label: 'User ID' },
  { key: 'name', label: 'Name' },
  { key: 'contact', label: 'Contact' },
  { key: 'email', label: 'Email' },
  { key: 'phone', label: 'Phone' },
  { key: 'status', label: 'Status' },
  { key: 'linkedUserId', label: 'Linked User ID' },
  { key: 'purchaseLimitOrder', label: 'Per Order Limit' },
  { key: 'purchaseLimitDay', label: 'Per Day Limit' },
  { key: 'purchaseLimitMonth', label: 'Per Month Limit' },
  { key: 'monthCumulative', label: 'Mn Cumulative', type: 'currency' },
  { key: 'limitNone', label: 'No Limit', type: 'boolean' },
  { key: 'autoApprove', label: 'Auto Approve', type: 'boolean' },
  { key: 'paymentMethodId', label: 'Payment Method ID' },
  { key: 'smsConsent', label: 'SMS Consent', type: 'boolean' },
  { key: 'createdAt', label: 'Created' },
  { key: 'updatedAt', label: 'Updated' }
]

const clientRelationFields = [
  { key: 'id', label: 'ID' },
  { key: 'userId', label: 'User ID' },
  { key: 'name', label: 'Name' },
  { key: 'email', label: 'Email' },
  { key: 'phone', label: 'Phone' },
  { key: 'status', label: 'Status' },
  { key: 'monthCumulative', label: 'Mn Cumulative', type: 'currency' },
  { key: 'linkedUserId', label: 'Linked User ID' },
  { key: 'notes', label: 'Notes' },
  { key: 'createdAt', label: 'Created' },
  { key: 'updatedAt', label: 'Updated' }
]

function formatRelationValue(value, type) {
  if (type === 'boolean') {
    if (value === null || value === undefined || value === '') {
      return '—'
    }
    const yes = value === true || value === 1 || value === '1' || value === 'true'
    return yes ? 'Yes' : 'No'
  }
  if (type === 'currency') {
    if (value === null || value === undefined || value === '') {
      return '—'
    }
    const num = Number(value)
    if (Number.isNaN(num)) {
      return String(value)
    }
    return `$${num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
  }
  if (value === null || value === undefined || value === '') {
    return '—'
  }
  return String(value)
}

function buildRelationCard(title, item, fields) {
  const card = document.createElement('div')
  card.className = 'relation-card'
  const heading = document.createElement('div')
  heading.className = 'relation-title'
  heading.textContent = title
  card.appendChild(heading)

  const grid = document.createElement('div')
  grid.className = 'relation-grid'
  fields.forEach((field) => {
    const value = formatRelationValue(item[field.key], field.type)
    const cell = document.createElement('div')
    cell.innerHTML = `<span class="relation-label">${escapeHtml(field.label)}</span><span class="relation-value">${escapeHtml(value)}</span>`
    grid.appendChild(cell)
  })
  card.appendChild(grid)
  return card
}

function renderRelationList(body, summary, label, items, fields) {
  body.innerHTML = ''
  const count = Array.isArray(items) ? items.length : 0
  summary.textContent = `${label} (${count})`
  if (!count) {
    const empty = document.createElement('div')
    empty.className = 'relation-empty'
    empty.textContent = `No ${label.toLowerCase()} found.`
    body.appendChild(empty)
    return
  }
  items.forEach((item) => {
    const fallback = item.id ? `${label.slice(0, -1)} ${item.id}` : label.slice(0, -1)
    const title =
      (item.contact || item.name || item.email || item.id || fallback)
    body.appendChild(buildRelationCard(title, item, fields))
  })
}

function createRelationDetails(label) {
  const details = document.createElement('details')
  details.className = 'relation-details'
  const summary = document.createElement('summary')
  summary.textContent = `${label} (0)`
  const body = document.createElement('div')
  body.className = 'relation-body'
  details.appendChild(summary)
  details.appendChild(body)
  return { details, summary, body }
}

function createUserRelationsPanel(userId) {
  const wrap = document.createElement('div')
  wrap.className = 'user-relations'

  const vendorSection = createRelationDetails('Vendors')
  const clientSection = createRelationDetails('Clients')

  const vendorLoading = document.createElement('div')
  vendorLoading.className = 'relation-loading'
  vendorLoading.textContent = 'Loading vendors...'
  vendorSection.body.appendChild(vendorLoading)

  const clientLoading = document.createElement('div')
  clientLoading.className = 'relation-loading'
  clientLoading.textContent = 'Loading clients...'
  clientSection.body.appendChild(clientLoading)

  wrap.appendChild(vendorSection.details)
  wrap.appendChild(clientSection.details)

  fetch(`/api/user_relations.php?userId=${encodeURIComponent(userId)}`)
    .then((response) => handleJsonResponse(response, 'Failed to load user relations'))
    .then((data) => {
      renderRelationList(vendorSection.body, vendorSection.summary, 'Vendors', data.vendors || [], vendorRelationFields)
      renderRelationList(clientSection.body, clientSection.summary, 'Clients', data.clients || [], clientRelationFields)
    })
    .catch((error) => {
      const message = error instanceof Error ? error.message : 'Failed to load relations'
      vendorSection.body.innerHTML = `<div class="relation-error">${escapeHtml(message)}</div>`
      clientSection.body.innerHTML = `<div class="relation-error">${escapeHtml(message)}</div>`
    })

  return wrap
}

function createInlineEditPanel(resource, item, row, table) {
  const panel = document.createElement('div')
  panel.className = 'inline-edit-panel'
  panel.dataset.rowId = String(item.id || '')

  // Show order line items and invoice button at the top for orders
  if (resource.id === 'orders' && item.id) {
    panel.appendChild(createOrderItemsSection(item.id))
    var invoiceWrap = document.createElement('div')
    invoiceWrap.className = 'order-invoice-section'
    invoiceWrap.style.cssText = 'margin:8px 0 12px;'
    fetch('/api/invoices.php?orderId=' + encodeURIComponent(item.id))
      .then(function (r) { return r.json().catch(function () { return {} }) })
      .then(function (data) {
        var invoices = data.items || []
        if (invoices.length > 0) {
          var inv = invoices[0]
          var link = document.createElement('a')
          link.href = '/api/invoices.php?download=1&id=' + encodeURIComponent(inv.id)
          link.target = '_blank'
          link.className = 'ghost-btn'
          link.style.cssText = 'display:inline-flex;align-items:center;gap:6px;font-size:13px;'
          link.textContent = 'View Invoice PDF (' + escapeHtml(inv.invoiceNumber || '') + ')'
          invoiceWrap.appendChild(link)
        }
      })
      .catch(function () { /* ignore */ })
    panel.appendChild(invoiceWrap)
  }

  const form = document.createElement('form')
  form.className = 'inline-edit-form'

  const message = document.createElement('div')
  message.className = 'form-message'
  form.appendChild(message)

  const grid = document.createElement('div')
  grid.className = 'detail-grid'

  // Build fields using existing factory for consistent inputs
  resource.fields.forEach((field) => {
    const wrap = createField(`${resource.id}-${item.id}`, field)
    // Wider layout for multiline
    if (field.type === 'textarea') {
      wrap.classList.add('full-span')
    }
    // Pre-fill values
    const input = wrap.querySelector('input, select, textarea')
    const value = item[field.name]
    if (input) {
      setFieldInputValue(input, field, value)
      if (field.readonly) {
        input.readOnly = true
        input.classList.add('is-readonly')
      }
    }
    grid.appendChild(wrap)
  })

  form.appendChild(grid)

  if (resource.id === 'users' && item.id) {
    form.appendChild(createUserRelationsPanel(item.id))
  }

  const actions = document.createElement('div')
  actions.className = 'form-actions'
  const saveBtn = document.createElement('button')
  saveBtn.type = 'submit'
  saveBtn.className = 'primary-btn'
  saveBtn.textContent = 'Save'
  const cancelBtn = document.createElement('button')
  cancelBtn.type = 'button'
  cancelBtn.className = 'ghost-btn'
  cancelBtn.textContent = 'Cancel'
  cancelBtn.addEventListener('click', () => {
    panel.remove()
  })
  actions.appendChild(saveBtn)
  actions.appendChild(cancelBtn)
  form.appendChild(actions)

  form.addEventListener('submit', async (event) => {
    event.preventDefault()
    message.textContent = ''
    const payload = readForm(form, resource.fields)
    try {
      await updateItem(resource, item.id, payload)
      message.textContent = 'Saved.'
      message.className = 'form-message is-success'
      // Refresh listing and collapse after save
      await refreshResource(resource)
    } catch (error) {
      message.textContent = error instanceof Error ? error.message : 'Save failed'
      message.className = 'form-message is-error'
    }
  })

  panel.appendChild(form)
  return panel
}
function toggleInlineEditPanel(resource, table, row, item) {
  const existing = getInlinePanel(row)
  if (existing) {
    existing.remove()
    return
  }
  closeAllInlinePanels(table)
  const panel = createInlineEditPanel(resource, item, row, table)
  row.parentNode?.insertBefore(panel, row.nextSibling)
}

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
      const optionValue = typeof option === 'object' ? option.value : option
      const optionLabel = typeof option === 'object' ? (option.label || option.value) : option
      if (optionValue === undefined || optionValue === null || optionValue === '') {
        return
      }
      const opt = document.createElement('option')
      opt.value = optionValue
      opt.textContent = optionLabel
      input.appendChild(opt)
    })
  }
  if (field.required) {
    input.required = true
  }
  if (field.readonly) {
    input.setAttribute('readonly', 'readonly')
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
    if (field.type === 'checkbox') {
      payload[field.name] = input.checked ? 1 : 0
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
    setFieldInputValue(input, field, value)
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
  const columns = resource.columns.map((column) =>
    typeof column === 'string' ? { key: column, label: column } : column
  )
  // Use custom grid template for orders (many columns)
  let gridTemplate
  if (resource.id === 'orders') {
    // Orders has 1 expand col + 25 data columns + Actions = 27 total
    // Expand, Order#, Customer, Status, Payment, Fulfillment, Approval, OrderAmt, Tax, Shipping, Refund, Total,
    // Total-Refund, ShipMethod, DeliveryZone, DeliveryClass, PayMethod, CapturedAt, Carrier, Tracking, ShipStatus, ShippedAt, ETA, ArrivalDate, ServiceArrivalDate, Updated, Actions
    gridTemplate = '40px 85px 110px 75px 75px 85px 80px 85px 60px 65px 65px 65px 95px 95px 80px 80px 95px 90px 65px 85px 80px 85px 55px 90px 90px 90px 110px'
  } else {
    gridTemplate = `repeat(${columns.length}, minmax(0, 1fr)) 140px`
  }
  head.style.gridTemplateColumns = gridTemplate
  if (resource.id === 'orders') {
    const expandHeadCell = document.createElement('div')
    head.appendChild(expandHeadCell)
  }
  columns.forEach((column) => {
    const cell = document.createElement('div')
    cell.textContent = column.label || column.key
    cell.title = column.label || column.key // Tooltip for truncated text
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

  const useInline = isInlineEditResource(resource)

  items.forEach((item) => {
    const row = document.createElement('div')
    row.className = 'table-row'
    row.style.gridTemplateColumns = gridTemplate
    if (resource.id === 'orders') {
      const expandCell = document.createElement('div')
      expandCell.className = 'order-expand-cell'
      const expandBtn = document.createElement('button')
      expandBtn.type = 'button'
      expandBtn.className = 'order-expand-btn'
      expandBtn.title = 'Edit order'
      expandBtn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>'
      expandBtn.addEventListener('click', () => {
        table.querySelectorAll('.order-expand-btn.is-open').forEach((b) => b.classList.remove('is-open'))
        toggleInlineEditPanel(resource, table, row, item)
        const isOpen = !!getInlinePanel(row)
        expandBtn.classList.toggle('is-open', isOpen)
      })
      expandCell.appendChild(expandBtn)
      row.appendChild(expandCell)
    }
    columns.forEach((column) => {
      const cell = document.createElement('div')
      if (multilineColumns.has(column.key)) {
        cell.classList.add('cell-multiline')
      }
      const value = item[column.key]
      if (statusColumns.has(column.key) && value) {
        const pill = document.createElement('span')
        pill.className = `status ${normalizeStatus(value)}`
        pill.textContent = value
        cell.title = String(value)
        cell.appendChild(pill)
      } else {
        const formatted = formatValue(value)
        cell.textContent = formatted
        if (formatted) {
          cell.title = formatted // Tooltip for truncated text
        }
      }
      row.appendChild(cell)
    })

    const actions = document.createElement('div')
    actions.className = 'row-actions'
    const allowDelete = !resource.disableDelete
    // Orders use the leading expand button for editing; other resources use the Edit button here
    if (resource.id !== 'orders') {
      const editBtn = document.createElement('button')
      editBtn.type = 'button'
      editBtn.className = 'ghost-btn'
      editBtn.textContent = 'Edit'
      if (useInline) {
        editBtn.addEventListener('click', () => {
          toggleInlineEditPanel(resource, table, row, item)
        })
      } else {
        editBtn.addEventListener('click', () => {
          fillForm(form, resource.fields, item)
          if (resource.id === 'products') {
            setSelectedProduct(item)
          }
        })
      }
      actions.appendChild(editBtn)
    }
    if (allowDelete) {
      const deleteBtn = document.createElement('button')
      deleteBtn.type = 'button'
      deleteBtn.className = 'ghost-btn'
      deleteBtn.textContent = 'Delete'
      deleteBtn.addEventListener('click', async () => {
        if (!confirm('Delete this item? This cannot be undone.')) return
        await deleteItem(resource, item.id)
        await refreshResource(resource)
        if (resource.id === 'products' && state.products?.selectedProduct?.id === item.id) {
          clearSelectedProduct()
        }
      })
      actions.appendChild(deleteBtn)
    }
    row.appendChild(actions)
    table.appendChild(row)
  })
}

function normalizeProductValue(key, value) {
  if (value === null || value === undefined) {
    return ''
  }
  if (booleanFields.has(key)) {
    const isTrue =
      value === true ||
      value === 1 ||
      value === '1' ||
      value === 'true'
    return isTrue ? '1' : '0'
  }
  if (numericFields.has(key)) {
    if (value === '') {
      return ''
    }
    const numberValue = Number(value)
    return Number.isNaN(numberValue) ? '' : String(numberValue)
  }
  return String(value).trim()
}

function normalizeProductValues(item) {
  const values = {}
  productEditableKeys.forEach((key) => {
    values[key] = normalizeProductValue(key, item?.[key])
  })
  return values
}

function buildProductSearchIndex(values) {
  return `${values.name || ''} ${values.sku || ''} ${values.category || ''}`.toLowerCase()
}

function createProductTable() {
  const table = document.createElement('div')
  table.className = 'table product-table'
  table.dataset.table = 'products'
  table.style.setProperty(
    '--product-grid',
    '32px 74px 135px 90px 150px 104px 46px 90px 52px 52px 52px 36px 70px 70px 110px'
  )
  return table
}

function createNewProductItem(category = '') {
  return {
    id: `new-${Date.now()}-${Math.random().toString(16).slice(2)}`,
    name: '',
    sku: '',
    category,
    imageUrl: '',
    status: 'active',
    service: 0,
    daysOut: '',
    posNum: '',
    price: '',
    inventory: '',
    invStockTo: '',
    invMin: '',
    shortDescription: '',
    longDescription: '',
    wgt: '',
    lng: '',
    wdth: '',
    hght: '',
    tags: '',
    vnName: '',
    vnContact: '',
    vnPrice: '',
    compName: '',
    compPrice: '',
    shelfNum: '',
    isNew: true
  }
}

function insertProductRow(entryRow, table) {
  const row = entryRow.row
  const detailPanel = entryRow.detailPanel
  const head = table.querySelector('.table-head')
  if (head && head.nextSibling) {
    const anchor = head.nextSibling
    table.insertBefore(row, anchor)
    if (detailPanel) {
      table.insertBefore(detailPanel, row.nextSibling)
    }
    return
  }
  table.appendChild(row)
  if (detailPanel) {
    table.appendChild(detailPanel)
  }
}

function removeProductRow(row) {
  const entry = state.products
  if (!entry) {
    return
  }
  getRowPanels(row).forEach((panel) => panel.remove())
  const id = row.dataset.itemId
  entry.dirtyIds.delete(id)
  if (entry.selectedRow === row) {
    entry.selectedRow.classList.remove('is-selected')
    entry.selectedRow = null
    clearSelectedProduct()
  }
  row.remove()
  updateProductSaveButton()
}

function getProductDetailPanel(row) {
  return findRowPanel(row, 'product-detail-panel')
}

function getProductFieldInput(row, key) {
  const direct = row.querySelector(`[data-field="${key}"]`)
  if (direct) {
    return direct
  }
  const detail = getProductDetailPanel(row)
  if (!detail) {
    return null
  }
  return detail.querySelector(`[data-field="${key}"]`)
}

function getProductRowValues(row) {
  const values = {}
  productEditableKeys.forEach((key) => {
    const input = getProductFieldInput(row, key)
    if (input && input.type === 'checkbox') {
      values[key] = input.checked ? '1' : '0'
      return
    }
    values[key] = normalizeProductValue(key, input?.value ?? '')
  })
  return values
}

function updateProductRowIndex(row, values = null) {
  const data = values || getProductRowValues(row)
  row.dataset.category = data.category || ''
  row.dataset.search = buildProductSearchIndex(data)
}

function markProductRowDirty(row, dirty) {
  const entry = state.products
  if (!entry) {
    return
  }
  const id = row.dataset.itemId
  if (dirty) {
    row.classList.add('is-dirty')
    entry.dirtyIds.add(id)
  } else {
    row.classList.remove('is-dirty')
    entry.dirtyIds.delete(id)
  }
  updateProductSaveButton()
}

function updateProductRowDirtyState(row) {
  const original = JSON.parse(row.dataset.original || '{}')
  const current = getProductRowValues(row)
  const dirty = productEditableKeys.some((key) => original[key] !== current[key])
  markProductRowDirty(row, dirty)
}

function clearProductRowError(row) {
  row.classList.remove('is-error')
  row.removeAttribute('title')
}

function markProductRowError(row, message) {
  row.classList.add('is-error')
  row.title = message
}

function applyProductFilters() {
  const entry = state.products
  if (!entry?.table) {
    return
  }
  const query = productsSearch?.value.trim().toLowerCase() || ''
  const category = productsCategoryFilter?.value || ''
  const rows = entry.table.querySelectorAll('.product-row')
  rows.forEach((row) => {
    const matchesQuery = query === '' || (row.dataset.search || '').includes(query)
    const matchesCategory = category === '' || row.dataset.category === category
    const shouldShow = matchesQuery && matchesCategory
    row.style.display = shouldShow ? '' : 'none'
    getRowPanels(row).forEach((panel) => {
      if (!shouldShow) {
        panel.style.display = 'none'
      } else {
        panel.style.display = panel.dataset.collapsed === 'true' ? 'none' : ''
      }
    })
  })
}

function updateProductSaveButton() {
  if (!productsSaveBtn) {
    return
  }
  const dirtyCount = state.products?.dirtyIds?.size || 0
  productsSaveBtn.disabled = dirtyCount === 0
  productsSaveBtn.textContent = dirtyCount > 0 ? `Save Changes (${dirtyCount})` : 'Save Changes'
}

function setProductMoveMode(enabled, category) {
  const entry = state.products
  if (!entry?.table || !productsMoveBtn) {
    return
  }
  entry.moveMode = enabled
  entry.moveCategory = enabled ? category : ''
  productsMoveBtn.textContent = enabled ? 'Done Moving' : 'Move'
  productsMoveBtn.classList.toggle('is-active', enabled)
  entry.table.classList.toggle('is-reorder', enabled)
  entry.table.querySelectorAll('.product-row').forEach((row) => {
    row.draggable = false
    row.classList.toggle('is-movable', enabled)
  })
}

function buildProductPayload(row) {
  const payload = {}
  productEditableKeys.forEach((key) => {
    const input = getProductFieldInput(row, key)
    if (!input) {
      return
    }
    if (input.type === 'checkbox') {
      payload[key] = input.checked ? 1 : 0
      return
    }
    const raw = input.value.trim()
    if (raw === '') {
      payload[key] = null
      return
    }
    if (numericFields.has(key)) {
      const num = Number(raw)
      payload[key] = Number.isNaN(num) ? null : num
      return
    }
    payload[key] = raw
  })
  if (payload.imageUrl === undefined) {
    const entry = state.products
    const current = entry?.items?.find((item) => item.id === row.dataset.itemId)
    if (current && Object.prototype.hasOwnProperty.call(current, 'imageUrl')) {
      payload.imageUrl = current.imageUrl
    }
  }
  return payload
}

function validateProductPayload(payload) {
  if (!payload.name) {
    return 'Name is required.'
  }
  if (!payload.sku) {
    return 'SKU is required.'
  }
  if (!payload.category) {
    return 'Category is required.'
  }
  const numericChecks = [
    { key: 'price', label: 'Price' },
    { key: 'inventory', label: 'Inventory' },
    { key: 'invStockTo', label: 'Inv Stk To' },
    { key: 'invMin', label: 'Inv Min' },
    { key: 'daysOut', label: 'Days Out' },
    { key: 'wgt', label: 'Wgt' },
    { key: 'lng', label: 'Lng' },
    { key: 'wdth', label: 'Wdth' },
    { key: 'hght', label: 'Hght' },
    { key: 'vnPrice', label: 'Vn Price' },
    { key: 'compPrice', label: 'Comp Price' }
  ]
  for (const item of numericChecks) {
    const value = payload[item.key]
    if (value !== null && value !== undefined && Number.isNaN(Number(value))) {
      return `${item.label} must be a number.`
    }
  }
  return ''
}

function updateProductRowFromSaved(row, saved) {
  const updatedValues = normalizeProductValues(saved)
  productEditableKeys.forEach((key) => {
    const input = getProductFieldInput(row, key)
    if (!input) {
      return
    }
    const value = saved?.[key]
    if (input.type === 'checkbox') {
      input.checked = value === true || value === 1 || value === '1' || value === 'true'
      return
    }
    input.value = value === null || value === undefined ? '' : String(value)
  })
  row.dataset.original = JSON.stringify(updatedValues)
  updateProductRowIndex(row, updatedValues)
  updateProductOrderCell(row)
  clearProductRowError(row)
  markProductRowDirty(row, false)
  const detailPanel = getProductDetailPanel(row)
  if (detailPanel) {
    detailPanel.dataset.productId = saved.id
    if (detailPanel._imageManager) {
      detailPanel._imageManager.legacyUrl = saved.imageUrl ? String(saved.imageUrl) : ''
      setProductImageManagerEnabled(detailPanel, true)
      loadProductImages(detailPanel, saved.id)
    }
  }
}

function createNewVariantItem(productId) {
  return {
    id: `new-${Date.now()}-${Math.random().toString(16).slice(2)}`,
    productId,
    name: '',
    sku: '',
    status: '',
    posNum: '',
    price: '',
    inventory: '',
    invStockTo: '',
    invMin: '',
    shortDescription: '',
    longDescription: '',
    wgt: '',
    lng: '',
    wdth: '',
    hght: '',
    tags: '',
    vnName: '',
    vnContact: '',
    vnPrice: '',
    compName: '',
    compPrice: '',
    shelfNum: '',
    isNew: true
  }
}

function setVariantMessage(panel, text, type = '') {
  const message = panel.querySelector('.variant-message')
  if (!message) {
    return
  }
  message.textContent = text
  message.className = `form-message variant-message${type ? ` ${type}` : ''}`
}

function setAssocMessage(panel, text, type = '') {
  const message = panel.querySelector('.assoc-message')
  if (!message) {
    return
  }
  message.textContent = text
  message.className = `form-message assoc-message${type ? ` ${type}` : ''}`
}

function calculateOrderValue(inventoryValue, invMinValue, invStockToValue) {
  const hasValue = (value) => value !== null && value !== undefined && value !== ''
  if (!hasValue(inventoryValue) || !hasValue(invMinValue) || !hasValue(invStockToValue)) {
    return ''
  }
  const inventoryNum = Number(inventoryValue)
  const minNum = Number(invMinValue)
  const stockToNum = Number(invStockToValue)
  if (!Number.isFinite(inventoryNum) || !Number.isFinite(minNum) || !Number.isFinite(stockToNum)) {
    return ''
  }
  if (inventoryNum > minNum) {
    return ''
  }
  const orderQty = stockToNum - inventoryNum
  return String(Math.max(orderQty, 0))
}

function updateProductOrderCell(row) {
  const orderCell = row.querySelector('[data-field="order"]')
  if (!orderCell) {
    return
  }
  const inventory = row.querySelector('[data-field="inventory"]')?.value ?? ''
  const invMin = row.querySelector('[data-field="invMin"]')?.value ?? ''
  const invStockTo = row.querySelector('[data-field="invStockTo"]')?.value ?? ''
  const orderValue = calculateOrderValue(inventory, invMin, invStockTo)
  orderCell.textContent = formatValue(orderValue)
}

function updateVariantOrderCell(row) {
  const orderCell = row.querySelector('[data-field="order"]')
  if (!orderCell) {
    return
  }
  const inventory = row.querySelector('[data-field="inventory"]')?.value ?? ''
  const invMin = row.querySelector('[data-field="invMin"]')?.value ?? ''
  const invStockTo = row.querySelector('[data-field="invStockTo"]')?.value ?? ''
  const orderValue = calculateOrderValue(inventory, invMin, invStockTo)
  orderCell.textContent = formatValue(orderValue)
}

function updateToggleButton(button, isOpen) {
  if (!button) {
    return
  }
  button.setAttribute('aria-expanded', isOpen ? 'true' : 'false')
  button.classList.toggle('is-open', isOpen)
}

function setProductImageMessage(panel, text, type = '') {
  const manager = panel._imageManager
  if (!manager?.message) {
    return
  }
  manager.message.textContent = text
  manager.message.className = `form-message image-message${type ? ` ${type}` : ''}`
}

function setProductImageManagerEnabled(panel, enabled) {
  const manager = panel._imageManager
  if (!manager) {
    return
  }
  manager.uploadInput.disabled = !enabled
  manager.uploadBtn.disabled = !enabled
}

function renderProductImageList(panel, images, legacyUrl) {
  const manager = panel._imageManager
  if (!manager?.list) {
    return
  }
  const list = manager.list
  list.innerHTML = ''
  if (!images.length) {
    if (legacyUrl) {
      const legacy = document.createElement('div')
      legacy.className = 'product-image-empty'
      const legacyText = document.createElement('div')
      legacyText.textContent = 'A primary image already exists. Import it to manage thumbnails.'
      const legacyBtn = document.createElement('button')
      legacyBtn.type = 'button'
      legacyBtn.className = 'ghost-btn'
      legacyBtn.textContent = 'Import current image'
      legacyBtn.addEventListener('click', async () => {
        const productId = panel.dataset.productId || ''
        if (!productId) {
          return
        }
        setProductImageMessage(panel, 'Importing image...', 'is-neutral')
        try {
          await createProductImages(productId, [legacyUrl], true)
          panel._imageManager.legacyUrl = ''
          await loadProductImages(panel, productId)
          setProductImageMessage(panel, 'Image imported.', 'is-success')
        } catch (error) {
          setProductImageMessage(panel, error instanceof Error ? error.message : 'Import failed', 'is-error')
        }
      })
      legacy.appendChild(legacyText)
      legacy.appendChild(legacyBtn)
      list.appendChild(legacy)
      return
    }
    const empty = document.createElement('div')
    empty.className = 'product-image-empty'
    empty.textContent = 'No images yet.'
    list.appendChild(empty)
    return
  }
  images.forEach((image) => {
    const card = document.createElement('div')
    card.className = 'product-image-item'
    if (image.isPrimary) {
      card.classList.add('is-primary')
    }
    const img = document.createElement('img')
    img.src = image.url || ''
    img.alt = image.isPrimary ? 'Primary product image' : 'Product image'
    const actions = document.createElement('div')
    actions.className = 'product-image-actions'
    if (image.isPrimary) {
      const badge = document.createElement('span')
      badge.className = 'product-image-badge'
      badge.textContent = 'Primary'
      actions.appendChild(badge)
    } else {
      const primaryBtn = document.createElement('button')
      primaryBtn.type = 'button'
      primaryBtn.className = 'ghost-btn'
      primaryBtn.textContent = 'Make primary'
      primaryBtn.addEventListener('click', async () => {
        const productId = panel.dataset.productId || ''
        if (!productId) {
          return
        }
        setProductImageMessage(panel, 'Updating primary image...', 'is-neutral')
        try {
          await setPrimaryProductImage(productId, image.id)
          await loadProductImages(panel, productId)
          setProductImageMessage(panel, 'Primary image updated.', 'is-success')
        } catch (error) {
          setProductImageMessage(panel, error instanceof Error ? error.message : 'Update failed', 'is-error')
        }
      })
      actions.appendChild(primaryBtn)
    }
    const deleteBtn = document.createElement('button')
    deleteBtn.type = 'button'
    deleteBtn.className = 'ghost-btn'
    deleteBtn.textContent = 'Remove'
    deleteBtn.addEventListener('click', async () => {
      const productId = panel.dataset.productId || ''
      if (!productId) {
        return
      }
      deleteBtn.disabled = true
      setProductImageMessage(panel, 'Removing image...', 'is-neutral')
      try {
        await deleteProductImage(image.id)
        await loadProductImages(panel, productId)
        setProductImageMessage(panel, 'Image removed.', 'is-success')
      } catch (error) {
        setProductImageMessage(panel, error instanceof Error ? error.message : 'Remove failed', 'is-error')
      } finally {
        deleteBtn.disabled = false
      }
    })
    actions.appendChild(deleteBtn)
    card.appendChild(img)
    card.appendChild(actions)
    list.appendChild(card)
  })
}

async function loadProductImages(panel, productId) {
  if (!productId || productId.startsWith('new-')) {
    return
  }
    const legacyUrl = panel._imageManager?.legacyUrl || ''
    setProductImageMessage(panel, 'Loading images...', 'is-neutral')
    try {
      const items = await fetchProductImages(productId)
      let primaryUrl = ''
      if (items.length) {
        const primary = items.find((item) => item.isPrimary) || items[0]
        primaryUrl = primary?.url || ''
      }
      if (!primaryUrl && legacyUrl) {
        primaryUrl = legacyUrl
      }
      const entry = state.products
      if (entry?.items?.length) {
        const index = entry.items.findIndex((item) => item.id === productId)
        if (index !== -1) {
          entry.items[index].imageUrl = primaryUrl || null
        }
      }
      renderProductImageList(panel, items, legacyUrl)
      setProductImageMessage(panel, '', '')
    } catch (error) {
      setProductImageMessage(panel, error instanceof Error ? error.message : 'Failed to load images', 'is-error')
    }
}

async function handleProductImageUpload(panel) {
  const manager = panel._imageManager
  const productId = panel.dataset.productId || ''
  if (!manager || !productId || productId.startsWith('new-')) {
    return
  }
  const files = manager.uploadInput.files ? Array.from(manager.uploadInput.files) : []
  if (!files.length) {
    setProductImageMessage(panel, 'Select images first.', 'is-error')
    return
  }
  manager.uploadBtn.disabled = true
  setProductImageMessage(panel, `Uploading ${files.length} image${files.length === 1 ? '' : 's'}...`, 'is-neutral')
  const uploaded = []
  try {
    for (const file of files) {
      const formData = new FormData()
      formData.append('image', file)
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
      uploaded.push(data.path)
    }
    await createProductImages(productId, uploaded)
    manager.uploadInput.value = ''
    panel._imageManager.legacyUrl = ''
    await loadProductImages(panel, productId)
    setProductImageMessage(panel, 'Images uploaded.', 'is-success')
  } catch (error) {
    setProductImageMessage(panel, error instanceof Error ? error.message : 'Upload failed', 'is-error')
  } finally {
    manager.uploadBtn.disabled = false
  }
}

function createProductImageManager(item, row, panel) {
  const section = document.createElement('div')
  section.className = 'product-image-manager'

  const header = document.createElement('div')
  header.className = 'detail-title'
  header.textContent = 'Images'

  const uploadRow = document.createElement('div')
  uploadRow.className = 'image-upload-row'

  const inputId = `${row.dataset.itemId}-images`
  const uploadLabel = document.createElement('label')
  uploadLabel.htmlFor = inputId
  uploadLabel.textContent = 'Upload images'

  const uploadInput = document.createElement('input')
  uploadInput.type = 'file'
  uploadInput.id = inputId
  uploadInput.accept = 'image/*'
  uploadInput.multiple = true

  const uploadBtn = document.createElement('button')
  uploadBtn.type = 'button'
  uploadBtn.className = 'ghost-btn'
  uploadBtn.textContent = 'Upload'
  uploadBtn.addEventListener('click', () => {
    handleProductImageUpload(panel)
  })

  uploadRow.appendChild(uploadLabel)
  uploadRow.appendChild(uploadInput)
  uploadRow.appendChild(uploadBtn)

  const message = document.createElement('div')
  message.className = 'form-message image-message'

  const list = document.createElement('div')
  list.className = 'product-image-list'

  const layout = document.createElement('div')
  layout.className = 'image-manager-row'
  layout.appendChild(uploadRow)
  layout.appendChild(list)

  section.appendChild(header)
  section.appendChild(layout)
  section.appendChild(message)

  panel._imageManager = {
    uploadInput,
    uploadBtn,
    message,
    list,
    legacyUrl: item.imageUrl ? String(item.imageUrl) : ''
  }

  if (row.dataset.isNew === 'true') {
    setProductImageManagerEnabled(panel, false)
    setProductImageMessage(panel, 'Save this product before uploading images.', 'is-neutral')
  }

  return section
}

function createProductDetailPanel(item, row) {
  const panel = document.createElement('div')
  panel.className = 'product-detail-panel'
  panel.dataset.collapsed = 'true'
  panel.style.display = 'none'
  panel.dataset.productId = item.id || ''

  const imageManager = createProductImageManager(item, row, panel)
  const grid = document.createElement('div')
  grid.className = 'detail-grid'

  productDetailFields.forEach((field) => {
    const wrap = document.createElement('div')
    wrap.className = 'detail-field'
    const label = document.createElement('label')
    const inputId = `${row.dataset.itemId}-${field.key}`
    label.htmlFor = inputId
    label.textContent = field.label
    let input
    if (field.type === 'textarea') {
      input = document.createElement('textarea')
    } else {
      input = document.createElement('input')
      input.type = field.type || 'text'
      if (field.type === 'number') {
        input.step = field.step || '0.01'
      }
    }
    input.id = inputId
    input.dataset.field = field.key
    const value = item[field.key]
    if (field.type === 'checkbox') {
      input.checked = value === true || value === 1 || value === '1' || value === 'true'
    } else {
      input.value = value === null || value === undefined ? '' : String(value)
    }
    if (field.readonly) {
      input.readOnly = true
      input.classList.add('is-readonly')
    }
    input.addEventListener('input', () => {
      updateProductRowDirtyState(row)
      clearProductRowError(row)
    })
    input.addEventListener('change', () => {
      updateProductRowDirtyState(row)
      clearProductRowError(row)
    })
    wrap.appendChild(label)
    wrap.appendChild(input)
    grid.appendChild(wrap)
  })

  panel.appendChild(imageManager)
  panel.appendChild(grid)
  return panel
}

function toggleProductDetailPanel(row, toggleBtn) {
  const panel = getProductDetailPanel(row)
  if (!panel) {
    return
  }
  const collapsed = panel.dataset.collapsed === 'true'
  panel.dataset.collapsed = collapsed ? 'false' : 'true'
  panel.style.display = collapsed ? '' : 'none'
  updateToggleButton(toggleBtn, collapsed)
  if (collapsed) {
    const productId = panel.dataset.productId || row.dataset.itemId || ''
    if (productId) {
      loadProductImages(panel, productId)
    }
  }
}

function getRowPanels(row) {
  const panels = []
  let sibling = row.nextSibling
  while (sibling instanceof HTMLElement && !sibling.classList.contains('product-row')) {
    panels.push(sibling)
    sibling = sibling.nextSibling
  }
  return panels
}

function getProductRowBlock(row) {
  return [row, ...getRowPanels(row)]
}

function moveProductRowBlock(row, targetRow, position) {
  const entry = state.products
  if (!entry?.table) {
    return
  }
  const block = getProductRowBlock(row)
  const targetBlock = getProductRowBlock(targetRow)
  const anchor = position === 'before'
    ? targetRow
    : targetBlock[targetBlock.length - 1].nextSibling
  block.forEach((node) => {
    entry.table.insertBefore(node, anchor)
  })
}

function clearProductDragIndicators() {
  const entry = state.products
  if (!entry?.table) {
    return
  }
  entry.table.querySelectorAll('.product-row').forEach((row) => {
    row.classList.remove('drag-over-top', 'drag-over-bottom')
  })
}

function updateProductPositionsForCategory(category) {
  const entry = state.products
  if (!entry?.table || !category) {
    return
  }
  const rows = Array.from(entry.table.querySelectorAll('.product-row')).filter(
    (row) => row.dataset.category === category
  )
  rows.forEach((row, index) => {
    const input = getProductFieldInput(row, 'posNum')
    if (input) {
      input.value = String(index + 1)
    }
    updateProductRowDirtyState(row)
  })
}

function wireProductRowDrag(row) {
  row.addEventListener('dragstart', (event) => {
    const entry = state.products
    if (!entry?.moveMode) {
      event.preventDefault()
      return
    }
    const category = entry.moveCategory || ''
    if (category && row.dataset.category !== category) {
      event.preventDefault()
      return
    }
    row.classList.add('is-dragging')
    entry.draggingRow = row
    if (event.dataTransfer) {
      event.dataTransfer.effectAllowed = 'move'
      event.dataTransfer.setData('text/plain', row.dataset.itemId || '')
    }
  })

  row.addEventListener('dragover', (event) => {
    const entry = state.products
    if (!entry?.moveMode || !entry.draggingRow || entry.draggingRow === row) {
      return
    }
    const category = entry.moveCategory || ''
    if (category && row.dataset.category !== category) {
      return
    }
    event.preventDefault()
    const rect = row.getBoundingClientRect()
    const isAfter = event.clientY > rect.top + rect.height / 2
    row.classList.toggle('drag-over-top', !isAfter)
    row.classList.toggle('drag-over-bottom', isAfter)
  })

  row.addEventListener('dragleave', () => {
    row.classList.remove('drag-over-top', 'drag-over-bottom')
  })

  row.addEventListener('drop', (event) => {
    const entry = state.products
    if (!entry?.moveMode || !entry.draggingRow || entry.draggingRow === row) {
      return
    }
    const category = entry.moveCategory || ''
    if (category && row.dataset.category !== category) {
      return
    }
    event.preventDefault()
    const rect = row.getBoundingClientRect()
    const isAfter = event.clientY > rect.top + rect.height / 2
    moveProductRowBlock(entry.draggingRow, row, isAfter ? 'after' : 'before')
    clearProductDragIndicators()
    updateProductPositionsForCategory(category)
  })

  row.addEventListener('dragend', () => {
    row.classList.remove('is-dragging')
    clearProductDragIndicators()
    if (state.products) {
      state.products.draggingRow = null
    }
  })
}

function findRowPanel(row, className) {
  return getRowPanels(row).find((panel) => panel.classList.contains(className)) || null
}

function collapseRowPanel(panel, toggleBtn) {
  if (!panel) {
    return
  }
  panel.dataset.collapsed = 'true'
  panel.style.display = 'none'
  updateToggleButton(toggleBtn, false)
}

function getVariantDetailPanel(row) {
  if (row._detailPanel) {
    return row._detailPanel
  }
  const sibling = row.nextSibling
  if (sibling instanceof HTMLElement && sibling.classList.contains('variant-detail-panel')) {
    return sibling
  }
  return null
}

function getVariantRowBlock(row) {
  const panels = []
  let sibling = row.nextSibling
  while (sibling instanceof HTMLElement && !sibling.classList.contains('variant-row')) {
    panels.push(sibling)
    sibling = sibling.nextSibling
  }
  return [row, ...panels]
}

function moveVariantRowBlock(row, targetRow, position) {
  const table = targetRow.parentNode
  if (!table) {
    return
  }
  const block = getVariantRowBlock(row)
  const targetBlock = getVariantRowBlock(targetRow)
  const anchor = position === 'before'
    ? targetRow
    : targetBlock[targetBlock.length - 1].nextSibling
  block.forEach((node) => {
    table.insertBefore(node, anchor)
  })
}

function clearVariantDragIndicators(panel) {
  panel.querySelectorAll('.variant-row').forEach((row) => {
    row.classList.remove('drag-over-top', 'drag-over-bottom')
  })
}

function updateVariantPositions(panel) {
  const rows = Array.from(panel.querySelectorAll('.variant-row'))
  rows.forEach((row, index) => {
    const input = getVariantFieldInput(row, 'posNum')
    if (input) {
      input.value = String(index + 1)
    }
    row.classList.add('is-dirty')
  })
}

function wireVariantRowDrag(row, panel) {
  row.addEventListener('dragstart', (event) => {
    if (!panel._moveMode) {
      event.preventDefault()
      return
    }
    row.classList.add('is-dragging')
    panel._draggingRow = row
    if (event.dataTransfer) {
      event.dataTransfer.effectAllowed = 'move'
      event.dataTransfer.setData('text/plain', row.dataset.variantId || '')
    }
  })

  row.addEventListener('dragover', (event) => {
    if (!panel._moveMode || !panel._draggingRow || panel._draggingRow === row) {
      return
    }
    event.preventDefault()
    const rect = row.getBoundingClientRect()
    const isAfter = event.clientY > rect.top + rect.height / 2
    row.classList.toggle('drag-over-top', !isAfter)
    row.classList.toggle('drag-over-bottom', isAfter)
  })

  row.addEventListener('dragleave', () => {
    row.classList.remove('drag-over-top', 'drag-over-bottom')
  })

  row.addEventListener('drop', (event) => {
    if (!panel._moveMode || !panel._draggingRow || panel._draggingRow === row) {
      return
    }
    event.preventDefault()
    const rect = row.getBoundingClientRect()
    const isAfter = event.clientY > rect.top + rect.height / 2
    moveVariantRowBlock(panel._draggingRow, row, isAfter ? 'after' : 'before')
    clearVariantDragIndicators(panel)
    updateVariantPositions(panel)
  })

  row.addEventListener('dragend', () => {
    row.classList.remove('is-dragging')
    clearVariantDragIndicators(panel)
    panel._draggingRow = null
  })
}

function getVariantFieldInput(row, key) {
  const direct = row.querySelector(`[data-field="${key}"]`)
  if (direct) {
    return direct
  }
  const detail = getVariantDetailPanel(row)
  if (!detail) {
    return null
  }
  return detail.querySelector(`[data-field="${key}"]`)
}

function readVariantRow(row, productId) {
  const payload = { productId }
  variantEditableKeys.forEach((key) => {
    const input = getVariantFieldInput(row, key)
    if (!input) {
      return
    }
    const raw = input.value.trim()
    if (raw === '') {
      payload[key] = null
      return
    }
    if (numericFields.has(key)) {
      const num = Number(raw)
      payload[key] = Number.isNaN(num) ? null : num
      return
    }
    payload[key] = raw
  })
  return payload
}

function validateVariantPayload(payload) {
  if (!payload.productId) {
    return 'Product is required.'
  }
  if (!payload.name) {
    return 'Variant name is required.'
  }
  if (!payload.sku) {
    return 'Variant SKU is required.'
  }
  return ''
}

function createVariantDetailPanel(variant, row) {
  const panel = document.createElement('div')
  panel.className = 'variant-detail-panel'
  panel.dataset.collapsed = 'true'
  panel.style.display = 'none'

  const grid = document.createElement('div')
  grid.className = 'detail-grid'

  variantDetailFields.forEach((field) => {
    const wrap = document.createElement('div')
    wrap.className = 'detail-field'
    const label = document.createElement('label')
    const inputId = `${row.dataset.variantId}-${field.key}`
    label.htmlFor = inputId
    label.textContent = field.label
    let input
    if (field.type === 'textarea') {
      input = document.createElement('textarea')
    } else {
      input = document.createElement('input')
      input.type = field.type || 'text'
      if (field.type === 'number') {
        input.step = field.step || '0.01'
      }
    }
    input.id = inputId
    input.dataset.field = field.key
    const value = variant[field.key]
    input.value = value === null || value === undefined ? '' : String(value)
    input.addEventListener('input', () => {
      row.classList.add('is-dirty')
      row.classList.remove('is-error')
    })
    input.addEventListener('change', () => {
      row.classList.add('is-dirty')
      row.classList.remove('is-error')
    })
    wrap.appendChild(label)
    wrap.appendChild(input)
    grid.appendChild(wrap)
  })

  panel.appendChild(grid)
  return panel
}

function toggleVariantDetailPanel(row, toggleBtn) {
  const panel = getVariantDetailPanel(row)
  if (!panel) {
    return
  }
  const collapsed = panel.dataset.collapsed === 'true'
  panel.dataset.collapsed = collapsed ? 'false' : 'true'
  panel.style.display = collapsed ? '' : 'none'
  updateToggleButton(toggleBtn, collapsed)
}

function buildVariantRow(variant, productId, panel) {
  const row = document.createElement('div')
  row.className = 'variant-row'
  row.dataset.variantId = variant.id
  row.dataset.isNew = variant.isNew ? 'true' : 'false'
  row.dataset.productId = productId

  const dragCell = document.createElement('div')
  dragCell.className = 'variant-cell variant-drag-cell'
  const dragHandle = document.createElement('button')
  dragHandle.type = 'button'
  dragHandle.className = 'drag-handle'
  dragHandle.textContent = '::'
  dragHandle.setAttribute('aria-label', 'Reorder variant')
  dragHandle.dataset.dragHandle = 'true'
  dragHandle.addEventListener('mousedown', () => {
    if (panel._moveMode) {
      dragHandle.draggable = true
      row.draggable = true
    }
  })
  dragHandle.addEventListener('dragstart', (event) => {
    if (panel._moveMode) {
      event.stopPropagation()
      row.classList.add('is-dragging')
      panel._draggingRow = row
      if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move'
        event.dataTransfer.setData('text/plain', row.dataset.variantId || '')
      }
    }
  })
  if (panel._moveMode) {
    row.draggable = true
  }
  dragCell.appendChild(dragHandle)
  row.appendChild(dragCell)

  const expandCell = document.createElement('div')
  expandCell.className = 'variant-cell variant-expand-cell'
  const expandBtn = document.createElement('button')
  expandBtn.type = 'button'
  expandBtn.className = 'ghost-btn expand-btn'
  expandBtn.textContent = 'Expand'
  expandBtn.setAttribute('aria-expanded', 'false')
  expandBtn.addEventListener('click', () => {
    toggleVariantDetailPanel(row, expandBtn)
  })
  expandCell.appendChild(expandBtn)
  row.appendChild(expandCell)

  variantTableColumns.forEach((column) => {
    const cell = document.createElement('div')
    cell.className = 'variant-cell'
    if (column.editable === false) {
      cell.dataset.field = column.key
      if (column.key === 'order') {
        const orderValue = calculateOrderValue(variant.inventory, variant.invMin, variant.invStockTo)
        cell.textContent = formatValue(orderValue)
      } else {
        cell.textContent = formatValue(variant[column.key])
      }
      row.appendChild(cell)
      return
    }
    const input = document.createElement('input')
    input.className = 'variant-input'
    input.dataset.field = column.key
    input.type = column.type === 'number' ? 'number' : 'text'
    if (column.type === 'number') {
      input.step = column.step || '1'
    }
    if (column.readonly) {
      input.readOnly = true
      input.classList.add('is-readonly')
    }
    const value = variant[column.key]
    input.value = value === null || value === undefined ? '' : String(value)
    input.addEventListener('input', () => {
      row.classList.add('is-dirty')
      row.classList.remove('is-error')
      updateVariantOrderCell(row)
    })
    cell.appendChild(input)
    row.appendChild(cell)
  })

  const actions = document.createElement('div')
  actions.className = 'variant-actions-row'

  const deleteBtn = document.createElement('button')
  deleteBtn.type = 'button'
  deleteBtn.className = 'ghost-btn'
  deleteBtn.textContent = 'Delete'
  deleteBtn.addEventListener('click', async () => {
    if (row.dataset.isNew === 'true') {
      row.remove()
      setVariantMessage(panel, '', '')
      return
    }
    if (!confirm('Delete this item? This cannot be undone.')) return
    deleteBtn.disabled = true
    try {
      await deleteVariant(row.dataset.variantId)
      row.remove()
      setVariantMessage(panel, 'Variant deleted.', 'is-success')
    } catch (error) {
      setVariantMessage(panel, error instanceof Error ? error.message : 'Delete failed', 'is-error')
    } finally {
      deleteBtn.disabled = false
    }
  })

  actions.appendChild(deleteBtn)

  const actionsCell = document.createElement('div')
  actionsCell.className = 'variant-cell'
  actionsCell.appendChild(actions)
  row.appendChild(actionsCell)
  updateVariantOrderCell(row)

  const detailPanel = createVariantDetailPanel(variant, row)
  row._detailPanel = detailPanel
  wireVariantRowDrag(row, panel)
  return { row, detailPanel }
}

function renderVariantTable(panel, items, productId) {
  const table = panel.querySelector('.variant-table')
  if (!table) {
    return
  }
  table.innerHTML = ''
  const head = document.createElement('div')
  head.className = 'variant-head'
  const dragHead = document.createElement('div')
  dragHead.className = 'variant-cell variant-drag-cell'
  dragHead.textContent = 'Move'
  head.appendChild(dragHead)
  const expandHead = document.createElement('div')
  expandHead.className = 'variant-cell variant-expand-cell'
  expandHead.textContent = 'Expand'
  head.appendChild(expandHead)
  variantTableColumns.forEach((column) => {
    const cell = document.createElement('div')
    cell.textContent = column.label
    head.appendChild(cell)
  })
  const actionsHead = document.createElement('div')
  actionsHead.textContent = 'Actions'
  head.appendChild(actionsHead)
  table.appendChild(head)

  if (!items.length) {
    const empty = document.createElement('div')
    empty.className = 'variant-empty'
    empty.textContent = 'No variants yet.'
    table.appendChild(empty)
    return
  }

  items.forEach((variant) => {
    const entryRow = buildVariantRow(variant, productId, panel)
    table.appendChild(entryRow.row)
    table.appendChild(entryRow.detailPanel)
  })

  if (panel._moveMode) {
    table.classList.add('is-reorder')
    table.querySelectorAll('.variant-row').forEach((row) => {
      row.classList.add('is-movable')
    })
  } else {
    table.classList.remove('is-reorder')
  }
}

async function loadVariantPanel(panel, productId) {
  setVariantMessage(panel, 'Loading variants...', 'is-neutral')
  try {
    const items = await fetchVariants(productId)
    renderVariantTable(panel, items, productId)
    setVariantMessage(panel, '', '')
  } catch (error) {
    setVariantMessage(panel, error instanceof Error ? error.message : 'Failed to load variants', 'is-error')
  }
}

async function saveVariantEdits(panel) {
  const productId = panel.dataset.productId || ''
  if (!productId) {
    setVariantMessage(panel, 'Select a product first.', 'is-error')
    return
  }
  const saveBtn = panel.querySelector('[data-action="save-variants"]')
  const dirtyRows = Array.from(panel.querySelectorAll('.variant-row')).filter((row) =>
    row.classList.contains('is-dirty')
  )
  if (!dirtyRows.length) {
    setVariantMessage(panel, 'No variant changes to save.', 'is-neutral')
    return
  }
  if (saveBtn) {
    saveBtn.disabled = true
  }
  setVariantMessage(panel, 'Saving variants...', 'is-neutral')
  let savedCount = 0
  let failedCount = 0
  for (const row of dirtyRows) {
    row.classList.remove('is-error')
    const payload = readVariantRow(row, productId)
    const validationError = validateVariantPayload(payload)
    if (validationError) {
      row.classList.add('is-error')
      failedCount += 1
      setVariantMessage(panel, validationError, 'is-error')
      continue
    }
    try {
      const isNew = row.dataset.isNew === 'true'
      const saved = isNew
        ? await createVariant(payload)
        : await updateVariant(row.dataset.variantId, payload)
      if (saved && typeof saved === 'object') {
        row.dataset.variantId = saved.id
        row.dataset.isNew = 'false'
        variantEditableKeys.forEach((key) => {
          const input = getVariantFieldInput(row, key)
          if (!input) {
            return
          }
          const value = saved[key]
          input.value = value === null || value === undefined ? '' : String(value)
        })
        updateVariantOrderCell(row)
      }
      row.classList.remove('is-dirty')
      savedCount += 1
    } catch (error) {
      row.classList.add('is-error')
      failedCount += 1
      setVariantMessage(panel, error instanceof Error ? error.message : 'Save failed', 'is-error')
    }
  }
  if (failedCount) {
    setVariantMessage(panel, `Saved ${savedCount}. ${failedCount} failed.`, 'is-error')
  } else {
    setVariantMessage(
      panel,
      `Saved ${savedCount} variant${savedCount === 1 ? '' : 's'}.`,
      'is-success'
    )
  }
  if (saveBtn) {
    saveBtn.disabled = false
  }
}

function createVariantPanel(productId) {
  const panel = document.createElement('div')
  panel.className = 'variant-panel'
  panel.dataset.productId = productId
  panel.dataset.collapsed = 'false'
  panel._moveMode = false

  const header = document.createElement('div')
  header.className = 'variant-header'
  header.textContent = 'Variants'

  const message = document.createElement('div')
  message.className = 'form-message variant-message'

  const table = document.createElement('div')
  table.className = 'variant-table'
  table.style.setProperty(
    '--variant-grid',
    '32px 44px minmax(150px, 1.2fr) minmax(130px, 0.9fr) minmax(120px, 0.8fr) minmax(70px, 0.5fr) minmax(90px, 0.6fr) minmax(80px, 0.5fr) minmax(80px, 0.5fr) minmax(80px, 0.5fr) minmax(70px, 0.5fr) 120px'
  )

  const actions = document.createElement('div')
  actions.className = 'variant-actions'
  const moveBtn = document.createElement('button')
  moveBtn.type = 'button'
  moveBtn.className = 'ghost-btn'
  moveBtn.textContent = 'Move'
  moveBtn.addEventListener('click', () => {
    panel._moveMode = !panel._moveMode
    moveBtn.textContent = panel._moveMode ? 'Done Moving' : 'Move'
    table.classList.toggle('is-reorder', panel._moveMode)
    panel.querySelectorAll('.variant-row').forEach((row) => {
      row.draggable = false
      row.classList.toggle('is-movable', panel._moveMode)
    })
    if (panel._moveMode) {
      setVariantMessage(panel, 'Drag rows to reorder. Save Variants to keep the order.', 'is-neutral')
    } else {
      setVariantMessage(panel, '', '')
    }
  })
  actions.appendChild(moveBtn)
  const addBtn = document.createElement('button')
  addBtn.type = 'button'
  addBtn.className = 'ghost-btn'
  addBtn.textContent = 'Add New Vrt'
  addBtn.addEventListener('click', () => {
    const empty = table.querySelector('.variant-empty')
    if (empty) {
      empty.remove()
    }
    const newEntryRow = buildVariantRow(createNewVariantItem(productId), productId, panel)
    table.appendChild(newEntryRow.row)
    table.appendChild(newEntryRow.detailPanel)
    setVariantMessage(panel, '', '')
  })
  actions.appendChild(addBtn)
  const saveBtn = document.createElement('button')
  saveBtn.type = 'button'
  saveBtn.className = 'primary-btn'
  saveBtn.dataset.action = 'save-variants'
  saveBtn.textContent = 'Save Variants'
  saveBtn.addEventListener('click', () => {
    saveVariantEdits(panel)
  })
  actions.appendChild(saveBtn)

  panel.appendChild(header)
  panel.appendChild(message)
  panel.appendChild(table)
  panel.appendChild(actions)

  return panel
}

function normalizeCategoryLabel(value) {
  const label = String(value || '').trim()
  return label || 'Uncategorized'
}

function groupProductsByCategory(products) {
  const grouped = new Map()
  products.forEach((product) => {
    const category = normalizeCategoryLabel(product.category)
    if (!grouped.has(category)) {
      grouped.set(category, [])
    }
    grouped.get(category).push(product)
  })
  return grouped
}

function buildAssociationCategoryOrder(grouped) {
  const ordered = [...productCategoryOptions]
  grouped.forEach((_items, category) => {
    if (!ordered.includes(category)) {
      ordered.push(category)
    }
  })
  return ordered
}

function wireAssocItemDrag(item, list, panel) {
  item.addEventListener('dragstart', (event) => {
    if (!panel._assocMoveMode) {
      event.preventDefault()
      return
    }
    item.classList.add('is-dragging')
    panel._assocDragging = item
    if (event.dataTransfer) {
      event.dataTransfer.effectAllowed = 'move'
      event.dataTransfer.setData('text/plain', 'assoc')
    }
  })

  item.addEventListener('dragover', (event) => {
    if (!panel._assocMoveMode || !panel._assocDragging || panel._assocDragging === item) {
      return
    }
    if (panel._assocDragging.parentNode !== list) {
      return
    }
    event.preventDefault()
    const rect = item.getBoundingClientRect()
    const isAfter = event.clientY > rect.top + rect.height / 2
    item.classList.toggle('drag-over-top', !isAfter)
    item.classList.toggle('drag-over-bottom', isAfter)
  })

  item.addEventListener('dragleave', () => {
    item.classList.remove('drag-over-top', 'drag-over-bottom')
  })

  item.addEventListener('drop', (event) => {
    if (!panel._assocMoveMode || !panel._assocDragging || panel._assocDragging === item) {
      return
    }
    if (panel._assocDragging.parentNode !== list) {
      return
    }
    event.preventDefault()
    const rect = item.getBoundingClientRect()
    const isAfter = event.clientY > rect.top + rect.height / 2
    const anchor = isAfter ? item.nextSibling : item
    list.insertBefore(panel._assocDragging, anchor)
    list.querySelectorAll('.assoc-item').forEach((node) => {
      node.classList.remove('drag-over-top', 'drag-over-bottom')
    })
  })

  item.addEventListener('dragend', () => {
    item.classList.remove('is-dragging')
    item.draggable = false
    list.querySelectorAll('.assoc-item').forEach((node) => {
      node.classList.remove('drag-over-top', 'drag-over-bottom')
    })
    panel._assocDragging = null
  })
}

function renderAssociationPanel(panel, productId, selectedIds, orderMap = {}) {
  const container = panel.querySelector('.assoc-categories')
  if (!container) {
    return
  }
  panel.classList.toggle('is-reorder', Boolean(panel._assocMoveMode))
  const products = (state.products?.items || []).filter((item) => item.id !== productId)
  container.innerHTML = ''
  const grouped = groupProductsByCategory(products)
  const categories = buildAssociationCategoryOrder(grouped)
  if (!categories.length) {
    container.innerHTML = '<div class="assoc-empty">No categories available.</div>'
    return
  }
  categories.forEach((category) => {
    const items = grouped.get(category) || []
    const orderedItems = items.slice().sort((a, b) => {
      const aOrder = orderMap[String(a.id)] ?? null
      const bOrder = orderMap[String(b.id)] ?? null
      if (aOrder !== null && bOrder !== null && aOrder !== bOrder) {
        return aOrder - bOrder
      }
      if (aOrder !== null && bOrder === null) {
        return -1
      }
      if (aOrder === null && bOrder !== null) {
        return 1
      }
      return String(a.name || '').localeCompare(String(b.name || ''))
    })
    const details = document.createElement('details')
    details.className = 'assoc-group'
    const summary = document.createElement('summary')
    summary.textContent = `${category} (${items.length})`
    details.appendChild(summary)
    const list = document.createElement('div')
    list.className = 'assoc-list'
    if (!items.length) {
      const empty = document.createElement('div')
      empty.className = 'assoc-empty'
      empty.textContent = 'No products in this category.'
      list.appendChild(empty)
    } else {
      orderedItems.forEach((product) => {
        const label = document.createElement('label')
        label.className = 'assoc-item'
        label.draggable = false
        if (panel._assocMoveMode) {
          label.classList.add('is-movable')
        }
        const checkbox = document.createElement('input')
        checkbox.type = 'checkbox'
        checkbox.value = String(product.id)
        checkbox.checked = selectedIds.has(String(product.id))
        const handle = document.createElement('button')
        handle.type = 'button'
        handle.className = 'drag-handle assoc-drag-handle'
        handle.textContent = '::'
        handle.setAttribute('aria-label', 'Reorder association')
        handle.dataset.dragHandle = 'true'
        handle.addEventListener('mousedown', () => {
          if (panel._assocMoveMode) {
            handle.draggable = true
            label.draggable = true
          }
        })
        handle.addEventListener('dragstart', (event) => {
          if (!panel._assocMoveMode) {
            event.preventDefault()
            return
          }
          event.stopPropagation()
          label.classList.add('is-dragging')
          panel._assocDragging = label
          if (event.dataTransfer) {
            event.dataTransfer.effectAllowed = 'move'
            event.dataTransfer.setData('text/plain', 'assoc')
          }
        })
        const text = document.createElement('span')
        text.textContent = `${product.name || 'Product'} (${product.sku || product.id})`
        label.appendChild(handle)
        label.appendChild(checkbox)
        label.appendChild(text)
        list.appendChild(label)
        wireAssocItemDrag(label, list, panel)
      })
    }
    details.appendChild(list)
    if (items.some((product) => selectedIds.has(String(product.id)))) {
      details.open = true
    }
    container.appendChild(details)
  })
}

async function loadAssociationPanel(panel, productId) {
  setAssocMessage(panel, 'Loading associations...', 'is-neutral')
  try {
    const response = await fetchAssociations(productId)
    const selectedIds = new Set((response.relatedProductIds || []).map((id) => String(id)))
    let orderMap = response.relatedOrders || {}
    if ((!orderMap || typeof orderMap !== 'object') && Array.isArray(response.related)) {
      orderMap = {}
      response.related.forEach((entry) => {
        if (entry && entry.id) {
          orderMap[String(entry.id)] = entry.sortOrder ?? null
        }
      })
    }
    renderAssociationPanel(panel, productId, selectedIds, orderMap)
    setAssocMessage(panel, '', '')
  } catch (error) {
    setAssocMessage(panel, error instanceof Error ? error.message : 'Failed to load associations', 'is-error')
  }
}

async function saveAssociationPanel(panel) {
  const productId = panel.dataset.productId || ''
  if (!productId) {
    setAssocMessage(panel, 'Select a product first.', 'is-error')
    return
  }
  const saveBtn = panel.querySelector('[data-action="save-associations"]')
  if (saveBtn) {
    saveBtn.disabled = true
  }
  setAssocMessage(panel, 'Saving associations...', 'is-neutral')
  const related = []
  panel.querySelectorAll('.assoc-group').forEach((group) => {
    const list = group.querySelector('.assoc-list')
    if (!list) {
      return
    }
    let sortOrder = 1
    list.querySelectorAll('.assoc-item').forEach((item) => {
      const checkbox = item.querySelector('input')
      if (checkbox && checkbox.checked) {
        related.push({ id: checkbox.value, sortOrder })
        sortOrder += 1
      }
    })
  })
  const selectedIds = related.map((entry) => entry.id)
  try {
    const response = await saveAssociations(productId, related)
    const variantCount = Array.isArray(response?.relatedVariantIds) ? response.relatedVariantIds.length : 0
    setAssocMessage(
      panel,
      `Associations saved (${selectedIds.length} products, ${variantCount} variants).`,
      'is-success'
    )
  } catch (error) {
    setAssocMessage(panel, error instanceof Error ? error.message : 'Save failed', 'is-error')
  } finally {
    if (saveBtn) {
      saveBtn.disabled = false
    }
  }
}

function createAssociationPanel(productId) {
  const panel = document.createElement('div')
  panel.className = 'assoc-panel'
  panel.dataset.productId = productId
  panel.dataset.collapsed = 'false'
  panel._assocMoveMode = false

  const header = document.createElement('div')
  header.className = 'assoc-header'
  header.textContent = 'Associations'

  const message = document.createElement('div')
  message.className = 'form-message assoc-message'

  const categories = document.createElement('div')
  categories.className = 'assoc-categories'

  const actions = document.createElement('div')
  actions.className = 'assoc-actions'
  const moveBtn = document.createElement('button')
  moveBtn.type = 'button'
  moveBtn.className = 'ghost-btn'
  moveBtn.textContent = 'Move'
  moveBtn.addEventListener('click', () => {
    panel._assocMoveMode = !panel._assocMoveMode
    moveBtn.textContent = panel._assocMoveMode ? 'Done Moving' : 'Move'
    panel.classList.toggle('is-reorder', panel._assocMoveMode)
    panel.querySelectorAll('.assoc-item').forEach((item) => {
      item.draggable = false
      item.classList.toggle('is-movable', panel._assocMoveMode)
    })
    if (panel._assocMoveMode) {
      setAssocMessage(panel, 'Drag items inside a category to reorder.', 'is-neutral')
    } else {
      setAssocMessage(panel, '', '')
    }
  })
  actions.appendChild(moveBtn)
  const saveBtn = document.createElement('button')
  saveBtn.type = 'button'
  saveBtn.className = 'primary-btn'
  saveBtn.dataset.action = 'save-associations'
  saveBtn.textContent = 'Save Associations'
  saveBtn.addEventListener('click', () => {
    saveAssociationPanel(panel)
  })
  actions.appendChild(saveBtn)

  panel.appendChild(header)
  panel.appendChild(message)
  panel.appendChild(categories)
  panel.appendChild(actions)

  return panel
}

function toggleAssociationPanel(row, item, toggleBtn) {
  if (row.dataset.isNew === 'true') {
    return
  }
  const productId = row.dataset.itemId || item.id
  const existing = findRowPanel(row, 'assoc-panel')
  const variantPanel = findRowPanel(row, 'variant-panel')
  const variantBtn = row.querySelector('[data-action="variants"]')
  if (existing) {
    const collapsed = existing.dataset.collapsed === 'true'
    existing.dataset.collapsed = collapsed ? 'false' : 'true'
    existing.style.display = collapsed ? '' : 'none'
    updateToggleButton(toggleBtn, collapsed)
    if (collapsed && variantPanel && variantPanel.dataset.collapsed !== 'true') {
      collapseRowPanel(variantPanel, variantBtn)
    }
    if (collapsed) {
      loadAssociationPanel(existing, productId)
    }
    return
  }

  const panel = createAssociationPanel(productId)
  const detailPanel = getProductDetailPanel(row)
  const insertBefore = detailPanel ? detailPanel.nextSibling : row.nextSibling
  row.parentNode?.insertBefore(panel, insertBefore)
  if (variantPanel && variantPanel.dataset.collapsed !== 'true') {
    collapseRowPanel(variantPanel, variantBtn)
  }
  updateToggleButton(toggleBtn, true)
  loadAssociationPanel(panel, productId)
}

function toggleVariantPanel(row, item, toggleBtn) {
  if (row.dataset.isNew === 'true') {
    return
  }
  const productId = row.dataset.itemId || item.id
  const existing = findRowPanel(row, 'variant-panel')
  const assocPanel = findRowPanel(row, 'assoc-panel')
  const assocBtn = row.querySelector('[data-action="associations"]')
  if (existing) {
    const collapsed = existing.dataset.collapsed === 'true'
    existing.dataset.collapsed = collapsed ? 'false' : 'true'
    existing.style.display = collapsed ? '' : 'none'
    updateToggleButton(toggleBtn, collapsed)
    if (collapsed && assocPanel && assocPanel.dataset.collapsed !== 'true') {
      collapseRowPanel(assocPanel, assocBtn)
    }
    if (collapsed) {
      loadVariantPanel(existing, productId)
    }
    return
  }

  const panel = createVariantPanel(productId)
  const detailPanel = getProductDetailPanel(row)
  const insertBefore = detailPanel ? detailPanel.nextSibling : row.nextSibling
  row.parentNode?.insertBefore(panel, insertBefore)
  if (assocPanel && assocPanel.dataset.collapsed !== 'true') {
    collapseRowPanel(assocPanel, assocBtn)
  }
  updateToggleButton(toggleBtn, true)
  loadVariantPanel(panel, productId)
}

function selectProductRow(row, item) {
  if (row.dataset.isNew === 'true') {
    return
  }
  const rowValues = getProductRowValues(row)
  const currentId = row.dataset.itemId || item.id
  setSelectedProduct({
    id: currentId,
    name: rowValues.name || item.name,
    sku: rowValues.sku || item.sku,
    category: rowValues.category || item.category,
    imageUrl: rowValues.imageUrl || item.imageUrl,
    status: rowValues.status || item.status,
    price: rowValues.price === '' ? item.price : Number(rowValues.price),
    inventory: rowValues.inventory === '' ? item.inventory : Number(rowValues.inventory),
    invStockTo: rowValues.invStockTo === '' ? item.invStockTo : Number(rowValues.invStockTo),
    invMin: rowValues.invMin === '' ? item.invMin : Number(rowValues.invMin)
  })
  const entry = state.products
  if (entry?.selectedRow) {
    entry.selectedRow.classList.remove('is-selected')
  }
  row.classList.add('is-selected')
  if (entry) {
    entry.selectedRow = row
  }
}

function buildProductRow(item) {
  const row = document.createElement('div')
  row.className = 'table-row product-row'
  row.dataset.itemId = item.id
  const isNew = Boolean(item.isNew)
  row.dataset.isNew = isNew ? 'true' : 'false'
  const normalized = normalizeProductValues(item)
  row.dataset.original = JSON.stringify(normalized)
  updateProductRowIndex(row, normalized)

  const dragCell = document.createElement('div')
  dragCell.className = 'table-cell product-drag-cell'
  const dragHandle = document.createElement('button')
  dragHandle.type = 'button'
  dragHandle.className = 'drag-handle'
  dragHandle.textContent = '::'
  dragHandle.setAttribute('aria-label', 'Reorder product')
  dragHandle.dataset.dragHandle = 'true'
  dragHandle.addEventListener('mousedown', () => {
    const entry = state.products
    if (entry?.moveMode) {
      dragHandle.draggable = true
      row.draggable = true
    }
  })
  dragHandle.addEventListener('dragstart', (event) => {
    const entry = state.products
    if (!entry?.moveMode) {
      event.preventDefault()
      return
    }
    const category = entry.moveCategory || ''
    if (category && row.dataset.category !== category) {
      event.preventDefault()
      return
    }
    event.stopPropagation()
    row.classList.add('is-dragging')
    entry.draggingRow = row
    if (event.dataTransfer) {
      event.dataTransfer.effectAllowed = 'move'
      event.dataTransfer.setData('text/plain', row.dataset.itemId || '')
    }
  })
  if (state.products?.moveMode) {
    row.draggable = true
  }
  dragCell.appendChild(dragHandle)
  row.appendChild(dragCell)

  const expandCell = document.createElement('div')
  expandCell.className = 'table-cell product-expand-cell'
  const expandBtn = document.createElement('button')
  expandBtn.type = 'button'
  expandBtn.className = 'ghost-btn expand-btn'
  expandBtn.textContent = 'Expand'
  expandBtn.setAttribute('aria-expanded', 'false')
  expandBtn.addEventListener('click', (event) => {
    event.stopPropagation()
    toggleProductDetailPanel(row, expandBtn)
  })
  expandCell.appendChild(expandBtn)
  row.appendChild(expandCell)

  productTableColumns.forEach((column) => {
    const cell = document.createElement('div')
    cell.className = 'table-cell'
    if (!column.editable) {
      cell.dataset.field = column.key
      if (column.key === 'order') {
        const orderValue = calculateOrderValue(item.inventory, item.invMin, item.invStockTo)
        cell.textContent = formatValue(orderValue)
      } else {
        cell.textContent = formatValue(item[column.key])
      }
      row.appendChild(cell)
      return
    }

    let input
    if (column.type === 'select') {
      input = document.createElement('select')
      input.className = 'table-select'
      input.dataset.field = column.key
      const placeholder = document.createElement('option')
      placeholder.value = ''
      placeholder.textContent = 'Select...'
      input.appendChild(placeholder)
      const optionsSource = column.options || (column.key === 'category' ? productCategoryOptions : [])
      const normalizedOptions = optionsSource.map((option) => {
        if (typeof option === 'object') {
          return {
            value: option.value,
            label: option.label || option.value
          }
        }
        return { value: option, label: option }
      }).filter((option) => option.value !== undefined && option.value !== null && option.value !== '')
      const currentValue = item[column.key] || ''
      if (currentValue && !normalizedOptions.some((option) => option.value === currentValue)) {
        normalizedOptions.push({ value: currentValue, label: currentValue })
      }
      normalizedOptions.forEach((option) => {
        const opt = document.createElement('option')
        opt.value = option.value
        opt.textContent = option.label
        input.appendChild(opt)
      })
      input.value = currentValue
    } else {
      input = document.createElement('input')
      input.className = 'table-input'
      input.dataset.field = column.key
      input.type = column.type === 'number' ? 'number' : 'text'
      if (column.type === 'number') {
        input.step = column.step || '1'
      }
      if (column.readonly) {
        input.readOnly = true
        input.classList.add('is-readonly')
      }
      const value = item[column.key]
      input.value = value === null || value === undefined ? '' : String(value)
    }

    const handleChange = () => {
      updateProductRowIndex(row)
      updateProductRowDirtyState(row)
      updateProductOrderCell(row)
      clearProductRowError(row)
      if ((productsSearch?.value || productsCategoryFilter?.value) && row.style.display === 'none') {
        applyProductFilters()
        return
      }
      if (productsSearch?.value || productsCategoryFilter?.value) {
        applyProductFilters()
      }
    }

    input.addEventListener('input', handleChange)
    input.addEventListener('change', handleChange)

    cell.appendChild(input)
    row.appendChild(cell)
  })

  updateProductOrderCell(row)

  // Assoc and Variant buttons (after data columns)
  const assocCell = document.createElement('div')
  assocCell.className = 'table-cell product-assoc-cell'
  const assocBtn = document.createElement('button')
  assocBtn.type = 'button'
  assocBtn.className = 'ghost-btn'
  assocBtn.classList.add('product-assoc-btn')
  assocBtn.dataset.action = 'associations'
  assocBtn.textContent = 'Assc'
  assocBtn.disabled = isNew
  assocBtn.setAttribute('aria-expanded', 'false')
  assocBtn.addEventListener('click', (event) => {
    event.stopPropagation()
    toggleAssociationPanel(row, item, assocBtn)
  })
  assocCell.appendChild(assocBtn)
  row.appendChild(assocCell)

  const variantCell = document.createElement('div')
  variantCell.className = 'table-cell product-variant-cell'
  const variantBtn = document.createElement('button')
  variantBtn.type = 'button'
  variantBtn.className = 'ghost-btn'
  variantBtn.classList.add('product-variant-btn')
  variantBtn.dataset.action = 'variants'
  variantBtn.textContent = 'Vrt'
  variantBtn.disabled = isNew
  variantBtn.setAttribute('aria-expanded', 'false')
  variantBtn.addEventListener('click', (event) => {
    event.stopPropagation()
    toggleVariantPanel(row, item, variantBtn)
  })
  variantCell.appendChild(variantBtn)
  row.appendChild(variantCell)

  const actions = document.createElement('div')
  actions.className = 'product-actions'

  const deleteBtn = document.createElement('button')
  deleteBtn.type = 'button'
  deleteBtn.className = 'ghost-btn'
  deleteBtn.dataset.action = 'delete'
  deleteBtn.textContent = 'Delete'
  deleteBtn.addEventListener('click', async (event) => {
    event.stopPropagation()
    if (row.dataset.isNew === 'true') {
      removeProductRow(row)
      return
    }
    if (!confirm('Delete this item? This cannot be undone.')) return
    await deleteItem(productsResource, item.id)
    await refreshResource(productsResource)
  })

  actions.appendChild(deleteBtn)

  const actionsCell = document.createElement('div')
  actionsCell.className = 'table-cell'
  actionsCell.appendChild(actions)
  row.appendChild(actionsCell)

  row.addEventListener('click', (event) => {
    const target = event.target
    if (target instanceof HTMLElement) {
      if (target.closest('input, select, button, a, textarea')) {
        return
      }
    }
    selectProductRow(row, item)
  })

  const detailPanel = createProductDetailPanel(item, row)
  row._detailPanel = detailPanel
  wireProductRowDrag(row)
  return { row, detailPanel }
}

function renderProductTable(items) {
  const entry = state.products
  if (!entry?.table) {
    return
  }
  const table = entry.table
  table.innerHTML = ''
  const head = document.createElement('div')
  head.className = 'table-head'
  const dragHead = document.createElement('div')
  dragHead.className = 'product-drag-cell'
  dragHead.textContent = 'Move'
  head.appendChild(dragHead)
  const expandHead = document.createElement('div')
  expandHead.className = 'product-expand-cell'
  expandHead.textContent = 'Expand'
  head.appendChild(expandHead)
  productTableColumns.forEach((column) => {
    const cell = document.createElement('div')
    cell.textContent = column.label
    head.appendChild(cell)
  })
  const assocHead = document.createElement('div')
  assocHead.className = 'product-assoc-cell'
  assocHead.textContent = 'Assc'
  head.appendChild(assocHead)
  const variantHead = document.createElement('div')
  variantHead.className = 'product-variant-cell'
  variantHead.textContent = 'Vrt'
  head.appendChild(variantHead)
  const actionsHead = document.createElement('div')
  actionsHead.textContent = 'Actions'
  head.appendChild(actionsHead)
  table.appendChild(head)

  if (!items.length) {
    const empty = document.createElement('div')
    empty.className = 'table-empty'
    empty.textContent = 'No products yet.'
    table.appendChild(empty)
    return
  }

  items.forEach((item) => {
    const entryRow = buildProductRow(item)
    table.appendChild(entryRow.row)
    table.appendChild(entryRow.detailPanel)
  })

  applyProductFilters()
  if (state.products?.moveMode) {
    table.classList.add('is-reorder')
    table.querySelectorAll('.product-row').forEach((row) => {
      row.classList.add('is-movable')
    })
  }
}

async function saveProductEdits() {
  const entry = state.products
  if (!entry?.table || !productsSaveBtn) {
    return
  }
  const dirtyRows = Array.from(entry.table.querySelectorAll('.product-row.is-dirty'))
  if (!dirtyRows.length) {
    if (entry.message) {
      entry.message.textContent = 'No changes to save.'
      entry.message.className = 'form-message is-neutral'
    }
    return
  }

  productsSaveBtn.disabled = true
  if (entry.message) {
    entry.message.textContent = 'Saving changes...'
    entry.message.className = 'form-message is-neutral'
  }

  let savedCount = 0
  const failed = []

  for (const row of dirtyRows) {
    clearProductRowError(row)
    const payload = buildProductPayload(row)
    const validationError = validateProductPayload(payload)
    if (validationError) {
      markProductRowError(row, validationError)
      failed.push(validationError)
      continue
    }
    try {
      const isNew = row.dataset.isNew === 'true'
      const saved = isNew
        ? await createItem(productsResource, payload)
        : await updateItem(productsResource, row.dataset.itemId, payload)
      if (saved && typeof saved === 'object') {
        if (isNew) {
          const tempId = row.dataset.itemId
          row.dataset.itemId = saved.id
          row.dataset.isNew = 'false'
          entry.dirtyIds.delete(tempId)
          const assocBtn = row.querySelector('[data-action="associations"]')
          if (assocBtn) {
            assocBtn.disabled = false
          }
          const variantBtn = row.querySelector('[data-action="variants"]')
          if (variantBtn) {
            variantBtn.disabled = false
          }
        }
        updateProductRowFromSaved(row, saved)
        const index = entry.items.findIndex((item) => item.id === saved.id)
        if (index !== -1) {
          entry.items[index] = saved
        } else if (isNew) {
          entry.items.push(saved)
        }
      }
      savedCount += 1
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Save failed'
      markProductRowError(row, message)
      failed.push(message)
    }
  }

  if (entry.message) {
    if (failed.length) {
      const uniqueErrors = Array.from(new Set(failed.filter(Boolean)))
      const detail = uniqueErrors.length ? ` First error: ${uniqueErrors[0]}` : ''
      entry.message.textContent = `Saved ${savedCount}. ${failed.length} failed.${detail}`
      entry.message.className = 'form-message is-error'
      if (uniqueErrors.length > 1) {
        entry.message.title = uniqueErrors.join(' | ')
      } else {
        entry.message.removeAttribute('title')
      }
    } else {
      entry.message.textContent = `Saved ${savedCount} product${savedCount === 1 ? '' : 's'}.`
      entry.message.className = 'form-message is-success'
      entry.message.removeAttribute('title')
    }
  }

  productsSaveBtn.disabled = false
  updateProductSaveButton()
  updateMetrics()
  applyProductFilters()
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
  const data = await response.json().catch(() => null)
  if (!response.ok) {
    const message =
      (data && (data.error || data.message)) ||
      fallbackMessage
    throw new Error(message)
  }
  if (returnItemsOnly) {
    return data?.items || []
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

async function fetchProductImages(productId) {
  const response = await fetch(`/api/product_images.php?productId=${encodeURIComponent(productId)}`)
  return handleJsonResponse(response, 'Failed to load images', true)
}

async function createProductImages(productId, urls, makePrimary = false) {
  const response = await fetch('/api/product_images.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': csrfToken
    },
    body: JSON.stringify({ productId, urls, makePrimary })
  })
  return handleJsonResponse(response, 'Upload failed')
}

async function setPrimaryProductImage(productId, imageId) {
  const response = await fetch('/api/product_images.php', {
    method: 'PUT',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': csrfToken
    },
    body: JSON.stringify({ productId, primaryId: imageId })
  })
  return handleJsonResponse(response, 'Update failed')
}

async function deleteProductImage(imageId) {
  const response = await fetch(`/api/product_images.php?id=${encodeURIComponent(imageId)}`, {
    method: 'DELETE',
    headers: {
      'X-CSRF-Token': csrfToken
    }
  })
  return handleJsonResponse(response, 'Delete failed')
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

async function fetchAssociations(productId) {
  const response = await fetch(`/api/product_associations.php?productId=${encodeURIComponent(productId)}`)
  return handleJsonResponse(response, 'Failed to load associations')
}

async function saveAssociations(productId, related) {
  const response = await fetch('/api/product_associations.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': csrfToken
    },
    body: JSON.stringify({ productId, related })
  })
  return handleJsonResponse(response, 'Save failed')
}

function setSelectedProduct(product) {
  const entry = state.products
  if (!entry) {
    return
  }
  entry.selectedProduct = product
}

function clearSelectedProduct() {
  const entry = state.products
  if (!entry) {
    return
  }
  entry.selectedProduct = null
  if (entry.selectedRow) {
    entry.selectedRow.classList.remove('is-selected')
    entry.selectedRow = null
  }
}

function buildPanel(resource, container) {
  const body = document.createElement('div')
  body.className = 'panel-body'
  container.appendChild(body)

  const form = createForm(resource)
  const table = createTable(resource)
  body.appendChild(form)
  body.appendChild(table)

  // Hide top form for inline-edit resources
  if (isInlineEditResource(resource)) {
    form.style.display = 'none'
  }

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
  if (resource.id === 'products') {
    entry.dirtyIds?.clear()
    renderProductTable(items)
    updateProductSaveButton()
    const selectedId = state.products?.selectedProduct?.id
    if (selectedId) {
      const updated = items.find((item) => item.id === selectedId)
      if (updated) {
        setSelectedProduct(updated)
        const selectedRow = Array.from(entry.table.querySelectorAll('.product-row')).find(
          (row) => row.dataset.itemId === selectedId
        )
        if (selectedRow) {
          if (entry.selectedRow) {
            entry.selectedRow.classList.remove('is-selected')
          }
          selectedRow.classList.add('is-selected')
          entry.selectedRow = selectedRow
        }
      } else {
        clearSelectedProduct()
      }
    }
  } else {
    renderTable(resource, entry.table, items, entry.form)
    if ((resource.id === 'settings' || resource.id === 'shipping') && items.length) {
      fillForm(entry.form, resource.fields, items[0])
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
  const revenue = (state.orders?.items || []).reduce((sum, item) => {
    const value = Number(item.total || 0)
    return sum + (Number.isNaN(value) ? 0 : value)
  }, 0)

  metrics.innerHTML = ''
  const cards = [
    { label: 'Products', value: productCount },
    { label: 'Orders', value: orderCount },
    { label: 'Revenue', value: `$${revenue.toFixed(2)}` }
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

function getDbHealthIssues(data) {
  const missingTables = Array.isArray(data?.missingTables) ? data.missingTables : []
  const missingColumns = data?.missingColumns && typeof data.missingColumns === 'object'
    ? data.missingColumns
    : {}
  const parts = []

  if (missingTables.length) {
    parts.push(`Missing tables: ${missingTables.join(', ')}`)
  }

  Object.entries(missingColumns).forEach(([table, columns]) => {
    if (!Array.isArray(columns) || !columns.length) {
      return
    }
    parts.push(`Missing columns in ${table}: ${columns.join(', ')}`)
  })

  return { missingTables, missingColumns, parts }
}

function renderDbHealthResults(data) {
  const entry = state.dbHealth
  if (!entry?.results) {
    return
  }
  const results = entry.results
  results.innerHTML = ''

  if (!data) {
    return
  }

  const meta = document.createElement('div')
  meta.className = 'db-health-meta'
  meta.textContent = `Checked ${data.checkedAt || 'just now'} UTC`
  results.appendChild(meta)

  const { missingTables, missingColumns } = getDbHealthIssues(data)

  const list = document.createElement('ul')
  list.className = 'db-health-list'
  let hasIssues = false

  if (missingTables.length) {
    hasIssues = true
    const li = document.createElement('li')
    li.textContent = `Missing tables: ${missingTables.join(', ')}`
    list.appendChild(li)
  }

  Object.entries(missingColumns).forEach(([table, columns]) => {
    if (!Array.isArray(columns) || !columns.length) {
      return
    }
    hasIssues = true
    const li = document.createElement('li')
    li.textContent = `Missing columns in ${table}: ${columns.join(', ')}`
    list.appendChild(li)
  })

  if (!hasIssues) {
    const ok = document.createElement('div')
    ok.className = 'db-health-ok'
    ok.textContent = 'All required tables and columns are present.'
    results.appendChild(ok)
    return
  }

  results.appendChild(list)
}

async function runDbHealthCheck() {
  const entry = state.dbHealth
  if (!entry?.message || !entry.results) {
    return
  }

  entry.message.textContent = 'Checking schema...'
  entry.message.className = 'form-message is-neutral'
  entry.message.removeAttribute('title')
  entry.results.innerHTML = ''

  if (dbHealthRunBtn) {
    dbHealthRunBtn.disabled = true
  }

  try {
    const response = await fetch('/api/db_health.php', { method: 'GET' })
    const data = await handleJsonResponse(response, 'Health check failed')
    const issues = getDbHealthIssues(data)
    renderDbHealthResults(data)
    if (data?.ok) {
      entry.message.textContent = 'Schema looks healthy.'
      entry.message.className = 'form-message is-success'
      entry.message.removeAttribute('title')
    } else {
      entry.message.textContent = issues.parts.length
        ? `Schema issues detected. ${issues.parts[0]}`
        : 'Schema issues detected.'
      entry.message.className = 'form-message is-error'
      if (issues.parts.length > 1) {
        entry.message.title = issues.parts.join(' | ')
      } else {
        entry.message.removeAttribute('title')
      }
    }
  } catch (error) {
    const message = error instanceof Error ? error.message : 'Health check failed.'
    entry.message.textContent = message
    entry.message.className = 'form-message is-error'
  } finally {
    if (dbHealthRunBtn) {
      dbHealthRunBtn.disabled = false
    }
  }
}

function initDbHealth() {
  if (!dbHealthPanel) {
    return
  }

  dbHealthPanel.innerHTML = ''
  const message = document.createElement('div')
  message.className = 'form-message'
  dbHealthPanel.appendChild(message)

  const results = document.createElement('div')
  results.className = 'db-health-results'
  dbHealthPanel.appendChild(results)

  state.dbHealth = { message, results }

  dbHealthRunBtn?.addEventListener('click', runDbHealthCheck)
  runDbHealthCheck()
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

  const pagesLink = document.createElement('a')
  pagesLink.href = '#pages'
  pagesLink.innerHTML = '<span class="nav-dot"></span>Pages'

  const seenGroups = new Set()
  resources.forEach((resource) => {
    if (resource.group) {
      if (seenGroups.has(resource.group)) {
        return
      }
      const group = resourceGroups[resource.group]
      if (!group) {
        return
      }
      const link = document.createElement('a')
      link.href = `#${group.id}`
      link.innerHTML = `<span class="nav-dot"></span>${group.title}`
      nav.appendChild(link)
      seenGroups.add(resource.group)
      return
    }
    // Insert Pages link after Users and before Settings
    if (resource.id === 'settings') {
      nav.appendChild(pagesLink)
    }
    const link = document.createElement('a')
    link.href = `#${resource.id}`
    link.innerHTML = `<span class="nav-dot"></span>${resource.title}`
    nav.appendChild(link)
    // Insert Used Equipment link right after Orders
    if (resource.id === 'orders') {
      const usedEquipLink = document.createElement('a')
      usedEquipLink.href = '#used-equipment'
      usedEquipLink.innerHTML = '<span class="nav-dot"></span>Used Equipment'
      nav.appendChild(usedEquipLink)
    }
  })

  const salesTaxLink = document.createElement('a')
  salesTaxLink.href = '#sales-tax'
  salesTaxLink.innerHTML = '<span class="nav-dot"></span>Sales Tax'
  nav.appendChild(salesTaxLink)

  const dbHealthLink = document.createElement('a')
  dbHealthLink.href = '#db-health'
  dbHealthLink.innerHTML = '<span class="nav-dot"></span>DB Health'
  nav.appendChild(dbHealthLink)
}

function buildResourceSections() {
  const grouped = resources.reduce((acc, resource) => {
    if (!resource.group) {
      return acc
    }
    if (!acc[resource.group]) {
      acc[resource.group] = []
    }
    acc[resource.group].push(resource)
    return acc
  }, {})

  const seenGroups = new Set()

  resources.forEach((resource) => {
    if (resource.group) {
      if (seenGroups.has(resource.group)) {
        return
      }
      const group = resourceGroups[resource.group]
      const groupResources = grouped[resource.group] || []
      if (!group || !groupResources.length) {
        return
      }

      const section = document.createElement('section')
      section.id = group.id
      section.className = 'panel'
      section.innerHTML = `
        <div class="panel-header">
          <div>
            <div class="eyebrow">${group.title}</div>
            <h2>${group.title}</h2>
            <p>${group.description}</p>
          </div>
        </div>
      `

      const groupBody = document.createElement('div')
      groupBody.className = 'panel-group'
      section.appendChild(groupBody)
      stack.appendChild(section)

      groupResources.forEach((entry) => {
        const subPanel = document.createElement('div')
        subPanel.className = 'panel-subsection'
        const exportLink = entry.exportHref
          ? `<a class="ghost-btn" href="${entry.exportHref}">Export to Excel</a>`
          : ''
        subPanel.innerHTML = `
          <div class="panel-header">
            <div>
              <div class="eyebrow">${entry.title}</div>
              <h3>${entry.title}</h3>
            </div>
            <div class="panel-actions">
              ${exportLink}
              <button class="ghost-btn" data-refresh="${entry.id}">Refresh</button>
            </div>
          </div>
        `
        groupBody.appendChild(subPanel)
        buildPanel(entry, subPanel)
      })

      seenGroups.add(resource.group)
      return
    }

    const section = document.createElement('section')
    section.id = resource.id
    section.className = 'panel'
    const exportAction = resource.exportHref
      ? `<a class="ghost-btn" href="${resource.exportHref}">Export to Excel</a>`
      : ''
    section.innerHTML = `
      <div class="panel-header">
        <div>
          <div class="eyebrow">${resource.title}</div>
          <h2>${resource.title}</h2>
        </div>
        <div class="panel-actions">
          ${exportAction}
          <button class="ghost-btn" data-refresh="${resource.id}">Refresh</button>
        </div>
      </div>
    `
    stack.appendChild(section)
    buildPanel(resource, section)
  })
}

function placePageBuilderPanel() {
  const stack = document.getElementById('resource-stack')
  const pagesPanel = document.getElementById('pages')
  const editorPanel = document.getElementById('page-editor')
  if (!stack || !pagesPanel) {
    return
  }
  const settingsSection = stack.querySelector('#settings')
  const usersSection = stack.querySelector('#users')
  if (usersSection) {
    stack.insertBefore(pagesPanel, settingsSection || null)
    if (editorPanel) {
      stack.insertBefore(editorPanel, settingsSection || null)
    }
    return
  }
  stack.appendChild(pagesPanel)
  if (editorPanel) {
    stack.appendChild(editorPanel)
  }
}

function wireRefreshButtons() {
  document.querySelectorAll('[data-refresh]').forEach((button) => {
    button.addEventListener('click', async () => {
      const id = button.getAttribute('data-refresh')
      if (!id) {
        return
      }
      if (id === 'products' && state.products?.dirtyIds?.size) {
        const proceed = window.confirm('You have unsaved product changes. Refresh anyway?')
        if (!proceed) {
          return
        }
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
  productsPanel.innerHTML = ''

  const message = document.createElement('div')
  message.className = 'form-message'
  productsPanel.appendChild(message)

  const table = createProductTable()
  productsPanel.appendChild(table)

  state.products = {
    items: [],
    table,
    selectedProduct: null,
    selectedRow: null,
    dirtyIds: new Set(),
    message,
    moveMode: false,
    moveCategory: '',
    draggingRow: null
  }

  if (productsCategoryFilter) {
    productsCategoryFilter.innerHTML = ''
    const allOption = document.createElement('option')
    allOption.value = ''
    allOption.textContent = 'All categories'
    productsCategoryFilter.appendChild(allOption)
    productCategoryOptions.forEach((category) => {
      const option = document.createElement('option')
      option.value = category
      option.textContent = category
      productsCategoryFilter.appendChild(option)
    })
  }

  productsSearch?.addEventListener('input', applyProductFilters)
  productsCategoryFilter?.addEventListener('change', () => {
    applyProductFilters()
    if (state.products?.moveMode) {
      setProductMoveMode(false, '')
    }
  })
  productsAddBtn?.addEventListener('click', () => {
    const entry = state.products
    if (!entry?.table) {
      return
    }
    const category = productsCategoryFilter?.value || ''
    const entryRow = buildProductRow(createNewProductItem(category))
    insertProductRow(entryRow, entry.table)
    markProductRowDirty(entryRow.row, true)
    applyProductFilters()
    const nameInput = entryRow.row.querySelector('[data-field="name"]')
    if (nameInput) {
      nameInput.focus()
    }
  })
  productsSaveBtn?.addEventListener('click', saveProductEdits)
  productsMoveBtn?.addEventListener('click', () => {
    const entry = state.products
    if (!entry) {
      return
    }
    const category = productsCategoryFilter?.value || ''
    if (!category) {
      if (entry.message) {
        entry.message.textContent = 'Select a category filter before reordering.'
        entry.message.className = 'form-message is-error'
      }
      return
    }
    const next = !entry.moveMode
    setProductMoveMode(next, next ? category : '')
    if (entry.message) {
      entry.message.textContent = next ? 'Drag rows to reorder. Click Done Moving when finished.' : ''
      entry.message.className = next ? 'form-message is-neutral' : 'form-message'
    }
  })

  updateProductSaveButton()
}

// Page Builder functionality
const pageBuilderState = {
  pages: [],
  currentPage: null,
  rows: []
}

const pageRowHeightOptions = [
  { value: 'auto', label: 'Auto' },
  { value: '200', label: '200px' },
  { value: '300', label: '300px' },
  { value: '400', label: '400px' },
  { value: '500', label: '500px' },
  { value: '600', label: '600px' }
]

function extractVideoEmbedUrl(input) {
  if (!input) return ''
  let value = String(input).trim()
  if (!value) return ''

  const iframeMatch = value.match(/<iframe[^>]+src=["']([^"']+)["']/i)
  if (iframeMatch) {
    value = iframeMatch[1]
  }
  value = value.trim()
  if (value.startsWith('//')) {
    value = `https:${value}`
  }

  try {
    const parsed = new URL(value, window.location.origin)
    const host = parsed.hostname.replace(/^www\./, '').replace(/^m\./, '')
    if (host === 'youtu.be') {
      const id = parsed.pathname.split('/').filter(Boolean)[0]
      if (id) return `https://www.youtube.com/embed/${id}`
    }
    if (host.endsWith('youtube.com')) {
      if (parsed.pathname === '/watch') {
        const id = parsed.searchParams.get('v')
        if (id) return `https://www.youtube.com/embed/${id}`
      }
      const match = parsed.pathname.match(/^\/(embed|shorts|live)\/([^/?]+)/)
      if (match) return `https://www.youtube.com/embed/${match[2]}`
    }
    if (host.endsWith('vimeo.com')) {
      const match = parsed.pathname.match(/\/(?:video\/)?(\d+)/)
      if (match) return `https://player.vimeo.com/video/${match[1]}`
    }
  } catch (error) {
    // Fall through to regex parsing.
  }

  let match
  match = value.match(/youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)/)
  if (match) return 'https://www.youtube.com/embed/' + match[1]
  match = value.match(/youtube\.com\/shorts\/([a-zA-Z0-9_-]+)/)
  if (match) return 'https://www.youtube.com/embed/' + match[1]
  match = value.match(/youtube\.com\/embed\/([a-zA-Z0-9_-]+)/)
  if (match) return 'https://www.youtube.com/embed/' + match[1]
  match = value.match(/youtube\.com\/live\/([a-zA-Z0-9_-]+)/)
  if (match) return 'https://www.youtube.com/embed/' + match[1]
  match = value.match(/youtu\.be\/([a-zA-Z0-9_-]+)/)
  if (match) return 'https://www.youtube.com/embed/' + match[1]
  match = value.match(/vimeo\.com\/(\d+)/)
  if (match) return 'https://player.vimeo.com/video/' + match[1]
  match = value.match(/player\.vimeo\.com\/video\/(\d+)/)
  if (match) return 'https://player.vimeo.com/video/' + match[1]
  return ''
}

function createDefaultSection(type) {
  const safeType = ['text', 'image', 'video'].includes(type) ? type : 'text'
  return {
    type: safeType,
    content: buildSectionContent(safeType, {})
  }
}

function buildSectionContent(type, existing) {
  if (type === 'image') {
    return {
      url: existing.url || '',
      alt: existing.alt || ''
    }
  }
  if (type === 'video') {
    return {
      url: existing.url || ''
    }
  }
  return {
    headline: existing.headline || '',
    text: existing.text || ''
  }
}

function normalizeSection(rawSection) {
  const rawType = rawSection?.type || rawSection?.sectionType || 'text'
  if (rawType === 'headline') {
    return {
      type: 'text',
      content: {
        headline: rawSection?.content?.text || '',
        text: ''
      }
    }
  }
  const safeType = ['text', 'image', 'video'].includes(rawType) ? rawType : 'text'
  return {
    type: safeType,
    content: buildSectionContent(safeType, rawSection?.content || {})
  }
}

function createDefaultRow() {
  return {
    id: `row-${Date.now()}-${Math.random().toString(16).slice(2)}`,
    height: 'auto',
    columns: 1,
    sections: [createDefaultSection('text')]
  }
}

function ensureRowSections(row) {
  const nextColumns = Math.max(1, Math.min(4, parseInt(row.columns, 10) || 1))
  row.columns = nextColumns
  if (!Array.isArray(row.sections)) {
    row.sections = []
  }
  while (row.sections.length < nextColumns) {
    row.sections.push(createDefaultSection('text'))
  }
  if (row.sections.length > nextColumns) {
    row.sections = row.sections.slice(0, nextColumns)
  }
  row.sections = row.sections.map(section => normalizeSection(section))
}

function normalizeRowsFromSections(sections) {
  if (!Array.isArray(sections) || !sections.length) {
    return [createDefaultRow()]
  }
  const rows = []
  sections.forEach((section, index) => {
    const sectionType = section.sectionType || section.type
    const content = section.content || {}
    if (sectionType === 'row' && Array.isArray(content.sections)) {
      const row = {
        id: section.id || `row-${index}`,
        height: content.height || 'auto',
        columns: content.columns || content.sections.length || 1,
        sections: content.sections.map((s) => normalizeSection(s))
      }
      ensureRowSections(row)
      rows.push(row)
      return
    }
    const row = createDefaultRow()
    row.id = section.id || row.id
    row.height = 'auto'
    row.columns = 1
    row.sections = [normalizeSection({ type: sectionType, content })]
    rows.push(row)
  })
  return rows.length ? rows : [createDefaultRow()]
}

function syncRowCountInput() {
  const rowInput = document.getElementById('page-row-count')
  if (rowInput) {
    rowInput.value = String(pageBuilderState.rows.length || 1)
  }
}

function setRowCount(count) {
  const target = Math.max(1, Math.min(20, count || 1))
  while (pageBuilderState.rows.length < target) {
    pageBuilderState.rows.push(createDefaultRow())
  }
  if (pageBuilderState.rows.length > target) {
    pageBuilderState.rows = pageBuilderState.rows.slice(0, target)
  }
  syncRowCountInput()
  renderPageRows()
  updatePagePreview()
}

async function loadPages() {
  const list = document.getElementById('pages-list')
  try {
    const resp = await fetch('/api/pages.php')
    if (resp.status === 401) {
      window.location.href = '/admin-login.php'
      return
    }
    if (!resp.ok) {
      throw new Error(`Server returned ${resp.status}`)
    }
    const data = await resp.json()
    pageBuilderState.pages = data.pages || []
    renderPagesList()
  } catch (err) {
    console.error('Error loading pages:', err)
    if (list) {
      list.innerHTML = '<div class="form-message is-error">Failed to load pages. Please refresh and try again.</div>'
    }
  }
}

function renderPagesList() {
  const list = document.getElementById('pages-list')
  if (!list) return

  if (!pageBuilderState.pages.length) {
    list.innerHTML = '<div class="empty-state">No pages created yet. Click "+ New Page" to get started.</div>'
    return
  }

  list.innerHTML = ''
  const table = document.createElement('table')
  table.className = 'data-table'
  table.innerHTML = `
    <thead>
      <tr>
        <th>Title</th>
        <th>URL</th>
        <th>Template</th>
        <th>Status</th>
        <th>Updated</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody></tbody>
  `
  const tbody = table.querySelector('tbody')

  pageBuilderState.pages.forEach((page) => {
    const tr = document.createElement('tr')
    tr.innerHTML = `
      <td>${escapeHtml(page.title)}</td>
      <td><a href="/page/${escapeHtml(page.slug)}" target="_blank">/page/${escapeHtml(page.slug)}</a></td>
      <td>${escapeHtml(page.template)}</td>
      <td><span class="status-badge ${page.status}">${page.status}</span></td>
      <td>${page.updatedAt || ''}</td>
      <td>
        <button class="ghost-btn page-edit-btn" data-id="${page.id}">Edit</button>
        <button class="ghost-btn page-delete-btn" data-id="${page.id}">Delete</button>
      </td>
    `
    tbody.appendChild(tr)
  })

  list.appendChild(table)

  // Wire up edit buttons
  list.querySelectorAll('.page-edit-btn').forEach((btn) => {
    btn.addEventListener('click', () => editPage(btn.dataset.id))
  })

  // Wire up delete buttons
  list.querySelectorAll('.page-delete-btn').forEach((btn) => {
    btn.addEventListener('click', () => deletePage(btn.dataset.id))
  })
}

async function editPage(pageId) {
  try {
    const resp = await fetch(`/api/pages.php?id=${pageId}`)
    if (resp.status === 401) {
      window.location.href = '/admin-login.php'
      return
    }
    const page = await resp.json()
    pageBuilderState.currentPage = page
    pageBuilderState.rows = normalizeRowsFromSections(page.sections || [])
    openPageEditor(false)
  } catch (err) {
    console.error('Error loading page:', err)
    showAdminNotification('Error loading page', true)
  }
}

async function deletePage(pageId) {
  if (!confirm('Are you sure you want to delete this page?')) return

  try {
    const resp = await fetch('/api/pages.php', {
      method: 'DELETE',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrfToken
      },
      body: JSON.stringify({ id: pageId })
    })
    if (resp.status === 401) {
      window.location.href = '/admin-login.php'
      return
    }
    loadPages()
  } catch (err) {
    console.error('Error deleting page:', err)
    showAdminNotification('Error deleting page', true)
  }
}

function openPageEditor(isNew = true) {
  const pagesPanel = document.getElementById('pages')
  const editorPanel = document.getElementById('page-editor')
  const title = document.getElementById('page-editor-title')

  if (pagesPanel) pagesPanel.style.display = 'none'
  if (editorPanel) editorPanel.style.display = 'block'
  if (title) title.textContent = isNew ? 'Create Page' : 'Edit Page'

  if (isNew) {
    pageBuilderState.currentPage = null
    pageBuilderState.rows = [createDefaultRow()]
    document.getElementById('page-title').value = ''
    document.getElementById('page-slug').value = ''
    document.getElementById('page-template').value = 'custom'
    document.getElementById('page-status').value = 'draft'
    document.getElementById('page-meta').value = ''
  } else {
    const page = pageBuilderState.currentPage
    document.getElementById('page-title').value = page.title || ''
    document.getElementById('page-slug').value = page.slug || ''
    document.getElementById('page-template').value = page.template || 'custom'
    document.getElementById('page-status').value = page.status || 'draft'
    document.getElementById('page-meta').value = page.metaDescription || ''
  }

  syncRowCountInput()
  renderPageRows()
  updatePagePreview()
}

function closePageEditor() {
  const pagesPanel = document.getElementById('pages')
  const editorPanel = document.getElementById('page-editor')
  if (pagesPanel) pagesPanel.style.display = 'block'
  if (editorPanel) editorPanel.style.display = 'none'
}

async function handlePageImageUpload(rowIndex, sectionIndex, fileInput, urlInput, previewEl, statusEl) {
  const file = fileInput?.files?.[0]
  if (!file) {
    if (statusEl) {
      statusEl.textContent = 'Choose an image file first, then click Upload.'
      statusEl.className = 'form-message is-error'
    }
    return
  }
  const allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif']
  if (!allowedTypes.includes(file.type)) {
    if (statusEl) {
      statusEl.textContent = 'Unsupported file type. Use JPG, PNG, WebP, or GIF.'
      statusEl.className = 'form-message is-error'
    }
    return
  }
  const maxSize = 5 * 1024 * 1024
  if (file.size > maxSize) {
    if (statusEl) {
      statusEl.textContent = 'Image too large. Max 5 MB.'
      statusEl.className = 'form-message is-error'
    }
    return
  }
  if (statusEl) {
    statusEl.textContent = 'Uploading image...'
    statusEl.className = 'form-message is-neutral'
  }
  try {
    const formData = new FormData()
    formData.append('image', file)
    formData.append('context', 'pages')
    const response = await fetch('/api/upload.php', {
      method: 'POST',
      headers: {
        'X-CSRF-Token': csrfToken
      },
      body: formData
    })
    const data = await response.json().catch(() => ({}))
    if (!response.ok) {
      throw new Error(data.error || 'Upload failed (' + response.status + ')')
    }
    if (!data.path) {
      throw new Error(data.error || 'Upload failed - no path returned')
    }
    const row = pageBuilderState.rows[rowIndex]
    const section = row?.sections?.[sectionIndex]
    if (section) {
      section.content.url = data.path
    }
    if (urlInput) {
      urlInput.value = data.path
    }
    if (previewEl) {
      previewEl.innerHTML = `<img src="${escapeHtml(data.path)}" alt="" />`
    }
    if (statusEl) {
      statusEl.textContent = 'Image uploaded successfully.'
      statusEl.className = 'form-message is-success'
    }
    updatePagePreview()
  } catch (error) {
    console.error('Page image upload error:', error)
    if (statusEl) {
      statusEl.textContent = error instanceof Error ? error.message : 'Upload failed'
      statusEl.className = 'form-message is-error'
    }
  }
}

function renderPageRows() {
  const list = document.getElementById('page-rows-list')
  if (!list) return

  if (!pageBuilderState.rows.length) {
    list.innerHTML = `
      <div class="page-rows-empty">
        <p>No rows added yet</p>
        <p style="font-size: 12px; color: #aaa;">Use the row count or add row button to get started.</p>
      </div>
    `
    return
  }

  list.innerHTML = ''
  pageBuilderState.rows.forEach((row, rowIndex) => {
    ensureRowSections(row)
    const card = document.createElement('div')
    card.className = 'page-row-item'

    const heightOptions = pageRowHeightOptions.map((opt) => {
      const selected = String(opt.value) === String(row.height) ? 'selected' : ''
      return `<option value="${opt.value}" ${selected}>${opt.label}</option>`
    }).join('')

    const columnOptions = [1, 2, 3, 4].map((count) => {
      const selected = Number(row.columns) === count ? 'selected' : ''
      return `<option value="${count}" ${selected}>${count}</option>`
    }).join('')

    card.innerHTML = `
      <div class="page-row-header">
        <div class="page-row-title">Row ${rowIndex + 1}</div>
        <div class="page-row-actions">
          <button class="ghost-btn page-row-remove" type="button">Remove</button>
        </div>
      </div>
      <div class="page-row-controls">
        <div class="page-row-field">
          <label>Row Height</label>
          <select class="row-height-select">${heightOptions}</select>
        </div>
        <div class="page-row-field">
          <label>Sections in Row</label>
          <select class="row-columns-select">${columnOptions}</select>
        </div>
      </div>
      <div class="page-row-sections"></div>
    `

    const removeBtn = card.querySelector('.page-row-remove')
    removeBtn?.addEventListener('click', () => {
      pageBuilderState.rows.splice(rowIndex, 1)
      if (!pageBuilderState.rows.length) {
        pageBuilderState.rows = [createDefaultRow()]
      }
      syncRowCountInput()
      renderPageRows()
      updatePagePreview()
    })

    const heightSelect = card.querySelector('.row-height-select')
    heightSelect?.addEventListener('change', (e) => {
      row.height = e.target.value || 'auto'
      updatePagePreview()
    })

    const columnSelect = card.querySelector('.row-columns-select')
    columnSelect?.addEventListener('change', (e) => {
      row.columns = parseInt(e.target.value, 10) || 1
      ensureRowSections(row)
      renderPageRows()
      updatePagePreview()
    })

    const sectionsWrap = card.querySelector('.page-row-sections')
    row.sections.forEach((section, sectionIndex) => {
      const sectionCard = document.createElement('div')
      sectionCard.className = 'page-row-section-card'

      const typeOptions = ['text', 'image', 'video'].map((type) => {
        const selected = section.type === type ? 'selected' : ''
        return `<option value="${type}" ${selected}>${type.charAt(0).toUpperCase() + type.slice(1)}</option>`
      }).join('')

      sectionCard.innerHTML = `
        <div class="page-row-section-header">Section ${sectionIndex + 1}</div>
        <div class="page-section-field">
          <label>Section Type</label>
          <select class="section-type-select">${typeOptions}</select>
        </div>
        <div class="page-row-section-fields"></div>
      `

      const typeSelect = sectionCard.querySelector('.section-type-select')
      typeSelect?.addEventListener('change', (e) => {
        section.type = e.target.value
        section.content = buildSectionContent(section.type, section.content || {})
        renderPageRows()
        updatePagePreview()
      })

      const fieldsWrap = sectionCard.querySelector('.page-row-section-fields')
      if (section.type === 'text') {
        fieldsWrap.innerHTML = `
          <div class="page-section-field">
            <label>Headline</label>
            <input type="text" class="section-input" data-field="headline" value="${escapeHtml(section.content.headline || '')}" placeholder="Headline text" />
          </div>
          <div class="page-section-field">
            <label>Text</label>
            <textarea class="section-input" data-field="text" rows="4" placeholder="Enter text content">${escapeHtml(section.content.text || '')}</textarea>
          </div>
        `
      } else if (section.type === 'image') {
        const imageUrl = escapeHtml(section.content.url || '')
        fieldsWrap.innerHTML = `
          <div class="page-section-field">
            <label>Image Upload</label>
            <div class="page-image-upload">
              <input type="file" class="section-image-file" accept="image/*" />
              <button class="ghost-btn section-image-upload" type="button">Upload</button>
            </div>
            <div class="form-message"></div>
          </div>
          <div class="page-section-field">
            <label>Image URL</label>
            <input type="text" class="section-input section-image-url" data-field="url" value="${imageUrl}" placeholder="Upload or paste image URL" />
          </div>
          <div class="page-section-field">
            <label>Alt Text</label>
            <input type="text" class="section-input" data-field="alt" value="${escapeHtml(section.content.alt || '')}" placeholder="Describe the image" />
          </div>
          <div class="page-image-preview">${imageUrl ? `<img src="${imageUrl}" alt="" />` : '<span>No image selected.</span>'}</div>
        `
      } else {
        const videoUrl = section.content.url || ''
        const embedUrl = extractVideoEmbedUrl(videoUrl)
        fieldsWrap.innerHTML = `
          <div class="page-section-field">
            <label>Video URL (YouTube or Vimeo)</label>
            <input type="text" class="section-input section-video-url" data-field="url" value="${escapeHtml(videoUrl)}" placeholder="https://www.youtube.com/watch?v=... or https://vimeo.com/..." />
          </div>
          <div class="page-video-preview">${embedUrl ? `<div class="preview-video"><iframe src="${embedUrl}" frameborder="0" allowfullscreen></iframe></div>` : '<div class="preview-video placeholder">' + (videoUrl ? 'Unrecognized video URL' : 'Paste a YouTube or Vimeo link above') + '</div>'}</div>
        `
      }

      fieldsWrap.querySelectorAll('.section-input').forEach((input) => {
        input.addEventListener('input', (e) => {
          const field = e.target.dataset.field
          section.content[field] = e.target.value
          if (section.type === 'image' && field === 'url') {
            const preview = sectionCard.querySelector('.page-image-preview')
            if (preview) {
              preview.innerHTML = e.target.value ? `<img src="${escapeHtml(e.target.value)}" alt="" />` : '<span>No image selected.</span>'
            }
          }
          if (section.type === 'video' && field === 'url') {
            const videoPreview = sectionCard.querySelector('.page-video-preview')
            if (videoPreview) {
              const embed = extractVideoEmbedUrl(e.target.value)
              if (embed) {
                videoPreview.innerHTML = `<div class="preview-video"><iframe src="${embed}" frameborder="0" allowfullscreen></iframe></div>`
              } else if (e.target.value) {
                videoPreview.innerHTML = '<div class="preview-video placeholder">Unrecognized video URL</div>'
              } else {
                videoPreview.innerHTML = '<div class="preview-video placeholder">Paste a YouTube or Vimeo link above</div>'
              }
            }
          }
          updatePagePreview()
        })
        input.addEventListener('change', (e) => {
          const field = e.target.dataset.field
          section.content[field] = e.target.value
          updatePagePreview()
        })
      })

      const uploadBtn = sectionCard.querySelector('.section-image-upload')
      if (uploadBtn) {
        const doUpload = () => {
          const fileInput = sectionCard.querySelector('.section-image-file')
          const urlInput = sectionCard.querySelector('.section-image-url')
          const previewEl = sectionCard.querySelector('.page-image-preview')
          const statusEl = sectionCard.querySelector('.form-message')
          handlePageImageUpload(rowIndex, sectionIndex, fileInput, urlInput, previewEl, statusEl)
        }
        uploadBtn.addEventListener('click', doUpload)
        const fileInput = sectionCard.querySelector('.section-image-file')
        if (fileInput) {
          fileInput.addEventListener('change', doUpload)
        }
      }

      sectionsWrap.appendChild(sectionCard)
    })

    list.appendChild(card)
  })
}

function updatePagePreview() {
  const preview = document.getElementById('page-preview-content')
  if (!preview) return

  if (!pageBuilderState.rows.length) {
    preview.innerHTML = '<div class="page-builder-preview-empty">Add rows to see a preview</div>'
    return
  }

  let html = ''
  pageBuilderState.rows.forEach((row) => {
    const columnCount = row.columns || 1
    const heightStyle = row.height && row.height !== 'auto' ? `min-height: ${row.height}px;` : ''
    html += `<div class="preview-row" style="grid-template-columns: repeat(${columnCount}, minmax(0, 1fr)); ${heightStyle}">`
    row.sections.forEach((section) => {
      html += '<div class="preview-cell">'
      if (section.type === 'text') {
        const headline = escapeHtml(section.content.headline || '')
        const textContent = section.content.text || ''
        const paragraphs = textContent.split(/\r?\n/).filter(p => p.trim())
        if (headline) {
          html += `<h3>${headline}</h3>`
        }
        if (paragraphs.length) {
          paragraphs.forEach(p => {
            html += `<p>${escapeHtml(p)}</p>`
          })
        } else if (!headline) {
          html += '<p style="color: #999; font-style: italic;">Text content...</p>'
        }
      } else if (section.type === 'image') {
        const imgUrl = section.content.url || ''
        const imgAlt = escapeHtml(section.content.alt || '')
        if (imgUrl) {
          html += `<div class="preview-image"><img src="${escapeHtml(imgUrl)}" alt="${imgAlt}" onerror="this.parentNode.innerHTML='<div class=\\'preview-image placeholder\\'>Image failed to load</div>'" /></div>`
        } else {
          html += '<div class="preview-image placeholder">Image preview</div>'
        }
      } else if (section.type === 'video') {
        const videoUrl = section.content.url || ''
        const embedUrl = extractVideoEmbedUrl(videoUrl)
        if (embedUrl) {
          html += `<div class="preview-video"><iframe src="${embedUrl}" frameborder="0" allowfullscreen></iframe></div>`
        } else if (videoUrl) {
          html += '<div class="preview-video placeholder">Unrecognized video URL</div>'
        } else {
          html += '<div class="preview-video placeholder">Paste a YouTube or Vimeo link</div>'
        }
      }
      html += '</div>'
    })
    html += '</div>'
  })

  preview.innerHTML = html
}

async function savePage() {
  const title = document.getElementById('page-title').value.trim()
  const slug = document.getElementById('page-slug').value.trim()
  const template = document.getElementById('page-template').value
  const status = document.getElementById('page-status').value
  const metaDescription = document.getElementById('page-meta').value.trim()

  if (!title) {
    showAdminNotification('Page title is required', true)
    return
  }

  const payload = {
    id: pageBuilderState.currentPage?.id || null,
    title,
    slug,
    template,
    status,
    metaDescription,
    sections: pageBuilderState.rows.map((row) => ({
      type: 'row',
      content: {
        height: row.height || 'auto',
        columns: row.columns || 1,
        sections: row.sections.map((section) => ({
          type: section.type,
          content: section.content
        }))
      }
    }))
  }

  try {
    const resp = await fetch('/api/pages.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrfToken
      },
      body: JSON.stringify(payload)
    })
    if (resp.status === 401) {
      window.location.href = '/admin-login.php'
      return
    }
    const data = await resp.json()
    if (!resp.ok) {
      showAdminNotification(data.error || 'Error saving page', true)
      return
    }
    closePageEditor()
    loadPages()
  } catch (err) {
    console.error('Error saving page:', err)
    showAdminNotification('Error saving page', true)
  }
}

function initPageBuilder() {
  // New page button
  document.getElementById('pages-add')?.addEventListener('click', () => {
    openPageEditor(true)
  })

  // Cancel button
  document.getElementById('page-editor-cancel')?.addEventListener('click', closePageEditor)

  // Save button
  document.getElementById('page-editor-save')?.addEventListener('click', savePage)

  // Row count input
  const rowCountInput = document.getElementById('page-row-count')
  rowCountInput?.addEventListener('change', (e) => {
    const value = parseInt(e.target.value, 10)
    setRowCount(value)
  })

  // Add row button
  document.getElementById('page-row-add')?.addEventListener('click', () => {
    pageBuilderState.rows.push(createDefaultRow())
    syncRowCountInput()
    renderPageRows()
    updatePagePreview()
  })

  // Load initial pages
  loadPages()
}


// --- Used Equipment Section ---
const usedEquipPanel = document.getElementById('used-equip-panel')
const usedEquipRefreshBtn = document.getElementById('used-equip-refresh')

function initUsedEquipment() {
  if (!usedEquipPanel) return
  state.usedEquipment = { items: [] }
  usedEquipRefreshBtn?.addEventListener('click', loadUsedEquipment)
}

async function loadUsedEquipment() {
  if (!usedEquipPanel) return
  try {
    const resp = await fetch('/api/admin_equipment.php', {
      headers: { 'X-CSRF-Token': csrfToken }
    })
    const data = await resp.json()
    const items = Array.isArray(data.items) ? data.items : []
    state.usedEquipment.items = items
    renderUsedEquipmentTable(items)
  } catch (err) {
    usedEquipPanel.textContent = 'Unable to load equipment data.'
  }
}

function renderUsedEquipmentTable(items) {
  if (!usedEquipPanel) return
  usedEquipPanel.innerHTML = ''

  if (!items.length) {
    usedEquipPanel.innerHTML = '<div class="form-message">No equipment submissions yet.</div>'
    return
  }

  const table = document.createElement('table')
  table.className = 'resource-table'
  table.innerHTML = `
    <thead>
      <tr>
        <th>User</th>
        <th>Equipment Name</th>
        <th>Qty</th>
        <th>Cur Qty</th>
        <th>Price</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
  `
  const tbody = document.createElement('tbody')

  items.forEach(item => {
    const status = (item.status || 'Pending Approval').toLowerCase()
    const userName = escapeHtml(item.userName || item.userEmail || 'Unknown')
    const equipName = escapeHtml(item.name || '')
    const qty = Number(item.quantity || 0)
    const curQty = Number(item.curQty ?? qty)
    const price = item.price != null && item.price !== '' ? '$' + Number(item.price).toFixed(2) : '-'

    // Primary row
    const tr = document.createElement('tr')
    tr.className = 'used-equip-row'
    tr.style.cursor = 'pointer'

    let statusHtml = ''
    if (status === 'active') {
      statusHtml = '<span class="status-badge approved">Approved &#10003;</span>'
    } else if (status === 'declined') {
      statusHtml = '<span class="status-badge declined">Declined</span>'
    } else {
      statusHtml = '<span class="status-badge pending">Pending</span>'
    }

    let actionsHtml = ''
    if (status === 'pending approval') {
      actionsHtml = `
        <button class="primary-btn equip-approve-btn" data-id="${escapeHtml(item.id)}">Approve</button>
        <button class="ghost-btn equip-decline-btn" data-id="${escapeHtml(item.id)}">Decline</button>
      `
    }

    tr.innerHTML = `
      <td>${userName}</td>
      <td>${equipName}</td>
      <td>${qty}</td>
      <td>${curQty}</td>
      <td>${price}</td>
      <td>${statusHtml}</td>
      <td class="used-equip-actions">${actionsHtml}</td>
    `

    // Click to toggle dropdown
    tr.addEventListener('click', (e) => {
      if (e.target.closest('button')) return
      const detailRow = tr.nextElementSibling
      if (detailRow && detailRow.classList.contains('used-equip-detail')) {
        detailRow.classList.toggle('is-open')
      }
    })

    tbody.appendChild(tr)

    // Detail dropdown row
    const detailTr = document.createElement('tr')
    detailTr.className = 'used-equip-detail'
    const detailTd = document.createElement('td')
    detailTd.colSpan = 7
    detailTd.innerHTML = `
      <div class="used-equip-detail-grid">
        <div><strong>Serial:</strong> ${escapeHtml(item.serial || '-')}</div>
        <div><strong>Status:</strong> ${escapeHtml(item.status || '-')}</div>
        <div><strong>Location of Equipment:</strong> ${escapeHtml(item.location || '-')}</div>
        <div><strong>Description of Equipment:</strong> ${escapeHtml(item.notes || '-')}</div>
        <div><strong>Contact Name:</strong> ${escapeHtml(item.contactName || '-')}</div>
        <div><strong>Contact Phone:</strong> ${escapeHtml(item.contactPhone || '-')}</div>
        <div><strong>Contact Email:</strong> ${escapeHtml(item.contactEmail || '-')}</div>
      </div>
    `
    detailTr.appendChild(detailTd)
    tbody.appendChild(detailTr)
  })

  table.appendChild(tbody)
  usedEquipPanel.appendChild(table)

  // Wire up approve/decline buttons
  usedEquipPanel.querySelectorAll('.equip-approve-btn').forEach(btn => {
    btn.addEventListener('click', async (e) => {
      e.stopPropagation()
      const id = btn.getAttribute('data-id')
      if (!confirm('Approve this equipment listing? This will create a product page.')) return
      btn.disabled = true
      btn.textContent = 'Approving...'
      try {
        const resp = await fetch('/api/admin_equipment.php', {
          method: 'PUT',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
          body: JSON.stringify({ action: 'approve', equipmentId: id })
        })
        const data = await resp.json()
        if (data.ok) {
          await loadUsedEquipment()
        } else {
          showAdminNotification(data.error || 'Approval failed', true)
          btn.disabled = false
          btn.textContent = 'Approve'
        }
      } catch (err) {
        showAdminNotification('Approval failed', true)
        btn.disabled = false
        btn.textContent = 'Approve'
      }
    })
  })

  usedEquipPanel.querySelectorAll('.equip-decline-btn').forEach(btn => {
    btn.addEventListener('click', async (e) => {
      e.stopPropagation()
      const id = btn.getAttribute('data-id')
      if (!confirm('Decline this equipment listing?')) return
      btn.disabled = true
      btn.textContent = 'Declining...'
      try {
        const resp = await fetch('/api/admin_equipment.php', {
          method: 'PUT',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
          body: JSON.stringify({ action: 'decline', equipmentId: id })
        })
        const data = await resp.json()
        if (data.ok) {
          await loadUsedEquipment()
        } else {
          showAdminNotification(data.error || 'Decline failed', true)
          btn.disabled = false
          btn.textContent = 'Decline'
        }
      } catch (err) {
        showAdminNotification('Decline failed', true)
        btn.disabled = false
        btn.textContent = 'Decline'
      }
    })
  })
}

// ========== Sales Tax Rates ==========

let taxGroups = []

async function loadTaxGroups() {
  try {
    const res = await fetch('/api/tax_rates.php', {
      headers: { 'X-CSRF-TOKEN': csrfToken }
    })
    const data = await handleJsonResponse(res, { items: [] })
    taxGroups = data.items || []
  } catch {
    taxGroups = []
  }
  renderTaxGroups()
}

function renderTaxGroups() {
  if (!taxPanel) return
  taxPanel.innerHTML = ''

  if (taxGroups.length === 0) {
    const empty = document.createElement('div')
    empty.className = 'tax-empty'
    empty.textContent = 'No tax rate groups yet. Click "+ Add Rate Group" to create one.'
    taxPanel.appendChild(empty)
    return
  }

  taxGroups.forEach(group => {
    const card = document.createElement('div')
    card.className = 'tax-group-card'

    const header = document.createElement('div')
    header.className = 'tax-group-header'

    const title = document.createElement('span')
    title.className = 'tax-group-title'
    title.textContent = group.name || 'Unnamed Group'
    header.appendChild(title)

    const rate = document.createElement('span')
    rate.className = 'tax-group-rate'
    rate.textContent = String(parseFloat(group.ratePercent)) + '%'
    header.appendChild(rate)

    card.appendChild(header)

    const zipsWrap = document.createElement('div')
    zipsWrap.className = 'tax-group-zips'
    ;(group.zips || []).forEach(zip => {
      const tag = document.createElement('span')
      tag.className = 'zip-tag'
      tag.textContent = zip
      zipsWrap.appendChild(tag)
    })
    card.appendChild(zipsWrap)

    const actions = document.createElement('div')
    actions.className = 'tax-group-actions'

    const editBtn = document.createElement('button')
    editBtn.className = 'ghost-btn'
    editBtn.textContent = 'Edit'
    editBtn.addEventListener('click', () => renderTaxGroupForm(group))
    actions.appendChild(editBtn)

    const deleteBtn = document.createElement('button')
    deleteBtn.className = 'ghost-btn'
    deleteBtn.style.color = '#b91c1c'
    deleteBtn.textContent = 'Delete'
    deleteBtn.addEventListener('click', () => deleteTaxGroup(group.id))
    actions.appendChild(deleteBtn)

    card.appendChild(actions)
    taxPanel.appendChild(card)
  })
}

function renderTaxGroupForm(group) {
  if (!taxPanel) return
  // Remove any existing form
  const existingForm = taxPanel.querySelector('.tax-group-form')
  if (existingForm) existingForm.remove()

  const form = document.createElement('div')
  form.className = 'tax-group-form'

  const nameLabel = document.createElement('label')
  nameLabel.textContent = 'Group Name (optional)'
  const nameInput = document.createElement('input')
  nameInput.type = 'text'
  nameInput.placeholder = 'e.g. Oklahoma City Metro'
  nameInput.value = group ? (group.name || '') : ''
  const nameWrap = document.createElement('div')
  nameWrap.appendChild(nameLabel)
  nameWrap.appendChild(nameInput)
  form.appendChild(nameWrap)

  const rateLabel = document.createElement('label')
  rateLabel.textContent = 'Tax Rate %'
  const rateInput = document.createElement('input')
  rateInput.type = 'number'
  rateInput.step = 'any'
  rateInput.min = '0'
  rateInput.max = '100'
  rateInput.placeholder = 'e.g. 9.5'
  rateInput.value = group ? parseFloat(group.ratePercent) : ''
  const rateWrap = document.createElement('div')
  rateWrap.appendChild(rateLabel)
  rateWrap.appendChild(rateInput)
  form.appendChild(rateWrap)

  const zipsLabel = document.createElement('label')
  zipsLabel.textContent = 'Zip Codes (comma or newline separated)'
  const zipsInput = document.createElement('textarea')
  zipsInput.placeholder = '73101, 73102, 73103'
  zipsInput.value = group ? (group.zips || []).join(', ') : ''
  const zipsWrap = document.createElement('div')
  zipsWrap.appendChild(zipsLabel)
  zipsWrap.appendChild(zipsInput)
  form.appendChild(zipsWrap)

  const actionsDiv = document.createElement('div')
  actionsDiv.className = 'tax-group-form-actions'

  const cancelBtn = document.createElement('button')
  cancelBtn.className = 'ghost-btn'
  cancelBtn.textContent = 'Cancel'
  cancelBtn.type = 'button'
  cancelBtn.addEventListener('click', () => {
    form.remove()
  })
  actionsDiv.appendChild(cancelBtn)

  const saveBtn = document.createElement('button')
  saveBtn.className = 'primary-btn'
  saveBtn.textContent = group ? 'Update' : 'Save'
  saveBtn.type = 'button'
  saveBtn.addEventListener('click', async () => {
    const payload = {
      name: nameInput.value.trim(),
      rate: rateInput.value.trim(),
      zips: zipsInput.value.trim()
    }
    if (!payload.zips) {
      showAdminNotification('At least one zip code is required.', true)
      return
    }
    if (!payload.rate || isNaN(payload.rate) || Number(payload.rate) <= 0) {
      showAdminNotification('A valid tax rate is required.', true)
      return
    }
    saveBtn.disabled = true
    saveBtn.textContent = 'Saving...'
    await saveTaxGroup(group ? group.id : null, payload)
    form.remove()
  })
  actionsDiv.appendChild(saveBtn)

  form.appendChild(actionsDiv)

  // Insert form at top of panel
  taxPanel.insertBefore(form, taxPanel.firstChild)
  nameInput.focus()
}

async function saveTaxGroup(groupId, payload) {
  const isUpdate = groupId !== null
  const url = isUpdate ? `/api/tax_rates.php?id=${encodeURIComponent(groupId)}` : '/api/tax_rates.php'
  const method = isUpdate ? 'PUT' : 'POST'

  try {
    const res = await fetch(url, {
      method,
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken
      },
      body: JSON.stringify(payload)
    })
    const data = await res.json().catch(() => null)
    if (!res.ok) {
      showAdminNotification((data && data.error) || 'Failed to save tax rate group.', true)
      return
    }
    showAdminNotification(isUpdate ? 'Tax rate group updated.' : 'Tax rate group created.')
    await loadTaxGroups()
  } catch (err) {
    showAdminNotification(err.message || 'Failed to save tax rate group.', true)
  }
}

async function deleteTaxGroup(groupId) {
  if (!confirm('Delete this tax rate group? This cannot be undone.')) return

  try {
    const res = await fetch(`/api/tax_rates.php?id=${encodeURIComponent(groupId)}`, {
      method: 'DELETE',
      headers: { 'X-CSRF-TOKEN': csrfToken }
    })
    const data = await res.json().catch(() => null)
    if (!res.ok) {
      showAdminNotification((data && data.error) || 'Failed to delete tax rate group.', true)
      return
    }
    showAdminNotification('Tax rate group deleted.')
    await loadTaxGroups()
  } catch (err) {
    showAdminNotification(err.message || 'Failed to delete tax rate group.', true)
  }
}

function initSalesTax() {
  if (taxAddGroupBtn) {
    taxAddGroupBtn.addEventListener('click', () => renderTaxGroupForm(null))
  }
}

// ========== Init ==========

async function init() {
  buildNav()
  buildResourceSections()
  // Move Used Equipment section right after Orders in the DOM
  const ordersSection = document.getElementById('orders')
  const usedEquipSection = document.getElementById('used-equipment')
  if (ordersSection && usedEquipSection) {
    ordersSection.after(usedEquipSection)
  }
  // Move Sales Tax section after shipping in the resource stack
  const shippingSection = document.getElementById('shipping')
  const salesTaxSection = document.getElementById('sales-tax')
  if (shippingSection && salesTaxSection) {
    shippingSection.after(salesTaxSection)
  }
  placePageBuilderPanel()
  initProducts()
  initUsedEquipment()
  initDbHealth()
  initPageBuilder()
  initSalesTax()
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

  // Load used equipment, tax groups, and invoices after other resources
  loadUsedEquipment()
  loadTaxGroups()
  loadInvoices()
}

// ========== Invoices ==========

async function loadInvoices() {
  const panel = document.getElementById('invoice-panel')
  if (!panel) return
  const filterEl = document.getElementById('invoice-status-filter')
  const status = filterEl?.value || ''
  const url = '/api/invoices.php' + (status ? '?status=' + encodeURIComponent(status) : '')

  panel.innerHTML = '<div class="loading">Loading invoices...</div>'
  try {
    const res = await fetch(url)
    const data = await res.json()
    if (!res.ok) {
      panel.innerHTML = `<div class="notice is-error">${escapeHtml(data.error || 'Failed to load')}</div>`
      return
    }
    const items = data.items || []
    if (!items.length) {
      panel.innerHTML = '<div class="notice">No invoices found.</div>'
      return
    }
    renderInvoiceTable(panel, items)
  } catch (err) {
    panel.innerHTML = '<div class="notice is-error">Failed to load invoices.</div>'
  }
}

function renderInvoiceTable(panel, items) {
  let html = '<div class="table invoice-table">'
  html += '<div class="table-row table-header" style="grid-template-columns: 120px 100px 150px 180px 90px 90px 80px 140px;">'
  html += '<div class="header-cell">Invoice #</div>'
  html += '<div class="header-cell">Order #</div>'
  html += '<div class="header-cell">Customer</div>'
  html += '<div class="header-cell">Email</div>'
  html += '<div class="header-cell">Amount</div>'
  html += '<div class="header-cell">Due Date</div>'
  html += '<div class="header-cell">Status</div>'
  html += '<div class="header-cell">Actions</div>'
  html += '</div>'

  items.forEach((inv) => {
    const statusClass = inv.status === 'paid' ? 'is-success' : inv.status === 'overdue' ? 'is-error' : ''
    const statusLabel = (inv.status || 'pending').charAt(0).toUpperCase() + (inv.status || 'pending').slice(1)
    const dueDate = inv.dueDate ? new Date(inv.dueDate + 'T00:00:00').toLocaleDateString() : ''
    html += `<div class="table-row" style="grid-template-columns: 120px 100px 150px 180px 90px 90px 80px 140px;">`
    html += `<div class="table-cell">${escapeHtml(inv.invoiceNumber || '')}</div>`
    html += `<div class="table-cell">${escapeHtml(inv.orderNumber || '')}</div>`
    html += `<div class="table-cell">${escapeHtml(inv.customerName || '')}</div>`
    html += `<div class="table-cell">${escapeHtml(inv.customerEmail || '')}</div>`
    html += `<div class="table-cell">$${Number(inv.amount || 0).toFixed(2)}</div>`
    html += `<div class="table-cell">${dueDate}</div>`
    html += `<div class="table-cell"><span class="tag ${statusClass}">${statusLabel}</span></div>`
    html += `<div class="table-cell">`
    html += `<a class="ghost-btn" href="/api/invoices.php?download=1&id=${encodeURIComponent(inv.id)}" target="_blank" title="View PDF">PDF</a>`
    if (inv.status !== 'paid') {
      html += ` <button class="ghost-btn" onclick="markInvoicePaid('${escapeHtml(inv.id)}')" title="Mark Paid">Paid</button>`
    }
    html += ` <button class="ghost-btn" onclick="resendInvoice('${escapeHtml(inv.id)}')" title="Resend Email">Resend</button>`
    html += `</div></div>`
  })
  html += '</div>'
  panel.innerHTML = html
}

async function markInvoicePaid(invoiceId) {
  if (!confirm('Mark this invoice as paid?')) return
  try {
    const res = await fetch('/api/invoices.php?id=' + encodeURIComponent(invoiceId), {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
      body: JSON.stringify({ action: 'mark_paid' })
    })
    const data = await res.json()
    if (!res.ok) { alert(data.error || 'Failed'); return }
    loadInvoices()
  } catch (err) { alert('Failed to update invoice.') }
}

async function resendInvoice(invoiceId) {
  if (!confirm('Resend invoice email?')) return
  try {
    const res = await fetch('/api/invoices.php?id=' + encodeURIComponent(invoiceId), {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
      body: JSON.stringify({ action: 'resend' })
    })
    const data = await res.json()
    if (!res.ok || !data.ok) { alert(data.error || 'Failed to send'); return }
    alert('Invoice email sent.')
  } catch (err) { alert('Failed to resend.') }
}

function initInvoices() {
  document.getElementById('invoice-refresh')?.addEventListener('click', () => loadInvoices())
  document.getElementById('invoice-status-filter')?.addEventListener('change', () => loadInvoices())
}

initInvoices()

// Add Invoices nav link
;(function () {
  const nav = document.querySelector('.admin-nav, nav')
  if (!nav) return
  const taxLink = nav.querySelector('a[href="#sales-tax"]')
  if (taxLink) {
    const invoiceLink = document.createElement('a')
    invoiceLink.href = '#invoices'
    invoiceLink.innerHTML = '<span class="nav-dot"></span>Invoices'
    taxLink.parentNode.insertBefore(invoiceLink, taxLink.nextSibling)
  }
})()

// ========== CSV Import ==========

let currentImportType = ''

const importConfig = {
  products: { title: 'Import Products', exampleUrl: '/uploads/examples/import-products-example.csv' },
  variants: { title: 'Import Product Variants', exampleUrl: '/uploads/examples/import-variants-example.csv' },
  images: { title: 'Import Images', exampleUrl: '/uploads/examples/import-images-example.csv' }
}

function openImportModal(importType) {
  const config = importConfig[importType]
  if (!config) return
  currentImportType = importType
  const modal = document.getElementById('import-modal')
  const title = document.getElementById('import-modal-title')
  const exampleLink = document.getElementById('import-example-link')
  const fileInput = document.getElementById('import-csv-file')
  const message = document.getElementById('import-modal-message')
  title.textContent = config.title
  exampleLink.href = config.exampleUrl
  fileInput.value = ''
  message.style.display = 'none'
  message.textContent = ''
  message.className = 'import-modal-message'
  modal.style.display = ''
}

function closeImportModal() {
  document.getElementById('import-modal').style.display = 'none'
  currentImportType = ''
}

async function submitImport() {
  const fileInput = document.getElementById('import-csv-file')
  const mode = document.getElementById('import-mode')?.value || 'add'
  const message = document.getElementById('import-modal-message')
  const submitBtn = document.getElementById('import-modal-submit')
  if (!fileInput.files || !fileInput.files[0]) {
    message.textContent = 'Please select a CSV file.'
    message.className = 'import-modal-message is-error'
    message.style.display = ''
    return
  }
  submitBtn.disabled = true
  submitBtn.textContent = 'Importing...'
  message.textContent = 'Processing...'
  message.className = 'import-modal-message'
  message.style.display = ''
  try {
    const formData = new FormData()
    formData.append('csv', fileInput.files[0])
    formData.append('importType', currentImportType)
    formData.append('mode', mode)
    const res = await fetch('/api/import.php', { method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken }, body: formData })
    const responseText = await res.text()
    let data
    try { data = JSON.parse(responseText) } catch (e) {
      message.textContent = 'Server error: ' + responseText.substring(0, 200)
      message.className = 'import-modal-message is-error'
      message.style.display = ''
      return
    }
    if (!res.ok) {
      message.textContent = data.error || 'Import failed.'
      message.className = 'import-modal-message is-error'
      message.style.display = ''
      return
    }
    let summary = []
    if (data.created > 0) summary.push(data.created + ' created')
    if (data.updated > 0) summary.push(data.updated + ' updated')
    if (data.skipped > 0) summary.push(data.skipped + ' skipped')
    let msg = 'Import complete: ' + (summary.join(', ') || 'no changes') + ' (' + data.total + ' rows processed).'
    if (data.errors && data.errors.length > 0) msg += '\n' + data.errors.join('\n')
    message.textContent = msg
    message.style.whiteSpace = 'pre-line'
    message.className = 'import-modal-message is-success'
    message.style.display = ''
    if (currentImportType === 'products' || currentImportType === 'images') refreshResource(productsResource).catch(() => {})
  } catch (err) {
    message.textContent = err.message || 'Import failed.'
    message.className = 'import-modal-message is-error'
    message.style.display = ''
  } finally {
    submitBtn.disabled = false
    submitBtn.textContent = 'Import'
  }
}

function initImport() {
  document.getElementById('import-products-btn')?.addEventListener('click', () => openImportModal('products'))
  document.getElementById('import-variants-btn')?.addEventListener('click', () => openImportModal('variants'))
  document.getElementById('import-images-btn')?.addEventListener('click', () => openImportModal('images'))
  document.getElementById('import-modal-close')?.addEventListener('click', closeImportModal)
  document.getElementById('import-modal-cancel')?.addEventListener('click', closeImportModal)
  document.getElementById('import-modal-submit')?.addEventListener('click', submitImport)
  document.getElementById('import-modal')?.addEventListener('click', (e) => { if (e.target.id === 'import-modal') closeImportModal() })
}

// ========== Product Export ==========

const exportableFields = [
  { key: 'sku', label: 'SKU' }, { key: 'name', label: 'Name' }, { key: 'category', label: 'Category' },
  { key: 'status', label: 'Status' }, { key: 'productType', label: 'Type' }, { key: 'price', label: 'Price' },
  { key: 'inventory', label: 'Inventory' }, { key: 'invStockTo', label: 'Inv Stock To' }, { key: 'invMin', label: 'Inv Min' },
  { key: 'posNum', label: 'Position' }, { key: 'shortDescription', label: 'Short Desc' }, { key: 'longDescription', label: 'Long Desc' },
  { key: 'wgt', label: 'Weight' }, { key: 'lng', label: 'Length' }, { key: 'wdth', label: 'Width' }, { key: 'hght', label: 'Height' },
  { key: 'tags', label: 'Tags' }, { key: 'vnName', label: 'Vendor Name' }, { key: 'vnPrice', label: 'Vendor Price' },
  { key: 'compName', label: 'Comp Name' }, { key: 'compPrice', label: 'Comp Price' }, { key: 'shelfNum', label: 'Shelf #' }
]
const variantExportKeys = ['sku','name','price','inventory','invStockTo','invMin','status','wgt','lng','wdth','hght','shortDescription','longDescription']

function openExportModal() {
  const modal = document.getElementById('export-modal')
  const fieldsList = document.getElementById('export-fields-list')
  const catsList = document.getElementById('export-categories-list')
  const typesList = document.getElementById('export-types-list')
  if (fieldsList && !fieldsList.children.length) {
    exportableFields.forEach(f => {
      const label = document.createElement('label'); label.className = 'export-checkbox'
      const cb = document.createElement('input'); cb.type = 'checkbox'; cb.checked = true; cb.dataset.key = f.key
      label.appendChild(cb); label.appendChild(document.createTextNode(' ' + f.label)); fieldsList.appendChild(label)
    })
  }
  if (catsList && !catsList.children.length) {
    const cats = productsResource.fields.find(f => f.name === 'category')?.options || []
    cats.forEach(c => {
      const label = document.createElement('label'); label.className = 'export-checkbox'
      const cb = document.createElement('input'); cb.type = 'checkbox'; cb.checked = true; cb.dataset.cat = c
      label.appendChild(cb); label.appendChild(document.createTextNode(' ' + c)); catsList.appendChild(label)
    })
  }
  if (typesList && !typesList.children.length) {
    ;['Simple','Variant','Associated','Combo'].forEach(t => {
      const label = document.createElement('label'); label.className = 'export-checkbox'
      const cb = document.createElement('input'); cb.type = 'checkbox'; cb.checked = true; cb.dataset.type = t
      label.appendChild(cb); label.appendChild(document.createTextNode(' ' + t)); typesList.appendChild(label)
    })
  }
  modal.style.display = ''
}

function closeExportModal() { document.getElementById('export-modal').style.display = 'none' }

function csvSafe(val) {
  if (val == null) return ''
  const s = String(val)
  if (s.includes(',') || s.includes('"') || s.includes('\n')) return '"' + s.replace(/"/g, '""') + '"'
  return s
}

async function exportProductsCsv() {
  const selectedFields = []; document.querySelectorAll('#export-fields-list input:checked').forEach(cb => selectedFields.push(cb.dataset.key))
  const selectedCats = []; document.querySelectorAll('#export-categories-list input:checked').forEach(cb => selectedCats.push(cb.dataset.cat))
  const selectedTypes = []; document.querySelectorAll('#export-types-list input:checked').forEach(cb => selectedTypes.push(cb.dataset.type))
  const includeVariants = document.getElementById('export-include-variants')?.checked || false
  if (!selectedFields.length) { alert('Select at least one field.'); return }
  const entry = state.products
  if (!entry?.items?.length) { alert('No products loaded.'); return }
  let items = entry.items.filter(p => {
    if (selectedCats.length && !selectedCats.includes(p.category)) return false
    if (selectedTypes.length && !selectedTypes.includes(p.productType || 'Simple')) return false
    return true
  })
  const headers = includeVariants ? ['Row Type', 'Parent SKU', ...selectedFields] : [...selectedFields]
  const rows = [headers]
  let variants = {}
  if (includeVariants) {
    try {
      const res = await fetch('/api/product_variants.php', { headers: { 'X-CSRF-Token': csrfToken } })
      const data = await res.json()
      if (Array.isArray(data.items)) data.items.forEach(v => { if (!variants[v.productId]) variants[v.productId] = []; variants[v.productId].push(v) })
    } catch (e) { console.error('Failed to fetch variants', e) }
  }
  items.forEach(p => {
    const row = includeVariants ? ['Product', ''] : []
    selectedFields.forEach(key => row.push(csvSafe(p[key])))
    rows.push(row)
    if (includeVariants && variants[p.id]) {
      variants[p.id].forEach(v => {
        const vRow = ['Variant', p.sku || '']
        selectedFields.forEach(key => vRow.push(variantExportKeys.includes(key) ? csvSafe(v[key]) : ''))
        rows.push(vRow)
      })
    }
  })
  const csv = rows.map(r => r.join(',')).join('\n')
  const blob = new Blob([csv], { type: 'text/csv' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a'); a.href = url; a.download = 'products-export.csv'; a.click()
  URL.revokeObjectURL(url)
  closeExportModal()
}

function initExport() {
  document.getElementById('export-products-btn')?.addEventListener('click', openExportModal)
  document.getElementById('export-modal-close')?.addEventListener('click', closeExportModal)
  document.getElementById('export-modal-cancel')?.addEventListener('click', closeExportModal)
  document.getElementById('export-modal-submit')?.addEventListener('click', exportProductsCsv)
  document.getElementById('export-modal')?.addEventListener('click', (e) => { if (e.target.id === 'export-modal') closeExportModal() })
}

// ========== Reports ==========

function initReports() {
  document.getElementById('run-report-btn')?.addEventListener('click', generateReport)
  document.querySelectorAll('.report-tab').forEach(tab => {
    tab.addEventListener('click', () => {
      document.querySelectorAll('.report-tab').forEach(t => t.classList.remove('active'))
      tab.classList.add('active')
      document.querySelectorAll('.report-panel').forEach(p => p.style.display = 'none')
      const target = document.getElementById(tab.dataset.target)
      if (target) target.style.display = ''
    })
  })
}

async function generateReport() {
  const activeTab = document.querySelector('.report-tab.active')
  const reportType = activeTab?.dataset.report || 'sales-volume'
  const period = document.getElementById('report-period')?.value || 'last7'
  const resultArea = document.getElementById('report-results')
  if (!resultArea) return
  resultArea.innerHTML = '<div class="report-loading">Loading...</div>'
  try {
    let url = '/api/reports.php?type=' + reportType + '&period=' + period
    if (reportType === 'product-sales') {
      const search = document.getElementById('report-product-search')?.value || ''
      if (search) url += '&search=' + encodeURIComponent(search)
    }
    const res = await fetch(url, { headers: { 'X-CSRF-Token': csrfToken } })
    const data = await res.json()
    if (!res.ok) { resultArea.innerHTML = '<div class="notice is-error">' + (data.error || 'Report failed') + '</div>'; return }
    if (reportType === 'sales-volume') renderSalesVolumeReport(data, resultArea)
    else if (reportType === 'product-sales') renderProductSalesReport(data, resultArea)
  } catch (err) { resultArea.innerHTML = '<div class="notice is-error">' + err.message + '</div>' }
}

function renderSalesVolumeReport(data, container) {
  let html = '<div class="report-kpi-grid">'
  html += '<div class="report-kpi"><div class="report-kpi-value">$' + Number(data.totalRevenue || 0).toFixed(2) + '</div><div class="report-kpi-label">Total Revenue</div></div>'
  html += '<div class="report-kpi"><div class="report-kpi-value">' + (data.orderCount || 0) + '</div><div class="report-kpi-label">Orders</div></div>'
  html += '<div class="report-kpi"><div class="report-kpi-value">$' + Number(data.avgOrderValue || 0).toFixed(2) + '</div><div class="report-kpi-label">Avg Order Value</div></div>'
  html += '<div class="report-kpi"><div class="report-kpi-value">' + (data.itemsSold || 0) + '</div><div class="report-kpi-label">Items Sold</div></div>'
  html += '</div>'
  if (data.daily && data.daily.length) {
    html += '<table class="report-table"><thead><tr><th>Date</th><th>Orders</th><th>Revenue</th></tr></thead><tbody>'
    data.daily.forEach(d => { html += '<tr><td>' + d.date + '</td><td>' + d.orders + '</td><td>$' + Number(d.revenue).toFixed(2) + '</td></tr>' })
    html += '</tbody></table>'
  }
  container.innerHTML = html
}

function renderProductSalesReport(data, container) {
  if (!data.products || !data.products.length) { container.innerHTML = '<div class="notice">No products found.</div>'; return }
  let html = '<table class="report-table"><thead><tr><th>Product</th><th>SKU</th><th>Qty Sold</th><th>Revenue</th></tr></thead><tbody>'
  data.products.forEach(p => { html += '<tr><td>' + escapeHtml(p.name || '') + '</td><td>' + escapeHtml(p.sku || '') + '</td><td>' + (p.qtySold || 0) + '</td><td>$' + Number(p.revenue || 0).toFixed(2) + '</td></tr>' })
  html += '</tbody></table>'
  container.innerHTML = html
}

initImport()
initExport()
initReports()

init()
