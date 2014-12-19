asset-proxy.php
===============

A PHP proxy to make your website assets as high available as your site. It is aimed at simple replacement of your CDN URL's for your assets with this proxy. This makes sure that your assets have the same availablity as your website and at the same time circumvent same origin restrictions.

Install asset-proxy.php in your webroot. Then replace all references in your HTML from:

<pre>
href="http://fonts.googleapis.com/css?family=Droid+Sans:400,700"
</pre>

to:

<pre>
href="/asset-proxy.php/fonts.googleapis.com/css?family=Droid+Sans:400,700"
</pre>

Make sure you edit the list of allowed hostnames in the header of the PHP file and that you set an appropriate refresh time (in seconds). If the assets are not available upon refresh the stale files are served.
