<?php

declare(strict_types=1);

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

    public function onKernelException(ExceptionEvent $exceptionEvent): void
    {
        $throwable = $exceptionEvent->getThrowable();

        if (!$throwable instanceof InformativeException) {
            return;
        }

        $this->logger->notice($throwable->getMessage());
        $jsonResponse = new JsonResponse([
            'title' => $throwable->getMessage(),
            'status' => $throwable->getStatusCode()
        ]);
        $exceptionEvent->setResponse($jsonResponse);
    }

    #[Override]
    public static function getSubscribedEvents(): array
    {
        return [
            'kernel.exception' => 'onKernelException'
        ];
    }
}
