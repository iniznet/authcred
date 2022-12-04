#!/bin/bash

shopt -s extglob nullglob

rm -rf scripts .github .editorconfig .git .gitignore
rm -rf vite.config.js tailwind.config.js renovate.json readme.md postcss.config.js
rm -rf plugin.json package-lock.json package.json composer.lock composer.json CHANGELOG.md
rm -rf node_modules resources

mkdir authcred
mv !(authcred) authcred