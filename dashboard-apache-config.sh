#!/bin/bash

INPUT_FILE="domain.txt"
APACHE_TEMPLATE="/opt/devops/AWS_migration/apache/default_apache-foundation.conf"
APACHE_SITE_DIR="/etc/apache2/sites-available"

while read DOMAIN; do

  echo "=============================="
  echo "Processing: $DOMAIN"
  echo "=============================="

  # Skip empty lines
  if [ -z "$DOMAIN" ]; then
    continue
  fi

  safe_domainname=$(echo "$DOMAIN" | tr '.' '_')

  CONF_FILE="$APACHE_SITE_DIR/$DOMAIN.conf"

  # Copy template
  sudo cp -avf "$APACHE_TEMPLATE" "$CONF_FILE"

  # Replace placeholders
  sudo sed -i "s/maindomain.in/$DOMAIN/g" "$CONF_FILE"
  sudo sed -i "s/dev_siliconpractice_in/$safe_domainname/g" "$CONF_FILE"

  # Enable site
  sudo a2ensite "$DOMAIN.conf"

  echo "Enabled: $DOMAIN"

done < "$INPUT_FILE"

# Validate Apache config once
#sudo apache2ctl configtest

# Reload Apache
#sudo systemctl reload apache2
#
#unison apache-oneway
#ansible-reload
