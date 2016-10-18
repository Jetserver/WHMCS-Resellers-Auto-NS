# WHMCS-Resellers-Auto-NS

This hook will automatically generate A Records for each NS in the reseller’s main domain dns zone.

How it works ?

Once an order was placed and activated, the hook will send API calls to the server, getting the default nameservers & ips. Next, it will add A records for each NS in the reseller’s main domain. All is done using pure API & access hash (that is already configured in WHMCS).

We created this hook after getting lot’s of support tickets from our resellers, saying that their private NS doesn’t work (since they didn’t created A Records for them).

# Installation

** This hook only works on cPanel/WHM servers **

Upload the hook file to your WHMCS hooks folder (“includes/hooks“).

Next, login to your WHMCS system as admin user, and edit the product (reseller hosting probebly) you want to activate this hook on.

Setup -> Product Services -> Products Services -> (Edit the desired product) -> Module Settings

A configuration box will be added to the product’s module settings tab.

Once a product is ordered and the “create” action was triggered, the hook will generate the necessary A records for each name servers.

Example –

* An order for reseller account was placed for “reseller.com” domain.
* Order was activated, created on the server.
* Hook is triggered
* Hook connects WHM server using API and asks: “What are the ips for your default nameservers ?”
* Hook gets the results, creates an A record for each ip using the reseller’s main domain
```
1.1.1.1 ns1.reseller.com
2.2.2.2 ns2.reseller.com
```

# More Information

https://docs.jetapps.com/category/whmcs-addons/whmcs-auto-ns
