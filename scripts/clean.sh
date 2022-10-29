#!/bin/bash

shopt -s extglob nullglob

rm -rf scripts .github node_modules .gitignore

mkdir authcred
mv !(authcred|build.sh) authcred