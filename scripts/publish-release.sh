#!/usr/bin/env bash
set -euo pipefail
: "${VERSION:?Missing release version}"
: "${GITHUB_SHA:?Missing source commit}"
: "${GH_REPO:?Missing GitHub repository}"
[[ "$VERSION" =~ ^v[0-9]{4}\.[0-9]{2}\.[0-9]{2}\.[0-9]+$ ]] || { echo 'Invalid version' >&2; exit 1; }

cd dist
sha256sum --check SHA256SUMS
zip="_edit-${VERSION}.zip"
[[ -f "$zip" ]] || { echo 'Missing verified ZIP' >&2; exit 1; }

# Published releases are immutable on reruns. An interrupted draft can be resumed.
if draft=$(gh release view "$VERSION" --json isDraft --jq .isDraft 2>/dev/null); then
  if [[ "$draft" == false ]]; then
    echo "Release $VERSION already published; leaving it unchanged."
    exit 0
  fi
else
  gh release create "$VERSION" --target "$GITHUB_SHA" --title "_edit CMS $VERSION" --generate-notes --draft
fi
gh release upload "$VERSION" "$zip" SHA256SUMS --clobber
gh release edit "$VERSION" --draft=false --latest
