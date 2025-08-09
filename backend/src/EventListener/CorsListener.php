<?php

namespace App\EventListener;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

class CorsListener
{
    public function onKernelRequest(RequestEvent $event): void
    {
        // Обрабатываем только главный запрос
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        
        // Обрабатываем preflight запросы
        if ($request->getMethod() === 'OPTIONS') {
            $response = new Response('', 200);
            $this->addCorsHeaders($response);
            $event->setResponse($response);
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        // Обрабатываем только главный запрос
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();
        $this->addCorsHeaders($response);
    }

    private function addCorsHeaders(Response $response): void
    {
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin, X-JWT-Claim-*');
        $response->headers->set('Access-Control-Allow-Credentials', 'false');
        $response->headers->set('Access-Control-Max-Age', '86400');
        $response->headers->set('Access-Control-Expose-Headers', 'Content-Length, Content-Type, Authorization, X-JWT-*');
    }
}