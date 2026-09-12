#!/bin/bash

DOMAIN_FILE="domain.txt"

GODADDY_KEY="h1JsNzRTdNre_CdPpV8tcD7bvzfvqoq9Cdw"
GODADDY_SECRET="HHueWdTnmKpMFaAsE44xEc"

AWS_PROFILE="default"   # Optional if using multiple AWS profiles

while read DOMAIN; do

    echo "======================================"
    echo "Processing Domain: $DOMAIN"

    # Get Hosted Zone ID from Route53
    HOSTED_ZONE_ID=$(aws route53 list-hosted-zones-by-name \
        --dns-name "$DOMAIN" \
        --query "HostedZones[0].Id" \
        --output text \
        --profile $AWS_PROFILE)

    # Remove '/hostedzone/' from ID
    HOSTED_ZONE_ID=$(echo $HOSTED_ZONE_ID | awk -F/ '{print $3}')

    if [ -z "$HOSTED_ZONE_ID" ] || [ "$HOSTED_ZONE_ID" == "None" ]; then
        echo "Hosted Zone not found for $DOMAIN"
        continue
    fi

    echo "Hosted Zone ID: $HOSTED_ZONE_ID"

    # Fetch NS Records
    # Fetch NS Records
NS_RECORDS=$(aws route53 list-resource-record-sets \
    --hosted-zone-id "$HOSTED_ZONE_ID" \
    --query "ResourceRecordSets[?Type == 'NS' && Name == '$DOMAIN.'].ResourceRecords[].Value" \
    --output text \
    --profile $AWS_PROFILE)

if [ -z "$NS_RECORDS" ]; then
    echo "No NS records found for $DOMAIN"
    continue
fi

echo "Raw NS Records:"
echo "$NS_RECORDS"

# Convert to clean GoDaddy format
NS_JSON=$(echo "$NS_RECORDS" | \
    tr '\t' '\n' | \
    tr -d '\r' | \
    sed 's/\.$//' | \
    awk '{print "\"" $1 "\""}' | \
    paste -sd "," -)

PAYLOAD="{\"nameServers\": [$NS_JSON]}"

echo "Payload:"
echo "$PAYLOAD"

   RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}\n" \
    -X PATCH "https://api.godaddy.com/v1/domains/$DOMAIN" \
    -H "Authorization: sso-key $GODADDY_KEY:$GODADDY_SECRET" \
    -H "Content-Type: application/json" \
    -d "$PAYLOAD")

   echo "$RESPONSE"

   HTTP_CODE=$(echo "$RESPONSE" | grep HTTP_CODE | cut -d':' -f2)

if [ "$HTTP_CODE" == "200" ]; then
    echo "Successfully updated NS for $DOMAIN"
else
    echo "Failed to update NS for $DOMAIN"
    echo "HTTP Response Code: $HTTP_CODE"
fi

done < "$DOMAIN_FILE"
