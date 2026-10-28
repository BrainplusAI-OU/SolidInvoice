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

namespace SolidInvoice\ExpenseBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SolidInvoice\ExpenseBundle\Entity\ExpenseAttachment;
use function base64_decode;

final class ExpenseAttachmentTest extends TestCase
{
    private const string PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    public function testTheTypeAndSizeComeFromTheContent(): void
    {
        $attachment = new ExpenseAttachment();
        $attachment->setBase64Content(self::PNG);

        self::assertSame('image/png', $attachment->getMimeType());
        self::assertSame(strlen((string) base64_decode(self::PNG, true)), $attachment->getSize());
        self::assertSame(base64_decode(self::PNG, true), $attachment->getContent());
    }

    public function testInvalidBase64LeavesTheAttachmentEmpty(): void
    {
        $attachment = new ExpenseAttachment();
        $attachment->setBase64Content('not base64 at all!');

        self::assertSame(0, $attachment->getSize());
        self::assertSame('', $attachment->getMimeType());
    }

    #[DataProvider('fileNames')]
    public function testTheFileNameIsReducedToASafeBaseName(string $given, string $expected): void
    {
        $attachment = new ExpenseAttachment();
        $attachment->setFileName($given);

        self::assertSame($expected, $attachment->getFileName());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function fileNames(): iterable
    {
        yield 'plain' => ['receipt.pdf', 'receipt.pdf'];
        yield 'unix path' => ['../../etc/receipt.pdf', 'receipt.pdf'];
        yield 'windows path' => ['C:\\Users\\me\\receipt.jpg', 'receipt.jpg'];
        yield 'quotes and control characters' => ["re\"ce\nipt.png", 'receipt.png'];
        yield 'accents are kept' => ['reçu-été.pdf', 'reçu-été.pdf'];
    }
}
