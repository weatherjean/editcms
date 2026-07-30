// Exercise the actual widget package's solver against PHP-generated challenges.
import { pbkdf2, solveChallenge } from '../_edit/admin-source/node_modules/altcha/dist/lib/index.js'
let input = ''
for await (const chunk of process.stdin) input += chunk
const challenge = JSON.parse(input)
const solution = await solveChallenge({ challenge, deriveKey: pbkdf2.deriveKey })
if (!solution) throw new Error('ALTCHA JavaScript solver failed')
process.stdout.write(Buffer.from(JSON.stringify({ challenge, solution })).toString('base64'))
