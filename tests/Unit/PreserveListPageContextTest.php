<?php

namespace Tests\Unit;

use App\Http\Middleware\PreserveListPageContext;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class PreserveListPageContextTest extends TestCase
{
    public function test_redirect_to_the_same_module_list_preserves_page_and_filters(): void
    {
        $middleware = new PreserveListPageContext;
        $session = new Store('test', new ArraySessionHandler(3600));
        $session->start();

        $listRequest = Request::create('/customers?page=4&search=Lan&status=active');
        $listRequest->setLaravelSession($session);
        $listRoute = $this->route('GET', 'customers', 'customers.index', 'index');
        self::assertSame('customers.index', $listRoute->getName());
        $listRequest->setRouteResolver(fn () => $listRoute);

        $middleware->handle($listRequest, fn (): Response => new Response);
        self::assertNotEmpty($session->get('list_page_contexts'));

        $mutationRequest = Request::create('/customers/12', 'PUT');
        $mutationRequest->setLaravelSession($session);
        $mutationRequest->setRouteResolver(fn () => $this->route('PUT', 'customers/{customer}', 'customers.update', 'update'));
        $response = $middleware->handle(
            $mutationRequest,
            fn (): RedirectResponse => new RedirectResponse('http://localhost/customers'),
        );

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(
            'http://localhost/customers?page=4&search=Lan&status=active',
            $response->getTargetUrl(),
        );
    }

    public function test_redirect_to_another_workflow_page_is_not_rewritten(): void
    {
        $middleware = new PreserveListPageContext;
        $session = new Store('test', new ArraySessionHandler(3600));
        $session->start();

        $listRequest = Request::create('/customers?page=4');
        $listRequest->setLaravelSession($session);
        $listRequest->setRouteResolver(fn () => $this->route('GET', 'customers', 'customers.index', 'index'));
        $middleware->handle($listRequest, fn (): Response => new Response);

        $mutationRequest = Request::create('/customers/12', 'PUT');
        $mutationRequest->setLaravelSession($session);
        $mutationRequest->setRouteResolver(fn () => $this->route('PUT', 'customers/{customer}', 'customers.update', 'update'));
        $response = $middleware->handle(
            $mutationRequest,
            fn (): RedirectResponse => new RedirectResponse('http://localhost/customers/12'),
        );

        self::assertSame('http://localhost/customers/12', $response->getTargetUrl());
    }

    public function test_redirect_to_another_host_is_not_rewritten(): void
    {
        $middleware = new PreserveListPageContext;
        $session = new Store('test', new ArraySessionHandler(3600));
        $session->start();

        $listRequest = Request::create('/customers?page=4');
        $listRequest->setLaravelSession($session);
        $listRequest->setRouteResolver(fn () => $this->route('GET', 'customers', 'customers.index', 'index'));
        $middleware->handle($listRequest, fn (): Response => new Response);

        $mutationRequest = Request::create('/customers/12', 'PUT');
        $mutationRequest->setLaravelSession($session);
        $mutationRequest->setRouteResolver(fn () => $this->route('PUT', 'customers/{customer}', 'customers.update', 'update'));
        $response = $middleware->handle(
            $mutationRequest,
            fn (): RedirectResponse => new RedirectResponse('https://outside.example/customers'),
        );

        self::assertSame('https://outside.example/customers', $response->getTargetUrl());
    }

    private function route(string $method, string $uri, string $name, string $action): Route
    {
        $controllerAction = 'App\\Http\\Controllers\\Admin\\KhachHangController@'.$action;
        $route = new Route([$method], $uri, [
            'controller' => $controllerAction,
            'uses' => $controllerAction,
        ]);
        $route->name($name);

        return $route;
    }
}
