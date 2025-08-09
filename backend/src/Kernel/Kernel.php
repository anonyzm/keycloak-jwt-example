<?php

namespace App\Kernel;

use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\YamlFileLoader as RoutingYamlLoader;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Config\FileLocator;

class Kernel extends BaseKernel
{
    public function registerBundles(): array
    {
        return [];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        // Конфигурация загружается в buildContainer()
    }

    public function getProjectDir(): string
    {
        return dirname(__DIR__, 2);
    }

    public function getCacheDir(): string
    {
        return $this->getProjectDir() . '/cache';
    }

    public function getLogDir(): string
    {
        return $this->getProjectDir() . '/logs';
    }

    public function loadRoutes(): RouteCollection
    {
        $fileLocator = new FileLocator($this->getProjectDir() . '/config');
        $loader = new RoutingYamlLoader($fileLocator);
        return $loader->load('routes.yaml');
    }

    protected function buildContainer(): ContainerBuilder
    {
        $container = parent::buildContainer();
        
        $container->setParameter('kernel.project_dir', $this->getProjectDir());
        $container->setParameter('kernel.cache_dir', $this->getCacheDir());
        $container->setParameter('kernel.logs_dir', $this->getLogDir());
        
        // Загружаем сервисы из YAML
        $fileLocator = new FileLocator($this->getProjectDir() . '/config');
        $yamlLoader = new YamlFileLoader($container, $fileLocator);
        $yamlLoader->load('services.yaml');
        
        return $container;
    }
    
    public function registerEventListeners($dispatcher, $container): void
    {
        $authListener = $container->get('App\EventListener\AuthorizationListener');
        $dispatcher->addListener('kernel.controller', [$authListener, 'onKernelController'], 10);
    }
} 