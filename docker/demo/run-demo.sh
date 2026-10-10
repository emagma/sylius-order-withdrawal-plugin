#!/usr/bin/env bash
set -euo pipefail

IMAGE="emagma/owp-demo"
REPO="https://github.com/emagma/sylius-order-withdrawal-plugin.git"
DIR="sylius-order-withdrawal-plugin"

if [ ! -d "$DIR" ]; then
    echo "Cloning the repository…"
    git clone --depth 1 "$REPO"
fi

cd "$DIR"

echo "Building the demo image (this may take a few minutes on a cold build)…"
docker build -f docker/demo/Dockerfile --build-arg SYMFONY_VERSION="7.4.*" -t "$IMAGE" .

echo ""
echo "Starting the demo…"
echo ""
echo "  Shop form  →  http://localhost:8080/en_US/withdrawal-request"
echo "  Admin      →  http://localhost:8080/admin  (sylius / sylius)"
echo "  Emails     →  http://localhost:8025"
echo ""

docker run --rm -p 8080:80 -p 8025:8025 "$IMAGE"
