#!/bin/bash

shopt -s extglob nullglob

mkdir authcred

for d in !(authcred)/; do
    mv "$d" authcred
done