<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Associations;
use App\Entity\Facture;
use App\Form\FactureType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class FacturesController extends AbstractController
{
    /**
     * @Route("/factures", name="factures_index")
     */
    public function index(EntityManagerInterface $entityManager): Response
    {
        // Récupérer la liste des associations depuis la base de données
        $associations = $this->getDoctrine()->getRepository(Associations::class)->findAll();

        $notificationFacture = $this->checkNotification($entityManager);


        return $this->render('facturation/index.html.twig', [
            'associations' => $associations,
            'notificationFacture' => $notificationFacture,
        ]);
    }

    /**
     * @Route("/factures/deposer/{id}", name="deposer_facture")
     */
    public function deposerFacture(Request $request, Associations $association, EntityManagerInterface $entityManager, MailerInterface $mailer): Response
    {
        $form = $this->createForm(FactureType::class, null, [
            'is_deposer_action' => true
        ]);
        $form->handleRequest($request);
    
        if ($form->isSubmitted() && $form->isValid()) {
            // Récupérer les fichiers PDF
            $pdfFiles = $form->get('pdfContent')->getData();
    
            if ($pdfFiles) {
                foreach ($pdfFiles as $pdfFile) {
                    try {
                        // Créer une nouvelle facture pour chaque fichier
                        $facture = new Facture();
                        $facture->setAssociationId($association->getId());
                        $facture->setCreatedAt(new \DateTime());
                        $facture->setUpdatedAt(new \DateTime());
    
                        $facture->setNotification(true);
                        $facture->setNotificationEndDate((new \DateTime())->modify('+2 weeks'));
    
                        // Lire et enregistrer chaque fichier PDF
                        $pdfContent = file_get_contents($pdfFile->getPathname());
                        $facture->setPdfContent($pdfContent);
                        $facture->setPdfFilename($pdfFile->getClientOriginalName()); // Enregistrer le nom original du fichier
    
                        // Persist la facture pour chaque fichier
                        $entityManager->persist($facture);
    
                    } catch (\Exception $e) {
                        $this->addFlash('error', 'Une erreur est survenue lors du traitement du fichier PDF.');
                        return $this->redirectToRoute('deposer_facture', ['id' => $association->getId()]);
                    }
                }
    
                // Flush toutes les factures après avoir persisté chacune
                $entityManager->flush();
    
                // Envoyer des notifications par e-mail (si nécessaire)
                $this->sendEmailNotification($mailer, $association, $facture);
    
                $this->addFlash('success', 'Les factures ont été déposées avec succès.');
                return $this->redirectToRoute('factures_index');
            }
        }
    
        return $this->render('facturation/deposer_facture.html.twig', [
            'form' => $form->createView(),
            'association' => $association,
        ]);
    }
    
    

    private function sendEmailNotification(MailerInterface $mailer, Associations $association, Facture $facture)
    {
        $logoPath = '../public/uploads/94.png';

        $email = (new Email())
            ->from('no.reply.naturisme@gmail.com')
            ->to($association->getEmailPresident());

        if ($association->getEmailSecretaireGeneral()) {
            $email->addTo($association->getEmailSecretaireGeneral());
        }

        if ($association->getEmailTresorier()) {
            $email->addTo($association->getEmailTresorier());
        }
        $email->subject('Nouvelle facture FFN')
            ->html('<img src="cid:logo" alt="Logo FFN PRO"><br>
            Bonjour,<br>
            Une nouvelle facture a été déposée dans votre espace FFN PRO. <br><br>
            Cliquez <a href="https://ffnpro.net">ici</a> pour accéder à votre espace FFN. <br><br>
            Liliana<br>
            Secrétariat Fédération française de naturisme<br>
            26 Rue Paul Belmondo<br>
            75012 PARIS<br>
            01.48.10.31.00<br>
            contact@ffn-naturisme.com<br>
            www.ffn-naturisme.com');

        $email->embed(fopen($logoPath, 'r'), 'logo');


        $pdfContent = $facture->getPdfContent();
        $pdfFilename = $facture->getPdfFilename();

        if ($pdfContent && $pdfFilename) {
            $email->attach($pdfContent, $pdfFilename, 'application/pdf');
        } else {
            // Gérer le cas où le contenu PDF ou le nom de fichier est manquant
            throw new \Exception('PDF content or filename is missing.');
        }

        // Envoi de l'email
        $mailer->send($email);
    }



    /**
     * @Route("/factures/show/{associationId}", name="show_list_factures")
     */
    public function showAllFactures(EntityManagerInterface $entityManager, int $associationId): Response
    {
        $factures = $entityManager->getRepository(Facture::class)->findBy(['associationId' => $associationId]);

        return $this->render('facturation/show_list_factures.html.twig', [
            'factures' => $factures,
            'associationId' => $associationId
        ]);
    }

    /**
     * @Route("/factures/download/{id}", name="download_pdf")
     */
    public function downloadPdf(Facture $facture): Response
    {
        if ($facture->getPdfContent()) {
            $pdfContent = stream_get_contents($facture->getPdfContent());
            return new Response($pdfContent, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="facture_' . $facture->getId() . '.pdf"',
            ]);
        }

        $this->addFlash('error', 'Le fichier PDF n\'existe pas.');
        return $this->redirectToRoute('show_list_factures', ['associationId' => $facture->getAssociationId()]);
    }

    /**
     * @Route("/factures/edit/{id}", name="edit_facture")
     */
    public function editFacture(Request $request, Facture $facture, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(FactureType::class, $facture);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $facture->setUpdatedAt(new \DateTime());

            $entityManager->persist($facture);
            $entityManager->flush();

            $this->addFlash('success', 'Le nom de la facture a été modifié avec succès.');
            return $this->redirectToRoute('show_list_factures', ['associationId' => $facture->getAssociationId()]);
        }

        return $this->render('facturation/edit_facture.html.twig', [
            'form' => $form->createView(),
            'facture' => $facture,
        ]);
    }

    /**
     * @Route("/factures/delete/{id}", name="delete_facture", methods={"DELETE"})
     */
    public function deleteFacture(Request $request, Facture $facture, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $facture->getId(), $request->headers->get('X-CSRF-TOKEN'))) {
            $entityManager->remove($facture);
            $entityManager->flush();
            $this->addFlash('success', 'La facture a été supprimée avec succès.');
        } else {
            $this->addFlash('error', 'La validation CSRF a échoué.');
        }

        return $this->redirectToRoute('show_list_factures', ['associationId' => $facture->getAssociationId()]);
    }

    /**
     * @Route("/factures/association/{id}", name="factures_association")
     */
    public function facturesAssociation(Associations $association, EntityManagerInterface $entityManager): Response
    {
        $factures = $entityManager->getRepository(Facture::class)->findBy(['associationId' => $association->getId()]);

        return $this->render('facturation/factures_association.html.twig', [
            'factures' => $factures,
            'association' => $association,
        ]);
    }

    public function checkNotification(EntityManagerInterface $entityManager): ?Facture
{
    $userId = $this->getUser()->getId();
    $factureRepository = $entityManager->getRepository(Facture::class);

    $factures = $factureRepository->findBy(['associationId' => $userId, 'notification' => true]);

    foreach ($factures as $facture) {
        $notificationEndDate = $facture->getNotificationEndDate();
        if ($notificationEndDate && $notificationEndDate > new \DateTime()) {
            return $facture;
        }
    }

    return null;
}

}
