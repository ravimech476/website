#!/bin/bash

# Enter the Single site Customer name
echo "Please Enter the Customer name"
read customername

cp -avf /opt/devops/newcustomer-setup/subdomain/subdomain_template_siliconpractice_in "/sync/sitedata/foundation_live/${customername}_siliconpractice_in"


echo "Creating new MySQL database..."
mysql -h localhost -e "CREATE DATABASE wpdb_${customername} /*\!40100 DEFAULT CHARACTER SET utf8 */;"

echo "Database successfully created!"

# Restore demo template database into new customer database
echo "Restore demo template database into new customer database"

mysql -h localhost "wpdb_${customername}" < /opt/devops/newcustomer-setup/subdomain/subdomain_template_siliconpractice_in_mysql.sql

echo "Demo template database restore completed"


# Update site name into newly created database
SQL_QUERY="UPDATE \`footfallv6_options\` SET \`option_value\` = 'https://${customername}.siliconpractice.in/' WHERE \`footfallv6_options\`.\`option_id\` IN (1, 36);"

mysql -h localhost wpdb_${customername} -e "${SQL_QUERY}"

CONFIG_PATH="/sync/sitedata/foundation_live/${customername}_siliconpractice_in/www/wp-config.php"

sed -i "s/wpdb_demotemplate/wpdb_${customername}/" "${CONFIG_PATH}"

#####################################################################
PLUGIN_PATH="/sync/sitedata/foundation_live/${customername}_siliconpractice_in/www/wp-content/plugins/spForms"

cd "$PLUGIN_PATH" && sed -i "s|demotemplate|${customername}|g" .env
cd "$PLUGIN_PATH/includes" && sed -i "s|demotemplate|${customername}|g" form_submission.php

#######################Apache conf########################
cp -avf /opt/devops/newcustomer-setup/subdomain/subdomain.siliconpractice.in.conf "/etc/apache2/sites-available/${customername}.siliconpractice.in.conf"

sed -i "s/subdomain/${customername}/" "/etc/apache2/sites-available/${customername}.siliconpractice.in.conf"
a2ensite ${customername}.siliconpractice.in.conf
###################################################
# Generate serialized string using PHP
serialized=$(php -r "
\$customername = '$customername';
\$data = [
    \"${customername}.siliconpractice.in\" => [[
        \"internalcode\" => \$customername,
        \"domain\"       => \"${customername}.siliconpractice.in\",
        \"shortname\"    => \$customername,
        \"fullname\"     => \$customername,
        \"branchname\"   => \$customername,
        \"phone\"        => \"\",
        \"email\"        => \"\"
    ]]
];
echo addslashes(serialize(\$data));
")
#SQL_QUERY3="UPDATE \`footfallv6_options\` SET \`option_value\` = '$serialized' WHERE option_name = 'spm_multisite' AND WHERE `option_id` = 19564;"
SQL_QUERY3="UPDATE \`footfallv6_options\` SET \`option_value\` = '$serialized' WHERE option_name = 'spm_multisite' AND \`option_id\` = 19564;"

mysql -h localhost wpdb_${customername} -e "${SQL_QUERY3}"

SQL_QUERY4="UPDATE footfallv6_staff_metas SET value='${customername}' WHERE id=2;"

mysql -h localhost "wpdb_${customername}" -e "$SQL_QUERY4"
