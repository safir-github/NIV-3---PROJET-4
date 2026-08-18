<?php

namespace App\Service;

use Stripe\StripeClient;

/**
 * Service StripeService (Gestion des paiements en ligne)
 * 
 * Cette classe encapsule la logique d'interaction avec l'API Stripe
 * pour créer des sessions de paiement Checkout sécurisées.
 */
class StripeService
{
    private string $stripeSecretKey;
    private StripeClient $stripe;

    public function __construct(string $stripeSecretKey)
    {
        $this->stripeSecretKey = $stripeSecretKey;
        // Initialisation du client Stripe SDK avec la clé secrète
        $this->stripe = new StripeClient($this->stripeSecretKey);
    }

    /**
     * Crée une session Stripe Checkout pour rediriger l'utilisateur vers la page de paiement.
     * 
     * @param array $cartItems Le panier détaillé contenant les produits, tailles et quantités
     * @param string $successUrl L'URL absolue de redirection en cas de succès
     * @param string $cancelUrl L'URL absolue de redirection en cas d'annulation
     * @return string L'URL de la session Stripe Checkout créée
     */
    public function createCheckoutSession(array $cartItems, string $successUrl, string $cancelUrl): string
    {
        $lineItems = [];

        // Conversion des articles de notre panier au format attendu par Stripe
        foreach ($cartItems as $item) {
            $lineItems[] = [
                'price_data' => [
                    'currency' => 'eur',
                    'product_data' => [
                        'name' => sprintf('%s (Taille : %s)', $item['product']->getName(), $item['size']),
                    ],
                    // Stripe attend le montant unitaire en centimes d'euros (ex: 29.90 € -> 2990)
                    'unit_amount' => (int) round($item['product']->getPrice() * 100),
                ],
                'quantity' => $item['quantity'],
            ];
        }

        // Création de la session Checkout
        $session = $this->stripe->checkout->sessions->create([
            'payment_method_types' => ['card'],
            'line_items' => $lineItems,
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ]);

        // Retourne l'URL vers laquelle rediriger l'utilisateur pour le paiement
        return $session->url;
    }

    /**
     * Récupère une session Stripe Checkout depuis l'API de Stripe pour vérification.
     */
    public function retrieveSession(string $sessionId)
    {
        return $this->stripe->checkout->sessions->retrieve($sessionId);
    }
}
