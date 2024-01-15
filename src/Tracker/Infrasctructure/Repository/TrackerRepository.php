<?php

namespace App\Tracker\Infrasctructure\Repository;

use App\Tracker\Domain\Repository\TrackerFinderInterface;
use App\Tracker\Domain\Tracker;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tracker>
 *
 * @method Tracker|null find($id, $lockMode = null, $lockVersion = null)
 * @method Tracker|null findOneBy(array $criteria, array $orderBy = null)
 * @method Tracker[]    findAll()
 * @method Tracker[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TrackerRepository extends ServiceEntityRepository implements TrackerFinderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tracker::class);
    }

    /**
     * @return Tracker[] Returns an array of Tracker objects
     */
    public function returnLastElements(): array
    {
        return $this->createQueryBuilder('t')
            ->orderBy('t.id', 'DESC')
            ->setMaxResults(2)
            ->getQuery()
            ->getResult()
        ;
    }


}
