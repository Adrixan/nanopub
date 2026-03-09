<?php

declare(strict_types=1);

namespace NanoPub\Controllers\ActivityPub;

use NanoPub\Core\Request;
use NanoPub\Core\Response;
use NanoPub\Core\Config;

/**
 * Returns host-meta for discovery.
 */
final class HostMetaController
{
    /**
     * Return host-meta XML.
     * 
     * Route: /.well-known/host-meta
     * 
     * @param Request $request The HTTP request
     */
    public function show(Request $request): void
    {
        $appUrl = Config::get('app.url');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<XRD xmlns="http://docs.oasis-open.org/ns/xri/xrd-1.0">
  <Link rel="lrdd" type="application/xrd+xml" template="' . $appUrl . '/.well-known/webfinger?resource={uri}"/>
</XRD>';

        $response = new Response();
        $response->status(200)
            ->header('Content-Type', 'application/xrd+xml')
            ->content($xml)
            ->send();
    }
}
