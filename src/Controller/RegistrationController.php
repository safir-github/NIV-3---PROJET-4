<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Security\EmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

/**
 * Contrôleur d'inscription (RegistrationController)
 * 
 * Ce contrôleur gère :
 * - L'affichage et la soumission du formulaire d'inscription (`/register`).
 * - L'envoi d'un email de confirmation avec un lien signé temporaire.
 * - Le traitement de la validation de l'adresse email lorsque l'utilisateur clique sur le lien reçu.
 */
class RegistrationController extends AbstractController
{
    private EmailVerifier $emailVerifier;

    public function __construct(EmailVerifier $emailVerifier)
    {
        $this->emailVerifier = $emailVerifier;
    }

    #[Route('/register', name: 'app_register')]
    public function register(Request $request, UserPasswordHasherInterface $userPasswordHasher, Security $security, EntityManagerInterface $entityManager): Response
    {
        // Si l'utilisateur est déjà connecté, on le redirige vers l'accueil
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Récupération du mot de passe en clair saisi dans le formulaire
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();

            // Hachage du mot de passe en utilisant l'encodeur de sécurité
            $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));

            // Par défaut, l'utilisateur a le rôle client (ROLE_USER). 
            // On s'assure qu'il n'ait pas de rôle administrateur.
            $user->setRoles(['ROLE_USER']);

            // L'utilisateur n'est pas encore vérifié
            $user->setIsVerified(false);

            // Sauvegarde de l'utilisateur en base de données
            $entityManager->persist($user);
            $entityManager->flush();

            // Génération d'une URL sécurisée et signée et envoi de l'e-mail de confirmation
            $this->emailVerifier->sendEmailConfirmation('app_verify_email', $user,
                (new TemplatedEmail())
                    ->from(new Address('stubborn@blabla.com', 'Stubborn'))
                    ->to((string) $user->getEmail())
                    ->subject('Veuillez confirmer votre adresse email')
                    ->htmlTemplate('registration/confirmation_email.html.twig')
            );

            // Authentification automatique de l'utilisateur après soumission du formulaire
            return $security->login($user, 'form_login', 'main');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }

    #[Route('/verify/email', name: 'app_verify_email')]
    public function verifyUserEmail(Request $request, TranslatorInterface $translator): Response
    {
        // Pour confirmer l'e-mail, l'utilisateur doit être connecté
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        // Validation du lien de confirmation d'email.
        // Si le lien est valide, handleEmailConfirmation va passer User::isVerified à true et enregistrer en base.
        try {
            /** @var User $user */
            $user = $this->getUser();
            $this->emailVerifier->handleEmailConfirmation($request, $user);
        } catch (VerifyEmailExceptionInterface $exception) {
            // En cas d'erreur (ex: lien expiré ou altéré), on affiche un message flash d'erreur
            $this->addFlash('verify_email_error', $translator->trans($exception->getReason(), [], 'VerifyEmailBundle'));

            return $this->redirectToRoute('app_register');
        }

        // Message de succès affiché sur la page d'accueil après confirmation
        $this->addFlash('success', 'Votre adresse email a été vérifiée avec succès. Bienvenue chez Stubborn !');

        // Redirection finale vers la page d'accueil (/) requise par le brief
        return $this->redirectToRoute('app_home');
    }
}
