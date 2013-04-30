## Overview

Proxy servers take all incoming API requests and decide what to do with them. There are no special requirements needed on the server. 

Make any necessary changes to the AWS configuration to connect to your database.

Change the base URL it's looking for to register the proxy handler on line 164 of `index.php`. Currently set to 'filtrme', so all requests to domain.com/filtrme/* will be forwarded by the Proxy. Since API calls to the application servers live under /api/, your proxied filter endpoint will look something like this domain.com/filtrme/api/API_METHOD. If using Mashape, they'll provide you with an alias or you can create your own alias. That way API calls look more like this filtrme.p.mashape.com/API_METHOD which then resolves to your base final URL. domain.com resolves to your proxy server which then forwards the requests to your application servers.