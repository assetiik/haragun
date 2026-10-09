<?php

namespace App\Http\Middleware;

use App\Enums\AppLocale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetApiLocale
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale($this->locale($request));

        return $next($request);
    }

    private function locale(Request $request): string
    {
        $query = $request->query('locale');

        if (is_string($query) && AppLocale::tryFrom($query) !== null) {
            return $query;
        }

        $header = strtolower(trim(strtok((string) $request->header('Accept-Language', ''), ',') ?: ''));
        $header = substr($header, 0, 2);

        if ($header === 'kk') {
            $header = 'kz';
        }

        if (AppLocale::tryFrom($header) !== null) {
            return $header;
        }

        return AppLocale::Ru->value;
    }
}
