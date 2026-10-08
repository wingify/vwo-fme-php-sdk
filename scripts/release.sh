#!/usr/bin/env bash
# Copyright 2024-2026 Wingify Software Pvt. Ltd.
#
# Licensed under the Apache License, Version 2.0 (the "License");
# you may not use this file except in compliance with the License.
# You may obtain a copy of the License at
#
#    http://www.apache.org/licenses/LICENSE-2.0
#
# Unless required by applicable law or agreed to in writing, software
# distributed under the License is distributed on an "AS IS" BASIS,
# WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
# See the License for the specific language governing permissions and
# limitations under the License.
#
# Tag the composer.json version and create a GitHub Release.
# Skips when both already exist (safe for docs-only merges).
#
# Packagist is not called from this script.
# vwo/vwo-fme-php-sdk auto-updates on Packagist from the tag and GitHub Release.
#
# Usage (CI — called by GitHub Actions):
#   ./scripts/release.sh
#
# Usage (local dry-run, no tag push, no GitHub Release):
#   DRY_RUN=1 ./scripts/release.sh

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

VERSION=$(sed -n 's/^[[:space:]]*"version"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' composer.json | head -n 1)
if [ -z "${VERSION}" ]; then
  echo "ERROR: Could not read version from composer.json"
  exit 1
fi

TAG="v${VERSION}"
DRY_RUN="${DRY_RUN:-0}"

echo "=== Version: ${VERSION} / Tag: ${TAG} ==="

tag_exists=0
if ! remote_tags="$(git ls-remote --tags origin "${TAG}")"; then
  echo "ERROR: Could not list tags on origin"
  exit 1
fi
if printf '%s\n' "${remote_tags}" | grep -q "refs/tags/${TAG}$"; then
  tag_exists=1
fi

release_exists=0
if command -v gh >/dev/null 2>&1 && gh release view "${TAG}" >/dev/null 2>&1; then
  release_exists=1
fi

if [ "${tag_exists}" = "1" ] && [ "${release_exists}" = "1" ]; then
  echo "Tag ${TAG} and GitHub Release already exist. Skipping release."
  exit 0
fi

notes_file="$(mktemp)"
trap 'rm -f "${notes_file}"' EXIT

if ! awk -v version="${VERSION}" '
  BEGIN {
    esc = version
    gsub(/\./, "\\.", esc)
    header = "^## \\[" esc "\\]"
  }
  $0 ~ header { found=1; next }
  found && /^## \[/ { exit }
  found { lines[++n] = $0 }
  END {
    if (!found) exit 2
    start=1
    while (start<=n && lines[start] ~ /^[[:space:]]*$/) start++
    end=n
    while (end>=start && lines[end] ~ /^[[:space:]]*$/) end--
    if (end < start) exit 3
    for (i=start; i<=end; i++) print lines[i]
  }
' CHANGELOG.md > "${notes_file}"; then
  echo "ERROR: No CHANGELOG.md section for ${VERSION}"
  exit 1
fi

if [ "${DRY_RUN}" = "1" ]; then
  echo "=== DRY_RUN: skipping tag push and GitHub Release ==="
  if [ "${tag_exists}" = "1" ]; then
    echo "Tag ${TAG} already exists on origin."
  else
    echo "Would push tag ${TAG}"
  fi
  if [ "${release_exists}" = "1" ]; then
    echo "GitHub Release ${TAG} already exists."
  else
    echo "Would create GitHub Release ${TAG} with notes:"
    cat "${notes_file}"
  fi
  exit 0
fi

if [ "${tag_exists}" = "0" ]; then
  if [ -n "${CI:-}" ]; then
    git config user.name "github-actions[bot]"
    git config user.email "github-actions[bot]@users.noreply.github.com"
  fi

  if git tag -l "${TAG}" | grep -q "${TAG}"; then
    echo "Removing stale local tag ${TAG}"
    git tag -d "${TAG}"
  fi

  git tag -a "${TAG}" -m "Release ${VERSION}"
  git push origin "${TAG}"
  echo "=== Pushed tag ${TAG} ==="
else
  echo "Tag ${TAG} already exists on origin."
fi

if [ "${release_exists}" = "1" ]; then
  echo "GitHub Release ${TAG} already exists."
else
  echo "=== Creating GitHub Release ${TAG} ==="
  gh release create "${TAG}" \
    --title "Release ${VERSION}" \
    --notes-file "${notes_file}"
  echo "=== Created GitHub Release ${TAG} ==="
fi

echo "=== Release ${VERSION} done ==="
