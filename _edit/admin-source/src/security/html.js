import DOMPurify from 'dompurify'

const classes = new Set([
  'ql-align-center', 'ql-align-right', 'ql-align-justify', 'ql-direction-rtl',
  'ql-size-small', 'ql-size-large', 'ql-size-huge', 'ql-font-serif', 'ql-font-monospace',
  'ql-code-block', 'ql-code-block-container',
  ...Array.from({ length: 8 }, (_, index) => `ql-indent-${index + 1}`)
])
DOMPurify.addHook('uponSanitizeAttribute', (_, attribute) => {
  if (attribute.attrName === 'class') {
    attribute.attrValue = attribute.attrValue.split(/\s+/).filter(value => classes.has(value)).join(' ')
  }
  if (attribute.attrName === 'href' || attribute.attrName === 'src') {
    try {
      const protocol = new URL(attribute.attrValue, window.location.origin).protocol
      const allowed = attribute.attrName === 'src' ? ['http:', 'https:'] : ['http:', 'https:', 'mailto:', 'tel:']
      if (!allowed.includes(protocol)) attribute.keepAttr = false
    } catch { attribute.keepAttr = false }
  }
})

export function sanitizeEditorHtml(value) {
  return DOMPurify.sanitize(value || '', {
    ALLOWED_TAGS: ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'a', 'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'code', 'pre', 'span', 'div', 'img', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'sub', 'sup'],
    ALLOWED_ATTR: ['class', 'href', 'title', 'src', 'alt', 'width', 'height', 'colspan', 'rowspan', 'data-list'],
    ALLOW_DATA_ATTR: false,
    ALLOW_ARIA_ATTR: false
  })
}
