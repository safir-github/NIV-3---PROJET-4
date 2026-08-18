<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

/**
 * Service EmailVerifier
 * 
 * Cette classe encapsule la logique de génération de signature de lien sécurisé 
 * pour la vérification de l'email de l'utilisateur, l'envoi de l'email et 
 * l'activation finale du statut de vérification de l'utilisateur.
 */
class EmailVerifier
{
    private VerifyEmailHelperInterface $verifyEmailHelper;
    private MailerInterface $mailer;
    private EntityManagerInterface $entityManager;

    public function __construct(
        VerifyEmailHelperInterface $verifyEmailHelper,
        MailerInterface $mailer,
        EntityManagerInterface $entityManager
    ) {
        $this->verifyEmailHelper = $verifyEmailHelper;
        $this->mailer = $mailer;
        $this->entityManager = $entityManager;
    }

    /**
     * Génère le lien signé et envoie l'email de confirmation à l'utilisateur
     */
    public function sendEmailConfirmation(string $verifyEmailRouteName, User $user, TemplatedEmail $email): void
    {
        // Génération des composants de signature du lien d'activation sécurisé
        $signatureComponents = $this->verifyEmailHelper->generateSignature(
            $verifyEmailRouteName,
            (string) $user->getId(),
            (string) $user->getEmail()
        );

        $context = $email->getContext();
        $context['signedUrl'] = $signatureComponents->getSignedUrl();
        $context['expiresAtMessageKey'] = $signatureComponents->getExpirationMessageKey();
        $context['expiresAtMessageData'] = $signatureComponents->getExpirationMessageData();

        $email->context($context);

        // Envoi réel ou simulé de l'email
        $this->mailer->send($email);
    }

    /**
     * Valide le lien de confirmation envoyé à l'utilisateur, 
     * passe isVerified à true et met à jour l'utilisateur en base de données.
     * 
     * @throws VerifyEmailExceptionInterface
     */
    public function handleEmailConfirmation(Request $request, User $user): void
    {
        // Valide la signature du lien depuis la requête reçue
        $this->verifyEmailHelper->validateEmailConfirmationFromRequest($request, (string) $user->getId(), (string) $user->getEmail());

        // Passage du statut de vérification à true
        $user->setIsVerified(true);

        // Sauvegarde de la modification en BDD
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }
}
