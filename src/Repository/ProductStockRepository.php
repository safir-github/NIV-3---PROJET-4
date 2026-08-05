<?php

namespace App\Repository;

use App\Entity\ProductStock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * ProductStockRepository (Dépôt de données pour ProductStock)
 * 
 * Cette classe contient les fonctions nécessaires pour effectuer des requêtes personnalisées
 * sur les stocks par taille.
 * 
 * @extends ServiceEntityRepository<ProductStock>
 */
class ProductStockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductStock::class);
    }
}
