<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class PreserveListPageContext
{
    private const SESSION_KEY = 'list_page_contexts';

    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $action = $route?->getActionName();
        $controller = is_string($action) && str_contains($action, '@')
            ? strstr($action, '@', true)
            : null;

        if (
            $request->isMethod('GET')
            && is_string($controller)
            && str_ends_with((string) $route?->getName(), '.index')
        ) {
            $contexts = $request->session()->get(self::SESSION_KEY, []);
            $contexts[$controller] = [
                'path' => $request->getPathInfo(),
                'query' => $request->query(),
            ];
            $request->session()->put(self::SESSION_KEY, $contexts);
        }

        $response = $next($request);

        if (! $response instanceof RedirectResponse || ! is_string($controller)) {
            return $response;
        }

        $contexts = $request->session()->get(self::SESSION_KEY, []);
        $context = $contexts[$controller] ?? null;
        if (! is_array($context) || ! is_string($context['path'] ?? null)) {
            return $response;
        }

        $targetUrl = $response->getTargetUrl();
        $target = parse_url($targetUrl);
        $targetPath = $target['path'] ?? null;
        if ($targetPath !== $context['path']) {
            return $response;
        }

        $targetHost = $target['host'] ?? null;
        if ($targetHost !== null && strcasecmp($targetHost, $request->getHost()) !== 0) {
            return $response;
        }

        $targetQuery = [];
        parse_str($target['query'] ?? '', $targetQuery);
        $query = $targetQuery + ($context['query'] ?? []);

        if ($query === []) {
            return $response;
        }

        $response->setTargetUrl(
            $request->getSchemeAndHttpHost()
            .$targetPath
            .'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986),
        );

        return $response;
    }
}
