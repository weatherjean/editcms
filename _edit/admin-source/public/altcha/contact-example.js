import './altcha.min.js'

const form = document.querySelector('#contact-form')
const widget = form.querySelector('altcha-widget')
const button = form.querySelector('button[type="submit"]')
const status = document.querySelector('#status')
let sending = false

widget.addEventListener('statechange', (event) => {
  button.disabled = sending || event.detail.state !== 'verified'
})

form.addEventListener('submit', async (event) => {
  event.preventDefault()
  if (sending) return
  const values = Object.fromEntries(new FormData(form))
  if (!values.altcha) {
    status.textContent = 'Please complete verification first.'
    return
  }
  sending = true
  button.disabled = true
  status.textContent = 'Sending…'
  try {
    const response = await fetch('/_edit/api/send-email', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(values),
    })
    const result = await response.json()
    if (!response.ok) throw new Error(result.error || 'Unable to send your message.')
    form.reset()
    status.textContent = 'Your message has been sent.'
  } catch (error) {
    status.textContent = error.message + ' Please verify again before retrying.'
  } finally {
    sending = false
    // Proofs may have been consumed even if delivery or the connection failed.
    widget.reset()
    button.disabled = true
  }
})
