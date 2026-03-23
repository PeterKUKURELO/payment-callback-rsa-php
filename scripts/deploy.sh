#!/usr/bin/env bash
set -euo pipefail

STAGE="${1:-preprod}"
REGION="${2:-${AWS_REGION:-us-east-1}}"

if ! command -v composer >/dev/null 2>&1; then
  echo "Error: composer is not installed."
  exit 1
fi

if ! command -v npx >/dev/null 2>&1; then
  echo "Error: npx (Node.js) is not installed."
  exit 1
fi

if [[ ! -f ".env" ]]; then
  echo "Warning: .env not found. Copy .env.example to .env and set values."
fi

echo "Installing PHP dependencies for deployment..."
composer install --no-dev -o

echo "Deploying with Serverless (stage=${STAGE}, region=${REGION})..."
npx serverless deploy --stage "${STAGE}" --region "${REGION}"

echo "Deployment completed."
