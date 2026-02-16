<?php
namespace Pentatrion\UploadBundle\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class InformativeException extends HttpException
{
  public function __construct(int $statusCode, string $message = null, Throwable $previous = null, array $headers = [], ?int $code = E_NOTICE)
  {
    parent::__construct($statusCode, $message, $previous, $headers, $code);
  }
}
