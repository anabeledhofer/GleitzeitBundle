<?php

namespace KimaiPlugin\GleitzeitBundle\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use KimaiPlugin\GleitzeitBundle\Entity\MonatsAbschluss;

/**
 * Datenbank-Zugriffe für Monatsabschlüsse.
 *
 * @extends ServiceEntityRepository<MonatsAbschluss>
 */
class MonatsAbschlussRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MonatsAbschluss::class);
    }

    /**
     * Abschluss für User + Monat holen (oder null = Monat ist offen).
     */
    public function findAbschluss(User $user, int $jahr, int $monat): ?MonatsAbschluss
    {
        return $this->findOneBy(['user' => $user, 'jahr' => $jahr, 'monat' => $monat]);
    }
    /**
     * letzter abschluss eines users = aktuelle kontostände
     */
    public function findLetzten(User $user): ?MonatsAbschluss
    {
        return $this->findOneBy(['user' => $user], ['jahr' => 'DESC', 'monat' => 'DESC']);
    }

    /**
     * Abschluss des vormonats holen = Startüberträge
     * darf nur abschließen wenn vormonat abgeschlossen
     */
    public function findVormonat(User $user, int $jahr, int $monat): ?MonatsAbschluss
    {
        if ($monat === 1) {
            return $this->findAbschluss($user, $jahr - 1, 12); //Jänner -> Dezember
        }

        return $this->findAbschluss($user, $jahr, $monat - 1);
    }

    /**
     * summe der verbrauchten urlaubstage im jahr aus den abgeschlossenen monaten bis einschließlich $bisMonat.
     */
    public function summeUrlaubImJahr(User $user, int $jahr, int $bisMonat): int
    {
        return (int) $this->createQueryBuilder('a')
            // COALESCE: liefert 0 statt NULL, wenn es noch gar keine
            // abschlüsse gibt (SUM über null zeilen wäre sonst NULL)
            ->select('COALESCE(SUM(a.urlaubTage), 0)')
            ->andWhere('a.user = :user')
            ->andWhere('a.jahr = :jahr')
            ->andWhere('a.monat <= :monat')
            ->setParameter('user', $user)
            ->setParameter('jahr', $jahr)
            ->setParameter('monat', $bisMonat)
            ->getQuery()
            ->getSingleScalarResult();
    }
    //Summe urlaub aller abgeschlossener Monate seit systemstart
    public function summeUrlaubGesamt(User $user){
         return (int) $this->createQueryBuilder('a')
            // COALESCE: liefert 0 statt NULL, wenn es noch gar keine
            // abschlüsse gibt (SUM über null zeilen wäre sonst NULL)
            ->select('COALESCE(SUM(a.urlaubTage), 0)')
            ->andWhere('a.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    } 
    
    

    /**
     * neuen abschluss Speichern (insert) 
     */
    public function speichern(MonatsAbschluss $abschluss): void
    {
        $em = $this->getEntityManager();
        $em->persist($abschluss);
        $em->flush();
    }

    /**
     * Löschen = Monat wieder öffnen 
     */
    public function loeschen(MonatsAbschluss $abschluss): void
    {
        //TODO: nur mit Amdinrechten Abschluss löschen 
        $em = $this->getEntityManager();
        $em->remove($abschluss);
        $em->flush();
    }
}