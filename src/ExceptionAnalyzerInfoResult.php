<?php

namespace Dbox\UploaderApi;

final class ExceptionAnalyzerInfoResult
{
    public string $type;
    public string $message;
    public bool $repeat;

    public function __construct(string $type, string $message, bool $repeat)
    {
        $this->type = $type;
        $this->message = $message;
        $this->repeat = $repeat;
    }
}
