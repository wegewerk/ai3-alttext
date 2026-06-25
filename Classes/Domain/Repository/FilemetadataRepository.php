<?php

declare(strict_types=1);

namespace Wegewerk\Ai3Alttext\Domain\Repository;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class FilemetadataRepository
{
    public function __construct(
        private ConnectionPool $connectionPool,
        private string $table = 'sys_file_metadata',
    ) {}

    /**
     * @throws Exception
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function updateAlttext(int $uid, string $altText)
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->table);
        $queryBuilder
            ->update($this->table)
            ->set('alternative', $altText)
            ->set('tstamp', time())
            ->where(
                $queryBuilder->expr()->and(
                    $queryBuilder->expr()->eq('file', $queryBuilder->createNamedParameter($uid)),
                    $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter('0'))
                )
            );
        return $queryBuilder
            ->executeStatement();
    }

    public function getMetadata(int $recordId, int $langUid): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_file_metadata');
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $queryBuilder->select('*')
            ->from('sys_file_metadata');
        $queryBuilder->where($queryBuilder->expr()
            ->and(
                $queryBuilder->expr()
                ->eq('file', $queryBuilder->createNamedParameter($recordId, Connection::PARAM_INT)),
                $queryBuilder->expr()
                    ->eq('sys_language_uid', $queryBuilder->createNamedParameter($langUid))
            ));

        return $queryBuilder->executeQuery()
            ->fetchAssociative();
    }

    public function getFilemetadataUidForLanguage(int $fileUid, int $langUid = 0): int
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('sys_file_metadata');
        $metadataRecord = $queryBuilder
            ->select('uid')
            ->from('sys_file_metadata')
            ->where(
                $queryBuilder->expr()->eq('file', $queryBuilder->createNamedParameter($fileUid, ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($langUid, ParameterType::INTEGER))
            )
            ->executeQuery()
            ->fetchOne();

        return (int)$metadataRecord;
    }

}
