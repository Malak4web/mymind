#!/usr/bin/env node
/**
 * Repairs Tailwind colour classes that reference shades outside the scale.
 *
 * The codebase accumulated 57 distinct classes on shades like `slate-955`,
 * `violet-650` and `border-slate-805`. Tailwind only emits 50/100…900/950, so
 * every one of them compiled to nothing: elements silently inherited instead
 * of taking the colour the author wrote. `dark:bg-slate-955` sat on the app
 * root, which is why dark mode rendered dark cards on a light page.
 *
 * Each bad shade is snapped to the nearest valid one (ties round down).
 *
 *   node scripts/fix-dead-classes.mjs          # rewrite files
 *   node scripts/fix-dead-classes.mjs --check  # exit 1 if any remain (CI)
 */

import { readFileSync, writeFileSync, readdirSync, statSync } from 'node:fs'
import { join, extname } from 'node:path'

const VALID = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950]

const PREFIXES = [
  'bg', 'text', 'border', 'from', 'to', 'via', 'ring', 'shadow', 'divide',
  'decoration', 'outline', 'placeholder', 'accent', 'fill', 'stroke', 'caret',
]

const FAMILIES = [
  'slate', 'gray', 'zinc', 'neutral', 'stone', 'red', 'orange', 'amber',
  'yellow', 'lime', 'green', 'emerald', 'teal', 'cyan', 'sky', 'blue',
  'indigo', 'violet', 'purple', 'fuchsia', 'pink', 'rose',
]

// Shades defined by the project's own @theme block are legitimate.
const THEME_SHADES = new Set()
try {
  const theme = readFileSync('src/style.css', 'utf8')
  for (const m of theme.matchAll(/--color-([a-z]+)-(\d{2,3})\s*:/g)) {
    THEME_SHADES.add(`${m[1]}-${m[2]}`)
  }
} catch {
  /* style.css is optional for --check runs */
}

/** Nearest valid shade; ties round down to the lighter value. */
function snap(shade) {
  let best = VALID[0]
  let bestDist = Infinity
  for (const v of VALID) {
    const d = Math.abs(v - shade)
    if (d < bestDist) {
      bestDist = d
      best = v
    }
  }
  return best
}

const RX = new RegExp(
  `\\b(${PREFIXES.join('|')})-(${FAMILIES.join('|')})-(\\d{2,3})\\b`,
  'g'
)

function walk(dir, out = []) {
  for (const entry of readdirSync(dir)) {
    if (entry === 'node_modules' || entry === 'dist' || entry.startsWith('.')) continue
    const full = join(dir, entry)
    const st = statSync(full)
    if (st.isDirectory()) walk(full, out)
    else if (['.vue', '.js', '.html', '.css'].includes(extname(full))) out.push(full)
  }
  return out
}

const check = process.argv.includes('--check')
const files = walk('src').concat(['index.html'])

const replacements = new Map()
let touchedFiles = 0
let total = 0
const offenders = []

for (const file of files) {
  const src = readFileSync(file, 'utf8')
  let fileHits = 0

  const out = src.replace(RX, (match, prefix, family, shadeStr) => {
    const shade = Number(shadeStr)
    if (VALID.includes(shade)) return match
    if (THEME_SHADES.has(`${family}-${shadeStr}`)) return match

    const fixed = `${prefix}-${family}-${snap(shade)}`
    replacements.set(`${match} → ${fixed}`, (replacements.get(`${match} → ${fixed}`) ?? 0) + 1)
    fileHits++
    total++
    offenders.push(`${file}: ${match}`)
    return fixed
  })

  if (fileHits > 0) {
    touchedFiles++
    if (!check) writeFileSync(file, out)
  }
}

if (check) {
  if (total === 0) {
    console.log('✓ no dead Tailwind colour shades')
    process.exit(0)
  }
  console.error(`✗ ${total} dead Tailwind colour shade(s) in ${touchedFiles} file(s).`)
  console.error('  These compile to nothing — the element silently inherits its colour.')
  for (const line of offenders.slice(0, 25)) console.error(`    ${line}`)
  if (offenders.length > 25) console.error(`    …and ${offenders.length - 25} more`)
  console.error('\n  Run: node scripts/fix-dead-classes.mjs')
  process.exit(1)
}

console.log(`Rewrote ${total} occurrence(s) across ${touchedFiles} file(s):\n`)
for (const [pair, count] of [...replacements.entries()].sort((a, b) => b[1] - a[1])) {
  console.log(`  ${String(count).padStart(4)}×  ${pair}`)
}
