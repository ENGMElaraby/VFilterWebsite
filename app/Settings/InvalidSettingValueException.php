<?php

namespace App\Settings;

use InvalidArgumentException;

class InvalidSettingValueException extends InvalidArgumentException
{
    /**
     * @param  array<string, string>  $errors  key => message
     */
    public function __construct(private readonly array $errors)
    {
        parent::__construct('Invalid filter setting value(s): '.implode(', ', array_keys($errors)));
    }

    /**
     * @return array<string, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
