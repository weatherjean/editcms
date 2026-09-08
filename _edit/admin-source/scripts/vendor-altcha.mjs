import { copyFile, mkdir, readFile, writeFile } from 'node:fs/promises'
import { createHash } from 'node:crypto'

const upstream = new URL('../node_modules/altcha/', import.meta.url)
const target = new URL('../public/altcha/', import.meta.url)
const { version } = JSON.parse(await readFile(new URL('package.json', upstream), 'utf8'))
await mkdir(target, { recursive: true })
for (const [source, destination] of [
  ['dist/main/altcha.min.js', 'altcha.min.js'],
  ['LICENSE.txt', 'LICENSE.txt'],
]) {
  await copyFile(new URL(source, upstream), new URL(destination, target))
}
const script = await readFile(new URL('altcha.min.js', target))
await writeFile(new URL('UPSTREAM.json', target), JSON.stringify({
  package: 'altcha', version, license: 'MIT',
  source: `https://registry.npmjs.org/altcha/-/altcha-${version}.tgz`,
  sha256: createHash('sha256').update(script).digest('hex'),
}, null, 2) + '\n')
console.log(`Vendored ALTCHA ${version} widget and MIT license`)
