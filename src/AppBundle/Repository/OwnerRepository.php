<?php

namespace AppBundle\Repository;

/**
 * OwnerRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class OwnerRepository extends \Doctrine\ORM\EntityRepository
{

    public function countActiveBetween($endDate){
        return $this->getEntityManager()
            ->createQuery(
                'select count(distinct em.id) as total
                    from AppBundle:owner em
                    where em.creationDate <= :endDate and em.phone is not null'
            )->setParameter('endDate',$endDate)->execute();
    }
}