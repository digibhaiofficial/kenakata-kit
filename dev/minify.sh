#!/bin/sh
# Make the .min.css / .min.js files the plugin loads on the shop (the readable files stay the source).
# Usage: sh dev/minify.sh   (needs esbuild: npx esbuild, or ESBUILD=/path/to/esbuild)
set -e
cd "$(dirname "$0")/../kenakata-kit/assets"
E="${ESBUILD:-npx --yes esbuild@0.28.2}"
for f in kenakata-kit kenakata-kit-checkout kenakata-kit-account kenakata-kit-order kenakata-kit-product store-dashboard courier-check admin kenakata-kit-compat manual-pay kenakata-kit-wishlist kenakata-kit-search kenakata-kit-popup kenakata-kit-bbar kenakata-kit-sticky kenakata-kit-readmore kenakata-kit-carousel kenakata-kit-terms; do
  [ -f "$f.css" ] && $E "$f.css" --minify --log-level=warning --outfile="$f.min.css"
done
for f in kenakata-kit store-dashboard courier-check admin kenakata-kit-incomplete manual-pay; do
  [ -f "$f.js" ] && $E "$f.js" --minify --target=es2017 --log-level=warning --outfile="$f.min.js"
done
ls -la *.min.*
