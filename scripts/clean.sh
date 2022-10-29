#!/bin/bash

shopt -s extglob nullglob

rm -rf scripts .github node_modules .git .gitignore

mkdir authcred
mv !(authcred) authcred
mv .editorconfig authcred