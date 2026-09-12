#!/bin/bash

INPUT_FILE="domain.txt"
ALB_DNS="spi-cluster1-alb01-854033008.ap-south-1.elb.amazonaws.com"

while read DOMAIN; do

  echo "=============================="
  echo "Processing: $DOMAIN"
  echo "=============================="

  ########################################
  # 1. Create Hosted Zone
  ########################################
  HOSTED_ZONE_ID=$(aws route53 create-hosted-zone \
    --name "$DOMAIN" \
    --caller-reference "$(date +%s)-$DOMAIN" \
    --query 'HostedZone.Id' \
    --output text | sed 's|/hostedzone/||')

  echo "Hosted Zone ID: $HOSTED_ZONE_ID"

  ########################################
  # 2. Request ACM Certificate
  ########################################
  CERT_ARN=$(aws acm request-certificate \
    --domain-name "$DOMAIN" \
    --subject-alternative-names "*.$DOMAIN" \
    --validation-method DNS \
    --query 'CertificateArn' \
    --output text)

  echo "Certificate ARN: $CERT_ARN"

  # Wait a few seconds for ACM to generate validation records
  sleep 10

  ########################################
  # 3. Get DNS Validation Record
  ########################################
  VALIDATION_JSON=$(aws acm describe-certificate \
    --certificate-arn "$CERT_ARN")

  CNAME_NAME=$(echo $VALIDATION_JSON | jq -r '.Certificate.DomainValidationOptions[0].ResourceRecord.Name')
  CNAME_VALUE=$(echo $VALIDATION_JSON | jq -r '.Certificate.DomainValidationOptions[0].ResourceRecord.Value')

  echo "Validation CNAME:"
  echo "$CNAME_NAME -> $CNAME_VALUE"

  ########################################
  # Add CNAME to Route53
  ########################################
  cat > cname.json <<EOF
{
  "Changes": [{
    "Action": "UPSERT",
    "ResourceRecordSet": {
      "Name": "$CNAME_NAME",
      "Type": "CNAME",
      "TTL": 300,
      "ResourceRecords": [{
        "Value": "$CNAME_VALUE"
      }]
    }
  }]
}
EOF

  aws route53 change-resource-record-sets \
    --hosted-zone-id "$HOSTED_ZONE_ID" \
    --change-batch file://cname.json

  echo "CNAME added for validation"

  ########################################
  # 4. Create ALB Alias Records
  ########################################

  cat > alb.json <<EOF
{
  "Changes": [
    {
      "Action": "UPSERT",
      "ResourceRecordSet": {
        "Name": "$DOMAIN",
        "Type": "A",
        "AliasTarget": {
          "HostedZoneId": "ZP97RAFLXTNZK",
          "DNSName": "$ALB_DNS",
          "EvaluateTargetHealth": false
        }
      }
    },
    {
      "Action": "UPSERT",
      "ResourceRecordSet": {
        "Name": "*.$DOMAIN",
        "Type": "A",
        "AliasTarget": {
          "HostedZoneId": "ZP97RAFLXTNZK",
          "DNSName": "$ALB_DNS",
          "EvaluateTargetHealth": false
        }
      }
    }
  ]
}
EOF

  aws route53 change-resource-record-sets \
    --hosted-zone-id "$HOSTED_ZONE_ID" \
    --change-batch file://alb.json

  echo "ALB records created for $DOMAIN"

done < "$INPUT_FILE"
