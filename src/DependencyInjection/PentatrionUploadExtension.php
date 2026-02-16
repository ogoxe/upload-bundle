<?php

namespace Pentatrion\UploadBundle\DependencyInjection;

use Exception;
use Pentatrion\UploadBundle\Service\FileManagerHelperInterface;
use Pentatrion\UploadBundle\Service\UploadedFileHelperInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class PentatrionUploadExtension extends Extension
{
    /**
     * @throws Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        $configuration = $this->getConfiguration($configs, $container);
        $config = $this->processConfiguration($configuration, $configs);
        $webRoot = $container->getParameter('kernel.project_dir') . '/public';
        $origins = [];

        foreach ($config['origins'] as $key => $origin) {
            if (str_starts_with($origin['path'], $webRoot)) {
                $origin['web_prefix'] = substr($origin['path'], strlen($webRoot));
            }
            $origins[$key] = $origin;
        }

        if (!isset($config['default_origin'])) {
            $config['default_origin'] = array_keys($origins)[0];
        }

        $container->setParameter('pentatrion_upload.origins', $origins);
        $container->setParameter('pentatrion_upload.default_origin', $config['default_origin']);
        $container->setParameter('pentatrion_upload.liip_filters', $config['liip_filters']);

        if (null !== $config['uploaded_file_helper']) {
            $container->setAlias(UploadedFileHelperInterface::class, $config['uploaded_file_helper']);
        }
        if (null !== $config['file_manager_helper']) {
            $container->setAlias(FileManagerHelperInterface::class, $config['file_manager_helper']);
        }
    }
}
