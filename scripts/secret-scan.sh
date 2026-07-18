#!/usr/bin/env bash
set -euo pipefail

patterns='(BEGIN (RSA |EC |OPENSSH )?PRIVATE KEY|AKIA[0-9A-Z]{16}|sk_live_[A-Za-z0-9]+|-----BEGIN CERTIFICATE-----)'
if git grep -nEI "$patterns" -- . ':!.hermes/**'; then
  printf '%s
' 'Potential committed secret detected.' >&2
  exit 1
fi
printf '%s
' 'No high-confidence secret patterns found in tracked content.'
