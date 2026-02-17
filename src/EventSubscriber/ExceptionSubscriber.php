<?php

namespace Pentatrion\UploadBundle\EventSubscriber;

use Override;
use Pentatrion\UploadBundle\Exception\InformativeException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

readonly class ExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if (!$exception instanceof InformativeException) {
            return;
        }

        $this->logger->notice($exception->getMessage());
        $response = new JsonResponse([
            'title' => $exception->getMessage(),
            'status' => $exception->getStatusCode()
        ]);
        $event->setResponse($response);
    }

    #[Override]
    public static function getSubscribedEvents(): array
    {
        return [
            'kernel.exception' => 'onKernelException'
        ];
    }
}
