#!/usr/bin/env bash
app_dir='/Users/fagathe/workspace/perso/sf-p02-moment-app'
app_host='dev.sf-p02-moment-app.fagathe-dev.fr'
port='9700'
db_driver='mysql'

# enregistrer le nouveau nom de domaine dans le host de la machine
# echo "127.0.0.1\t${app_host}" | sudo tee -a /etc/hosts

echo "lance le service ${db_driver}"
brew services start $db_driver
cd $app_dir
echo 'cd api dir'
echo 'ouvrir le projet sur vscode'
code .
bin/console c:c -n
echo "open http://${app_host}:${port} in browser"
# open http://$app_host:$port
            
# lance le serveur interne de php
php -S $app_host:$port -t public

# stop le service mysql lorsqu'on stop le script
trap "brew services stop ${db_driver}" EXIT