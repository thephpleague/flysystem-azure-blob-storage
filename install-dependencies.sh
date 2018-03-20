#!/bin/bash

set -e

docker run -e executable=blob --name azureblob -d -t -p 10000:10000 arafato/azurite
composer install --prefer-dist
sleep 15
curl -X PUT -H "$x_ms_date_h" -H "$x_ms_version_h" -H "Content-Length: 0" -H "$authorization_header" "http://0.0.0.0:10000/devstoreaccount1/mycontainer?restype=container"
