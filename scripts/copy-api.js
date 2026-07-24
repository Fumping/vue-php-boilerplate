import { readdirSync, statSync, mkdirSync, copyFileSync, existsSync } from 'node:fs'
import { join, relative, sep, posix } from 'node:path'

function toPosix(p) {
  return p.split(sep).join(posix.sep)
}

function matchesGlob(pathPosix, pattern) {
  // Ondersteunt: exact match, wildcard * (geen /), dubbele ** (recursief)
  const re = new RegExp(
    '^' +
    pattern
      .replace(/[.+^${}()|[\]\\]/g, '\\$&')
      .replace(/\*\*\//g, '::GLOBSTAR_SLASH::')
      .replace(/\*\*/g, '::GLOBSTAR::')
      .replace(/\*/g, '[^/]*')
      .replace(/::GLOBSTAR_SLASH::/g, '(?:.*/)?')
      .replace(/::GLOBSTAR::/g, '.*') +
    '$'
  )
  return re.test(pathPosix)
}

function isExcluded(relPath, excludes) {
  const p = toPosix(relPath)
  return excludes.some((pattern) => matchesGlob(p, pattern))
}

function isIncluded(relPath, includes) {
  const p = toPosix(relPath)
  return includes.some((pattern) => matchesGlob(p, pattern))
}

function walk(srcDir, destDir, includes, excludes, srcRoot) {
  let copied = 0
  for (const entry of readdirSync(srcDir)) {
    const srcPath = join(srcDir, entry)
    const relPath = relative(srcRoot, srcPath)
    if (isExcluded(relPath, excludes)) continue

    const destPath = join(destDir, entry)
    const stat = statSync(srcPath)
    if (stat.isDirectory()) {
      if (!existsSync(destPath)) mkdirSync(destPath, { recursive: true })
      copied += walk(srcPath, destPath, includes, excludes, srcRoot)
    } else {
      if (!isIncluded(relPath, includes)) continue
      copyFileSync(srcPath, destPath)
      copied += 1
    }
  }
  return copied
}

export function copyApiPlugin(options = {}) {
  const src = options.src ?? 'api'
  const dest = options.dest ?? 'dist/api'
  const include = options.include ?? [
    '.htaccess',
    'index.php',
    'config.example.php',
    'handlers/**/*.php',
    'helpers/**/*.php',
    'storage/.htaccess',
  ]
  const exclude = options.exclude ?? [
    'config.php',
    'config.local.php',
    'storage/**/*.sqlite*',
    'storage/tmp/**',
    '**/.env*',
    '**/*.log',
    '**/*.bak',
  ]

  return {
    name: 'copy-api',
    apply: 'build',
    closeBundle() {
      if (!existsSync(src)) {
        this.warn(`copy-api: source directory "${src}" does not exist, skipping`)
        return
      }
      if (!existsSync(dest)) mkdirSync(dest, { recursive: true })
      const count = walk(src, dest, include, exclude, src)
      console.log(`[copy-api] copied ${count} files from ${src}/ → ${dest}/`)
    },
  }
}
