<?php

namespace App\Tests\Service;

use App\Entity\Product;
use App\Entity\ProductStock;
use App\Service\CartService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockFileSessionStorage;

/**
 * Tests Unitaires pour CartService
 * 
 * Cette classe teste toute la logique d'ajout, de retrait, et de calcul du total
 * du panier d'achat stocké en session.
 */
class CartServiceTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private CartService $cartService;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get('doctrine')->getManager();
        $this->cartService = self::getContainer()->get(CartService::class);

        // Configuration d'une session factice pour le RequestStack afin d'éviter SessionNotFoundException
        $request = new Request();
        $session = new Session(new MockFileSessionStorage());
        $request->setSession($session);
        self::getContainer()->get(RequestStack::class)->push($request);

        // Recréation dynamique de la structure de base de données SQLite pour le test
        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        // On s'assure de vider le panier en session avant chaque test
        $this->cartService->clear();
    }

    /**
     * Méthode utilitaire pour créer un produit de test avec du stock
     */
    private function createTestProduct(string $name, float $price, string $size, int $quantity): Product
    {
        $product = new Product();
        $product->setName($name);
        $product->setPrice($price);
        $product->setImage('sweat-test.jpg');
        $product->setIsFeatured(false);
        $this->entityManager->persist($product);

        $stock = new ProductStock();
        $stock->setProduct($product);
        $stock->setSize($size);
        $stock->setQuantity($quantity);
        $this->entityManager->persist($stock);

        $this->entityManager->flush();

        return $product;
    }

    public function testCartIsEmptyByDefault(): void
    {
        // Scénario 1 : Panier initial vide
        $this->assertCount(0, $this->cartService->getRawCart());
        $this->assertCount(0, $this->cartService->getDetailedCart());
        $this->assertEquals(0.0, $this->cartService->getTotal());
    }

    public function testAddProductToCart(): void
    {
        // Scénario 2 : Ajout simple d'un produit
        $product = $this->createTestProduct('Sweat Street', 34.50, 'M', 10);

        $this->cartService->add($product->getId(), 'M');

        $rawCart = $this->cartService->getRawCart();
        $key = $product->getId() . '-M';

        $this->assertArrayHasKey($key, $rawCart);
        $this->assertEquals(1, $rawCart[$key]);

        $detailedCart = $this->cartService->getDetailedCart();
        $this->assertCount(1, $detailedCart);
        $this->assertEquals('Sweat Street', $detailedCart[0]['product']->getName());
        $this->assertEquals('M', $detailedCart[0]['size']);
        $this->assertEquals(1, $detailedCart[0]['quantity']);
    }

    public function testIncrementQuantityOnSameProductAndSize(): void
    {
        // Scénario 3 : Incrémentation automatique (même produit, même taille)
        $product = $this->createTestProduct('Sweat Street', 34.50, 'M', 10);

        $this->cartService->add($product->getId(), 'M');
        $this->cartService->add($product->getId(), 'M'); // Deuxième ajout identique

        $rawCart = $this->cartService->getRawCart();
        $key = $product->getId() . '-M';

        $this->assertEquals(2, $rawCart[$key]); // La quantité doit être incrémentée à 2
    }

    public function testDistinguishSameProductWithDifferentSizes(): void
    {
        // Scénario 4 : Distinction par taille (même produit, tailles différentes)
        $product = $this->createTestProduct('Sweat Street', 34.50, 'M', 10);
        // Ajout d'une autre taille en stock pour le test
        $stockS = new ProductStock();
        $stockS->setProduct($product);
        $stockS->setSize('S');
        $stockS->setQuantity(5);
        $this->entityManager->persist($stockS);
        $this->entityManager->flush();

        $this->cartService->add($product->getId(), 'M');
        $this->cartService->add($product->getId(), 'S'); // Même produit, taille différente

        $rawCart = $this->cartService->getRawCart();
        $this->assertCount(2, $rawCart); // Deux clés distinctes attendues
        $this->assertArrayHasKey($product->getId() . '-M', $rawCart);
        $this->assertArrayHasKey($product->getId() . '-S', $rawCart);
    }

    public function testRemoveProductFromCart(): void
    {
        // Scénario 5 : Retrait d'un article
        $product = $this->createTestProduct('Sweat Street', 34.50, 'M', 10);

        $this->cartService->add($product->getId(), 'M');
        $this->cartService->remove($product->getId(), 'M');

        $this->assertCount(0, $this->cartService->getRawCart());
    }

    public function testCalculateCartTotal(): void
    {
        // Scénario 6 : Calcul du montant total
        $product1 = $this->createTestProduct('Sweat Street', 30.00, 'M', 10);
        $product2 = $this->createTestProduct('Sweat Pokeball', 45.00, 'L', 5);

        $this->cartService->add($product1->getId(), 'M'); // 1 * 30.00 = 30.00 €
        $this->cartService->add($product1->getId(), 'M'); // 1 * 30.00 = 30.00 € (total 60.00 €)
        $this->cartService->add($product2->getId(), 'L'); // 1 * 45.00 = 45.00 € (total 105.00 €)

        $this->assertEquals(105.00, $this->cartService->getTotal());
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
    }
}
