<?php

namespace App\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class RequiresRole
{
    public function __construct(
        private string|array $roles
    ) {
    }

    public function getRoles(): array
    {
        return is_array($this->roles) ? $this->roles : [$this->roles];
    }
}