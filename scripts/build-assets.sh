#!/usr/bin/env bash
# Build the Vite assets without ever leaving public/build empty.
#
# `vite build` empties its output folder before writing, so building straight
# into public/build left the site without manifest.json for the length of the
# build, and every page requested in that window failed with "Vite manifest
# not found". This builds beside the live assets and swaps them in, carrying
# the previous build's hashed files over so pages already open keep working.
set -euo pipefail
cd "$(dirname "$0")/.."

rm -rf public/build-next public/build-old
npx vite build --outDir public/build-next --emptyOutDir

if [ ! -f public/build-next/manifest.json ]; then
  echo "Vite build produced no manifest; keeping the current assets" >&2
  rm -rf public/build-next
  exit 1
fi

if [ -d public/build/assets ]; then
  mkdir -p public/build-next/assets
  cp -rn public/build/assets/. public/build-next/assets/ 2>/dev/null || true
fi
if [ -d public/build ]; then mv public/build public/build-old; fi
mv public/build-next public/build
rm -rf public/build-old
echo "Assets built and swapped in"
