<?php

declare(strict_types=1);

/*
 * This file is part of SolidInvoice project.
 *
 * (c) Pierre du Plessis <open-source@solidworx.co>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace SolidInvoice\AssistantBundle\Manager;

use InvalidArgumentException;

/**
 * What a bot sent could not be recorded. The messages go back to it in the response.
 */
final class InvalidPayload extends InvalidArgumentException
{
    /**
     * @param list<string> $errors
     */
    public function __construct(
        private readonly array $errors,
    ) {
        parent::__construct(implode(' ', $errors));
    }

    /**
     * @return list<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
