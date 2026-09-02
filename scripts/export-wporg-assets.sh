#!/usr/bin/env bash
# Export WordPress.org listing assets for 4wp-faq.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SRC="${ROOT}/assets/images"
OUT="${ROOT}/.wordpress-org/assets"

if [[ ! -f "${SRC}/icon.png" ]] || [[ ! -f "${SRC}/banner.png" ]]; then
	echo "Missing ${SRC}/icon.png or banner.png" >&2
	exit 1
fi

mkdir -p "${OUT}"

sips -z 128 128 "${SRC}/icon.png" --out "${OUT}/icon-128x128.png" >/dev/null
sips -z 256 256 "${SRC}/icon.png" --out "${OUT}/icon-256x256.png" >/dev/null
sips -z 250 772 "${SRC}/banner.png" --out "${OUT}/banner-772x250.png" >/dev/null
sips -z 500 1544 "${OUT}/banner-772x250.png" --out "${OUT}/banner-1544x500.png" >/dev/null

echo "Wrote assets to ${OUT}"
