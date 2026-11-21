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

use Doctrine\ORM\EntityManagerInterface;
use SolidInvoice\AssistantBundle\Entity\Assistant;
use SolidInvoice\AssistantBundle\Entity\AssistantMember;
use SolidInvoice\AssistantBundle\Repository\AssistantMemberRepository;
use function is_array;
use function is_scalar;
use function trim;

/**
 * Brings in a list of members kept elsewhere (a spreadsheet, the bot's own config), and
 * applies the changes the bot itself makes (a person picks a language, adds a plate).
 *
 * @see \SolidInvoice\AssistantBundle\Tests\Functional\Api\AssistantApiTest
 */
final readonly class MemberSync
{
    /**
     * The fields a bot may set, as the API names them.
     */
    private const array FIELDS = ['name', 'vehiclePlates', 'language', 'payer', 'active'];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private AssistantMemberRepository $members,
    ) {
    }

    /**
     * Adds the people not yet known and updates the others. Nobody is removed unless asked:
     * a list that arrives short must not lock people out.
     *
     * @param list<mixed> $rows
     *
     * @return array{created: int, updated: int, deactivated: int}
     *
     * @throws InvalidPayload
     */
    public function import(Assistant $assistant, array $rows, bool $deactivateMissing = false): array
    {
        $errors = [];
        $clean = [];

        foreach ($rows as $index => $row) {
            $externalId = is_array($row) && isset($row['externalId']) && is_scalar($row['externalId']) ? trim((string) $row['externalId']) : '';
            $name = is_array($row) && isset($row['name']) && is_scalar($row['name']) ? trim((string) $row['name']) : '';

            if ($externalId === '' || $name === '') {
                $errors[] = 'members[' . $index . ']: externalId and name are required.';
                continue;
            }

            assert(is_array($row));
            $clean[$externalId] = $row;
        }

        if ($errors !== []) {
            throw new InvalidPayload($errors);
        }

        $result = ['created' => 0, 'updated' => 0, 'deactivated' => 0];

        foreach ($clean as $externalId => $row) {
            $member = $this->members->findOneByExternalId($assistant, (string) $externalId);

            if (! $member instanceof AssistantMember) {
                $member = new AssistantMember();
                $member->setAssistant($assistant);
                $member->setExternalId((string) $externalId);
                $this->entityManager->persist($member);
                ++$result['created'];
            } else {
                ++$result['updated'];
            }

            $this->apply($member, $row);
        }

        if ($deactivateMissing) {
            foreach ($this->members->findActive($assistant) as $member) {
                if (! isset($clean[$member->getExternalId()])) {
                    $member->setActive(false);
                    ++$result['deactivated'];
                }
            }
        }

        $this->entityManager->flush();

        return $result;
    }

    /**
     * @param array<array-key, mixed> $changes
     */
    public function update(AssistantMember $member, array $changes): void
    {
        $this->apply($member, $changes);
        $this->entityManager->flush();
    }

    /**
     * @param array<array-key, mixed> $row
     */
    private function apply(AssistantMember $member, array $row): void
    {
        foreach (self::FIELDS as $field) {
            if (! array_key_exists($field, $row)) {
                continue;
            }

            $value = $row[$field];

            match ($field) {
                'name' => is_scalar($value) && trim((string) $value) !== '' ? $member->setName(mb_substr((string) $value, 0, 120)) : null,
                'vehiclePlates' => $member->setVehiclePlates(is_scalar($value) ? mb_substr((string) $value, 0, 255) : null),
                'language' => $member->setLanguage(is_scalar($value) ? mb_substr((string) $value, 0, 8) : null),
                'payer' => $member->setPayer(is_scalar($value) ? mb_substr((string) $value, 0, 120) : null),
                'active' => $member->setActive((bool) $value),
            };
        }
    }

    /**
     * The members as rows of the spreadsheet the bot used to read: id, name, plates,
     * language, payer. The bot keeps its parsing; only the URL changes.
     *
     * @return list<list<string>>
     */
    public function asConfigRows(Assistant $assistant): array
    {
        $rows = [];

        foreach ($this->members->findActive($assistant) as $member) {
            $rows[] = [
                $member->getExternalId(),
                $member->getName(),
                $member->getVehiclePlates() ?? '',
                $member->getLanguage() ?? '',
                $member->getPayer() ?? '',
            ];
        }

        return $rows;
    }
}
