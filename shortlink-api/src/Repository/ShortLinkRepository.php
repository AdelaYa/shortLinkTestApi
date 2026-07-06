<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\ShortLink;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ShortLink>
 */
class ShortLinkRepository extends ServiceEntityRepository {
    public function __construct(ManagerRegistry $registry) {
        parent::__construct($registry, ShortLink::class);
    }

    public function findOneByOriginalUrlHash(string $originalUrlHash): ?ShortLink {
        return $this->findOneBy([
            'originalUrlHash' => $originalUrlHash,
        ]);
    }
}
