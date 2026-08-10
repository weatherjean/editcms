import test from 'node:test'
import assert from 'node:assert/strict'
import { effectScope, ref } from '../_edit/admin-source/node_modules/vue/dist/vue.runtime.esm-bundler.js'
import { useContentList } from '../_edit/admin-source/src/composables/useContentList.js'

function fixture(singleton = false) {
  const pending = [], redirects = []
  const scope = effectScope()
  const type = ref('property'), postType = ref({ singleton })
  const state = scope.run(() => useContentList((method, path) => new Promise((resolve, reject) => {
    pending.push({ method, path, resolve, reject })
  }), type, postType, { replace: path => redirects.push(path) }))
  return { ...state, type, pending, redirects, stop: () => scope.stop() }
}

test('Loading, failure, retry and a successfully empty result remain distinct', async () => {
  const f = fixture()
  try {
    const failed = f.loadContent()
    assert.equal(f.loadingContent.value, true)
    f.pending[0].reject(new Error('Server unavailable'))
    await failed
    assert.equal(f.loadError.value, 'Server unavailable')
    assert.equal(f.loadingContent.value, false)
    const retry = f.loadContent()
    assert.equal(f.loadError.value, '')
    assert.equal(f.loadingContent.value, true)
    f.pending[1].resolve([])
    await retry
    assert.deepEqual(f.contentItems.value, [])
    assert.equal(f.loadError.value, '')
    assert.equal(f.loadingContent.value, false)
  } finally { f.stop() }
})

test('An older result or error cannot replace the next type after navigation', async () => {
  for (const failure of [false, true]) {
    const f = fixture()
    try {
      const old = f.loadContent()
      f.type.value = 'page'
      const next = f.loadContent()
      assert.equal(f.pending[1].path, '/page')
      f.pending[1].resolve([{ id: 2 }])
      await next
      if (failure) f.pending[0].reject(new Error('Old failure'))
      else f.pending[0].resolve([{ id: 1 }])
      await old
      assert.deepEqual(f.contentItems.value, [{ id: 2 }])
      assert.equal(f.loadError.value, '')
      assert.equal(f.loadingContent.value, false)
    } finally { f.stop() }
  }
})

test('Disposing a view prevents late singleton redirects', async () => {
  const f = fixture(true)
  const result = f.loadContent()
  f.stop()
  f.pending[0].resolve([{ id: 1 }])
  await result
  assert.deepEqual(f.redirects, [])
})

test('Singleton redirects happen only after a successful current response', async () => {
  for (const items of [[], [{ id: 3 }]]) {
    const f = fixture(true)
    try {
      const result = f.loadContent()
      f.pending[0].resolve(items)
      await result
      assert.deepEqual(f.redirects, [items.length ? '/property/3' : '/property/create'])
    } finally { f.stop() }
  }
})
