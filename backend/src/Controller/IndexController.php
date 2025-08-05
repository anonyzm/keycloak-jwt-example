<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class IndexController
{
    public function helloWorld(Request $request): Response
    {
        $content = json_encode(['message' => 'Hello World']);        
        return new Response($content);
    }
}