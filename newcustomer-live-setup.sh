# /bin/bash

NEW_DB_USER="dbadmin"
NEW_DB_PASS="XHGofpHe55Zy2Bb36StXfIjM"
NEW_DB_HOST="localhost"


echo "Please Enter the Customer Domain name"
read domainname

echo $domainname > domain.txt

# Replace dots with underscores
safe_domainname=$(echo "$domainname" | tr '.' '_')

cp -avf /opt/devops/newcustomer-setup/subdomain/subdomain_template_siliconpractice_in "/sync/sitedata/foundation_live/${safe_domainname}"


echo "Creating new MySQL database..."
NEW_DB_NAME=wpdb_${safe_domainname}

mysql -h localhost -e "CREATE DATABASE wpdb_${safe_domainname} /*\!40100 DEFAULT CHARACTER SET utf8 */;"

echo "Database successfully created!"

# Restore demo template database into new customer database
echo "Restore demo template database into new customer database"

mysql -h localhost "wpdb_${safe_domainname}" < /opt/devops/newcustomer-setup/subdomain/subdomain_template_siliconpractice_in_mysql.sql

echo "Demo template database restore completed"

# Update site name into newly created database
SQL_QUERY="UPDATE \`footfallv6_options\` SET \`option_value\` = 'https://${domainname}/' WHERE \`footfallv6_options\`.\`option_id\` IN (1, 36);"

mysql -h localhost wpdb_${safe_domainname} -e "${SQL_QUERY}"
##

CONFIG_PATH="/sync/sitedata/foundation_live/${safe_domainname}/www/wp-config.php"

sed -i "s/wpdb_demotemplate/wpdb_${safe_domainname}/" "${CONFIG_PATH}"

#####################################################################
PLUGIN_PATH="/sync/sitedata/foundation_live/${safe_domainname}/www/wp-content/plugins/spForms"

cd "$PLUGIN_PATH" && sed -i "s|demotemplate.siliconpractice.in|${domainname}|g" .env
cd "$PLUGIN_PATH/includes" && sed -i "s|demotemplate.siliconpractice.in|${domainname}|g" form_submission.php


################################ SPM Multisite #############################################
# Generate serialized string using PHP
serialized=$(php -r "
\$customername = '${domainname}';
\$data = [
    \"${domainname}\" => [[
        \"internalcode\" => \$customername,
        \"domain\"       => \"${domainname}\",
        \"shortname\"    => \$customername,
        \"fullname\"     => \$customername,
        \"branchname\"   => \$customername,
        \"phone\"        => \"\",
        \"email\"        => \"\"
    ]]
];
echo addslashes(serialize(\$data));
")

############################Form Thread domain name update #################################################

SQL_QUERY4="UPDATE \`footfallv6_sp_formthread\` SET \`domain\` = '${domainname}' WHERE \`footfallv6_sp_formthread\`.\`domain\` = 'demo.siliconpractice.in';"

mysql -h localhost wpdb_${safe_domainname} -e "${SQL_QUERY4}"

cp -avf /opt/devops/newcustomer-setup/subdomain/robots.txt "/sync/sitedata/foundation_live/${safe_domainname}/www/"
