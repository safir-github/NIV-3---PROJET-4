<?php

namespace App\Tests\Controller;

use App\Entity\Product;
use App\Entity\ProductStock;
use App\Entity\User;
use App\Service\StripeService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests Fonctionnels pour le tunnel d'achat et Stripe
 * 
 * Cette classe teste le comportement du tunnel d'achat :
 * - L'ajout au panier.
 * - Le mocking de Stripe Checkout.
 * - La validation du retour avec succès (décrémentation des stocks BDD).
 */
class CartControllerTest extends WebTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Initialisation de la BDD SQLite et injection des données
     */
    private function initializeDatabase($client): array
    {
        $this->entityManager = $client->getContainer()->get('doctrine')->getManager();
        
        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        
        // Supprime et recrée la structure de base
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        // 1. Création de l'utilisateur de test
        $user = new User();
        $user->setEmail('client-test@stubborn.com');
        $user->setPassword('password123'); // Hachage non nécessaire pour le test fonctionnel simple
        $user->setName('Client Test');
        $user->setDeliveryAddress('10 Rue du Test, 75000 Paris');
        $user->setRoles(['ROLE_USER']);
        $user->setIsVerified(true);
        $this->entityManager->persist($user);

        // 2. Création du produit de test
        $product = new Product();
        $product->setName('Sweat Street');
        $product->setPrice(30.00);
        $product->setImage('sweat-test.jpg');
        $product->setIsFeatured(false);
        $this->entityManager->persist($product);

        // 3. Ajout du stock pour la taille M
        $stock = new ProductStock();
        $stock->setSize('M');
        $stock->setQuantity(5); // Stock initial de 5
        $product->addStock($stock);
        $this->entityManager->persist($stock);

        $this->entityManager->flush();
        $this->entityManager->clear();

        return [$user, $product, $stock];
    }

    public function testPurchaseWorkflowWithStripeMock(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        
        // Initialisation de la base de données SQLite en mémoire pour cette requête
        list($user, $product, $stock) = $this->initializeDatabase($client);

        // --- STUB DU SERVICE STRIPE (Doit être injecté avant toute requête HTTP) ---
        $stripeServiceMock = $this->createStub(StripeService::class);
        $stripeServiceMock->method('createCheckoutSession')
            ->willReturn('https://checkout.stripe.com/pay/fake_session_123');
        $fakeStripeSession = (object) ['payment_status' => 'paid'];
        $stripeServiceMock->method('retrieveSession')
            ->willReturn($fakeStripeSession);

        // Injection du mock dans le container de tests pour remplacer l'instance réelle
        self::getContainer()->set(StripeService::class, $stripeServiceMock);

        // Authentification de l'utilisateur de test
        $client->loginUser($user);

        // --- ÉTAPE 1 : AJOUT AU PANIER ---
        // Simuler la soumission du formulaire d'ajout au panier depuis la fiche produit
        $client->request('POST', '/cart/add/' . $product->getId(), [
            'size' => 'M'
        ]);

        // Vérifier qu'on est redirigé vers le panier
        $this->assertResponseRedirects('/cart');
        $client->followRedirect();
        
        // Vérifier que le panier affiche le produit
        $this->assertSelectorTextContains('table', 'Sweat Street');
        $this->assertSelectorTextContains('table', 'M');

        // --- ÉTAPE 3 : CLIC SUR FINALISER LA COMMANDE ---
        $client->request('GET', '/cart/checkout');
        
        // On vérifie que la redirection vers l'URL retournée par le mock Stripe fonctionne
        $this->assertResponseRedirects('https://checkout.stripe.com/pay/fake_session_123');

        // --- ÉTAPE 4 : RETOUR SUR LA PAGE DE SUCCÈS (Validation de l'achat) ---
        // On simule le retour de Stripe vers notre page success avec le paramètre session_id
        $client->request('GET', '/cart/success?session_id=fake_session_123');

        // Vérifier que la page affiche bien le template de confirmation verte
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Merci pour votre commande !');

        // --- ÉTAPE 5 : VÉRIFICATION DÉFENSIVE DES STOCKS EN BASE ---
        // On récupère le stock mis à jour en base de données
        $updatedStock = $this->entityManager->getRepository(ProductStock::class)->findOneBy([
            'size' => 'M'
        ]);

        // Le stock initial était de 5. Après l'achat de 1 sweat, le stock doit être descendu à 4 !
        $this->assertEquals(4, $updatedStock->getQuantity());

        // On vérifie également que le panier en session a bien été vidé (le panier doit être affiché vide)
        $client->request('GET', '/cart');
        $this->assertSelectorTextContains('body', 'Votre panier est actuellement vide.');
    }
}
