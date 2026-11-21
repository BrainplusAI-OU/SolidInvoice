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

namespace SolidInvoice\AssistantBundle\Tests\Functional\Api;

use PHPUnit\Framework\Attributes\Group;
use SolidInvoice\ApiBundle\Test\ApiTestCase;
use SolidInvoice\AssistantBundle\Entity\Assistant;
use SolidInvoice\AssistantBundle\Entity\AssistantEvent;
use SolidInvoice\AssistantBundle\Entity\AssistantMember;
use SolidInvoice\AssistantBundle\Enum\EventStatus;
use SolidInvoice\AssistantBundle\Test\Factory\AssistantFactory;
use SolidInvoice\AssistantBundle\Test\Factory\AssistantMemberFactory;
use SolidInvoice\CoreBundle\Test\Factory\CompanyFactory;
use SolidInvoice\ExpenseBundle\Test\Factory\ExpenseFactory;
use Symfony\Component\Uid\Ulid;

#[Group('functional')]
final class AssistantApiTest extends ApiTestCase
{
    protected function getResourceClass(): string
    {
        return Assistant::class;
    }

    public function testCreateAndListAssistants(): void
    {
        $response = self::$client->request('POST', '/api/assistants', ['json' => [
            'name' => 'Jeannine',
            'channel' => 'telegram',
            'inboxEmail' => 'accounting@example.com',
        ]]);

        self::assertResponseStatusCodeSame(201);
        $created = $response->toArray(false);
        self::assertTrue(Ulid::isValid($created['id']));
        self::assertSame('accounting@example.com', $created['inboxEmail']);

        $list = self::$client->request('GET', '/api/assistants')->toArray(false);
        self::assertSame(['Jeannine'], array_column($list, 'name'));
    }

    public function testADocumentIsFollowedUnderOneKey(): void
    {
        $assistant = AssistantFactory::createOne(['company' => $this->company]);
        $member = AssistantMemberFactory::createOne(['company' => $this->company, 'assistant' => $assistant, 'externalId' => '42', 'name' => 'Rose']);
        $expense = ExpenseFactory::createOne(['company' => $this->company]);
        $url = '/api/assistants/' . $assistant->getId() . '/events';

        $first = self::$client->request('POST', $url, ['json' => [
            'key' => 'telegram:recap:1:99',
            'type' => 'document',
            'subject' => 'Makro · 1,250.00 THB',
            'member' => ['externalId' => '42', 'name' => 'Rose T.'],
            'details' => ['amount' => '1250.00', 'currency' => 'THB'],
            'links' => [['label' => 'Drive', 'url' => 'https://drive.example.com/file']],
        ]]);

        self::assertResponseStatusCodeSame(201);
        self::assertSame('pending', $first->toArray(false)['status']);
        self::assertSame('Rose', $first->toArray(false)['member']);

        $second = self::$client->request('POST', $url, ['json' => [
            'key' => 'telegram:recap:1:99',
            'status' => 'processed',
            'expenseId' => (string) $expense->getId(),
            'details' => ['encoding' => 'N° 12'],
        ]]);

        self::assertResponseStatusCodeSame(200);
        self::assertFalse($second->toArray(false)['created']);
        self::assertSame((string) $expense->getId(), $second->toArray(false)['expenseId']);

        $events = self::getContainer()->get('doctrine')->getRepository(AssistantEvent::class)->findBy(['assistant' => $assistant->getId()]);
        self::assertCount(1, $events);

        $event = $events[0];
        self::assertSame(EventStatus::Processed, $event->getStatus());
        self::assertSame('Makro · 1,250.00 THB', $event->getSubject());
        self::assertSame(['pending', 'processed'], array_column($event->getHistory(), 'status'));
        self::assertSame(['amount' => '1250.00', 'currency' => 'THB', 'encoding' => 'N° 12'], $event->getExtraDetails());
        self::assertSame([['label' => 'Drive', 'url' => 'https://drive.example.com/file']], $event->getLinks());
        self::assertSame($member->getId()?->toBase32(), $event->getMember()?->getId()?->toBase32());

        $seen = self::getContainer()->get('doctrine')->getRepository(AssistantMember::class)->find($member->getId());
        self::assertNotNull($seen?->getLastSeenAt());
    }

    public function testAStrangerIsRecordedWithoutBecomingAMember(): void
    {
        $assistant = AssistantFactory::createOne(['company' => $this->company]);

        $response = self::$client->request('POST', '/api/assistants/' . $assistant->getId() . '/events', ['json' => [
            'type' => 'access',
            'subject' => '/start',
            'member' => ['externalId' => '777', 'name' => 'Unknown'],
        ]]);

        self::assertResponseStatusCodeSame(201);
        self::assertSame('rejected', $response->toArray(false)['status']);
        self::assertNull($response->toArray(false)['member']);
        self::assertSame([], self::getContainer()->get('doctrine')->getRepository(AssistantMember::class)->findAll());
    }

    public function testInvalidReportsAreRefused(): void
    {
        $assistant = AssistantFactory::createOne(['company' => $this->company]);

        $response = self::$client->request('POST', '/api/assistants/' . $assistant->getId() . '/events', ['json' => [
            'type' => 'receipt',
            'links' => 'https://example.com',
        ]]);

        self::assertResponseStatusCodeSame(422);
        $errors = implode(' ', $response->toArray(false)['errors']);
        self::assertStringContainsString('type:', $errors);
        self::assertStringContainsString('links:', $errors);
    }

    public function testAnotherCompanysAssistantIsOutOfReach(): void
    {
        $other = AssistantFactory::createOne(['company' => CompanyFactory::createOne()]);

        self::$client->request('POST', '/api/assistants/' . $other->getId() . '/events', ['json' => [
            'type' => 'message',
            'subject' => 'hello',
        ]]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testMembersImportConfigAndUpdate(): void
    {
        $assistant = AssistantFactory::createOne(['company' => $this->company]);
        $base = '/api/assistants/' . $assistant->getId() . '/members';

        $import = self::$client->request('PUT', $base, ['json' => ['members' => [
            ['externalId' => '111', 'name' => 'Jimmy', 'language' => 'fr', 'payer' => 'Justine'],
            ['externalId' => '222', 'name' => 'Rose', 'vehiclePlates' => 'กข 1234'],
        ]]]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['created' => 2, 'updated' => 0, 'deactivated' => 0], $import->toArray(false));

        self::$client->request('PATCH', $base . '/222', ['json' => ['language' => 'EN']]);
        self::assertResponseStatusCodeSame(200);

        self::$client->request('PATCH', $base . '/999', ['json' => ['language' => 'en']]);
        self::assertResponseStatusCodeSame(404);

        $config = self::$client->request('GET', $base . '/config')->toArray(false);
        self::assertSame([
            ['111', 'Jimmy', '', 'fr', 'Justine'],
            ['222', 'Rose', 'กข 1234', 'en', ''],
        ], $config['values']);

        $again = self::$client->request('PUT', $base, ['json' => [
            'members' => [['externalId' => '111', 'name' => 'Jimmy']],
            'deactivateMissing' => true,
        ]]);
        self::assertSame(['created' => 0, 'updated' => 1, 'deactivated' => 1], $again->toArray(false));

        $config = self::$client->request('GET', $base . '/config')->toArray(false);
        self::assertSame([['111', 'Jimmy', '', 'fr', 'Justine']], $config['values']);
    }
}
