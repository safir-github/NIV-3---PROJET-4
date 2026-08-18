<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use App\Service\CartService;
use App\Service\StripeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Contrôleur du Panier (CartController)
 * 
 * Ce contrôleur gère toutes les étapes du panier d'achat, du tunnel de validation
 * et de la finalisation du paiement via l'intégration Stripe.
 */
class CartController extends AbstractController
{
    private CartService $cartService;
    private ProductRepository $productRepository;
    private StripeService $stripeService;
    private EntityManagerInterface $entityManager;

    public function __construct(
        CartService $cartService, 
        ProductRepository $productRepository,
        StripeService $stripeService,
        EntityManagerInterface $entityManager
    ) {
        $this->cartService = $cartService;
        $this->productRepository = $productRepository;
        $this->stripeService = $stripeService;
        $this->entityManager = $entityManager;
    }

    #[Route('/cart', name: 'app_cart')]
    public function index(): Response
    {
        // Récupération des informations détaillées du panier
        $detailedCart = $this->cartService->getDetailedCart();
        $total = $this->cartService->getTotal();

        return $this->render('cart/index.html.twig', [
            'cart_items' => $detailedCart,
            'total' => $total
        ]);
    }

    #[Route('/cart/add/{id}', name: 'app_cart_add', methods: ['POST'])]
    public function add(int $id, Request $request): Response
    {
        // Récupération de la taille soumise dans le formulaire
        $size = $request->request->get('size');

        // Style défensif : on vérifie si le produit existe bien en BDD
        $product = $this->productRepository->find($id);
        if (!$product) {
            throw $this->createNotFoundException('Le produit que vous essayez d\'ajouter n\'existe pas.');
        }

        // Style défensif : validation de la sélection de la taille
        if (!$size) {
            $this->addFlash('error', 'Veuillez sélectionner une taille.');
            return $this->redirectToRoute('app_product_show', ['id' => $id]);
        }

        // Style défensif : on vérifie que la taille demandée dispose bien d'un stock > 0
        $hasStock = false;
        foreach ($product->getStocks() as $stock) {
            if ($stock->getSize() === $size && $stock->getQuantity() > 0) {
                $hasStock = true;
                break;
            }
        }

        if (!$hasStock) {
            $this->addFlash('error', sprintf('La taille %s pour ce produit est actuellement en rupture de stock.', $size));
            return $this->redirectToRoute('app_product_show', ['id' => $id]);
        }

        // Ajout au panier via le service
        $this->cartService->add($id, $size);

        $this->addFlash('success', sprintf('Le sweat-shirt %s (taille %s) a été ajouté à votre panier.', $product->getName(), $size));

        // Redirection classique vers la page du panier
        return $this->redirectToRoute('app_cart');
    }

    #[Route('/cart/remove/{id}/{size}', name: 'app_cart_remove')]
    public function remove(int $id, string $size): Response
    {
        // Suppression de la ligne produit + taille via le service
        $this->cartService->remove($id, $size);

        $this->addFlash('success', 'L\'article a été retiré de votre panier.');

        // Redirection classique vers le panier
        return $this->redirectToRoute('app_cart');
    }

    #[Route('/cart/checkout', name: 'app_cart_checkout')]
    public function checkout(): Response
    {
        $cartItems = $this->cartService->getDetailedCart();

        // Style défensif : si le panier est vide, impossible de payer
        if (empty($cartItems)) {
            $this->addFlash('error', 'Votre panier est vide. Ajoutez des articles avant de finaliser la commande.');
            return $this->redirectToRoute('app_cart');
        }

        // Génération des URL absolues de retour pour Stripe
        $successUrl = $this->generateUrl('app_cart_success', [], UrlGeneratorInterface::ABSOLUTE_URL) . '?session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl = $this->generateUrl('app_cart_cancel', [], UrlGeneratorInterface::ABSOLUTE_URL);

        // Création de la session Checkout via notre service Stripe
        $checkoutUrl = $this->stripeService->createCheckoutSession($cartItems, $successUrl, $cancelUrl);

        // Redirection de l'utilisateur vers le portail Stripe sécurisé
        return $this->redirect($checkoutUrl);
    }

    #[Route('/cart/success', name: 'app_cart_success')]
    public function success(Request $request): Response
    {
        $sessionId = $request->query->get('session_id');

        // Style défensif : s'assurer qu'un ID de session Stripe valide est transmis
        if (!$sessionId) {
            $this->addFlash('error', 'Session de paiement invalide.');
            return $this->redirectToRoute('app_cart');
        }

        try {
            // Récupération de la session de paiement depuis l'API de Stripe
            $session = $this->stripeService->retrieveSession($sessionId);

            // Vérification stricte du statut du paiement
            if ($session->payment_status !== 'paid') {
                $this->addFlash('error', 'Le paiement n\'a pas pu être validé par Stripe.');
                return $this->redirectToRoute('app_cart');
            }

            // Récupération du panier détaillé actuel pour décrémenter les stocks physiques
            $cartItems = $this->cartService->getDetailedCart();

            if (!empty($cartItems)) {
                // Parcourir les articles achetés et décrémenter les stocks correspondants en base
                foreach ($cartItems as $item) {
                    $product = $item['product'];
                    $purchasedSize = $item['size'];
                    $quantityPurchased = $item['quantity'];

                    // Recherche de la ligne de stock pour le produit et la taille achetés
                    foreach ($product->getStocks() as $stock) {
                        if ($stock->getSize() === $purchasedSize) {
                            $newQuantity = $stock->getQuantity() - $quantityPurchased;
                            // Style défensif : on s'assure que le stock ne devienne pas négatif
                            $stock->setQuantity(max(0, $newQuantity));
                            $this->entityManager->persist($stock);
                        }
                    }
                }

                // Sauvegarde de la mise à jour des stocks en BDD
                $this->entityManager->flush();

                // On vide le panier en session maintenant que l'achat est validé et les stocks ajustés
                $this->cartService->clear();
            }

            // Rendu de la page de confirmation de commande
            return $this->render('cart/success.html.twig');

        } catch (\Exception $e) {
            $this->addFlash('error', 'Une erreur est survenue lors de la validation du paiement : ' . $e->getMessage());
            return $this->redirectToRoute('app_cart');
        }
    }

    #[Route('/cart/cancel', name: 'app_cart_cancel')]
    public function cancel(): Response
    {
        // Rendu de la page d'annulation
        return $this->render('cart/cancel.html.twig');
    }
}
