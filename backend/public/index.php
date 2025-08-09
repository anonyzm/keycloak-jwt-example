<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\Controller\ContainerControllerResolver;
use Symfony\Component\EventDispatcher\EventDispatcher;
use App\Kernel\Kernel;

// Создаем Kernel и загружаем контейнер
$kernel = new Kernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();

// Загружаем маршруты из YAML файла
$routes = $kernel->loadRoutes();

// Создаем контекст запроса
$context = new RequestContext();
$context->fromRequest(Request::createFromGlobals());

// Создаем матчер URL
$matcher = new UrlMatcher($routes, $context);

// Создаем резолвер контроллеров с поддержкой контейнера
$resolver = new ContainerControllerResolver($container);

// Получаем диспетчер событий из контейнера
$dispatcher = $container->get('event_dispatcher');

// Регистрируем event listeners
$kernel->registerEventListeners($dispatcher, $container);

// Создаем HTTP Kernel
$httpKernel = new HttpKernel($dispatcher, $resolver);

// Обрабатываем запрос
$request = Request::createFromGlobals();

// Обрабатываем OPTIONS запросы для API эндпоинтов до роутинга
// OPTIONS запросы не должны проходить через JWT аутентификацию
if ($request->getMethod() === 'OPTIONS' && str_starts_with($request->getPathInfo(), '/api/')) {
    $response = new Response('', 204);
    $response->headers->set('Access-Control-Allow-Origin', '*');
    $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
    $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin');
    $response->headers->set('Access-Control-Max-Age', '86400');
    $response->headers->set('Access-Control-Allow-Credentials', 'false');
} else {
    try {
        $parameters = $matcher->match($request->getPathInfo());
        $request->attributes->add($parameters);
        
        $response = $httpKernel->handle($request);
    } catch (Exception $e) {
        $response = new Response('Page not found: ' . $e->getMessage(), 404);
    }
}

$response->send();
$httpKernel->terminate($request, $response); 