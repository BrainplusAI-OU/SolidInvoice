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

namespace SolidInvoice\CatalogBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * The items a company sells, to fill an invoice or quote line from.
 */
final class SolidInvoiceCatalogBundle extends Bundle
{
    public const NAMESPACE = __NAMESPACE__;
}
