<?php

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * ProductRepository (Dépôt de données pour Product)
 * 
 * Cette classe contient les fonctions nécessaires pour effectuer des requêtes personnalisées
 * sur la table 'product' (par exemple, filtrer les produits par prix).
 * 
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        // On associe ce repository à l'entité Product
        parent::__construct($registry, Product::class);
    }

    /**
     * Filtre les sweat-shirts selon une fourchette de prix minimum et maximum.
     * 
     * @param float $minPrice Le prix minimum
     * @param float $maxPrice Le prix maximum
     * @return Product[] Un tableau contenant les sweat-shirts correspondants
     */
    public function findByPriceRange(float $minPrice, float $maxPrice): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.price >= :minPrice')
            ->andWhere('p.price <= :maxPrice')
            ->setParameter('minPrice', $minPrice)
            ->setParameter('maxPrice', $maxPrice)
            ->orderBy('p.price', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
