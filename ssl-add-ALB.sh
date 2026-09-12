#!/bin/bash

DOMAIN_FILE="domain.txt"

# Your ALB listener ARN (HTTPS :443)
LISTENER_ARN="arn:aws:elasticloadbalancing:ap-south-1:867344429130:listener/app/spi-cluster1-alb01/08f0b30fda21401e/b38e56e50441dc4c"

while read DOMAIN; do

  echo "=============================="
  echo "Processing: $DOMAIN"
  echo "=============================="

  # Skip empty lines
  if [ -z "$DOMAIN" ]; then
    continue
  fi

  ########################################
  # 1. Get ACM Certificate ARN
  ########################################
  CERT_ARN=$(aws acm list-certificates \
    --query "CertificateSummaryList[?DomainName=='$DOMAIN' || DomainName=='*.$DOMAIN'].CertificateArn | [0]" \
    --output text)

  if [ "$CERT_ARN" == "None" ] || [ -z "$CERT_ARN" ]; then
    echo "❌ Certificate not found for $DOMAIN"
    continue
  fi

  echo "✔ Certificate ARN: $CERT_ARN"

  ########################################
  # 2. Attach certificate to ALB listener
  ########################################
  aws elbv2 add-listener-certificates \
    --listener-arn "$LISTENER_ARN" \
    --certificates CertificateArn="$CERT_ARN"

  if [ $? -eq 0 ]; then
    echo "✅ SSL attached for $DOMAIN"
  else
    echo "❌ Failed for $DOMAIN"
  fi

done < "$DOMAIN_FILE"
