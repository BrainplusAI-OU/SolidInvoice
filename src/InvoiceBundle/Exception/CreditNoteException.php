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

namespace SolidInvoice\InvoiceBundle\Exception;

use RuntimeException;

/**
 * A credit note that cannot be issued. The message is a translation key.
 */
final class CreditNoteException extends RuntimeException
{
}
