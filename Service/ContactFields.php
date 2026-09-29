<?php

declare(strict_types=1);

namespace MauticPlugin\SmtpingBundle\Service;

use Doctrine\DBAL\Connection;
use Mautic\LeadBundle\Entity\LeadField;
use Mautic\LeadBundle\Model\FieldModel;

/**
 * Creates the three SMTPing contact fields and reads or writes them.
 * Values are written with plain SQL so a 100,000-contact run stays fast.
 */
class ContactFields
{
    public const STATUS = 'smtping_status';
    public const BAND = 'smtping_band';
    public const VERIFIED_AT = 'smtping_verified_at';

    private const FIELDS = [
        self::STATUS      => ['SMTPing status', 'text'],
        self::BAND        => ['SMTPing band', 'text'],
        self::VERIFIED_AT => ['SMTPing verified at', 'datetime'],
    ];

    public function __construct(private FieldModel $fieldModel, private Connection $connection)
    {
    }

    /** Creates missing fields. Returns the aliases that were created. */
    public function ensure(): array
    {
        $created = [];
        foreach (self::FIELDS as $alias => [$label, $type]) {
            if ($this->fieldModel->getEntityByAlias($alias)) {
                continue;
            }
            $field = new LeadField();
            $field->setLabel($label);
            $field->setAlias($alias);
            $field->setType($type);
            $field->setObject('lead');
            $field->setGroup('core');
            $field->setIsPublished(true);
            $field->setIsListable(true);
            $field->setIsPubliclyUpdatable(false);
            $this->fieldModel->saveEntity($field);
            $created[] = $alias;
        }

        return $created;
    }

    /** True once the database columns exist (Mautic may create them in the background). */
    public function columnsReady(): bool
    {
        $sm      = method_exists($this->connection, 'createSchemaManager') ? $this->connection->createSchemaManager() : $this->connection->getSchemaManager();
        $columns = array_map('strtolower', array_keys($sm->listTableColumns($this->table())));

        return !array_diff(array_keys(self::FIELDS), $columns);
    }

    /**
     * Contacts with an email that were never verified, or verified before $olderThanDays.
     *
     * @return array<int, array{id: int, email: string}>
     */
    public function pending(int $limit, ?int $segmentId = null, ?int $olderThanDays = null): array
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('l.id', 'l.email')
            ->from($this->table(), 'l')
            ->where("l.email IS NOT NULL AND l.email <> ''")
            ->orderBy('l.id', 'ASC')
            ->setMaxResults($limit);

        if (null !== $olderThanDays) {
            $qb->andWhere('l.'.self::VERIFIED_AT.' IS NULL OR l.'.self::VERIFIED_AT.' < :cutoff')
                ->setParameter('cutoff', (new \DateTime('-'.$olderThanDays.' days', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'));
        } else {
            $qb->andWhere('l.'.self::STATUS." IS NULL OR l.".self::STATUS." = ''");
        }

        if (null !== $segmentId) {
            $qb->innerJoin('l', $this->prefix().'lead_lists_leads', 'll', 'll.lead_id = l.id AND ll.leadlist_id = :segment AND ll.manually_removed = 0')
                ->setParameter('segment', $segmentId);
        }

        $rows = method_exists($qb, 'executeQuery') ? $qb->executeQuery()->fetchAllAssociative() : $qb->execute()->fetchAllAssociative();

        return array_map(static fn (array $r) => ['id' => (int) $r['id'], 'email' => (string) $r['email']], $rows);
    }

    public function write(int $contactId, string $status, string $band): void
    {
        $this->connection->update($this->table(), [
            self::STATUS      => $status,
            self::BAND        => $band,
            self::VERIFIED_AT => (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
        ], ['id' => $contactId]);
    }

    public function band(int $contactId): ?string
    {
        $v = $this->connection->fetchOne('SELECT '.self::BAND.' FROM '.$this->table().' WHERE id = ?', [$contactId]);

        return is_string($v) && '' !== $v ? $v : null;
    }

    private function table(): string
    {
        return $this->prefix().'leads';
    }

    private function prefix(): string
    {
        return defined('MAUTIC_TABLE_PREFIX') ? (string) MAUTIC_TABLE_PREFIX : '';
    }
}
