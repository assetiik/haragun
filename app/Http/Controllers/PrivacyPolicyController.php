<?php

namespace App\Http\Controllers;

use App\Support\SiteContent;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('Site', weight: 3)]
class PrivacyPolicyController extends Controller
{
    #[Endpoint(
        title: 'Политика конфиденциальности',
        description: 'Публичная политика конфиденциальности: заголовок и список загруженных документов (url). Без авторизации. Язык заголовка — Accept-Language или ?locale=ru|kz|en.',
    )]
    public function __invoke(): JsonResponse
    {
        return response()->json(SiteContent::privacyPolicy());
    }
}
