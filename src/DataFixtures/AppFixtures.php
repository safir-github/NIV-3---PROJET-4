<?php

namespace App\DataFixtures;

use App\Entity\Product;
use App\Entity\ProductStock;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * AppFixtures (Jeu de données de test)
 * 
 * Cette classe remplit automatiquement la base de données avec des données de test initiales.
 * Elle contient :
 * - Les 10 sweat-shirts décrits dans le brief (avec leurs prix et statut mis en avant).
 * - Les stocks associés pour chaque taille (XS, S, M, L, XL) avec au moins 2 articles chacun.
 * - Deux utilisateurs (un client et un administrateur) avec leurs mots de passe hachés.
 */
class AppFixtures extends Fixture
{
    // Le service qui nous permet de hacher les mots de passe des utilisateurs en toute sécurité
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        // ==========================================
        // 1. CRÉATION DES UTILISATEURS DE TEST
        // ==========================================

        // Création du compte client test
        $clientUser = new User();
        $clientUser->setEmail('client@stubborn.com')
            ->setName('Jean Client')
            ->setDeliveryAddress('123 Rue de la Liberté, 75001 Paris')
            ->setIsVerified(true)
            ->setRoles(['ROLE_USER']);
        
        // Hachage et définition du mot de passe
        $hashedClientPassword = $this->passwordHasher->hashPassword($clientUser, 'client123');
        $clientUser->setPassword($hashedClientPassword);
        
        $manager->persist($clientUser);

        // Création du compte administrateur test
        $adminUser = new User();
        $adminUser->setEmail('admin@stubborn.com')
            ->setName('Marc Admin')
            ->setIsVerified(true)
            ->setRoles(['ROLE_ADMIN']);
        
        // Hachage et définition du mot de passe
        $hashedAdminPassword = $this->passwordHasher->hashPassword($adminUser, 'admin123');
        $adminUser->setPassword($hashedAdminPassword);
        
        $manager->persist($adminUser);

        // ==========================================
        // 2. CRÉATION DES PRODUITS (SWEAT-SHIRTS)
        // ==========================================

        // Liste des sweat-shirts fournie dans le brief
        // Format : [Nom du sweat-shirt, prix, isFeatured (mis en avant ou non)]
        $productsData = [
            ['Blackbelt', 29.90, true],
            ['BlueBelt', 29.90, false],
            ['Street', 34.50, false],
            ['Pokeball', 45.00, true],
            ['PinkLady', 29.90, false],
            ['Snow', 32.00, false],
            ['Greyback', 28.50, false],
            ['BlueCloud', 45.00, false],
            ['BornInUsa', 59.90, true],
            ['GreenSchool', 42.20, false],
        ];

        // Tailles disponibles pour chaque produit
        $sizes = ['XS', 'S', 'M', 'L', 'XL'];

        foreach ($productsData as $index => $data) {
            $product = new Product();
            $product->setName($data[0])
                ->setPrice($data[1])
                // On associe l'image officielle tirée du dossier Ressources (ex: "1.jpeg", "2.jpeg")
                ->setImage(($index + 1) . '.jpeg')
                ->setIsFeatured($data[2]);

            $manager->persist($product);

            // Pour chaque produit, on génère un stock pour chacune des tailles (XS à XL)
            foreach ($sizes as $index => $size) {
                $stock = new ProductStock();
                $stock->setSize($size)
                    // On attribue un stock variable (au moins 2 exemplaires) pour faire réaliste
                    ->setQuantity(5 + ($index * 2))
                    ->setProduct($product);

                $manager->persist($stock);
            }
        }

        // On applique tous les changements (INSERT INTO SQL) en base de données
        $manager->flush();
    }
}
