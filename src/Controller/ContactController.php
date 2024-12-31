<?php

namespace App\Controller;

use App\Form\ContactType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;


class ContactController extends AbstractController
{
    public function index(Request$request, MailerInterface $mailer): Response
    {
        $form = $this->createForm(ContactType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $contactFormData = $form->getData();

            $message = (new Email())
                ->to($_ENV['ADMIN_EMAIL'])
                ->subject('Message de contact Mileo')
                ->text(
                    'E-mail : ' . $contactFormData['email'] . \PHP_EOL . \PHP_EOL .
                    'Nom : ' . $contactFormData['name'] . \PHP_EOL . \PHP_EOL .
                    'Tél : ' . $contactFormData['phone'] . \PHP_EOL . \PHP_EOL .
                    'Entreprise : ' . $contactFormData['company'] . \PHP_EOL . \PHP_EOL .
                    'Message : ' . \PHP_EOL .
                        $contactFormData['message'],
                    'utf-8'
                );
            $mailer->send($message);
            $this->addFlash('success', 'Merci de nous avoir contacté! 
            Votre message a été envoyé, nous mettons tout en oeuvre pour y répondre dans les plus brefs délais.');
            return $this->redirectToRoute('contact');
            
        }
        
        return $this->render('Front/contact.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
