<?php

namespace App\EventListener;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

class ApiCorsListener
{
    public function onKernelRequest(RequestEvent $event): void
    {
        // OPTIONS запросы обрабатываются в index.php до роутинга
        // Этот метод оставляем для совместимости, но он не используется для OPTIONS
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        // Обрабатываем только главный запрос
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        
        // Проверяем, что это API запрос
        if (!str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        $response = $event->getResponse();
        $this->addCorsHeaders($response, $request);
    }

    private function addCorsHeaders(Response $response, $request): void
    {
        // Основные CORS заголовки
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Credentials', 'false');
        
        // Разрешенные методы
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        
        // Разрешенные заголовки
        $allowedHeaders = [
            'Content-Type',
            'Authorization', 
            'X-Requested-With',
            'Accept',
            'Origin',
            'X-JWT-Claim-*'
        ];
        
        // Если клиент запрашивает дополнительные заголовки, добавляем их
        if ($request->headers->has('Access-Control-Request-Headers')) {
            $requestedHeaders = $request->headers->get('Access-Control-Request-Headers');
            $allowedHeaders[] = $requestedHeaders;
        }
        
        $response->headers->set('Access-Control-Allow-Headers', implode(', ', $allowedHeaders));
        
        // Заголовки, которые можно читать в JavaScript
        $response->headers->set('Access-Control-Expose-Headers', 'Content-Length, Content-Type, Authorization');
        
        // Кэширование preflight запросов на 24 часа
        $response->headers->set('Access-Control-Max-Age', '86400');
        
        // Vary заголовок для правильного кэширования
        $response->headers->set('Vary', 'Origin');
    }
}
