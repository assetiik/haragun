<?php

namespace App\Support;

use App\Models\SiteSetting;

final class SiteContent
{
    /**
     * @return array{title: string, version: ?string, text: ?string}
     */
    public static function about(): array
    {
        return SiteSetting::current()->aboutPayload();
    }

    /**
     * @return array{title: string, phone: ?string, phone_raw: ?string, email: ?string, address: ?string, hours: ?string}
     */
    public static function contacts(): array
    {
        return SiteSetting::current()->contactsPayload();
    }

    /**
     * @return array{title: string, documents: list<array{url: string, name: string}>}
     */
    public static function privacyPolicy(): array
    {
        return SiteSetting::current()->privacyPolicyPayload();
    }

    /**
     * @return array{title: string, documents: list<array{url: string, name: string}>}
     */
    public static function offer(): array
    {
        return SiteSetting::current()->offerPayload();
    }
}
